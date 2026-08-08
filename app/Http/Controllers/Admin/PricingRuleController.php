<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\PricingConfigurationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SavePricingRuleRequest;
use App\Models\CinemaPricingRule;
use App\Models\Room;
use App\Models\Showtime;
use App\Services\ActivityLogger;
use App\Services\CinemaAccessService;
use App\Services\TicketPricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PricingRuleController extends Controller
{
    public function __construct(
        private readonly CinemaAccessService $access,
        private readonly TicketPricingService $pricing,
        private readonly ActivityLogger $activity,
    ) {}

    public function index(Request $request)
    {
        $query = CinemaPricingRule::query()->with(['cinema:id,code,name', 'room:id,cinema_id,code,name']);
        if (! $this->access->hasGlobalAccess($request->user())) {
            $ids = $this->access->accessibleCinemas($request->user())->pluck('id');
            $query->where(fn ($query) => $query->whereNull('cinema_id')->orWhereIn('cinema_id', $ids));
        } elseif ($cinemaId = $this->access->currentCinemaId($request->user())) {
            $query->where(fn ($query) => $query->whereNull('cinema_id')->orWhere('cinema_id', $cinemaId));
        }
        foreach (['rule_type', 'seat_type', 'room_type', 'status'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->string($field));
            }
        }
        if ($request->filled('cinema_id')) {
            $query->where('cinema_id', $request->integer('cinema_id'));
        }
        if ($request->filled('room_id')) {
            $query->where('room_id', $request->integer('room_id'));
        }
        if ($request->filled('special_date')) {
            $date = $request->validate(['special_date' => ['date_format:Y-m-d']])['special_date'];
            $query->whereDate('date_start', '<=', $date)->whereDate('date_end', '>=', $date);
        }

        return view('admin.pricing-rules.index', [
            'rules' => $query->orderByDesc('priority')->orderBy('id')->paginate(20)->withQueryString(),
            ...$this->formOptions($request),
        ]);
    }

    public function create(Request $request)
    {
        return view('admin.pricing-rules.form', ['rule' => new CinemaPricingRule, ...$this->formOptions($request)]);
    }

    public function store(SavePricingRuleRequest $request)
    {
        $data = $this->authorizedData($request, $request->validated());
        $rule = DB::transaction(function () use ($request, $data): CinemaPricingRule {
            $rule = CinemaPricingRule::query()->create([...$data, 'created_by_user_id' => $request->user()->id]);
            $this->activity->log('pricing_rule.created', $rule, after: $this->auditData($rule));

            return $rule;
        });

        return redirect()->route('admin.pricing-rules.edit', $rule)->with('success', 'Đã tạo quy tắc giá.');
    }

    public function edit(Request $request, CinemaPricingRule $pricingRule)
    {
        $this->authorizeRule($request, $pricingRule, true);

        return view('admin.pricing-rules.form', ['rule' => $pricingRule, ...$this->formOptions($request)]);
    }

    public function update(SavePricingRuleRequest $request, CinemaPricingRule $pricingRule)
    {
        $this->authorizeRule($request, $pricingRule, true);
        $data = $this->authorizedData($request, $request->validated());
        $before = $this->auditData($pricingRule);
        DB::transaction(function () use ($pricingRule, $data, $before): void {
            $pricingRule->update($data);
            $this->activity->log('pricing_rule.updated', $pricingRule, $before, $this->auditData($pricingRule));
        });

        return back()->with('success', 'Đã cập nhật quy tắc giá.');
    }

    public function status(Request $request, CinemaPricingRule $pricingRule)
    {
        $this->authorizeRule($request, $pricingRule, true);
        $status = $request->validate(['status' => ['required', Rule::in(['active', 'inactive'])]])['status'];
        $before = $this->auditData($pricingRule);
        DB::transaction(function () use ($pricingRule, $status, $before): void {
            $pricingRule->update(['status' => $status]);
            $this->activity->log('pricing_rule.'.($status === 'active' ? 'activated' : 'deactivated'), $pricingRule, $before, $this->auditData($pricingRule));
        });

        return back()->with('success', $status === 'active' ? 'Đã kích hoạt quy tắc giá.' : 'Đã tạm ngừng quy tắc giá.');
    }

    public function preview(Request $request)
    {
        $data = $request->validate([
            'cinema_id' => ['required', 'integer', 'exists:cinemas,id'],
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'show_date' => ['required', 'date_format:Y-m-d'],
            'show_time' => ['required', 'date_format:H:i'],
            'seat_type' => ['required', Rule::in(['normal', 'vip', 'couple'])],
        ]);
        $this->access->authorizeCinema($request->user(), (int) $data['cinema_id']);
        $room = Room::query()->with('cinema')->whereKey($data['room_id'])->where('cinema_id', $data['cinema_id'])->firstOrFail();
        $showtime = new Showtime([
            'cinema_id' => $room->cinema_id, 'room_id' => $room->id,
            'show_date' => $data['show_date'], 'show_time' => $data['show_time'].':00',
        ]);
        $showtime->setRelation('cinema', $room->cinema);
        $showtime->setRelation('room', $room);
        try {
            $price = $this->pricing->calculate($showtime, $data['seat_type'], false);
        } catch (PricingConfigurationException $exception) {
            throw ValidationException::withMessages(['pricing' => $exception->getMessage()]);
        }

        return response()->json(['base_amount' => $price->baseAmount, 'surcharges' => $price->surcharges, 'final_amount' => $price->finalAmount]);
    }

    private function authorizedData(Request $request, array $data): array
    {
        $cinemaId = isset($data['cinema_id']) ? (int) $data['cinema_id'] : null;
        if ($cinemaId === null && ! $this->access->hasGlobalAccess($request->user())) {
            throw ValidationException::withMessages(['cinema_id' => 'Manager không thể tạo quy tắc toàn hệ thống.']);
        }
        if ($cinemaId) {
            $this->access->authorizeCinema($request->user(), $cinemaId);
        }
        if (! empty($data['room_id'])) {
            $room = Room::query()->findOrFail((int) $data['room_id']);
            if ($cinemaId === null || (int) $room->cinema_id !== $cinemaId) {
                throw ValidationException::withMessages(['room_id' => 'Phòng phải thuộc đúng chi nhánh của quy tắc.']);
            }
        }
        if ($data['rule_type'] === 'base' && (int) $data['amount_vnd'] < 0) {
            throw ValidationException::withMessages(['amount_vnd' => 'Giá cơ bản không được âm.']);
        }
        if ($data['rule_type'] === 'holiday' && empty($data['date_end'])) {
            $data['date_end'] = $data['date_start'];
        }

        return $data;
    }

    private function authorizeRule(Request $request, CinemaPricingRule $rule, bool $manage): void
    {
        if ($rule->cinema_id === null) {
            abort_unless($this->access->hasGlobalAccess($request->user()), 404);
        } else {
            $this->access->authorizeCinema($request->user(), (int) $rule->cinema_id);
        }
        if ($manage) {
            abort_unless($request->user()->can('pricing.manage'), 403);
        }
    }

    private function formOptions(Request $request): array
    {
        $cinemas = $this->access->accessibleCinemas($request->user(), $this->access->hasGlobalAccess($request->user()));
        $rooms = Room::query()->whereIn('cinema_id', $cinemas->pluck('id'))->orderBy('code')->get();

        return compact('cinemas', 'rooms');
    }

    private function auditData(CinemaPricingRule $rule): array
    {
        return ['cinema_id' => $rule->cinema_id, 'room_id' => $rule->room_id, 'seat_type' => $rule->seat_type, 'price' => $rule->amount_vnd, 'status' => $rule->status];
    }
}
