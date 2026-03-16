# Blogy Assistant — Self-Hosted Auto-Blogging Engine

A fully self-hosted PHP auto-blogging system that fetches articles from RSS/Atom feeds, rewrites them with AI in a single optimized call, generates featured images, and publishes them to WordPress — automatically, on a cron schedule. Includes a complete web dashboard for monitoring, configuration, content management, and AI token cost tracking.

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

Blogy Assistant is a zero-dependency, pure-PHP auto-blogging engine that runs on any LAMP/XAMPP server. Point it at RSS/Atom feeds, choose an AI provider (OpenAI GPT-4o or Google Gemini), and it handles the rest — fetching, rewriting, image sourcing, SEO metadata generation, category management, and WordPress publishing — on a configurable schedule with a full web dashboard for monitoring, management, and AI cost tracking.

---

## Features

### Pipeline
- **Multi-feed RSS/Atom support** — unlimited feeds, each with independent language and WordPress site settings
- **Dual AI providers** — OpenAI (GPT-4o and newer) or Google Gemini (2.5 Flash), switchable with one config key
- **Single AI call per article** — `generateAll()` produces title, body, excerpt, meta title, meta description, focus keyword, and tags in one API round-trip (9-step journalist prompt)
- **Per-feed language** — English, Urdu, Arabic, Hindi, French, or auto-detected from feed XML/HTTP headers
- **RTL language support** — Arabic and Urdu use a dedicated RTL font for image overlays
- **Custom prompt overrides** — replace the built-in combined prompt or any individual prompt with your own template
- **Smart image sourcing** — uses the article's own RSS feed image first; falls back to Pixabay with progressive keyword shortening (5→3→2→1 word→"news")
- **Full GD image pipeline** — smart center-crop, watermark, title overlay, brightness/contrast adjustment
- **WordPress REST API publishing** — post to any WP site using Application Passwords, with per-feed site override
- **Auto category matching** — word-overlap scoring against existing WP categories; never creates duplicate or unwanted categories
- **Auto tag management** — finds or creates WP tags based on AI-generated SEO tags
- **Yoast SEO integration** — writes `meta_title`, `meta_description`, and `focus_keyword` to Yoast custom fields automatically
- **Custom DB publishing** — alternative PDO/MySQL direct-insert mode for non-WordPress sites
- **Draft mode & approval flow** — force all posts to draft for manual review before publishing
- **Duplicate detection** — URL-based deduplication via persistent posted log — same article never published twice
- **Email notifications** — success/error alerts via PHP `mail()` or raw SMTP (no library needed)

### Dashboard
- **5-step setup wizard** — guided first-time configuration with live connection tests at every step
- **AI token cost tracking** — real-time token consumption and estimated cost per model (GPT-4o, Gemini Flash, etc.)
- **7-day usage chart** — bar chart of token usage over the past 7 days
- **System health panel** — PHP extension status, disk usage, log file sizes, image count, memory limit
- **Live dashboard** — stat cards, countdown timer to next run, connection status pills, activity log, quick actions
- **RSS Feed Manager** — add, edit, test, enable/disable, and delete feeds without touching config files
- **Settings UI** — tabbed editor for all 8 config sections with inline connection tests and preset buttons
- **Posts & Drafts** — paginated post history with search, direct WP link, draft approval queue, error log viewer
- **Log management** — clear activity, error, posted, or token logs individually or all at once
- **No Composer required** — pure PHP 8.1 + cURL + GD, no external dependencies

---

## Project Structure

