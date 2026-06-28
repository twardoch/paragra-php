---
this_file: paragra-php/src-docs/architecture/fallback-algorithm.md
---

# Fallback Algorithm

`FallbackStrategy` is the heart of ParaGra's resilience model. It ensures that a transient error from one provider never surfaces to the caller as long as another tier has capacity left.

## Algorithm overview

```
for each priority tier (index 0 = highest priority):
    family  = detect_family(tier[0].solution.metadata)
    limit   = resolve_max_attempts(family, len(tier))
    spec    = KeyRotator.select(tier)          // timestamp-based round-robin
    attempt = 0

    while attempt < limit:
        try:
            return operation(spec)
        catch Throwable as e:
            attempt++
            log_failure(tier_index, family, spec, e, attempt, limit)
            if attempt >= limit: break
            spec = tier[(current_index + 1) % len(tier)]

    log_pool_exhausted(tier_index, family)

throw RuntimeException("All priority pools exhausted", cause=last_exception)
```

## Step-by-step

### 1. Tier iteration

Tiers are zero-indexed arrays in `priority_pools`. Index 0 is always tried first. The fallback moves to tier 1 only when tier 0 is exhausted, and so on.

### 2. Family detection

The family is read from `solution.metadata` of the **first** spec in the tier, using the first non-null value of:

1. `metadata['plan']`
2. `metadata['tier']`
3. `metadata['latency_tier']`

If none are set the family defaults to `hybrid`.

Token normalisation:

| Raw value | Canonical family |
|---|---|
| `free`, `free-tier`, `freemium`, `starter` | `free` |
| `hosted`, `managed` | `hosted` |
| anything else | `hybrid` |

### 3. Attempt cap

| Family | Default max_attempts | Meaning |
|---|---|---|
| `free` | `null` (= pool size) | Try every key; free tiers have zero marginal cost |
| `hybrid` | 2 | Balance cost against resilience |
| `hosted` | 1 | The provider's SLA is trusted; fail fast to the next tier |
| `default` | `null` (= pool size) | Same as `free` |

You can override these per instance:

```php
$strategy = new FallbackStrategy($pools, $rotator, familyPolicies: [
    'hybrid' => ['max_attempts' => 3],
    'hosted' => ['max_attempts' => 2],
]);
```

### 4. Key rotation (within a tier)

`KeyRotator::selectSpec()` picks the starting index using:

```
index = unix_timestamp() % pool_size
```

This naturally distributes load across keys without shared state. On failure, the strategy walks forward one position at a time:

```
next_index = (current_index + 1) % pool_size
```

### 5. Logging

Every failed attempt logs:

```
[ParaGra] Pool {tier} ({family}) attempt {n}/{max} failed for
          provider={slug} model={model} key#{sha1_prefix}: {message}
```

The key fingerprint is the first 8 characters of the SHA-1 of the raw API key — enough to correlate logs without leaking credentials.

When a tier is exhausted:

```
[ParaGra] Pool {tier} ({family}) exhausted after {max} attempt(s); keys={fingerprints}
```

The default logger is `error_log()`; pass a custom `callable(string): void` to the constructor to redirect to your logging framework.

## Sequence diagram

```
Client
  │
  ▼
FallbackStrategy.execute(operation)
  │
  ├─► Tier 0 (free)  ──► KeyRotator.select ──► spec-A
  │         │                                      │
  │         │                              operation(spec-A) ──► FAIL
  │         │                                      │
  │         │                              operation(spec-B) ──► FAIL
  │         │                                      │
  │         │                              operation(spec-C) ──► FAIL
  │         ▼ exhausted
  │
  ├─► Tier 1 (hybrid) ──► KeyRotator.select ──► spec-D
  │         │                                       │
  │         │                               operation(spec-D) ──► SUCCESS
  │         ▼ returns result
  ▼
caller receives result
```

## Fallback vs. retry

ParaGra does **not** implement automatic retry with back-off for the same spec. Each attempt within a tier uses a **different spec** (different API key). Back-off retry for a single key should be handled at the HTTP adapter layer (e.g., via Guzzle retry middleware) if needed.
