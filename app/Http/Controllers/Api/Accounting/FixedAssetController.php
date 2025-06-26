<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\FixedAsset;
use App\Models\Accounting\ExchangeRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class FixedAssetController extends Controller
{
    /**
     * Display a listing of fixed assets.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = FixedAsset::with('assetDepreciations');
        
        // Filter by category
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Filter by currency
        if ($request->has('currency')) {
            $query->where('currency', $request->currency);
        }
        
        // Filter by acquisition date range
        if ($request->has('from_date') && $request->has('to_date')) {
            $query->whereBetween('acquisition_date', [$request->from_date, $request->to_date]);
        }
        
        // Filter by cost range (in base currency)
        if ($request->has('min_cost')) {
            $query->where('base_currency_cost', '>=', $request->min_cost);
        }
        if ($request->has('max_cost')) {
            $query->where('base_currency_cost', '<=', $request->max_cost);
        }
        
        $assets = $query->orderBy('name')
            ->paginate($request->input('per_page', 15));
        
        // Add currency conversion if requested
        if ($request->filled('display_currency')) {
            $displayCurrency = $request->display_currency;
            $conversionDate = $request->input('conversion_date', now()->toDateString());
            
            $assets->getCollection()->transform(function ($asset) use ($displayCurrency, $conversionDate) {
                if ($asset->currency !== $displayCurrency) {
                    $rate = $this->getExchangeRate($asset->currency, $displayCurrency, $conversionDate);
                    $asset->converted_cost = $asset->acquisition_cost * $rate;
                    $asset->converted_current_value = $asset->current_value * $rate;
                    $asset->display_currency = $displayCurrency;
                    $asset->exchange_rate = $rate;
                }
                return $asset;
            });
        }
        
        return response()->json($assets, 200);
    }

    /**
     * Store a newly created fixed asset in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'asset_code' => 'required|string|max:50|unique:FixedAsset',
            'name' => 'required|string|max:100',
            'category' => 'required|string|max:50',
            'acquisition_date' => 'required|date',
            'acquisition_cost' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'depreciation_rate' => 'required|numeric|min:0|max:100',
            'depreciation_method' => 'nullable|string|in:straight_line,declining_balance,sum_of_years,units_of_production',
            'useful_life_years' => 'nullable|integer|min:1',
            'salvage_value' => 'nullable|numeric|min:0',
            'status' => 'required|string|max:50',
            'location' => 'nullable|string|max:255',
            'serial_number' => 'nullable|string|max:100',
            'supplier' => 'nullable|string|max:255',
            'warranty_expiry' => 'nullable|date',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $baseCurrency = config('app.base_currency', 'USD');
        $currency = $request->currency;
        
        // Calculate base currency equivalent
        $exchangeRate = $this->getExchangeRate($currency, $baseCurrency, $request->acquisition_date);
        $baseCurrencyCost = $request->acquisition_cost * $exchangeRate;
        $baseCurrencySalvageValue = $request->salvage_value ? $request->salvage_value * $exchangeRate : 0;

        $asset = FixedAsset::create([
            'asset_code' => $request->asset_code,
            'name' => $request->name,
            'category' => $request->category,
            'acquisition_date' => $request->acquisition_date,
            'acquisition_cost' => $request->acquisition_cost,
            'currency' => $currency,
            'base_currency_cost' => $baseCurrencyCost,
            'exchange_rate' => $exchangeRate,
            'current_value' => $request->acquisition_cost, // Initially same as acquisition cost
            'base_currency_current_value' => $baseCurrencyCost,
            'depreciation_rate' => $request->depreciation_rate,
            'depreciation_method' => $request->depreciation_method ?? 'straight_line',
            'useful_life_years' => $request->useful_life_years,
            'salvage_value' => $request->salvage_value ?? 0,
            'base_currency_salvage_value' => $baseCurrencySalvageValue,
            'status' => $request->status,
            'location' => $request->location,
            'serial_number' => $request->serial_number,
            'supplier' => $request->supplier,
            'warranty_expiry' => $request->warranty_expiry,
            'notes' => $request->notes
        ]);

        return response()->json([
            'data' => $asset, 
            'message' => 'Fixed asset created successfully'
        ], 201);
    }

    /**
     * Display the specified fixed asset.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $asset = FixedAsset::with(['assetDepreciations.accountingPeriod'])
            ->findOrFail($id);
        
        // Calculate depreciation summary
        $depreciationSummary = $this->calculateDepreciationSummary($asset);
        
        // Add currency summary
        $currencySummary = $this->getAssetCurrencySummary($asset);
        
        return response()->json([
            'data' => $asset,
            'depreciation_summary' => $depreciationSummary,
            'currency_summary' => $currencySummary
        ], 200);
    }

    /**
     * Update the specified fixed asset in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $asset = FixedAsset::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'asset_code' => 'string|max:50|unique:FixedAsset,asset_code,' . $id . ',asset_id',
            'name' => 'string|max:100',
            'category' => 'string|max:50',
            'acquisition_date' => 'date',
            'acquisition_cost' => 'numeric|min:0',
            'currency' => 'string|size:3',
            'current_value' => 'numeric|min:0',
            'depreciation_rate' => 'numeric|min:0|max:100',
            'depreciation_method' => 'nullable|string|in:straight_line,declining_balance,sum_of_years,units_of_production',
            'useful_life_years' => 'nullable|integer|min:1',
            'salvage_value' => 'nullable|numeric|min:0',
            'status' => 'string|max:50',
            'location' => 'nullable|string|max:255',
            'serial_number' => 'nullable|string|max:100',
            'supplier' => 'nullable|string|max:255',
            'warranty_expiry' => 'nullable|date',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // If there are existing depreciations, don't allow changing certain fields
        if ($asset->assetDepreciations()->count() > 0) {
            $protectedFields = ['acquisition_date', 'acquisition_cost', 'depreciation_rate', 'currency'];
            
            foreach ($protectedFields as $field) {
                if ($request->has($field) && $request->$field != $asset->$field) {
                    return response()->json([
                        'message' => 'Cannot modify ' . $field . ' when depreciation has already been recorded'
                    ], 422);
                }
            }
        }
        
        $baseCurrency = config('app.base_currency', 'USD');
        
        // Recalculate base currency amounts if currency or amounts changed
        if ($request->has('acquisition_cost') || $request->has('currency') || $request->has('current_value') || $request->has('salvage_value')) {
            $cost = $request->acquisition_cost ?? $asset->acquisition_cost;
            $currentValue = $request->current_value ?? $asset->current_value;
            $salvageValue = $request->salvage_value ?? $asset->salvage_value;
            $currency = $request->currency ?? $asset->currency;
            $date = $request->acquisition_date ?? $asset->acquisition_date;
            
            $exchangeRate = $this->getExchangeRate($currency, $baseCurrency, $date);
            $baseCurrencyCost = $cost * $exchangeRate;
            $baseCurrencyCurrentValue = $currentValue * $exchangeRate;
            $baseCurrencySalvageValue = $salvageValue * $exchangeRate;
            
            $request->merge([
                'base_currency_cost' => $baseCurrencyCost,
                'base_currency_current_value' => $baseCurrencyCurrentValue,
                'base_currency_salvage_value' => $baseCurrencySalvageValue,
                'exchange_rate' => $exchangeRate
            ]);
        }
        
        $asset->update($request->only([
            'asset_code', 'name', 'category', 'acquisition_date', 'acquisition_cost',
            'currency', 'base_currency_cost', 'exchange_rate', 'current_value',
            'base_currency_current_value', 'depreciation_rate', 'depreciation_method',
            'useful_life_years', 'salvage_value', 'base_currency_salvage_value',
            'status', 'location', 'serial_number', 'supplier', 'warranty_expiry', 'notes'
        ]));

        return response()->json([
            'data' => $asset, 
            'message' => 'Fixed asset updated successfully'
        ], 200);
    }

    /**
     * Remove the specified fixed asset from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $asset = FixedAsset::findOrFail($id);
        
        // Check if there are depreciations
        if ($asset->assetDepreciations()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete asset with recorded depreciation'
            ], 422);
        }
        
        $asset->delete();

        return response()->json(['message' => 'Fixed asset deleted successfully'], 200);
    }

    /**
     * Get fixed assets summary by category and currency.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function categorySummary(Request $request)
    {
        $currency = $request->input('currency', config('app.base_currency', 'USD'));
        $conversionDate = $request->input('conversion_date', now()->toDateString());
        
        $assets = FixedAsset::where('status', '!=', 'Disposed')->get();
        
        $summary = $assets->groupBy('category')->map(function($group, $category) use ($currency, $conversionDate) {
            $totalCost = 0;
            $totalCurrentValue = 0;
            $totalDepreciation = 0;
            
            foreach ($group as $asset) {
                $rate = $this->getExchangeRate($asset->currency, $currency, $conversionDate);
                $costConverted = $asset->acquisition_cost * $rate;
                $currentValueConverted = $asset->current_value * $rate;
                
                $totalCost += $costConverted;
                $totalCurrentValue += $currentValueConverted;
                $totalDepreciation += ($costConverted - $currentValueConverted);
            }
            
            return [
                'category' => $category,
                'asset_count' => $group->count(),
                'total_cost' => $totalCost,
                'total_current_value' => $totalCurrentValue,
                'total_depreciation' => $totalDepreciation,
                'depreciation_percentage' => $totalCost > 0 ? ($totalDepreciation / $totalCost) * 100 : 0,
                'currencies_involved' => $group->pluck('currency')->unique()->values(),
                'average_age_years' => $group->avg(function($asset) {
                    return now()->diffInYears($asset->acquisition_date);
                })
            ];
        });
        
        return response()->json([
            'data' => $summary,
            'grand_total' => [
                'currency' => $currency,
                'total_assets' => $assets->count(),
                'total_cost' => $summary->sum('total_cost'),
                'total_current_value' => $summary->sum('total_current_value'),
                'total_depreciation' => $summary->sum('total_depreciation'),
                'categories_count' => $summary->count()
            ]
        ], 200);
    }

    /**
     * Convert asset values to different currency.
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

        $asset = FixedAsset::findOrFail($id);
        $targetCurrency = $request->target_currency;
        $conversionDate = $request->conversion_date ?? now()->toDateString();

        $exchangeRate = $this->getExchangeRate($asset->currency, $targetCurrency, $conversionDate);
        
        $convertedValues = [
            'acquisition_cost' => $asset->acquisition_cost * $exchangeRate,
            'current_value' => $asset->current_value * $exchangeRate,
            'salvage_value' => $asset->salvage_value * $exchangeRate,
            'accumulated_depreciation' => ($asset->acquisition_cost - $asset->current_value) * $exchangeRate
        ];

        return response()->json([
            'data' => [
                'asset' => $asset,
                'original_currency' => $asset->currency,
                'target_currency' => $targetCurrency,
                'exchange_rate' => $exchangeRate,
                'conversion_date' => $conversionDate,
                'converted_values' => $convertedValues
            ]
        ], 200);
    }

    /**
     * Generate asset valuation report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function valuationReport(Request $request)
    {
        $request->validate([
            'as_of_date' => 'nullable|date',
            'currency' => 'nullable|string|size:3',
            'category' => 'nullable|string',
            'include_disposed' => 'boolean'
        ]);
        
        $asOfDate = $request->input('as_of_date', now()->toDateString());
        $reportCurrency = $request->input('currency', config('app.base_currency', 'USD'));
        
        $query = FixedAsset::with('assetDepreciations');
        
        // Filter by category
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        
        // Exclude disposed assets unless requested
        if (!$request->boolean('include_disposed')) {
            $query->where('status', '!=', 'Disposed');
        }
        
        $assets = $query->get();
        
        $valuationData = $assets->map(function($asset) use ($asOfDate, $reportCurrency) {
            $rate = $this->getExchangeRate($asset->currency, $reportCurrency, $asOfDate);
            
            // Calculate depreciation up to the valuation date
            $yearsElapsed = now()->parse($asOfDate)->diffInYears($asset->acquisition_date, false);
            $depreciationCalculated = $this->calculateDepreciationToDate($asset, $asOfDate);
            
            $currentValueCalculated = max(
                $asset->acquisition_cost - $depreciationCalculated,
                $asset->salvage_value
            );
            
            return [
                'asset_code' => $asset->asset_code,
                'asset_name' => $asset->name,
                'category' => $asset->category,
                'acquisition_date' => $asset->acquisition_date,
                'years_elapsed' => $yearsElapsed,
                'original_currency' => $asset->currency,
                'acquisition_cost' => $asset->acquisition_cost * $rate,
                'calculated_current_value' => $currentValueCalculated * $rate,
                'book_current_value' => $asset->current_value * $rate,
                'salvage_value' => $asset->salvage_value * $rate,
                'accumulated_depreciation' => $depreciationCalculated * $rate,
                'remaining_useful_life' => max(0, ($asset->useful_life_years ?? 10) - $yearsElapsed),
                'depreciation_method' => $asset->depreciation_method,
                'status' => $asset->status,
                'variance' => ($currentValueCalculated - $asset->current_value) * $rate
            ];
        });
        
        // Calculate summary statistics
        $summary = [
            'as_of_date' => $asOfDate,
            'currency' => $reportCurrency,
            'total_assets' => $valuationData->count(),
            'total_acquisition_cost' => $valuationData->sum('acquisition_cost'),
            'total_calculated_value' => $valuationData->sum('calculated_current_value'),
            'total_book_value' => $valuationData->sum('book_current_value'),
            'total_depreciation' => $valuationData->sum('accumulated_depreciation'),
            'total_variance' => $valuationData->sum('variance'),
            'average_age' => $valuationData->avg('years_elapsed')
        ];
        
        return response()->json([
            'data' => $valuationData,
            'summary' => $summary
        ], 200);
    }

    /**
     * Generate asset disposal report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function disposalReport(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'currency' => 'nullable|string|size:3'
        ]);
        
        $fromDate = $request->from_date;
        $toDate = $request->to_date;
        $reportCurrency = $request->input('currency', config('app.base_currency', 'USD'));
        
        // For this example, we'll assume disposal_date is added to the model
        // In real implementation, you might track disposals in a separate table
        $disposedAssets = FixedAsset::where('status', 'Disposed')
            ->whereBetween('updated_at', [$fromDate, $toDate]) // This would be disposal_date
            ->get();
        
        $disposalData = $disposedAssets->map(function($asset) use ($reportCurrency) {
            $rate = $this->getExchangeRate($asset->currency, $reportCurrency, $asset->updated_at);
            
            $acquisitionCost = $asset->acquisition_cost * $rate;
            $currentValue = $asset->current_value * $rate;
            $accumulatedDepreciation = $acquisitionCost - $currentValue;
            
            // Disposal proceeds would come from disposal transaction
            $disposalProceeds = 0; // This would be fetched from disposal transaction
            $gainLoss = $disposalProceeds - $currentValue;
            
            return [
                'asset_code' => $asset->asset_code,
                'asset_name' => $asset->name,
                'category' => $asset->category,
                'acquisition_date' => $asset->acquisition_date,
                'disposal_date' => $asset->updated_at, // This would be actual disposal_date
                'acquisition_cost' => $acquisitionCost,
                'accumulated_depreciation' => $accumulatedDepreciation,
                'book_value_at_disposal' => $currentValue,
                'disposal_proceeds' => $disposalProceeds,
                'gain_loss' => $gainLoss,
                'gain_loss_type' => $gainLoss >= 0 ? 'gain' : 'loss'
            ];
        });
        
        return response()->json([
            'data' => $disposalData,
            'summary' => [
                'period' => ['from' => $fromDate, 'to' => $toDate],
                'currency' => $reportCurrency,
                'total_disposals' => $disposalData->count(),
                'total_acquisition_cost' => $disposalData->sum('acquisition_cost'),
                'total_disposal_proceeds' => $disposalData->sum('disposal_proceeds'),
                'total_gain_loss' => $disposalData->sum('gain_loss'),
                'gains_count' => $disposalData->where('gain_loss_type', 'gain')->count(),
                'losses_count' => $disposalData->where('gain_loss_type', 'loss')->count()
            ]
        ], 200);
    }

    /**
     * Get available currencies from fixed assets.
     *
     * @return \Illuminate\Http\Response
     */
    public function getAvailableCurrencies()
    {
        $currencies = FixedAsset::distinct()
            ->pluck('currency')
            ->filter()
            ->sort()
            ->values();
            
        return response()->json(['data' => $currencies], 200);
    }

    /**
     * Calculate depreciation summary for an asset.
     *
     * @param FixedAsset $asset
     * @return array
     */
    private function calculateDepreciationSummary($asset)
    {
        $totalDepreciation = $asset->assetDepreciations->sum('depreciation_amount');
        $accumulatedDepreciation = $asset->acquisition_cost - $asset->current_value;
        
        return [
            'total_recorded_depreciation' => $totalDepreciation,
            'accumulated_depreciation' => $accumulatedDepreciation,
            'remaining_depreciable_amount' => $asset->current_value - $asset->salvage_value,
            'depreciation_percentage' => $asset->acquisition_cost > 0 ? 
                ($accumulatedDepreciation / $asset->acquisition_cost) * 100 : 0,
            'years_elapsed' => now()->diffInYears($asset->acquisition_date),
            'estimated_remaining_life' => max(0, ($asset->useful_life_years ?? 10) - now()->diffInYears($asset->acquisition_date))
        ];
    }

    /**
     * Calculate depreciation to a specific date.
     *
     * @param FixedAsset $asset
     * @param string $date
     * @return float
     */
    private function calculateDepreciationToDate($asset, $date)
    {
        $yearsElapsed = now()->parse($date)->diffInYears($asset->acquisition_date, false);
        $depreciableAmount = $asset->acquisition_cost - $asset->salvage_value;
        
        return match($asset->depreciation_method) {
            'straight_line' => min(
                ($depreciableAmount / ($asset->useful_life_years ?? 10)) * $yearsElapsed,
                $depreciableAmount
            ),
            'declining_balance' => $asset->acquisition_cost * (1 - pow(1 - $asset->depreciation_rate / 100, $yearsElapsed)) - $asset->acquisition_cost,
            default => ($depreciableAmount / ($asset->useful_life_years ?? 10)) * $yearsElapsed
        };
    }

    /**
     * Get currency summary for an asset.
     *
     * @param FixedAsset $asset
     * @return array
     */
    private function getAssetCurrencySummary($asset)
    {
        $baseCurrency = config('app.base_currency', 'USD');
        
        return [
            'asset_currency' => $asset->currency,
            'base_currency' => $baseCurrency,
            'exchange_rate' => $asset->exchange_rate,
            'values' => [
                'original' => [
                    'currency' => $asset->currency,
                    'acquisition_cost' => $asset->acquisition_cost,
                    'current_value' => $asset->current_value,
                    'salvage_value' => $asset->salvage_value
                ],
                'base_currency' => [
                    'currency' => $baseCurrency,
                    'acquisition_cost' => $asset->base_currency_cost,
                    'current_value' => $asset->base_currency_current_value,
                    'salvage_value' => $asset->base_currency_salvage_value
                ]
            ],
            'depreciation' => [
                'method' => $asset->depreciation_method,
                'rate' => $asset->depreciation_rate . '%',
                'useful_life' => $asset->useful_life_years . ' years'
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