<?php

namespace App\Models\Farm;

use App\Models\Farm;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountBalanceAdjustment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['farm_id', 'account_id', 'amount', 'adjustment_date', 'reason', 'user_id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'adjustment_date' => 'date',
        ];
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
