<?php
define('BASE_PATH', realpath(__DIR__ . '/..'));
$cfg           = require BASE_PATH . '/config/config.php';
$customEnabled = !empty($cfg['custom_site']['enabled']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Posts — Blogy Assistant</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --bg: #0f1117; --sidebar: #13161f; --card: #1a1d27; --border: #252838;
    --accent: #6c63ff; --accent2: rgba(108,99,255,.1); --text: #e2e8f0;
    --muted: #64748b; --green: #4ade80; --red: #f87171; --yellow: #fbbf24;
    --blue: #60a5fa; --orange: #fb923c; --purple: #c084fc;
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
  .main { margin-left:var(--sidebar-w); flex:1; padding:32px 28px; min-width:0; }
  .page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; gap:12px; flex-wrap:wrap; }
  .page-title { font-size:22px; font-weight:600; }
  .header-actions { display:flex; gap:8px; flex-wrap:wrap; }

  /* ── Tab bar ── */
  .tab-bar { display:flex; border-bottom:1px solid var(--border); margin-bottom:20px; gap:0; }
  .tab-btn { background:transparent; border:none; color:var(--muted); font-family:inherit; font-size:14px; font-weight:500; padding:10px 20px; cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-1px; transition:color .15s,border-color .15s; display:flex; align-items:center; gap:7px; }
  .tab-btn:hover { color:var(--text); }
  .tab-btn.active { color:var(--accent); border-bottom-color:var(--accent); }
  .tab-badge { background:var(--accent); color:#fff; border-radius:10px; padding:1px 7px; font-size:11px; font-weight:600; }

  /* ── Tab panels ── */
  .tab-panel { display:none; }
  .tab-panel.active { display:block; }

  /* ── Card ── */
  .card { background:var(--card); border:1px solid var(--border); border-radius:12px; overflow:hidden; margin-bottom:16px; }

  /* ── Buttons ── */
  .btn { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border-radius:8px; font-size:13px; font-weight:500; cursor:pointer; border:none; font-family:inherit; transition:opacity .15s,transform .1s; }
  .btn:active { transform:scale(.97); }
  .btn:disabled { opacity:.4; cursor:not-allowed; transform:none; }
  .btn-ghost   { background:transparent; border:1px solid var(--border); color:var(--muted); }
  .btn-ghost:hover   { border-color:var(--accent); color:var(--accent); }
  .btn-approve { background:rgba(74,222,128,.12); border:1px solid rgba(74,222,128,.25); color:var(--green); }
  .btn-approve:hover { background:rgba(74,222,128,.2); }
  .btn-reject  { background:rgba(248,113,113,.1);  border:1px solid rgba(248,113,113,.25); color:var(--red); }
  .btn-reject:hover  { background:rgba(248,113,113,.2); }
  .btn-view    { background:var(--accent2); border:1px solid rgba(108,99,255,.25); color:var(--accent); }
  .btn-view:hover { background:rgba(108,99,255,.18); }
  .btn-sm { padding:5px 12px; font-size:12px; }
  .btn-danger-ghost { background:transparent; border:1px solid rgba(248,113,113,.3); color:var(--red); }
  .btn-danger-ghost:hover { border-color:var(--red); }

  /* ── Table ── */
  .table-wrap { overflow-x:auto; }
  table { width:100%; border-collapse:collapse; font-size:13px; }
  thead th { text-align:left; padding:10px 14px; font-size:11px; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:.05em; border-bottom:1px solid var(--border); white-space:nowrap; }
  tbody tr { border-bottom:1px solid var(--border); transition:background .12s,opacity .3s,transform .3s; }
  tbody tr:last-child { border-bottom:none; }
  tbody tr:hover { background:rgba(255,255,255,.02); }
  tbody tr.fade-out { opacity:0; transform:translateX(20px); }
  td { padding:11px 14px; vertical-align:middle; }
  .td-title { max-width:220px; }
  .td-title span { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-weight:500; }
  .td-source { color:var(--muted); font-size:12px; max-width:120px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .td-date   { color:var(--muted); font-size:12px; white-space:nowrap; }

  /* ── Language pills ── */
  .lang-pill { display:inline-block; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:600; }
  .lang-English { background:rgba(96,165,250,.12); color:var(--blue);   border:1px solid rgba(96,165,250,.2); }
  .lang-Urdu    { background:rgba(74,222,128,.12); color:var(--green);  border:1px solid rgba(74,222,128,.2); }
  .lang-Arabic  { background:rgba(251,146,60,.12); color:var(--orange); border:1px solid rgba(251,146,60,.2); }
  .lang-Hindi   { background:rgba(192,132,252,.12);color:var(--purple); border:1px solid rgba(192,132,252,.2); }
  .lang-French  { background:rgba(251,191,36,.12); color:var(--yellow); border:1px solid rgba(251,191,36,.2); }
  .lang-auto    { background:rgba(100,116,139,.12);color:var(--muted);  border:1px solid var(--border); }
  .lang-default { background:rgba(100,116,139,.12);color:var(--muted);  border:1px solid var(--border); }

  /* ── Status pills ── */
  .status-pill { display:inline-flex; align-items:center; gap:4px; padding:2px 9px; border-radius:10px; font-size:11px; font-weight:600; }
  .s-published { background:rgba(74,222,128,.1);  color:var(--green);  border:1px solid rgba(74,222,128,.2); }
  .s-draft     { background:rgba(251,191,36,.1);  color:var(--yellow); border:1px solid rgba(251,191,36,.2); }

  /* ── Skeleton ── */
  .skeleton { background:linear-gradient(90deg,#1e2235 25%,#252840 50%,#1e2235 75%); background-size:200% 100%; animation:shimmer 1.4s infinite; border-radius:6px; }
  @keyframes shimmer { to { background-position:-200% 0; } }
  .skel-row td .skel-line { height:14px; border-radius:4px; }

  /* ── Pagination ── */
  .pagination { display:flex; align-items:center; justify-content:flex-end; gap:10px; padding:14px 18px; border-top:1px solid var(--border); }
  .page-info { font-size:13px; color:var(--muted); }
  .pagination .btn { padding:6px 14px; font-size:12px; }

  /* ── Empty state ── */
  .empty-state { text-align:center; padding:52px 20px; color:var(--muted); }
  .empty-icon  { font-size:40px; margin-bottom:10px; }
  .empty-state p { font-size:14px; line-height:1.6; }

  /* ── Info card ── */
  .info-card { background:rgba(108,99,255,.06); border:1px solid rgba(108,99,255,.2); border-radius:12px; padding:22px; display:flex; gap:14px; align-items:flex-start; margin-bottom:16px; }
  .info-card .info-icon { font-size:22px; flex-shrink:0; margin-top:2px; }
  .info-card p { font-size:14px; color:var(--muted); line-height:1.6; }
  .info-card a { color:var(--accent); }

  /* ── Collapsible error log ── */
  .collapse-header { display:flex; align-items:center; justify-content:space-between; padding:14px 18px; cursor:pointer; user-select:none; }
  .collapse-title  { display:flex; align-items:center; gap:8px; font-size:14px; font-weight:600; }
  .collapse-arrow  { font-size:12px; transition:transform .2s; color:var(--muted); }
  .collapse-arrow.open { transform:rotate(180deg); }
  .collapse-actions { display:flex; align-items:center; gap:8px; }
  .collapse-body   { display:none; border-top:1px solid var(--border); }
  .collapse-body.open { display:block; }
  .error-log-area {
    background:#1f1419; padding:14px 18px; max-height:280px; overflow-y:auto;
    font-family:'Courier New',monospace; font-size:12px; line-height:1.8;
  }
  .error-log-area::-webkit-scrollbar { width:4px; }
  .error-log-area::-webkit-scrollbar-thumb { background:#3a2020; border-radius:2px; }
  .err-line { color:#f87171; }
  .err-ok   { color:var(--green); font-style:italic; }

  /* ── View modal ── */
  .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.7); z-index:500; align-items:flex-start; justify-content:center; padding:40px 16px; overflow-y:auto; }
  .modal-overlay.open { display:flex; }
  .modal { background:var(--card); border:1px solid var(--border); border-radius:14px; width:100%; max-width:680px; animation:modalIn .2s ease; margin:auto; }
  @keyframes modalIn { from { opacity:0; transform:translateY(14px); } }
  .modal-header { display:flex; align-items:flex-start; justify-content:space-between; padding:22px 24px 0; gap:12px; }
  .modal-header h2 { font-size:17px; font-weight:600; line-height:1.4; flex:1; }
  .modal-close { background:transparent; border:none; color:var(--muted); font-size:20px; cursor:pointer; padding:2px 6px; line-height:1; }
  .modal-close:hover { color:var(--text); }
  .modal-meta { display:flex; gap:14px; flex-wrap:wrap; padding:10px 24px 0; font-size:12px; color:var(--muted); }
  .modal-meta span { display:flex; align-items:center; gap:5px; }
  .modal-excerpt { padding:14px 24px; font-size:14px; color:#94a3b8; font-style:italic; line-height:1.6; border-top:1px solid var(--border); margin-top:14px; }
  .modal-content { margin:0 24px 24px; background:#12141e; border:1px solid var(--border); border-radius:8px; padding:16px; max-height:300px; overflow-y:auto; font-size:13px; line-height:1.7; }
  .modal-content::-webkit-scrollbar { width:4px; }
  .modal-content::-webkit-scrollbar-thumb { background:#2d3147; border-radius:2px; }
  .modal-footer { padding:0 24px 20px; }

  /* ── Spinner ── */
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
    .main { margin-left:0; padding:20px 14px; padding-top:60px; }
  }
  @media(max-width:700px) {
    td:nth-child(3), th:nth-child(3),
    td:nth-child(5), th:nth-child(5) { display:none; }
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
    <a class="nav-link" href="feeds.php"><span class="nav-icon">⊟</span> Feeds</a>
    <a class="nav-link active" href="posts.php"><span class="nav-icon">📄</span> Posts</a>
    <a class="nav-link" href="settings.php"><span class="nav-icon">⚙</span> Settings</a>
    <a class="nav-link" href="setup.php"><span class="nav-icon">✦</span> Setup Wizard</a>
  </nav>
  <div class="sidebar-footer">v2.0.0</div>
</aside>

<!-- ── Main ── -->
<main class="main">
  <div class="page-header">
    <h1 class="page-title">Posts</h1>
    <div class="header-actions">
      <input type="text" id="search-input" placeholder="Search posts…" oninput="filterPosts()"
        style="background:var(--card);border:1px solid var(--border);color:var(--text);border-radius:8px;padding:6px 12px;font-size:13px;font-family:inherit;outline:none;width:200px;">
      <button class="btn btn-ghost btn-sm" onclick="loadPosts()">↻ Refresh</button>
      <button class="btn btn-sm btn-danger-ghost" onclick="clearPostedLog()">🗑 Clear Posted Log</button>
    </div>
  </div>

  <!-- ── Tab bar ── -->
  <div class="tab-bar">
    <button class="tab-btn active" data-tab="posts" onclick="switchTab('posts')">📄 All Posts</button>
    <button class="tab-btn" data-tab="drafts" onclick="switchTab('drafts')">
      🕓 Draft Queue <span class="tab-badge" id="draft-badge" style="display:none">0</span>
    </button>
  </div>

  <!-- ════════════════════════════════
       TAB: ALL POSTS
  ════════════════════════════════════ -->
  <div class="tab-panel active" id="tab-posts">
    <div class="card">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Title</th>
              <th>Source</th>
              <th>Language</th>
              <th>Status</th>
              <th>Published At</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="posts-tbody">

            <!-- skeleton rows -->
            <?php for ($s = 0; $s < 5; $s++): ?>
            <tr class="skel-row">
              <?php for ($c = 0; $c < 6; $c++): ?>
              <td><div class="skeleton skel-line" style="width:<?= [80,55,40,50,70,36][$c] ?>%"></div></td>
              <?php endfor; ?>
            </tr>
            <?php endfor; ?>
          </tbody>
        </table>
      </div>
      <div class="pagination" id="posts-pagination" style="display:none">
        <button class="btn btn-ghost btn-sm" id="btn-prev" onclick="changePage(-1)">← Prev</button>
        <span class="page-info" id="page-info">Page 1 of 1</span>
        <button class="btn btn-ghost btn-sm" id="btn-next" onclick="changePage(1)">Next →</button>
      </div>
    </div>
  </div>

  <!-- ════════════════════════════════
       TAB: DRAFT QUEUE
  ════════════════════════════════════ -->
  <div class="tab-panel" id="tab-drafts">
    <?php if (!$customEnabled): ?>
    <div class="info-card">
      <div class="info-icon">ℹ</div>
      <div>
        <p><strong style="color:var(--text)">Draft queue requires Custom DB mode.</strong><br>
           Enable Custom DB publishing in <a href="settings.php">Settings → WordPress &amp; DB</a>
           to use the draft approval workflow.</p>
      </div>
    </div>
    <?php else: ?>
    <div class="card">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Title</th>
              <th>Source</th>
              <th>Language</th>
              <th>Status</th>
              <th>Created At</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="drafts-tbody">
            <tr><td colspan="6"><div class="empty-state" style="padding:30px"><span class="spin" style="width:18px;height:18px;border-width:3px"></span></div></td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- ════════════════════════════════
       ERROR LOG (collapsible)
  ════════════════════════════════════ -->
  <div class="card" style="margin-top:8px">
    <div class="collapse-header" onclick="toggleErrorLog()">
      <div class="collapse-title">
        <span>⚠</span> Error Log
        <span style="font-size:11px;color:var(--muted);font-weight:400">(last 50 entries)</span>
      </div>
      <div class="collapse-actions">
        <button class="btn btn-sm btn-danger-ghost" onclick="event.stopPropagation();clearErrorLog()" style="font-size:11px;padding:4px 10px">Clear</button>
        <span class="collapse-arrow" id="err-arrow">▼</span>
      </div>
    </div>
    <div class="collapse-body" id="err-body">
      <div class="error-log-area" id="err-log">
        <span class="err-ok">Loading…</span>
      </div>
    </div>
  </div>

</main>

<!-- ── View Post Modal ── -->
<div class="modal-overlay" id="view-modal">
  <div class="modal">
    <div class="modal-header">
      <h2 id="modal-title">—</h2>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>
    <div class="modal-meta" id="modal-meta"></div>
    <div class="modal-excerpt" id="modal-excerpt" style="display:none"></div>
    <div class="modal-content" id="modal-content"></div>
    <div class="modal-footer">
      <button class="btn btn-ghost btn-sm" onclick="closeModal()">Close</button>
    </div>
  </div>
</div>

<div id="toast-container"></div>

<script>
// ─────────────────────────────────────────────────────────────────
// State
// ─────────────────────────────────────────────────────────────────
const CUSTOM_ENABLED = <?= $customEnabled ? 'true' : 'false' ?>;
const PAGE_SIZE = 20;

let allPosts    = [];
let currentPage = 1;
let allDrafts   = [];
let draftTimer  = null;
let activeTab   = 'posts';

// ─────────────────────────────────────────────────────────────────
// Tabs
// ─────────────────────────────────────────────────────────────────
function switchTab(tab) {
  activeTab = tab;
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
  document.getElementById('tab-posts').classList.toggle('active',  tab === 'posts');
  document.getElementById('tab-drafts').classList.toggle('active', tab === 'drafts');
  if (tab === 'drafts' && CUSTOM_ENABLED) loadDrafts();
}

// ─────────────────────────────────────────────────────────────────
// All Posts
// ─────────────────────────────────────────────────────────────────
async function loadPosts() {
  showSkeleton();
  try {
    const res  = await api('get_posts');
    allPosts   = res.data || [];
    currentPage = 1;
    renderPostsPage();
  } catch(e) {
    renderEmpty('posts-tbody', 6, '⚠ Failed to load posts: ' + e.message);
    document.getElementById('posts-pagination').style.display = 'none';
  }
}

function showSkeleton() {
  const widths = [80, 55, 40, 50, 70, 36];
  document.getElementById('posts-tbody').innerHTML = Array.from({length:5}, () =>
    `<tr class="skel-row">${widths.map(w =>
      `<td><div class="skeleton skel-line" style="width:${w}%"></div></td>`
    ).join('')}</tr>`
  ).join('');
  document.getElementById('posts-pagination').style.display = 'none';
}

function renderPostsPage() {
  const total  = allPosts.length;
  const pages  = Math.max(1, Math.ceil(total / PAGE_SIZE));
  currentPage  = Math.min(currentPage, pages);
  const start  = (currentPage - 1) * PAGE_SIZE;
  const slice  = allPosts.slice(start, start + PAGE_SIZE);

  if (!total) {
    renderEmpty('posts-tbody', 6, '<div class="empty-icon">📭</div><p>No posts published yet.<br>Run the pipeline to get started.</p>');
    document.getElementById('posts-pagination').style.display = 'none';
    return;
  }

  document.getElementById('posts-tbody').innerHTML = slice.map((p, i) =>
    renderPostRow(p, start + i)
  ).join('');

  // Pagination
  const pg = document.getElementById('posts-pagination');
  pg.style.display = 'flex';
  document.getElementById('page-info').textContent = `Page ${currentPage} of ${pages}`;
  document.getElementById('btn-prev').disabled = currentPage <= 1;
  document.getElementById('btn-next').disabled = currentPage >= pages;
}

function renderPostRow(post, idx) {
  const title    = post.title      || post.url || '—';
  const source   = post.source     || extractDomain(post.url || '');
  const lang     = post.language   || 'English';
  const status   = post.status     || 'published';
  const dateStr  = formatDate(post.created_at || post.posted_at);
  const statusEl = status === 'draft'
    ? '<span class="status-pill s-draft">○ Draft</span>'
    : '<span class="status-pill s-published">● Published</span>';
  const langEl   = `<span class="lang-pill lang-${lang.replace(/\s/g,'')}">${esc(lang)}</span>`;

  const wpLinkBtn = post.wp_url
    ? `<a href="${esc(post.wp_url)}" target="_blank" rel="noopener" class="btn btn-ghost btn-sm" style="text-decoration:none">↗ WP</a>`
    : '';

  return `<tr id="row-${idx}">
    <td class="td-title"><span title="${esc(title)}">${esc(title.length > 60 ? title.slice(0,60)+'…' : title)}</span></td>
    <td class="td-source">${esc(source)}</td>
    <td>${langEl}</td>
    <td>${statusEl}</td>
    <td class="td-date">${dateStr}</td>
    <td>
      <div style="display:flex;gap:5px;flex-wrap:wrap">
        <button class="btn btn-view btn-sm" onclick='openViewModal(${JSON.stringify(post)})'>👁 View</button>
        ${wpLinkBtn}
      </div>
    </td>
  </tr>`;
}

function changePage(dir) {
  currentPage += dir;
  renderPostsPage();
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function filterPosts() {
  const q = (document.getElementById('search-input').value || '').toLowerCase().trim();
  if (!q) { renderPostsPage(); return; }
  const filtered = allPosts.filter(p =>
    (p.title  || '').toLowerCase().includes(q) ||
    (p.source || '').toLowerCase().includes(q) ||
    (p.language || '').toLowerCase().includes(q)
  );
  const total = filtered.length;
  if (!total) { renderEmpty('posts-tbody', 6, `<div class="empty-icon">🔍</div><p>No posts match "${esc(q)}"</p>`); document.getElementById('posts-pagination').style.display='none'; return; }
  document.getElementById('posts-tbody').innerHTML = filtered.slice(0, PAGE_SIZE).map((p,i) => renderPostRow(p, i)).join('');
  document.getElementById('posts-pagination').style.display = total > PAGE_SIZE ? 'flex' : 'none';
  document.getElementById('page-info').textContent = `${total} result(s)`;
  document.getElementById('btn-prev').disabled = true;
  document.getElementById('btn-next').disabled = true;
}

// ─────────────────────────────────────────────────────────────────
// Draft Queue
// ─────────────────────────────────────────────────────────────────
async function loadDrafts() {
  if (!CUSTOM_ENABLED) return;
  try {
    const res = await api('get_drafts');
    allDrafts  = res.data || [];
    renderDrafts();
  } catch(e) {
    document.getElementById('drafts-tbody').innerHTML =
      `<tr><td colspan="6"><div class="empty-state" style="padding:28px"><p>⚠ ${esc(e.message)}</p></div></td></tr>`;
  }
}

function renderDrafts() {
  const badge = document.getElementById('draft-badge');
  if (allDrafts.length) {
    badge.textContent = allDrafts.length;
    badge.style.display = 'inline-block';
  } else {
    badge.style.display = 'none';
  }

  if (!allDrafts.length) {
    document.getElementById('drafts-tbody').innerHTML =
      `<tr><td colspan="6"><div class="empty-state"><div class="empty-icon" style="color:var(--green)">✓</div><p>No drafts waiting for approval.</p></div></td></tr>`;
    return;
  }

  document.getElementById('drafts-tbody').innerHTML = allDrafts.map((d, i) => {
    const title   = d.title    || '—';
    const source  = d.source   || '—';
    const lang    = d.language || 'English';
    const dateStr = formatDate(d.created_at);
    const langEl  = `<span class="lang-pill lang-${lang.replace(/\s/g,'')}">${esc(lang)}</span>`;
    return `<tr id="draft-row-${d.id || i}">
      <td class="td-title"><span title="${esc(title)}">${esc(title.length > 60 ? title.slice(0,60)+'…' : title)}</span></td>
      <td class="td-source">${esc(source)}</td>
      <td>${langEl}</td>
      <td><span class="status-pill s-draft">○ Draft</span></td>
      <td class="td-date">${dateStr}</td>
      <td>
        <div style="display:flex;gap:6px">
          <button class="btn btn-approve btn-sm" onclick="approvePost(${d.id || i},'draft-row-${d.id || i}')">✓ Approve</button>
          <button class="btn btn-reject  btn-sm" onclick="rejectPost(${d.id || i},'draft-row-${d.id || i}')">✗ Reject</button>
        </div>
      </td>
    </tr>`;
  }).join('');
}

async function approvePost(id, rowId) {
  try {
    await api('approve_post', { id });
    fadeRemoveRow(rowId);
    allDrafts = allDrafts.filter(d => d.id !== id);
    setTimeout(() => { renderDrafts(); loadPosts(); }, 360);
    toast('Post approved and published', 'success');
  } catch(e) { toast('Approve failed: ' + e.message, 'error'); }
}

async function rejectPost(id, rowId) {
  if (!confirm('Delete this draft permanently? This cannot be undone.')) return;
  try {
    await api('reject_post', { id });
    fadeRemoveRow(rowId);
    allDrafts = allDrafts.filter(d => d.id !== id);
    setTimeout(() => renderDrafts(), 360);
    toast('Draft rejected and deleted', 'success');
  } catch(e) { toast('Reject failed: ' + e.message, 'error'); }
}

function fadeRemoveRow(rowId) {
  const row = document.getElementById(rowId);
  if (row) {
    row.classList.add('fade-out');
  }
}

// ─────────────────────────────────────────────────────────────────
// View modal
// ─────────────────────────────────────────────────────────────────
function openViewModal(post) {
  const title   = post.title    || post.url || '—';
  const source  = post.source   || extractDomain(post.url || '');
  const lang    = post.language || 'English';
  const dateStr = formatDate(post.created_at || post.posted_at);
  const excerpt = post.excerpt  || '';
  const content = post.content  || (post.url ? `<p><a href="${esc(post.url)}" target="_blank" rel="noopener">${esc(post.url)}</a></p>` : '<p>No content preview available.</p>');

  document.getElementById('modal-title').textContent = title;
  document.getElementById('modal-meta').innerHTML =
    `<span>📡 ${esc(source)}</span>
     <span>🌐 ${esc(lang)}</span>
     <span>🕒 ${dateStr}</span>`;

  const excerptEl = document.getElementById('modal-excerpt');
  if (excerpt) {
    excerptEl.textContent = excerpt;
    excerptEl.style.display = 'block';
  } else {
    excerptEl.style.display = 'none';
  }

  // Render HTML content in a sandboxed div (no scripts execute)
  document.getElementById('modal-content').innerHTML = content;

  document.getElementById('view-modal').classList.add('open');
}

function closeModal() {
  document.getElementById('view-modal').classList.remove('open');
}
document.getElementById('view-modal').addEventListener('click', e => {
  if (e.target === e.currentTarget) closeModal();
});

// ─────────────────────────────────────────────────────────────────
// Error log
// ─────────────────────────────────────────────────────────────────
function toggleErrorLog() {
  const body  = document.getElementById('err-body');
  const arrow = document.getElementById('err-arrow');
  const open  = body.classList.toggle('open');
  arrow.classList.toggle('open', open);
  if (open) loadErrorLog();
}

async function loadErrorLog() {
  try {
    const res   = await api('get_logs');
    const lines = res.data.errors || [];
    const el    = document.getElementById('err-log');
    if (!lines.length) {
      el.innerHTML = '<span class="err-ok">No errors logged. ✓</span>';
    } else {
      el.innerHTML = lines.map(l => `<div class="err-line">${esc(l)}</div>`).join('');
    }
  } catch(e) {
    document.getElementById('err-log').innerHTML = `<span class="err-line">${esc(e.message)}</span>`;
  }
}

async function clearErrorLog() {
  if (!confirm('Clear error log?')) return;
  try {
    await api('clear_error_log');
    toast('Error log cleared', 'success');
    document.getElementById('err-log').innerHTML = '<span class="err-ok">No errors logged. ✓</span>';
  } catch(e) { toast(e.message, 'error'); }
}

// ─────────────────────────────────────────────────────────────────
// Clear posted log
// ─────────────────────────────────────────────────────────────────
async function clearPostedLog() {
  if (!confirm('Clear the posted URL log? The pipeline may republish previously published articles.')) return;
  try {
    await api('clear_posted_log');
    toast('Posted log cleared', 'success');
    loadPosts();
  } catch(e) { toast(e.message, 'error'); }
}

// ─────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────
function renderEmpty(tbodyId, cols, html) {
  document.getElementById(tbodyId).innerHTML =
    `<tr><td colspan="${cols}"><div class="empty-state">${html}</div></td></tr>`;
}

function formatDate(str) {
  if (!str) return '—';
  try {
    return new Date(str).toLocaleString('en-US', { month:'short', day:'numeric', year:'numeric', hour:'2-digit', minute:'2-digit' });
  } catch(_) { return String(str); }
}

function extractDomain(url) {
  try { return new URL(url).hostname.replace('www.',''); } catch(_) { return url; }
}

function esc(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

async function api(action, extra = {}) {
  const res  = await fetch('api.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({ action, ...extra }) });
  if (!res.ok) throw new Error('HTTP ' + res.status);
  const data = await res.json();
  if (!data.success) throw new Error(data.message || 'Unknown error');
  return data;
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

// ─────────────────────────────────────────────────────────────────
// Mobile sidebar
// ─────────────────────────────────────────────────────────────────
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('overlay');
document.getElementById('hamburger').addEventListener('click', () => {
  sidebar.classList.toggle('open'); overlay.classList.toggle('visible');
});
overlay.addEventListener('click', () => {
  sidebar.classList.remove('open'); overlay.classList.remove('visible');
});

// ─────────────────────────────────────────────────────────────────
// Boot
// ─────────────────────────────────────────────────────────────────
loadPosts();

// Auto-refresh drafts every 60s when on that tab
setInterval(() => { if (activeTab === 'drafts') loadDrafts(); }, 60000);
</script>
</body>
</html>
