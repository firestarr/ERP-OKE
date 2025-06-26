<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Accounting\ExchangeRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class JournalEntryController extends Controller
{
    /**
     * Display a listing of the journal entries.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = JournalEntry::with(['accountingPeriod', 'journalEntryLines.chartOfAccount']);
        
        // Filter by date range
        if ($request->has('from_date') && $request->has('to_date')) {
            $query->whereBetween('entry_date', [$request->from_date, $request->to_date]);
        }
        
        // Filter by period
        if ($request->has('period_id')) {
            $query->where('period_id', $request->period_id);
        }
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Filter by currency (filter entries that have lines with specific currency)
        if ($request->has('currency')) {
            $query->whereHas('journalEntryLines', function($q) use ($request) {
                $q->where('currency', $request->currency);
            });
        }
        
        // Filter by reference type
        if ($request->has('reference_type')) {
            $query->where('reference_type', $request->reference_type);
        }
        
        $journalEntries = $query->orderBy('entry_date', 'desc')
            ->orderBy('journal_number', 'desc')
            ->paginate($request->input('per_page', 15));
        
        return response()->json($journalEntries, 200);
    }

    /**
     * Store a newly created journal entry in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'journal_number' => 'required|string|max:50|unique:JournalEntry',
            'entry_date' => 'required|date',
            'reference_type' => 'nullable|string|max:50',
            'reference_id' => 'nullable|integer',
            'description' => 'nullable|string',
            'period_id' => 'required|exists:AccountingPeriod,period_id',
            'status' => 'required|string|max:50',
            'lines' => 'required|array|min:1',
            'lines.*.account_id' => 'required|exists:ChartOfAccount,account_id',
            'lines.*.debit_amount' => 'required_without:lines.*.credit_amount|numeric|min:0',
            'lines.*.credit_amount' => 'required_without:lines.*.debit_amount|numeric|min:0',
            'lines.*.description' => 'nullable|string',
            'lines.*.currency' => 'nullable|string|size:3',
            'lines.*.foreign_amount' => 'nullable|numeric',
            'lines.*.exchange_rate' => 'nullable|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        DB::beginTransaction();
        
        try {
            $baseCurrency = config('app.base_currency', 'USD');
            
            // Validate multi-currency rules
            $validationResult = $this->validateMultiCurrencyEntry($request->lines, $baseCurrency, $request->entry_date);
            if (!$validationResult['valid']) {
                return response()->json(['message' => $validationResult['message']], 422);
            }
            
            // Validate total debits = total credits (in base currency)
            $totalDebits = 0;
            $totalCredits = 0;
            
            foreach ($request->lines as $line) {
                $currency = $line['currency'] ?? $baseCurrency;
                $exchangeRate = $this->getExchangeRate($currency, $baseCurrency, $request->entry_date);
                
                $baseDebitAmount = ($line['debit_amount'] ?? 0) * $exchangeRate;
                $baseCreditAmount = ($line['credit_amount'] ?? 0) * $exchangeRate;
                
                $totalDebits += $baseDebitAmount;
                $totalCredits += $baseCreditAmount;
            }
            
            if (abs($totalDebits - $totalCredits) > 0.01) {
                return response()->json([
                    'message' => 'Total debits must equal total credits in base currency'
                ], 422);
            }

            // Create journal entry
            $journalEntry = JournalEntry::create([
                'journal_number' => $request->journal_number,
                'entry_date' => $request->entry_date,
                'reference_type' => $request->reference_type,
                'reference_id' => $request->reference_id,
                'description' => $request->description,
                'period_id' => $request->period_id,
                'status' => $request->status
            ]);

            // Create journal entry lines
            foreach ($request->lines as $line) {
                $currency = $line['currency'] ?? $baseCurrency;
                $exchangeRate = $this->getExchangeRate($currency, $baseCurrency, $request->entry_date);
                
                // Calculate base currency amounts
                $baseDebitAmount = ($line['debit_amount'] ?? 0) * $exchangeRate;
                $baseCreditAmount = ($line['credit_amount'] ?? 0) * $exchangeRate;
                
                JournalEntryLine::create([
                    'journal_id' => $journalEntry->journal_id,
                    'account_id' => $line['account_id'],
                    'debit_amount' => $baseDebitAmount,
                    'credit_amount' => $baseCreditAmount,
                    'description' => $line['description'] ?? '',
                    'currency' => $currency,
                    'foreign_amount' => $currency !== $baseCurrency ? ($line['debit_amount'] ?? $line['credit_amount']) : null,
                    'exchange_rate' => $currency !== $baseCurrency ? $exchangeRate : null
                ]);
            }

            DB::commit();

            return response()->json([
                'data' => $journalEntry->load('journalEntryLines.chartOfAccount'), 
                'message' => 'Journal entry created successfully'
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create journal entry: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified journal entry.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $journalEntry = JournalEntry::with([
            'accountingPeriod',
            'journalEntryLines.chartOfAccount'
        ])->findOrFail($id);
        
        // Add currency summary
        $currencySummary = $this->getCurrencySummary($journalEntry);
        
        return response()->json([
            'data' => $journalEntry,
            'currency_summary' => $currencySummary
        ], 200);
    }

    /**
     * Update the specified journal entry in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $journalEntry = JournalEntry::findOrFail($id);
        
        // Don't allow updates if entry is posted
        if ($journalEntry->status === 'Posted') {
            return response()->json(['message' => 'Cannot update posted journal entry'], 422);
        }

        $validator = Validator::make($request->all(), [
            'journal_number' => 'string|max:50|unique:JournalEntry,journal_number,' . $id . ',journal_id',
            'entry_date' => 'date',
            'reference_type' => 'nullable|string|max:50',
            'reference_id' => 'nullable|integer',
            'description' => 'nullable|string',
            'period_id' => 'exists:AccountingPeriod,period_id',
            'status' => 'string|max:50',
            'lines' => 'array|min:1',
            'lines.*.account_id' => 'required|exists:ChartOfAccount,account_id',
            'lines.*.debit_amount' => 'required_without:lines.*.credit_amount|numeric|min:0',
            'lines.*.credit_amount' => 'required_without:lines.*.debit_amount|numeric|min:0',
            'lines.*.description' => 'nullable|string',
            'lines.*.currency' => 'nullable|string|size:3',
            'lines.*.foreign_amount' => 'nullable|numeric',
            'lines.*.exchange_rate' => 'nullable|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        
        try {
            $baseCurrency = config('app.base_currency', 'USD');
            
            // If lines are provided, validate and update them
            if ($request->has('lines')) {
                // Validate multi-currency rules
                $validationResult = $this->validateMultiCurrencyEntry($request->lines, $baseCurrency, $request->entry_date ?? $journalEntry->entry_date);
                if (!$validationResult['valid']) {
                    return response()->json(['message' => $validationResult['message']], 422);
                }
                
                // Validate total debits = total credits
                $totalDebits = 0;
                $totalCredits = 0;
                
                foreach ($request->lines as $line) {
                    $currency = $line['currency'] ?? $baseCurrency;
                    $exchangeRate = $this->getExchangeRate($currency, $baseCurrency, $request->entry_date ?? $journalEntry->entry_date);
                    
                    $baseDebitAmount = ($line['debit_amount'] ?? 0) * $exchangeRate;
                    $baseCreditAmount = ($line['credit_amount'] ?? 0) * $exchangeRate;
                    
                    $totalDebits += $baseDebitAmount;
                    $totalCredits += $baseCreditAmount;
                }
                
                if (abs($totalDebits - $totalCredits) > 0.01) {
                    return response()->json([
                        'message' => 'Total debits must equal total credits in base currency'
                    ], 422);
                }

                // Delete existing lines
                $journalEntry->journalEntryLines()->delete();

                // Create new lines
                foreach ($request->lines as $line) {
                    $currency = $line['currency'] ?? $baseCurrency;
                    $exchangeRate = $this->getExchangeRate($currency, $baseCurrency, $request->entry_date ?? $journalEntry->entry_date);
                    
                    $baseDebitAmount = ($line['debit_amount'] ?? 0) * $exchangeRate;
                    $baseCreditAmount = ($line['credit_amount'] ?? 0) * $exchangeRate;
                    
                    JournalEntryLine::create([
                        'journal_id' => $journalEntry->journal_id,
                        'account_id' => $line['account_id'],
                        'debit_amount' => $baseDebitAmount,
                        'credit_amount' => $baseCreditAmount,
                        'description' => $line['description'] ?? '',
                        'currency' => $currency,
                        'foreign_amount' => $currency !== $baseCurrency ? ($line['debit_amount'] ?? $line['credit_amount']) : null,
                        'exchange_rate' => $currency !== $baseCurrency ? $exchangeRate : null
                    ]);
                }
            }

            // Update journal entry
            $journalEntry->update($request->only([
                'journal_number', 'entry_date', 'reference_type', 
                'reference_id', 'description', 'period_id', 'status'
            ]));

            DB::commit();

            return response()->json([
                'data' => $journalEntry->load('journalEntryLines.chartOfAccount'), 
                'message' => 'Journal entry updated successfully'
            ], 200);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update journal entry: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified journal entry from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $journalEntry = JournalEntry::findOrFail($id);
        
        if ($journalEntry->status === 'Posted') {
            return response()->json(['message' => 'Cannot delete posted journal entry'], 422);
        }

        $journalEntry->delete();

        return response()->json(['message' => 'Journal entry deleted successfully'], 200);
    }

    /**
     * Post the specified journal entry.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function post($id)
    {
        $journalEntry = JournalEntry::with('journalEntryLines')->findOrFail($id);
        
        if ($journalEntry->status === 'Posted') {
            return response()->json(['message' => 'Journal entry is already posted'], 422);
        }

        // Validate that debits equal credits
        $totalDebits = $journalEntry->journalEntryLines->sum('debit_amount');
        $totalCredits = $journalEntry->journalEntryLines->sum('credit_amount');
        
        if (abs($totalDebits - $totalCredits) > 0.01) {
            return response()->json(['message' => 'Cannot post unbalanced entry'], 422);
        }

        $journalEntry->update(['status' => 'Posted']);

        return response()->json([
            'data' => $journalEntry, 
            'message' => 'Journal entry posted successfully'
        ], 200);
    }

    /**
     * Get available currencies for journal entries.
     *
     * @return \Illuminate\Http\Response
     */
    public function getAvailableCurrencies()
    {
        $currencies = DB::table('system_currencies')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['code', 'name', 'symbol']);
            
        return response()->json(['data' => $currencies], 200);
    }

    /**
     * Get exchange rate for a specific currency and date.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getExchangeRateForDate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from_currency' => 'required|string|size:3',
            'to_currency' => 'required|string|size:3',
            'date' => 'required|date'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $rate = $this->getExchangeRate(
            $request->from_currency, 
            $request->to_currency, 
            $request->date
        );

        return response()->json([
            'data' => [
                'from_currency' => $request->from_currency,
                'to_currency' => $request->to_currency,
                'date' => $request->date,
                'rate' => $rate
            ]
        ], 200);
    }

    /**
     * Convert journal entry amounts to different currency.
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

        $journalEntry = JournalEntry::with('journalEntryLines.chartOfAccount')->findOrFail($id);
        $targetCurrency = $request->target_currency;
        $conversionDate = $request->conversion_date ?? $journalEntry->entry_date;
        $baseCurrency = config('app.base_currency', 'USD');

        $convertedLines = $journalEntry->journalEntryLines->map(function($line) use ($targetCurrency, $conversionDate, $baseCurrency) {
            $rate = $this->getExchangeRate($baseCurrency, $targetCurrency, $conversionDate);
            
            return [
                'account' => $line->chartOfAccount,
                'description' => $line->description,
                'original_debit' => $line->debit_amount,
                'original_credit' => $line->credit_amount,
                'converted_debit' => $line->debit_amount * $rate,
                'converted_credit' => $line->credit_amount * $rate,
                'original_currency' => $line->currency ?? $baseCurrency,
                'converted_currency' => $targetCurrency,
                'exchange_rate' => $rate
            ];
        });

        return response()->json([
            'data' => [
                'journal_entry' => $journalEntry,
                'converted_lines' => $convertedLines,
                'conversion_summary' => [
                    'target_currency' => $targetCurrency,
                    'conversion_date' => $conversionDate,
                    'total_debit' => $convertedLines->sum('converted_debit'),
                    'total_credit' => $convertedLines->sum('converted_credit')
                ]
            ]
        ], 200);
    }

    /**
     * Validate multi-currency journal entry rules.
     *
     * @param  array  $lines
     * @param  string  $baseCurrency
     * @param  string  $entryDate
     * @return array
     */
    private function validateMultiCurrencyEntry($lines, $baseCurrency, $entryDate)
    {
        $currencies = collect($lines)->pluck('currency')->filter()->unique();
        
        // Check if all foreign currencies have valid exchange rates
        foreach ($currencies as $currency) {
            if ($currency !== $baseCurrency) {
                $rate = $this->getExchangeRate($currency, $baseCurrency, $entryDate);
                if ($rate <= 0) {
                    return [
                        'valid' => false,
                        'message' => "No valid exchange rate found for {$currency} on {$entryDate}"
                    ];
                }
            }
        }

        // Check that foreign currency lines have foreign_amount
        foreach ($lines as $line) {
            $currency = $line['currency'] ?? $baseCurrency;
            if ($currency !== $baseCurrency) {
                if (!isset($line['foreign_amount']) || $line['foreign_amount'] <= 0) {
                    return [
                        'valid' => false,
                        'message' => "Foreign amount is required for {$currency} transactions"
                    ];
                }
            }
        }

        return ['valid' => true];
    }

    /**
     * Get exchange rate for currency conversion.
     *
     * @param  string  $fromCurrency
     * @param  string  $toCurrency
     * @param  string  $date
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

    /**
     * Get currency summary for journal entry.
     *
     * @param  JournalEntry  $journalEntry
     * @return array
     */
    private function getCurrencySummary($journalEntry)
    {
        $baseCurrency = config('app.base_currency', 'USD');
        $summary = [];

        $currencies = $journalEntry->journalEntryLines
            ->pluck('currency')
            ->filter()
            ->unique()
            ->values();

        if ($currencies->isEmpty()) {
            $currencies = collect([$baseCurrency]);
        }

        foreach ($currencies as $currency) {
            $lines = $journalEntry->journalEntryLines->where('currency', $currency);
            
            $summary[] = [
                'currency' => $currency,
                'total_debit' => $lines->sum('debit_amount'),
                'total_credit' => $lines->sum('credit_amount'),
                'is_base_currency' => $currency === $baseCurrency
            ];
        }

        return $summary;
    }

    /**
     * Helper method to get the current accounting period ID.
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
}