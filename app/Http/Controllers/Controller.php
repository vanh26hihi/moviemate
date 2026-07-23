<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MegaOnePageController extends Controller
{
    // ===== FAKE DB =====
    protected static $movies = [];
    protected static $users = [];
    protected static $tokens = [];

    public function __construct()
    {
        // seed user
        if (empty(self::$users)) {
            self::$users[1] = [
                'id' => 1,
                'name' => 'admin',
                'email' => 'admin@gmail.com',
                'password' => '123456',
                'role' => 'admin'
            ];
        }

        // seed movie
        if (empty(self::$movies)) {
            for ($i = 1; $i <= 20; $i++) {
                self::$movies[$i] = [
                    'id' => $i,
                    'name' => "Movie $i",
                    'price' => rand(10000, 100000),
                    'views' => rand(0, 1000),
                    'image' => null
                ];
            }
        }
    }

    // ================= AUTH =================

    public function register(Request $request)
    {
        $id = count(self::$users) + 1;

        self::$users[$id] = [
            'id' => $id,
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'role' => 'user'
        ];

        return ['message' => 'Registered'];
    }

    public function login(Request $request)
    {
        foreach (self::$users as $user) {
            if (
                $user['email'] == $request->email &&
                $user['password'] == $request->password
            ) {
                $token = md5($user['email'] . time());
                self::$tokens[$token] = $user;

                return ['token' => $token];
            }
        }

        return ['error' => 'Login failed'];
    }

    protected function auth($request)
    {
        $token = $request->header('Authorization');

        return self::$tokens[$token] ?? null;
    }

    // ================= MOVIE CRUD =================

    public function index()
    {
        return array_values(self::$movies);
    }

    public function store(Request $request)
    {
        $user = $this->auth($request);

        if (!$user || $user['role'] != 'admin') {
            return ['error' => 'Unauthorized'];
        }

        $id = count(self::$movies) + 1;

        self::$movies[$id] = [
            'id' => $id,
            'name' => $request->name,
            'price' => $request->price,
            'views' => 0,
            'image' => null
        ];

        return self::$movies[$id];
    }

    public function show($id)
    {
        return self::$movies[$id] ?? ['error' => 'Not found'];
    }

    public function update(Request $request, $id)
    {
        $user = $this->auth($request);

        if (!$user || $user['role'] != 'admin') {
            return ['error' => 'Unauthorized'];
        }

        if (!isset(self::$movies[$id])) {
            return ['error' => 'Not found'];
        }

        self::$movies[$id]['name'] = $request->name ?? self::$movies[$id]['name'];
        self::$movies[$id]['price'] = $request->price ?? self::$movies[$id]['price'];

        return self::$movies[$id];
    }

    public function destroy(Request $request, $id)
    {
        $user = $this->auth($request);

        if (!$user || $user['role'] != 'admin') {
            return ['error' => 'Unauthorized'];
        }

        unset(self::$movies[$id]);

        return ['message' => 'Deleted'];
    }

    // ================= UPLOAD =================

    public function upload(Request $request, $id)
    {
        $user = $this->auth($request);

        if (!$user) return ['error' => 'Unauthorized'];

        if (!$request->hasFile('file')) {
            return ['error' => 'No file'];
        }

        $path = $request->file('file')->store('uploads', 'public');

        self::$movies[$id]['image'] = $path;

        return ['path' => $path];
    }

    // ================= EXTRA =================

    public function top()
    {
        $data = array_values(self::$movies);

        usort($data, fn($a, $b) => $b['views'] <=> $a['views']);

        return array_slice($data, 0, 5);
    }

    public function view($id)
    {
        if (!isset(self::$movies[$id])) {
            return ['error' => 'Not found'];
        }

        self::$movies[$id]['views']++;

        return self::$movies[$id];
    }
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
}