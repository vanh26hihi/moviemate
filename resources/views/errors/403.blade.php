@extends('layouts.app')
@section('title', 'Không có quyền truy cập - MovieMate')
@section('content')
<section class="cinema-surface flex min-h-[70vh] items-center py-12"><div class="mx-auto w-full max-w-3xl px-4 sm:px-6">
    <x-empty-state title="Không có quyền truy cập" description="Bạn không có quyền truy cập nội dung này." icon="ph-lock-key">
        <a href="{{ route('home') }}" class="btn-primary"><i class="ph ph-house"></i> Về trang chủ</a>
    </x-empty-state>
</div></section>
@endsection
