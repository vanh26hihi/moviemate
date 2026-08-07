<?php

namespace App\Services\Tickets;

use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

final class TicketQrCode
{
    public function png(string $payload, int $size = 320): string
    {
        if ($payload === '') {
            throw new \InvalidArgumentException('QR payload must not be empty.');
        }

        return (new Writer(new GDLibRenderer(max(160, min(800, $size)), 4, 'png')))
            ->writeString($payload);
    }

    public function svg(string $payload, int $size = 240): string
    {
        if ($payload === '') {
            throw new \InvalidArgumentException('QR payload must not be empty.');
        }

        return (new Writer(new ImageRenderer(
            new RendererStyle(max(120, min(600, $size)), 2),
            new SvgImageBackEnd,
        )))->writeString($payload);
    }
}
