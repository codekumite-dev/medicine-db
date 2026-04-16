<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckApiClientIpAllowlist
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token && $token->tokenable_type === ApiClient::class) {
            $client = ApiClient::where('id', $token->tokenable_id)->first();
            if ($client && ! empty($client->allowed_ips)) {
                $clientIp = $request->ip();
                if (! in_array($clientIp, $client->allowed_ips)) {
                    abort(403, 'IP not allowed for this API key.');
                }
            }
        }

        return $next($request);
    }
}
