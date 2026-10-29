<?php

namespace App\Http\Controllers;

use App\Support\SchoolModules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FeePaymentController extends Controller
{
    public function show(Request $request, int $fee)
    {
        $fee = SchoolModules::query('fees', $request->user())->with('student')->findOrFail($fee);

        return view('school.fee-payment', ['fee' => $fee, 'hasQr' => Storage::disk('local')->exists('payments/school-qr.png') || is_file(base_path('Image/image.png'))]);
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
        if (! Storage::disk('local')->exists('payments/school-qr.png')) {
            abort_unless(is_file(base_path('Image/image.png')), 404);

            return response()->file(base_path('Image/image.png'), ['Cache-Control' => 'no-store, private']);
        }

        return Storage::disk('local')->response('payments/school-qr.png', null, ['Cache-Control' => 'no-store, private']);
    }
}
