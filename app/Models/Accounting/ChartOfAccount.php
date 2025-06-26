<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\SystemCurrency;

class ChartOfAccount extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ChartOfAccount';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'account_id';

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
        'account_code',
        'name',
        'account_type',
        'is_active',
        'parent_account_id',
        'default_currency',
        'allow_multi_currency'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_active' => 'boolean',
        'allow_multi_currency' => 'boolean',
    ];

    /**
     * Get the default currency that belongs to the account.
     */
    public function defaultCurrency(): BelongsTo
    {
        return $this->belongsTo(SystemCurrency::class, 'default_currency', 'code');
    }

    /**
     * Get the parent account that owns the account.
     */
    public function parentAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'parent_account_id');
    }

    /**
     * Get the child accounts for the account.
     */
    public function childAccounts(): HasMany
    {
        return $this->hasMany(ChartOfAccount::class, 'parent_account_id');
    }

    /**
     * Get the journal entry lines for the account.
     */
    public function journalEntryLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'account_id');
    }

    /**
     * Get the budgets for the account.
     */
    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class, 'account_id');
    }

    /**
     * Get the bank accounts for the GL account.
     */
    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class, 'gl_account_id');
    }

    /**
     * Get account balance in a specific currency.
     *
     * @param string $currency
     * @param string|null $asOfDate
     * @return float
     */
    public function getBalanceInCurrency($currency = null, $asOfDate = null)
    {
        $currency = $currency ?: $this->default_currency;
        $asOfDate = $asOfDate ?: now()->toDateString();

        // Get base balance first
        $baseBalance = $this->getBalance($asOfDate);

        // If requesting base currency, return base balance
        $baseCurrency = config('app.base_currency', 'USD');
        if ($currency === $baseCurrency) {
            return $baseBalance;
        }

        // Get exchange rate and convert
        $exchangeRate = $this->getExchangeRate($baseCurrency, $currency, $asOfDate);
        
        if (!$exchangeRate) {
            return null; // No exchange rate available
        }

        return $baseBalance / $exchangeRate;
    }

    /**
     * Get account balance in base currency as of specific date.
     *
     * @param string|null $asOfDate
     * @return float
     */
    public function getBalance($asOfDate = null)
    {
        $asOfDate = $asOfDate ?: now()->toDateString();

        $query = $this->journalEntryLines()
            ->join('JournalEntry', 'JournalEntryLine.journal_id', '=', 'JournalEntry.journal_id')
            ->where('JournalEntry.entry_date', '<=', $asOfDate)
            ->where('JournalEntry.status', 'Posted');

        $debits = $query->sum('JournalEntryLine.debit_amount');
        $credits = $query->sum('JournalEntryLine.credit_amount');

        return $debits - $credits;
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

        // This should use the ExchangeRate model when available
        // For now, return null if no rate service is available
        $exchangeRateModel = app(\App\Models\ExchangeRate::class);
        
        $rate = $exchangeRateModel::where('from_currency', $fromCurrency)
            ->where('to_currency', $toCurrency)
            ->where('rate_date', '<=', $date)
            ->orderBy('rate_date', 'desc')
            ->first();

        return $rate ? $rate->rate : null;
    }

    /**
     * Check if account supports multi-currency transactions.
     *
     * @return bool
     */
    public function supportsMultiCurrency()
    {
        return $this->allow_multi_currency;
    }

    /**
     * Get available currencies for this account.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getAvailableCurrencies()
    {
        if (!$this->allow_multi_currency) {
            return collect([$this->defaultCurrency]);
        }

        return SystemCurrency::where('is_active', true)->get();
    }

    /**
     * Get foreign currency balances for this account.
     *
     * @param string|null $asOfDate
     * @return array
     */
    public function getForeignCurrencyBalances($asOfDate = null)
    {
        if (!$this->allow_multi_currency) {
            return [];
        }

        $asOfDate = $asOfDate ?: now()->toDateString();
        $baseCurrency = config('app.base_currency', 'USD');

        $foreignBalances = $this->journalEntryLines()
            ->join('JournalEntry', 'JournalEntryLine.journal_id', '=', 'JournalEntry.journal_id')
            ->where('JournalEntry.entry_date', '<=', $asOfDate)
            ->where('JournalEntry.status', 'Posted')
            ->whereNotNull('JournalEntryLine.currency')
            ->where('JournalEntryLine.currency', '!=', $baseCurrency)
            ->selectRaw('
                JournalEntryLine.currency,
                SUM(CASE WHEN JournalEntryLine.debit_amount > 0 THEN JournalEntryLine.foreign_amount ELSE 0 END) as foreign_debits,
                SUM(CASE WHEN JournalEntryLine.credit_amount > 0 THEN JournalEntryLine.foreign_amount ELSE 0 END) as foreign_credits
            ')
            ->groupBy('JournalEntryLine.currency')
            ->get();

        $balances = [];
        foreach ($foreignBalances as $balance) {
            $balances[$balance->currency] = $balance->foreign_debits - $balance->foreign_credits;
        }

        return $balances;
    }

    /**
     * Scope to filter accounts by currency support.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param bool $multiCurrency
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithMultiCurrencySupport($query, $multiCurrency = true)
    {
        return $query->where('allow_multi_currency', $multiCurrency);
    }

    /**
     * Scope to filter accounts by default currency.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $currency
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithDefaultCurrency($query, $currency)
    {
        return $query->where('default_currency', $currency);
    }

    /**
     * Scope to get accounts by type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $accountType
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByType($query, $accountType)
    {
        return $query->where('account_type', $accountType);
    }
}