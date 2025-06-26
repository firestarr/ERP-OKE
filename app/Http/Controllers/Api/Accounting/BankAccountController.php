<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\BankAccount;
use App\Models\Accounting\ChartOfAccount;
use App\Models\Accounting\ExchangeRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BankAccountController extends Controller
{
    /**
     * Display a listing of bank accounts.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = BankAccount::with('chartOfAccount');
        
        // Filter by currency
        if ($request->filled('currency')) {
            $query->where('currency', $request->currency);
        }
        
        // Filter by bank name
        if ($request->filled('bank_name')) {
            $query->where('bank_name', 'like', '%' . $request->bank_name . '%');
        }
        
        // Filter by status
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }
        
        $bankAccounts = $query->orderBy('bank_name')
            ->orderBy('account_name')
            ->paginate($request->input('per_page', 15));
        
        // Add currency conversion if requested
        if ($request->filled('display_currency')) {
            $displayCurrency = $request->display_currency;
            $conversionDate = $request->input('conversion_date', now()->toDateString());
            
            $bankAccounts->getCollection()->transform(function ($account) use ($displayCurrency, $conversionDate) {
                if ($account->currency !== $displayCurrency) {
                    $rate = $this->getExchangeRate($account->currency, $displayCurrency, $conversionDate);
                    $account->converted_balance = $account->current_balance * $rate;
                    $account->display_currency = $displayCurrency;
                    $account->exchange_rate = $rate;
                }
                return $account;
            });
        }
        
        return response()->json($bankAccounts, 200);
    }

    /**
     * Store a newly created bank account in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bank_name' => 'required|string|max:100',
            'account_number' => 'required|string|max:50',
            'account_name' => 'required|string|max:100',
            'currency' => 'required|string|size:3',
            'current_balance' => 'required|numeric',
            'gl_account_id' => 'required|exists:ChartOfAccount,account_id',
            'bank_address' => 'nullable|string',
            'swift_code' => 'nullable|string|max:11',
            'iban' => 'nullable|string|max:34',
            'bank_contact' => 'nullable|string',
            'is_active' => 'boolean',
            'overdraft_limit' => 'nullable|numeric|min:0',
            'minimum_balance' => 'nullable|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Verify that the GL account is of type Asset
        $glAccount = ChartOfAccount::findOrFail($request->gl_account_id);
        if ($glAccount->account_type !== 'Asset') {
            return response()->json([
                'message' => 'The selected GL account must be of type Asset'
            ], 422);
        }
        
        // Check if account number already exists for the same bank
        $exists = BankAccount::where('bank_name', $request->bank_name)
            ->where('account_number', $request->account_number)
            ->exists();
            
        if ($exists) {
            return response()->json([
                'message' => 'Account number already exists for this bank'
            ], 422);
        }
        
        $baseCurrency = config('app.base_currency', 'USD');
        $currency = $request->currency;
        
        // Calculate base currency equivalent
        $exchangeRate = $this->getExchangeRate($currency, $baseCurrency, now()->toDateString());
        $baseCurrencyBalance = $request->current_balance * $exchangeRate;

        $bankAccount = BankAccount::create([
            'bank_name' => $request->bank_name,
            'account_number' => $request->account_number,
            'account_name' => $request->account_name,
            'currency' => $currency,
            'current_balance' => $request->current_balance,
            'base_currency_balance' => $baseCurrencyBalance,
            'exchange_rate' => $exchangeRate,
            'gl_account_id' => $request->gl_account_id,
            'bank_address' => $request->bank_address,
            'swift_code' => $request->swift_code,
            'iban' => $request->iban,
            'bank_contact' => $request->bank_contact,
            'is_active' => $request->input('is_active', true),
            'overdraft_limit' => $request->overdraft_limit ?? 0,
            'minimum_balance' => $request->minimum_balance ?? 0
        ]);

        return response()->json([
            'data' => $bankAccount->load('chartOfAccount'), 
            'message' => 'Bank account created successfully'
        ], 201);
    }

    /**
     * Display the specified bank account.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $bankAccount = BankAccount::with([
            'chartOfAccount',
            'bankReconciliations' => function($query) {
                $query->orderBy('statement_date', 'desc')->limit(5);
            }
        ])->findOrFail($id);
        
        // Add currency summary
        $currencySummary = $this->getBankAccountCurrencySummary($bankAccount);
        
        return response()->json([
            'data' => $bankAccount,
            'currency_summary' => $currencySummary
        ], 200);
    }

    /**
     * Update the specified bank account in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $bankAccount = BankAccount::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'bank_name' => 'string|max:100',
            'account_number' => 'string|max:50',
            'account_name' => 'string|max:100',
            'currency' => 'string|size:3',
            'current_balance' => 'numeric',
            'gl_account_id' => 'exists:ChartOfAccount,account_id',
            'bank_address' => 'nullable|string',
            'swift_code' => 'nullable|string|max:11',
            'iban' => 'nullable|string|max:34',
            'bank_contact' => 'nullable|string',
            'is_active' => 'boolean',
            'overdraft_limit' => 'nullable|numeric|min:0',
            'minimum_balance' => 'nullable|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Check if account number already exists for the same bank (excluding current account)
        if ($request->has('account_number') || $request->has('bank_name')) {
            $bankName = $request->bank_name ?? $bankAccount->bank_name;
            $accountNumber = $request->account_number ?? $bankAccount->account_number;
            
            $exists = BankAccount::where('bank_name', $bankName)
                ->where('account_number', $accountNumber)
                ->where('bank_id', '!=', $id)
                ->exists();
                
            if ($exists) {
                return response()->json([
                    'message' => 'Account number already exists for this bank'
                ], 422);
            }
        }
        
        $baseCurrency = config('app.base_currency', 'USD');
        
        // Recalculate base currency balance if currency or balance changed
        if ($request->has('current_balance') || $request->has('currency')) {
            $currency = $request->currency ?? $bankAccount->currency;
            $balance = $request->current_balance ?? $bankAccount->current_balance;
            
            $exchangeRate = $this->getExchangeRate($currency, $baseCurrency, now()->toDateString());
            $baseCurrencyBalance = $balance * $exchangeRate;
            
            $request->merge([
                'base_currency_balance' => $baseCurrencyBalance,
                'exchange_rate' => $exchangeRate
            ]);
        }

        $bankAccount->update($request->only([
            'bank_name', 'account_number', 'account_name', 'currency',
            'current_balance', 'base_currency_balance', 'exchange_rate',
            'gl_account_id', 'bank_address', 'swift_code', 'iban',
            'bank_contact', 'is_active', 'overdraft_limit', 'minimum_balance'
        ]));

        return response()->json([
            'data' => $bankAccount->load('chartOfAccount'), 
            'message' => 'Bank account updated successfully'
        ], 200);
    }

    /**
     * Remove the specified bank account from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $bankAccount = BankAccount::findOrFail($id);
        
        // Check if there are bank reconciliations
        if ($bankAccount->bankReconciliations()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete bank account with recorded reconciliations'
            ], 422);
        }
        
        // Check if balance is not zero
        if ($bankAccount->current_balance != 0) {
            return response()->json([
                'message' => 'Cannot delete bank account with non-zero balance'
            ], 422);
        }
        
        $bankAccount->delete();

        return response()->json(['message' => 'Bank account deleted successfully'], 200);
    }

    /**
     * Get bank accounts summary by currency.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function currencySummary(Request $request)
    {
        $query = BankAccount::where('is_active', true);
        
        // Filter by bank if specified
        if ($request->filled('bank_name')) {
            $query->where('bank_name', $request->bank_name);
        }
        
        $accounts = $query->get();
        
        $summary = $accounts->groupBy('currency')->map(function($group, $currency) {
            $totalBalance = $group->sum('current_balance');
            $totalBaseCurrencyBalance = $group->sum('base_currency_balance');
            $accountCount = $group->count();
            
            return [
                'currency' => $currency,
                'account_count' => $accountCount,
                'total_balance' => $totalBalance,
                'base_currency_total' => $totalBaseCurrencyBalance,
                'average_balance' => $accountCount > 0 ? $totalBalance / $accountCount : 0,
                'accounts' => $group->map(function($account) {
                    return [
                        'bank_id' => $account->bank_id,
                        'bank_name' => $account->bank_name,
                        'account_name' => $account->account_name,
                        'account_number' => $account->account_number,
                        'current_balance' => $account->current_balance,
                        'overdraft_limit' => $account->overdraft_limit,
                        'available_balance' => $account->current_balance + $account->overdraft_limit
                    ];
                })
            ];
        });
        
        $baseCurrency = config('app.base_currency', 'USD');
        $grandTotal = $accounts->sum('base_currency_balance');
        
        return response()->json([
            'data' => $summary,
            'grand_total' => [
                'base_currency' => $baseCurrency,
                'total_accounts' => $accounts->count(),
                'total_balance' => $grandTotal,
                'currencies_count' => $summary->count(),
                'active_accounts' => $accounts->where('is_active', true)->count()
            ]
        ], 200);
    }

    /**
     * Convert bank account balance to different currency.
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

        $bankAccount = BankAccount::findOrFail($id);
        $targetCurrency = $request->target_currency;
        $conversionDate = $request->conversion_date ?? now()->toDateString();

        $exchangeRate = $this->getExchangeRate($bankAccount->currency, $targetCurrency, $conversionDate);
        $convertedBalance = $bankAccount->current_balance * $exchangeRate;

        return response()->json([
            'data' => [
                'bank_account' => $bankAccount,
                'original_currency' => $bankAccount->currency,
                'target_currency' => $targetCurrency,
                'exchange_rate' => $exchangeRate,
                'conversion_date' => $conversionDate,
                'original_balance' => $bankAccount->current_balance,
                'converted_balance' => $convertedBalance,
                'overdraft_limit' => $bankAccount->overdraft_limit * $exchangeRate,
                'available_balance' => ($bankAccount->current_balance + $bankAccount->overdraft_limit) * $exchangeRate
            ]
        ], 200);
    }

    /**
     * Get cash position across all bank accounts.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function cashPosition(Request $request)
    {
        $currency = $request->input('currency', config('app.base_currency', 'USD'));
        $conversionDate = $request->input('conversion_date', now()->toDateString());
        
        $accounts = BankAccount::where('is_active', true)->get();
        
        $cashPosition = $accounts->map(function($account) use ($currency, $conversionDate) {
            $rate = $this->getExchangeRate($account->currency, $currency, $conversionDate);
            
            return [
                'bank_name' => $account->bank_name,
                'account_name' => $account->account_name,
                'original_currency' => $account->currency,
                'original_balance' => $account->current_balance,
                'converted_balance' => $account->current_balance * $rate,
                'overdraft_limit' => $account->overdraft_limit * $rate,
                'available_balance' => ($account->current_balance + $account->overdraft_limit) * $rate,
                'exchange_rate' => $rate,
                'last_updated' => $account->updated_at
            ];
        });
        
        $totalCash = $cashPosition->sum('converted_balance');
        $totalOverdraft = $cashPosition->sum('overdraft_limit');
        $totalAvailable = $cashPosition->sum('available_balance');
        
        return response()->json([
            'data' => $cashPosition,
            'summary' => [
                'currency' => $currency,
                'conversion_date' => $conversionDate,
                'total_accounts' => $cashPosition->count(),
                'total_cash' => $totalCash,
                'total_overdraft_limit' => $totalOverdraft,
                'total_available' => $totalAvailable,
                'currencies_involved' => $accounts->pluck('currency')->unique()->values()
            ]
        ], 200);
    }

    /**
     * Update bank account balance.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function updateBalance(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'new_balance' => 'required|numeric',
            'reason' => 'required|string|max:255',
            'reference_date' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $bankAccount = BankAccount::findOrFail($id);
        $oldBalance = $bankAccount->current_balance;
        $newBalance = $request->new_balance;
        $referenceDate = $request->reference_date ?? now()->toDateString();
        
        $baseCurrency = config('app.base_currency', 'USD');
        $exchangeRate = $this->getExchangeRate($bankAccount->currency, $baseCurrency, $referenceDate);
        $newBaseCurrencyBalance = $newBalance * $exchangeRate;
        
        // Update balance
        $bankAccount->update([
            'current_balance' => $newBalance,
            'base_currency_balance' => $newBaseCurrencyBalance,
            'exchange_rate' => $exchangeRate
        ]);
        
        // Log the balance change (you might want to create a balance history table)
        $this->logBalanceChange($bankAccount, $oldBalance, $newBalance, $request->reason, $referenceDate);

        return response()->json([
            'data' => $bankAccount,
            'balance_change' => [
                'old_balance' => $oldBalance,
                'new_balance' => $newBalance,
                'difference' => $newBalance - $oldBalance,
                'reason' => $request->reason,
                'reference_date' => $referenceDate
            ],
            'message' => 'Bank account balance updated successfully'
        ], 200);
    }

    /**
     * Get available currencies from bank accounts.
     *
     * @return \Illuminate\Http\Response
     */
    public function getAvailableCurrencies()
    {
        $currencies = BankAccount::distinct()
            ->pluck('currency')
            ->filter()
            ->sort()
            ->values();
            
        return response()->json(['data' => $currencies], 200);
    }

    /**
     * Get bank accounts by currency.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getAccountsByCurrency(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'currency' => 'required|string|size:3'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $accounts = BankAccount::where('currency', $request->currency)
            ->where('is_active', true)
            ->orderBy('bank_name')
            ->orderBy('account_name')
            ->get();

        return response()->json([
            'data' => $accounts,
            'summary' => [
                'currency' => $request->currency,
                'account_count' => $accounts->count(),
                'total_balance' => $accounts->sum('current_balance'),
                'total_available' => $accounts->sum(function($account) {
                    return $account->current_balance + $account->overdraft_limit;
                })
            ]
        ], 200);
    }

    /**
     * Generate bank account statement.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function generateStatement(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'include_pending' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $bankAccount = BankAccount::findOrFail($id);
        
        // This would typically get transactions from a bank transaction table
        // For now, we'll return a placeholder structure
        $statement = [
            'bank_account' => $bankAccount,
            'period' => [
                'from_date' => $request->from_date,
                'to_date' => $request->to_date
            ],
            'opening_balance' => $bankAccount->current_balance, // This should be calculated
            'closing_balance' => $bankAccount->current_balance,
            'transactions' => [], // This would come from bank transactions
            'summary' => [
                'total_debits' => 0,
                'total_credits' => 0,
                'transaction_count' => 0
            ]
        ];

        return response()->json([
            'data' => $statement,
            'message' => 'Bank statement generated successfully'
        ], 200);
    }

    /**
     * Get currency summary for a bank account.
     *
     * @param BankAccount $bankAccount
     * @return array
     */
    private function getBankAccountCurrencySummary($bankAccount)
    {
        $baseCurrency = config('app.base_currency', 'USD');
        
        return [
            'account_currency' => $bankAccount->currency,
            'base_currency' => $baseCurrency,
            'exchange_rate' => $bankAccount->exchange_rate,
            'balances' => [
                'current' => [
                    'currency' => $bankAccount->currency,
                    'amount' => $bankAccount->current_balance
                ],
                'base_currency' => [
                    'currency' => $baseCurrency,
                    'amount' => $bankAccount->base_currency_balance
                ]
            ],
            'limits' => [
                'overdraft_limit' => $bankAccount->overdraft_limit,
                'minimum_balance' => $bankAccount->minimum_balance,
                'available_balance' => $bankAccount->current_balance + $bankAccount->overdraft_limit
            ],
            'risk_assessment' => [
                'currency_risk' => $bankAccount->currency !== $baseCurrency ? 'high' : 'none',
                'liquidity_status' => $this->assessLiquidityStatus($bankAccount)
            ]
        ];
    }

    /**
     * Assess liquidity status of bank account.
     *
     * @param BankAccount $bankAccount
     * @return string
     */
    private function assessLiquidityStatus($bankAccount)
    {
        $balance = $bankAccount->current_balance;
        $minBalance = $bankAccount->minimum_balance;
        
        if ($balance < $minBalance) {
            return 'critical';
        } elseif ($balance < $minBalance * 2) {
            return 'low';
        } elseif ($balance < $minBalance * 5) {
            return 'medium';
        } else {
            return 'healthy';
        }
    }

    /**
     * Log balance change for audit trail.
     *
     * @param BankAccount $bankAccount
     * @param float $oldBalance
     * @param float $newBalance
     * @param string $reason
     * @param string $referenceDate
     * @return void
     */
    private function logBalanceChange($bankAccount, $oldBalance, $newBalance, $reason, $referenceDate)
    {
        // This would typically insert into a bank_account_balance_history table
        // For now, we'll just log it
        \Log::info('Bank account balance updated', [
            'bank_id' => $bankAccount->bank_id,
            'old_balance' => $oldBalance,
            'new_balance' => $newBalance,
            'difference' => $newBalance - $oldBalance,
            'reason' => $reason,
            'reference_date' => $referenceDate,
            'updated_by' => auth()->id() ?? 'system'
        ]);
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