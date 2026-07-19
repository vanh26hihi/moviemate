<?php

namespace App\Http\Controllers;

abstract class Controller
{
    <?php

namespace App\Http\Controllers;

use App\Services\MegaPaymentService;
use App\Services\MegaNotificationService;

class MegaAdvancedController extends Controller
{
    public function payments()
    {
        $service = new MegaPaymentService();
        $data = $service->bulkPayments(100);

        return response()->json([
            'total_success' => $service->totalSuccess($data),
            'data' => $data
        ]);
    }

    public function notify()
    {
        $users = ['A', 'B', 'C', 'D'];

        $noti = new MegaNotificationService();

        return $noti->broadcast($users);
    }
}
}
