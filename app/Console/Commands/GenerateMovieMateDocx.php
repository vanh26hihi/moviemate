<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\ListItem;

class GenerateMovieMateDocx extends Command
{
    protected $signature = 'moviemate:generate-docx
                            {--output= : Optional DOCX output path for local verification}';

    protected $description = 'Generate MovieMate final product and defense documentation as DOCX.';

    /** @var array<string, mixed> */
    private array $fontStyles = [];

    /** @var array<string, mixed> */
    private array $paragraphStyles = [];

    public function handle(): int
    {
        $tempDirectory = storage_path('app/phpword-temp');
        if (! is_dir($tempDirectory)) {
            mkdir($tempDirectory, 0755, true);
        }

        Settings::setTempDir($tempDirectory);
        Settings::setOutputEscapingEnabled(true);
        if (! class_exists(\ZipArchive::class)) {
            Settings::setZipClass(Settings::PCLZIP);
        }

        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(11);

        $this->configureStyles($phpWord);

        $section = $phpWord->addSection([
            'marginTop' => Converter::cmToTwip(1.8),
            'marginRight' => Converter::cmToTwip(1.8),
            'marginBottom' => Converter::cmToTwip(1.8),
            'marginLeft' => Converter::cmToTwip(1.8),
        ]);

        $this->addTitle($section, 'HỒ SƠ NGHIỆP VỤ CUỐI CÙNG — MOVIEMATE');
        $this->addParagraph($section, 'Phạm vi: Một công ty rạp chiếu phim vận hành nhiều chi nhánh.', true);
        $this->addParagraph($section, 'Nguồn thẩm quyền: runtime Phase 1–8B4, invariant phía server và bằng chứng kiểm thử tự động.', true);
        $section->addTextBreak(1);

        foreach ($this->documentSections() as $documentSection) {
            $this->addHeading1($section, $documentSection['title']);

            foreach ($documentSection['paragraphs'] ?? [] as $paragraph) {
                $this->addParagraph($section, $paragraph);
            }

            if (! empty($documentSection['bullets'])) {
                $this->addBulletList($section, $documentSection['bullets']);
            }

            foreach ($documentSection['subsections'] ?? [] as $subsection) {
                $this->addHeading2($section, $subsection['title']);

                foreach ($subsection['paragraphs'] ?? [] as $paragraph) {
                    $this->addParagraph($section, $paragraph);
                }

                if (! empty($subsection['bullets'])) {
                    $this->addBulletList($section, $subsection['bullets']);
                }
            }

            $section->addTextBreak(1);
        }

        $explicitOutput = $this->explicitOutputPath();
        if ($explicitOutput !== null) {
            $this->ensureDirectory(dirname($explicitOutput));
            $this->removeExistingFile($explicitOutput);
            IOFactory::createWriter($phpWord, 'Word2007')->save($explicitOutput);

            $this->info('DOCX generated successfully.');
            $this->line('Output file: '.$explicitOutput);

            return self::SUCCESS;
        }

        $documentsDirectory = storage_path('app/documents');
        $this->ensureDirectory($documentsDirectory);

        $fileName = 'TaiLieu_ChucNang_HeThong_MovieMate.docx';
        $storagePath = $documentsDirectory.DIRECTORY_SEPARATOR.$fileName;
        $rootPath = base_path($fileName);

        foreach ([$storagePath, $rootPath] as $path) {
            $this->removeExistingFile($path);
        }

        IOFactory::createWriter($phpWord, 'Word2007')->save($storagePath);
        copy($storagePath, $rootPath);

        $this->info('DOCX generated successfully.');
        $this->line('Storage file: '.$storagePath);
        $this->line('Project root copy: '.$rootPath);

        return self::SUCCESS;
    }

