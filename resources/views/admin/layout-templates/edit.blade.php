@extends('layouts.admin')
@section('title', 'Chỉnh sửa mẫu sơ đồ')
@section('content')
<h1 class="text-2xl font-bold mb-6">Chỉnh sửa {{ $template->name }}</h1>
@include('admin.layout-templates.form', ['action' => route('admin.layout-templates.update', $template), 'method' => 'PUT'])
@endsection
