
@extends('layouts.admin')

@section('title', isset($food) ? 'Chỉnh sửa món ăn' : 'Thêm món ăn')

@section('content')

<style>
    /* =========================================================
       MOVIEMATE ADMIN FOOD FORM
       ========================================================= */

    :root {
        --food-primary: #e50914;
        --food-primary-dark: #b20710;
        --food-bg: #f5f6f8;
        --food-card: #ffffff;
        --food-text: #17191c;
        --food-muted: #737981;
        --food-border: #e2e5e9;
        --food-success: #16a34a;
        --food-warning: #f59e0b;
        --food-danger: #dc2626;
        --food-info: #2563eb;
        --food-radius: 14px;
        --food-shadow: 0 5px 20px rgba(0,0,0,.06);
    }

    .food-form-page {
        min-height: 100vh;
        background: var(--food-bg);
        padding: 25px;
    }

    .food-form-container {
        width: min(1500px, 100%);
        margin: 0 auto;
    }

    /* =========================================================
       PAGE HEADER
       ========================================================= */

    .food-page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 25px;
    }

    .food-page-title-area {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .food-back-button {
        width: 43px;
        height: 43px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 11px;
        border: 1px solid var(--food-border);
        background: #fff;
        color: #333;
        text-decoration: none;
        transition: .2s;
    }

    .food-back-button:hover {
        background: #f1f2f4;
        color: var(--food-primary);
        transform: translateX(-2px);
    }

    .food-page-title {
        margin: 0;
        color: var(--food-text);
        font-size: 28px;
        line-height: 1.2;
        font-weight: 800;
    }

    .food-page-subtitle {
        margin: 5px 0 0;
        color: var(--food-muted);
        font-size: 13px;
    }

    .food-header-actions {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .food-button {
        min-height: 43px;
        padding: 0 17px;
        border-radius: 10px;
        border: 1px solid transparent;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
        cursor: pointer;
        transition: .2s;
    }

    .food-button-secondary {
        background: #fff;
        color: #454a51;
        border-color: var(--food-border);
    }

    .food-button-secondary:hover {
        background: #f7f7f8;
        color: #111;
    }

    .food-button-primary {
        background: var(--food-primary);
        color: #fff;
        box-shadow: 0 5px 15px rgba(229,9,20,.15);
    }

    .food-button-primary:hover {
        background: var(--food-primary-dark);
        color: #fff;
        transform: translateY(-1px);
    }

    .food-button-success {
        background: var(--food-success);
        color: #fff;
    }

    .food-button-danger {
        background: #fff;
        color: var(--food-danger);
        border-color: #fecaca;
    }

    .food-button-danger:hover {
        background: #fef2f2;
    }

    /* =========================================================
       LAYOUT
       ========================================================= */

    .food-form-layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 390px;
        gap: 22px;
        align-items: start;
    }

    .food-main-column {
        min-width: 0;
    }

    .food-sidebar {
        min-width: 0;
        position: sticky;
        top: 20px;
    }

    /* =========================================================
       CARD
       ========================================================= */

    .food-card-panel {
        background: var(--food-card);
        border: 1px solid var(--food-border);
        border-radius: var(--food-radius);
        box-shadow: var(--food-shadow);
        margin-bottom: 20px;
        overflow: hidden;
    }

    .food-card-header {
        min-height: 68px;
        padding: 17px 21px;
        border-bottom: 1px solid var(--food-border);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
    }

    .food-card-header-left {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .food-card-icon {
        width: 39px;
        height: 39px;
        border-radius: 10px;
        background: #fff1f2;
        color: var(--food-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
    }

    .food-card-title {
        margin: 0;
        font-size: 16px;
        font-weight: 800;
        color: var(--food-text);
    }

    .food-card-description {
        margin: 3px 0 0;
        font-size: 12px;
        color: var(--food-muted);
    }

    .food-card-body {
        padding: 22px;
    }

    /* =========================================================
       FORM GRID
       ========================================================= */

    .food-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 19px;
    }

    .food-form-grid-3 {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 17px;
    }

    .food-form-group {
        min-width: 0;
    }

    .food-form-group.full {
        grid-column: 1 / -1;
    }

    .food-label {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 7px;
        color: #30343a;
        font-size: 13px;
        font-weight: 750;
    }

    .food-label-required {
        color: var(--food-danger);
    }

    .food-label-help {
        color: #9ca3af;
        font-size: 11px;
        font-weight: 500;
    }

    .food-input,
    .food-select,
    .food-textarea {
        width: 100%;
        border: 1px solid #dfe2e6;
        border-radius: 10px;
        background: #fff;
        color: #20242a;
        outline: none;
        transition: .2s;
        font-family: inherit;
    }

    .food-input,
    .food-select {
        height: 46px;
        padding: 0 13px;
        font-size: 13px;
    }

    .food-textarea {
        min-height: 135px;
        padding: 12px 13px;
        resize: vertical;
        line-height: 1.65;
        font-size: 13px;
    }

    .food-input::placeholder,
    .food-textarea::placeholder {
        color: #b0b5bc;
    }

    .food-input:focus,
    .food-select:focus,
    .food-textarea:focus {
        border-color: rgba(229,9,20,.65);
        box-shadow: 0 0 0 4px rgba(229,9,20,.07);
    }

    .food-input.is-invalid,
    .food-select.is-invalid,
    .food-textarea.is-invalid {
        border-color: #ef4444;
        background: #fffafa;
    }

    .food-error {
        display: flex;
        align-items: center;
        gap: 5px;
        margin-top: 6px;
        color: #dc2626;
        font-size: 11px;
    }

    .food-help {
        margin-top: 6px;
        color: #92979e;
        font-size: 11px;
        line-height: 1.5;
    }

    /* =========================================================
       INPUT WITH PREFIX / SUFFIX
       ========================================================= */

    .food-input-group {
        position: relative;
    }

    .food-input-prefix,
    .food-input-suffix {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        color: #8b9198;
        font-size: 12px;
        pointer-events: none;
    }

    .food-input-prefix {
        left: 13px;
    }

    .food-input-suffix {
        right: 13px;
    }

    .food-input-group.has-prefix .food-input {
        padding-left: 34px;
    }

    .food-input-group.has-suffix .food-input {
        padding-right: 45px;
    }

    /* =========================================================
       CHARACTER COUNTER
       ========================================================= */

    .food-counter {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: 5px;
        font-size: 10px;
        color: #9ca3af;
    }

    .food-counter.warning {
        color: #d97706;
    }

    .food-counter.danger {
        color: #dc2626;
    }

    /* =========================================================
       STATUS BOX
       ========================================================= */

    .food-status-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
    }

    .food-status-option {
        position: relative;
    }

    .food-status-option input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .food-status-label {
        min-height: 85px;
        padding: 13px;
        border: 1px solid #e1e4e8;
        border-radius: 12px;
        background: #fff;
        display: flex;
        align-items: center;
        gap: 11px;
        cursor: pointer;
        transition: .2s;
    }

    .food-status-label:hover {
        border-color: #cbd0d6;
    }

    .food-status-option input:checked + .food-status-label {
        border-color: var(--food-primary);
        background: #fff7f7;
        box-shadow: 0 0 0 3px rgba(229,9,20,.05);
    }

    .food-status-icon {
        width: 38px;
        height: 38px;
        flex: 0 0 auto;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        background: #f3f4f6;
        color: #6b7280;
    }

    .food-status-option input:checked + .food-status-label .food-status-icon {
        background: #fee2e2;
        color: var(--food-primary);
    }

    .food-status-content strong {
        display: block;
        margin-bottom: 3px;
        color: #292d33;
        font-size: 12px;
        font-weight: 800;
    }

    .food-status-content span {
        display: block;
        color: #8b9198;
        font-size: 10px;
        line-height: 1.4;
    }

    /* =========================================================
       TOGGLE
       ========================================================= */

    .food-toggle-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        padding: 15px 0;
        border-bottom: 1px solid #f0f1f2;
    }

    .food-toggle-row:last-child {
        border-bottom: 0;
    }

    .food-toggle-info strong {
        display: block;
        color: #2a2e34;
        font-size: 13px;
        font-weight: 750;
    }

    .food-toggle-info span {
        display: block;
        margin-top: 4px;
        color: #90959b;
        font-size: 11px;
        line-height: 1.5;
    }

    .food-toggle {
        position: relative;
        flex: 0 0 auto;
    }

    .food-toggle input {
        position: absolute;
        opacity: 0;
    }

    .food-toggle-label {
        width: 48px;
        height: 26px;
        display: block;
        border-radius: 999px;
        background: #d1d5db;
        cursor: pointer;
        transition: .2s;
        position: relative;
    }

    .food-toggle-label::after {
        content: "";
        width: 20px;
        height: 20px;
        position: absolute;
        top: 3px;
        left: 3px;
        border-radius: 50%;
        background: #fff;
        box-shadow: 0 2px 5px rgba(0,0,0,.15);
        transition: .2s;
    }

    .food-toggle input:checked + .food-toggle-label {
        background: var(--food-primary);
    }

    .food-toggle input:checked + .food-toggle-label::after {
        transform: translateX(22px);
    }

    /* =========================================================
       IMAGE UPLOAD
       ========================================================= */

    .food-image-upload {
        position: relative;
        border: 2px dashed #d9dde2;
        border-radius: 15px;
        background: #fafbfc;
        min-height: 250px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        transition: .2s;
    }

    .food-image-upload.dragover {
        border-color: var(--food-primary);
        background: #fff7f7;
    }

    .food-image-upload.has-image {
        border-style: solid;
        border-color: #e0e3e7;
    }

    .food-upload-placeholder {
        text-align: center;
        padding: 30px;
    }

    .food-upload-icon {
        width: 62px;
        height: 62px;
        margin: 0 auto 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 15px;
        background: #fff1f2;
        color: var(--food-primary);
        font-size: 25px;
    }

    .food-upload-title {
        margin: 0 0 6px;
        color: #32363b;
        font-size: 14px;
        font-weight: 800;
    }

    .food-upload-description {
        margin: 0 0 15px;
        color: #92979e;
        font-size: 11px;
        line-height: 1.6;
    }

    .food-upload-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        min-height: 38px;
        padding: 0 14px;
        border: 1px solid #e1e4e8;
        border-radius: 9px;
        background: #fff;
        color: #41464d;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
    }

    .food-upload-button:hover {
        border-color: #c9ced4;
    }

    .food-file-input {
        display: none;
    }

    .food-image-preview {
        position: relative;
        width: 100%;
        height: 100%;
        min-height: 250px;
        display: none;
    }

    .food-image-preview.active {
        display: block;
    }

    .food-image-preview img {
        width: 100%;
        height: 280px;
        display: block;
        object-fit: cover;
    }

    .food-image-overlay {
        position: absolute;
        inset: auto 0 0;
        padding: 40px 15px 15px;
        background: linear-gradient(
            transparent,
            rgba(0,0,0,.75)
        );
        color: #fff;
    }

    .food-image-file-name {
        margin: 0 0 4px;
        font-size: 12px;
        font-weight: 800;
    }

    .food-image-file-size {
        margin: 0;
        color: rgba(255,255,255,.75);
        font-size: 10px;
    }

    .food-image-remove {
        position: absolute;
        top: 12px;
        right: 12px;
        width: 36px;
        height: 36px;
        border: 0;
        border-radius: 50%;
        background: rgba(0,0,0,.65);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 5;
    }

    .food-image-remove:hover {
        background: #dc2626;
    }

    /* =========================================================
       IMAGE REQUIREMENTS
       ========================================================= */

    .food-image-requirements {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
        margin-top: 12px;
    }

    .food-requirement {
        display: flex;
        align-items: center;
        gap: 7px;
        color: #81878e;
        font-size: 10px;
    }

    .food-requirement i {
        color: #16a34a;
        font-size: 10px;
    }

    /* =========================================================
       PRICE PREVIEW
       ========================================================= */

    .food-price-preview {
        padding: 17px;
        border: 1px solid #e6e8eb;
        border-radius: 12px;
        background:
            linear-gradient(
                135deg,
                #fff,
                #fafafa
            );
    }

    .food-price-preview-label {
        margin-bottom: 5px;
        color: #8c9299;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: .4px;
        font-weight: 800;
    }

    .food-price-preview-value {
        color: var(--food-primary);
        font-size: 27px;
        font-weight: 900;
    }

    .food-price-preview-note {
        margin-top: 4px;
        color: #8c9299;
        font-size: 10px;
    }

    /* =========================================================
       INVENTORY
       ========================================================= */

    .food-stock-indicator {
        margin-top: 8px;
    }

    .food-stock-bar {
        height: 7px;
        border-radius: 999px;
        background: #edf0f2;
        overflow: hidden;
    }

    .food-stock-progress {
        width: 0%;
        height: 100%;
        border-radius: inherit;
        background: #16a34a;
        transition: width .3s ease;
    }

    .food-stock-text {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: 5px;
        color: #8a9097;
        font-size: 10px;
    }

    /* =========================================================
       TAGS
       ========================================================= */

    .food-tag-input-wrapper {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 7px;
        min-height: 46px;
        padding: 6px 9px;
        border: 1px solid #dfe2e6;
        border-radius: 10px;
        background: #fff;
        cursor: text;
    }

    .food-tag-input-wrapper:focus-within {
        border-color: rgba(229,9,20,.65);
        box-shadow: 0 0 0 4px rgba(229,9,20,.07);
    }

    .food-tag {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        min-height: 29px;
        padding: 0 8px 0 10px;
        border-radius: 999px;
        background: #f1f5f9;
        color: #475569;
        font-size: 10px;
        font-weight: 700;
    }

    .food-tag button {
        width: 18px;
        height: 18px;
        border: 0;
        border-radius: 50%;
        background: #dbe1e7;
        color: #64748b;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 9px;
    }

    .food-tag button:hover {
        background: #fecaca;
        color: #dc2626;
    }

    .food-tag-input {
        flex: 1;
        min-width: 100px;
        height: 31px;
        border: 0;
        outline: 0;
        background: transparent;
        color: #333;
        font-size: 12px;
    }

    /* =========================================================
       CHECKBOX
       ========================================================= */

    .food-checkbox-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .food-checkbox {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        cursor: pointer;
    }

    .food-checkbox input {
        width: 17px;
        height: 17px;
        margin-top: 1px;
        accent-color: var(--food-primary);
        cursor: pointer;
    }

    .food-checkbox-text strong {
        display: block;
        color: #353a40;
        font-size: 12px;
        font-weight: 750;
    }

    .food-checkbox-text span {
        display: block;
        margin-top: 2px;
        color: #959aa1;
        font-size: 10px;
        line-height: 1.5;
    }

    /* =========================================================
       SIDEBAR PREVIEW
       ========================================================= */

    .food-live-preview {
        overflow: hidden;
        border: 1px solid #e2e5e8;
        border-radius: 15px;
        background: #fff;
    }

    .food-live-image {
        width: 100%;
        height: 220px;
        object-fit: cover;
        display: block;
        background: #eef0f2;
    }

    .food-live-image-placeholder {
        width: 100%;
        height: 220px;
        display: flex;
        align-items: center;
        justify-content: center;
        background:
            linear-gradient(
                135deg,
                #eef1f4,
                #f8f9fa
            );
        color: #adb3ba;
        font-size: 40px;
    }

    .food-live-body {
        padding: 17px;
    }

    .food-live-category {
        display: inline-flex;
        min-height: 24px;
        align-items: center;
        padding: 0 8px;
        border-radius: 999px;
        background: #fff1f2;
        color: var(--food-primary);
        font-size: 9px;
        font-weight: 800;
        text-transform: uppercase;
    }

    .food-live-name {
        margin: 10px 0 5px;
        color: #20242a;
        font-size: 18px;
        line-height: 1.35;
        font-weight: 850;
    }

    .food-live-description {
        margin: 0 0 13px;
        color: #858b92;
        font-size: 11px;
        line-height: 1.6;
    }

    .food-live-bottom {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }

    .food-live-price {
        color: var(--food-primary);
        font-size: 19px;
        font-weight: 900;
    }

    .food-live-stock {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        color: #16a34a;
        font-size: 10px;
        font-weight: 700;
    }

    /* =========================================================
       FORM FOOTER
       ========================================================= */

    .food-form-footer {
        position: sticky;
        bottom: 15px;
        z-index: 20;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        padding: 14px 17px;
        border: 1px solid #dfe3e7;
        border-radius: 14px;
        background: rgba(255,255,255,.94);
        box-shadow: 0 12px 35px rgba(0,0,0,.12);
        backdrop-filter: blur(12px);
    }

    .food-footer-note {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #777e86;
        font-size: 11px;
    }

    .food-footer-note i {
        color: #16a34a;
    }

    .food-footer-actions {
        display: flex;
        gap: 9px;
    }

    /* =========================================================
       ALERT
       ========================================================= */

    .food-alert {
        display: flex;
        align-items: flex-start;
        gap: 11px;
        margin-bottom: 20px;
        padding: 14px 16px;
        border-radius: 11px;
        font-size: 12px;
        line-height: 1.5;
    }

    .food-alert-danger {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #991b1b;
    }

    .food-alert-success {
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        color: #166534;
    }

    .food-alert-icon {
        flex: 0 0 auto;
        margin-top: 1px;
    }

    /* =========================================================
       STEPS
       ========================================================= */

    .food-progress {
        display: flex;
        align-items: center;
        margin-bottom: 20px;
        padding: 17px 20px;
        background: #fff;
        border: 1px solid var(--food-border);
        border-radius: 13px;
        box-shadow: var(--food-shadow);
    }

    .food-progress-step {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #9ca3af;
        font-size: 11px;
        font-weight: 700;
    }

    .food-progress-number {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #eef0f2;
        color: #777e85;
        font-size: 10px;
        font-weight: 900;
    }

    .food-progress-step.active {
        color: var(--food-primary);
    }

    .food-progress-step.active .food-progress-number {
        background: var(--food-primary);
        color: #fff;
    }

    .food-progress-line {
        flex: 1;
        height: 1px;
        margin: 0 13px;
        background: #e3e6e9;
    }

    /* =========================================================
       MODAL
       ========================================================= */

    .food-confirm-modal {
        position: fixed;
        inset: 0;
        z-index: 5000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
        background: rgba(15,18,22,.55);
        backdrop-filter: blur(5px);
    }

    .food-confirm-modal.active {
        display: flex;
    }

    .food-modal-box {
        width: min(430px, 100%);
        padding: 25px;
        border-radius: 17px;
        background: #fff;
        box-shadow: 0 25px 70px rgba(0,0,0,.25);
    }

    .food-modal-icon {
        width: 52px;
        height: 52px;
        margin-bottom: 15px;
        border-radius: 50%;
        background: #fff7ed;
        color: #ea580c;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }

    .food-modal-title {
        margin: 0 0 7px;
        color: #20242a;
        font-size: 18px;
        font-weight: 850;
    }

    .food-modal-text {
        margin: 0;
        color: #7c8289;
        font-size: 12px;
        line-height: 1.6;
    }

    .food-modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 9px;
        margin-top: 22px;
    }

    /* =========================================================
       RESPONSIVE
       ========================================================= */

    @media (max-width: 1200px) {
        .food-form-layout {
            grid-template-columns: minmax(0, 1fr) 330px;
        }

        .food-form-grid-3 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .food-status-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 980px) {
        .food-form-layout {
            grid-template-columns: 1fr;
        }

        .food-sidebar {
            position: static;
        }

        .food-live-preview {
            max-width: 500px;
        }
    }

    @media (max-width: 700px) {
        .food-form-page {
            padding: 15px;
        }

        .food-page-header {
            align-items: flex-start;
            flex-direction: column;
        }

        .food-header-actions {
            width: 100%;
        }

        .food-header-actions .food-button {
            flex: 1;
        }

        .food-form-grid,
        .food-form-grid-3 {
            grid-template-columns: 1fr;
        }

        .food-form-group.full {
            grid-column: auto;
        }

        .food-card-body {
            padding: 16px;
        }

        .food-card-header {
            padding: 15px 16px;
        }

        .food-progress {
            overflow-x: auto;
        }

        .food-progress-step {
            white-space: nowrap;
        }

        .food-form-footer {
            align-items: flex-start;
            flex-direction: column;
        }

        .food-footer-actions {
            width: 100%;
        }

        .food-footer-actions .food-button {
            flex: 1;
        }

        .food-image-requirements {
            grid-template-columns: 1fr;
        }
    }
