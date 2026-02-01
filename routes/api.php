<?php

use App\Http\Controllers\Admin\PayoutController as AdminPayoutController;
use App\Http\Controllers\Admin\TournamentController as AdminTournamentController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\PasswordResetController;
use App\Http\Controllers\Api\GeographicUnitController;
use App\Http\Controllers\Api\MatchController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrganizerController;
use App\Http\Controllers\Api\OrganizerWalletController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\MpesaCallbackController;
use App\Http\Controllers\Api\PlayerController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\StatusController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\TournamentController;
use App\Http\Controllers\Support\PayoutController as SupportPayoutController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

// Health check for load balancers
Route::get('/health', [StatusController::class, 'health']);

// Full system status for status page
Route::get('/status', [StatusController::class, 'index']);

/*
|--------------------------------------------------------------------------
| Location Routes (Public - needed for registration)
|--------------------------------------------------------------------------
*/

Route::prefix('locations')->group(function () {
    Route::get('/countries', [GeographicUnitController::class, 'countries']);
    Route::get('/search', [GeographicUnitController::class, 'search']);
    Route::get('/{geographicUnit}', [GeographicUnitController::class, 'show']);
    Route::get('/{geographicUnit}/children', [GeographicUnitController::class, 'children']);
    Route::get('/{geographicUnit}/hierarchy', [GeographicUnitController::class, 'hierarchy']);
});

/*
|--------------------------------------------------------------------------
| Player Routes (Public - rankings and profiles)
|--------------------------------------------------------------------------
*/

Route::prefix('players')->group(function () {
    Route::get('/rankings', [PlayerController::class, 'rankings']);
});

/*
|--------------------------------------------------------------------------
| Article Routes (Public - news/blog)
|--------------------------------------------------------------------------
*/

Route::prefix('articles')->group(function () {
    Route::get('/', [ArticleController::class, 'index']);
    Route::get('/trending', [ArticleController::class, 'trending']);
    Route::get('/featured', [ArticleController::class, 'featured']);
    Route::get('/{slug}', [ArticleController::class, 'show'])->name('api.articles.show');
    Route::get('/{slug}/related', [ArticleController::class, 'related']);
});

/*
|--------------------------------------------------------------------------
| Country Routes (Public - rankings)
|--------------------------------------------------------------------------
*/

Route::prefix('countries')->group(function () {
    Route::get('/rankings', [PlayerController::class, 'countryRankings']);
});

/*
|--------------------------------------------------------------------------
| Auth Routes (Guest) - Rate Limited for Security
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
    // Registration - 5 attempts per minute per IP
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:5,1');

    // Email verification - 10 attempts per minute
    Route::post('/verify-email', [AuthController::class, 'verifyEmail'])
        ->middleware('throttle:10,1');

    // Resend verification - 3 attempts per minute (prevent spam)
    Route::post('/resend-verification', [AuthController::class, 'resendVerification'])
        ->middleware('throttle:3,1');

    // Login - 5 attempts per minute per IP (prevent brute force)
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1');

    // Password Reset - Rate limited to prevent abuse
    Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword'])
        ->middleware('throttle:3,1');
    Route::post('/verify-reset-code', [PasswordResetController::class, 'verifyResetCode'])
        ->middleware('throttle:10,1');
    Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])
        ->middleware('throttle:5,1');
});

/*
|--------------------------------------------------------------------------
| Matches Routes (Public - feed)
|--------------------------------------------------------------------------
*/

Route::prefix('matches')->group(function () {
    Route::get('/feed', [MatchController::class, 'feed']);
});

/*
|--------------------------------------------------------------------------
| Tournament Routes (Public - viewing)
|--------------------------------------------------------------------------
*/

Route::prefix('tournaments')->group(function () {
    Route::get('/', [TournamentController::class, 'index']);

    // Authenticated routes that need to come BEFORE {tournament} wildcard
    Route::middleware('auth:api')->group(function () {
        Route::get('/my-tournaments', [TournamentController::class, 'myTournaments']);
        Route::get('/my-registered', [TournamentController::class, 'myRegistered']);
        Route::get('/eligible', [TournamentController::class, 'eligible']);
    });

    // Wildcard routes must come AFTER specific routes
    Route::get('/{tournament}', [TournamentController::class, 'show']);
    Route::get('/{tournament}/participants', [TournamentController::class, 'participants']);
    Route::get('/{tournament}/standings', [TournamentController::class, 'standings']);
    Route::get('/{tournament}/matches', [TournamentController::class, 'matches']);
    Route::get('/{tournament}/bracket', [TournamentController::class, 'bracket']);
});

/*
|--------------------------------------------------------------------------
| Payment Routes (Public - webhooks & callbacks)
|--------------------------------------------------------------------------
*/

Route::prefix('payments')->group(function () {
    Route::get('/callback', [PaymentController::class, 'callback']);
    Route::post('/webhook', [PaymentController::class, 'webhook']);
    // M-Pesa STK Push callback
    Route::post('/mpesa/callback', [MpesaCallbackController::class, 'stkCallback']);
});

