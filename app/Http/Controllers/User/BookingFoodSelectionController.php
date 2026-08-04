<?php

namespace App\Http\Controllers\User;

use App\Exceptions\FoodSelectionValidationException;
use App\Http\Controllers\Controller;
use App\Models\FoodItem;
use App\Services\BookingCheckoutDraftService;
use App\Services\BookingCheckoutPreviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BookingFoodSelectionController extends Controller
{
    public function __construct(
        private readonly BookingCheckoutDraftService $drafts,
        private readonly BookingCheckoutPreviewService $previews,
    ) {}

    public function show(Request $request): View
    {
        $draft = $this->drafts->current($request);

        return $this->view($draft);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_email' => ['required', 'email:rfc', 'max:255'],
            'food_items' => ['sometimes', 'array'],
            'food_items.*.food_id' => ['required', 'integer', 'distinct', 'min:1'],
            'food_items.*.quantity' => ['required', 'integer', 'min:0', 'max:'.config('booking.max_food_quantity', 20)],
            'skip_food' => ['sometimes', 'boolean'],
            'pickup_cinema_id' => ['prohibited'],
            'seat_price' => ['prohibited'],
            'food_subtotal' => ['prohibited'],
            'total_amount' => ['prohibited'],
        ]);

        $food = ($validated['skip_food'] ?? false) ? [] : ($validated['food_items'] ?? []);
        $draft = $this->drafts->updateContactAndFood(
            $request,
            $validated['customer_email'],
            $food,
        );

        try {
            $this->previews->preview($draft);
        } catch (FoodSelectionValidationException $exception) {
            throw ValidationException::withMessages(['food_items' => $exception->getMessage()]);
        }

        return redirect()->route('user.bookings.review');
    }

    private function view(array $draft): View
    {
        $preview = $this->previews->preview($draft);
        $foods = FoodItem::query()->where('active', true)->orderBy('name')->get();

        return view('user.bookings.food', compact('draft', 'preview', 'foods'));
    }
}
