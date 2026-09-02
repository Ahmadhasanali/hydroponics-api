<?php

namespace App\Models;

use Database\Factories\TelegramPendingTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramPendingTransaction extends Model
{
    /** @use HasFactory<TelegramPendingTransactionFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['messaging_account_id', 'chat_id', 'message_id', 'farm_id', 'type', 'category_id', 'amount', 'transaction_date', 'note', 'status', 'expires_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'transaction_date' => 'date', 'expires_at' => 'datetime'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(MessagingAccount::class, 'messaging_account_id');
    }
}
