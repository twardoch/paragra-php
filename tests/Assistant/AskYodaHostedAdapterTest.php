<?php

declare(strict_types=1);

// this_file: paragra-php/tests/Assistant/AskYodaHostedAdapterTest.php

namespace ParaGra\Tests\Assistant;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use ParaGra\Assistant\AskYodaHostedAdapter;
use ParaGra\Assistant\AskYodaHostedResult;
use ParaGra\Llm\AskYodaClient;
use PHPUnit\Framework\TestCase;

final class AskYodaHostedAdapterTest extends TestCase
{
    public function test_ask_returns_result_and_emits_success_telemetry(): void
    {
        $handler = new MockHandler([
            new Response(200, [], json_encode([
                'result' => 'AskYoda answer',
                'chunks_ids' => ['alpha', 'beta'],
                'llm_provider' => 'google',
                'llm_model' => 'gemini-2.0-flash-exp',
                'cost' => 0.01,
            ], JSON_THROW_ON_ERROR)),
        ]);
        $client = $this->askYodaClient($handler);
        $adapter = new AskYodaHostedAdapter($client);

        $events = [];
        $result = $adapter->ask('Explain ParaGra', telemetry: function (string $event, array $context) use (&$events): void {
            $events[] = [$event, $context];
        });

        self::assertInstanceOf(AskYodaHostedResult::class, $result);
        self::assertSame('AskYoda answer', $result->getResponse()->getResult());
        self::assertGreaterThanOrEqual(0, $result->getDurationMs());
        self::assertSame(2, $result->getChunkCount());

        self::assertNotEmpty($events);
        [$event, $context] = $events[0];
        self::assertSame('askyoda.success', $event);
        self::assertSame(2, $context['chunk_count'] ?? null);
        self::assertArrayHasKey('duration_ms', $context);
    }

    public function test_ask_emits_failure_event_before_throwing(): void
    {
        $handler = new MockHandler([
            new Response(500, [], 'internal error'),
        ]);
        $client = $this->askYodaClient($handler);
        $adapter = new AskYodaHostedAdapter($client);

        $events = [];

        $this->expectException(\RuntimeException::class);
        try {
            $adapter->ask('Fallback please', telemetry: function (string $event, array $context) use (&$events): void {
                $events[] = [$event, $context];
            });
        } finally {
            self::assertNotEmpty($events);
            self::assertSame('askyoda.failure', $events[0][0] ?? null);
            self::assertArrayHasKey('error', $events[0][1]);
        }
    }

    private function askYodaClient(MockHandler $handler): AskYodaClient
    {
        $http = new HttpClient([
            'handler' => HandlerStack::create($handler),
        ]);

        return new AskYodaClient(
            apiKey: 'eden-key',
            projectId: 'eden-project',
            httpClient: $http
        );
    }
}
