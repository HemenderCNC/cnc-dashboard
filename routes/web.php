<?php
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use App\Models\Permission;

Route::get('/test-mongo', function () {
    $permissions = Permission::all();
    return response()->json($permissions);
});
// Public routes
Route::get('/', function () {
    return redirect('login');
});

// Admin registration
Route::get('register-admin', [AuthController::class, 'showAdminRegistrationForm'])->name('register-admin');
Route::post('register-admin', [AuthController::class, 'registerAdmin']);

// Login and authentication
Route::get('login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('login', [AuthController::class, 'login']);

// Authenticated routes
Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [AuthController::class, 'dashboard'])->name('dashboard');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
});
