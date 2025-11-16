<?php

declare(strict_types=1);

// this_file: paragra-php/src/Assistant/AskYodaHostedResult.php

namespace ParaGra\Assistant;

use ParaGra\Llm\AskYodaResponse;

/**
 * Wraps the AskYoda API response together with duration metadata so
 * RagAnswerer and other callers can emit telemetry consistently.
 */
final class AskYodaHostedResult
{
    public function __construct(
        private readonly AskYodaResponse $response,
        private readonly int $durationMs,
    ) {
    }

    public function getResponse(): AskYodaResponse
    {
        return $this->response;
    }

    public function getDurationMs(): int
    {
        return $this->durationMs;
    }

    public function getChunkCount(): int
    {
        return $this->response->getChunkCount();
    }
}