```
Blogy_Assistant/
│
├── config/
│   ├── config.php                    # Master configuration (all 8 sections) — gitignored
│   └── config.example.php            # Template with blanked API keys — safe to commit
│
├── src/
│   ├── Logger.php                    # Static logging, duplicate tracking, token stats
│   ├── RssFetcher.php                # RSS 2.0 / Atom / RDF feed parser
│   ├── AiService.php                 # OpenAI + Gemini unified rewriting (single generateAll call)
│   ├── ImageService.php              # Feed image + Pixabay fallback + GD pipeline
│   ├── WordPressPublisher.php        # WordPress REST API publisher with auto-categories
│   ├── CustomSitePublisher.php       # Direct PDO/MySQL publisher
│   └── Mailer.php                    # Email notifications (mail() + raw SMTP)
│
├── dashboard/
│   ├── api.php                       # AJAX JSON backend (21 actions)
│   ├── setup.php                     # 5-step first-time setup wizard
│   ├── index.php                     # Dashboard home — stats, token chart, system health, logs
│   ├── feeds.php                     # RSS Feed Manager
│   ├── settings.php                  # Tabbed settings editor (5 tabs)
│   └── posts.php                     # Post history + search + draft approval queue
│
├── assets/
│   ├── font.ttf                      # LTR font for image title overlays
│   └── font-rtl.ttf                  # RTL font for Arabic / Urdu overlays
│
├── images/                           # Generated featured images (auto-created, gitignored)
│
├── logs/
│   ├── activity.log                  # INFO-level event log
│   ├── error.log                     # Error log
│   ├── posted.log                    # One URL per line — already-published articles
│   └── tokens.log                    # JSON-lines token usage per AI call
│
├── run.php                           # Main CLI entry point / cron runner
├── .gitignore
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
| `mbstring` extension | Multi-byte string handling for non-Latin languages |
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

1. **Clone or copy the project** into your web root:
   ```bash
   git clone https://github.com/SyedSafwanAli/Bloggy_Assistant.git Blogy_Assistant
   ```

2. **Place fonts** in `assets/`:
   - `font.ttf` — any LTR TTF font (e.g. Roboto, Open Sans)
   - `font-rtl.ttf` — Arabic/Urdu-compatible font (e.g. Noto Naskh Arabic)

3. **Create the config file** from the example template:
   ```bash
   cp config/config.example.php config/config.php
   ```

4. **Open the setup wizard** in your browser:
   ```
   http://localhost/Blogy_Assistant/dashboard/setup.php
   ```
   The wizard guides you through all API keys, feeds, and publishing settings with live connection tests.

5. **Or configure manually** — edit `config/config.php` directly, then access the dashboard at:
   ```
   http://localhost/Blogy_Assistant/dashboard/
   ```

6. **Create the database table** (if using custom site mode):
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

7. **Test a manual run:**
   ```bash
   php run.php
   ```

8. **Set up a cron job** to match your `interval_hours` setting:
   ```
   0 */6 * * * php /path/to/Blogy_Assistant/run.php >> /path/to/Blogy_Assistant/logs/cron.log 2>&1
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
    'openai_model'   => 'gpt-4o',
    'gemini_api_key' => 'YOUR_GEMINI_API_KEY',
    'gemini_model'   => 'gemini-2.5-flash',
    'timeout'        => 60,
    'tone'           => 'news',                // professional / casual / news
    'min_words'      => 400,
    'custom_prompts' => [
        'combined' => '',   // leave empty to use built-in 9-step journalist prompt
        'rewrite'  => '',
        'title'    => '',
        'excerpt'  => '',
        'seo_meta' => '',
    ],
],
```

The `combined` prompt is the primary prompt used in `generateAll()`. It receives `{title}`, `{content}`, `{language}`, and `{min_words}` and must return a valid JSON object with: `title`, `body`, `excerpt`, `meta_title`, `meta_description`, `focus_keyword`, `tags`.

Leave `combined` empty to use the built-in 9-step journalist prompt (quality filter → duplicate detection → topic clustering → event writing → headline → rewrite → SEO → internal linking → metadata).

Individual prompts (`rewrite`, `title`, `excerpt`, `seo_meta`) are used as fallbacks if the combined JSON call fails.

**Custom prompt placeholders:**

| Key | Available placeholders |
|---|---|
| `combined` | `{title}` `{content}` `{language}` `{min_words}` |
| `rewrite` | `{title}` `{content}` `{language}` `{tone}` `{min_words}` |
| `title` | `{title}` `{content}` `{language}` |
| `excerpt` | `{content}` `{language}` |
| `seo_meta` | `{title}` `{content}` `{language}` |

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

> **Note:** Pixabay is used as a **fallback only**. If the RSS feed provides its own article image, that image is always used first. When searching Pixabay, the keyword is progressively shortened (5 words → 3 → 2 → 1 → "news") until results are found.

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
    'category'     => [1],                   // last-resort fallback only
    'author_id'    => 1,
],
```

