<?php

declare(strict_types=1);

// this_file: paragra-php/src/Providers/ProviderInterface.php

namespace ParaGra\Providers;

use ParaGra\Response\UnifiedResponse;

/**
 * Contract that every retrieval-capable provider must implement so
 * ParaGra can treat Ragie, Gemini File Search, AskYoda, or anything else
 * through the same entry points.
 */
interface ProviderInterface
{
    /**
     * Machine-friendly provider slug such as "ragie" or "gemini-file-search".
     */
    public function getProvider(): string;

    /**
     * Model identifier reported by the upstream provider (e.g. gpt-4o-mini).
     */
    public function getModel(): string;

    /**
     * Capabilities advertised by this provider (retrieval, rerank, llm_generation, etc.).
     *
     * @return list<string>
     */
    public function getCapabilities(): array;

    /**
     * Whether a given capability is supported (case-insensitive lookup).
     */
    public function supports(string $capability): bool;

    /**
     * Execute a retrieval request and return the normalized response bundle.
     *
     * @param array<string, mixed> $options Provider-specific tuning parameters
     */
    public function retrieve(string $query, array $options = []): UnifiedResponse;
}
