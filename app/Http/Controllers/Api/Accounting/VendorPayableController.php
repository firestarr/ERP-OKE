<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\VendorPayable;
use App\Models\Accounting\ExchangeRate;
use App\Models\Vendor;
use App\Models\VendorInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class VendorPayableController extends Controller
{
    /**
     * Display a listing of vendor payables.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = VendorPayable::with(['vendor', 'vendorInvoice']);
        
        // Filter by vendor
        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
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
        
        // Filter by priority (if overdue)
        if ($request->boolean('overdue_only')) {
            $query->where('due_date', '<', now())
                  ->where('status', '!=', 'Paid');
        }
        
        $payables = $query->orderBy('due_date')
            ->paginate($request->input('per_page', 15));
        
        // Add currency conversion if requested
        if ($request->filled('display_currency') && $request->display_currency !== config('app.base_currency', 'USD')) {
            $payables->getCollection()->transform(function ($payable) use ($request) {
                $convertedAmounts = $payable->getAmountsInCurrency($request->display_currency);
                $payable->converted_amounts = $convertedAmounts;
                return $payable;
            });
        }
        
        return response()->json($payables, 200);
    }

    /**
     * Store a newly created vendor payable in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vendor_id' => 'required|exists:vendors,vendor_id',
            'invoice_id' => 'required|exists:vendor_invoices,invoice_id',
            'amount' => 'required|numeric|min:0',
            'currency_code' => 'nullable|string|size:3',
            'due_date' => 'required|date',
            'status' => 'required|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Check if a payable already exists for this invoice
        $exists = VendorPayable::where('invoice_id', $request->invoice_id)->exists();
        if ($exists) {
            return response()->json([
                'message' => 'A payable already exists for this invoice'
            ], 422);
        }

        $baseCurrency = config('app.base_currency', 'USD');
        $currency = $request->currency_code ?? $baseCurrency;
        
        // Get exchange rate for currency conversion
        $exchangeRate = $this->getExchangeRate($currency, $baseCurrency, $request->due_date);
        $baseCurrencyAmount = $request->amount * $exchangeRate;

        $payable = VendorPayable::create([
            'vendor_id' => $request->vendor_id,
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
            'data' => $payable->load(['vendor', 'vendorInvoice']), 
            'message' => 'Vendor payable created successfully'
        ], 201);
    }

    /**
     * Display the specified vendor payable.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $payable = VendorPayable::with(['vendor', 'vendorInvoice', 'payablePayments'])
            ->findOrFail($id);
        
        // Add currency summary
        $currencySummary = $this->getPayableCurrencySummary($payable);
        
        return response()->json([
            'data' => $payable,
            'currency_summary' => $currencySummary
        ], 200);
    }

    /**
     * Update the specified vendor payable in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $payable = VendorPayable::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'vendor_id' => 'exists:vendors,vendor_id',
            'invoice_id' => 'exists:vendor_invoices,invoice_id',
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
            $amount = $request->amount ?? $payable->amount;
            $currency = $request->currency_code ?? $payable->currency_code;
            
            $exchangeRate = $this->getExchangeRate($currency, $baseCurrency, $request->due_date ?? $payable->due_date);
            $baseCurrencyAmount = $amount * $exchangeRate;
            
            // Update base currency fields
            $request->merge([
                'exchange_rate' => $exchangeRate,
                'base_currency_amount' => $baseCurrencyAmount,
                'base_currency_balance' => $baseCurrencyAmount - ($payable->paid_amount * $exchangeRate),
                'balance' => $amount - $payable->paid_amount
            ]);
        }

        $payable->update($request->only([
            'vendor_id', 'invoice_id', 'amount', 'currency_code', 
            'exchange_rate', 'base_currency_amount', 'base_currency_balance',
            'due_date', 'balance', 'status'
        ]));

        return response()->json([
            'data' => $payable->load(['vendor', 'vendorInvoice']), 
            'message' => 'Vendor payable updated successfully'
        ], 200);
    }

    /**
     * Remove the specified vendor payable from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $payable = VendorPayable::findOrFail($id);
        
        // Check if there are payments
        if ($payable->payablePayments()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete payable with recorded payments'
            ], 422);
        }
        
        $payable->delete();

        return response()->json(['message' => 'Vendor payable deleted successfully'], 200);
    }

    /**
     * Generate aging report for vendor payables.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function aging(Request $request)
    {
        $asOfDate = $request->input('as_of_date', now()->toDateString());
        $currency = $request->input('currency', config('app.base_currency', 'USD'));
        $groupByCurrency = $request->boolean('group_by_currency', false);
        
        $query = VendorPayable::with('vendor')
            ->where('status', '!=', 'Paid')
            ->where('due_date', '<=', $asOfDate);
        
        // Filter by vendor if specified
        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }
        
        // Filter by currency if not grouping by currency
        if (!$groupByCurrency && $request->filled('currency')) {
            $query->where('currency_code', $request->currency);
        }
        
        $payables = $query->get();
        
        if ($groupByCurrency) {
            return $this->generateMultiCurrencyAging($payables, $asOfDate);
        } else {
            return $this->generateSingleCurrencyAging($payables, $asOfDate, $currency);
        }
    }

    /**
     * Get vendor transactions in specific currency.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function vendorTransactions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vendor_id' => 'required|exists:vendors,vendor_id',
            'currency' => 'nullable|string|size:3',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $query = VendorPayable::with(['vendorInvoice', 'payablePayments'])
            ->where('vendor_id', $request->vendor_id);

        // Filter by currency
        if ($request->filled('currency')) {
            $query->where('currency_code', $request->currency);
        }

        // Filter by date range
        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->whereBetween('due_date', [$request->from_date, $request->to_date]);
        }

        $payables = $query->orderBy('due_date')->get();

        // Group by currency and calculate totals
        $currencyGroups = $payables->groupBy('currency_code')->map(function($group, $currency) {
            return [
                'currency' => $currency,
                'total_amount' => $group->sum('amount'),
                'total_paid' => $group->sum('paid_amount'),
                'total_balance' => $group->sum('balance'),
                'count' => $group->count(),
                'overdue_count' => $group->where('due_date', '<', now())->where('status', '!=', 'Paid')->count(),
                'overdue_amount' => $group->where('due_date', '<', now())->where('status', '!=', 'Paid')->sum('balance'),
                'payables' => $group->values()
            ];
        });

        return response()->json([
            'data' => $currencyGroups,
            'summary' => [
                'total_payables' => $payables->count(),
                'currencies_involved' => $currencyGroups->keys(),
                'base_currency_total' => $payables->sum('base_currency_amount'),
                'base_currency_balance' => $payables->sum('base_currency_balance')
            ]
        ], 200);
    }

    /**
     * Convert payable amounts to different currency.
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

        $payable = VendorPayable::with(['vendor', 'vendorInvoice'])->findOrFail($id);
        $targetCurrency = $request->target_currency;
        $conversionDate = $request->conversion_date ?? now()->toDateString();

        $convertedAmounts = $payable->getAmountsInCurrency($targetCurrency, $conversionDate);
        $exchangeRate = $this->getExchangeRate($payable->currency_code, $targetCurrency, $conversionDate);

        return response()->json([
            'data' => [
                'payable' => $payable,
                'original_currency' => $payable->currency_code,
                'target_currency' => $targetCurrency,
                'exchange_rate' => $exchangeRate,
                'conversion_date' => $conversionDate,
                'converted_amounts' => $convertedAmounts
            ]
        ], 200);
    }

    /**
     * Get currency summary for all payables.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function currencySummary(Request $request)
    {
        $query = VendorPayable::query();
        
        // Filter by vendor if specified
        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }
        
        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        $payables = $query->get();
        
        $summary = $payables->groupBy('currency_code')->map(function($group, $currency) {
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
                'overdue_amount' => $group->where('due_date', '<', now())->where('status', '!=', 'Paid')->sum('balance'),
                'average_days_to_pay' => $this->calculateAverageDaysToPay($group)
            ];
        });
        
        return response()->json([
            'data' => $summary,
            'grand_total' => [
                'base_currency' => config('app.base_currency', 'USD'),
                'total_amount' => $payables->sum('base_currency_amount'),
                'total_balance' => $payables->sum('base_currency_balance'),
                'currencies_count' => $summary->count(),
                'total_overdue' => $payables->where('due_date', '<', now())->where('status', '!=', 'Paid')->sum('base_currency_balance')
            ]
        ], 200);
    }

    /**
     * Get cash flow projection based on payables.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function cashFlowProjection(Request $request)
    {
        $fromDate = $request->input('from_date', now()->toDateString());
        $toDate = $request->input('to_date', now()->addMonths(3)->toDateString());
        $currency = $request->input('currency', config('app.base_currency', 'USD'));
        
        $query = VendorPayable::with('vendor')
            ->where('status', '!=', 'Paid')
            ->whereBetween('due_date', [$fromDate, $toDate]);
        
        if ($request->filled('currency') && $currency !== config('app.base_currency', 'USD')) {
            $query->where('currency_code', $currency);
        }
        
        $payables = $query->orderBy('due_date')->get();
        
        // Group by month for projection
        $monthlyProjection = $payables->groupBy(function($payable) {
            return $payable->due_date->format('Y-m');
        })->map(function($group, $month) use ($currency) {
            $baseCurrency = config('app.base_currency', 'USD');
            
            if ($currency === $baseCurrency) {
                $amount = $group->sum('base_currency_balance');
            } else {
                $amount = $group->where('currency_code', $currency)->sum('balance');
            }
            
            return [
                'month' => $month,
                'currency' => $currency,
                'amount' => $amount,
                'count' => $group->count(),
                'payables' => $group->map(function($payable) use ($currency) {
                    $amounts = $payable->getAmountsInCurrency($currency);
                    return [
                        'vendor_name' => $payable->vendor->name,
                        'due_date' => $payable->due_date,
                        'amount' => $amounts['balance'],
                        'priority' => $payable->due_date < now() ? 'overdue' : 'upcoming'
                    ];
                })
            ];
        });
        
        return response()->json([
            'data' => $monthlyProjection,
            'summary' => [
                'period' => ['from' => $fromDate, 'to' => $toDate],
                'currency' => $currency,
                'total_amount' => $monthlyProjection->sum('amount'),
                'total_count' => $monthlyProjection->sum('count')
            ]
        ], 200);
    }

    /**
     * Get available currencies from payables.
     *
     * @return \Illuminate\Http\Response
     */
    public function getAvailableCurrencies()
    {
        $currencies = VendorPayable::distinct()
            ->pluck('currency_code')
            ->filter()
            ->sort()
            ->values();
            
        return response()->json(['data' => $currencies], 200);
    }

    /**
     * Generate single currency aging report.
     *
     * @param \Illuminate\Support\Collection $payables
     * @param string $asOfDate
     * @param string $currency
     * @return \Illuminate\Http\Response
     */
    private function generateSingleCurrencyAging($payables, $asOfDate, $currency)
    {
        $aging = [];
        $baseCurrency = config('app.base_currency', 'USD');
        
        foreach ($payables as $payable) {
            $daysPastDue = now()->parse($asOfDate)->diffInDays($payable->due_date, false);
            
            // Convert amounts to requested currency
            $amounts = $payable->getAmountsInCurrency($currency, $asOfDate);
            
            $aging[] = [
                'payable_id' => $payable->payable_id,
                'vendor_name' => $payable->vendor->name,
                'invoice_number' => $payable->vendorInvoice->invoice_number ?? 'N/A',
                'due_date' => $payable->due_date,
                'days_past_due' => $daysPastDue,
                'original_currency' => $payable->currency_code,
                'original_amount' => $payable->amount,
                'original_balance' => $payable->balance,
                'display_currency' => $currency,
                'display_amount' => $amounts['amount'],
                'display_balance' => $amounts['balance'],
                'age_bucket' => $this->getAgeBucket($daysPastDue),
                'priority' => $this->getPaymentPriority($daysPastDue, $amounts['balance'])
            ];
        }
        
        // Group by age buckets
        $agingBuckets = collect($aging)->groupBy('age_bucket')->map(function($group) {
            return [
                'count' => $group->count(),
                'total_amount' => $group->sum('display_amount'),
                'total_balance' => $group->sum('display_balance'),
                'payables' => $group->values()
            ];
        });
        
        return response()->json([
            'data' => $agingBuckets,
            'summary' => [
                'as_of_date' => $asOfDate,
                'currency' => $currency,
                'total_payables' => count($aging),
                'total_amount' => collect($aging)->sum('display_amount'),
                'total_balance' => collect($aging)->sum('display_balance'),
                'critical_count' => collect($aging)->where('priority', 'critical')->count()
            ]
        ], 200);
    }

    /**
     * Generate multi-currency aging report.
     *
     * @param \Illuminate\Support\Collection $payables
     * @param string $asOfDate
     * @return \Illuminate\Http\Response
     */
    private function generateMultiCurrencyAging($payables, $asOfDate)
    {
        $aging = $payables->groupBy('currency_code')->map(function($group, $currency) use ($asOfDate) {
            $agingData = [];
            
            foreach ($group as $payable) {
                $daysPastDue = now()->parse($asOfDate)->diffInDays($payable->due_date, false);
                
                $agingData[] = [
                    'payable_id' => $payable->payable_id,
                    'vendor_name' => $payable->vendor->name,
                    'invoice_number' => $payable->vendorInvoice->invoice_number ?? 'N/A',
                    'due_date' => $payable->due_date,
                    'days_past_due' => $daysPastDue,
                    'amount' => $payable->amount,
                    'balance' => $payable->balance,
                    'base_currency_amount' => $payable->base_currency_amount,
                    'base_currency_balance' => $payable->base_currency_balance,
                    'age_bucket' => $this->getAgeBucket($daysPastDue),
                    'priority' => $this->getPaymentPriority($daysPastDue, $payable->balance)
                ];
            }
            
            return [
                'currency' => $currency,
                'total_amount' => $group->sum('amount'),
                'total_balance' => $group->sum('balance'),
                'base_currency_total' => $group->sum('base_currency_amount'),
                'base_currency_balance' => $group->sum('base_currency_balance'),
                'critical_count' => collect($agingData)->where('priority', 'critical')->count(),
                'payables' => $agingData
            ];
        });
        
        return response()->json([
            'data' => $aging,
            'summary' => [
                'as_of_date' => $asOfDate,
                'base_currency' => config('app.base_currency', 'USD'),
                'total_payables' => $payables->count(),
                'base_currency_total' => $payables->sum('base_currency_amount'),
                'base_currency_balance' => $payables->sum('base_currency_balance'),
                'currencies_involved' => $aging->keys(),
                'total_critical' => $aging->sum('critical_count')
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
     * Get payment priority based on days past due and amount.
     *
     * @param int $daysPastDue
     * @param float $amount
     * @return string
     */
    private function getPaymentPriority($daysPastDue, $amount)
    {
        if ($daysPastDue <= -60 || $amount > 50000) {
            return 'critical';
        } elseif ($daysPastDue <= -30 || $amount > 20000) {
            return 'high';
        } elseif ($daysPastDue <= -7) {
            return 'medium';
        } else {
            return 'normal';
        }
    }

    /**
     * Calculate average days to pay for a group of payables.
     *
     * @param \Illuminate\Support\Collection $payables
     * @return float
     */
    private function calculateAverageDaysToPay($payables)
    {
        $paidPayables = $payables->where('status', 'Paid');
        
        if ($paidPayables->isEmpty()) {
            return 0;
        }
        
        $totalDays = $paidPayables->sum(function($payable) {
            // This would require payment_date in the model
            // For now, return 0 or implement based on your payment tracking
            return 0;
        });
        
        return $totalDays / $paidPayables->count();
    }

    /**
     * Get currency summary for a specific payable.
     *
     * @param VendorPayable $payable
     * @return array
     */
    private function getPayableCurrencySummary($payable)
    {
        $baseCurrency = config('app.base_currency', 'USD');
        
        return [
            'original_currency' => $payable->currency_code,
            'base_currency' => $baseCurrency,
            'exchange_rate' => $payable->exchange_rate,
            'amounts' => [
                'original' => [
                    'total' => $payable->amount,
                    'paid' => $payable->paid_amount,
                    'balance' => $payable->balance
                ],
                'base_currency' => [
                    'total' => $payable->base_currency_amount,
                    'balance' => $payable->base_currency_balance
                ]
            ],
            'payment_priority' => $this->getPaymentPriority(
                now()->diffInDays($payable->due_date, false),
                $payable->balance
            )
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