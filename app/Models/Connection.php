<?php

namespace App\Models;

use Database\Factories\ConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Throwable;

/**
 * @property int $id
 * @property int $system_id
 * @property int|null $auth_profile_id
 * @property string $name
 * @property string $status
 * @property Carbon|null $last_tested_at
 * @property string|null $last_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['system_id', 'auth_profile_id', 'name', 'status', 'last_tested_at', 'last_error'])]
class Connection extends Model
{
    /** @use HasFactory<ConnectionFactory> */
    use HasFactory;

    /**
     * The possible connectivity states for a connection.
     *
     * @var array<int, string>
     */
    public const STATUSES = ['untested', 'connected', 'failed'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_tested_at' => 'datetime',
        ];
    }

    /**
     * The system this connection reaches.
     *
     * @return BelongsTo<System, $this>
     */
    public function system(): BelongsTo
    {
        return $this->belongsTo(System::class);
    }

    /**
     * The credentials used to authenticate this connection.
     *
     * @return BelongsTo<AuthProfile, $this>
     */
    public function authProfile(): BelongsTo
    {
        return $this->belongsTo(AuthProfile::class);
    }

    /**
     * Attempt to reach the system's base URL using this connection's auth
     * profile, recording the outcome.
     */
    public function test(): bool
    {
        if (blank($this->system->base_url)) {
            $this->forceFill([
                'status' => 'failed',
                'last_tested_at' => Date::now(),
                'last_error' => 'The system has no base URL configured.',
            ])->save();

            return false;
        }

        $client = $this->authProfile?->toHttpClient() ?? \Illuminate\Support\Facades\Http::asJson();

        try {
            $response = $client->timeout(10)->get($this->system->base_url);

            $this->forceFill([
                'status' => $response->successful() ? 'connected' : 'failed',
                'last_tested_at' => Date::now(),
                'last_error' => $response->successful() ? null : "Received HTTP {$response->status()}.",
            ])->save();

            return $response->successful();
        } catch (Throwable $e) {
            $this->forceFill([
                'status' => 'failed',
                'last_tested_at' => Date::now(),
                'last_error' => $e->getMessage(),
            ])->save();

            return false;
        }
    }
}
