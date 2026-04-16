<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

class SystemController extends Controller
{
    public function health()
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
        ]);
    }

    public function version()
    {
        return response()->json([
            'version' => '1.0.0',
        ]);
    }
}
