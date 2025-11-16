<?php

declare(strict_types=1);

// this_file: paragra-php/src/Media/ChutesImageProvider.php

namespace ParaGra\Media;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Psr\Http\Message\ResponseInterface;

use function array_flip;
use function array_intersect_key;
use function array_key_exists;
use function array_merge;
use function array_replace;
use function base64_encode;
use function json_decode;
use function max;
use function min;
use function microtime;
use function rtrim;
use function round;
use function str_contains;
use function is_array;
use function is_string;
use function trim;
use function usleep;

use const JSON_THROW_ON_ERROR;

/**
 * Executes Chutes image jobs by POSTing JSON payloads to a chute endpoint.
 *
 * The official docs (`chutes.ai/docs/examples/image-generation`) describe a
 * `/generate` endpoint that returns either JSON metadata or the binary image.
 * This adapter normalizes both shapes into a MediaResult so ParaGra callers
 * can immediately embed URLs/base64 responses inside their experiences.
 */
final class ChutesImageProvider implements ImageOperationInterface
{
    /**
     * @param array{
     *     endpoint?: string,
     *     default_width?: int,
     *     default_height?: int,
     *     num_inference_steps?: int,
     *     guidance_scale?: float,
     *     max_images?: int,
     *     max_retries?: int,
     *     retry_delay_ms?: int,
     *     provider_label?: string
     * } $defaults
     */
    public function __construct(
        private readonly ClientInterface $http,
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly array $defaults = [],
    ) {
    }

    #[\Override]
    public function generate(MediaRequest $request, array $options = []): MediaResult
    {
        $settings = $this->resolveSettings($options);
        $dimensions = $request->resolveDimensions($settings['default_width'], $settings['default_height']);
        $payloadOverrides = $options['payload'] ?? [];
        if ($payloadOverrides !== [] && !is_array($payloadOverrides)) {
            throw new MediaException('Chutes payload overrides must be an array.');
        }

        $payload = $this->buildPayload($request, $settings, $dimensions, $payloadOverrides);
        $url = $this->buildUrl($settings['endpoint']);
        $attempt = 0;
        $start = microtime(true);

        do {
            try {
                $response = $this->http->request('POST', $url, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->apiKey,
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                    'json' => $payload,
                    'timeout' => $options['timeout'] ?? 60,
                ]);

                if ($response->getStatusCode() >= 500 && $attempt < $settings['max_retries']) {
                    $this->delay($settings['retry_delay_ms']);
                    ++$attempt;
                    continue;
                }

                return $this->normalizeResponse(
                    $response,
                    $request,
                    $dimensions,
                    $settings,
                    $attempt,
                    $start,
                );
            } catch (GuzzleException $exception) {
                if ($attempt >= $settings['max_retries']) {
                    throw new MediaException('Chutes image request failed: ' . $exception->getMessage(), 0, $exception);
                }

                $this->delay($settings['retry_delay_ms']);
            }

            ++$attempt;
        } while ($attempt <= $settings['max_retries']);

