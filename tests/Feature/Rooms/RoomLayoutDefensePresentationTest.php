<?php

namespace Tests\Feature\Rooms;

use App\Models\Room;
use App\Models\RoomLayout;
use App\Models\RoomLayoutCell;
use App\Models\RoomLayoutTemplate;
use App\Models\RoomLayoutTemplateCell;
use App\Models\Seat;
use Database\Seeders\DemoCinemaLayoutSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RoomLayoutDefensePresentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRbac();
        app()->setLocale('vi');
    }

    public function test_room_pages_separate_physical_facts_from_logical_layout_and_operational_state(): void
    {
        $room = Room::factory()->create([
            'code' => 'DEFENSE-UI',
            'name' => 'Phòng bảo vệ thuật ngữ rất dài để kiểm tra xuống dòng an toàn',
            'width_mm' => 7_500,
            'length_mm' => 10_000,
        ]);
        $layout = $this->publishedLayout($room);

        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)->get(route('admin.rooms.index'))
            ->assertOk()
            ->assertSee('Sơ đồ bố trí')
            ->assertSee('Lưới logic: 2 hàng × 4 cột')
            ->assertSee('Sức chứa vật lý');

        $this->actingAs($admin)->get(route('admin.rooms.show', $room))
            ->assertOk()
            ->assertSeeInOrder(['Thông tin vật lý', 'Kích thước phòng', '7,50 m × 10,00 m', 'Diện tích mặt bằng', '75,00 m²'])
            ->assertSeeInOrder(['Sơ đồ bố trí logic', 'Phiên bản sơ đồ', 'Trạng thái sơ đồ', 'Lưới logic', '2 hàng × 4 cột', 'Vị trí màn hình'])
            ->assertSee('Sức chứa vật lý')
            ->assertDontSee('Kích thước: 2 × 4');

        $this->get(route('admin.rooms.edit', $room))
            ->assertOk()
            ->assertSeeInOrder(['Thông tin vật lý', 'Kích thước phòng', 'Sơ đồ bố trí logic', 'Lưới logic và sức chứa vật lý'])
            ->assertSee('lưới bố trí logic và không có đơn vị mét');

        $this->get(route('admin.rooms.layout.show', $room))
            ->assertOk()
            ->assertSee('Chú thích cấu trúc')
            ->assertSee('Ghế')
            ->assertSee('Lối đi')
            ->assertSee('Vật cản cố định')
            ->assertSee('Ô trống')
            ->assertSee('Ghế bảo trì vẫn là một Seat vật lý tạm thời không khả dụng')
            ->assertSee('Sao chép thành bản nháp phiên bản 2')
            ->assertDontSee('Lưu bản nháp')
            ->assertDontSee('Phát hành '.$layout->display_name);

        $this->get(route('admin.rooms.layout.preview', ['room' => $room, 'version' => 1]))
            ->assertOk()
            ->assertSee('Lưới logic: 2 hàng × 4 cột')
            ->assertSee('Sức chứa vật lý: 5 vị trí')
            ->assertSee('Sơ đồ chỉ đọc, có thể cuộn ngang trên màn hình nhỏ', false)
            ->assertSee('Vật cản cố định là ô cấu trúc không có Seat');
    }

    public function test_template_pages_use_logical_grid_and_explain_copy_on_apply_without_physical_room_fields(): void
    {
        $template = RoomLayoutTemplate::query()->create([
            'code' => 'DEFENSE_TEMPLATE',
            'name' => 'Mẫu lưới bố trí có tên rất dài để kiểm tra khả năng xuống dòng trên màn hình nhỏ',
            'description' => null,
            'rows' => 2,
            'columns' => 4,
            'screen_position' => 'top',
            'status' => RoomLayoutTemplate::STATUS_ACTIVE,
        ]);
        foreach ([
            [1, 1, 'seat', 'normal', 'A1'],
            [2, 1, 'aisle', null, null],
            [3, 1, 'blocked', null, null],
        ] as [$x, $y, $type, $seatType, $label]) {
            RoomLayoutTemplateCell::query()->create([
                'room_layout_template_id' => $template->id,
                'x_position' => $x,
                'y_position' => $y,
                'cell_type' => $type,
                'seat_type' => $seatType,
                'seat_label' => $label,
                'seat_unit_key' => $label,
            ]);
        }

        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)->get(route('admin.layout-templates.index'))
            ->assertOk()
            ->assertSee('Mẫu lưới bố trí logic')
            ->assertSee('Lưới logic')
            ->assertSee('2 hàng × 4 cột')
            ->assertDontSee('<th>Kích thước</th>', false);

        $this->get(route('admin.layout-templates.show', $template))
            ->assertOk()
            ->assertSee('Mẫu lưới bố trí logic dùng lại khi cấu hình phòng chiếu')
            ->assertSee('Áp dụng mẫu sẽ tạo một sơ đồ phòng độc lập')
            ->assertSee('thay đổi sau này trên mẫu không làm thay đổi sơ đồ đã áp dụng', false)
            ->assertSee('Vị trí SEAT trong mẫu')
            ->assertSee('Chú thích cấu trúc')
            ->assertDontSee('Chiều rộng phòng')
            ->assertDontSee('Chiều dài phòng')
            ->assertDontSee('Diện tích mặt bằng');

        $this->get(route('admin.layout-templates.edit', $template))
            ->assertOk()
            ->assertSee('Hàng × cột là lưới logic, không phải kích thước phòng theo mét')
            ->assertSee('Ô trống là tọa độ không sử dụng và không được lưu thành một cell')
            ->assertSee('Sơ đồ ghế có thể cuộn ngang', false)
            ->assertDontSee('width_mm')
            ->assertDontSee('length_mm');
    }

    public function test_dedicated_demo_layout_is_coherent_without_replacing_a_seat_with_blocked(): void
    {
        $room = Room::factory()->create([
            'code' => 'DEMO',
            'name' => 'Phòng demo bảo vệ',
            'width_mm' => 6_500,
            'length_mm' => 9_000,
            'status' => 'active',
        ]);

        $this->seed(DemoCinemaLayoutSeeder::class);

        $layout = $room->fresh()->latestPublishedLayout;
        $this->assertNotNull($layout);
        $this->assertSame([1, 4, 8, 'published'], [$layout->version, $layout->rows, $layout->columns, $layout->status]);
        $this->assertSame([6_500, 9_000], [$room->fresh()->width_mm, $room->fresh()->length_mm]);
        $this->assertSame(26, $layout->cells()->where('cell_type', RoomLayoutCell::TYPE_SEAT)->count());
        $this->assertSame(4, $layout->cells()->where('cell_type', RoomLayoutCell::TYPE_AISLE)->count());
        $this->assertDatabaseHas('room_layout_cells', [
            'room_layout_id' => $layout->id,
            'x_position' => 7,
            'y_position' => 2,
            'cell_type' => RoomLayoutCell::TYPE_BLOCKED,
            'seat_id' => null,
        ]);
        $this->assertDatabaseMissing('room_layout_cells', [
            'room_layout_id' => $layout->id,
            'x_position' => 8,
            'y_position' => 2,
        ]);
        $this->assertSame(2, Seat::query()->where('room_id', $room->id)->where('type', 'couple')->count());
        $this->assertSame(1, Seat::query()->where('room_id', $room->id)->where('type', 'vip')->count());
        $this->assertSame(1, Seat::query()->where('room_id', $room->id)->where('status', 'maintenance')->count());
        $this->assertSame(25, Seat::query()->where('room_id', $room->id)->where('type', '!=', 'couple')->count()
            + Seat::query()->where('room_id', $room->id)->where('type', 'couple')->distinct()->count('pair_code'));
    }

    private function publishedLayout(Room $room): RoomLayout
    {
        $layout = RoomLayout::query()->create([
            'room_id' => $room->id,
            'version' => 1,
            'name' => 'Sơ đồ bảo vệ',
            'rows' => 2,
            'columns' => 4,
            'screen_position' => 'top',
            'status' => 'draft',
        ]);
        foreach ([
            [1, 1, 'normal', 'active'],
            [2, 1, 'vip', 'maintenance'],
            [1, 2, 'couple', 'active'],
            [2, 2, 'couple', 'active'],
            [3, 2, 'normal', 'active'],
        ] as $index => [$x, $y, $type, $status]) {
            $seat = Seat::query()->create([
                'room_id' => $room->id,
                'row' => chr(64 + $y),
                'number' => $x,
                'seat_code' => chr(64 + $y).$x,
                'type' => $type,
                'status' => $status,
                'pair_code' => $type === 'couple' ? 'DEFENSE-PAIR' : null,
                'pair_position' => $type === 'couple' ? ($index === 2 ? 'left' : 'right') : null,
            ]);
            RoomLayoutCell::query()->create([
                'room_layout_id' => $layout->id,
                'x_position' => $x,
                'y_position' => $y,
                'cell_type' => RoomLayoutCell::TYPE_SEAT,
                'seat_id' => $seat->id,
            ]);
        }
        foreach ([[3, 1, RoomLayoutCell::TYPE_AISLE], [4, 1, RoomLayoutCell::TYPE_BLOCKED]] as [$x, $y, $type]) {
            RoomLayoutCell::query()->create([
                'room_layout_id' => $layout->id,
                'x_position' => $x,
                'y_position' => $y,
                'cell_type' => $type,
            ]);
        }
        $layout->update(['status' => 'published', 'published_at' => now()]);

        return $layout->fresh(['cells.seat']);
    }
}
