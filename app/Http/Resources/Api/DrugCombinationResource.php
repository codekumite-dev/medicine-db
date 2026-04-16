<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DrugCombinationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'canonical_name' => $this->canonical_name,
            'short_name' => $this->short_name,
            'summary' => $this->summary,
            'alternate_names' => $this->alternate_names,
            'evidence_level' => $this->evidence_level,
            'is_featured' => $this->is_featured,
            'seo' => [
                'title' => $this->seo_title,
                'description' => $this->seo_description,
                'canonical' => $this->canonical_url,
            ],
            'published_at' => $this->published_at?->toISOString(),
            'sections' => $this->whenLoaded('sections', function () {
                return $this->sections->map(function ($section) {
                    return [
                        'key' => $section->section_key,
                        'title' => $section->section_title,
                        'content' => $section->content,
                    ];
                });
            }),
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(function ($item) {
                    return [
                        'ingredient_name' => $item->ingredient_name,
                        'strength' => $item->strength,
                        'role' => $item->role,
                        'medicine' => $item->medicine ? new MedicineResource($item->medicine) : null,
                    ];
                });
            }),
            'faqs' => $this->whenLoaded('faqs', function () {
                return $this->faqs->map(function ($faq) {
                    return [
                        'question' => $faq->question,
                        'answer' => $faq->answer,
                    ];
                });
            }),
        ];
    }
}
