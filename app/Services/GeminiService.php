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
        return "IMPORTANT:
If the JSON will be incomplete, do NOT start writing it.

        You are an expert at analyzing certificate designs and generating HTML templates.

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

Return ONLY a VALID JSON object.
Do NOT truncate.
If you cannot finish, STOP BEFORE STARTING.
 (IMPORTANT: Escape all special characters in strings, use \\\\n for newlines, \\\\\" for quotes):
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
    \"template_html\": \"<complete HTML string here with escaped quotes and newlines>\"
}

CRITICAL REQUIREMENTS:
- Return ONLY valid JSON, NO markdown code blocks, NO explanations
- Escape ALL special characters in strings: use \\\\n for newlines, \\\\\" for quotes, \\\\\\\\ for backslashes
- Extract exact colors, fonts, and sizes from the image
- Generate complete HTML with inline CSS matching the design exactly";
    }

    /**
     * Call Gemini API with image
     */
    private function callGeminiApi($base64Image, $mimeType, $prompt): array
    {
        // Build URL (API key will be sent in header)
        $url = $this->apiUrl;
        
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
                'maxOutputTokens' => 8192, // Increased to handle larger responses
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
            
            // Hard validation before parsing
if (!str_ends_with(trim($jsonText), '}')) {
    throw new \Exception('Gemini returned incomplete JSON');
}

if (substr_count($jsonText, '{') !== substr_count($jsonText, '}')) {
    throw new \Exception('Unbalanced JSON braces');
}

            if (empty($text)) {
                throw new \Exception('Empty response from Gemini API');
            }

            // Check if response was cut off
            $finishReason = $response['candidates'][0]['finishReason'] ?? '';
            if ($finishReason === 'MAX_TOKENS') {
                Log::warning('Gemini response was cut off due to MAX_TOKENS', [
                    'text_length' => strlen($text)
                ]);
            }

            Log::info('Parsing Gemini response', [
                'text_length' => strlen($text),
                'text_preview' => substr($text, 0, 200),
                'finish_reason' => $finishReason
            ]);

            // Try to extract JSON from response
            // Gemini might return JSON wrapped in markdown code blocks
            $jsonText = $this->extractJsonFromText($text);
            
            // Clean JSON text - remove control characters that might cause issues
            $jsonText = $this->cleanJsonText($jsonText);
            
            // Parse JSON with flags to handle invalid UTF-8
            $data = json_decode($jsonText, true, 512, JSON_INVALID_UTF8_IGNORE | JSON_INVALID_UTF8_SUBSTITUTE);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Try to fix common JSON issues
                $jsonText = $this->fixJsonIssues($jsonText);
                $data = json_decode($jsonText, true, 512, JSON_INVALID_UTF8_IGNORE | JSON_INVALID_UTF8_SUBSTITUTE);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    // Last attempt: try to extract and fix JSON more aggressively
                    $jsonText = $this->aggressiveJsonFix($jsonText);
                    $data = json_decode($jsonText, true, 512, JSON_INVALID_UTF8_IGNORE | JSON_INVALID_UTF8_SUBSTITUTE);
                    
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        Log::error('JSON Parse Error', [
                            'error' => json_last_error_msg(),
                            'json_text_preview' => substr($jsonText, 0, 1000),
                            'json_text_length' => strlen($jsonText),
                            'finish_reason' => $finishReason
                        ]);
                        throw new \Exception('Invalid JSON response from Gemini: ' . json_last_error_msg() . ($finishReason === 'MAX_TOKENS' ? ' (Response was cut off)' : ''));
                    }
                }
            }

            // Validate required fields
            if (!isset($data['template_config']) || !isset($data['template_html'])) {
                Log::error('Missing required fields', [
                    'has_template_config' => isset($data['template_config']),
                    'has_template_html' => isset($data['template_html']),
                    'data_keys' => array_keys($data ?? [])
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
                'response_preview' => isset($response['candidates'][0]['content']['parts'][0]['text']) ? substr($response['candidates'][0]['content']['parts'][0]['text'], 0, 500) : 'No text'
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
        $text = preg_replace('/```json\s*/i', '', $text);
        $text = preg_replace('/```\s*/', '', $text);
        $text = trim($text);
        
        // Try to find JSON object - handle nested braces
        // But we need to be careful with strings that contain braces
        $start = strpos($text, '{');
        if ($start === false) {
            return $text;
        }
        
        // Find matching closing brace by counting braces
        // Skip braces inside strings
        $braceCount = 0;
        $end = $start;
        $length = strlen($text);
        $inString = false;
        $escapeNext = false;
        
        for ($i = $start; $i < $length; $i++) {
            $char = $text[$i];
            
            if ($escapeNext) {
                $escapeNext = false;
                continue;
            }
            
            if ($char === '\\') {
                $escapeNext = true;
                continue;
            }
            
            if ($char === '"' && !$escapeNext) {
                $inString = !$inString;
                continue;
            }
            
            if (!$inString) {
                if ($char === '{') {
                    $braceCount++;
                } elseif ($char === '}') {
                    $braceCount--;
                    if ($braceCount === 0) {
                        $end = $i;
                        break;
                    }
                }
            }
        }
        
        if ($end > $start) {
            return substr($text, $start, $end - $start + 1);
        }
        
        return $text;
    }

    /**
     * Clean JSON text from control characters and fix encoding issues
     */
    private function cleanJsonText($jsonText): string
    {
        // First, ensure valid UTF-8 encoding
        if (!mb_check_encoding($jsonText, 'UTF-8')) {
            $jsonText = mb_convert_encoding($jsonText, 'UTF-8', 'UTF-8');
        }
        
        // Remove control characters that are not escaped sequences
        // This regex removes control chars but preserves \n, \t, \r, etc. when escaped
        $jsonText = preg_replace_callback(
            '/\\\\u([0-9a-fA-F]{4})/',
            function ($matches) {
                return '\\u' . $matches[1];
            },
            $jsonText
        );
        
        // Remove unescaped control characters (but keep escaped ones like \n)
        // We need to be careful not to break valid JSON escape sequences
        $jsonText = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $jsonText);
        
        return trim($jsonText);
    }

    /**
     * Fix common JSON issues
     */
    private function fixJsonIssues($jsonText): string
    {
        // Remove trailing commas before closing braces/brackets
        $jsonText = preg_replace('/,\s*([}\]])/', '$1', $jsonText);
        
        // Try to fix incomplete JSON if it was cut off
        // Check if JSON ends abruptly
        $braceCount = substr_count($jsonText, '{') - substr_count($jsonText, '}');
        $bracketCount = substr_count($jsonText, '[') - substr_count($jsonText, ']');
        
        // If JSON is incomplete, try to close it
        if ($braceCount > 0) {
            $jsonText .= str_repeat('}', $braceCount);
        }
        if ($bracketCount > 0) {
            $jsonText .= str_repeat(']', $bracketCount);
        }
        
        return $jsonText;
    }

    /**
     * Aggressive JSON fixing for problematic responses
     */
    private function aggressiveJsonFix($jsonText): string
    {
        // First, try to extract just the JSON part
        $start = strpos($jsonText, '{');
        if ($start !== false) {
            $jsonText = substr($jsonText, $start);
        }
        
        // Find the last complete closing brace
        $lastBrace = strrpos($jsonText, '}');
        if ($lastBrace !== false) {
            $jsonText = substr($jsonText, 0, $lastBrace + 1);
        }
        
        // Fix unescaped control characters in string values
        // We'll process the JSON character by character, escaping newlines in strings
        $result = '';
        $inString = false;
        $escapeNext = false;
        $length = strlen($jsonText);
        
        for ($i = 0; $i < $length; $i++) {
            $char = $jsonText[$i];
            
            if ($escapeNext) {
                $result .= $char;
                $escapeNext = false;
                continue;
            }
            
            if ($char === '\\') {
                $result .= $char;
                $escapeNext = true;
                continue;
            }
            
            if ($char === '"' && !$escapeNext) {
                $inString = !$inString;
                $result .= $char;
                continue;
            }
            
            if ($inString) {
                // Inside a string, escape control characters
                if ($char === "\n") {
                    $result .= '\\n';
                } elseif ($char === "\r") {
                    $result .= '\\r';
                } elseif ($char === "\t") {
                    $result .= '\\t';
                } elseif (ord($char) < 32 && $char !== "\n" && $char !== "\r" && $char !== "\t") {
                    // Skip other control characters
                    continue;
                } else {
                    $result .= $char;
                }
            } else {
                $result .= $char;
            }
        }
        
        // Remove trailing commas
        $result = preg_replace('/,\s*([}\]])/', '$1', $result);
        
        // Close incomplete JSON
        $braceCount = substr_count($result, '{') - substr_count($result, '}');
        if ($braceCount > 0) {
            $result .= str_repeat('}', $braceCount);
        }
        
        return $result;
    }
}