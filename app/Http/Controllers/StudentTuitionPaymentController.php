<?php

namespace App\Http\Controllers;

use App\Exceptions\StudentTuitionRateException;
use App\Models\Fee;
use App\Models\FeePayment;
use App\Models\Student;
use App\Services\StudentTuitionPayment;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StudentTuitionPaymentController extends Controller
{
    public function index(Request $request, StudentTuitionPayment $tuition)
    {
        $filters = $request->validate([
            'search' => 'nullable|string|max:100',
            'student' => 'nullable|integer',
            'months' => 'nullable|integer|in:1,6,12',
            'select_programs' => 'nullable|in:1',
            'enrollments' => 'nullable|array',
            'enrollments.*' => ['required', 'string', 'distinct', 'regex:/^(group|class):[0-9]+$/'],
        ]);
        $search = trim($filters['search'] ?? '');
        $students = $search === '' ? collect() : Student::where('status', 'active')->search($search)
            ->with(['studyClasses.classGroup', 'user'])->orderBy('name')->orderBy('id')->limit(31)->get();
        if ($request->ajax() && ! isset($filters['student'])) {
            return response()->view('school.finance._student-payment-results', compact('students', 'search'))
                ->header('Cache-Control', 'private, no-store');
        }

        $student = isset($filters['student'])
            ? Student::with(['schoolClass.classGroup', 'studyClasses.classGroup', 'user'])->findOrFail($filters['student']) : null;
        $months = (int) ($filters['months'] ?? 1);
        $quote = null;
        $problem = null;
        $rateIssue = null;
        $choices = collect();
        $selection = $filters['enrollments'] ?? (isset($filters['select_programs']) ? [] : null);
        if ($student) {
            $finance = app(\App\Services\SchoolFinanceService::class);
            $choices = $student->studyClasses->sortBy('id')->unique(fn ($class) => $finance->enrollmentKey($class))
                ->keyBy(fn ($class) => $finance->enrollmentKey($class));
            $selection ??= $choices->keys()->all();
            try {
                $quote = $tuition->quote($student, $months, $selection);
            } catch (StudentTuitionRateException $exception) {
                $problem = $exception->errors()['finance'][0];
                $rateIssue = $exception->details;
            } catch (ValidationException $exception) {
                $problem = $exception->errors()['finance'][0];
            }
        }
        $receipts = FeePayment::whereIn('id', $request->session()->get('student_tuition_receipts', []))
            ->whereHas('fee', fn ($fees) => $fees->where('student_id', $student?->id))->get();
        $invoices = Fee::with(['items', 'adjustments'])->where('student_id', $student?->id)
            ->whereIn('id', $request->session()->get('student_tuition_invoices', []))->orderBy('id')->get();

        $view = view('school.finance.student-payment', compact('students', 'search', 'student', 'months', 'quote', 'problem', 'rateIssue', 'receipts', 'invoices', 'choices', 'selection'));

        return $request->ajax()
            ? response($view->fragment('student-payment-preview'))->header('Cache-Control', 'private, no-store')
            : $view;
    }

    public function store(Request $request, Student $student, StudentTuitionPayment $tuition)
    {
        $data = $request->validate([
            'months' => 'required|integer|in:1,6,12',
            'fingerprint' => 'required|string|size:64',
            'method' => 'required|in:cash,bank_transfer,qr,other',
            'reference' => 'nullable|string|max:120',
            'confirmed' => 'accepted',
            'enrollments' => 'sometimes|required|array|min:1',
            'enrollments.*' => ['required', 'string', 'distinct', 'regex:/^(group|class):[0-9]+$/'],
        ]);
        $payment = $tuition->record($student, $data, $request->user());
        $request->session()->flash('student_tuition_receipts', $payment['receipts']);
        $request->session()->flash('student_tuition_invoices', $payment['invoices']);
        $request->session()->flash('success', $payment['receipts'] ? 'Student tuition payment recorded.' : 'Tuition coverage recorded. No amount was due.');
        $next = route('finance.student-payment', ['student' => $student->id]);

        return $request->expectsJson()
            ? response()->json(['next' => $next.'#student-payment-invoice'])
            : redirect()->to($next);
    }
}
