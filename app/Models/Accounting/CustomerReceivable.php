<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\CurrencyRate;
use App\Models\Sales\Customer;
use App\Models\Sales\SalesInvoice;

class CustomerReceivable extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'CustomerReceivable';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'receivable_id';

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
        'customer_id',
        'invoice_id',
        'amount',
        'due_date',
        'paid_amount',
        'balance',
        'status',
        'currency_code', // Baru
        'exchange_rate', // Baru
        'base_currency', // Baru
        'base_currency_amount', // Baru
        'base_currency_balance' // Baru
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'amount' => 'float',
        'paid_amount' => 'float',
        'balance' => 'float',
        'due_date' => 'date',
        'exchange_rate' => 'float', // Baru
        'base_currency_amount' => 'float', // Baru
        'base_currency_balance' => 'float' // Baru
    ];

    /**
     * Get the customer that owns the receivable.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Get the sales invoice that owns the receivable.
     */
    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'invoice_id');
    }

    /**
     * Get the receivable payments for the receivable.
     */
    public function receivablePayments(): HasMany
    {
        return $this->hasMany(ReceivablePayment::class, 'receivable_id');
    }
    
    /**
     * Convert amounts to specified currency.
     *
     * @param string $toCurrency
     * @param string|null $date
     * @return array
     */
    public function getAmountsInCurrency($toCurrency, $date = null)
    {
        $date = $date ?? $this->due_date;
        
        // If already in requested currency, return original amounts
        if ($this->currency_code === $toCurrency) {
            return [
                'amount' => $this->amount,
                'paid_amount' => $this->paid_amount,
                'balance' => $this->balance
            ];
        }
        
        // Try to convert via base currency first
        if ($toCurrency === $this->base_currency) {
            return [
                'amount' => $this->base_currency_amount,
                'paid_amount' => $this->base_currency_amount - $this->base_currency_balance,
                'balance' => $this->base_currency_balance
            ];
        }
        
        // Get rate from base currency to requested currency
        $rate = CurrencyRate::getCurrentRate($this->base_currency, $toCurrency, $date);
        
        if (!$rate) {
            // Try direct conversion
            $rate = CurrencyRate::getCurrentRate($this->currency_code, $toCurrency, $date);
            if (!$rate) {
                // If no conversion possible, return original values
                return [
                    'amount' => $this->amount,
                    'paid_amount' => $this->paid_amount,
                    'balance' => $this->balance
                ];
            }
            
            return [
                'amount' => $this->amount * $rate,
                'paid_amount' => $this->paid_amount * $rate,
                'balance' => $this->balance * $rate
            ];
        }
        
        return [
            'amount' => $this->base_currency_amount * $rate,
            'paid_amount' => ($this->base_currency_amount - $this->base_currency_balance) * $rate,
            'balance' => $this->base_currency_balance * $rate
        ];
    }
}