<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public const PERMISSIONS = [
        'cinemas.view' => 'Xem danh sách chi nhánh',
        'cinemas.manage' => 'Quản lý chi nhánh',
        'cinema_assignments.view' => 'Xem phân công chi nhánh',
        'cinema_assignments.manage' => 'Quản lý phân công chi nhánh',
        'admin.access' => 'Truy cập khu vực quản trị',
        'dashboard.view' => 'Xem tổng quan',
        'cinema.view' => 'Xem rạp',
        'cinema.create' => 'Tạo rạp',
        'cinema.update' => 'Sửa rạp',
        'cinema.delete' => 'Xóa rạp',
        'rooms.view' => 'Xem phòng',
        'rooms.create' => 'Tạo phòng',
        'rooms.update' => 'Sửa phòng',
        'rooms.delete' => 'Xóa phòng',
        'seats.view' => 'Xem ghế',
        'seats.manage' => 'Quản lý sơ đồ ghế',
        'movies.view' => 'Xem phim',
        'movies.create' => 'Tạo phim',
        'movies.update' => 'Sửa phim',
        'movies.delete' => 'Xóa phim',
        'genres.view' => 'Xem thể loại',
        'genres.create' => 'Tạo thể loại',
        'genres.update' => 'Sửa thể loại',
        'genres.delete' => 'Xóa thể loại',
        'showtimes.view' => 'Xem suất chiếu',
        'showtimes.create' => 'Tạo suất chiếu',
        'showtimes.update' => 'Sửa suất chiếu',
        'showtimes.delete' => 'Xóa suất chiếu',
        'foods.view' => 'Xem danh mục món ăn',
        'foods.create' => 'Tạo món ăn',
        'foods.update' => 'Sửa món ăn',
        'foods.delete' => 'Xóa món ăn',
        'food-orders.view' => 'Xem đơn đồ ăn',
        'food-orders.update-status' => 'Cập nhật trạng thái đơn đồ ăn',
        'bookings.view' => 'Xem đơn đặt vé',
        'bookings.operate' => 'Vận hành đơn đặt vé',
        'payments.view' => 'Xem thanh toán',
        'payments.reconcile' => 'Đối soát giao dịch',
        'ticket_deliveries.view' => 'Xem lịch sử gửi vé điện tử',
        'ticket_deliveries.retry' => 'Gửi lại vé điện tử',
        'ticket_checkins.view' => 'Xem lịch sử soát vé',
        'seats.maintenance.view' => 'Xem trạng thái bảo trì ghế',
        'seats.maintenance.update' => 'Cập nhật trạng thái bảo trì ghế',
        'discounts.view' => 'Xem mã giảm giá',
        'discounts.manage' => 'Quản lý mã giảm giá',
        'reviews.view' => 'Xem đánh giá phim',
        'reviews.moderate' => 'Kiểm duyệt đánh giá phim',
        'reports.view' => 'Xem báo cáo',
        'activity_logs.view' => 'Xem nhật ký hoạt động',
        'tickets.print' => 'In vé',
        'tickets.checkin' => 'Soát vé',
        'users.view' => 'Xem người dùng',
        'users.manage-role' => 'Thay đổi vai trò người dùng',
        'users.manage-status' => 'Thay đổi trạng thái người dùng',
        'roles.view' => 'Xem vai trò và quyền',
        'roles.manage' => 'Thay đổi quyền của vai trò',
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $slug => $name) {
            Permission::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'group' => str($slug)->before('.')->toString()],
            );
        }
    }
}