</style>


<div class="food-form-page">

    <div class="food-form-container">

        {{-- =====================================================
             PAGE HEADER
             ====================================================== --}}

        <div class="food-page-header">

            <div class="food-page-title-area">

                <a
                    href="{{ route('admin.foods.index') }}"
                    class="food-back-button"
                    title="Quay lại"
                >
                    <i class="fa-solid fa-arrow-left"></i>
                </a>

                <div>

                    <h1 class="food-page-title">

                        @if(isset($food))
                            Chỉnh sửa món ăn
                        @else
                            Thêm món ăn mới
                        @endif

                    </h1>

                    <p class="food-page-subtitle">

                        @if(isset($food))
                            Cập nhật thông tin món ăn
                            trong thực đơn MovieMate.
                        @else
                            Tạo món ăn hoặc combo mới
                            cho hệ thống MovieMate.
                        @endif

                    </p>

                </div>

            </div>


            <div class="food-header-actions">

                <a
                    href="{{ route('admin.foods.index') }}"
                    class="food-button food-button-secondary"
                >

                    <i class="fa-solid fa-list"></i>

                    Danh sách món

                </a>

                @if(isset($food))

                    <a
                        href="{{ route(
                            'foods.show',
                            $food->id
                        ) }}"
                        target="_blank"
                        class="food-button food-button-secondary"
                    >

                        <i
                            class="fa-solid
                            fa-arrow-up-right-from-square"
                        ></i>

                        Xem trang món

                    </a>

                @endif

            </div>

        </div>


        {{-- =====================================================
             ERROR ALERT
             ====================================================== --}}

        @if($errors->any())

            <div
                class="food-alert food-alert-danger"
            >

                <div class="food-alert-icon">
                    <i class="fa-solid fa-circle-exclamation"></i>
                </div>

                <div>

                    <strong>
                        Vui lòng kiểm tra lại thông tin.
                    </strong>

                    <ul style="margin:6px 0 0;padding-left:18px;">

                        @foreach($errors->all() as $error)

                            <li>
                                {{ $error }}
                            </li>

                        @endforeach

                    </ul>

                </div>

            </div>

        @endif


        {{-- =====================================================
             SUCCESS ALERT
             ====================================================== --}}

        @if(session('success'))

            <div
                class="food-alert food-alert-success"
            >

                <div class="food-alert-icon">
                    <i class="fa-solid fa-circle-check"></i>
                </div>

                <div>
                    {{ session('success') }}
                </div>

            </div>

        @endif


        {{-- =====================================================
             PROGRESS
             ====================================================== --}}

        <div class="food-progress">

            <div
                class="food-progress-step active"
            >

                <span class="food-progress-number">
                    1
                </span>

                Thông tin

            </div>

            <div class="food-progress-line"></div>

            <div
                class="food-progress-step active"
            >

                <span class="food-progress-number">
                    2
                </span>

                Giá & kho

            </div>

            <div class="food-progress-line"></div>

            <div
                class="food-progress-step active"
            >

                <span class="food-progress-number">
                    3
                </span>

                Hình ảnh

            </div>

            <div class="food-progress-line"></div>

            <div
                class="food-progress-step active"
            >

                <span class="food-progress-number">
                    4
                </span>

                Xuất bản

            </div>

        </div>


        {{-- =====================================================
             FORM
             ====================================================== --}}

        <form
            method="POST"
            action="{{
                isset($food)
                    ? route(
                        'admin.foods.update',
                        $food->id
                    )
                    : route(
                        'admin.foods.store'
                    )
            }}"
            enctype="multipart/form-data"
            id="food-form"
        >

            @csrf

            @if(isset($food))
                @method('PUT')
            @endif


            <div class="food-form-layout">


                {{-- =================================================
                     MAIN COLUMN
                     ================================================== --}}

                <div class="food-main-column">


                    {{-- =================================================
                         BASIC INFORMATION
                         ================================================== --}}

                    <div class="food-card-panel">

                        <div class="food-card-header">

                            <div class="food-card-header-left">

                                <div class="food-card-icon">

                                    <i
                                        class="fa-solid
                                        fa-utensils"
                                    ></i>

                                </div>

                                <div>

                                    <h2 class="food-card-title">
                                        Thông tin món ăn
                                    </h2>

                                    <p class="food-card-description">
                                        Thông tin cơ bản của sản phẩm.
                                    </p>

                                </div>

                            </div>

                        </div>


                        <div class="food-card-body">

                            <div class="food-form-grid">


                                {{-- NAME --}}

                                <div
                                    class="food-form-group full"
                                >

                                    <label
                                        class="food-label"
                                        for="name"
                                    >

                                        <span>
                                            Tên món ăn
                                            <span
                                                class="food-label-required"
                                            >
                                                *
                                            </span>
                                        </span>

                                        <span
                                            class="food-label-help"
                                        >
                                            Tối đa 150 ký tự
                                        </span>

                                    </label>


                                    <input
                                        type="text"
                                        id="name"
                                        name="name"
                                        class="food-input
                                            {{
                                                $errors->has('name')
                                                    ? 'is-invalid'
                                                    : ''
                                            }}"
                                        value="{{
                                            old(
                                                'name',
                                                $food->name
                                                ?? ''
                                            )
                                        }}"
                                        maxlength="150"
                                        placeholder="Ví dụ: Combo Bắp + Coca Cola"
                                        required
                                    >


                                    <div
                                        class="food-counter"
                                        id="name-counter"
                                    >

                                        <span>
                                            Tên món nên ngắn
                                            gọn và dễ nhớ.
                                        </span>

                                        <span>
                                            <strong>0</strong>/150
                                        </span>

                                    </div>


                                    @error('name')

                                        <div class="food-error">

                                            <i
                                                class="fa-solid
                                                fa-circle-exclamation"
                                            ></i>

                                            {{ $message }}

                                        </div>

                                    @enderror

                                </div>


                                {{-- CATEGORY --}}

                                <div class="food-form-group">

                                    <label
                                        class="food-label"
                                        for="category_id"
                                    >

                                        <span>
                                            Danh mục
                                            <span
                                                class="food-label-required"
                                            >
                                                *
                                            </span>
                                        </span>

                                    </label>


                                    <select
                                        name="category_id"
                                        id="category_id"
                                        class="food-select
                                            {{
                                                $errors->has(
                                                    'category_id'
                                                )
                                                    ? 'is-invalid'
                                                    : ''
                                            }}"
                                        required
                                    >

                                        <option value="">
                                            -- Chọn danh mục --
                                        </option>

                                        @foreach(
                                            $categories
                                            ?? []
                                            as $category
                                        )

                                            <option
                                                value="{{ $category->id }}"
                                                {{ old(
                                                    'category_id',
                                                    $food->category_id
                                                    ?? ''
                                                ) == $category->id
                                                    ? 'selected'
                                                    : '' }}
                                            >
                                                {{ $category->name }}
                                            </option>

                                        @endforeach

                                    </select>


                                    @error('category_id')

                                        <div class="food-error">
                                            <i
                                                class="fa-solid
                                                fa-circle-exclamation"
                                            ></i>
                                            {{ $message }}
                                        </div>

                                    @enderror

                                </div>


                                {{-- SKU --}}

                                <div class="food-form-group">

                                    <label
                                        class="food-label"
                                        for="sku"
                                    >

                                        <span>
                                            Mã sản phẩm
                                        </span>

                                        <span
                                            class="food-label-help"
                                        >
                                            Không bắt buộc
                                        </span>

                                    </label>


                                    <input
                                        type="text"
                                        id="sku"
                                        name="sku"
                                        class="food-input"
                                        value="{{
                                            old(
                                                'sku',
                                                $food->sku
                                                ?? ''
                                            )
                                        }}"
                                        maxlength="50"
                                        placeholder="Ví dụ: MM-CB-001"
                                    >

                                    <div class="food-help">
                                        Dùng mã riêng để quản lý
                                        món ăn trong kho.
                                    </div>

                                </div>


                                {{-- DESCRIPTION --}}

                                <div
                                    class="food-form-group full"
                                >

                                    <label
                                        class="food-label"
                                        for="description"
                                    >

                                        <span>
                                            Mô tả món ăn
                                        </span>

                                        <span
                                            class="food-label-help"
                                        >
                                            Tối đa 500 ký tự
                                        </span>

                                    </label>


                                    <textarea
                                        id="description"
                                        name="description"
                                        class="food-textarea"
                                        maxlength="500"
                                        placeholder="Mô tả thành phần, khẩu phần, hương vị..."
                                    >{{ old(
                                        'description',
                                        $food->description
                                        ?? ''
                                    ) }}</textarea>


                                    <div
                                        class="food-counter"
                                        id="description-counter"
                                    >

                                        <span>
                                            Mô tả giúp khách hàng
                                            hiểu rõ hơn về món.
                                        </span>

                                        <span>
                                            <strong>0</strong>/500
                                        </span>

                                    </div>

                                </div>


                                {{-- SHORT DESCRIPTION --}}

                                <div
                                    class="food-form-group full"
                                >

                                    <label
                                        class="food-label"
                                        for="short_description"
                                    >

                                        <span>
                                            Mô tả ngắn
                                        </span>

                                    </label>


                                    <textarea
                                        id="short_description"
                                        name="short_description"
                                        class="food-textarea"
                                        style="min-height:90px;"
                                        maxlength="200"
                                        placeholder="Mô tả ngắn hiển thị trên thẻ món ăn..."
                                    >{{ old(
                                        'short_description',
                                        $food->short_description
                                        ?? ''
                                    ) }}</textarea>

                                </div>


                                {{-- TAGS --}}

                                <div
                                    class="food-form-group full"
                                >

                                    <label class="food-label">

                                        <span>
                                            Từ khóa / Tags
                                        </span>

                                        <span
                                            class="food-label-help"
                                        >
                                            Enter để thêm
                                        </span>

                                    </label>


                                    <div
                                        class="food-tag-input-wrapper"
                                        id="tag-wrapper"
                                    >

                                        <div
                                            id="tag-list"
                                            style="
                                                display:flex;
                                                flex-wrap:wrap;
                                                gap:7px;
                                            "
                                        ></div>

                                        <input
                                            type="text"
                                            id="tag-input"
                                            class="food-tag-input"
                                            placeholder="Nhập tag..."
                                        >

                                    </div>


                                    <input
                                        type="hidden"
                                        name="tags"
                                        id="tags-hidden"
                                        value="{{
                                            old(
                                                'tags',
                                                $food->tags
                                                ?? ''
                                            )
                                        }}"
                                    >

                                    <div class="food-help">
                                        Ví dụ:
                                        combo, bán chạy, coca,
                                        bắp rang, đồ uống.
                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>


                    {{-- =================================================
                         PRICE & INVENTORY
                         ================================================== --}}

                    <div class="food-card-panel">

                        <div class="food-card-header">

                            <div class="food-card-header-left">

                                <div class="food-card-icon">

                                    <i
                                        class="fa-solid
                                        fa-money-bill-wave"
                                    ></i>

                                </div>

                                <div>

                                    <h2 class="food-card-title">
                                        Giá & kho hàng
                                    </h2>

                                    <p class="food-card-description">
                                        Thiết lập giá bán và số lượng
                                        tồn kho.
                                    </p>

                                </div>

                            </div>

                        </div>


                        <div class="food-card-body">

                            <div class="food-form-grid">


                                {{-- PRICE --}}

                                <div class="food-form-group">

                                    <label
                                        class="food-label"
                                        for="price"
                                    >

                                        <span>
                                            Giá bán
                                            <span
                                                class="food-label-required"
                                            >
                                                *
                                            </span>
                                        </span>

                                    </label>


                                    <div
                                        class="food-input-group
                                            has-suffix"
                                    >

                                        <input
                                            type="number"
                                            id="price"
                                            name="price"
                                            class="food-input
                                                {{
                                                    $errors->has('price')
                                                        ? 'is-invalid'
                                                        : ''
                                                }}"
                                            value="{{
                                                old(
                                                    'price',
                                                    $food->price
                                                    ?? ''
                                                )
                                            }}"
                                            min="0"
                                            step="1000"
                                            placeholder="50000"
                                            required
                                        >

                                        <span
                                            class="food-input-suffix"
                                        >
                                            VNĐ
                                        </span>

                                    </div>


                                    @error('price')

                                        <div class="food-error">
                                            <i
                                                class="fa-solid
                                                fa-circle-exclamation"
                                            ></i>
                                            {{ $message }}
                                        </div>

                                    @enderror

                                </div>


                                {{-- ORIGINAL PRICE --}}

                                <div class="food-form-group">

                                    <label
                                        class="food-label"
                                        for="original_price"
                                    >

                                        <span>
                                            Giá niêm yết
                                        </span>

                                        <span
                                            class="food-label-help"
                                        >
                                            Dùng khi có giảm giá
                                        </span>

                                    </label>


                                    <div
                                        class="food-input-group
                                            has-suffix"
                                    >

                                        <input
                                            type="number"
                                            id="original_price"
                                            name="original_price"
                                            class="food-input"
                                            value="{{
                                                old(
                                                    'original_price',
                                                    $food->original_price
                                                    ?? ''
                                                )
                                            }}"
                                            min="0"
                                            step="1000"
                                            placeholder="60000"
                                        >

                                        <span
                                            class="food-input-suffix"
                                        >
                                            VNĐ
                                        </span>

                                    </div>

                                </div>


                                {{-- STOCK --}}

                                <div class="food-form-group">

                                    <label
                                        class="food-label"
                                        for="stock"
                                    >

                                        <span>
                                            Tồn kho
                                            <span
                                                class="food-label-required"
                                            >
                                                *
                                            </span>
                                        </span>

                                    </label>


                                    <div
                                        class="food-input-group
                                            has-suffix"
                                    >

                                        <input
                                            type="number"
                                            id="stock"
                                            name="stock"
                                            class="food-input"
                                            value="{{
                                                old(
                                                    'stock',
                                                    $food->stock
                                                    ?? 0
                                                )
                                            }}"
                                            min="0"
                                            step="1"
                                            placeholder="100"
                                            required
                                        >

                                        <span
                                            class="food-input-suffix"
                                        >
                                            phần
                                        </span>

                                    </div>


                                    <div
                                        class="food-stock-indicator"
                                    >

                                        <div
                                            class="food-stock-bar"
                                        >

                                            <div
                                                class="food-stock-progress"
                                                id="stock-progress"
                                            ></div>

                                        </div>

                                        <div
                                            class="food-stock-text"
                                        >

                                            <span>
                                                Mức tồn kho
                                            </span>

                                            <span
                                                id="stock-status-text"
                                            >
                                                Chưa xác định
                                            </span>

                                        </div>

                                    </div>

                                </div>


                                {{-- LOW STOCK --}}

                                <div class="food-form-group">

                                    <label
                                        class="food-label"
                                        for="low_stock_threshold"
                                    >

                                        <span>
                                            Ngưỡng cảnh báo
                                        </span>

                                    </label>


                                    <div
                                        class="food-input-group
                                            has-suffix"
                                    >

                                        <input
                                            type="number"
                                            id="low_stock_threshold"
                                            name="low_stock_threshold"
                                            class="food-input"
                                            value="{{
                                                old(
                                                    'low_stock_threshold',
                                                    $food->low_stock_threshold
                                                    ?? 10
                                                )
                                            }}"
                                            min="0"
                                            step="1"
                                            placeholder="10"
                                        >

                                        <span
                                            class="food-input-suffix"
                                        >
                                            phần
                                        </span>

                                    </div>

                                    <div class="food-help">
                                        Hệ thống cảnh báo khi
                                        tồn kho thấp hơn mức này.
                                    </div>

                                </div>


                                {{-- PRICE PREVIEW --}}

                                <div
                                    class="food-form-group full"
                                >

                                    <div
                                        class="food-price-preview"
                                    >

                                        <div
                                            class="food-price-preview-label"
                                        >
                                            Giá hiển thị
                                        </div>

                                        <div
                                            class="food-price-preview-value"
                                            id="price-preview"
                                        >
                                            0đ
                                        </div>

                                        <div
                                            class="food-price-preview-note"
                                        >
                                            Đây là giá khách hàng
                                            sẽ nhìn thấy trên website.
                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>


                    {{-- =================================================
                         IMAGE
                         ================================================== --}}

                    <div class="food-card-panel">

                        <div class="food-card-header">

                            <div class="food-card-header-left">

                                <div class="food-card-icon">

                                    <i
                                        class="fa-solid
                                        fa-image"
                                    ></i>

                                </div>

                                <div>

                                    <h2 class="food-card-title">
                                        Hình ảnh món ăn
                                    </h2>

                                    <p class="food-card-description">
                                        Tải ảnh đại diện cho món ăn.
                                    </p>

                                </div>

                            </div>

                        </div>


                        <div class="food-card-body">

                            <div
                                class="food-image-upload"
                                id="food-image-upload"
                            >

                                <div
                                    class="food-upload-placeholder"
                                    id="upload-placeholder"
                                >

                                    <div
                                        class="food-upload-icon"
                                    >

                                        <i
                                            class="fa-solid
                                            fa-cloud-arrow-up"
                                        ></i>

                                    </div>

                                    <h3
                                        class="food-upload-title"
                                    >
                                        Kéo thả ảnh vào đây
                                    </h3>

                                    <p
                                        class="food-upload-description"
                                    >
                                        hoặc chọn ảnh từ máy tính.
                                        <br>
                                        JPG, JPEG, PNG, WEBP —
                                        tối đa 5MB.
                                    </p>

                                    <label
                                        for="image"
                                        class="food-upload-button"
                                    >

                                        <i
                                            class="fa-solid
                                            fa-folder-open"
                                        ></i>

                                        Chọn ảnh

                                    </label>

                                </div>


                                <input
                                    type="file"
                                    name="image"
                                    id="image"
                                    class="food-file-input"
                                    accept="image/jpeg,image/png,image/webp"
                                >


                                <div
                                    class="food-image-preview"
                                    id="image-preview"
                                >

                                    <img
                                        src=""
                                        alt="Preview"
                                        id="preview-image"
                                    >

                                    <button
                                        type="button"
                                        class="food-image-remove"
                                        id="remove-image"
                                    >

                                        <i
                                            class="fa-solid
                                            fa-trash"
                                        ></i>

                                    </button>

                                    <div
                                        class="food-image-overlay"
                                    >

                                        <p
                                            class="food-image-file-name"
                                            id="preview-file-name"
                                        >
                                            image.jpg
                                        </p>

                                        <p
                                            class="food-image-file-size"
                                            id="preview-file-size"
                                        >
                                            0 KB
                                        </p>

                                    </div>

                                </div>

                            </div>


                            <div
                                class="food-image-requirements"
                            >

                                <div
                                    class="food-requirement"
                                >

                                    <i
                                        class="fa-solid
                                        fa-circle-check"
                                    ></i>

                                    Tỷ lệ đề xuất 4:3

                                </div>

                                <div
                                    class="food-requirement"
                                >

                                    <i
                                        class="fa-solid
                                        fa-circle-check"
                                    ></i>

                                    Độ phân giải tối thiểu 800×600

                                </div>

                                <div
                                    class="food-requirement"
                                >

                                    <i
                                        class="fa-solid
                                        fa-circle-check"
                                    ></i>

                                    Dung lượng tối đa 5MB

                                </div>

                                <div
                                    class="food-requirement"
                                >

                                    <i
                                        class="fa-solid
                                        fa-circle-check"
                                    ></i>

                                    Nền ảnh rõ ràng

                                </div>

                            </div>


                            {{-- CURRENT IMAGE --}}

                            @if(
                                isset($food)
                                && $food->image
                            )

                                <div
                                    style="
                                        margin-top:18px;
                                        padding-top:18px;
                                        border-top:1px solid #eee;
                                    "
                                >

                                    <div
                                        class="food-label"
                                        style="
                                            margin-bottom:10px;
                                        "
                                    >
                                        Ảnh hiện tại
                                    </div>

                                    <img
                                        src="{{
                                            filter_var(
                                                $food->image,
                                                FILTER_VALIDATE_URL
                                            )
                                                ? $food->image
                                                : asset(
                                                    'storage/' .
                                                    $food->image
                                                )
                                        }}"
                                        alt="{{ $food->name }}"
                                        style="
                                            width:180px;
                                            height:130px;
                                            object-fit:cover;
                                            border-radius:10px;
                                            border:1px solid #ddd;
                                        "
                                    >

                                </div>

                            @endif

                        </div>

                    </div>


                    {{-- =================================================
                         PUBLISH SETTINGS
                         ================================================== --}}

                    <div class="food-card-panel">

                        <div class="food-card-header">

                            <div class="food-card-header-left">

                                <div class="food-card-icon">

                                    <i
                                        class="fa-solid
                                        fa-sliders"
                                    ></i>

                                </div>

                                <div>

                                    <h2 class="food-card-title">
                                        Cài đặt hiển thị
                                    </h2>

                                    <p class="food-card-description">
                                        Kiểm soát trạng thái và vị trí
                                        hiển thị của món ăn.
                                    </p>

                                </div>

                            </div>

                        </div>


                        <div class="food-card-body">

                            <div
                                class="food-status-grid"
                            >

                                {{-- ACTIVE --}}

                                <div
                                    class="food-status-option"
                                >

                                    <input
                                        type="radio"
                                        name="status"
                                        id="status-active"
                                        value="1"
                                        {{
                                            old(
                                                'status',
                                                $food->status
                                                ?? 1
                                            ) == 1
                                                ? 'checked'
                                                : ''
                                        }}
                                    >

                                    <label
                                        for="status-active"
                                        class="food-status-label"
                                    >

                                        <div
                                            class="food-status-icon"
                                        >

                                            <i
                                                class="fa-solid
                                                fa-circle-check"
                                            ></i>

                                        </div>

                                        <div
                                            class="food-status-content"
                                        >

                                            <strong>
                                                Đang bán
                                            </strong>

                                            <span>
                                                Khách hàng có thể
                                                đặt món này.
                                            </span>

                                        </div>

                                    </label>

                                </div>


                                {{-- INACTIVE --}}

                                <div
                                    class="food-status-option"
                                >

                                    <input
                                        type="radio"
                                        name="status"
                                        id="status-inactive"
                                        value="0"
                                        {{
                                            old(
                                                'status',
                                                $food->status
                                                ?? 1
                                            ) == 0
                                                ? 'checked'
                                                : ''
                                        }}
                                    >

                                    <label
                                        for="status-inactive"
                                        class="food-status-label"
                                    >

                                        <div
                                            class="food-status-icon"
                                        >

                                            <i
                                                class="fa-solid
                                                fa-pause"
                                            ></i>

                                        </div>

                                        <div
                                            class="food-status-content"
                                        >

                                            <strong>
                                                Tạm ngừng
                                            </strong>

                                            <span>
                                                Ẩn món khỏi hệ thống
                                                đặt hàng.
                                            </span>

                                        </div>

                                    </label>

                                </div>


                                {{-- SOLD OUT --}}

                                <div
                                    class="food-status-option"
                                >

                                    <input
                                        type="radio"
                                        name="status"
                                        id="status-soldout"
                                        value="2"
                                        {{
                                            old(
                                                'status',
                                                $food->status
                                                ?? 1
                                            ) == 2
                                                ? 'checked'
                                                : ''
                                        }}
                                    >

                                    <label
                                        for="status-soldout"
                                        class="food-status-label"
                                    >

                                        <div
                                            class="food-status-icon"
                                        >

                                            <i
                                                class="fa-solid
                                                fa-box-open"
                                            ></i>

                                        </div>

                                        <div
                                            class="food-status-content"
                                        >

                                            <strong>
                                                Hết hàng
                                            </strong>

                                            <span>
                                                Hiển thị món nhưng
                                                không cho đặt.
                                            </span>

                                        </div>

                                    </label>

                                </div>

                            </div>


                            <div
                                style="
                                    margin-top:20px;
                                    border-top:1px solid #f0f1f2;
                                "
                            >

                                {{-- FEATURED --}}

                                <div
                                    class="food-toggle-row"
                                >

                                    <div
                                        class="food-toggle-info"
                                    >

                                        <strong>
                                            Món nổi bật
                                        </strong>

                                        <span>
                                            Hiển thị món ở khu vực
                                            món nổi bật.
                                        </span>

                                    </div>

                                    <div
                                        class="food-toggle"
                                    >

                                        <input
                                            type="checkbox"
                                            name="is_featured"
                                            value="1"
                                            id="is_featured"
                                            {{
                                                old(
                                                    'is_featured',
                                                    $food->is_featured
                                                    ?? 0
                                                )
                                                    ? 'checked'
                                                    : ''
                                            }}
                                        >

                                        <label
                                            for="is_featured"
                                            class="food-toggle-label"
                                        ></label>

                                    </div>

                                </div>


                                {{-- POPULAR --}}

                                <div
                                    class="food-toggle-row"
                                >

                                    <div
                                        class="food-toggle-info"
                                    >

                                        <strong>
                                            Hiển thị bán chạy
                                        </strong>

                                        <span>
                                            Cho phép món xuất hiện
                                            trong danh sách bán chạy.
                                        </span>

                                    </div>

                                    <div
                                        class="food-toggle"
                                    >

                                        <input
                                            type="checkbox"
                                            name="show_popular"
                                            value="1"
                                            id="show_popular"
                                            {{
                                                old(
                                                    'show_popular',
                                                    $food->show_popular
                                                    ?? 0
                                                )
                                                    ? 'checked'
                                                    : ''
                                            }}
                                        >

                                        <label
                                            for="show_popular"
                                            class="food-toggle-label"
                                        ></label>

                                    </div>

                                </div>


                                {{-- HOME --}}

                                <div
                                    class="food-toggle-row"
                                >

                                    <div
                                        class="food-toggle-info"
                                    >

                                        <strong>
                                            Hiển thị trang chủ
                                        </strong>

                                        <span>
                                            Cho phép món xuất hiện
                                            trên trang chủ MovieMate.
                                        </span>

                                    </div>

                                    <div
                                        class="food-toggle"
                                    >

                                        <input
                                            type="checkbox"
                                            name="show_home"
                                            value="1"
                                            id="show_home"
                                            {{
                                                old(
                                                    'show_home',
                                                    $food->show_home
                                                    ?? 0
                                                )
                                                    ? 'checked'
                                                    : ''
                                            }}
                                        >

                                        <label
                                            for="show_home"
                                            class="food-toggle-label"
                                        ></label>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>


                    {{-- =================================================
                         EXTRA INFORMATION
                         ================================================== --}}

                    <div class="food-card-panel">

                        <div class="food-card-header">

                            <div class="food-card-header-left">

                                <div class="food-card-icon">

                                    <i
                                        class="fa-solid
                                        fa-circle-info"
                                    ></i>

                                </div>

                                <div>

                                    <h2 class="food-card-title">
                                        Thông tin bổ sung
                                    </h2>

                                    <p class="food-card-description">
                                        Các thông tin phục vụ quản lý
                                        và hiển thị sản phẩm.
                                    </p>

                                </div>

                            </div>

                        </div>


                        <div class="food-card-body">

                            <div class="food-form-grid">


                                {{-- UNIT --}}

                                <div class="food-form-group">

                                    <label
                                        class="food-label"
                                        for="unit"
                                    >
                                        Đơn vị tính
                                    </label>

                                    <select
                                        name="unit"
                                        id="unit"
                                        class="food-select"
                                    >

                                        <option
                                            value="phan"
                                            {{
                                                old(
                                                    'unit',
                                                    $food->unit
                                                    ?? 'phan'
                                                ) === 'phan'
                                                    ? 'selected'
                                                    : ''
                                            }}
                                        >
                                            Phần
                                        </option>

                                        <option
                                            value="ly"
                                            {{
                                                old(
                                                    'unit',
                                                    $food->unit
                                                    ?? ''
                                                ) === 'ly'
                                                    ? 'selected'
                                                    : ''
                                            }}
                                        >
                                            Ly
                                        </option>

                                        <option
                                            value="chai"
                                            {{
                                                old(
                                                    'unit',
                                                    $food->unit
                                                    ?? ''
                                                ) === 'chai'
                                                    ? 'selected'
                                                    : ''
                                            }}
                                        >
                                            Chai
                                        </option>

                                        <option
                                            value="hop"
                                            {{
                                                old(
                                                    'unit',
                                                    $food->unit
                                                    ?? ''
                                                ) === 'hop'
                                                    ? 'selected'
                                                    : ''
                                            }}
                                        >
                                            Hộp
                                        </option>

                                        <option
                                            value="combo"
                                            {{
                                                old(
                                                    'unit',
                                                    $food->unit
                                                    ?? ''
                                                ) === 'combo'
                                                    ? 'selected'
                                                    : ''
                                            }}
                                        >
                                            Combo
                                        </option>

                                    </select>

                                </div>


                                {{-- SORT ORDER --}}

                                <div class="food-form-group">

                                    <label
                                        class="food-label"
                                        for="sort_order"
                                    >
                                        Thứ tự hiển thị
                                    </label>

                                    <input
                                        type="number"
                                        name="sort_order"
                                        id="sort_order"
                                        class="food-input"
                                        min="0"
                                        value="{{
                                            old(
                                                'sort_order',
                                                $food->sort_order
                                                ?? 0
                                            )
                                        }}"
                                        placeholder="0"
                                    >

                                    <div class="food-help">
                                        Số nhỏ hơn sẽ được ưu tiên
                                        hiển thị trước.
                                    </div>

                                </div>


                                {{-- PREPARATION TIME --}}

                                <div class="food-form-group">

                                    <label
                                        class="food-label"
                                        for="preparation_time"
                                    >
                                        Thời gian chuẩn bị
                                    </label>

                                    <div
                                        class="food-input-group
                                            has-suffix"
                                    >

                                        <input
                                            type="number"
                                            name="preparation_time"
                                            id="preparation_time"
                                            class="food-input"
                                            min="0"
                                            value="{{
                                                old(
                                                    'preparation_time',
                                                    $food->preparation_time
                                                    ?? 5
                                                )
                                            }}"
                                            placeholder="5"
                                        >

                                        <span
                                            class="food-input-suffix"
                                        >
                                            phút
                                        </span>

                                    </div>

                                </div>


                                {{-- CALORIES --}}

                                <div class="food-form-group">

                                    <label
                                        class="food-label"
                                        for="calories"
                                    >
                                        Calories
                                    </label>

                                    <div
                                        class="food-input-group
                                            has-suffix"
                                    >

                                        <input
                                            type="number"
                                            name="calories"
                                            id="calories"
                                            class="food-input"
                                            min="0"
                                            value="{{
                                                old(
                                                    'calories',
                                                    $food->calories
                                                    ?? ''
                                                )
                                            }}"
                                            placeholder="450"
                                        >

                                        <span
                                            class="food-input-suffix"
                                        >
                                            kcal
                                        </span>

                                    </div>

                                </div>


                                {{-- NOTE --}}

                                <div
                                    class="food-form-group full"
                                >

                                    <label
                                        class="food-label"
                                        for="admin_note"
                                    >
                                        Ghi chú nội bộ
                                    </label>

                                    <textarea
                                        name="admin_note"
                                        id="admin_note"
                                        class="food-textarea"
                                        placeholder="Ghi chú chỉ dành cho quản trị viên..."
                                    >{{ old(
                                        'admin_note',
                                        $food->admin_note
                                        ?? ''
                                    ) }}</textarea>

                                    <div class="food-help">
                                        Nội dung này không hiển thị
                                        cho khách hàng.
                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                {{-- =================================================
                     SIDEBAR
                     ================================================== --}}

                <aside class="food-sidebar">


                    {{-- =================================================
                         LIVE PREVIEW
                         ================================================== --}}

                    <div class="food-card-panel">

                        <div class="food-card-header">

                            <div class="food-card-header-left">

                                <div class="food-card-icon">

                                    <i
                                        class="fa-solid
                                        fa-eye"
                                    ></i>

                                </div>

                                <div>

                                    <h2 class="food-card-title">
                                        Xem trước
                                    </h2>

                                    <p class="food-card-description">
                                        Preview sản phẩm.
                                    </p>

                                </div>

                            </div>

                        </div>


                        <div class="food-card-body">

                            <div
                                class="food-live-preview"
                            >

                                <div
                                    id="live-image-container"
                                >

                                    <div
                                        class="food-live-image-placeholder"
                                    >

                                        <i
                                            class="fa-solid
                                            fa-utensils"
                                        ></i>

                                    </div>

                                </div>


                                <div
                                    class="food-live-body"
                                >

                                    <span
                                        class="food-live-category"
                                        id="live-category"
                                    >
                                        Danh mục
                                    </span>


                                    <h3
                                        class="food-live-name"
                                        id="live-name"
                                    >
                                        Tên món ăn
                                    </h3>


                                    <p
                                        class="food-live-description"
                                        id="live-description"
                                    >
                                        Mô tả món ăn sẽ được hiển thị
                                        tại đây.
                                    </p>


                                    <div
                                        class="food-live-bottom"
                                    >

                                        <span
                                            class="food-live-price"
                                            id="live-price"
                                        >
                                            0đ
                                        </span>


                                        <span
                                            class="food-live-stock"
                                            id="live-stock"
                                        >

                                            <i
                                                class="fa-solid
                                                fa-circle-check"
                                            ></i>

                                            Còn hàng

                                        </span>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>


                    {{-- =================================================
                         QUICK CHECK
                         ================================================== --}}

                    <div class="food-card-panel">

                        <div class="food-card-header">

                            <div
                                class="food-card-header-left"
                            >

                                <div
                                    class="food-card-icon"
                                >

                                    <i
                                        class="fa-solid
                                        fa-list-check"
                                    ></i>

                                </div>

                                <div>

                                    <h2
                                        class="food-card-title"
                                    >
                                        Kiểm tra nhanh
                                    </h2>

                                    <p
                                        class="food-card-description"
                                    >
                                        Kiểm tra trước khi lưu.
                                    </p>

                                </div>

                            </div>

                        </div>


                        <div class="food-card-body">

                            <div
                                class="food-checkbox-list"
                            >

                                <label
                                    class="food-checkbox"
                                >

                                    <input
                                        type="checkbox"
                                        id="check-name"
                                    >

                                    <span
                                        class="food-checkbox-text"
                                    >

                                        <strong>
                                            Tên món hợp lệ
                                        </strong>

                                        <span>
                                            Tên món không được để trống.
                                        </span>

                                    </span>

                                </label>


                                <label
                                    class="food-checkbox"
                                >

                                    <input
                                        type="checkbox"
                                        id="check-price"
                                    >

                                    <span
                                        class="food-checkbox-text"
                                    >

                                        <strong>
                                            Giá bán hợp lệ
                                        </strong>

                                        <span>
                                            Giá phải lớn hơn hoặc bằng 0.
                                        </span>

                                    </span>

                                </label>


                                <label
                                    class="food-checkbox"
                                >

                                    <input
                                        type="checkbox"
                                        id="check-category"
                                    >

                                    <span
                                        class="food-checkbox-text"
                                    >

                                        <strong>
                                            Đã chọn danh mục
                                        </strong>

                                        <span>
                                            Món ăn cần có danh mục.
                                        </span>

                                    </span>

                                </label>


                                <label
                                    class="food-checkbox"
                                >

                                    <input
                                        type="checkbox"
                                        id="check-image"
                                    >

                                    <span
                                        class="food-checkbox-text"
                                    >

                                        <strong>
                                            Hình ảnh
                                        </strong>

                                        <span>
                                            Nên có ảnh đại diện
                                            cho món ăn.
                                        </span>

                                    </span>

                                </label>

                            </div>

                        </div>

                    </div>


                    {{-- =================================================
                         TIPS
                         ================================================== --}}

                    <div
                        class="food-card-panel"
                    >

                        <div
                            class="food-card-header"
                        >

                            <div
                                class="food-card-header-left"
                            >

                                <div
                                    class="food-card-icon"
                                >

                                    <i
                                        class="fa-solid
                                        fa-lightbulb"
                                    ></i>

                                </div>

                                <div>

                                    <h2
                                        class="food-card-title"
                                    >
                                        Mẹo
                                    </h2>

                                </div>

                            </div>

                        </div>


                        <div
                            class="food-card-body"
                        >

                            <ul
                                style="
                                    margin:0;
                                    padding-left:17px;
                                    color:#858b92;
                                    font-size:11px;
                                    line-height:1.8;
                                "
                            >

                                <li>
                                    Dùng ảnh món ăn rõ nét.
                                </li>

                                <li>
                                    Tên món nên ngắn gọn.
                                </li>

                                <li>
                                    Giá bán cần chính xác.
                                </li>

                                <li>
                                    Kiểm tra tồn kho thường xuyên.
                                </li>

                                <li>
                                    Đánh dấu món nổi bật cho
                                    combo bán chạy.
                                </li>

                            </ul>

                        </div>

                    </div>

                </aside>

            </div>


            {{-- =====================================================
                 FORM FOOTER
                 ====================================================== --}}

            <div class="food-form-footer">

                <div class="food-footer-note">

                    <i
                        class="fa-solid
                        fa-shield-halved"
                    ></i>

                    Thông tin sẽ được lưu bảo mật
                    trong hệ thống MovieMate.

                </div>


                <div class="food-footer-actions">

                    <a
                        href="{{ route(
                            'admin.foods.index'
                        ) }}"
                        class="food-button
                            food-button-secondary"
                    >

                        Hủy

                    </a>


                    <button
                        type="button"
                        class="food-button
                            food-button-secondary"
                        id="preview-submit-button"
                    >

                        <i
                            class="fa-solid
                            fa-eye"
                        ></i>

                        Kiểm tra

                    </button>


                    <button
                        type="submit"
                        class="food-button
                            food-button-primary"
                        id="submit-button"
                    >

                        <i
                            class="fa-solid
                            {{
                                isset($food)
                                    ? 'fa-floppy-disk'
                                    : 'fa-plus'
                            }}"
                        ></i>

                        @if(isset($food))
                            Lưu thay đổi
                        @else
                            Tạo món ăn
                        @endif

                    </button>

                </div>

            </div>

        </form>

    </div>

