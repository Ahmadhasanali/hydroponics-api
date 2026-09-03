<?php

namespace App\Models\Farm;

use App\Models\Farm;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountTransfer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['farm_id', 'from_account_id', 'to_account_id', 'amount', 'transfer_date', 'note', 'user_id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transfer_date' => 'date',
        ];
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'from_account_id');
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'to_account_id');
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
