<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\MegaPaymentService;
use App\Services\MegaAnalyticsService;

class MegaController extends Controller
{
    protected $payment;
    protected $analytics;

    public function __construct(
        MegaPaymentService $payment,
        MegaAnalyticsService $analytics
    ) {
        $this->payment = $payment;
        $this->analytics = $analytics;
    }

    public function payments()
    {
        return $this->payment->bulkPayments(300);
    }

    public function traffic()
    {
        $data = $this->analytics->generateTraffic(100);
        return response()->json($data);
    }

    public function conversion()
    {
        $traffic = $this->analytics->generateTraffic(50);
        return $this->analytics->calculateConversionRate($traffic);
    }

    public function summary()
    {
        return $this->analytics->summary();
    }

    public function stressTest()
    {
        $result = [];

        for ($i = 0; $i < 1000; $i++) {
            $result[] = [
                'id' => $i,
                'value' => md5($i . time())
            ];
        }

        return $result;
    }
}