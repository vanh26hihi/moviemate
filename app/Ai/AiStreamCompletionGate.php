<?php

namespace App\Ai;

class AiStreamCompletionGate
{
    public function clientConnected(): bool
    {
        return connection_aborted() === 0;
    }
}
