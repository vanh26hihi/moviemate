@extends('layouts.app')
@section('title', 'Phiên làm việc đã hết hạn - MovieMate')
@section('content')
<section class="cinema-surface flex min-h-[70vh] items-center py-12"><div class="mx-auto w-full max-w-3xl px-4 sm:px-6">
    <x-empty-state title="Phiên làm việc đã hết hạn" description="Vui lòng tải lại trang và thử lại để bảo đảm yêu cầu được gửi an toàn." icon="ph-clock-counter-clockwise">
        <button type="button" class="btn-primary" onclick="window.location.reload()"><i class="ph ph-arrow-clockwise"></i> Tải lại trang</button>
        <a href="{{ route('home') }}" class="btn-secondary">Về trang chủ</a>
    </x-empty-state>
</div></section>
@endsection
