<?php

use App\Http\Controllers\Auth\DashboardAuthController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\GeographyController;
use App\Http\Controllers\Admin\PayoutPageController as AdminPayoutPageController;
use App\Http\Controllers\Admin\TournamentManagementController as AdminTournamentController;
use App\Http\Controllers\Support\SupportDashboardController;
use App\Http\Controllers\Support\ArticleController as SupportArticleController;
use App\Http\Controllers\Support\DisputeController;
use App\Http\Controllers\Support\PayoutPageController;
use App\Http\Controllers\Support\PlayerManagementController;
use App\Http\Controllers\Support\OrganizerManagementController;
use App\Http\Controllers\Support\TournamentManagementController as SupportTournamentController;
use App\Http\Controllers\Support\CommunityController;
use App\Http\Controllers\Support\ActivityLogController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return view('welcome');
});

// Dashboard Authentication Routes
Route::prefix('dashboard')->group(function () {
    Route::get('/login', [DashboardAuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [DashboardAuthController::class, 'login']);
    Route::post('/logout', [DashboardAuthController::class, 'logout'])->name('logout');
});

// Support Dashboard Routes
Route::middleware(['auth', 'role:support'])
    ->prefix('support')
    ->name('support.')
    ->group(function () {
        // Dashboard
        Route::get('/', [SupportDashboardController::class, 'index'])->name('dashboard');

        // Disputes
        Route::prefix('disputes')->name('disputes.')->group(function () {
            Route::get('/', [DisputeController::class, 'index'])->name('index');
            Route::get('/{match}', [DisputeController::class, 'show'])->name('show');
            Route::post('/{match}/resolve', [DisputeController::class, 'resolve'])->name('resolve');
        });

        // Payouts
        Route::prefix('payouts')->name('payouts.')->group(function () {
            Route::get('/', [PayoutPageController::class, 'index'])->name('index');
            Route::get('/{payout}', [PayoutPageController::class, 'show'])->name('show');
            Route::post('/{payout}/confirm', [PayoutPageController::class, 'confirm'])->name('confirm');
            Route::post('/{payout}/reject', [PayoutPageController::class, 'reject'])->name('reject');
        });

        // Players
        Route::prefix('players')->name('players.')->group(function () {
            Route::get('/', [PlayerManagementController::class, 'index'])->name('index');
            Route::get('/{player}', [PlayerManagementController::class, 'show'])->name('show');
            Route::post('/{player}/reactivate', [PlayerManagementController::class, 'reactivate'])->name('reactivate');
            Route::post('/{player}/deactivate', [PlayerManagementController::class, 'deactivate'])->name('deactivate');
            Route::post('/{player}/notes', [PlayerManagementController::class, 'addNote'])->name('addNote');
        });

        // Organizers
        Route::prefix('organizers')->name('organizers.')->group(function () {
            Route::get('/', [OrganizerManagementController::class, 'index'])->name('index');
            Route::get('/{organizer}', [OrganizerManagementController::class, 'show'])->name('show');
            Route::post('/{organizer}/activate', [OrganizerManagementController::class, 'activate'])->name('activate');
            Route::post('/{organizer}/deactivate', [OrganizerManagementController::class, 'deactivate'])->name('deactivate');
        });

        // Tournaments
        Route::prefix('tournaments')->name('tournaments.')->group(function () {
            Route::get('/', [SupportTournamentController::class, 'index'])->name('index');
            Route::get('/pending-review', [SupportTournamentController::class, 'pendingReview'])->name('pending-review');
            Route::get('/{tournament}', [SupportTournamentController::class, 'show'])->name('show');
            Route::post('/{tournament}/start', [SupportTournamentController::class, 'start'])->name('start');
            Route::post('/{tournament}/cancel', [SupportTournamentController::class, 'cancel'])->name('cancel');
            Route::post('/{tournament}/verify', [SupportTournamentController::class, 'verify'])->name('verify');
            Route::post('/{tournament}/reject', [SupportTournamentController::class, 'reject'])->name('reject');
        });

        // Communities
        Route::prefix('communities')->name('communities.')->group(function () {
            Route::get('/', [CommunityController::class, 'index'])->name('index');
        });

        // Activity Log
        Route::get('/activity', [ActivityLogController::class, 'index'])->name('activity.index');

        // Articles (News)
        Route::prefix('articles')->name('articles.')->group(function () {
            Route::get('/', [SupportArticleController::class, 'index'])->name('index');
            Route::get('/create', [SupportArticleController::class, 'create'])->name('create');
            Route::post('/', [SupportArticleController::class, 'store'])->name('store');
            Route::get('/{article}/edit', [SupportArticleController::class, 'edit'])->name('edit');
            Route::put('/{article}', [SupportArticleController::class, 'update'])->name('update');
            Route::delete('/{article}', [SupportArticleController::class, 'destroy'])->name('destroy');
            Route::post('/{article}/publish', [SupportArticleController::class, 'publish'])->name('publish');
            Route::post('/{article}/unpublish', [SupportArticleController::class, 'unpublish'])->name('unpublish');
            Route::post('/{article}/toggle-featured', [SupportArticleController::class, 'toggleFeatured'])->name('toggle-featured');
        });
    });

// Generic dashboard redirect based on role
Route::middleware(['auth'])->get('/dashboard', function () {
    $user = auth()->user();

    if ($user->is_super_admin) {
        return redirect()->route('admin.dashboard');
    }

    if ($user->is_support) {
        return redirect()->route('support.dashboard');
    }

    return redirect('/');
})->name('dashboard');

// Admin Dashboard Routes (protected - super_admin only)
Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', function () {
            return Inertia::render('Admin/Index');
        })->name('dashboard');

        // Players Management
        Route::prefix('users/players')->name('users.players.')->group(function () {
            Route::get('/', [AdminUserController::class, 'players'])->name('index');
            Route::post('/{user}/toggle-status', [AdminUserController::class, 'togglePlayerStatus'])->name('toggle-status');
        });

        // Organizers Management
        Route::prefix('users/organizers')->name('users.organizers.')->group(function () {
            Route::get('/', [AdminUserController::class, 'organizers'])->name('index');
            Route::post('/{user}/toggle-verification', [AdminUserController::class, 'toggleOrganizerVerification'])->name('toggle-verification');
            Route::post('/{user}/toggle-status', [AdminUserController::class, 'toggleOrganizerStatus'])->name('toggle-status');
        });

        // Support Staff Management (full CRUD)
        Route::prefix('users/support')->name('users.support.')->group(function () {
            Route::get('/', [AdminUserController::class, 'support'])->name('index');
            Route::post('/', [AdminUserController::class, 'storeSupport'])->name('store');
            Route::put('/{user}', [AdminUserController::class, 'updateSupport'])->name('update');
            Route::delete('/{user}', [AdminUserController::class, 'destroySupport'])->name('destroy');
            Route::post('/{user}/toggle-status', [AdminUserController::class, 'toggleSupportStatus'])->name('toggle-status');
        });

        // Geography Management
        Route::prefix('geography')->name('geography.')->group(function () {
            Route::get('/', [GeographyController::class, 'index'])->name('index');
            Route::post('/', [GeographyController::class, 'store'])->name('store');
            Route::post('/add-country', [GeographyController::class, 'addCountry'])->name('add-country');
            Route::put('/{unit}', [GeographyController::class, 'update'])->name('update');
            Route::delete('/{unit}', [GeographyController::class, 'destroy'])->name('destroy');
            Route::get('/search', [GeographyController::class, 'search'])->name('search');
            Route::get('/{unit}/children', [GeographyController::class, 'getChildren'])->name('children');
            Route::get('/{unit}/ancestors', [GeographyController::class, 'getAncestors'])->name('ancestors');
        });

        // Tournaments Management
        Route::prefix('tournaments')->name('tournaments.')->group(function () {
            Route::get('/', [AdminTournamentController::class, 'index'])->name('index');
            Route::get('/create', [AdminTournamentController::class, 'create'])->name('create');
            Route::post('/', [AdminTournamentController::class, 'store'])->name('store');
            Route::get('/level-settings', [AdminTournamentController::class, 'levelSettings'])->name('level-settings');
            Route::put('/level-settings', [AdminTournamentController::class, 'updateLevelSettings'])->name('level-settings.update');
            Route::get('/{tournament}', [AdminTournamentController::class, 'show'])->name('show');
            Route::post('/{tournament}/approve', [AdminTournamentController::class, 'approve'])->name('approve');
            Route::post('/{tournament}/reject', [AdminTournamentController::class, 'reject'])->name('reject');
            Route::post('/{tournament}/cancel', [AdminTournamentController::class, 'cancel'])->name('cancel');
        });

        // Payouts Management
        Route::prefix('payouts')->name('payouts.')->group(function () {
            Route::get('/', [AdminPayoutPageController::class, 'index'])->name('index');
            Route::get('/{payout}', [AdminPayoutPageController::class, 'show'])->name('show');
            Route::post('/{payout}/approve', [AdminPayoutPageController::class, 'approve'])->name('approve');
            Route::post('/{payout}/reject', [AdminPayoutPageController::class, 'reject'])->name('reject');
            Route::post('/{payout}/process', [AdminPayoutPageController::class, 'process'])->name('process');
        });
    });
