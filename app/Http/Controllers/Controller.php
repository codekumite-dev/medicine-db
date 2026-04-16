<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

abstract class Controller
{
    protected function checkAbility(Request $request, string $ability): void
    {
        if ($request->user() && ! $request->user()->tokenCan($ability)) {
            throw new AccessDeniedHttpException("Missing required ability: {$ability}");
        }
    }

    protected function wrapResponse($data)
    {
        return response()->json([
            'data' => $data,
            'meta' => [
                'request_id' => (string) Str::uuid(),
                'version' => 'v1',
                'timestamp' => now()->toISOString(),
            ],
        ]);
    }

    protected function wrapResource($resource)
    {
        return $resource->additional([
            'meta' => [
                'request_id' => (string) Str::uuid(),
                'version' => 'v1',
                'timestamp' => now()->toISOString(),
            ],
        ])->response();
    }
}
