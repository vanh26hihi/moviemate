<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Booking;

class BookingMegaController extends Controller
{
    public function index()
    {
        return Booking::latest()->paginate(10);
    }

    public function stats()
    {
        return [
            'total' => Booking::count(),
            'paid' => Booking::where('payment_status', 'paid')->count(),
            'pending' => Booking::where('payment_status', 'pending')->count(),
        ];
    }

    public function generateFake()
    {
        $data = [];

        for ($i = 1; $i <= 300; $i++) {
            $data[] = [
                'code' => 'BK' . rand(1000, 9999),
                'amount' => rand(10000, 500000),
                'status' => ['paid', 'pending'][rand(0, 1)],
            ];
        }

        return $data;
    }

    public function heavyProcess()
    {
        $sum = 0;

        for ($i = 0; $i < 10000; $i++) {
            $sum += $i * rand(1, 10);
        }

        return ['result' => $sum];
    }

    public function testLoop()
    {
        $arr = [];

        for ($i = 0; $i < 500; $i++) {
            $arr[] = "Line number " . $i;
        }

        return $arr;
    }

    public function bigResponse()
    {
        return response()->json([
            'status' => true,
            'message' => 'Huge data',
            'data' => $this->testLoop()
        ]);
    }
}