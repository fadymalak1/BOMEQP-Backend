<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GeminiService
{
    private $apiKey;
    private $apiUrl;
    private $model;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
        if (empty($this->apiKey)) {
            throw new \Exception('GEMINI_API_KEY is not set in .env file');
        }
        
        // Use the correct model name for Gemini API
        // Available models: gemini-2.0-flash, gemini-1.5-pro, gemini-1.5-flash, gemini-pro
        $this->model = config('services.gemini.model', 'gemini-2.0-flash');
        
        // Build API URL with correct model
        $baseUrl = config('services.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta/models');
        $this->apiUrl = $baseUrl . '/' . $this->model . ':generateContent';
        
        Log::info('GeminiService initialized', [
            'model' => $this->model,
            'api_url' => $this->apiUrl
        ]);
    }

    /**
     * Analyze certificate image and generate HTML template using a 2-step pipeline
     */
    public function analyzeCertificateImage($imagePath, $orientation = 'landscape'): array
    {
        try {
            // STEP 1: Vision -> JSON (template_config)
            Log::info('Gemini Pipeline Step 1: Vision -> JSON');
            $templateConfig = $this->extractTemplateConfigFromImage($imagePath, $orientation);
            
            // STEP 2: JSON -> HTML
            Log::info('Gemini Pipeline Step 2: JSON -> HTML');
            $templateHtml = $this->generateHtmlFromConfigUsingAi($templateConfig);
            
            return [
                'template_config' => $templateConfig,
                'template_html' => $templateHtml
            ];
            
        } catch (\Exception $e) {
            Log::error('Gemini Pipeline Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception('Failed to generate template from image: ' . $e->getMessage());
        }
    }

    /**
     * Step 1: Analyze certificate image and extract template_config
     */
    private function extractTemplateConfigFromImage($imagePath, $orientation, $retry = true): array
    {
        // Read image file
        $imageData = Storage::disk('public')->get($imagePath);
        $base64Image = base64_encode($imageData);
        
        // Get mime type
        $mimeType = mime_content_type(Storage::disk('public')->path($imagePath));
        
        // Prepare prompt for Step 1
        $prompt = $this->buildVisionPrompt($orientation);
        
        // Call Gemini API
        $response = $this->callGeminiApi($prompt, $base64Image, $mimeType);
        
        try {
            $data = $this->parseGeminiJsonResponse($response);
            
            if (!isset($data['template_config'])) {
                throw new \Exception('Gemini response missing template_config');
            }
            
            return $data['template_config'];
            
        } catch (\Exception $e) {
            if ($retry) {
                Log::warning('Step 1 failed, retrying with stronger instructions...', ['error' => $e->getMessage()]);
                $retryPrompt = "Your previous response contained invalid JSON. \n" . 
                              "Return ONLY a VALID JSON object named template_config. \n" . 
                              "No HTML. No explanations. \n\n" . $prompt;
                
                $response = $this->callGeminiApi($retryPrompt, $base64Image, $mimeType);
                return $this->extractTemplateConfigFromImageAfterRetry($response);
            }
            throw $e;
        }
    }

    /**
     * Handle parsing after a retry
     */
    private function extractTemplateConfigFromImageAfterRetry($response): array
    {
        $data = $this->parseGeminiJsonResponse($response);
        if (!isset($data['template_config'])) {
            throw new \Exception('Gemini retry response missing template_config');
        }
        return $data['template_config'];
    }

    /**
     * Step 2: Generate HTML from template_config using AI
     */
    private function generateHtmlFromConfigUsingAi(array $templateConfig): string
    {
        $prompt = "Generate a complete HTML certificate template using the following template_config JSON.
        
Requirements:
- Return HTML only. No explanations.
- Use inline CSS styles.
- The design should match the specifications in the template_config.
- Use placeholders like {{trainee_name}}, {{course_name}}, {{certificate_number}}, {{issue_date}}, {{verification_code}}.
- Ensure it's A4 size (" . ($templateConfig['layout']['orientation'] === 'landscape' ? '297mm × 210mm' : '210mm × 297mm') . ").
- IMPORTANT: Return ONLY the HTML code, starting with <!DOCTYPE html>.

JSON Config:
" . json_encode($templateConfig, JSON_PRETTY_PRINT);

        $response = $this->callGeminiApi($prompt);
        
        // For HTML, we don't parse JSON, we just extract the HTML part
        $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';
        
        // Extract HTML if it's wrapped in code blocks
        if (preg_match('/<!DOCTYPE html>.*<\/html>/is', $text, $matches)) {
            return $matches[0];
        }
        
        // If not found with regex, try cleaning common markdown
        $html = preg_replace('/^```html\s*/i', '', $text);
        $html = preg_replace('/```\s*$/', '', $html);
        
        return trim($html);
    }

    /**
     * Build Vision prompt for Step 1
     */
    private function buildVisionPrompt($orientation): string
    {
        return "Analyze the certificate image and extract its design elements.
        
Return ONLY a VALID JSON object named \"template_config\".
Do NOT include HTML. 
Do NOT include explanations.
If you cannot finish the JSON, STOP BEFORE STARTING.

Requirements for analysis:
- The certificate is A4 size: " . ($orientation === 'landscape' ? '297mm × 210mm' : '210mm × 297mm') . "
- Identify colors (hex), fonts, sizes, and exact positioning.
- Map elements to this structure: layout, title, trainee_name, course_name, subtitle_before, subtitle_after, certificate_number, issue_date, verification_code.

Return format:
{
    \"template_config\": {
        \"layout\": { \"orientation\": \"$orientation\", \"border_color\": \"#...\", \"border_width\": \"10px\", \"background_color\": \"#...\" },
        \"title\": { \"show\": true, \"text\": \"...\", \"position\": \"...\", \"font_size\": \"...\", \"color\": \"#...\", \"text_align\": \"...\" },
        \"trainee_name\": { \"show\": true, \"font_size\": \"...\", \"color\": \"#...\", \"text_align\": \"...\" },
        ...
    }
}";
    }

    /**
     * Call Gemini API
     */
    private function callGeminiApi($prompt, $base64Image = null, $mimeType = null): array
    {
        $url = $this->apiUrl;
        
        $parts = [['text' => $prompt]];
        if ($base64Image && $mimeType) {
            $parts[] = [
                'inline_data' => [
                    'mime_type' => $mimeType,
                    'data' => $base64Image
                ]
            ];
        }

        $payload = [
            'contents' => [['parts' => $parts]],
            'generationConfig' => [
                'temperature' => 0.2,
                'maxOutputTokens' => 4096,
            ]
        ];

        try {
            $response = Http::timeout(120)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-goog-api-key' => $this->apiKey,
                ])
                ->post($url, $payload);

            if (!$response->successful()) {
                throw new \Exception('Gemini API request failed: ' . $response->body());
            }

            return $response->json();

        } catch (\Exception $e) {
            Log::error('Gemini API Error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Robust JSON parsing with Guards
     */
    private function parseGeminiJsonResponse($response): array
    {
        $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';
        
        if (empty($text)) {
            throw new \Exception('Empty response from Gemini API');
        }

        // Extract JSON if wrapped in markdown
        $jsonText = trim($text);
        $jsonText = preg_replace('/^```json\s*/i', '', $jsonText);
        $jsonText = preg_replace('/```\s*$/', '', $jsonText);
        $jsonText = trim($jsonText);

        // --- THE GOLDEN GUARDS ---
        
        // 1. Must end with a closing brace
        if (!str_ends_with($jsonText, '}')) {
            throw new \Exception('Gemini returned truncated JSON (No closing brace)');
        }

        // 2. Balanced braces
        if (substr_count($jsonText, '{') !== substr_count($jsonText, '}')) {
            throw new \Exception('Unbalanced JSON braces (Response was likely cut off)');
        }

        // 3. Attempt parse
        $data = json_decode($jsonText, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Attempt to clean control characters before giving up
            $cleaned = preg_replace('/[\x00-\x1F\x7F]/', '', $jsonText);
            $data = json_decode($cleaned, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('JSON Parse Error: ' . json_last_error_msg());
            }
        }

        return $data;
    }
}
