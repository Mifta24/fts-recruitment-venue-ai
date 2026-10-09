<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'venue_id',
    'name',
    'slug',
    'description',
    'translations',
    'size_sqm',
    'breakout_rooms',
    'booths',
    'floor_range',
    'max_candidates',
    'max_recruiters',
    'layout_config',
    'ambience',
    'catering_included',
    'tech_crew_available',
    'tech_crew_price',
    'base_price',
    'amenities',
    'is_active',
    'sort_order',
])]
class SpaceType extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'translations' => 'array',
            'breakout_rooms' => 'integer',
            'booths' => 'integer',
            'layout_config' => 'array',
            'amenities' => 'array',
            'catering_included' => 'boolean',
            'tech_crew_available' => 'boolean',
            'tech_crew_price' => 'decimal:2',
            'base_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(SpaceImage::class)->orderBy('sort_order');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(SpaceInventory::class);
    }

    public function isOpenHall(): bool
    {
        return $this->breakout_rooms === 0;
    }

    public function maxCapacity(): int
    {
        return $this->max_candidates + $this->max_recruiters;
    }

    public function translatedName(string $locale): string
    {
        $value = $this->translations[$locale]['name'] ?? null;

        return filled($value) ? $value : $this->name;
    }

    public function translatedDescription(string $locale): ?string
    {
        $value = $this->translations[$locale]['description'] ?? null;

        return filled($value) ? $value : $this->description;
    }
}
