<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VoucherController extends Controller
{
    public function index(Request $request)
    {
        $query = Voucher::query();

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $vouchers = $query->latest()->paginate(15)->withQueryString();

        return view('admin.vouchers.index', compact('vouchers', 'search', 'status'));
    }

    public function create()
    {
        return view('admin.vouchers.create');
    }

    public function store(Request $request)
    {
        $validated = $this->validatedData($request);
        $validated['code'] = strtoupper($validated['code']);

        Voucher::create($validated);

        return redirect()
            ->route('admin.vouchers.index')
            ->with('success', 'Đã tạo voucher thành công.');
    }

    public function edit(Voucher $voucher)
    {
        return view('admin.vouchers.edit', compact('voucher'));
    }

    public function update(Request $request, Voucher $voucher)
    {
        $validated = $this->validatedData($request, $voucher);
        $validated['code'] = strtoupper($validated['code']);

        $voucher->update($validated);

        return redirect()
            ->route('admin.vouchers.index')
            ->with('success', 'Đã cập nhật voucher.');
    }

    public function destroy(Voucher $voucher)
    {
        if ($voucher->bookings()->exists()) {
            $voucher->update(['status' => 'inactive']);

            return redirect()
                ->route('admin.vouchers.index')
                ->with('success', 'Voucher đã có booking sử dụng nên hệ thống đã chuyển sang trạng thái tắt.');
        }

        $voucher->delete();

        return redirect()
            ->route('admin.vouchers.index')
            ->with('success', 'Đã xóa voucher.');
    }

    private function validatedData(Request $request, ?Voucher $voucher = null): array
    {
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('vouchers', 'code')->ignore($voucher?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'discount_type' => ['required', Rule::in(['fixed', 'percent'])],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $validated['min_order_amount'] = $validated['min_order_amount'] ?? 0;
        $validated['max_discount_amount'] = $validated['max_discount_amount'] ?? null;
        $validated['usage_limit'] = $validated['usage_limit'] ?? null;
        $validated['per_user_limit'] = $validated['per_user_limit'] ?? null;

        if ($validated['discount_type'] === 'percent' && (float) $validated['discount_value'] > 100) {
            throw ValidationException::withMessages([
                'discount_value' => 'Voucher phần trăm không được lớn hơn 100%.',
            ]);
        }

        return $validated;
    }
}
