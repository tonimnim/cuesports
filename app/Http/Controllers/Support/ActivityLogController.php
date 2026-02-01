<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $logs = ActivityLog::query()
            ->with('user')
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('description', 'like', "%{$search}%")
                        ->orWhere('action', 'like', "%{$search}%")
                        ->orWhereHas('user', fn($q) => $q->where('email', 'like', "%{$search}%"));
                });
            })
            ->when($request->entity_type, fn($q, $type) => $q->where('entity_type', $type))
            ->when($request->action, fn($q, $action) => $q->where('action', $action))
            ->orderBy('created_at', 'desc')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Support/Activity/Index', [
            'logs' => [
                'data' => $logs->map(fn($log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'action_label' => $log->getActionLabel(),
                    'entity_type' => $log->entity_type,
                    'entity_id' => $log->entity_id,
                    'description' => $log->description,
                    'metadata' => $log->metadata,
                    'ip_address' => $log->ip_address,
                    'performed_by' => $log->user ? [
                        'id' => $log->user->id,
                        'email' => $log->user->email,
                    ] : null,
                    'created_at' => $log->created_at->toISOString(),
                ]),
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
            'filters' => $request->only(['search', 'entity_type', 'action']),
            'entityTypes' => [
                ActivityLog::ENTITY_USER => 'Users',
                ActivityLog::ENTITY_MATCH => 'Matches',
                ActivityLog::ENTITY_TOURNAMENT => 'Tournaments',
                ActivityLog::ENTITY_ORGANIZER => 'Organizers',
            ],
        ]);
    }
}