Generate an Application Password: **WP Admin → Users → Edit User → Application Passwords**.

**Auto Category Management:**
- On every publish, the tool fetches all existing WP categories
- Scores each category by word overlap with the article's `focus_keyword` and SEO `tags`
- Assigns the highest-scoring existing category (score must be > 0)
- **Never creates new categories** — if no match is found, falls back to Uncategorized (ID 1)
- Word-overlap matching handles different word orders correctly (e.g. "Oscars 2026" matches "2026 Oscars")

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
    'tokens_log'      => __DIR__ . '/../logs/tokens.log',
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
| Status bar | AI provider + model name, Pixabay, WordPress — green/red indicator pills |
| Token stats | 5 cards: Tokens Today, Cost Today, Total Tokens, Total Cost, Total Errors |
| 7-day chart | Bar chart of token usage — today highlighted in green, past days in purple |
| System Health | PHP extension pills (ok/fail), PHP version, memory limit, disk free/used%, image count, log file sizes |
| Recent Posts | Last 10 published posts with language color pill, source, and date |
| Activity Log | Last 100 log lines, color-coded INFO/ERROR, auto-refreshes every 30s |
| Clear Log buttons | Clear Activity / Clear Errors / Clear Posted / Clear All — instant via API |
| Quick Actions | Run Now (background process), Pause/Resume toggle |

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
- Search bar — filter by title, source, or language in real time
- Columns: Title, Source, Language (color-coded pill), Status, Published At, Actions
- **↗ WP** button in Actions column links directly to the live WordPress post
- Fetches posts from WP REST API when in WordPress mode (no DB required)

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
| Stats | `get_stats`, `get_token_stats`, `get_system_health` |
| Logs | `get_logs`, `clear_posted_log`, `clear_activity_log`, `clear_error_log`, `clear_token_log`, `clear_all_logs` |
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
// ['total_posted' => 42, 'today_posted' => 3, 'error_count' => 1, ...]

// Token tracking
Logger::logTokens(1200, 800, 'gpt-4o', 'openai', 'Article Title Here');

Logger::getTokenStats();
// ['today_tokens' => 2000, 'cost_today' => 0.03, 'total_tokens' => 50000,
//  'cost_total' => 1.25, 'by_day' => [...7 days...], 'calls_today' => 3, ...]

// Log management
Logger::clearActivityLog();
Logger::clearErrorLog();
Logger::clearTokenLog();
Logger::clearPosted();
```

Token cost rates (per 1M tokens, input/output):

| Model | Input | Output |
|---|---|---|
| gpt-4o | $2.50 | $10.00 |
| gpt-4o-mini | $0.15 | $0.60 |
| gpt-4-turbo | $10.00 | $30.00 |
| gemini-2.5-flash | $0.075 | $0.30 |
| gemini-2.0-flash | $0.075 | $0.30 |

All writes use `FILE_APPEND | LOCK_EX` to prevent race conditions under concurrent cron runs. Token log uses JSON-lines format (one JSON object per line).

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

// Primary: single API call returning all fields (4000 max tokens)
AiService::$currentArticleTitle = $article['title'];
$result = $ai->generateAll($originalTitle, $content, 'English');
// Returns:
[
    'title'            => 'High CTR SEO headline',
    'body'             => '<p>Full HTML article...</p>',
    'excerpt'          => '2-3 sentence summary',
    'meta_title'       => 'SEO title ≤ 60 chars',
    'meta_description' => 'Meta description ≤ 155 chars',
    'focus_keyword'    => 'primary keyword phrase',
    'tags'             => ['tag1', 'tag2', 'tag3', 'tag4', 'tag5'],
]

// Fallback individual calls (used if combined JSON fails)
$title   = $ai->generateTitle($originalTitle, $content, 'English');
$body    = $ai->rewriteArticle($title, $content, 'English');
$excerpt = $ai->generateExcerpt($body, 'English');
$seo     = $ai->generateSeoMeta($title, $body, 'English');
```

