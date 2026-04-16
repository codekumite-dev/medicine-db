<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use App\Enums\DosageFormEnum;
use App\Enums\ApprovalStatusEnum;

class Medicine extends Model
{
    use HasUuids, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    protected $casts = [
        'rx_required'     => 'boolean',
        'is_discontinued' => 'boolean',
        'price'           => 'decimal:2',
        'mrp'             => 'decimal:2',
        'quantity'        => 'integer',
        'published_at'    => 'datetime',
        'dosage_form'     => DosageFormEnum::class,
        'approval_status' => ApprovalStatusEnum::class,
    ];

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(MedicineAlias::class);
    }

    public function identifiers(): HasMany
    {
        return $this->hasMany(MedicineIdentifier::class);
    }

    protected static function booted(): void
    {
        static::saved(function (Medicine $medicine) {
            \Illuminate\Support\Facades\Cache::forget("medicine:{$medicine->id}");
            \Illuminate\Support\Facades\Cache::forget("medicine:slug:{$medicine->slug}");
        });
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('approval_status', ApprovalStatusEnum::Published)
                     ->whereNotNull('published_at');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_discontinued', false);
    }
}
