<?php

namespace App\Models;

use Database\Factories\MessagingLinkCodeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessagingLinkCode extends Model
{
    /** @use HasFactory<MessagingLinkCodeFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['user_id', 'channel', 'code', 'expires_at', 'used_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function newFactory(): MessagingLinkCodeFactory
    {
        return MessagingLinkCodeFactory::new();
    }
}
