<?php

namespace Tests\Feature\Defense;

use Tests\TestCase;

class DefenseDocumentationSemanticContractTest extends TestCase
{
    /** @var list<string> */
    private const ACTIVE_DEFENSE_FILES = [
        'docs/DEFENSE_READINESS.md',
        'docs/DEFENSE_TALK_TRACK.md',
        'docs/DEMO_ACCOUNTS.md',
        'docs/DEMO_SCRIPT.md',
        'docs/FINAL_ACCEPTANCE_CHECKLIST.md',
        'docs/PRESENTATION_OUTLINE.md',
        'docs/USE_CASE_SUMMARY.md',
    ];

    public function test_active_defense_allowlist_uses_the_frozen_product_model(): void
    {
        $content = $this->activeDefenseContent();

        foreach ([
            'QR đơn đặt vé',
            'AdmissionTicket',
            'FoodPickupVoucher',
            'Lưới logic',
            'Vật cản cố định',
            'RoomType',
            'PresentationFormat',
            'PriceBookVersion',
            'ShowtimeTicketPrice',
            'tối đa một Khuyến mãi',
            'Đã xác minh',
            'Đã thu tiền',
            'branch-first',
        ] as $requiredClaim) {
            $this->assertStringContainsString($requiredClaim, $content);
        }

        foreach ([
            '/nhận vé điện tử/i',
            '/xem vé điện tử/i',
            '/vé điện tử là/i',
            '/soát vé\s*\/\s*check-in/i',
            '/check-in atomic/i',
            '/TicketCheckinService/i',
            '/CinemaPricingRule/i',
            '/(?<!Versioned)TicketPricingService/i',
            '/showtimes\.price/i',
            '/giá vé VIP/i',
            '/pricing-rules/i',
            '/Holiday (?:cộng|stack).{0,20}Weekend/i',
            '/cho phép.{0,30}(?:nhiều mã|stacking)/i',
            '/PresentationFormat (?:tạo|có) (?:phụ thu|surcharge)/i',
            '/Manager (?:quản lý|thay đổi) (?:Movie|Genre|Food)/i',
            '/lưới logic.{0,20}(?:là|=).{0,20}kích thước vật lý/i',
        ] as $forbiddenCurrentClaim) {
            $this->assertDoesNotMatchRegularExpression($forbiddenCurrentClaim, $content);
        }
    }

    public function test_docx_generator_uses_the_same_authoritative_language(): void
    {
        $generator = file_get_contents(base_path('app/Console/Commands/GenerateMovieMateDocx.php'));

        $this->assertIsString($generator);
        foreach ([
            'QR đơn đặt vé',
            'AdmissionTicket',
            'FoodPickupVoucher',
            'RoomType',
            'PresentationFormat',
            'PriceBookVersion',
            'ShowtimeTicketPrice',
            'row lock',
            'Browser return không bao giờ tự đánh dấu paid',
            'Không duy trì digital attendance/check-in',
        ] as $requiredClaim) {
            $this->assertStringContainsString($requiredClaim, $generator);
        }

        foreach ([
            'nhận vé điện tử',
            'Vé điện tử là',
            'QR check ticket',
            'Cập nhật vé đã sử dụng',
            'giá vé thường, giá vé VIP',
            'CinemaPricingRule',
            'showtimes.price',
        ] as $forbiddenClaim) {
            $this->assertStringNotContainsStringIgnoringCase($forbiddenClaim, $generator);
        }

        $this->assertDoesNotMatchRegularExpression('/(?<!Versioned)TicketPricingService/i', $generator);
    }

    public function test_canonical_demo_uses_existing_handoffs_without_manual_urls(): void
    {
        $demo = file_get_contents(base_path('docs/DEMO_SCRIPT.md'));

        $this->assertIsString($demo);
        $this->assertStringContainsString('Branch360 → Room', $demo);
        $this->assertStringContainsString('Room → suất chiếu sắp tới → chi tiết Showtime', $demo);
        $this->assertStringContainsString('Booking → Payment', $demo);
        $this->assertStringContainsString('Manager → Customer → Staff → Manager', $demo);
        $this->assertMatchesRegularExpression('/không nhập URL trực tiếp/i', $demo);
        $this->assertDoesNotMatchRegularExpression('/`\/(?:admin|staff|booking|movies|cinemas)/i', $demo);
        $this->assertStringNotContainsString('phần này chưa có', $demo);
        $this->assertStringNotContainsString('giả sử', $demo);
    }

    private function activeDefenseContent(): string
    {
        $contents = [];

        foreach (self::ACTIVE_DEFENSE_FILES as $relativePath) {
            $path = base_path($relativePath);
            $this->assertFileExists($path);
            $content = file_get_contents($path);
            $this->assertIsString($content);
            $contents[] = $content;
        }

        return implode("\n", $contents);
    }
}
