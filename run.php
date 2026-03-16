<?php

/**
 * AutoBlogger — Main pipeline entry point.
 * Usage: php run.php
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
// Fetch articles
// -------------------------------------------------------------------------

$articles = $rss->fetch((int) ($cfg['general']['posts_per_run'] ?? 3));

if (empty($articles)) {
    Logger::info('No new articles found.');
    echo "No new articles found.\n";
    exit(0);
}

echo "Found " . count($articles) . " article(s). Processing...\n";

// -------------------------------------------------------------------------
// Pipeline
// -------------------------------------------------------------------------

$processed = 0;

foreach ($articles as $article) {
    try {
        $lang = $article['language'] ?? ($cfg['general']['language'] ?? 'English');

        echo "Processing: {$article['title']}\n";
        Logger::info("Processing: {$article['title']}");

        // Single AI call — returns title, content, excerpt + all SEO meta
        AiService::$currentArticleTitle = $article['title'];
        $aiResult           = $ai->generateAll($article['title'], $article['content'], $lang);
        $article['title']   = $aiResult['title'];
        $article['content'] = $aiResult['content'];
        $article['excerpt'] = $aiResult['excerpt'];
        $seoMeta            = [
            'meta_title'       => $aiResult['meta_title'],
            'meta_description' => $aiResult['meta_description'],
            'focus_keyword'    => $aiResult['focus_keyword'],
            'tags'             => $aiResult['tags'],
        ];

        // Featured image — prefer RSS feed image, fall back to Pixabay
        $feedImage    = $article['image'] ?? '';
        $imageKeyword = $seoMeta['focus_keyword'];
        if ($imageKeyword === '') {
            $words        = explode(' ', strip_tags($article['title']));
            $imageKeyword = implode(' ', array_slice($words, 0, 3));
        }
        $imagePath = $img->process($imageKeyword, $article['title'], $lang, $feedImage);

        // Publish
        $publishedAny = false;
        $publishErrors = [];

        if ($wp !== null) {
            $wpOk = $wp->publish($article, $imagePath, $seoMeta);
            if ($wpOk) {
                $publishedAny = true;
                Logger::info("WordPress: published successfully — {$article['title']}");
            } else {
                $publishErrors[] = 'WordPress publish failed (check error log)';
                Logger::error("WordPress: publish failed — {$article['title']}");
            }
        }

        if ($db !== null) {
            $dbOk = $db->publish($article, $imagePath, $seoMeta);
            if ($dbOk) {
                $publishedAny = true;
                Logger::info("CustomSite: published successfully — {$article['title']}");
            } else {
                $publishErrors[] = 'Custom site DB publish failed (check error log)';
                Logger::error("CustomSite: publish failed — {$article['title']}");
            }
        }

        if (!$publishedAny) {
            // Nothing published — do NOT mark as posted so it can be retried
            $errMsg = implode('; ', $publishErrors) ?: 'No publisher enabled or all failed';
            throw new RuntimeException($errMsg);
        }

        // Mark as posted only after at least one successful publish
        Logger::markPosted($article['url']);
        Logger::info("Published: {$article['title']}");
        echo "  Done: {$article['title']}\n";

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
    }
}

// -------------------------------------------------------------------------
// Summary
// -------------------------------------------------------------------------

echo "\nDone. Processed: {$processed} article(s).\n";
