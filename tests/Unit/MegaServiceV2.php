<?php

namespace App\Services;

class MegaServiceV2
{
    public function generateData($n = 500)
    {
        $data = [];

        for ($i = 0; $i < $n; $i++) {
            $data[] = [
                'id' => $i,
                'name' => 'Item ' . $i,
                'price' => rand(100, 10000),
                'status' => ['new', 'old', 'sale'][rand(0, 2)],
                'created_at' => now(),
            ];
        }

        return $data;
    }

    public function processData()
    {
        $data = $this->generateData();

        $result = [];

        foreach ($data as $item) {
            if ($item['price'] > 5000) {
                $item['vip'] = true;
            } else {
                $item['vip'] = false;
            }

            $result[] = $item;
        }

        return $result;
    }

    public function heavyCalculation()
    {
        $sum = 0;

        for ($i = 1; $i <= 30000; $i++) {
            $sum += ($i * rand(1, 50)) % 97;
        }

        return $sum;
    }

    public function fakeUsers()
    {
        $users = [];

        for ($i = 1; $i <= 400; $i++) {
            $users[] = [
                'username' => "user_{$i}",
                'email' => "user{$i}@mail.com",
                'score' => rand(0, 1000),
            ];
        }

        return $users;
    }

    public function mergeEverything()
    {
        return [
            'data' => $this->generateData(200),
            'users' => $this->fakeUsers(),
            'calc' => $this->heavyCalculation(),
        ];
    }

    public function longString()
    {
        $str = '';

        for ($i = 0; $i < 1000; $i++) {
            $str .= "LINE{$i} ";
        }

        return $str;
    }

    public function matrix()
    {
        $m = [];

        for ($i = 0; $i < 100; $i++) {
            for ($j = 0; $j < 100; $j++) {
                $m[$i][$j] = rand(1, 999);
            }
        }

        return $m;
    }

    public function flatten($matrix)
    {
        $flat = [];

        foreach ($matrix as $row) {
            foreach ($row as $v) {
                $flat[] = $v;
            }
        }

        return $flat;
    }

    public function logs()
    {
        for ($i = 0; $i < 1000; $i++) {
            error_log("Mega log {$i}");
        }
    }

    public function randomBooleans()
    {
        $arr = [];

        for ($i = 0; $i < 500; $i++) {
            $arr[] = (bool)rand(0, 1);
        }

        return $arr;
    }

    public function generateJson()
    {
        $data = [];

        for ($i = 0; $i < 500; $i++) {
            $data[] = [
                'id' => $i,
                'value' => rand(1, 9999),
                'ok' => rand(0, 1),
            ];
        }

        return json_encode($data);
    }

    public function nestedLoop()
    {
        $count = 0;

        for ($i = 0; $i < 300; $i++) {
            for ($j = 0; $j < 300; $j++) {
                $count += $i * $j;
            }
        }

        return $count;
    }

    public function emails()
    {
        $emails = [];

        for ($i = 0; $i < 500; $i++) {
            $emails[] = "test{$i}@gmail.com";
        }

        return $emails;
    }

    public function bigArray()
    {
        $arr = [];

        for ($i = 0; $i < 1000; $i++) {
            $arr[] = rand(1, 100000);
        }

        sort($arr);

        return $arr;
    }

    public function filter()
    {
        return array_filter($this->bigArray(), function ($v) {
            return $v > 50000;
        });
    }

    public function crazyMath()
    {
        $r = 0;

        for ($i = 1; $i < 20000; $i++) {
            $r += sqrt($i) * log($i);
        }

        return $r;
    }

    public function buildHugeText()
    {
        $txt = '';

        for ($i = 0; $i < 1500; $i++) {
            $txt .= "DATA-{$i}-" . rand(1, 999) . "\n";
        }

        return $txt;
    }

    public function combine()
    {
        return [
            'users' => $this->fakeUsers(),
            'data' => $this->generateData(),
            'json' => $this->generateJson(),
        ];
    }

    public function superMega()
    {
        $result = [];

        for ($i = 0; $i < 200; $i++) {
            $result[] = [
                'index' => $i,
                'value' => md5($i . time()),
                'rand' => rand(1, 999999),
            ];
        }

        return $result;
    }

    public function spamLines()
    {
        $lines = [];

        for ($i = 0; $i < 1000; $i++) {
            $lines[] = "Spam line number {$i}";
        }

        return implode("\n", $lines);
    }
}