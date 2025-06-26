<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\SystemCurrency;

class JournalEntryLine extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'JournalEntryLine';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'line_id';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'journal_id',
        'account_id',
        'debit_amount',
        'credit_amount',
        'description',
        'currency',
        'foreign_amount',
        'exchange_rate',
        'base_currency'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'debit_amount' => 'decimal:2',
        'credit_amount' => 'decimal:2',
        'foreign_amount' => 'decimal:4',
        'exchange_rate' => 'decimal:6',
    ];

    /**
     * Get the journal entry that owns the journal entry line.
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_id');
    }

    /**
     * Get the chart of account that owns the journal entry line.
     */
    public function chartOfAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    /**
     * Get the currency that belongs to the journal entry line.
     */
    public function transactionCurrency(): BelongsTo
    {
        return $this->belongsTo(SystemCurrency::class, 'currency', 'code');
    }

    /**
     * Get the base currency that belongs to the journal entry line.
     */
    public function baseCurrencyModel(): BelongsTo
    {
        return $this->belongsTo(SystemCurrency::class, 'base_currency', 'code');
    }

    /**
     * Get the net amount (debit - credit) in base currency.
     *
     * @return float
     */
    public function getNetAmountAttribute()
    {
        return $this->debit_amount - $this->credit_amount;
    }

    /**
     * Get the net amount in foreign currency.
     *
     * @return float|null
     */
    public function getForeignNetAmountAttribute()
    {
        if (!$this->currency || !$this->foreign_amount) {
            return null;
        }

        // Calculate foreign net amount based on debit/credit direction
        if ($this->debit_amount > 0) {
            return $this->foreign_amount;
        } elseif ($this->credit_amount > 0) {
            return -$this->foreign_amount;
        }

        return 0;
    }

    /**
     * Check if this line is a foreign currency transaction.
     *
     * @return bool
     */
    public function isForeignCurrency()
    {
        return !is_null($this->currency) && 
               $this->currency !== ($this->base_currency ?: config('app.base_currency', 'USD'));
    }

    /**
     * Get amount in specified currency.
     *
     * @param string $targetCurrency
     * @param string|null $exchangeDate
     * @return float|null
     */
    public function getAmountInCurrency($targetCurrency, $exchangeDate = null)
    {
        $exchangeDate = $exchangeDate ?: $this->journalEntry->entry_date;
        $baseCurrency = $this->base_currency ?: config('app.base_currency', 'USD');
        $baseAmount = $this->net_amount;

        // If target currency is base currency, return base amount
        if ($targetCurrency === $baseCurrency) {
            return $baseAmount;
        }

        // If this line has the target currency and foreign amount
        if ($this->currency === $targetCurrency && $this->foreign_amount !== null) {
            return $this->foreign_net_amount;
        }

        // Otherwise, convert from base currency to target currency
        $exchangeRate = $this->getExchangeRate($baseCurrency, $targetCurrency, $exchangeDate);
        
        if (!$exchangeRate) {
            return null;
        }

        return $baseAmount / $exchangeRate;
    }

    /**
     * Calculate exchange gain/loss for foreign currency transactions.
     *
     * @param float $currentExchangeRate
     * @return float
     */
    public function calculateExchangeGainLoss($currentExchangeRate)
    {
        if (!$this->isForeignCurrency()) {
            return 0;
        }

        $originalBaseAmount = $this->net_amount;
        $currentBaseAmount = $this->foreign_amount * $currentExchangeRate;

        return $currentBaseAmount - $originalBaseAmount;
    }

    /**
     * Get exchange rate between two currencies.
     *
     * @param string $fromCurrency
     * @param string $toCurrency
     * @param string $date
     * @return float|null
     */
    private function getExchangeRate($fromCurrency, $toCurrency, $date)
    {
        if ($fromCurrency === $toCurrency) {
            return 1;
        }

        // Use ExchangeRate model to get the rate
        $exchangeRateModel = app(\App\Models\ExchangeRate::class);
        
        $rate = $exchangeRateModel::where('from_currency', $fromCurrency)
            ->where('to_currency', $toCurrency)
            ->where('rate_date', '<=', $date)
            ->orderBy('rate_date', 'desc')
            ->first();

        if ($rate) {
            return $rate->rate;
        }

        // Try reverse rate
        $reverseRate = $exchangeRateModel::where('from_currency', $toCurrency)
            ->where('to_currency', $fromCurrency)
            ->where('rate_date', '<=', $date)
            ->orderBy('rate_date', 'desc')
            ->first();

        return $reverseRate ? (1 / $reverseRate->rate) : null;
    }

    /**
     * Scope to filter by currency.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $currency
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByCurrency($query, $currency)
    {
        return $query->where('currency', $currency);
    }

    /**
     * Scope to filter foreign currency transactions only.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForeignCurrencyOnly($query)
    {
        $baseCurrency = config('app.base_currency', 'USD');
        return $query->whereNotNull('currency')
                    ->where('currency', '!=', $baseCurrency);
    }

    /**
     * Scope to filter by date range.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $startDate
     * @param string $endDate
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->join('JournalEntry', 'JournalEntryLine.journal_id', '=', 'JournalEntry.journal_id')
                    ->whereBetween('JournalEntry.entry_date', [$startDate, $endDate]);
    }

    /**
     * Scope to filter posted entries only.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePosted($query)
    {
        return $query->join('JournalEntry', 'JournalEntryLine.journal_id', '=', 'JournalEntry.journal_id')
                    ->where('JournalEntry.status', 'Posted');
    }
}