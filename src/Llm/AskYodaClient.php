<?php

// this_file: paragra-php/src/Llm/AskYodaClient.php

declare(strict_types=1);

namespace ParaGra\Llm;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;

/**
 * EdenAI AskYoda RAG Client
 *
 * Provides RAG functionality through EdenAI's AskYoda service.
 * Used as a fallback when Ragie API rate limits are exceeded.
 */
class AskYodaClient
{
    private const BASE_URL = 'https://api.edenai.run/v2/aiproducts/askyoda/v2';

    private HttpClient $httpClient;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $projectId,
        private readonly string $llmProvider = 'google',
        private readonly string $llmModel = 'gemini-2.0-flash-exp',
        ?HttpClient $httpClient = null
    ) {
        $this->httpClient = $httpClient ?? new HttpClient([
            'timeout' => 30,
            'http_errors' => true,
        ]);
    }

    /**
     * Ask a question using AskYoda RAG
     *
     * @param string $query The question to ask
     * @param int $k Number of chunks to retrieve (default: 10)
     * @param float $minScore Minimum relevance score (0.0-1.0, default: 0.3)
     * @param float $temperature LLM temperature (0.0-2.0, default: 0.99)
     * @param int $maxTokens Maximum tokens in response (default: 8000)
     * @throws \RuntimeException On API errors
     * @return AskYodaResponse
     */
    public function ask(
        string $query,
        int $k = 10,
        float $minScore = 0.3,
        float $temperature = 0.99,
        int $maxTokens = 8000
    ): AskYodaResponse {
        $url = sprintf('%s/%s/ask_llm/', self::BASE_URL, $this->projectId);

        $payload = [
            'k' => $k,
            'min_score' => $minScore,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'query' => $query,
            'llm_provider' => $this->llmProvider,
            'llm_model' => $this->llmModel,
        ];

        try {
            $response = $this->httpClient->request('POST', $url, [
                'json' => $payload,
                'headers' => [
                    'accept' => 'application/json',
                    'authorization' => 'Bearer ' . $this->apiKey,
                    'content-type' => 'application/json',
                ],
            ]);

            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Failed to decode AskYoda response: ' . json_last_error_msg());
            }

            return new AskYodaResponse($data);
        } catch (GuzzleException $e) {
            throw new \RuntimeException('AskYoda API request failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Create AskYoda client from environment variables
     *
     * Required environment variables:
     * - EDENAI_API_KEY
     * - EDENAI_ASKYODA_PROJECT
     *
     * Optional environment variables:
     * - EDENAI_LLM_PROVIDER (default: google)
     * - EDENAI_LLM_MODEL (default: gemini-2.0-flash-exp)
     */
    public static function fromEnv(): self
    {
        $apiKey = getenv('EDENAI_API_KEY');
        if ($apiKey === false || $apiKey === '') {
            throw new \RuntimeException('EDENAI_API_KEY environment variable is required');
        }

        $projectId = getenv('EDENAI_ASKYODA_PROJECT');
        if ($projectId === false || $projectId === '') {
            throw new \RuntimeException('EDENAI_ASKYODA_PROJECT environment variable is required');
        }

        $llmProvider = getenv('EDENAI_LLM_PROVIDER') ?: 'google';
        $llmModel = getenv('EDENAI_LLM_MODEL') ?: 'gemini-2.0-flash-exp';

        return new self($apiKey, $projectId, $llmProvider, $llmModel);
    }
}
