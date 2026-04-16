<?php

namespace App\Models;

use App\Enums\EditorialStatusEnum;
use App\Enums\SectionKeyEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DrugCombination extends Model
{
    use HasUuids, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'alternate_names' => 'array',
        'schema_markup' => 'array',
        'is_featured' => 'boolean',
        'published_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'editorial_status' => EditorialStatusEnum::class,
    ];

    public function sections(): HasMany
    {
        return $this->hasMany(DrugCombinationSection::class)
            ->orderBy('display_order');
    }

    public function faqs(): HasMany
    {
        return $this->hasMany(Faq::class)->orderBy('display_order');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DrugCombinationItem::class)->orderBy('display_order');
    }

    public function getSection(SectionKeyEnum $key): ?DrugCombinationSection
    {
        return $this->sections->firstWhere('section_key', $key->value);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('editorial_status', EditorialStatusEnum::Published)
            ->whereNotNull('published_at');
    }
}
