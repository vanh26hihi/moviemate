<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MegaOnePageController extends Controller
{
    // ================= BASIC =================

    public function index()
    {
        return response()->json([
            'message' => 'Mega One Page Controller Ready 🚀'
        ]);
    }

    // ================= BOOKING FAKE =================

    public function bookings()
    {
        $data = [];

        for ($i = 1; $i <= 100; $i++) {
            $data[] = [
                'id' => $i,
                'code' => 'BK' . rand(1000, 9999),
                'amount' => rand(10000, 500000),
                'status' => ['paid', 'pending'][rand(0, 1)],
            ];
        }

        return $data;
    }

    // ================= ANALYTICS =================

    public function traffic()
    {
        $data = [];

        for ($i = 1; $i <= 30; $i++) {
            $data[] = [
                'day' => $i,
                'visits' => rand(100, 5000),
                'clicks' => rand(50, 2000),
                'conversions' => rand(1, 300),
            ];
        }

        return $data;
    }

    public function conversion()
    {
        $traffic = $this->traffic();
        $result = [];

        foreach ($traffic as $t) {
            $rate = $t['clicks'] > 0
                ? ($t['conversions'] / $t['clicks']) * 100
                : 0;

            $result[] = [
                'day' => $t['day'],
                'rate' => round($rate, 2),
            ];
        }

        return $result;
    }

    // ================= HEAVY PROCESS =================

    public function heavy()
    {
        $sum = 0;

        for ($i = 0; $i < 20000; $i++) {
            $sum += sqrt($i) * rand(1, 100);
        }

        return ['result' => $sum];
    }

    // ================= CACHE FAKE =================

    protected $cache = [];

    public function cachePut()
    {
        for ($i = 0; $i < 100; $i++) {
            $this->cache["key_$i"] = rand(1, 9999);
        }

        return ['message' => 'Cache stored'];
    }

    public function cacheGet($key)
    {
        return $this->cache[$key] ?? null;
    }

    // ================= PAYMENT =================

    public function payment()
    {
        return [
            'amount' => rand(1000, 10000),
            'status' => rand(0, 1) ? 'success' : 'failed',
            'transaction_id' => uniqid('txn_'),
            'time' => now()
        ];
    }

    // ================= BIG RESPONSE =================

    public function big()
    {
        $arr = [];

        for ($i = 0; $i < 300; $i++) {
            $arr[] = "Line $i";
        }

        return response()->json([
            'status' => true,
            'data' => $arr
        ]);
    }
}