<?php
define('BASE_PATH', realpath(__DIR__ . '/..'));
$cfg = require BASE_PATH . '/config/config.php';

require_once BASE_PATH . '/src/Logger.php';
Logger::init($cfg['general']);

$wpConfigured  = !empty($cfg['wordpress']['site_url']) && $cfg['wordpress']['site_url'] !== 'https://yoursite.com';
$aiConfigured  = (!empty($cfg['ai']['openai_api_key']) && $cfg['ai']['openai_api_key'] !== 'YOUR_OPENAI_API_KEY')
              || (!empty($cfg['ai']['gemini_api_key']) && $cfg['ai']['gemini_api_key'] !== 'YOUR_GEMINI_API_KEY');
if (!$wpConfigured && !$aiConfigured) { header('Location: setup.php'); exit; }

$stats         = Logger::getStats();
$tokenStats    = Logger::getTokenStats();
$totalPosted   = Logger::getPostedCount();
$todayPosted   = $stats['today_posted'];
$activeFeeds   = count(array_filter($cfg['rss_feeds'] ?? [], fn($f) => !empty($f['enabled'])));
$intervalHours = (int) ($cfg['general']['interval_hours'] ?? 6);
$nextRunTs     = time() + ($intervalHours * 3600);

$aiProvider = $cfg['ai']['provider'] ?? 'openai';
$aiLabel    = $aiProvider === 'gemini' ? 'Gemini' : 'OpenAI';
$aiModel    = $aiProvider === 'gemini' ? ($cfg['ai']['gemini_model'] ?? '—') : ($cfg['ai']['openai_model'] ?? '—');
$aiOk       = ($aiProvider === 'openai')
                ? (!empty($cfg['ai']['openai_api_key']) && $cfg['ai']['openai_api_key'] !== 'YOUR_OPENAI_API_KEY')
                : (!empty($cfg['ai']['gemini_api_key']) && $cfg['ai']['gemini_api_key'] !== 'YOUR_GEMINI_API_KEY');
$pxOk       = !empty($cfg['pixabay']['api_key']) && $cfg['pixabay']['api_key'] !== 'YOUR_PIXABAY_API_KEY';
$wpOk       = $wpConfigured;
$isPaused   = !empty($cfg['general']['pause']);

// Token chart data
$chartDays    = [];
$chartTokens  = [];
$last7 = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $last7[$d] = $tokenStats['by_day'][$d] ?? 0;
}
$chartDays   = array_keys($last7);
$chartTokens = array_values($last7);
$chartMax    = max(max($chartTokens), 1);

