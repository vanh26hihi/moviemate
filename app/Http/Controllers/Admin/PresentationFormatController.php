<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SavePresentationFormatRequest;
use App\Models\PresentationFormat;
use App\Services\PresentationFormatManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class PresentationFormatController extends Controller
{
    public function __construct(private readonly PresentationFormatManagementService $management) {}

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'archived'])],
        ]);
        $search = trim((string) ($validated['search'] ?? ''));
        $status = $validated['status'] ?? '';
        $formats = PresentationFormat::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")))
            ->when($status === 'active', fn ($query) => $query->active())
            ->when($status === 'archived', fn ($query) => $query->where('is_active', false))
            ->orderBy('sort_order')->orderBy('name')->paginate(20)->withQueryString();

        return view('admin.presentation-formats.index', compact('formats', 'search', 'status'));
    }

    public function create(): View
    {
        return view('admin.presentation-formats.form', ['presentationFormat' => new PresentationFormat]);
    }

    public function store(SavePresentationFormatRequest $request): RedirectResponse
    {
        $this->management->create($request->validated(), $request->user());

        return redirect()->route('admin.presentation-formats.index')
            ->with('success', 'Đã tạo định dạng trình chiếu.');
    }

    public function edit(PresentationFormat $presentationFormat): View
    {
        return view('admin.presentation-formats.form', compact('presentationFormat'));
    }

    public function update(SavePresentationFormatRequest $request, PresentationFormat $presentationFormat): RedirectResponse
    {
        $this->management->update($presentationFormat, $request->validated(), $request->user());

        return redirect()->route('admin.presentation-formats.index')
            ->with('success', 'Đã cập nhật định dạng trình chiếu.');
    }

    public function archive(Request $request, PresentationFormat $presentationFormat): RedirectResponse
    {
        $this->management->archive($presentationFormat, $request->user());

        return back()->with('success', 'Đã lưu trữ định dạng trình chiếu. Các tham chiếu hiện có được giữ nguyên.');
    }
}
