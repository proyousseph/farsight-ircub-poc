<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        $required = [];
        foreach ($permissions as $permission) {
            foreach (explode('|', $permission) as $slug) {
                $slug = trim($slug);
                if ($slug !== '') {
                    $required[] = $slug;
                }
            }
        }

        $required = array_values(array_unique($required));

        if (! $user || $required === []) {
            return response()->json([
                'message' => 'You do not have permission to perform this action.',
                'required_permission' => $permissions[0] ?? null,
            ], 403);
        }

        foreach ($required as $slug) {
            if ($user->hasPermission($slug)) {
                return $next($request);
            }
        }

        return response()->json([
            'message' => 'You do not have permission to perform this action.',
            'required_permission' => implode('|', $required),
        ], 403);
    }
}
