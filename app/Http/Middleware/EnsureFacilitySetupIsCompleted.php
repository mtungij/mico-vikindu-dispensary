<?php

namespace App\Http\Middleware;

use App\Services\FacilitySetupService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFacilitySetupIsCompleted
{
    public function __construct(private readonly FacilitySetupService $setup) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->setup->isSetupCompleted()) {
            return $next($request);
        }

        if ($request->routeIs('facility.setup', 'logout', 'profile.*')) {
            return $next($request);
        }

        if ($request->user()?->is_super_admin) {
            return redirect()->route('facility.setup');
        }

        return response()->view('facility.setup-pending', status: 503);
    }
}
