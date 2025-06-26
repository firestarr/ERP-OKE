<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\BankAccount;
use App\Models\Accounting\BankReconciliation;
use App\Models\Accounting\BankReconciliationLine;
use App\Models\Accounting\ExchangeRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BankReconciliationController extends Controller
{
    /**
     * Display a listing of bank reconciliations.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = BankReconciliation::with(['bankAccount', 'bankReconciliationLines']);
        
        // Filter by bank account
        if ($request->has('bank_id')) {
            $query->where('bank_id', $request->bank_id);
        }
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Filter by currency
        if ($request->has('currency')) {
            $query->whereHas('bankAccount', function($q) use ($request) {
                $q->where('currency', $request->currency);
            });
        }
        
        // Filter by statement date range
        if ($request->has('from_date') && $request->has('to_date')) {
            $query->whereBetween('statement_date', [$request->from_date, $request->to_date]);
        }
        
        $reconciliations = $query->orderBy('statement_date', 'desc')
            ->paginate($request->input('per_page', 15));
        
        // Add currency conversion if requested
        if ($request->filled('display_currency')) {
            $displayCurrency = $request->display_currency;
            $conversionDate = $request->input('conversion_date', now()->toDateString());
            
            $reconciliations->getCollection()->transform(function ($reconciliation) use ($displayCurrency, $conversionDate) {
                $bankCurrency = $reconciliation->bankAccount->currency;
                if ($bankCurrency !== $displayCurrency) {
                    $rate = $this->getExchangeRate($bankCurrency, $displayCurrency, $conversionDate);
                    $reconciliation->converted_statement_balance = $reconciliation->statement_balance * $rate;
                    $reconciliation->converted_book_balance = $reconciliation->book_balance * $rate;
                    $reconciliation->converted_difference = $reconciliation->difference * $rate;
                    $reconciliation->display_currency = $displayCurrency;
                    $reconciliation->exchange_rate = $rate;
                }
                return $reconciliation;
            });
        }
        
        return response()->json($reconciliations, 200);
    }

    /**
     * Store a newly created bank reconciliation in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bank_id' => 'required|exists:BankAccount,bank_id',
            'statement_date' => 'required|date',
            'statement_balance' => 'required|numeric',
            'book_balance' => 'required|numeric',
            'status' => 'required|string|max:50',
            'statement_reference' => 'nullable|string|max:100',
            'reconciler_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Check if a reconciliation already exists for this bank account and date
        $exists = BankReconciliation::where('bank_id', $request->bank_id)
            ->where('statement_date', $request->statement_date)
            ->exists();
        
        if ($exists) {
            return response()->json([
                'message' => 'A reconciliation already exists for this bank account and date'
            ], 422);
        }
        
        $bankAccount = BankAccount::findOrFail($request->bank_id);
        $baseCurrency = config('app.base_currency', 'USD');
        
        // Calculate base currency equivalents
        $exchangeRate = $this->getExchangeRate($bankAccount->currency, $baseCurrency, $request->statement_date);
        $baseCurrencyStatementBalance = $request->statement_balance * $exchangeRate;
        $baseCurrencyBookBalance = $request->book_balance * $exchangeRate;
        $difference = $request->statement_balance - $request->book_balance;
        $baseCurrencyDifference = $difference * $exchangeRate;
        
        $reconciliation = BankReconciliation::create([
            'bank_id' => $request->bank_id,
            'statement_date' => $request->statement_date,
            'statement_balance' => $request->statement_balance,
            'book_balance' => $request->book_balance,
            'difference' => $difference,
            'currency' => $bankAccount->currency,
            'base_currency_statement_balance' => $baseCurrencyStatementBalance,
            'base_currency_book_balance' => $baseCurrencyBookBalance,
            'base_currency_difference' => $baseCurrencyDifference,
            'exchange_rate' => $exchangeRate,
            'status' => $request->status,
            'statement_reference' => $request->statement_reference,
            'reconciler_name' => $request->reconciler_name,
            'notes' => $request->notes
        ]);

        return response()->json([
            'data' => $reconciliation->load('bankAccount'), 
            'message' => 'Bank reconciliation created successfully'
        ], 201);
    }

    /**
     * Display the specified bank reconciliation.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $reconciliation = BankReconciliation::with([
            'bankAccount',
            'bankReconciliationLines'
        ])->findOrFail($id);
        
        // Calculate reconciliation summary
        $summary = $this->calculateReconciliationSummary($reconciliation);
        
        // Add currency summary
        $currencySummary = $this->getReconciliationCurrencySummary($reconciliation);
        
        return response()->json([
            'data' => $reconciliation,
            'summary' => $summary,
            'currency_summary' => $currencySummary
        ], 200);
    }

    /**
     * Update the specified bank reconciliation in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $reconciliation = BankReconciliation::findOrFail($id);
        
        // Don't allow updates if reconciliation is finalized
        if ($reconciliation->status === 'Finalized') {
            return response()->json(['message' => 'Cannot update finalized reconciliation'], 422);
        }
        
        $validator = Validator::make($request->all(), [
            'statement_date' => 'date',
            'statement_balance' => 'numeric',
            'book_balance' => 'numeric',
            'status' => 'string|max:50',
            'statement_reference' => 'nullable|string|max:100',
            'reconciler_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $bankAccount = $reconciliation->bankAccount;
        $baseCurrency = config('app.base_currency', 'USD');
        
        // Recalculate base currency amounts if values changed
        if ($request->has('statement_balance') || $request->has('book_balance') || $request->has('statement_date')) {
            $statementBalance = $request->statement_balance ?? $reconciliation->statement_balance;
            $bookBalance = $request->book_balance ?? $reconciliation->book_balance;
            $date = $request->statement_date ?? $reconciliation->statement_date;
            
            $exchangeRate = $this->getExchangeRate($bankAccount->currency, $baseCurrency, $date);
            $baseCurrencyStatementBalance = $statementBalance * $exchangeRate;
            $baseCurrencyBookBalance = $bookBalance * $exchangeRate;
            $difference = $statementBalance - $bookBalance;
            $baseCurrencyDifference = $difference * $exchangeRate;
            
            $request->merge([
                'difference' => $difference,
                'base_currency_statement_balance' => $baseCurrencyStatementBalance,
                'base_currency_book_balance' => $baseCurrencyBookBalance,
                'base_currency_difference' => $baseCurrencyDifference,
                'exchange_rate' => $exchangeRate
            ]);
        }

        $reconciliation->update($request->only([
            'statement_date', 'statement_balance', 'book_balance', 'difference',
            'base_currency_statement_balance', 'base_currency_book_balance', 
            'base_currency_difference', 'exchange_rate', 'status', 
            'statement_reference', 'reconciler_name', 'notes'
        ]));

        return response()->json([
            'data' => $reconciliation->load('bankAccount'), 
            'message' => 'Bank reconciliation updated successfully'
        ], 200);
    }

    /**
     * Remove the specified bank reconciliation from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $reconciliation = BankReconciliation::findOrFail($id);
        
        if ($reconciliation->status === 'Finalized') {
            return response()->json([
                'message' => 'Cannot delete finalized reconciliation'
            ], 422);
        }
        
        // Delete all reconciliation lines
        $reconciliation->bankReconciliationLines()->delete();
        $reconciliation->delete();

        return response()->json(['message' => 'Bank reconciliation deleted successfully'], 200);
    }

    /**
     * Finalize the specified bank reconciliation.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function finalize($id)
    {
        $reconciliation = BankReconciliation::with('bankReconciliationLines')->findOrFail($id);
        
        if ($reconciliation->status === 'Finalized') {
            return response()->json(['message' => 'Reconciliation is already finalized'], 422);
        }
        
        // Check if reconciliation is balanced
        $summary = $this->calculateReconciliationSummary($reconciliation);
        
        if (abs($summary['adjusted_difference']) > 0.01) {
            return response()->json([
                'message' => 'Cannot finalize unbalanced reconciliation. Adjusted difference: ' . $summary['adjusted_difference']
            ], 422);
        }
        
        DB::beginTransaction();
        
        try {
            // Update reconciliation status
            $reconciliation->update([
                'status' => 'Finalized',
                'finalized_at' => now(),
                'finalized_by' => auth()->id() ?? 'system'
            ]);
            
            // Update bank account balance
            $reconciliation->bankAccount->update([
                'current_balance' => $reconciliation->statement_balance,
                'base_currency_balance' => $reconciliation->base_currency_statement_balance,
                'last_reconciled_date' => $reconciliation->statement_date
            ]);
            
            DB::commit();

            return response()->json([
                'data' => $reconciliation, 
                'message' => 'Bank reconciliation finalized successfully'
            ], 200);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to finalize reconciliation: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get reconciliation lines for a specific reconciliation.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function lines($id)
    {
        $reconciliation = BankReconciliation::with(['bankReconciliationLines', 'bankAccount'])->findOrFail($id);
        
        return response()->json([
            'data' => $reconciliation->bankReconciliationLines,
            'reconciliation' => $reconciliation
        ], 200);
    }

    /**
     * Store a new reconciliation line.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $reconciliationId
     * @return \Illuminate\Http\Response
     */
    public function storeLine(Request $request, $reconciliationId)
    {
        $reconciliation = BankReconciliation::findOrFail($reconciliationId);
        
        if ($reconciliation->status === 'Finalized') {
            return response()->json(['message' => 'Cannot add lines to finalized reconciliation'], 422);
        }
        
        $validator = Validator::make($request->all(), [
            'line_type' => 'required|in:outstanding_deposit,outstanding_check,bank_charge,bank_interest,adjustment',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric',
            'reference_number' => 'nullable|string|max:100',
            'transaction_date' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $bankAccount = $reconciliation->bankAccount;
        $baseCurrency = config('app.base_currency', 'USD');
        
        // Calculate base currency equivalent
        $exchangeRate = $this->getExchangeRate($bankAccount->currency, $baseCurrency, $reconciliation->statement_date);
        $baseCurrencyAmount = $request->amount * $exchangeRate;
        
        $line = BankReconciliationLine::create([
            'reconciliation_id' => $reconciliationId,
            'line_type' => $request->line_type,
            'description' => $request->description,
            'amount' => $request->amount,
            'currency' => $bankAccount->currency,
            'base_currency_amount' => $baseCurrencyAmount,
            'exchange_rate' => $exchangeRate,
            'reference_number' => $request->reference_number,
            'transaction_date' => $request->transaction_date ?? $reconciliation->statement_date
        ]);

        return response()->json([
            'data' => $line, 
            'message' => 'Reconciliation line created successfully'
        ], 201);
    }

    /**
     * Update a reconciliation line.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $reconciliationId
     * @param  int  $lineId
     * @return \Illuminate\Http\Response
     */
    public function updateLine(Request $request, $reconciliationId, $lineId)
    {
        $reconciliation = BankReconciliation::findOrFail($reconciliationId);
        
        if ($reconciliation->status === 'Finalized') {
            return response()->json(['message' => 'Cannot update lines in finalized reconciliation'], 422);
        }
        
        $line = BankReconciliationLine::where('reconciliation_id', $reconciliationId)
            ->where('line_id', $lineId)
            ->firstOrFail();
        
        $validator = Validator::make($request->all(), [
            'line_type' => 'in:outstanding_deposit,outstanding_check,bank_charge,bank_interest,adjustment',
            'description' => 'string|max:255',
            'amount' => 'numeric',
            'reference_number' => 'nullable|string|max:100',
            'transaction_date' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $bankAccount = $reconciliation->bankAccount;
        $baseCurrency = config('app.base_currency', 'USD');
        
        // Recalculate base currency amount if amount changed
        if ($request->has('amount')) {
            $exchangeRate = $this->getExchangeRate($bankAccount->currency, $baseCurrency, $reconciliation->statement_date);
            $baseCurrencyAmount = $request->amount * $exchangeRate;
            
            $request->merge([
                'base_currency_amount' => $baseCurrencyAmount,
                'exchange_rate' => $exchangeRate
            ]);
        }

        $line->update($request->only([
            'line_type', 'description', 'amount', 'base_currency_amount',
            'exchange_rate', 'reference_number', 'transaction_date'
        ]));

        return response()->json([
            'data' => $line, 
            'message' => 'Reconciliation line updated successfully'
        ], 200);
    }

    /**
     * Delete a reconciliation line.
     *
     * @param  int  $reconciliationId
     * @param  int  $lineId
     * @return \Illuminate\Http\Response
     */
    public function destroyLine($reconciliationId, $lineId)
    {
        $reconciliation = BankReconciliation::findOrFail($reconciliationId);
        
        if ($reconciliation->status === 'Finalized') {
            return response()->json(['message' => 'Cannot delete lines from finalized reconciliation'], 422);
        }
        
        $line = BankReconciliationLine::where('reconciliation_id', $reconciliationId)
            ->where('line_id', $lineId)
            ->firstOrFail();
        
        $line->delete();

        return response()->json(['message' => 'Reconciliation line deleted successfully'], 200);
    }

    /**
     * Generate reconciliation summary report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function summaryReport(Request $request)
    {
        $request->validate([
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'currency' => 'nullable|string|size:3',
            'bank_id' => 'nullable|exists:BankAccount,bank_id'
        ]);
        
        $query = BankReconciliation::with('bankAccount');
        
        // Filter by date range
        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->whereBetween('statement_date', [$request->from_date, $request->to_date]);
        }
        
        // Filter by bank account
        if ($request->filled('bank_id')) {
            $query->where('bank_id', $request->bank_id);
        }
        
        // Filter by currency
        if ($request->filled('currency')) {
            $query->whereHas('bankAccount', function($q) use ($request) {
                $q->where('currency', $request->currency);
            });
        }
        
        $reconciliations = $query->get();
        $reportCurrency = $request->input('currency', config('app.base_currency', 'USD'));
        
        // Group by bank account and currency
        $summary = $reconciliations->groupBy('bank_id')->map(function($group, $bankId) use ($reportCurrency) {
            $bankAccount = $group->first()->bankAccount;
            $rate = $this->getExchangeRate($bankAccount->currency, $reportCurrency, now()->toDateString());
            
            $totalStatementBalance = 0;
            $totalBookBalance = 0;
            $totalDifference = 0;
            $finalizedCount = 0;
            $pendingCount = 0;
            
            foreach ($group as $reconciliation) {
                $conversionRate = $this->getExchangeRate($bankAccount->currency, $reportCurrency, $reconciliation->statement_date);
                $totalStatementBalance += $reconciliation->statement_balance * $conversionRate;
                $totalBookBalance += $reconciliation->book_balance * $conversionRate;
                $totalDifference += $reconciliation->difference * $conversionRate;
                
                if ($reconciliation->status === 'Finalized') {
                    $finalizedCount++;
                } else {
                    $pendingCount++;
                }
            }
            
            return [
                'bank_id' => $bankId,
                'bank_name' => $bankAccount->bank_name,
                'account_name' => $bankAccount->account_name,
                'account_currency' => $bankAccount->currency,
                'report_currency' => $reportCurrency,
                'reconciliation_count' => $group->count(),
                'finalized_count' => $finalizedCount,
                'pending_count' => $pendingCount,
                'total_statement_balance' => $totalStatementBalance,
                'total_book_balance' => $totalBookBalance,
                'total_difference' => $totalDifference,
                'last_reconciliation_date' => $group->max('statement_date'),
                'average_difference' => $group->count() > 0 ? $totalDifference / $group->count() : 0
            ];
        });
        
        return response()->json([
            'data' => $summary,
            'grand_total' => [
                'currency' => $reportCurrency,
                'total_reconciliations' => $reconciliations->count(),
                'total_banks' => $summary->count(),
                'total_statement_balance' => $summary->sum('total_statement_balance'),
                'total_book_balance' => $summary->sum('total_book_balance'),
                'total_difference' => $summary->sum('total_difference'),
                'finalized_reconciliations' => $summary->sum('finalized_count'),
                'pending_reconciliations' => $summary->sum('pending_count')
            ]
        ], 200);
    }

    /**
     * Convert reconciliation amounts to different currency.
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

        $reconciliation = BankReconciliation::with(['bankAccount', 'bankReconciliationLines'])->findOrFail($id);
        $targetCurrency = $request->target_currency;
        $conversionDate = $request->conversion_date ?? $reconciliation->statement_date;

        $bankCurrency = $reconciliation->bankAccount->currency;
        $exchangeRate = $this->getExchangeRate($bankCurrency, $targetCurrency, $conversionDate);
        
        $convertedAmounts = [
            'statement_balance' => $reconciliation->statement_balance * $exchangeRate,
            'book_balance' => $reconciliation->book_balance * $exchangeRate,
            'difference' => $reconciliation->difference * $exchangeRate
        ];
        
        // Convert reconciliation lines
        $convertedLines = $reconciliation->bankReconciliationLines->map(function($line) use ($exchangeRate) {
            return [
                'line_id' => $line->line_id,
                'line_type' => $line->line_type,
                'description' => $line->description,
                'original_amount' => $line->amount,
                'converted_amount' => $line->amount * $exchangeRate
            ];
        });

        return response()->json([
            'data' => [
                'reconciliation' => $reconciliation,
                'original_currency' => $bankCurrency,
                'target_currency' => $targetCurrency,
                'exchange_rate' => $exchangeRate,
                'conversion_date' => $conversionDate,
                'converted_amounts' => $convertedAmounts,
                'converted_lines' => $convertedLines
            ]
        ], 200);
    }

    /**
     * Get available currencies from bank reconciliations.
     *
     * @return \Illuminate\Http\Response
     */
    public function getAvailableCurrencies()
    {
        $currencies = BankReconciliation::join('BankAccount', 'BankReconciliation.bank_id', '=', 'BankAccount.bank_id')
            ->distinct()
            ->pluck('BankAccount.currency')
            ->filter()
            ->sort()
            ->values();
            
        return response()->json(['data' => $currencies], 200);
    }

    /**
     * Calculate reconciliation summary.
     *
     * @param BankReconciliation $reconciliation
     * @return array
     */
    private function calculateReconciliationSummary($reconciliation)
    {
        $lines = $reconciliation->bankReconciliationLines;
        
        $outstandingDeposits = $lines->where('line_type', 'outstanding_deposit')->sum('amount');
        $outstandingChecks = $lines->where('line_type', 'outstanding_check')->sum('amount');
        $bankCharges = $lines->where('line_type', 'bank_charge')->sum('amount');
        $bankInterest = $lines->where('line_type', 'bank_interest')->sum('amount');
        $adjustments = $lines->where('line_type', 'adjustment')->sum('amount');
        
        // Adjusted book balance calculation
        $adjustedBookBalance = $reconciliation->book_balance + $outstandingDeposits - $outstandingChecks - $bankCharges + $bankInterest + $adjustments;
        $adjustedDifference = $reconciliation->statement_balance - $adjustedBookBalance;
        
        return [
            'outstanding_deposits' => $outstandingDeposits,
            'outstanding_checks' => $outstandingChecks,
            'bank_charges' => $bankCharges,
            'bank_interest' => $bankInterest,
            'adjustments' => $adjustments,
            'adjusted_book_balance' => $adjustedBookBalance,
            'adjusted_difference' => $adjustedDifference,
            'is_balanced' => abs($adjustedDifference) < 0.01,
            'total_reconciling_items' => $lines->count()
        ];
    }

    /**
     * Get currency summary for a reconciliation.
     *
     * @param BankReconciliation $reconciliation
     * @return array
     */
    private function getReconciliationCurrencySummary($reconciliation)
    {
        $baseCurrency = config('app.base_currency', 'USD');
        $bankCurrency = $reconciliation->bankAccount->currency;
        
        return [
            'bank_currency' => $bankCurrency,
            'base_currency' => $baseCurrency,
            'exchange_rate' => $reconciliation->exchange_rate,
            'balances' => [
                'original' => [
                    'currency' => $bankCurrency,
                    'statement_balance' => $reconciliation->statement_balance,
                    'book_balance' => $reconciliation->book_balance,
                    'difference' => $reconciliation->difference
                ],
                'base_currency' => [
                    'currency' => $baseCurrency,
                    'statement_balance' => $reconciliation->base_currency_statement_balance,
                    'book_balance' => $reconciliation->base_currency_book_balance,
                    'difference' => $reconciliation->base_currency_difference
                ]
            ],
            'bank_info' => [
                'bank_name' => $reconciliation->bankAccount->bank_name,
                'account_name' => $reconciliation->bankAccount->account_name,
                'account_number' => $reconciliation->bankAccount->account_number
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