<?php

namespace App\Exceptions;

use RuntimeException;

final class UnsafeProductionMailConfiguration extends RuntimeException
{
    public static function atPath(array $path, string $reason): self
    {
        return new self(sprintf(
            'Unsafe production mail configuration at [%s]: %s Every reachable leaf transport must be explicitly approved in mail.production_allowed_transports.',
            implode(' -> ', $path),
            $reason,
        ));
    }
}
