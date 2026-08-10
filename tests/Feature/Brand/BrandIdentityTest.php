<?php

namespace Tests\Feature\Brand;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class BrandIdentityTest extends TestCase
{
    public function test_official_source_and_public_brand_assets_exist_with_expected_dimensions(): void
    {
        $sources = [
            'moviemate-logo-dark.png' => [1536, 1024],
            'moviemate-logo-light.png' => [1536, 1024],
            'moviemate-logo-white.png' => [1536, 1024],
            'moviemate-icon.png' => [1536, 1024],
            'moviemate-app-icon.png' => [1536, 1024],
        ];

        foreach ($sources as $file => $expectedSize) {
            $path = resource_path("images/brand/source/{$file}");
            $this->assertFileExists($path);
            $this->assertSame($expectedSize, array_slice(getimagesize($path), 0, 2));
        }

        $generated = [
            'logo-on-dark.png' => [978, 211],
            'logo-on-light.png' => [1055, 215],
            'logo-white.png' => [835, 177],
            'mark.png' => [512, 512],
            'icon-32.png' => [32, 32],
            'icon-48.png' => [48, 48],
            'icon-64.png' => [64, 64],
            'apple-touch-icon.png' => [180, 180],
            'app-icon-192.png' => [192, 192],
            'app-icon-512.png' => [512, 512],
        ];

        foreach ($generated as $file => $expectedSize) {
            $path = public_path("images/brand/{$file}");
            $this->assertFileExists($path);
            $this->assertSame($expectedSize, array_slice(getimagesize($path), 0, 2));
        }
    }

    public function test_shared_component_references_theme_aware_official_wordmarks(): void
    {
        $html = File::get(resource_path('views/components/brand/logo.blade.php'));

        $this->assertStringContainsString("asset('images/brand/logo-on-dark.png')", $html);
        $this->assertStringContainsString("asset('images/brand/logo-on-light.png')", $html);
        $this->assertStringContainsString("asset('images/brand/mark.png')", $html);
        $this->assertStringContainsString('width="978" height="211"', $html);
        $this->assertStringContainsString("'alt' => 'MovieMate'", $html);
    }

    public function test_customer_admin_and_staff_brand_areas_use_the_shared_component(): void
    {
        $customer = File::get(resource_path('views/layouts/user.blade.php'));
        $admin = File::get(resource_path('views/layouts/admin.blade.php'));
        $staff = File::get(resource_path('views/layouts/staff.blade.php'));

        $this->assertStringContainsString('<x-brand.logo class="brand-logo--customer-header" />', $customer);
        $this->assertStringContainsString('<x-brand.logo class="brand-logo--footer" />', $customer);
        $this->assertStringContainsString('<x-brand.logo class="brand-logo--sidebar" />', $admin);
        $this->assertStringContainsString('<x-brand.logo class="brand-logo--sidebar" />', $staff);
        $this->assertStringNotContainsString('ph-fill ph-film-strip text-3xl text-brand-start', $admin);
        $this->assertStringNotContainsString('ph-fill ph-film-strip text-3xl text-ai-start', $staff);
    }

    public function test_all_browser_document_shells_reference_shared_png_favicons(): void
    {
        $headIcons = File::get(resource_path('views/components/brand/head-icons.blade.php'));
        $this->assertStringContainsString("asset('images/brand/icon-32.png')", $headIcons);
        $this->assertStringContainsString("asset('images/brand/icon-48.png')", $headIcons);
        $this->assertStringContainsString("asset('images/brand/apple-touch-icon.png')", $headIcons);

        foreach ([
            'layouts/user.blade.php',
            'layouts/admin.blade.php',
            'layouts/staff.blade.php',
            'admin/auth/login.blade.php',
            'staff/tickets/print.blade.php',
            'user/bookings/access.blade.php',
            'user/bookings/guest-handoff.blade.php',
        ] as $view) {
            $this->assertStringContainsString('<x-brand.head-icons />', File::get(resource_path("views/{$view}")), $view);
        }
    }
}
