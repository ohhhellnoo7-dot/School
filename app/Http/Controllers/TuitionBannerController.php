<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Services\SchoolFinanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TuitionBannerController extends Controller
{
    public function show(Request $request, Program $program)
    {
        abort_unless(in_array($request->user()->role, ['administrator', 'parent', 'student'], true), 403);
        $disk = Storage::disk('local');
        abort_unless($program->tuition_banner && $disk->exists($program->tuition_banner), 404);

        return $disk->response($program->tuition_banner, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function update(Request $request, Program $program, SchoolFinanceService $finance)
    {
        $request->validate([
            'action' => 'required|in:save,reset',
            'banner' => 'exclude_if:action,reset|required_if:action,save|nullable|image|mimes:jpg,jpeg,png,webp,gif|max:2048',
        ]);

        $disk = Storage::disk('local');
        $path = null;
        if ($request->input('action') === 'save') {
            $path = $request->file('banner')->store('tuition-banners', 'local');
            if (! $path) {
                return back()->withErrors(['banner' => __('The picture could not be saved. Please try again.')]);
            }
        }

        try {
            $previous = $finance->locked(function () use ($program, $path) {
                $program->refresh();
                $previous = $program->tuition_banner;
                $program->update(['tuition_banner' => $path]);

                return $previous;
            });
        } catch (Throwable $exception) {
            if ($path) {
                $disk->delete($path);
            }
            throw $exception;
        }

        if ($previous) {
            $disk->delete($previous);
        }

        return redirect()->route('finance.index')->withFragment('current-program-tuition')
            ->with('success', $path ? __('Program picture saved.') : __('Original program picture restored.'));
    }
}
