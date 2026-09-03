<?php

namespace App\Models\Farm;

use App\Models\Farm;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'farm_id', 'customer_id', 'sale_date', 'due_date',
        'total_amount', 'note', 'user_id', 'staff_id',
    ];

    protected function casts(): array
    {
        return [
            'sale_date' => 'date',
            'due_date' => 'date',
            'total_amount' => 'decimal:2',
        ];
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
