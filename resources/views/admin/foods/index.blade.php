@extends('layouts.app')

@section('title', 'Thực đơn rạp - MovieMate')

@section('content')

<style>
    /* =========================================================
       MOVIEMATE FOOD PAGE
       ========================================================= */

    :root {
        --food-bg: #080b12;
        --food-card: #111621;
        --food-card-hover: #171e2c;
        --food-border: rgba(255, 255, 255, 0.08);
        --food-text: #ffffff;
        --food-muted: #9ca3af;
        --food-primary: #ef4444;
        --food-primary-hover: #dc2626;
        --food-yellow: #f59e0b;
        --food-green: #22c55e;
        --food-blue: #3b82f6;
        --food-radius: 18px;
        --food-shadow: 0 15px 40px rgba(0, 0, 0, 0.25);
    }

    * {
        box-sizing: border-box;
    }

    .food-page {
        min-height: 100vh;
        background:
            radial-gradient(
                circle at 10% 0%,
                rgba(239, 68, 68, 0.12),
                transparent 30%
            ),
            radial-gradient(
                circle at 90% 10%,
                rgba(59, 130, 246, 0.10),
                transparent 30%
            ),
            var(--food-bg);

        color: var(--food-text);
        padding-bottom: 100px;
    }

    .food-container {
        width: min(1400px, calc(100% - 40px));
        margin: 0 auto;
    }

    /* =========================================================
       HERO
       ========================================================= */

    .food-hero {
        position: relative;
        min-height: 420px;
        display: flex;
        align-items: center;
        overflow: hidden;
        margin-bottom: 45px;
        border-bottom: 1px solid var(--food-border);
    }

    .food-hero::before {
        content: "";
        position: absolute;
        inset: 0;

        background:
            linear-gradient(
                90deg,
                rgba(8, 11, 18, 0.98) 0%,
                rgba(8, 11, 18, 0.85) 40%,
                rgba(8, 11, 18, 0.35) 75%,
                rgba(8, 11, 18, 0.90) 100%
            );
        z-index: 1;
    }

    .food-hero-bg {
        position: absolute;
        inset: 0;

        background-image:
            url("https://images.unsplash.com/photo-1585647347384-2593bc35786b?auto=format&fit=crop&w=1800&q=85");

        background-position: center;
        background-size: cover;

        filter: saturate(1.15);
    }

    .food-hero-content {
        position: relative;
        z-index: 2;
        width: 100%;
        padding: 70px 0;
    }

    .food-hero-content-inner {
        max-width: 720px;
    }

    .food-small-label {
        display: inline-flex;
        align-items: center;
        gap: 8px;

        padding: 8px 14px;
        border-radius: 999px;

        background: rgba(239, 68, 68, 0.14);
        border: 1px solid rgba(239, 68, 68, 0.35);

        color: #fca5a5;
        font-size: 13px;
        font-weight: 700;

        margin-bottom: 18px;
    }

    .food-small-label i {
        font-size: 14px;
    }

    .food-hero-title {
        margin: 0 0 18px;

        font-size: clamp(38px, 6vw, 70px);
        line-height: 1.02;

        font-weight: 900;
        letter-spacing: -2px;

        color: #ffffff;
    }

    .food-hero-title span {
        color: #ef4444;
    }

    .food-hero-description {
        margin: 0;
        max-width: 620px;

        color: #d1d5db;
        font-size: 17px;
        line-height: 1.7;
    }

    .food-hero-actions {
        display: flex;
        gap: 12px;
        margin-top: 30px;
        flex-wrap: wrap;
    }

    .food-hero-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 9px;

        min-height: 48px;
        padding: 0 20px;

        border-radius: 12px;

        font-weight: 700;
        text-decoration: none;

        transition:
            transform 0.25s ease,
            box-shadow 0.25s ease,
            background 0.25s ease;
    }

    .food-hero-button:hover {
        transform: translateY(-2px);
    }

    .food-hero-button.primary {
        background: var(--food-primary);
        color: white;

        box-shadow:
            0 10px 25px rgba(239, 68, 68, 0.25);
    }

    .food-hero-button.primary:hover {
        background: var(--food-primary-hover);
        color: white;
    }

    .food-hero-button.secondary {
        background: rgba(255, 255, 255, 0.08);
        color: white;

        border: 1px solid rgba(255, 255, 255, 0.12);

        backdrop-filter: blur(10px);
    }

    /* =========================================================
       SECTION HEADER
       ========================================================= */

    .food-section {
        margin-bottom: 50px;
    }

    .food-section-header {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 20px;

        margin-bottom: 24px;
    }

    .food-section-heading {
        margin: 0;
        font-size: 28px;
        font-weight: 850;
        letter-spacing: -0.5px;
    }

    .food-section-description {
        margin: 7px 0 0;
        color: var(--food-muted);
        font-size: 14px;
    }

    .food-view-all {
        display: inline-flex;
        align-items: center;
        gap: 7px;

        color: #fca5a5;
        text-decoration: none;

        font-size: 14px;
        font-weight: 700;

        white-space: nowrap;
    }

    .food-view-all:hover {
        color: white;
    }

    /* =========================================================
       FEATURED FOODS
       ========================================================= */

    .featured-grid {
        display: grid;
        grid-template-columns:
            repeat(4, minmax(0, 1fr));
        gap: 18px;
    }

    .featured-card {
        position: relative;
        min-height: 250px;

        overflow: hidden;

        border-radius: var(--food-radius);

        background: var(--food-card);

        border: 1px solid var(--food-border);

        box-shadow: var(--food-shadow);

        text-decoration: none;
        color: white;

        transition:
            transform 0.3s ease,
            border-color 0.3s ease;
    }

    .featured-card:hover {
        transform: translateY(-6px);
        border-color: rgba(239, 68, 68, 0.4);
        color: white;
    }

    .featured-card-image {
        position: absolute;
        inset: 0;
    }

    .featured-card-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;

        transition:
            transform 0.5s ease;
    }

    .featured-card:hover img {
        transform: scale(1.08);
    }

    .featured-card-overlay {
        position: absolute;
        inset: 0;

        background:
            linear-gradient(
                180deg,
                transparent 25%,
                rgba(0, 0, 0, 0.82) 100%
            );
    }

    .featured-card-content {
        position: absolute;
        left: 18px;
        right: 18px;
        bottom: 17px;
        z-index: 2;
    }

    .featured-card-category {
        display: inline-block;

        padding: 5px 9px;

        border-radius: 999px;

        background: rgba(239, 68, 68, 0.85);

        font-size: 11px;
        font-weight: 800;

        margin-bottom: 9px;
    }

    .featured-card-name {
        margin: 0 0 6px;

        font-size: 18px;
        font-weight: 800;
    }

    .featured-card-price {
        font-size: 16px;
        color: #fca5a5;
        font-weight: 800;
    }

    /* =========================================================
       SEARCH
       ========================================================= */

    .food-toolbar {
        display: grid;
        grid-template-columns:
            minmax(250px, 1fr)
            auto;

        gap: 15px;

        margin-bottom: 22px;
    }

    .food-search {
        position: relative;
    }

    .food-search-icon {
        position: absolute;
        left: 17px;
        top: 50%;
        transform: translateY(-50%);

        color: #6b7280;
        pointer-events: none;
    }

    .food-search input {
        width: 100%;
        height: 52px;

        padding:
            0 45px
            0 48px;

        border-radius: 14px;

        border: 1px solid var(--food-border);

        background: rgba(17, 22, 33, 0.9);

        color: white;

        outline: none;

        font-size: 14px;

        transition:
            border-color 0.2s ease,
            box-shadow 0.2s ease;
    }

    .food-search input::placeholder {
        color: #6b7280;
    }

    .food-search input:focus {
        border-color: rgba(239, 68, 68, 0.7);

        box-shadow:
            0 0 0 4px rgba(239, 68, 68, 0.10);
    }

    .food-search-clear {
        position: absolute;
        right: 14px;
        top: 50%;
        transform: translateY(-50%);

        width: 28px;
        height: 28px;

        border: 0;
        border-radius: 50%;

        background: rgba(255, 255, 255, 0.08);

        color: #d1d5db;

        cursor: pointer;

        display: none;

        align-items: center;
        justify-content: center;
    }

    .food-search-clear:hover {
        background: rgba(255, 255, 255, 0.15);
    }

    .food-sort {
        height: 52px;

        min-width: 190px;

        padding: 0 42px 0 15px;

        border-radius: 14px;

        border: 1px solid var(--food-border);

        background:
            var(--food-card);

        color: white;

        outline: none;

        font-size: 14px;

        cursor: pointer;
    }

    .food-sort option {
        background: #111621;
        color: white;
    }

    /* =========================================================
       CATEGORY FILTER
       ========================================================= */

    .category-list {
        display: flex;
        gap: 10px;

        overflow-x: auto;

        padding-bottom: 7px;

        scrollbar-width: thin;
    }

    .category-list::-webkit-scrollbar {
        height: 4px;
    }

    .category-list::-webkit-scrollbar-thumb {
        background: #374151;
        border-radius: 10px;
    }

    .category-button {
        flex: 0 0 auto;

        display: inline-flex;
        align-items: center;
        gap: 8px;

        min-height: 42px;

        padding: 0 16px;

        border-radius: 999px;

        border: 1px solid var(--food-border);

        background: rgba(255, 255, 255, 0.04);

        color: #d1d5db;

        text-decoration: none;

        font-size: 13px;
        font-weight: 700;

        transition:
            background 0.2s ease,
            color 0.2s ease,
            border-color 0.2s ease;
    }

    .category-button:hover {
        color: white;
        border-color: rgba(239, 68, 68, 0.45);
        background: rgba(239, 68, 68, 0.08);
    }

    .category-button.active {
        background: var(--food-primary);
        border-color: var(--food-primary);

        color: white;

        box-shadow:
            0 8px 20px rgba(239, 68, 68, 0.18);
    }

    .category-count {
        display: inline-flex;

        min-width: 22px;
        height: 22px;

        align-items: center;
        justify-content: center;

        padding: 0 6px;

        border-radius: 999px;

        background: rgba(255, 255, 255, 0.10);

        font-size: 11px;
    }

    .category-button.active .category-count {
        background: rgba(255, 255, 255, 0.20);
    }

    /* =========================================================
       FOOD GRID
       ========================================================= */

    .food-grid {
        display: grid;

        grid-template-columns:
            repeat(4, minmax(0, 1fr));

        gap: 22px;
    }

    .food-card {
        position: relative;

        display: flex;
        flex-direction: column;

        min-width: 0;

        overflow: hidden;

        border-radius: var(--food-radius);

        background: var(--food-card);

        border: 1px solid var(--food-border);

        box-shadow:
            0 12px 35px rgba(0, 0, 0, 0.15);

        transition:
            transform 0.3s ease,
            border-color 0.3s ease,
            box-shadow 0.3s ease;
    }

    .food-card:hover {
        transform: translateY(-6px);

        border-color:
            rgba(239, 68, 68, 0.35);

        box-shadow:
            0 20px 45px rgba(0, 0, 0, 0.30);
    }

    /* =========================================================
       FOOD IMAGE
       ========================================================= */

    .food-card-image {
        position: relative;

        width: 100%;
        height: 245px;

        overflow: hidden;

        background: #1a202c;
    }

    .food-card-image img {
        width: 100%;
        height: 100%;

        object-fit: cover;

        transition:
            transform 0.45s ease;
    }

    .food-card:hover
    .food-card-image img {
        transform: scale(1.07);
    }

    .food-image-overlay {
        position: absolute;
        inset: 0;

        background:
            linear-gradient(
                180deg,
                rgba(0, 0, 0, 0.20),
                transparent 35%,
                rgba(0, 0, 0, 0.20)
            );

        pointer-events: none;
    }

    /* =========================================================
       BADGES
       ========================================================= */

    .food-badges {
        position: absolute;

        top: 13px;
        left: 13px;

        display: flex;
        flex-direction: column;

        align-items: flex-start;

        gap: 7px;

        z-index: 3;
    }

    .food-badge {
        display: inline-flex;

        align-items: center;
        gap: 5px;

        min-height: 27px;

        padding: 0 9px;

        border-radius: 999px;

        font-size: 10px;
        font-weight: 850;

        text-transform: uppercase;
        letter-spacing: 0.2px;

        backdrop-filter: blur(8px);
    }

    .food-badge.featured {
        background: rgba(245, 158, 11, 0.92);
        color: white;
    }

    .food-badge.sold {
        background: rgba(239, 68, 68, 0.92);
        color: white;
    }

    .food-badge.available {
        background: rgba(34, 197, 94, 0.92);
        color: white;
    }

    /* =========================================================
       FAVORITE
       ========================================================= */

    .food-favorite {
        position: absolute;

        top: 13px;
        right: 13px;

        width: 40px;
        height: 40px;

        border-radius: 50%;

        border: 1px solid rgba(255, 255, 255, 0.15);

        background: rgba(0, 0, 0, 0.35);

        backdrop-filter: blur(10px);

        color: white;

        cursor: pointer;

        display: flex;
        align-items: center;
        justify-content: center;

        z-index: 5;

        transition:
            background 0.2s ease,
            transform 0.2s ease,
            color 0.2s ease;
    }

    .food-favorite:hover {
        transform: scale(1.08);

        background: rgba(239, 68, 68, 0.80);
    }

    .food-favorite.active {
        color: #ef4444;
    }

    /* =========================================================
       FOOD BODY
       ========================================================= */

    .food-card-body {
        display: flex;
        flex-direction: column;

        flex: 1;

        padding: 18px;
    }

    .food-category {
        color: #f87171;

        font-size: 11px;
        font-weight: 800;

        text-transform: uppercase;

        letter-spacing: 0.6px;

        margin-bottom: 7px;
    }

    .food-name {
        margin: 0;

        min-height: 48px;

        font-size: 17px;
        line-height: 1.4;

        font-weight: 800;

        color: white;
    }

    .food-name a {
        color: inherit;
        text-decoration: none;
    }

    .food-name a:hover {
        color: #fca5a5;
    }

    .food-description {
        display: -webkit-box;

        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;

        overflow: hidden;

        margin: 8px 0 15px;

        min-height: 40px;

        color: #9ca3af;

        font-size: 12px;
        line-height: 1.65;
    }

    /* =========================================================
       PRICE
       ========================================================= */

    .food-price-row {
        display: flex;

        align-items: center;
        justify-content: space-between;

        gap: 10px;

        margin-bottom: 15px;
    }

    .food-price {
        font-size: 20px;
        font-weight: 900;

        color: #ffffff;
    }

    .food-price-unit {
        color: #9ca3af;

        font-size: 11px;
        font-weight: 500;
    }

    .food-sold {
        display: inline-flex;

        align-items: center;
        gap: 4px;

        color: #9ca3af;

        font-size: 11px;
    }

    /* =========================================================
       QUANTITY
       ========================================================= */

    .food-actions {
        display: flex;

        align-items: center;

        gap: 9px;
    }

    .quantity-control {
        display: flex;

        align-items: center;

        height: 45px;

        border-radius: 12px;

        border: 1px solid var(--food-border);

        background: rgba(255, 255, 255, 0.035);

        overflow: hidden;
    }

    .quantity-button {
        width: 38px;
        height: 100%;

        border: 0;

        background: transparent;

        color: #d1d5db;

        cursor: pointer;

        font-size: 16px;

        transition:
            background 0.2s ease,
            color 0.2s ease;
    }

    .quantity-button:hover {
        background: rgba(255, 255, 255, 0.08);

        color: white;
    }

    .quantity-value {
        width: 32px;

        text-align: center;

        font-size: 13px;

        font-weight: 800;

        color: white;
    }

    /* =========================================================
       ADD BUTTON
       ========================================================= */

    .add-food-button {
        flex: 1;

        min-width: 0;

        height: 45px;

        border: 0;

        border-radius: 12px;

        background:
            linear-gradient(
                135deg,
                #ef4444,
                #dc2626
            );

        color: white;

        font-size: 12px;

        font-weight: 800;

        cursor: pointer;

        display: inline-flex;

        align-items: center;
        justify-content: center;

        gap: 7px;

        box-shadow:
            0 7px 20px rgba(239, 68, 68, 0.16);

        transition:
            transform 0.2s ease,
            box-shadow 0.2s ease,
            opacity 0.2s ease;
    }

    .add-food-button:hover {
        transform: translateY(-2px);

        box-shadow:
            0 10px 25px rgba(239, 68, 68, 0.28);
    }

    .add-food-button:active {
        transform: translateY(0);
    }

    .add-food-button.loading {
        pointer-events: none;
        opacity: 0.75;
    }

    .add-food-button.disabled {
        background: #374151;

        color: #9ca3af;

        box-shadow: none;

        cursor: not-allowed;
    }

    /* =========================================================
       EMPTY STATE
       ========================================================= */

    .food-empty {
        padding: 75px 20px;

        text-align: center;

        border: 1px dashed
            rgba(255, 255, 255, 0.13);

        border-radius: var(--food-radius);

        background:
            rgba(255, 255, 255, 0.02);
    }

    .food-empty-icon {
        width: 75px;
        height: 75px;

        margin: 0 auto 18px;

        border-radius: 50%;

        display: flex;

        align-items: center;
        justify-content: center;

        background:
            rgba(239, 68, 68, 0.10);

        color: #f87171;

        font-size: 30px;
    }

    .food-empty h3 {
        margin: 0 0 8px;

        font-size: 20px;
        font-weight: 800;
    }

    .food-empty p {
        margin: 0;

        color: #9ca3af;

        font-size: 14px;
    }

    /* =========================================================
       PAGINATION
       ========================================================= */

    .food-pagination {
        display: flex;

        align-items: center;
        justify-content: center;

        margin-top: 35px;
    }

    .food-pagination nav {
        display: flex;
        justify-content: center;
    }

    .food-pagination .pagination {
        gap: 6px;
        margin: 0;
    }

    .food-pagination .page-item .page-link {
        width: 40px;
        height: 40px;

        display: flex;
        align-items: center;
        justify-content: center;

        border-radius: 10px !important;

        background: #111621;

        border: 1px solid var(--food-border);

        color: #d1d5db;

        font-size: 13px;
    }

    .food-pagination
    .page-item.active
    .page-link {
        background: var(--food-primary);

        border-color: var(--food-primary);

        color: white;
    }

    .food-pagination
    .page-item
    .page-link:hover {
        background: #1f2937;

        color: white;
    }

    /* =========================================================
       FLOATING CART
       ========================================================= */

    .floating-cart {
        position: fixed;

        right: 25px;
        bottom: 25px;

        z-index: 999;

        display: flex;

        align-items: center;

        gap: 10px;

        min-height: 58px;

        padding: 7px 17px 7px 8px;

        border-radius: 999px;

        background:
            linear-gradient(
                135deg,
                #ef4444,
                #b91c1c
            );

        color: white;

        text-decoration: none;

        box-shadow:
            0 18px 40px
            rgba(0, 0, 0, 0.35);

        transition:
            transform 0.25s ease,
            box-shadow 0.25s ease;
    }

    .floating-cart:hover {
        color: white;

        transform:
            translateY(-4px);

        box-shadow:
            0 22px 50px
            rgba(239, 68, 68, 0.30);
    }

    .floating-cart-icon {
        width: 44px;
        height: 44px;

        display: flex;
        align-items: center;
        justify-content: center;

        border-radius: 50%;

        background:
            rgba(255, 255, 255, 0.15);

        font-size: 19px;
    }

    .floating-cart-text {
        display: flex;

        flex-direction: column;

        line-height: 1.25;
    }

    .floating-cart-label {
        font-size: 10px;

        text-transform: uppercase;

        opacity: 0.75;

        font-weight: 700;
    }

    .floating-cart-total {
        font-size: 13px;

        font-weight: 900;
    }

    .floating-cart-count {
        position: absolute;

        top: -5px;
        right: -3px;

        min-width: 23px;
        height: 23px;

        padding: 0 6px;

        display: flex;
        align-items: center;
        justify-content: center;

        border-radius: 999px;

        background: white;

        color: #dc2626;

        font-size: 10px;

        font-weight: 900;

        border: 2px solid #dc2626;
    }

    .floating-cart.bump {
        animation: cartBump 0.45s ease;
    }

    @keyframes cartBump {

        0% {
            transform: scale(1);
        }

        35% {
            transform: scale(1.10);
        }

        70% {
            transform: scale(0.96);
        }

        100% {
            transform: scale(1);
        }
    }

    /* =========================================================
       TOAST
       ========================================================= */

    .food-toast-container {
        position: fixed;

        top: 25px;
        right: 25px;

        z-index: 2000;

        display: flex;

        flex-direction: column;

        gap: 10px;

        width: min(
            380px,
            calc(100vw - 30px)
        );
    }

    .food-toast {
        display: flex;

        align-items: flex-start;

        gap: 12px;

        padding: 15px;

        border-radius: 14px;

        background:
            rgba(17, 24, 39, 0.96);

        border: 1px solid
            rgba(255, 255, 255, 0.10);

        box-shadow:
            0 18px 40px
            rgba(0, 0, 0, 0.30);

        backdrop-filter: blur(14px);

        animation:
            toastIn 0.35s ease;
    }

    .food-toast.success {
        border-left:
            4px solid #22c55e;
    }

    .food-toast.error {
        border-left:
            4px solid #ef4444;
    }

    .food-toast-icon {
        width: 32px;
        height: 32px;

        flex: 0 0 auto;

        display: flex;

        align-items: center;
        justify-content: center;

        border-radius: 50%;

        background:
            rgba(34, 197, 94, 0.12);

        color: #4ade80;
    }

    .food-toast.error
    .food-toast-icon {
        background:
            rgba(239, 68, 68, 0.12);

        color: #f87171;
    }

    .food-toast-content {
        flex: 1;
    }

    .food-toast-title {
        margin: 0 0 3px;

        color: white;

        font-size: 13px;

        font-weight: 800;
    }

    .food-toast-message {
        margin: 0;

        color: #9ca3af;

        font-size: 12px;

        line-height: 1.5;
    }

    .food-toast-close {
        border: 0;

        background: transparent;

        color: #6b7280;

        cursor: pointer;

        font-size: 16px;
    }

    @keyframes toastIn {

        from {
            opacity: 0;
            transform:
                translateX(30px);
        }

        to {
            opacity: 1;
            transform:
                translateX(0);
        }
    }

    @keyframes toastOut {

        from {
            opacity: 1;
            transform:
                translateX(0);
        }

        to {
            opacity: 0;
            transform:
                translateX(30px);
        }
    }

    /* =========================================================
       CART FLY ANIMATION
       ========================================================= */

    .food-flying-image {
        position: fixed;

        z-index: 3000;

        width: 65px;
        height: 65px;

        border-radius: 50%;

        object-fit: cover;

        pointer-events: none;

        box-shadow:
            0 10px 30px
            rgba(0, 0, 0, 0.35);

        transition:
            left 0.65s cubic-bezier(
                0.65,
                0,
                0.35,
                1
            ),
            top 0.65s cubic-bezier(
                0.65,
                0,
                0.35,
                1
            ),
            width 0.65s ease,
            height 0.65s ease,
            opacity 0.65s ease;
    }

    /* =========================================================
       RESULT INFO
       ========================================================= */

    .food-result-info {
        display: flex;

        align-items: center;
        justify-content: space-between;

        gap: 15px;

        margin-bottom: 17px;
    }

    .food-result-count {
        color: #9ca3af;

        font-size: 13px;
    }

    .food-result-count strong {
        color: white;
    }

    .food-reset {
        display: inline-flex;

        align-items: center;

        gap: 6px;

        color: #fca5a5;

        font-size: 12px;

        font-weight: 700;

        text-decoration: none;
    }

    .food-reset:hover {
        color: white;
    }

    /* =========================================================
       RESPONSIVE
       ========================================================= */

    @media (max-width: 1200px) {

        .food-grid {
            grid-template-columns:
                repeat(3, minmax(0, 1fr));
        }

        .featured-grid {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 900px) {

        .food-container {
            width:
                min(
                    100% - 28px,
                    1400px
                );
        }

        .food-hero {
            min-height: 390px;
        }

        .food-grid {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }

        .food-toolbar {
            grid-template-columns:
                1fr;
        }

        .food-sort {
            width: 100%;
        }
    }

    @media (max-width: 600px) {

        .food-page {
            padding-bottom: 90px;
        }

        .food-container {
            width:
                calc(100% - 20px);
        }

        .food-hero {
            min-height: 450px;
        }

        .food-hero-content {
            padding: 45px 0;
        }

        .food-hero-title {
            font-size: 42px;

            letter-spacing: -1.5px;
        }

        .food-hero-description {
            font-size: 14px;
        }

        .food-section {
            margin-bottom: 35px;
        }

        .food-section-header {
            align-items: flex-start;

            flex-direction: column;

            margin-bottom: 18px;
        }

        .food-section-heading {
            font-size: 23px;
        }

        .featured-grid {
            grid-template-columns:
                1fr 1fr;

            gap: 10px;
        }

        .featured-card {
            min-height: 190px;
        }

        .featured-card-content {
            left: 12px;
            right: 12px;
            bottom: 12px;
        }

        .featured-card-name {
            font-size: 14px;
        }

        .featured-card-price {
            font-size: 13px;
        }

        .food-grid {
            grid-template-columns:
                1fr 1fr;

            gap: 10px;
        }

        .food-card-image {
            height: 170px;
        }

        .food-card-body {
            padding: 12px;
        }

        .food-category {
            font-size: 9px;
        }

        .food-name {
            min-height: 40px;

            font-size: 14px;
        }

        .food-description {
            min-height: 35px;

            margin: 5px 0 10px;

            font-size: 10px;
        }

        .food-price {
            font-size: 15px;
        }

        .food-sold {
            display: none;
        }

        .food-price-row {
            margin-bottom: 10px;
        }

        .food-actions {
            gap: 5px;
        }

        .quantity-control {
            height: 38px;
        }

        .quantity-button {
            width: 28px;
        }

        .quantity-value {
            width: 22px;
        }

        .add-food-button {
            height: 38px;

            font-size: 10px;

            padding: 0 7px;
        }

        .add-food-button span {
            display: none;
        }

        .food-favorite {
            width: 33px;
            height: 33px;
        }

        .food-badges {
            top: 8px;
            left: 8px;
        }

        .food-badge {
            min-height: 22px;

            padding: 0 7px;

            font-size: 8px;
        }

        .floating-cart {
            right: 12px;
            bottom: 12px;

            min-height: 52px;
        }

        .floating-cart-icon {
            width: 38px;
            height: 38px;
        }

        .floating-cart-text {
            display: none;
        }

        .food-toast-container {
            top: 12px;
            right: 10px;
            left: 10px;

            width: auto;
        }
    }

    @media (max-width: 380px) {

        .food-grid {
            grid-template-columns:
                1fr;
        }

        .food-card-image {
            height: 210px;
        }

        .food-description {
            min-height: auto;
        }

        .featured-grid {
            grid-template-columns:
                1fr;
        }
    }
</style>


<div class="food-page">

    {{-- =====================================================
         HERO
         ====================================================== --}}

    <section class="food-hero">

        <div class="food-hero-bg"></div>

        <div class="food-hero-content">

            <div class="food-container">

                <div class="food-hero-content-inner">

                    <div class="food-small-label">
                        <i class="fa-solid fa-film"></i>

                        MOVIEMATE FOOD
                    </div>

                    <h1 class="food-hero-title">
                        Thưởng thức phim
                        <br>
                        cùng <span>đồ ăn ngon</span>
                    </h1>

                    <p class="food-hero-description">
                        Khám phá những combo đồ ăn và thức uống
                        hấp dẫn tại MovieMate. Đặt trước để nhận
                        đồ ăn nhanh chóng và không bỏ lỡ bộ phim
                        yêu thích của bạn.
                    </p>

                    <div class="food-hero-actions">

                        <a
                            href="#food-menu"
                            class="food-hero-button primary"
                        >
                            <i class="fa-solid fa-utensils"></i>

                            Xem thực đơn
                        </a>

                        <a
                            href="{{ route('foods.cart') }}"
                            class="food-hero-button secondary"
                        >
                            <i class="fa-solid fa-cart-shopping"></i>

                            Xem giỏ hàng
                        </a>

                    </div>

                </div>

            </div>

        </div>

    </section>


    <div class="food-container">


        {{-- =====================================================
             FEATURED
             ====================================================== --}}

        @if(
            isset($featuredFoods)
            && $featuredFoods->count()
        )

            <section class="food-section">

                <div class="food-section-header">

                    <div>

                        <h2 class="food-section-heading">
                            <i
                                class="fa-solid fa-fire"
                                style="color:#f59e0b;"
                            ></i>

                            Món được yêu thích
                        </h2>

                        <p class="food-section-description">
                            Những lựa chọn được khách hàng
                            MovieMate yêu thích nhất.
                        </p>

                    </div>

                    <a
                        href="#food-menu"
                        class="food-view-all"
                    >
                        Xem tất cả

                        <i class="fa-solid fa-arrow-right"></i>
                    </a>

                </div>


                <div class="featured-grid">

                    @foreach(
                        $featuredFoods
                        as $featured
                    )

                        @php

                            $featuredImage =
                                $featured->image
                                    ? (
                                        filter_var(
                                            $featured->image,
                                            FILTER_VALIDATE_URL
                                        )
                                            ? $featured->image
                                            : asset(
                                                'storage/' .
                                                $featured->image
                                            )
                                    )
                                    : asset(
                                        'images/default-food.jpg'
                                    );

                        @endphp

                        <a
                            href="{{ route(
                                'foods.show',
                                $featured->id
                            ) }}"
                            class="featured-card"
                        >

                            <div
                                class="featured-card-image"
                            >

                                <img
                                    src="{{ $featuredImage }}"
                                    alt="{{ $featured->name }}"
                                    loading="lazy"
                                >

                            </div>

                            <div
                                class="featured-card-overlay"
                            ></div>

                            <div
                                class="featured-card-content"
                            >

                                @if($featured->category)

                                    <span
                                        class="featured-card-category"
                                    >
                                        {{ $featured->category->name }}
                                    </span>

                                @endif

                                <h3
                                    class="featured-card-name"
                                >
                                    {{ $featured->name }}
                                </h3>

                                <div
                                    class="featured-card-price"
                                >
                                    {{ number_format(
                                        $featured->price,
                                        0,
                                        ',',
                                        '.'
                                    ) }}đ
                                </div>

                            </div>

                        </a>

                    @endforeach

                </div>

            </section>

        @endif


        {{-- =====================================================
             MENU
             ====================================================== --}}

        <section
            class="food-section"
            id="food-menu"
        >

            <div class="food-section-header">

                <div>

                    <h2 class="food-section-heading">
                        Thực đơn MovieMate
                    </h2>

                    <p class="food-section-description">
                        Chọn món yêu thích và thêm vào giỏ hàng.
                    </p>

                </div>

            </div>


            {{-- =================================================
                 SEARCH + SORT
                 ================================================== --}}

            <form
                method="GET"
                action="{{ route('foods.index') }}"
                class="food-toolbar"
                id="food-filter-form"
            >

                <div class="food-search">

                    <i
                        class="fa-solid fa-magnifying-glass
                        food-search-icon"
                    ></i>

                    <input
                        type="text"
                        name="search"
                        id="food-search-input"
                        value="{{ request('search') }}"
                        placeholder="Tìm kiếm món ăn, combo, nước..."
                        autocomplete="off"
                    >

                    <button
                        type="button"
                        class="food-search-clear"
                        id="food-search-clear"
                    >
                        <i class="fa-solid fa-xmark"></i>
                    </button>

                </div>


                <select
                    name="sort"
                    class="food-sort"
                    onchange="this.form.submit()"
                >

                    <option
                        value=""
                        {{ !request('sort')
                            ? 'selected'
                            : '' }}
                    >
                        Sắp xếp mặc định
                    </option>

                    <option
                        value="popular"
                        {{ request('sort') === 'popular'
                            ? 'selected'
                            : '' }}
                    >
                        Bán chạy nhất
                    </option>

                    <option
                        value="newest"
                        {{ request('sort') === 'newest'
                            ? 'selected'
                            : '' }}
                    >
                        Mới nhất
                    </option>

                    <option
                        value="price_asc"
                        {{ request('sort') === 'price_asc'
                            ? 'selected'
                            : '' }}
                    >
                        Giá thấp → cao
                    </option>

                    <option
                        value="price_desc"
                        {{ request('sort') === 'price_desc'
                            ? 'selected'
                            : '' }}
                    >
                        Giá cao → thấp
                    </option>

                </select>

            </form>


            {{-- =================================================
                 CATEGORY
                 ================================================== --}}

            <div class="category-list">

                <a
                    href="{{ route('foods.index', [
                        'search' => request('search'),
                        'sort' => request('sort')
                    ]) }}"
                    class="category-button
                        {{ !request('category')
                            ? 'active'
                            : '' }}"
                >

                    <i class="fa-solid fa-border-all"></i>

                    Tất cả

                    @if(isset($foods))
                        <span class="category-count">
                            {{ $foods->total() }}
                        </span>
                    @endif

                </a>


                @if(
                    isset($categories)
                    && $categories->count()
                )

                    @foreach($categories as $category)

                        <a
                            href="{{ route(
                                'foods.index',
                                [
                                    'category' =>
                                        $category->id,

                                    'search' =>
                                        request('search'),

                                    'sort' =>
                                        request('sort')
                                ]
                            ) }}"
                            class="category-button
                                {{
                                    request('category')
                                    == $category->id
                                    ? 'active'
                                    : ''
                                }}"
                        >

                            @if(
                                str_contains(
                                    strtolower(
                                        $category->name
                                    ),
                                    'combo'
                                )
                            )

                                <i
                                    class="fa-solid fa-box-open"
                                ></i>

                            @elseif(
                                str_contains(
                                    strtolower(
                                        $category->name
                                    ),
                                    'bắp'
                                )
                            )

                                <i
                                    class="fa-solid fa-bowl-food"
                                ></i>

                            @elseif(
                                str_contains(
                                    strtolower(
                                        $category->name
                                    ),
                                    'nước'
                                )
                            )

                                <i
                                    class="fa-solid fa-glass-water"
                                ></i>

                            @else

                                <i
                                    class="fa-solid fa-utensils"
                                ></i>

                            @endif


                            {{ $category->name }}


                            @if(
                                isset(
                                    $category->foods_count
                                )
                            )

                                <span
                                    class="category-count"
                                >
                                    {{ $category->foods_count }}
                                </span>

                            @endif

                        </a>

                    @endforeach

                @endif

            </div>


            {{-- =================================================
                 RESULT
                 ================================================== --}}

            <div
                class="food-result-info"
                style="margin-top:22px;"
            >

                <div class="food-result-count">

                    Hiển thị

                    <strong>
                        {{ $foods->count() }}
                    </strong>

                    món trong tổng số

                    <strong>
                        {{ $foods->total() }}
                    </strong>

                    món

                </div>


                @if(
                    request('search')
                    || request('category')
                    || request('sort')
                )

                    <a
                        href="{{ route(
                            'foods.index'
                        ) }}"
                        class="food-reset"
                    >

                        <i
                            class="fa-solid fa-rotate-left"
                        ></i>

                        Xóa bộ lọc

                    </a>

                @endif

            </div>


            {{-- =================================================
                 FOOD LIST
                 ================================================== --}}

            @if(
                isset($foods)
                && $foods->count()
            )

                <div class="food-grid">

                    @foreach($foods as $food)

                        @php

                            $foodImage =
                                $food->image
                                    ? (
                                        filter_var(
                                            $food->image,
                                            FILTER_VALIDATE_URL
                                        )
                                            ? $food->image
                                            : asset(
                                                'storage/' .
                                                $food->image
                                            )
                                    )
                                    : asset(
                                        'images/default-food.jpg'
                                    );

                            $isAvailable =
                                $food->status
                                && $food->stock > 0;

                        @endphp


                        <article
                            class="food-card"
                            data-food-id="{{ $food->id }}"
                        >

                            {{-- IMAGE --}}

                            <div
                                class="food-card-image"
                            >

                                <img
                                    src="{{ $foodImage }}"
                                    alt="{{ $food->name }}"
                                    loading="lazy"
                                    class="food-product-image"
                                >


                                <div
                                    class="food-image-overlay"
                                ></div>


                                {{-- BADGES --}}

                                <div class="food-badges">

                                    @if(
                                        $food->is_featured
                                    )

                                        <span
                                            class="food-badge featured"
                                        >

                                            <i
                                                class="fa-solid
                                                fa-star"
                                            ></i>

                                            Nổi bật

                                        </span>

                                    @endif


                                    @if(
                                        !$isAvailable
                                    )

                                        <span
                                            class="food-badge sold"
                                        >

                                            <i
                                                class="fa-solid
                                                fa-circle-xmark"
                                            ></i>

                                            Hết hàng

                                        </span>

                                    @elseif(
                                        $food->stock <= 10
                                    )

                                        <span
                                            class="food-badge sold"
                                        >

                                            Sắp hết

                                        </span>

                                    @endif

                                </div>


                                {{-- FAVORITE --}}

                                <button
                                    type="button"
                                    class="food-favorite"
                                    aria-label="Yêu thích"
                                    data-favorite-id="{{ $food->id }}"
                                >

                                    <i
                                        class="fa-regular
                                        fa-heart"
                                    ></i>

                                </button>

                            </div>


                            {{-- BODY --}}

                            <div
                                class="food-card-body"
                            >

                                {{-- CATEGORY --}}

                                @if(
                                    $food->category
                                )

                                    <div
                                        class="food-category"
                                    >
                                        {{
                                            $food
                                                ->category
                                                ->name
                                        }}
                                    </div>

                                @endif


                                {{-- NAME --}}

                                <h3
                                    class="food-name"
                                >

                                    <a
                                        href="{{ route(
                                            'foods.show',
                                            $food->id
                                        ) }}"
                                    >
                                        {{ $food->name }}
                                    </a>

                                </h3>


                                {{-- DESCRIPTION --}}

                                <p
                                    class="food-description"
                                >
                                    {{
                                        $food
                                            ->description
                                            ?: 'Món ăn hấp dẫn tại MovieMate.'
                                    }}
                                </p>


                                {{-- PRICE --}}

                                <div
                                    class="food-price-row"
                                >

                                    <div>

                                        <div
                                            class="food-price"
                                        >
                                            {{
                                                number_format(
                                                    $food->price,
                                                    0,
                                                    ',',
                                                    '.'
                                                )
                                            }}đ
                                        </div>

                                        <div
                                            class="food-price-unit"
                                        >
                                            / phần
                                        </div>

                                    </div>


                                    <div
                                        class="food-sold"
                                    >

                                        <i
                                            class="fa-solid
                                            fa-fire"
                                        ></i>

                                        {{ $food->sold_count }}
                                        đã bán

                                    </div>

                                </div>


                                {{-- ACTIONS --}}

                                @if(
                                    $isAvailable
                                )

                                    <div
                                        class="food-actions"
                                    >

                                        <div
                                            class="quantity-control"
                                        >

                                            <button
                                                type="button"
                                                class="quantity-button
                                                    quantity-minus"
                                                data-id="{{ $food->id }}"
                                            >
                                                −
                                            </button>

                                            <span
                                                class="quantity-value"
                                                data-quantity="{{ $food->id }}"
                                            >
                                                1
                                            </span>

                                            <button
                                                type="button"
                                                class="quantity-button
                                                    quantity-plus"
                                                data-id="{{ $food->id }}"
                                                data-stock="{{ $food->stock }}"
                                            >
                                                +
                                            </button>

                                        </div>


                                        <button
                                            type="button"
                                            class="add-food-button"
                                            data-id="{{ $food->id }}"
                                            data-name="{{ $food->name }}"
                                            data-stock="{{ $food->stock }}"
                                            data-image="{{ $foodImage }}"
                                        >

                                            <i
                                                class="fa-solid
                                                fa-cart-plus"
                                            ></i>

                                            <span>
                                                Thêm vào giỏ
                                            </span>

                                        </button>

                                    </div>

                                @else

                                    <button
                                        type="button"
                                        class="add-food-button disabled"
                                        disabled
                                    >

                                        <i
                                            class="fa-solid
                                            fa-ban"
                                        ></i>

                                        Hết hàng

                                    </button>

                                @endif

                            </div>

                        </article>

                    @endforeach

                </div>


                {{-- =================================================
                     PAGINATION
                     ================================================== --}}

                @if(
                    $foods->hasPages()
                )

                    <div
                        class="food-pagination"
                    >

                        {{ $foods->links() }}

                    </div>

                @endif

            @else

                {{-- =================================================
                     EMPTY
                     ================================================== --}}

                <div
                    class="food-empty"
                >

                    <div
                        class="food-empty-icon"
                    >

                        <i
                            class="fa-solid
                            fa-bowl-food"
                        ></i>

                    </div>

                    <h3>
                        Không tìm thấy món ăn
                    </h3>

                    <p>
                        Không có món ăn nào phù hợp
                        với tìm kiếm của bạn.
                    </p>

                    <br>

                    <a
                        href="{{ route(
                            'foods.index'
                        ) }}"
                        class="food-hero-button primary"
                    >

                        <i
                            class="fa-solid
                            fa-rotate-left"
                        ></i>

                        Xem tất cả món

                    </a>

                </div>

            @endif

        </section>

    </div>


    {{-- =========================================================
         FLOATING CART
         ========================================================== --}}

    @php

        $currentCart =
            session()->get(
                'food_cart',
                []
            );

        $currentCartCount =
            collect($currentCart)
                ->sum('quantity');

        $currentCartTotal =
            collect($currentCart)
                ->sum(function ($item) {

                    return
                        $item['price']
                        * $item['quantity'];
                });

    @endphp


    <a
        href="{{ route('foods.cart') }}"
        class="floating-cart"
        id="floating-food-cart"
    >

        <div
            class="floating-cart-icon"
        >

            <i
                class="fa-solid
                fa-cart-shopping"
            ></i>

        </div>


        <div
            class="floating-cart-text"
        >

            <span
                class="floating-cart-label"
            >
                Giỏ hàng
            </span>

            <span
                class="floating-cart-total"
                id="floating-cart-total"
            >

                {{
                    number_format(
                        $currentCartTotal,
                        0,
                        ',',
                        '.'
                    )
                }}đ

            </span>

        </div>


        <span
            class="floating-cart-count"
            id="floating-cart-count"
        >
            {{ $currentCartCount }}
        </span>

    </a>


    {{-- =========================================================
         TOAST CONTAINER
         ========================================================== --}}

    <div
        class="food-toast-container"
        id="food-toast-container"
    ></div>


