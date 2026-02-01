<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\OrganizerProfile;
use App\Models\Tournament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrganizerManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $organizers = OrganizerProfile::query()
            ->with(['user.country'])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('organization_name', 'like', "%{$search}%")
                        ->orWhereHas('user', fn($q) =>
                            $q->where('email', 'like', "%{$search}%")
                                ->orWhere('phone_number', 'like', "%{$search}%")
                        );
                });
            })
            ->when($request->status === 'active', fn($q) => $q->where('is_active', true))
            ->when($request->status === 'inactive', fn($q) => $q->where('is_active', false))
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Support/Organizers/Index', [
            'organizers' => [
                'data' => $organizers->map(fn($org) => $this->formatOrganizer($org)),
                'current_page' => $organizers->currentPage(),
                'last_page' => $organizers->lastPage(),
                'per_page' => $organizers->perPage(),
                'total' => $organizers->total(),
            ],
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function show(Request $request, OrganizerProfile $organizer): Response
    {
        $organizer->load(['user.country']);

        // Log view
        ActivityLog::log(
            ActivityLog::ACTION_ORGANIZER_VIEWED,
            ActivityLog::ENTITY_ORGANIZER,
            $organizer->id,
            "Viewed organizer profile: {$organizer->organization_name}",
            null,
            $request
        );

        // Get tournaments hosted by this organizer
        $tournaments = Tournament::where('created_by', $organizer->user_id)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(fn($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'status' => $t->status instanceof \BackedEnum ? $t->status->value : $t->status,
                'type' => $t->type instanceof \BackedEnum ? $t->type->value : $t->type,
                'participants_count' => $t->participants_count,
                'starts_at' => $t->starts_at?->toISOString(),
                'ends_at' => $t->ends_at?->toISOString(),
                'created_at' => $t->created_at->toISOString(),
            ]);

        // Get activity log
        $activityLog = ActivityLog::forEntity(ActivityLog::ENTITY_ORGANIZER, $organizer->id)
            ->with('user')
            ->recent(20)
            ->get()
            ->map(fn($a) => [
                'id' => $a->id,
                'action' => $a->action,
                'action_label' => $a->getActionLabel(),
                'description' => $a->description,
                'performed_by' => $a->user ? [
                    'id' => $a->user->id,
                    'email' => $a->user->email,
                ] : null,
                'created_at' => $a->created_at->toISOString(),
            ]);

        return Inertia::render('Support/Organizers/Show', [
            'organizer' => $this->formatOrganizer($organizer, true),
            'tournaments' => $tournaments,
            'activityLog' => $activityLog,
        ]);
    }

    public function activate(Request $request, OrganizerProfile $organizer): RedirectResponse
    {
        if ($organizer->is_active) {
            return back()->with('error', 'Organizer is already active.');
        }

        $organizer->update(['is_active' => true]);

        ActivityLog::log(
            ActivityLog::ACTION_ORGANIZER_ACTIVATED,
            ActivityLog::ENTITY_ORGANIZER,
            $organizer->id,
            "Activated organizer: {$organizer->organization_name}",
            null,
            $request
        );

        return back()->with('success', 'Organizer has been activated.');
    }

    public function deactivate(Request $request, OrganizerProfile $organizer): RedirectResponse
    {
        if (!$organizer->is_active) {
            return back()->with('error', 'Organizer is already inactive.');
        }

        $organizer->update(['is_active' => false]);

        // Revoke API credentials
        $organizer->revokeApiCredentials();

        ActivityLog::log(
            ActivityLog::ACTION_ORGANIZER_DEACTIVATED,
            ActivityLog::ENTITY_ORGANIZER,
            $organizer->id,
            "Deactivated organizer: {$organizer->organization_name}",
            null,
            $request
        );

        return back()->with('success', 'Organizer has been deactivated.');
    }

    private function formatOrganizer(OrganizerProfile $organizer, bool $detailed = false): array
    {
        $data = [
            'id' => $organizer->id,
            'organization_name' => $organizer->organization_name,
            'logo_url' => $organizer->logo_url,
            'is_active' => $organizer->is_active,
            'tournaments_hosted' => $organizer->tournaments_hosted,
            'created_at' => $organizer->created_at->toISOString(),
            'user' => [
                'id' => $organizer->user->id,
                'email' => $organizer->user->email,
                'phone_number' => $organizer->user->phone_number,
                'is_active' => $organizer->user->is_active,
                'country' => $organizer->user->country ? [
                    'id' => $organizer->user->country->id,
                    'name' => $organizer->user->country->name,
                ] : null,
            ],
        ];

        if ($detailed) {
            $data['description'] = $organizer->description;
            $data['api_key'] = $organizer->api_key ? substr($organizer->api_key, 0, 12) . '...' : null;
            $data['api_key_last_used_at'] = $organizer->api_key_last_used_at?->toISOString();
            $data['updated_at'] = $organizer->updated_at->toISOString();
        }

        return $data;
    }
}
