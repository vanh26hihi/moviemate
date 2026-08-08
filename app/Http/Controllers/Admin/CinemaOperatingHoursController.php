<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cinema;
use App\Services\ActivityLogger;
use App\Services\CinemaAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CinemaOperatingHoursController extends Controller
{
    public function __construct(private readonly CinemaAccessService $access, private readonly ActivityLogger $activity) {}

    public function update(Request $request, Cinema $cinema)
    {
        $this->access->authorizeCinema($request->user(), (int) $cinema->id);
        $data = $request->validate([
            'hours' => ['required', 'array', 'size:7'], 'hours.*.day_of_week' => ['required', 'integer', 'between:1,7', 'distinct'],
            'hours.*.is_closed' => ['nullable', 'boolean'], 'hours.*.opens_at' => ['nullable', 'date_format:H:i'],
            'hours.*.latest_show_start_at' => ['nullable', 'date_format:H:i'],
            'default_cleaning_buffer_minutes' => ['required', 'integer', 'between:0,180'],
        ]);
        $before = ['cinema_id' => $cinema->id, 'cleaning_buffer' => $cinema->default_cleaning_buffer_minutes];
        DB::transaction(function () use ($cinema, $data, $before): void {
            $cinema->update(['default_cleaning_buffer_minutes' => $data['default_cleaning_buffer_minutes']]);
            foreach ($data['hours'] as $row) {
                $closed = (bool) ($row['is_closed'] ?? false);
                if (! $closed && (empty($row['opens_at']) || empty($row['latest_show_start_at']))) {
                    throw ValidationException::withMessages(['hours' => 'Ngày mở cửa phải có giờ mở cửa và giờ nhận suất cuối.']);
                }
                if (! $closed && $row['opens_at'] > $row['latest_show_start_at']) {
                    throw ValidationException::withMessages(['hours' => 'Giờ nhận suất cuối không được sớm hơn giờ mở cửa.']);
                }
                $cinema->operatingHours()->updateOrCreate(['day_of_week' => $row['day_of_week']], [
                    'is_closed' => $closed, 'opens_at' => $closed ? null : $row['opens_at'],
                    'latest_show_start_at' => $closed ? null : $row['latest_show_start_at'],
                ]);
            }
            $this->activity->log('cinema.operating_hours_updated', $cinema, $before, ['cinema_id' => $cinema->id, 'cleaning_buffer' => $cinema->default_cleaning_buffer_minutes]);
        });

        return back()->with('success', 'Đã cập nhật giờ hoạt động.');
    }
}
