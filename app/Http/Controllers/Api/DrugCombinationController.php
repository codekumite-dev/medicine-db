<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DrugCombination;
use App\Http\Resources\Api\DrugCombinationResource;

class DrugCombinationController extends Controller
{
    public function index(Request $request)
    {
        $this->checkAbility($request, 'combinations:read');

        $combinations = DrugCombination::published()
            ->orderBy($request->sort_by ?? 'title', $request->sort_dir ?? 'asc')
            ->paginate($request->per_page ?? 25);

        return $this->wrapResource(DrugCombinationResource::collection($combinations));
    }

    public function show(Request $request, string $slug)
    {
        $this->checkAbility($request, 'combinations:read');
        
        $combination = DrugCombination::published()
            ->with(['sections' => fn($q) => $q->where('is_visible', true), 'items.medicine'])
            ->where('slug', $slug)
            ->firstOrFail();

        return $this->wrapResource(new DrugCombinationResource($combination));
    }

    public function faqs(Request $request, string $slug)
    {
        $this->checkAbility($request, 'combinations:read');

        $combination = DrugCombination::published()
            ->with(['faqs' => fn($q) => $q->where('is_published', true)])
            ->where('slug', $slug)
            ->firstOrFail();

        return $this->wrapResponse($combination->faqs->map(function ($faq) {
            return [
                'question' => $faq->question,
                'answer'   => $faq->answer,
            ];
        }));
    }

    public function section(Request $request, string $slug, string $key)
    {
        $this->checkAbility($request, 'combinations:read');

        $combination = DrugCombination::published()
            ->where('slug', $slug)
            ->firstOrFail();

        $section = $combination->sections()->where('section_key', $key)->where('is_visible', true)->firstOrFail();

        return $this->wrapResponse([
            'key'     => $section->section_key,
            'title'   => $section->section_title,
            'content' => $section->content,
        ]);
    }
}
