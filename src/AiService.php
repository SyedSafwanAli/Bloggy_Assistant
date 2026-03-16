<?php

class AiService
{
    // Languages that use right-to-left script
    public static array $rtlLanguages = ['Urdu', 'Arabic'];

    // Set this before generateAll() so token log records the article title
    public static string $currentArticleTitle = '';

    private string $provider;
    private string $openaiApiKey;
    private string $openaiModel;
    private string $geminiApiKey;
    private string $geminiModel;
    private int    $timeout;
    private string $tone;
    private int    $minWords;
    private array  $customPrompts;

    public function __construct(array $cfg)
    {
        $ai = $cfg['ai'] ?? [];

        $this->provider      = $ai['provider']       ?? 'openai';
        $this->openaiApiKey  = $ai['openai_api_key'] ?? '';
        $this->openaiModel   = $ai['openai_model']   ?? 'gpt-4o';
        $this->geminiApiKey  = $ai['gemini_api_key'] ?? '';
        $this->geminiModel   = $ai['gemini_model']   ?? 'gemini-2.0-flash';
        $this->timeout       = (int) ($ai['timeout']   ?? 60);
        $this->tone          = $ai['tone']            ?? 'professional';
        $this->minWords      = (int) ($ai['min_words'] ?? 600);
        $this->customPrompts = $ai['custom_prompts']  ?? [
            'combined' => '',
            'rewrite'  => '',
            'title'    => '',
            'excerpt'  => '',
            'seo_meta' => '',
        ];
    }

    // -------------------------------------------------------------------------
    // Public methods
    // -------------------------------------------------------------------------

    /**
     * Single API call that returns title, body, excerpt, and all SEO meta.
     * Falls back to the user's custom 'combined' prompt if set.
     * On JSON parse failure, falls back to running 4 individual calls.
     *
     * @return array{title:string, content:string, excerpt:string,
     *               meta_title:string, meta_description:string,
     *               focus_keyword:string, tags:array}
     */
    public function generateAll(string $originalTitle, string $content, string $language = ''): array
    {
        $lang     = $language ?: 'English';
        $toneMap  = [
            'professional' => 'formal expert tone',
            'casual'       => 'friendly conversational tone',
            'news'         => 'neutral journalistic tone',
        ];
        $toneDesc = $toneMap[$this->tone] ?? 'formal expert tone';

        if (!empty($this->customPrompts['combined'])) {
            $prompt = str_replace(
                ['{title}',        '{content}', '{language}', '{tone}',    '{min_words}'],
                [$originalTitle,   $content,    $lang,         $toneDesc,   $this->minWords],
                $this->customPrompts['combined']
            );
        } else {
            $prompt = "You are a professional journalist and SEO expert.\n\n"
                . "Rewrite the following news article in {$lang} with a {$toneDesc}.\n\n"
                . "Original Title:\n{$originalTitle}\n\n"
                . "Original Content:\n{$content}\n\n"
                . "Requirements:\n"
                . "- Minimum {$this->minWords} words\n"
                . "- Follow the inverted pyramid journalism structure\n"
                . "- Keep facts accurate and do not add fictional information\n"
                . "- Use clear HTML headings (<h2>, <h3>) if article is longer than 400 words\n"
                . "- Use <p>, <ul>, <li>, <strong> tags for proper formatting\n"
                . "- Write for a global audience\n\n"
                . "Return ONLY valid JSON with this exact format — no markdown, no code blocks:\n"
                . "{\n"
                . "  \"title\": \"Compelling news headline under 12 words\",\n"
                . "  \"body\": \"Full rewritten article HTML\",\n"
                . "  \"excerpt\": \"2-3 sentence news summary covering Who What When Where Why\",\n"
                . "  \"meta_title\": \"SEO title under 60 characters\",\n"
                . "  \"meta_description\": \"SEO meta description under 155 characters\",\n"
                . "  \"focus_keyword\": \"Primary keyword phrase (2-4 words)\",\n"
                . "  \"tags\": [\"tag1\",\"tag2\",\"tag3\",\"tag4\",\"tag5\"]\n"
                . "}";
        }

        $raw = trim($this->ask($prompt, 4000));

        // Strip markdown code fences
        $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw);
        $raw = preg_replace('/\s*```$/m', '', $raw);
        $raw = trim($raw);

