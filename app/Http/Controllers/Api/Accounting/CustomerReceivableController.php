<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\CustomerReceivable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CustomerReceivableController extends Controller
{
    /**
     * Display a listing of customer receivables.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = CustomerReceivable::with(['customer', 'salesInvoice']);
        
        // Filter by customer
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }
        
        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        // Filter by due date range
        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->whereBetween('due_date', [$request->from_date, $request->to_date]);
        }
        
        $receivables = $query->orderBy('due_date')
            ->paginate($request->input('per_page', 15));
        
        return response()->json($receivables, 200);
    }

    /**
     * Display the statement for a specific receivable.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function statement($id)
    {
        $receivable = CustomerReceivable::with(['salesInvoice', 'receivablePayments'])
            ->findOrFail($id);

        // Validate customer_id
        if (empty($receivable->customer_id) || !is_numeric($receivable->customer_id)) {
            return response()->json([
                'error' => 'Invalid or missing customer_id for this receivable.'
            ], 400);
        }

        // Load customer relationship separately with validation
        $customer = $receivable->customer()->first();
        if (!$customer) {
            return response()->json([
                'error' => 'Customer not found for this receivable.'
            ], 404);
        }

        // Prepare statement data
        $statement = [
            'receivable' => $receivable,
            'customer' => $customer,
            'invoice' => $receivable->salesInvoice,
            'payments' => $receivable->receivablePayments()->orderBy('payment_date')->get(),
        ];

        return response()->json(['data' => $statement], 200);
    }

    /**
     * Store a newly created customer receivable in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:Customer,customer_id',
            'invoice_id' => 'required|exists:SalesInvoice,invoice_id',
            'amount' => 'required|numeric|min:0',
            'due_date' => 'required|date',
            'status' => 'required|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Check if a receivable already exists for this invoice
        $exists = CustomerReceivable::where('invoice_id', $request->invoice_id)->exists();
        if ($exists) {
            return response()->json([
                'message' => 'A receivable already exists for this invoice'
            ], 422);
        }

        $receivable = CustomerReceivable::create([
            'customer_id' => $request->customer_id,
            'invoice_id' => $request->invoice_id,
            'amount' => $request->amount,
            'due_date' => $request->due_date,
            'paid_amount' => 0,
            'balance' => $request->amount,
            'status' => $request->status
        ]);

        return response()->json([
            'data' => $receivable, 
            'message' => 'Customer receivable created successfully'
        ], 201);
    }

    /**
     * Display the specified customer receivable.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $receivable = CustomerReceivable::with([
            'customer', 
            'salesInvoice',
            'receivablePayments'
        ])->findOrFail($id);
        
        return response()->json(['data' => $receivable], 200);
    }

    /**
     * Update the specified customer receivable in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $receivable = CustomerReceivable::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'due_date' => 'date',
            'status' => 'string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Only allow updating due date and status
        $receivable->update($request->only(['due_date', 'status']));

        return response()->json([
            'data' => $receivable, 
            'message' => 'Customer receivable updated successfully'
        ], 200);
    }

    /**
     * Remove the specified customer receivable from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $receivable = CustomerReceivable::findOrFail($id);
        
        // Check if there are payments
        if ($receivable->receivablePayments()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete receivable with associated payments'
            ], 422);
        }
        
        $receivable->delete();

        return response()->json(['message' => 'Customer receivable deleted successfully'], 200);
    }
    
    /**
     * Generate aging report for customer receivables.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function aging(Request $request)
    {
        $aging = DB::table('CustomerReceivable')
            ->join('Customer', 'CustomerReceivable.customer_id', '=', 'Customer.customer_id')
            ->select(
                'CustomerReceivable.customer_id',
                'Customer.name as customer_name',
                DB::raw('SUM(CASE WHEN (current_date - due_date) <= 0 THEN balance ELSE 0 END) as current_amount'),
                DB::raw('SUM(CASE WHEN (current_date - due_date) BETWEEN 1 AND 30 THEN balance ELSE 0 END) as days_1_30'),
                DB::raw('SUM(CASE WHEN (current_date - due_date) BETWEEN 31 AND 60 THEN balance ELSE 0 END) as days_31_60'),
                DB::raw('SUM(CASE WHEN (current_date - due_date) BETWEEN 61 AND 90 THEN balance ELSE 0 END) as days_61_90'),
                DB::raw('SUM(CASE WHEN (current_date - due_date) > 90 THEN balance ELSE 0 END) as days_over_90'),
                DB::raw('SUM(balance) as total_balance')
            )
            ->where('CustomerReceivable.status', '!=', 'Paid')
            ->groupBy('CustomerReceivable.customer_id', 'Customer.name')
            ->orderBy('Customer.name')
            ->get();
        
        $totals = [
            'current_amount' => $aging->sum('current_amount'),
            'days_1_30' => $aging->sum('days_1_30'),
            'days_31_60' => $aging->sum('days_31_60'),
            'days_61_90' => $aging->sum('days_61_90'),
            'days_over_90' => $aging->sum('days_over_90'),
            'total_balance' => $aging->sum('total_balance')
        ];
        
        return response()->json([
            'data' => $aging,
            'totals' => $totals
        ], 200);
    }

    /**
     * Get customer transactions including receivables, invoices, and payments.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function customerTransactions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:Customer,customer_id',
            'currency_code' => 'string|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $customerId = $request->customer_id;
        $currencyCode = $request->currency_code;

        // Fetch receivables
        $receivables = CustomerReceivable::with(['salesInvoice'])
            ->where('customer_id', $customerId)
            ->get()
            ->map(function ($receivable) use ($currencyCode) {
                $amounts = $currencyCode ? $receivable->getAmountsInCurrency($currencyCode) : [
                    'amount' => $receivable->amount,
                    'paid_amount' => $receivable->paid_amount,
                    'balance' => $receivable->balance,
                ];
                return [
                    'type' => 'receivable',
                    'id' => $receivable->receivable_id,
                    'invoice_id' => $receivable->invoice_id,
                    'invoice_number' => $receivable->salesInvoice->invoice_number ?? null,
                    'due_date' => $receivable->due_date,
                    'amount' => $amounts['amount'],
                    'paid_amount' => $amounts['paid_amount'],
                    'balance' => $amounts['balance'],
                    'status' => $receivable->status,
                ];
            });

        // Fetch payments
        $payments = \App\Models\Accounting\ReceivablePayment::whereHas('customerReceivable', function ($query) use ($customerId) {
                $query->where('customer_id', $customerId);
            })
            ->get()
            ->map(function ($payment) use ($currencyCode) {
                $amount = $currencyCode ? $payment->getAmountInCurrency($currencyCode) : $payment->amount;
                return [
                    'type' => 'payment',
                    'id' => $payment->payment_id,
                    'receivable_id' => $payment->receivable_id,
                    'payment_date' => $payment->payment_date,
                    'amount' => $amount,
                    'payment_method' => $payment->payment_method,
                    'reference_number' => $payment->reference_number,
                ];
            });

        // Fetch invoices
        $invoices = \App\Models\Sales\SalesInvoice::where('customer_id', $customerId)
            ->get()
            ->map(function ($invoice) use ($currencyCode) {
                $amounts = $currencyCode ? $invoice->getAmountsInCurrency($currencyCode) : [
                    'total_amount' => $invoice->total_amount,
                    'tax_amount' => $invoice->tax_amount,
                ];
                return [
                    'type' => 'invoice',
                    'id' => $invoice->invoice_id,
                    'invoice_number' => $invoice->invoice_number,
                    'invoice_date' => $invoice->invoice_date,
                    'total_amount' => $amounts['total_amount'],
                    'tax_amount' => $amounts['tax_amount'],
                    'status' => $invoice->status,
                ];
            });

        // Combine all transactions and sort by date descending
        $transactions = $receivables->concat($payments)->concat($invoices)->sortByDesc(function ($item) {
            if ($item['type'] === 'payment') {
                return $item['payment_date'];
            } elseif ($item['type'] === 'invoice') {
                return $item['invoice_date'];
            } elseif ($item['type'] === 'receivable') {
                return $item['due_date'];
            }
            return null;
        })->values();

        return response()->json(['data' => $transactions], 200);
    }
}