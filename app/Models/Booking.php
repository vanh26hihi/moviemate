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
    <?php

namespace App\Services;

class MegaAnalyticsService
{
    public function generateTraffic($days = 365)
    {
        $data = [];

        for ($i = 0; $i < $days; $i++) {
            $data[] = [
                'day' => $i,
                'visits' => rand(100, 5000),
                'clicks' => rand(50, 2000),
                'conversions' => rand(1, 300),
            ];
        }

        return $data;
    }

    public function calculateConversionRate($traffic)
    {
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

    public function summary()
    {
        $traffic = $this->generateTraffic();

        $totalVisits = array_sum(array_column($traffic, 'visits'));
        $totalClicks = array_sum(array_column($traffic, 'clicks'));
        $totalConversions = array_sum(array_column($traffic, 'conversions'));

        return [
            'visits' => $totalVisits,
            'clicks' => $totalClicks,
            'conversions' => $totalConversions,
        ];
    }
}
<?php

namespace App\Services;

class MegaCacheSimulator
{
    protected $cache = [];

    public function put($key, $value)
    {
        $this->cache[$key] = [
            'value' => $value,
            'time' => time()
        ];
    }

    public function get($key)
    {
        return $this->cache[$key]['value'] ?? null;
    }

    public function warmUp()
    {
        for ($i = 0; $i < 1000; $i++) {
            $this->put("key_{$i}", rand(1, 99999));
        }
    }

    public function stats()
    {
        return [
            'total_keys' => count($this->cache),
            'memory_estimate' => strlen(json_encode($this->cache))
        ];
    }
}
<?php

namespace App\Services;

class MegaPaymentService
{
    public function process($amount)
    {
        return [
            'amount' => $amount,
            'status' => rand(0, 1) ? 'success' : 'failed',
            'transaction_id' => uniqid('txn_'),
            'time' => now()
        ];
    }

    public function bulkPayments($n = 200)
    {
        $results = [];

        for ($i = 0; $i < $n; $i++) {
            $results[] = $this->process(rand(100, 10000));
        }

        return $results;
    }

    public function totalSuccess($payments)
    {
        return count(array_filter($payments, fn($p) => $p['status'] === 'success'));
    }
}
<?php

namespace App\Services;

class MegaNotificationService
{
    public function send($user, $message)
    {
        return [
            'user' => $user,
            'message' => $message,
            'sent_at' => now(),
            'status' => 'sent'
        ];
    }

    public function broadcast($users)
    {
        $logs = [];

        foreach ($users as $u) {
            $logs[] = $this->send($u, "Hello {$u}");
        }

        return $logs;
    }
}
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class MegaJob implements ShouldQueue
{
    use Queueable;

    public function handle()
    {
        $sum = 0;

        for ($i = 0; $i < 50000; $i++) {
            $sum += sqrt($i) * rand(1, 100);
        }

        \Log::info("MegaJob done: " . $sum);
    }
}
<?php

namespace App\Repositories;

class MegaRepository
{
    protected $data = [];

    public function seed($n = 500)
    {
        for ($i = 0; $i < $n; $i++) {
            $this->data[] = [
                'id' => $i,
                'value' => rand(1, 9999)
            ];
        }
    }

    public function all()
    {
        return $this->data;
    }

    public function find($id)
    {
        foreach ($this->data as $d) {
            if ($d['id'] == $id) return $d;
        }

        return null;
    }
}
}