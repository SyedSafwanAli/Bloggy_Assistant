<?php

class Logger
{
    private static array $cfg = [];

    // -------------------------------------------------------------------------
    // Initialisation
    // -------------------------------------------------------------------------

    public static function init(array $cfg): void
    {
        self::$cfg = $cfg;

        $dirs = [
            dirname($cfg['activity_log']),
            dirname($cfg['error_log']),
            dirname($cfg['posted_log']),
        ];

        foreach (array_unique($dirs) as $dir) {
            if ($dir !== '' && !is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Core log writers
    // -------------------------------------------------------------------------

    public static function info(string $message): void
    {
        $line = '[' . date('Y-m-d H:i:s') . '] [INFO] ' . $message . "\n";
        file_put_contents(self::$cfg['activity_log'], $line, FILE_APPEND | LOCK_EX);
    }

    public static function error(string $message): void
    {
        $line = '[' . date('Y-m-d H:i:s') . '] [ERROR] ' . $message . "\n";
        file_put_contents(self::$cfg['error_log'], $line, FILE_APPEND | LOCK_EX);
    }

    // -------------------------------------------------------------------------
    // Posted URL tracking
    // -------------------------------------------------------------------------

    public static function markPosted(string $url): void
    {
        file_put_contents(self::$cfg['posted_log'], rtrim($url) . "\n", FILE_APPEND | LOCK_EX);
    }

    public static function isPosted(string $url): bool
    {
        $path = self::$cfg['posted_log'];
        if (!file_exists($path)) {
            return false;
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return false;
        }

        $target = trim($url);
        while (($line = fgets($handle)) !== false) {
            if (trim($line) === $target) {
                fclose($handle);
                return true;
            }
        }

        fclose($handle);
        return false;
    }

    public static function clearPosted(): void
    {
        file_put_contents(self::$cfg['posted_log'], '', LOCK_EX);
    }

    public static function clearActivityLog(): void
    {
        file_put_contents(self::$cfg['activity_log'], '', LOCK_EX);
    }

    public static function clearErrorLog(): void
    {
        file_put_contents(self::$cfg['error_log'], '', LOCK_EX);
    }

    public static function clearTokenLog(): void
    {
        $path = self::tokenLogPath();
        if (file_exists($path)) {
            file_put_contents($path, '', LOCK_EX);
        }
    }

    // -------------------------------------------------------------------------
    // Log readers
    // -------------------------------------------------------------------------

    public static function getActivityLog(int $lines = 50): array
    {
        return self::readLastLines(self::$cfg['activity_log'], $lines);
    }

    public static function getErrorLog(int $lines = 50): array
    {
        return self::readLastLines(self::$cfg['error_log'], $lines);
    }

    // -------------------------------------------------------------------------
    // Stats
    // -------------------------------------------------------------------------

    public static function getPostedCount(): int
    {
        $path = self::$cfg['posted_log'];
        if (!file_exists($path)) {
            return 0;
        }

        $count  = 0;
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return 0;
        }

        while (($line = fgets($handle)) !== false) {
            if (trim($line) !== '') {
                $count++;
            }
        }

        fclose($handle);
        return $count;
    }

    public static function getStats(): array
    {
        $today = date('Y-m-d');

        return [
            'total_posted' => self::getPostedCount(),
            'today_posted' => self::countTodayLines(self::$cfg['activity_log'], $today, '[INFO]'),
            'error_count'  => self::countLines(self::$cfg['error_log']),
        ];
    }

    // -------------------------------------------------------------------------
    // Token tracking
    // -------------------------------------------------------------------------

    public static function logTokens(
        int    $promptTokens,
        int    $completionTokens,
        string $model,
        string $provider,
        string $articleTitle = ''
    ): void {
        $path  = self::tokenLogPath();
        $entry = json_encode([
            'date'             => date('Y-m-d'),
            'time'             => date('H:i:s'),
            'provider'         => $provider,
            'model'            => $model,
            'prompt_tokens'    => $promptTokens,
            'completion_tokens'=> $completionTokens,
            'total_tokens'     => $promptTokens + $completionTokens,
            'article'          => mb_substr($articleTitle, 0, 80),
        ]) . "\n";
        file_put_contents($path, $entry, FILE_APPEND | LOCK_EX);
    }

    public static function getTokenStats(): array
    {
        $path = self::tokenLogPath();

        $result = [
            'today_tokens'  => 0,
            'total_tokens'  => 0,
            'cost_today'    => 0.0,
            'cost_total'    => 0.0,
            'by_day'        => [],
            'last_model'    => '—',
            'last_provider' => '—',
            'calls_today'   => 0,
            'calls_total'   => 0,
        ];

        if (!file_exists($path)) {
            return $result;
        }

        $today  = date('Y-m-d');
        $handle = fopen($path, 'r');
        if (!$handle) {
            return $result;
        }

        while (($line = fgets($handle)) !== false) {
            $d = json_decode(trim($line), true);
            if (!is_array($d)) {
                continue;
            }

            $tokens  = (int)  ($d['total_tokens']      ?? 0);
            $prompt  = (int)  ($d['prompt_tokens']      ?? 0);
            $compl   = (int)  ($d['completion_tokens']  ?? 0);
            $model   = (string)($d['model']             ?? '');
            $prov    = (string)($d['provider']          ?? '');
            $day     = (string)($d['date']              ?? '');
            $cost    = self::estimateCost($prompt, $compl, $model, $prov);

            $result['total_tokens'] += $tokens;
            $result['cost_total']   += $cost;
            $result['calls_total']  += 1;
            $result['last_model']    = $model ?: $result['last_model'];
            $result['last_provider'] = $prov  ?: $result['last_provider'];

            if ($day === $today) {
                $result['today_tokens'] += $tokens;
                $result['cost_today']   += $cost;
                $result['calls_today']  += 1;
            }

            if ($day !== '') {
                $result['by_day'][$day] = ($result['by_day'][$day] ?? 0) + $tokens;
            }
        }

        fclose($handle);

        // Keep last 7 days sorted ascending for chart
        krsort($result['by_day']);
        $result['by_day'] = array_slice($result['by_day'], 0, 7, true);
        ksort($result['by_day']);

        $result['cost_today'] = round($result['cost_today'], 4);
        $result['cost_total'] = round($result['cost_total'], 4);

        return $result;
    }

    private static function tokenLogPath(): string
    {
        $logDir = self::$cfg['tokens_log']
            ?? (dirname(self::$cfg['activity_log'] ?? sys_get_temp_dir()) . '/tokens.log');
        return $logDir;
    }

    private static function estimateCost(int $promptTokens, int $completionTokens, string $model, string $provider): float
    {
        // [input $/1M, output $/1M]
        $rates = [
            'gpt-4o-mini'          => [0.15,   0.60],
            'gpt-4o'               => [2.50,  10.00],
            'gpt-4-turbo'          => [10.00, 30.00],
            'gpt-4'                => [30.00, 60.00],
            'gpt-3.5'              => [0.50,   1.50],
            'gemini-2.5-flash'     => [0.075,  0.30],
            'gemini-2.5-pro'       => [1.25,   5.00],
            'gemini-2.0-flash'     => [0.075,  0.30],
            'gemini-1.5-flash'     => [0.075,  0.30],
            'gemini-1.5-pro'       => [1.25,   5.00],
        ];

        $rate = [2.50, 10.00]; // default gpt-4o
        foreach ($rates as $pattern => [$in, $out]) {
            if (stripos($model, $pattern) !== false) {
                $rate = [$in, $out];
                break;
            }
        }

        return ($promptTokens * $rate[0] + $completionTokens * $rate[1]) / 1_000_000;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Read the last N lines of a file and return them newest-first.
     */
    private static function readLastLines(string $path, int $n): array
    {
        if (!file_exists($path) || $n <= 0) {
            return [];
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        // Collect all lines; memory-acceptable for typical log sizes.
        $all = [];
        while (($line = fgets($handle)) !== false) {
            $trimmed = rtrim($line);
            if ($trimmed !== '') {
                $all[] = $trimmed;
            }
        }
        fclose($handle);

        $slice = array_slice($all, -$n);
        return array_reverse($slice);
    }

    /**
     * Count non-empty lines in a file.
     */
    private static function countLines(string $path): int
    {
        if (!file_exists($path)) {
            return 0;
        }

        $count  = 0;
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return 0;
        }

        while (($line = fgets($handle)) !== false) {
            if (trim($line) !== '') {
                $count++;
            }
        }

        fclose($handle);
        return $count;
    }

    /**
     * Count lines in a file that contain both a given date and a level token.
     * e.g. countTodayLines($path, '2025-03-15', '[INFO]')
     */
    private static function countTodayLines(string $path, string $date, string $level): int
    {
        if (!file_exists($path)) {
            return 0;
        }

        $count  = 0;
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return 0;
        }

        while (($line = fgets($handle)) !== false) {
            if (strpos($line, $date) !== false && strpos($line, $level) !== false) {
                $count++;
            }
        }

        fclose($handle);
        return $count;
    }
}
