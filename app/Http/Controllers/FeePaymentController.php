<?php

namespace App\Http\Controllers;

use App\Support\SchoolModules;
use App\Services\SchoolFinanceService;
use App\Models\FeePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class FeePaymentController extends Controller
{
    public function show(Request $request, int $fee)
    {
        $fee = SchoolModules::query('fees', $request->user())->with(['student.user', 'items', 'adjustments', 'payments'])->findOrFail($fee);

        return view('school.fee-payment', ['fee' => $fee, 'hasQr' => Storage::disk('local')->exists('payments/school-qr.png')]);
    }

    private function visibleFee(Request $request, int $fee)
    {
        abort_unless(in_array($request->user()->role, ['administrator', 'student', 'parent'], true), 403);
        return SchoolModules::query('fees', $request->user())->findOrFail($fee);
    }

    public function status(Request $request, int $fee)
    {
        $fee = $this->visibleFee($request, $fee);
        return response()->json([
            'paid' => $fee->balanceCents() <= 0, 'balance' => $fee->balance,
            'pending' => (bool) $fee->checkout_submitted_at,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function checkout(Request $request, int $fee, SchoolFinanceService $finance)
    {
        $fee = $this->visibleFee($request, $fee);
        $data = $request->validate(['method' => 'required|in:cash,qr', 'reference' => 'nullable|string|max:255']);
        if ($data['method'] === 'qr' && !Storage::disk('local')->exists('payments/school-qr.png')) {
            throw ValidationException::withMessages(['method' => 'Upload the current school KHQR before starting a QR payment.']);
        }
        $finance->locked(function () use ($fee, $data, $request, $finance) {
            $fee = $fee->fresh();
            if ($fee->balanceCents() <= 0) return;
            if ($fee->checkout_submitted_at && $request->user()->role !== 'administrator') return;
            $fee->update(['checkout_method' => $data['method'], 'checkout_submitted_at' => now(), 'checkout_reference' => $data['reference'] ?? null]);
            $finance->audit($request->user(), 'submit_payment_for_review', 'fee', $fee->id, $data);
        });
        return redirect()->route('fees.pay', $fee)->with('success', 'Payment is awaiting administrator confirmation.');
    }

    public function confirm(Request $request, int $fee, SchoolFinanceService $finance)
    {
        abort_unless($request->user()->role === 'administrator', 403);
        $fee = $this->visibleFee($request, $fee);
        $data = $request->validate([
            'request_key' => 'required|uuid', 'verified' => 'accepted',
            'reference' => 'nullable|string|max:255',
        ]);
        $finance->locked(function () use ($fee, $data, $request, $finance) {
            $fee = $fee->fresh();
            if ($fee->balanceCents() <= 0) return;
            if (!$fee->checkout_submitted_at || !in_array($fee->checkout_method, ['cash', 'qr'], true)) {
                throw ValidationException::withMessages(['payment' => 'Choose a method and submit the payment for confirmation first.']);
            }
            $reference = trim($data['reference'] ?? '');
            if ($fee->checkout_method === 'qr' && ($reference === '' || FeePayment::where('method', 'qr')->where('reference', $reference)->exists())) {
                throw ValidationException::withMessages(['reference' => 'Enter the unique bank transaction reference after checking your bank account.']);
            }
            $finance->payment($fee, [
                'request_key' => $data['request_key'], 'amount' => $fee->balance,
                'paid_on' => today()->toDateString(), 'method' => $fee->checkout_method,
                'reference' => $reference ?: null,
            ], $request->user());
        });
        return redirect()->route('fees.pay', $fee)->with('success', 'Payment confirmed. Receipt created.');
    }

    public function uploadQr(Request $request)
    {
        abort_unless($request->user()->role === 'administrator', 403);
        $request->validate(['qr' => 'required|image|mimes:png,jpg,jpeg|max:4096']);
        $request->file('qr')->storeAs('payments', 'school-qr.png', 'local');

        return back()->with('success', 'School payment QR updated.');
    }

    public function qr(Request $request)
    {
        abort_unless(in_array($request->user()->role, ['administrator', 'student', 'parent'], true), 403);
        abort_unless(Storage::disk('local')->exists('payments/school-qr.png'), 404);

        return Storage::disk('local')->response('payments/school-qr.png', null, ['Cache-Control' => 'no-store, private']);
    }
}
