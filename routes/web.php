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
use App\Http\Controllers\SchoolModuleController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\ProfileController;

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
    Route::get('/classes/{schoolClass}/timetable', [SchoolModuleController::class, 'classTimetable'])->name('classes.timetable');
    Route::get('/school/{module}', [SchoolModuleController::class, 'index'])->name('modules.index');
    Route::get('/school/{module}/create', [SchoolModuleController::class, 'create'])->name('modules.create');
    Route::post('/school/{module}', [SchoolModuleController::class, 'store'])->name('modules.store');
    Route::get('/school/{module}/{id}', [SchoolModuleController::class, 'show'])->whereNumber('id')->name('modules.show');
    Route::get('/school/{module}/{id}/edit', [SchoolModuleController::class, 'edit'])->name('modules.edit');
    Route::put('/school/{module}/{id}', [SchoolModuleController::class, 'update'])->name('modules.update');
    Route::delete('/school/{module}/{id}', [SchoolModuleController::class, 'destroy'])->name('modules.destroy');
});
