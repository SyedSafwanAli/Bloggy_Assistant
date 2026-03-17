<?php

/**
 * JobQueue — PDO-backed article processing queue.
 *
 * Config key required in config.php:
 *   'queue_db' => ['host' => 'localhost', 'name' => 'Bloggy_Assistant', 'user' => 'root', 'pass' => '']
 */
class JobQueue
{
    private PDO $pdo;

    public function __construct(array $config)
    {
        $db = $config['queue_db'];

        $dsn = "mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4";

        $this->pdo = new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    /**
     * Insert a new pending job. Skips silently if article_url already exists
     * with status pending or processing (prevents duplicates on re-run).
     */
    public function addJob(string $feedUrl, string $articleUrl): int
    {
        // Skip if already queued / in progress
        $check = $this->pdo->prepare(
            "SELECT id FROM jobs
             WHERE article_url = ?
               AND status IN ('pending','processing')
             LIMIT 1"
        );
        $check->execute([$articleUrl]);
        if ($check->fetch()) {
            return 0;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO jobs (feed_url, article_url, status, attempts)
             VALUES (?, ?, 'pending', 0)"
        );
        $stmt->execute([$feedUrl, $articleUrl]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Fetch up to $limit pending jobs, ordered oldest-first.
     *
     * @return array<int, array{id:int, feed_url:string, article_url:string, attempts:int}>
     */
    public function getPendingJobs(int $limit = 10): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, feed_url, article_url, attempts
             FROM jobs
             WHERE status = 'pending'
             ORDER BY created_at ASC
             LIMIT ?"
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Mark a job as processing and increment attempt counter.
     */
    public function markProcessing(int $id): void
    {
        $this->pdo->prepare(
            "UPDATE jobs
             SET status = 'processing', attempts = attempts + 1
             WHERE id = ?"
        )->execute([$id]);
    }

    /**
     * Mark a job as completed.
     */
    public function markCompleted(int $id): void
    {
        $this->pdo->prepare(
            "UPDATE jobs SET status = 'completed' WHERE id = ?"
        )->execute([$id]);
    }

    /**
     * Mark a job as failed and record the error message.
     */
    public function markFailed(int $id, string $error): void
    {
        $this->pdo->prepare(
            "UPDATE jobs
             SET status = 'failed', last_error = ?
             WHERE id = ?"
        )->execute([$error, $id]);
    }

    /**
     * Save a fully processed article to the articles table.
     *
     * @param array $article  Processed article array (title, content, excerpt, …)
     * @param array $seoMeta  focus_keyword, tags, …
     * @param string $imagePath  Saved image filename (basename only)
     */
    public function saveArticle(array $article, array $seoMeta, string $imagePath): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO articles
                 (title, content, excerpt, focus_keyword, tags,
                  source_url, language, image, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->execute([
            $article['title']          ?? '',
            $article['content']        ?? '',
            $article['excerpt']        ?? '',
            $seoMeta['focus_keyword']  ?? '',
            json_encode($seoMeta['tags'] ?? []),
            $article['url']            ?? '',
            $article['language']       ?? 'English',
            $imagePath !== '' ? basename($imagePath) : '',
            'published',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Count jobs by status.
     *
     * @return array{pending:int, processing:int, completed:int, failed:int}
     */
    public function getStats(): array
    {
        $stmt = $this->pdo->query(
            "SELECT status, COUNT(*) AS cnt FROM jobs GROUP BY status"
        );

        $stats = ['pending' => 0, 'processing' => 0, 'completed' => 0, 'failed' => 0];
        foreach ($stmt->fetchAll() as $row) {
            $stats[$row['status']] = (int) $row['cnt'];
        }

        return $stats;
    }
}
