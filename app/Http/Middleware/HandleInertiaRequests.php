<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'email' => $request->user()->email,
                    'phone_number' => $request->user()->phone_number,
                    'roles' => [
                        'is_super_admin' => $request->user()->is_super_admin,
                        'is_support' => $request->user()->is_support,
                        'is_player' => $request->user()->is_player,
                        'is_organizer' => $request->user()->is_organizer,
                    ],
                    'player_profile' => $request->user()->playerProfile ? [
                        'id' => $request->user()->playerProfile->id,
                        'first_name' => $request->user()->playerProfile->first_name,
                        'last_name' => $request->user()->playerProfile->last_name,
                        'nickname' => $request->user()->playerProfile->nickname,
                        'photo_url' => $request->user()->playerProfile->photo_url,
                    ] : null,
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
