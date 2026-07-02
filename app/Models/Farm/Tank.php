<?php

namespace App\Models\Farm;

use App\Models\Farm;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tank extends Model
{
    /** @use HasFactory<TankFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'farm_id',
        'created_by',
        'name',
        'capacity_liter',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'capacity_liter' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Farm,Tank>
     */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    /**
     * @return BelongsTo<User,Tank>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
