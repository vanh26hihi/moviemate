@extends('layouts.user')

@section('title', 'Lịch sử đặt vé - MovieMate')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <aside class="lg:col-span-1">
            <div class="app-card border app-border rounded-3xl p-6 flex flex-col items-center text-center sticky top-24">
                <div class="w-20 h-20 rounded-full overflow-hidden mb-3 border-2 border-brand-start/30">
                    <img src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name) }}&background=FF3D57&color=fff&size=128" alt="Avatar" class="w-full h-full object-cover">
                </div>
                <h2 class="font-bold app-text mb-1">{{ Auth::user()->name }}</h2>
                <p class="text-xs text-ai-start font-bold mb-5">Hạng {{ Auth::user()->role->name ?? 'Khách' }}</p>

                <div class="w-full rounded-2xl border border-ai-start/30 bg-ai-start/10 px-4 py-3 mb-4 text-left">
                    <p class="text-xs app-muted">Thành viên {{ Auth::user()->membership_tier }}</p>
                    <p class="text-2xl font-extrabold text-ai-start">{{ number_format(Auth::user()->loyalty_points, 0, ',', '.') }}</p>
                    <p class="text-xs app-muted">điểm khả dụng</p>
                </div>