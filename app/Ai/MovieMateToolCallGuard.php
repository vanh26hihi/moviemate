<?php

namespace App\Ai;

use App\Ai\Agents\MovieMateCinemaAssistant;
use DomainException;
use OverflowException;

final class MovieMateToolCallGuard
{
    private int $total = 0;

    /** @var array<string, int> */
    private array $identicalCalls = [];

    public function reset(): void
    {
        $this->total = 0;
        $this->identicalCalls = [];
    }

    public function record(string $name, array $arguments): void
    {
        if (! in_array($name, MovieMateCinemaAssistant::TOOL_ALLOWLIST, true)) {
            throw new DomainException('Unknown MovieMate AI tool.');
        }

        $this->total++;
        if ($this->total > max(1, (int) config('moviemate-ai.max_tool_calls', 6))) {
            throw new OverflowException('MovieMate AI tool-call limit reached.');
        }

        ksort($arguments);
        $fingerprint = hash('sha256', $name.'|'.json_encode($arguments, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->identicalCalls[$fingerprint] = ($this->identicalCalls[$fingerprint] ?? 0) + 1;
        if ($this->identicalCalls[$fingerprint] > max(1, (int) config('moviemate-ai.max_identical_tool_calls', 2))) {
            throw new OverflowException('MovieMate AI repeated-tool limit reached.');
        }
    }

    public function count(): int
    {
        return $this->total;
    }
}
