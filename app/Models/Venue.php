<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Str;

#[Fillable([
    'name',
    'slug',
    'custom_domain',
    'description',
    'translations',
    'address',
    'city',
    'country',
    'latitude',
    'longitude',
    'phone',
    'whatsapp',
    'email',
    'timezone',
    'currency',
    'default_locale',
    'open_time',
    'close_time',
    'multiday_discount_percent',
    'campaign_discount_percent',
    'logo_path',
    'cover_path',
    'public_status',
])]
class Venue extends Model
{
    use HasFactory, SoftDeletes;

    /** Bookings of at least this many days get the multi-day rate. */
    public const MULTIDAY_PACKAGE_DAYS = 3;

    /** Bookings of at least this many days get the hiring-campaign rate. */
    public const CAMPAIGN_PACKAGE_DAYS = 10;

    protected function casts(): array
    {
        return [
            'translations' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'multiday_discount_percent' => 'integer',
            'campaign_discount_percent' => 'integer',
        ];
    }

    /**
     * The package discount a booking of this many days earns, in percent.
     */
    public function packageDiscountPercent(int $days): int
    {
        return match (true) {
            $days >= self::CAMPAIGN_PACKAGE_DAYS && $this->campaign_discount_percent > 0 => $this->campaign_discount_percent,
            $days >= self::MULTIDAY_PACKAGE_DAYS && $this->multiday_discount_percent > 0 => $this->multiday_discount_percent,
            default => 0,
        };
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'venue_users')
            ->using(VenueUser::class)
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }

    /**
     * Sends a notification to every active member of the venue team.
     */
    public function notifyStaff(Notification $notification): void
    {
        NotificationFacade::send($this->users()->wherePivot('status', 'active')->get(), $notification);
    }

    public function spaceTypes(): HasMany
    {
        return $this->hasMany(SpaceType::class);
    }

    public function knowledgeItems(): HasMany
    {
        return $this->hasMany(VenueKnowledgeItem::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function translatedDescription(string $locale): ?string
    {
        $value = $this->translations[$locale]['description'] ?? null;

        return filled($value) ? $value : $this->description;
    }

    public function isPublished(): bool
    {
        return $this->public_status === 'published';
    }

    public function publicUrl(): string
    {
        return $this->custom_domain
            ? 'https://'.$this->custom_domain
            : url('/'.$this->slug);
    }

    public static function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'venue';
        $slug = $base;
        $suffix = 1;

        while (static::withTrashed()->where('slug', $slug)->exists() || in_array($slug, static::reservedSlugs(), true)) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    public static function reservedSlugs(): array
    {
        return [
            'admin', 'login', 'logout', 'register', 'pricing', 'dashboard',
            'api', 'terms', 'privacy', 'forgot-password', 'reset-password',
            'verify-email', 'confirm-password', 'storage', 'build', 'account',
        ];
    }
}
