<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <title>MovieMate - Chuyển đến booking</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #080a12; color: #fff; font-family: system-ui, sans-serif; }
    </style>
</head>
<body>
<main aria-live="polite">
    <p>Đang mở booking trong phiên truy cập an toàn…</p>
    <noscript>Trình duyệt cần bật JavaScript để hoàn tất bước xác minh quyền truy cập.</noscript>
</main>
<script>
(() => {
    const accessUrl = @json($accessUrl);
    const token = @json($guestAccessToken);
    const destination = @json($destination);
    const fragment = new URLSearchParams({ token, destination });
    window.location.replace(accessUrl + '#' + fragment.toString());
})();
</script>
</body>
</html>
