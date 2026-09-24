<?php

namespace App\Models;

use Database\Factories\AuthProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * @property int $id
 * @property int $system_id
 * @property string $name
 * @property string $type
 * @property array<string, mixed>|null $credentials
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['system_id', 'name', 'type', 'credentials'])]
class AuthProfile extends Model
{
    /** @use HasFactory<AuthProfileFactory> */
    use HasFactory;

    /**
     * The authentication strategies an auth profile can use.
     *
     * @var array<int, string>
     */
    public const TYPES = ['none', 'api_key', 'basic', 'bearer', 'oauth2'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Credentials are encrypted at rest and only ever decrypted in memory.
            'credentials' => 'encrypted:array',
        ];
    }

    /**
     * The system these credentials authenticate against.
     *
     * @return BelongsTo<System, $this>
     */
    public function system(): BelongsTo
    {
        return $this->belongsTo(System::class);
    }

    /**
     * The connections using this auth profile.
     *
     * @return HasMany<Connection, $this>
     */
    public function connections(): HasMany
    {
        return $this->hasMany(Connection::class);
    }

    /**
     * Build an HTTP client pre-authenticated according to this profile's type.
     */
    public function toHttpClient(): PendingRequest
    {
        $credentials = $this->credentials ?? [];

        return match ($this->type) {
            'api_key' => Http::withHeaders([
                ($credentials['header_name'] ?? 'X-API-Key') => $credentials['api_key'] ?? '',
            ]),
            'basic' => Http::withBasicAuth(
                $credentials['username'] ?? '',
                $credentials['password'] ?? '',
            ),
            'bearer' => Http::withToken($credentials['token'] ?? ''),
            'oauth2' => Http::withToken($this->oauthAccessToken()),
            default => Http::asJson(),
        };
    }

    /**
     * Get a valid OAuth2 access token, fetching (and caching) a new one via
     * the client-credentials grant when none is cached or the cached one has
     * expired. Credentials in the database never hold an access token —
     * only the client ID/secret/token URL needed to mint one.
     */
    protected function oauthAccessToken(): string
    {
        $cacheKey = "auth-profile:{$this->id}:oauth-token";

        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        $credentials = $this->credentials ?? [];

        $response = Http::asForm()->post($credentials['token_url'] ?? '', [
            'grant_type' => 'client_credentials',
            'client_id' => $credentials['client_id'] ?? '',
            'client_secret' => $credentials['client_secret'] ?? '',
        ]);

        $response->throw();

        $token = (string) $response->json('access_token');
        $expiresIn = (int) ($response->json('expires_in') ?? 3600);

        // Cache for slightly less than the token's real lifetime so we never
        // hand out one that's about to expire mid-request.
        Cache::put($cacheKey, $token, max($expiresIn - 30, 30));

        return $token;
    }
}
