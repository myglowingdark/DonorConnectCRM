<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'logo_path',
        'brand_color',
        'timezone',
        'currency',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['is_active'])
            ->withTimestamps();
    }

    public function donors(): HasMany
    {
        return $this->hasMany(Donor::class);
    }

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function apiConnection(): HasOne
    {
        return $this->hasOne(OrganizationApiConnection::class);
    }

    public function apiConnections(): HasMany
    {
        return $this->hasMany(OrganizationApiConnection::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DonorAssignment::class);
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(DonorInteraction::class);
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $letters = collect($parts)->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)));

        return $letters->implode('') ?: 'ORG';
    }
}
