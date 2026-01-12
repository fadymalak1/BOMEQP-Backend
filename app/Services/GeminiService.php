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
        // Available models for v1beta: gemini-1.5-pro, gemini-1.5-flash, gemini-pro
        // Note: gemini-1.5-flash-latest is not available in v1beta, use gemini-1.5-flash instead
        $this->model = config('services.gemini.model', 'gemini-1.5-flash');
        
        // Build API URL with correct model
        $baseUrl = config('services.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta/models');
        $this->apiUrl = $baseUrl . '/' . $this->model . ':generateContent';
        
        Log::info('GeminiService initialized', [
            'model' => $this->model,
            'api_url' => $this->apiUrl
        ]);
    }

    /**
     * Analyze certificate image and generate HTML template
     */
    public function analyzeCertificateImage($imagePath, $orientation = 'landscape'): array
    {
        try {
            // Read image file
            $imageData = Storage::disk('public')->get($imagePath);
            $base64Image = base64_encode($imageData);
            
            // Get mime type
            $mimeType = mime_content_type(Storage::disk('public')->path($imagePath));
            
            // Prepare prompt for Gemini
            $prompt = $this->buildPrompt($orientation);
            
            // Call Gemini API
            $response = $this->callGeminiApi($base64Image, $mimeType, $prompt);
            
            // Parse response and extract template_config and HTML
            return $this->parseGeminiResponse($response);
            
        } catch (\Exception $e) {
            Log::error('Gemini API Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception('Failed to analyze certificate image: ' . $e->getMessage());
        }
    }

    /**
     * Build prompt for Gemini
     */
    private function buildPrompt($orientation): string
    {
        return "You are an expert at analyzing certificate designs and generating HTML templates.

Analyze this certificate image and generate:
1. A complete HTML template that matches the design exactly
2. A JSON configuration (template_config) that describes all elements

Requirements:
- The certificate is A4 size: " . ($orientation === 'landscape' ? '297mm × 210mm (landscape)' : '210mm × 297mm (portrait)') . "
- Use exact colors, fonts, sizes, and positioning from the image
- Identify all text elements: title, trainee name, course name, subtitles, dates, certificate number, verification code
- Identify background colors, borders, and any background images
- Extract exact font sizes, colors, and text alignment
- The HTML should be complete and ready to use with CSS inline styles
- Use placeholders like {{trainee_name}}, {{course_name}}, {{certificate_number}}, {{issue_date}}, {{verification_code}} for dynamic content

Return your response in this exact JSON format:
{
    \"template_config\": {
        \"layout\": {
            \"orientation\": \"" . $orientation . "\",
            \"border_color\": \"#hexcolor\",
            \"border_width\": \"10px\",
            \"background_color\": \"#hexcolor\"
        },
        \"title\": {
            \"show\": true,
            \"text\": \"Certificate Title\",
            \"position\": \"top-center\",
            \"font_size\": \"32pt\",
            \"font_weight\": \"bold\",
            \"color\": \"#hexcolor\",
            \"text_align\": \"center\"
        },
        \"trainee_name\": {
            \"show\": true,
            \"position\": \"center\",
            \"font_size\": \"26pt\",
            \"font_weight\": \"bold\",
            \"color\": \"#hexcolor\",
            \"text_align\": \"center\"
        },
        \"course_name\": {
            \"show\": true,
            \"position\": \"center\",
            \"font_size\": \"18pt\",
            \"color\": \"#hexcolor\",
            \"text_align\": \"center\"
        },
        \"subtitle_before\": {
            \"show\": true,
            \"text\": \"Subtitle text before name\",
            \"position\": \"center\",
            \"font_size\": \"14pt\",
            \"color\": \"#hexcolor\",
            \"text_align\": \"center\"
        },
        \"subtitle_after\": {
            \"show\": true,
            \"text\": \"Subtitle text after name\",
            \"position\": \"center\",
            \"font_size\": \"14pt\",
            \"color\": \"#hexcolor\",
            \"text_align\": \"center\"
        },
        \"certificate_number\": {
            \"show\": true,
            \"position\": \"bottom-left\",
            \"text_align\": \"left\"
        },
        \"issue_date\": {
            \"show\": true,
            \"position\": \"bottom-center\",
            \"text_align\": \"center\"
        },
        \"verification_code\": {
            \"show\": true,
            \"position\": \"bottom-right\",
            \"text_align\": \"right\"
        }
    },
    \"template_html\": \"<complete HTML string here>\"
}

