<?php

namespace App\Support\Mapping;

use RuntimeException;

/**
 * Raised when a single record cannot be mapped (bad date, missing lookup
 * entry, ...). It fails that one record, never the whole run.
 */
class MappingException extends RuntimeException
{
}
