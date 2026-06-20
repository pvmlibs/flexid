<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Exceptions;

/**
 * This should be caught in secret key rotation logic to verify with other keys during rotation period.
 */
final class IdDecryptException extends \RuntimeException
{
}
