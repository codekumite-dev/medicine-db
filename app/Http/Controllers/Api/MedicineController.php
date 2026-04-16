<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Medicine;
use App\Http\Resources\Api\MedicineResource;

class MedicineController extends Controller
{
    public function index(Request $request)
    {
        $this->checkAbility($request, 'medicines:read');

        $medicines = Medicine::published()
            ->active()
            ->with(['manufacturer'])
            ->when($request->manufacturer, fn($q) => $q->where('manufacturer_id', $request->manufacturer))
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->when($request->has('rx_required'), fn($q) => $q->where('rx_required', $request->boolean('rx_required')))
            ->when($request->discontinued, fn($q) => $q->where('is_discontinued', true))
            ->orderBy($request->sort_by ?? 'name', $request->sort_dir ?? 'asc')
            ->paginate($request->per_page ?? 25);

        return $this->wrapResource(MedicineResource::collection($medicines));
    }

    public function search(Request $request)
    {
        $this->checkAbility($request, 'medicines:search');

        $q = $request->validate(['q' => 'required|string|min:2|max:100'])['q'];

        $results = Medicine::published()
            ->where(function ($query) use ($q) {
                $query->where('name', 'LIKE', "%{$q}%")
                      ->orWhere('short_composition', 'LIKE', "%{$q}%")
                      ->orWhereHas('aliases', fn($a) => $a->where('alias', 'LIKE', "%{$q}%"));
            })
            ->limit(50)
            ->get();

        return $this->wrapResource(MedicineResource::collection($results));
    }

    public function show(Request $request, string $uuid)
    {
        $this->checkAbility($request, 'medicines:read');
        $medicine = \Illuminate\Support\Facades\Cache::remember(
            "medicine:{$uuid}",
            60 * 60 * 24, // 24 hours
            fn() => Medicine::published()->active()->with(['manufacturer'])->findOrFail($uuid)
        );
        return $this->wrapResource(new MedicineResource($medicine));
    }

    public function showBySlug(Request $request, string $slug)
    {
        $this->checkAbility($request, 'medicines:read');
        $medicine = \Illuminate\Support\Facades\Cache::remember(
            "medicine:slug:{$slug}",
            60 * 60 * 24,
            fn() => Medicine::published()->active()->with(['manufacturer'])->where('slug', $slug)->firstOrFail()
        );
        return $this->wrapResource(new MedicineResource($medicine));
    }

    public function showByBarcode(Request $request, string $barcode)
    {
        $this->checkAbility($request, 'medicines:read');
        $medicine = Medicine::published()->active()->with(['manufacturer'])->where('barcode', $barcode)->firstOrFail();
        return $this->wrapResource(new MedicineResource($medicine));
    }

    public function showByGtin(Request $request, string $gtin)
    {
        $this->checkAbility($request, 'medicines:read');
        $medicine = Medicine::published()->active()->with(['manufacturer'])->where('gs1_gtin', $gtin)->firstOrFail();
        return $this->wrapResource(new MedicineResource($medicine));
    }
}
