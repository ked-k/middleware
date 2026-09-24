<?php

namespace App\Models;

use Database\Factories\SystemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $base_url
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'description', 'base_url', 'is_active'])]
class System extends Model
{
    /** @use HasFactory<SystemFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * The API endpoints registered under this system.
     *
     * @return HasMany<Endpoint, $this>
     */
    public function endpoints(): HasMany
    {
        return $this->hasMany(Endpoint::class);
    }

    /**
     * The credential sets registered for authenticating against this system.
     *
     * @return HasMany<AuthProfile, $this>
     */
    public function authProfiles(): HasMany
    {
        return $this->hasMany(AuthProfile::class);
    }

    /**
     * The authenticated connections established to this system.
     *
     * @return HasMany<Connection, $this>
     */
    public function connections(): HasMany
    {
        return $this->hasMany(Connection::class);
    }

    /**
     * Set a unique, URL-friendly slug whenever the name changes.
     */
    protected static function booted(): void
    {
        static::saving(function (System $system) {
            if (blank($system->slug)) {
                $system->slug = Str::slug($system->name);
            }
        });
    }
}
