<?php

namespace App\Services;

class BookingCodeGenerator
{
    public function generate(): string
    {
        return 'MMT-'.now()->format('Y').'-'.strtoupper(bin2hex(random_bytes(8)));
    }
}
