<?php
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\UserFieldOptionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TaskTypeController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DesignationController;
use App\Http\Controllers\QualificationController;
use App\Http\Controllers\SkillController;
use App\Http\Controllers\EmployeeTypesController;

Route::middleware('api')->group(function () {
    // Public routes
    Route::post('register', [AuthController::class, 'registerAdmin']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('otp-verify', [AuthController::class, 'otpVerify']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);

    // Protected routes with auth.token
    Route::middleware('auth.token')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('dashboard', [AuthController::class, 'dashboard']);

        Route::get('permissions', [PermissionController::class, 'getAllPermissions']); // Get all permissions

        Route::prefix('task-types')->group(function () {
            Route::get('/', [TaskTypeController::class, 'index']);
            Route::post('/', [TaskTypeController::class, 'store']);
            Route::get('/{id}', [TaskTypeController::class, 'show']);
            Route::put('/{id}', [TaskTypeController::class, 'update']);
            Route::delete('/{id}', [TaskTypeController::class, 'destroy']);
        });

        //Role group
        Route::middleware(['permission:678e3b79b9a4b5377a0d1793'])->group(function () {
            Route::post('roles', [RoleController::class, 'addRole']);        // Add Role
            Route::put('roles/{id}', [RoleController::class, 'editRole']);  // Edit Role
            Route::delete('roles/{id}', [RoleController::class, 'deleteRole']); // Delete Role
            Route::get('roles', [RoleController::class, 'getAllRoles']); // Get all roles
            Route::get('role/{id}', [RoleController::class, 'getRoleById']); // Get all roles
        });


        //Permissions group
        Route::middleware(['permission:678e3b79b9a4b5377a0d1794'])->group(function () {
            Route::post('permission', [PermissionController::class, 'addPermission']);        // Add Permission
            Route::get('permission/{id}', [PermissionController::class, 'getPermissionById']);        // Add Permission
            Route::put('permission/{id}', [PermissionController::class, 'editPermission']);  // Edit Permission
            Route::delete('permission/{id}', [PermissionController::class, 'deletePermission']); // Delete Permission
        });

        //User API group for Admin panel
        Route::middleware(['permission:678e3b79b9a4b5377a0d1793'])->group(function () {
            Route::get('getuserfieldoptions', [UserFieldOptionController::class, 'getOptions']);        // Get all options for User Employee field options
            Route::get('user/{id}', [UserController::class, 'getUserById']); // Get a user details by user ID
            Route::get('users', [UserController::class, 'getAllUsers']); //Get all users
            Route::post('users', [UserController::class, 'addUser']);
            Route::post('users/{id}', [UserController::class, 'editUser']);  // Edit user
            //User profile picture
            Route::post('users/{id}/update-profile-picture', [UserController::class, 'updateProfilePicture']); //update user profile picture
            Route::delete('users/{id}/delete-profile-picture', [UserController::class, 'deleteProfilePicture']); //update user profile picture
            //User qualification document
            Route::post('users/{id}/update-qualification-document', [UserController::class, 'updateQualificationDocument']); //update user profile picture
            Route::delete('users/{id}/delete-qualification-document', [UserController::class, 'deleteQualificationDocument']); //update user profile picture

            //Delete User
            Route::delete('users/{id}', [UserController::class, 'deleteUser']);
        });

        //Department API
        Route::apiResource('departments', DepartmentController::class);


        //Designation API
        Route::apiResource('designations', DesignationController::class);

        //Qualifications API
        Route::apiResource('qualifications', QualificationController::class);

        //Skills API
        Route::apiResource('skills', SkillController::class);

        //Employee Type API
        Route::apiResource('employee-types', EmployeeTypesController::class);

        //Work Location API
        Route::apiResource('employee-types', EmployeeTypesController::class);
    });
});
