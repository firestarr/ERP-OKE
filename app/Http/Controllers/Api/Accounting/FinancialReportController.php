<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\ChartOfAccount;
use App\Models\Accounting\AccountingPeriod;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Accounting\CustomerReceivable;
use App\Models\Accounting\VendorPayable;
use App\Models\Accounting\ExchangeRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinancialReportController extends Controller
{
    /**
     * Generate trial balance report with multi-currency support.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function trialBalance(Request $request)
    {
        // Validate request parameters
        $request->validate([
            'period_id' => 'required|exists:AccountingPeriod,period_id',
            'include_zero_balances' => 'boolean',
            'currency' => 'nullable|string|size:3',
            'show_foreign_currency' => 'boolean',
            'as_of_date' => 'nullable|date'
        ]);
        
        $period = AccountingPeriod::findOrFail($request->period_id);
        $baseCurrency = config('app.base_currency', 'USD');
        $reportCurrency = $request->input('currency', $baseCurrency);
        $asOfDate = $request->input('as_of_date', $period->end_date);
        $showForeignCurrency = $request->boolean('show_foreign_currency', false);
        
        // Get all journal entries for the period that are posted
        $journalEntries = JournalEntry::where('period_id', $period->period_id)
            ->where('status', 'Posted')
            ->where('entry_date', '<=', $asOfDate)
            ->pluck('journal_id');
        
        // Calculate debits and credits for each account
        $accounts = DB::table('ChartOfAccount')
            ->leftJoin('JournalEntryLine', 'ChartOfAccount.account_id', '=', 'JournalEntryLine.account_id')
            ->leftJoin('JournalEntry', 'JournalEntryLine.journal_id', '=', 'JournalEntry.journal_id')
            ->select(
                'ChartOfAccount.account_id',
                'ChartOfAccount.account_code',
                'ChartOfAccount.name',
                'ChartOfAccount.account_type',
                DB::raw('SUM(IFNULL(JournalEntryLine.debit_amount, 0)) as total_debit'),
                DB::raw('SUM(IFNULL(JournalEntryLine.credit_amount, 0)) as total_credit')
            )
            ->whereIn('JournalEntry.journal_id', $journalEntries)
            ->where('JournalEntry.status', 'Posted')
            ->groupBy(
                'ChartOfAccount.account_id',
                'ChartOfAccount.account_code',
                'ChartOfAccount.name',
                'ChartOfAccount.account_type'
            );
        
        // Exclude accounts with zero balances if requested
        if (!$request->input('include_zero_balances', true)) {
            $accounts->havingRaw('SUM(IFNULL(JournalEntryLine.debit_amount, 0)) > 0 OR SUM(IFNULL(JournalEntryLine.credit_amount, 0)) > 0');
        }
        
        $accounts = $accounts->orderBy('ChartOfAccount.account_code')->get();
        
        // Convert to report currency if different from base currency
        $exchangeRate = $this->getExchangeRate($baseCurrency, $reportCurrency, $asOfDate);
        
        $trialBalanceData = $accounts->map(function($account) use ($exchangeRate, $reportCurrency, $baseCurrency, $showForeignCurrency, $period, $asOfDate) {
            $debitBalance = $account->total_debit;
            $creditBalance = $account->total_credit;
            $netBalance = $debitBalance - $creditBalance;
            
            // Convert to report currency
            $convertedDebit = $debitBalance * $exchangeRate;
            $convertedCredit = $creditBalance * $exchangeRate;
            $convertedNet = $netBalance * $exchangeRate;
            
            $result = [
                'account_id' => $account->account_id,
                'account_code' => $account->account_code,
                'account_name' => $account->name,
                'account_type' => $account->account_type,
                'debit_balance' => $convertedDebit,
                'credit_balance' => $convertedCredit,
                'net_balance' => $convertedNet,
                'currency' => $reportCurrency
            ];
            
            // Add foreign currency details if requested
            if ($showForeignCurrency && $reportCurrency !== $baseCurrency) {
                $result['base_currency_amounts'] = [
                    'currency' => $baseCurrency,
                    'debit_balance' => $debitBalance,
                    'credit_balance' => $creditBalance,
                    'net_balance' => $netBalance
                ];
                $result['exchange_rate'] = $exchangeRate;
            }
            
            // Add foreign currency breakdown
            if ($showForeignCurrency) {
                $result['foreign_currency_breakdown'] = $this->getForeignCurrencyBreakdown($account->account_id, $period->period_id, $asOfDate);
            }
            
            return $result;
        });
        
        // Calculate totals
        $totalDebits = $trialBalanceData->sum('debit_balance');
        $totalCredits = $trialBalanceData->sum('credit_balance');
        
        return response()->json([
            'data' => $trialBalanceData,
            'summary' => [
                'period' => $period,
                'as_of_date' => $asOfDate,
                'currency' => $reportCurrency,
                'exchange_rate' => $exchangeRate,
                'total_debits' => $totalDebits,
                'total_credits' => $totalCredits,
                'difference' => $totalDebits - $totalCredits,
                'is_balanced' => abs($totalDebits - $totalCredits) < 0.01
            ]
        ], 200);
    }

    /**
     * Generate income statement with multi-currency support.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function incomeStatement(Request $request)
    {
        $request->validate([
            'period_id' => 'required|exists:AccountingPeriod,period_id',
            'currency' => 'nullable|string|size:3',
            'compare_previous_period' => 'boolean'
        ]);
        
        $period = AccountingPeriod::findOrFail($request->period_id);
        $baseCurrency = config('app.base_currency', 'USD');
        $reportCurrency = $request->input('currency', $baseCurrency);
        $exchangeRate = $this->getExchangeRate($baseCurrency, $reportCurrency, $period->end_date);
        
        // Get revenue accounts (Income type)
        $revenueAccounts = $this->getAccountBalances(['Income'], $period->period_id, $exchangeRate);
        
        // Get expense accounts (Expense type)
        $expenseAccounts = $this->getAccountBalances(['Expense'], $period->period_id, $exchangeRate);
        
        // Calculate totals
        $totalRevenue = $revenueAccounts->sum('credit_balance');
        $totalExpenses = $expenseAccounts->sum('debit_balance');
        $netIncome = $totalRevenue - $totalExpenses;
        
        $incomeStatement = [
            'revenue' => [
                'accounts' => $revenueAccounts,
                'total' => $totalRevenue
            ],
            'expenses' => [
                'accounts' => $expenseAccounts,
                'total' => $totalExpenses
            ],
            'net_income' => $netIncome
        ];
        
        // Add previous period comparison if requested
        if ($request->boolean('compare_previous_period')) {
            $previousPeriod = $this->getPreviousPeriod($period);
            if ($previousPeriod) {
                $prevExchangeRate = $this->getExchangeRate($baseCurrency, $reportCurrency, $previousPeriod->end_date);
                
                $prevRevenueAccounts = $this->getAccountBalances(['Income'], $previousPeriod->period_id, $prevExchangeRate);
                $prevExpenseAccounts = $this->getAccountBalances(['Expense'], $previousPeriod->period_id, $prevExchangeRate);
                
                $prevTotalRevenue = $prevRevenueAccounts->sum('credit_balance');
                $prevTotalExpenses = $prevExpenseAccounts->sum('debit_balance');
                $prevNetIncome = $prevTotalRevenue - $prevTotalExpenses;
                
                $incomeStatement['comparison'] = [
                    'previous_period' => $previousPeriod,
                    'previous_revenue' => $prevTotalRevenue,
                    'previous_expenses' => $prevTotalExpenses,
                    'previous_net_income' => $prevNetIncome,
                    'revenue_variance' => $totalRevenue - $prevTotalRevenue,
                    'expense_variance' => $totalExpenses - $prevTotalExpenses,
                    'net_income_variance' => $netIncome - $prevNetIncome,
                    'revenue_variance_percent' => $prevTotalRevenue != 0 ? (($totalRevenue - $prevTotalRevenue) / $prevTotalRevenue) * 100 : 0,
                    'expense_variance_percent' => $prevTotalExpenses != 0 ? (($totalExpenses - $prevTotalExpenses) / $prevTotalExpenses) * 100 : 0
                ];
            }
        }
        
        return response()->json([
            'data' => $incomeStatement,
            'summary' => [
                'period' => $period,
                'currency' => $reportCurrency,
                'exchange_rate' => $exchangeRate,
                'total_revenue' => $totalRevenue,
                'total_expenses' => $totalExpenses,
                'net_income' => $netIncome,
                'profit_margin' => $totalRevenue != 0 ? ($netIncome / $totalRevenue) * 100 : 0
            ]
        ], 200);
    }

    /**
     * Generate balance sheet with multi-currency support.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function balanceSheet(Request $request)
    {
        $request->validate([
            'as_of_date' => 'required|date',
            'currency' => 'nullable|string|size:3'
        ]);
        
        $asOfDate = $request->as_of_date;
        $baseCurrency = config('app.base_currency', 'USD');
        $reportCurrency = $request->input('currency', $baseCurrency);
        $exchangeRate = $this->getExchangeRate($baseCurrency, $reportCurrency, $asOfDate);
        
        // Get period for the date
        $period = AccountingPeriod::where('start_date', '<=', $asOfDate)
            ->where('end_date', '>=', $asOfDate)
            ->first();
            
        if (!$period) {
            return response()->json(['message' => 'No accounting period found for the specified date'], 422);
        }
        
        // Get account balances by type
        $assets = $this->getAccountBalances(['Asset'], $period->period_id, $exchangeRate, $asOfDate);
        $liabilities = $this->getAccountBalances(['Liability'], $period->period_id, $exchangeRate, $asOfDate);
        $equity = $this->getAccountBalances(['Equity'], $period->period_id, $exchangeRate, $asOfDate);
        
        // Calculate totals
        $totalAssets = $assets->sum('debit_balance') - $assets->sum('credit_balance');
        $totalLiabilities = $liabilities->sum('credit_balance') - $liabilities->sum('debit_balance');
        $totalEquity = $equity->sum('credit_balance') - $equity->sum('debit_balance');
        
        // Add retained earnings
        $retainedEarnings = $this->calculateRetainedEarnings($asOfDate, $exchangeRate);
        $totalEquity += $retainedEarnings;
        
        $balanceSheet = [
            'assets' => [
                'accounts' => $assets,
                'total' => $totalAssets
            ],
            'liabilities' => [
                'accounts' => $liabilities,
                'total' => $totalLiabilities
            ],
            'equity' => [
                'accounts' => $equity,
                'retained_earnings' => $retainedEarnings,
                'total' => $totalEquity
            ],
            'total_liabilities_equity' => $totalLiabilities + $totalEquity
        ];
        
        return response()->json([
            'data' => $balanceSheet,
            'summary' => [
                'as_of_date' => $asOfDate,
                'currency' => $reportCurrency,
                'exchange_rate' => $exchangeRate,
                'total_assets' => $totalAssets,
                'total_liabilities' => $totalLiabilities,
                'total_equity' => $totalEquity,
                'is_balanced' => abs($totalAssets - ($totalLiabilities + $totalEquity)) < 0.01,
                'difference' => $totalAssets - ($totalLiabilities + $totalEquity)
            ]
        ], 200);
    }

    /**
     * Generate cash flow statement with multi-currency support.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function cashFlow(Request $request)
    {
        $request->validate([
            'period_id' => 'required|exists:AccountingPeriod,period_id',
            'currency' => 'nullable|string|size:3',
            'method' => 'nullable|in:direct,indirect'
        ]);
        
        $period = AccountingPeriod::findOrFail($request->period_id);
        $baseCurrency = config('app.base_currency', 'USD');
        $reportCurrency = $request->input('currency', $baseCurrency);
        $method = $request->input('method', 'indirect');
        $exchangeRate = $this->getExchangeRate($baseCurrency, $reportCurrency, $period->end_date);
        
        if ($method === 'indirect') {
            return $this->generateIndirectCashFlow($period, $reportCurrency, $exchangeRate);
        } else {
            return $this->generateDirectCashFlow($period, $reportCurrency, $exchangeRate);
        }
    }

    /**
     * Generate accounts receivable report with multi-currency support.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function accountsReceivable(Request $request)
    {
        $request->validate([
            'as_of_date' => 'nullable|date',
            'currency' => 'nullable|string|size:3',
            'group_by_currency' => 'boolean'
        ]);
        
        $asOfDate = $request->input('as_of_date', now()->toDateString());
        $reportCurrency = $request->input('currency', config('app.base_currency', 'USD'));
        $groupByCurrency = $request->boolean('group_by_currency', false);
        
        $query = CustomerReceivable::with('customer')
            ->where('status', '!=', 'Paid');
        
        $receivables = $query->get();
        
        if ($groupByCurrency) {
            $groupedData = $receivables->groupBy('currency_code')->map(function($group, $currency) use ($asOfDate) {
                return [
                    'currency' => $currency,
                    'count' => $group->count(),
                    'total_amount' => $group->sum('amount'),
                    'total_balance' => $group->sum('balance'),
                    'base_currency_total' => $group->sum('base_currency_amount'),
                    'base_currency_balance' => $group->sum('base_currency_balance'),
                    'aging' => $this->calculateAgingBuckets($group, $asOfDate)
                ];
            });
            
            return response()->json([
                'data' => $groupedData,
                'summary' => [
                    'as_of_date' => $asOfDate,
                    'base_currency' => config('app.base_currency', 'USD'),
                    'total_receivables' => $receivables->count(),
                    'total_base_currency_balance' => $receivables->sum('base_currency_balance'),
                    'currencies_involved' => $groupedData->keys()
                ]
            ], 200);
        } else {
            // Convert all to single currency
            $convertedReceivables = $receivables->map(function($receivable) use ($reportCurrency, $asOfDate) {
                $amounts = $receivable->getAmountsInCurrency($reportCurrency, $asOfDate);
                return [
                    'customer_name' => $receivable->customer->name,
                    'original_currency' => $receivable->currency_code,
                    'original_amount' => $receivable->amount,
                    'original_balance' => $receivable->balance,
                    'converted_amount' => $amounts['amount'],
                    'converted_balance' => $amounts['balance'],
                    'due_date' => $receivable->due_date,
                    'days_outstanding' => now()->diffInDays($receivable->due_date, false)
                ];
            });
            
            return response()->json([
                'data' => $convertedReceivables,
                'summary' => [
                    'as_of_date' => $asOfDate,
                    'currency' => $reportCurrency,
                    'total_receivables' => $convertedReceivables->count(),
                    'total_amount' => $convertedReceivables->sum('converted_amount'),
                    'total_balance' => $convertedReceivables->sum('converted_balance')
                ]
            ], 200);
        }
    }

    /**
     * Generate accounts payable report with multi-currency support.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function accountsPayable(Request $request)
    {
        $request->validate([
            'as_of_date' => 'nullable|date',
            'currency' => 'nullable|string|size:3',
            'group_by_currency' => 'boolean'
        ]);
        
        $asOfDate = $request->input('as_of_date', now()->toDateString());
        $reportCurrency = $request->input('currency', config('app.base_currency', 'USD'));
        $groupByCurrency = $request->boolean('group_by_currency', false);
        
        $query = VendorPayable::with('vendor')
            ->where('status', '!=', 'Paid');
        
        $payables = $query->get();
        
        if ($groupByCurrency) {
            $groupedData = $payables->groupBy('currency_code')->map(function($group, $currency) use ($asOfDate) {
                return [
                    'currency' => $currency,
                    'count' => $group->count(),
                    'total_amount' => $group->sum('amount'),
                    'total_balance' => $group->sum('balance'),
                    'base_currency_total' => $group->sum('base_currency_amount'),
                    'base_currency_balance' => $group->sum('base_currency_balance'),
                    'aging' => $this->calculateAgingBuckets($group, $asOfDate),
                    'overdue_amount' => $group->where('due_date', '<', $asOfDate)->sum('balance')
                ];
            });
            
            return response()->json([
                'data' => $groupedData,
                'summary' => [
                    'as_of_date' => $asOfDate,
                    'base_currency' => config('app.base_currency', 'USD'),
                    'total_payables' => $payables->count(),
                    'total_base_currency_balance' => $payables->sum('base_currency_balance'),
                    'currencies_involved' => $groupedData->keys(),
                    'total_overdue' => $payables->where('due_date', '<', $asOfDate)->sum('base_currency_balance')
                ]
            ], 200);
        } else {
            // Convert all to single currency
            $convertedPayables = $payables->map(function($payable) use ($reportCurrency, $asOfDate) {
                $amounts = $payable->getAmountsInCurrency($reportCurrency, $asOfDate);
                return [
                    'vendor_name' => $payable->vendor->name,
                    'original_currency' => $payable->currency_code,
                    'original_amount' => $payable->amount,
                    'original_balance' => $payable->balance,
                    'converted_amount' => $amounts['amount'],
                    'converted_balance' => $amounts['balance'],
                    'due_date' => $payable->due_date,
                    'days_outstanding' => now()->diffInDays($payable->due_date, false),
                    'is_overdue' => $payable->due_date < $asOfDate
                ];
            });
            
            return response()->json([
                'data' => $convertedPayables,
                'summary' => [
                    'as_of_date' => $asOfDate,
                    'currency' => $reportCurrency,
                    'total_payables' => $convertedPayables->count(),
                    'total_amount' => $convertedPayables->sum('converted_amount'),
                    'total_balance' => $convertedPayables->sum('converted_balance'),
                    'overdue_count' => $convertedPayables->where('is_overdue', true)->count(),
                    'overdue_amount' => $convertedPayables->where('is_overdue', true)->sum('converted_balance')
                ]
            ], 200);
        }
    }

    /**
     * Generate currency exposure report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function currencyExposure(Request $request)
    {
        $request->validate([
            'as_of_date' => 'nullable|date'
        ]);
        
        $asOfDate = $request->input('as_of_date', now()->toDateString());
        $baseCurrency = config('app.base_currency', 'USD');
        
        // Get receivables by currency
        $receivablesByCurrency = CustomerReceivable::where('status', '!=', 'Paid')
            ->where('currency_code', '!=', $baseCurrency)
            ->groupBy('currency_code')
            ->selectRaw('currency_code, SUM(balance) as total_balance, SUM(base_currency_balance) as base_total, COUNT(*) as count')
            ->get();
        
        // Get payables by currency
        $payablesByCurrency = VendorPayable::where('status', '!=', 'Paid')
            ->where('currency_code', '!=', $baseCurrency)
            ->groupBy('currency_code')
            ->selectRaw('currency_code, SUM(balance) as total_balance, SUM(base_currency_balance) as base_total, COUNT(*) as count')
            ->get();
        
        // Combine exposure data
        $allCurrencies = $receivablesByCurrency->pluck('currency_code')
            ->merge($payablesByCurrency->pluck('currency_code'))
            ->unique();
        
        $exposureData = $allCurrencies->map(function($currency) use ($receivablesByCurrency, $payablesByCurrency, $baseCurrency, $asOfDate) {
            $receivables = $receivablesByCurrency->where('currency_code', $currency)->first();
            $payables = $payablesByCurrency->where('currency_code', $currency)->first();
            
            $receivableAmount = $receivables ? $receivables->total_balance : 0;
            $payableAmount = $payables ? $payables->total_balance : 0;
            $netExposure = $receivableAmount - $payableAmount;
            
            $receivableBase = $receivables ? $receivables->base_total : 0;
            $payableBase = $payables ? $payables->base_total : 0;
            $netExposureBase = $receivableBase - $payableBase;
            
            $currentRate = $this->getExchangeRate($currency, $baseCurrency, $asOfDate);
            
            return [
                'currency' => $currency,
                'receivables' => [
                    'amount' => $receivableAmount,
                    'base_amount' => $receivableBase,
                    'count' => $receivables ? $receivables->count : 0
                ],
                'payables' => [
                    'amount' => $payableAmount,
                    'base_amount' => $payableBase,
                    'count' => $payables ? $payables->count : 0
                ],
                'net_exposure' => [
                    'amount' => $netExposure,
                    'base_amount' => $netExposureBase,
                    'percentage_of_total' => 0 // Will be calculated after all currencies
                ],
                'current_exchange_rate' => $currentRate,
                'risk_level' => $this->calculateRiskLevel($netExposureBase)
            ];
        });
        
        // Calculate percentages
        $totalExposure = $exposureData->sum('net_exposure.base_amount');
        $exposureData = $exposureData->map(function($item) use ($totalExposure) {
            if ($totalExposure != 0) {
                $item['net_exposure']['percentage_of_total'] = ($item['net_exposure']['base_amount'] / $totalExposure) * 100;
            }
            return $item;
        });
        
        return response()->json([
            'data' => $exposureData,
            'summary' => [
                'as_of_date' => $asOfDate,
                'base_currency' => $baseCurrency,
                'total_currencies' => $exposureData->count(),
                'total_net_exposure' => $totalExposure,
                'high_risk_currencies' => $exposureData->where('risk_level', 'high')->count(),
                'total_receivables_exposure' => $exposureData->sum('receivables.base_amount'),
                'total_payables_exposure' => $exposureData->sum('payables.base_amount')
            ]
        ], 200);
    }

    /**
     * Get account balances for specific account types.
     *
     * @param array $accountTypes
     * @param int $periodId
     * @param float $exchangeRate
     * @param string|null $asOfDate
     * @return \Illuminate\Support\Collection
     */
    private function getAccountBalances($accountTypes, $periodId, $exchangeRate, $asOfDate = null)
    {
        $query = DB::table('ChartOfAccount')
            ->leftJoin('JournalEntryLine', 'ChartOfAccount.account_id', '=', 'JournalEntryLine.account_id')
            ->leftJoin('JournalEntry', 'JournalEntryLine.journal_id', '=', 'JournalEntry.journal_id')
            ->select(
                'ChartOfAccount.account_id',
                'ChartOfAccount.account_code',
                'ChartOfAccount.name',
                'ChartOfAccount.account_type',
                DB::raw('SUM(IFNULL(JournalEntryLine.debit_amount, 0)) as debit_balance'),
                DB::raw('SUM(IFNULL(JournalEntryLine.credit_amount, 0)) as credit_balance')
            )
            ->where('JournalEntry.period_id', $periodId)
            ->where('JournalEntry.status', 'Posted')
            ->whereIn('ChartOfAccount.account_type', $accountTypes);
            
        if ($asOfDate) {
            $query->where('JournalEntry.entry_date', '<=', $asOfDate);
        }
        
        return $query->groupBy(
                'ChartOfAccount.account_id',
                'ChartOfAccount.account_code',
                'ChartOfAccount.name',
                'ChartOfAccount.account_type'
            )
            ->orderBy('ChartOfAccount.account_code')
            ->get()
            ->map(function($account) use ($exchangeRate) {
                return (object)[
                    'account_id' => $account->account_id,
                    'account_code' => $account->account_code,
                    'name' => $account->name,
                    'account_type' => $account->account_type,
                    'debit_balance' => $account->debit_balance * $exchangeRate,
                    'credit_balance' => $account->credit_balance * $exchangeRate
                ];
            });
    }

    /**
     * Get foreign currency breakdown for an account.
     *
     * @param int $accountId
     * @param int $periodId
     * @param string $asOfDate
     * @return array
     */
    private function getForeignCurrencyBreakdown($accountId, $periodId, $asOfDate)
    {
        $baseCurrency = config('app.base_currency', 'USD');
        
        $breakdown = DB::table('JournalEntryLine')
            ->join('JournalEntry', 'JournalEntryLine.journal_id', '=', 'JournalEntry.journal_id')
            ->where('JournalEntryLine.account_id', $accountId)
            ->where('JournalEntry.period_id', $periodId)
            ->where('JournalEntry.status', 'Posted')
            ->where('JournalEntry.entry_date', '<=', $asOfDate)
            ->whereNotNull('JournalEntryLine.currency')
            ->where('JournalEntryLine.currency', '!=', $baseCurrency)
            ->groupBy('JournalEntryLine.currency')
            ->selectRaw('
                JournalEntryLine.currency,
                SUM(IFNULL(JournalEntryLine.foreign_amount, 0)) as total_foreign_amount,
                SUM(IFNULL(JournalEntryLine.debit_amount, 0)) as total_debit,
                SUM(IFNULL(JournalEntryLine.credit_amount, 0)) as total_credit
            ')
            ->get();
            
        return $breakdown->toArray();
    }

    /**
     * Calculate retained earnings.
     *
     * @param string $asOfDate
     * @param float $exchangeRate
     * @return float
     */
    private function calculateRetainedEarnings($asOfDate, $exchangeRate)
    {
        // Get all income and expense account balances up to the date
        $income = DB::table('ChartOfAccount')
            ->leftJoin('JournalEntryLine', 'ChartOfAccount.account_id', '=', 'JournalEntryLine.account_id')
            ->leftJoin('JournalEntry', 'JournalEntryLine.journal_id', '=', 'JournalEntry.journal_id')
            ->where('ChartOfAccount.account_type', 'Income')
            ->where('JournalEntry.status', 'Posted')
            ->where('JournalEntry.entry_date', '<=', $asOfDate)
            ->sum('JournalEntryLine.credit_amount');
            
        $expenses = DB::table('ChartOfAccount')
            ->leftJoin('JournalEntryLine', 'ChartOfAccount.account_id', '=', 'JournalEntryLine.account_id')
            ->leftJoin('JournalEntry', 'JournalEntryLine.journal_id', '=', 'JournalEntry.journal_id')
            ->where('ChartOfAccount.account_type', 'Expense')
            ->where('JournalEntry.status', 'Posted')
            ->where('JournalEntry.entry_date', '<=', $asOfDate)
            ->sum('JournalEntryLine.debit_amount');
            
        return ($income - $expenses) * $exchangeRate;
    }

    /**
     * Calculate aging buckets for receivables/payables.
     *
     * @param \Illuminate\Support\Collection $items
     * @param string $asOfDate
     * @return array
     */
    private function calculateAgingBuckets($items, $asOfDate)
    {
        $buckets = [
            'current' => 0,
            '1-30_days' => 0,
            '31-60_days' => 0,
            '61-90_days' => 0,
            'over_90_days' => 0
        ];
        
        foreach ($items as $item) {
            $daysPastDue = now()->parse($asOfDate)->diffInDays($item->due_date, false);
            
            if ($daysPastDue >= 0) {
                $buckets['current'] += $item->balance;
            } elseif ($daysPastDue >= -30) {
                $buckets['1-30_days'] += $item->balance;
            } elseif ($daysPastDue >= -60) {
                $buckets['31-60_days'] += $item->balance;
            } elseif ($daysPastDue >= -90) {
                $buckets['61-90_days'] += $item->balance;
            } else {
                $buckets['over_90_days'] += $item->balance;
            }
        }
        
        return $buckets;
    }

    /**
     * Calculate risk level based on exposure amount.
     *
     * @param float $exposureAmount
     * @return string
     */
    private function calculateRiskLevel($exposureAmount)
    {
        $absExposure = abs($exposureAmount);
        
        if ($absExposure > 100000) {
            return 'high';
        } elseif ($absExposure > 50000) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Get previous accounting period.
     *
     * @param AccountingPeriod $period
     * @return AccountingPeriod|null
     */
    private function getPreviousPeriod($period)
    {
        return AccountingPeriod::where('end_date', '<', $period->start_date)
            ->orderBy('end_date', 'desc')
            ->first();
    }

    /**
     * Generate indirect cash flow statement.
     *
     * @param AccountingPeriod $period
     * @param string $reportCurrency
     * @param float $exchangeRate
     * @return \Illuminate\Http\Response
     */
    private function generateIndirectCashFlow($period, $reportCurrency, $exchangeRate)
    {
        // This is a simplified implementation
        // In a real system, you would need more detailed cash flow calculation
        
        return response()->json([
            'data' => [
                'operating_activities' => [],
                'investing_activities' => [],
                'financing_activities' => []
            ],
            'summary' => [
                'period' => $period,
                'currency' => $reportCurrency,
                'method' => 'indirect'
            ]
        ], 200);
    }

    /**
     * Generate direct cash flow statement.
     *
     * @param AccountingPeriod $period
     * @param string $reportCurrency
     * @param float $exchangeRate
     * @return \Illuminate\Http\Response
     */
    private function generateDirectCashFlow($period, $reportCurrency, $exchangeRate)
    {
        // This is a simplified implementation
        // In a real system, you would need more detailed cash flow calculation
        
        return response()->json([
            'data' => [
                'cash_receipts' => [],
                'cash_payments' => []
            ],
            'summary' => [
                'period' => $period,
                'currency' => $reportCurrency,
                'method' => 'direct'
            ]
        ], 200);
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