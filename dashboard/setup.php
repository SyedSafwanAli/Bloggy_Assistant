<?php
session_start();
define('BASE_PATH', realpath(__DIR__ . '/..'));
$cfg = require BASE_PATH . '/config/config.php';

// If already configured, skip wizard
$wpConfigured  = !empty($cfg['wordpress']['site_url']) && $cfg['wordpress']['site_url'] !== 'https://yoursite.com';
$aiConfigured  = (!empty($cfg['ai']['openai_api_key']) && $cfg['ai']['openai_api_key'] !== 'YOUR_OPENAI_API_KEY')
              || (!empty($cfg['ai']['gemini_api_key'])  && $cfg['ai']['gemini_api_key']  !== 'YOUR_GEMINI_API_KEY');
if ($wpConfigured || $aiConfigured) {
    header('Location: index.php');
    exit;
}

$currentStep = $_SESSION['setup_step'] ?? 1;

// Requirements check
$reqs = [
    'PHP 8.1+'   => version_compare(PHP_VERSION, '8.1.0', '>='),
    'cURL'       => extension_loaded('curl'),
    'SimpleXML'  => extension_loaded('simplexml'),
    'GD'         => extension_loaded('gd'),
];
$allPassed = !in_array(false, $reqs, true);

// Existing config values to preserve
$existingImage      = json_encode($cfg['image']       ?? []);
$existingCustomSite = json_encode($cfg['custom_site'] ?? []);
$existingEmail      = json_encode($cfg['email']       ?? []);
$existingLogPaths   = json_encode([
    'posted_log'   => $cfg['general']['posted_log']   ?? '',
    'error_log'    => $cfg['general']['error_log']    ?? '',
    'activity_log' => $cfg['general']['activity_log'] ?? '',
]);
$basePath = BASE_PATH;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Blogy Assistant — Setup</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'Inter', sans-serif;
    background: #0f1117;
    color: #e2e8f0;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 40px 16px 80px;
  }

  .wizard-wrap { width: 100%; max-width: 560px; }

  /* ---------- Dot Progress ---------- */
  .dots {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
    margin-bottom: 32px;
  }
  .dot {
    width: 12px; height: 12px;
    border-radius: 50%;
    background: #2d3147;
    border: 2px solid #3a3f5c;
    cursor: default;
    transition: background .2s, border-color .2s, transform .15s;
    position: relative;
  }
  .dot.filled   { background: #6c63ff; border-color: #6c63ff; }
  .dot.current  { background: #6c63ff; border-color: #a09cf7; box-shadow: 0 0 0 3px rgba(108,99,255,.25); }
  .dot.clickable { cursor: pointer; }
  .dot.clickable:hover { transform: scale(1.2); }
  .dot-line {
    flex: 1; height: 2px; max-width: 48px;
    background: #2d3147;
    border-radius: 2px;
    transition: background .3s;
  }
  .dot-line.filled { background: #6c63ff; }

  /* ---------- Card ---------- */
  .card {
    background: #1a1d27;
    border: 1px solid #252838;
    border-radius: 16px;
    padding: 36px 32px;
    display: none;
  }
  .card.active { display: block; }

  /* ---------- Typography ---------- */
  .step-label {
    font-size: 12px; font-weight: 600; letter-spacing: .08em;
    text-transform: uppercase; color: #6c63ff; margin-bottom: 8px;
  }
  h1 { font-size: 26px; font-weight: 600; line-height: 1.2; margin-bottom: 8px; }
  h2 { font-size: 20px; font-weight: 600; margin-bottom: 6px; }
  .subtitle { color: #94a3b8; font-size: 15px; margin-bottom: 28px; line-height: 1.6; }

  /* ---------- Requirements ---------- */
  .req-list { list-style: none; margin-bottom: 24px; display: flex; flex-direction: column; gap: 10px; }
  .req-item {
    display: flex; align-items: center; gap: 10px;
    background: #12141e; border-radius: 8px; padding: 10px 14px;
    font-size: 14px;
  }
  .req-icon { font-size: 16px; width: 20px; text-align: center; }
  .pass  { color: #4ade80; }
  .fail  { color: #f87171; }
  .warn-banner {
    background: rgba(251,191,36,.08); border: 1px solid rgba(251,191,36,.25);
    border-radius: 8px; padding: 12px 16px; font-size: 13px; color: #fbbf24;
    margin-bottom: 20px; display: flex; gap: 8px; align-items: flex-start;
  }

  /* ---------- Form ---------- */
  .field { margin-bottom: 18px; }
  label { display: block; font-size: 13px; font-weight: 500; color: #94a3b8; margin-bottom: 6px; }
  input[type="text"], input[type="url"], input[type="password"],
  input[type="number"], select {
    width: 100%; background: #12141e; border: 1px solid #2d3147;
    border-radius: 8px; padding: 10px 14px; font-size: 14px;
    color: #e2e8f0; font-family: inherit; outline: none;
    transition: border-color .2s;
  }
  input:focus, select:focus { border-color: #6c63ff; }
  input::placeholder { color: #4a5170; }
  .info-link { font-size: 12px; color: #6c63ff; text-decoration: none; margin-top: 5px; display: inline-block; }
  .info-link:hover { text-decoration: underline; }

  /* ---------- Provider Toggle ---------- */
  .provider-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px; }
  .provider-card {
    background: #12141e; border: 2px solid #252838; border-radius: 10px;
    padding: 16px; cursor: pointer; text-align: center;
    transition: border-color .2s, background .2s;
  }
  .provider-card:hover { border-color: #3a3f5c; }
  .provider-card.selected { border-color: #6c63ff; background: rgba(108,99,255,.08); }
  .provider-logo { font-size: 28px; margin-bottom: 6px; }
  .provider-name { font-size: 14px; font-weight: 600; }
  .provider-sub  { font-size: 11px; color: #64748b; margin-top: 2px; }
  .provider-fields { display: none; }
  .provider-fields.visible { display: block; }

  /* ---------- Banner ---------- */
  .banner {
    border-radius: 8px; padding: 11px 14px; font-size: 13px;
    margin-bottom: 16px; display: none; align-items: flex-start; gap: 8px;
  }
  .banner.visible { display: flex; }
  .banner.success { background: rgba(74,222,128,.08); border: 1px solid rgba(74,222,128,.25); color: #4ade80; }
  .banner.error   { background: rgba(248,113,113,.08); border: 1px solid rgba(248,113,113,.25); color: #f87171; }

  /* ---------- Buttons ---------- */
  .btn-row { display: flex; align-items: center; gap: 12px; margin-top: 24px; flex-wrap: wrap; }
  .btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 10px 22px; border-radius: 8px; font-size: 14px; font-weight: 600;
    cursor: pointer; border: none; font-family: inherit; transition: opacity .15s, transform .1s;
  }
  .btn:active { transform: scale(.97); }
  .btn-primary  { background: #6c63ff; color: #fff; }
  .btn-primary:hover { opacity: .88; }
  .btn-primary:disabled { opacity: .4; cursor: not-allowed; transform: none; }
  .btn-ghost { background: transparent; border: 1px solid #2d3147; color: #94a3b8; }
  .btn-ghost:hover { border-color: #6c63ff; color: #6c63ff; }
  .btn-ghost:disabled { opacity: .35; cursor: not-allowed; }
  .btn-sm { padding: 7px 14px; font-size: 13px; }
  .skip-link { font-size: 12px; color: #4a5170; text-decoration: underline; cursor: pointer; margin-left: auto; }
  .skip-link:hover { color: #94a3b8; }

  /* ---------- Model selector ---------- */
  .model-select-wrap { display: none; margin-top: 14px; }
  .model-select-wrap.visible { display: block; }

  /* ---------- Feed preview ---------- */
  .feed-preview { display: none; margin-top: 16px; }
  .feed-preview.visible { display: block; }
  .article-card {
    background: #12141e; border-radius: 8px; padding: 12px 14px;
    margin-bottom: 8px; display: flex; gap: 12px; align-items: flex-start;
  }
  .article-thumb {
    width: 52px; height: 38px; border-radius: 4px; object-fit: cover;
    background: #1e2235; flex-shrink: 0;
  }
  .article-title { font-size: 13px; font-weight: 500; line-height: 1.4; }
  .article-src   { font-size: 11px; color: #64748b; margin-top: 3px; }

  /* ---------- Cron block ---------- */
  .cron-wrap { margin-top: 20px; }
  .cron-label { font-size: 12px; color: #64748b; margin-bottom: 6px; font-weight: 500; }
  .cron-box {
    background: #0a0c12; border: 1px solid #252838; border-radius: 8px;
    padding: 12px 14px; display: flex; justify-content: space-between; align-items: center; gap: 10px;
  }
  .cron-box code { font-size: 11px; color: #a0aec0; word-break: break-all; flex: 1; }
  .copy-btn {
    background: #252838; border: none; color: #94a3b8; border-radius: 6px;
    padding: 5px 10px; font-size: 11px; cursor: pointer; white-space: nowrap;
    transition: background .15s;
  }
  .copy-btn:hover { background: #3a3f5c; color: #fff; }

  /* ---------- Spinner ---------- */
  .spin {
    display: inline-block; width: 14px; height: 14px;
    border: 2px solid rgba(255,255,255,.25);
    border-top-color: #fff; border-radius: 50%;
    animation: spin .6s linear infinite;
  }
  @keyframes spin { to { transform: rotate(360deg); } }

  /* ---------- Responsive ---------- */
  @media (max-width: 480px) {
    .card { padding: 24px 18px; }
    h1 { font-size: 22px; }
    .provider-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
  }
</style>
</head>
<body>
<div class="wizard-wrap">

  <!-- ── Dot Progress ─────────────────────────────────────────── -->
  <div class="dots" id="dots">
    <div class="dot" data-step="1" title="Welcome"></div>
    <div class="dot-line" data-line="1"></div>
    <div class="dot" data-step="2" title="WordPress"></div>
    <div class="dot-line" data-line="2"></div>
    <div class="dot" data-step="3" title="AI Provider"></div>
    <div class="dot-line" data-line="3"></div>
    <div class="dot" data-step="4" title="Pixabay"></div>
    <div class="dot-line" data-line="4"></div>
    <div class="dot" data-step="5" title="Feed & Schedule"></div>
  </div>

  <!-- ════════════════════════════════════════════════════════════
       STEP 1 — Welcome
  ════════════════════════════════════════════════════════════════ -->
  <div class="card" id="step-1">
    <div style="text-align:center; margin-bottom: 28px;">
      <div style="font-size:52px; margin-bottom:12px;">🤖</div>
      <h1>Blogy Assistant</h1>
      <p class="subtitle">Automated blog publishing powered by AI.<br>Let's get you set up in a few minutes.</p>
    </div>

    <p class="step-label">System Requirements</p>
    <ul class="req-list">
      <?php foreach ($reqs as $label => $pass): ?>
      <li class="req-item">
        <span class="req-icon <?= $pass ? 'pass' : 'fail' ?>"><?= $pass ? '✓' : '✗' ?></span>
        <span><?= htmlspecialchars($label) ?></span>
        <?php if (!$pass): ?><span style="margin-left:auto;font-size:11px;color:#f87171;">Missing</span><?php endif; ?>
      </li>
      <?php endforeach; ?>
    </ul>

    <?php if (!$allPassed): ?>
    <div class="warn-banner">
      <span>⚠</span>
      <span>Some requirements are missing. Installation may not work correctly.</span>
    </div>
    <?php endif; ?>

    <div class="btn-row" style="justify-content: flex-end;">
      <button class="btn btn-primary" onclick="goTo(2)">Get Started →</button>
    </div>
  </div>

  <!-- ════════════════════════════════════════════════════════════
       STEP 2 — WordPress Connection
  ════════════════════════════════════════════════════════════════ -->
  <div class="card" id="step-2">
    <p class="step-label">Step 2 of 5</p>
    <h2>WordPress Connection</h2>
    <p class="subtitle">Connect to the WordPress site where posts will be published.</p>

    <div class="field">
      <label for="wp_site_url">Site URL</label>
      <input type="url" id="wp_site_url" placeholder="https://yoursite.com">
    </div>
    <div class="field">
      <label for="wp_username">Username</label>
      <input type="text" id="wp_username" placeholder="admin">
    </div>
    <div class="field">
      <label for="wp_app_password">Application Password</label>
      <input type="password" id="wp_app_password" placeholder="xxxx xxxx xxxx xxxx">
      <a class="info-link" href="https://wordpress.org/documentation/article/application-passwords/" target="_blank" rel="noopener">
        ↗ How to create an Application Password
      </a>
    </div>

    <div class="banner" id="wp-banner"></div>

    <div class="btn-row">
      <button class="btn btn-ghost btn-sm" onclick="goTo(1)">← Back</button>
      <button class="btn btn-ghost btn-sm" onclick="testWp()">Test Connection</button>
      <button class="btn btn-primary" id="wp-next" disabled onclick="goTo(3)">Next →</button>
      <span class="skip-link" onclick="skipStep(3)">Skip for now</span>
    </div>
  </div>

  <!-- ════════════════════════════════════════════════════════════
       STEP 3 — AI Provider
  ════════════════════════════════════════════════════════════════ -->
  <div class="card" id="step-3">
    <p class="step-label">Step 3 of 5</p>
    <h2>AI Provider</h2>
    <p class="subtitle">Choose the AI service that will rewrite your articles.</p>

    <div class="provider-grid">
      <div class="provider-card selected" id="card-openai" onclick="selectProvider('openai')">
        <div class="provider-logo">🧠</div>
        <div class="provider-name">OpenAI</div>
        <div class="provider-sub">GPT-4o</div>
      </div>
      <div class="provider-card" id="card-gemini" onclick="selectProvider('gemini')">
        <div class="provider-logo">✨</div>
        <div class="provider-name">Google Gemini</div>
        <div class="provider-sub">2.0 Flash</div>
      </div>
    </div>

    <!-- OpenAI fields -->
    <div class="provider-fields visible" id="fields-openai">
      <div class="field">
        <label for="openai_key">OpenAI API Key</label>
        <input type="password" id="openai_key" placeholder="sk-...">
      </div>
      <div class="field">
        <label for="openai_model_input">Model</label>
        <input type="text" id="openai_model_input" value="gpt-4o" placeholder="gpt-4o">
      </div>
    </div>

    <!-- Gemini fields -->
    <div class="provider-fields" id="fields-gemini">
      <div class="field">
        <label for="gemini_key">Gemini API Key</label>
        <input type="password" id="gemini_key" placeholder="AIza...">
      </div>
      <div class="field">
        <label for="gemini_model_input">Model</label>
        <input type="text" id="gemini_model_input" value="gemini-2.0-flash" placeholder="gemini-2.0-flash">
      </div>
    </div>

    <!-- Model dropdown (populated after successful test) -->
    <div class="model-select-wrap" id="model-select-wrap">
      <div class="field">
        <label for="model_select">Select Model</label>
        <select id="model_select"></select>
      </div>
    </div>

    <div class="banner" id="ai-banner"></div>

    <div class="btn-row">
      <button class="btn btn-ghost btn-sm" onclick="goTo(2)">← Back</button>
      <button class="btn btn-ghost btn-sm" onclick="testAi()">Test Connection</button>
      <button class="btn btn-primary" id="ai-next" disabled onclick="goTo(4)">Next →</button>
    </div>
  </div>

  <!-- ════════════════════════════════════════════════════════════
       STEP 4 — Pixabay
  ════════════════════════════════════════════════════════════════ -->
  <div class="card" id="step-4">
    <p class="step-label">Step 4 of 5</p>
    <h2>Pixabay Images</h2>
    <p class="subtitle">
      Pixabay provides free stock images for your blog posts. A free account gives you access to millions of photos.
    </p>

    <div class="field">
      <label for="pixabay_key">Pixabay API Key</label>
      <input type="text" id="pixabay_key" placeholder="Your Pixabay API key">
      <a class="info-link" href="https://pixabay.com/api/docs/" target="_blank" rel="noopener">↗ Get a free API key</a>
    </div>

    <div class="banner" id="px-banner"></div>

    <div class="btn-row">
      <button class="btn btn-ghost btn-sm" onclick="goTo(3)">← Back</button>
      <button class="btn btn-ghost btn-sm" onclick="testPixabay()">Test Key</button>
      <button class="btn btn-primary" id="px-next" disabled onclick="goTo(5)">Next →</button>
      <span class="skip-link" onclick="skipStep(5)">Skip</span>
    </div>
  </div>

  <!-- ════════════════════════════════════════════════════════════
       STEP 5 — First Feed + Schedule
  ════════════════════════════════════════════════════════════════ -->
  <div class="card" id="step-5">
    <p class="step-label">Step 5 of 5</p>
    <h2>First Feed &amp; Schedule</h2>
    <p class="subtitle">Add your first RSS feed and configure the publishing schedule.</p>

    <div class="field" style="display:flex; gap:8px; align-items: flex-end;">
      <div style="flex:1">
        <label for="feed_url">RSS / Atom Feed URL</label>
        <input type="url" id="feed_url" placeholder="https://feeds.example.com/feed.xml">
      </div>
      <button class="btn btn-ghost btn-sm" style="margin-bottom:1px" onclick="testFeed()">Test</button>
    </div>

    <div class="feed-preview" id="feed-preview"></div>

    <div class="field">
      <label for="feed_name">Feed Name</label>
      <input type="text" id="feed_name" placeholder="My News Feed">
    </div>

    <div class="field">
      <label for="feed_lang">Language</label>
      <select id="feed_lang">
        <option value="English">English</option>
        <option value="Urdu">Urdu</option>
        <option value="Arabic">Arabic</option>
        <option value="Hindi">Hindi</option>
        <option value="French">French</option>
        <option value="auto">Auto-detect</option>
      </select>
    </div>

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
      <div class="field">
        <label for="posts_per_run">Posts per run</label>
        <input type="number" id="posts_per_run" value="3" min="1" max="10">
      </div>
      <div class="field">
        <label for="interval_hours">Run every</label>
        <select id="interval_hours" onchange="updateCron()">
          <option value="1">1 hour</option>
          <option value="2">2 hours</option>
          <option value="3">3 hours</option>
          <option value="6" selected>6 hours</option>
          <option value="12">12 hours</option>
          <option value="24">24 hours</option>
        </select>
      </div>
    </div>

    <div class="cron-wrap">
      <p class="cron-label">Cron command (add to server crontab)</p>
      <div class="cron-box">
        <code id="cron-cmd"></code>
        <button class="copy-btn" onclick="copyCron()">Copy</button>
      </div>
    </div>

    <div class="banner" id="finish-banner"></div>

    <div class="btn-row" style="margin-top:28px;">
      <button class="btn btn-ghost btn-sm" onclick="goTo(4)">← Back</button>
      <button class="btn btn-primary" style="margin-left:auto" onclick="finishSetup()">
        Finish Setup ✓
      </button>
    </div>
  </div>

</div><!-- /wizard-wrap -->

<script>
// ─────────────────────────────────────────────────────────────────
// State
// ─────────────────────────────────────────────────────────────────
const API        = 'api.php';
const BASE_PATH  = <?= json_encode($basePath) ?>;
const IMG_CFG    = <?= $existingImage ?>;
const CUSTOM_CFG = <?= $existingCustomSite ?>;
const EMAIL_CFG  = <?= $existingEmail ?>;
const LOG_PATHS  = <?= $existingLogPaths ?>;

let currentStep    = <?= (int)$currentStep ?>;
let wpVerified     = false;
let aiVerified     = false;
let pxVerified     = false;
let selectedProvider = 'openai';
let completedSteps = new Set([]);

// ─────────────────────────────────────────────────────────────────
// Navigation
// ─────────────────────────────────────────────────────────────────
function goTo(step) {
  document.getElementById('step-' + currentStep).classList.remove('active');
  if (currentStep < step) completedSteps.add(currentStep);
  currentStep = step;
  document.getElementById('step-' + step).classList.add('active');
  renderDots();
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function skipStep(next) { goTo(next); }

function renderDots() {
  for (let i = 1; i <= 5; i++) {
    const dot  = document.querySelector(`.dot[data-step="${i}"]`);
    const line = document.querySelector(`.dot-line[data-line="${i}"]`);
    dot.classList.remove('filled', 'current', 'clickable');
    if (i < currentStep || completedSteps.has(i)) {
      dot.classList.add('filled', 'clickable');
      dot.onclick = () => goTo(i);
      if (line) line.classList.add('filled');
    } else if (i === currentStep) {
      dot.classList.add('current');
      dot.onclick = null;
      if (line) line.classList.remove('filled');
    } else {
      dot.onclick = null;
      if (line) line.classList.remove('filled');
    }
  }
}

// ─────────────────────────────────────────────────────────────────
// Banner helper
// ─────────────────────────────────────────────────────────────────
function showBanner(id, type, msg) {
  const el = document.getElementById(id);
  el.className = 'banner visible ' + type;
  el.innerHTML = (type === 'success' ? '✓ ' : '✗ ') + msg;
}
function hideBanner(id) {
  document.getElementById(id).className = 'banner';
}

// ─────────────────────────────────────────────────────────────────
// Step 2 — WordPress
// ─────────────────────────────────────────────────────────────────
async function testWp() {
  const btn = event.currentTarget;
  btn.innerHTML = '<span class="spin"></span> Testing…';
  btn.disabled = true;
  hideBanner('wp-banner');

  try {
    const res = await post({ action: 'test_wp_connection',
      site_url: v('wp_site_url'), username: v('wp_username'), app_password: v('wp_app_password') });
    if (res.success) {
      showBanner('wp-banner', 'success', 'Connected as ' + res.data.name);
      wpVerified = true;
      document.getElementById('wp-next').disabled = false;
    } else {
      showBanner('wp-banner', 'error', res.message || 'Connection failed');
    }
  } catch(e) { showBanner('wp-banner', 'error', e.message); }
  finally { btn.textContent = 'Test Connection'; btn.disabled = false; }
}

// ─────────────────────────────────────────────────────────────────
// Step 3 — AI
// ─────────────────────────────────────────────────────────────────
function selectProvider(p) {
  selectedProvider = p;
  document.getElementById('card-openai').classList.toggle('selected', p === 'openai');
  document.getElementById('card-gemini').classList.toggle('selected', p === 'gemini');
  document.getElementById('fields-openai').classList.toggle('visible', p === 'openai');
  document.getElementById('fields-gemini').classList.toggle('visible', p === 'gemini');
  document.getElementById('model-select-wrap').classList.remove('visible');
  hideBanner('ai-banner');
  document.getElementById('ai-next').disabled = true;
  aiVerified = false;
}

async function testAi() {
  const btn = event.currentTarget;
  btn.innerHTML = '<span class="spin"></span> Testing…';
  btn.disabled = true;
  hideBanner('ai-banner');

  const payload = { action: 'test_ai_connection', provider: selectedProvider };
  if (selectedProvider === 'openai') {
    payload.openai_api_key = v('openai_key');
    payload.openai_model   = v('openai_model_input');
  } else {
    payload.gemini_api_key = v('gemini_key');
    payload.gemini_model   = v('gemini_model_input');
  }

  try {
    const res = await post(payload);
    if (res.success) {
      showBanner('ai-banner', 'success', 'Connection successful — ' + res.data.models.length + ' models available');
      populateModels(res.data.models);
      aiVerified = true;
      document.getElementById('ai-next').disabled = false;
    } else {
      showBanner('ai-banner', 'error', res.message || 'Connection failed');
    }
  } catch(e) { showBanner('ai-banner', 'error', e.message); }
  finally { btn.textContent = 'Test Connection'; btn.disabled = false; }
}

function populateModels(models) {
  const sel = document.getElementById('model_select');
  sel.innerHTML = '';
  models.forEach(m => {
    const opt = document.createElement('option');
    opt.value = m; opt.textContent = m;
    sel.appendChild(opt);
  });
  // Pre-select current model input value if present
  const current = selectedProvider === 'openai' ? v('openai_model_input') : v('gemini_model_input');
  if (current) sel.value = current;
  document.getElementById('model-select-wrap').classList.add('visible');
}

// ─────────────────────────────────────────────────────────────────
// Step 4 — Pixabay
// ─────────────────────────────────────────────────────────────────
async function testPixabay() {
  const btn = event.currentTarget;
  btn.innerHTML = '<span class="spin"></span> Testing…';
  btn.disabled = true;
  hideBanner('px-banner');

  try {
    const res = await post({ action: 'test_pixabay', api_key: v('pixabay_key') });
    if (res.success) {
      showBanner('px-banner', 'success', 'Key valid — ' + res.data.total.toLocaleString() + ' images available');
      pxVerified = true;
      document.getElementById('px-next').disabled = false;
    } else {
      showBanner('px-banner', 'error', res.message || 'Invalid key');
    }
  } catch(e) { showBanner('px-banner', 'error', e.message); }
  finally { btn.textContent = 'Test Key'; btn.disabled = false; }
}

// ─────────────────────────────────────────────────────────────────
// Step 5 — Feed test
// ─────────────────────────────────────────────────────────────────
async function testFeed() {
  const btn = event.currentTarget;
  btn.innerHTML = '<span class="spin"></span>';
  btn.disabled = true;

  const preview = document.getElementById('feed-preview');
  preview.className = 'feed-preview';
  preview.innerHTML = '';

  try {
    const res = await post({ action: 'test_feed', url: v('feed_url') });
    if (res.success && res.data.articles.length) {
      preview.className = 'feed-preview visible';
      preview.innerHTML = '<p style="font-size:12px;color:#64748b;margin-bottom:8px;">'
        + res.data.articles.length + ' article(s) found</p>';
      res.data.articles.forEach(a => {
        preview.innerHTML += `
          <div class="article-card">
            ${a.image ? `<img class="article-thumb" src="${esc(a.image)}" alt="" onerror="this.style.display='none'">` : ''}
            <div>
              <div class="article-title">${esc(a.title)}</div>
              <div class="article-src">${esc(a.url)}</div>
            </div>
          </div>`;
      });
      // Auto-fill feed name from URL domain
      if (!v('feed_name')) {
        try {
          const domain = new URL(v('feed_url')).hostname.replace('www.', '');
          document.getElementById('feed_name').value = domain;
        } catch(_) {}
      }
    } else {
      preview.className = 'feed-preview visible';
      preview.innerHTML = '<p style="font-size:13px;color:#f87171;">No articles found — check the feed URL.</p>';
    }
  } catch(e) {
    preview.className = 'feed-preview visible';
    preview.innerHTML = '<p style="font-size:13px;color:#f87171;">' + esc(e.message) + '</p>';
  }
  finally { btn.textContent = 'Test'; btn.disabled = false; }
}

// ─────────────────────────────────────────────────────────────────
// Cron command
// ─────────────────────────────────────────────────────────────────
function updateCron() {
  const h    = parseInt(document.getElementById('interval_hours').value) || 6;
  const run  = BASE_PATH.replace(/\\/g, '/') + '/run.php';
  const log  = BASE_PATH.replace(/\\/g, '/') + '/logs/cron.log';
  let cron;
  if (h === 24) {
    cron = `0 0 * * * php ${run} >> ${log} 2>&1`;
  } else {
    cron = `0 */${h} * * * php ${run} >> ${log} 2>&1`;
  }
  document.getElementById('cron-cmd').textContent = cron;
}

function copyCron() {
  const text = document.getElementById('cron-cmd').textContent;
  navigator.clipboard.writeText(text).then(() => {
    const btn = document.querySelector('.copy-btn');
    btn.textContent = 'Copied!';
    setTimeout(() => btn.textContent = 'Copy', 2000);
  });
}

// ─────────────────────────────────────────────────────────────────
// Finish — collect + save
// ─────────────────────────────────────────────────────────────────
async function finishSetup() {
  const btn = event.currentTarget;
  btn.innerHTML = '<span class="spin"></span> Saving…';
  btn.disabled = true;
  hideBanner('finish-banner');

  const selectedModel = document.getElementById('model_select').value
    || (selectedProvider === 'openai' ? v('openai_model_input') : v('gemini_model_input'));

  const config = {
    action: 'save_settings',
    rss_feeds: [{
      url:      v('feed_url'),
      name:     v('feed_name') || 'My Feed',
      language: v('feed_lang'),
      enabled:  true,
      wp_site:  '',
    }],
    ai: {
      provider:        selectedProvider,
      openai_api_key:  v('openai_key'),
      openai_model:    selectedProvider === 'openai' ? (selectedModel || 'gpt-4o') : 'gpt-4o',
      gemini_api_key:  v('gemini_key'),
      gemini_model:    selectedProvider === 'gemini' ? (selectedModel || 'gemini-2.0-flash') : 'gemini-2.0-flash',
      timeout:         60,
      tone:            'professional',
      min_words:       600,
      custom_prompts:  { rewrite: '', title: '', excerpt: '', seo_meta: '' },
    },
    pixabay: {
      api_key:     v('pixabay_key'),
      image_type:  'photo',
      orientation: 'horizontal',
      min_width:   1280,
      per_page:    5,
    },
    image: IMG_CFG,
    wordpress: {
      enabled:      v('wp_site_url') !== '',
      site_url:     v('wp_site_url'),
      username:     v('wp_username'),
      app_password: v('wp_app_password'),
      status:       'publish',
      category:     [1],
      author_id:    1,
    },
    custom_site: CUSTOM_CFG,
    email:       EMAIL_CFG,
    general: {
      language:         v('feed_lang'),
      posts_per_run:    parseInt(v('posts_per_run')) || 3,
      interval_hours:   parseInt(v('interval_hours')) || 6,
      draft_mode:       false,
      pause:            false,
      posted_log:       LOG_PATHS.posted_log,
      error_log:        LOG_PATHS.error_log,
      activity_log:     LOG_PATHS.activity_log,
      duplicate_check:  true,
    },
  };

  try {
    const res = await post(config);
    if (res.success) {
      showBanner('finish-banner', 'success', 'Setup complete! Redirecting…');
      setTimeout(() => { window.location.href = 'index.php'; }, 1200);
    } else {
      showBanner('finish-banner', 'error', res.message || 'Save failed');
      btn.textContent = 'Finish Setup ✓';
      btn.disabled = false;
    }
  } catch(e) {
    showBanner('finish-banner', 'error', e.message);
    btn.textContent = 'Finish Setup ✓';
    btn.disabled = false;
  }
}

// ─────────────────────────────────────────────────────────────────
// Utilities
// ─────────────────────────────────────────────────────────────────
function v(id) {
  const el = document.getElementById(id);
  return el ? el.value.trim() : '';
}

function esc(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

async function post(data) {
  const res = await fetch(API, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  });
  if (!res.ok) throw new Error('HTTP ' + res.status);
  return res.json();
}

// ─────────────────────────────────────────────────────────────────
// Boot
// ─────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('step-' + currentStep).classList.add('active');
  renderDots();
  updateCron();
});
</script>
</body>
</html>
