@extends('layouts.user')

@section('title', 'Đăng nhập - MovieMate')

@section('content')
<div class="min-h-[calc(100svh-4rem)] md:min-h-[calc(100svh-5rem)] flex">
        <div class="hidden lg:flex w-1/2 relative dark-surface border-r border-white/10 overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-t from-[var(--bg-main)] via-[var(--bg-main)]/40 to-transparent z-10"></div>
            <img src="https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80"
                 alt="Cinema" class="w-full h-full object-cover opacity-50">
            <div class="absolute bottom-20 left-12 right-12 z-20">
                <h2 class="text-3xl font-bold text-white mb-3 leading-tight">Mở ra thế giới điện ảnh<br>của riêng bạn.</h2>
                <p class="surface-muted text-base">Hàng ngàn bộ phim bom tấn và tính năng AI đề xuất thông minh đang chờ đón bạn.</p>
            </div>
        </div>
        <div class="w-full lg:w-1/2 flex items-center justify-center p-6 sm:p-10">
            <div class="w-full max-w-md">
                <div class="mb-8">
                    <h1 class="text-2xl md:text-3xl font-bold app-heading mb-2">Đăng nhập</h1>
                    <p class="app-text-muted text-sm">Chào mừng bạn quay lại với MovieMate.</p>
                </div>
                @if(session('success'))
                    <div class="mb-5 rounded-2xl border border-success/30 bg-success/10 text-success px-4 py-3 text-sm font-semibold">
                        {{ session('success') }}
                    </div>
                @endif
                @if($errors->any())
                    <div class="mb-5 rounded-2xl border border-error/30 bg-error/10 text-error px-4 py-3 text-sm font-semibold">
                        {{ $errors->first() }}
                    </div>
                @endif