        throw new MediaException('Chutes image request exhausted retries.');
    }

    /**
     * @param array{width:int,height:int} $dimensions
     * @param array{endpoint:string,default_width:int,default_height:int,num_inference_steps:int,guidance_scale:float,max_images:int,max_retries:int,retry_delay_ms:int,provider_label:string} $settings
     */
    private function normalizeResponse(
        ResponseInterface $response,
        MediaRequest $request,
        array $dimensions,
        array $settings,
        int $attempt,
        float $startTime,
    ): MediaResult {
        $durationMs = (int) max(1, round((microtime(true) - $startTime) * 1000));
        $contentType = trim($response->getHeaderLine('Content-Type'));
        $metadata = [
            'endpoint' => $settings['endpoint'],
            'duration_ms' => $durationMs,
            'attempts' => $attempt + 1,
        ];

        if ($contentType !== '' && str_contains($contentType, 'json')) {
            $body = (string) $response->getBody();
            try {
                /** @var array<string, mixed> $payload */
                $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new MediaException('Chutes JSON response could not be decoded.', 0, $exception);
            }

            $artifacts = $this->extractJsonArtifacts($payload);
            $metadata = array_merge(
                $payload['metadata'] ?? [],
                $metadata,
                [
                    'job_id' => $payload['job_id'] ?? null,
                    'duration_ms' => $payload['duration_ms'] ?? $durationMs,
                ]
            );

            return new MediaResult(
                provider: $settings['provider_label'],
                model: $settings['model'] ?? 'image',
                artifacts: $artifacts,
                metadata: $metadata,
            );
        }

        $artifact = [
            'base64' => base64_encode((string) $response->getBody()),
            'mime_type' => $contentType !== '' ? $contentType : 'image/png',
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'metadata' => ['binary_response' => true],
        ];

        if ($request->getMetadata() !== []) {
            $artifact['metadata'] = array_merge($request->getMetadata(), $artifact['metadata']);
        }

        return new MediaResult(
            provider: $settings['provider_label'],
            model: $settings['model'] ?? 'image',
            artifacts: [$artifact],
            metadata: $metadata,
        );
    }

    /**
     * @return list<array{
     *     url?: string,
     *     base64?: string,
     *     mime_type?: string,
     *     width?: int,
     *     height?: int,
     *     bytes?: int|null,
     *     metadata?: array<string, string|int|float|bool|null>
     * }>
     */
    private function extractJsonArtifacts(array $payload): array
    {
        $images = $payload['images'] ?? $payload['result']['images'] ?? null;
        if (!is_array($images) || $images === []) {
            throw new MediaException('Chutes response missing image entries.');
        }

        $artifacts = [];
        foreach ($images as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $artifact = [];
            if (array_key_exists('url', $entry) && is_string($entry['url'])) {
                $artifact['url'] = $entry['url'];
            }

            if (array_key_exists('b64_json', $entry) && is_string($entry['b64_json'])) {
                $artifact['base64'] = $entry['b64_json'];
            }

            if (array_key_exists('mime_type', $entry) && is_string($entry['mime_type'])) {
                $artifact['mime_type'] = $entry['mime_type'];
            }

            if (array_key_exists('width', $entry)) {
                $artifact['width'] = (int) $entry['width'];
            }

            if (array_key_exists('height', $entry)) {
                $artifact['height'] = (int) $entry['height'];
            }

            if (array_key_exists('bytes', $entry)) {
                $artifact['bytes'] = (int) $entry['bytes'];
            }

            if (array_key_exists('metadata', $entry) && is_array($entry['metadata'])) {
                $artifact['metadata'] = $entry['metadata'];
            }

            if ($artifact === []) {
                continue;
            }

            $artifacts[] = $artifact;
        }

        if ($artifacts === []) {
            throw new MediaException('Chutes response returned no valid image payloads.');
        }

        return $artifacts;
    }

    /**
     * @param array{width:int,height:int} $dimensions
     * @param array<string, mixed> $payloadOverrides
     *
     * @return array<string, mixed>
     */
    private function buildPayload(
        MediaRequest $request,
        array $settings,
        array $dimensions,
        array $payloadOverrides
    ): array {
        $payload = [
            'prompt' => $request->getPrompt(),
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'num_inference_steps' => $settings['num_inference_steps'],
            'guidance_scale' => $settings['guidance_scale'],
            'images' => max(1, min($settings['max_images'], $request->getImageCount())),
        ];

        if ($request->getNegativePrompt() !== null && $request->getNegativePrompt() !== '') {
            $payload['negative_prompt'] = $request->getNegativePrompt();
        }

        if ($request->getSeed() !== null) {
            $payload['seed'] = $request->getSeed();
        }

        if ($request->getMetadata() !== []) {
            $payload['metadata'] = $request->getMetadata();
        }

        if (isset($settings['model'])) {
            $payload['model'] = $settings['model'];
        }

        if ($payloadOverrides !== []) {
            $payload = array_replace($payload, $payloadOverrides);
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array{endpoint:string,default_width:int,default_height:int,num_inference_steps:int,guidance_scale:float,max_images:int,max_retries:int,retry_delay_ms:int,provider_label:string,model?:string}
     */
    private function resolveSettings(array $options): array
    {
        $defaults = array_merge([
            'endpoint' => '/generate',
            'default_width' => 1024,
            'default_height' => 1024,
            'num_inference_steps' => 28,
            'guidance_scale' => 7.5,
            'max_images' => 4,
            'max_retries' => 2,
            'retry_delay_ms' => 200,
            'provider_label' => 'chutes',
        ], $this->defaults);

        $overrides = array_intersect_key(
            $options,
            array_flip([
                'endpoint',
                'default_width',
                'default_height',
                'num_inference_steps',
                'guidance_scale',
                'max_images',
                'max_retries',
                'retry_delay_ms',
                'provider_label',
                'model',
            ])
        );

        /** @var array{endpoint:string,default_width:int,default_height:int,num_inference_steps:int,guidance_scale:float,max_images:int,max_retries:int,retry_delay_ms:int,provider_label:string,model?:string} $settings */
        $settings = array_merge($defaults, $overrides);

        return $settings;
    }

    private function buildUrl(string $endpoint): string
    {
        $base = rtrim($this->baseUrl, '/');
        $path = '/' . ltrim($endpoint, '/');

        return $base . $path;
    }

    private function delay(int $milliseconds): void
    {
        if ($milliseconds <= 0) {
            return;
        }

        usleep($milliseconds * 1000);
    }
}
