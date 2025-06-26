<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\FixedAsset;
use App\Models\Accounting\AssetDepreciation;
use App\Models\Accounting\AccountingPeriod;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Accounting\ExchangeRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AssetDepreciationController extends Controller
{
    /**
     * Display a listing of asset depreciations.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = AssetDepreciation::with(['fixedAsset', 'accountingPeriod']);
        
        // Filter by asset
        if ($request->has('asset_id')) {
            $query->where('asset_id', $request->asset_id);
        }
        
        // Filter by period
        if ($request->has('period_id')) {
            $query->where('period_id', $request->period_id);
        }
        
        // Filter by currency
        if ($request->has('currency')) {
            $query->whereHas('fixedAsset', function($q) use ($request) {
                $q->where('currency', $request->currency);
            });
        }
        
        // Filter by depreciation date range
        if ($request->has('from_date') && $request->has('to_date')) {
            $query->whereBetween('depreciation_date', [$request->from_date, $request->to_date]);
        }
        
        $depreciations = $query->orderBy('depreciation_date', 'desc')
            ->paginate($request->input('per_page', 15));
        
        // Add currency conversion if requested
        if ($request->filled('display_currency')) {
            $displayCurrency = $request->display_currency;
            $conversionDate = $request->input('conversion_date', now()->toDateString());
            
            $depreciations->getCollection()->transform(function ($depreciation) use ($displayCurrency, $conversionDate) {
                $assetCurrency = $depreciation->fixedAsset->currency;
                if ($assetCurrency !== $displayCurrency) {
                    $rate = $this->getExchangeRate($assetCurrency, $displayCurrency, $conversionDate);
                    $depreciation->converted_amount = $depreciation->depreciation_amount * $rate;
                    $depreciation->converted_accumulated = $depreciation->accumulated_depreciation * $rate;
                    $depreciation->converted_remaining = $depreciation->remaining_value * $rate;
                    $depreciation->display_currency = $displayCurrency;
                    $depreciation->exchange_rate = $rate;
                }
                return $depreciation;
            });
        }
        
        return response()->json($depreciations, 200);
    }

    /**
     * Store a newly created asset depreciation in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'asset_id' => 'required|exists:FixedAsset,asset_id',
            'period_id' => 'required|exists:AccountingPeriod,period_id',
            'depreciation_date' => 'required|date',
            'depreciation_amount' => 'required|numeric|min:0',
            'accumulated_depreciation' => 'required|numeric|min:0',
            'remaining_value' => 'required|numeric|min:0',
            'create_journal_entry' => 'boolean',
            'depreciation_expense_account_id' => 'nullable|exists:ChartOfAccount,account_id',
            'accumulated_depreciation_account_id' => 'nullable|exists:ChartOfAccount,account_id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Check if a depreciation already exists for this asset and period
        $exists = AssetDepreciation::where('asset_id', $request->asset_id)
            ->where('period_id', $request->period_id)
            ->exists();
        
        if ($exists) {
            return response()->json([
                'message' => 'A depreciation already exists for this asset and period'
            ], 422);
        }
        
        $asset = FixedAsset::findOrFail($request->asset_id);
        $baseCurrency = config('app.base_currency', 'USD');
        
        // Calculate base currency equivalents
        $exchangeRate = $this->getExchangeRate($asset->currency, $baseCurrency, $request->depreciation_date);
        $baseCurrencyDepreciationAmount = $request->depreciation_amount * $exchangeRate;
        $baseCurrencyAccumulatedDepreciation = $request->accumulated_depreciation * $exchangeRate;
        $baseCurrencyRemainingValue = $request->remaining_value * $exchangeRate;
        
        DB::beginTransaction();
        
        try {
            $depreciation = AssetDepreciation::create([
                'asset_id' => $request->asset_id,
                'period_id' => $request->period_id,
                'depreciation_date' => $request->depreciation_date,
                'depreciation_amount' => $request->depreciation_amount,
                'accumulated_depreciation' => $request->accumulated_depreciation,
                'remaining_value' => $request->remaining_value,
                'currency' => $asset->currency,
                'base_currency_depreciation_amount' => $baseCurrencyDepreciationAmount,
                'base_currency_accumulated_depreciation' => $baseCurrencyAccumulatedDepreciation,
                'base_currency_remaining_value' => $baseCurrencyRemainingValue,
                'exchange_rate' => $exchangeRate
            ]);
            
            // Update fixed asset current value
            $asset->current_value = $request->remaining_value;
            $asset->base_currency_current_value = $baseCurrencyRemainingValue;
            $asset->save();
            
            // Create journal entry if requested
            if ($request->boolean('create_journal_entry')) {
                $this->createDepreciationJournalEntry($depreciation, $request);
            }
            
            DB::commit();

            return response()->json([
                'data' => $depreciation->load(['fixedAsset', 'accountingPeriod']), 
                'message' => 'Asset depreciation created successfully'
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create depreciation: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified asset depreciation.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $depreciation = AssetDepreciation::with(['fixedAsset', 'accountingPeriod', 'journalEntry'])
            ->findOrFail($id);
        
        // Add currency summary
        $currencySummary = $this->getDepreciationCurrencySummary($depreciation);
        
        return response()->json([
            'data' => $depreciation,
            'currency_summary' => $currencySummary
        ], 200);
    }

    /**
     * Update the specified asset depreciation in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $depreciation = AssetDepreciation::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'depreciation_date' => 'date',
            'depreciation_amount' => 'numeric|min:0',
            'accumulated_depreciation' => 'numeric|min:0',
            'remaining_value' => 'numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $asset = $depreciation->fixedAsset;
        $baseCurrency = config('app.base_currency', 'USD');
        
        // Recalculate base currency amounts if values changed
        if ($request->has('depreciation_amount') || $request->has('accumulated_depreciation') || $request->has('remaining_value') || $request->has('depreciation_date')) {
            $depreciationAmount = $request->depreciation_amount ?? $depreciation->depreciation_amount;
            $accumulatedDepreciation = $request->accumulated_depreciation ?? $depreciation->accumulated_depreciation;
            $remainingValue = $request->remaining_value ?? $depreciation->remaining_value;
            $date = $request->depreciation_date ?? $depreciation->depreciation_date;
            
            $exchangeRate = $this->getExchangeRate($asset->currency, $baseCurrency, $date);
            $baseCurrencyDepreciationAmount = $depreciationAmount * $exchangeRate;
            $baseCurrencyAccumulatedDepreciation = $accumulatedDepreciation * $exchangeRate;
            $baseCurrencyRemainingValue = $remainingValue * $exchangeRate;
            
            $request->merge([
                'base_currency_depreciation_amount' => $baseCurrencyDepreciationAmount,
                'base_currency_accumulated_depreciation' => $baseCurrencyAccumulatedDepreciation,
                'base_currency_remaining_value' => $baseCurrencyRemainingValue,
                'exchange_rate' => $exchangeRate
            ]);
            
            // Update asset current value
            if ($request->has('remaining_value')) {
                $asset->current_value = $remainingValue;
                $asset->base_currency_current_value = $baseCurrencyRemainingValue;
                $asset->save();
            }
        }

        $depreciation->update($request->only([
            'depreciation_date', 'depreciation_amount', 'accumulated_depreciation', 'remaining_value',
            'base_currency_depreciation_amount', 'base_currency_accumulated_depreciation', 
            'base_currency_remaining_value', 'exchange_rate'
        ]));

        return response()->json([
            'data' => $depreciation->load(['fixedAsset', 'accountingPeriod']), 
            'message' => 'Asset depreciation updated successfully'
        ], 200);
    }

    /**
     * Remove the specified asset depreciation from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $depreciation = AssetDepreciation::findOrFail($id);
        
        // Check if there's a journal entry that needs to be reversed
        if ($depreciation->journalEntry && $depreciation->journalEntry->status === 'Posted') {
            return response()->json([
                'message' => 'Cannot delete depreciation with posted journal entry. Reverse the journal entry first.'
            ], 422);
        }
        
        DB::beginTransaction();
        
        try {
            // Delete associated journal entry if exists
            if ($depreciation->journalEntry) {
                $depreciation->journalEntry->journalEntryLines()->delete();
                $depreciation->journalEntry->delete();
            }
            
            // Restore asset current value
            $asset = $depreciation->fixedAsset;
            $asset->current_value += $depreciation->depreciation_amount;
            $asset->base_currency_current_value += $depreciation->base_currency_depreciation_amount;
            $asset->save();
            
            $depreciation->delete();
            
            DB::commit();

            return response()->json(['message' => 'Asset depreciation deleted successfully'], 200);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to delete depreciation: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Calculate depreciation for a specific asset.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $assetId
     * @return \Illuminate\Http\Response
     */
    public function calculateDepreciation(Request $request, $assetId)
    {
        $validator = Validator::make($request->all(), [
            'period_id' => 'required|exists:AccountingPeriod,period_id',
            'depreciation_date' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $asset = FixedAsset::findOrFail($assetId);
        $period = AccountingPeriod::findOrFail($request->period_id);
        $depreciationDate = $request->depreciation_date ?? $period->end_date;
        
        // Calculate depreciation based on method
        $calculation = $this->performDepreciationCalculation($asset, $period, $depreciationDate);
        
        return response()->json([
            'data' => $calculation,
            'message' => 'Depreciation calculated successfully'
        ], 200);
    }

    /**
     * Generate depreciation summary report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function summaryReport(Request $request)
    {
        $request->validate([
            'period_id' => 'nullable|exists:AccountingPeriod,period_id',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'currency' => 'nullable|string|size:3',
            'category' => 'nullable|string'
        ]);
        
        $reportCurrency = $request->input('currency', config('app.base_currency', 'USD'));
        
        $query = AssetDepreciation::with(['fixedAsset', 'accountingPeriod']);
        
        // Filter by period
        if ($request->filled('period_id')) {
            $query->where('period_id', $request->period_id);
        }
        
        // Filter by date range
        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->whereBetween('depreciation_date', [$request->from_date, $request->to_date]);
        }
        
        // Filter by asset category
        if ($request->filled('category')) {
            $query->whereHas('fixedAsset', function($q) use ($request) {
                $q->where('category', $request->category);
            });
        }
        
        $depreciations = $query->get();
        
        // Group by category and convert to report currency
        $summary = $depreciations->groupBy('fixedAsset.category')->map(function($group, $category) use ($reportCurrency) {
            $totalDepreciation = 0;
            $totalAccumulated = 0;
            $totalRemainingValue = 0;
            
            foreach ($group as $depreciation) {
                $rate = $this->getExchangeRate($depreciation->fixedAsset->currency, $reportCurrency, $depreciation->depreciation_date);
                $totalDepreciation += $depreciation->depreciation_amount * $rate;
                $totalAccumulated += $depreciation->accumulated_depreciation * $rate;
                $totalRemainingValue += $depreciation->remaining_value * $rate;
            }
            
            return [
                'category' => $category,
                'asset_count' => $group->count(),
                'total_depreciation' => $totalDepreciation,
                'total_accumulated_depreciation' => $totalAccumulated,
                'total_remaining_value' => $totalRemainingValue,
                'average_depreciation' => $group->count() > 0 ? $totalDepreciation / $group->count() : 0,
                'depreciation_methods' => $group->pluck('fixedAsset.depreciation_method')->unique()->values()
            ];
        });
        
        return response()->json([
            'data' => $summary,
            'grand_total' => [
                'currency' => $reportCurrency,
                'total_entries' => $depreciations->count(),
                'total_depreciation' => $summary->sum('total_depreciation'),
                'total_accumulated' => $summary->sum('total_accumulated_depreciation'),
                'total_remaining_value' => $summary->sum('total_remaining_value'),
                'categories_count' => $summary->count()
            ]
        ], 200);
    }

    /**
     * Generate batch depreciation for multiple assets.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function batchDepreciation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'period_id' => 'required|exists:AccountingPeriod,period_id',
            'asset_ids' => 'nullable|array',
            'asset_ids.*' => 'exists:FixedAsset,asset_id',
            'category' => 'nullable|string',
            'depreciation_date' => 'nullable|date',
            'create_journal_entries' => 'boolean',
            'depreciation_expense_account_id' => 'nullable|exists:ChartOfAccount,account_id',
            'accumulated_depreciation_account_id' => 'nullable|exists:ChartOfAccount,account_id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $period = AccountingPeriod::findOrFail($request->period_id);
        $depreciationDate = $request->depreciation_date ?? $period->end_date;
        
        // Get assets to depreciate
        $query = FixedAsset::where('status', 'Active');
        
        if ($request->filled('asset_ids')) {
            $query->whereIn('asset_id', $request->asset_ids);
        }
        
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        
        // Exclude assets that already have depreciation for this period
        $query->whereDoesntHave('assetDepreciations', function($q) use ($request) {
            $q->where('period_id', $request->period_id);
        });
        
        $assets = $query->get();
        
        if ($assets->isEmpty()) {
            return response()->json([
                'message' => 'No assets found for depreciation or all assets already have depreciation for this period'
            ], 422);
        }
        
        DB::beginTransaction();
        
        try {
            $createdDepreciations = [];
            $totalDepreciationByCurrency = [];
            
            foreach ($assets as $asset) {
                $calculation = $this->performDepreciationCalculation($asset, $period, $depreciationDate);
                
                if ($calculation['depreciation_amount'] > 0) {
                    $depreciation = AssetDepreciation::create([
                        'asset_id' => $asset->asset_id,
                        'period_id' => $request->period_id,
                        'depreciation_date' => $depreciationDate,
                        'depreciation_amount' => $calculation['depreciation_amount'],
                        'accumulated_depreciation' => $calculation['accumulated_depreciation'],
                        'remaining_value' => $calculation['remaining_value'],
                        'currency' => $asset->currency,
                        'base_currency_depreciation_amount' => $calculation['base_currency_depreciation_amount'],
                        'base_currency_accumulated_depreciation' => $calculation['base_currency_accumulated_depreciation'],
                        'base_currency_remaining_value' => $calculation['base_currency_remaining_value'],
                        'exchange_rate' => $calculation['exchange_rate']
                    ]);
                    
                    // Update asset current value
                    $asset->current_value = $calculation['remaining_value'];
                    $asset->base_currency_current_value = $calculation['base_currency_remaining_value'];
                    $asset->save();
                    
                    $createdDepreciations[] = $depreciation;
                    
                    // Track totals by currency
                    if (!isset($totalDepreciationByCurrency[$asset->currency])) {
                        $totalDepreciationByCurrency[$asset->currency] = 0;
                    }
                    $totalDepreciationByCurrency[$asset->currency] += $calculation['depreciation_amount'];
                }
            }
            
            // Create consolidated journal entries by currency if requested
            if ($request->boolean('create_journal_entries') && !empty($createdDepreciations)) {
                $this->createBatchDepreciationJournalEntries($createdDepreciations, $period, $request);
            }
            
            DB::commit();

            return response()->json([
                'data' => $createdDepreciations,
                'summary' => [
                    'total_assets_processed' => $assets->count(),
                    'depreciations_created' => count($createdDepreciations),
                    'total_by_currency' => $totalDepreciationByCurrency,
                    'period' => $period->period_name,
                    'depreciation_date' => $depreciationDate
                ],
                'message' => count($createdDepreciations) . ' depreciation entries created successfully'
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create batch depreciation: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Convert depreciation amounts to different currency.
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

        $depreciation = AssetDepreciation::with('fixedAsset')->findOrFail($id);
        $targetCurrency = $request->target_currency;
        $conversionDate = $request->conversion_date ?? $depreciation->depreciation_date;

        $assetCurrency = $depreciation->fixedAsset->currency;
        $exchangeRate = $this->getExchangeRate($assetCurrency, $targetCurrency, $conversionDate);
        
        $convertedAmounts = [
            'depreciation_amount' => $depreciation->depreciation_amount * $exchangeRate,
            'accumulated_depreciation' => $depreciation->accumulated_depreciation * $exchangeRate,
            'remaining_value' => $depreciation->remaining_value * $exchangeRate
        ];

        return response()->json([
            'data' => [
                'depreciation' => $depreciation,
                'original_currency' => $assetCurrency,
                'target_currency' => $targetCurrency,
                'exchange_rate' => $exchangeRate,
                'conversion_date' => $conversionDate,
                'converted_amounts' => $convertedAmounts
            ]
        ], 200);
    }

    /**
     * Get available currencies from asset depreciations.
     *
     * @return \Illuminate\Http\Response
     */
    public function getAvailableCurrencies()
    {
        $currencies = AssetDepreciation::join('FixedAsset', 'AssetDepreciation.asset_id', '=', 'FixedAsset.asset_id')
            ->distinct()
            ->pluck('FixedAsset.currency')
            ->filter()
            ->sort()
            ->values();
            
        return response()->json(['data' => $currencies], 200);
    }

    /**
     * Perform depreciation calculation for an asset.
     *
     * @param FixedAsset $asset
     * @param AccountingPeriod $period
     * @param string $depreciationDate
     * @return array
     */
    private function performDepreciationCalculation($asset, $period, $depreciationDate)
    {
        $baseCurrency = config('app.base_currency', 'USD');
        $exchangeRate = $this->getExchangeRate($asset->currency, $baseCurrency, $depreciationDate);
        
        // Get previous accumulated depreciation
        $previousDepreciation = $asset->assetDepreciations()
            ->where('depreciation_date', '<', $depreciationDate)
            ->sum('depreciation_amount');
        
        $depreciableAmount = $asset->acquisition_cost - $asset->salvage_value;
        $yearsElapsed = now()->parse($depreciationDate)->diffInYears($asset->acquisition_date, false);
        
        // Calculate depreciation based on method
        $depreciationAmount = match($asset->depreciation_method) {
            'straight_line' => $this->calculateStraightLineDepreciation($asset, $period),
            'declining_balance' => $this->calculateDecliningBalanceDepreciation($asset, $previousDepreciation),
            'sum_of_years' => $this->calculateSumOfYearsDepreciation($asset, $yearsElapsed),
            'units_of_production' => $this->calculateUnitsOfProductionDepreciation($asset, $period),
            default => $this->calculateStraightLineDepreciation($asset, $period)
        };
        
        $accumulatedDepreciation = $previousDepreciation + $depreciationAmount;
        $remainingValue = $asset->acquisition_cost - $accumulatedDepreciation;
        
        // Ensure remaining value doesn't go below salvage value
        if ($remainingValue < $asset->salvage_value) {
            $depreciationAmount = $asset->current_value - $asset->salvage_value;
            $accumulatedDepreciation = $asset->acquisition_cost - $asset->salvage_value;
            $remainingValue = $asset->salvage_value;
        }
        
        return [
            'depreciation_amount' => $depreciationAmount,
            'accumulated_depreciation' => $accumulatedDepreciation,
            'remaining_value' => $remainingValue,
            'base_currency_depreciation_amount' => $depreciationAmount * $exchangeRate,
            'base_currency_accumulated_depreciation' => $accumulatedDepreciation * $exchangeRate,
            'base_currency_remaining_value' => $remainingValue * $exchangeRate,
            'exchange_rate' => $exchangeRate,
            'calculation_method' => $asset->depreciation_method
        ];
    }

    /**
     * Calculate straight line depreciation.
     *
     * @param FixedAsset $asset
     * @param AccountingPeriod $period
     * @return float
     */
    private function calculateStraightLineDepreciation($asset, $period)
    {
        $depreciableAmount = $asset->acquisition_cost - $asset->salvage_value;
        $usefulLife = $asset->useful_life_years ?? 10;
        
        // Calculate pro-rata for the period
        $periodMonths = now()->parse($period->start_date)->diffInMonths($period->end_date) + 1;
        $annualDepreciation = $depreciableAmount / $usefulLife;
        
        return ($annualDepreciation / 12) * $periodMonths;
    }

    /**
     * Calculate declining balance depreciation.
     *
     * @param FixedAsset $asset
     * @param float $previousDepreciation
     * @return float
     */
    private function calculateDecliningBalanceDepreciation($asset, $previousDepreciation)
    {
        $bookValue = $asset->acquisition_cost - $previousDepreciation;
        $rate = $asset->depreciation_rate / 100;
        
        return $bookValue * $rate;
    }

    /**
     * Calculate sum of years digits depreciation.
     *
     * @param FixedAsset $asset
     * @param float $yearsElapsed
     * @return float
     */
    private function calculateSumOfYearsDepreciation($asset, $yearsElapsed)
    {
        $usefulLife = $asset->useful_life_years ?? 10;
        $depreciableAmount = $asset->acquisition_cost - $asset->salvage_value;
        $sumOfYears = ($usefulLife * ($usefulLife + 1)) / 2;
        $remainingLife = max(1, $usefulLife - floor($yearsElapsed));
        
        return ($remainingLife / $sumOfYears) * $depreciableAmount;
    }

    /**
     * Calculate units of production depreciation.
     *
     * @param FixedAsset $asset
     * @param AccountingPeriod $period
     * @return float
     */
    private function calculateUnitsOfProductionDepreciation($asset, $period)
    {
        // This would require additional fields in the asset model for total expected units and period usage
        // For now, fallback to straight line
        return $this->calculateStraightLineDepreciation($asset, $period);
    }

    /**
     * Create journal entry for depreciation.
     *
     * @param AssetDepreciation $depreciation
     * @param Request $request
     * @return void
     */
    private function createDepreciationJournalEntry($depreciation, $request)
    {
        if (!$request->has('depreciation_expense_account_id') || !$request->has('accumulated_depreciation_account_id')) {
            throw new \Exception('Depreciation expense and accumulated depreciation account IDs are required');
        }
        
        $journalEntry = JournalEntry::create([
            'journal_number' => 'DEP-' . $depreciation->asset_id . '-' . date('YmdHis'),
            'entry_date' => $depreciation->depreciation_date,
            'reference_type' => 'AssetDepreciation',
            'reference_id' => $depreciation->depreciation_id,
            'description' => 'Depreciation for ' . $depreciation->fixedAsset->name,
            'period_id' => $depreciation->period_id,
            'status' => 'Posted'
        ]);
        
        // Debit Depreciation Expense
        JournalEntryLine::create([
            'journal_id' => $journalEntry->journal_id,
            'account_id' => $request->depreciation_expense_account_id,
            'debit_amount' => $depreciation->base_currency_depreciation_amount,
            'credit_amount' => 0,
            'description' => 'Depreciation expense for ' . $depreciation->fixedAsset->name,
            'currency' => $depreciation->currency,
            'foreign_amount' => $depreciation->currency !== config('app.base_currency', 'USD') ? $depreciation->depreciation_amount : null
        ]);
        
        // Credit Accumulated Depreciation
        JournalEntryLine::create([
            'journal_id' => $journalEntry->journal_id,
            'account_id' => $request->accumulated_depreciation_account_id,
            'debit_amount' => 0,
            'credit_amount' => $depreciation->base_currency_depreciation_amount,
            'description' => 'Accumulated depreciation for ' . $depreciation->fixedAsset->name,
            'currency' => $depreciation->currency,
            'foreign_amount' => $depreciation->currency !== config('app.base_currency', 'USD') ? $depreciation->depreciation_amount : null
        ]);
        
        // Link journal entry to depreciation
        $depreciation->journal_entry_id = $journalEntry->journal_id;
        $depreciation->save();
    }

    /**
     * Create batch journal entries for multiple depreciations.
     *
     * @param array $depreciations
     * @param AccountingPeriod $period
     * @param Request $request
     * @return void
     */
    private function createBatchDepreciationJournalEntries($depreciations, $period, $request)
    {
        // Group depreciations by currency for consolidated entries
        $groupedByCurrency = collect($depreciations)->groupBy('currency');
        
        foreach ($groupedByCurrency as $currency => $currencyDepreciations) {
            $totalDepreciation = $currencyDepreciations->sum('depreciation_amount');
            $totalBaseCurrencyDepreciation = $currencyDepreciations->sum('base_currency_depreciation_amount');
            
            $journalEntry = JournalEntry::create([
                'journal_number' => 'DEP-BATCH-' . $currency . '-' . date('YmdHis'),
                'entry_date' => $currencyDepreciations->first()->depreciation_date,
                'reference_type' => 'BatchDepreciation',
                'reference_id' => $period->period_id,
                'description' => 'Batch depreciation for ' . $period->period_name . ' (' . $currency . ')',
                'period_id' => $period->period_id,
                'status' => 'Posted'
            ]);
            
            // Debit Depreciation Expense
            JournalEntryLine::create([
                'journal_id' => $journalEntry->journal_id,
                'account_id' => $request->depreciation_expense_account_id,
                'debit_amount' => $totalBaseCurrencyDepreciation,
                'credit_amount' => 0,
                'description' => 'Batch depreciation expense (' . $currency . ')',
                'currency' => $currency,
                'foreign_amount' => $currency !== config('app.base_currency', 'USD') ? $totalDepreciation : null
            ]);
            
            // Credit Accumulated Depreciation
            JournalEntryLine::create([
                'journal_id' => $journalEntry->journal_id,
                'account_id' => $request->accumulated_depreciation_account_id,
                'debit_amount' => 0,
                'credit_amount' => $totalBaseCurrencyDepreciation,
                'description' => 'Batch accumulated depreciation (' . $currency . ')',
                'currency' => $currency,
                'foreign_amount' => $currency !== config('app.base_currency', 'USD') ? $totalDepreciation : null
            ]);
        }
    }

    /**
     * Get currency summary for a depreciation.
     *
     * @param AssetDepreciation $depreciation
     * @return array
     */
    private function getDepreciationCurrencySummary($depreciation)
    {
        $baseCurrency = config('app.base_currency', 'USD');
        $assetCurrency = $depreciation->fixedAsset->currency;
        
        return [
            'asset_currency' => $assetCurrency,
            'base_currency' => $baseCurrency,
            'exchange_rate' => $depreciation->exchange_rate,
            'amounts' => [
                'original' => [
                    'currency' => $assetCurrency,
                    'depreciation_amount' => $depreciation->depreciation_amount,
                    'accumulated_depreciation' => $depreciation->accumulated_depreciation,
                    'remaining_value' => $depreciation->remaining_value
                ],
                'base_currency' => [
                    'currency' => $baseCurrency,
                    'depreciation_amount' => $depreciation->base_currency_depreciation_amount,
                    'accumulated_depreciation' => $depreciation->base_currency_accumulated_depreciation,
                    'remaining_value' => $depreciation->base_currency_remaining_value
                ]
            ],
            'asset_info' => [
                'name' => $depreciation->fixedAsset->name,
                'category' => $depreciation->fixedAsset->category,
                'depreciation_method' => $depreciation->fixedAsset->depreciation_method,
                'acquisition_cost' => $depreciation->fixedAsset->acquisition_cost
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