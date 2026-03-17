<?php

/**
 * AutoBlogger — Main pipeline entry point.
 * Usage: php run.php
 *
 * Flow:
 *   1. Fetch new articles from RSS feeds
 *   2. Insert each as a pending job in the DB queue
 *   3. Process pending jobs (AI → Image → Publish)
 */

define('BASE_PATH', __DIR__);

$cfg = require_once BASE_PATH . '/config/config.php';

require_once BASE_PATH . '/src/Logger.php';
require_once BASE_PATH . '/src/RssFetcher.php';
require_once BASE_PATH . '/src/AiService.php';
require_once BASE_PATH . '/src/ImageService.php';
require_once BASE_PATH . '/src/WordPressPublisher.php';
require_once BASE_PATH . '/src/CustomSitePublisher.php';
require_once BASE_PATH . '/src/Mailer.php';

// -------------------------------------------------------------------------
// Boot
// -------------------------------------------------------------------------

Logger::init($cfg['general']);

// -------------------------------------------------------------------------
// Pause check
// -------------------------------------------------------------------------

if (!empty($cfg['general']['pause'])) {
    echo "AutoBlogger is paused.\n";
    exit(0);
}

// -------------------------------------------------------------------------
// Instantiate services
// -------------------------------------------------------------------------

$rss    = new RssFetcher($cfg);
$ai     = new AiService($cfg);
$img    = new ImageService($cfg);
$mailer = new Mailer($cfg);
$wp     = !empty($cfg['wordpress']['enabled'])   ? new WordPressPublisher($cfg)  : null;
$db     = !empty($cfg['custom_site']['enabled']) ? new CustomSitePublisher($cfg) : null;

// -------------------------------------------------------------------------
// Job Queue — optional (only if queue_db is configured)
// -------------------------------------------------------------------------

$queue = null;
if (!empty($cfg['queue_db']['name'])) {
    require_once BASE_PATH . '/src/JobQueue.php';
    try {
        $queue = new JobQueue($cfg);
    } catch (Exception $e) {
        Logger::error('JobQueue DB connect failed: ' . $e->getMessage());
        // Non-fatal — fall through to direct processing
    }
}

// -------------------------------------------------------------------------
// Fetch articles from RSS
// -------------------------------------------------------------------------

$postsPerRun = (int) ($cfg['general']['posts_per_run'] ?? 3);
$articles    = $rss->fetch($postsPerRun);

if (empty($articles)) {
    Logger::info('No new articles found.');
    echo "No new articles found.\n";
    exit(0);
}

echo "Found " . count($articles) . " article(s).\n";

// -------------------------------------------------------------------------
// STEP 1 — Enqueue jobs (if queue is available)
// -------------------------------------------------------------------------

if ($queue !== null) {
    $enqueued = 0;
    foreach ($articles as $article) {
        $feedUrl    = $article['feed_url'] ?? '';
        $articleUrl = $article['url']      ?? '';

        if ($articleUrl === '') {
            continue;
        }

        $jobId = $queue->addJob($feedUrl, $articleUrl);
        if ($jobId > 0) {
            $enqueued++;
            Logger::info("Queued: {$article['title']} [job #{$jobId}]");
        }
    }

    echo "Enqueued: {$enqueued} job(s).\n";

    // Reload pending jobs from DB so we process in queue order
    $pendingJobs = $queue->getPendingJobs($postsPerRun);

    // Map article_url → article data for quick lookup
    $articleMap = [];
    foreach ($articles as $art) {
        $articleMap[$art['url']] = $art;
    }
} else {
    // No queue — build a fake job list from fetched articles directly
    $pendingJobs = [];
    foreach ($articles as $art) {
        $pendingJobs[] = [
            'id'          => 0,
            'feed_url'    => $art['feed_url'] ?? '',
            'article_url' => $art['url'],
            'attempts'    => 0,
        ];
    }
    $articleMap = [];
    foreach ($articles as $art) {
        $articleMap[$art['url']] = $art;
    }
}

// -------------------------------------------------------------------------
// STEP 2 — Process jobs
// -------------------------------------------------------------------------

echo "Processing " . count($pendingJobs) . " job(s)...\n";

$processed = 0;

