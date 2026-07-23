<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MegaOnePageController extends Controller
{
    // ====== FAKE DATABASE ======
    protected static $data = [];

    public function __construct()
    {
        if (empty(self::$data)) {
            for ($i = 1; $i <= 50; $i++) {
                self::$data[$i] = [
                    'id' => $i,
                    'name' => "Movie $i",
                    'price' => rand(10000, 100000),
                    'status' => ['active', 'inactive'][rand(0, 1)],
                    'views' => rand(0, 1000),
                ];
            }
        }
    }

    // ====== READ ALL ======
    public function index(Request $request)
    {
        $data = array_values(self::$data);

        // search
        if ($request->keyword) {
            $data = array_filter($data, function ($item) use ($request) {
                return str_contains(strtolower($item['name']), strtolower($request->keyword));
            });
        }

        // filter status
        if ($request->status) {
            $data = array_filter($data, fn($i) => $i['status'] == $request->status);
        }

        // sort
        if ($request->sort == 'price') {
            usort($data, fn($a, $b) => $a['price'] <=> $b['price']);
        }

        return array_values($data);
    }

    // ====== SHOW ======
    public function show($id)
    {
        return self::$data[$id] ?? ['error' => 'Not found'];
    }

    // ====== CREATE ======
    public function store(Request $request)
    {
        $id = count(self::$data) + 1;

        self::$data[$id] = [
            'id' => $id,
            'name' => $request->name ?? "New Movie",
            'price' => $request->price ?? rand(10000, 50000),
            'status' => $request->status ?? 'active',
            'views' => 0
        ];

        return self::$data[$id];
    }

    // ====== UPDATE ======
    public function update(Request $request, $id)
    {
        if (!isset(self::$data[$id])) {
            return ['error' => 'Not found'];
        }

        self::$data[$id]['name'] = $request->name ?? self::$data[$id]['name'];
        self::$data[$id]['price'] = $request->price ?? self::$data[$id]['price'];
        self::$data[$id]['status'] = $request->status ?? self::$data[$id]['status'];

        return self::$data[$id];
    }

    // ====== DELETE ======
    public function destroy($id)
    {
        if (!isset(self::$data[$id])) {
            return ['error' => 'Not found'];
        }

        unset(self::$data[$id]);

        return ['message' => 'Deleted'];
    }

    // ====== TOP MOVIES ======
    public function top()
    {
        $data = array_values(self::$data);

        usort($data, fn($a, $b) => $b['views'] <=> $a['views']);

        return array_slice($data, 0, 5);
    }

    // ====== INCREASE VIEW ======
    public function view($id)
    {
        if (!isset(self::$data[$id])) {
            return ['error' => 'Not found'];
        }

        self::$data[$id]['views']++;

        return self::$data[$id];
    }

    // ====== STATS ======
    public function stats()
    {
        $data = array_values(self::$data);

        return [
            'total' => count($data),
            'active' => count(array_filter($data, fn($i) => $i['status'] == 'active')),
            'inactive' => count(array_filter($data, fn($i) => $i['status'] == 'inactive')),
            'avg_price' => array_sum(array_column($data, 'price')) / count($data),
        ];
    }

    // ====== BULK GENERATE ======
    public function generate($n = 100)
    {
        for ($i = 0; $i < $n; $i++) {
            $id = count(self::$data) + 1;

            self::$data[$id] = [
                'id' => $id,
                'name' => "Generated $id",
                'price' => rand(10000, 100000),
                'status' => ['active', 'inactive'][rand(0, 1)],
                'views' => rand(0, 500)
            ];
        }

        return ['message' => "Generated $n records"];
    }
}