<?php

namespace App\Models;

use Database\Factories\MessagingAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessagingAccount extends Model
{
    /** @use HasFactory<MessagingAccountFactory> */
    use HasFactory;

    protected $fillable = ['channel', 'external_id', 'user_id', 'default_farm_id', 'linked_at'];

    protected function casts(): array
    {
        return ['linked_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function defaultFarm(): BelongsTo
    {
        return $this->belongsTo(Farm::class, 'default_farm_id');
    }
}