function fmtTokens(int $n): string {
    if ($n >= 1000000) return round($n/1000000, 2) . 'M';
    if ($n >= 1000)    return round($n/1000, 1) . 'K';
    return (string) $n;
}
function fmtCost(float $c): string {
    return '$' . number_format($c, 4);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — Blogy Assistant</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --bg:       #0f1117;
    --sidebar:  #13161f;
    --card:     #1a1d27;
    --border:   #252838;
    --accent:   #6c63ff;
    --accent2:  rgba(108,99,255,.12);
    --text:     #e2e8f0;
    --muted:    #64748b;
    --green:    #4ade80;
    --red:      #f87171;
    --yellow:   #fbbf24;
    --blue:     #60a5fa;
    --orange:   #fb923c;
    --sidebar-w: 220px;
  }
  body { font-family:'Inter',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; display:flex; }

  /* Sidebar */
  .sidebar { width:var(--sidebar-w); background:var(--sidebar); border-right:1px solid var(--border); display:flex; flex-direction:column; position:fixed; top:0; left:0; bottom:0; z-index:100; transition:transform .25s ease; }
  .sidebar-logo { padding:22px 20px 18px; font-size:17px; font-weight:600; letter-spacing:-.3px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:8px; }
  .sidebar-logo span { font-size:22px; }
  .nav { flex:1; padding:12px 10px; display:flex; flex-direction:column; gap:2px; }
  .nav-link { display:flex; align-items:center; gap:10px; padding:9px 12px; border-radius:8px; font-size:14px; font-weight:500; color:var(--muted); text-decoration:none; cursor:pointer; transition:background .15s,color .15s; border-left:3px solid transparent; }
  .nav-link:hover { background:rgba(255,255,255,.04); color:var(--text); }
  .nav-link.active { background:var(--accent2); color:var(--accent); border-left-color:var(--accent); }
  .nav-icon { font-size:15px; width:18px; text-align:center; }
  .sidebar-footer { padding:14px 20px; font-size:11px; color:var(--muted); border-top:1px solid var(--border); }

  /* Main */
  .main { margin-left:var(--sidebar-w); flex:1; padding:28px 26px; min-width:0; }
  .page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; gap:12px; flex-wrap:wrap; }
  .page-title { font-size:22px; font-weight:600; }
  .pause-badge { display:inline-flex; align-items:center; gap:6px; background:rgba(251,191,36,.1); border:1px solid rgba(251,191,36,.25); color:var(--yellow); border-radius:20px; padding:4px 12px; font-size:12px; font-weight:500; }
  .pause-badge.hidden { display:none; }

  /* Section title */
  .section-title { font-size:12px; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:.07em; margin:20px 0 10px; }

  /* Stat Grid */
  .stat-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:6px; }
  .stat-grid.six { grid-template-columns:repeat(6,1fr); }
  .stat-card { background:var(--card); border:1px solid var(--border); border-radius:12px; padding:18px 16px; position:relative; overflow:hidden; }
  .stat-card::before { content:''; position:absolute; top:0; left:0; right:0; height:2px; border-radius:12px 12px 0 0; }
  .stat-card.c-purple::before { background:var(--accent); }
  .stat-card.c-green::before  { background:var(--green); }
  .stat-card.c-blue::before   { background:var(--blue); }
  .stat-card.c-yellow::before { background:var(--yellow); }
  .stat-card.c-orange::before { background:var(--orange); }
  .stat-card.c-red::before    { background:var(--red); }
  .stat-label { font-size:11px; color:var(--muted); font-weight:500; margin-bottom:8px; text-transform:uppercase; letter-spacing:.05em; display:flex; align-items:center; gap:5px; }
  .stat-value { font-size:26px; font-weight:600; line-height:1; margin-bottom:4px; }
  .stat-sub   { font-size:11px; color:var(--muted); }
  .stat-value.purple { color:var(--accent); }
  .stat-value.green  { color:var(--green); }
  .stat-value.blue   { color:var(--blue); }
  .stat-value.yellow { color:var(--yellow); }
  .stat-value.orange { color:var(--orange); }

  /* Status Bar */
  .status-bar { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:18px; }
  .status-pill { display:flex; align-items:center; gap:7px; background:var(--card); border:1px solid var(--border); border-radius:20px; padding:6px 13px; font-size:12.5px; }
  .status-dot { width:7px; height:7px; border-radius:50%; flex-shrink:0; }
  .status-dot.ok  { background:var(--green); box-shadow:0 0 6px rgba(74,222,128,.5); }
  .status-dot.bad { background:var(--red);   box-shadow:0 0 6px rgba(248,113,113,.4); }
  .status-name  { font-weight:500; }
  .status-state { color:var(--muted); font-size:11px; }

  /* Layout grids */
  .grid-3-1 { display:grid; grid-template-columns:3fr 1fr; gap:14px; align-items:start; }
  .grid-2-1 { display:grid; grid-template-columns:2fr 1fr; gap:14px; align-items:start; }
  .grid-2   { display:grid; grid-template-columns:1fr 1fr; gap:14px; align-items:start; }

  /* Card */
  .card { background:var(--card); border:1px solid var(--border); border-radius:12px; overflow:hidden; }
  .card-header { display:flex; align-items:center; justify-content:space-between; padding:14px 18px; border-bottom:1px solid var(--border); }
  .card-title { font-size:13px; font-weight:600; }
  .card-body  { padding:16px 18px; }

  /* Log viewer */
  .log-scroll { max-height:320px; overflow-y:auto; background:#0a0c12; border-radius:8px; padding:10px 12px; font-family:'Courier New',monospace; font-size:11.5px; line-height:1.7; }
  .log-scroll::-webkit-scrollbar { width:4px; }
  .log-scroll::-webkit-scrollbar-thumb { background:#2d3147; border-radius:2px; }
  .log-line-info  { color:#4ade80; }
  .log-line-error { color:#f87171; }
  .log-line-plain { color:#94a3b8; }
  .log-empty { color:var(--muted); font-size:12px; font-style:italic; }
  .log-footer { display:flex; align-items:center; justify-content:space-between; padding:9px 18px; border-top:1px solid var(--border); font-size:11px; color:var(--muted); }

  /* Quick Actions */
  .action-list { display:flex; flex-direction:column; gap:9px; }
  .btn-action { display:flex; align-items:center; justify-content:center; gap:8px; width:100%; padding:10px; border-radius:8px; font-size:13.5px; font-weight:500; cursor:pointer; border:none; font-family:inherit; transition:opacity .15s,transform .1s; }
  .btn-action:active { transform:scale(.97); }
  .btn-action:disabled { opacity:.45; cursor:not-allowed; transform:none; }
  .btn-run   { background:var(--accent); color:#fff; }
  .btn-run:hover { opacity:.85; }
  .btn-pause { background:var(--card); border:1px solid var(--border); color:var(--text); }
  .btn-pause:hover { border-color:var(--accent); color:var(--accent); }
  .divider   { height:1px; background:var(--border); margin:3px 0; }
  .last-run  { font-size:11.5px; color:var(--muted); line-height:1.6; word-break:break-word; }
  .last-run strong { color:var(--text); display:block; margin-bottom:3px; font-size:12px; }

  /* Buttons */
  .btn-sm { background:transparent; border:1px solid var(--border); color:var(--muted); border-radius:6px; padding:4px 10px; font-size:11px; cursor:pointer; font-family:inherit; transition:border-color .15s,color .15s; }
  .btn-sm:hover { border-color:var(--accent); color:var(--accent); }
  .btn-danger { border-color:rgba(248,113,113,.3); color:var(--red); }
  .btn-danger:hover { border-color:var(--red); }

  /* Token Chart */
  .chart-wrap { padding:14px 18px 10px; }
  .chart-bars { display:flex; align-items:flex-end; gap:6px; height:80px; }
  .chart-bar-col { flex:1; display:flex; flex-direction:column; align-items:center; gap:5px; }
  .chart-bar { width:100%; border-radius:4px 4px 0 0; background:var(--accent); opacity:.7; min-height:3px; transition:opacity .2s; }
  .chart-bar:hover { opacity:1; }
  .chart-bar.today { background:var(--green); opacity:1; }
  .chart-label { font-size:9px; color:var(--muted); }
  .token-meta { display:flex; gap:16px; padding:10px 18px 14px; flex-wrap:wrap; }
  .token-meta-item { display:flex; flex-direction:column; gap:2px; }
  .token-meta-key   { font-size:10px; color:var(--muted); text-transform:uppercase; letter-spacing:.05em; }
  .token-meta-val   { font-size:14px; font-weight:600; color:var(--text); }

  /* System Health */
  .health-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
  .health-row  { display:flex; align-items:center; justify-content:space-between; padding:7px 10px; background:#13161f; border-radius:7px; font-size:12px; }
  .health-key  { color:var(--muted); }
  .health-val  { font-weight:500; }
  .health-val.ok  { color:var(--green); }
  .health-val.bad { color:var(--red); }
  .health-val.warn{ color:var(--yellow); }
  .ext-grid { display:flex; flex-wrap:wrap; gap:6px; padding:0 0 4px; }
  .ext-pill { font-size:11px; padding:3px 8px; border-radius:12px; font-weight:500; }
  .ext-pill.ok  { background:rgba(74,222,128,.12); color:var(--green); border:1px solid rgba(74,222,128,.2); }
  .ext-pill.bad { background:rgba(248,113,113,.12); color:var(--red); border:1px solid rgba(248,113,113,.2); }

  /* Progress bar */
  .progress-wrap { background:var(--border); border-radius:4px; height:5px; overflow:hidden; margin-top:6px; }
  .progress-bar  { height:100%; border-radius:4px; background:var(--accent); transition:width .4s; }

  /* Recent posts */
  .post-list { display:flex; flex-direction:column; }
  .post-row  { display:flex; align-items:flex-start; gap:10px; padding:10px 0; border-bottom:1px solid var(--border); }
  .post-row:last-child { border-bottom:none; }
  .post-dot  { width:6px; height:6px; border-radius:50%; background:var(--accent); flex-shrink:0; margin-top:5px; }
  .post-info { flex:1; min-width:0; }
  .post-title{ font-size:12.5px; font-weight:500; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .post-meta { font-size:11px; color:var(--muted); margin-top:2px; }

  /* Spinner / Toast / Mobile */
  .spin { display:inline-block; width:12px; height:12px; border:2px solid rgba(255,255,255,.25); border-top-color:#fff; border-radius:50%; animation:spin .6s linear infinite; }
  @keyframes spin { to { transform:rotate(360deg); } }
  #toast-container { position:fixed; bottom:22px; right:22px; display:flex; flex-direction:column; gap:8px; z-index:999; pointer-events:none; }
  .toast { display:flex; align-items:center; gap:9px; padding:11px 16px; border-radius:10px; font-size:13px; font-weight:500; box-shadow:0 4px 20px rgba(0,0,0,.5); pointer-events:auto; transform:translateX(120%); transition:transform .3s cubic-bezier(.34,1.56,.64,1); min-width:200px; max-width:320px; }
  .toast.show { transform:translateX(0); }
  .toast.success { background:#162a20; border:1px solid rgba(74,222,128,.3); color:var(--green); }
  .toast.error   { background:#2a1616; border:1px solid rgba(248,113,113,.3); color:var(--red); }
  .hamburger { display:none; position:fixed; top:14px; left:14px; z-index:200; background:var(--sidebar); border:1px solid var(--border); border-radius:8px; padding:8px 10px; cursor:pointer; font-size:18px; line-height:1; color:var(--text); }
  .overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.6); z-index:99; }
  .overlay.visible { display:block; }

  @media(max-width:1100px) { .stat-grid.six { grid-template-columns:repeat(3,1fr); } }
  @media(max-width:900px) { .grid-3-1,.grid-2-1,.grid-2 { grid-template-columns:1fr; } }
  @media(max-width:700px) { .stat-grid { grid-template-columns:1fr 1fr; } }
  @media(max-width:640px) { .hamburger { display:block; } .sidebar { transform:translateX(-100%); } .sidebar.open { transform:translateX(0); } .main { margin-left:0; padding:18px 14px; padding-top:58px; } }
  @media(max-width:420px) { .stat-grid { grid-template-columns:1fr; } }
</style>
</head>
<body>

<button class="hamburger" id="hamburger">☰</button>
<div class="overlay" id="overlay"></div>

<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo"><span>🤖</span> Blogy</div>
  <nav class="nav">
    <a class="nav-link active" href="index.php"><span class="nav-icon">⊞</span> Dashboard</a>
    <a class="nav-link" href="feeds.php"><span class="nav-icon">⊟</span> Feeds</a>
    <a class="nav-link" href="posts.php"><span class="nav-icon">📄</span> Posts</a>
    <a class="nav-link" href="settings.php"><span class="nav-icon">⚙</span> Settings</a>
    <a class="nav-link" href="setup.php"><span class="nav-icon">✦</span> Setup Wizard</a>
  </nav>
  <div class="sidebar-footer">v2.0.0</div>
</aside>

<main class="main">

  <div class="page-header">
    <h1 class="page-title">Dashboard</h1>
    <div class="pause-badge <?= $isPaused ? '' : 'hidden' ?>" id="pause-badge">⏸ Pipeline Paused</div>
  </div>

  <!-- Status Pills -->
  <div class="status-bar">
    <div class="status-pill">
      <div class="status-dot <?= $aiOk ? 'ok' : 'bad' ?>"></div>
      <span class="status-name"><?= htmlspecialchars($aiLabel) ?></span>
      <span class="status-state"><?= htmlspecialchars($aiModel) ?></span>
    </div>
    <div class="status-pill">
      <div class="status-dot <?= $pxOk ? 'ok' : 'bad' ?>"></div>
      <span class="status-name">Pixabay</span>
      <span class="status-state"><?= $pxOk ? 'Active' : 'Not configured' ?></span>
    </div>
    <div class="status-pill">
      <div class="status-dot <?= $wpOk ? 'ok' : 'bad' ?>"></div>
      <span class="status-name">WordPress</span>
      <span class="status-state"><?= $wpOk ? htmlspecialchars(parse_url($cfg['wordpress']['site_url'] ?? '', PHP_URL_HOST) ?? 'Connected') : 'Not configured' ?></span>
    </div>
    <div class="status-pill">
      <div class="status-dot <?= $isPaused ? 'bad' : 'ok' ?>"></div>
      <span class="status-name">Pipeline</span>
      <span class="status-state"><?= $isPaused ? 'Paused' : 'Running' ?></span>
    </div>
  </div>

  <!-- Row 1: Publishing Stats -->
  <div class="section-title">Publishing Stats</div>
  <div class="stat-grid">
    <div class="stat-card c-purple">
      <div class="stat-label">Total Published</div>
      <div class="stat-value purple"><?= number_format($totalPosted) ?></div>
      <div class="stat-sub">all time</div>
    </div>
    <div class="stat-card c-green">
      <div class="stat-label">Posts Today</div>
      <div class="stat-value green"><?= number_format($todayPosted) ?></div>
      <div class="stat-sub"><?= date('M j, Y') ?></div>
    </div>
    <div class="stat-card c-blue">
      <div class="stat-label">Active Feeds</div>
      <div class="stat-value blue"><?= $activeFeeds ?></div>
      <div class="stat-sub">of <?= count($cfg['rss_feeds'] ?? []) ?> total</div>
    </div>
    <div class="stat-card c-yellow">
      <div class="stat-label">Next Run</div>
      <div class="stat-value yellow" id="countdown">--</div>
      <div class="stat-sub">every <?= $intervalHours ?>h</div>
    </div>
  </div>

  <!-- Row 2: AI Token Stats -->
  <div class="section-title">AI Token Consumption</div>
  <div class="stat-grid" style="grid-template-columns:repeat(5,1fr)">
    <div class="stat-card c-purple">
      <div class="stat-label">Tokens Today</div>
      <div class="stat-value purple"><?= fmtTokens($tokenStats['today_tokens']) ?></div>
      <div class="stat-sub"><?= number_format($tokenStats['today_tokens']) ?> tokens</div>
    </div>
    <div class="stat-card c-orange">
      <div class="stat-label">Cost Today</div>
      <div class="stat-value orange"><?= fmtCost($tokenStats['cost_today']) ?></div>
      <div class="stat-sub">estimated USD</div>
    </div>
    <div class="stat-card c-blue">
      <div class="stat-label">Total Tokens</div>
      <div class="stat-value blue"><?= fmtTokens($tokenStats['total_tokens']) ?></div>
      <div class="stat-sub"><?= number_format($tokenStats['calls_total']) ?> API calls</div>
    </div>
    <div class="stat-card c-red">
      <div class="stat-label">Total Cost</div>
      <div class="stat-value" style="color:var(--red)"><?= fmtCost($tokenStats['cost_total']) ?></div>
      <div class="stat-sub">estimated USD</div>
    </div>
    <div class="stat-card c-green">
      <div class="stat-label">Errors</div>
      <div class="stat-value green"><?= number_format($stats['error_count']) ?></div>
      <div class="stat-sub">in error log</div>
    </div>
  </div>

  <!-- Row 3: Log + Actions -->
  <div class="section-title">Activity</div>
  <div class="grid-3-1">

    <!-- Activity Log -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">Activity Log</span>
        <button class="btn-sm" onclick="loadLogs()">↺ Refresh</button>
      </div>
      <div class="card-body" style="padding:12px 16px;">
        <div class="log-scroll" id="log-output"><span class="log-empty">Loading…</span></div>
      </div>
      <div class="log-footer">
        <span id="log-meta">—</span>
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
          <button class="btn-sm" onclick="clearLog('clear_activity_log','Activity log')">Clear Activity</button>
          <button class="btn-sm" onclick="clearLog('clear_error_log','Error log')">Clear Errors</button>
          <button class="btn-sm" onclick="clearLog('clear_posted_log','Posted log')">Clear Posted</button>
          <button class="btn-sm btn-danger" onclick="clearLog('clear_all_logs','All logs')">Clear All</button>
        </div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="card">
      <div class="card-header"><span class="card-title">Quick Actions</span></div>
      <div class="card-body">
        <div class="action-list">
          <button class="btn-action btn-run" id="btn-run" onclick="runNow()">▶ Run Now</button>
          <button class="btn-action btn-pause" id="btn-pause" onclick="togglePause()">
            <?= $isPaused ? '▶ Resume Pipeline' : '⏸ Pause Pipeline' ?>
          </button>
          <div class="divider"></div>
          <div class="last-run">
            <strong>Last activity</strong>
            <span id="last-run-text">Loading…</span>
          </div>
        </div>
      </div>
    </div>

  </div>

  <!-- Row 4: Token Chart + Recent Posts -->
  <div class="section-title">Insights</div>
  <div class="grid-2">

    <!-- Token Chart -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">Token Usage — Last 7 Days</span>
        <span style="font-size:11px;color:var(--muted)">Model: <?= htmlspecialchars($tokenStats['last_model'] ?: $aiModel) ?></span>
      </div>
      <div class="chart-wrap">
        <div class="chart-bars" id="token-chart">
          <?php foreach ($last7 as $day => $tokens):
            $pct   = $chartMax > 0 ? round(($tokens / $chartMax) * 100) : 0;
            $isToday = ($day === date('Y-m-d'));
            $label = date('M j', strtotime($day));
          ?>
          <div class="chart-bar-col">
            <div class="chart-bar <?= $isToday ? 'today' : '' ?>"
                 style="height:<?= max($pct, 2) ?>%"
                 title="<?= $label ?>: <?= number_format($tokens) ?> tokens"></div>
            <div class="chart-label"><?= $isToday ? 'Today' : $label ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="token-meta">
        <div class="token-meta-item">
          <div class="token-meta-key">Today</div>
          <div class="token-meta-val"><?= fmtTokens($tokenStats['today_tokens']) ?></div>
        </div>
        <div class="token-meta-item">
          <div class="token-meta-key">Cost Today</div>
          <div class="token-meta-val"><?= fmtCost($tokenStats['cost_today']) ?></div>
        </div>
        <div class="token-meta-item">
          <div class="token-meta-key">7-Day Total</div>
          <div class="token-meta-val" id="week-tokens"><?= fmtTokens(array_sum($chartTokens)) ?></div>
        </div>
        <div class="token-meta-item">
          <div class="token-meta-key">7-Day Cost</div>
          <div class="token-meta-val" id="week-cost">—</div>
        </div>
        <div class="token-meta-item">
          <div class="token-meta-key">Total Cost</div>
          <div class="token-meta-val"><?= fmtCost($tokenStats['cost_total']) ?></div>
        </div>
      </div>
    </div>

    <!-- System Health -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">System Health</span>
        <button class="btn-sm" onclick="loadHealth()">↺</button>
      </div>
      <div class="card-body" id="health-body">
        <span class="log-empty">Loading…</span>
      </div>
    </div>

  </div>

  <!-- Row 5: Recent Posts -->
  <div class="section-title">Recently Published</div>
  <div class="card">
    <div class="card-header">
      <span class="card-title">Last 10 Posts</span>
      <a href="posts.php" style="font-size:11px;color:var(--accent);text-decoration:none;">View all →</a>
    </div>
    <div class="card-body" id="recent-posts-body">
      <span class="log-empty">Loading…</span>
    </div>
  </div>

</main>

<div id="toast-container"></div>

<script>
const NEXT_RUN_TS = <?= $nextRunTs ?> * 1000;
let isPaused = <?= $isPaused ? 'true' : 'false' ?>;

// Countdown
function updateCountdown() {
  const diff = NEXT_RUN_TS - Date.now();
  const el   = document.getElementById('countdown');
  if (diff <= 0) { el.textContent = 'Soon'; return; }
  const h = Math.floor(diff / 3600000);
  const m = Math.floor((diff % 3600000) / 60000);
  const s = Math.floor((diff % 60000) / 1000);
  el.textContent = h > 0 ? `${h}h ${m}m` : m > 0 ? `${m}m ${s}s` : `${s}s`;
}
updateCountdown();
setInterval(updateCountdown, 1000);

// Logs
async function loadLogs() {
  try {
    const res   = await api('get_logs');
    const lines = res.data.activity || [];
    const el    = document.getElementById('log-output');
    if (!lines.length) {
      el.innerHTML = '<span class="log-empty">No activity yet.</span>';
      document.getElementById('log-meta').textContent = '0 entries';
      document.getElementById('last-run-text').textContent = '—';
      return;
    }
    el.innerHTML = lines.map(line => {
      const cls = line.includes('[INFO]') ? 'log-line-info' : line.includes('[ERROR]') ? 'log-line-error' : 'log-line-plain';
      return `<div class="${cls}">${esc(line)}</div>`;
    }).join('');
    document.getElementById('log-meta').textContent = lines.length + ' entries';
    const m = (lines[0] || '').match(/\]\s+(.+)$/);
    document.getElementById('last-run-text').textContent = m ? m[1] : lines[0];
    el.scrollTop = 0;
  } catch(e) {
    document.getElementById('log-output').innerHTML = '<span class="log-empty" style="color:var(--red)">Failed to load logs.</span>';
  }
}
loadLogs();
setInterval(loadLogs, 30000);

// System Health
async function loadHealth() {
  const body = document.getElementById('health-body');
  body.innerHTML = '<span class="log-empty">Loading…</span>';
  try {
    const res = await api('get_system_health');
    const d   = res.data;
    const exts = d.extensions || {};
    const extHtml = Object.entries(exts).map(([k,v]) =>
      `<span class="ext-pill ${v ? 'ok':'bad'}">${k}</span>`
    ).join('');

    const diskColor = d.disk_used_pct > 90 ? 'bad' : d.disk_used_pct > 75 ? 'warn' : 'ok';

    body.innerHTML = `
      <div class="ext-grid" style="margin-bottom:12px">${extHtml}</div>
      <div class="health-grid">
        <div class="health-row"><span class="health-key">PHP</span><span class="health-val ok">${esc(d.php_version)}</span></div>
        <div class="health-row"><span class="health-key">Memory Limit</span><span class="health-val">${esc(d.memory_limit)}</span></div>
        <div class="health-row"><span class="health-key">Max Exec Time</span><span class="health-val">${esc(d.max_exec_time)}s</span></div>
        <div class="health-row"><span class="health-key">Disk Free</span><span class="health-val ${diskColor}">${d.disk_free_gb} GB</span></div>
        <div class="health-row"><span class="health-key">Images Stored</span><span class="health-val">${d.image_count}</span></div>
        <div class="health-row"><span class="health-key">Disk Used</span><span class="health-val ${diskColor}">${d.disk_used_pct}%</span></div>
      </div>
      <div style="margin-top:12px">
        <div style="font-size:11px;color:var(--muted);margin-bottom:6px">Log File Sizes</div>
        <div class="health-grid">
          ${Object.entries(d.log_files||{}).map(([f,s])=>`<div class="health-row"><span class="health-key">${esc(f)}</span><span class="health-val">${esc(s)}</span></div>`).join('')}
        </div>
      </div>`;
  } catch(e) {
    body.innerHTML = `<span class="log-empty" style="color:var(--red)">Failed: ${esc(e.message)}</span>`;
  }
}
loadHealth();

// Recent Posts
async function loadRecentPosts() {
  const body = document.getElementById('recent-posts-body');
  try {
    const res   = await api('get_posts');
    const posts = (res.data || []).slice(0, 10);
    if (!posts.length) { body.innerHTML = '<span class="log-empty">No posts yet.</span>'; return; }
    const langColors = { English:'#60a5fa', Urdu:'#4ade80', Arabic:'#fb923c', Hindi:'#a78bfa', French:'#fbbf24' };
    body.innerHTML = `<div class="post-list">${posts.map(p => {
      const color = langColors[p.language] || '#94a3b8';
      const date  = (p.created_at || '').slice(0,10);
      return `<div class="post-row">
        <div class="post-dot"></div>
        <div class="post-info">
          <div class="post-title" title="${esc(p.title)}">${esc(p.title||'Untitled')}</div>
          <div class="post-meta">
            <span style="color:${color}">${esc(p.language||'')}</span>
            &nbsp;·&nbsp;${esc(p.source||'')}
            &nbsp;·&nbsp;${esc(date)}
          </div>
        </div>
        <span style="font-size:10px;color:${p.status==='published'?'var(--green)':'var(--yellow)'};">${esc(p.status||'')}</span>
      </div>`;
    }).join('')}</div>`;
  } catch(e) {
    body.innerHTML = `<span class="log-empty" style="color:var(--red)">Failed to load posts.</span>`;
  }
}
loadRecentPosts();

// Load token stats for week cost
async function loadTokenStats() {
  try {
    const res = await api('get_token_stats');
    const d   = res.data;
    const weekTotal = Object.values(d.by_day||{}).reduce((a,b)=>a+b, 0);
    document.getElementById('week-tokens').textContent = fmtTokens(weekTotal);
    document.getElementById('week-cost').textContent   = '$' + (d.cost_total||0).toFixed(4);
  } catch(e) {}
}
loadTokenStats();

async function clearLog(action, label) {
  const warn = action === 'clear_all_logs' || action === 'clear_posted_log'
    ? '\n\nWarning: clearing posted log may cause duplicate posts.' : '';
  if (!confirm(`Clear ${label}?${warn}`)) return;
  try {
    await api(action);
    toast(label + ' cleared', 'success');
    loadLogs();
  } catch(e) { toast(e.message, 'error'); }
}

async function runNow() {
  const btn = document.getElementById('btn-run');
  btn.disabled = true;
  btn.innerHTML = '<span class="spin"></span> Starting…';
  try { await api('run_now'); toast('Pipeline started in background', 'success'); }
  catch(e) { toast(e.message, 'error'); }
  finally { setTimeout(() => { btn.disabled = false; btn.innerHTML = '▶ Run Now'; }, 5000); }
}

async function togglePause() {
  const btn = document.getElementById('btn-pause');
  btn.disabled = true;
  try {
    const res = await api('pause_toggle');
    isPaused = res.data.paused;
    btn.textContent = isPaused ? '▶ Resume Pipeline' : '⏸ Pause Pipeline';
    document.getElementById('pause-badge').classList.toggle('hidden', !isPaused);
    toast(isPaused ? 'Pipeline paused' : 'Pipeline resumed', 'success');
  } catch(e) { toast(e.message, 'error'); }
  finally { btn.disabled = false; }
}

function fmtTokens(n) {
  if (n >= 1000000) return (n/1000000).toFixed(2)+'M';
  if (n >= 1000)    return (n/1000).toFixed(1)+'K';
  return String(n);
}

function toast(msg, type='success') {
  const c  = document.getElementById('toast-container');
  const el = document.createElement('div');
  el.className = 'toast ' + type;
  el.innerHTML = (type==='success'?'✓':'✗') + ' ' + esc(msg);
  c.appendChild(el);
  requestAnimationFrame(() => requestAnimationFrame(() => el.classList.add('show')));
  setTimeout(() => { el.classList.remove('show'); setTimeout(() => el.remove(), 350); }, 3500);
}

const sidebar   = document.getElementById('sidebar');
const overlay   = document.getElementById('overlay');
const hamburger = document.getElementById('hamburger');
hamburger.addEventListener('click', () => { sidebar.classList.toggle('open'); overlay.classList.toggle('visible'); });
overlay.addEventListener('click',   () => { sidebar.classList.remove('open'); overlay.classList.remove('visible'); });

async function api(action, extra={}) {
  const res = await fetch('api.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action,...extra}) });
  if (!res.ok) throw new Error('HTTP '+res.status);
  const data = await res.json();
  if (!data.success) throw new Error(data.message||'Unknown error');
  return data;
}

function esc(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
</body>
</html>
