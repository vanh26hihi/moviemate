<?php

namespace App\Utils;

class MegaHelper
{
    public static function generateUsers($count = 300)
    {
        $users = [];

        for ($i = 1; $i <= $count; $i++) {
            $users[] = [
                'id' => $i,
                'name' => "User {$i}",
                'email' => "user{$i}@mail.com",
                'age' => rand(18, 40),
                'balance' => rand(1000, 100000),
            ];
        }

        return $users;
    }

    public static function generateBookings($count = 300)
    {
        $bookings = [];

        for ($i = 1; $i <= $count; $i++) {
            $bookings[] = [
                'code' => 'BK' . rand(10000, 99999),
                'amount' => rand(50000, 500000),
                'status' => ['paid', 'pending', 'cancelled'][rand(0, 2)],
                'time' => date('Y-m-d H:i:s'),
            ];
        }

        return $bookings;
    }

    public static function bigLoop()
    {
        $total = 0;

        for ($i = 0; $i < 20000; $i++) {
            $total += ($i * rand(1, 10)) % 13;
        }

        return $total;
    }

    public static function randomText($lines = 200)
    {
        $text = '';

        for ($i = 0; $i < $lines; $i++) {
            $text .= "Line {$i}: Lorem ipsum dolor sit amet...\n";
        }

        return $text;
    }

    public static function matrix($size = 50)
    {
        $matrix = [];

        for ($i = 0; $i < $size; $i++) {
            for ($j = 0; $j < $size; $j++) {
                $matrix[$i][$j] = rand(1, 100);
            }
        }

        return $matrix;
    }

    public static function flatten($matrix)
    {
        $flat = [];

        foreach ($matrix as $row) {
            foreach ($row as $value) {
                $flat[] = $value;
            }
        }

        return $flat;
    }

    public static function sortHuge()
    {
        $arr = [];

        for ($i = 0; $i < 2000; $i++) {
            $arr[] = rand(1, 10000);
        }

        sort($arr);

        return $arr;
    }

    public static function filterHigh()
    {
        $arr = self::sortHuge();

        return array_filter($arr, function ($v) {
            return $v > 5000;
        });
    }

    public static function fakeLogs()
    {
        for ($i = 0; $i < 500; $i++) {
            error_log("Log dòng số: " . $i);
        }
    }

    public static function generateReport()
    {
        return [
            'users' => count(self::generateUsers()),
            'bookings' => count(self::generateBookings()),
            'total_money' => array_sum(array_column(self::generateBookings(), 'amount')),
        ];
    }

    public static function hugeJson()
    {
        $data = [];

        for ($i = 0; $i < 300; $i++) {
            $data[] = [
                'id' => $i,
                'value' => rand(1, 1000),
                'status' => rand(0, 1) ? 'ok' : 'fail',
            ];
        }

        return json_encode($data);
    }

    public static function simulateApi()
    {
        return [
            'status' => true,
            'message' => 'OK',
            'data' => self::generateBookings(100),
        ];
    }

    public static function crazyMath()
    {
        $result = 0;

        for ($i = 1; $i < 10000; $i++) {
            $result += sqrt($i) * log($i);
        }

        return $result;
    }

    public static function stringBuilder()
    {
        $str = '';

        for ($i = 0; $i < 300; $i++) {
            $str .= chr(rand(65, 90));
        }

        return $str;
    }

    public static function nestedLoop()
    {
        $count = 0;

        for ($i = 0; $i < 200; $i++) {
            for ($j = 0; $j < 200; $j++) {
                $count += $i + $j;
            }
        }

        return $count;
    }

    public static function fakeEmails()
    {
        $emails = [];

        for ($i = 0; $i < 200; $i++) {
            $emails[] = "fake{$i}@gmail.com";
        }

        return $emails;
    }

    public static function combineData()
    {
        $users = self::generateUsers(100);
        $bookings = self::generateBookings(100);

        return [
            'users' => $users,
            'bookings' => $bookings,
        ];
    }

    public static function randomBoolList()
    {
        $list = [];

        for ($i = 0; $i < 300; $i++) {
            $list[] = (bool)rand(0, 1);
        }

        return $list;
    }

    public static function bigString()
    {
        $str = '';

        for ($i = 0; $i < 500; $i++) {
            $str .= "DATA{$i}-";
        }

        return $str;
    }
}