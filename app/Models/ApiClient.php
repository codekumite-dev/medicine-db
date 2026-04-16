<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\NewAccessToken;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class ApiClient extends Model
{
    use HasUuids, SoftDeletes, HasApiTokens;

    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    protected $casts = [
        'allowed_ips' => 'array',
        'abilities'   => 'array',
        'is_active'   => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public function tokens(): MorphMany
    {
        return $this->morphMany(PersonalAccessToken::class, 'tokenable');
    }

    public function issueToken(string $tokenName): NewAccessToken
    {
        return $this->tokens()->create([
            'name'      => $tokenName,
            'token'     => hash('sha256', $plaintext = Str::random(40)),
            'abilities' => $this->abilities ?? ['medicines:read'],
        ]);
    }
}
