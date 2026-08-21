<?php

namespace App\Ai\Providers;

use App\Ai\Gateways\NineRouterGateway;
use Laravel\Ai\Contracts\Gateway\TextGateway;
use Laravel\Ai\Providers\OpenRouterProvider;

final class NineRouterProvider extends OpenRouterProvider
{
    public function textGateway(): TextGateway
    {
        return $this->textGateway ??= new NineRouterGateway($this->events);
    }
}
