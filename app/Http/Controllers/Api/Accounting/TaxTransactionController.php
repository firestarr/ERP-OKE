<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\TaxTransaction;
use App\Models\Accounting\ExchangeRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TaxTransactionController extends Controller
{
    /**
     * Display a listing of tax transactions.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = TaxTransaction::query();
        
        // Filter by tax type
        if ($request->has('tax_type')) {
            $query->where('tax_type', $request->tax_type);
        }
        
        // Filter by tax code
        if ($request->has('tax_code')) {
            $query->where('tax_code', $request->tax_code);
        }
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Filter by currency
        if ($request->has('currency')) {
            $query->where('currency', $request->currency);
        }
        
        // Filter by date range
        if ($request->has('from_date') && $request->has('to_date')) {
            $query->whereBetween('transaction_date', [$request->from_date, $request->to_date]);
        }
        
        // Filter by amount range (in base currency)
        if ($request->has('min_amount')) {
            $query->where('base_currency_amount', '>=', $request->min_amount);
        }
        if ($request->has('max_amount')) {
            $query->where('base_currency_amount', '<=', $request->max_amount);
        }
        
        $transactions = $query->orderBy('transaction_date', 'desc')
            ->paginate($request->input('per_page', 15));
        
        // Add currency conversion if requested
        if ($request->filled('display_currency')) {
            $displayCurrency = $request->display_currency;
            $conversionDate = $request->input('conversion_date', now()->toDateString());
            
            $transactions->getCollection()->transform(function ($transaction) use ($displayCurrency, $conversionDate) {
                if ($transaction->currency !== $displayCurrency) {
                    $rate = $this->getExchangeRate($transaction->currency, $displayCurrency, $conversionDate);
                    $transaction->converted_amount = $transaction->tax_amount * $rate;
                    $transaction->display_currency = $displayCurrency;
                    $transaction->exchange_rate = $rate;
                }
                return $transaction;
            });
        }
        
        return response()->json($transactions, 200);
    }

    /**
     * Store a newly created tax transaction in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tax_type' => 'required|string|max:50',
            'reference_type' => 'required|string|max:50',
            'reference_id' => 'required|integer',
            'transaction_date' => 'required|date',
            'tax_amount' => 'required|numeric',
            'currency' => 'required|string|size:3',
            'tax_code' => 'required|string|max:50',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'taxable_amount' => 'nullable|numeric|min:0',
            'status' => 'required|string|max:50',
            'description' => 'nullable|string',
            'supplier_name' => 'nullable|string|max:255',
            'supplier_tax_id' => 'nullable|string|max:50',
            'invoice_number' => 'nullable|string|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $baseCurrency = config('app.base_currency', 'USD');
        $currency = $request->currency;
        
        // Calculate base currency equivalent
        $exchangeRate = $this->getExchangeRate($currency, $baseCurrency, $request->transaction_date);
        $baseCurrencyAmount = $request->tax_amount * $exchangeRate;
        $baseCurrencyTaxableAmount = $request->taxable_amount ? $request->taxable_amount * $exchangeRate : null;

        $transaction = TaxTransaction::create([
            'tax_type' => $request->tax_type,
            'reference_type' => $request->reference_type,
            'reference_id' => $request->reference_id,
            'transaction_date' => $request->transaction_date,
            'tax_amount' => $request->tax_amount,
            'currency' => $currency,
            'base_currency_amount' => $baseCurrencyAmount,
            'exchange_rate' => $exchangeRate,
            'tax_code' => $request->tax_code,
            'tax_rate' => $request->tax_rate,
            'taxable_amount' => $request->taxable_amount,
            'base_currency_taxable_amount' => $baseCurrencyTaxableAmount,
            'status' => $request->status,
            'description' => $request->description,
            'supplier_name' => $request->supplier_name,
            'supplier_tax_id' => $request->supplier_tax_id,
            'invoice_number' => $request->invoice_number
        ]);

        return response()->json([
            'data' => $transaction, 
            'message' => 'Tax transaction created successfully'
        ], 201);
    }

    /**
     * Display the specified tax transaction.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $transaction = TaxTransaction::findOrFail($id);
        
        // Add currency summary
        $currencySummary = $this->getTaxTransactionCurrencySummary($transaction);
        
        return response()->json([
            'data' => $transaction,
            'currency_summary' => $currencySummary
        ], 200);
    }

    /**
     * Update the specified tax transaction in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $transaction = TaxTransaction::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'tax_type' => 'string|max:50',
            'reference_type' => 'string|max:50',
            'reference_id' => 'integer',
            'transaction_date' => 'date',
            'tax_amount' => 'numeric',
            'currency' => 'string|size:3',
            'tax_code' => 'string|max:50',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'taxable_amount' => 'nullable|numeric|min:0',
            'status' => 'string|max:50',
            'description' => 'nullable|string',
            'supplier_name' => 'nullable|string|max:255',
            'supplier_tax_id' => 'nullable|string|max:50',
            'invoice_number' => 'nullable|string|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $baseCurrency = config('app.base_currency', 'USD');
        
        // Recalculate base currency amounts if currency or amounts changed
        if ($request->has('tax_amount') || $request->has('currency') || $request->has('transaction_date')) {
            $amount = $request->tax_amount ?? $transaction->tax_amount;
            $currency = $request->currency ?? $transaction->currency;
            $date = $request->transaction_date ?? $transaction->transaction_date;
            $taxableAmount = $request->taxable_amount ?? $transaction->taxable_amount;
            
            $exchangeRate = $this->getExchangeRate($currency, $baseCurrency, $date);
            $baseCurrencyAmount = $amount * $exchangeRate;
            $baseCurrencyTaxableAmount = $taxableAmount ? $taxableAmount * $exchangeRate : null;
            
            $request->merge([
                'base_currency_amount' => $baseCurrencyAmount,
                'base_currency_taxable_amount' => $baseCurrencyTaxableAmount,
                'exchange_rate' => $exchangeRate
            ]);
        }

        $transaction->update($request->only([
            'tax_type', 'reference_type', 'reference_id', 'transaction_date',
            'tax_amount', 'currency', 'base_currency_amount', 'exchange_rate',
            'tax_code', 'tax_rate', 'taxable_amount', 'base_currency_taxable_amount',
            'status', 'description', 'supplier_name', 'supplier_tax_id', 'invoice_number'
        ]));

        return response()->json([
            'data' => $transaction, 
            'message' => 'Tax transaction updated successfully'
        ], 200);
    }

    /**
     * Remove the specified tax transaction from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $transaction = TaxTransaction::findOrFail($id);
        
        // Check if transaction can be deleted based on status
        if (in_array($transaction->status, ['Posted', 'Filed', 'Paid'])) {
            return response()->json([
                'message' => 'Cannot delete tax transaction with status: ' . $transaction->status
            ], 422);
        }
        
        $transaction->delete();

        return response()->json(['message' => 'Tax transaction deleted successfully'], 200);
    }

    /**
     * Generate tax summary report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function summary(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'currency' => 'nullable|string|size:3',
            'tax_type' => 'nullable|string',
            'group_by' => 'nullable|in:tax_type,tax_code,currency,month'
        ]);
        
        $fromDate = $request->from_date;
        $toDate = $request->to_date;
        $reportCurrency = $request->input('currency', config('app.base_currency', 'USD'));
        $groupBy = $request->input('group_by', 'tax_type');
        
        $query = TaxTransaction::whereBetween('transaction_date', [$fromDate, $toDate]);
        
        // Filter by tax type if specified
        if ($request->filled('tax_type')) {
            $query->where('tax_type', $request->tax_type);
        }
        
        $transactions = $query->get();
        
        // Group and summarize based on groupBy parameter
        $summaryData = match($groupBy) {
            'tax_type' => $this->groupByTaxType($transactions, $reportCurrency),
            'tax_code' => $this->groupByTaxCode($transactions, $reportCurrency),
            'currency' => $this->groupByCurrency($transactions, $reportCurrency),
            'month' => $this->groupByMonth($transactions, $reportCurrency),
            default => $this->groupByTaxType($transactions, $reportCurrency)
        };
        
        // Calculate overall totals
        $baseCurrency = config('app.base_currency', 'USD');
        $overallTotal = $transactions->sum('base_currency_amount');
        $exchangeRate = $this->getExchangeRate($baseCurrency, $reportCurrency, $toDate);
        $convertedTotal = $overallTotal * $exchangeRate;
        
        return response()->json([
            'data' => $summaryData,
            'summary' => [
                'period' => ['from' => $fromDate, 'to' => $toDate],
                'currency' => $reportCurrency,
                'group_by' => $groupBy,
                'total_transactions' => $transactions->count(),
                'total_amount' => $convertedTotal,
                'base_currency_total' => $overallTotal,
                'exchange_rate' => $exchangeRate,
                'unique_tax_types' => $transactions->pluck('tax_type')->unique()->count(),
                'unique_currencies' => $transactions->pluck('currency')->unique()->count()
            ]
        ], 200);
    }

    /**
     * Generate tax payable/receivable report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function payableReceivableReport(Request $request)
    {
        $request->validate([
            'as_of_date' => 'nullable|date',
            'currency' => 'nullable|string|size:3',
            'tax_type' => 'nullable|string'
        ]);
        
        $asOfDate = $request->input('as_of_date', now()->toDateString());
        $reportCurrency = $request->input('currency', config('app.base_currency', 'USD'));
        
        $query = TaxTransaction::where('transaction_date', '<=', $asOfDate)
            ->whereIn('status', ['Pending', 'Due', 'Overdue']);
        
        // Filter by tax type if specified
        if ($request->filled('tax_type')) {
            $query->where('tax_type', $request->tax_type);
        }
        
        $transactions = $query->get();
        
        // Separate payables and receivables
        $payables = $transactions->where('tax_type', 'VAT_Payable')
            ->concat($transactions->where('tax_type', 'Income_Tax_Payable'))
            ->concat($transactions->where('tax_type', 'Sales_Tax_Payable'));
            
        $receivables = $transactions->where('tax_type', 'VAT_Receivable')
            ->concat($transactions->where('tax_type', 'Withholding_Tax_Receivable'));
        
        // Convert to report currency and calculate totals
        $payableTotal = $this->calculateTotalInCurrency($payables, $reportCurrency, $asOfDate);
        $receivableTotal = $this->calculateTotalInCurrency($receivables, $reportCurrency, $asOfDate);
        $netPosition = $payableTotal - $receivableTotal;
        
        // Group by tax code for detailed breakdown
        $payableBreakdown = $this->createTaxBreakdown($payables, $reportCurrency, $asOfDate);
        $receivableBreakdown = $this->createTaxBreakdown($receivables, $reportCurrency, $asOfDate);
        
        return response()->json([
            'data' => [
                'payables' => [
                    'total' => $payableTotal,
                    'count' => $payables->count(),
                    'breakdown' => $payableBreakdown
                ],
                'receivables' => [
                    'total' => $receivableTotal,
                    'count' => $receivables->count(),
                    'breakdown' => $receivableBreakdown
                ],
                'net_position' => [
                    'amount' => $netPosition,
                    'type' => $netPosition > 0 ? 'net_payable' : 'net_receivable',
                    'absolute_amount' => abs($netPosition)
                ]
            ],
            'summary' => [
                'as_of_date' => $asOfDate,
                'currency' => $reportCurrency,
                'total_transactions' => $transactions->count(),
                'payable_amount' => $payableTotal,
                'receivable_amount' => $receivableTotal,
                'net_amount' => $netPosition
            ]
        ], 200);
    }

    /**
     * Generate VAT return data.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function vatReturn(Request $request)
    {
        $request->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'currency' => 'nullable|string|size:3'
        ]);
        
        $periodStart = $request->period_start;
        $periodEnd = $request->period_end;
        $reportCurrency = $request->input('currency', config('app.base_currency', 'USD'));
        
        // Get VAT transactions for the period
        $vatTransactions = TaxTransaction::whereBetween('transaction_date', [$periodStart, $periodEnd])
            ->whereIn('tax_type', ['VAT_Payable', 'VAT_Receivable', 'Input_VAT', 'Output_VAT'])
            ->get();
        
        // Categorize VAT transactions
        $outputVAT = $vatTransactions->whereIn('tax_type', ['VAT_Payable', 'Output_VAT']);
        $inputVAT = $vatTransactions->whereIn('tax_type', ['VAT_Receivable', 'Input_VAT']);
        
        // Calculate totals in report currency
        $outputVATTotal = $this->calculateTotalInCurrency($outputVAT, $reportCurrency, $periodEnd);
        $inputVATTotal = $this->calculateTotalInCurrency($inputVAT, $reportCurrency, $periodEnd);
        $netVAT = $outputVATTotal - $inputVATTotal;
        
        // Create detailed breakdown
        $outputBreakdown = $this->createVATBreakdown($outputVAT, $reportCurrency, $periodEnd);
        $inputBreakdown = $this->createVATBreakdown($inputVAT, $reportCurrency, $periodEnd);
        
        return response()->json([
            'data' => [
                'period' => ['start' => $periodStart, 'end' => $periodEnd],
                'currency' => $reportCurrency,
                'output_vat' => [
                    'total' => $outputVATTotal,
                    'transactions' => $outputVAT->count(),
                    'breakdown' => $outputBreakdown
                ],
                'input_vat' => [
                    'total' => $inputVATTotal,
                    'transactions' => $inputVAT->count(),
                    'breakdown' => $inputBreakdown
                ],
                'net_vat' => [
                    'amount' => $netVAT,
                    'type' => $netVAT > 0 ? 'payable' : 'refundable',
                    'absolute_amount' => abs($netVAT)
                ]
            ],
            'summary' => [
                'total_output_vat' => $outputVATTotal,
                'total_input_vat' => $inputVATTotal,
                'net_vat_position' => $netVAT,
                'requires_payment' => $netVAT > 0,
                'refund_due' => $netVAT < 0
            ]
        ], 200);
    }

    /**
     * Convert tax transaction amounts to different currency.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function convertToCurrency(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'target_currency' => 'required|string|size:3',
            'conversion_date' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $transaction = TaxTransaction::findOrFail($id);
        $targetCurrency = $request->target_currency;
        $conversionDate = $request->conversion_date ?? $transaction->transaction_date;

        $exchangeRate = $this->getExchangeRate($transaction->currency, $targetCurrency, $conversionDate);
        $convertedTaxAmount = $transaction->tax_amount * $exchangeRate;
        $convertedTaxableAmount = $transaction->taxable_amount ? $transaction->taxable_amount * $exchangeRate : null;

        return response()->json([
            'data' => [
                'transaction' => $transaction,
                'original_currency' => $transaction->currency,
                'target_currency' => $targetCurrency,
                'exchange_rate' => $exchangeRate,
                'conversion_date' => $conversionDate,
                'converted_amounts' => [
                    'tax_amount' => $convertedTaxAmount,
                    'taxable_amount' => $convertedTaxableAmount
                ]
            ]
        ], 200);
    }

    /**
     * Get available currencies from tax transactions.
     *
     * @return \Illuminate\Http\Response
     */
    public function getAvailableCurrencies()
    {
        $currencies = TaxTransaction::distinct()
            ->pluck('currency')
            ->filter()
            ->sort()
            ->values();
            
        return response()->json(['data' => $currencies], 200);
    }

    /**
     * Group transactions by tax type.
     *
     * @param \Illuminate\Support\Collection $transactions
     * @param string $reportCurrency
     * @return \Illuminate\Support\Collection
     */
    private function groupByTaxType($transactions, $reportCurrency)
    {
        return $transactions->groupBy('tax_type')->map(function($group, $taxType) use ($reportCurrency) {
            $total = $this->calculateTotalInCurrency($group, $reportCurrency);
            
            return [
                'tax_type' => $taxType,
                'transaction_count' => $group->count(),
                'total_amount' => $total,
                'currency' => $reportCurrency,
                'transactions' => $group->map(function($transaction) use ($reportCurrency) {
                    $rate = $this->getExchangeRate($transaction->currency, $reportCurrency, $transaction->transaction_date);
                    return [
                        'tax_id' => $transaction->tax_id,
                        'transaction_date' => $transaction->transaction_date,
                        'tax_code' => $transaction->tax_code,
                        'original_amount' => $transaction->tax_amount,
                        'converted_amount' => $transaction->tax_amount * $rate,
                        'status' => $transaction->status
                    ];
                })
            ];
        });
    }

    /**
     * Group transactions by tax code.
     *
     * @param \Illuminate\Support\Collection $transactions
     * @param string $reportCurrency
     * @return \Illuminate\Support\Collection
     */
    private function groupByTaxCode($transactions, $reportCurrency)
    {
        return $transactions->groupBy('tax_code')->map(function($group, $taxCode) use ($reportCurrency) {
            $total = $this->calculateTotalInCurrency($group, $reportCurrency);
            
            return [
                'tax_code' => $taxCode,
                'transaction_count' => $group->count(),
                'total_amount' => $total,
                'currency' => $reportCurrency,
                'tax_types' => $group->pluck('tax_type')->unique()->values()
            ];
        });
    }

    /**
     * Group transactions by currency.
     *
     * @param \Illuminate\Support\Collection $transactions
     * @param string $reportCurrency
     * @return \Illuminate\Support\Collection
     */
    private function groupByCurrency($transactions, $reportCurrency)
    {
        return $transactions->groupBy('currency')->map(function($group, $currency) use ($reportCurrency) {
            $originalTotal = $group->sum('tax_amount');
            $rate = $this->getExchangeRate($currency, $reportCurrency, now()->toDateString());
            $convertedTotal = $originalTotal * $rate;
            
            return [
                'currency' => $currency,
                'transaction_count' => $group->count(),
                'original_total' => $originalTotal,
                'converted_total' => $convertedTotal,
                'exchange_rate' => $rate,
                'report_currency' => $reportCurrency
            ];
        });
    }

    /**
     * Group transactions by month.
     *
     * @param \Illuminate\Support\Collection $transactions
     * @param string $reportCurrency
     * @return \Illuminate\Support\Collection
     */
    private function groupByMonth($transactions, $reportCurrency)
    {
        return $transactions->groupBy(function($transaction) {
            return $transaction->transaction_date->format('Y-m');
        })->map(function($group, $month) use ($reportCurrency) {
            $total = $this->calculateTotalInCurrency($group, $reportCurrency);
            
            return [
                'month' => $month,
                'transaction_count' => $group->count(),
                'total_amount' => $total,
                'currency' => $reportCurrency,
                'tax_types' => $group->pluck('tax_type')->unique()->values()
            ];
        });
    }

    /**
     * Calculate total amount in specific currency.
     *
     * @param \Illuminate\Support\Collection $transactions
     * @param string $currency
     * @param string|null $date
     * @return float
     */
    private function calculateTotalInCurrency($transactions, $currency, $date = null)
    {
        $date = $date ?? now()->toDateString();
        $total = 0;
        
        foreach ($transactions as $transaction) {
            $rate = $this->getExchangeRate($transaction->currency, $currency, $date);
            $total += $transaction->tax_amount * $rate;
        }
        
        return $total;
    }

    /**
     * Create tax breakdown by tax code.
     *
     * @param \Illuminate\Support\Collection $transactions
     * @param string $reportCurrency
     * @param string $date
     * @return \Illuminate\Support\Collection
     */
    private function createTaxBreakdown($transactions, $reportCurrency, $date)
    {
        return $transactions->groupBy('tax_code')->map(function($group, $taxCode) use ($reportCurrency, $date) {
            $total = $this->calculateTotalInCurrency($group, $reportCurrency, $date);
            
            return [
                'tax_code' => $taxCode,
                'count' => $group->count(),
                'total_amount' => $total,
                'average_rate' => $group->avg('tax_rate'),
                'statuses' => $group->pluck('status')->unique()->values()
            ];
        });
    }

    /**
     * Create VAT breakdown.
     *
     * @param \Illuminate\Support\Collection $transactions
     * @param string $reportCurrency
     * @param string $date
     * @return \Illuminate\Support\Collection
     */
    private function createVATBreakdown($transactions, $reportCurrency, $date)
    {
        return $transactions->groupBy('tax_rate')->map(function($group, $rate) use ($reportCurrency, $date) {
            $taxTotal = $this->calculateTotalInCurrency($group, $reportCurrency, $date);
            $taxableTotal = 0;
            
            foreach ($group as $transaction) {
                if ($transaction->taxable_amount) {
                    $exchangeRate = $this->getExchangeRate($transaction->currency, $reportCurrency, $date);
                    $taxableTotal += $transaction->taxable_amount * $exchangeRate;
                }
            }
            
            return [
                'tax_rate' => $rate . '%',
                'transaction_count' => $group->count(),
                'taxable_amount' => $taxableTotal,
                'tax_amount' => $taxTotal,
                'effective_rate' => $taxableTotal > 0 ? ($taxTotal / $taxableTotal) * 100 : 0
            ];
        });
    }

    /**
     * Get currency summary for a tax transaction.
     *
     * @param TaxTransaction $transaction
     * @return array
     */
    private function getTaxTransactionCurrencySummary($transaction)
    {
        $baseCurrency = config('app.base_currency', 'USD');
        
        return [
            'transaction_currency' => $transaction->currency,
            'base_currency' => $baseCurrency,
            'exchange_rate' => $transaction->exchange_rate,
            'amounts' => [
                'tax_amount' => [
                    'currency' => $transaction->currency,
                    'amount' => $transaction->tax_amount
                ],
                'base_currency_tax' => [
                    'currency' => $baseCurrency,
                    'amount' => $transaction->base_currency_amount
                ]
            ],
            'taxable_amounts' => $transaction->taxable_amount ? [
                'original' => [
                    'currency' => $transaction->currency,
                    'amount' => $transaction->taxable_amount
                ],
                'base_currency' => [
                    'currency' => $baseCurrency,
                    'amount' => $transaction->base_currency_taxable_amount
                ]
            ] : null,
            'effective_rate' => $transaction->taxable_amount > 0 ? 
                ($transaction->tax_amount / $transaction->taxable_amount) * 100 : null
        ];
    }

    /**
     * Get exchange rate for currency conversion.
     *
     * @param string $fromCurrency
     * @param string $toCurrency
     * @param string $date
     * @return float
     */
    private function getExchangeRate($fromCurrency, $toCurrency, $date)
    {
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }

        $rate = ExchangeRate::where('from_currency', $fromCurrency)
            ->where('to_currency', $toCurrency)
            ->where('rate_date', '<=', $date)
            ->orderBy('rate_date', 'desc')
            ->value('rate');

        return $rate ?? 1.0;
    }
}