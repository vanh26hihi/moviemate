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
    protected $signature = 'moviemate:generate-docx';

    protected $description = 'Generate MovieMate system function documentation as DOCX.';

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

        $this->addTitle($section, 'TÀI LIỆU CHỨC NĂNG HỆ THỐNG MOVIEMATE');
        $this->addParagraph($section, 'Tên đề tài: Website đặt vé xem phim trực tuyến tích hợp AI', true);
        $this->addParagraph($section, 'Công nghệ sử dụng: Laravel, Blade, Tailwind CSS, MySQL, JavaScript, Vite và PHPWord.', true);
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

        $documentsDirectory = storage_path('app/documents');
        if (! is_dir($documentsDirectory)) {
            mkdir($documentsDirectory, 0755, true);
        }

        $fileName = 'TaiLieu_ChucNang_HeThong_MovieMate.docx';
        $storagePath = $documentsDirectory.DIRECTORY_SEPARATOR.$fileName;
        $rootPath = base_path($fileName);

        foreach ([$storagePath, $rootPath] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        IOFactory::createWriter($phpWord, 'Word2007')->save($storagePath);
        copy($storagePath, $rootPath);

        $this->info('DOCX generated successfully.');
        $this->line('Storage file: '.$storagePath);
        $this->line('Project root copy: '.$rootPath);

        return self::SUCCESS;
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
                'title' => '1. Thông tin chung',
                'paragraphs' => [
                    'MovieMate là hệ thống website đặt vé xem phim trực tuyến được xây dựng nhằm phục vụ đồ án tốt nghiệp với định hướng tích hợp các tính năng AI vào trải nghiệm đặt vé.',
                ],
                'bullets' => [
                    'Tên đề tài: Website đặt vé xem phim trực tuyến tích hợp AI.',
                    'Tên hệ thống: MovieMate.',
                    'Công nghệ sử dụng: Laravel, Blade, Tailwind CSS, MySQL, JavaScript, Vite, Laravel Mail SMTP và PHPWord.',
                    'Đối tượng sử dụng: khách vãng lai, khách hàng đã đăng nhập, nhân viên rạp và quản trị viên.',
                    'Mục tiêu hệ thống: hỗ trợ xem phim, xem lịch chiếu, chọn rạp, chọn suất, chọn ghế, đặt vé, nhận vé điện tử, quản lý vận hành rạp và khai thác AI để gợi ý nội dung phù hợp.',
                ],
            ],
            [
                'title' => '2. Tổng quan hệ thống',
                'paragraphs' => [
                    'MovieMate là website đặt vé xem phim trực tuyến, kết nối dữ liệu phim, rạp, phòng chiếu, ghế, suất chiếu và booking trong một quy trình thống nhất. Người dùng có thể tra cứu thông tin phim, chọn lịch chiếu phù hợp, đặt vé và nhận vé điện tử để sử dụng tại rạp.',
                    'Hệ thống đồng thời cung cấp khu vực quản trị cho Admin, khu vực nghiệp vụ cho Staff và các tính năng AI nhằm hỗ trợ khách hàng cũng như tăng tốc thao tác nhập liệu cho quản trị viên.',
                ],
                'bullets' => [
                    'Xem danh sách phim đang chiếu và phim sắp chiếu.',
                    'Xem chi tiết phim, trailer, thể loại, độ tuổi, quốc gia, thời lượng và lịch chiếu liên quan.',
                    'Xem lịch chiếu theo thành phố, rạp, ngày, thương hiệu rạp và suất chiếu.',
                    'Chọn rạp, chọn suất chiếu, chọn ghế và xác nhận checkout.',
                    'Đặt vé, tạo booking code, nhận vé điện tử và quản lý lịch sử đặt vé.',
                    'Sử dụng AI để gợi ý phim và chatbot để hỗ trợ khách hàng.',
                ],
            ],
            [
                'title' => '3. Nhóm người dùng',
                'subsections' => [
                    [
                        'title' => '3.1. Khách vãng lai',
                        'paragraphs' => [
                            'Khách vãng lai là người chưa đăng nhập hệ thống. Nhóm này có thể xem trang chủ, danh sách phim, chi tiết phim, trailer, lịch chiếu và thông tin rạp. Khi thực hiện thao tác đặt vé, hệ thống yêu cầu đăng nhập để bảo đảm booking gắn với tài khoản hợp lệ.',
                        ],
                    ],
                    [
                        'title' => '3.2. Khách hàng đã đăng nhập',
                        'paragraphs' => [
                            'Khách hàng đã đăng nhập có đầy đủ chức năng đặt vé, chọn ghế, checkout, nhận vé điện tử, xem lịch sử đặt vé, cập nhật hồ sơ cá nhân và đánh giá phim. Người dùng cũng có thể dùng AI gợi ý phim, chatbot hỗ trợ và các tiện ích gần bạn hoặc dẫn đường.',
                        ],
                    ],
                    [
                        'title' => '3.3. Nhân viên',
                        'paragraphs' => [
                            'Nhân viên sử dụng khu vực Staff để kiểm tra vé, xác nhận vé hợp lệ, cảnh báo vé đã dùng, tra cứu vé không tồn tại và bán vé trực tiếp tại quầy. Các thao tác này phục vụ nghiệp vụ vận hành tại rạp.',
                        ],
                    ],
                    [
                        'title' => '3.4. Quản trị viên',
                        'paragraphs' => [
                            'Quản trị viên có quyền quản lý dữ liệu lõi của hệ thống gồm phim, thể loại, rạp, phòng chiếu, ghế, suất chiếu, booking, người dùng, đánh giá và thống kê doanh thu. Admin cũng có thể sử dụng công cụ AI để tạo mô tả phim hoặc nội dung marketing.',
                        ],
                    ],
                ],
            ],
            [
                'title' => '4. Chức năng User / Khách hàng',
                'subsections' => [
                    ['title' => '4.1. Trang chủ', 'paragraphs' => ['Trang chủ là điểm vào chính của khách hàng, hiển thị không gian điện ảnh hiện đại với hero banner, phim đang chiếu, phim sắp chiếu, lịch chiếu nổi bật và khu vực AI hỗ trợ. Giao diện hỗ trợ sáng/tối để phù hợp thói quen sử dụng của người dùng.'], 'bullets' => ['Hero banner giới thiệu phim, ưu đãi hoặc nội dung nổi bật.', 'Khối phim đang chiếu giúp khách hàng đặt vé nhanh.', 'Khối phim sắp chiếu giúp khách hàng theo dõi lịch phát hành.', 'Lịch chiếu phim cho phép đi nhanh tới bước chọn suất.', 'AI gợi ý phim hỗ trợ tìm phim theo nhu cầu.', 'Chatbot hỗ trợ giải đáp câu hỏi về đặt vé, lịch chiếu và tài khoản.', 'Giao diện sáng/tối tạo trải nghiệm linh hoạt.']],
                    ['title' => '4.2. Đăng ký', 'paragraphs' => ['Chức năng đăng ký tạo tài khoản khách hàng mới. Hệ thống thu thập thông tin cơ bản, kiểm tra hợp lệ dữ liệu và mã hóa mật khẩu trước khi lưu vào cơ sở dữ liệu.'], 'bullets' => ['Nhập họ tên, email, số điện thoại, mật khẩu và xác nhận mật khẩu.', 'Validate email không trùng, mật khẩu đủ điều kiện và số điện thoại đúng định dạng.', 'Mật khẩu được hash trước khi lưu.', 'Tài khoản được tạo với role user mặc định.']],
                    ['title' => '4.3. Đăng nhập', 'paragraphs' => ['Người dùng đăng nhập bằng email và mật khẩu. Sau khi xác thực, hệ thống kiểm tra role để chuyển hướng tới khu vực phù hợp như user frontend, staff dashboard hoặc admin dashboard.'], 'bullets' => ['Đăng nhập bằng email và mật khẩu.', 'Hỗ trợ ghi nhớ đăng nhập nếu được bật.', 'Phân quyền theo role.', 'Chuyển hướng theo vai trò tài khoản.']],
                    ['title' => '4.4. Đăng xuất', 'paragraphs' => ['Đăng xuất giúp kết thúc phiên làm việc hiện tại, hủy session và regenerate token để giảm rủi ro bảo mật. Sau khi đăng xuất, người dùng được chuyển về trang phù hợp.']],
                    ['title' => '4.5. Hồ sơ cá nhân', 'paragraphs' => ['Khách hàng có thể xem và cập nhật thông tin cá nhân. Email đóng vai trò định danh đăng nhập nên không cho sửa trực tiếp trong giao diện hồ sơ.'], 'bullets' => ['Xem thông tin cá nhân.', 'Cập nhật họ tên và số điện thoại.', 'Email hiển thị nhưng không cho chỉnh sửa.', 'Hiển thị thông báo thành công hoặc lỗi khi lưu.']],
                    ['title' => '4.6. Danh sách phim', 'paragraphs' => ['Danh sách phim giúp khách hàng khám phá toàn bộ phim trong hệ thống. Giao diện hiển thị thông tin tóm tắt để người dùng quyết định xem chi tiết hoặc đặt vé.'], 'bullets' => ['Hiển thị poster, tên phim, thể loại, trạng thái, thời lượng và độ tuổi.', 'Hỗ trợ tìm kiếm theo tên phim.', 'Hỗ trợ lọc phim theo trạng thái hoặc tiêu chí liên quan.', 'Phân trang để danh sách tải ổn định khi dữ liệu lớn.']],
                    ['title' => '4.7. Chi tiết phim', 'paragraphs' => ['Trang chi tiết phim cung cấp dữ liệu đầy đủ về một phim, bao gồm hình ảnh, nội dung, trailer và lịch chiếu liên quan. Đây là nơi người dùng chuyển từ thông tin phim sang hành động đặt vé.'], 'bullets' => ['Poster và cover image.', 'Mô tả phim, trailer, quốc gia, thời lượng, ngày khởi chiếu và độ tuổi.', 'Danh sách thể loại của phim.', 'Lịch chiếu liên quan để đặt vé nhanh.']],
                    ['title' => '4.8. Trailer phim', 'paragraphs' => ['Trailer phim được mở từ URL đã nhập trong phần quản trị. Hệ thống có thể hiển thị trailer trong popup hoặc mở link mới; nếu phim chưa có trailer, giao diện hiển thị fallback phù hợp để tránh lỗi trải nghiệm.']],
                    ['title' => '4.9. Lịch chiếu phim', 'paragraphs' => ['Khu vực lịch chiếu cho phép người dùng lọc theo thành phố, rạp, ngày và thương hiệu rạp. Tab thương hiệu được sinh động từ dữ liệu rạp trong database nên khi Admin thêm rạp hoặc thương hiệu mới, frontend tự cập nhật mà không cần hard-code giao diện.'], 'bullets' => ['Chọn thành phố, chọn rạp, chọn ngày và chọn suất chiếu.', 'Lọc theo thương hiệu rạp và hiển thị logo rạp.', 'Tab thương hiệu rạp sinh động từ database.', 'Nhận diện các thương hiệu như CGV, Lotte, Galaxy, BHD, Beta, Cinestar, NCC và thương hiệu mới.', 'Danh sách rạp đặt bên trái, nội dung lịch chiếu đặt bên phải.']],
                    ['title' => '4.10. Gần bạn', 'paragraphs' => ['Chức năng gần bạn xin quyền định vị từ trình duyệt, lấy latitude và longitude của người dùng, sau đó tính khoảng cách tới các rạp có tọa độ. Danh sách rạp được sắp xếp theo khoảng cách gần nhất để người dùng chọn rạp thuận tiện.'], 'bullets' => ['Xin quyền định vị qua trình duyệt.', 'Lấy latitude và longitude.', 'Tính khoảng cách tới rạp.', 'Sắp xếp rạp gần nhất.', 'Xử lý trường hợp người dùng từ chối quyền vị trí.']],
                    ['title' => '4.11. Dẫn đường', 'paragraphs' => ['Chức năng dẫn đường mở Google Maps trong tab mới. Nếu rạp có tọa độ, hệ thống ưu tiên dùng latitude và longitude; nếu chưa có tọa độ, hệ thống dùng địa chỉ rạp làm fallback.']],
                    ['title' => '4.12. Chọn suất chiếu', 'paragraphs' => ['Người dùng chọn ngày chiếu và giờ chiếu từ lịch chiếu hợp lệ. Hệ thống kiểm tra suất chiếu trước khi chuyển sang màn hình chọn ghế nhằm tránh đặt vé cho suất không tồn tại hoặc không còn khả dụng.']],
                    ['title' => '4.13. Chọn ghế', 'paragraphs' => ['Màn hình chọn ghế hiển thị sơ đồ ghế của phòng chiếu theo suất đã chọn. Người dùng có thể chọn ghế thường hoặc ghế VIP, trong khi ghế đã đặt và ghế bảo trì bị khóa để tránh đặt trùng hoặc đặt ghế không khả dụng.'], 'bullets' => ['Hiển thị sơ đồ ghế.', 'Phân biệt ghế thường, ghế VIP, ghế đã đặt và ghế bảo trì.', 'Tính tổng tiền theo loại ghế.', 'Không cho chọn ghế đã đặt hoặc ghế bảo trì.']],
                    ['title' => '4.14. Checkout', 'paragraphs' => ['Checkout là bước xác nhận cuối cùng trước khi tạo booking. Người dùng xem lại phim, rạp, phòng, suất chiếu, ghế đã chọn và tổng tiền để bảo đảm thông tin chính xác.']],
                    ['title' => '4.15. Đặt vé thành công', 'paragraphs' => ['Khi đặt vé thành công, hệ thống tạo booking code, booking chính và các booking seats tương ứng với ghế đã chọn. Sau đó hệ thống hiển thị thông tin vé cho người dùng.']],
                    ['title' => '4.16. Vé điện tử', 'paragraphs' => ['Vé điện tử là bằng chứng đặt vé của khách hàng. Vé gồm mã vé, QR code, thông tin phim, rạp, phòng, ghế, ngày giờ chiếu và tổng tiền. Nhân viên có thể dùng mã vé hoặc QR để kiểm tra tại rạp.']],
                    ['title' => '4.17. Lịch sử đặt vé', 'paragraphs' => ['Khách hàng có thể xem danh sách booking đã tạo, trạng thái vé, ngày đặt, tổng tiền và truy cập trang chi tiết vé. Chức năng này giúp người dùng theo dõi giao dịch và lấy lại vé khi cần.']],
                    ['title' => '4.18. Đánh giá phim', 'paragraphs' => ['Sau khi xem phim hoặc khi muốn chia sẻ cảm nhận, khách hàng có thể chọn số sao và nhập nhận xét. Đánh giá được lưu để hiển thị hoặc để Admin quản lý, bao gồm xóa nội dung không phù hợp.']],
                ],
            ],
            [
                'title' => '5. Chức năng Staff / Nhân viên',
                'subsections' => [
                    ['title' => '5.1. Đăng nhập Staff', 'paragraphs' => ['Staff đăng nhập bằng tài khoản được cấp quyền nhân viên. Hệ thống kiểm tra role staff trước khi cho truy cập dashboard staff.']],
                    ['title' => '5.2. Dashboard Staff', 'paragraphs' => ['Dashboard Staff cung cấp các truy cập nhanh phục vụ vận hành tại rạp như kiểm tra vé, bán vé tại quầy và xem các nghiệp vụ cần xử lý trong ca làm việc.']],
                    ['title' => '5.3. Kiểm tra vé', 'paragraphs' => ['Nhân viên nhập mã vé hoặc quét QR để tra cứu booking. Hệ thống hiển thị thông tin vé gồm phim, rạp, phòng, ghế, suất chiếu, trạng thái thanh toán và trạng thái sử dụng.']],
                    ['title' => '5.4. Vé hợp lệ', 'paragraphs' => ['Khi vé tồn tại, đã thanh toán và chưa sử dụng, nhân viên xác nhận cho khách vào rạp. Hệ thống cập nhật vé sang trạng thái đã sử dụng để ngăn dùng lại.']],
                    ['title' => '5.5. Vé đã sử dụng', 'paragraphs' => ['Nếu vé đã được xác nhận trước đó, hệ thống cảnh báo vé đã dùng và không cho xác nhận lại. Điều này giúp hạn chế gian lận vé.']],
                    ['title' => '5.6. Vé không tồn tại', 'paragraphs' => ['Nếu mã vé không khớp booking nào trong hệ thống, giao diện thông báo không tìm thấy vé để nhân viên xử lý với khách hàng.']],
                    ['title' => '5.7. Bán vé tại quầy', 'paragraphs' => ['Nhân viên có thể chọn phim, chọn suất chiếu, chọn ghế và tạo booking trực tiếp cho khách mua tại quầy. Chức năng này giúp MovieMate hỗ trợ cả kênh online và offline.']],
                ],
            ],
            [
                'title' => '6. Chức năng Admin / Quản trị viên',
                'subsections' => [
                    ['title' => '6.1. Đăng nhập Admin', 'paragraphs' => ['Admin đăng nhập bằng tài khoản quản trị. Hệ thống xác thực thông tin, kiểm tra role admin và chuyển vào dashboard quản trị.']],
                    ['title' => '6.2. Dashboard Admin', 'paragraphs' => ['Dashboard Admin hiển thị tổng quan vận hành: tổng số phim, tổng số rạp, tổng số suất chiếu, tổng vé đặt, tổng người dùng và tổng quan doanh thu.']],
                    ['title' => '6.3. Quản lý phim', 'paragraphs' => ['Admin quản lý toàn bộ dữ liệu phim, bao gồm danh sách phim, tìm kiếm, thêm, sửa, xem chi tiết và xóa phim. Khi nhập phim, Admin có thể upload poster, cover image, trailer URL, thể loại và trạng thái now_showing hoặc coming_soon.']],
                    ['title' => '6.4. Quản lý thể loại', 'paragraphs' => ['Chức năng quản lý thể loại cho phép tạo danh mục phim gồm tên, slug và mô tả. Admin có thể thêm, sửa hoặc xóa thể loại để phục vụ lọc phim và hiển thị chi tiết phim.']],
                    ['title' => '6.5. Quản lý rạp', 'paragraphs' => ['Admin quản lý danh sách rạp với đầy đủ thông tin tên rạp, địa chỉ, thành phố, số điện thoại, mô tả, hình ảnh hoặc logo, vĩ độ, kinh độ và trạng thái hoạt động. Dữ liệu này được dùng cho lịch chiếu, gần bạn và dẫn đường.']],
                    ['title' => '6.6. Quản lý thương hiệu rạp động', 'paragraphs' => ['Hệ thống sinh tab thương hiệu từ bảng cinemas, nhận diện các thương hiệu phổ biến như CGV, Lotte, Galaxy, BHD, Beta, Cinestar, NCC và các thương hiệu mới. Logo lấy từ ảnh rạp upload trong Admin; nếu thiếu ảnh, giao diện dùng chữ viết tắt làm fallback.']],
                    ['title' => '6.7. Quản lý phòng chiếu', 'paragraphs' => ['Admin quản lý phòng chiếu thuộc từng rạp, gồm danh sách phòng, thêm, sửa, xóa, tên phòng, loại phòng, tổng số ghế và trạng thái. Phòng chiếu là dữ liệu bắt buộc để tạo ghế và suất chiếu.']],
                    ['title' => '6.8. Quản lý ghế', 'paragraphs' => ['Admin tạo và quản lý ghế cho từng phòng, gồm mã ghế, loại ghế thường hoặc VIP và trạng thái hoạt động hoặc bảo trì. Trạng thái ghế ảnh hưởng trực tiếp tới màn hình chọn ghế của khách hàng.']],
                    ['title' => '6.9. Quản lý suất chiếu', 'paragraphs' => ['Admin tạo suất chiếu bằng cách chọn phim, rạp, phòng, ngày chiếu, giờ chiếu, giá vé thường, giá vé VIP và trạng thái. Suất chiếu là trung tâm của quy trình đặt vé.']],
                    ['title' => '6.10. Quản lý vé đặt', 'paragraphs' => ['Admin xem danh sách booking và chi tiết booking, bao gồm người đặt, phim, rạp, phòng, ghế, tổng tiền, trạng thái thanh toán và trạng thái sử dụng vé. Chức năng này hỗ trợ kiểm soát đơn đặt vé.']],
                    ['title' => '6.11. Quản lý người dùng', 'paragraphs' => ['Admin quản lý danh sách user, xem chi tiết user, role admin/staff/user, email, số điện thoại và trạng thái tài khoản. Đây là nền tảng của phân quyền hệ thống.']],
                    ['title' => '6.12. Quản lý đánh giá', 'paragraphs' => ['Admin xem danh sách đánh giá, người đánh giá, phim được đánh giá, số sao và nội dung. Admin có thể xóa đánh giá không phù hợp để giữ chất lượng nội dung.']],
                    ['title' => '6.13. Thống kê doanh thu', 'paragraphs' => ['Hệ thống hỗ trợ thống kê tổng doanh thu, doanh thu theo thời gian và doanh thu theo phim. Dữ liệu này giúp quản trị viên theo dõi hiệu quả vận hành.']],
                    ['title' => '6.14. Thống kê phim bán chạy', 'paragraphs' => ['Admin có thể theo dõi số vé bán theo phim và các phim có doanh thu cao. Kết quả thống kê giúp đưa ra quyết định tăng suất chiếu hoặc ưu tiên truyền thông.']],
                    ['title' => '6.15. AI Tools Admin', 'paragraphs' => ['AI Tools hỗ trợ Admin tạo mô tả phim, gợi ý nội dung marketing và tạo mô tả ngắn. Chức năng này giúp tăng tốc nhập liệu và cải thiện chất lượng nội dung hiển thị.']],
                ],
            ],
            [
                'title' => '7. Chức năng AI',
                'subsections' => [
                    ['title' => '7.1. AI gợi ý phim', 'paragraphs' => ['Người dùng nhập nhu cầu xem phim như thể loại yêu thích, tâm trạng hoặc mong muốn nội dung. AI phân tích yêu cầu và gợi ý phim phù hợp, có thể dựa theo thể loại, trạng thái phim và lịch sử đặt vé nếu dữ liệu sẵn có.']],
                    ['title' => '7.2. Chatbot hỗ trợ khách hàng', 'paragraphs' => ['Chatbot hỗ trợ trả lời câu hỏi về đặt vé, lịch chiếu, chọn ghế, kiểm tra vé, tài khoản và lịch sử đặt vé. Chatbot giúp giảm thao tác tìm kiếm thông tin và hỗ trợ khách hàng nhanh hơn.']],
                    ['title' => '7.3. AI hỗ trợ Admin', 'paragraphs' => ['AI hỗ trợ Admin tạo mô tả phim, nội dung quảng bá và mô tả ngắn. Đây là công cụ tăng tốc nhập liệu, đặc biệt khi quản trị nhiều phim hoặc cần nội dung marketing nhanh.']],
                ],
            ],
            [
                'title' => '8. Gửi vé qua email',
                'paragraphs' => [
                    'Sau khi đặt vé thành công, hệ thống có thể gửi email cho khách hàng thông qua Laravel Mail SMTP. Email gồm mã vé, phim, rạp, phòng, ngày giờ chiếu, ghế, tổng tiền và có thể kèm QR code.',
                    'Nếu gửi email lỗi, booking vẫn được ghi nhận thành công và lỗi gửi mail được ghi log để quản trị viên kiểm tra sau. Thiết kế này tránh làm thất bại giao dịch chỉ vì sự cố email.',
                ],
            ],
            [
                'title' => '9. Luồng nghiệp vụ chính',
                'subsections' => [
                    ['title' => '9.1. Luồng Admin tạo dữ liệu', 'bullets' => ['Tạo thể loại.', 'Tạo phim.', 'Tạo rạp.', 'Tạo phòng.', 'Tạo ghế.', 'Tạo suất chiếu.', 'User xem lịch chiếu từ dữ liệu đã được công bố.']],
                    ['title' => '9.2. Luồng khách hàng đặt vé', 'bullets' => ['Đăng nhập.', 'Chọn phim hoặc lịch chiếu.', 'Chọn rạp.', 'Chọn suất chiếu.', 'Chọn ghế.', 'Checkout.', 'Tạo booking.', 'Nhận vé điện tử.', 'Nhận email nếu hệ thống đã cấu hình SMTP.']],
                    ['title' => '9.3. Luồng nhân viên kiểm tra vé', 'bullets' => ['Nhập mã vé hoặc quét QR.', 'Tìm booking.', 'Kiểm tra trạng thái vé.', 'Xác nhận vé hợp lệ.', 'Cập nhật vé đã sử dụng.']],
                ],
            ],
            [
                'title' => '10. Cấu trúc dữ liệu chính',
                'paragraphs' => ['Các bảng dữ liệu chính của MovieMate phục vụ quản lý tài khoản, phim, rạp, lịch chiếu, đặt vé và đánh giá. Tùy theo cấu hình thanh toán, hệ thống có thể có thêm bảng payments.'],
                'bullets' => [
                    'users: lưu tài khoản, thông tin cá nhân, role và trạng thái tài khoản.',
                    'roles: lưu nhóm quyền hoặc định danh vai trò admin, staff và user nếu hệ thống tách bảng role.',
                    'movies: lưu thông tin phim, poster, cover, trailer, mô tả, thời lượng, ngày khởi chiếu và trạng thái.',
                    'genres: lưu thể loại phim, slug và mô tả.',
                    'cinemas: lưu thông tin rạp, địa chỉ, thành phố, logo, tọa độ và trạng thái.',
                    'rooms: lưu phòng chiếu thuộc rạp.',
                    'seats: lưu ghế theo phòng, mã ghế, loại ghế và trạng thái.',
                    'showtimes: lưu suất chiếu, phim, rạp, phòng, ngày giờ và giá vé.',
                    'bookings: lưu giao dịch đặt vé, user, showtime, booking code, tổng tiền và trạng thái.',
                    'booking_seats: lưu các ghế thuộc một booking.',
                    'reviews: lưu đánh giá phim của người dùng.',
                    'payments: lưu thông tin thanh toán nếu hệ thống tích hợp cổng thanh toán.',
                ],
            ],
            [
                'title' => '11. Quan hệ dữ liệu',
                'bullets' => [
                    'Một phim có nhiều suất chiếu.',
                    'Một rạp có nhiều phòng.',
                    'Một phòng có nhiều ghế.',
                    'Một suất chiếu thuộc một phim, một rạp và một phòng.',
                    'Một booking thuộc một user và một showtime.',
                    'Một booking có nhiều ghế thông qua booking_seats.',
                    'Một phim có nhiều đánh giá.',
                    'Một user có nhiều booking.',
                ],
            ],
            [
                'title' => '12. Bảo mật và phân quyền',
                'paragraphs' => ['MovieMate sử dụng các cơ chế bảo mật nền tảng của Laravel kết hợp phân quyền theo role để giới hạn truy cập đúng khu vực chức năng.'],
                'bullets' => [
                    'Middleware auth bảo vệ các chức năng yêu cầu đăng nhập.',
                    'Middleware role hoặc middleware riêng cho admin, staff và user.',
                    'CSRF token bảo vệ form khỏi tấn công giả mạo request.',
                    'Mật khẩu được hash trước khi lưu.',
                    'Phân quyền admin/staff/user theo vai trò tài khoản.',
                    'User chưa đăng nhập không được đặt vé.',
                ],
            ],
            [
                'title' => '13. Giao diện hệ thống',
                'paragraphs' => ['Giao diện MovieMate theo phong cách cinema hiện đại, ưu tiên trải nghiệm trực quan khi xem phim, chọn lịch chiếu và chọn ghế. Hệ thống hỗ trợ responsive cho desktop, tablet và mobile.'],
                'bullets' => [
                    'Dark cinema theme phù hợp ngữ cảnh phim ảnh.',
                    'Light/dark mode cho người dùng frontend.',
                    'Admin dashboard tập trung dữ liệu quản trị và thống kê.',
                    'Staff panel đơn giản, phục vụ kiểm tra vé và bán vé tại quầy.',
                    'User frontend hiện đại, dễ thao tác trên nhiều kích thước màn hình.',
                ],
            ],
            [
                'title' => '14. Điểm nổi bật',
                'bullets' => [
                    'Đầy đủ luồng đặt vé từ xem phim đến nhận vé điện tử.',
                    'Có ba phân hệ User, Staff và Admin.',
                    'Có chọn ghế theo sơ đồ phòng chiếu.',
                    'Có vé điện tử và QR check ticket.',
                    'Có AI gợi ý phim và chatbot hỗ trợ.',
                    'Có tính năng gần bạn và dẫn đường Google Maps.',
                    'Có gửi email ticket.',
                    'Có thống kê doanh thu và phim bán chạy.',
                ],
            ],
            [
                'title' => '15. Hạn chế và hướng phát triển',
                'bullets' => [
                    'Tích hợp thanh toán online qua VNPay, MoMo hoặc ZaloPay.',
                    'Bổ sung QR scanner bằng camera cho Staff.',
                    'Nâng cấp AI gợi ý phim theo lịch sử đặt vé, hành vi xem và đánh giá.',
                    'Bổ sung hoàn vé hoặc đổi vé theo chính sách rạp.',
                    'Tối ưu concurrency khi nhiều người đặt cùng ghế trong cùng thời điểm.',
                    'Deploy hệ thống lên hosting hoặc VPS để phục vụ môi trường thật.',
                ],
            ],
            [
                'title' => '16. Kết luận',
                'paragraphs' => [
                    'MovieMate là hệ thống đặt vé xem phim trực tuyến có đầy đủ các phân hệ cần thiết cho một bài toán thực tế: khách hàng đặt vé, nhân viên kiểm tra vé và quản trị viên vận hành dữ liệu. Hệ thống cũng tích hợp AI để nâng cao trải nghiệm người dùng và hỗ trợ nhập liệu quản trị.',
                    'Với các chức năng như chọn ghế, vé điện tử, QR check ticket, gợi ý phim bằng AI, chatbot, email ticket, gần bạn, dẫn đường và thống kê doanh thu, MovieMate đáp ứng tốt yêu cầu của đề tài tốt nghiệp Website đặt vé xem phim trực tuyến tích hợp AI.',
                ],
            ],
        ];
    }
}
