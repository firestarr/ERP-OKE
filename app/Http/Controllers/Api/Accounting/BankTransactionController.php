<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\BankTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BankTransactionController extends Controller
{
    /**
     * Display a listing of bank transactions.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $transactions = BankTransaction::orderBy('transaction_date', 'desc')->get();
        return response()->json(['data' => $transactions], 200);
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
            'bank_account_id' => 'required|exists:bank_accounts,bank_id',
            'transaction_date' => 'required|date',
            'description' => 'nullable|string|max:255',
            'amount' => 'required|numeric',
            'transaction_type' => 'required|string|max:50',
            'reference_number' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $transaction = BankTransaction::create($request->all());

        return response()->json(['data' => $transaction, 'message' => 'Bank transaction created successfully'], 201);
    }

    /**
     * Display the specified bank transaction.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $transaction = BankTransaction::findOrFail($id);
        return response()->json(['data' => $transaction], 200);
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

        $validator = Validator::make($request->all(), [
            'bank_account_id' => 'exists:bank_accounts,bank_id',
            'transaction_date' => 'date',
            'description' => 'nullable|string|max:255',
            'amount' => 'numeric',
            'transaction_type' => 'string|max:50',
            'reference_number' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $transaction->update($request->all());

        return response()->json(['data' => $transaction, 'message' => 'Bank transaction updated successfully'], 200);
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
        $transaction->delete();

        return response()->json(['message' => 'Bank transaction deleted successfully'], 200);
    }
}
