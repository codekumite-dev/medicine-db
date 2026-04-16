<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MedicineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'short_composition' => $this->short_composition,
            'dosage_form' => $this->dosage_form?->value,
            'strength' => $this->strength,
            'route' => $this->route_of_administration,
            'type' => $this->type,
            'schedule' => $this->schedule,
            'rx_required' => $this->rx_required,
            'rx_required_header' => $this->rx_required_header,
            'manufacturer' => [
                'id' => $this->manufacturer?->id,
                'name' => $this->manufacturer?->name,
            ],
            'pricing' => [
                'price' => $this->price,
                'mrp' => $this->mrp,
                'currency' => $this->currency,
            ],
            'packaging' => [
                'pack_size_label' => $this->pack_size_label,
                'quantity' => $this->quantity,
                'quantity_unit' => $this->quantity_unit,
            ],
            'identifiers' => [
                'barcode' => $this->barcode,
                'gs1_gtin' => $this->gs1_gtin,
                'hsn_code' => $this->hsn_code,
            ],
            'is_discontinued' => $this->is_discontinued,
            'storage' => $this->storage_conditions,
            'published_at' => $this->published_at?->toISOString(),
        ];
    }
}
