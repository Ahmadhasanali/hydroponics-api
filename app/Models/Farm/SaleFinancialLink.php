<?php

namespace App\Models\Farm;

use App\Models\Farm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SaleFinancialLink extends Model
{
    public $timestamps = true;

    protected $fillable = ['farm_id', 'financial_transaction_id', 'linkable_type', 'linkable_id'];

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function financialTransaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class);
    }

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }
}
