<?php

require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/AiService.php';

class ImageService
{
    private array $pixabay;
    private array $image;

    public function __construct(array $cfg)
    {
        $this->pixabay = $cfg['pixabay'] ?? [];
        $this->image   = $cfg['image']   ?? [];

        $saveDir = $this->image['save_dir'] ?? '';
        if ($saveDir !== '' && !is_dir($saveDir)) {
            mkdir($saveDir, 0755, true);
        }
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Fetch an image for $keyword, process it, save it, and return the file path.
     * If $directUrl is provided, it is used first; Pixabay is only tried as fallback.
     * Returns empty string on any failure.
     */
    public function process(string $keyword, string $title, string $language = '', string $directUrl = ''): string
    {
        try {
            // Use direct URL (e.g. from RSS feed) first; fall back to Pixabay
            if ($directUrl !== '') {
                $imageUrl = $directUrl;
                Logger::info('ImageService: using feed image — ' . $directUrl);
            } else {
                $imageUrl = $this->fetchFromPixabay($keyword);
            }

            if ($imageUrl === '') {
                Logger::error('ImageService: no image found for keyword "' . $keyword . '"');
                return '';
            }

            $data = $this->downloadImage($imageUrl);

            // If feed image download failed, try Pixabay as fallback
            if ($data === '' && $directUrl !== '') {
                Logger::info('ImageService: feed image failed, falling back to Pixabay');
                $pbUrl = $this->fetchFromPixabay($keyword);
                if ($pbUrl !== '') {
                    $data = $this->downloadImage($pbUrl);
                }
            }

            if ($data === '') {
                Logger::error('ImageService: could not download any image for "' . $keyword . '"');
                return '';
            }

            $gd = @imagecreatefromstring($data);

            if ($gd === false) {
                Logger::error('ImageService: could not create GD resource from downloaded image');
                return '';
            }

            // Pipeline
            $gd = $this->cropResize($gd);
            $gd = $this->adjustBrightnessContrast($gd);

            if (!empty($this->image['overlay_title'])) {
                $gd = $this->overlayTitle($gd, $title, $language);
            }

            $gd = $this->addWatermark($gd);

            // Save
            $filename  = uniqid('img_', true) . '.jpg';
            $saveDir   = rtrim($this->image['save_dir'] ?? sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR;
            $filepath  = $saveDir . $filename;

            imagejpeg($gd, $filepath, 90);
            imagedestroy($gd);

            return $filepath;

        } catch (Exception $e) {
            Logger::error('ImageService: ' . $e->getMessage());
            return '';
        }
    }

    // -------------------------------------------------------------------------
    // Pixabay
    // -------------------------------------------------------------------------

    /**
     * Search Pixabay with progressive keyword fallback.
     * Tries full keyword → 3 words → 2 words → 1 word → 'news'.
     */
    private function fetchFromPixabay(string $keyword): string
    {
        $words     = preg_split('/\s+/', trim(strip_tags($keyword)));
        $candidates = array_filter(array_unique([
            implode(' ', array_slice($words, 0, 5)),
            implode(' ', array_slice($words, 0, 3)),
            implode(' ', array_slice($words, 0, 2)),
            $words[0] ?? '',
            'news',
        ]));

        foreach ($candidates as $q) {
            $url = 'https://pixabay.com/api/?' . http_build_query([
                'key'         => $this->pixabay['api_key']     ?? '',
                'q'           => $q,
                'image_type'  => $this->pixabay['image_type']  ?? 'photo',
                'orientation' => $this->pixabay['orientation'] ?? 'horizontal',
                'min_width'   => $this->pixabay['min_width']   ?? 1280,
                'per_page'    => $this->pixabay['per_page']    ?? 5,
                'safesearch'  => 'true',
            ]);

            $context  = stream_context_create(['http' => ['timeout' => 15, 'user_agent' => 'AutoBlogger/1.0']]);
            $response = @file_get_contents($url, false, $context);

            if ($response === false) {
                continue;
            }

            $hits = json_decode($response, true)['hits'] ?? [];

            if (!empty($hits)) {
                $pick = $hits[array_rand($hits)];
                Logger::info('ImageService: Pixabay match for "' . $q . '"');
                return $pick['largeImageURL'] ?? ($pick['webformatURL'] ?? '');
            }

            Logger::info('ImageService: no Pixabay results for "' . $q . '" — trying shorter keyword');
        }

        return '';
    }

    // -------------------------------------------------------------------------
    // Download
    // -------------------------------------------------------------------------

    /**
     * Download a remote image and return its raw binary content.
     *
     * @throws RuntimeException on cURL failure
     */
    private function downloadImage(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_USERAGENT      => 'AutoBlogger/1.0',
        ]);

        $data    = curl_exec($ch);
        $curlErr = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($data === false || $httpCode >= 400) {
            Logger::error('ImageService: download failed (HTTP ' . $httpCode . '): ' . $curlErr . ' — ' . $url);
            return '';
        }

        return $data;
    }

    // -------------------------------------------------------------------------
    // Pipeline steps
    // -------------------------------------------------------------------------

