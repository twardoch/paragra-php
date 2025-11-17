# Testing

ParaGra uses PHPUnit for tests and includes static analysis with PHPStan and Psalm.

## Quick start

Run all QA tools:
```bash
composer qa
```

This runs:
1. PHP-CS-Fixer (linting)
2. PHPStan (static analysis)
3. Psalm (static analysis)
4. PHPUnit (tests)

## Individual tools

### Unit tests
```bash
composer test
```

### Linting
```bash
composer lint
```

### Static analysis
```bash
composer stan   # PHPStan level 7
composer psalm  # Psalm error level 3
```

## Test structure

```
tests/
├── Unit/           # Unit tests
├── Integration/    # Integration tests (require API keys)
└── Fixtures/       # Test data and mocks
```

## Running specific tests

```bash
vendor/bin/phpunit tests/Unit/ParaGraTest.php
vendor/bin/phpunit --filter testRetrieve
```

## Coverage

```bash
vendor/bin/phpunit --coverage-html coverage/
open coverage/index.html
```

Target: ≥90% coverage for core classes.

## Environment for integration tests

```bash
export RAGIE_API_KEY=sk_live_...
export OPENAI_API_KEY=sk-...
# ... other API keys

composer test
```

Integration tests are skipped if required keys are missing.
