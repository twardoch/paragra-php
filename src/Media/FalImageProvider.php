<?php

declare(strict_types=1);

// this_file: paragra-php/src/Media/FalImageProvider.php

namespace ParaGra\Media;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Psr\Http\Message\ResponseInterface;

use function array_flip;
use function array_intersect_key;
use function array_key_exists;
use function array_merge;
use function base64_encode;
use function is_array;
use function is_string;
use function json_decode;
use function ltrim;
use function max;
use function microtime;
use function min;
use function round;
use function rtrim;
use function sprintf;
use function str_contains;
use function trim;
use function usleep;

use const JSON_THROW_ON_ERROR;

/**
 * Fal.ai async image generator.
 *
 * Submits a job (POST https://api.fal.ai/v1/{modelId}) then polls
 * https://api.fal.ai/v1/jobs/{requestId} until completion. Based on
 * `docs.fal.ai/model-apis/guides/generate-images-from-text`.
 */
final class FalImageProvider implements ImageOperationInterface
{
    /**
     * @param array{
     *     base_url?: string,
     *     guidance_scale?: float,
     *     poll_interval_ms?: int,
     *     max_poll_attempts?: int,
     *     num_images?: int,
     *     provider_label?: string
     * } $defaults
     */
    public function __construct(
        private readonly ClientInterface $http,
        private readonly string $apiKey,
        private readonly string $modelId,
        private readonly array $defaults = [],
    ) {
    }

