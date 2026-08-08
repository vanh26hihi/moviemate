@extends('layouts.app')
@section('title', 'Không thể xử lý yêu cầu - MovieMate')
@section('content')
<section class="cinema-surface flex min-h-[70vh] items-center py-12"><div class="mx-auto w-full max-w-3xl px-4 sm:px-6">
    <x-empty-state title="Không thể xử lý yêu cầu" description="Thông tin gửi lên chưa hợp lệ. Vui lòng quay lại, kiểm tra các trường và thử lại." icon="ph-warning">
        <button type="button" class="btn-primary" onclick="window.history.back()"><i class="ph ph-arrow-left"></i> Quay lại</button>
    </x-empty-state>
</div></section>
@endsection
