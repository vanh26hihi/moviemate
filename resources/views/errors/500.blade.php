@extends('layouts.app')

@section('title', 'Hệ thống tạm gián đoạn - MovieMate')

@section('content')
<section class="cinema-surface flex min-h-[70vh] items-center py-12">
    <div class="mx-auto w-full max-w-3xl px-4 sm:px-6">
        <x-empty-state
            title="Hệ thống tạm gián đoạn"
            description="MovieMate chưa thể xử lý yêu cầu lúc này. Vui lòng quay lại sau."
            icon="ph-warning-circle"
        >
            <a href="{{ route('home') }}" class="btn-primary"><i class="ph ph-arrow-clockwise"></i> Thử lại</a>
        </x-empty-state>
    </div>
</section>
@endsection