    #[\Override]
    public function generate(MediaRequest $request, array $options = []): MediaResult
    {
        $settings = $this->resolveSettings($options);
        $dimensions = $request->resolveDimensions();
        $payloadOverrides = $options['payload'] ?? [];
        if ($payloadOverrides !== [] && !is_array($payloadOverrides)) {
            throw new MediaException('Fal payload overrides must be an array.');
        }

        $payload = $this->buildPayload($request, $settings, $dimensions, $payloadOverrides);

        $jobId = $this->submitJob($payload, $settings);
        $resultPayload = $this->pollResult($jobId, $settings);

        $artifacts = $this->flattenFalArtifacts($resultPayload);
        $metadata = array_merge(
            [
                'request_id' => $jobId,
                'provider' => 'fal.ai',
                'latency_ms' => $resultPayload['metrics']['queue_time'] ?? null,
            ],
            $resultPayload['metadata'] ?? []
        );

        return new MediaResult(
            provider: $settings['provider_label'],
            model: $this->modelId,
            artifacts: $artifacts,
            metadata: $metadata,
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @param array{base_url:string,poll_interval_ms:int,max_poll_attempts:int,provider_label:string} $settings
     */
    private function submitJob(array $payload, array $settings): string
    {
        try {
            $response = $this->http->request('POST', $this->endpoint($settings['base_url']), [
                'headers' => [
                    'Authorization' => 'Key ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);
        } catch (GuzzleException $exception) {
            throw new MediaException('Fal image submission failed: ' . $exception->getMessage(), 0, $exception);
        }

        $data = $this->decodeJson($response);
        $requestId = $data['request_id'] ?? $data['id'] ?? null;
        if (!is_string($requestId) || trim($requestId) === '') {
            throw new MediaException('Fal response missing request_id.');
        }

        return $requestId;
    }

    /**
     * @param array{base_url:string,poll_interval_ms:int,max_poll_attempts:int,provider_label:string} $settings
     *
     * @return array<string, mixed>
     */
    private function pollResult(string $requestId, array $settings): array
    {
        $attempts = 0;
        $start = microtime(true);
        do {
            $response = $this->getJobStatus($requestId, $settings['base_url']);
            $payload = $this->decodeJson($response);

            $status = (string) ($payload['status'] ?? 'unknown');
            $done = ($payload['done'] ?? false) === true || str_contains(strtolower($status), 'complete');

            if ($done) {
                $payload['metadata'] = array_merge(
                    $payload['metadata'] ?? [],
                    ['duration_ms' => (int) max(1, round((microtime(true) - $start) * 1000))]
                );

                return $payload;
            }

            if (str_contains(strtolower($status), 'fail')) {
                $message = is_string($payload['error'] ?? null) ? $payload['error'] : 'Fal job failed';
                throw new MediaException($message);
            }

            $this->delay($settings['poll_interval_ms']);
            ++$attempts;
        } while ($attempts < $settings['max_poll_attempts']);

        throw new MediaException('Fal image job did not complete before timeout.');
    }

    private function getJobStatus(string $requestId, string $baseUrl): ResponseInterface
    {
        $url = rtrim($baseUrl, '/') . '/jobs/' . $requestId;
        try {
            return $this->http->request('GET', $url, [
                'headers' => [
                    'Authorization' => 'Key ' . $this->apiKey,
                    'Accept' => 'application/json',
                ],
            ]);
        } catch (GuzzleException $exception) {
            throw new MediaException('Fal job polling failed: ' . $exception->getMessage(), 0, $exception);
        }
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
    private function flattenFalArtifacts(array $payload): array
    {
        $images = $payload['images'] ?? $payload['output']['images'] ?? null;
        if (!is_array($images) || $images === []) {
            throw new MediaException('Fal job response missing images.');
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

            if (array_key_exists('content_type', $entry) && is_string($entry['content_type'])) {
                $artifact['mime_type'] = $entry['content_type'];
            }

            if (array_key_exists('width', $entry)) {
                $artifact['width'] = (int) $entry['width'];
            }

            if (array_key_exists('height', $entry)) {
                $artifact['height'] = (int) $entry['height'];
            }

            if (array_key_exists('file_size', $entry)) {
                $artifact['bytes'] = (int) $entry['file_size'];
            }

            if (array_key_exists('b64_json', $entry) && is_string($entry['b64_json'])) {
                $artifact['base64'] = $entry['b64_json'];
            }

            if ($artifact === [] && array_key_exists('data', $entry) && is_string($entry['data'])) {
                $artifact['base64'] = base64_encode($entry['data']);
            }

            if ($artifact !== []) {
                $artifacts[] = $artifact;
            }
        }

        if ($artifacts === []) {
            throw new MediaException('Fal job returned no usable artifacts.');
        }

        return $artifacts;
    }

    /**
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
        $size = sprintf('%dx%d', $dimensions['width'], $dimensions['height']);
        $payload = [
            'prompt' => $request->getPrompt(),
            'negative_prompt' => $request->getNegativePrompt(),
            'num_images' => max(1, min($settings['num_images'], $request->getImageCount())),
            'image_size' => $size,
            'guidance_scale' => $settings['guidance_scale'],
        ];

        if ($request->getSeed() !== null) {
            $payload['seed'] = $request->getSeed();
        }

        if ($payloadOverrides !== []) {
            $payload = array_merge($payload, $payloadOverrides);
        }

        return $payload;
    }

    /**
     * @param ResponseInterface $response
     *
     * @return array<string, mixed>
     */
    private function decodeJson(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();
        try {
            /** @var array<string, mixed> $payload */
            $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

            return $payload;
        } catch (JsonException $exception) {
            throw new MediaException('Fal response could not be decoded.', 0, $exception);
        }
    }

    private function endpoint(string $baseUrl): string
    {
        return rtrim($baseUrl, '/') . '/' . ltrim($this->modelId, '/');
    }

    private function delay(int $milliseconds): void
    {
        if ($milliseconds <= 0) {
            return;
        }

        usleep($milliseconds * 1000);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array{
     *     base_url: string,
     *     guidance_scale: float,
     *     poll_interval_ms: int,
     *     max_poll_attempts: int,
     *     num_images: int,
     *     provider_label: string
     * }
     */
    private function resolveSettings(array $options): array
    {
        $defaults = array_merge([
            'base_url' => 'https://api.fal.ai/v1',
            'guidance_scale' => 7.5,
            'poll_interval_ms' => 750,
            'max_poll_attempts' => 40,
            'num_images' => 1,
            'provider_label' => 'fal.ai',
        ], $this->defaults);

        $overrides = array_intersect_key(
            $options,
            array_flip(['base_url', 'guidance_scale', 'poll_interval_ms', 'max_poll_attempts', 'num_images', 'provider_label'])
        );

        /** @var array{
         *     base_url: string,
         *     guidance_scale: float,
         *     poll_interval_ms: int,
         *     max_poll_attempts: int,
         *     num_images: int,
         *     provider_label: string
         * } $settings
         */
        $settings = array_merge($defaults, $overrides);

        return $settings;
    }
}
