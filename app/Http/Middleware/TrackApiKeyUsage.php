<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackApiKeyUsage
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $token = $request->user()?->currentAccessToken();
        if ($token && $token->tokenable_type === ApiClient::class) {
            ApiClient::where('id', $token->tokenable_id)
                ->update(['last_used_at' => now()]);
        }

        return $response;
    }
}
