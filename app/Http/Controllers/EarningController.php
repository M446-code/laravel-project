<?php

namespace App\Http\Controllers;

use App\Models\Commission;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EarningController extends Controller
{
    // get all earnings fron Commission model
    public function index(Request $request)
    {
        $perPage = $request->input('perPage', 500);
        $startDate = $request->input('startDate', Carbon::now()->subDays(7)->toDateString());
        $endDate = $request->input('endDate', Carbon::now()->toDateString());

        // Add 1 day to the endDate
        $endDate = Carbon::parse($endDate)->addDay()->toDateString();

        $earnings = Commission::with('salesRep', 'customer')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            // calculate balance & add to the response
            ->selectRaw('*, (SELECT SUM(commission_amount) FROM commissions WHERE sales_rep_id = sales_reps.user_id) - (SELECT SUM(deduction) FROM commissions WHERE sales_rep_id = sales_reps.id) AS total_balance')
            ->paginate($perPage);

        return response()->json($earnings, 200);
    }

    // allSalesRepsEarnings
    public function allSalesRepsEarnings(Request $request)
    {
        $perPage = $request->input('perPage', 500);
        $startDate = $request->input('startDate', '2021-01-01');
        $endDate = $request->input('endDate', Carbon::now()->toDateString());

        // Add 1 day to the endDate
        $endDate = Carbon::parse($endDate)->addDay()->toDateString();

        $earnings = Commission::with('salesRep', 'customer')
            ->whereBetween('created_at', [$startDate, $endDate])
            // ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $initialBalance = 0;
        $earnings->map(function ($earning) use (&$initialBalance) {
            $initialBalance += $earning->commission_amount - $earning->deduction;
            $earning->balance = $initialBalance;
            return $earning;
        });

        return response()->json($earnings, 200);
    }

    // single sales rep earnings
    public function singleSalesRepEarnings(Request $request, $id)
    {
        $perPage = $request->input('perPage', 500);
        $startDate = $request->input('startDate', '2021-01-01');
        $endDate = $request->input('endDate', Carbon::now()->toDateString());

        // Add 1 day to the endDate
        $endDate = Carbon::parse($endDate)->addDay()->toDateString();

        $earnings = Commission::where('sales_rep_id', $id)
            ->with('salesRep', 'customer')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->paginate($perPage);

        $initialBalance = 0;
        $earnings->map(function ($earning) use (&$initialBalance) {
            $initialBalance += $earning->commission_amount - $earning->deduction;
            $earning->balance = $initialBalance;
            return $earning;
        });

        return response()->json($earnings, 200);
    }

    // get all earnings from Commission model by sales rep id
    // public function show($id)
    // {
    //     $earnings = Commission::where('sales_rep_id', $id)->get();

    //     return response()->json($earnings, 200);
    // }

    // get all earnings from Commission model by sales rep id and month
    public function showByMonth($id, $month)
    {
        $earnings = Commission::where('sales_rep_id', $id)
            ->where('month', $month)
            ->get();

        return response()->json($earnings, 200);
    }

    // add credits to the sales rep
    public function addCredits(Request $request)
    {
        $request->validate([
            'sales_rep_id' => 'required',
            'month' => 'required',
            'commission_type' => 'required',
            'commission_amount' => 'required',
        ]);

        // sum of commission_amount
        $sumOfCommissionAmount = Commission::where('sales_rep_id', $request->sales_rep_id)
            ->sum('commission_amount');
        // sum of deduction
        $sumOfDeduction = Commission::where('sales_rep_id', $request->sales_rep_id)
            ->sum('deduction');

        // calculate the  balance
        $calculatedBalance = $sumOfCommissionAmount + $request->commission_amount - $sumOfDeduction;

        $earning = new Commission();
        $earning->sales_rep_id = $request->sales_rep_id;
        $earning->month = $request->month;
        $earning->description = $request->description;
        $earning->commission_type = $request->commission_type;
        $earning->commission_amount = $request->commission_amount;
        $earning->deduction = $request->deduction ?? 0;
        $earning->balance = $request->balance ?? $calculatedBalance;
        $earning->paid = $request->paid ?? false;
        $earning->customer_id = $request->customer_id ?? null;
        $earning->save();

        return response()->json($earning, 201);
    }

    // deduct credits
    public function deductCredits(Request $request)
    {
        $request->validate([
            'sales_rep_id' => 'required',
            'month' => 'required',
            'commission_type' => 'required',
            'deduction' => 'required',
        ]);

        // sum of commission_amount
        $sumOfCommissionAmount = Commission::where('sales_rep_id', $request->sales_rep_id)
            ->sum('commission_amount');
        // sum of deduction
        $sumOfDeduction = Commission::where('sales_rep_id', $request->sales_rep_id)
            ->sum('deduction');

        // calculate the  balance
        $calculatedBalance = $sumOfCommissionAmount - $request->deduction - $sumOfDeduction;

        $earning = new Commission();
        $earning->sales_rep_id = $request->sales_rep_id;
        $earning->month = $request->month;
        $earning->description = $request->description;
        $earning->commission_type = $request->commission_type;
        $earning->commission_amount = $request->commission_amount ?? 0;
        $earning->deduction = $request->deduction ?? 0;
        $earning->balance = $request->balance ?? $calculatedBalance;
        $earning->paid = $request->paid ?? false;
        $earning->customer_id = $request->customer_id ?? null;
        $earning->save();

        return response()->json($earning, 201);
    }

    // update the earnings
    public function update(Request $request, $id)
    {
        $earning = Commission::find($id);

        if (!$earning) {
            return response()->json(['error' => 'Earning not found'], 404);
        }

        $earning->update($request->all());

        return response()->json($earning, 200);
    }

    // delete the earnings
    public function delete($id)
    {
        $earning = Commission::find($id);

        if (!$earning) {
            return response()->json(['error' => 'Earning not found'], 404);
        }

        $earning->delete();

        return response()->json(['message' => 'Earning deleted successfully'], 200);
    }
}
