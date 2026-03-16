<?php

/**
 * AutoBlogger Dashboard — AJAX JSON API
 * All responses: { success: bool, data: mixed, message: string }
 */

define('BASE_PATH', realpath(__DIR__ . '/..'));

$cfg = require_once BASE_PATH . '/config/config.php';

require_once BASE_PATH . '/src/Logger.php';
require_once BASE_PATH . '/src/RssFetcher.php';
require_once BASE_PATH . '/src/AiService.php';
require_once BASE_PATH . '/src/ImageService.php';
require_once BASE_PATH . '/src/WordPressPublisher.php';
require_once BASE_PATH . '/src/CustomSitePublisher.php';
require_once BASE_PATH . '/src/Mailer.php';

Logger::init($cfg['general']);

header('Content-Type: application/json');

// -------------------------------------------------------------------------
// Helper
// -------------------------------------------------------------------------

function respond(bool $success, $data = null, string $message = ''): never
{
    echo json_encode(['success' => $success, 'data' => $data, 'message' => $message]);
    exit;
}

// -------------------------------------------------------------------------
// Input — merge query string, POST body, and raw JSON body
// -------------------------------------------------------------------------

$body    = json_decode(file_get_contents('php://input'), true) ?? [];
$_POST   = array_merge($_POST, $body);
$action  = $_POST['action'] ?? $_GET['action'] ?? '';

// -------------------------------------------------------------------------
// Lazy-load DB publisher only when needed
// -------------------------------------------------------------------------

function getDb(array $cfg): ?CustomSitePublisher
{
    if (!empty($cfg['custom_site']['enabled'])) {
        return new CustomSitePublisher($cfg);
    }
    return null;
}

// -------------------------------------------------------------------------
// Config persistence helpers
// -------------------------------------------------------------------------

/**
 * Load the live config file and return the array.
 * Using require would hit the opcode cache, so we eval() a fresh parse.
 */
function loadConfig(): array
{
    $raw = file_get_contents(BASE_PATH . '/config/config.php');
    // Strip <?php and eval the return statement
    $code = preg_replace('/^<\?php\s*/i', '', $raw);
    return eval($code);
}

/**
 * Serialise $config back to config/config.php using var_export.
 */
function saveConfig(array $config): void
{
    $export = "<?php\n\nreturn " . var_export($config, true) . ";\n";
    file_put_contents(BASE_PATH . '/config/config.php', $export);
}

// -------------------------------------------------------------------------
// cURL helper for internal connection tests
// -------------------------------------------------------------------------

/**
 * Perform a cURL GET with optional Basic auth and return [httpCode, decodedBody].
 */
function curlGet(string $url, array $headers = [], int $timeout = 10): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_USERAGENT      => 'AutoBlogger/1.0',
    ]);
    $response = curl_exec($ch);
    $code     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('cURL error: ' . $err);
    }

    return [$code, json_decode($response, true)];
}

// -------------------------------------------------------------------------
// Router
// -------------------------------------------------------------------------

