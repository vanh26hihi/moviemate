<?php

namespace App\Exceptions;

use RuntimeException;

final class PriceBookException extends RuntimeException
{
    public const BOOK_NOT_FOUND = 'PRICE_BOOK_NOT_FOUND';

    public const VERSION_NOT_FOUND = 'PRICE_BOOK_VERSION_NOT_FOUND';

    public const VERSION_OVERLAP = 'PRICE_BOOK_VERSION_OVERLAP';

    public const INVALID_ADJUSTMENT = 'PRICE_BOOK_INVALID_ADJUSTMENT';

    public const AMBIGUOUS_ADJUSTMENT = 'PRICE_BOOK_AMBIGUOUS_ADJUSTMENT';

    public const RESULT_NOT_POSITIVE = 'PRICE_RESULT_NOT_POSITIVE';

    public const IMMUTABLE = 'PRICE_BOOK_IMMUTABLE';

    public const INVALID_TRANSITION = 'PRICE_BOOK_INVALID_TRANSITION';

    public function __construct(public readonly string $domainCode, string $message)
    {
        parent::__construct($message);
    }

    public static function immutable(): self
    {
        return new self(self::IMMUTABLE, 'Published and retired PriceBook financial definitions are immutable.');
    }
}