</div>


<script>
    /* =========================================================
       MOVIEMATE FOOD JAVASCRIPT
       ========================================================= */

    document.addEventListener(
        'DOMContentLoaded',
        function () {

            /*
             * -------------------------------------------------
             * ELEMENTS
             * -------------------------------------------------
             */

            const searchInput =
                document.getElementById(
                    'food-search-input'
                );

            const searchClear =
                document.getElementById(
                    'food-search-clear'
                );

            const filterForm =
                document.getElementById(
                    'food-filter-form'
                );

            const floatingCart =
                document.getElementById(
                    'floating-food-cart'
                );

            const floatingCartCount =
                document.getElementById(
                    'floating-cart-count'
                );

            const floatingCartTotal =
                document.getElementById(
                    'floating-cart-total'
                );

            const toastContainer =
                document.getElementById(
                    'food-toast-container'
                );


            /*
             * -------------------------------------------------
             * HELPERS
             * -------------------------------------------------
             */

            function formatMoney(
                value
            ) {

                return new Intl.NumberFormat(
                    'vi-VN'
                ).format(
                    Number(value || 0)
                ) + 'đ';

            }


            function escapeHtml(
                value
            ) {

                const div =
                    document.createElement(
                        'div'
                    );

                div.textContent =
                    value ?? '';

                return div.innerHTML;

            }


            /*
             * -------------------------------------------------
             * TOAST
             * -------------------------------------------------
             */

            function showToast(
                message,
                type = 'success'
            ) {

                if (!toastContainer) {
                    return;
                }

                const toast =
                    document.createElement(
                        'div'
                    );

                toast.className =
                    `food-toast ${type}`;

                const icon =
                    type === 'success'
                        ? 'fa-check'
                        : 'fa-xmark';

                const title =
                    type === 'success'
                        ? 'Thành công'
                        : 'Có lỗi xảy ra';

                toast.innerHTML = `

                    <div
                        class="food-toast-icon"
                    >

                        <i
                            class="fa-solid ${icon}"
                        ></i>

                    </div>

                    <div
                        class="food-toast-content"
                    >

                        <p
                            class="food-toast-title"
                        >
                            ${title}
                        </p>

                        <p
                            class="food-toast-message"
                        >
                            ${escapeHtml(message)}
                        </p>

                    </div>

                    <button
                        type="button"
                        class="food-toast-close"
                    >

                        <i
                            class="fa-solid
                            fa-xmark"
                        ></i>

                    </button>

                `;

                toastContainer.appendChild(
                    toast
                );

                const closeButton =
                    toast.querySelector(
                        '.food-toast-close'
                    );

                closeButton.addEventListener(
                    'click',
                    function () {

                        removeToast(
                            toast
                        );

                    }
                );

                setTimeout(
                    function () {

                        removeToast(
                            toast
                        );

                    },
                    3500
                );

            }


            function removeToast(
                toast
            ) {

                if (
                    !toast ||
                    !toast.parentNode
                ) {
                    return;
                }

                toast.style.animation =
                    'toastOut 0.3s ease forwards';

                setTimeout(
                    function () {

                        toast.remove();

                    },
                    300
                );

            }


            /*
             * -------------------------------------------------
             * SEARCH CLEAR
             * -------------------------------------------------
             */

            function updateSearchClear() {

                if (
                    !searchInput ||
                    !searchClear
                ) {
                    return;
                }

                if (
                    searchInput.value.trim()
                ) {

                    searchClear.style.display =
                        'flex';

                } else {

                    searchClear.style.display =
                        'none';

                }

            }


            if (searchInput) {

                updateSearchClear();

                searchInput.addEventListener(
                    'input',
                    updateSearchClear
                );

                searchInput.addEventListener(
                    'keydown',
                    function (event) {

                        if (
                            event.key === 'Enter'
                        ) {

                            event.preventDefault();

                            filterForm.submit();

                        }

                    }
                );

            }


            if (searchClear) {

                searchClear.addEventListener(
                    'click',
                    function () {

                        searchInput.value = '';

                        updateSearchClear();

                        filterForm.submit();

                    }
                );

            }


            /*
             * -------------------------------------------------
             * QUANTITY
             * -------------------------------------------------
             */

            document
                .querySelectorAll(
                    '.quantity-minus'
                )
                .forEach(
                    function (button) {

                        button.addEventListener(
                            'click',
                            function () {

                                const id =
                                    this.dataset.id;

                                const element =
                                    document.querySelector(
                                        `[data-quantity="${id}"]`
                                    );

                                if (!element) {
                                    return;
                                }

                                let quantity =
                                    parseInt(
                                        element.textContent
                                    ) || 1;

                                quantity--;

                                if (
                                    quantity < 1
                                ) {
                                    quantity = 1;
                                }

                                element.textContent =
                                    quantity;

                            }
                        );

                    }
                );


            document
                .querySelectorAll(
                    '.quantity-plus'
                )
                .forEach(
                    function (button) {

                        button.addEventListener(
                            'click',
                            function () {

                                const id =
                                    this.dataset.id;

                                const stock =
                                    parseInt(
                                        this.dataset.stock
                                    ) || 1;

                                const element =
                                    document.querySelector(
                                        `[data-quantity="${id}"]`
                                    );

                                if (!element) {
                                    return;
                                }

                                let quantity =
                                    parseInt(
                                        element.textContent
                                    ) || 1;

                                quantity++;

                                if (
                                    quantity > stock
                                ) {

                                    showToast(
                                        'Số lượng vượt quá tồn kho.',
                                        'error'
                                    );

                                    quantity =
                                        stock;
                                }

                                element.textContent =
                                    quantity;

                            }
                        );

                    }
                );


            /*
             * -------------------------------------------------
             * ADD TO CART
             * -------------------------------------------------
             */

            document
                .querySelectorAll(
                    '.add-food-button:not(.disabled)'
                )
                .forEach(
                    function (button) {

                        button.addEventListener(
                            'click',
                            function () {

                                addFoodToCart(
                                    this
                                );

                            }
                        );

                    }
                );


            async function addFoodToCart(
                button
            ) {

                if (
                    button.classList.contains(
                        'loading'
                    )
                ) {
                    return;
                }

                const id =
                    button.dataset.id;

                const name =
                    button.dataset.name;

                const stock =
                    parseInt(
                        button.dataset.stock
                    ) || 1;

                const quantityElement =
                    document.querySelector(
                        `[data-quantity="${id}"]`
                    );

                let quantity =
                    parseInt(
                        quantityElement
                            ? quantityElement.textContent
                            : 1
                    ) || 1;

                if (
                    quantity < 1
                ) {
                    quantity = 1;
                }

                if (
                    quantity > stock
                ) {

                    showToast(
                        'Số lượng vượt quá tồn kho.',
                        'error'
                    );

                    return;

                }


                /*
                 * Loading
                 */

                button.classList.add(
                    'loading'
                );

                const oldHtml =
                    button.innerHTML;

                button.innerHTML = `

                    <i
                        class="fa-solid
                        fa-spinner
                        fa-spin"
                    ></i>

                    <span>
                        Đang thêm...
                    </span>

                `;


                /*
                 * Fly animation
                 */

                createFlyingImage(
                    button
                );


                try {

                    const response =
                        await fetch(
                            `{{ url('/foods') }}/${id}/add-to-cart`,
                            {
                                method: 'POST',

                                headers: {
                                    'Content-Type':
                                        'application/json',

                                    'Accept':
                                        'application/json',

                                    'X-CSRF-TOKEN':
                                        getCsrfToken()
                                },

                                body:
                                    JSON.stringify({
                                        quantity:
                                            quantity
                                    })
                            }
                        );


                    const data =
                        await response.json();


                    if (
                        !response.ok ||
                        !data.success
                    ) {

                        throw new Error(
                            data.message
                            ||
                            'Không thể thêm món vào giỏ.'
                        );

                    }


                    /*
                     * Update cart
                     */

                    updateFloatingCart(
                        data.cart_count,
                        data.cart_total
                    );


                    /*
                     * Reset quantity
                     */

                    if (
                        quantityElement
                    ) {

                        quantityElement
                            .textContent = 1;

                    }


                    /*
                     * Animation
                     */

                    if (
                        floatingCart
                    ) {

                        floatingCart.classList
                            .remove('bump');

                        void floatingCart
                            .offsetWidth;

                        floatingCart.classList
                            .add('bump');

                    }


                    showToast(
                        `${name} đã được thêm vào giỏ hàng.`,
                        'success'
                    );


                } catch (error) {

                    console.error(
                        error
                    );

                    showToast(
                        error.message
                        ||
                        'Có lỗi xảy ra.',
                        'error'
                    );

                } finally {

                    setTimeout(
                        function () {

                            button
                                .classList
                                .remove(
                                    'loading'
                                );

                            button.innerHTML =
                                oldHtml;

                        },
                        500
                    );

                }

            }


            /*
             * -------------------------------------------------
             * CSRF
             * -------------------------------------------------
             */

            function getCsrfToken() {

                const meta =
                    document.querySelector(
                        'meta[name="csrf-token"]'
                    );

                if (meta) {

                    return meta
                        .getAttribute(
                            'content'
                        );

                }

                const input =
                    document.querySelector(
                        'input[name="_token"]'
                    );

                return input
                    ? input.value
                    : '';

            }


            /*
             * -------------------------------------------------
             * UPDATE FLOATING CART
             * -------------------------------------------------
             */

            function updateFloatingCart(
                count,
                total
            ) {

                if (
                    floatingCartCount
                ) {

                    floatingCartCount
                        .textContent =
                        count || 0;

                }

                if (
                    floatingCartTotal
                ) {

                    floatingCartTotal
                        .textContent =
                        formatMoney(
                            total || 0
                        );

                }

            }


            /*
             * -------------------------------------------------
             * FLYING IMAGE
             * -------------------------------------------------
             */

            function createFlyingImage(
                button
            ) {

                const card =
                    button.closest(
                        '.food-card'
                    );

                if (!card) {
                    return;
                }

                const image =
                    card.querySelector(
                        '.food-product-image'
                    );

                if (!image) {
                    return;
                }

                if (!floatingCart) {
                    return;
                }


                const imageRect =
                    image.getBoundingClientRect();

                const cartRect =
                    floatingCart
                        .getBoundingClientRect();


                const flying =
                    document.createElement(
                        'img'
                    );

                flying.className =
                    'food-flying-image';

                flying.src =
                    image.src;

                flying.style.left =
                    `${imageRect.left}px`;

                flying.style.top =
                    `${imageRect.top}px`;

                document.body.appendChild(
                    flying
                );


                requestAnimationFrame(
                    function () {

                        flying.style.left =
                            `${
                                cartRect.left
                                + cartRect.width / 2
                                - 32
                            }px`;

                        flying.style.top =
                            `${
                                cartRect.top
                                + cartRect.height / 2
                                - 32
                            }px`;

                        flying.style.width =
                            '15px';

                        flying.style.height =
                            '15px';

                        flying.style.opacity =
                            '0';

                    }
                );


                setTimeout(
                    function () {

                        flying.remove();

                    },
                    700
                );

            }


            /*
             * -------------------------------------------------
             * FAVORITE BUTTON
             * -------------------------------------------------
             */

            document
                .querySelectorAll(
                    '.food-favorite'
                )
                .forEach(
                    function (button) {

                        button.addEventListener(
                            'click',
                            function (event) {

                                event.preventDefault();

                                event.stopPropagation();

                                this.classList.toggle(
                                    'active'
                                );

                                const icon =
                                    this.querySelector(
                                        'i'
                                    );

                                if (
                                    this.classList
                                        .contains(
                                            'active'
                                        )
                                ) {

                                    icon.classList
                                        .remove(
                                            'fa-regular'
                                        );

                                    icon.classList
                                        .add(
                                            'fa-solid'
                                        );

                                } else {

                                    icon.classList
                                        .remove(
                                            'fa-solid'
                                        );

                                    icon.classList
                                        .add(
                                            'fa-regular'
                                        );

                                }

                            }
                        );

                    }
                );


            /*
             * -------------------------------------------------
             * LOAD CART COUNT
             * -------------------------------------------------
             */

            async function loadCartCount() {

                try {

                    const response =
                        await fetch(
                            '{{ route(
                                "foods.cart.count"
                            ) }}',
                            {
                                headers: {
                                    'Accept':
                                        'application/json'
                                }
                            }
                        );


                    if (
                        !response.ok
                    ) {
                        return;
                    }


                    const data =
                        await response.json();


                    updateFloatingCart(
                        data.count,
                        data.total
                    );

                } catch (error) {

                    console.warn(
                        'Không thể lấy số lượng giỏ hàng.',
                        error
                    );

                }

            }


            loadCartCount();


            /*
             * -------------------------------------------------
             * SMOOTH SCROLL
             * -------------------------------------------------
             */

            document
                .querySelectorAll(
                    'a[href="#food-menu"]'
                )
                .forEach(
                    function (link) {

                        link.addEventListener(
                            'click',
                            function (event) {

                                const target =
                                    document.getElementById(
                                        'food-menu'
                                    );

                                if (!target) {
                                    return;
                                }

                                event.preventDefault();

                                target.scrollIntoView({
                                    behavior:
                                        'smooth',

                                    block:
                                        'start'
                                });

                            }
                        );

                    }
                );


            /*
             * -------------------------------------------------
             * CARD HOVER EFFECT
             * -------------------------------------------------
             */

            document
                .querySelectorAll(
                    '.food-card'
                )
                .forEach(
                    function (card) {

                        card.addEventListener(
                            'mouseenter',
                            function () {

                                this.style.zIndex =
                                    '5';

                            }
                        );

                        card.addEventListener(
                            'mouseleave',
                            function () {

                                this.style.zIndex =
                                    '';

                            }
                        );

                    }
                );


            /*
             * -------------------------------------------------
             * SEARCH AUTO FOCUS
             * -------------------------------------------------
             */

            const urlParams =
                new URLSearchParams(
                    window.location.search
                );

            if (
                urlParams.has('search')
                &&
                searchInput
            ) {

                searchInput.focus();

                searchInput.setSelectionRange(
                    searchInput.value.length,
                    searchInput.value.length
                );

            }


            /*
             * -------------------------------------------------
             * IMAGE ERROR FALLBACK
             * -------------------------------------------------
             */

            document
                .querySelectorAll(
                    '.food-product-image'
                )
                .forEach(
                    function (image) {

                        image.addEventListener(
                            'error',
                            function () {

                                this.src =
                                    'https://images.unsplash.com/photo-1585647347384-2593bc35786b?auto=format&fit=crop&w=900&q=80';

                            }
                        );

                    }
                );

        }
    );
</script>

@endsection