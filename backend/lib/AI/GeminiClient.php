<?php

namespace RecruiterLib\AI;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Handles communication with the Google AI (Gemini) API.
 */
class GeminiClient {
    private const API_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models/';
    private const DEFAULT_MODEL = 'gemini-1.5-flash-latest'; // Or configure as needed
    private const DEFAULT_TIMEOUT = 45.0; // Increased default timeout

    private ?string $apiKey;
    private Client $httpClient;

    public function __construct(?string $apiKey = null, ?Client $httpClient = null) {
        $this->apiKey = $apiKey ?: $this->getApiKeyFromEnv();
        if (!$this->apiKey) {
            throw new \InvalidArgumentException('Google AI API Key is required but not found in environment variables (GOOGLE_AI_API_KEY).');
        }
        $this->httpClient = $httpClient ?: new Client(['timeout' => self::DEFAULT_TIMEOUT]);
    }

    /**
     * Fetches the API key from environment variables.
     */
    private function getApiKeyFromEnv(): ?string {
        $key = getenv('GOOGLE_AI_API_KEY');
        if (!$key) {
            error_log("ERROR: GOOGLE_AI_API_KEY environment variable not set.");
            return null;
        }
        return $key;
    }

    /**
     * Generates content using the specified model and prompt.
     *
     * @param string $prompt The prompt text.
     * @param string $responseMimeType The expected response MIME type (e.g., 'application/json').
     * @param float $temperature Generation temperature.
     * @param string|null $model The model to use (defaults to DEFAULT_MODEL).
     * @return array The decoded JSON response from the API.
     * @throws AICommsException On communication or API errors.
     * @throws GuzzleException
     */
    public function generateContent(string $prompt, string $responseMimeType = 'application/json', float $temperature = 0.2, ?string $model = null): array {
        $model = $model ?: self::DEFAULT_MODEL;
        $apiUrl = self::API_BASE_URL . $model . ':generateContent?key=' . $this->apiKey;

        $requestBody = json_encode([
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'responseMimeType' => $responseMimeType,
                'temperature' => $temperature
            ]
            // Optional: Configure safety settings if needed
            // 'safetySettings' => [...],
        ]);

        try {
            error_log("[GeminiClient] Sending request to: $model (Size: " . strlen($requestBody) . " bytes)");
            $response = $this->httpClient->request('POST', $apiUrl, [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => $requestBody
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            // Avoid logging full body in production if it contains sensitive data
            // error_log("[GeminiClient] Received response body snippet: " . substr($responseBody, 0, 200));
            error_log("[GeminiClient] Received status code: " . $statusCode);


            if ($statusCode !== 200) {
                error_log("[GeminiClient] API request failed with status: " . $statusCode . " Body: " . $responseBody);
                throw new AICommsException("Google AI API request failed with status: {$statusCode}", $statusCode);
            }

            $responseData = json_decode($responseBody, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("[GeminiClient] Failed to decode API response JSON. Error: " . json_last_error_msg() . " Body: " . $responseBody);
                throw new AICommsException("Failed to decode API response JSON: " . json_last_error_msg());
            }

            return $responseData;

        } catch (RequestException $e) {
            $errorMessage = "[GeminiClient] Guzzle Request Exception: " . $e->getMessage();
            error_log($errorMessage);
            if ($e->hasResponse()) {
                $errorResponseBody = $e->getResponse()->getBody()->getContents();
                error_log("[GeminiClient] Guzzle Response Body on Error: " . $errorResponseBody);
                // Attempt to parse error details from Google's response
                $googleError = json_decode($errorResponseBody, true);
                if (isset($googleError['error']['message'])) {
                   $errorMessage .= " | Google Error: " . $googleError['error']['message'];
                }
            }
            throw new AICommsException("Error communicating with Google AI API: " . $errorMessage, $e->getCode(), $e);
        } catch (\Exception $e) { // Catch other potential errors
            error_log("[GeminiClient] General Exception: " . $e->getMessage());
            throw new AICommsException("An unexpected error occurred during AI communication: " . $e->getMessage(), $e->getCode(), $e);
        }
    }
} 