    /**
     * Smart center-crop then resize to target dimensions.
     * Destroys $src and returns a new GdImage.
     */
    private function cropResize(GdImage $src): GdImage
    {
        $srcW = imagesx($src);
        $srcH = imagesy($src);

        $targetW = (int) ($this->image['output_width']  ?? 1200);
        $targetH = (int) ($this->image['output_height'] ?? 628);

        $targetRatio = $targetW / $targetH;
        $srcRatio    = $srcW   / $srcH;

        if ($srcRatio > $targetRatio) {
            // Source is wider than target — crop horizontally
            $cropH = $srcH;
            $cropW = (int) round($srcH * $targetRatio);
            $cropX = (int) round(($srcW - $cropW) / 2);
            $cropY = 0;
        } else {
            // Source is taller than target — crop vertically
            $cropW = $srcW;
            $cropH = (int) round($srcW / $targetRatio);
            $cropX = 0;
            $cropY = (int) round(($srcH - $cropH) / 2);
        }

        $dst = imagecreatetruecolor($targetW, $targetH);

        imagecopyresampled($dst, $src, 0, 0, $cropX, $cropY, $targetW, $targetH, $cropW, $cropH);
        imagedestroy($src);

        return $dst;
    }

    /**
     * Apply brightness and contrast filters.
     * GD contrast is inverted: positive value = lower contrast.
     */
    private function adjustBrightnessContrast(GdImage $img): GdImage
    {
        $brightness = (float) ($this->image['brightness'] ?? 0);
        $contrast   = (float) ($this->image['contrast']   ?? 0);

        imagefilter($img, IMG_FILTER_BRIGHTNESS, (int) ($brightness * 2.55));
        imagefilter($img, IMG_FILTER_CONTRAST,   -(int) $contrast);

        return $img;
    }

    /**
     * Burn the post title onto a semi-transparent gradient bar at the bottom.
     */
    private function overlayTitle(GdImage $img, string $title, string $language): GdImage
    {
        // Choose font based on language direction
        $fontPath = in_array($language, AiService::$rtlLanguages)
            ? ($this->image['rtl_font_path'] ?? '')
            : ($this->image['font_path']     ?? '');

        if ($fontPath === '' || !file_exists($fontPath)) {
            // Cannot render text without a valid font — skip silently
            return $img;
        }

        $w    = imagesx($img);
        $h    = imagesy($img);
        $barH = (int) round($h * 0.32);
        $barY = $h - $barH;

        // Draw gradient bar row by row (dark → transparent going upward)
        for ($y = $barY; $y < $h; $y++) {
            $progress = ($y - $barY) / $barH;               // 0.0 at top of bar, 1.0 at bottom
            $alpha    = (int) round(127 * (1 - $progress * 0.85)); // more opaque at bottom
            $color    = imagecolorallocatealpha($img, 0, 0, 0, $alpha);
            imagefilledrectangle($img, 0, $y, $w - 1, $y, $color);
        }

        // Font size relative to image width
        $fontSize = (int) ($w / 25);
        if ($fontSize < 10) {
            $fontSize = 10;
        }

        // Word-wrap and split into lines
        $wrapped = wordwrap($title, 45, "\n", true);
        $lines   = explode("\n", $wrapped);

        // Estimate total text block height
        $bbox        = imagettfbbox($fontSize, 0, $fontPath, 'Ag');
        $lineH       = abs($bbox[7] - $bbox[1]);
        $lineSpacing = (int) ($lineH * 1.4);
        $totalTextH  = count($lines) * $lineSpacing;

        // Vertically center the text block inside the gradient bar
        $textBlockY = $barY + (int) (($barH - $totalTextH) / 2) + $lineH;

        foreach ($lines as $i => $line) {
            $lineY = $textBlockY + $i * $lineSpacing;

            // Measure line width for horizontal centering
            $lb    = imagettfbbox($fontSize, 0, $fontPath, $line);
            $lineW = abs($lb[4] - $lb[0]);
            $lineX = (int) (($w - $lineW) / 2);

            // Shadow (black, semi-transparent)
            $shadow = imagecolorallocatealpha($img, 0, 0, 0, 60);
            imagettftext($img, $fontSize, 0, $lineX + 2, $lineY + 2, $shadow, $fontPath, $line);

            // White text
            $white = imagecolorallocate($img, 255, 255, 255);
            imagettftext($img, $fontSize, 0, $lineX, $lineY, $white, $fontPath, $line);
        }

        return $img;
    }

    /**
     * Stamp a semi-transparent watermark text at the configured position.
     */
    private function addWatermark(GdImage $img): GdImage
    {
        $text     = $this->image['watermark_text']     ?? '';
        $position = $this->image['watermark_position'] ?? 'bottom-right';
        $opacity  = (int) ($this->image['watermark_opacity'] ?? 60);
        $fontPath = $this->image['font_path'] ?? '';

        if ($text === '' || $fontPath === '' || !file_exists($fontPath)) {
            return $img;
        }

        $fontSize = 14;
        $padding  = 16;

        $w = imagesx($img);
        $h = imagesy($img);

        $bbox    = imagettfbbox($fontSize, 0, $fontPath, $text);
        $textW   = abs($bbox[4] - $bbox[0]);
        $textH   = abs($bbox[7] - $bbox[1]);

        // Calculate position
        switch ($position) {
            case 'top-left':
                $x = $padding;
                $y = $padding + $textH;
                break;
            case 'top-right':
                $x = $w - $textW - $padding;
                $y = $padding + $textH;
                break;
            case 'bottom-left':
                $x = $padding;
                $y = $h - $padding;
                break;
            case 'center':
                $x = (int) (($w - $textW) / 2);
                $y = (int) (($h + $textH) / 2);
                break;
            case 'bottom-right':
            default:
                $x = $w - $textW - $padding;
                $y = $h - $padding;
                break;
        }

        // Convert opacity (0–100) to GD alpha (0 = opaque, 127 = transparent)
        $alpha = (int) round(127 * (1 - $opacity / 100));
        $color = imagecolorallocatealpha($img, 255, 255, 255, $alpha);

        imagettftext($img, $fontSize, 0, $x, $y, $color, $fontPath, $text);

        return $img;
    }
}
