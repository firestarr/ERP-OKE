<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\BankTransaction;
use App\Models\Accounting\BankAccount;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Accounting\ExchangeRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BankTransactionController extends Controller
{
    /**
     * Display a listing of bank transactions.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = BankTransaction::with('bankAccount');
        
        // Filter by bank account
        if ($request->filled('bank_id')) {
            $query->where('bank_account_id', $request->bank_id);
        }
        
        // Filter by transaction type
        if ($request->filled('transaction_type')) {
            $query->where('transaction_type', $request->transaction_type);
        }
        
        // Filter by currency
        if ($request->filled('currency')) {
            $query->where('currency', $request->currency);
        }
        
        // Filter by date range
        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->whereBetween('transaction_date', [$request->from_date, $request->to_date]);
        }
        
        // Filter by amount range (in base currency)
        if ($request->filled('min_amount')) {
            $query->where('base_currency_amount', '>=', $request->min_amount);
        }
        if ($request->filled('max_amount')) {
            $query->where('base_currency_amount', '<=', $request->max_amount);
        }
        
        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        $transactions = $query->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));
        
        // Add currency conversion if requested
        if ($request->filled('display_currency')) {
            $displayCurrency = $request->display_currency;
            $conversionDate = $request->input('conversion_date', now()->toDateString());
            
            $transactions->getCollection()->transform(function ($transaction) use ($displayCurrency, $conversionDate) {
                if ($transaction->currency !== $displayCurrency) {
                    $rate = $this->getExchangeRate($transaction->currency, $displayCurrency, $conversionDate);
                    $transaction->converted_amount = $transaction->amount * $rate;
                    $transaction->display_currency = $displayCurrency;
                    $transaction->exchange_rate = $rate;
                }
                return $transaction;
            });
        }
        
        return response()->json($transactions, 200);
    }

    /**
     * Store a newly created bank transaction in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bank_account_id' => 'required|exists:BankAccount,bank_id',
            'transaction_type' => 'required|in:deposit,withdrawal,transfer,fee,interest,adjustment',
            'transaction_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'description' => 'required|string|max:255',
            'reference_number' => 'nullable|string|max:100',
            'counterparty_name' => 'nullable|string|max:255',
            'counterparty_account' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:100',
            'status' => 'nullable|in:pending,cleared,reconciled,cancelled',
            'create_journal_entry' => 'boolean',
            'contra_account_id' => 'nullable|exists:ChartOfAccount,account_id',
            'transfer_to_bank_id' => 'nullable|exists:BankAccount,bank_id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $bankAccount = BankAccount::findOrFail($request->bank_account_id);
        $currency = $request->currency ?? $bankAccount->currency;
        $baseCurrency = config('app.base_currency', 'USD');
        
        // Calculate base currency equivalent
        $exchangeRate = $this->getExchangeRate($currency, $baseCurrency, $request->transaction_date);
        $baseCurrencyAmount = $request->amount * $exchangeRate;
        
        // Validate transfer transactions
        if ($request->transaction_type === 'transfer' && !$request->filled('transfer_to_bank_id')) {
            return response()->json([
                'message' => 'Transfer destination bank account is required for transfer transactions'
            ], 422);
        }
        
        DB::beginTransaction();
        
        try {
            $transaction = BankTransaction::create([
                'bank_account_id' => $request->bank_account_id,
                'transaction_type' => $request->transaction_type,
                'transaction_date' => $request->transaction_date,
                'amount' => $request->amount,
                'currency' => $currency,
                'base_currency_amount' => $baseCurrencyAmount,
                'exchange_rate' => $exchangeRate,
                'description' => $request->description,
                'reference_number' => $request->reference_number,
                'counterparty_name' => $request->counterparty_name,
                'counterparty_account' => $request->counterparty_account,
                'category' => $request->category,
                'status' => $request->status ?? 'pending',
                'transfer_to_bank_id' => $request->transfer_to_bank_id
            ]);
            
            // Handle transfer transactions (create counterpart transaction)
            if ($request->transaction_type === 'transfer') {
                $this->createTransferCounterpart($transaction, $request);
            }
            
            // Update bank account balance if transaction is cleared
            if ($transaction->status === 'cleared' || $transaction->status === 'reconciled') {
                $this->updateBankAccountBalance($transaction);
            }
            
            // Create journal entry if requested
            if ($request->boolean('create_journal_entry')) {
                $this->createTransactionJournalEntry($transaction, $request);
            }
            
            DB::commit();

            return response()->json([
                'data' => $transaction->load('bankAccount'), 
                'message' => 'Bank transaction created successfully'
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create transaction: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified bank transaction.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $transaction = BankTransaction::with(['bankAccount', 'transferToBankAccount', 'journalEntry'])
            ->findOrFail($id);
        
        // Add currency summary
        $currencySummary = $this->getTransactionCurrencySummary($transaction);
        
        return response()->json([
            'data' => $transaction,
            'currency_summary' => $currencySummary
        ], 200);
    }

    /**
     * Update the specified bank transaction in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $transaction = BankTransaction::findOrFail($id);
        
        // Don't allow updates if transaction is reconciled
        if ($transaction->status === 'reconciled') {
            return response()->json(['message' => 'Cannot update reconciled transaction'], 422);
        }
        
        $validator = Validator::make($request->all(), [
            'transaction_type' => 'in:deposit,withdrawal,transfer,fee,interest,adjustment',
            'transaction_date' => 'date',
            'amount' => 'numeric|min:0',
            'currency' => 'string|size:3',
            'description' => 'string|max:255',
            'reference_number' => 'nullable|string|max:100',
            'counterparty_name' => 'nullable|string|max:255',
            'counterparty_account' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:100',
            'status' => 'in:pending,cleared,reconciled,cancelled'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $baseCurrency = config('app.base_currency', 'USD');
        $oldStatus = $transaction->status;
        
        // Recalculate base currency amount if amount, currency, or date changed
        if ($request->has('amount') || $request->has('currency') || $request->has('transaction_date')) {
            $amount = $request->amount ?? $transaction->amount;
            $currency = $request->currency ?? $transaction->currency;
            $date = $request->transaction_date ?? $transaction->transaction_date;
            
            $exchangeRate = $this->getExchangeRate($currency, $baseCurrency, $date);
            $baseCurrencyAmount = $amount * $exchangeRate;
            
            $request->merge([
                'base_currency_amount' => $baseCurrencyAmount,
                'exchange_rate' => $exchangeRate
            ]);
        }
        
        DB::beginTransaction();
        
        try {
            // Reverse old bank balance effect if status is changing from cleared/reconciled
            if (in_array($oldStatus, ['cleared', 'reconciled'])) {
                $this->reverseBankAccountBalance($transaction);
            }
            
            $transaction->update($request->only([
                'transaction_type', 'transaction_date', 'amount', 'currency',
                'base_currency_amount', 'exchange_rate', 'description',
                'reference_number', 'counterparty_name', 'counterparty_account',
                'category', 'status'
            ]));
            
            // Apply new bank balance effect if status is cleared/reconciled
            $newStatus = $request->status ?? $transaction->status;
            if (in_array($newStatus, ['cleared', 'reconciled'])) {
                $this->updateBankAccountBalance($transaction);
            }
            
            DB::commit();

            return response()->json([
                'data' => $transaction->load('bankAccount'), 
                'message' => 'Bank transaction updated successfully'
            ], 200);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update transaction: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified bank transaction from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $transaction = BankTransaction::findOrFail($id);
        
        if ($transaction->status === 'reconciled') {
            return response()->json([
                'message' => 'Cannot delete reconciled transaction'
            ], 422);
        }
        
        DB::beginTransaction();
        
        try {
            // Reverse bank balance effect if transaction was cleared
            if (in_array($transaction->status, ['cleared', 'reconciled'])) {
                $this->reverseBankAccountBalance($transaction);
            }
            
            // Delete associated journal entry if exists
            if ($transaction->journalEntry) {
                $transaction->journalEntry->journalEntryLines()->delete();
                $transaction->journalEntry->delete();
            }
            
            // Delete counterpart transfer transaction if exists
            if ($transaction->transaction_type === 'transfer' && $transaction->transfer_transaction_id) {
                BankTransaction::where('transfer_transaction_id', $transaction->transaction_id)->delete();
            }
            
            $transaction->delete();
            
            DB::commit();

            return response()->json(['message' => 'Bank transaction deleted successfully'], 200);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to delete transaction: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Generate bank transaction summary report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function summaryReport(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'bank_id' => 'nullable|exists:BankAccount,bank_id',
            'currency' => 'nullable|string|size:3',
            'group_by' => 'nullable|in:bank,currency,type,category,month'
        ]);
        
        $fromDate = $request->from_date;
        $toDate = $request->to_date;
        $reportCurrency = $request->input('currency', config('app.base_currency', 'USD'));
        $groupBy = $request->input('group_by', 'bank');
        
        $query = BankTransaction::with('bankAccount')
            ->whereBetween('transaction_date', [$fromDate, $toDate]);
        
        // Filter by bank account
        if ($request->filled('bank_id')) {
            $query->where('bank_account_id', $request->bank_id);
        }
        
        $transactions = $query->get();
        
        // Group and summarize based on groupBy parameter
        $summaryData = match($groupBy) {
            'bank' => $this->groupByBank($transactions, $reportCurrency),
            'currency' => $this->groupByCurrency($transactions, $reportCurrency),
            'type' => $this->groupByTransactionType($transactions, $reportCurrency),
            'category' => $this->groupByCategory($transactions, $reportCurrency),
            'month' => $this->groupByMonth($transactions, $reportCurrency),
            default => $this->groupByBank($transactions, $reportCurrency)
        };
        
        // Calculate overall totals
        $totalAmount = $this->calculateTotalInCurrency($transactions, $reportCurrency, $toDate);
        $totalDeposits = $this->calculateTotalInCurrency(
            $transactions->whereIn('transaction_type', ['deposit', 'interest']), 
            $reportCurrency, 
            $toDate
        );
        $totalWithdrawals = $this->calculateTotalInCurrency(
            $transactions->whereIn('transaction_type', ['withdrawal', 'fee']), 
            $reportCurrency, 
            $toDate
        );
        
        return response()->json([
            'data' => $summaryData,
            'summary' => [
                'period' => ['from' => $fromDate, 'to' => $toDate],
                'currency' => $reportCurrency,
                'group_by' => $groupBy,
                'total_transactions' => $transactions->count(),
                'total_amount' => $totalAmount,
                'total_deposits' => $totalDeposits,
                'total_withdrawals' => $totalWithdrawals,
                'net_amount' => $totalDeposits - $totalWithdrawals,
                'average_transaction_size' => $transactions->count() > 0 ? $totalAmount / $transactions->count() : 0
            ]
        ], 200);
    }

    /**
     * Generate cash flow analysis.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function cashFlowAnalysis(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'bank_id' => 'nullable|exists:BankAccount,bank_id',
            'currency' => 'nullable|string|size:3',
            'interval' => 'nullable|in:daily,weekly,monthly'
        ]);
        
        $fromDate = $request->from_date;
        $toDate = $request->to_date;
        $interval = $request->input('interval', 'daily');
        $reportCurrency = $request->input('currency', config('app.base_currency', 'USD'));
        
        $query = BankTransaction::with('bankAccount')
            ->whereBetween('transaction_date', [$fromDate, $toDate])
            ->where('status', '!=', 'cancelled');
        
        // Filter by bank account
        if ($request->filled('bank_id')) {
            $query->where('bank_account_id', $request->bank_id);
        }
        
        $transactions = $query->get();
        
        // Group by time interval
        $cashFlowData = $transactions->groupBy(function($transaction) use ($interval) {
            return match($interval) {
                'weekly' => $transaction->transaction_date->format('Y-W'),
                'monthly' => $transaction->transaction_date->format('Y-m'),
                default => $transaction->transaction_date->format('Y-m-d')
            };
        })->map(function($group, $period) use ($reportCurrency) {
            $inflows = 0;
            $outflows = 0;
            
            foreach ($group as $transaction) {
                $rate = $this->getExchangeRate($transaction->currency, $reportCurrency, $transaction->transaction_date);
                $convertedAmount = $transaction->amount * $rate;
                
                if (in_array($transaction->transaction_type, ['deposit', 'interest'])) {
                    $inflows += $convertedAmount;
                } elseif (in_array($transaction->transaction_type, ['withdrawal', 'fee'])) {
                    $outflows += $convertedAmount;
                }
            }
            
            return [
                'period' => $period,
                'inflows' => $inflows,
                'outflows' => $outflows,
                'net_flow' => $inflows - $outflows,
                'transaction_count' => $group->count()
            ];
        })->sortBy('period');
        
        // Calculate running balance
        $runningBalance = 0;
        $cashFlowData = $cashFlowData->map(function($item) use (&$runningBalance) {
            $runningBalance += $item['net_flow'];
            $item['running_balance'] = $runningBalance;
            return $item;
        });
        
        return response()->json([
            'data' => $cashFlowData,
            'summary' => [
                'period' => ['from' => $fromDate, 'to' => $toDate],
                'currency' => $reportCurrency,
                'interval' => $interval,
                'total_inflows' => $cashFlowData->sum('inflows'),
                'total_outflows' => $cashFlowData->sum('outflows'),
                'net_cash_flow' => $cashFlowData->sum('net_flow'),
                'average_daily_flow' => $cashFlowData->avg('net_flow'),
                'periods_analyzed' => $cashFlowData->count()
            ]
        ], 200);
    }

    /**
     * Convert transaction amounts to different currency.
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

        $transaction = BankTransaction::with('bankAccount')->findOrFail($id);
        $targetCurrency = $request->target_currency;
        $conversionDate = $request->conversion_date ?? $transaction->transaction_date;

        $exchangeRate = $this->getExchangeRate($transaction->currency, $targetCurrency, $conversionDate);
        $convertedAmount = $transaction->amount * $exchangeRate;

        return response()->json([
            'data' => [
                'transaction' => $transaction,
                'original_currency' => $transaction->currency,
                'target_currency' => $targetCurrency,
                'exchange_rate' => $exchangeRate,
                'conversion_date' => $conversionDate,
                'original_amount' => $transaction->amount,
                'converted_amount' => $convertedAmount
            ]
        ], 200);
    }

    /**
     * Bulk import bank transactions.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function bulkImport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bank_account_id' => 'required|exists:BankAccount,bank_id',
            'transactions' => 'required|array|min:1',
            'transactions.*.transaction_date' => 'required|date',
            'transactions.*.amount' => 'required|numeric',
            'transactions.*.transaction_type' => 'required|in:deposit,withdrawal,transfer,fee,interest,adjustment',
            'transactions.*.description' => 'required|string',
            'transactions.*.reference_number' => 'nullable|string',
            'update_bank_balance' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $bankAccount = BankAccount::findOrFail($request->bank_account_id);
        $baseCurrency = config('app.base_currency', 'USD');
        $importedTransactions = [];
        $errors = [];
        
        DB::beginTransaction();
        
        try {
            foreach ($request->transactions as $index => $transactionData) {
                try {
                    $currency = $transactionData['currency'] ?? $bankAccount->currency;
                    $exchangeRate = $this->getExchangeRate($currency, $baseCurrency, $transactionData['transaction_date']);
                    $baseCurrencyAmount = $transactionData['amount'] * $exchangeRate;
                    
                    $transaction = BankTransaction::create([
                        'bank_account_id' => $request->bank_account_id,
                        'transaction_type' => $transactionData['transaction_type'],
                        'transaction_date' => $transactionData['transaction_date'],
                        'amount' => $transactionData['amount'],
                        'currency' => $currency,
                        'base_currency_amount' => $baseCurrencyAmount,
                        'exchange_rate' => $exchangeRate,
                        'description' => $transactionData['description'],
                        'reference_number' => $transactionData['reference_number'] ?? null,
                        'counterparty_name' => $transactionData['counterparty_name'] ?? null,
                        'category' => $transactionData['category'] ?? null,
                        'status' => $transactionData['status'] ?? 'cleared'
                    ]);
                    
                    $importedTransactions[] = $transaction;
                    
                } catch (\Exception $e) {
                    $errors[] = [
                        'row' => $index + 1,
                        'error' => $e->getMessage(),
                        'data' => $transactionData
                    ];
                }
            }
            
            // Update bank account balance if requested
            if ($request->boolean('update_bank_balance') && !empty($importedTransactions)) {
                $this->updateBankAccountBalanceFromTransactions($bankAccount, $importedTransactions);
            }
            
            DB::commit();

            return response()->json([
                'data' => $importedTransactions,
                'summary' => [
                    'total_rows' => count($request->transactions),
                    'imported_count' => count($importedTransactions),
                    'error_count' => count($errors),
                    'bank_account' => $bankAccount->bank_name . ' - ' . $bankAccount->account_name
                ],
                'errors' => $errors,
                'message' => count($importedTransactions) . ' transactions imported successfully'
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to import transactions: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get available currencies from bank transactions.
     *
     * @return \Illuminate\Http\Response
     */
    public function getAvailableCurrencies()
    {
        $currencies = BankTransaction::distinct()
            ->pluck('currency')
            ->filter()
            ->sort()
            ->values();
            
        return response()->json(['data' => $currencies], 200);
    }

    /**
     * Create counterpart transaction for transfers.
     *
     * @param BankTransaction $transaction
     * @param Request $request
     * @return void
     */
    private function createTransferCounterpart($transaction, $request)
    {
        $toBankAccount = BankAccount::findOrFail($request->transfer_to_bank_id);
        $baseCurrency = config('app.base_currency', 'USD');
        
        // Calculate amount in destination bank currency
        $destinationCurrency = $toBankAccount->currency;
        $rate = $this->getExchangeRate($transaction->currency, $destinationCurrency, $transaction->transaction_date);
        $destinationAmount = $transaction->amount * $rate;
        
        $exchangeRateToBase = $this->getExchangeRate($destinationCurrency, $baseCurrency, $transaction->transaction_date);
        $baseCurrencyAmount = $destinationAmount * $exchangeRateToBase;
        
        $counterpartTransaction = BankTransaction::create([
            'bank_account_id' => $request->transfer_to_bank_id,
            'transaction_type' => 'deposit', // Transfer in becomes deposit
            'transaction_date' => $transaction->transaction_date,
            'amount' => $destinationAmount,
            'currency' => $destinationCurrency,
            'base_currency_amount' => $baseCurrencyAmount,
            'exchange_rate' => $exchangeRateToBase,
            'description' => 'Transfer from ' . $transaction->bankAccount->account_name . ' - ' . $transaction->description,
            'reference_number' => $transaction->reference_number,
            'status' => $transaction->status,
            'transfer_transaction_id' => $transaction->transaction_id
        ]);
        
        // Update original transaction with counterpart reference
        $transaction->update(['transfer_transaction_id' => $counterpartTransaction->transaction_id]);
    }

    /**
     * Update bank account balance.
     *
     * @param BankTransaction $transaction
     * @return void
     */
    private function updateBankAccountBalance($transaction)
    {
        $bankAccount = $transaction->bankAccount;
        $multiplier = in_array($transaction->transaction_type, ['deposit', 'interest']) ? 1 : -1;
        
        $balanceChange = $transaction->amount * $multiplier;
        $baseCurrencyBalanceChange = $transaction->base_currency_amount * $multiplier;
        
        $bankAccount->increment('current_balance', $balanceChange);
        $bankAccount->increment('base_currency_balance', $baseCurrencyBalanceChange);
    }

    /**
     * Reverse bank account balance.
     *
     * @param BankTransaction $transaction
     * @return void
     */
    private function reverseBankAccountBalance($transaction)
    {
        $bankAccount = $transaction->bankAccount;
        $multiplier = in_array($transaction->transaction_type, ['deposit', 'interest']) ? -1 : 1;
        
        $balanceChange = $transaction->amount * $multiplier;
        $baseCurrencyBalanceChange = $transaction->base_currency_amount * $multiplier;
        
        $bankAccount->increment('current_balance', $balanceChange);
        $bankAccount->increment('base_currency_balance', $baseCurrencyBalanceChange);
    }

    /**
     * Update bank account balance from multiple transactions.
     *
     * @param BankAccount $bankAccount
     * @param array $transactions
     * @return void
     */
    private function updateBankAccountBalanceFromTransactions($bankAccount, $transactions)
    {
        $totalBalanceChange = 0;
        $totalBaseCurrencyChange = 0;
        
        foreach ($transactions as $transaction) {
            $multiplier = in_array($transaction->transaction_type, ['deposit', 'interest']) ? 1 : -1;
            $totalBalanceChange += $transaction->amount * $multiplier;
            $totalBaseCurrencyChange += $transaction->base_currency_amount * $multiplier;
        }
        
        $bankAccount->increment('current_balance', $totalBalanceChange);
        $bankAccount->increment('base_currency_balance', $totalBaseCurrencyChange);
    }

    /**
     * Create journal entry for transaction.
     *
     * @param BankTransaction $transaction
     * @param Request $request
     * @return void
     */
    private function createTransactionJournalEntry($transaction, $request)
    {
        if (!$request->has('contra_account_id')) {
            throw new \Exception('Contra account ID is required for journal entry creation');
        }
        
        $journalEntry = JournalEntry::create([
            'journal_number' => 'BANK-' . $transaction->transaction_id . '-' . date('YmdHis'),
            'entry_date' => $transaction->transaction_date,
            'reference_type' => 'BankTransaction',
            'reference_id' => $transaction->transaction_id,
            'description' => $transaction->description,
            'period_id' => $this->getCurrentPeriodId(),
            'status' => 'Posted'
        ]);
        
        $bankAccountId = $transaction->bankAccount->gl_account_id;
        $contraAccountId = $request->contra_account_id;
        
        if (in_array($transaction->transaction_type, ['deposit', 'interest'])) {
            // Debit Bank Account
            JournalEntryLine::create([
                'journal_id' => $journalEntry->journal_id,
                'account_id' => $bankAccountId,
                'debit_amount' => $transaction->base_currency_amount,
                'credit_amount' => 0,
                'description' => $transaction->description,
                'currency' => $transaction->currency,
                'foreign_amount' => $transaction->currency !== config('app.base_currency', 'USD') ? $transaction->amount : null
            ]);
            
            // Credit Contra Account
            JournalEntryLine::create([
                'journal_id' => $journalEntry->journal_id,
                'account_id' => $contraAccountId,
                'debit_amount' => 0,
                'credit_amount' => $transaction->base_currency_amount,
                'description' => $transaction->description,
                'currency' => $transaction->currency,
                'foreign_amount' => $transaction->currency !== config('app.base_currency', 'USD') ? $transaction->amount : null
            ]);
        } else {
            // Credit Bank Account
            JournalEntryLine::create([
                'journal_id' => $journalEntry->journal_id,
                'account_id' => $bankAccountId,
                'debit_amount' => 0,
                'credit_amount' => $transaction->base_currency_amount,
                'description' => $transaction->description,
                'currency' => $transaction->currency,
                'foreign_amount' => $transaction->currency !== config('app.base_currency', 'USD') ? $transaction->amount : null
            ]);
            
            // Debit Contra Account
            JournalEntryLine::create([
                'journal_id' => $journalEntry->journal_id,
                'account_id' => $contraAccountId,
                'debit_amount' => $transaction->base_currency_amount,
                'credit_amount' => 0,
                'description' => $transaction->description,
                'currency' => $transaction->currency,
                'foreign_amount' => $transaction->currency !== config('app.base_currency', 'USD') ? $transaction->amount : null
            ]);
        }
        
        // Link journal entry to transaction
        $transaction->update(['journal_entry_id' => $journalEntry->journal_id]);
    }

    /**
     * Group transactions by bank account.
     *
     * @param \Illuminate\Support\Collection $transactions
     * @param string $reportCurrency
     * @return \Illuminate\Support\Collection
     */
    private function groupByBank($transactions, $reportCurrency)
    {
        return $transactions->groupBy('bank_account_id')->map(function($group, $bankId) use ($reportCurrency) {
            $bankAccount = $group->first()->bankAccount;
            $total = $this->calculateTotalInCurrency($group, $reportCurrency);
            
            return [
                'bank_id' => $bankId,
                'bank_name' => $bankAccount->bank_name,
                'account_name' => $bankAccount->account_name,
                'account_currency' => $bankAccount->currency,
                'transaction_count' => $group->count(),
                'total_amount' => $total,
                'currency' => $reportCurrency
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
            $originalTotal = $group->sum('amount');
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
     * Group transactions by transaction type.
     *
     * @param \Illuminate\Support\Collection $transactions
     * @param string $reportCurrency
     * @return \Illuminate\Support\Collection
     */
    private function groupByTransactionType($transactions, $reportCurrency)
    {
        return $transactions->groupBy('transaction_type')->map(function($group, $type) use ($reportCurrency) {
            $total = $this->calculateTotalInCurrency($group, $reportCurrency);
            
            return [
                'transaction_type' => $type,
                'transaction_count' => $group->count(),
                'total_amount' => $total,
                'currency' => $reportCurrency,
                'average_amount' => $group->count() > 0 ? $total / $group->count() : 0
            ];
        });
    }

    /**
     * Group transactions by category.
     *
     * @param \Illuminate\Support\Collection $transactions
     * @param string $reportCurrency
     * @return \Illuminate\Support\Collection
     */
    private function groupByCategory($transactions, $reportCurrency)
    {
        return $transactions->groupBy('category')->map(function($group, $category) use ($reportCurrency) {
            $total = $this->calculateTotalInCurrency($group, $reportCurrency);
            
            return [
                'category' => $category ?? 'Uncategorized',
                'transaction_count' => $group->count(),
                'total_amount' => $total,
                'currency' => $reportCurrency
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
                'currency' => $reportCurrency
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
            $total += $transaction->amount * $rate;
        }
        
        return $total;
    }

    /**
     * Get currency summary for a transaction.
     *
     * @param BankTransaction $transaction
     * @return array
     */
    private function getTransactionCurrencySummary($transaction)
    {
        $baseCurrency = config('app.base_currency', 'USD');
        
        return [
            'transaction_currency' => $transaction->currency,
            'base_currency' => $baseCurrency,
            'exchange_rate' => $transaction->exchange_rate,
            'amounts' => [
                'original' => [
                    'currency' => $transaction->currency,
                    'amount' => $transaction->amount
                ],
                'base_currency' => [
                    'currency' => $baseCurrency,
                    'amount' => $transaction->base_currency_amount
                ]
            ],
            'bank_account' => [
                'bank_name' => $transaction->bankAccount->bank_name,
                'account_name' => $transaction->bankAccount->account_name,
                'account_currency' => $transaction->bankAccount->currency
            ]
        ];
    }

    /**
     * Get current accounting period ID.
     *
     * @return int
     */
    private function getCurrentPeriodId()
    {
        $currentPeriod = DB::table('AccountingPeriod')
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->where('status', 'Open')
            ->first();
        
        if (!$currentPeriod) {
            throw new \Exception('No active accounting period found for the current date');
        }
        
        return $currentPeriod->period_id;
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