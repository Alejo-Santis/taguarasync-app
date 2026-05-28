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
        $tenant = $request->user()?->tenant;

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user()?->only('id', 'name', 'email', 'is_super_admin'),
                'notifications' => fn () => $request->user() ? [
                    'unread_count' => $request->user()->unreadNotifications()->count(),
                    'items' => $request->user()
                        ->unreadNotifications()
                        ->latest()
                        ->limit(8)
                        ->get(['id', 'type', 'data', 'created_at'])
                        ->map(fn ($notification) => [
                            'id' => $notification->id,
                            'type' => $notification->type,
                            'title' => $notification->data['title'] ?? 'Notificación',
                            'body' => $notification->data['body'] ?? '',
                            'severity' => $notification->data['severity'] ?? 'info',
                            'href' => $notification->data['href'] ?? '/dashboard',
                            'created_at' => $notification->created_at?->diffForHumans(),
                        ])
                        ->all(),
                ] : ['unread_count' => 0, 'items' => []],
                'tenant' => $tenant ? [
                    'uuid' => $tenant->uuid,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'status' => $tenant->status->value,
                    'printer_settings' => $tenant->printer_settings ?? [
                        'printer_name' => null,
                        'paper_width' => '80mm',
                        'copies' => 1,
                        'auto_print' => false,
                    ],
                ] : null,
                'permissions' => fn () => $request->user()
                    ? $request->user()->getAllPermissions()->pluck('name')->values()->all()
                    : [],
                'roles' => fn () => $request->user()
                    ? $request->user()->getRoleNames()->values()->all()
                    : [],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
                'status' => fn () => $request->session()->get('status'),
                'message' => fn () => $request->session()->get('message'),
            ],
            'completedSale' => fn () => $request->session()->get('completedSale'),
        ];
    }
}