Important:
- Only return valid JSON, no markdown, no code blocks
- Extract exact colors from the image (use color picker values)
- Extract exact font sizes (estimate from image scale)
- Identify all text elements and their positions
- Generate complete HTML with inline CSS that matches the design exactly
- Use proper HTML structure with all necessary CSS for PDF generation";
    }

    /**
     * Call Gemini API with image
     */
    private function callGeminiApi($base64Image, $mimeType, $prompt): array
    {
        // Build URL with API key
        $url = $this->apiUrl . '?key=' . urlencode($this->apiKey);
        
        Log::info('Calling Gemini API', [
            'url' => $url,
            'model' => $this->model,
            'mime_type' => $mimeType
        ]);
        
        // Prepare payload according to Gemini API format
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => $prompt
                        ],
                        [
                            'inline_data' => [
                                'mime_type' => $mimeType,
                                'data' => $base64Image
                            ]
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.4,
                'topK' => 32,
                'topP' => 1,
                'maxOutputTokens' => 4096,
            ]
        ];

        try {
            $response = Http::timeout(120)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post($url, $payload);

            if (!$response->successful()) {
                $errorBody = $response->body();
                $errorJson = $response->json();
                
                Log::error('Gemini API Request Failed', [
                    'status' => $response->status(),
                    'url' => $url,
                    'model' => $this->model,
                    'error_body' => $errorBody,
                    'error_json' => $errorJson
                ]);
                
                $errorMessage = 'Gemini API request failed';
                if (isset($errorJson['error']['message'])) {
                    $errorMessage .= ': ' . $errorJson['error']['message'];
                } elseif (isset($errorJson['error'])) {
                    $errorMessage .= ': ' . json_encode($errorJson['error']);
                } else {
                    $errorMessage .= ': ' . $errorBody;
                }
                
                throw new \Exception($errorMessage);
            }

            $responseData = $response->json();
            
            Log::info('Gemini API Response received', [
                'has_candidates' => isset($responseData['candidates']),
                'candidates_count' => count($responseData['candidates'] ?? [])
            ]);
            
            if (!isset($responseData['candidates']) || empty($responseData['candidates'])) {
                Log::error('Gemini API Invalid Response', [
                    'response' => $responseData
                ]);
                throw new \Exception('Invalid response format from Gemini API: No candidates in response');
            }

            return $responseData;

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Gemini API Connection Error', [
                'error' => $e->getMessage(),
                'url' => $url
            ]);
            throw new \Exception('Failed to connect to Gemini API: ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Gemini API Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'url' => $url,
                'model' => $this->model
            ]);
            throw $e;
        }
    }

    /**
     * Parse Gemini response and extract template_config and HTML
     */
    private function parseGeminiResponse($response): array
    {
        try {
            // Extract text from response
            $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';
            
            if (empty($text)) {
                throw new \Exception('Empty response from Gemini API');
            }

            Log::info('Parsing Gemini response', [
                'text_length' => strlen($text),
                'text_preview' => substr($text, 0, 200)
            ]);

            // Try to extract JSON from response
            // Gemini might return JSON wrapped in markdown code blocks
            $jsonText = $this->extractJsonFromText($text);
            
            // Parse JSON
            $data = json_decode($jsonText, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('JSON Parse Error', [
                    'error' => json_last_error_msg(),
                    'json_text' => $jsonText
                ]);
                throw new \Exception('Invalid JSON response from Gemini: ' . json_last_error_msg());
            }

            // Validate required fields
            if (!isset($data['template_config']) || !isset($data['template_html'])) {
                Log::error('Missing required fields', [
                    'has_template_config' => isset($data['template_config']),
                    'has_template_html' => isset($data['template_html']),
                    'data_keys' => array_keys($data)
                ]);
                throw new \Exception('Missing required fields in Gemini response');
            }

            Log::info('Gemini response parsed successfully');

            return [
                'template_config' => $data['template_config'],
                'template_html' => $data['template_html'],
                'raw_response' => $text // Keep for debugging
            ];

        } catch (\Exception $e) {
            Log::error('Failed to parse Gemini response', [
                'error' => $e->getMessage(),
                'response' => $response
            ]);
            throw $e;
        }
    }

    /**
     * Extract JSON from text (handles markdown code blocks)
     */
    private function extractJsonFromText($text): string
    {
        // Remove markdown code blocks if present
        $text = preg_replace('/```json\s*/', '', $text);
        $text = preg_replace('/```\s*/', '', $text);
        $text = trim($text);
        
        // Try to find JSON object
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        
        if ($start !== false && $end !== false && $end > $start) {
            return substr($text, $start, $end - $start + 1);
        }
        
        return $text;
    }
}