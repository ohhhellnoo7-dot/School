<?php

namespace App\Http\Controllers;

use App\Models\ClassGroup;
use App\Models\FeeStructure;
use App\Models\Program;
use App\Models\SchoolClass;
use App\Services\SchoolFinanceService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TuitionRateController extends Controller
{
    private function sessions(string $program): array
    {
        return array_values(array_intersect(['Morning', 'Afternoon', 'Evening'], Program::sessionMap()[$program] ?? []));
    }

    private function grades(string $program): array
    {
        if ($program !== 'Japan') {
            return range($program === 'Khmer' ? 7 : 1, 12);
        }

        return ClassGroup::where('program', 'Japan')->pluck('grade')
            ->merge(SchoolClass::where('program', 'Japan')->pluck('name')->map(fn ($name) => preg_match('/(?:Grade\s*)?(\d+)/i', $name, $match) ? (int) $match[1] : null))
            ->filter(fn ($grade) => $grade >= 1 && $grade <= 12)->unique()->sort()->values()->all();
    }

    public function edit(Request $request)
    {
        $request->validate(['structure' => 'nullable|integer|exists:fee_structures,id', 'program' => 'nullable|in:Khmer,English,Chinese,Japan', 'grades' => 'nullable|array', 'grades.*' => 'integer|between:1,12']);
        $source = $request->filled('structure') ? FeeStructure::findOrFail($request->integer('structure')) : null;
        $program = $request->input('program', $source?->program ?? 'Chinese');
        if (! $source || $source->program !== $program) {
            $source = FeeStructure::where('program', $program)->where('status', 'active')->orderByDesc('academic_year')->orderBy('grade')->first();
        }
        $source ??= new FeeStructure(['program' => $program, 'academic_year' => now()->year.'-'.(now()->year + 1), 'currency' => 'USD', 'monthly_tuition' => '0.00', 'billable_months' => 12, 'semester_months' => 6, 'starts_on' => today()->startOfMonth(), 'effective_from' => today(), 'due_day' => 10]);
        $sessions = $this->sessions($program);
        $grades = $this->grades($program);
        $selected = $request->input('grades', $source->exists ? [$source->grade] : $grades);
        $versions = FeeStructure::where('program', $program)->where('academic_year', $source->academic_year)->where('status', 'active')->pluck('id', 'grade');

        $savedRates = FeeStructure::where('program', $program)->where('academic_year', $source->academic_year)->where('status', 'active')
            ->where(fn ($query) => $query->whereNotNull('supersedes_id')->orWhereIn('id', DB::table('finance_audits')->where('entity', 'fee_structure')->where('action', 'edit_session_tuition')->select('entity_id')))
            ->orderBy('grade')->get();
        $editedGrades = $savedRates->pluck('grade');

        return view('school.finance.tuition-edit', compact('source', 'program', 'grades', 'selected', 'versions', 'sessions', 'savedRates', 'editedGrades'));
    }

    public function save(Request $request, SchoolFinanceService $service)
    {
        $money = ['required', 'regex:/^\d{1,9}(\.\d{1,2})?$/'];
        $sessions = $this->sessions(is_string($request->input('program')) ? $request->input('program') : '');
        $rules = [
            'program' => ['required', Rule::in(['Khmer', 'English', 'Chinese', 'Japan'])],
            'grades' => 'required|array|min:1', 'grades.*' => 'required|integer|distinct|between:1,12',
            'academic_year' => ['required', 'regex:/^\d{4}-\d{4}$/'], 'currency' => 'required|in:USD,KHR',
            'billable_months' => 'required|integer|between:1,12', 'starts_on' => 'required|date_format:Y-m-d', 'due_day' => 'required|integer|between:1,28',
            'versions' => 'required|array', 'versions.*' => 'integer|min:0', 'same_day' => 'nullable|boolean',
            'package_months' => 'nullable|integer|in:3,6',
            'prices' => 'required|array:'.implode(',', $sessions),
            'prices.*.quarterly' => ['nullable', 'regex:/^\d{1,9}(\.\d{1,2})?$/'],
        ];
        foreach ($sessions as $session) {
            $rules['prices.'.$session.'.monthly'] = $money;
        }
        $data = $request->validate($rules);
        if (! $sessions) {
            $service->fail('Configure study sessions for this program before setting tuition.');
        }
        if (array_diff($data['grades'], $this->grades($data['program']))) {
            $service->fail('Choose grades or levels configured for this program.');
        }
        if ((int) substr($data['academic_year'], 5) !== (int) substr($data['academic_year'], 0, 4) + 1) {
            $service->fail('Choose a consecutive academic year.');
        }
        $duration = (int) ($data['package_months'] ?? 6);
        $prices = [];
        foreach ($sessions as $session) {
            $row = $data['prices'][$session];
            $prices[$session] = ['monthly' => Money::decimal(Money::cents($row['monthly'])), 'quarterly' => isset($row['quarterly']) ? Money::decimal(Money::cents($row['quarterly'])) : null];
        }
        if ($request->boolean('same_day') && isset($prices['Morning'], $prices['Afternoon'])) {
            $prices['Afternoon'] = $prices['Morning'];
        }
        foreach ($prices as $row) {
            if ($row['quarterly'] !== null && Money::cents($row['quarterly']) > Money::cents($row['monthly']) * $duration) {
                $service->fail('A package cannot cost more than the same number of monthly payments.');
            }
        }
        $basePrice = $prices[$sessions[0]];
        $updatedSource = $service->locked(function () use ($data, $prices, $basePrice, $duration, $request, $service) {
            $firstVersion = null;
            foreach ($data['grades'] as $grade) {
                $previous = FeeStructure::where('program', $data['program'])->where('grade', $grade)->where('academic_year', $data['academic_year'])->where('status', 'active')->first();
                if (! array_key_exists($grade, $data['versions']) || (int) $data['versions'][$grade] !== (int) $previous?->id) {
                    $service->fail('Tuition prices changed while this editor was open. Reload before saving.');
                }
                $discount = Money::cents($previous?->discount ?? '0.00');
                foreach ($prices as $row) {
                    if ($discount > Money::cents($row['monthly']) || ($row['quarterly'] !== null && $discount > intdiv(Money::cents($row['quarterly']), $duration))) {
                        $service->fail('Existing scholarships exceed these new prices. Review the discount in fee settings first.');
                    }
                }
                $attributes = $previous ? $previous->only(['charges', 'discount', 'discount_reason', 'semester_months', 'effective_until']) : ['charges' => array_fill_keys(['registration', 'books', 'uniform', 'examination', 'other'], '0.00'), 'discount' => '0.00', 'semester_months' => min(6, $data['billable_months'])];
                $structure = FeeStructure::create(array_merge($attributes, [
                    'program' => $data['program'], 'grade' => $grade, 'academic_year' => $data['academic_year'], 'currency' => $data['currency'],
                    'billable_months' => $data['billable_months'], 'starts_on' => $data['starts_on'], 'due_day' => $data['due_day'],
                    'effective_from' => today(), 'status' => 'draft', 'created_by' => $request->user()->id, 'supersedes_id' => $previous?->id,
                    'package_months' => $duration, 'monthly_tuition' => $basePrice['monthly'], 'quarterly_tuition' => $basePrice['quarterly'], 'session_prices' => $prices,
                ]));
                $service->audit($request->user(), 'edit_session_tuition', 'fee_structure', $structure->id, ['previous_version' => $previous?->id, 'session_prices' => $prices]);
                $service->approve($structure, $request->user());
                $firstVersion ??= $structure->id;
            }

            return $firstVersion;
        });

        return redirect()->route('finance.tuition.edit', ['program' => $data['program'], 'structure' => $updatedSource, 'grades' => $data['grades']])->with('success', 'Tuition updated for '.count($data['grades']).' grades/levels. Issued invoices remain unchanged.');
    }
}
