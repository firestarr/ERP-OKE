<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\CustomerReceivable;
use App\Models\Accounting\ExchangeRate;
use App\Models\Sales\Customer;
use App\Models\Sales\SalesInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CustomerReceivableController extends Controller
{
    /**
     * Display a listing of customer receivables.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = CustomerReceivable::with(['customer', 'salesInvoice']);
        
        // Filter by customer
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }
        
        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        // Filter by currency
        if ($request->filled('currency')) {
            $query->where('currency_code', $request->currency);
        }
        
        // Filter by due date range
        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->whereBetween('due_date', [$request->from_date, $request->to_date]);
        }
        
        // Filter by amount range (in base currency)
        if ($request->filled('min_amount')) {
            $query->where('base_currency_amount', '>=', $request->min_amount);
        }
        if ($request->filled('max_amount')) {
            $query->where('base_currency_amount', '<=', $request->max_amount);
        }
        
        $receivables = $query->orderBy('due_date')
            ->paginate($request->input('per_page', 15));
        
        // Add currency conversion if requested
        if ($request->filled('display_currency') && $request->display_currency !== config('app.base_currency', 'USD')) {
            $receivables->getCollection()->transform(function ($receivable) use ($request) {
                $convertedAmounts = $receivable->getAmountsInCurrency($request->display_currency);
                $receivable->converted_amounts = $convertedAmounts;
                return $receivable;
            });
        }
        
        return response()->json($receivables, 200);
    }

    /**
     * Store a newly created customer receivable in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:Customer,customer_id',
            'invoice_id' => 'required|exists:SalesInvoice,invoice_id',
            'amount' => 'required|numeric|min:0',
            'currency_code' => 'nullable|string|size:3',
            'due_date' => 'required|date',
            'status' => 'required|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Check if a receivable already exists for this invoice
        $exists = CustomerReceivable::where('invoice_id', $request->invoice_id)->exists();
        if ($exists) {
            return response()->json([
                'message' => 'A receivable already exists for this invoice'
            ], 422);
        }

        $baseCurrency = config('app.base_currency', 'USD');
        $currency = $request->currency_code ?? $baseCurrency;
        
        // Get exchange rate for currency conversion
        $exchangeRate = $this->getExchangeRate($currency, $baseCurrency, $request->due_date);
        $baseCurrencyAmount = $request->amount * $exchangeRate;

        $receivable = CustomerReceivable::create([
            'customer_id' => $request->customer_id,
            'invoice_id' => $request->invoice_id,
            'amount' => $request->amount,
            'currency_code' => $currency,
            'exchange_rate' => $exchangeRate,
            'base_currency' => $baseCurrency,
            'base_currency_amount' => $baseCurrencyAmount,
            'base_currency_balance' => $baseCurrencyAmount,
            'due_date' => $request->due_date,
            'paid_amount' => 0,
            'balance' => $request->amount,
            'status' => $request->status
        ]);

        return response()->json([
            'data' => $receivable->load(['customer', 'salesInvoice']), 
            'message' => 'Customer receivable created successfully'
        ], 201);
    }

    /**
     * Display the specified customer receivable.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $receivable = CustomerReceivable::with(['customer', 'salesInvoice', 'receivablePayments'])
            ->findOrFail($id);
        
        return response()->json(['data' => $receivable], 200);
    }

    /**
     * Update the specified customer receivable in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $receivable = CustomerReceivable::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'customer_id' => 'exists:Customer,customer_id',
            'invoice_id' => 'exists:SalesInvoice,invoice_id',
            'amount' => 'numeric|min:0',
            'currency_code' => 'string|size:3',
            'due_date' => 'date',
            'status' => 'string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $baseCurrency = config('app.base_currency', 'USD');
        
        // If amount or currency changed, recalculate base currency amounts
        if ($request->has('amount') || $request->has('currency_code')) {
            $amount = $request->amount ?? $receivable->amount;
            $currency = $request->currency_code ?? $receivable->currency_code;
            
            $exchangeRate = $this->getExchangeRate($currency, $baseCurrency, $request->due_date ?? $receivable->due_date);
            $baseCurrencyAmount = $amount * $exchangeRate;
            
            // Update base currency fields
            $request->merge([
                'exchange_rate' => $exchangeRate,
                'base_currency_amount' => $baseCurrencyAmount,
                'base_currency_balance' => $baseCurrencyAmount - ($receivable->paid_amount * $exchangeRate),
                'balance' => $amount - $receivable->paid_amount
            ]);
        }

        $receivable->update($request->only([
            'customer_id', 'invoice_id', 'amount', 'currency_code', 
            'exchange_rate', 'base_currency_amount', 'base_currency_balance',
            'due_date', 'balance', 'status'
        ]));

        return response()->json([
            'data' => $receivable->load(['customer', 'salesInvoice']), 
            'message' => 'Customer receivable updated successfully'
        ], 200);
    }

    /**
     * Remove the specified customer receivable from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $receivable = CustomerReceivable::findOrFail($id);
        
        // Check if there are payments
        if ($receivable->receivablePayments()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete receivable with recorded payments'
            ], 422);
        }
        
        $receivable->delete();

        return response()->json(['message' => 'Customer receivable deleted successfully'], 200);
    }

    /**
     * Generate aging report for customer receivables.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function aging(Request $request)
    {
        $asOfDate = $request->input('as_of_date', now()->toDateString());
        $currency = $request->input('currency', config('app.base_currency', 'USD'));
        $groupByCurrency = $request->boolean('group_by_currency', false);
        
        $query = CustomerReceivable::with('customer')
            ->where('status', '!=', 'Paid')
            ->where('due_date', '<=', $asOfDate);
        
        // Filter by customer if specified
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }
        
        // Filter by currency if not grouping by currency
        if (!$groupByCurrency && $request->filled('currency')) {
            $query->where('currency_code', $request->currency);
        }
        
        $receivables = $query->get();
        
        if ($groupByCurrency) {
            return $this->generateMultiCurrencyAging($receivables, $asOfDate);
        } else {
            return $this->generateSingleCurrencyAging($receivables, $asOfDate, $currency);
        }
    }

    /**
     * Display the statement for a specific receivable.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function statement($id)
    {
        $receivable = CustomerReceivable::with(['salesInvoice', 'receivablePayments'])
            ->findOrFail($id);

        // Validate customer_id
        if (empty($receivable->customer_id) || !is_numeric($receivable->customer_id)) {
            return response()->json([
                'error' => 'Invalid or missing customer_id for this receivable.'
            ], 400);
        }

        // Load customer relationship separately with validation
        $customer = $receivable->customer()->first();
        if (!$customer) {
            return response()->json([
                'error' => 'Customer not found for this receivable.'
            ], 404);
        }

        // Prepare statement data with currency information
        $statement = [
            'receivable' => $receivable,
            'customer' => $customer,
            'invoice' => $receivable->salesInvoice,
            'payments' => $receivable->receivablePayments()
                ->orderBy('payment_date')
                ->get()
                ->map(function($payment) {
                    // Add running balance
                    return $payment;
                }),
            'currency_summary' => $this->getReceivableCurrencySummary($receivable)
        ];

        return response()->json(['data' => $statement], 200);
    }

    /**
     * Get customer transactions in specific currency.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function customerTransactions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:Customer,customer_id',
            'currency' => 'nullable|string|size:3',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $query = CustomerReceivable::with(['salesInvoice', 'receivablePayments'])
            ->where('customer_id', $request->customer_id);

        // Filter by currency
        if ($request->filled('currency')) {
            $query->where('currency_code', $request->currency);
        }

        // Filter by date range
        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->whereBetween('due_date', [$request->from_date, $request->to_date]);
        }

        $receivables = $query->orderBy('due_date')->get();

        // Group by currency and calculate totals
        $currencyGroups = $receivables->groupBy('currency_code')->map(function($group, $currency) {
            return [
                'currency' => $currency,
                'total_amount' => $group->sum('amount'),
                'total_paid' => $group->sum('paid_amount'),
                'total_balance' => $group->sum('balance'),
                'count' => $group->count(),
                'receivables' => $group->values()
            ];
        });

        return response()->json([
            'data' => $currencyGroups,
            'summary' => [
                'total_receivables' => $receivables->count(),
                'currencies_involved' => $currencyGroups->keys(),
                'base_currency_total' => $receivables->sum('base_currency_amount'),
                'base_currency_balance' => $receivables->sum('base_currency_balance')
            ]
        ], 200);
    }

    /**
     * Convert receivable amounts to different currency.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
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

        $receivable = CustomerReceivable::with(['customer', 'salesInvoice'])->findOrFail($id);
        $targetCurrency = $request->target_currency;
        $conversionDate = $request->conversion_date ?? now()->toDateString();

        $convertedAmounts = $receivable->getAmountsInCurrency($targetCurrency, $conversionDate);
        $exchangeRate = $this->getExchangeRate($receivable->currency_code, $targetCurrency, $conversionDate);

        return response()->json([
            'data' => [
                'receivable' => $receivable,
                'original_currency' => $receivable->currency_code,
                'target_currency' => $targetCurrency,
                'exchange_rate' => $exchangeRate,
                'conversion_date' => $conversionDate,
                'converted_amounts' => $convertedAmounts
            ]
        ], 200);
    }

    /**
     * Get currency summary for all receivables.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function currencySummary(Request $request)
    {
        $query = CustomerReceivable::query();
        
        // Filter by customer if specified
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }
        
        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        $receivables = $query->get();
        
        $summary = $receivables->groupBy('currency_code')->map(function($group, $currency) {
            $totalAmount = $group->sum('amount');
            $totalPaid = $group->sum('paid_amount');
            $totalBalance = $group->sum('balance');
            $baseCurrencyTotal = $group->sum('base_currency_amount');
            $baseCurrencyBalance = $group->sum('base_currency_balance');
            
            return [
                'currency' => $currency,
                'count' => $group->count(),
                'total_amount' => $totalAmount,
                'total_paid' => $totalPaid,
                'total_balance' => $totalBalance,
                'base_currency_total' => $baseCurrencyTotal,
                'base_currency_balance' => $baseCurrencyBalance,
                'overdue_count' => $group->where('due_date', '<', now())->where('status', '!=', 'Paid')->count(),
                'overdue_amount' => $group->where('due_date', '<', now())->where('status', '!=', 'Paid')->sum('balance')
            ];
        });
        
        return response()->json([
            'data' => $summary,
            'grand_total' => [
                'base_currency' => config('app.base_currency', 'USD'),
                'total_amount' => $receivables->sum('base_currency_amount'),
                'total_balance' => $receivables->sum('base_currency_balance'),
                'currencies_count' => $summary->count()
            ]
        ], 200);
    }

    /**
     * Get available currencies from receivables.
     *
     * @return \Illuminate\Http\Response
     */
    public function getAvailableCurrencies()
    {
        $currencies = CustomerReceivable::distinct()
            ->pluck('currency_code')
            ->filter()
            ->sort()
            ->values();
            
        return response()->json(['data' => $currencies], 200);
    }

    /**
     * Generate single currency aging report.
     *
     * @param \Illuminate\Support\Collection $receivables
     * @param string $asOfDate
     * @param string $currency
     * @return \Illuminate\Http\Response
     */
    private function generateSingleCurrencyAging($receivables, $asOfDate, $currency)
    {
        $aging = [];
        $baseCurrency = config('app.base_currency', 'USD');
        
        foreach ($receivables as $receivable) {
            $daysPastDue = now()->parse($asOfDate)->diffInDays($receivable->due_date, false);
            
            // Convert amounts to requested currency
            $amounts = $receivable->getAmountsInCurrency($currency, $asOfDate);
            
            $aging[] = [
                'receivable_id' => $receivable->receivable_id,
                'customer_name' => $receivable->customer->name,
                'invoice_number' => $receivable->salesInvoice->invoice_number,
                'due_date' => $receivable->due_date,
                'days_past_due' => $daysPastDue,
                'original_currency' => $receivable->currency_code,
                'original_amount' => $receivable->amount,
                'original_balance' => $receivable->balance,
                'display_currency' => $currency,
                'display_amount' => $amounts['amount'],
                'display_balance' => $amounts['balance'],
                'age_bucket' => $this->getAgeBucket($daysPastDue)
            ];
        }
        
        // Group by age buckets
        $agingBuckets = collect($aging)->groupBy('age_bucket')->map(function($group) {
            return [
                'count' => $group->count(),
                'total_amount' => $group->sum('display_amount'),
                'total_balance' => $group->sum('display_balance'),
                'receivables' => $group->values()
            ];
        });
        
        return response()->json([
            'data' => $agingBuckets,
            'summary' => [
                'as_of_date' => $asOfDate,
                'currency' => $currency,
                'total_receivables' => count($aging),
                'total_amount' => collect($aging)->sum('display_amount'),
                'total_balance' => collect($aging)->sum('display_balance')
            ]
        ], 200);
    }

    /**
     * Generate multi-currency aging report.
     *
     * @param \Illuminate\Support\Collection $receivables
     * @param string $asOfDate
     * @return \Illuminate\Http\Response
     */
    private function generateMultiCurrencyAging($receivables, $asOfDate)
    {
        $aging = $receivables->groupBy('currency_code')->map(function($group, $currency) use ($asOfDate) {
            $agingData = [];
            
            foreach ($group as $receivable) {
                $daysPastDue = now()->parse($asOfDate)->diffInDays($receivable->due_date, false);
                
                $agingData[] = [
                    'receivable_id' => $receivable->receivable_id,
                    'customer_name' => $receivable->customer->name,
                    'invoice_number' => $receivable->salesInvoice->invoice_number,
                    'due_date' => $receivable->due_date,
                    'days_past_due' => $daysPastDue,
                    'amount' => $receivable->amount,
                    'balance' => $receivable->balance,
                    'base_currency_amount' => $receivable->base_currency_amount,
                    'base_currency_balance' => $receivable->base_currency_balance,
                    'age_bucket' => $this->getAgeBucket($daysPastDue)
                ];
            }
            
            return [
                'currency' => $currency,
                'total_amount' => $group->sum('amount'),
                'total_balance' => $group->sum('balance'),
                'base_currency_total' => $group->sum('base_currency_amount'),
                'base_currency_balance' => $group->sum('base_currency_balance'),
                'receivables' => $agingData
            ];
        });
        
        return response()->json([
            'data' => $aging,
            'summary' => [
                'as_of_date' => $asOfDate,
                'base_currency' => config('app.base_currency', 'USD'),
                'total_receivables' => $receivables->count(),
                'base_currency_total' => $receivables->sum('base_currency_amount'),
                'base_currency_balance' => $receivables->sum('base_currency_balance'),
                'currencies_involved' => $aging->keys()
            ]
        ], 200);
    }

    /**
     * Get age bucket for days past due.
     *
     * @param int $daysPastDue
     * @return string
     */
    private function getAgeBucket($daysPastDue)
    {
        if ($daysPastDue >= 0) {
            return 'current';
        } elseif ($daysPastDue >= -30) {
            return '1-30_days';
        } elseif ($daysPastDue >= -60) {
            return '31-60_days';
        } elseif ($daysPastDue >= -90) {
            return '61-90_days';
        } else {
            return 'over_90_days';
        }
    }

    /**
     * Get currency summary for a specific receivable.
     *
     * @param CustomerReceivable $receivable
     * @return array
     */
    private function getReceivableCurrencySummary($receivable)
    {
        $baseCurrency = config('app.base_currency', 'USD');
        
        return [
            'original_currency' => $receivable->currency_code,
            'base_currency' => $baseCurrency,
            'exchange_rate' => $receivable->exchange_rate,
            'amounts' => [
                'original' => [
                    'total' => $receivable->amount,
                    'paid' => $receivable->paid_amount,
                    'balance' => $receivable->balance
                ],
                'base_currency' => [
                    'total' => $receivable->base_currency_amount,
                    'balance' => $receivable->base_currency_balance
                ]
            ]
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