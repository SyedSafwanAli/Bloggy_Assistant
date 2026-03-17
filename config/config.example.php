<?php

/**
 * Blogy Assistant — Configuration Template
 * Copy this file to config.php and fill in your values.
 * The setup wizard at dashboard/setup.php will also generate this file.
 */

return [

    // ── RSS Feeds ─────────────────────────────────────────────────
    'rss_feeds' => [
        [
            'url'      => 'https://feeds.bbci.co.uk/news/rss.xml',
            'name'     => 'BBC News',
            'language' => 'English',
            'enabled'  => true,
            'wp_site'  => '',          // leave empty to use global WordPress site
        ],
    ],

    // ── AI Provider ───────────────────────────────────────────────
    'ai' => [
        'provider'       => 'openai',          // 'openai' or 'gemini'
        'openai_api_key' => 'YOUR_OPENAI_API_KEY',
        'openai_model'   => 'gpt-4o',
        'gemini_api_key' => 'YOUR_GEMINI_API_KEY',
        'gemini_model'   => 'gemini-2.0-flash',
        'timeout'        => 60,
        'tone'           => 'news',            // professional / casual / news
        'min_words'      => 400,
        'custom_prompts' => [
            'combined' => '',   // leave empty to use built-in 9-step journalist prompt
            'rewrite'  => '',
            'title'    => '',
            'excerpt'  => '',
            'seo_meta' => '',
        ],
    ],

    // ── Pixabay Images ────────────────────────────────────────────
    'pixabay' => [
        'api_key'     => 'YOUR_PIXABAY_API_KEY',
        'image_type'  => 'photo',
        'orientation' => 'horizontal',
        'min_width'   => 1280,
        'per_page'    => 5,
    ],

    // ── Image Processing ──────────────────────────────────────────
    'image' => [
        'output_width'       => 1200,
        'output_height'      => 628,
        'watermark_text'     => 'YourSite.com',
        'watermark_opacity'  => 60,
        'watermark_position' => 'bottom-right',
        'overlay_title'      => true,
        'brightness'         => 5,
        'contrast'           => 10,
        'save_dir'           => __DIR__ . '/../images/',
        'font_path'          => __DIR__ . '/../assets/font.ttf',
        'rtl_font_path'      => __DIR__ . '/../assets/font-rtl.ttf',
    ],

    // ── WordPress Publishing ──────────────────────────────────────
    'wordpress' => [
        'enabled'      => true,
        'site_url'     => 'https://yoursite.com',
        'username'     => 'admin',
        'app_password' => 'xxxx xxxx xxxx xxxx',
        'status'       => 'publish',
        'category'     => [1],
        'author_id'    => 1,
    ],

    // ── Custom Site / Database ────────────────────────────────────
    'custom_site' => [
        'enabled'     => false,
        'db_host'     => 'localhost',
        'db_name'     => 'your_db',
        'db_user'     => 'root',
        'db_pass'     => '',
        'table'       => 'blogs',
        'uploads_dir' => __DIR__ . '/../images/',
    ],

    // ── Email Notifications ───────────────────────────────────────
    'email' => [
        'enabled'        => false,
        'recipient'      => 'admin@yoursite.com',
        'subject_prefix' => '[AutoBlogger]',
        'use_smtp'       => false,
        'smtp_host'      => '',
        'smtp_port'      => 587,
        'smtp_user'      => '',
        'smtp_pass'      => '',
        'on_success'     => true,
        'on_error'       => true,
    ],

    // ── Queue Database ────────────────────────────────────────────
    'queue_db' => [
        'host' => 'localhost',
        'name' => 'Bloggy_Assistant',   // DB created in phpMyAdmin — run database/schema.sql
        'user' => 'root',
        'pass' => '',
    ],

    // ── General Settings ──────────────────────────────────────────
    'general' => [
        'language'        => 'English',
        'posts_per_run'   => 3,
        'interval_hours'  => 6,
        'draft_mode'      => false,
        'pause'           => false,
        'posted_log'      => __DIR__ . '/../logs/posted.log',
        'error_log'       => __DIR__ . '/../logs/error.log',
        'activity_log'    => __DIR__ . '/../logs/activity.log',
        'tokens_log'      => __DIR__ . '/../logs/tokens.log',
        'duplicate_check' => true,
    ],

];
