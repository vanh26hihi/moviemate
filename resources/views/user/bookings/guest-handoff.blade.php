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
<p>Đang mở booking an toàn…</p>
<script>
(() => {
    const accessUrl = @json($accessUrl);
    const token = @json($guestAccessToken);
    const destination = @json($destination);
    window.location.replace(accessUrl + '#token=' + encodeURIComponent(token) + '&destination=' + encodeURIComponent(destination));
})();
</script>
</body>
</html>