foreach ($pendingJobs as $job) {
    $jobId      = (int) $job['id'];
    $articleUrl = $job['article_url'];

    // Resolve article data
    $article = $articleMap[$articleUrl] ?? null;

    if ($article === null) {
        // Job was queued in a previous run — article data not in memory, skip for now
        Logger::info("Job #{$jobId}: article data not in current batch, skipping.");
        continue;
    }

    if ($queue !== null && $jobId > 0) {
        $queue->markProcessing($jobId);
    }

    try {
        $lang = $article['language'] ?? ($cfg['general']['language'] ?? 'English');

        echo "Processing: {$article['title']}\n";
        Logger::info("Processing: {$article['title']}");

        // ------------------------------------------------------------------
        // AI — single call returning all fields
        // ------------------------------------------------------------------
        AiService::$currentArticleTitle = $article['title'];
        $aiResult           = $ai->generateAll($article['title'], $article['content'], $lang);
        $article['title']   = $aiResult['title'];
        $article['content'] = $aiResult['content'];
        $article['excerpt'] = $aiResult['excerpt'];
        $seoMeta = [
            'meta_title'       => $aiResult['meta_title'],
            'meta_description' => $aiResult['meta_description'],
            'focus_keyword'    => $aiResult['focus_keyword'],
            'tags'             => $aiResult['tags'],
        ];

        // ------------------------------------------------------------------
        // Image — feed image first, Pixabay progressive fallback
        // ------------------------------------------------------------------
        $feedImage    = $article['image'] ?? '';
        $imageKeyword = $seoMeta['focus_keyword'];
        if ($imageKeyword === '') {
            $words        = explode(' ', strip_tags($article['title']));
            $imageKeyword = implode(' ', array_slice($words, 0, 3));
        }
        $imagePath = $img->process($imageKeyword, $article['title'], $lang, $feedImage);

        // ------------------------------------------------------------------
        // Publish
        // ------------------------------------------------------------------
        $publishedAny  = false;
        $publishErrors = [];

        if ($wp !== null) {
            $wpOk = $wp->publish($article, $imagePath, $seoMeta);
            if ($wpOk) {
                $publishedAny = true;
                Logger::info("WordPress: published — {$article['title']}");
            } else {
                $publishErrors[] = 'WordPress publish failed';
                Logger::error("WordPress: publish failed — {$article['title']}");
            }
        }

        if ($db !== null) {
            $dbOk = $db->publish($article, $imagePath, $seoMeta);
            if ($dbOk) {
                $publishedAny = true;
                Logger::info("CustomSite: published — {$article['title']}");
            } else {
                $publishErrors[] = 'Custom site DB publish failed';
                Logger::error("CustomSite: publish failed — {$article['title']}");
            }
        }

        if (!$publishedAny) {
            $errMsg = implode('; ', $publishErrors) ?: 'No publisher enabled or all failed';
            throw new RuntimeException($errMsg);
        }

        // ------------------------------------------------------------------
        // Save to articles table (queue DB)
        // ------------------------------------------------------------------
        if ($queue !== null) {
            $queue->saveArticle($article, $seoMeta, $imagePath);
        }

        // Mark posted + complete job
        Logger::markPosted($article['url']);
        Logger::info("Published: {$article['title']}");
        echo "  Done: {$article['title']}\n";

        if ($queue !== null && $jobId > 0) {
            $queue->markCompleted($jobId);
        }

        // Success notification
        $stats   = Logger::getStats();
        $siteUrl = !empty($article['wp_site'])
            ? $article['wp_site']
            : ($cfg['wordpress']['site_url'] ?? '');
        $mailer->sendSuccess($article['title'], $siteUrl, $stats['today_posted']);

        $processed++;
        sleep(2);

    } catch (Exception $e) {
        Logger::error($e->getMessage());
        $mailer->sendError($e->getMessage(), $article['title'] ?? '');
        echo "  Error: {$e->getMessage()}\n";

        if ($queue !== null && $jobId > 0) {
            $queue->markFailed($jobId, $e->getMessage());
        }
    }
}

// -------------------------------------------------------------------------
// Summary
// -------------------------------------------------------------------------

echo "\nDone. Processed: {$processed} article(s).\n";

if ($queue !== null) {
    $qStats = $queue->getStats();
    echo "Queue — pending: {$qStats['pending']}, completed: {$qStats['completed']}, failed: {$qStats['failed']}\n";
}
