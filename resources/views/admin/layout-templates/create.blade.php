@extends('layouts.admin')
@section('title', 'Tạo mẫu sơ đồ')
@section('content')
<h1 class="text-2xl font-bold mb-6">Tạo mẫu sơ đồ phòng</h1>
@include('admin.layout-templates.form', ['action' => route('admin.layout-templates.store'), 'method' => 'POST'])
@endsection
