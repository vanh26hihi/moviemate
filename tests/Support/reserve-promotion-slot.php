<?php

use App\Models\Booking;
use App\Services\PromotionService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    $booking = Booking::query()->findOrFail((int) ($argv[1] ?? 0));
    $code = (string) ($argv[2] ?? '');
    DB::transaction(fn () => app(PromotionService::class)->reserveForBooking($booking, $code, 100_000));
    fwrite(STDOUT, json_encode(['result' => 'reserved'], JSON_THROW_ON_ERROR));
    exit(0);
} catch (ValidationException $exception) {
    fwrite(STDOUT, json_encode(['result' => 'rejected', 'errors' => $exception->errors()], JSON_THROW_ON_ERROR));
    exit(2);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage());
    exit(1);
}
