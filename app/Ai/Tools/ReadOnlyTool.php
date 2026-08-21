<?php

namespace App\Ai\Tools;

use Illuminate\Validation\ValidationException;
use Laravel\Ai\Tools\Request;

abstract class ReadOnlyTool
{
    /** @return array<string, mixed> */
    protected function validate(Request $request, array $rules): array
    {
        $allowed = collect(array_keys($rules))
            ->reject(fn (string $key): bool => str_contains($key, '.*'))
            ->values()->all();
        $unknown = array_values(array_diff(array_keys($request->all()), $allowed));
        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'arguments' => 'Unsupported tool arguments: '.implode(', ', $unknown),
            ]);
        }

        return validator($request->all(), $rules)->validate();
    }

    protected function json(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
