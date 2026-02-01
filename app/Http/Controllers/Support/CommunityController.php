<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CommunityController extends Controller
{
    public function index(Request $request): Response
    {
        // Placeholder for community management
        // Communities feature will be implemented in a future phase
        return Inertia::render('Support/Communities/Index', [
            'communities' => [
                'data' => [],
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 20,
                'total' => 0,
            ],
            'filters' => $request->only(['search', 'status']),
        ]);
    }
}
