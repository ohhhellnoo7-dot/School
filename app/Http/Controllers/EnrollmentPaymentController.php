<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\SchoolFinanceService;
use App\Support\Money;
use Illuminate\Http\Request;

class EnrollmentPaymentController extends Controller
{
    public function show(Request $request, Student $student)
    {
        abort_unless($request->user()->role === 'administrator', 403);
        $fee = $student->fees()->where('invoice_number', 'ENR-'.$student->id)->first();
        if ($fee) return redirect()->route('fees.pay', $fee);

        return view('school.students.enrollment-payment', compact('student'));
    }

    public function store(Request $request, Student $student, SchoolFinanceService $finance)
    {
        abort_unless($request->user()->role === 'administrator', 403);
        $data = $request->validate([
            'amount_usd' => ['required', 'numeric', 'min:0.01', 'max:999999', 'decimal:0,2'],
            'currency' => 'required|in:USD,KHR',
            'method' => 'required|in:cash,qr',
        ]);
        $fee = $finance->locked(function () use ($student, $data, $request, $finance) {
            // One enrollment invoice per student, including repeated submissions.
            $existing = $student->fees()->where('invoice_number', 'ENR-'.$student->id)->first();
            if ($existing) return $existing;
            $usdCents = Money::cents($data['amount_usd']);
            $amount = Money::decimal($usdCents * ($data['currency'] === 'KHR' ? 4100 : 1));
            $fee = $student->fees()->create([
                'invoice_number' => 'ENR-'.$student->id,
                'description' => 'Enrollment fee'.($data['currency'] === 'KHR' ? ' (USD '.Money::decimal($usdCents).' at 4,100 KHR/USD)' : ''),
                'amount' => $amount, 'currency' => $data['currency'], 'paid' => 0,
                'opening_paid' => 0, 'due_date' => today(), 'legacy_reviewed' => true,
                'issued_by' => $request->user()->id, 'checkout_method' => $data['method'],
            ]);
            $fee->items()->create(['description' => $fee->description, 'amount' => $amount]);
            $finance->audit($request->user(), 'issue_enrollment_invoice', 'fee', $fee->id, $fee->toArray());
            return $fee;
        });

        return redirect()->route('fees.pay', $fee);
    }
}
