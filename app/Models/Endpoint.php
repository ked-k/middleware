<?php

namespace App\Models;

use Database\Factories\EndpointFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $system_id
 * @property string $name
 * @property string $method
 * @property string $path
 * @property string|null $description
 * @property array<string, mixed>|null $request_schema
 * @property array<string, mixed>|null $response_schema
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['system_id', 'name', 'method', 'path', 'description', 'request_schema', 'response_schema', 'is_active'])]
class Endpoint extends Model
{
    /** @use HasFactory<EndpointFactory> */
    use HasFactory;

    /**
     * The HTTP methods available for an endpoint.
     *
     * @var array<int, string>
     */
    public const METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'request_schema' => 'array',
            'response_schema' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The system this endpoint belongs to.
     *
     * @return BelongsTo<System, $this>
     */
    public function system(): BelongsTo
    {
        return $this->belongsTo(System::class);
    }
}
