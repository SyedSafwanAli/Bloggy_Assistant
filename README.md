# Blogy Assistant — Self-Hosted Auto-Blogging Engine

A fully self-hosted PHP auto-blogging system that fetches articles from RSS/Atom feeds, rewrites them with AI, generates featured images, and publishes them to WordPress — automatically, on a cron schedule. Includes a complete web dashboard for monitoring, configuration, and content management.

---

## Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Project Structure](#project-structure)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
  - [RSS Feeds](#1-rss-feeds)
  - [AI Provider](#2-ai-provider)
  - [Pixabay Images](#3-pixabay-images)
  - [Image Processing](#4-image-processing)
  - [WordPress Publishing](#5-wordpress-publishing)
  - [Custom Site / Database](#6-custom-site--database)
  - [Email Notifications](#7-email-notifications)
  - [General Settings](#8-general-settings)
- [Dashboard](#dashboard)
  - [Setup Wizard](#setup-wizard--dashboardsetupphp)
  - [Dashboard Home](#dashboard-home--dashboardindexphp)
  - [Feed Manager](#feed-manager--dashboardfeedsphp)
  - [Settings](#settings--dashboardsettingsphp)
  - [Posts & Drafts](#posts--drafts--dashboardpostsphp)
  - [API Backend](#api-backend--dashboardapiphp)
- [Components](#components)
  - [Logger](#logger--srcloggerphp)
  - [RssFetcher](#rssfetcher--srcrssfetcherphp)
  - [AiService](#aiservice--srcaiservicephp)
  - [ImageService](#imageservice--srcimageservicephp)
  - [WordPressPublisher](#wordpresspublisher--srcwordpresspublisherphp)
  - [CustomSitePublisher](#customsitepublisher--srccustomsitepublisherphp)
  - [Mailer](#mailer--srcmailerphp)
- [How It Works](#how-it-works)
- [Running the Pipeline](#running-the-pipeline)
- [Custom Prompts Guide](#custom-prompts-guide)
- [Roadmap](#roadmap)

---

## Overview

Blogy Assistant is a zero-dependency, pure-PHP auto-blogging engine that runs on any LAMP/XAMPP server. Point it at RSS/Atom feeds, choose an AI provider (OpenAI GPT-4o or Google Gemini), and it handles the rest — fetching, rewriting, image sourcing, SEO metadata generation, category management, and WordPress publishing — on a configurable schedule with a full web dashboard for monitoring and management.

---

## Features

### Pipeline
- **Multi-feed RSS/Atom support** — unlimited feeds, each with independent language and WordPress site settings
- **Dual AI providers** — OpenAI (GPT-4o and newer) or Google Gemini (2.0 Flash), switchable with one config key
- **Per-feed language** — English, Urdu, Arabic, Hindi, French, or auto-detected from feed XML/HTTP headers
- **RTL language support** — Arabic and Urdu use a dedicated RTL font for image overlays
- **Custom prompt overrides** — replace any built-in AI prompt per operation (rewrite, title, excerpt, SEO meta) with template variables
- **Smart image sourcing** — uses the article's own RSS feed image first; falls back to Pixabay search if none available
- **Full GD image pipeline** — smart center-crop, watermark, title overlay, brightness/contrast adjustment
- **WordPress REST API publishing** — post to any WP site using Application Passwords, with per-feed site override
- **Auto category management** — matches existing WP categories by name; auto-creates a new category from SEO keyword if none match
- **Auto tag management** — finds or creates WP tags based on AI-generated SEO tags
- **Yoast SEO integration** — writes `meta_title`, `meta_description`, and `focus_keyword` to Yoast custom fields automatically
- **Custom DB publishing** — alternative PDO/MySQL direct-insert mode for non-WordPress sites
- **Draft mode & approval flow** — force all posts to draft for manual review before publishing
- **Duplicate detection** — URL-based deduplication via persistent posted log — same article never published twice
- **Email notifications** — success/error alerts via PHP `mail()` or raw SMTP (no library needed)

### Dashboard
- **5-step setup wizard** — guided first-time configuration with live connection tests at every step
- **Live dashboard** — stat cards, countdown timer to next run, connection status pills, activity log, quick actions
- **RSS Feed Manager** — add, edit, test, enable/disable, and delete feeds without touching config files
- **Settings UI** — tabbed editor for all 8 config sections with inline connection tests and preset buttons
- **Posts & Drafts** — paginated post history, draft approval queue, error log viewer
- **No Composer required** — pure PHP 8.1 + cURL + GD, no external dependencies

---

## Project Structure

```
Blogy_Assistant/
│
├── config/
│   └── config.php                    # Master configuration (all 8 sections)
│
├── src/
│   ├── Logger.php                    # Static logging, duplicate tracking, stats
│   ├── RssFetcher.php                # RSS 2.0 / Atom / RDF feed parser
│   ├── AiService.php                 # OpenAI + Gemini unified rewriting service
│   ├── ImageService.php              # Feed image + Pixabay fallback + GD pipeline
│   ├── WordPressPublisher.php        # WordPress REST API publisher with auto-categories
│   ├── CustomSitePublisher.php       # Direct PDO/MySQL publisher
│   └── Mailer.php                    # Email notifications (mail() + raw SMTP)
│
├── dashboard/
│   ├── api.php                       # AJAX JSON backend (all dashboard actions)
│   ├── setup.php                     # 5-step first-time setup wizard
│   ├── index.php                     # Dashboard home — stats, logs, quick actions
│   ├── feeds.php                     # RSS Feed Manager
│   ├── settings.php                  # Tabbed settings editor (5 tabs)
│   └── posts.php                     # Post history + draft approval queue
│
├── assets/
│   ├── font.ttf                      # LTR font for image title overlays
│   └── font-rtl.ttf                  # RTL font for Arabic / Urdu overlays
│
├── images/                           # Generated featured images (auto-created)
│
├── logs/
│   ├── activity.log                  # INFO-level event log
│   ├── error.log                     # Error log
│   └── posted.log                    # One URL per line — already-published articles
│
├── run.php                           # Main CLI entry point / cron runner
└── README.md
```

---

## Requirements

| Requirement | Notes |
|---|---|
| PHP 8.1+ | Minimum version |
| `curl` extension | Feed downloads, AI API calls, WP REST API |
| `simplexml` extension | RSS / Atom parsing |
| `gd` extension | Featured image generation — enable in `php.ini`: `extension=gd` |
| `pdo_mysql` extension | Custom site DB publishing (optional) |
| Web server | Apache / Nginx / XAMPP |
| OpenAI **or** Gemini API key | At least one required for AI rewriting |
| Pixabay API key | Free — fallback image search |
| WordPress 5.6+ | With Application Passwords enabled (optional) |
| MySQL 5.7+ / MariaDB 10.3+ | Required only for custom site mode |

### Enabling GD in XAMPP
Open `C:\xampp\php\php.ini`, find `;extension=gd` and remove the semicolon:
```ini
extension=gd
```
Then restart Apache from the XAMPP Control Panel.

---

## Installation

1. **Copy the project** into your web root:
   ```
   c:\xampp\htdocs\Blogy_Assistant\
   ```

2. **Place fonts** in `assets/`:
   - `font.ttf` — any LTR TTF font (e.g. Roboto, Open Sans)
   - `font-rtl.ttf` — Arabic/Urdu-compatible font (e.g. Noto Naskh Arabic)

3. **Open the setup wizard** in your browser:
   ```
   http://localhost/Blogy_Assistant/dashboard/setup.php
   ```
   The wizard guides you through all API keys, feeds, and publishing settings with live connection tests.

4. **Or configure manually** — edit `config/config.php` directly, then access the dashboard at:
   ```
   http://localhost/Blogy_Assistant/dashboard/
   ```

5. **Create the database table** (if using custom site mode):
   ```sql
   CREATE TABLE blogs (
     id               INT AUTO_INCREMENT PRIMARY KEY,
     title            VARCHAR(255)  NOT NULL,
     content          LONGTEXT,
     excerpt          TEXT,
     meta_title       VARCHAR(255),
     meta_description VARCHAR(255),
     focus_keyword    VARCHAR(100),
     tags             JSON,
     source_url       VARCHAR(500),
     source           VARCHAR(100),
     language         VARCHAR(50),
     image            VARCHAR(255),
     status           VARCHAR(20)   DEFAULT 'published',
     created_at       DATETIME      DEFAULT CURRENT_TIMESTAMP
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
   ```

6. **Test a manual run:**
   ```bash
   php run.php
   ```

7. **Set up a cron job** to match your `interval_hours` setting (default: every 6 hours):
   ```
   0 */6 * * * php /xampp/htdocs/Blogy_Assistant/run.php >> /xampp/htdocs/Blogy_Assistant/logs/cron.log 2>&1
   ```
   The exact command is shown and copyable in **Settings → Schedule**.

---

## Configuration

All settings live in `config/config.php` and can be edited directly or via the dashboard Settings page.

---

### 1. RSS Feeds

```php
'rss_feeds' => [
    [
        'url'      => 'https://feeds.example.com/feed.xml',
        'name'     => 'Example Feed',
        'language' => 'English',   // English / Urdu / Arabic / Hindi / French / auto
        'enabled'  => true,
        'wp_site'  => '',          // Override WordPress site per feed (empty = global)
    ],
],
```

| Option | Description |
|---|---|
| `language: auto` | Detect from `xml:lang`, `<channel><language>`, or `Content-Language` HTTP header |
| `wp_site` | Publish this feed to a different WordPress instance than the global one |
| `enabled: false` | Pause a feed without deleting it |

---

### 2. AI Provider

```php
'ai' => [
    'provider'       => 'openai',              // 'openai' or 'gemini'
    'openai_api_key' => 'YOUR_OPENAI_API_KEY',
    'openai_model'   => 'gpt-4o',             // gpt-4o, gpt-4o-mini, gpt-4-turbo, etc.
    'gemini_api_key' => 'YOUR_GEMINI_API_KEY',
    'gemini_model'   => 'gemini-2.0-flash',
    'timeout'        => 60,
    'tone'           => 'professional',        // professional / casual / news
    'min_words'      => 600,
    'custom_prompts' => [
        'rewrite'  => '',  // leave empty to use built-in prompt
        'title'    => '',
        'excerpt'  => '',
        'seo_meta' => '',
    ],
],
```

**Custom prompt placeholders:**

| Key | Available placeholders |
|---|---|
| `rewrite` | `{title}` `{content}` `{language}` `{tone}` `{min_words}` |
| `title` | `{title}` `{content}` `{language}` |
| `excerpt` | `{content}` `{language}` |
| `seo_meta` | `{title}` `{content}` `{language}` |

See the [Custom Prompts Guide](#custom-prompts-guide) for ready-to-use news prompts.

---

### 3. Pixabay Images

```php
'pixabay' => [
    'api_key'     => 'YOUR_PIXABAY_API_KEY',
    'image_type'  => 'photo',        // photo / illustration / vector
    'orientation' => 'horizontal',
    'min_width'   => 1280,
    'per_page'    => 5,              // candidates — a random one is selected
],
```

> **Note:** Pixabay is used as a **fallback only**. If the RSS feed provides its own article image, that image is always used first. Pixabay is called only when no feed image is found.

---

### 4. Image Processing

```php
'image' => [
    'output_width'       => 1200,
    'output_height'      => 628,            // standard Open Graph ratio
    'watermark_text'     => 'YourSite.com',
    'watermark_opacity'  => 60,             // 0 (transparent) – 100 (opaque)
    'watermark_position' => 'bottom-right', // top-left/right, bottom-left/right, center
    'overlay_title'      => true,           // burn post title onto image
    'brightness'         => 5,              // -50 to 50
    'contrast'           => 10,
    'save_dir'           => __DIR__ . '/../images/',
    'font_path'          => __DIR__ . '/../assets/font.ttf',
    'rtl_font_path'      => __DIR__ . '/../assets/font-rtl.ttf',
],
```

---

### 5. WordPress Publishing

```php
'wordpress' => [
    'enabled'      => true,
    'site_url'     => 'https://yoursite.com',
    'username'     => 'admin',
    'app_password' => 'xxxx xxxx xxxx xxxx',  // WP Application Password
    'status'       => 'publish',              // publish / draft / pending
    'category'     => [1],                   // fallback only — auto-management handles this
    'author_id'    => 1,
],
```

Generate an Application Password: **WP Admin → Users → Edit User → Application Passwords**.

**Auto Category Management:**
- On every publish, the tool fetches all existing WP categories
- Matches against the article's `focus_keyword` and SEO `tags`
- If a match is found → assigns that category
- If no match → automatically creates a new category from the focus keyword
- `category: [1]` in config is the last-resort fallback (Uncategorized) only

**Yoast SEO fields** (`_yoast_wpseo_title`, `_yoast_wpseo_metadesc`, `_yoast_wpseo_focuskw`) are written automatically when Yoast SEO is installed.

---

### 6. Custom Site / Database

```php
'custom_site' => [
    'enabled'     => false,
    'db_host'     => 'localhost',
    'db_name'     => 'your_db',
    'db_user'     => 'root',
    'db_pass'     => '',
    'table'       => 'blogs',
    'uploads_dir' => __DIR__ . '/../images/',
],
```

An alternative to WordPress for custom PHP sites. See the SQL schema in the Installation section. When enabled, the **Draft Queue** in the Posts page becomes available.

---

### 7. Email Notifications

```php
'email' => [
    'enabled'        => false,            // set true to enable email alerts
    'recipient'      => 'admin@yoursite.com',
    'subject_prefix' => '[AutoBlogger]',
    'use_smtp'       => false,            // true = raw SMTP, false = PHP mail()
    'smtp_host'      => '',
    'smtp_port'      => 587,
    'smtp_user'      => '',
    'smtp_pass'      => '',
    'on_success'     => true,
    'on_error'       => true,
],
```

> Email is **disabled by default**. Enable by setting `'enabled' => true` and configuring SMTP or `mail()` settings.

---

### 8. General Settings

```php
'general' => [
    'language'        => 'English',   // global fallback when feed language is unset
    'posts_per_run'   => 3,           // articles processed per cron execution
    'interval_hours'  => 6,           // cron interval (used for scheduling reference)
    'draft_mode'      => false,       // true = all posts saved as draft
    'pause'           => false,       // true = skip run without disabling cron
    'posted_log'      => __DIR__ . '/../logs/posted.log',
    'error_log'       => __DIR__ . '/../logs/error.log',
    'activity_log'    => __DIR__ . '/../logs/activity.log',
    'duplicate_check' => true,        // skip URLs already in posted.log
],
```

---

## Dashboard

The dashboard is pure PHP + vanilla JS (no frameworks, no Composer). Dark theme throughout.

Access at: `http://localhost/Blogy_Assistant/dashboard/`

---

### Setup Wizard — `dashboard/setup.php`

A 5-step guided wizard for first-time configuration. Automatically redirects to `index.php` once configured.

| Step | Content |
|---|---|
| 1 — Welcome | PHP extension requirement checklist (PHP 8.1, cURL, SimpleXML, GD) |
| 2 — WordPress | Site URL, username, app password + live connection test |
| 3 — AI Provider | OpenAI or Gemini toggle, API key + model, live connection test + model list |
| 4 — Pixabay | API key + live key validation with image count |
| 5 — Feed & Schedule | RSS URL with article preview, language, posts per run, interval, cron command |

Progress dots at the top are clickable to navigate back to completed steps. Steps 2 and 4 can be skipped.

---

### Dashboard Home — `dashboard/index.php`

| Section | Detail |
|---|---|
| Stat cards | Total Published, Posts Today, Active Feeds, Next Run countdown (live, updates every second) |
| Status bar | AI provider, Pixabay, WordPress — green/red indicator pills |
| Activity Log | Last 100 log lines, color-coded INFO/ERROR, auto-refreshes every 30s |
| Quick Actions | Run Now (background process), Pause/Resume toggle |

The **Run Now** button fires `run.php` as a background process via `shell_exec`.

---

### Feed Manager — `dashboard/feeds.php`

All feed data is managed client-side in a JS array seeded from PHP, synced to `config.php` via `api.php save_feeds` on every change.

| Feature | Detail |
|---|---|
| Add Feed | Form with URL, name, language, WP site override, enabled toggle |
| Test Feed | Inline article preview (3 articles); auto-fills feed name from domain |
| Status toggle | Click Active/Paused pill to flip `enabled` and save instantly |
| Inline edit | Edit a row in-place; Save/Cancel without modal |
| Delete | Confirm dialog → removes from array → saves |

---

### Settings — `dashboard/settings.php`

Five-tab settings editor. Active tab is persisted to `localStorage`.

| Tab | Sections |
|---|---|
| **AI** | Provider toggle (OpenAI/Gemini), API keys + model, connection test, tone selector, min-words, custom prompts with per-field Reset |
| **Image** | Pixabay key + test, output size presets (Blog/Square/Portrait) + custom W×H, watermark text/opacity/position, title overlay toggle, brightness/contrast sliders |
| **WordPress & DB** | Publishing mode (WP / DB / Both), WP connection test + category loader, post status, draft approval toggle; DB connection settings |
| **Schedule** | Posts per run, interval, live cron command with copy button, pause toggle |
| **Email** | Master enable toggle, recipient, subject prefix, on-success/on-error toggles, SMTP settings |

Each tab has its own **Save** button that merges only its section into the full config.

---

### Posts & Drafts — `dashboard/posts.php`

**All Posts tab**
- Paginated post history — 20 per page, client-side, no round-trip per page flip
- Columns: Title, Source, Language (color-coded pill), Status, Published At
- Language pill colors: English=blue, Urdu=green, Arabic=orange, Hindi=purple, French=yellow

**Draft Queue tab**
- Functional only when `custom_site.enabled = true`
- Approve → post goes live; Reject → post deleted
- Draft badge shows pending count; auto-refreshes every 60s

**Error Log** — collapsible, last 50 error lines, red-tinted background

---

### API Backend — `dashboard/api.php`

Single AJAX endpoint. Accepts JSON body, query string, or form-encoded POST. Always returns `{ success: bool, data: mixed, message: string }`.

| Group | Actions |
|---|---|
| Settings | `save_settings`, `save_feeds` |
| Pipeline | `run_now`, `pause_toggle` |
| Logs | `get_stats`, `get_logs`, `clear_posted_log` |
| Connection tests | `test_wp_connection`, `get_wp_categories`, `test_ai_connection`, `test_pixabay`, `test_feed` |
| Posts | `get_posts`, `get_drafts`, `approve_post`, `reject_post` |

---

## Components

### Logger — `src/Logger.php`

Static utility class. Call `Logger::init()` once at startup.

```php
Logger::init($config['general']);

Logger::info('Article published: My Post Title');
Logger::error('WordPress API returned 401');
Logger::markPosted('https://source.com/article-slug');

Logger::isPosted('https://source.com/article-slug');  // bool
Logger::getPostedCount();                              // int
Logger::getStats();
// ['total_posted' => 42, 'today_posted' => 3, 'error_count' => 1]
```

All writes use `FILE_APPEND | LOCK_EX` to prevent race conditions under concurrent cron runs.

---

### RssFetcher — `src/RssFetcher.php`

Downloads and normalises articles from all enabled feeds.

```php
$fetcher  = new RssFetcher($config);
$articles = $fetcher->fetch(limit: 5);

// Each article array:
[
    'title'    => 'Article headline',
    'content'  => '<p>HTML body...</p>',
    'url'      => 'https://source.com/article',
    'image'    => 'https://cdn.source.com/img.jpg',  // or '' if none
    'source'   => 'Feed Name',
    'language' => 'English',
    'wp_site'  => '',
]
```

| Capability | Detail |
|---|---|
| Feed formats | RSS 2.0, Atom, RDF/RSS 1.0 |
| Image detection | `media:content` → `media:thumbnail` → `media:group` → `<enclosure>` → `<img src>` in body |
| Language detection | `xml:lang` → `<channel><language>` → `Content-Language` header → `'English'` |
| Deduplication | Skips any article whose URL is already in `posted.log` |

---

### AiService — `src/AiService.php`

Unified wrapper for OpenAI and Gemini. Switch providers in config with no code changes.

```php
$ai = new AiService($config);

$title   = $ai->generateTitle($originalTitle, $content, 'English');
$body    = $ai->rewriteArticle($title, $content, 'English');
$excerpt = $ai->generateExcerpt($body, 'English');
$seo     = $ai->generateSeoMeta($title, $body, 'English');

// $seo shape:
[
    'meta_title'       => 'SEO title up to 60 chars',
    'meta_description' => 'Meta description up to 160 chars',
    'focus_keyword'    => 'main keyword phrase',
    'tags'             => ['tag1', 'tag2', 'tag3', 'tag4', 'tag5'],
]
```

- `generateSeoMeta()` strips markdown code fences and returns a safe fallback array if JSON parsing fails
- Custom prompts in config override built-in prompts per operation
- `AiService::$rtlLanguages = ['Urdu', 'Arabic']` — used by ImageService for font selection

---

### ImageService — `src/ImageService.php`

Intelligent image sourcing with full GD processing pipeline.

```php
$img      = new ImageService($config);
$filepath = $img->process($keyword, $title, $language, $feedImageUrl);
// Returns absolute path to saved JPEG, or '' on failure
```

**Image sourcing priority:**
1. **Feed image** (`$feedImageUrl`) — uses the article's own image from RSS if available
2. **Pixabay search** — searches using `$keyword` if no feed image found

**GD processing pipeline:**

| Step | Detail |
|---|---|
| `cropResize` | Smart center-crop to target aspect ratio, resample to exact output size |
| `adjustBrightnessContrast` | `IMG_FILTER_BRIGHTNESS` + `IMG_FILTER_CONTRAST` (GD inverted scale) |
| `overlayTitle` | Semi-transparent gradient bar (bottom 32%) with word-wrapped title + drop shadow |
| `addWatermark` | Semi-transparent text stamp at configured position |

RTL languages automatically use `rtl_font_path`. Missing font files skip the overlay gracefully.

---

### WordPressPublisher — `src/WordPressPublisher.php`

Publishes to WordPress via the REST API in four sequential steps.

```php
$wp      = new WordPressPublisher($config);
$success = $wp->publish($article, $imagePath, $seoMeta);  // bool
```

| Step | Endpoint | Notes |
|---|---|---|
| Upload image | `POST /wp-json/wp/v2/media` | Raw binary upload; returns `media_id` |
| Resolve tags | `GET/POST /wp-json/wp/v2/tags` | Finds existing by exact name or creates new |
| Resolve category | `GET/POST /wp-json/wp/v2/categories` | Matches by name from SEO keyword; creates if no match |
| Create post | `POST /wp-json/wp/v2/posts` | Assigns category, tags, featured media, Yoast SEO meta |

**Auto category logic:**
- Fetches all WP categories (up to 100)
- Tries to match `focus_keyword` then each SEO tag against existing category names (case-insensitive)
- On match → assigns that category ID
- On no match → creates a new category named from the focus keyword
- Fallback → Uncategorized (ID 1)

Additional behaviour:
- `draft_mode: true` overrides `wordpress.status` globally to `'draft'`
- `article['wp_site']` overrides the global `site_url` per article
- Tag failures are non-fatal — post still publishes with whichever tags succeed

---

### CustomSitePublisher — `src/CustomSitePublisher.php`

Inserts articles directly into a MySQL table via PDO.

```php
$db = new CustomSitePublisher($config);

$db->publish($article, $imagePath, $seoMeta);  // bool

$drafts = $db->getPendingDrafts();   // all rows with status='draft'
$db->approvePost(42);               // SET status='published' WHERE id=42
$db->rejectPost(43);                // DELETE WHERE id=43

$recent = $db->getRecentPosts(20);  // id, title, source, language, image, status, created_at
```

All queries use prepared statements. `tags` stored as JSON. Image copied into `uploads_dir`.

---

### Mailer — `src/Mailer.php`

Sends plain-text email notifications. Never throws — all failures are logged internally.

```php
$mailer = new Mailer($config);

$mailer->sendSuccess($article['title'], $siteUrl, $stats['today_posted']);
$mailer->sendError('WordPress API returned 401', 'WordPressPublisher::publish');
```

| Mode | How |
|---|---|
| `use_smtp: false` | PHP built-in `mail()` |
| `use_smtp: true` | Raw `fsockopen` SMTP — no library needed |

SMTP flow: connect → read 220 → EHLO → AUTH LOGIN → base64 credentials → MAIL FROM → RCPT TO → DATA → QUIT. Each step logged with `Logger::info()`.

---

## How It Works

```
Browser / Dashboard
        │  Run Now button  →  api.php run_now  →  shell_exec (background)
        │
php run.php  (or cron)
        │
        ▼
  Pause check → exit if general.pause = true
        │
        ▼
  RssFetcher::fetch()
  ┌────────────────────────────────────────────┐
  │  Loop all enabled feeds                    │
  │  → cURL download XML (15s timeout)         │
  │  → Parse RSS 2.0 / Atom / RDF             │
  │  → Auto-detect language if set to 'auto'  │
  │  → Extract title, body, image URL         │
  │  → Skip if Logger::isPosted(url)          │
  │  → Stop when per-run limit reached        │
  └────────────────────────────────────────────┘
        │  array of article arrays
        ▼
  foreach article:
  ┌────────────────────────────────────────────┐
  │  AiService                                 │
  │  → generateTitle()    new SEO headline     │
  │  → rewriteArticle()   full HTML body       │
  │  → generateExcerpt()  2-3 sentence blurb  │
  │  → generateSeoMeta()  title/desc/kw/tags  │
  └────────────────────────────────────────────┘
        │
        ▼
  ┌────────────────────────────────────────────┐
  │  ImageService                              │
  │  1. Feed image available?                  │
  │     YES → download feed image directly     │
  │     NO  → search Pixabay with keyword      │
  │  2. Smart center-crop to output size       │
  │  3. Brightness / contrast adjustment       │
  │  4. Overlay title (LTR or RTL font)        │
  │  5. Watermark stamp                        │
  │  6. Save JPEG to images/                   │
  └────────────────────────────────────────────┘
        │
        ▼
  ┌────────────────────────────────────────────┐
  │  WordPressPublisher (if enabled)           │
  │  → Upload image to WP Media Library        │
  │  → Resolve / auto-create tags              │
  │  → Resolve / auto-create category          │
  │     (match by SEO keyword → create new)    │
  │  → POST /wp/v2/posts + Yoast SEO meta      │
  └────────────────────────────────────────────┘
        │
        ▼
  ┌────────────────────────────────────────────┐
  │  CustomSitePublisher (if enabled)          │
  │  → Copy image to uploads_dir               │
  │  → INSERT INTO blogs via PDO               │
  └────────────────────────────────────────────┘
        │
        ▼
  Logger::markPosted(url)
  Logger::info('Published: ...')
  Mailer::sendSuccess() or sendError()
  sleep(2) between articles
        │
        ▼
  Dashboard reflects results
  ┌────────────────────────────────────────────┐
  │  index.php  → stat cards update            │
  │  posts.php  → post appears in history      │
  │             → or draft queue if paused     │
  │  Activity log auto-refreshes every 30s     │
  └────────────────────────────────────────────┘
```

---

## Running the Pipeline

**Via dashboard:** Click **▶ Run Now** on the Dashboard home page.

**Manual CLI:**
```bash
php run.php
```

**Sample output:**
```
Found 3 article(s). Processing...
Processing: Original headline from RSS feed
  Done: Rewritten SEO headline by AI
Processing: Another article title
  Done: Another rewritten title
Processing: Third article title
  Done: Third rewritten title

Done. Processed: 3 article(s).
```

**Cron (every 6 hours):**
```
0 */6 * * * php /xampp/htdocs/Blogy_Assistant/run.php >> /xampp/htdocs/Blogy_Assistant/logs/cron.log 2>&1
```

**Pause without disabling cron:**
```php
'general' => ['pause' => true, ...]
```

**Force all posts to draft:**
```php
'general' => ['draft_mode' => true, ...]
```
Then approve or reject drafts in **Posts → Draft Queue**.

---

## Custom Prompts Guide

Custom prompts let you control exactly how the AI rewrites articles. Leave fields empty to use the built-in prompts. These are optimized for global news coverage:

### Rewrite Article
```
You are a professional global news journalist. Rewrite the following news article in {language} with a {tone} tone.

Original Title: {title}
Original Content: {content}

Requirements:
- Minimum {min_words} words
- Start with a strong news lead (who, what, when, where, why)
- Use inverted pyramid structure (most important facts first)
- Include context and background for international readers
- Keep facts accurate — do not add fictional details
- Use clear, concise journalistic language
- Add subheadings if content exceeds 400 words
- End with implications or what to watch next

Output only the rewritten article body. No introductory sentences.
```

### Generate Title
```
You are a news headline writer for a global news website. Create a compelling headline in {language} for the following article.

Original Title: {title}
Content Summary: {content}

Requirements:
- Maximum 12 words
- Use active voice
- Must convey urgency or importance
- No clickbait — headline must be factually accurate
- Output only the headline, nothing else
```

### Generate Excerpt
```
You are a news editor. Write a brief news summary in {language} for the following article content.

Content: {content}

Requirements:
- Exactly 2-3 sentences
- Cover the 5 Ws: Who, What, When, Where, Why
- Written for a global audience
- Neutral, objective tone
- Output only the excerpt, nothing else
```

### SEO Meta
```
You are an SEO specialist for a global news website. Generate SEO metadata in {language} for the following article.

Title: {title}
Content: {content}

Return a valid JSON object with exactly these keys:
{
  "meta_title": "SEO title under 60 characters",
  "meta_description": "Compelling meta description under 155 characters",
  "focus_keyword": "Primary keyword phrase (2-4 words)",
  "tags": ["tag1", "tag2", "tag3", "tag4", "tag5"]
}

Rules:
- meta_title should include the focus keyword
- tags should be relevant news categories (e.g. Politics, Economy, US, NATO)
- Output only raw JSON — no markdown, no code fences, no extra text
```

---

## Roadmap

| Status | Component | Description |
|---|---|---|
| Done | `config/config.php` | Master configuration — all 8 sections |
| Done | `src/Logger.php` | Activity log, error log, duplicate tracking, stats |
| Done | `src/RssFetcher.php` | RSS 2.0 / Atom / RDF parser with language detection |
| Done | `src/AiService.php` | OpenAI (GPT-4o) + Gemini unified rewriting service |
| Done | `src/ImageService.php` | Feed image priority + Pixabay fallback + GD pipeline |
| Done | `src/WordPressPublisher.php` | WP REST API — media upload, auto tag+category, post creation |
| Done | `src/CustomSitePublisher.php` | PDO/MySQL publisher with draft approval workflow |
| Done | `src/Mailer.php` | Email notifications via mail() and raw SMTP |
| Done | `run.php` | Main CLI entry point / cron runner |
| Done | `dashboard/api.php` | AJAX JSON backend — 15 actions |
| Done | `dashboard/setup.php` | 5-step guided setup wizard |
| Done | `dashboard/index.php` | Live dashboard — stats, logs, quick actions |
| Done | `dashboard/feeds.php` | RSS Feed Manager — add, edit, test, toggle, delete |
| Done | `dashboard/settings.php` | Tabbed settings editor — 5 tabs, all config sections |
| Done | `dashboard/posts.php` | Post history + draft approval queue + error log |
| Done | Auto category management | WP categories auto-matched or created from SEO keywords |
| Done | Feed image priority | RSS feed image used first; Pixabay as fallback only |
| Done | Custom prompts | Per-operation prompt overrides with template variables |
