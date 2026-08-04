<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class BookingCheckoutConflictException extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => 'Checkout token was already used for a different booking request.',
        ], 409);
    }
}
