<?php

declare(strict_types=1);

// this_file: paragra-php/tests/Providers/RagieProviderTest.php

namespace ParaGra\Tests\Providers;

use ParaGra\Config\ProviderSpec;
use ParaGra\Providers\RagieProvider;
use ParaGra\Response\UnifiedResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Ragie\Api\Model\Retrieval;
use Ragie\Api\Model\RetrieveParams;
use Ragie\Api\Model\ScoredChunk;
use Ragie\Client as RagieClient;
use Ragie\RetrievalOptions;

#[CoversClass(RagieProvider::class)]
final class RagieProviderTest extends TestCase
{
    public function test_retrieve_whenValidInput_thenReturnsUnifiedResponse(): void
    {
        $spec = $this->spec();
        $client = $this->createMock(RagieClient::class);
        $chunks = [
            $this->chunk('Doc 1 text', 0.9, 'doc-1', 'Doc 1'),
            $this->chunk('Doc 2 text', 0.7, 'doc-2', 'Doc 2'),
        ];

        $client->expects($this->once())
            ->method('retrieve')
            ->with(
                'What is ParaGra?',
                $this->callback(function (?RetrievalOptions $options): bool {
                    self::assertInstanceOf(RetrievalOptions::class, $options);
                    $params = $options->toRetrieveParams('What is ParaGra?');
                    self::assertInstanceOf(RetrieveParams::class, $params);
                    self::assertSame(5, $params->getTopK());
                    self::assertTrue($params->getRerank());
                    self::assertSame('docs', $params->getPartition());
                    return true;
                })
            )
            ->willReturn($this->retrieval($chunks));

        $provider = new RagieProvider(
            $spec,
            $client,
            [
                'default_options' => [
                    'top_k' => 5,
                    'rerank' => true,
                    'partition' => 'docs',
                ],
            ]
        );

        $response = $provider->retrieve('  What is ParaGra?  ');
        self::assertInstanceOf(UnifiedResponse::class, $response);
        self::assertSame(2, $response->count());
        self::assertSame(['Doc 1 text', 'Doc 2 text'], $response->getChunkTexts());
        self::assertSame('openai', $response->getProvider());
        self::assertSame('gpt-4o-mini', $response->getModel());
    }

    public function test_retrieve_whenQueryEmpty_thenThrows(): void
    {
        $spec = $this->spec();
        $client = $this->createMock(RagieClient::class);
        $provider = new RagieProvider($spec, $client);

        $this->expectException(\InvalidArgumentException::class);
        $provider->retrieve('   ');
    }

    private function spec(): ProviderSpec
    {
        return new ProviderSpec(
            provider: 'openai',
            model: 'gpt-4o-mini',
            apiKey: 'sk-test',
            solution: [
                'type' => 'ragie',
                'ragie_api_key' => 'ragie-key',
                'ragie_partition' => 'docs',
            ],
        );
    }

    private function chunk(string $text, float $score, string $documentId, string $documentName): ScoredChunk
    {
        $chunk = new ScoredChunk();
        $chunk->setText($text);
        $chunk->setScore($score);
        $chunk->setDocumentId($documentId);
        $chunk->setDocumentName($documentName);

        return $chunk;
    }

    /**
     * @param list<ScoredChunk> $chunks
     */
    private function retrieval(array $chunks): \Ragie\RetrievalResult
    {
        $retrieval = new Retrieval();
        $retrieval->setScoredChunks($chunks);

        return new \Ragie\RetrievalResult($retrieval);
    }
}
