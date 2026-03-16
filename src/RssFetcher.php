<?php

require_once __DIR__ . '/Logger.php';

class RssFetcher
{
    private array $feeds;
    private string $postedLog;
    private bool   $duplicateCheck;

    public function __construct(array $cfg)
    {
        $this->feeds          = $cfg['rss_feeds']          ?? [];
        $this->postedLog      = $cfg['general']['posted_log'] ?? '';
        $this->duplicateCheck = $cfg['general']['duplicate_check'] ?? true;
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Fetch up to $limit unposted articles from all enabled feeds.
     *
     * @return array<int, array{title:string, content:string, url:string, image:string,
     *                          source:string, language:string, wp_site:string}>
     */
    public function fetch(int $limit = 5): array
    {
        $collected = [];

        foreach ($this->feeds as $feed) {
            if (empty($feed['enabled'])) {
                continue;
            }

            try {
                $articles = $this->parseFeed($feed);
            } catch (Exception $e) {
                Logger::error('RssFetcher: failed to parse feed "' . ($feed['name'] ?? $feed['url']) . '": ' . $e->getMessage());
                continue;
            }

            foreach ($articles as $article) {
                if ($this->duplicateCheck && Logger::isPosted($article['url'])) {
                    continue;
                }

                $collected[] = $article;

                if (count($collected) >= $limit) {
                    return $collected;
                }
            }
        }

        return $collected;
    }

    // -------------------------------------------------------------------------
    // Feed parsing
    // -------------------------------------------------------------------------

    /**
     * Download and parse a single RSS or Atom feed.
     */
    private function parseFeed(array $feed): array
    {
        $url      = $feed['url'];
        $language = $feed['language'] ?? 'English';
        $wpSite   = $feed['wp_site']  ?? '';
        $source   = $feed['name']     ?? $url;

        $context = stream_context_create([
            'http' => [
                'timeout'    => 15,
                'user_agent' => 'AutoBlogger/1.0',
                'header'     => 'Accept: application/rss+xml, application/atom+xml, application/xml, text/xml, */*',
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        // Capture response headers for language detection
        $responseHeaders = [];
        $raw = @file_get_contents($url, false, $context);

        if ($raw === false) {
            throw new Exception('Could not download feed: ' . $url);
        }

        // $http_response_header is populated by file_get_contents
        if (!empty($http_response_header)) {
            $responseHeaders = $http_response_header;
        }

        $xml = @simplexml_load_string($raw, 'SimpleXMLElement', LIBXML_NOCDATA);

        if ($xml === false) {
            throw new Exception('Could not parse XML from: ' . $url);
        }

        // Resolve language
        if ($language === 'auto') {
            $language = $this->detectLanguage($xml, $responseHeaders);
        }

        // Detect feed format and extract items
        $items = [];

        if (isset($xml->channel->item)) {
            // RSS 2.0
            $items = $this->parseRss($xml, $source, $language, $wpSite);
        } elseif (isset($xml->entry)) {
            // Atom
            $items = $this->parseAtom($xml, $source, $language, $wpSite);
        } else {
            // Try RSS with namespace prefix (e.g. RDF/RSS 1.0)
            $xml->registerXPathNamespace('rss', 'http://purl.org/rss/1.0/');
            $rdfItems = $xml->xpath('//rss:item');
            if (!empty($rdfItems)) {
                foreach ($rdfItems as $item) {
                    $items[] = $this->buildArticle($item, $source, $language, $wpSite, 'rss');
                }
            }
        }

        return $items;
    }

    /**
     * Parse RSS 2.0 channel items.
     */
    private function parseRss(SimpleXMLElement $xml, string $source, string $language, string $wpSite): array
    {
        $items = [];

        foreach ($xml->channel->item as $item) {
            $items[] = $this->buildArticle($item, $source, $language, $wpSite, 'rss');
        }

        return $items;
    }

    /**
     * Parse Atom feed entries.
     */
    private function parseAtom(SimpleXMLElement $xml, string $source, string $language, string $wpSite): array
    {
        $items = [];

        foreach ($xml->entry as $entry) {
            $items[] = $this->buildArticle($entry, $source, $language, $wpSite, 'atom');
        }

        return $items;
    }

    /**
     * Build a normalised article array from an RSS item or Atom entry.
     *
     * @param string $format 'rss' or 'atom'
     */
    private function buildArticle(SimpleXMLElement $entry, string $source, string $language, string $wpSite, string $format): array
    {
        // ---- Title ----------------------------------------------------------
        $title = trim((string) $entry->title);

        // ---- URL ------------------------------------------------------------
        if ($format === 'atom') {
            $url = '';
            // Prefer <link rel="alternate" href="...">
            foreach ($entry->link as $link) {
                $rel = (string) ($link['rel'] ?? 'alternate');
                if ($rel === 'alternate') {
                    $url = (string) $link['href'];
                    break;
                }
            }
            // Fall back to <id>
            if ($url === '') {
                $url = trim((string) $entry->id);
            }
        } else {
            $url = trim((string) $entry->link);
            // Some RSS feeds use <guid isPermaLink="true"> as the URL
            if ($url === '' && isset($entry->guid)) {
                $isPermaLink = strtolower((string) ($entry->guid['isPermaLink'] ?? 'true'));
                if ($isPermaLink !== 'false') {
                    $url = trim((string) $entry->guid);
                }
            }
        }

        // ---- Raw content / description ------------------------------------
        if ($format === 'atom') {
            // Atom: prefer <content>, fall back to <summary>
            $namespaces = $entry->getNamespaces(true);
            $raw = '';
            if (isset($namespaces['content'])) {
                $contentNs = $entry->children($namespaces['content']);
                $raw = (string) ($contentNs->encoded ?? $contentNs->content ?? '');
            }
            if ($raw === '') {
                $raw = (string) ($entry->content ?? $entry->summary ?? '');
            }
        } else {
            // RSS: prefer <content:encoded>, fall back to <description>
            $namespaces = $entry->getNamespaces(true);
            $raw = '';
            if (isset($namespaces['content'])) {
                $contentNs = $entry->children($namespaces['content']);
                $raw = (string) ($contentNs->encoded ?? '');
            }
            if ($raw === '') {
                $raw = (string) ($entry->description ?? '');
            }
        }

        $content = $this->cleanContent($raw);

        // ---- Image ----------------------------------------------------------
        $image = $this->extractImage($entry, $raw);

        // If RSS has no image, try scraping og:image from the article page
        if ($image === '' && $url !== '') {
            $image = $this->fetchOgImage($url);
        }

        return [
            'title'          => $title,
            'original_title' => $title,
            'content'        => $content,
            'url'            => $url,
            'image'          => $image,
            'source'         => $source,
            'language'       => $language,
            'wp_site'        => $wpSite,
        ];
    }

    // -------------------------------------------------------------------------
    // Image extraction
    // -------------------------------------------------------------------------

    /**
     * Try multiple strategies to find a representative image for the entry.
     */
    private function extractImage(SimpleXMLElement $entry, string $desc): string
    {
        $namespaces = $entry->getNamespaces(true);

        // 1. media:content url attribute
        if (isset($namespaces['media'])) {
            $media = $entry->children($namespaces['media']);

            if (isset($media->content) && !empty($media->content['url'])) {
                return (string) $media->content['url'];
            }

            // 2. media:thumbnail url attribute
            if (isset($media->thumbnail) && !empty($media->thumbnail['url'])) {
                return (string) $media->thumbnail['url'];
            }

            // 3. media:group containing media:content or media:thumbnail
            if (isset($media->group)) {
                $group = $media->group->children($namespaces['media']);
                if (isset($group->content['url'])) {
                    return (string) $group->content['url'];
                }
                if (isset($group->thumbnail['url'])) {
                    return (string) $group->thumbnail['url'];
                }
            }
        }

        // 4. RSS <enclosure> with an image MIME type
        if (isset($entry->enclosure)) {
            $type = strtolower((string) ($entry->enclosure['type'] ?? ''));
            if (strpos($type, 'image/') === 0) {
                return (string) $entry->enclosure['url'];
            }
        }

        // 5. First <img src="..."> in the description HTML
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $desc, $matches)) {
            return $matches[1];
        }

        return '';
    }

    // -------------------------------------------------------------------------
    // OG image scraper
    // -------------------------------------------------------------------------

    /**
     * Fetch the og:image meta tag from an article URL.
     * Returns empty string on failure.
     */
    private function fetchOgImage(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'timeout'    => 10,
                'user_agent' => 'AutoBlogger/1.0',
                'method'     => 'GET',
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);

        $html = @file_get_contents($url, false, $context);
        if ($html === false) {
            return '';
        }

        // Match <meta property="og:image" content="..."> (any attribute order)
        if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html, $m)) {
            return $m[1];
        }
        if (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\'][^>]*>/i', $html, $m)) {
            return $m[1];
        }

        return '';
    }

    // -------------------------------------------------------------------------
    // Content cleaning
    // -------------------------------------------------------------------------

    /**
     * Remove script/style blocks and trim whitespace from HTML content.
     */
    private function cleanContent(string $html): string
    {
        // Remove <script>...</script> blocks (including multi-line)
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);

        // Remove <style>...</style> blocks
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);

        return trim($html);
    }

    // -------------------------------------------------------------------------
    // Language detection
    // -------------------------------------------------------------------------

    /**
     * Detect language from the feed's xml:lang attribute or HTTP Content-Language header.
     * Falls back to 'English'.
     */
    private function detectLanguage(SimpleXMLElement $xml, array $headers): string
    {
        // Map of common BCP-47 tags / ISO 639-1 codes to display names
        static $langMap = [
            'en'  => 'English',
            'ur'  => 'Urdu',
            'ar'  => 'Arabic',
            'hi'  => 'Hindi',
            'fr'  => 'French',
        ];

        // 1. Check xml:lang on the root element (standard placement)
        $xmlAttrs = $xml->attributes('xml', true);
        $xmlLang  = strtolower(trim((string) ($xmlAttrs['lang'] ?? '')));

        if ($xmlLang === '') {
            // Also try plain lang= attribute
            $xmlLang = strtolower(trim((string) ($xml['lang'] ?? '')));
        }

        if ($xmlLang !== '') {
            $code = strtolower(substr($xmlLang, 0, 2));
            return $langMap[$code] ?? ucfirst($xmlLang);
        }

        // 2. Check <language> element inside <channel> (RSS 2.0)
        if (isset($xml->channel->language)) {
            $code = strtolower(substr(trim((string) $xml->channel->language), 0, 2));
            if ($code !== '' && isset($langMap[$code])) {
                return $langMap[$code];
            }
        }

        // 3. Check HTTP Content-Language response header
        foreach ($headers as $header) {
            if (stripos($header, 'Content-Language:') === 0) {
                $value = trim(substr($header, strlen('Content-Language:')));
                $code  = strtolower(substr($value, 0, 2));
                if (isset($langMap[$code])) {
                    return $langMap[$code];
                }
            }
        }

        return 'English';
    }
}
