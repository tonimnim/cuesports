<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\OrganizerProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class AdminUserController extends Controller
{
    // Cache TTL in seconds (5 minutes for lists, 1 hour for stats)
    private const CACHE_TTL_LIST = 300;
    private const CACHE_TTL_STATS = 3600;

    // ==========================================
    // PLAYERS
    // ==========================================

    public function players()
    {
        $players = Cache::remember('admin:players:list', self::CACHE_TTL_LIST, function () {
            return User::where('is_player', true)
                ->with('playerProfile')
                ->orderBy('created_at', 'desc')
                ->get();
        });

        $stats = Cache::remember('admin:players:stats', self::CACHE_TTL_STATS, function () use ($players) {
            // Re-fetch if needed (when stats cache expires but list doesn't)
            $playerData = Cache::get('admin:players:list') ?? User::where('is_player', true)->get();
            return [
                'total' => $playerData->count(),
                'active' => $playerData->where('is_active', true)->count(),
                'inactive' => $playerData->where('is_active', false)->count(),
                'new_this_month' => $playerData->where('created_at', '>=', now()->startOfMonth())->count(),
            ];
        });

        return Inertia::render('Admin/Users/Players', [
            'players' => $players,
            'stats' => $stats,
        ]);
    }

    public function togglePlayerStatus(Request $request, User $user)
    {
        $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $user->update([
            'is_active' => $request->is_active,
        ]);

        // Clear player caches
        $this->clearPlayerCache();

        return back()->with('success', 'Player status updated successfully.');
    }

    // ==========================================
    // ORGANIZERS
    // ==========================================

    public function organizers()
    {
        $organizers = Cache::remember('admin:organizers:list', self::CACHE_TTL_LIST, function () {
            return User::where('is_organizer', true)
                ->with('organizerProfile')
                ->orderBy('created_at', 'desc')
                ->get();
        });

        $stats = Cache::remember('admin:organizers:stats', self::CACHE_TTL_STATS, function () {
            $organizerData = Cache::get('admin:organizers:list') ?? User::where('is_organizer', true)->with('organizerProfile')->get();
            $totalTournaments = OrganizerProfile::sum('tournaments_hosted');

            return [
                'total' => $organizerData->count(),
                'verified' => $organizerData->filter(fn($o) => $o->organizerProfile?->is_active)->count(),
                'pending' => $organizerData->filter(fn($o) => !$o->organizerProfile?->is_active)->count(),
                'total_tournaments' => $totalTournaments,
            ];
        });

        return Inertia::render('Admin/Users/Organizers', [
            'organizers' => $organizers,
            'stats' => $stats,
        ]);
    }

    public function toggleOrganizerVerification(Request $request, User $user)
    {
        $request->validate([
            'is_active' => 'required|boolean',
        ]);

        if ($user->organizerProfile) {
            $user->organizerProfile->update([
                'is_active' => $request->is_active,
            ]);
        }

        // Clear organizer caches
        $this->clearOrganizerCache();

        return back()->with('success', 'Organizer verification status updated.');
    }

    public function toggleOrganizerStatus(Request $request, User $user)
    {
        $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $user->update([
            'is_active' => $request->is_active,
        ]);

        // Clear organizer caches
        $this->clearOrganizerCache();

        return back()->with('success', 'Organizer account status updated.');
    }

    // ==========================================
    // SUPPORT STAFF
    // ==========================================

    public function support()
    {
        $supportUsers = Cache::remember('admin:support:list', self::CACHE_TTL_LIST, function () {
            return User::where(function ($query) {
                $query->where('is_support', true)
                      ->orWhere('is_super_admin', true);
            })
            ->orderBy('created_at', 'desc')
            ->get();
        });

        $stats = Cache::remember('admin:support:stats', self::CACHE_TTL_STATS, function () {
            $supportData = Cache::get('admin:support:list') ?? User::where(function ($query) {
                $query->where('is_support', true)->orWhere('is_super_admin', true);
            })->get();

            return [
                'total' => $supportData->count(),
                'admins' => $supportData->where('is_super_admin', true)->count(),
                'support' => $supportData->where('is_support', true)->where('is_super_admin', false)->count(),
                'active' => $supportData->where('is_active', true)->count(),
            ];
        });

        return Inertia::render('Admin/Users/Support', [
            'supportUsers' => $supportUsers,
            'stats' => $stats,
        ]);
    }

    public function storeSupport(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone_number' => 'required|string|unique:users,phone_number',
            'password' => ['required', 'confirmed', Password::min(8)],
            'is_super_admin' => 'boolean',
        ]);

        User::create([
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'password' => Hash::make($request->password),
            'name' => $request->name,
            'is_support' => true,
            'is_super_admin' => $request->is_super_admin ?? false,
            'is_player' => false,
            'is_organizer' => false,
            'is_active' => true,
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);

        // Clear support caches
        $this->clearSupportCache();

        return back()->with('success', 'Support staff member created successfully.');
    }

    public function updateSupport(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone_number' => 'required|string|unique:users,phone_number,' . $user->id,
            'is_super_admin' => 'boolean',
        ]);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'is_super_admin' => $request->is_super_admin ?? false,
        ]);

        // Clear support caches
        $this->clearSupportCache();

        return back()->with('success', 'Support staff member updated successfully.');
    }

    public function destroySupport(User $user)
    {
        // Prevent deleting yourself
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        // Clear support caches
        $this->clearSupportCache();

        return back()->with('success', 'Support staff member deleted successfully.');
    }

    public function toggleSupportStatus(Request $request, User $user)
    {
        $request->validate([
            'is_active' => 'required|boolean',
        ]);

        // Prevent deactivating yourself
        if ($user->id === auth()->id() && !$request->is_active) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        $user->update([
            'is_active' => $request->is_active,
        ]);

        // Clear support caches
        $this->clearSupportCache();

        return back()->with('success', 'Staff member status updated.');
    }

    // ==========================================
    // CACHE HELPERS
    // ==========================================

    private function clearPlayerCache(): void
    {
        Cache::forget('admin:players:list');
        Cache::forget('admin:players:stats');
        Cache::forget('support:dashboard:stats');
    }

    private function clearOrganizerCache(): void
    {
        Cache::forget('admin:organizers:list');
        Cache::forget('admin:organizers:stats');
    }

    private function clearSupportCache(): void
    {
        Cache::forget('admin:support:list');
        Cache::forget('admin:support:stats');
    }

    public static function clearAllUserCaches(): void
    {
        Cache::forget('admin:players:list');
        Cache::forget('admin:players:stats');
        Cache::forget('admin:organizers:list');
        Cache::forget('admin:organizers:stats');
        Cache::forget('admin:support:list');
        Cache::forget('admin:support:stats');
        Cache::forget('support:dashboard:stats');
    }
}
