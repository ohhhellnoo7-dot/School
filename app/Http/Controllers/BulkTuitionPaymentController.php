<?php

namespace App\Http\Controllers;

use App\Models\BulkPaymentRun;
use App\Services\BulkTuitionPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BulkTuitionPaymentController extends Controller
{
    public function preview(Request $request, BulkTuitionPaymentService $service)
    {
        $data = $request->validate(['months' => 'nullable|integer|in:1,6,12']);
        $plan = $service->preview((int) ($data['months'] ?? 1));

        if ($request->expectsJson()) {
            return response()->json([
                'locked' => $plan['cycle']['locked'],
                'errors' => $plan['errors'],
                'next_date' => $plan['cycle']['next_date']->format('d F Y'),
                'families' => $plan['parents_count'],
                'children' => $plan['students_count'],
                'totals' => collect($plan['totals'])->map(fn ($amount, $currency) => \App\Support\Money::display($amount).' '.$currency)->values(),
                'payload' => ['months' => $plan['months'], 'fingerprint' => $plan['fingerprint'], 'request_key' => (string) \Illuminate\Support\Str::uuid()],
            ])->header('Cache-Control', 'private, no-store');
        }

        return response()->view('school.finance.bulk-pay', compact('plan'))->header('Cache-Control', 'private, no-store');
    }

    public function pay(Request $request, BulkTuitionPaymentService $service)
    {
        $data = $request->validate([
            'months' => 'required|integer|in:1,6,12', 'request_key' => 'required|uuid', 'fingerprint' => 'required|string|size:64',
            'method' => 'required|in:cash,bank_transfer,qr,card,other', 'reference' => 'nullable|string|max:150', 'confirmed' => 'required|accepted',
        ]);
        $run = $service->pay($data, $request->user());

        return redirect()->route('finance.parents')->with('success', 'Payments saved for '.$run->students_count.' children across '.$run->parents_count.' families. Their invoices and paid amounts are shown below. Next payment: '.$run->next_on->format('d F Y').'.')->with('bulk_payment_run_id', $run->id);
    }

    public function show(BulkPaymentRun $run)
    {
        $allocations = DB::table('bulk_payment_allocations')->where('bulk_payment_run_id', $run->id)
            ->join('fees', 'fees.id', '=', 'bulk_payment_allocations.fee_id')
            ->join('students', 'students.id', '=', 'fees.student_id')
            ->leftJoin('fee_payments', 'fee_payments.id', '=', 'bulk_payment_allocations.fee_payment_id')
            ->select('fees.id as fee_id', 'students.name', 'fees.program', 'fees.session', 'fee_payments.id as payment_id', 'fee_payments.amount', 'fees.currency')
            ->orderBy('students.name')->paginate(25);

        return response()->view('school.finance.bulk-result', compact('run', 'allocations'))->header('Cache-Control', 'private, no-store');
    }
}
