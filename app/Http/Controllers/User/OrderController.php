<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class OrderController extends Controller
{
    public function retired(): Response
    {
        return response()->view('foods.retired', status: Response::HTTP_GONE);
    }
}
