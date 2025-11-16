<?php

declare(strict_types=1);

// this_file: paragra-php/src/Router/FallbackStrategy.php

namespace ParaGra\Router;

use ParaGra\Config\PriorityPool;
use ParaGra\Config\ProviderSpec;
use RuntimeException;
use Throwable;

use function array_key_exists;
use function array_search;
use function count;
use function error_log;
use function hash;
use function implode;
use function is_array;
use function is_string;
use function sprintf;
use function strtolower;
use function substr;
use function trim;

/**
 * Executes an operation across priority pools until one succeeds.
 */
final class FallbackStrategy
{
    private const DEFAULT_FAMILY_POLICIES = [
        'free' => ['max_attempts' => null],
        'hybrid' => ['max_attempts' => 2],
        'hosted' => ['max_attempts' => 1],
        'default' => ['max_attempts' => null],
    ];

    /**
     * @var array<string, array{max_attempts: int|null}>
     */
    private array $familyPolicies;

    /**
     * @var callable(string):void
     */
    private $logger;

    public function __construct(
        private readonly PriorityPool $pools,
        private readonly KeyRotator $rotator,
        array $familyPolicies = [],
        ?callable $logger = null,
    ) {
        $this->familyPolicies = $this->normalizePolicies($familyPolicies);
        $this->logger = $logger ?? static function (string $message): void {
            error_log($message);
        };
    }

    /**
     * @param callable(ProviderSpec): mixed $operation
     */
    public function execute(callable $operation): mixed
    {
        $poolCount = $this->pools->getPoolCount();
        $lastException = null;

        for ($priority = 0; $priority < $poolCount; $priority++) {
            $pool = $this->pools->getPool($priority);
            if ($pool === []) {
                continue;
            }

            $family = $this->detectFamily($pool[0]);
            $maxAttempts = $this->resolveMaxAttempts($pool, $family);
            if ($maxAttempts === 0) {
                continue;
            }

            $spec = $this->rotator->selectSpec($pool);
            $currentIndex = $this->findIndex($pool, $spec);
            $attempt = 0;
            $fingerprints = [];

            while ($attempt < $maxAttempts) {
                try {
                    return $operation($spec);
                } catch (Throwable $exception) {
                    $attempt++;
                    $lastException = $exception;
                    $fingerprint = $this->fingerprint($spec->apiKey);
                    $fingerprints[] = $fingerprint;
                    $this->logFailure(
                        $priority,
                        $family,
                        $spec,
                        $exception,
                        $attempt,
                        $maxAttempts,
                        $fingerprint
                    );

                    if ($attempt >= $maxAttempts) {
                        break;
                    }

                    $currentIndex = $this->nextIndex($currentIndex, count($pool));
                    $spec = $pool[$currentIndex];
                }
            }

            $this->logPoolExhausted($priority, $family, $fingerprints, $maxAttempts);
        }

        throw new RuntimeException('All priority pools exhausted', 0, $lastException);
    }

    /**
     * @param array<string, array<string, mixed>> $overrides
     * @return array<string, array{max_attempts: int|null}>
     */
    private function normalizePolicies(array $overrides): array
    {
        $policies = self::DEFAULT_FAMILY_POLICIES;

        foreach ($overrides as $family => $config) {
            if (!is_string($family) || !is_array($config)) {
                continue;
            }

            $key = $this->normalizePolicyKey($family);
            if (!array_key_exists($key, $policies)) {
                $policies[$key] = ['max_attempts' => null];
            }

            $maxAttempts = $config['max_attempts'] ?? $policies[$key]['max_attempts'];
            if ($maxAttempts === null) {
                $policies[$key]['max_attempts'] = null;
                continue;
            }

            $policies[$key]['max_attempts'] = max(1, (int) $maxAttempts);
        }

        return $policies;
    }

    /**
     * @param array<int, ProviderSpec> $pool
     */
    private function resolveMaxAttempts(array $pool, string $family): int
    {
        $size = count($pool);
        if ($size === 0) {
            return 0;
        }

        $policy = $this->familyPolicies[$family] ?? $this->familyPolicies['default'];
        $maxAttempts = $policy['max_attempts'];

        if ($maxAttempts === null) {
            return $size;
        }

        if ($maxAttempts >= $size) {
            return $size;
        }

        return max(1, $maxAttempts);
    }

    /**
     * @param array<int, ProviderSpec> $pool
     */
    private function findIndex(array $pool, ProviderSpec $spec): int
    {
        $index = array_search($spec, $pool, true);

        return $index === false ? 0 : (int) $index;
    }

    private function nextIndex(int $currentIndex, int $poolSize): int
    {
        if ($poolSize === 0) {
            return 0;
        }

        return ($currentIndex + 1) % $poolSize;
    }

    private function detectFamily(ProviderSpec $spec): string
    {
        $solution = $spec->solution;
        $metadata = [];

        if (isset($solution['metadata']) && is_array($solution['metadata'])) {
            $metadata = $solution['metadata'];
        }

        foreach (['plan', 'tier', 'latency_tier'] as $key) {
            if (isset($metadata[$key]) && is_string($metadata[$key])) {
                return $this->normalizeFamilyToken($metadata[$key]);
            }
        }

        return 'hybrid';
    }

    private function normalizePolicyKey(string $family): string
    {
        $token = strtolower(trim($family));
        if ($token === 'default') {
            return 'default';
        }

        return $this->normalizeFamilyToken($token);
    }

    private function normalizeFamilyToken(string $value): string
    {
        $normalized = strtolower(trim($value));

        return match ($normalized) {
            'free', 'free-tier', 'freemium', 'starter' => 'free',
            'hosted', 'managed' => 'hosted',
            default => 'hybrid',
        };
    }

    private function fingerprint(string $apiKey): string
    {
        return substr(hash('sha1', $apiKey), 0, 8);
    }

    /**
     * @param array<int, string> $fingerprints
     */
    private function logPoolExhausted(int $priority, string $family, array $fingerprints, int $maxAttempts): void
    {
        if ($fingerprints === []) {
            return;
        }

        ($this->logger)(sprintf(
            '[ParaGra] Pool %d (%s) exhausted after %d attempt(s); keys=%s',
            $priority,
            $family,
            $maxAttempts,
            implode(',', $fingerprints)
        ));
    }

    private function logFailure(
        int $priority,
        string $family,
        ProviderSpec $spec,
        Throwable $exception,
        int $attempt,
        int $maxAttempts,
        string $fingerprint
    ): void {
        ($this->logger)(sprintf(
            '[ParaGra] Pool %d (%s) attempt %d/%d failed for provider=%s model=%s key#%s: %s',
            $priority,
            $family,
            $attempt,
            $maxAttempts,
            $spec->provider,
            $spec->model,
            $fingerprint,
            $exception->getMessage()
        ));
    }
}
