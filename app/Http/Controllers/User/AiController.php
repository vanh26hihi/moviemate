<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Cinema;
use App\Models\Genre;
use App\Services\AiChatbotService;
use App\Services\AiMovieRecommendationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AiController extends Controller
{
    public function recommend(Request $request): View
    {
        return $this->recommendationView(
            preferences: $request->session()->get('ai.recommend.preferences', []),
            result: $request->session()->get('ai.recommend.result'),
        );
    }

    public function recommendStore(Request $request, AiMovieRecommendationService $service): View
    {
        $preferences = $request->validate([
            'mood' => ['required', 'string', 'in:happy,sad,stress,chill,excited,romantic'],
            'genres' => ['nullable', 'array', 'max:5'],
            'genres.*' => ['string', 'max:100'],
            'companion' => ['required', 'string', 'in:alone,couple,friends,family'],
            'preferred_time' => ['required', 'string', 'in:tonight,tomorrow,weekend,after_21,morning,afternoon'],
            'location' => ['nullable', 'string', 'max:191'],
        ]);

        $preferences['genres'] = array_values(array_unique($preferences['genres'] ?? []));
        $result = $service->recommend($preferences);

        $request->session()->put([
            'ai.recommend.preferences' => $preferences,
            'ai.recommend.result' => $result,
        ]);

        return $this->recommendationView($preferences, $result);
    }

    public function chatbot(Request $request): View
    {
        $history = $this->chatHistory($request);

        return view('user.ai.chatbot', [
            'chatHistory' => $history,
            'currentChat' => $history->last(),
            'chatMeta' => $request->session()->get('ai.chat.meta'),
        ]);
    }

    public function chatbotStore(Request $request, AiChatbotService $service): RedirectResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $result = $service->answer($validated['message']);
        $history = $this->chatHistory($request);
        $history->push([
            'message' => $validated['message'],
            'response' => $result['answer'],
            'created_at' => now()->toIso8601String(),
        ]);

        $request->session()->put([
            'ai.chat.history' => $history->take(-20)->values()->all(),
            'ai.chat.meta' => $result,
        ]);

        return to_route('user.ai.chatbot');
    }

    private function recommendationView(array $preferences, ?array $result): View
    {
        return view('user.ai.recommend', [
            'preferences' => $preferences,
            'recommendations' => $result['recommendations'] ?? null,
            'recommendationMeta' => $result,
            'genres' => Genre::query()->orderBy('name')->get(),
            'cinemas' => Cinema::query()->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    private function chatHistory(Request $request): Collection
    {
        return collect($request->session()->get('ai.chat.history', []))
            ->filter(fn ($chat): bool => is_array($chat))
            ->map(function (array $chat): object {
                $chat['created_at'] = isset($chat['created_at'])
                    ? now()->parse($chat['created_at'])
                    : now();

                return (object) $chat;
            })
            ->values();
    }
}