</div>


{{-- =========================================================
     CONFIRM MODAL
     ========================================================== --}}

<div
    class="food-confirm-modal"
    id="food-confirm-modal"
>

    <div class="food-modal-box">

        <div class="food-modal-icon">

            <i
                class="fa-solid
                fa-circle-question"
            ></i>

        </div>

        <h3 class="food-modal-title">
            Kiểm tra thông tin
        </h3>

        <p class="food-modal-text">
            Bạn đã kiểm tra các thông tin cơ bản của món ăn.
            Bạn có muốn tiếp tục lưu sản phẩm này không?
        </p>

        <div class="food-modal-actions">

            <button
                type="button"
                class="food-button
                    food-button-secondary"
                id="modal-cancel"
            >
                Kiểm tra lại
            </button>

            <button
                type="button"
                class="food-button
                    food-button-primary"
                id="modal-confirm"
            >
                Tiếp tục lưu
            </button>

        </div>

    </div>

</div>


<script>
    /* =========================================================
       MOVIEMATE FOOD FORM JAVASCRIPT
       ========================================================= */

    document.addEventListener(
        'DOMContentLoaded',
        function () {


            /* =====================================================
               ELEMENTS
               ====================================================== */

            const form =
                document.getElementById(
                    'food-form'
                );

            const nameInput =
                document.getElementById(
                    'name'
                );

            const descriptionInput =
                document.getElementById(
                    'description'
                );

            const shortDescriptionInput =
                document.getElementById(
                    'short_description'
                );

            const categoryInput =
                document.getElementById(
                    'category_id'
                );

            const priceInput =
                document.getElementById(
                    'price'
                );

            const originalPriceInput =
                document.getElementById(
                    'original_price'
                );

            const stockInput =
                document.getElementById(
                    'stock'
                );

            const lowStockInput =
                document.getElementById(
                    'low_stock_threshold'
                );

            const imageInput =
                document.getElementById(
                    'image'
                );

            const imageUpload =
                document.getElementById(
                    'food-image-upload'
                );

            const uploadPlaceholder =
                document.getElementById(
                    'upload-placeholder'
                );

            const imagePreview =
                document.getElementById(
                    'image-preview'
                );

            const previewImage =
                document.getElementById(
                    'preview-image'
                );

            const removeImageButton =
                document.getElementById(
                    'remove-image'
                );

            const pricePreview =
                document.getElementById(
                    'price-preview'
                );

            const liveName =
                document.getElementById(
                    'live-name'
                );

            const liveDescription =
                document.getElementById(
                    'live-description'
                );

            const liveCategory =
                document.getElementById(
                    'live-category'
                );

            const livePrice =
                document.getElementById(
                    'live-price'
                );

            const liveStock =
                document.getElementById(
                    'live-stock'
                );

            const liveImageContainer =
                document.getElementById(
                    'live-image-container'
                );

            const stockProgress =
                document.getElementById(
                    'stock-progress'
                );

            const stockStatusText =
                document.getElementById(
                    'stock-status-text'
                );

            const submitButton =
                document.getElementById(
                    'submit-button'
                );

            const previewSubmitButton =
                document.getElementById(
                    'preview-submit-button'
                );

            const modal =
                document.getElementById(
                    'food-confirm-modal'
                );

            const modalCancel =
                document.getElementById(
                    'modal-cancel'
                );

            const modalConfirm =
                document.getElementById(
                    'modal-confirm'
                );


            /* =====================================================
               FORMAT MONEY
               ====================================================== */

            function formatMoney(
                value
            ) {

                const number =
                    Number(
                        value || 0
                    );

                return new Intl.NumberFormat(
                    'vi-VN'
                ).format(
                    number
                ) + 'đ';

            }


            /* =====================================================
               ESCAPE HTML
               ====================================================== */

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


            /* =====================================================
               COUNTERS
               ====================================================== */

            function updateCounter(
                input,
                counterId,
                max
            ) {

                if (!input) {
                    return;
                }

                const counter =
                    document.getElementById(
                        counterId
                    );

                if (!counter) {
                    return;
                }

                const strong =
                    counter.querySelector(
                        'strong'
                    );

                const length =
                    input.value.length;

                strong.textContent =
                    length;

                counter.classList
                    .remove(
                        'warning',
                        'danger'
                    );

                if (
                    length >=
                    max * 0.8
                ) {

                    counter.classList
                        .add(
                            'warning'
                        );

                }

                if (
                    length >= max
                ) {

                    counter.classList
                        .add(
                            'danger'
                        );

                }

            }


            if (nameInput) {

                nameInput.addEventListener(
                    'input',
                    function () {

                        updateCounter(
                            nameInput,
                            'name-counter',
                            150
                        );

                        updateLivePreview();

                    }
                );

                updateCounter(
                    nameInput,
                    'name-counter',
                    150
                );

            }


            if (descriptionInput) {

                descriptionInput.addEventListener(
                    'input',
                    function () {

                        updateCounter(
                            descriptionInput,
                            'description-counter',
                            500
                        );

                        updateLivePreview();

                    }
                );

                updateCounter(
                    descriptionInput,
                    'description-counter',
                    500
                );

            }


            if (shortDescriptionInput) {

                shortDescriptionInput
                    .addEventListener(
                        'input',
                        updateLivePreview
                    );

            }


            /* =====================================================
               LIVE PREVIEW
               ====================================================== */

            function updateLivePreview() {

                if (liveName) {

                    liveName.textContent =
                        nameInput &&
                        nameInput.value.trim()
                            ? nameInput.value.trim()
                            : 'Tên món ăn';

                }


                if (liveDescription) {

                    const description =
                        descriptionInput &&
                        descriptionInput.value.trim()
                            ? descriptionInput.value.trim()
                            : (
                                shortDescriptionInput &&
                                shortDescriptionInput.value.trim()
                                    ? shortDescriptionInput.value.trim()
                                    : 'Mô tả món ăn sẽ được hiển thị tại đây.'
                            );

                    liveDescription.textContent =
                        description;

                }


                if (
                    categoryInput &&
                    liveCategory
                ) {

                    const option =
                        categoryInput
                            .options[
                                categoryInput.selectedIndex
                            ];

                    liveCategory.textContent =
                        option &&
                        option.value
                            ? option.textContent.trim()
                            : 'Danh mục';

                }


                if (
                    priceInput &&
                    livePrice
                ) {

                    livePrice.textContent =
                        formatMoney(
                            priceInput.value
                        );

                }

            }


            if (categoryInput) {

                categoryInput.addEventListener(
                    'change',
                    function () {

                        updateLivePreview();

                        updateChecks();

                    }
                );

            }


            if (priceInput) {

                priceInput.addEventListener(
                    'input',
                    function () {

                        if (
                            pricePreview
                        ) {

                            pricePreview.textContent =
                                formatMoney(
                                    priceInput.value
                                );

                        }

                        updateLivePreview();

                        updateChecks();

                    }
                );

            }


            if (originalPriceInput) {

                originalPriceInput
                    .addEventListener(
                        'input',
                        function () {

                            validateOriginalPrice();

                        }
                    );

            }


            function validateOriginalPrice() {

                if (
                    !originalPriceInput ||
                    !priceInput
                ) {
                    return;
                }

                const price =
                    Number(
                        priceInput.value
                    );

                const original =
                    Number(
                        originalPriceInput.value
                    );

                if (
                    original > 0 &&
                    price > original
                ) {

                    originalPriceInput
                        .style.borderColor =
                        '#f59e0b';

                } else {

                    originalPriceInput
                        .style.borderColor =
                        '';

                }

            }


            /* =====================================================
               STOCK
               ====================================================== */

            function updateStock() {

                if (!stockInput) {
                    return;
                }

                const stock =
                    Number(
                        stockInput.value || 0
                    );

                const threshold =
                    Number(
                        lowStockInput
                            ? lowStockInput.value
                            : 10
                    );

                let percentage = 0;

                if (
                    stock > 0
                ) {

                    percentage =
                        Math.min(
                            100,
                            Math.max(
                                10,
                                stock
                            )
                        );

                }


                if (
                    stockProgress
                ) {

                    stockProgress.style.width =
                        `${percentage}%`;

                }


                if (
                    stockStatusText
                ) {

                    if (
                        stock <= 0
                    ) {

                        stockStatusText.textContent =
                            'Hết hàng';

                        stockStatusText.style.color =
                            '#dc2626';

                        if (liveStock) {

                            liveStock.innerHTML = `
                                <i
                                    class="fa-solid
                                    fa-circle-xmark"
                                ></i>
                                Hết hàng
                            `;

                            liveStock.style.color =
                                '#dc2626';

                        }

                    } else if (
                        stock <= threshold
                    ) {

                        stockStatusText.textContent =
                            'Sắp hết';

                        stockStatusText.style.color =
                            '#d97706';

                        if (liveStock) {

                            liveStock.innerHTML = `
                                <i
                                    class="fa-solid
                                    fa-triangle-exclamation"
                                ></i>
                                Sắp hết
                            `;

                            liveStock.style.color =
                                '#d97706';

                        }

                    } else {

                        stockStatusText.textContent =
                            'Còn nhiều';

                        stockStatusText.style.color =
                            '#16a34a';

                        if (liveStock) {

                            liveStock.innerHTML = `
                                <i
                                    class="fa-solid
                                    fa-circle-check"
                                ></i>
                                Còn hàng
                            `;

                            liveStock.style.color =
                                '#16a34a';

                        }

                    }

                }

            }


            if (stockInput) {

                stockInput.addEventListener(
                    'input',
                    function () {

                        updateStock();

                        updateChecks();

                    }
                );

            }


            if (lowStockInput) {

                lowStockInput.addEventListener(
                    'input',
                    updateStock
                );

            }


            updateStock();


            /* =====================================================
               IMAGE PREVIEW
               ====================================================== */

            function handleImageFile(
                file
            ) {

                if (!file) {
                    return;
                }


                const allowedTypes = [
                    'image/jpeg',
                    'image/png',
                    'image/webp'
                ];


                if (
                    !allowedTypes.includes(
                        file.type
                    )
                ) {

                    alert(
                        'Chỉ cho phép JPG, PNG hoặc WEBP.'
                    );

                    imageInput.value = '';

                    return;

                }


                const maxSize =
                    5 * 1024 * 1024;


                if (
                    file.size > maxSize
                ) {

                    alert(
                        'Ảnh không được vượt quá 5MB.'
                    );

                    imageInput.value = '';

                    return;

                }


                const reader =
                    new FileReader();


                reader.onload =
                    function (event) {

                        if (
                            previewImage
                        ) {

                            previewImage.src =
                                event.target.result;

                        }


                        if (
                            uploadPlaceholder
                        ) {

                            uploadPlaceholder
                                .style
                                .display =
                                'none';

                        }


                        if (
                            imagePreview
                        ) {

                            imagePreview
                                .classList
                                .add(
                                    'active'
                                );

                        }


                        if (
                            imageUpload
                        ) {

                            imageUpload
                                .classList
                                .add(
                                    'has-image'
                                );

                        }


                        const fileName =
                            document.getElementById(
                                'preview-file-name'
                            );

                        const fileSize =
                            document.getElementById(
                                'preview-file-size'
                            );


                        if (fileName) {

                            fileName.textContent =
                                file.name;

                        }


                        if (fileSize) {

                            fileSize.textContent =
                                formatFileSize(
                                    file.size
                                );

                        }


                        updateLiveImage(
                            event.target.result
                        );

                        const checkImage =
                            document.getElementById(
                                'check-image'
                            );

                        if (checkImage) {
                            checkImage.checked =
                                true;
                        }

                    };


                reader.readAsDataURL(
                    file
                );

            }


            function formatFileSize(
                bytes
            ) {

                if (
                    bytes < 1024
                ) {

                    return bytes + ' B';

                }

                if (
                    bytes < 1024 * 1024
                ) {

                    return (
                        bytes / 1024
                    ).toFixed(1)
                    + ' KB';

                }

                return (
                    bytes /
                    (1024 * 1024)
                ).toFixed(2)
                + ' MB';

            }


            function updateLiveImage(
                src
            ) {

                if (
                    !liveImageContainer
                ) {
                    return;
                }

                liveImageContainer.innerHTML = `

                    <img
                        src="${src}"
                        alt="Preview"
                        class="food-live-image"
                    >

                `;

            }


            if (imageInput) {

                imageInput.addEventListener(
                    'change',
                    function () {

                        const file =
                            this.files &&
                            this.files[0];

                        handleImageFile(
                            file
                        );

                        updateChecks();

                    }
                );

            }


            /* =====================================================
               DRAG DROP
               ====================================================== */

            if (imageUpload) {

                [
                    'dragenter',
                    'dragover'
                ].forEach(
                    function (eventName) {

                        imageUpload
                            .addEventListener(
                                eventName,
                                function (event) {

                                    event.preventDefault();

                                    event.stopPropagation();

                                    imageUpload
                                        .classList
                                        .add(
                                            'dragover'
                                        );

                                }
                            );

                    }
                );


                [
                    'dragleave',
                    'drop'
                ].forEach(
                    function (eventName) {

                        imageUpload
                            .addEventListener(
                                eventName,
                                function (event) {

                                    event.preventDefault();

                                    event.stopPropagation();

                                    imageUpload
                                        .classList
                                        .remove(
                                            'dragover'
                                        );

                                }
                            );

                    }
                );


                imageUpload
                    .addEventListener(
                        'drop',
                        function (event) {

                            const files =
                                event
                                    .dataTransfer
                                    .files;

                            if (
                                files &&
                                files.length
                            ) {

                                const file =
                                    files[0];

                                handleImageFile(
                                    file
                                );


                                const dataTransfer =
                                    new DataTransfer();

                                dataTransfer.items
                                    .add(
                                        file
                                    );

                                imageInput.files =
                                    dataTransfer.files;

                            }

                        }
                    );

            }


            /* =====================================================
               REMOVE IMAGE
               ====================================================== */

            if (removeImageButton) {

                removeImageButton
                    .addEventListener(
                        'click',
                        function () {

                            if (imageInput) {
                                imageInput.value =
                                    '';
                            }

                            if (
                                previewImage
                            ) {
                                previewImage.src =
                                    '';
                            }

                            if (
                                imagePreview
                            ) {

                                imagePreview
                                    .classList
                                    .remove(
                                        'active'
                                    );

                            }

                            if (
                                uploadPlaceholder
                            ) {

                                uploadPlaceholder
                                    .style
                                    .display =
                                    '';

                            }

                            if (
                                imageUpload
                            ) {

                                imageUpload
                                    .classList
                                    .remove(
                                        'has-image'
                                    );

                            }

                            if (
                                liveImageContainer
                            ) {

                                liveImageContainer.innerHTML = `

                                    <div
                                        class="
                                            food-live-image-placeholder
                                        "
                                    >

                                        <i
                                            class="
                                                fa-solid
                                                fa-utensils
                                            "
                                        ></i>

                                    </div>

                                `;

                            }

                            updateChecks();

                        }
                    );

            }


            /* =====================================================
               TAG SYSTEM
               ====================================================== */

            const tagInput =
                document.getElementById(
                    'tag-input'
                );

            const tagList =
                document.getElementById(
                    'tag-list'
                );

            const tagsHidden =
                document.getElementById(
                    'tags-hidden'
                );

            let tags = [];


            function loadExistingTags() {

                if (!tagsHidden) {
                    return;
                }

                const value =
                    tagsHidden.value
                        .trim();

                if (!value) {
                    return;
                }

                try {

                    const parsed =
                        JSON.parse(
                            value
                        );

                    if (
                        Array.isArray(
                            parsed
                        )
                    ) {

                        tags =
                            parsed;

                    } else {

                        tags =
                            value
                                .split(',')
                                .map(
                                    item =>
                                        item.trim()
                                )
                                .filter(
                                    Boolean
                                );

                    }

                } catch (error) {

                    tags =
                        value
                            .split(',')
                            .map(
                                item =>
                                    item.trim()
                            )
                            .filter(
                                Boolean
                            );

                }

                renderTags();

            }


            function renderTags() {

                if (!tagList) {
                    return;
                }

                tagList.innerHTML =
                    '';

                tags.forEach(
                    function (
                        tag,
                        index
                    ) {

                        const tagElement =
                            document
                                .createElement(
                                    'span'
                                );

                        tagElement.className =
                            'food-tag';

                        tagElement.innerHTML = `

                            ${escapeHtml(tag)}

                            <button
                                type="button"
                                data-index="${index}"
                            >

                                <i
                                    class="fa-solid
                                    fa-xmark"
                                ></i>

                            </button>

                        `;

                        tagList.appendChild(
                            tagElement
                        );

                    }
                );


                if (tagsHidden) {

                    tagsHidden.value =
                        JSON.stringify(
                            tags
                        );

                }

            }


            function addTag(
                value
            ) {

                const tag =
                    value
                        .trim()
                        .replace(
                            /,/g,
                            ''
                        );

                if (
                    !tag
                ) {
                    return;
                }

                if (
                    tags.includes(
                        tag
                    )
                ) {
                    return;
                }

                if (
                    tags.length >= 15
                ) {

                    alert(
                        'Tối đa 15 tags.'
                    );

                    return;

                }

                tags.push(
                    tag
                );

                renderTags();

            }


            if (tagInput) {

                tagInput.addEventListener(
                    'keydown',
                    function (event) {

                        if (
                            event.key === 'Enter'
                            ||
                            event.key === ','
                        ) {

                            event.preventDefault();

                            addTag(
                                tagInput.value
                            );

                            tagInput.value =
                                '';

                        }

                    }
                );

            }


            if (tagList) {

                tagList.addEventListener(
                    'click',
                    function (event) {

                        const button =
                            event.target
                                .closest(
                                    'button'
                                );

                        if (!button) {
                            return;
                        }

                        const index =
                            Number(
                                button.dataset.index
                            );

                        tags.splice(
                            index,
                            1
                        );

                        renderTags();

                    }
                );

            }


            loadExistingTags();


            /* =====================================================
               CHECKLIST
               ====================================================== */

            function updateChecks() {

                const checkName =
                    document.getElementById(
                        'check-name'
                    );

                const checkPrice =
                    document.getElementById(
                        'check-price'
                    );

                const checkCategory =
                    document.getElementById(
                        'check-category'
                    );

                const checkImage =
                    document.getElementById(
                        'check-image'
                    );


                if (
                    checkName &&
                    nameInput
                ) {

                    checkName.checked =
                        nameInput.value
                            .trim()
                            .length > 0;

                }


                if (
                    checkPrice &&
                    priceInput
                ) {

                    checkPrice.checked =
                        Number(
                            priceInput.value
                        ) >= 0
                        &&
                        priceInput.value !== '';

                }


                if (
                    checkCategory &&
                    categoryInput
                ) {

                    checkCategory.checked =
                        categoryInput.value
                        !== '';

                }


                if (
                    checkImage
                ) {

                    checkImage.checked =
                        (
                            imageInput &&
                            imageInput.files &&
                            imageInput.files.length
                        ) > 0
                        ||
                        {{
                            isset($food)
                            && $food->image
                                ? 'true'
                                : 'false'
                        }};

                }

            }


            updateChecks();


            /* =====================================================
               MODAL
               ====================================================== */

            function openModal() {

                if (modal) {

                    modal.classList.add(
                        'active'
                    );

                }

            }


            function closeModal() {

                if (modal) {

                    modal.classList.remove(
                        'active'
                    );

                }

            }


            if (
                previewSubmitButton
            ) {

                previewSubmitButton
                    .addEventListener(
                        'click',
                        function () {

                            updateChecks();

                            const invalid =
                                !nameInput ||
                                !nameInput.value
                                    .trim()
                                ||
                                !categoryInput ||
                                !categoryInput.value
                                ||
                                !priceInput ||
                                priceInput.value === '';

                            if (invalid) {

                                if (
                                    !nameInput.value.trim()
                                ) {

                                    nameInput
                                        .focus();

                                    alert(
                                        'Vui lòng nhập tên món ăn.'
                                    );

                                    return;

                                }

                                if (
                                    !categoryInput.value
                                ) {

                                    categoryInput
                                        .focus();

                                    alert(
                                        'Vui lòng chọn danh mục.'
                                    );

                                    return;

                                }

                                if (
                                    priceInput.value === ''
                                ) {

                                    priceInput
                                        .focus();

                                    alert(
                                        'Vui lòng nhập giá bán.'
                                    );

                                    return;

                                }

                            }

                            openModal();

                        }
                    );

            }


            if (modalCancel) {

                modalCancel.addEventListener(
                    'click',
                    closeModal
                );

            }


            if (modalConfirm) {

                modalConfirm.addEventListener(
                    'click',
                    function () {

                        closeModal();

                        if (form) {

                            form.submit();

                        }

                    }
                );

            }


            if (modal) {

                modal.addEventListener(
                    'click',
                    function (event) {

                        if (
                            event.target ===
                            modal
                        ) {

                            closeModal();

                        }

                    }
                );

            }


            /* =====================================================
               FORM VALIDATION
               ====================================================== */

            if (form) {

                form.addEventListener(
                    'submit',
                    function (event) {

                        let valid =
                            true;


                        if (
                            !nameInput ||
                            !nameInput.value
                                .trim()
                        ) {

                            valid =
                                false;

                            if (
                                nameInput
                            ) {

                                nameInput
                                    .classList
                                    .add(
                                        'is-invalid'
                                    );

                                nameInput.focus();

                            }

                        } else {

                            nameInput
                                .classList
                                .remove(
                                    'is-invalid'
                                );

                        }


                        if (
                            !categoryInput ||
                            !categoryInput.value
                        ) {

                            valid =
                                false;

                            if (
                                categoryInput
                            ) {

                                categoryInput
                                    .classList
                                    .add(
                                        'is-invalid'
                                    );

                            }

                        } else {

                            categoryInput
                                .classList
                                .remove(
                                    'is-invalid'
                                );

                        }


                        if (
                            !priceInput ||
                            priceInput.value === ''
                            ||
                            Number(
                                priceInput.value
                            ) < 0
                        ) {

                            valid =
                                false;

                            if (
                                priceInput
                            ) {

                                priceInput
                                    .classList
                                    .add(
                                        'is-invalid'
                                    );

                            }

                        } else {

                            priceInput
                                .classList
                                .remove(
                                    'is-invalid'
                                );

                        }


                        if (
                            !valid
                        ) {

                            event.preventDefault();

                            alert(
                                'Vui lòng kiểm tra lại các trường bắt buộc.'
                            );

                            return;

                        }


                        if (
                            submitButton
                        ) {

                            submitButton
                                .disabled =
                                true;

                            submitButton
                                .innerHTML = `

                                    <i
                                        class="
                                            fa-solid
                                            fa-spinner
                                            fa-spin
                                        "
                                    ></i>

                                    Đang lưu...

                                `;

                        }

                    }
                );

            }


            /* =====================================================
               UNSAVED CHANGES
               ====================================================== */

            let formChanged =
                false;


            if (form) {

                form.addEventListener(
                    'input',
                    function () {

                        formChanged =
                            true;

                    }
                );

                form.addEventListener(
                    'change',
                    function () {

                        formChanged =
                            true;

                    }
                );

                form.addEventListener(
                    'submit',
                    function () {

                        formChanged =
                            false;

                    }
                );

            }


            window.addEventListener(
                'beforeunload',
                function (event) {

                    if (
                        !formChanged
                    ) {
                        return;
                    }

                    event.preventDefault();

                    event.returnValue =
                        '';

                }
            );


            /* =====================================================
               KEYBOARD SHORTCUT
               ====================================================== */

            document.addEventListener(
                'keydown',
                function (event) {

                    if (
                        (event.ctrlKey ||
                         event.metaKey)
                        &&
                        event.key === 's'
                    ) {

                        event.preventDefault();

                        if (
                            previewSubmitButton
                        ) {

                            previewSubmitButton
                                .click();

                        }

                    }

                }
            );


            /* =====================================================
               INITIAL PREVIEW
               ====================================================== */

            updateLivePreview();

            if (priceInput) {

                pricePreview.textContent =
                    formatMoney(
                        priceInput.value
                    );

            }

            validateOriginalPrice();

        }
    );
</script>

@endsection