- `generateAll()` calls the `combined` custom prompt if set, otherwise uses the built-in 9-step journalist prompt
- Token usage is logged automatically after every API call via `Logger::logTokens()`
- `AiService::$currentArticleTitle` (static) passes article context to the token log without changing method signatures
- Falls back to 4 individual calls if the combined JSON response fails to parse
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
2. **Pixabay progressive search** — tries keyword at 5 words → 3 → 2 → 1 word → "news" until results found

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
| Resolve category | `GET /wp-json/wp/v2/categories` | Word-overlap scoring — never creates new categories |
| Create post | `POST /wp-json/wp/v2/posts` | Assigns category, tags, featured media, Yoast SEO meta |

**Auto category logic (word-overlap scoring):**
- Fetches all WP categories (up to 100)
- Splits `focus_keyword` + all `tags` into individual words
- Scores each WP category name by how many words overlap
- Assigns highest-scoring category (score must be > 0)
- If no category scores > 0 → falls back to Uncategorized (ID 1)
- **Never creates new categories** — prevents taxonomy bloat

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
  │  AiService::generateAll()  (single call)   │
  │  → 9-step journalist prompt                │
  │  → Returns JSON: title, body, excerpt,     │
  │    meta_title, meta_description,           │
  │    focus_keyword, tags                     │
  │  → Logs token usage to tokens.log          │
  │  → Falls back to 4 individual calls        │
  │    if JSON parse fails                     │
  └────────────────────────────────────────────┘
        │
        ▼
  ┌────────────────────────────────────────────┐
  │  ImageService                              │
  │  1. Feed image available?                  │
  │     YES → download feed image directly     │
  │     NO  → Pixabay search with progressive  │
  │           keyword shortening               │
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
  │  → Match category by word-overlap score    │
  │    (never creates new categories)          │
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
  │  index.php  → stat cards + token chart     │
  │             → system health panel          │
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
0 */6 * * * php /path/to/Blogy_Assistant/run.php >> /path/to/Blogy_Assistant/logs/cron.log 2>&1
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

Custom prompts let you control exactly how the AI rewrites articles. Leave fields empty to use the built-in prompts.

### Combined Prompt (recommended — single API call)

The combined prompt must return a **valid JSON object** with exactly these keys. This is the most efficient approach as it makes only one API call per article.

```
You are a professional global news journalist, SEO strategist, and editor.

Rewrite the following article and return ONLY valid JSON.

Original Title: {title}
Original Content: {content}
Language: {language}

Return this exact JSON structure:
{
  "title": "High CTR headline under 12 words",
  "body": "Full rewritten article in HTML with paragraphs and subheadings (min {min_words} words)",
  "excerpt": "2-3 sentence news summary",
  "meta_title": "SEO title under 60 characters",
  "meta_description": "SEO description under 155 characters",
  "focus_keyword": "Primary keyword phrase",
  "tags": ["tag1","tag2","tag3","tag4","tag5"]
}

Rules:
- Output only valid JSON, no markdown, no code blocks
- Article body must be HTML (use <p>, <h2>, <h3> tags)
- Minimum {min_words} words in body
- Follow inverted pyramid structure
- Do not copy sentences from the original
```

### Individual Fallback Prompts

These are used if the combined JSON call fails to parse.

