@extends('layouts.app')
@section('title', 'Hệ thống đang bảo trì - MovieMate')
@section('content')
<section class="cinema-surface flex min-h-[70vh] items-center py-12"><div class="mx-auto w-full max-w-3xl px-4 sm:px-6">
    <x-empty-state title="Hệ thống đang bảo trì" description="MovieMate đang được nâng cấp. Vui lòng quay lại sau ít phút." icon="ph-wrench">
        <a href="{{ route('home') }}" class="btn-primary"><i class="ph ph-house"></i> Về trang chủ</a>
    </x-empty-state>
</div></section>
@endsection
