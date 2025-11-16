<?php

declare(strict_types=1);

// this_file: paragra-php/src/ExternalSearch/ExternalSearchRetrieverInterface.php

namespace ParaGra\ExternalSearch;

use ParaGra\Response\UnifiedResponse;

/**
 * Contract for adapters that augment ParaGra with external web searches
 * (e.g., Brave, DuckDuckGo, or SerpAPI) to fill gaps when first-party
 * retrieval providers return no context.
 */
interface ExternalSearchRetrieverInterface
{
    /**
     * Machine-friendly provider slug such as "twat-search".
     */
    public function getProvider(): string;

    /**
     * Identifier of the upstream command or API powering the retriever.
     */
    public function getModel(): string;

    /**
     * Execute a search and return normalized chunks ready for prompt builders.
     *
     * @param array<string, mixed> $options Implementation-specific tuning knobs.
     */
    public function search(string $query, array $options = []): UnifiedResponse;
}
