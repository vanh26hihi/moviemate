<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public const PERMISSIONS = [
        'cinemas.view' => 'Xem danh sách chi nhánh',
        'cinemas.manage' => 'Quản lý chi nhánh',
        'cinemas.operations.manage' => 'Quản lý giờ hoạt động chi nhánh',
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
        'room_types.view' => 'Xem danh mục loại phòng',
        'room_types.manage' => 'Quản lý danh mục loại phòng',
        'presentation_formats.view' => 'Xem danh mục định dạng trình chiếu',
        'presentation_formats.manage' => 'Quản lý danh mục định dạng trình chiếu',
        'seats.view' => 'Xem ghế',
        'seats.manage' => 'Quản lý sơ đồ ghế',
        'layout_templates.view' => 'Xem mẫu sơ đồ phòng',
        'layout_templates.manage' => 'Quản lý mẫu sơ đồ phòng',
        'room_layouts.apply_template' => 'Áp dụng mẫu sơ đồ cho phòng',
        'movies.view' => 'Xem phim',
        'movies.create' => 'Tạo phim',
        'movies.update' => 'Sửa phim',
        'movies.lifecycle' => 'Quản lý vòng đời phim',
        'genres.view' => 'Xem thể loại',
        'genres.create' => 'Tạo thể loại',
        'genres.update' => 'Sửa thể loại',
        'genres.delete' => 'Xóa thể loại',
        'showtimes.view' => 'Xem suất chiếu',
        'showtimes.create' => 'Tạo suất chiếu',
        'showtimes.update' => 'Sửa suất chiếu',
        'showtimes.delete' => 'Xóa suất chiếu',
        'pricing.view' => 'Xem bảng giá vé',
        'pricing.manage' => 'Quản lý bảng giá vé',
        'foods.view' => 'Xem danh mục món ăn',
        'foods.create' => 'Tạo món ăn',
        'foods.update' => 'Sửa món ăn',
        'foods.delete' => 'Xóa món ăn',
        'food-orders.view' => 'Xem đơn đồ ăn',
        'food-orders.update-status' => 'Cập nhật trạng thái đơn đồ ăn',
        'bookings.view' => 'Xem đơn đặt vé',
        'bookings.operate' => 'Vận hành đơn đặt vé',
        'counter_sales.view' => 'Xem khu vực bán vé tại quầy',
        'counter_sales.create' => 'Tạo đơn bán vé tại quầy',
        'counter_sales.settle' => 'Xác nhận thu tiền mặt tại quầy',
        'counter_sales.cancel' => 'Hủy đơn giữ chỗ tại quầy',
        'payments.view' => 'Xem thanh toán',
        'payments.reconcile' => 'Đối soát giao dịch',
        'refunds.view' => 'Xem nghĩa vụ hoàn tiền',
        'refunds.resolve' => 'Ghi nhận hoàn tiền thủ công',
        'ticket_deliveries.view' => 'Xem lịch sử gửi tài liệu nhận vé',
        'ticket_deliveries.retry' => 'Gửi lại tài liệu nhận vé',
        'seats.maintenance.view' => 'Xem trạng thái bảo trì ghế',
        'seats.maintenance.update' => 'Cập nhật trạng thái bảo trì ghế',
        'discounts.view' => 'Xem mã giảm giá',
        'discounts.manage' => 'Quản lý mã giảm giá',
        'reviews.view' => 'Xem đánh giá phim',
        'reviews.moderate' => 'Kiểm duyệt đánh giá phim',
        'reports.view' => 'Xem báo cáo',
        'activity_logs.view' => 'Xem nhật ký hoạt động',
        'tickets.print' => 'In vé',
        'tickets.lookup' => 'Tra cứu vé',
        'tickets.print.override' => 'Cho phép in lại vé',
        'ticket_prints.view' => 'Xem lịch sử in vé',
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
