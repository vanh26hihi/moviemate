<?php

namespace Database\Seeders;

use App\Models\Cinema;
use App\Models\CinemaPricingRule;
use App\Models\Permission;
use App\Models\Role;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class PricingRuleSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Bỏ qua dữ liệu bảng giá demo ngoài môi trường local/testing.');

            return;
        }

        $this->ensurePermissions();

        Cinema::query()->active()->with('rooms')->orderBy('id')->limit(3)->get()->each(function (Cinema $cinema): void {
            $cinema->update(['default_cleaning_buffer_minutes' => 15]);
            foreach (range(1, 7) as $day) {
                $cinema->operatingHours()->updateOrCreate(['day_of_week' => $day], [
                    'opens_at' => '08:00', 'latest_show_start_at' => '23:00', 'is_closed' => false,
                ]);
            }

            $rules = [
                ['name' => 'Giá cơ bản '.$cinema->code, 'rule_type' => 'base', 'amount_vnd' => 80_000],
                ['name' => 'Phụ thu VIP '.$cinema->code, 'rule_type' => 'seat_type', 'seat_type' => 'vip', 'amount_vnd' => 30_000],
                ['name' => 'Giá ghế đôi '.$cinema->code, 'rule_type' => 'seat_type', 'seat_type' => 'couple', 'amount_vnd' => 80_000],
                ['name' => 'Phụ thu suất tối '.$cinema->code, 'rule_type' => 'time_window', 'time_start' => '18:00', 'time_end' => '22:00', 'amount_vnd' => 15_000],
                ['name' => 'Phụ thu cuối tuần '.$cinema->code, 'rule_type' => 'weekend', 'days_of_week' => [6, 7], 'amount_vnd' => 10_000],
                ['name' => 'Ngày hội MovieMate '.$cinema->code, 'rule_type' => 'holiday', 'date_start' => CarbonImmutable::now($cinema->timezone)->addMonth()->startOfMonth()->toDateString(), 'date_end' => CarbonImmutable::now($cinema->timezone)->addMonth()->startOfMonth()->toDateString(), 'amount_vnd' => 20_000],
            ];
            foreach ($rules as $rule) {
                CinemaPricingRule::query()->updateOrCreate(
                    ['name' => $rule['name'], 'cinema_id' => $cinema->id],
                    [...$rule, 'cinema_id' => $cinema->id, 'priority' => 100, 'status' => 'active'],
                );
            }
        });
    }

    private function ensurePermissions(): void
    {
        $permissions = collect([
            'pricing.view' => 'Xem bảng giá vé',
            'pricing.manage' => 'Quản lý bảng giá vé',
            'cinemas.operations.manage' => 'Quản lý giờ hoạt động chi nhánh',
        ])->mapWithKeys(function (string $name, string $slug): array {
            $permission = Permission::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'group' => str($slug)->before('.')->toString()],
            );

            return [$slug => $permission->id];
        });

        Role::query()->where('slug', 'admin')->first()?->permissions()->syncWithoutDetaching($permissions->values()->all());
        Role::query()->where('slug', 'manager')->first()?->permissions()->syncWithoutDetaching($permissions->values()->all());
    }
}
