<?php

namespace App\Models;

use Database\Factories\TelegramPendingSaleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramPendingSale extends Model
{
    /** @use HasFactory<TelegramPendingSaleFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'messaging_account_id',
        'chat_id',
        'message_id',
        'farm_id',
        'customer_id',
        'customer_name',
        'customer_phone',
        'sale_date',
        'due_date',
        'items',
        'amount_paid',
        'note',
        'status',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'amount_paid' => 'decimal:2',
            'sale_date' => 'date',
            'due_date' => 'date',
            'expires_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(MessagingAccount::class, 'messaging_account_id');
    }
}
