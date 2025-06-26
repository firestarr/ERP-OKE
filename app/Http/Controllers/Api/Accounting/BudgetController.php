<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Budget;
use App\Models\Accounting\ChartOfAccount;
use App\Models\Accounting\AccountingPeriod;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Accounting\ExchangeRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BudgetController extends Controller
{
    /**
     * Display a listing of budgets.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Budget::with(['chartOfAccount', 'accountingPeriod']);
        
        // Filter by account
        if ($request->has('account_id')) {
            $query->where('account_id', $request->account_id);
        }
        
        // Filter by period
        if ($request->has('period_id')) {
            $query->where('period_id', $request->period_id);
        }
        
        // Filter by currency
        if ($request->has('currency')) {
            $query->where('currency', $request->currency);
        }
        
        // Filter by department
        if ($request->has('department')) {
            $query->where('department', $request->department);
        }
        
        $budgets = $query->orderBy('period_id')
            ->orderBy('account_id')
            ->paginate($request->input('per_page', 15));
        
        // Add currency conversion if requested
        if ($request->filled('display_currency')) {
            $displayCurrency = $request->display_currency;
            $conversionDate = $request->input('conversion_date', now()->toDateString());
            
            $budgets->getCollection()->transform(function ($budget) use ($displayCurrency, $conversionDate) {
                if ($budget->currency !== $displayCurrency) {
                    $rate = $this->getExchangeRate($budget->currency, $displayCurrency, $conversionDate);
                    $budget->converted_amount = $budget->budgeted_amount * $rate;
                    $budget->display_currency = $displayCurrency;
                    $budget->exchange_rate = $rate;
                }
                return $budget;
            });
        }
        
        return response()->json($budgets, 200);
    }

    /**
     * Store a newly created budget in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_id' => 'required|exists:ChartOfAccount,account_id',
            'period_id' => 'required|exists:AccountingPeriod,period_id',
            'budgeted_amount' => 'required|numeric',
            'currency' => 'required|string|size:3',
            'department' => 'nullable|string|max:100',
            'budget_type' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'quarterly_breakdown' => 'nullable|array',
            'quarterly_breakdown.*.quarter' => 'required_with:quarterly_breakdown|in:Q1,Q2,Q3,Q4',
            'quarterly_breakdown.*.amount' => 'required_with:quarterly_breakdown|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Check if budget already exists for this account and period
        $exists = Budget::where('account_id', $request->account_id)
            ->where('period_id', $request->period_id)
            ->where('department', $request->department)
            ->exists();
        
        if ($exists) {
            return response()->json([
                'message' => 'A budget already exists for this account, period, and department'
            ], 422);
        }
        
        $baseCurrency = config('app.base_currency', 'USD');
        $currency = $request->currency;
        
        // Calculate base currency equivalent
        $period = AccountingPeriod::findOrFail($request->period_id);
        $exchangeRate = $this->getExchangeRate($currency, $baseCurrency, $period->start_date);
        $baseCurrencyAmount = $request->budgeted_amount * $exchangeRate;
        
        // Validate quarterly breakdown if provided
        if ($request->has('quarterly_breakdown')) {
            $quarterlyTotal = collect($request->quarterly_breakdown)->sum('amount');
            if (abs($quarterlyTotal - $request->budgeted_amount) > 0.01) {
                return response()->json([
                    'message' => 'Quarterly breakdown total must equal budgeted amount'
                ], 422);
            }
        }

        $budget = Budget::create([
            'account_id' => $request->account_id,
            'period_id' => $request->period_id,
            'budgeted_amount' => $request->budgeted_amount,
            'currency' => $currency,
            'base_currency_amount' => $baseCurrencyAmount,
            'exchange_rate' => $exchangeRate,
            'department' => $request->department,
            'budget_type' => $request->budget_type ?? 'operational',
            'notes' => $request->notes,
            'quarterly_breakdown' => $request->quarterly_breakdown ? json_encode($request->quarterly_breakdown) : null,
            'actual_amount' => 0,
            'variance' => $request->budgeted_amount
        ]);

        return response()->json([
            'data' => $budget->load(['chartOfAccount', 'accountingPeriod']), 
            'message' => 'Budget created successfully'
        ], 201);
    }

    /**
     * Display the specified budget.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $budget = Budget::with(['chartOfAccount', 'accountingPeriod'])->findOrFail($id);
        
        // Calculate actual amounts from journal entries
        $actualAmounts = $this->calculateActualAmounts($budget);
        $budget->actual_amount = $actualAmounts['total'];
        $budget->variance = $budget->budgeted_amount - $actualAmounts['total'];
        $budget->variance_percentage = $budget->budgeted_amount != 0 ? 
            ($budget->variance / $budget->budgeted_amount) * 100 : 0;
        
        // Add currency summary
        $currencySummary = $this->getBudgetCurrencySummary($budget);
        
        return response()->json([
            'data' => $budget,
            'actual_breakdown' => $actualAmounts['breakdown'],
            'currency_summary' => $currencySummary
        ], 200);
    }

    /**
     * Update the specified budget in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $budget = Budget::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'account_id' => 'exists:ChartOfAccount,account_id',
            'period_id' => 'exists:AccountingPeriod,period_id',
            'budgeted_amount' => 'numeric',
            'currency' => 'string|size:3',
            'department' => 'nullable|string|max:100',
            'budget_type' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'quarterly_breakdown' => 'nullable|array',
            'quarterly_breakdown.*.quarter' => 'required_with:quarterly_breakdown|in:Q1,Q2,Q3,Q4',
            'quarterly_breakdown.*.amount' => 'required_with:quarterly_breakdown|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $baseCurrency = config('app.base_currency', 'USD');
        
        // Recalculate base currency amount if currency or amount changed
        if ($request->has('budgeted_amount') || $request->has('currency')) {
            $amount = $request->budgeted_amount ?? $budget->budgeted_amount;
            $currency = $request->currency ?? $budget->currency;
            
            $period = $request->has('period_id') ? 
                AccountingPeriod::findOrFail($request->period_id) : 
                $budget->accountingPeriod;
                
            $exchangeRate = $this->getExchangeRate($currency, $baseCurrency, $period->start_date);
            $baseCurrencyAmount = $amount * $exchangeRate;
            
            $request->merge([
                'base_currency_amount' => $baseCurrencyAmount,
                'exchange_rate' => $exchangeRate,
                'variance' => $amount - $budget->actual_amount
            ]);
        }
        
        // Validate quarterly breakdown if provided
        if ($request->has('quarterly_breakdown')) {
            $budgetedAmount = $request->budgeted_amount ?? $budget->budgeted_amount;
            $quarterlyTotal = collect($request->quarterly_breakdown)->sum('amount');
            if (abs($quarterlyTotal - $budgetedAmount) > 0.01) {
                return response()->json([
                    'message' => 'Quarterly breakdown total must equal budgeted amount'
                ], 422);
            }
            $request->merge(['quarterly_breakdown' => json_encode($request->quarterly_breakdown)]);
        }

        $budget->update($request->only([
            'account_id', 'period_id', 'budgeted_amount', 'currency',
            'base_currency_amount', 'exchange_rate', 'department',
            'budget_type', 'notes', 'quarterly_breakdown', 'variance'
        ]));

        return response()->json([
            'data' => $budget->load(['chartOfAccount', 'accountingPeriod']), 
            'message' => 'Budget updated successfully'
        ], 200);
    }

    /**
     * Remove the specified budget from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $budget = Budget::findOrFail($id);
        $budget->delete();

        return response()->json(['message' => 'Budget deleted successfully'], 200);
    }

    /**
     * Generate variance report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function varianceReport(Request $request)
    {
        $request->validate([
            'period_id' => 'required|exists:AccountingPeriod,period_id',
            'currency' => 'nullable|string|size:3',
            'department' => 'nullable|string',
            'account_type' => 'nullable|string',
            'variance_threshold' => 'nullable|numeric'
        ]);
        
        $period = AccountingPeriod::findOrFail($request->period_id);
        $reportCurrency = $request->input('currency', config('app.base_currency', 'USD'));
        $baseCurrency = config('app.base_currency', 'USD');
        
        $query = Budget::with(['chartOfAccount', 'accountingPeriod'])
            ->where('period_id', $request->period_id);
        
        // Filter by department
        if ($request->filled('department')) {
            $query->where('department', $request->department);
        }
        
        // Filter by account type
        if ($request->filled('account_type')) {
            $query->whereHas('chartOfAccount', function($q) use ($request) {
                $q->where('account_type', $request->account_type);
            });
        }
        
        $budgets = $query->get();
        
        // Calculate actual amounts and variances
        $varianceData = $budgets->map(function($budget) use ($reportCurrency, $baseCurrency, $period) {
            // Get actual amounts from journal entries
            $actualAmounts = $this->calculateActualAmounts($budget);
            $actualTotal = $actualAmounts['total'];
            
            // Convert to report currency
            $exchangeRate = $this->getExchangeRate($budget->currency, $reportCurrency, $period->end_date);
            $budgetedInReportCurrency = $budget->budgeted_amount * $exchangeRate;
            $actualInReportCurrency = $actualTotal * $exchangeRate;
            
            $variance = $budgetedInReportCurrency - $actualInReportCurrency;
            $variancePercentage = $budgetedInReportCurrency != 0 ? 
                ($variance / $budgetedInReportCurrency) * 100 : 0;
            
            return [
                'budget_id' => $budget->budget_id,
                'account_code' => $budget->chartOfAccount->account_code,
                'account_name' => $budget->chartOfAccount->name,
                'account_type' => $budget->chartOfAccount->account_type,
                'department' => $budget->department,
                'original_currency' => $budget->currency,
                'report_currency' => $reportCurrency,
                'exchange_rate' => $exchangeRate,
                'budgeted_amount' => $budgetedInReportCurrency,
                'actual_amount' => $actualInReportCurrency,
                'variance' => $variance,
                'variance_percentage' => $variancePercentage,
                'variance_type' => $variance > 0 ? 'favorable' : 'unfavorable',
                'status' => $this->getVarianceStatus($variancePercentage),
                'quarterly_performance' => $this->calculateQuarterlyPerformance($budget, $reportCurrency)
            ];
        });
        
        // Filter by variance threshold if specified
        if ($request->filled('variance_threshold')) {
            $threshold = $request->variance_threshold;
            $varianceData = $varianceData->filter(function($item) use ($threshold) {
                return abs($item['variance_percentage']) >= $threshold;
            });
        }
        
        // Sort by variance percentage (highest absolute variance first)
        $varianceData = $varianceData->sortByDesc(function($item) {
            return abs($item['variance_percentage']);
        })->values();
        
        // Calculate summary statistics
        $summary = [
            'period' => $period,
            'currency' => $reportCurrency,
            'total_budgets' => $varianceData->count(),
            'total_budgeted' => $varianceData->sum('budgeted_amount'),
            'total_actual' => $varianceData->sum('actual_amount'),
            'total_variance' => $varianceData->sum('variance'),
            'average_variance_percentage' => $varianceData->avg('variance_percentage'),
            'favorable_variances' => $varianceData->where('variance_type', 'favorable')->count(),
            'unfavorable_variances' => $varianceData->where('variance_type', 'unfavorable')->count(),
            'critical_variances' => $varianceData->where('status', 'critical')->count()
        ];
        
        return response()->json([
            'data' => $varianceData,
            'summary' => $summary
        ], 200);
    }

    /**
     * Get budget summary by department and currency.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function departmentSummary(Request $request)
    {
        $request->validate([
            'period_id' => 'required|exists:AccountingPeriod,period_id',
            'currency' => 'nullable|string|size:3'
        ]);
        
        $period = AccountingPeriod::findOrFail($request->period_id);
        $reportCurrency = $request->input('currency', config('app.base_currency', 'USD'));
        
        $budgets = Budget::with(['chartOfAccount'])
            ->where('period_id', $request->period_id)
            ->get();
        
        $departmentSummary = $budgets->groupBy('department')->map(function($group, $department) use ($reportCurrency, $period) {
            $totalBudgeted = 0;
            $totalActual = 0;
            $accountBreakdown = [];
            
            foreach ($group as $budget) {
                $rate = $this->getExchangeRate($budget->currency, $reportCurrency, $period->end_date);
                $budgetedConverted = $budget->budgeted_amount * $rate;
                
                $actualAmounts = $this->calculateActualAmounts($budget);
                $actualConverted = $actualAmounts['total'] * $rate;
                
                $totalBudgeted += $budgetedConverted;
                $totalActual += $actualConverted;
                
                $accountBreakdown[] = [
                    'account_code' => $budget->chartOfAccount->account_code,
                    'account_name' => $budget->chartOfAccount->name,
                    'account_type' => $budget->chartOfAccount->account_type,
                    'budgeted' => $budgetedConverted,
                    'actual' => $actualConverted,
                    'variance' => $budgetedConverted - $actualConverted
                ];
            }
            
            $totalVariance = $totalBudgeted - $totalActual;
            $variancePercentage = $totalBudgeted != 0 ? ($totalVariance / $totalBudgeted) * 100 : 0;
            
            return [
                'department' => $department ?? 'Unassigned',
                'currency' => $reportCurrency,
                'budget_count' => $group->count(),
                'total_budgeted' => $totalBudgeted,
                'total_actual' => $totalActual,
                'total_variance' => $totalVariance,
                'variance_percentage' => $variancePercentage,
                'performance_rating' => $this->getDepartmentPerformanceRating($variancePercentage),
                'account_breakdown' => $accountBreakdown
            ];
        });
        
        return response()->json([
            'data' => $departmentSummary,
            'summary' => [
                'period' => $period,
                'currency' => $reportCurrency,
                'total_departments' => $departmentSummary->count(),
                'grand_total_budgeted' => $departmentSummary->sum('total_budgeted'),
                'grand_total_actual' => $departmentSummary->sum('total_actual'),
                'grand_total_variance' => $departmentSummary->sum('total_variance')
            ]
        ], 200);
    }

    /**
     * Generate budget vs actual trend analysis.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function trendAnalysis(Request $request)
    {
        $request->validate([
            'account_id' => 'required|exists:ChartOfAccount,account_id',
            'periods' => 'required|array|min:2',
            'periods.*' => 'exists:AccountingPeriod,period_id',
            'currency' => 'nullable|string|size:3'
        ]);
        
        $account = ChartOfAccount::findOrFail($request->account_id);
        $periods = AccountingPeriod::whereIn('period_id', $request->periods)
            ->orderBy('start_date')
            ->get();
        $reportCurrency = $request->input('currency', config('app.base_currency', 'USD'));
        
        $trendData = $periods->map(function($period) use ($account, $reportCurrency) {
            $budget = Budget::where('account_id', $account->account_id)
                ->where('period_id', $period->period_id)
                ->first();
            
            if (!$budget) {
                return [
                    'period_id' => $period->period_id,
                    'period_name' => $period->period_name,
                    'budgeted_amount' => 0,
                    'actual_amount' => 0,
                    'variance' => 0,
                    'variance_percentage' => 0
                ];
            }
            
            $rate = $this->getExchangeRate($budget->currency, $reportCurrency, $period->end_date);
            $budgetedConverted = $budget->budgeted_amount * $rate;
            
            $actualAmounts = $this->calculateActualAmounts($budget);
            $actualConverted = $actualAmounts['total'] * $rate;
            
            $variance = $budgetedConverted - $actualConverted;
            $variancePercentage = $budgetedConverted != 0 ? ($variance / $budgetedConverted) * 100 : 0;
            
            return [
                'period_id' => $period->period_id,
                'period_name' => $period->period_name,
                'start_date' => $period->start_date,
                'end_date' => $period->end_date,
                'budgeted_amount' => $budgetedConverted,
                'actual_amount' => $actualConverted,
                'variance' => $variance,
                'variance_percentage' => $variancePercentage
            ];
        });
        
        // Calculate trend indicators
        $trendIndicators = $this->calculateTrendIndicators($trendData);
        
        return response()->json([
            'data' => $trendData,
            'trend_indicators' => $trendIndicators,
            'summary' => [
                'account' => $account,
                'currency' => $reportCurrency,
                'periods_analyzed' => $trendData->count(),
                'average_variance_percentage' => $trendData->avg('variance_percentage'),
                'trend_direction' => $trendIndicators['overall_trend']
            ]
        ], 200);
    }

    /**
     * Copy budget from previous period.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function copyFromPreviousPeriod(Request $request)
    {
        $request->validate([
            'source_period_id' => 'required|exists:AccountingPeriod,period_id',
            'target_period_id' => 'required|exists:AccountingPeriod,period_id',
            'adjustment_percentage' => 'nullable|numeric',
            'target_currency' => 'nullable|string|size:3',
            'departments' => 'nullable|array',
            'account_types' => 'nullable|array'
        ]);
        
        $sourcePeriod = AccountingPeriod::findOrFail($request->source_period_id);
        $targetPeriod = AccountingPeriod::findOrFail($request->target_period_id);
        $adjustmentPercentage = $request->input('adjustment_percentage', 0);
        $targetCurrency = $request->input('target_currency');
        
        $query = Budget::with('chartOfAccount')
            ->where('period_id', $request->source_period_id);
        
        // Filter by departments if specified
        if ($request->filled('departments')) {
            $query->whereIn('department', $request->departments);
        }
        
        // Filter by account types if specified
        if ($request->filled('account_types')) {
            $query->whereHas('chartOfAccount', function($q) use ($request) {
                $q->whereIn('account_type', $request->account_types);
            });
        }
        
        $sourceBudgets = $query->get();
        $copiedBudgets = [];
        $baseCurrency = config('app.base_currency', 'USD');
        
        foreach ($sourceBudgets as $sourceBudget) {
            // Check if budget already exists for target period
            $existingBudget = Budget::where('account_id', $sourceBudget->account_id)
                ->where('period_id', $request->target_period_id)
                ->where('department', $sourceBudget->department)
                ->first();
            
            if ($existingBudget) {
                continue; // Skip if already exists
            }
            
            // Determine target currency
            $currency = $targetCurrency ?? $sourceBudget->currency;
            
            // Calculate adjusted amount
            $adjustedAmount = $sourceBudget->budgeted_amount * (1 + $adjustmentPercentage / 100);
            
            // Convert currency if needed
            if ($currency !== $sourceBudget->currency) {
                $rate = $this->getExchangeRate($sourceBudget->currency, $currency, $targetPeriod->start_date);
                $adjustedAmount *= $rate;
            }
            
            // Calculate base currency amount
            $exchangeRate = $this->getExchangeRate($currency, $baseCurrency, $targetPeriod->start_date);
            $baseCurrencyAmount = $adjustedAmount * $exchangeRate;
            
            $newBudget = Budget::create([
                'account_id' => $sourceBudget->account_id,
                'period_id' => $request->target_period_id,
                'budgeted_amount' => $adjustedAmount,
                'currency' => $currency,
                'base_currency_amount' => $baseCurrencyAmount,
                'exchange_rate' => $exchangeRate,
                'department' => $sourceBudget->department,
                'budget_type' => $sourceBudget->budget_type,
                'notes' => 'Copied from ' . $sourcePeriod->period_name . 
                          ($adjustmentPercentage != 0 ? " with {$adjustmentPercentage}% adjustment" : ''),
                'actual_amount' => 0,
                'variance' => $adjustedAmount
            ]);
            
            $copiedBudgets[] = $newBudget;
        }
        
        return response()->json([
            'data' => $copiedBudgets,
            'summary' => [
                'source_period' => $sourcePeriod,
                'target_period' => $targetPeriod,
                'budgets_copied' => count($copiedBudgets),
                'adjustment_percentage' => $adjustmentPercentage,
                'target_currency' => $targetCurrency
            ],
            'message' => count($copiedBudgets) . ' budgets copied successfully'
        ], 201);
    }

    /**
     * Get available currencies from budgets.
     *
     * @return \Illuminate\Http\Response
     */
    public function getAvailableCurrencies()
    {
        $currencies = Budget::distinct()
            ->pluck('currency')
            ->filter()
            ->sort()
            ->values();
            
        return response()->json(['data' => $currencies], 200);
    }

    /**
     * Calculate actual amounts from journal entries.
     *
     * @param Budget $budget
     * @return array
     */
    private function calculateActualAmounts($budget)
    {
        $period = $budget->accountingPeriod;
        $account = $budget->chartOfAccount;
        
        // Get journal entry lines for this account in this period
        $lines = JournalEntryLine::whereHas('journalEntry', function($q) use ($period) {
                $q->where('period_id', $period->period_id)
                  ->where('status', 'Posted');
            })
            ->where('account_id', $account->account_id)
            ->get();
        
        $totalDebit = $lines->sum('debit_amount');
        $totalCredit = $lines->sum('credit_amount');
        
        // Calculate net amount based on account type
        $netAmount = match($account->account_type) {
            'Income' => $totalCredit - $totalDebit,
            'Expense' => $totalDebit - $totalCredit,
            'Asset' => $totalDebit - $totalCredit,
            'Liability' => $totalCredit - $totalDebit,
            'Equity' => $totalCredit - $totalDebit,
            default => $totalDebit - $totalCredit
        };
        
        return [
            'total' => $netAmount,
            'breakdown' => [
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'net_amount' => $netAmount,
                'transaction_count' => $lines->count()
            ]
        ];
    }

    /**
     * Calculate quarterly performance.
     *
     * @param Budget $budget
     * @param string $reportCurrency
     * @return array
     */
    private function calculateQuarterlyPerformance($budget, $reportCurrency)
    {
        if (!$budget->quarterly_breakdown) {
            return [];
        }
        
        $quarterlyBudgets = json_decode($budget->quarterly_breakdown, true);
        $rate = $this->getExchangeRate($budget->currency, $reportCurrency, $budget->accountingPeriod->end_date);
        
        return collect($quarterlyBudgets)->map(function($quarter) use ($rate) {
            return [
                'quarter' => $quarter['quarter'],
                'budgeted' => $quarter['amount'] * $rate,
                'actual' => 0, // Would need to calculate from actual transactions
                'variance' => $quarter['amount'] * $rate
            ];
        })->toArray();
    }

    /**
     * Get variance status based on percentage.
     *
     * @param float $variancePercentage
     * @return string
     */
    private function getVarianceStatus($variancePercentage)
    {
        $absVariance = abs($variancePercentage);
        
        if ($absVariance > 20) {
            return 'critical';
        } elseif ($absVariance > 10) {
            return 'high';
        } elseif ($absVariance > 5) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Get department performance rating.
     *
     * @param float $variancePercentage
     * @return string
     */
    private function getDepartmentPerformanceRating($variancePercentage)
    {
        if ($variancePercentage > 10) {
            return 'excellent';
        } elseif ($variancePercentage > 0) {
            return 'good';
        } elseif ($variancePercentage > -10) {
            return 'satisfactory';
        } else {
            return 'needs_improvement';
        }
    }

    /**
     * Calculate trend indicators.
     *
     * @param \Illuminate\Support\Collection $trendData
     * @return array
     */
    private function calculateTrendIndicators($trendData)
    {
        if ($trendData->count() < 2) {
            return ['overall_trend' => 'insufficient_data'];
        }
        
        $variances = $trendData->pluck('variance_percentage')->toArray();
        $trend = end($variances) - reset($variances);
        
        return [
            'overall_trend' => $trend > 5 ? 'improving' : ($trend < -5 ? 'declining' : 'stable'),
            'trend_value' => $trend,
            'volatility' => $this->calculateVolatility($variances),
            'best_period' => $trendData->sortBy('variance_percentage')->first(),
            'worst_period' => $trendData->sortByDesc('variance_percentage')->first()
        ];
    }

    /**
     * Calculate volatility of variances.
     *
     * @param array $variances
     * @return float
     */
    private function calculateVolatility($variances)
    {
        $mean = array_sum($variances) / count($variances);
        $squareDiffs = array_map(function($variance) use ($mean) {
            return pow($variance - $mean, 2);
        }, $variances);
        
        return sqrt(array_sum($squareDiffs) / count($squareDiffs));
    }

    /**
     * Get currency summary for a budget.
     *
     * @param Budget $budget
     * @return array
     */
    private function getBudgetCurrencySummary($budget)
    {
        $baseCurrency = config('app.base_currency', 'USD');
        
        return [
            'budget_currency' => $budget->currency,
            'base_currency' => $baseCurrency,
            'exchange_rate' => $budget->exchange_rate,
            'amounts' => [
                'budgeted' => [
                    'currency' => $budget->currency,
                    'amount' => $budget->budgeted_amount
                ],
                'base_currency' => [
                    'currency' => $baseCurrency,
                    'amount' => $budget->base_currency_amount
                ]
            ],
            'risk_assessment' => [
                'currency_risk' => $budget->currency !== $baseCurrency ? 'medium' : 'none',
                'variance_risk' => $this->getVarianceStatus($budget->variance_percentage ?? 0)
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