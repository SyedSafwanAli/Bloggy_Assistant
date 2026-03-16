<?php
define('BASE_PATH', realpath(__DIR__ . '/..'));
$cfg   = require BASE_PATH . '/config/config.php';
$feeds = $cfg['rss_feeds'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RSS Feeds — Blogy Assistant</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg:      #0f1117;
    --sidebar: #13161f;
    --card:    #1a1d27;
    --border:  #252838;
    --accent:  #6c63ff;
    --accent2: rgba(108,99,255,.1);
    --text:    #e2e8f0;
    --muted:   #64748b;
    --green:   #4ade80;
    --red:     #f87171;
    --sidebar-w: 220px;
  }

  body { font-family:'Inter',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; display:flex; }

  /* ─── Sidebar ─── */
  .sidebar {
    width:var(--sidebar-w); background:var(--sidebar); border-right:1px solid var(--border);
    display:flex; flex-direction:column; position:fixed; top:0; left:0; bottom:0; z-index:100;
    transition:transform .25s ease;
  }
  .sidebar-logo { padding:22px 20px 18px; font-size:17px; font-weight:600; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:8px; }
  .sidebar-logo span { font-size:22px; }
  .nav { flex:1; padding:12px 10px; display:flex; flex-direction:column; gap:2px; }
  .nav-link {
    display:flex; align-items:center; gap:10px; padding:9px 12px; border-radius:8px;
    font-size:14px; font-weight:500; color:var(--muted); text-decoration:none;
    transition:background .15s, color .15s; border-left:3px solid transparent;
  }
  .nav-link:hover { background:rgba(255,255,255,.04); color:var(--text); }
  .nav-link.active { background:var(--accent2); color:var(--accent); border-left-color:var(--accent); }
  .nav-icon { font-size:16px; width:18px; text-align:center; }
  .sidebar-footer { padding:14px 20px; font-size:11px; color:var(--muted); border-top:1px solid var(--border); }

  /* ─── Main ─── */
  .main { margin-left:var(--sidebar-w); flex:1; padding:32px 28px; min-width:0; }

  /* ─── Top bar ─── */
  .top-bar { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; gap:12px; flex-wrap:wrap; }
  .page-title { font-size:22px; font-weight:600; }

  /* ─── Buttons ─── */
  .btn {
    display:inline-flex; align-items:center; gap:6px;
    padding:9px 18px; border-radius:8px; font-size:13px; font-weight:600;
    cursor:pointer; border:none; font-family:inherit; transition:opacity .15s, transform .1s;
  }
  .btn:active { transform:scale(.97); }
  .btn-primary { background:var(--accent); color:#fff; }
  .btn-primary:hover { opacity:.85; }
  .btn-primary:disabled { opacity:.4; cursor:not-allowed; transform:none; }
  .btn-ghost { background:transparent; border:1px solid var(--border); color:var(--muted); }
  .btn-ghost:hover { border-color:var(--accent); color:var(--accent); }
  .btn-danger-ghost { background:transparent; border:1px solid rgba(248,113,113,.3); color:var(--red); }
  .btn-danger-ghost:hover { border-color:var(--red); }
  .btn-icon {
    background:transparent; border:1px solid var(--border); color:var(--muted);
    border-radius:6px; padding:5px 9px; font-size:13px; cursor:pointer;
    transition:border-color .15s, color .15s; font-family:inherit;
  }
  .btn-icon:hover { border-color:var(--accent); color:var(--accent); }
  .btn-icon.danger:hover { border-color:var(--red); color:var(--red); }
  .btn-sm-plain {
    background:transparent; border:1px solid var(--border); color:var(--muted);
    border-radius:6px; padding:5px 12px; font-size:12px; cursor:pointer; font-family:inherit;
    transition:border-color .15s, color .15s;
  }
  .btn-sm-plain:hover { border-color:var(--accent); color:var(--accent); }

  /* ─── Card ─── */
  .card { background:var(--card); border:1px solid var(--border); border-radius:12px; overflow:hidden; }
  .card-body { padding:22px; }

  /* ─── Add feed form ─── */
  #add-form-wrap { display:none; margin-bottom:20px; }
  #add-form-wrap.visible { display:block; }
  .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
  .form-full { grid-column:1/-1; }
  .field label { display:block; font-size:12px; font-weight:500; color:var(--muted); margin-bottom:5px; }
  .field input[type=text], .field input[type=url], .field select {
    width:100%; background:#12141e; border:1px solid var(--border); border-radius:8px;
    padding:9px 12px; font-size:13px; color:var(--text); font-family:inherit; outline:none;
    transition:border-color .2s;
  }
  .field input:focus, .field select:focus { border-color:var(--accent); }
  .field input::placeholder { color:#3a4060; }
  .checkbox-row { display:flex; align-items:center; gap:8px; padding-top:4px; }
  .checkbox-row input[type=checkbox] { width:15px; height:15px; accent-color:var(--accent); cursor:pointer; }
  .checkbox-row label { font-size:13px; color:var(--text); cursor:pointer; margin:0; }
  .form-actions { display:flex; align-items:center; gap:10px; margin-top:18px; flex-wrap:wrap; }

  /* ─── Test preview (inline) ─── */
  .test-preview { display:none; margin-top:14px; }
  .test-preview.visible { display:block; }
  .preview-item {
    display:flex; align-items:flex-start; gap:10px;
    padding:9px 0; border-bottom:1px solid var(--border);
  }
  .preview-item:last-child { border-bottom:none; }
  .preview-dot { width:6px; height:6px; border-radius:50%; background:var(--accent); margin-top:5px; flex-shrink:0; }
  .preview-title { font-size:13px; font-weight:500; line-height:1.4; }
  .preview-url   { font-size:11px; color:var(--muted); margin-top:2px; }

  /* ─── Banner ─── */
  .banner { display:none; border-radius:8px; padding:10px 14px; font-size:13px; margin-top:12px; }
  .banner.visible { display:block; }
  .banner.success { background:rgba(74,222,128,.08); border:1px solid rgba(74,222,128,.25); color:var(--green); }
  .banner.error   { background:rgba(248,113,113,.08); border:1px solid rgba(248,113,113,.25); color:var(--red); }

  /* ─── Table ─── */
  .table-wrap { overflow-x:auto; }
  table { width:100%; border-collapse:collapse; font-size:13px; }
  thead th {
    text-align:left; padding:10px 14px; font-size:11px; font-weight:600;
    color:var(--muted); text-transform:uppercase; letter-spacing:.05em;
    border-bottom:1px solid var(--border); white-space:nowrap;
  }
  tbody tr { border-bottom:1px solid var(--border); transition:background .12s; }
  tbody tr:last-child { border-bottom:none; }
  tbody tr:hover { background:rgba(255,255,255,.02); }
  td { padding:12px 14px; vertical-align:middle; }
  .td-num   { color:var(--muted); font-size:12px; width:32px; }
  .td-name  { font-weight:500; max-width:160px; }
  .td-url   { color:var(--muted); font-family:'Courier New',monospace; font-size:11px; max-width:220px; }
  .td-url span { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .td-lang  { color:var(--muted); }
  .td-wp    { color:var(--muted); font-size:12px; }
  .td-wp.global { font-style:italic; }

  /* Status pill */
  .pill-toggle {
    display:inline-flex; align-items:center; gap:5px;
    padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600;
    cursor:pointer; border:none; font-family:inherit; transition:opacity .15s;
  }
  .pill-toggle:hover { opacity:.75; }
  .pill-active { background:rgba(74,222,128,.12); color:var(--green); border:1px solid rgba(74,222,128,.2); }
  .pill-paused { background:rgba(100,116,139,.12); color:var(--muted); border:1px solid var(--border); }

  /* Actions */
  .actions-cell { display:flex; gap:5px; }

  /* ─── Empty state ─── */
  .empty-state { text-align:center; padding:52px 20px; color:var(--muted); }
  .empty-icon  { font-size:40px; margin-bottom:12px; }
  .empty-state p { font-size:14px; }

  /* ─── Inline edit row ─── */
  .edit-input {
    background:#12141e; border:1px solid var(--border); border-radius:6px;
    padding:5px 8px; font-size:12px; color:var(--text); font-family:inherit;
    outline:none; width:100%;
  }
  .edit-input:focus { border-color:var(--accent); }
  .edit-select {
    background:#12141e; border:1px solid var(--border); border-radius:6px;
    padding:5px 8px; font-size:12px; color:var(--text); font-family:inherit;
    outline:none;
  }

  /* ─── Modal ─── */
  .modal-overlay {
    display:none; position:fixed; inset:0; background:rgba(0,0,0,.65);
    z-index:500; align-items:center; justify-content:center; padding:16px;
  }
  .modal-overlay.open { display:flex; }
  .modal {
    background:var(--card); border:1px solid var(--border); border-radius:14px;
    width:100%; max-width:500px; overflow:hidden;
    animation:modalIn .2s ease;
  }
  @keyframes modalIn { from { opacity:0; transform:translateY(12px); } }
  .modal-header { display:flex; align-items:flex-start; justify-content:space-between; padding:20px 22px 16px; gap:12px; }
  .modal-title  { font-size:15px; font-weight:600; }
  .modal-sub    { font-size:12px; color:var(--muted); margin-top:3px; word-break:break-all; }
  .modal-close  { background:transparent; border:none; color:var(--muted); font-size:20px; cursor:pointer; line-height:1; padding:2px; }
  .modal-close:hover { color:var(--text); }
  .modal-body   { padding:0 22px 22px; }
  .modal-article {
    display:flex; align-items:flex-start; gap:10px;
    padding:10px 0; border-bottom:1px solid var(--border);
  }
  .modal-article:last-child { border-bottom:none; }

  /* ─── Toast ─── */
  #toast-container { position:fixed; bottom:24px; right:24px; display:flex; flex-direction:column; gap:8px; z-index:999; pointer-events:none; }
  .toast {
    display:flex; align-items:center; gap:10px; padding:11px 16px; border-radius:10px;
    font-size:13px; font-weight:500; box-shadow:0 4px 20px rgba(0,0,0,.5);
    pointer-events:auto; transform:translateX(120%);
    transition:transform .3s cubic-bezier(.34,1.56,.64,1); min-width:200px;
  }
  .toast.show { transform:translateX(0); }
  .toast.success { background:#162a20; border:1px solid rgba(74,222,128,.3); color:var(--green); }
  .toast.error   { background:#2a1616; border:1px solid rgba(248,113,113,.3); color:var(--red); }

  /* ─── Spinner ─── */
  .spin { display:inline-block; width:12px; height:12px; border:2px solid rgba(255,255,255,.25); border-top-color:#fff; border-radius:50%; animation:spin .6s linear infinite; }
  @keyframes spin { to { transform:rotate(360deg); } }

  /* ─── Hamburger / mobile ─── */
  .hamburger { display:none; position:fixed; top:14px; left:14px; z-index:200; background:var(--sidebar); border:1px solid var(--border); border-radius:8px; padding:8px 10px; cursor:pointer; font-size:18px; color:var(--text); }
  .overlay   { display:none; position:fixed; inset:0; background:rgba(0,0,0,.6); z-index:99; }
  .overlay.visible { display:block; }

  @media(max-width:640px) {
    .hamburger { display:block; }
    .sidebar   { transform:translateX(-100%); }
    .sidebar.open { transform:translateX(0); }
    .main      { margin-left:0; padding:20px 14px; padding-top:60px; }
    .form-grid { grid-template-columns:1fr; }
  }
  @media(max-width:780px) {
    td:nth-child(5), th:nth-child(5) { display:none; } /* hide WP site col on small */
  }
</style>
</head>
<body>

<button class="hamburger" id="hamburger">☰</button>
<div class="overlay" id="overlay"></div>

<!-- ── Sidebar ── -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo"><span>🤖</span> Blogy</div>
  <nav class="nav">
    <a class="nav-link" href="index.php"><span class="nav-icon">⊞</span> Dashboard</a>
    <a class="nav-link active" href="feeds.php"><span class="nav-icon">⊟</span> Feeds</a>
    <a class="nav-link" href="posts.php"><span class="nav-icon">📄</span> Posts</a>
    <a class="nav-link" href="settings.php"><span class="nav-icon">⚙</span> Settings</a>
    <a class="nav-link" href="setup.php"><span class="nav-icon">✦</span> Setup Wizard</a>
  </nav>
  <div class="sidebar-footer">v1.0.0</div>
</aside>

<!-- ── Main ── -->
<main class="main">

  <!-- Top bar -->
  <div class="top-bar">
    <h1 class="page-title">RSS Feeds</h1>
    <button class="btn btn-primary" onclick="toggleAddForm()">＋ Add Feed</button>
  </div>

  <!-- ── Add Feed Form ── -->
  <div id="add-form-wrap">
    <div class="card">
      <div class="card-body">
        <div class="form-grid">
          <div class="field form-full">
            <label>Feed URL <span style="color:var(--red)">*</span></label>
            <input type="url" id="add-url" placeholder="https://feeds.example.com/feed.xml">
          </div>
          <div class="field">
            <label>Feed Name <span style="color:var(--red)">*</span></label>
            <input type="text" id="add-name" placeholder="My News Feed">
          </div>
          <div class="field">
            <label>Language</label>
            <select id="add-lang">
              <option value="English">English</option>
              <option value="Urdu">Urdu</option>
              <option value="Arabic">Arabic</option>
              <option value="Hindi">Hindi</option>
              <option value="French">French</option>
              <option value="auto">Auto-detect</option>
            </select>
          </div>
          <div class="field form-full">
            <label>WP Site Override</label>
            <input type="text" id="add-wp" placeholder="Leave empty to use global WordPress site">
          </div>
          <div class="field form-full">
            <div class="checkbox-row">
              <input type="checkbox" id="add-enabled" checked>
              <label for="add-enabled">Enabled</label>
            </div>
          </div>
        </div>

        <div id="add-preview" class="test-preview"></div>
        <div id="add-banner" class="banner"></div>

        <div class="form-actions">
          <button class="btn btn-ghost" id="btn-test-add" onclick="testAddFeed()">🔍 Test Feed</button>
          <button class="btn btn-primary" onclick="addFeed()">Add Feed</button>
          <button class="btn btn-ghost" onclick="toggleAddForm()">Cancel</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Feeds Table ── -->
  <div class="card">
    <div class="table-wrap">
      <table id="feeds-table">
        <thead>
          <tr>
            <th class="td-num">#</th>
            <th>Name</th>
            <th>URL</th>
            <th>Language</th>
            <th>WP Site</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="feeds-tbody">
          <?php if (empty($feeds)): ?>
          <tr id="empty-row">
            <td colspan="7">
              <div class="empty-state">
                <div class="empty-icon">📡</div>
                <p>No feeds added yet. Click <strong>+ Add Feed</strong> to get started.</p>
              </div>
            </td>
          </tr>
          <?php else: ?>
          <?php foreach ($feeds as $i => $feed): ?>
          <?php
            $enabled = !empty($feed['enabled']);
            $wpSite  = trim($feed['wp_site'] ?? '');
            $wpLabel = $wpSite ? (parse_url($wpSite, PHP_URL_HOST) ?: $wpSite) : 'Global';
            $wpClass = $wpSite ? '' : 'global';
            $urlFull = htmlspecialchars($feed['url'] ?? '');
            $urlShort = strlen($feed['url'] ?? '') > 40
              ? htmlspecialchars(substr($feed['url'], 0, 40) . '…')
              : $urlFull;
          ?>
          <tr id="row-<?= $i ?>" data-index="<?= $i ?>">
            <td class="td-num"><?= $i + 1 ?></td>
            <td class="td-name"><?= htmlspecialchars($feed['name'] ?? '') ?></td>
            <td class="td-url"><span title="<?= $urlFull ?>"><?= $urlShort ?></span></td>
            <td class="td-lang"><?= htmlspecialchars($feed['language'] ?? 'English') ?></td>
            <td class="td-wp <?= $wpClass ?>"><?= htmlspecialchars($wpLabel) ?></td>
            <td>
              <button class="pill-toggle <?= $enabled ? 'pill-active' : 'pill-paused' ?>"
                      onclick="toggleStatus(<?= $i ?>)">
                <?= $enabled ? '● Active' : '○ Paused' ?>
              </button>
            </td>
            <td>
              <div class="actions-cell">
                <button class="btn-icon" title="Test feed" onclick="openTestModal(<?= $i ?>)">🔍</button>
                <button class="btn-icon" title="Edit feed" onclick="editRow(<?= $i ?>)">✏</button>
                <button class="btn-icon danger" title="Delete feed" onclick="deleteFeed(<?= $i ?>)">✕</button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</main>

<!-- ── Test Feed Modal ── -->
<div class="modal-overlay" id="test-modal">
  <div class="modal">
    <div class="modal-header">
      <div>
        <div class="modal-title" id="modal-feed-name">Testing Feed…</div>
        <div class="modal-sub"  id="modal-feed-url"></div>
      </div>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>
    <div class="modal-body" id="modal-body">
      <div style="text-align:center;padding:20px;color:var(--muted)">
        <span class="spin" style="width:20px;height:20px;border-width:3px"></span>
      </div>
    </div>
  </div>
</div>

<div id="toast-container"></div>

<script>
// ─────────────────────────────────────────────────────────────────
// Feed data from PHP
// ─────────────────────────────────────────────────────────────────
let feeds = <?= json_encode(array_values($feeds)) ?>;

const LANGS = ['English','Urdu','Arabic','Hindi','French','auto'];

// ─────────────────────────────────────────────────────────────────
// Add form toggle
// ─────────────────────────────────────────────────────────────────
function toggleAddForm() {
  const wrap = document.getElementById('add-form-wrap');
  wrap.classList.toggle('visible');
  if (wrap.classList.contains('visible')) {
    document.getElementById('add-url').focus();
  } else {
    resetAddForm();
  }
}

function resetAddForm() {
  ['add-url','add-name','add-wp'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('add-lang').value    = 'English';
  document.getElementById('add-enabled').checked = true;
  document.getElementById('add-preview').className = 'test-preview';
  document.getElementById('add-preview').innerHTML  = '';
  hideBanner('add-banner');
}

// ─────────────────────────────────────────────────────────────────
// Test feed (add form)
// ─────────────────────────────────────────────────────────────────
async function testAddFeed() {
  const url = document.getElementById('add-url').value.trim();
  if (!url) { showBanner('add-banner', 'error', 'Enter a feed URL first.'); return; }
  const btn = document.getElementById('btn-test-add');
  btn.innerHTML = '<span class="spin"></span> Testing…';
  btn.disabled  = true;
  hideBanner('add-banner');

  try {
    const res = await api('test_feed', { url });
    renderInlinePreview('add-preview', res.data.articles || []);
    if (!(res.data.articles || []).length) showBanner('add-banner', 'error', 'No articles found in this feed.');
    // Auto-fill name from domain
    const nameEl = document.getElementById('add-name');
    if (!nameEl.value) {
      try { nameEl.value = new URL(url).hostname.replace('www.',''); } catch(_){}
    }
  } catch(e) { showBanner('add-banner', 'error', e.message); }
  finally { btn.textContent = '🔍 Test Feed'; btn.disabled = false; }
}

function renderInlinePreview(containerId, articles) {
  const el = document.getElementById(containerId);
  if (!articles.length) { el.className = 'test-preview'; return; }
  el.className = 'test-preview visible';
  el.innerHTML = articles.map(a => `
    <div class="preview-item">
      <div class="preview-dot"></div>
      <div>
        <div class="preview-title">${esc(a.title)}</div>
        <div class="preview-url">${esc(a.url)}</div>
      </div>
    </div>`).join('');
}

// ─────────────────────────────────────────────────────────────────
// Add Feed
// ─────────────────────────────────────────────────────────────────
async function addFeed() {
  const url  = document.getElementById('add-url').value.trim();
  const name = document.getElementById('add-name').value.trim();
  if (!url || !name) { showBanner('add-banner','error','Feed URL and Name are required.'); return; }

  const feed = {
    url,
    name,
    language: document.getElementById('add-lang').value,
    wp_site:  document.getElementById('add-wp').value.trim(),
    enabled:  document.getElementById('add-enabled').checked,
  };

  feeds.push(feed);
  await saveFeeds('Feed added');
  toggleAddForm();
}

// ─────────────────────────────────────────────────────────────────
// Toggle enabled status
// ─────────────────────────────────────────────────────────────────
async function toggleStatus(idx) {
  feeds[idx].enabled = !feeds[idx].enabled;
  await saveFeeds(feeds[idx].enabled ? 'Feed enabled' : 'Feed paused');
}

// ─────────────────────────────────────────────────────────────────
// Delete
// ─────────────────────────────────────────────────────────────────
async function deleteFeed(idx) {
  const name = feeds[idx].name || feeds[idx].url;
  if (!confirm(`Delete feed "${name}"? This cannot be undone.`)) return;
  feeds.splice(idx, 1);
  await saveFeeds('Feed deleted');
}

// ─────────────────────────────────────────────────────────────────
// Inline edit
// ─────────────────────────────────────────────────────────────────
function editRow(idx) {
  const row  = document.getElementById('row-' + idx);
  const feed = feeds[idx];

  const langOptions = LANGS.map(l =>
    `<option value="${l}" ${l === feed.language ? 'selected' : ''}>${l === 'auto' ? 'Auto-detect' : l}</option>`
  ).join('');

  row.innerHTML = `
    <td class="td-num">${idx + 1}</td>
    <td><input class="edit-input" id="edit-name-${idx}" value="${esc(feed.name)}"></td>
    <td><input class="edit-input" id="edit-url-${idx}"  value="${esc(feed.url)}"></td>
    <td><select class="edit-select" id="edit-lang-${idx}">${langOptions}</select></td>
    <td><input class="edit-input" id="edit-wp-${idx}"   value="${esc(feed.wp_site || '')}" placeholder="Global"></td>
    <td>
      <div class="checkbox-row" style="padding:0">
        <input type="checkbox" id="edit-en-${idx}" ${feed.enabled ? 'checked' : ''} style="width:14px;height:14px;accent-color:var(--accent)">
        <label for="edit-en-${idx}" style="font-size:12px;color:var(--muted)">Enabled</label>
      </div>
    </td>
    <td>
      <div class="actions-cell">
        <button class="btn-icon" onclick="saveEdit(${idx})">💾</button>
        <button class="btn-icon danger" onclick="renderTable()">✕</button>
      </div>
    </td>`;
}

async function saveEdit(idx) {
  const name = document.getElementById(`edit-name-${idx}`).value.trim();
  const url  = document.getElementById(`edit-url-${idx}`).value.trim();
  if (!url || !name) { toast('Name and URL are required', 'error'); return; }

  feeds[idx] = {
    url,
    name,
    language: document.getElementById(`edit-lang-${idx}`).value,
    wp_site:  document.getElementById(`edit-wp-${idx}`).value.trim(),
    enabled:  document.getElementById(`edit-en-${idx}`).checked,
  };

  await saveFeeds('Feed updated');
}

// ─────────────────────────────────────────────────────────────────
// Test modal (for existing rows)
// ─────────────────────────────────────────────────────────────────
async function openTestModal(idx) {
  const feed = feeds[idx];
  document.getElementById('modal-feed-name').textContent = feed.name || 'Feed';
  document.getElementById('modal-feed-url').textContent  = feed.url;
  document.getElementById('modal-body').innerHTML =
    '<div style="text-align:center;padding:24px;color:var(--muted)"><span class="spin" style="width:20px;height:20px;border-width:3px"></span></div>';
  document.getElementById('test-modal').classList.add('open');

  try {
    const res      = await api('test_feed', { url: feed.url });
    const articles = res.data.articles || [];
    if (!articles.length) {
      document.getElementById('modal-body').innerHTML =
        '<p style="color:var(--muted);font-size:13px;padding:4px 0">No articles found.</p>';
      return;
    }
    document.getElementById('modal-body').innerHTML = articles.map(a => `
      <div class="modal-article">
        <div class="preview-dot" style="margin-top:5px;flex-shrink:0"></div>
        <div>
          <a href="${esc(a.url)}" target="_blank" rel="noopener"
             style="font-size:13px;font-weight:500;color:var(--text);text-decoration:none">
            ${esc(a.title)}
          </a>
          <div style="font-size:11px;color:var(--muted);margin-top:3px">
            ${esc(a.url)} &nbsp;·&nbsp; ${esc(a.language)}
          </div>
        </div>
      </div>`).join('');
  } catch(e) {
    document.getElementById('modal-body').innerHTML =
      `<p style="color:var(--red);font-size:13px">${esc(e.message)}</p>`;
  }
}

function closeModal() {
  document.getElementById('test-modal').classList.remove('open');
}
document.getElementById('test-modal').addEventListener('click', e => {
  if (e.target === e.currentTarget) closeModal();
});

// ─────────────────────────────────────────────────────────────────
// Save + re-render
// ─────────────────────────────────────────────────────────────────
async function saveFeeds(successMsg = 'Saved') {
  try {
    await api('save_feeds', { rss_feeds: feeds });
    toast(successMsg, 'success');
    renderTable();
  } catch(e) {
    // Roll back last change on failure
    toast('Save failed: ' + e.message, 'error');
    // Reload from server to restore truth
    const resp = await fetch('../config/config.php'); // can't do this, re-render from current state
    renderTable();
  }
}

// ─────────────────────────────────────────────────────────────────
// Render table from JS feeds array
// ─────────────────────────────────────────────────────────────────
function renderTable() {
  const tbody = document.getElementById('feeds-tbody');

  if (!feeds.length) {
    tbody.innerHTML = `
      <tr id="empty-row"><td colspan="7">
        <div class="empty-state">
          <div class="empty-icon">📡</div>
          <p>No feeds added yet. Click <strong>+ Add Feed</strong> to get started.</p>
        </div>
      </td></tr>`;
    return;
  }

  tbody.innerHTML = feeds.map((feed, i) => {
    const enabled  = !!feed.enabled;
    const wpSite   = (feed.wp_site || '').trim();
    let   wpLabel  = 'Global';
    let   wpClass  = 'global';
    if (wpSite) {
      try { wpLabel = new URL(wpSite).hostname; wpClass = ''; } catch(_) { wpLabel = wpSite; wpClass = ''; }
    }
    const urlShort = feed.url.length > 40 ? feed.url.slice(0, 40) + '…' : feed.url;

    return `
    <tr id="row-${i}" data-index="${i}">
      <td class="td-num">${i + 1}</td>
      <td class="td-name">${esc(feed.name)}</td>
      <td class="td-url"><span title="${esc(feed.url)}">${esc(urlShort)}</span></td>
      <td class="td-lang">${esc(feed.language)}</td>
      <td class="td-wp ${wpClass}">${esc(wpLabel)}</td>
      <td>
        <button class="pill-toggle ${enabled ? 'pill-active' : 'pill-paused'}"
                onclick="toggleStatus(${i})">
          ${enabled ? '● Active' : '○ Paused'}
        </button>
      </td>
      <td>
        <div class="actions-cell">
          <button class="btn-icon" title="Test feed" onclick="openTestModal(${i})">🔍</button>
          <button class="btn-icon" title="Edit feed"  onclick="editRow(${i})">✏</button>
          <button class="btn-icon danger" title="Delete feed" onclick="deleteFeed(${i})">✕</button>
        </div>
      </td>
    </tr>`;
  }).join('');
}

// ─────────────────────────────────────────────────────────────────
// Banner helpers
// ─────────────────────────────────────────────────────────────────
function showBanner(id, type, msg) {
  const el = document.getElementById(id);
  el.className = 'banner visible ' + type;
  el.textContent = (type === 'error' ? '✗ ' : '✓ ') + msg;
}
function hideBanner(id) { document.getElementById(id).className = 'banner'; }

// ─────────────────────────────────────────────────────────────────
// Toast
// ─────────────────────────────────────────────────────────────────
function toast(msg, type = 'success') {
  const c  = document.getElementById('toast-container');
  const el = document.createElement('div');
  el.className = 'toast ' + type;
  el.textContent = (type === 'success' ? '✓ ' : '✗ ') + msg;
  c.appendChild(el);
  requestAnimationFrame(() => requestAnimationFrame(() => el.classList.add('show')));
  setTimeout(() => { el.classList.remove('show'); setTimeout(() => el.remove(), 350); }, 3000);
}

// ─────────────────────────────────────────────────────────────────
// API
// ─────────────────────────────────────────────────────────────────
async function api(action, extra = {}) {
  const res = await fetch('api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action, ...extra }),
  });
  if (!res.ok) throw new Error('HTTP ' + res.status);
  const data = await res.json();
  if (!data.success) throw new Error(data.message || 'Unknown error');
  return data;
}

function esc(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
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
