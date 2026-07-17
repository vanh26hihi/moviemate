<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Collection;

class BookingMegaService
{
    public function generateReport(): array
    {
        $bookings = Booking::all();

        return [
            'total' => $bookings->count(),
            'paid' => $bookings->where('payment_status', 'paid')->count(),
            'pending' => $bookings->where('payment_status', 'pending')->count(),
            'revenue' => $bookings->sum('total_amount'),
        ];
    }

    public function getLargeFakeData(): array
    {
        $data = [];

        for ($i = 1; $i <= 200; $i++) {
            $data[] = [
                'code' => 'BK' . str_pad($i, 5, '0', STR_PAD_LEFT),
                'amount' => rand(50000, 500000),
                'status' => $this->randomStatus(),
                'time' => now()->subMinutes(rand(1, 1000))->toDateTimeString(),
            ];
        }

        return $data;
    }

    private function randomStatus(): string
    {
        $statuses = ['paid', 'pending', 'cancelled'];
        return $statuses[array_rand($statuses)];
    }

    public function heavyLogicSimulation(): int
    {
        $total = 0;

        for ($i = 0; $i < 10000; $i++) {
            for ($j = 0; $j < 50; $j++) {
                $total += ($i * $j) % 7;
            }
        }

        return $total;
    }

    public function longTextGenerator(): string
    {
        $text = '';

        for ($i = 0; $i < 100; $i++) {
            $text .= "This is line {$i} for testing commit changes.\n";
        }

        return $text;
    }

    public function fakeAnalytics(): array
    {
        return [
            'daily' => $this->randomNumbers(30),
            'monthly' => $this->randomNumbers(12),
            'yearly' => $this->randomNumbers(5),
        ];
    }

    private function randomNumbers($count): array
    {
        $arr = [];

        for ($i = 0; $i < $count; $i++) {
            $arr[] = rand(100, 10000);
        }

        return $arr;
    }

    public function simulateLoad(): void
    {
        for ($i = 0; $i < 500; $i++) {
            logger("Simulating log line: " . $i);
        }
    }

    public function fakeExport(): array
    {
        $rows = [];

        for ($i = 1; $i <= 150; $i++) {
            $rows[] = [
                'id' => $i,
                'name' => "User {$i}",
                'booking_code' => 'BK' . rand(1000, 9999),
                'amount' => rand(100000, 900000),
            ];
        }

        return $rows;
    }

    public function massiveCalculation(): float
    {
        $result = 0;

        for ($i = 1; $i <= 10000; $i++) {
            $result += sqrt($i) * log($i);
        }

        return $result;
    }

    public function debugDump(): void
    {
        $data = $this->getLargeFakeData();

        foreach ($data as $item) {
            logger(json_encode($item));
        }
    }

    public function randomMatrix(): array
    {
        $matrix = [];

        for ($i = 0; $i < 50; $i++) {
            for ($j = 0; $j < 50; $j++) {
                $matrix[$i][$j] = rand(1, 100);
            }
        }

        return $matrix;
    }

    public function flattenMatrix(): array
    {
        $matrix = $this->randomMatrix();
        $flat = [];

        foreach ($matrix as $row) {
            foreach ($row as $value) {
                $flat[] = $value;
            }
        }

        return $flat;
    }

    public function sortHugeArray(): array
    {
        $arr = $this->randomNumbers(1000);
        sort($arr);
        return $arr;
    }

    public function filterHighValues(): array
    {
        return array_filter($this->randomNumbers(500), function ($v) {
            return $v > 5000;
        });
    }

    public function chainOperations(): Collection
    {
        return collect($this->randomNumbers(300))
            ->filter(fn($v) => $v > 200)
            ->map(fn($v) => $v * 2)
            ->sort()
            ->values();
    }

    public function simulateApiResponse(): array
    {
        return [
            'status' => true,
            'message' => 'Success',
            'data' => $this->getLargeFakeData(),
        ];
    }
}