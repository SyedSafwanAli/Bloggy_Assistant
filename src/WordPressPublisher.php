<?php

require_once __DIR__ . '/Logger.php';

class WordPressPublisher
{
    private array  $wp;
    private bool   $draftMode;
    private string $authHeader;

    public function __construct(array $cfg)
    {
        $this->wp        = $cfg['wordpress']            ?? [];
        $this->draftMode = (bool) ($cfg['general']['draft_mode'] ?? false);

        // Pre-build the Basic auth header (reused on every request)
        $credentials      = ($this->wp['username'] ?? '') . ':' . ($this->wp['app_password'] ?? '');
        $this->authHeader = 'Basic ' . base64_encode($credentials);
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Publish one article to WordPress.
     * Returns true on success, false on any failure (error is logged).
     */
    public function publish(array $article, string $imagePath, array $seoMeta): bool
    {
        try {
            // Resolve target site and post status
            $siteUrl = !empty($article['wp_site'])
                ? rtrim($article['wp_site'], '/')
                : rtrim($this->wp['site_url'] ?? '', '/');

            $status = $this->draftMode ? 'draft' : ($this->wp['status'] ?? 'publish');

            // Step 1 — Upload featured image
            $mediaId = $this->uploadImage($siteUrl, $imagePath);

            // Step 2 — Resolve / create tags
            $tagIds = $this->resolveTagIds($siteUrl, $seoMeta['tags'] ?? []);

            // Step 3 — Auto-match or create category from article topic
            $categoryIds = $this->resolveCategories($siteUrl, $seoMeta);

            // Step 4 — Create the post
            $this->createPost($siteUrl, $article, $seoMeta, $status, $mediaId, $tagIds, $categoryIds);

            return true;

        } catch (Exception $e) {
            Logger::error('WordPressPublisher: ' . $e->getMessage());
            return false;
        }
    }

    // -------------------------------------------------------------------------
    // Step 1 — Image upload
    // -------------------------------------------------------------------------

    /**
     * Upload $imagePath as a WP media attachment and return its media ID.
     * Returns 0 if no image is provided or upload fails.
     */
    private function uploadImage(string $siteUrl, string $imagePath): int
    {
        if ($imagePath === '' || !file_exists($imagePath)) {
            return 0;
        }

        $endpoint = $siteUrl . '/wp-json/wp/v2/media';
        $binary   = file_get_contents($imagePath);

        if ($binary === false) {
            Logger::error('WordPressPublisher: could not read image file: ' . $imagePath);
            return 0;
        }

        $extraHeaders = [
            'Content-Disposition: attachment; filename="' . basename($imagePath) . '"',
            'Content-Type: image/jpeg',
        ];

        $response = $this->request($endpoint, 'POST', $binary, $extraHeaders);

        return (int) ($response['id'] ?? 0);
    }

    // -------------------------------------------------------------------------
    // Step 2 — Tag resolution
    // -------------------------------------------------------------------------

    /**
     * For each tag name, find its existing WP term ID or create a new term.
     *
     * @param  string[] $tags
     * @return int[]
     */
    private function resolveTagIds(string $siteUrl, array $tags): array
    {
        $tagIds = [];

        foreach ($tags as $tag) {
            $tag = trim($tag);
            if ($tag === '') {
                continue;
            }

            try {
                $tagIds[] = $this->findOrCreateTag($siteUrl, $tag);
            } catch (Exception $e) {
                // Non-fatal: skip this tag and continue
                Logger::error('WordPressPublisher: could not resolve tag "' . $tag . '": ' . $e->getMessage());
            }
        }

        return $tagIds;
    }

    /**
     * Search for an existing tag; create it if not found. Returns tag ID.
     */
    private function findOrCreateTag(string $siteUrl, string $tag): int
    {
        // Search for existing tag
        $searchUrl = $siteUrl . '/wp-json/wp/v2/tags?search=' . urlencode($tag);
        $results   = $this->request($searchUrl, 'GET');

        if (!empty($results) && is_array($results)) {
            foreach ($results as $result) {
                // Match by exact name (case-insensitive)
                if (isset($result['name']) && strcasecmp($result['name'], $tag) === 0) {
                    return (int) $result['id'];
                }
            }
        }

        // Tag not found — create it
        $createUrl = $siteUrl . '/wp-json/wp/v2/tags';
        $response  = $this->request($createUrl, 'POST', json_encode(['name' => $tag]));

        if (empty($response['id'])) {
            throw new RuntimeException('WP API returned no ID when creating tag "' . $tag . '"');
        }

        return (int) $response['id'];
    }

    // -------------------------------------------------------------------------
    // Step 3 — Category resolution / auto-create
    // -------------------------------------------------------------------------

    /**
     * Match the best existing WP category using word-overlap scoring.
     * Never creates a new category — if nothing matches well enough, returns [1] (Uncategorized).
     * Falls back to [1] on any failure.
     */
    private function resolveCategories(string $siteUrl, array $seoMeta): array
    {
        // Build candidate words: focus_keyword + all SEO tags combined
        $allText = strtolower(trim(($seoMeta['focus_keyword'] ?? '') . ' ' . implode(' ', $seoMeta['tags'] ?? [])));
        $candidateWords = array_filter(array_unique(preg_split('/\W+/', $allText)));

        if (empty($candidateWords)) {
            return [1];
        }

        try {
            // Fetch all existing WP categories (up to 100)
            $listUrl    = $siteUrl . '/wp-json/wp/v2/categories?per_page=100';
            $categories = $this->request($listUrl, 'GET');

            $bestId    = 1;
            $bestScore = 0;
            $bestName  = 'Uncategorized';

            foreach ($categories as $cat) {
                if (empty($cat['name']) || (int) ($cat['id'] ?? 0) === 1) {
                    continue;
                }

                $catWords = array_filter(preg_split('/\W+/', strtolower($cat['name'])));
                $overlap  = count(array_intersect($candidateWords, $catWords));

                if ($overlap > $bestScore) {
                    $bestScore = $overlap;
                    $bestId    = (int) $cat['id'];
                    $bestName  = $cat['name'];
                }
            }

            if ($bestScore > 0) {
                Logger::info('WordPressPublisher: matched category "' . $bestName . '" (ID ' . $bestId . ', score ' . $bestScore . ')');
                return [$bestId];
            }

        } catch (Exception $e) {
            Logger::error('WordPressPublisher: category resolution failed — ' . $e->getMessage());
        }

        return [1]; // fallback to Uncategorized
    }

    // -------------------------------------------------------------------------
    // Step 4 — Post creation
    // -------------------------------------------------------------------------

    private function createPost(
        string $siteUrl,
        array  $article,
        array  $seoMeta,
        string $status,
        int    $mediaId,
        array  $tagIds,
        array  $categoryIds = [1]
    ): void {
        $endpoint = $siteUrl . '/wp-json/wp/v2/posts';

        $body = json_encode([
            'title'          => $article['title']   ?? '',
            'content'        => $article['content']  ?? '',
            'excerpt'        => $article['excerpt']  ?? '',
            'status'         => $status,
            'categories'     => $categoryIds,
            'author'         => (int) ($this->wp['author_id'] ?? 1),
            'featured_media' => $mediaId,
            'tags'           => $tagIds,
            'meta'           => [
                '_yoast_wpseo_title'    => $seoMeta['meta_title']       ?? '',
                '_yoast_wpseo_metadesc' => $seoMeta['meta_description'] ?? '',
                '_yoast_wpseo_focuskw'  => $seoMeta['focus_keyword']    ?? '',
            ],
        ]);

        $response = $this->request($endpoint, 'POST', $body);

        if (empty($response['id'])) {
            throw new RuntimeException('WP API returned no post ID after creation');
        }

        Logger::info(
            'WordPressPublisher: post created — ID ' . $response['id']
            . ', status "' . $status . '"'
            . ', site: ' . $siteUrl
        );
    }

    // -------------------------------------------------------------------------
    // cURL wrapper
    // -------------------------------------------------------------------------

    /**
     * Execute an authenticated HTTP request against the WP REST API.
     *
     * $body may be:
     *   - null          → no body (GET)
     *   - string (JSON) → sent with Content-Type: application/json
     *   - string (binary) → caller must pass correct Content-Type via $extraHeaders
     *
     * @throws RuntimeException on cURL error or HTTP 4xx/5xx response
     */
    private function request(string $url, string $method, $body = null, array $extraHeaders = []): array
    {
        // Base headers — Content-Type is overridden by $extraHeaders when uploading binary
        $headers = [
            'Authorization: ' . $this->authHeader,
            'Content-Type: application/json',
        ];

        // Extra headers (e.g. Content-Disposition, binary Content-Type) override defaults
        foreach ($extraHeaders as $extra) {
            $headerName = strtolower(strstr($extra, ':', true));
            // Remove any existing header with the same name
            $headers = array_filter($headers, static function (string $h) use ($headerName): bool {
                return strtolower(strstr($h, ':', true)) !== $headerName;
            });
            $headers[] = $extra;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_HTTPHEADER     => array_values($headers),
            CURLOPT_TIMEOUT        => 30,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        // Capture HTTP status code
        curl_setopt($ch, CURLOPT_HEADER, false);

        $response   = curl_exec($ch);
        $curlErr    = curl_error($ch);
        $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('WP API cURL error: ' . $curlErr);
        }

        if ($httpStatus >= 400) {
            // Include first 300 chars of response body for diagnostic context
            $excerpt = mb_substr($response, 0, 300);
            throw new RuntimeException("WP API error {$httpStatus}: {$excerpt}");
        }

        $decoded = json_decode($response, true);

        // Some endpoints return an array of items; others return a single object.
        // Always return an array — wrap single object if needed.
        if (is_array($decoded)) {
            return $decoded;
        }

        return [];
    }
}
