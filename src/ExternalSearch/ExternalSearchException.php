<?php

declare(strict_types=1);

// this_file: paragra-php/src/ExternalSearch/ExternalSearchException.php

namespace ParaGra\ExternalSearch;

use RuntimeException;

/**
 * Raised when an external search adapter (e.g. twat-search) fails to run
 * or returns malformed output.
 */
final class ExternalSearchException extends RuntimeException
{
}
