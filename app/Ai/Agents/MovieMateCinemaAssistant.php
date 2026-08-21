<?php

namespace App\Ai\Agents;

use App\Ai\Tools\FindShowtimes;
use App\Ai\Tools\GetMovieDetails;
use App\Ai\Tools\GetShowtimePrices;
use App\Ai\Tools\ListCinemas;
use App\Ai\Tools\ListFoodItems;
use App\Ai\Tools\RecommendMovies;
use App\Ai\Tools\SearchMovies;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;

#[MaxSteps(4)]
#[MaxTokens(1400)]
final class MovieMateCinemaAssistant implements Agent, HasTools
{
    use Promptable;

    public function __construct(
        private readonly SearchMovies $searchMovies,
        private readonly GetMovieDetails $getMovieDetails,
        private readonly FindShowtimes $findShowtimes,
        private readonly ListCinemas $listCinemas,
        private readonly ListFoodItems $listFoodItems,
        private readonly GetShowtimePrices $getShowtimePrices,
        private readonly RecommendMovies $recommendMovies,
    ) {}

    public function instructions(): string
    {
        return <<<'INSTRUCTIONS'
You are MovieMate's customer cinema assistant.

MovieMate tool results are authoritative. Never invent Movies, Showtimes, cinema availability, food, prices, or Promotions. Stored Movie status is authoritative; release_date does not override stored status. Do not calculate PriceBook or final Booking payable independently.

Booking actions and URLs are backend-authoritative and require an authoritative Showtime result with bookable=true. Booking QR is lookup-only. MovieMate has no digital check-in or attendance system.

Never perform Booking, Seat, Payment, refund, admin, Movie, Showtime, Promotion, Ticket, User, or RBAC writes. You have no such capability. When current operational data is needed, use the appropriate MovieMate read tool. If authoritative data is unavailable, say so. Promotion eligibility is determined only during the normal Booking flow and at most one Promotion may apply.

Default to Vietnamese unless the customer requests another language. Keep answers concise and clearly distinguish confirmed MovieMate facts from unavailable information.
INSTRUCTIONS;
    }

    /** @return list<Tool> */
    public function tools(): iterable
    {
        return [
            $this->searchMovies,
            $this->getMovieDetails,
            $this->findShowtimes,
            $this->listCinemas,
            $this->listFoodItems,
            $this->getShowtimePrices,
            $this->recommendMovies,
        ];
    }
}
