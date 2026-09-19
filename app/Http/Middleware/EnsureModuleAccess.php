<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleAccess
{
    public function handle(Request $request, Closure $next, string $module, ?string $action = null): Response
    {
        $user = $request->user();
        abort_unless($user, 403);

        $action = $action ?: $this->resolveAction($request);

        if ($this->allowsSelfUserView($request, $user, $module, $action)) {
            return $next($request);
        }

        abort_unless(
            $user->hasPermission("{$module}.{$action}"),
            403,
            'You do not have permission to access this page.'
        );

        return $next($request);
    }

    private function resolveAction(Request $request): string
    {
        $name = (string) $request->route()?->getName();

        if (str_ends_with($name, '.create') || str_ends_with($name, '.store')) {
            return 'create';
        }

        if (str_ends_with($name, '.edit') || str_ends_with($name, '.update')) {
            return 'edit';
        }

        if (str_ends_with($name, '.destroy')) {
            return 'delete';
        }

        return match ($request->method()) {
            'POST' => 'create',
            'PUT', 'PATCH' => 'edit',
            'DELETE' => 'delete',
            default => 'view',
        };
    }

    private function allowsSelfUserView(Request $request, User $user, string $module, string $action): bool
    {
        if ($module !== 'users' || $action !== 'view') {
            return false;
        }

        $target = $request->route('user');
        $id = $target instanceof User ? $target->id : $target;

        return $id !== null && (int) $id === (int) $user->id;
    }
}
