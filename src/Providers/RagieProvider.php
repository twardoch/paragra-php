<?php

declare(strict_types=1);

// this_file: paragra-php/src/Providers/RagieProvider.php

namespace ParaGra\Providers;

use ParaGra\Config\ProviderSpec;
use ParaGra\Response\UnifiedResponse;
use Ragie\Api\Model\ScoredChunk;
use Ragie\Client as RagieClient;
use Ragie\RetrievalOptions;
use Ragie\RetrievalResult;

use function array_filter;
use function array_map;
use function array_merge;
use function array_replace_recursive;
use function array_values;
use function count;
use function is_array;
use function trim;

/**
 * Adapter that turns Ragie's RetrievalResult payloads into UnifiedResponse objects.
 */
final class RagieProvider extends AbstractProvider
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        ProviderSpec $spec,
        private readonly RagieClient $client,
        private readonly array $config = [],
    ) {
        parent::__construct($spec, $config['capabilities'] ?? ['retrieval']);
    }

    #[\Override]
    public function retrieve(string $query, array $options = []): UnifiedResponse
    {
        $clean = $this->sanitizeQuery($query);
        $mergedOptions = $this->mergeOptions($options);
        $retrievalOptions = $this->buildRetrievalOptions($mergedOptions);

        $result = $this->client->retrieve($clean, $retrievalOptions);

        $metadata = array_merge(
            $this->baseMetadata(),
            [
                'solution_type' => $this->getSolution()['type'] ?? 'ragie',
                'chunk_count' => $result->count(),
            ],
            $this->config['metadata'] ?? []
        );

        return UnifiedResponse::fromChunks(
            provider: $this->getProvider(),
            model: $this->getModel(),
            chunks: $this->normalizeChunks($result),
            metadata: $metadata
        );
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function mergeOptions(array $options): array
    {
        $defaults = $this->config['default_options'] ?? [];

        return array_replace_recursive($defaults, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function buildRetrievalOptions(array $options): RetrievalOptions
    {
        $retrieval = RetrievalOptions::create();

        if (isset($options['top_k'])) {
            $retrieval = $retrieval->withTopK((int) $options['top_k']);
        }

        if (isset($options['filter']) && is_array($options['filter'])) {
            $retrieval = $retrieval->withFilter($options['filter']);
        }

        if (array_key_exists('rerank', $options)) {
            $retrieval = $retrieval->withRerank((bool) $options['rerank']);
        }

        if (isset($options['max_chunks_per_document'])) {
            $retrieval = $retrieval->withMaxChunksPerDocument((int) $options['max_chunks_per_document']);
        }

        $partition = $options['partition']
            ?? $this->getSolution()['ragie_partition']
            ?? $this->config['partition']
            ?? null;

        if ($partition !== null) {
            $retrieval = $retrieval->withPartition($partition);
        }

        if (array_key_exists('recency_bias', $options)) {
            $retrieval = $retrieval->withRecencyBias((bool) $options['recency_bias']);
        }

        return $retrieval;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeChunks(RetrievalResult $result): array
    {
        $chunks = $result->getChunks();

        return array_values(array_filter(array_map(
            function (ScoredChunk $chunk): ?array {
                $text = trim((string) ($chunk->getText() ?? ''));
                if ($text === '') {
                    return null;
                }

                $normalized = [
                    'text' => $text,
                ];

                $score = $chunk->getScore();
                if ($score !== null) {
                    $normalized['score'] = (float) $score;
                }

                $documentId = $chunk->getDocumentId();
                if ($documentId !== null && trim($documentId) !== '') {
                    $normalized['document_id'] = trim($documentId);
                }

                $documentName = $chunk->getDocumentName();
                if ($documentName !== null && trim($documentName) !== '') {
                    $normalized['document_name'] = trim($documentName);
                }

                if ($chunk->getMetadata() !== null && $chunk->getMetadata() !== []) {
                    $normalized['metadata'] = $chunk->getMetadata();
                }

                if ($chunk->getDocumentMetadata() !== null && $chunk->getDocumentMetadata() !== []) {
                    $normalized['document_metadata'] = $chunk->getDocumentMetadata();
                }

                return $normalized;
            },
            $chunks
        )));
    }
}
