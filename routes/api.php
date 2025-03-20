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
use App\Http\Controllers\WorkLocationController;
use App\Http\Controllers\EmployeeStatusController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\NoticeController;
use App\Http\Controllers\DocumentTypeController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\ProjectStatusController;
use App\Http\Controllers\PlatformController;
use App\Http\Controllers\LanguagesController;
use App\Http\Controllers\ProjectsController;
use App\Http\Controllers\ClientsController;
use App\Http\Controllers\MilestoneController;
use App\Http\Controllers\TaskStatusController;
use App\Http\Controllers\TasksController;
use App\Http\Controllers\TimesheetController;
use App\Http\Controllers\GeneralSettingsController;
use App\Http\Controllers\LoginSessionController;
use App\Http\Controllers\CountriesController;
use App\Http\Controllers\IndustryTypesController;
use App\Http\Controllers\ProjectFilesController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HelpingHandController;

Route::middleware('api')->group(function () {
    // Public routes
    Route::post('register', [AuthController::class, 'registerAdmin']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('otp-verify', [AuthController::class, 'otpVerify']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);

    // Protected routes with auth.token
    Route::middleware('auth.token')->group(function () {
        Route::get('track-session', [LoginSessionController::class, 'trackSession']);
        Route::get('attendance', [LoginSessionController::class, 'attendance']);
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
            // Route::post('users/{id}/update-profile-picture', [UserController::class, 'updateProfilePicture']); //update user profile picture
            // Route::delete('users/{id}/delete-profile-picture', [UserController::class, 'deleteProfilePicture']); //update user profile picture
            // //User qualification document
            // Route::post('users/{id}/update-qualification-document', [UserController::class, 'updateQualificationDocument']); //update user profile picture
            // Route::delete('users/{id}/delete-qualification-document', [UserController::class, 'deleteQualificationDocument']); //update user profile picture

            //Delete User
            Route::delete('users/{id}', [UserController::class, 'deleteUser']);
        });

        //Department API
        Route::apiResource('departments', DepartmentController::class);

        Route::apiResource('countries', CountriesController::class);

        Route::apiResource('industry-types', IndustryTypesController::class);

        //Designation API
        Route::apiResource('designations', DesignationController::class);

        //Qualifications API
        Route::apiResource('qualifications', QualificationController::class);

        //Skills API
        Route::apiResource('skills', SkillController::class);

        //Employee Type API
        Route::apiResource('employee-types', EmployeeTypesController::class);

        //Work Location API
        Route::apiResource('work-location', WorkLocationController::class);

        //Employee Status API
        Route::apiResource('employee-status', EmployeeStatusController::class);

        //Project Status API
        Route::apiResource('project-status', ProjectStatusController::class);

        //Document Type API
        Route::apiResource('document-types', DocumentTypeController::class);

        //Platform API
        Route::apiResource('platforms', PlatformController::class);

        //Languages API
        Route::apiResource('languages', LanguagesController::class);

        //Milestones API
        Route::apiResource('milestones', MilestoneController::class);
        Route::post('/project-milestones/update-order', [MilestoneController::class, 'updateMilestoneOrder']);


        //TaskStatus API
        Route::apiResource('task-status', TaskStatusController::class);


        //Clients API
        Route::prefix('clients')->group(function () {
            Route::get('/', [ClientsController::class, 'index']); // Get all holidays
            Route::post('/', [ClientsController::class, 'store']); // Create a holiday
            Route::get('/{id}', [ClientsController::class, 'show']); // Get holiday by ID
            Route::post('/{id}', [ClientsController::class, 'update']); // Update a holiday
            Route::delete('/{id}', [ClientsController::class, 'destroy']); // Delete a holiday
        });

        //Dashboard API
        Route::prefix('dashboard')->group(function () {
            Route::get('/', [DashboardController::class, 'index']); // Get all holidays
        });

        //Tasks API
        Route::prefix('tasks')->group(function () {
            //Get task of a project for logedin user
            Route::get('/project-tasks-summary', [TasksController::class, 'getTasksByProject']);
            Route::get('/project-milstones-summary', [TasksController::class, 'getProjectMilestonesSummary']);
            
            Route::get('/', [TasksController::class, 'index']); // Get all holidays
            Route::post('/', [TasksController::class, 'store']); // Create a holiday
            Route::get('/{id}', [TasksController::class, 'show']); // Get holiday by ID
            Route::post('/{id}', [TasksController::class, 'update']); // Update a holiday
            Route::delete('/{id}', [TasksController::class, 'destroy']); // Delete a holiday
        });

        //Projects API
        Route::prefix('projects')->group(function () {
            Route::get('/summary', [ProjectsController::class, 'summary']); // Get all holidays
            Route::get('/', [ProjectsController::class, 'index']); // Get all holidays
            Route::post('/', [ProjectsController::class, 'store']); // Create a holiday
            Route::get('/{id}', [ProjectsController::class, 'show']); // Get holiday by ID
            Route::post('/{id}', [ProjectsController::class, 'update']); // Update a holiday
            Route::delete('/{id}', [ProjectsController::class, 'destroy']); // Delete a holiday
        });

        //Leave Module
        Route::prefix('leaves')->group(function () {
            Route::get('/leaves-summary', [LeaveController::class, 'getLeaveSummary']);
            Route::get('/', [LeaveController::class, 'index']); // Employee views own leaves
            Route::post('/', [LeaveController::class, 'store']); // Employee requests leave
            Route::get('/{id}', [LeaveController::class, 'show']); // View specific leave request
            Route::put('/{id}', [LeaveController::class, 'update']); // Update leave request (only if pending)
            Route::post('/{id}/cancel', [LeaveController::class, 'cancel']); // Cancel leave request (only if start date not passed)
            Route::put('/{id}/approve', [LeaveController::class, 'approve']);
            Route::put('/{id}/reject', [LeaveController::class, 'reject']);
        });


        //Notice Module
        Route::prefix('notices')->group(function () {
            Route::get('/', [NoticeController::class, 'index']); // Get all notices
            Route::post('/', [NoticeController::class, 'store']); // Create a notice
            Route::get('/{id}', [NoticeController::class, 'show']); // Get notice details
            Route::put('/{id}', [NoticeController::class, 'update']); // Update a notice
            Route::put('/{id}/status', [NoticeController::class, 'changeStatus']);
            Route::delete('/{id}', [NoticeController::class, 'destroy']); // Delete a notice
        });

        //Public rout for notice board
        Route::get('/active-notices', [NoticeController::class, 'getVisibleNotices']);
        Route::get('/send-notification', [HelpingHandController::class, 'sendNotification']);
        Route::post('/set-token', [HelpingHandController::class, 'setToken']);


        //project files Module
        Route::prefix('project-files')->group(function () {
            Route::get('/{id}', [ProjectFilesController::class, 'show']);
            Route::get('/detail/{id}', [ProjectFilesController::class, 'detail']);
            Route::post('/', [ProjectFilesController::class, 'store']);
            Route::post('/{id}', [ProjectFilesController::class, 'update']);
            Route::delete('/{id}', [ProjectFilesController::class, 'destroy']);
        });

        //Helping Hand Module
        Route::prefix('helping-hand')->group(function () {
            Route::post('/create', [HelpingHandController::class, 'create']);
            Route::post('/{id}', [HelpingHandController::class, 'updateStatus']);
            Route::get('/', [HelpingHandController::class, 'index']);
        });

        //Holiday Module
        Route::prefix('holidays')->group(function () {
            Route::get('/', [HolidayController::class, 'index']); // Get all holidays
            Route::post('/', [HolidayController::class, 'store']); // Create a holiday
            Route::get('/{id}', [HolidayController::class, 'show']); // Get holiday by ID
            Route::post('/{id}', [HolidayController::class, 'update']); // Update a holiday
            Route::delete('/{id}', [HolidayController::class, 'destroy']); // Delete a holiday
        });

        //Timesheet Module
        Route::prefix('timesheet')->group(function () {
            Route::get('/', [TimesheetController::class, 'index']); // Get all timesheets
            Route::post('/', [TimesheetController::class, 'store']); // Create a timesheet
            Route::get('/{id}', [TimesheetController::class, 'show']); // Get timesheet by ID
            Route::get('/stop-task/{id}', [TimesheetController::class, 'stopTask']); // Get timesheet by ID
            Route::get('/run-task/{id}', [TimesheetController::class, 'runTask']); // Get timesheet by ID
            Route::get('/complete-task/{id}', [TimesheetController::class, 'completeTask']); // Get timesheet by ID
            Route::post('/{id}', [TimesheetController::class, 'update']); // Update a timesheet
            Route::delete('/{id}', [TimesheetController::class, 'destroy']); // Delete a timesheet

        });
        Route::get('/start-break', [TimesheetController::class, 'startBreak']);
        Route::get('/stop-break', [TimesheetController::class, 'stopBreak']);

        //General settings module
        Route::prefix('updategeneralsettings')->group(function () {
            //Route::get('/', [GeneralSettingsController::class, 'index']); // Get all settings
            Route::post('/', [GeneralSettingsController::class, 'update']); // Update settings
        });


        //Activity Logs listing
        Route::get('/activity-logs', [ActivityLogController::class, 'getActivityLogs']);
        Route::get('getgeneralsettings/protected', [GeneralSettingsController::class, 'index']); // Update settings
    });
});
Route::get('getgeneralsettings/', [GeneralSettingsController::class, 'index']); // Update settings
