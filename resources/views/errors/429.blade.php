@extends('layouts.app')
@section('title', 'Thao tác quá nhanh - MovieMate')
@section('content')
<section class="cinema-surface flex min-h-[70vh] items-center py-12"><div class="mx-auto w-full max-w-3xl px-4 sm:px-6">
    <x-empty-state title="Thao tác quá nhanh" description="Vui lòng chờ một lát rồi thử lại." icon="ph-hourglass-medium">
        <button type="button" class="btn-primary" onclick="window.location.reload()"><i class="ph ph-arrow-clockwise"></i> Thử lại</button>
    </x-empty-state>
</div></section>
@endsection