        $decoded = json_decode($raw, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return [
                'title'            => trim($decoded['title']            ?? $originalTitle),
                'content'          => trim($decoded['body']             ?? $decoded['content'] ?? ''),
                'excerpt'          => trim($decoded['excerpt']          ?? ''),
                'meta_title'       => trim($decoded['meta_title']       ?? ''),
                'meta_description' => trim($decoded['meta_description'] ?? ''),
                'focus_keyword'    => trim($decoded['focus_keyword']    ?? ''),
                'tags'             => (array) ($decoded['tags']         ?? []),
            ];
        }

        // JSON failed — fall back to individual calls
        Logger::error('AiService::generateAll JSON parse failed — falling back to individual calls');
        $newTitle   = $this->generateTitle($originalTitle, $content, $lang);
        $newContent = $this->rewriteArticle($newTitle, $content, $lang);
        $excerpt    = $this->generateExcerpt($newContent, $lang);
        $seo        = $this->generateSeoMeta($newTitle, $newContent, $lang);

        return [
            'title'            => $newTitle,
            'content'          => $newContent,
            'excerpt'          => $excerpt,
            'meta_title'       => $seo['meta_title']       ?? '',
            'meta_description' => $seo['meta_description'] ?? '',
            'focus_keyword'    => $seo['focus_keyword']    ?? '',
            'tags'             => $seo['tags']             ?? [],
        ];
    }

    public function generateTitle(string $originalTitle, string $content, string $language = ''): string
    {
        $lang    = $language ?: 'English';
        $snippet = mb_substr(strip_tags($content), 0, 300);

        if (!empty($this->customPrompts['title'])) {
            $prompt = str_replace(
                ['{title}',        '{content}', '{language}'],
                [$originalTitle,  $content,     $lang],
                $this->customPrompts['title']
            );
        } else {
            $prompt = "You are a professional blog writer.\n"
                . "Generate ONE compelling SEO-friendly blog title.\n"
                . "Language: {$lang}\n"
                . "Original headline: {$originalTitle}\n"
                . "Content snippet: {$snippet}\n"
                . "Rules: max 70 characters, no quotes, no numbering.\n"
                . "Return ONLY the title, nothing else.";
        }

        return trim($this->ask($prompt));
    }

    public function rewriteArticle(string $title, string $content, string $language = ''): string
    {
        $lang = $language ?: 'English';

        $toneMap = [
            'professional' => 'formal expert tone',
            'casual'       => 'friendly conversational tone',
            'news'         => 'neutral journalistic tone',
        ];
        $toneDesc = $toneMap[$this->tone] ?? 'formal expert tone';

        if (!empty($this->customPrompts['rewrite'])) {
            $prompt = str_replace(
                ['{title}', '{content}', '{language}', '{tone}', '{min_words}'],
                [$title,    $content,    $lang,         $toneDesc, $this->minWords],
                $this->customPrompts['rewrite']
            );
        } else {
            $prompt = "You are an expert blog writer. Rewrite the following as a complete,\n"
                . "engaging, original blog post in {$lang} with a {$toneDesc}.\n"
                . "Title: {$title}\n"
                . "Original content: {$content}\n"
                . "Requirements:\n"
                . "- Minimum {$this->minWords} words\n"
                . "- Use proper HTML: <h2>, <h3>, <p>, <ul>, <li>, <strong>\n"
                . "- Natural human tone, add your own insights\n"
                . "- Do NOT copy sentences verbatim\n"
                . "- Return ONLY the blog HTML content, nothing else.";
        }

        return trim($this->ask($prompt));
    }

    public function generateExcerpt(string $content, string $language = ''): string
    {
        $lang    = $language ?: 'English';
        $snippet = mb_substr(strip_tags($content), 0, 800);

        if (!empty($this->customPrompts['excerpt'])) {
            $prompt = str_replace(
                ['{content}', '{language}'],
                [$snippet,    $lang],
                $this->customPrompts['excerpt']
            );
        } else {
            $prompt = "Write a concise blog excerpt in {$lang}.\n"
                . "Max 160 characters. No quotes. Return ONLY the excerpt.\n"
                . "Article: {$snippet}";
        }

        return trim($this->ask($prompt));
    }

    public function generateSeoMeta(string $title, string $content, string $language = ''): array
    {
        $lang    = $language ?: 'English';
        $snippet = mb_substr(strip_tags($content), 0, 500);

        if (!empty($this->customPrompts['seo_meta'])) {
            $prompt = str_replace(
                ['{title}', '{content}', '{language}'],
                [$title,    $snippet,    $lang],
                $this->customPrompts['seo_meta']
            );
        } else {
            $prompt = "Generate SEO metadata in {$lang} for this blog post.\n"
                . "Title: {$title}\n"
                . "Content: {$snippet}\n"
                . "Return ONLY valid JSON (no markdown, no backticks):\n"
                . "{\n"
                . "  \"meta_title\": \"max 60 chars\",\n"
                . "  \"meta_description\": \"max 160 chars\",\n"
                . "  \"focus_keyword\": \"main keyword\",\n"
                . "  \"tags\": [\"tag1\",\"tag2\",\"tag3\",\"tag4\",\"tag5\"]\n"
                . "}";
        }

        $raw = trim($this->ask($prompt));

        // Strip markdown code fences if present (```json ... ``` or ``` ... ```)
        $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw);
        $raw = preg_replace('/\s*```$/', '', $raw);
        $raw = trim($raw);

        $decoded = json_decode($raw, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        // Fallback on parse failure
        return [
            'meta_title'       => mb_substr($title, 0, 60),
            'meta_description' => mb_substr(strip_tags($content), 0, 160),
            'focus_keyword'    => '',
            'tags'             => [],
        ];
    }

    // -------------------------------------------------------------------------
    // Provider routing
    // -------------------------------------------------------------------------

    private function ask(string $prompt, int $maxTokens = 2000): string
    {
        return match ($this->provider) {
            'openai' => $this->askOpenAI($prompt, $maxTokens),
            'gemini' => $this->askGemini($prompt, $maxTokens),
            default  => throw new RuntimeException(
                "AiService: unknown provider \"{$this->provider}\". Expected 'openai' or 'gemini'."
            ),
        };
    }

    // -------------------------------------------------------------------------
    // OpenAI
    // -------------------------------------------------------------------------

    private function askOpenAI(string $prompt, int $maxTokens = 2000): string
    {
        $url  = 'https://api.openai.com/v1/chat/completions';
        $body = json_encode([
            'model'       => $this->openaiModel,
            'messages'    => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0.7,
            'max_tokens'  => $maxTokens,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->openaiApiKey,
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('AiService/OpenAI cURL error: ' . $curlErr);
        }

        $data = json_decode($response, true);

        if (!empty($data['error'])) {
            throw new RuntimeException(
                'AiService/OpenAI API error: ' . ($data['error']['message'] ?? $response)
            );
        }

        $text = $data['choices'][0]['message']['content'] ?? null;

        if ($text === null) {
            throw new RuntimeException('AiService/OpenAI: unexpected response structure: ' . $response);
        }

        // Log token usage
        $usage = $data['usage'] ?? [];
        if (!empty($usage)) {
            Logger::logTokens(
                (int) ($usage['prompt_tokens']     ?? 0),
                (int) ($usage['completion_tokens'] ?? 0),
                $this->openaiModel,
                'openai',
                self::$currentArticleTitle
            );
        }

        return $text;
    }

    // -------------------------------------------------------------------------
    // Google Gemini
    // -------------------------------------------------------------------------

    private function askGemini(string $prompt, int $maxTokens = 2000): string
    {
        // Strip 'models/' prefix if user accidentally included it in config
        $modelId = ltrim($this->geminiModel, '/');
        if (strpos($modelId, 'models/') === 0) {
            $modelId = substr($modelId, strlen('models/'));
        }

        $url = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            urlencode($modelId),
            urlencode($this->geminiApiKey)
        );

        $body = json_encode([
            'contents'         => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => ['temperature' => 0.7, 'maxOutputTokens' => $maxTokens],
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        ]);

        $response = curl_exec($ch);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('AiService/Gemini cURL error: ' . $curlErr);
        }

        $data = json_decode($response, true);

        if (!empty($data['error'])) {
            throw new RuntimeException(
                'AiService/Gemini API error: ' . ($data['error']['message'] ?? $response)
            );
        }

        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if ($text === null) {
            throw new RuntimeException('AiService/Gemini: unexpected response structure: ' . $response);
        }

        // Log token usage
        $usage = $data['usageMetadata'] ?? [];
        if (!empty($usage)) {
            Logger::logTokens(
                (int) ($usage['promptTokenCount']     ?? 0),
                (int) ($usage['candidatesTokenCount'] ?? 0),
                $this->geminiModel,
                'gemini',
                self::$currentArticleTitle
            );
        }

        return $text;
    }
}
