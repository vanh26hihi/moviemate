@props(['includeValidation' => true, 'errorBag' => null])

@php
    $normalizeNotification = static fn (mixed $message): string => preg_replace('/\s+/u', ' ', trim((string) $message)) ?? '';
    $statusMessage = session('status');
    if ($statusMessage === 'verification-link-sent') {
        $statusMessage = 'Liên kết xác minh mới đã được gửi đến địa chỉ email của bạn.';
    }

    $channels = [
        'error' => [session('error')],
        'warning' => [session('warning')],
        'success' => [session('success')],
        'info' => [session('info')],
        'status' => [$statusMessage],
    ];
    if ($includeValidation && $errorBag?->any()) {
        $channels['error'] = [...$channels['error'], ...$errorBag->all()];
    }

    $seenNotifications = [];
    $notifications = [];
    foreach ($channels as $type => $messages) {
        foreach (\Illuminate\Support\Arr::flatten($messages) as $message) {
            $normalized = $normalizeNotification($message);
            if ($normalized === '' || isset($seenNotifications[$normalized])) {
                continue;
            }
            $seenNotifications[$normalized] = true;
            $notifications[$type][] = $normalized;
        }
    }

    $notificationStyles = [
        'error' => ['flash-banner-error', 'ph-warning-octagon', 'alert', 'Thông báo lỗi'],
        'warning' => ['flash-banner-warning', 'ph-warning-circle', 'alert', 'Cảnh báo'],
        'success' => ['flash-banner-success', 'ph-check-circle', 'status', 'Thông báo thành công'],
        'info' => ['flash-banner-info', 'ph-info', 'status', 'Thông tin'],
        'status' => ['flash-banner-info', 'ph-info', 'status', 'Trạng thái'],
    ];
@endphp

@if($notifications !== [])
    <div {{ $attributes->merge(['class' => 'mb-5 space-y-3']) }} data-flash-messages aria-label="Thông báo hệ thống">
        @foreach($notifications as $type => $messages)
            @php([$classes, $icon, $role, $label] = $notificationStyles[$type])
            <div class="flex min-w-0 items-start gap-3 rounded-2xl border px-4 py-3 text-sm font-semibold {{ $classes }}" role="{{ $role }}" aria-label="{{ $label }}" data-flash-banner data-flash-type="{{ $type }}">
                <i class="ph-fill {{ $icon }} mt-0.5 shrink-0 text-lg" aria-hidden="true"></i>
                <div class="min-w-0 flex-1 safe-break">
                    @if(count($messages) === 1)
                        <p>{{ $messages[0] }}</p>
                    @else
                        @if($type === 'error')<p class="mb-1 font-extrabold">Vui lòng kiểm tra lại các thông tin bên dưới.</p>@endif
                        <ul class="list-disc space-y-1 pl-5">
                            @foreach($messages as $message)<li>{{ $message }}</li>@endforeach
                        </ul>
                    @endif
                </div>
                <button type="button" class="-mr-1 shrink-0 rounded-lg p-1 opacity-70 transition hover:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-current" aria-label="Đóng thông báo" data-dismiss-flash>
                    <i class="ph ph-x text-base" aria-hidden="true"></i>
                </button>
            </div>
        @endforeach
    </div>
@endif
