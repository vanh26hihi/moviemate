<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class RetiredBookingStoreController extends Controller
{
    public function __invoke(): Response
    {
        return response('Luồng đặt vé cũ đã ngừng hoạt động.', 410);
    }
}
