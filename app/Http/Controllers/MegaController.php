<?php

namespace App\Services;

class UltraMegaService
{
    public function generateUsers($count = 1000)
    {
        $users = [];

        for ($i = 1; $i <= $count; $i++) {
            $users[] = [
                'id' => $i,
                'name' => "User {$i}",
                'email' => "user{$i}@gmail.com",
                'age' => rand(18, 60),
                'score' => rand(0, 1000),
            ];
        }

        return $users;
    }

    public function sortUsersByScore($users)
    {
        usort($users, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return $users;
    }

    public function topUsers()
    {
        $users = $this->generateUsers(500);
        return array_slice($this->sortUsersByScore($users), 0, 50);
    }

    public function generateOrders()
    {
        $orders = [];

        for ($i = 1; $i <= 800; $i++) {
            $orders[] = [
                'order_id' => $i,
                'amount' => rand(100, 10000),
                'status' => ['pending', 'done', 'cancel'][rand(0, 2)],
            ];
        }

        return $orders;
    }

    public function calculateRevenue()
    {
        $orders = $this->generateOrders();
        $sum = 0;

        foreach ($orders as $o) {
            if ($o['status'] === 'done') {
                $sum += $o['amount'];
            }
        }

        return $sum;
    }

    public function fibonacci($n = 30)
    {
        $fib = [0, 1];

        for ($i = 2; $i < $n; $i++) {
            $fib[$i] = $fib[$i - 1] + $fib[$i - 2];
        }

        return $fib;
    }

    public function primes($limit = 1000)
    {
        $primes = [];

        for ($i = 2; $i < $limit; $i++) {
            $isPrime = true;

            for ($j = 2; $j <= sqrt($i); $j++) {
                if ($i % $j === 0) {
                    $isPrime = false;
                    break;
                }
            }

            if ($isPrime) {
                $primes[] = $i;
            }
        }

        return $primes;
    }

    public function randomMatrix($size = 50)
    {
        $matrix = [];

        for ($i = 0; $i < $size; $i++) {
            for ($j = 0; $j < $size; $j++) {
                $matrix[$i][$j] = rand(1, 999);
            }
        }

        return $matrix;
    }

    public function matrixSum($matrix)
    {
        $sum = 0;

        foreach ($matrix as $row) {
            foreach ($row as $v) {
                $sum += $v;
            }
        }

        return $sum;
    }

    public function hugeText()
    {
        $text = '';

        for ($i = 0; $i < 2000; $i++) {
            $text .= "This is line {$i} with random " . rand(1, 9999) . "\n";
        }

        return $text;
    }

    public function randomStrings($count = 1000)
    {
        $arr = [];

        for ($i = 0; $i < $count; $i++) {
            $arr[] = bin2hex(random_bytes(5));
        }

        return $arr;
    }

    public function filterEvenNumbers()
    {
        $nums = range(1, 2000);

        return array_filter($nums, function ($n) {
            return $n % 2 === 0;
        });
    }

    public function bigLoop()
    {
        $count = 0;

        for ($i = 0; $i < 500; $i++) {
            for ($j = 0; $j < 500; $j++) {
                for ($k = 0; $k < 10; $k++) {
                    $count += $i + $j + $k;
                }
            }
        }

        return $count;
    }

    public function simulateLogs()
    {
        for ($i = 0; $i < 1000; $i++) {
            error_log("Ultra log {$i}");
        }
    }

    public function buildDataset()
    {
        return [
            'users' => $this->generateUsers(),
            'orders' => $this->generateOrders(),
            'revenue' => $this->calculateRevenue(),
            'fib' => $this->fibonacci(),
            'primes' => $this->primes(),
        ];
    }

    public function crazyCompute()
    {
        $res = 0;

        for ($i = 1; $i < 10000; $i++) {
            $res += sin($i) * cos($i) * log($i);
        }

        return $res;
    }

    public function spamData()
    {
        $data = [];

        for ($i = 0; $i < 1500; $i++) {
            $data[] = [
                'index' => $i,
                'hash' => sha1($i . time()),
                'value' => rand(1, 1000000),
            ];
        }

        return $data;
    }

    public function everything()
    {
        return [
            'dataset' => $this->buildDataset(),
            'spam' => $this->spamData(),
            'calc' => $this->crazyCompute(),
        ];
    }
    <?php

namespace App\Helpers;

class MegaHelper
{
    public static function randomStrings($n = 1000)
    {
        $arr = [];

        for ($i = 0; $i < $n; $i++) {
            $arr[] = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz"), 0, 10);
        }

        return $arr;
    }

    public static function hugeText()
    {
        $text = '';

        for ($i = 0; $i < 3000; $i++) {
            $text .= "TEXT_LINE_{$i}_" . rand(100, 999) . "\n";
        }

        return $text;
    }

    public static function randomNumbers()
    {
        $nums = [];

        for ($i = 0; $i < 2000; $i++) {
            $nums[] = rand(1, 100000);
        }

        return $nums;
    }
}
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MegaCommand extends Command
{
    protected $signature = 'mega:run';
    protected $description = 'Run mega heavy command';

    public function handle()
    {
        $this->info("Starting mega command...");

        $total = 0;

        for ($i = 0; $i < 100000; $i++) {
            $total += ($i * rand(1, 10)) % 99;
        }

        $this->info("Done: " . $total);

        return 0;
    }
}
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MegaSeeder extends Seeder
{
    public function run()
    {
        $data = [];

        for ($i = 0; $i < 1000; $i++) {
            $data[] = [
                'name' => 'Seeder ' . $i,
                'value' => rand(1, 99999),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('mega_table')->insert($data);
    }
}
use App\Http\Controllers\MegaController;

Route::get('/mega-users', function () {
    return (new MegaController())->generateUsers(200);
});

Route::get('/mega-movies', function () {
    return (new MegaController())->generateMovies(200);
});

Route::get('/mega-bookings', function () {
    return (new MegaController())->generateBookings(200);
});

Route::get('/mega-stats', function () {
    return (new MegaController())->stats();
});
}