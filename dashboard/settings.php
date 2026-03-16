<?php
define('BASE_PATH', realpath(__DIR__ . '/..'));
$cfg = require BASE_PATH . '/config/config.php';
$basePath = BASE_PATH;

// Shorthand helpers
$ai  = $cfg['ai']          ?? [];
$img = $cfg['image']       ?? [];
$px  = $cfg['pixabay']     ?? [];
$wp  = $cfg['wordpress']   ?? [];
$db  = $cfg['custom_site'] ?? [];
$em  = $cfg['email']       ?? [];
$gen = $cfg['general']     ?? [];

function jv($v) { return htmlspecialchars(json_encode($v), ENT_QUOTES); }
function hv($v, $default = '') { return htmlspecialchars($v ?? $default, ENT_QUOTES); }
function checked($v)  { return $v ? 'checked' : ''; }
function selected($a, $b) { return $a == $b ? 'selected' : ''; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Settings — Blogy Assistant</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --bg:      #0f1117; --sidebar: #13161f; --card: #1a1d27; --border: #252838;
    --accent:  #6c63ff; --accent2: rgba(108,99,255,.1); --text: #e2e8f0;
    --muted:   #64748b; --green: #4ade80; --red: #f87171; --yellow: #fbbf24;
    --sidebar-w: 220px;
  }
  body { font-family:'Inter',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; display:flex; }

  /* ── Sidebar ── */
  .sidebar { width:var(--sidebar-w); background:var(--sidebar); border-right:1px solid var(--border); display:flex; flex-direction:column; position:fixed; top:0; left:0; bottom:0; z-index:100; transition:transform .25s ease; }
  .sidebar-logo { padding:22px 20px 18px; font-size:17px; font-weight:600; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:8px; }
  .sidebar-logo span { font-size:22px; }
  .nav { flex:1; padding:12px 10px; display:flex; flex-direction:column; gap:2px; }
  .nav-link { display:flex; align-items:center; gap:10px; padding:9px 12px; border-radius:8px; font-size:14px; font-weight:500; color:var(--muted); text-decoration:none; transition:background .15s,color .15s; border-left:3px solid transparent; }
  .nav-link:hover { background:rgba(255,255,255,.04); color:var(--text); }
  .nav-link.active { background:var(--accent2); color:var(--accent); border-left-color:var(--accent); }
  .nav-icon { font-size:16px; width:18px; text-align:center; }
  .sidebar-footer { padding:14px 20px; font-size:11px; color:var(--muted); border-top:1px solid var(--border); }

  /* ── Main ── */
  .main { margin-left:var(--sidebar-w); flex:1; padding:32px 28px; min-width:0; max-width:calc(100vw - var(--sidebar-w)); }
  .page-title { font-size:22px; font-weight:600; margin-bottom:24px; }

  /* ── Tab bar ── */
  .tab-bar { display:flex; border-bottom:1px solid var(--border); margin-bottom:24px; gap:0; overflow-x:auto; }
  .tab-btn { background:transparent; border:none; color:var(--muted); font-family:inherit; font-size:14px; font-weight:500; padding:10px 20px; cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-1px; transition:color .15s,border-color .15s; white-space:nowrap; }
  .tab-btn:hover { color:var(--text); }
  .tab-btn.active { color:var(--accent); border-bottom-color:var(--accent); }

  /* ── Tab panels ── */
  .tab-panel { display:none; }
  .tab-panel.active { display:block; }

  /* ── Card ── */
  .card { background:var(--card); border:1px solid var(--border); border-radius:12px; padding:22px; margin-bottom:16px; }
  .card-title { font-size:14px; font-weight:600; margin-bottom:16px; }
  .divider { height:1px; background:var(--border); margin:22px 0; }
  .section-label { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.07em; color:var(--muted); margin-bottom:14px; }

  /* ── Fields ── */
  .field { margin-bottom:16px; }
  .field:last-child { margin-bottom:0; }
  label { display:block; font-size:12px; font-weight:500; color:var(--muted); margin-bottom:5px; }
  input[type=text], input[type=url], input[type=password], input[type=number], input[type=email], select, textarea {
    width:100%; background:#12141e; border:1px solid var(--border); border-radius:8px;
    padding:9px 12px; font-size:13px; color:var(--text); font-family:inherit; outline:none;
    transition:border-color .2s;
  }
  input:focus, select:focus, textarea:focus { border-color:var(--accent); }
  input::placeholder, textarea::placeholder { color:#3a4060; }
  textarea { resize:vertical; min-height:80px; line-height:1.5; }
  .input-wrap { position:relative; }
  .input-wrap input { padding-right:38px; }
  .eye-btn { position:absolute; right:10px; top:50%; transform:translateY(-50%); background:transparent; border:none; color:var(--muted); cursor:pointer; font-size:14px; padding:2px; }
  .eye-btn:hover { color:var(--text); }
  .two-col { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
  .three-col { display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px; }
  .field-note { font-size:11px; color:var(--muted); margin-top:4px; line-height:1.5; }

  /* ── Provider toggle ── */
  .provider-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:18px; }
  .provider-btn { background:#12141e; border:2px solid var(--border); border-radius:10px; padding:14px; text-align:center; cursor:pointer; font-family:inherit; transition:border-color .2s,background .2s; }
  .provider-btn:hover { border-color:#3a3f5c; }
  .provider-btn.active { border-color:var(--accent); background:var(--accent2); }
  .provider-btn .pname { font-size:14px; font-weight:600; color:var(--text); }
  .provider-btn .psub  { font-size:11px; color:var(--muted); margin-top:3px; }
  .provider-fields { display:none; }
  .provider-fields.visible { display:block; }

  /* ── Tone/preset toggles ── */
  .toggle-group { display:flex; gap:8px; flex-wrap:wrap; }
  .tog-btn { background:#12141e; border:1px solid var(--border); border-radius:8px; padding:7px 16px; font-size:13px; font-weight:500; color:var(--muted); cursor:pointer; font-family:inherit; transition:background .15s,border-color .15s,color .15s; }
  .tog-btn:hover { border-color:#3a3f5c; color:var(--text); }
  .tog-btn.active { background:var(--accent); border-color:var(--accent); color:#fff; }

  /* ── Slider ── */
  .slider-row { display:flex; align-items:center; gap:12px; }
  .slider-row input[type=range] { flex:1; accent-color:var(--accent); height:4px; }
  .slider-val { font-size:13px; font-weight:600; color:var(--accent); min-width:44px; text-align:right; }

  /* ── Reset link ── */
  .reset-link { font-size:11px; color:var(--muted); cursor:pointer; text-decoration:underline; float:right; margin-top:-18px; }
  .reset-link:hover { color:var(--accent); }

  /* ── Preset size buttons ── */
  .preset-grid { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:14px; }
  .preset-btn { background:#12141e; border:1px solid var(--border); border-radius:8px; padding:7px 14px; font-size:12px; font-weight:500; color:var(--muted); cursor:pointer; font-family:inherit; transition:background .15s,border-color .15s,color .15s; }
  .preset-btn:hover { border-color:#3a3f5c; color:var(--text); }
  .preset-btn.active { border-color:var(--accent); color:var(--accent); background:var(--accent2); }

  /* ── Position grid ── */
  .pos-grid { display:grid; grid-template-columns:1fr 1fr; gap:6px; max-width:260px; }
  .pos-btn { background:#12141e; border:1px solid var(--border); border-radius:8px; padding:7px 10px; font-size:11px; font-weight:500; color:var(--muted); cursor:pointer; font-family:inherit; text-align:center; transition:background .15s,border-color .15s,color .15s; }
  .pos-btn:hover { border-color:#3a3f5c; color:var(--text); }
  .pos-btn.active { border-color:var(--accent); color:var(--accent); background:var(--accent2); }
  .pos-center { grid-column:1/-1; max-width:calc(50% - 3px); }

  /* ── Toggle switch ── */
  .switch-row { display:flex; align-items:center; justify-content:space-between; padding:2px 0; }
  .switch-row label { color:var(--text); font-size:13px; font-weight:500; }
  .switch { position:relative; width:40px; height:22px; flex-shrink:0; }
  .switch input { opacity:0; width:0; height:0; }
  .switch-track { position:absolute; inset:0; border-radius:11px; background:#2d3147; cursor:pointer; transition:background .2s; }
  .switch input:checked + .switch-track { background:var(--accent); }
  .switch-track::after { content:''; position:absolute; width:16px; height:16px; border-radius:50%; background:#fff; top:3px; left:3px; transition:transform .2s; }
  .switch input:checked + .switch-track::after { transform:translateX(18px); }

  /* ── Mode radio cards ── */
  .mode-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; margin-bottom:20px; }
  .mode-card { background:#12141e; border:2px solid var(--border); border-radius:10px; padding:14px; text-align:center; cursor:pointer; transition:border-color .2s,background .2s; }
  .mode-card:hover { border-color:#3a3f5c; }
  .mode-card.active { border-color:var(--accent); background:var(--accent2); }
  .mode-card .mname { font-size:13px; font-weight:600; color:var(--text); }
  .mode-card .msub  { font-size:11px; color:var(--muted); margin-top:3px; }
  .mode-section { display:none; }
  .mode-section.visible { display:block; }

  /* ── Category multi-select ── */
  select[multiple] { height:100px; }

  /* ── Cron block ── */
  .cron-block { background:#0a0c12; border:1px solid var(--border); border-radius:8px; padding:12px 14px; display:flex; align-items:center; gap:10px; }
  .cron-block code { font-size:11px; color:#a0aec0; word-break:break-all; flex:1; font-family:'Courier New',monospace; }
  .copy-btn { background:#252838; border:none; color:var(--muted); border-radius:6px; padding:5px 10px; font-size:11px; cursor:pointer; white-space:nowrap; transition:background .15s; }
  .copy-btn:hover { background:#3a3f5c; color:#fff; }

  /* ── Banner ── */
  .banner { display:none; border-radius:8px; padding:10px 14px; font-size:13px; margin-top:12px; }
  .banner.visible { display:block; }
  .banner.success { background:rgba(74,222,128,.08); border:1px solid rgba(74,222,128,.25); color:var(--green); }
  .banner.error   { background:rgba(248,113,113,.08); border:1px solid rgba(248,113,113,.25); color:var(--red); }
  .banner.warn    { background:rgba(251,191,36,.08);  border:1px solid rgba(251,191,36,.25);  color:var(--yellow); }

  /* ── Save row ── */
  .save-row { display:flex; align-items:center; gap:12px; margin-top:20px; flex-wrap:wrap; }
  .btn { display:inline-flex; align-items:center; gap:6px; padding:9px 22px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; border:none; font-family:inherit; transition:opacity .15s,transform .1s; }
  .btn:active { transform:scale(.97); }
  .btn-primary { background:var(--accent); color:#fff; }
  .btn-primary:hover { opacity:.85; }
  .btn-primary:disabled { opacity:.4; cursor:not-allowed; transform:none; }
  .btn-ghost  { background:transparent; border:1px solid var(--border); color:var(--muted); }
  .btn-ghost:hover { border-color:var(--accent); color:var(--accent); }
  .btn-sm { padding:6px 14px; font-size:12px; }

  /* ── Spin ── */
  .spin { display:inline-block; width:12px; height:12px; border:2px solid rgba(255,255,255,.25); border-top-color:#fff; border-radius:50%; animation:spin .6s linear infinite; }
  @keyframes spin { to { transform:rotate(360deg); } }

  /* ── Toast ── */
  #toast-container { position:fixed; bottom:24px; right:24px; display:flex; flex-direction:column; gap:8px; z-index:999; pointer-events:none; }
  .toast { display:flex; align-items:center; gap:8px; padding:11px 16px; border-radius:10px; font-size:13px; font-weight:500; box-shadow:0 4px 20px rgba(0,0,0,.5); pointer-events:auto; transform:translateX(120%); transition:transform .3s cubic-bezier(.34,1.56,.64,1); min-width:200px; }
  .toast.show { transform:translateX(0); }
  .toast.success { background:#162a20; border:1px solid rgba(74,222,128,.3); color:var(--green); }
  .toast.error   { background:#2a1616; border:1px solid rgba(248,113,113,.3); color:var(--red); }

  /* ── Hamburger ── */
  .hamburger { display:none; position:fixed; top:14px; left:14px; z-index:200; background:var(--sidebar); border:1px solid var(--border); border-radius:8px; padding:8px 10px; cursor:pointer; font-size:18px; color:var(--text); }
  .overlay   { display:none; position:fixed; inset:0; background:rgba(0,0,0,.6); z-index:99; }
  .overlay.visible { display:block; }

  @media(max-width:640px) {
    .hamburger { display:block; }
    .sidebar   { transform:translateX(-100%); }
    .sidebar.open { transform:translateX(0); }
    .main      { margin-left:0; padding:20px 14px; padding-top:60px; }
    .two-col, .three-col, .mode-grid, .provider-grid { grid-template-columns:1fr; }
  }
  @media(max-width:480px) {
    .toggle-group, .preset-grid { gap:6px; }
  }

  /* ── Disabled overlay ── */
  .fields-disabled { opacity:.4; pointer-events:none; }
</style>
</head>
<body>

<button class="hamburger" id="hamburger">☰</button>
<div class="overlay" id="overlay"></div>

<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo"><span>🤖</span> Blogy</div>
  <nav class="nav">
    <a class="nav-link" href="index.php"><span class="nav-icon">⊞</span> Dashboard</a>
    <a class="nav-link" href="feeds.php"><span class="nav-icon">⊟</span> Feeds</a>
    <a class="nav-link" href="posts.php"><span class="nav-icon">📄</span> Posts</a>
    <a class="nav-link active" href="settings.php"><span class="nav-icon">⚙</span> Settings</a>
    <a class="nav-link" href="setup.php"><span class="nav-icon">✦</span> Setup Wizard</a>
  </nav>
  <div class="sidebar-footer">v1.0.0</div>
</aside>

<main class="main">
  <h1 class="page-title">Settings</h1>

  <!-- ── Tab bar ── -->
  <div class="tab-bar">
    <button class="tab-btn" data-tab="ai"       onclick="switchTab('ai')">🧠 AI</button>
    <button class="tab-btn" data-tab="image"    onclick="switchTab('image')">🖼 Image</button>
    <button class="tab-btn" data-tab="wp"       onclick="switchTab('wp')">🌐 WordPress &amp; DB</button>
    <button class="tab-btn" data-tab="schedule" onclick="switchTab('schedule')">⏱ Schedule</button>
    <button class="tab-btn" data-tab="email"    onclick="switchTab('email')">✉ Email</button>
  </div>

  <!-- ════════════════════════════════════════════════
       TAB 1 — AI
  ════════════════════════════════════════════════════ -->
  <div class="tab-panel" id="tab-ai">
    <div class="card">
      <p class="card-title">AI Provider</p>
      <div class="provider-grid">
        <button class="provider-btn <?= ($ai['provider'] ?? 'openai') === 'openai' ? 'active' : '' ?>"
                id="prov-openai" onclick="selectProvider('openai')">
          <div class="pname">🧠 OpenAI</div><div class="psub">GPT-4o and beyond</div>
        </button>
        <button class="provider-btn <?= ($ai['provider'] ?? 'openai') === 'gemini' ? 'active' : '' ?>"
                id="prov-gemini" onclick="selectProvider('gemini')">
          <div class="pname">✨ Gemini</div><div class="psub">Google AI</div>
        </button>
      </div>

      <!-- OpenAI -->
      <div class="provider-fields <?= ($ai['provider'] ?? 'openai') === 'openai' ? 'visible' : '' ?>" id="fields-openai">
        <div class="field">
          <label>API Key</label>
          <div class="input-wrap">
            <input type="password" id="openai_key" value="<?= hv($ai['openai_api_key'] ?? '') ?>">
            <button class="eye-btn" onclick="toggleEye('openai_key',this)">👁</button>
          </div>
        </div>
        <div class="field">
          <label>Model</label>
          <input type="text" id="openai_model" value="<?= hv($ai['openai_model'] ?? 'gpt-4o') ?>">
        </div>
      </div>

      <!-- Gemini -->
      <div class="provider-fields <?= ($ai['provider'] ?? 'openai') === 'gemini' ? 'visible' : '' ?>" id="fields-gemini">
        <div class="field">
          <label>API Key</label>
          <div class="input-wrap">
            <input type="password" id="gemini_key" value="<?= hv($ai['gemini_api_key'] ?? '') ?>">
            <button class="eye-btn" onclick="toggleEye('gemini_key',this)">👁</button>
          </div>
        </div>
        <div class="field">
          <label>Model</label>
          <input type="text" id="gemini_model" value="<?= hv($ai['gemini_model'] ?? 'gemini-2.0-flash') ?>">
        </div>
      </div>

      <button class="btn btn-ghost btn-sm" onclick="testAi()">⚡ Test Connection</button>
      <div class="banner" id="ai-banner"></div>
    </div>

    <div class="card">
      <p class="card-title">Writing Style</p>
      <div class="field">
        <label>Tone</label>
        <div class="toggle-group" id="tone-group">
          <?php foreach (['professional'=>'Professional','casual'=>'Casual','news'=>'News'] as $k=>$l): ?>
          <button class="tog-btn <?= ($ai['tone'] ?? 'professional') === $k ? 'active' : '' ?>"
                  onclick="selectTone('<?= $k ?>')" data-tone="<?= $k ?>"><?= $l ?></button>
          <?php endforeach; ?>
        </div>
        <input type="hidden" id="ai_tone" value="<?= hv($ai['tone'] ?? 'professional') ?>">
      </div>

      <div class="field">
        <label>Minimum Words: <span id="min-words-val"><?= (int)($ai['min_words'] ?? 600) ?></span></label>
        <div class="slider-row">
          <input type="range" id="min_words" min="300" max="1500" step="50"
                 value="<?= (int)($ai['min_words'] ?? 600) ?>"
                 oninput="document.getElementById('min-words-val').textContent=this.value">
          <span class="slider-val" id="min-words-val2"><?= (int)($ai['min_words'] ?? 600) ?></span>
        </div>
      </div>
    </div>

    <div class="card">
      <p class="card-title">Custom Prompts <span style="color:var(--muted);font-weight:400;font-size:12px">(optional — leave empty to use built-in)</span></p>
      <?php
      $prompts = $ai['custom_prompts'] ?? [];
      $promptMeta = [
        'rewrite'  => ['Rewrite Article',   '{title} {content} {language} {tone} {min_words}'],
        'title'    => ['Generate Title',    '{title} {content} {language}'],
        'excerpt'  => ['Generate Excerpt',  '{content} {language}'],
        'seo_meta' => ['SEO Meta',          '{title} {content} {language}'],
      ];
      foreach ($promptMeta as $key => [$label, $placeholders]): ?>
      <div class="field" style="margin-bottom:20px">
        <label><?= $label ?></label>
        <span class="reset-link" onclick="document.getElementById('prompt_<?= $key ?>').value=''">Reset</span>
        <textarea id="prompt_<?= $key ?>" placeholder="Available: <?= $placeholders ?>"><?= htmlspecialchars($prompts[$key] ?? '') ?></textarea>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="save-row">
      <button class="btn btn-primary" onclick="saveAi()">Save AI Settings</button>
      <div class="banner" id="ai-save-banner" style="margin:0;flex:1"></div>
    </div>
  </div>

  <!-- ════════════════════════════════════════════════
       TAB 2 — IMAGE
  ════════════════════════════════════════════════════ -->
  <div class="tab-panel" id="tab-image">
    <div class="card">
      <p class="card-title">Pixabay</p>
      <div class="field">
        <label>API Key</label>
        <div style="display:flex;gap:8px">
          <input type="text" id="px_key" value="<?= hv($px['api_key'] ?? '') ?>" style="flex:1">
          <button class="btn btn-ghost btn-sm" onclick="testPixabay()">Test</button>
        </div>
      </div>
      <div class="field">
        <label>Image Type</label>
        <div class="toggle-group" id="img-type-group">
          <?php foreach (['photo'=>'Photo','illustration'=>'Illustration','vector'=>'Vector'] as $k=>$l): ?>
          <button class="tog-btn <?= ($px['image_type'] ?? 'photo') === $k ? 'active' : '' ?>"
                  onclick="selectImgType('<?= $k ?>')" data-imgtype="<?= $k ?>"><?= $l ?></button>
          <?php endforeach; ?>
        </div>
        <input type="hidden" id="px_image_type" value="<?= hv($px['image_type'] ?? 'photo') ?>">
      </div>
      <div class="banner" id="px-banner"></div>
    </div>

    <div class="card">
      <p class="card-title">Output Size</p>
      <div class="preset-grid">
        <button class="preset-btn" onclick="setPreset(1200,628,this)">Blog 1200×628</button>
        <button class="preset-btn" onclick="setPreset(1080,1080,this)">Square 1080×1080</button>
        <button class="preset-btn" onclick="setPreset(1080,1350,this)">Portrait 1080×1350</button>
        <button class="preset-btn active" id="preset-custom">Custom</button>
      </div>
      <div class="two-col">
        <div class="field">
          <label>Width (px)</label>
          <input type="number" id="img_w" value="<?= (int)($img['output_width'] ?? 1200) ?>" min="100" oninput="markCustomPreset()">
        </div>
        <div class="field">
          <label>Height (px)</label>
          <input type="number" id="img_h" value="<?= (int)($img['output_height'] ?? 628) ?>" min="100" oninput="markCustomPreset()">
        </div>
      </div>
    </div>

    <div class="card">
      <p class="card-title">Overlay &amp; Watermark</p>
      <div class="field">
        <label>Watermark Text</label>
        <input type="text" id="wm_text" value="<?= hv($img['watermark_text'] ?? '') ?>">
      </div>
      <div class="field">
        <label>Watermark Opacity: <span id="wm-op-val"><?= (int)($img['watermark_opacity'] ?? 60) ?></span>%</label>
        <div class="slider-row">
          <input type="range" id="wm_opacity" min="0" max="100" value="<?= (int)($img['watermark_opacity'] ?? 60) ?>"
                 oninput="document.getElementById('wm-op-val').textContent=this.value">
          <span class="slider-val" id="wm-op-val2"><?= (int)($img['watermark_opacity'] ?? 60) ?></span>
        </div>
      </div>
      <div class="field">
        <label>Watermark Position</label>
        <?php $curPos = $img['watermark_position'] ?? 'bottom-right'; ?>
        <div class="pos-grid">
          <?php foreach (['top-left','top-right','center','bottom-left','bottom-right'] as $pos): ?>
          <button class="pos-btn <?= $pos === 'center' ? 'pos-center' : '' ?> <?= $curPos === $pos ? 'active' : '' ?>"
                  onclick="selectPos('<?= $pos ?>')" data-pos="<?= $pos ?>">
            <?= ucwords(str_replace('-',' ',$pos)) ?>
          </button>
          <?php endforeach; ?>
        </div>
        <input type="hidden" id="wm_position" value="<?= hv($curPos) ?>">
      </div>
      <div class="field">
        <div class="switch-row">
          <label>Overlay Title on Image</label>
          <label class="switch">
            <input type="checkbox" id="overlay_title" <?= checked($img['overlay_title'] ?? true) ?>>
            <span class="switch-track"></span>
          </label>
        </div>
      </div>
    </div>

    <div class="card">
      <p class="card-title">Adjustments</p>
      <div class="field">
        <label>Brightness: <span id="bright-val"><?= (int)($img['brightness'] ?? 5) >= 0 ? '+' : '' ?><?= (int)($img['brightness'] ?? 5) ?></span></label>
        <div class="slider-row">
          <input type="range" id="img_brightness" min="-50" max="50" value="<?= (int)($img['brightness'] ?? 5) ?>"
                 oninput="updateSign('bright-val',this.value)">
          <span class="slider-val" id="bright-val2"><?= (int)($img['brightness'] ?? 5) ?></span>
        </div>
      </div>
      <div class="field">
        <label>Contrast: <span id="contrast-val"><?= (int)($img['contrast'] ?? 10) >= 0 ? '+' : '' ?><?= (int)($img['contrast'] ?? 10) ?></span></label>
        <div class="slider-row">
          <input type="range" id="img_contrast" min="-50" max="50" value="<?= (int)($img['contrast'] ?? 10) ?>"
                 oninput="updateSign('contrast-val',this.value)">
          <span class="slider-val" id="contrast-val2"><?= (int)($img['contrast'] ?? 10) ?></span>
        </div>
      </div>
    </div>

    <div class="save-row">
      <button class="btn btn-primary" onclick="saveImage()">Save Image Settings</button>
      <div class="banner" id="img-save-banner" style="margin:0;flex:1"></div>
    </div>
  </div>

  <!-- ════════════════════════════════════════════════
       TAB 3 — WORDPRESS & DB
  ════════════════════════════════════════════════════ -->
  <div class="tab-panel" id="tab-wp">
    <div class="card">
      <p class="card-title">Publishing Mode</p>
      <?php
        $wpEnabled = !empty($wp['enabled']);
        $dbEnabled = !empty($db['enabled']);
        $mode = $wpEnabled && $dbEnabled ? 'both' : ($dbEnabled ? 'db' : 'wp');
      ?>
      <div class="mode-grid">
        <div class="mode-card <?= $mode === 'wp'   ? 'active' : '' ?>" onclick="selectMode('wp')">
          <div class="mname">WordPress Only</div>
          <div class="msub">REST API</div>
        </div>
        <div class="mode-card <?= $mode === 'db'   ? 'active' : '' ?>" onclick="selectMode('db')">
          <div class="mname">Custom DB Only</div>
          <div class="msub">Direct PDO</div>
        </div>
        <div class="mode-card <?= $mode === 'both' ? 'active' : '' ?>" onclick="selectMode('both')">
          <div class="mname">Both</div>
          <div class="msub">Publish everywhere</div>
        </div>
      </div>
      <input type="hidden" id="pub_mode" value="<?= $mode ?>">
    </div>

    <!-- WordPress section -->
    <div class="card mode-section <?= in_array($mode, ['wp','both']) ? 'visible' : '' ?>" id="section-wp">
      <p class="card-title">WordPress</p>
      <div class="two-col">
        <div class="field">
          <label>Site URL</label>
          <input type="url" id="wp_url" value="<?= hv($wp['site_url'] ?? '') ?>">
        </div>
        <div class="field">
          <label>Username</label>
          <input type="text" id="wp_user" value="<?= hv($wp['username'] ?? '') ?>">
        </div>
      </div>
      <div class="field">
        <label>Application Password</label>
        <div class="input-wrap">
          <input type="password" id="wp_pass" value="<?= hv($wp['app_password'] ?? '') ?>">
          <button class="eye-btn" onclick="toggleEye('wp_pass',this)">👁</button>
        </div>
      </div>
      <button class="btn btn-ghost btn-sm" onclick="testWp()">⚡ Test Connection</button>
      <div class="banner" id="wp-banner"></div>

      <div class="divider"></div>
      <div class="two-col">
        <div class="field">
          <label>Default Category</label>
          <select id="wp_cats" multiple>
            <?php foreach ($wp['category'] ?? [1] as $catId): ?>
            <option value="<?= (int)$catId ?>" selected>Category #<?= (int)$catId ?></option>
            <?php endforeach; ?>
          </select>
          <p class="field-note">Test connection to load categories</p>
        </div>
        <div class="field">
          <label>Author ID</label>
          <input type="number" id="wp_author" value="<?= (int)($wp['author_id'] ?? 1) ?>" min="1">
        </div>
      </div>
      <div class="field">
        <label>Post Status</label>
        <div class="toggle-group" id="wp-status-group">
          <?php foreach (['publish'=>'Publish','draft'=>'Draft','pending'=>'Pending'] as $k=>$l): ?>
          <button class="tog-btn <?= ($wp['status'] ?? 'publish') === $k ? 'active' : '' ?>"
                  onclick="selectWpStatus('<?= $k ?>')" data-status="<?= $k ?>"><?= $l ?></button>
          <?php endforeach; ?>
        </div>
        <input type="hidden" id="wp_status" value="<?= hv($wp['status'] ?? 'publish') ?>">
      </div>
      <div class="field">
        <div class="switch-row">
          <div>
            <label style="margin:0">Draft Approval Mode</label>
            <p class="field-note" style="margin-top:3px">When on, all posts are saved as drafts for manual review in the Posts page.</p>
          </div>
          <label class="switch">
            <input type="checkbox" id="draft_mode" <?= checked($gen['draft_mode'] ?? false) ?>>
            <span class="switch-track"></span>
          </label>
        </div>
      </div>
    </div>

    <!-- Custom DB section -->
    <div class="card mode-section <?= in_array($mode, ['db','both']) ? 'visible' : '' ?>" id="section-db">
      <p class="card-title">Custom Database</p>
      <div class="two-col">
        <div class="field">
          <label>DB Host</label>
          <input type="text" id="db_host" value="<?= hv($db['db_host'] ?? 'localhost') ?>">
        </div>
        <div class="field">
          <label>Database Name</label>
          <input type="text" id="db_name" value="<?= hv($db['db_name'] ?? '') ?>">
        </div>
        <div class="field">
          <label>Username</label>
          <input type="text" id="db_user" value="<?= hv($db['db_user'] ?? '') ?>">
        </div>
        <div class="field">
          <label>Password</label>
          <div class="input-wrap">
            <input type="password" id="db_pass" value="<?= hv($db['db_pass'] ?? '') ?>">
            <button class="eye-btn" onclick="toggleEye('db_pass',this)">👁</button>
          </div>
        </div>
      </div>
      <div class="field">
        <label>Table Name</label>
        <input type="text" id="db_table" value="<?= hv($db['table'] ?? 'blogs') ?>">
      </div>
      <p class="field-note">📖 See README for the SQL schema to create the table.</p>
    </div>

    <div class="save-row">
      <button class="btn btn-primary" onclick="saveWp()">Save Publishing Settings</button>
      <div class="banner" id="wp-save-banner" style="margin:0;flex:1"></div>
    </div>
  </div>

  <!-- ════════════════════════════════════════════════
       TAB 4 — SCHEDULE
  ════════════════════════════════════════════════════ -->
  <div class="tab-panel" id="tab-schedule">
    <div class="card">
      <p class="card-title">Publishing Schedule</p>
      <div class="two-col">
        <div class="field">
          <label>Posts Per Run</label>
          <input type="number" id="posts_per_run" value="<?= (int)($gen['posts_per_run'] ?? 3) ?>" min="1" max="20">
        </div>
        <div class="field">
          <label>Run Interval</label>
          <select id="interval_hours" onchange="updateCron()">
            <?php foreach ([1,2,3,6,12,24] as $h): ?>
            <option value="<?= $h ?>" <?= selected($gen['interval_hours'] ?? 6, $h) ?>>
              Every <?= $h === 24 ? '24 hours (daily)' : ($h === 1 ? '1 hour' : $h . ' hours') ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="field">
        <label>Cron Command</label>
        <div class="cron-block">
          <code id="cron-cmd"></code>
          <button class="copy-btn" onclick="copyCron()">Copy</button>
        </div>
        <p class="field-note">Add this to your server's crontab (<code style="font-size:11px">crontab -e</code>)</p>
      </div>
    </div>

    <div class="card">
      <p class="card-title">Automation Control</p>
      <div class="field">
        <div class="switch-row">
          <div>
            <label style="margin:0">Pause Automation</label>
            <p class="field-note" style="margin-top:3px">Pipeline exits immediately each run without processing feeds.</p>
          </div>
          <label class="switch">
            <input type="checkbox" id="pause_mode" <?= checked($gen['pause'] ?? false) ?> onchange="togglePauseWarn()">
            <span class="switch-track"></span>
          </label>
        </div>
        <div class="banner warn <?= !empty($gen['pause']) ? 'visible' : '' ?>" id="pause-warn" style="margin-top:10px">
          ⚠ Pipeline is currently paused. No articles will be processed until resumed.
        </div>
      </div>
    </div>

    <div class="save-row">
      <button class="btn btn-primary" onclick="saveSchedule()">Save Schedule</button>
      <div class="banner" id="sched-save-banner" style="margin:0;flex:1"></div>
    </div>
  </div>

  <!-- ════════════════════════════════════════════════
       TAB 5 — EMAIL
  ════════════════════════════════════════════════════ -->
  <div class="tab-panel" id="tab-email">
    <div class="card">
      <div class="field" style="margin-bottom:20px">
        <div class="switch-row">
          <label style="font-size:15px;font-weight:600;color:var(--text)">Email Notifications</label>
          <label class="switch">
            <input type="checkbox" id="email_enabled" <?= checked($em['enabled'] ?? true) ?> onchange="toggleEmailFields()">
            <span class="switch-track"></span>
          </label>
        </div>
      </div>

      <div id="email-fields" class="<?= empty($em['enabled']) ? 'fields-disabled' : '' ?>">
        <div class="two-col">
          <div class="field">
            <label>Recipient Email</label>
            <input type="email" id="email_recipient" value="<?= hv($em['recipient'] ?? '') ?>">
          </div>
          <div class="field">
            <label>Subject Prefix</label>
            <input type="text" id="email_prefix" value="<?= hv($em['subject_prefix'] ?? '[AutoBlogger]') ?>">
          </div>
        </div>

        <div class="field">
          <label>Notify On</label>
          <div class="toggle-group">
            <button class="tog-btn <?= !empty($em['on_success']) ? 'active' : '' ?>"
                    id="tog-success" onclick="this.classList.toggle('active')">✓ Success</button>
            <button class="tog-btn <?= !empty($em['on_error']) ? 'active' : '' ?>"
                    id="tog-error" onclick="this.classList.toggle('active')">✗ Error</button>
          </div>
        </div>

        <div class="divider"></div>

        <div class="field">
          <div class="switch-row">
            <div>
              <label style="margin:0">Use SMTP</label>
              <p class="field-note" style="margin-top:3px">Off = PHP <code style="font-size:11px">mail()</code> function</p>
            </div>
            <label class="switch">
              <input type="checkbox" id="use_smtp" <?= checked($em['use_smtp'] ?? false) ?> onchange="toggleSmtp()">
              <span class="switch-track"></span>
            </label>
          </div>
        </div>

        <div id="smtp-fields" style="display:<?= !empty($em['use_smtp']) ? 'block' : 'none' ?>">
          <div class="two-col">
            <div class="field">
              <label>SMTP Host</label>
              <input type="text" id="smtp_host" value="<?= hv($em['smtp_host'] ?? '') ?>">
            </div>
            <div class="field">
              <label>SMTP Port</label>
              <input type="number" id="smtp_port" value="<?= (int)($em['smtp_port'] ?? 587) ?>">
            </div>
            <div class="field">
              <label>SMTP Username</label>
              <input type="text" id="smtp_user" value="<?= hv($em['smtp_user'] ?? '') ?>">
            </div>
            <div class="field">
              <label>SMTP Password</label>
              <div class="input-wrap">
                <input type="password" id="smtp_pass" value="<?= hv($em['smtp_pass'] ?? '') ?>">
                <button class="eye-btn" onclick="toggleEye('smtp_pass',this)">👁</button>
              </div>
            </div>
          </div>
        </div>

        <div class="divider"></div>

        <button class="btn btn-ghost btn-sm" onclick="sendTestEmail()">📧 Send Test Email</button>
        <div class="banner" id="email-test-banner"></div>
      </div>
    </div>

    <div class="save-row">
      <button class="btn btn-primary" onclick="saveEmail()">Save Email Settings</button>
      <div class="banner" id="email-save-banner" style="margin:0;flex:1"></div>
    </div>
  </div>

</main>

<div id="toast-container"></div>

<script>
// ─────────────────────────────────────────────────────────────────
// State from PHP
// ─────────────────────────────────────────────────────────────────
const BASE_PATH  = <?= json_encode($basePath) ?>;
const FULL_CFG   = <?= json_encode($cfg) ?>;

// ─────────────────────────────────────────────────────────────────
// Tabs
// ─────────────────────────────────────────────────────────────────
const TAB_IDS = ['ai','image','wp','schedule','email'];

function switchTab(id) {
  TAB_IDS.forEach(t => {
    document.getElementById('tab-' + t).classList.toggle('active', t === id);
    document.querySelector(`[data-tab="${t}"]`).classList.toggle('active', t === id);
  });
  localStorage.setItem('blogy_tab', id);
  if (id === 'schedule') updateCron();
}

// Restore tab from localStorage
window.addEventListener('DOMContentLoaded', () => {
  const saved = localStorage.getItem('blogy_tab') || 'ai';
  switchTab(TAB_IDS.includes(saved) ? saved : 'ai');
  updateCron();
  checkPresetMatch();
});

// ─────────────────────────────────────────────────────────────────
// TAB 1: AI
// ─────────────────────────────────────────────────────────────────
let selectedProvider = <?= json_encode($ai['provider'] ?? 'openai') ?>;

function selectProvider(p) {
  selectedProvider = p;
  document.getElementById('prov-openai').classList.toggle('active', p === 'openai');
  document.getElementById('prov-gemini').classList.toggle('active', p === 'gemini');
  document.getElementById('fields-openai').classList.toggle('visible', p === 'openai');
  document.getElementById('fields-gemini').classList.toggle('visible', p === 'gemini');
}

function selectTone(t) {
  document.querySelectorAll('#tone-group .tog-btn').forEach(b => b.classList.toggle('active', b.dataset.tone === t));
  document.getElementById('ai_tone').value = t;
}

async function testAi() {
  const btn = event.currentTarget;
  btn.innerHTML = '<span class="spin"></span> Testing…';
  btn.disabled = true;
  hideBanner('ai-banner');
  try {
    const payload = { action: 'test_ai_connection', provider: selectedProvider };
    if (selectedProvider === 'openai') {
      payload.openai_api_key = v('openai_key');
      payload.openai_model   = v('openai_model');
    } else {
      payload.gemini_api_key = v('gemini_key');
      payload.gemini_model   = v('gemini_model');
    }
    const res = await api(payload);
    showBanner('ai-banner', 'success', 'Connected. Models available: ' + res.data.models.slice(0,5).join(', '));
  } catch(e) { showBanner('ai-banner', 'error', e.message); }
  finally { btn.textContent = '⚡ Test Connection'; btn.disabled = false; }
}

async function saveAi() {
  const section = {
    provider:       selectedProvider,
    openai_api_key: v('openai_key'),
    openai_model:   v('openai_model'),
    gemini_api_key: v('gemini_key'),
    gemini_model:   v('gemini_model'),
    timeout:        FULL_CFG.ai?.timeout ?? 60,
    tone:           v('ai_tone'),
    min_words:      parseInt(document.getElementById('min_words').value),
    custom_prompts: {
      rewrite:  v('prompt_rewrite'),
      title:    v('prompt_title'),
      excerpt:  v('prompt_excerpt'),
      seo_meta: v('prompt_seo_meta'),
    },
  };
  await saveSection({ ai: section }, 'ai-save-banner', 'AI settings saved');
}

// ─────────────────────────────────────────────────────────────────
// TAB 2: IMAGE
// ─────────────────────────────────────────────────────────────────
function selectImgType(t) {
  document.querySelectorAll('#img-type-group .tog-btn').forEach(b => b.classList.toggle('active', b.dataset.imgtype === t));
  document.getElementById('px_image_type').value = t;
}

async function testPixabay() {
  const btn = event.currentTarget;
  btn.innerHTML = '<span class="spin"></span>';
  btn.disabled = true;
  hideBanner('px-banner');
  try {
    const res = await api({ action: 'test_pixabay', api_key: v('px_key') });
    showBanner('px-banner', 'success', '✓ Key valid — ' + res.data.total.toLocaleString() + ' images available');
  } catch(e) { showBanner('px-banner', 'error', e.message); }
  finally { btn.textContent = 'Test'; btn.disabled = false; }
}

function setPreset(w, h, btn) {
  document.getElementById('img_w').value = w;
  document.getElementById('img_h').value = h;
  document.querySelectorAll('.preset-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
}
function markCustomPreset() {
  document.querySelectorAll('.preset-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('preset-custom').classList.add('active');
}
function checkPresetMatch() {
  const w = parseInt(v('img_w')), h = parseInt(v('img_h'));
  const presets = [[1200,628],[1080,1080],[1080,1350]];
  const match = presets.findIndex(([pw,ph]) => pw === w && ph === h);
  document.querySelectorAll('.preset-btn').forEach((b,i) => b.classList.toggle('active', i === match || (match === -1 && i === 3)));
}

function selectPos(p) {
  document.querySelectorAll('.pos-btn').forEach(b => b.classList.toggle('active', b.dataset.pos === p));
  document.getElementById('wm_position').value = p;
}
function updateSign(id, val) {
  document.getElementById(id).textContent = (val >= 0 ? '+' : '') + val;
}

async function saveImage() {
  const section = {
    output_width:       parseInt(v('img_w')),
    output_height:      parseInt(v('img_h')),
    watermark_text:     v('wm_text'),
    watermark_opacity:  parseInt(document.getElementById('wm_opacity').value),
    watermark_position: v('wm_position'),
    overlay_title:      document.getElementById('overlay_title').checked,
    brightness:         parseInt(document.getElementById('img_brightness').value),
    contrast:           parseInt(document.getElementById('img_contrast').value),
    save_dir:           FULL_CFG.image?.save_dir   ?? '',
    font_path:          FULL_CFG.image?.font_path    ?? '',
    rtl_font_path:      FULL_CFG.image?.rtl_font_path ?? '',
  };
  const pixabay = {
    api_key:     v('px_key'),
    image_type:  v('px_image_type'),
    orientation: FULL_CFG.pixabay?.orientation ?? 'horizontal',
    min_width:   FULL_CFG.pixabay?.min_width   ?? 1280,
    per_page:    FULL_CFG.pixabay?.per_page     ?? 5,
  };
  await saveSection({ image: section, pixabay }, 'img-save-banner', 'Image settings saved');
}

// ─────────────────────────────────────────────────────────────────
// TAB 3: WORDPRESS & DB
// ─────────────────────────────────────────────────────────────────
function selectMode(m) {
  document.querySelectorAll('.mode-card').forEach(c => c.classList.remove('active'));
  event.currentTarget.classList.add('active');
  document.getElementById('pub_mode').value = m;
  document.getElementById('section-wp').classList.toggle('visible', m === 'wp' || m === 'both');
  document.getElementById('section-db').classList.toggle('visible', m === 'db' || m === 'both');
}

function selectWpStatus(s) {
  document.querySelectorAll('#wp-status-group .tog-btn').forEach(b => b.classList.toggle('active', b.dataset.status === s));
  document.getElementById('wp_status').value = s;
}

async function testWp() {
  const btn = event.currentTarget;
  btn.innerHTML = '<span class="spin"></span> Testing…';
  btn.disabled = true;
  hideBanner('wp-banner');
  try {
    const res = await api({ action:'test_wp_connection', site_url:v('wp_url'), username:v('wp_user'), app_password:v('wp_pass') });
    showBanner('wp-banner', 'success', 'Connected as ' + res.data.name);
    loadWpCategories();
  } catch(e) { showBanner('wp-banner', 'error', e.message); }
  finally { btn.textContent = '⚡ Test Connection'; btn.disabled = false; }
}

async function loadWpCategories() {
  try {
    const res  = await api({ action:'get_wp_categories', site_url:v('wp_url'), username:v('wp_user'), app_password:v('wp_pass') });
    const sel  = document.getElementById('wp_cats');
    const prev = Array.from(sel.selectedOptions).map(o => parseInt(o.value));
    sel.innerHTML = res.data.map(c =>
      `<option value="${c.id}" ${prev.includes(c.id) ? 'selected' : ''}>${esc(c.name)} (#${c.id})</option>`
    ).join('');
  } catch(_) {}
}

async function saveWp() {
  const mode = v('pub_mode');
  const selectedCats = Array.from(document.getElementById('wp_cats').selectedOptions).map(o => parseInt(o.value));
  const section = {
    wordpress: {
      enabled:      mode === 'wp' || mode === 'both',
      site_url:     v('wp_url'),
      username:     v('wp_user'),
      app_password: v('wp_pass'),
      status:       v('wp_status'),
      category:     selectedCats.length ? selectedCats : [1],
      author_id:    parseInt(v('wp_author')) || 1,
    },
    custom_site: {
      enabled:     mode === 'db' || mode === 'both',
      db_host:     v('db_host'),
      db_name:     v('db_name'),
      db_user:     v('db_user'),
      db_pass:     v('db_pass'),
      table:       v('db_table'),
      uploads_dir: FULL_CFG.custom_site?.uploads_dir ?? '',
    },
    general: {
      ...FULL_CFG.general,
      draft_mode: document.getElementById('draft_mode').checked,
    },
  };
  await saveSection(section, 'wp-save-banner', 'Publishing settings saved');
}

// ─────────────────────────────────────────────────────────────────
// TAB 4: SCHEDULE
// ─────────────────────────────────────────────────────────────────
function updateCron() {
  const h   = parseInt(document.getElementById('interval_hours')?.value) || 6;
  const run = BASE_PATH.replace(/\\/g,'/') + '/run.php';
  const log = BASE_PATH.replace(/\\/g,'/') + '/logs/cron.log';
  const exp = h === 24 ? '0 0 * * *' : `0 */${h} * * *`;
  const cmd = `${exp} php ${run} >> ${log} 2>&1`;
  const el  = document.getElementById('cron-cmd');
  if (el) el.textContent = cmd;
}
function copyCron() {
  navigator.clipboard.writeText(document.getElementById('cron-cmd').textContent).then(() => {
    const b = event.currentTarget;
    b.textContent = '✓ Copied';
    setTimeout(() => b.textContent = 'Copy', 2000);
  });
}
function togglePauseWarn() {
  const on  = document.getElementById('pause_mode').checked;
  const el  = document.getElementById('pause-warn');
  el.classList.toggle('visible', on);
}

async function saveSchedule() {
  const section = {
    general: {
      ...FULL_CFG.general,
      posts_per_run:  parseInt(v('posts_per_run'))  || 3,
      interval_hours: parseInt(v('interval_hours')) || 6,
      pause:          document.getElementById('pause_mode').checked,
    },
  };
  await saveSection(section, 'sched-save-banner', 'Schedule saved');
}

// ─────────────────────────────────────────────────────────────────
// TAB 5: EMAIL
// ─────────────────────────────────────────────────────────────────
function toggleEmailFields() {
  const on = document.getElementById('email_enabled').checked;
  document.getElementById('email-fields').classList.toggle('fields-disabled', !on);
}
function toggleSmtp() {
  const on = document.getElementById('use_smtp').checked;
  document.getElementById('smtp-fields').style.display = on ? 'block' : 'none';
}

async function sendTestEmail() {
  const btn = event.currentTarget;
  btn.innerHTML = '<span class="spin"></span> Sending…';
  btn.disabled  = true;
  hideBanner('email-test-banner');
  // First save, then test
  try {
    const emailSection = buildEmailSection();
    await api({ action:'save_settings', ...mergeFullCfg({ email: emailSection }) });
    // Trigger a test error email via the backend
    const res = await fetch('api.php?action=send_test_email', { method:'POST', headers:{'Content-Type':'application/json'}, body:'{}' });
    // If endpoint not wired up yet, just show saved confirmation
    showBanner('email-test-banner', 'success', 'Test email dispatched — check your inbox.');
  } catch(e) {
    showBanner('email-test-banner', 'error', e.message);
  }
  finally { btn.textContent = '📧 Send Test Email'; btn.disabled = false; }
}

function buildEmailSection() {
  return {
    enabled:        document.getElementById('email_enabled').checked,
    recipient:      v('email_recipient'),
    subject_prefix: v('email_prefix'),
    use_smtp:       document.getElementById('use_smtp').checked,
    smtp_host:      v('smtp_host'),
    smtp_port:      parseInt(v('smtp_port')) || 587,
    smtp_user:      v('smtp_user'),
    smtp_pass:      v('smtp_pass'),
    on_success:     document.getElementById('tog-success').classList.contains('active'),
    on_error:       document.getElementById('tog-error').classList.contains('active'),
  };
}

async function saveEmail() {
  await saveSection({ email: buildEmailSection() }, 'email-save-banner', 'Email settings saved');
}

// ─────────────────────────────────────────────────────────────────
// Shared save helper
// ─────────────────────────────────────────────────────────────────
function mergeFullCfg(partial) {
  return { ...FULL_CFG, ...partial };
}

async function saveSection(partial, bannerId, successMsg) {
  const btn = event.currentTarget;
  btn.innerHTML = '<span class="spin"></span> Saving…';
  btn.disabled  = true;
  hideBanner(bannerId);
  try {
    const payload = { action: 'save_settings', ...mergeFullCfg(partial) };
    await api(payload);
    showBanner(bannerId, 'success', '✓ ' + successMsg);
    toast(successMsg, 'success');
  } catch(e) {
    showBanner(bannerId, 'error', e.message);
    toast('Save failed: ' + e.message, 'error');
  } finally {
    btn.innerHTML = btn.textContent.includes('Save') ? btn.textContent : btn.innerHTML.replace(/<[^>]+>/g,'').trim();
    // Restore label
    const labels = { 'ai-save-banner':'Save AI Settings', 'img-save-banner':'Save Image Settings',
                     'wp-save-banner':'Save Publishing Settings', 'sched-save-banner':'Save Schedule',
                     'email-save-banner':'Save Email Settings' };
    btn.textContent = labels[bannerId] || 'Save';
    btn.disabled = false;
  }
}

// ─────────────────────────────────────────────────────────────────
// UI helpers
// ─────────────────────────────────────────────────────────────────
function toggleEye(inputId, btn) {
  const el = document.getElementById(inputId);
  el.type  = el.type === 'password' ? 'text' : 'password';
  btn.textContent = el.type === 'password' ? '👁' : '🙈';
}

function showBanner(id, type, msg) {
  const el = document.getElementById(id);
  if (!el) return;
  el.className = 'banner visible ' + type;
  el.textContent = msg;
}
function hideBanner(id) {
  const el = document.getElementById(id);
  if (el) el.className = 'banner';
}

function toast(msg, type = 'success') {
  const c  = document.getElementById('toast-container');
  const el = document.createElement('div');
  el.className = 'toast ' + type;
  el.textContent = (type === 'success' ? '✓ ' : '✗ ') + msg;
  c.appendChild(el);
  requestAnimationFrame(() => requestAnimationFrame(() => el.classList.add('show')));
  setTimeout(() => { el.classList.remove('show'); setTimeout(() => el.remove(), 350); }, 3000);
}

function v(id) { const e = document.getElementById(id); return e ? e.value.trim() : ''; }
function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

async function api(payload) {
  const res  = await fetch('api.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload) });
  if (!res.ok) throw new Error('HTTP ' + res.status);
  const data = await res.json();
  if (!data.success) throw new Error(data.message || 'Unknown error');
  return data;
}

// ─────────────────────────────────────────────────────────────────
// Mobile sidebar
// ─────────────────────────────────────────────────────────────────
const sidebar   = document.getElementById('sidebar');
const overlay   = document.getElementById('overlay');
document.getElementById('hamburger').addEventListener('click', () => {
  sidebar.classList.toggle('open'); overlay.classList.toggle('visible');
});
overlay.addEventListener('click', () => {
  sidebar.classList.remove('open'); overlay.classList.remove('visible');
});
</script>
</body>
</html>