#### Rewrite Article
```
You are a professional global news journalist. Rewrite the following news article in {language} with a {tone} tone.

Original Title: {title}
Original Content: {content}

Requirements:
- Minimum {min_words} words
- Start with a strong news lead (who, what, when, where, why)
- Use inverted pyramid structure
- Include context and background for international readers
- Keep facts accurate — do not add fictional details
- Add subheadings if content exceeds 400 words

Output only the rewritten article body in HTML. No introductory sentences.
```

#### Generate Title
```
You are a news headline writer. Create a compelling SEO headline in {language}.

Original Title: {title}
Content: {content}

Requirements:
- Maximum 12 words
- Use active voice
- Must convey urgency or importance
- No clickbait — factually accurate
- Output only the headline, nothing else
```

#### Generate Excerpt
```
Write a brief news summary in {language} for the following content.

Content: {content}

Requirements:
- Exactly 2-3 sentences
- Cover the 5 Ws: Who, What, When, Where, Why
- Neutral, objective tone
- Output only the excerpt, nothing else
```

#### SEO Meta
```
Generate SEO metadata in {language} for the following article.

Title: {title}
Content: {content}

Return valid JSON only:
{
  "meta_title": "SEO title under 60 characters",
  "meta_description": "Meta description under 155 characters",
  "focus_keyword": "Primary keyword phrase (2-4 words)",
  "tags": ["tag1", "tag2", "tag3", "tag4", "tag5"]
}

Output only raw JSON — no markdown, no code fences.
```

---

## Roadmap

| Status | Component | Description |
|---|---|---|
| ✅ Done | `config/config.php` | Master configuration — all 8 sections |
| ✅ Done | `config/config.example.php` | Safe template for version control |
| ✅ Done | `src/Logger.php` | Activity log, error log, duplicate tracking, token stats, log clearing |
| ✅ Done | `src/RssFetcher.php` | RSS 2.0 / Atom / RDF parser with language detection |
| ✅ Done | `src/AiService.php` | OpenAI + Gemini — single `generateAll()` call with 9-step prompt |
| ✅ Done | `src/ImageService.php` | Feed image priority + Pixabay progressive fallback + GD pipeline |
| ✅ Done | `src/WordPressPublisher.php` | WP REST API — media upload, auto tags, word-overlap category matching |
| ✅ Done | `src/CustomSitePublisher.php` | PDO/MySQL publisher with draft approval workflow |
| ✅ Done | `src/Mailer.php` | Email notifications via mail() and raw SMTP |
| ✅ Done | `run.php` | Main CLI entry point / cron runner |
| ✅ Done | `dashboard/api.php` | AJAX JSON backend — 21 actions |
| ✅ Done | `dashboard/setup.php` | 5-step guided setup wizard |
| ✅ Done | `dashboard/index.php` | Live dashboard — stats, token chart, system health, log management |
| ✅ Done | `dashboard/feeds.php` | RSS Feed Manager — add, edit, test, toggle, delete |
| ✅ Done | `dashboard/settings.php` | Tabbed settings editor — 5 tabs, all config sections |
| ✅ Done | `dashboard/posts.php` | Post history + search + WP link + draft approval queue |
| ✅ Done | Single AI call | `generateAll()` — all fields in one API round-trip, 4-call fallback |
| ✅ Done | Token cost tracking | Per-call logging, daily/total stats, 7-day chart, cost estimation |
| ✅ Done | Auto category matching | Word-overlap scoring — never creates unwanted categories |
| ✅ Done | Feed image priority | RSS feed image used first; Pixabay progressive fallback |
| ✅ Done | System health panel | Extensions, disk usage, log sizes, image count |
| ✅ Done | Log management | Clear individual or all logs from dashboard |
| ⬜ Planned | Multi-site dashboard | Unified view across multiple WordPress installations |
| ⬜ Planned | Anthropic Claude support | Third AI provider option |
| ⬜ Planned | Article preview | Preview AI-rewritten article before publishing |
| ⬜ Planned | Webhook trigger | Trigger pipeline via HTTP POST (no cron needed) |
