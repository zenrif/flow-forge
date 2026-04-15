<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetTenantContext
{
    public function handle(Request $request, Closure $next): mixed
    {
        // Semua query otomatis akan difilter berdasarkan tenant user yang login
        // Gunakan ini bersama Global Scope di setiap Model
        $user = $request->user();
        if ($user) {
            app()->instance('current_tenant_id', $user->tenant_id);
        }
        return $next($request);
    }
}