    private function explicitOutputPath(): ?string
    {
        $output = $this->option('output');
        if (! is_string($output) || trim($output) === '') {
            return null;
        }

        $output = trim($output);
        if (preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/])/', $output) === 1) {
            return $output;
        }

        return base_path($output);
    }

    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    private function removeExistingFile(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }
    }

    private function configureStyles(PhpWord $phpWord): void
    {
        $this->fontStyles = [
            'title' => ['name' => 'Arial', 'size' => 18, 'bold' => true],
            'heading1' => ['name' => 'Arial', 'size' => 15, 'bold' => true, 'color' => '1F2937'],
            'heading2' => ['name' => 'Arial', 'size' => 12, 'bold' => true, 'color' => '374151'],
            'body' => ['name' => 'Arial', 'size' => 11],
            'bodyBold' => ['name' => 'Arial', 'size' => 11, 'bold' => true],
        ];

        $this->paragraphStyles = [
            'title' => ['alignment' => Jc::CENTER, 'spaceAfter' => 180],
            'heading1' => ['spaceBefore' => 220, 'spaceAfter' => 100],
            'heading2' => ['spaceBefore' => 140, 'spaceAfter' => 80],
            'body' => ['alignment' => Jc::BOTH, 'spaceAfter' => 80, 'lineHeight' => 1.15],
            'list' => ['spaceAfter' => 50, 'lineHeight' => 1.1],
        ];

        $phpWord->addTitleStyle(1, $this->fontStyles['heading1'], $this->paragraphStyles['heading1']);
        $phpWord->addTitleStyle(2, $this->fontStyles['heading2'], $this->paragraphStyles['heading2']);
    }

    private function addTitle(mixed $section, string $text): void
    {
        $section->addText($text, $this->fontStyles['title'], $this->paragraphStyles['title']);
    }

    private function addHeading1(mixed $section, string $text): void
    {
        $section->addText($text, $this->fontStyles['heading1'], $this->paragraphStyles['heading1']);
    }

    private function addHeading2(mixed $section, string $text): void
    {
        $section->addText($text, $this->fontStyles['heading2'], $this->paragraphStyles['heading2']);
    }

    private function addParagraph(mixed $section, string $text, bool $bold = false): void
    {
        $section->addText($text, $bold ? $this->fontStyles['bodyBold'] : $this->fontStyles['body'], $this->paragraphStyles['body']);
    }

    /**
     * @param  array<int, string>  $items
     */
    private function addBulletList(mixed $section, array $items): void
    {
        foreach ($items as $item) {
            $section->addListItem(
                $item,
                0,
                $this->fontStyles['body'],
                ['listType' => ListItem::TYPE_BULLET_FILLED],
                $this->paragraphStyles['list']
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function documentSections(): array
    {
        return [
            [
                'title' => '1. Phạm vi sản phẩm và vai trò',
                'paragraphs' => [
                    'MovieMate là hệ thống của một công ty rạp chiếu phim có nhiều chi nhánh; không phải marketplace, cinema aggregator hoặc nền tảng multi-company SaaS.',
                ],
                'bullets' => [
                    'Global Admin quản trị governance, cấu hình, master và báo cáo toàn chuỗi.',
                    'Manager vận hành chi nhánh được phân công theo mô hình branch-first.',
                    'Staff tra cứu Đơn đặt vé, xem bằng chứng thanh toán và in hiện vật giấy tại quầy.',
                    'Customer khám phá, đặt vé, thanh toán, xem lịch sử và review khi đủ điều kiện; Guest có thể bắt đầu luồng công khai.',
                    'Movie, Genre và Food là master toàn chuỗi do Global Admin quản lý; Manager chỉ read/use.',
                ],
            ],
            [
                'title' => '2. Chuỗi vận hành theo tác vụ',
                'bullets' => [
                    'Branch → Room → Showtime → Booking → Payment.',
                    'Booking → Counter / Print.',
                    'Room / Booking → exact SeatIncident context khi có ảnh hưởng.',
                    'Branch360 là workspace vận hành gồm action queue, Room, Showtime hôm nay/sắp tới, counter, finance và configuration context.',
                    'Showtime detail dùng để quan sát trạng thái vận hành; Edit chỉ dùng khi mutation được phép.',
                ],
            ],
            [
                'title' => '3. Room, RoomLayout và Seat',
                'paragraphs' => [
                    'Room là auditorium vật lý. Width/length được lưu integer mm và hiển thị mét; diện tích chỉ là xấp xỉ hình chữ nhật hành chính, không phải CAD hay chứng nhận pháp lý.',
                ],
                'bullets' => [
                    'Rows × columns là Lưới logic, tách khỏi kích thước vật lý.',
                    'SEAT = Ghế; AISLE = Lối đi; BLOCKED = Vật cản cố định; EMPTY = Ô trống.',
                    'Sức chứa vật lý, Ghế khả dụng vận hành, pricingUnitCount và Showtime saleable inventory là bốn khái niệm khác nhau.',
                    'Published RoomLayout bất biến; structural change tạo draft/version mới và Showtime ghim đúng room_layout_id.',
                    'Template apply tạo bản sao độc lập; sửa Template không mutate RoomLayout đã áp dụng.',
                    'Ghế bảo trì vẫn là Seat; SeatIncident không biến Seat thành BLOCKED.',
                ],
            ],
            [
                'title' => '4. Showtime và lịch vận hành',
                'bullets' => [
                    'Authority gồm Movie, Room, published RoomLayout, PresentationFormat, runtime, operating hours, cleaning buffer và overlap validation.',
                    'Validation phía server; bulk publish all-or-nothing; schedule copy re-derives authoritative intent.',
                    'START là lúc phim bắt đầu; END là runtime end; CLEANING_START bắt đầu vệ sinh; ROOM_READY là END + cleaning.',
                    'Cross-midnight được hỗ trợ; business date là ngày local của START.',
                    'Customer booking cutoff chính xác START + 15 phút.',
                    'RoomType là loại trải nghiệm phòng; PresentationFormat là phương thức chiếu như 2D/3D và không tạo phụ thu.',
                ],
            ],
            [
                'title' => '5. PriceBook và snapshot giá',
                'paragraphs' => [
                    'Chuỗi thẩm quyền: PriceBook → PriceBookVersion → VersionedTicketPricingService → ShowtimeTicketPrice → Booking/BookingSeat sold snapshot → Payment.',
                ],
                'bullets' => [
                    'Mỗi PriceBookVersion có đúng một Giá cơ sở toàn chuỗi.',
                    'Adjustment VND có dấu theo SeatType, RoomType, Time, Weekend/Holiday, Cinema và Room.',
                    'Thứ tự: base → SeatType → RoomType → Time → Weekend/Holiday → Cinema → Room; không có priority resolver.',
                    'Holiday thay Weekend; hai calendar adjustment không cộng đồng thời.',
                    'Couple có hai vị trí ghế vật lý nhưng một pricing unit và charge một lần.',
                    'ShowtimeTicketPrice khóa giá khi lập lịch; PriceBookVersion mới không reprice Showtime cũ.',
                    'Booking lưu sold snapshot nên cấu hình mới không viết lại lịch sử đã bán.',
                ],
            ],
            [
                'title' => '6. Khuyến mãi',
                'bullets' => [
                    'Mỗi Booking áp dụng tối đa một Khuyến mãi trên ticket + food gross trước giảm.',
                    'Fixed là số tiền giảm và không có cap; percentage có optional positive maximum cap.',
                    'Validity dùng local clock của Booking Cinema; start inclusive, end exclusive.',
                    'Quote không tiêu quota. Authoritative Booking confirm giữ quota dưới row lock.',
                    'Reserved và redeemed tiêu quota; released không còn tiêu quota hiện tại.',
                    'Bất kỳ usage nào, kể cả released, đều khóa economics, eligibility và scope; lifecycle controls vẫn còn.',
                    'Manager chỉ quản lý Promotion exact-own-branch; global/mixed read-only, foreign hidden.',
                ],
            ],
            [
                'title' => '7. Đơn đặt vé, thanh toán và in vật lý',
                'subsections' => [
                    [
                        'title' => '7.1. Booking và QR',
                        'paragraphs' => [
                            'Customer nhận booking code và QR đơn đặt vé cho toàn Booking. QR này là capability tra cứu tại quầy, không phải AdmissionTicket hoặc credential vào phòng.',
                        ],
                    ],
                    [
                        'title' => '7.2. Payment evidence',
                        'paragraphs' => [
                            'Browser return không bao giờ tự đánh dấu paid. Provider callback/query đã xác minh hoặc counter settlement mới là evidence; zero-payable dùng internal_zero và không gọi provider ngoài.',
                        ],
                    ],
                    [
                        'title' => '7.3. Hiện vật giấy',
                        'bullets' => [
                            'Một vị trí ghế vật lý tạo một AdmissionTicket; Couple tạo hai AdmissionTicket nhưng charge một pricing unit.',
                            'Booking có Food tạo một FoodPickupVoucher cho phần đồ ăn.',
                            'First print không cần reason; reprint cần reason; Print All gồm toàn bộ AdmissionTickets và FoodPickupVoucher.',
                            'Customer không tự in AdmissionTicket; Staff thực hiện và hệ thống ghi audit.',
                        ],
                    ],
                ],
            ],
            [
                'title' => '8. Review, incident và relocation',
                'bullets' => [
                    'Review eligibility: Customer sở hữu paid Movie Booking, Movie đã END và chưa review; không phụ thuộc attendance.',
                    'SeatIncident là ngoại lệ vận hành gắn Seat maintenance và có thể xác định Booking bị ảnh hưởng.',
                    'Relocation giữ cùng Booking identity, chỉ equivalent/upgrade, không downgrade, không extra charge và Couple atomic.',
                    'Replacement print có thể được cấp mà không tạo Booking mới.',
                ],
            ],
            [
                'title' => '9. Báo cáo và bằng chứng',
                'bullets' => [
                    'Báo cáo sử dụng Payment Đã xác minh hoặc Đã thu tiền và timestamp evidence tương ứng.',
                    'Showtime business date là local START date; payment settlement time là thời điểm xác minh/thu tiền thực tế.',
                    'Không tuyên bố net revenue sau refund vì hệ thống không có refund ledger.',
                    'Manager chỉ xem phạm vi chi nhánh; Global Admin xem hợp nhất toàn chuỗi.',
                ],
            ],
            [
                'title' => '10. Vì sao không chỉ là CRUD?',
                'paragraphs' => [
                    'MovieMate tổ chức read model theo tác vụ, handoff liên domain, snapshot lịch sử và invariant phía server. Authorization theo chi nhánh, row lock quota, active seat lock, all-or-nothing schedule và Payment evidence tạo tính deterministic vượt khỏi CRUD màn hình.',
                ],
                'bullets' => [
                    'RoomLayout publish, PriceBookVersion publish, ShowtimeTicketPrice schedule và Booking sale là các freeze point khác nhau.',
                    'Branch → Room → Showtime → Booking → Payment là đường nghiệp vụ có context.',
                    'Booking → Counter/Print và incident → relocation là handoff có audit.',
                ],
            ],
            [
                'title' => '11. Demo bảo vệ chuẩn',
                'bullets' => [
                    'Manager: Branch360 → Room → Sơ đồ/Bảo trì → Showtime operational detail.',
                    'Customer: Movie → Showtime → Seat → Food → một Khuyến mãi → Booking/Payment → QR đơn đặt vé.',
                    'Staff: Booking code/QR lookup → Payment evidence → Print All AdmissionTickets/FoodPickupVoucher.',
                    'Manager: Showtime → Booking → Payment → Báo cáo.',
                    'Dùng ba profile sẵn, ba role switches và navigation/handoff hiện hữu; không nhập URL thủ công.',
                ],
            ],
            [
                'title' => '12. Giới hạn phải trình bày trung thực',
                'bullets' => [
                    'Không duy trì digital attendance/check-in; vé giấy được kiểm tra thủ công.',
                    'Không có refund ledger, Loyalty hoặc net revenue sau refund.',
                    'Không có dynamic/AI pricing; PriceBook là deterministic business rules.',
                    'Không có CAD, evacuation simulation hoặc QCVN/legal compliance engine.',
                    'Không có branch-specific Food-price domain hoặc PresentationFormat surcharge.',
                    'Không tuyên bố occupancy analytics, phỏng vấn hoặc khảo sát khi không có bằng chứng nguồn.',
                ],
            ],
        ];
    }
}
