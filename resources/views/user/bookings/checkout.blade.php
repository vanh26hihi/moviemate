@extends('layouts.user')

@section('title', 'Thanh toán - MovieMate')

@php
    $loyaltyPoints = app(\App\Services\LoyaltyPointService::class)->calculate($totalAmount);
    $subtotalAmount = $subtotalAmount ?? $totalAmount;
    $voucherSummary = $voucherSummary ?? ['voucher' => null, 'code' => null, 'discount' => 0, 'total' => $totalAmount];
    $selectedSeatQuery = collect($seatSummaries)->pluck('id')->join(',');
@endphp

@section('content')