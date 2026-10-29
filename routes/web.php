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

    Route::get('/school/{module}', [SchoolModuleController::class, 'index'])->name('modules.index');
    Route::get('/school/{module}/create', [SchoolModuleController::class, 'create'])->name('modules.create');
    Route::post('/school/{module}', [SchoolModuleController::class, 'store'])->name('modules.store');
    Route::get('/school/{module}/{id}', [SchoolModuleController::class, 'show'])->whereNumber('id')->name('modules.show');
    Route::get('/school/{module}/{id}/edit', [SchoolModuleController::class, 'edit'])->name('modules.edit');
    Route::put('/school/{module}/{id}', [SchoolModuleController::class, 'update'])->name('modules.update');
    Route::delete('/school/{module}/{id}', [SchoolModuleController::class, 'destroy'])->name('modules.destroy');
});
