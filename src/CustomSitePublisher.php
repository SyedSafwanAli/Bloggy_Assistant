<?php

/*
 * Required database schema:
 *
 * CREATE TABLE blogs (
 *   id               INT AUTO_INCREMENT PRIMARY KEY,
 *   title            VARCHAR(255)  NOT NULL,
 *   content          LONGTEXT,
 *   excerpt          TEXT,
 *   meta_title       VARCHAR(255),
 *   meta_description VARCHAR(255),
 *   focus_keyword    VARCHAR(100),
 *   tags             JSON,
 *   source_url       VARCHAR(500),
 *   source           VARCHAR(100),
 *   language         VARCHAR(50),
 *   image            VARCHAR(255),
 *   status           VARCHAR(20)   DEFAULT 'published',
 *   created_at       DATETIME      DEFAULT CURRENT_TIMESTAMP
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 */

require_once __DIR__ . '/Logger.php';

class CustomSitePublisher
{
    private PDO    $pdo;
    private string $table;
    private string $uploadsDir;
    private bool   $draftMode;

    public function __construct(array $cfg)
    {
        $cs = $cfg['custom_site'] ?? [];

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            $cs['db_host'] ?? 'localhost',
            $cs['db_name'] ?? ''
        );

        $this->pdo = new PDO($dsn, $cs['db_user'] ?? '', $cs['db_pass'] ?? '', [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
        ]);

        $this->table      = $cs['table']       ?? 'blogs';
        $this->uploadsDir = rtrim($cs['uploads_dir'] ?? '', '/\\') . DIRECTORY_SEPARATOR;
        $this->draftMode  = (bool) ($cfg['general']['draft_mode'] ?? false);
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Publish one article to the custom database table.
     * Copies the featured image into uploads_dir if provided.
     * Returns true on success, false on failure (error is logged).
     */
    public function publish(array $article, string $imagePath, array $seoMeta): bool
    {
        try {
            // Step 1 — Copy image to uploads directory
            $imageFilename = '';
            if ($imagePath !== '' && file_exists($imagePath)) {
                $dest = $this->uploadsDir . basename($imagePath);
                if (@copy($imagePath, $dest)) {
                    $imageFilename = basename($imagePath);
                } else {
                    Logger::error('CustomSitePublisher: could not copy image to uploads dir: ' . $dest);
                }
            }

            // Step 2 — Resolve status
            $status = $this->draftMode ? 'draft' : 'published';

            // Step 3 — Insert record
            $sql = "INSERT INTO `{$this->table}`
                        (title, content, excerpt, meta_title, meta_description,
                         focus_keyword, tags, source_url, source, language,
                         image, status, created_at)
                    VALUES
                        (:title, :content, :excerpt, :meta_title, :meta_description,
                         :focus_keyword, :tags, :source_url, :source, :language,
                         :image, :status, NOW())";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':title'            => $article['title']             ?? '',
                ':content'          => $article['content']           ?? '',
                ':excerpt'          => $article['excerpt']           ?? '',
                ':meta_title'       => $seoMeta['meta_title']        ?? '',
                ':meta_description' => $seoMeta['meta_description']  ?? '',
                ':focus_keyword'    => $seoMeta['focus_keyword']     ?? '',
                ':tags'             => json_encode($seoMeta['tags']  ?? []),
                ':source_url'       => $article['url']               ?? '',
                ':source'           => $article['source']            ?? '',
                ':language'         => $article['language']          ?? '',
                ':image'            => $imageFilename,
                ':status'           => $status,
            ]);

            Logger::info(
                'CustomSitePublisher: inserted post "' . ($article['title'] ?? '') . '"'
                . ' [status: ' . $status . ']'
            );

            return true;

        } catch (PDOException $e) {
            Logger::error('CustomSitePublisher: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Return all posts with status = 'draft', newest first.
     */
    public function getPendingDrafts(): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM `{$this->table}` WHERE status = 'draft' ORDER BY created_at DESC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Set a post's status to 'published'. Returns true on success.
     */
    public function approvePost(int $id): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE `{$this->table}` SET status = 'published' WHERE id = ?"
            );
            $stmt->execute([$id]);
            return true;
        } catch (PDOException $e) {
            Logger::error('CustomSitePublisher::approvePost: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Permanently delete a post by ID. Returns true on success.
     */
    public function rejectPost(int $id): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "DELETE FROM `{$this->table}` WHERE id = ?"
            );
            $stmt->execute([$id]);
            return true;
        } catch (PDOException $e) {
            Logger::error('CustomSitePublisher::rejectPost: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Return the most recent $limit posts (summary columns only).
     */
    public function getRecentPosts(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, title, source, language, image, status, created_at
             FROM `{$this->table}`
             ORDER BY created_at DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
