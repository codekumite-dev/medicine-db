<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Manufacturer;
use App\Http\Resources\Api\ManufacturerResource;

class ManufacturerController extends Controller
{
    public function index(Request $request)
    {
        $this->checkAbility($request, 'manufacturers:read');

        $manufacturers = Manufacturer::where('is_active', true)
            ->orderBy($request->sort_by ?? 'name', $request->sort_dir ?? 'asc')
            ->paginate($request->per_page ?? 25);

        return $this->wrapResource(ManufacturerResource::collection($manufacturers));
    }

    public function show(Request $request, string $uuid)
    {
        $this->checkAbility($request, 'manufacturers:read');
        $manufacturer = Manufacturer::where('is_active', true)->findOrFail($uuid);
        return $this->wrapResource(new ManufacturerResource($manufacturer));
    }
}
