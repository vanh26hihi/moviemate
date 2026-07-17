<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MegaController extends Controller
{
    public function index()
    {
        return response()->json([
            'message' => 'Mega Controller Running',
            'time' => now()
        ]);
    }

    public function generateUsers($n = 300)
    {
        $users = [];

        for ($i = 1; $i <= $n; $i++) {
            $users[] = [
                'id' => $i,
                'name' => "User {$i}",
                'email' => "user{$i}@gmail.com",
                'score' => rand(0, 1000),
                'created_at' => now()
            ];
        }

        return $users;
    }

    public function generateMovies($n = 300)
    {
        $movies = [];

        for ($i = 1; $i <= $n; $i++) {
            $movies[] = [
                'id' => $i,
                'title' => "Movie {$i}",
                'rating' => rand(1, 10),
                'views' => rand(100, 100000),
                'status' => ['showing', 'coming', 'ended'][rand(0, 2)]
            ];
        }

        return $movies;
    }

    public function generateBookings($n = 300)
    {
        $data = [];

        for ($i = 0; $i < $n; $i++) {
            $data[] = [
                'code' => 'BK' . rand(10000, 99999),
                'price' => rand(50000, 500000),
                'status' => ['paid', 'pending', 'cancel'][rand(0, 2)],
                'created_at' => now()
            ];
        }

        return $data;
    }

    public function stats()
    {
        $users = $this->generateUsers(100);
        $movies = $this->generateMovies(100);
        $bookings = $this->generateBookings(100);

        return [
            'users' => count($users),
            'movies' => count($movies),
            'bookings' => count($bookings),
            'revenue' => array_sum(array_column($bookings, 'price'))
        ];
    }

    public function heavyLoop()
    {
        $total = 0;

        for ($i = 0; $i < 50000; $i++) {
            $total += ($i * rand(1, 20)) % 123;
        }

        return $total;
    }

    public function matrix($size = 80)
    {
        $m = [];

        for ($i = 0; $i < $size; $i++) {
            for ($j = 0; $j < $size; $j++) {
                $m[$i][$j] = rand(1, 999);
            }
        }

        return $m;
    }

    public function flatten()
    {
        $matrix = $this->matrix();
        $flat = [];

        foreach ($matrix as $row) {
            foreach ($row as $v) {
                $flat[] = $v;
            }
        }

        return $flat;
    }

    public function fakeLogs()
    {
        for ($i = 0; $i < 1500; $i++) {
            \Log::info("Mega log line {$i}");
        }

        return "Logged!";
    }

    public function hugeJson()
    {
        $data = [];

        for ($i = 0; $i < 800; $i++) {
            $data[] = [
                'id' => $i,
                'value' => rand(1, 10000),
                'status' => rand(0, 1) ? 'ok' : 'fail'
            ];
        }

        return response()->json($data);
    }

    public function randomText()
    {
        $txt = '';

        for ($i = 0; $i < 2000; $i++) {
            $txt .= "LINE {$i} - DATA " . rand(1, 9999) . "\n";
        }

        return $txt;
    }

    public function nestedLoop()
    {
        $count = 0;

        for ($i = 0; $i < 400; $i++) {
            for ($j = 0; $j < 400; $j++) {
                $count += $i + $j;
            }
        }

        return $count;
    }

    public function bigArray()
    {
        $arr = [];

        for ($i = 0; $i < 2000; $i++) {
            $arr[] = rand(1, 100000);
        }

        sort($arr);

        return $arr;
    }

    public function filterHigh()
    {
        return array_filter($this->bigArray(), function ($v) {
            return $v > 70000;
        });
    }

    public function crazyMath()
    {
        $r = 0;

        for ($i = 1; $i < 30000; $i++) {
            $r += sqrt($i) * log($i);
        }

        return $r;
    }

    public function fakeEmails()
    {
        $emails = [];

        for ($i = 0; $i < 1000; $i++) {
            $emails[] = "fake{$i}@mail.com";
        }

        return $emails;
    }

    public function combineAll()
    {
        return [
            'users' => $this->generateUsers(200),
            'movies' => $this->generateMovies(200),
            'bookings' => $this->generateBookings(200)
        ];
    }

    public function superMega()
    {
        $data = [];

        for ($i = 0; $i < 500; $i++) {
            $data[] = [
                'index' => $i,
                'hash' => md5($i . time()),
                'rand' => rand(1, 9999