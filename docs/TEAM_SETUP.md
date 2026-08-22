# MovieMate — Thiết lập môi trường cho thành viên nhóm

Tài liệu này dùng cho môi trường phát triển hoặc demo cục bộ. Không dùng chung `.env`, khóa thanh toán, SMTP hay URL tunnel giữa các thành viên.

## Yêu cầu

- PHP 8.3 trở lên cùng các extension Laravel/MySQL thông dụng.
- Composer 2.
- Node.js/npm tương thích Vite 8.
- MySQL 8 trở lên, khuyến nghị MySQL 8.4.
- Git.

## Cài đặt mã nguồn

```bash
git clone https://github.com/vanh26hihi/moviemate.git
cd moviemate
composer install
npm install
```

Tạo cấu hình cục bộ và khóa ứng dụng:

```powershell
Copy-Item .env.example .env
php artisan key:generate
```

Trên macOS/Linux, thay lệnh sao chép bằng `cp .env.example .env`.

Mở `.env` chưa được Git theo dõi và cấu hình database MySQL riêng của bạn. Mặc định an toàn trong `.env.example` là `APP_URL=http://127.0.0.1:8000`; không commit `.env` hay thay URL này bằng tunnel tạm trong file mẫu.

## Khởi tạo database

Tạo database UTF-8:

```sql
CREATE DATABASE moviemate CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Migrations và seeders là authority duy nhất cho schema/demo data. Không dùng SQL dump tĩnh vì dump có thể lệch khỏi business invariants đã được freeze.

```bash
php artisan migrate:fresh --seed
```

Điền host, port, username và password tương ứng vào `.env` nếu MySQL của bạn không dùng cấu hình mặc định.

Seeder demo chỉ được phép chạy khi `APP_ENV=local` hoặc `testing`. Danh sách tài khoản và mật khẩu demo cố ý công khai nằm tại `docs/DEMO_ACCOUNTS.md`; không dùng các tài khoản này trong production.

## Hoàn tất thiết lập

```bash
php artisan storage:link
php artisan optimize:clear
```

Nếu symlink đã tồn tại, không cần tạo lại.

## Chạy môi trường phát triển

Terminal 1 — Laravel:

```bash
php artisan serve
```

Terminal 2 — Vite:

```bash
npm run dev
```

Terminal 3 — scheduler bắt buộc cho hết hạn booking, reconciliation và ticket outbox:

```bash
php artisan schedule:work
```

`.env.example` dùng `QUEUE_CONNECTION=database`. Khi tính năng tạo queued job bất đồng bộ, chạy thêm worker:

```bash
php artisan queue:work --tries=3 --timeout=120
```

Luồng ticket outbox hiện được scheduler xử lý đồng bộ; `QUEUE_CONNECTION=sync` không cần queue worker, nhưng scheduler vẫn phải chạy.

## Dịch vụ bên ngoài

Mỗi thành viên phải dùng credential cục bộ của riêng mình cho SMTP, VNPAY, payOS và ZaloPay. Các biến cần thiết đã có trong `.env.example` với giá trị trống hoặc sandbox an toàn. Không gửi credential qua Git, tài liệu, ảnh chụp hoặc SQL dump.

Không có credential thật, ứng dụng vẫn có thể dùng `MAIL_MAILER=log` và duyệt các luồng demo không gọi provider thật. Thanh toán tích hợp thực tế cần tài khoản sandbox được cấp riêng.

Để thử callback/webhook qua HTTPS, đặt `APP_URL` và callback URL trong `.env` thành URL tunnel hiện tại của chính bạn, sau đó chạy:

```bash
php artisan optimize:clear
```

Khởi động lại web server, scheduler và queue worker sau khi đổi cấu hình. Không commit domain ngrok hoặc tunnel tạm.

## Kiểm tra nhanh

```bash
php artisan migrate --pretend
php artisan test
npm run build
```

`migrate --pretend` phải báo không còn migration khi database đã được nhập hoặc migrate đầy đủ.
