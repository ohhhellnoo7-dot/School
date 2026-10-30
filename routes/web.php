<?php

use App\Http\Controllers\HiController;
use Illuminate\Support\Facades\Route;

Route::post('/language', function (\Illuminate\Http\Request $request) {
    $data = $request->validate(['locale' => ['required', 'in:en,km,zh']]);
    $request->session()->put('locale', $data['locale']);

    return back();
})->name('language.update');

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});
Route::get('hi/{name}', [HiController::class, 'greet']);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ClassGroupController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\SchoolModuleController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TimetableController;

// Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
// Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
// Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
// Route::get('/customers/{id}', [CustomerController::class, 'show'])->name('customers.show');
// Route::get('/customers/{id}/edit/', [CustomerController::class, 'edit'])->name('customers.edit');
// Route::patch('/customers/{id}', [CustomerController::class, 'update'])->name('customers.update');
// Route::delete('/customers/{id}', [CustomerController::class,'destroy'])->name('customers.destroy');
Route::resource('/customers', CustomerController::class)->middleware(['auth', 'role:administrator']);

Route::middleware('guest')->group(function () {
    Route::view('/login', 'school.auth.login')->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
});
Route::middleware(['auth', 'role:administrator,teacher,student,parent'])->group(function () {
    Route::get('/school/timetables', [\App\Http\Controllers\UnifiedTimetableController::class, 'index'])->name('timetables.index');
    Route::get('/school/timetables/data', [\App\Http\Controllers\UnifiedTimetableController::class, 'data'])->name('timetables.data');
    Route::middleware('role:administrator')->prefix('school/timetables')->group(function () {
        Route::get('/school-audit', [\App\Http\Controllers\UnifiedTimetableController::class, 'schoolAudit'])->name('timetables.school-audit');
        Route::get('/rooms/review', [\App\Http\Controllers\TimetableRoomController::class, 'review'])->name('timetables.rooms.review');
        Route::post('/rooms/preview', [\App\Http\Controllers\TimetableRoomController::class, 'preview'])->name('timetables.rooms.preview');
        Route::post('/rooms/confirm', [\App\Http\Controllers\TimetableRoomController::class, 'confirm'])->name('timetables.rooms.confirm');
        Route::get('/diagnostics', [\App\Http\Controllers\UnifiedTimetableController::class, 'diagnostics'])->name('timetables.diagnostics');
        Route::get('/lessons/create', [\App\Http\Controllers\UnifiedTimetableController::class, 'form'])->name('timetables.lessons.create');
        Route::get('/lessons/options', [\App\Http\Controllers\UnifiedTimetableController::class, 'options'])->name('timetables.lessons.options');
        Route::post('/lessons', [\App\Http\Controllers\UnifiedTimetableController::class, 'store'])->name('timetables.lessons.store');
        Route::get('/lessons/{lesson}/edit', [\App\Http\Controllers\UnifiedTimetableController::class, 'form'])->name('timetables.lessons.edit');
        Route::put('/lessons/{lesson}', [\App\Http\Controllers\UnifiedTimetableController::class, 'update'])->name('timetables.lessons.update');
        Route::post('/lessons/{lesson}/cancel', [\App\Http\Controllers\UnifiedTimetableController::class, 'cancel'])->name('timetables.lessons.cancel');
        Route::get('/lessons/{lesson}/conflicts', [\App\Http\Controllers\UnifiedTimetableController::class, 'conflicts'])->name('timetables.conflicts');
        Route::post('/generate/preview', [\App\Http\Controllers\UnifiedTimetableController::class, 'preview'])->name('timetables.generate.preview');
        Route::post('/generate/confirm', [\App\Http\Controllers\UnifiedTimetableController::class, 'confirm'])->name('timetables.generate.confirm');
    });
    Route::get('/fees/qr', [\App\Http\Controllers\FeePaymentController::class, 'qr'])->name('fees.qr');
    Route::post('/fees/qr', [\App\Http\Controllers\FeePaymentController::class, 'uploadQr'])->name('fees.qr.upload');
    Route::get('/fees/{fee}/pay', [\App\Http\Controllers\FeePaymentController::class, 'show'])->whereNumber('fee')->name('fees.pay');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/profile/photo', [ProfileController::class, 'photo'])->name('profile.photo');
    Route::get('/dashboard/data', [DashboardController::class, 'data'])->name('dashboard.data');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/dashboard/attendance/all-present', [DashboardController::class, 'markAllPresent'])->middleware('role:administrator')->name('dashboard.attendance.all-present');
    Route::post('/dashboard/attendance/random', [DashboardController::class, 'randomAttendance'])->middleware('role:administrator')->name('dashboard.attendance.random');
    Route::get('/reports', [DashboardController::class, 'reports'])->middleware('role:administrator')->name('reports');
    Route::get('/users/{user}/photo', [ProfileController::class, 'userPhoto'])->name('users.photo');
    Route::get('/students/{student}/photo', [ProfileController::class, 'studentPhoto'])->name('students.photo');
    Route::resource('/students', StudentController::class);
    Route::get('/teachers/{teacher}/schedule', [SchoolModuleController::class, 'teacherTimetable'])->name('teachers.schedule');
    Route::get('/classes/{schoolClass}/timetable', [TimetableController::class, 'show'])->name('classes.timetable');
    Route::get('/teachers/{teacher}/schedule/status', [SchoolModuleController::class, 'teacherLessonStatus'])->name('teachers.schedule.status');
    Route::post('/classes/{schoolClass}/timetable/confirm', [TimetableController::class, 'confirm'])->middleware('role:administrator')->name('classes.timetable.confirm');
    Route::post('/classes/{schoolClass}/timetable/settings', [TimetableController::class, 'settings'])->middleware('role:administrator')->name('classes.timetable.settings');
    Route::post('/lessons/{schedule}/cancel', [TimetableController::class, 'cancelLesson'])->middleware('role:administrator')->name('lessons.cancel');
    Route::post('/classes/{schoolClass}/timetable/generate', [TimetableController::class, 'generate'])->middleware('role:administrator')->name('classes.timetable.generate');
    Route::post('/classes/{schoolClass}/timetable/regenerate', [TimetableController::class, 'regenerate'])->middleware('role:administrator')->name('classes.timetable.regenerate');

    // The redesigned class-group directory owns only this exact URL. Keep the
    // legacy dynamic class CRUD routes below for existing SchoolClass records.
    Route::get('/school/classes', [ClassGroupController::class, 'index'])->name('class-groups.index');
    Route::get('/class-groups/create', [ClassGroupController::class, 'create'])->middleware('role:administrator')->name('class-groups.create');
    Route::get('/class-groups/{classGroup}', [ClassGroupController::class, 'show'])->name('class-groups.show');

    Route::get('/school/classrooms', [ClassroomController::class, 'index'])->name('classrooms.index');
    Route::get('/school/classrooms/data', [ClassroomController::class, 'data'])->name('classrooms.data');
    Route::get('/school/classrooms/create', [ClassroomController::class, 'create'])->middleware('role:administrator')->name('classrooms.create');
    Route::get('/school/classrooms/{classroom}', [ClassroomController::class, 'show'])->name('classrooms.show');
    Route::get('/school/classrooms/{classroom}/lesson-status', [ClassroomController::class, 'lessonStatus'])->name('classrooms.lesson-status');

    Route::middleware('role:administrator')->group(function () {
        Route::post('/class-groups', [ClassGroupController::class, 'store'])->name('class-groups.store');
        Route::get('/class-groups/{classGroup}/edit', [ClassGroupController::class, 'edit'])->name('class-groups.edit');
        Route::put('/class-groups/{classGroup}', [ClassGroupController::class, 'update'])->name('class-groups.update');
        Route::delete('/class-groups/{classGroup}', [ClassGroupController::class, 'destroy'])->name('class-groups.destroy');

        Route::post('/school/classrooms', [ClassroomController::class, 'store'])->name('classrooms.store');
        Route::post('/school/classrooms/auto-assign', [ClassroomController::class, 'autoAssign'])->name('classrooms.auto-assign');
        Route::get('/school/classrooms/{classroom}/edit', [ClassroomController::class, 'edit'])->name('classrooms.edit');
        Route::put('/school/classrooms/{classroom}', [ClassroomController::class, 'update'])->name('classrooms.update');
        Route::delete('/school/classrooms/{classroom}', [ClassroomController::class, 'destroy'])->name('classrooms.destroy');

        Route::get('/classroom-bookings/create', [ClassroomController::class, 'bookingCreate'])->name('classroom-bookings.create');
        Route::post('/classroom-bookings', [ClassroomController::class, 'bookingStore'])->name('classroom-bookings.store');
        Route::get('/classroom-bookings/{classroomBooking}/edit', [ClassroomController::class, 'bookingEdit'])->name('classroom-bookings.edit');
        Route::put('/classroom-bookings/{classroomBooking}', [ClassroomController::class, 'bookingUpdate'])->name('classroom-bookings.update');
        Route::delete('/classroom-bookings/{classroomBooking}', [ClassroomController::class, 'bookingDestroy'])->name('classroom-bookings.destroy');
    });

    Route::prefix('teacher-management')->name('teacher-management.')->middleware('role:administrator')->group(function () {
        Route::post('/schedules/confirm', [\App\Http\Controllers\TeacherManagementController::class, 'confirmSchedule'])->name('schedules.confirm');
        Route::post('/{teacher}/schedules/preview', [\App\Http\Controllers\TeacherManagementController::class, 'previewSchedule'])->name('schedules.preview');
        Route::get('/reports', [\App\Http\Controllers\TeacherManagementController::class, 'reports'])->name('reports');
        Route::post('/settings', [\App\Http\Controllers\TeacherManagementController::class, 'settings'])->name('settings');
        Route::post('/curriculum/{subject}', [\App\Http\Controllers\TeacherManagementController::class, 'curriculum'])->name('curriculum');
        Route::post('/samples/preview', [\App\Http\Controllers\TeacherManagementController::class, 'samplePreview'])->name('samples.preview');
        Route::post('/samples/confirm', [\App\Http\Controllers\TeacherManagementController::class, 'sampleConfirm'])->name('samples.confirm');
        Route::post('/breaks/preview', [\App\Http\Controllers\TeacherManagementController::class, 'previewBreaks'])->name('breaks.preview-all');
        Route::post('/breaks/confirm', [\App\Http\Controllers\TeacherManagementController::class, 'confirmBreaks'])->name('breaks.confirm');
        Route::post('/assignments/preview', [\App\Http\Controllers\TeacherManagementController::class, 'assignments'])->name('assignments.preview');
        Route::post('/assignments/confirm', [\App\Http\Controllers\TeacherManagementController::class, 'confirmAssignments'])->name('assignments.confirm');
        Route::get('/create', [\App\Http\Controllers\TeacherManagementController::class, 'form'])->name('create');
        Route::post('/', [\App\Http\Controllers\TeacherManagementController::class, 'save'])->name('store');
        Route::get('/{teacher}', [\App\Http\Controllers\TeacherManagementController::class, 'show'])->name('show');
        Route::get('/{teacher}/edit', [\App\Http\Controllers\TeacherManagementController::class, 'form'])->name('edit');
        Route::put('/{teacher}', [\App\Http\Controllers\TeacherManagementController::class, 'save'])->name('update');
        Route::post('/{teacher}/assign', [\App\Http\Controllers\TeacherManagementController::class, 'assign'])->name('assign');
        Route::get('/{teacher}/available-times', [\App\Http\Controllers\TeacherManagementController::class, 'availableTimes'])->name('available-times');
        Route::post('/{teacher}/breaks/preview', [\App\Http\Controllers\TeacherManagementController::class, 'previewBreaks'])->name('breaks.preview');
        Route::put('/{teacher}/breaks/{break}', [\App\Http\Controllers\TeacherManagementController::class, 'editBreak'])->name('breaks.edit');
    });
    Route::get('/school/{module}', [SchoolModuleController::class, 'index'])->name('modules.index');
    Route::get('/school/{module}/create', [SchoolModuleController::class, 'create'])->name('modules.create');
    Route::post('/school/{module}', [SchoolModuleController::class, 'store'])->name('modules.store');
    Route::get('/school/{module}/{id}', [SchoolModuleController::class, 'show'])->whereNumber('id')->name('modules.show');
    Route::get('/school/{module}/{id}/edit', [SchoolModuleController::class, 'edit'])->name('modules.edit');
    Route::put('/school/{module}/{id}', [SchoolModuleController::class, 'update'])->name('modules.update');
    Route::delete('/school/{module}/{id}', [SchoolModuleController::class, 'destroy'])->name('modules.destroy');
});