try {
    switch ($action) {

        // =================================================================
        // SETTINGS
        // =================================================================

        case 'save_settings':
            if (empty($body) || !is_array($body)) {
                respond(false, null, 'No config data received');
            }
            // Remove the action key if it leaked into the body
            unset($body['action']);
            saveConfig($body);
            respond(true, null, 'Settings saved');

        case 'save_feeds':
            if (!isset($body['rss_feeds']) || !is_array($body['rss_feeds'])) {
                respond(false, null, 'rss_feeds array missing');
            }
            $config = loadConfig();
            $config['rss_feeds'] = $body['rss_feeds'];
            saveConfig($config);
            respond(true, null, 'Feeds saved');

        // =================================================================
        // PIPELINE
        // =================================================================

        case 'run_now':
            $cmd = 'php ' . escapeshellarg(BASE_PATH . '/run.php') . ' > /dev/null 2>&1 &';
            shell_exec($cmd);
            respond(true, null, 'Pipeline started in background');

        case 'pause_toggle':
            $config  = loadConfig();
            $current = !empty($config['general']['pause']);
            $config['general']['pause'] = !$current;
            saveConfig($config);
            respond(true, ['paused' => !$current]);

        // =================================================================
        // STATS & LOGS
        // =================================================================

        case 'get_stats':
            $stats = Logger::getStats();
            $stats['active_feeds'] = count(array_filter(
                $cfg['rss_feeds'] ?? [],
                fn($f) => !empty($f['enabled'])
            ));
            $stats['next_run_ts'] = time() + ((int) ($cfg['general']['interval_hours'] ?? 6) * 3600);
            respond(true, $stats);

        case 'get_logs':
            respond(true, [
                'activity' => Logger::getActivityLog(100),
                'errors'   => Logger::getErrorLog(50),
            ]);

        case 'clear_posted_log':
            Logger::clearPosted();
            respond(true, null, 'Posted log cleared');

        case 'clear_activity_log':
            Logger::clearActivityLog();
            respond(true, null, 'Activity log cleared');

        case 'clear_error_log':
            Logger::clearErrorLog();
            respond(true, null, 'Error log cleared');

        case 'clear_token_log':
            Logger::clearTokenLog();
            respond(true, null, 'Token log cleared');

        case 'clear_all_logs':
            Logger::clearActivityLog();
            Logger::clearErrorLog();
            Logger::clearPosted();
            Logger::clearTokenLog();
            respond(true, null, 'All logs cleared');

        case 'get_token_stats':
            respond(true, Logger::getTokenStats());

        case 'get_system_health':
            $logDir   = BASE_PATH . '/logs';
            $imgDir   = BASE_PATH . '/images';
            $freeBytes = disk_free_space($logDir) ?: 0;
            $totalBytes= disk_total_space($logDir) ?: 1;

            $exts = ['curl', 'simplexml', 'gd', 'pdo_mysql', 'mbstring', 'json'];
            $extStatus = [];
            foreach ($exts as $ext) {
                $extStatus[$ext] = extension_loaded($ext);
            }

            $logFiles = [];
            foreach (['activity.log','error.log','tokens.log','posted.log'] as $f) {
                $fp = $logDir . '/' . $f;
                $logFiles[$f] = file_exists($fp) ? round(filesize($fp) / 1024, 1) . ' KB' : '—';
            }

            $imageCount = is_dir($imgDir) ? count(glob($imgDir . '/*.jpg') ?: []) : 0;

            respond(true, [
                'php_version'   => PHP_VERSION,
                'extensions'    => $extStatus,
                'disk_free_gb'  => round($freeBytes / 1073741824, 2),
                'disk_used_pct' => round((1 - $freeBytes / $totalBytes) * 100, 1),
                'log_files'     => $logFiles,
                'image_count'   => $imageCount,
                'memory_limit'  => ini_get('memory_limit'),
                'max_exec_time' => ini_get('max_execution_time'),
            ]);

        // =================================================================
        // CONNECTION TESTS
        // =================================================================

        case 'test_wp_connection':
            $url  = rtrim(trim($_POST['site_url']    ?? ''), '/');
            $user = trim($_POST['username']           ?? '');
            $pass = trim($_POST['app_password']       ?? '');

            if ($url === '' || $user === '' || $pass === '') {
                respond(false, null, 'site_url, username, and app_password are required');
            }

            $auth = 'Basic ' . base64_encode($user . ':' . $pass);
            [$code, $decoded] = curlGet($url . '/wp-json/wp/v2/users/me', [
                'Authorization: ' . $auth,
            ]);

            if ($code === 200 && isset($decoded['name'])) {
                respond(true, ['name' => $decoded['name']]);
            }
            respond(false, null, 'Connection failed: HTTP ' . $code);

        case 'get_wp_categories':
            $url  = rtrim(trim($_POST['site_url']    ?? $cfg['wordpress']['site_url'] ?? ''), '/');
            $user = trim($_POST['username']           ?? $cfg['wordpress']['username'] ?? '');
            $pass = trim($_POST['app_password']       ?? $cfg['wordpress']['app_password'] ?? '');

            $auth = 'Basic ' . base64_encode($user . ':' . $pass);
            [$code, $decoded] = curlGet(
                $url . '/wp-json/wp/v2/categories?per_page=100',
                ['Authorization: ' . $auth]
            );

            if ($code !== 200 || !is_array($decoded)) {
                respond(false, null, 'Could not fetch categories: HTTP ' . $code);
            }

            $categories = array_map(
                fn($c) => ['id' => $c['id'], 'name' => $c['name']],
                $decoded
            );
            respond(true, $categories);

        case 'test_ai_connection':
            $provider = $_POST['provider'] ?? ($cfg['ai']['provider'] ?? 'openai');

            if ($provider === 'openai') {
                $key = trim($_POST['openai_api_key'] ?? $cfg['ai']['openai_api_key'] ?? '');
                [$code, $decoded] = curlGet(
                    'https://api.openai.com/v1/models',
                    ['Authorization: Bearer ' . $key],
                    15
                );
                if ($code !== 200 || empty($decoded['data'])) {
                    respond(false, null, 'OpenAI connection failed: HTTP ' . $code);
                }
                $models = array_values(array_slice(
                    array_filter(
                        array_column($decoded['data'], 'id'),
                        fn($id) => str_contains($id, 'gpt')
                    ),
                    0, 10
                ));
                respond(true, ['models' => $models]);
            }

            if ($provider === 'gemini') {
                $key = trim($_POST['gemini_api_key'] ?? $cfg['ai']['gemini_api_key'] ?? '');
                [$code, $decoded] = curlGet(
                    'https://generativelanguage.googleapis.com/v1beta/models?key=' . urlencode($key),
                    [],
                    15
                );
                if ($code !== 200 || empty($decoded['models'])) {
                    respond(false, null, 'Gemini connection failed: HTTP ' . $code);
                }
                $models = array_column($decoded['models'], 'name');
                respond(true, ['models' => $models]);
            }

            respond(false, null, 'Unknown provider: ' . $provider);

        case 'test_pixabay':
            $key = trim($_POST['api_key'] ?? $cfg['pixabay']['api_key'] ?? '');
            if ($key === '') {
                respond(false, null, 'api_key is required');
            }
            [$code, $decoded] = curlGet(
                'https://pixabay.com/api/?key=' . urlencode($key) . '&q=nature&per_page=3&safesearch=true'
            );
            if ($code === 200 && !empty($decoded['totalHits'])) {
                respond(true, ['total' => $decoded['totalHits']]);
            }
            respond(false, null, 'Invalid key or no results');

        case 'test_feed':
            $url = trim($_POST['url'] ?? '');
            if ($url === '') {
                respond(false, null, 'url is required');
            }

            // Build a minimal config to test just this one feed
            $testCfg = $cfg;
            $testCfg['rss_feeds'] = [[
                'url'      => $url,
                'name'     => 'Test Feed',
                'language' => 'auto',
                'enabled'  => true,
                'wp_site'  => '',
            ]];
            // Disable duplicate checking so we always get results
            $testCfg['general']['duplicate_check'] = false;

            $fetcher  = new RssFetcher($testCfg);
            $articles = $fetcher->fetch(3);

            $preview = array_map(fn($a) => [
                'title'    => $a['title'],
                'url'      => $a['url'],
                'image'    => $a['image'],
                'language' => $a['language'],
            ], $articles);

            respond(true, ['articles' => $preview]);

        // =================================================================
        // POSTS & DRAFTS
        // =================================================================

        case 'get_posts':
            $posts = [];

            // 1. Custom DB posts
            $db = getDb($cfg);
            if ($db !== null) {
                $posts = $db->getRecentPosts(100);
            }

            // 2. WordPress REST API posts (when WP is enabled)
            if (!empty($cfg['wordpress']['enabled']) && !empty($cfg['wordpress']['site_url'])) {
                $wpUrl  = rtrim($cfg['wordpress']['site_url'], '/');
                $wpUser = $cfg['wordpress']['username']     ?? '';
                $wpPass = $cfg['wordpress']['app_password'] ?? '';
                $auth   = 'Basic ' . base64_encode($wpUser . ':' . $wpPass);

                [$code, $wpPosts] = curlGet(
                    $wpUrl . '/wp-json/wp/v2/posts?per_page=50&orderby=date&order=desc&_fields=id,title,excerpt,status,date,link,categories,tags',
                    ['Authorization: ' . $auth],
                    15
                );

                if ($code === 200 && is_array($wpPosts)) {
                    foreach ($wpPosts as $wp) {
                        $posts[] = [
                            'id'         => $wp['id']   ?? null,
                            'title'      => html_entity_decode(strip_tags($wp['title']['rendered']   ?? ''), ENT_QUOTES),
                            'excerpt'    => html_entity_decode(strip_tags($wp['excerpt']['rendered'] ?? ''), ENT_QUOTES),
                            'content'    => $wp['content']['rendered'] ?? '',
                            'status'     => $wp['status']  ?? 'publish',
                            'created_at' => $wp['date']    ?? '',
                            'source'     => parse_url($wpUrl, PHP_URL_HOST) ?? $wpUrl,
                            'language'   => 'English',
                            'wp_url'     => $wp['link']    ?? '',
                            'wp_id'      => $wp['id']      ?? null,
                            '_source'    => 'wordpress',
                        ];
                    }
                }
            }

            // Sort all posts by date descending
            usort($posts, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

            respond(true, $posts);

        case 'get_drafts':
            $db = getDb($cfg);
            respond(true, $db !== null ? $db->getPendingDrafts() : []);

        case 'approve_post':
            $db = getDb($cfg);
            if ($db === null) {
                respond(false, null, 'Custom site mode is not enabled');
            }
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                respond(false, null, 'Invalid post ID');
            }
            $db->approvePost($id);
            respond(true, null, 'Post approved');

        case 'reject_post':
            $db = getDb($cfg);
            if ($db === null) {
                respond(false, null, 'Custom site mode is not enabled');
            }
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                respond(false, null, 'Invalid post ID');
            }
            $db->rejectPost($id);
            respond(true, null, 'Post rejected');

        // =================================================================
        // DEFAULT
        // =================================================================

        default:
            respond(false, null, 'Unknown action: ' . htmlspecialchars($action, ENT_QUOTES));
    }

} catch (Exception $e) {
    respond(false, null, $e->getMessage());
}
