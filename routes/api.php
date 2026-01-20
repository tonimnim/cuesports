<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\PasswordResetController;
use App\Http\Controllers\Api\GeographicUnitController;
use App\Http\Controllers\Api\OrganizerController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\TournamentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'CueSports Africa API',
        'version' => '1.0.0',
    ]);
});

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
| Auth Routes (Guest)
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
    // Registration
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
    Route::post('/resend-verification', [AuthController::class, 'resendVerification']);

    // Login
    Route::post('/login', [AuthController::class, 'login']);

    // Password Reset
    Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword']);
    Route::post('/verify-reset-code', [PasswordResetController::class, 'verifyResetCode']);
    Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);
});

/*
|--------------------------------------------------------------------------
| Tournament Routes (Public - viewing)
|--------------------------------------------------------------------------
*/

Route::prefix('tournaments')->group(function () {
    Route::get('/', [TournamentController::class, 'index']);
    Route::get('/{tournament}', [TournamentController::class, 'show']);
    Route::get('/{tournament}/participants', [TournamentController::class, 'participants']);
    Route::get('/{tournament}/standings', [TournamentController::class, 'standings']);
    Route::get('/{tournament}/matches', [TournamentController::class, 'matches']);
    Route::get('/{tournament}/bracket', [TournamentController::class, 'bracket']);
});

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
        Route::put('/', [ProfileController::class, 'update']);
        Route::post('/photo', [ProfileController::class, 'uploadPhoto']);
        Route::delete('/photo', [ProfileController::class, 'deletePhoto']);
        Route::post('/change-password', [ProfileController::class, 'changePassword']);
        Route::get('/rating-history', [ProfileController::class, 'ratingHistory']);
        Route::get('/match-history', [ProfileController::class, 'matchHistory']);
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
    });

    // Tournaments (Authenticated actions)
    Route::prefix('tournaments')->group(function () {
        // Player actions
        Route::get('/eligible', [TournamentController::class, 'eligible']);
        Route::post('/{tournament}/register', [TournamentController::class, 'register']);
        Route::delete('/{tournament}/register', [TournamentController::class, 'withdraw']);

        // Organizer actions
        Route::get('/my-tournaments', [TournamentController::class, 'myTournaments']);
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
    });
});
