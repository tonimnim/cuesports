<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\GameMatch as MatchModel;
use App\Models\User;
use App\Services\MatchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class SupportDashboardController extends Controller
{
    // Cache TTL in seconds (2 minutes for dashboard stats - needs to be relatively fresh)
    private const CACHE_TTL_STATS = 120;

    public function __construct(
        private MatchService $matchService
    ) {}

    public function index(): Response
    {
        $stats = Cache::remember('support:dashboard:stats', self::CACHE_TTL_STATS, function () {
            return [
                'pending_disputes' => MatchModel::disputed()->count(),
                'resolved_today' => MatchModel::where('status', 'completed')
                    ->whereNotNull('resolved_at')
                    ->whereDate('resolved_at', today())
                    ->count(),
                'inactive_users' => User::where('is_active', false)->count(),
                'total_users' => User::count(),
            ];
        });

        // Recent disputes - cache for 1 minute (needs to be fresh)
        $recentDisputes = Cache::remember('support:dashboard:recent_disputes', 60, function () {
            return $this->matchService->getDisputedMatches()
                ->take(5)
                ->map(function ($match) {
                    return [
                        'id' => $match->id,
                        'tournament' => [
                            'id' => $match->tournament->id,
                            'name' => $match->tournament->name,
                        ],
                        'player1' => $this->formatPlayer($match->player1),
                        'player2' => $this->formatPlayer($match->player2),
                        'player1_score' => $match->player1_score,
                        'player2_score' => $match->player2_score,
                        'disputed_at' => $match->disputed_at?->toISOString(),
                        'dispute_reason' => $match->dispute_reason,
                        'round_name' => $match->round_name,
                    ];
                });
        });

        return Inertia::render('Support/Index', [
            'stats' => $stats,
            'recentDisputes' => $recentDisputes,
        ]);
    }

    private function formatPlayer($participant): ?array
    {
        if (!$participant || !$participant->playerProfile) {
            return null;
        }

        $profile = $participant->playerProfile;
        return [
            'id' => $participant->id,
            'player_profile' => [
                'id' => $profile->id,
                'first_name' => $profile->first_name,
                'last_name' => $profile->last_name,
                'nickname' => $profile->nickname,
                'photo_url' => $profile->photo_url,
                'rating' => $profile->rating,
            ],
        ];
    }

    public static function clearDashboardCache(): void
    {
        Cache::forget('support:dashboard:stats');
        Cache::forget('support:dashboard:recent_disputes');
    }
}