// M-Pesa B2C callbacks (payouts)
Route::prefix('payouts/mpesa')->group(function () {
    Route::post('/result', [MpesaCallbackController::class, 'b2cResult']);
    Route::post('/timeout', [MpesaCallbackController::class, 'b2cTimeout']);
});

/*
|--------------------------------------------------------------------------
| Subscription Plans (Public - viewing)
|--------------------------------------------------------------------------
*/

Route::get('/subscriptions/plans', [SubscriptionController::class, 'plans']);

/*
|--------------------------------------------------------------------------
| Protected Routes (Authenticated)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:api')->group(function () {
    // Auth
    Route::prefix('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh-token', [AuthController::class, 'refreshToken']);
    });

    // Profile
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show']);
        Route::get('/settings', [ProfileController::class, 'settings']);
        Route::put('/', [ProfileController::class, 'update']);
        Route::post('/photo', [ProfileController::class, 'uploadPhoto']);
        Route::delete('/photo', [ProfileController::class, 'deletePhoto']);
        Route::post('/change-password', [ProfileController::class, 'changePassword']);
        Route::get('/rating-history', [ProfileController::class, 'ratingHistory']);
        Route::get('/match-history', [ProfileController::class, 'matchHistory']);
        Route::get('/tournament-history', [ProfileController::class, 'tournamentHistory']);
        Route::post('/deactivate', [ProfileController::class, 'deactivate']);
    });

    // Organizer
    Route::prefix('organizer')->group(function () {
        Route::post('/register', [OrganizerController::class, 'becomeOrganizer']);
        Route::get('/', [OrganizerController::class, 'show']);
        Route::put('/', [OrganizerController::class, 'update']);
        Route::post('/logo', [OrganizerController::class, 'uploadLogo']);
        Route::delete('/logo', [OrganizerController::class, 'deleteLogo']);
        Route::post('/api-credentials', [OrganizerController::class, 'generateApiCredentials']);
        Route::delete('/api-credentials', [OrganizerController::class, 'revokeApiCredentials']);

        // Financial & Analytics
        Route::get('/stats', [OrganizerController::class, 'stats']);
        Route::get('/finances', [OrganizerController::class, 'finances']);
        Route::get('/earnings', [OrganizerController::class, 'earnings']);
        Route::get('/payouts', [OrganizerController::class, 'payouts']);
        Route::post('/payouts/request', [OrganizerController::class, 'requestPayout']);

        // Wallet
        Route::get('/wallet', [OrganizerWalletController::class, 'index']);
        Route::get('/wallet/transactions', [OrganizerWalletController::class, 'transactions']);

        // Payout Methods
        Route::get('/payout-methods', [OrganizerWalletController::class, 'payoutMethods']);
        Route::post('/payout-methods', [OrganizerWalletController::class, 'addPayoutMethod']);
        Route::put('/payout-methods/{method}/default', [OrganizerWalletController::class, 'setDefaultMethod']);
        Route::delete('/payout-methods/{method}', [OrganizerWalletController::class, 'deletePayoutMethod']);

        // Payout Requests
        Route::get('/payout-requests', [OrganizerWalletController::class, 'payoutRequests']);
        Route::post('/payout-requests', [OrganizerWalletController::class, 'requestPayout']);
        Route::delete('/payout-requests/{payout}', [OrganizerWalletController::class, 'cancelPayout']);
    });

    // Tournaments (Authenticated actions)
    Route::prefix('tournaments')->group(function () {
        // Player actions
        Route::post('/{tournament}/register', [TournamentController::class, 'register']);
        Route::delete('/{tournament}/register', [TournamentController::class, 'withdraw']);

        // Tournament payment
        Route::post('/{tournament}/pay', [PaymentController::class, 'initializeTournamentEntryPayment']);
        Route::get('/{tournament}/payment-status', [PaymentController::class, 'tournamentPaymentStatus']);

        // Organizer actions
        Route::post('/', [TournamentController::class, 'store']);
        Route::put('/{tournament}', [TournamentController::class, 'update']);
        Route::delete('/{tournament}', [TournamentController::class, 'destroy']);

        // Tournament management
        Route::post('/{tournament}/open-registration', [TournamentController::class, 'openRegistration']);
        Route::post('/{tournament}/close-registration', [TournamentController::class, 'closeRegistration']);
        Route::get('/{tournament}/can-start', [TournamentController::class, 'canStart']);
        Route::post('/{tournament}/start', [TournamentController::class, 'start']);
        Route::post('/{tournament}/cancel', [TournamentController::class, 'cancel']);

        // Participant management
        Route::delete('/{tournament}/participants/{participant}', [TournamentController::class, 'removeParticipant']);

        // Tournament analytics (organizer)
        Route::get('/{tournament}/analytics', [TournamentController::class, 'analytics']);
        Route::get('/{tournament}/revenue', [TournamentController::class, 'revenue']);
    });

    // Matches (Player actions)
    Route::prefix('matches')->group(function () {
        // My matches dashboard
        Route::get('/my-matches', [MatchController::class, 'myMatches']);

        // Match actions
        Route::get('/{match}', [MatchController::class, 'show']);
        Route::post('/{match}/submit', [MatchController::class, 'submit']);
        Route::post('/{match}/confirm', [MatchController::class, 'confirm']);
        Route::post('/{match}/dispute', [MatchController::class, 'dispute']);

        // Admin: Dispute resolution
        Route::get('/disputed', [MatchController::class, 'disputed']);
        Route::post('/{match}/resolve', [MatchController::class, 'resolve']);

        // Organizer: Dispute resolution
        Route::post('/{match}/resolve-dispute', [MatchController::class, 'resolveDispute']);
        Route::post('/{match}/award-walkover', [MatchController::class, 'awardWalkover']);
    });

    // Match actions for players (alternative routes)
    Route::prefix('matches/{match}')->group(function () {
        Route::post('/submit-result', [MatchController::class, 'submitResult']);
        Route::post('/confirm', [MatchController::class, 'confirmResult']);
        Route::post('/dispute', [MatchController::class, 'disputeResult']);
        Route::post('/report-no-show', [MatchController::class, 'reportNoShow']);

        // Evidence routes
        Route::get('/evidence', [MatchController::class, 'getEvidence']);
        Route::post('/evidence', [MatchController::class, 'uploadEvidence']);
        Route::delete('/evidence/{evidence}', [MatchController::class, 'deleteEvidence']);
    });

    // Payments
    Route::prefix('payments')->group(function () {
        Route::get('/', [PaymentController::class, 'index']);
        Route::get('/verify/{reference}', [PaymentController::class, 'verify']);
        Route::get('/tournament-verify/{reference}', [PaymentController::class, 'verifyTournamentPayment']);
        Route::get('/{payment}', [PaymentController::class, 'show']);
        Route::post('/tournament/{tournament}', [PaymentController::class, 'initiateTournamentPayment']);
    });

    // Subscriptions
    Route::prefix('subscriptions')->group(function () {
        Route::get('/current', [SubscriptionController::class, 'current']);
        Route::get('/history', [SubscriptionController::class, 'history']);
        Route::get('/can-host', [SubscriptionController::class, 'canHost']);
        Route::post('/subscribe/{planCode}', [SubscriptionController::class, 'subscribe']);
        Route::post('/cancel', [SubscriptionController::class, 'cancel']);
        Route::post('/change/{planCode}', [SubscriptionController::class, 'changePlan']);
    });

    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread', [NotificationController::class, 'unread']);
        Route::get('/count', [NotificationController::class, 'count']);
        Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::post('/{notification}/read', [NotificationController::class, 'markAsRead']);
    });

    /*
    |--------------------------------------------------------------------------
    | Admin/Support Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('admin')->middleware('role:admin,support')->group(function () {
        // Tournament Management
        Route::prefix('tournaments')->group(function () {
            Route::get('/', [AdminTournamentController::class, 'index']);
            Route::get('/stats', [AdminTournamentController::class, 'stats']);
            Route::get('/level-settings', [AdminTournamentController::class, 'levelSettings']);
            Route::get('/geographic-units', [AdminTournamentController::class, 'geographicUnits']);
            Route::get('/{tournament}', [AdminTournamentController::class, 'show']);

            // Admin only actions
            Route::post('/', [AdminTournamentController::class, 'store']);
            Route::put('/level-settings', [AdminTournamentController::class, 'updateLevelSettings']);
            Route::post('/{tournament}/approve', [AdminTournamentController::class, 'approve']);
            Route::post('/{tournament}/reject', [AdminTournamentController::class, 'reject']);

            // Admin & Support actions
            Route::post('/{tournament}/cancel', [AdminTournamentController::class, 'cancel']);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Support Routes (Payout Review)
    |--------------------------------------------------------------------------
    */

    Route::prefix('support')->middleware('role:support,admin')->group(function () {
        // Payout Review
        Route::get('/payouts', [SupportPayoutController::class, 'index']);
        Route::get('/payouts/pending', [SupportPayoutController::class, 'pending']);
        Route::get('/payouts/{payout}', [SupportPayoutController::class, 'show']);
        Route::post('/payouts/{payout}/confirm', [SupportPayoutController::class, 'confirm']);
        Route::post('/payouts/{payout}/reject', [SupportPayoutController::class, 'reject']);
    });

    /*
    |--------------------------------------------------------------------------
    | Admin Routes (Payout Approval)
    |--------------------------------------------------------------------------
    */

    Route::prefix('admin/payouts')->middleware('role:admin')->group(function () {
        Route::get('/stats', [AdminPayoutController::class, 'stats']);
        Route::get('/', [AdminPayoutController::class, 'index']);
        Route::get('/pending', [AdminPayoutController::class, 'pending']);
        Route::get('/{payout}', [AdminPayoutController::class, 'show']);
        Route::post('/{payout}/approve', [AdminPayoutController::class, 'approve']);
        Route::post('/{payout}/reject', [AdminPayoutController::class, 'reject']);
        Route::post('/{payout}/process', [AdminPayoutController::class, 'process']);
    });
});
