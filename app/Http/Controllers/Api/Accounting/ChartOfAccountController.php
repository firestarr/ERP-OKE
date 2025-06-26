<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\ChartOfAccount;
use App\Models\Accounting\JournalEntryLine;
use App\Models\SystemCurrency;
use App\Models\ExchangeRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ChartOfAccountController extends Controller
{
    /**
     * Display a listing of the chart of accounts.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $accounts = ChartOfAccount::with('parentAccount')
            ->orderBy('account_code')
            ->get();
            
        return response()->json(['data' => $accounts], 200);
    }

    /**
     * Store a newly created account in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_code' => 'required|string|max:50|unique:ChartOfAccount',
            'name' => 'required|string|max:100',
            'account_type' => 'required|string|max:50',
            'is_active' => 'boolean',
            'parent_account_id' => 'nullable|exists:ChartOfAccount,account_id',
            'default_currency' => 'nullable|string|size:3|exists:SystemCurrency,code',
            'allow_multi_currency' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $account = ChartOfAccount::create($request->all());

        return response()->json(['data' => $account, 'message' => 'Account created successfully'], 201);
    }

    /**
     * Display the specified account.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $account = ChartOfAccount::with(['parentAccount', 'childAccounts'])
            ->findOrFail($id);
            
        return response()->json(['data' => $account], 200);
    }

    /**
     * Update the specified account in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $account = ChartOfAccount::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'account_code' => 'string|max:50|unique:ChartOfAccount,account_code,' . $id . ',account_id',
            'name' => 'string|max:100',
            'account_type' => 'string|max:50',
            'is_active' => 'boolean',
            'parent_account_id' => 'nullable|exists:ChartOfAccount,account_id',
            'default_currency' => 'nullable|string|size:3|exists:SystemCurrency,code',
            'allow_multi_currency' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $account->update($request->all());

        return response()->json(['data' => $account, 'message' => 'Account updated successfully'], 200);
    }

    /**
     * Remove the specified account from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $account = ChartOfAccount::findOrFail($id);
        
        // Check if account has transactions
        $hasTransactions = JournalEntryLine::where('account_id', $id)->exists();
        
        if ($hasTransactions) {
            return response()->json([
                'message' => 'Cannot delete account that has transactions'
            ], 422);
        }

        $account->delete();

        return response()->json(['message' => 'Account deleted successfully'], 200);
    }

    /**
     * Get account balance in multiple currencies.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $accountId
     * @return \Illuminate\Http\Response
     */
    public function getBalanceInCurrencies(Request $request, $accountId)
    {
        $validator = Validator::make($request->all(), [
            'as_of_date' => 'nullable|date',
            'currencies' => 'nullable|array',
            'currencies.*' => 'string|size:3|exists:SystemCurrency,code'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $account = ChartOfAccount::findOrFail($accountId);
        $asOfDate = $request->input('as_of_date', now()->toDateString());
        $requestedCurrencies = $request->input('currencies', []);
        
        // If no currencies specified, get all active currencies
        if (empty($requestedCurrencies)) {
            $requestedCurrencies = SystemCurrency::where('is_active', true)
                ->pluck('code')
                ->toArray();
        }

        try {
            $balances = [];
            $baseCurrency = config('app.base_currency', 'USD');

            // Get base currency balance
            $baseBalance = $this->getAccountBalance($accountId, $asOfDate);
            
            foreach ($requestedCurrencies as $currency) {
                if ($currency === $baseCurrency) {
                    $balances[$currency] = [
                        'currency' => $currency,
                        'balance' => $baseBalance,
                        'exchange_rate' => 1,
                        'rate_date' => $asOfDate
                    ];
                } else {
                    $exchangeRate = $this->getExchangeRate($baseCurrency, $currency, $asOfDate);
                    
                    if ($exchangeRate) {
                        $convertedBalance = $baseBalance / $exchangeRate['rate'];
                        $balances[$currency] = [
                            'currency' => $currency,
                            'balance' => round($convertedBalance, 4),
                            'exchange_rate' => $exchangeRate['rate'],
                            'rate_date' => $exchangeRate['date']
                        ];
                    } else {
                        $balances[$currency] = [
                            'currency' => $currency,
                            'balance' => null,
                            'exchange_rate' => null,
                            'rate_date' => null,
                            'error' => 'Exchange rate not available'
                        ];
                    }
                }
            }

            return response()->json([
                'data' => [
                    'account' => $account,
                    'as_of_date' => $asOfDate,
                    'base_currency' => $baseCurrency,
                    'base_balance' => $baseBalance,
                    'balances' => $balances
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error calculating balances',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get trial balance in specific currency.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getTrialBalanceInCurrency(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'as_of_date' => 'nullable|date',
            'currency' => 'required|string|size:3|exists:SystemCurrency,code',
            'include_zero_balances' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $asOfDate = $request->input('as_of_date', now()->toDateString());
        $currency = $request->input('currency');
        $includeZeroBalances = $request->input('include_zero_balances', false);
        $baseCurrency = config('app.base_currency', 'USD');

        try {
            $accounts = ChartOfAccount::where('is_active', true)
                ->orderBy('account_code')
                ->get();

            $trialBalance = [];
            $totalDebits = 0;
            $totalCredits = 0;

            foreach ($accounts as $account) {
                $baseBalance = $this->getAccountBalance($account->account_id, $asOfDate);
                
                // Skip zero balances if not requested
                if (!$includeZeroBalances && $baseBalance == 0) {
                    continue;
                }

                $convertedBalance = $baseBalance;
                $exchangeRate = 1;
                $rateDate = $asOfDate;

                // Convert to target currency if different from base
                if ($currency !== $baseCurrency) {
                    $exchangeRateData = $this->getExchangeRate($baseCurrency, $currency, $asOfDate);
                    
                    if ($exchangeRateData) {
                        $convertedBalance = $baseBalance / $exchangeRateData['rate'];
                        $exchangeRate = $exchangeRateData['rate'];
                        $rateDate = $exchangeRateData['date'];
                    } else {
                        $convertedBalance = null;
                        $exchangeRate = null;
                    }
                }

                $debitAmount = $convertedBalance > 0 ? $convertedBalance : 0;
                $creditAmount = $convertedBalance < 0 ? abs($convertedBalance) : 0;

                $trialBalance[] = [
                    'account_id' => $account->account_id,
                    'account_code' => $account->account_code,
                    'account_name' => $account->name,
                    'account_type' => $account->account_type,
                    'base_balance' => $baseBalance,
                    'converted_balance' => $convertedBalance,
                    'debit_amount' => round($debitAmount, 4),
                    'credit_amount' => round($creditAmount, 4),
                    'exchange_rate' => $exchangeRate,
                    'rate_date' => $rateDate
                ];

                if ($convertedBalance !== null) {
                    $totalDebits += $debitAmount;
                    $totalCredits += $creditAmount;
                }
            }

            return response()->json([
                'data' => [
                    'as_of_date' => $asOfDate,
                    'currency' => $currency,
                    'base_currency' => $baseCurrency,
                    'accounts' => $trialBalance,
                    'summary' => [
                        'total_debits' => round($totalDebits, 4),
                        'total_credits' => round($totalCredits, 4),
                        'difference' => round($totalDebits - $totalCredits, 4)
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error generating trial balance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available currencies.
     *
     * @return \Illuminate\Http\Response
     */
    public function getAvailableCurrencies()
    {
        $currencies = SystemCurrency::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get(['code', 'name', 'symbol', 'decimal_places']);

        return response()->json(['data' => $currencies], 200);
    }

    /**
     * Convert account balance to specified currency.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function convertBalance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_id' => 'required|exists:ChartOfAccount,account_id',
            'from_currency' => 'required|string|size:3|exists:SystemCurrency,code',
            'to_currency' => 'required|string|size:3|exists:SystemCurrency,code',
            'amount' => 'required|numeric',
            'exchange_date' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $fromCurrency = $request->input('from_currency');
        $toCurrency = $request->input('to_currency');
        $amount = $request->input('amount');
        $exchangeDate = $request->input('exchange_date', now()->toDateString());

        try {
            // If same currency, no conversion needed
            if ($fromCurrency === $toCurrency) {
                return response()->json([
                    'data' => [
                        'original_amount' => $amount,
                        'converted_amount' => $amount,
                        'from_currency' => $fromCurrency,
                        'to_currency' => $toCurrency,
                        'exchange_rate' => 1,
                        'exchange_date' => $exchangeDate
                    ]
                ], 200);
            }

            $exchangeRateData = $this->getExchangeRate($fromCurrency, $toCurrency, $exchangeDate);
            
            if (!$exchangeRateData) {
                return response()->json([
                    'message' => 'Exchange rate not available for the specified date and currencies'
                ], 404);
            }

            $convertedAmount = $amount * $exchangeRateData['rate'];

            return response()->json([
                'data' => [
                    'original_amount' => $amount,
                    'converted_amount' => round($convertedAmount, 4),
                    'from_currency' => $fromCurrency,
                    'to_currency' => $toCurrency,
                    'exchange_rate' => $exchangeRateData['rate'],
                    'exchange_date' => $exchangeRateData['date']
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error converting balance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get account hierarchy with multi-currency balances.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function hierarchyWithCurrencies(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'currency' => 'nullable|string|size:3|exists:SystemCurrency,code',
            'as_of_date' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $currency = $request->input('currency', config('app.base_currency', 'USD'));
        $asOfDate = $request->input('as_of_date', now()->toDateString());

        try {
            $accounts = ChartOfAccount::with('parentAccount')
                ->where('is_active', true)
                ->orderBy('account_code')
                ->get();

            $hierarchy = [];
            $baseCurrency = config('app.base_currency', 'USD');

            foreach ($accounts as $account) {
                $baseBalance = $this->getAccountBalance($account->account_id, $asOfDate);
                $convertedBalance = $baseBalance;
                $exchangeRate = 1;

                // Convert to target currency if different from base
                if ($currency !== $baseCurrency) {
                    $exchangeRateData = $this->getExchangeRate($baseCurrency, $currency, $asOfDate);
                    
                    if ($exchangeRateData) {
                        $convertedBalance = $baseBalance / $exchangeRateData['rate'];
                        $exchangeRate = $exchangeRateData['rate'];
                    }
                }

                $hierarchy[] = [
                    'account_id' => $account->account_id,
                    'account_code' => $account->account_code,
                    'name' => $account->name,
                    'account_type' => $account->account_type,
                    'parent_account_id' => $account->parent_account_id,
                    'is_active' => $account->is_active,
                    'base_balance' => $baseBalance,
                    'balance_in_currency' => round($convertedBalance, 4),
                    'currency' => $currency,
                    'exchange_rate' => $exchangeRate,
                    'parent_account' => $account->parentAccount
                ];
            }

            return response()->json([
                'data' => [
                    'currency' => $currency,
                    'as_of_date' => $asOfDate,
                    'accounts' => $hierarchy
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error getting hierarchy with currencies',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get account balance as of specific date.
     *
     * @param  int  $accountId
     * @param  string  $asOfDate
     * @return float
     */
    private function getAccountBalance($accountId, $asOfDate)
    {
        $debits = JournalEntryLine::join('JournalEntry', 'JournalEntryLine.journal_id', '=', 'JournalEntry.journal_id')
            ->where('JournalEntryLine.account_id', $accountId)
            ->where('JournalEntry.entry_date', '<=', $asOfDate)
            ->where('JournalEntry.status', 'Posted')
            ->sum('JournalEntryLine.debit_amount');

        $credits = JournalEntryLine::join('JournalEntry', 'JournalEntryLine.journal_id', '=', 'JournalEntry.journal_id')
            ->where('JournalEntryLine.account_id', $accountId)
            ->where('JournalEntry.entry_date', '<=', $asOfDate)
            ->where('JournalEntry.status', 'Posted')
            ->sum('JournalEntryLine.credit_amount');

        return $debits - $credits;
    }

    /**
     * Get exchange rate between two currencies.
     *
     * @param  string  $fromCurrency
     * @param  string  $toCurrency
     * @param  string  $date
     * @return array|null
     */
    private function getExchangeRate($fromCurrency, $toCurrency, $date)
    {
        // If same currency, rate is 1
        if ($fromCurrency === $toCurrency) {
            return [
                'rate' => 1,
                'date' => $date
            ];
        }

        // Get latest rate before or on the requested date
        $rate = ExchangeRate::where('from_currency', $fromCurrency)
            ->where('to_currency', $toCurrency)
            ->where('rate_date', '<=', $date)
            ->orderBy('rate_date', 'desc')
            ->first();

        if ($rate) {
            return [
                'rate' => $rate->rate,
                'date' => $rate->rate_date
            ];
        }

        // Try reverse rate
        $reverseRate = ExchangeRate::where('from_currency', $toCurrency)
            ->where('to_currency', $fromCurrency)
            ->where('rate_date', '<=', $date)
            ->orderBy('rate_date', 'desc')
            ->first();

        if ($reverseRate) {
            return [
                'rate' => 1 / $reverseRate->rate,
                'date' => $reverseRate->rate_date
            ];
        }

        return null;
    }

    /**
     * Get balances summary across all currencies.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getBalancesSummary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'as_of_date' => 'nullable|date',
            'base_currency' => 'nullable|string|size:3|exists:SystemCurrency,code'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $asOfDate = $request->input('as_of_date', now()->toDateString());
        $baseCurrency = $request->input('base_currency', config('app.base_currency', 'USD'));

        try {
            $currencies = SystemCurrency::where('is_active', true)->get();
            $summary = [];
            $totalBaseCurrencyValue = 0;

            foreach ($currencies as $currency) {
                $currencyTotal = 0;
                $accounts = ChartOfAccount::where('is_active', true)->get();

                foreach ($accounts as $account) {
                    $baseBalance = $this->getAccountBalance($account->account_id, $asOfDate);
                    
                    if ($currency->code === $baseCurrency) {
                        $currencyTotal += $baseBalance;
                    } else {
                        $exchangeRateData = $this->getExchangeRate($baseCurrency, $currency->code, $asOfDate);
                        if ($exchangeRateData) {
                            $currencyTotal += $baseBalance / $exchangeRateData['rate'];
                        }
                    }
                }

                // Convert back to base currency for total calculation
                if ($currency->code !== $baseCurrency) {
                    $exchangeRateData = $this->getExchangeRate($currency->code, $baseCurrency, $asOfDate);
                    if ($exchangeRateData) {
                        $totalBaseCurrencyValue += $currencyTotal * $exchangeRateData['rate'];
                    }
                } else {
                    $totalBaseCurrencyValue += $currencyTotal;
                }

                $summary[] = [
                    'currency' => $currency->code,
                    'currency_name' => $currency->name,
                    'symbol' => $currency->symbol,
                    'total_balance' => round($currencyTotal, $currency->decimal_places),
                    'decimal_places' => $currency->decimal_places
                ];
            }

            return response()->json([
                'data' => [
                    'as_of_date' => $asOfDate,
                    'base_currency' => $baseCurrency,
                    'total_base_currency_value' => round($totalBaseCurrencyValue, 2),
                    'currency_summaries' => $summary
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error generating balances summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get currency exposure report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getCurrencyExposure(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'as_of_date' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $asOfDate = $request->input('as_of_date', now()->toDateString());
        $baseCurrency = config('app.base_currency', 'USD');

        try {
            // Get accounts that have foreign currency transactions
            $foreignCurrencyAccounts = JournalEntryLine::join('JournalEntry', 'JournalEntryLine.journal_id', '=', 'JournalEntry.journal_id')
                ->join('ChartOfAccount', 'JournalEntryLine.account_id', '=', 'ChartOfAccount.account_id')
                ->where('JournalEntry.entry_date', '<=', $asOfDate)
                ->where('JournalEntry.status', 'Posted')
                ->whereNotNull('JournalEntryLine.currency')
                ->where('JournalEntryLine.currency', '!=', $baseCurrency)
                ->select('ChartOfAccount.*', 'JournalEntryLine.currency')
                ->distinct()
                ->get();

            $exposures = [];

            foreach ($foreignCurrencyAccounts as $account) {
                $currency = $account->currency;
                
                // Calculate foreign currency balance
                $foreignDebits = JournalEntryLine::join('JournalEntry', 'JournalEntryLine.journal_id', '=', 'JournalEntry.journal_id')
                    ->where('JournalEntryLine.account_id', $account->account_id)
                    ->where('JournalEntry.entry_date', '<=', $asOfDate)
                    ->where('JournalEntry.status', 'Posted')
                    ->where('JournalEntryLine.currency', $currency)
                    ->sum('JournalEntryLine.foreign_amount');

                $foreignCredits = JournalEntryLine::join('JournalEntry', 'JournalEntryLine.journal_id', '=', 'JournalEntry.journal_id')
                    ->where('JournalEntryLine.account_id', $account->account_id)
                    ->where('JournalEntry.entry_date', '<=', $asOfDate)
                    ->where('JournalEntry.status', 'Posted')
                    ->where('JournalEntryLine.currency', $currency)
                    ->where('JournalEntryLine.debit_amount', 0)
                    ->sum('JournalEntryLine.foreign_amount');

                $foreignBalance = $foreignDebits - $foreignCredits;

                if ($foreignBalance != 0) {
                    // Get current exchange rate
                    $currentRate = $this->getExchangeRate($currency, $baseCurrency, $asOfDate);
                    
                    // Get original exchange rate (average of transactions)
                    $originalRate = JournalEntryLine::join('JournalEntry', 'JournalEntryLine.journal_id', '=', 'JournalEntry.journal_id')
                        ->where('JournalEntryLine.account_id', $account->account_id)
                        ->where('JournalEntry.entry_date', '<=', $asOfDate)
                        ->where('JournalEntry.status', 'Posted')
                        ->where('JournalEntryLine.currency', $currency)
                        ->whereNotNull('JournalEntryLine.exchange_rate')
                        ->avg('JournalEntryLine.exchange_rate');

                    $currentBaseCurrencyValue = $currentRate ? $foreignBalance * $currentRate['rate'] : 0;
                    $originalBaseCurrencyValue = $originalRate ? $foreignBalance * $originalRate : 0;
                    $exposureAmount = $currentBaseCurrencyValue - $originalBaseCurrencyValue;

                    $exposures[] = [
                        'account_id' => $account->account_id,
                        'account_code' => $account->account_code,
                        'account_name' => $account->name,
                        'foreign_currency' => $currency,
                        'foreign_balance' => round($foreignBalance, 4),
                        'original_exchange_rate' => round($originalRate ?: 0, 6),
                        'current_exchange_rate' => $currentRate ? round($currentRate['rate'], 6) : 0,
                        'original_base_value' => round($originalBaseCurrencyValue, 2),
                        'current_base_value' => round($currentBaseCurrencyValue, 2),
                        'exposure_amount' => round($exposureAmount, 2),
                        'exposure_percentage' => $originalBaseCurrencyValue != 0 ? 
                            round(($exposureAmount / $originalBaseCurrencyValue) * 100, 2) : 0
                    ];
                }
            }

            $totalExposure = array_sum(array_column($exposures, 'exposure_amount'));

            return response()->json([
                'data' => [
                    'as_of_date' => $asOfDate,
                    'base_currency' => $baseCurrency,
                    'total_exposure' => round($totalExposure, 2),
                    'account_exposures' => $exposures
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error generating currency exposure report',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}