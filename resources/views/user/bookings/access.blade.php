<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="referrer" content="no-referrer">
    <title>MovieMate - Xác minh quyền truy cập</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #080a12; color: #fff; font-family: system-ui, sans-serif; }
        main { max-width: 32rem; padding: 2rem; text-align: center; }
        p { color: #b8bdc9; line-height: 1.6; }
    </style>
</head>
<body>
<main>
    <h1>Đang xác minh quyền truy cập booking</h1>
    <p id="status">MovieMate đang thiết lập phiên truy cập an toàn trên thiết bị này.</p>
</main>
<script>
(() => {
    const status = document.getElementById('status');
    const fragment = new URLSearchParams(window.location.hash.slice(1));
    const token = fragment.get('token');
    const destination = fragment.get('destination') === 'success' ? 'success' : 'ticket';
    window.history.replaceState(null, document.title, window.location.pathname);

    if (!token) {
        status.textContent = 'Liên kết truy cập không hợp lệ hoặc đã bị xóa.';
        return;
    }

    fetch(@json(route('user.bookings.access.exchange', $booking)), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({ token, destination }),
    }).then((response) => {
        if (!response.ok) throw new Error('exchange-failed');
        return response.json();
    }).then((payload) => {
        window.location.replace(payload.redirect_url);
    }).catch(() => {
        status.textContent = 'Liên kết truy cập không hợp lệ hoặc đã hết hạn.';
    });
})();
</script>
</body>
</html>
