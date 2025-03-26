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
use App\Http\Controllers\PermissionModulesController;
use App\Http\Controllers\MovieTicketController;

Route::middleware('api')->group(function () {
    // Public routes
    Route::post('register', [AuthController::class, 'registerAdmin']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('otp-verify', [AuthController::class, 'otpVerify']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);

    // Protected routes with auth.token
    Route::middleware('auth.token')->group(function () {
        Route::get('logout', [AuthController::class, 'logout']);
        Route::get('track-session', [LoginSessionController::class, 'trackSession']);
        Route::get('attendance', [LoginSessionController::class, 'attendance']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('dashboard', [AuthController::class, 'dashboard']);

        Route::get('permissions', [PermissionController::class, 'getAllPermissions']); // Get all permissions

        Route::prefix('task-types')->group(function () {
            Route::get('/', [TaskTypeController::class, 'index'])->middleware('permission:view_task_type');
            Route::post('/', [TaskTypeController::class, 'store'])->middleware('permission:add_task_type');
            Route::get('/{id}', [TaskTypeController::class, 'show'])->middleware('permission:view_task_type');
            Route::put('/{id}', [TaskTypeController::class, 'update'])->middleware('permission:edit_task_type');
            Route::delete('/{id}', [TaskTypeController::class, 'destroy'])->middleware('permission:delete_task_type');
        });

        //Role group
        Route::post('roles', [RoleController::class, 'addRole'])->middleware('permission:add_role');        // Add Role
        Route::put('roles/{id}', [RoleController::class, 'editRole'])->middleware('permission:edit_role');  // Edit Role
        Route::delete('roles/{id}', [RoleController::class, 'deleteRole'])->middleware('permission:delete_role'); // Delete Role
        Route::get('roles', [RoleController::class, 'getAllRoles'])->middleware('permission:view_role'); // Get all roles
        Route::get('role/{id}', [RoleController::class, 'getRoleById'])->middleware('permission:view_role'); // Get all roles

        // Route::middleware(['permission:678e3b79b9a4b5377a0d1793'])->group(function () {
        //     Route::post('roles', [RoleController::class, 'addRole']);        // Add Role
        //     Route::put('roles/{id}', [RoleController::class, 'editRole']);  // Edit Role
        //     Route::delete('roles/{id}', [RoleController::class, 'deleteRole']); // Delete Role
        //     Route::get('roles', [RoleController::class, 'getAllRoles']); // Get all roles
        //     Route::get('role/{id}', [RoleController::class, 'getRoleById']); // Get all roles
        // });

        // Route::middleware(['permission:678e3b79b9a4b5377a0d1793'])->group(function () {
            Route::get('/permissionsmodule/grouped', [PermissionModulesController::class, 'getGroupedPermissions']);
        // });





        //Permissions group
        Route::middleware(['permission:add_permission'])->group(function () {
            Route::post('permission', [PermissionController::class, 'addPermission']);        // Add Permission
        });
        Route::middleware(['permission:delete_permission'])->group(function () {
            Route::delete('permission/{id}', [PermissionController::class, 'deletePermission']); // Delete Permission
        });
        Route::middleware(['permission:view_permission'])->group(function () {
            Route::get('permission/{id}', [PermissionController::class, 'getPermissionById']); // Get Permission
        });
        Route::middleware(['permission:edit_permission'])->group(function () {
            Route::put('permission/{id}', [PermissionController::class, 'editPermission']);  // Edit Permission
        });

        // Route::middleware(['permission:678e3b79b9a4b5377a0d1794'])->group(function () {
        //     Route::post('permission', [PermissionController::class, 'addPermission']);        // Add Permission
        //     Route::get('permission/{id}', [PermissionController::class, 'getPermissionById']);        // Add Permission
        //     Route::put('permission/{id}', [PermissionController::class, 'editPermission']);  // Edit Permission
        //     Route::delete('permission/{id}', [PermissionController::class, 'deletePermission']); // Delete Permission
        // });

        //User API group for Admin panel
        Route::get('getuserfieldoptions', [UserFieldOptionController::class, 'getOptions']);        // Get all options for User Employee field options
        Route::get('users', [UserController::class, 'getAllUsers'])->middleware('permission:view_user'); //Get all users
        Route::get('user/{id}', [UserController::class, 'getUserById'])->middleware('permission:view_user'); // Get a user details by user ID
        Route::post('users', [UserController::class, 'addUser'])->middleware('permission:add_user');
        Route::post('users/{id}', [UserController::class, 'editUser'])->middleware('permission:edit_user');  // Edit user
        Route::delete('users/{id}', [UserController::class, 'deleteUser'])->middleware('permission:delete_user');

        // Route::middleware(['permission:678e3b79b9a4b5377a0d1793'])->group(function () {
        //     Route::get('getuserfieldoptions', [UserFieldOptionController::class, 'getOptions']);        // Get all options for User Employee field options
        //     Route::get('user/{id}', [UserController::class, 'getUserById']); // Get a user details by user ID
        //     Route::get('users', [UserController::class, 'getAllUsers']); //Get all users
        //     Route::post('users', [UserController::class, 'addUser']);
        //     Route::post('users/{id}', [UserController::class, 'editUser']);  // Edit user
        //     //User profile picture
        //     // Route::post('users/{id}/update-profile-picture', [UserController::class, 'updateProfilePicture']); //update user profile picture
        //     // Route::delete('users/{id}/delete-profile-picture', [UserController::class, 'deleteProfilePicture']); //update user profile picture
        //     // //User qualification document
        //     // Route::post('users/{id}/update-qualification-document', [UserController::class, 'updateQualificationDocument']); //update user profile picture
        //     // Route::delete('users/{id}/delete-qualification-document', [UserController::class, 'deleteQualificationDocument']); //update user profile picture

        //     //Delete User
        //     Route::delete('users/{id}', [UserController::class, 'deleteUser']);
        // });

        //Department API
        // Route::apiResource('departments', DepartmentController::class);
        Route::prefix('departments')->group(function () {
            // Get all industry types
            Route::get('/', [DepartmentController::class, 'index'])->middleware('permission:view_department');

            // Create a new industry type
            Route::post('/', [DepartmentController::class, 'store'])->middleware('permission:add_department');

            // Get a single industry type by ID
            Route::get('/{id}', [DepartmentController::class, 'show'])->middleware('permission:view_department');

            // Update an industry type
            Route::put('/{id}', [DepartmentController::class, 'update'])->middleware('permission:edit_department');

            // Delete an industry type
            Route::delete('/{id}', [DepartmentController::class, 'destroy'])->middleware('permission:delete_department');
        });

        // Route::apiResource('countries', CountriesController::class);
        Route::prefix('countries')->group(function () {
            // Get all industry types
            Route::get('/', [CountriesController::class, 'index'])->middleware('permission:view_country');

            // Create a new industry type
            Route::post('/', [CountriesController::class, 'store'])->middleware('permission:add_country');

            // Get a single industry type by ID
            Route::get('/{id}', [CountriesController::class, 'show'])->middleware('permission:view_country');

            // Update an industry type
            Route::put('/{id}', [CountriesController::class, 'update'])->middleware('permission:edit_country');

            // Delete an industry type
            Route::delete('/{id}', [CountriesController::class, 'destroy'])->middleware('permission:delete_country');
        });

        // Route::apiResource('industry-types', IndustryTypesController::class);
        Route::prefix('industry-types')->group(function () {
            // Get all industry types
            Route::get('/', [IndustryTypesController::class, 'index'])->middleware('permission:view_industry_type');

            // Create a new industry type
            Route::post('/', [IndustryTypesController::class, 'store'])->middleware('permission:add_industry_type');

            // Get a single industry type by ID
            Route::get('/{id}', [IndustryTypesController::class, 'show'])->middleware('permission:view_industry_type');

            // Update an industry type
            Route::put('/{id}', [IndustryTypesController::class, 'update'])->middleware('permission:edit_industry_type');

            // Delete an industry type
            Route::delete('/{id}', [IndustryTypesController::class, 'destroy'])->middleware('permission:delete_industry_type');
        });


        //Designation API
        // Route::apiResource('designations', DesignationController::class);
        Route::prefix('designations')->group(function () {
            // Get all designations
            Route::get('/', [DesignationController::class, 'index'])->middleware('permission:view_designation');

            // Create a new designation
            Route::post('/', [DesignationController::class, 'store'])->middleware('permission:add_designation');

            // Get a single designation by ID
            Route::get('/{id}', [DesignationController::class, 'show'])->middleware('permission:view_designation');

            // Update a designation
            Route::put('/{id}', [DesignationController::class, 'update'])->middleware('permission:edit_designation');

            // Delete a designation
            Route::delete('/{id}', [DesignationController::class, 'destroy'])->middleware('permission:delete_designation');
        });


        //Qualifications API
        // Route::apiResource('qualifications', QualificationController::class);
        Route::prefix('qualifications')->group(function () {
            // Get all qualifications
            Route::get('/', [QualificationController::class, 'index'])->middleware('permission:view_qualification');

            // Create a new qualification
            Route::post('/', [QualificationController::class, 'store'])->middleware('permission:add_qualification');

            // Get a single qualification by ID
            Route::get('/{id}', [QualificationController::class, 'show'])->middleware('permission:view_qualification');

            // Update a qualification
            Route::put('/{id}', [QualificationController::class, 'update'])->middleware('permission:edit_qualification');

            // Delete a qualification
            Route::delete('/{id}', [QualificationController::class, 'destroy'])->middleware('permission:delete_qualification');
        });


        //Skills API
        // Route::apiResource('skills', SkillController::class);
        Route::prefix('skills')->group(function () {
            // Get all skills
            Route::get('/', [SkillController::class, 'index'])->middleware('permission:view_skill');

            // Create a new skill
            Route::post('/', [SkillController::class, 'store'])->middleware('permission:add_skill');

            // Get a single skill by ID
            Route::get('/{id}', [SkillController::class, 'show'])->middleware('permission:view_skill');

            // Update a skill
            Route::put('/{id}', [SkillController::class, 'update'])->middleware('permission:edit_skill');

            // Delete a skill
            Route::delete('/{id}', [SkillController::class, 'destroy'])->middleware('permission:delete_skill');
        });


        //Employee Type API
        // Route::apiResource('employee-types', EmployeeTypesController::class);
        Route::prefix('employee-types')->group(function () {
            // Get all employee types
            Route::get('/', [EmployeeTypesController::class, 'index'])->middleware('permission:view_employee_type');

            // Create a new employee type
            Route::post('/', [EmployeeTypesController::class, 'store'])->middleware('permission:add_employee_type');

            // Get a single employee type by ID
            Route::get('/{id}', [EmployeeTypesController::class, 'show'])->middleware('permission:view_employee_type');

            // Update an employee type
            Route::put('/{id}', [EmployeeTypesController::class, 'update'])->middleware('permission:edit_employee_type');

            // Delete an employee type
            Route::delete('/{id}', [EmployeeTypesController::class, 'destroy'])->middleware('permission:delete_employee_type');
        });


        //Work Location API
        // Route::apiResource('work-location', WorkLocationController::class);
        Route::prefix('work-location')->group(function () {
            // Get all work locations
            Route::get('/', [WorkLocationController::class, 'index'])->middleware('permission:view_work_location');

            // Create a new work location
            Route::post('/', [WorkLocationController::class, 'store'])->middleware('permission:add_work_location');

            // Get a single work location by ID
            Route::get('/{id}', [WorkLocationController::class, 'show'])->middleware('permission:view_work_location');

            // Update a work location
            Route::put('/{id}', [WorkLocationController::class, 'update'])->middleware('permission:edit_work_location');

            // Delete a work location
            Route::delete('/{id}', [WorkLocationController::class, 'destroy'])->middleware('permission:delete_work_location');
        });


        //Employee Status API
        // Route::apiResource('employee-status', EmployeeStatusController::class);
        Route::prefix('employee-status')->group(function () {
            // Get all employee statuses
            Route::get('/', [EmployeeStatusController::class, 'index'])->middleware('permission:view_employee_status');

            // Create a new employee status
            Route::post('/', [EmployeeStatusController::class, 'store'])->middleware('permission:add_employee_status');

            // Get a single employee status by ID
            Route::get('/{id}', [EmployeeStatusController::class, 'show'])->middleware('permission:view_employee_status');

            // Update an employee status
            Route::put('/{id}', [EmployeeStatusController::class, 'update'])->middleware('permission:edit_employee_status');

            // Delete an employee status
            Route::delete('/{id}', [EmployeeStatusController::class, 'destroy'])->middleware('permission:delete_employee_status');
        });


        //Project Status API
        // Route::apiResource('project-status', ProjectStatusController::class);
        Route::prefix('project-status')->group(function () {
            // Get all project statuses
            Route::get('/', [ProjectStatusController::class, 'index'])->middleware('permission:view_project_status');

            // Create a new project status
            Route::post('/', [ProjectStatusController::class, 'store'])->middleware('permission:add_project_status');

            // Get a single project status by ID
            Route::get('/{id}', [ProjectStatusController::class, 'show'])->middleware('permission:view_project_status');

            // Update a project status
            Route::put('/{id}', [ProjectStatusController::class, 'update'])->middleware('permission:edit_project_status');

            // Delete a project status
            Route::delete('/{id}', [ProjectStatusController::class, 'destroy'])->middleware('permission:delete_project_status');
        });


        //Document Type API
        // Route::apiResource('document-types', DocumentTypeController::class);
        Route::prefix('document-types')->group(function () {
            // Get all document types
            Route::get('/', [DocumentTypeController::class, 'index'])->middleware('permission:view_document_type');

            // Create a new document type
            Route::post('/', [DocumentTypeController::class, 'store'])->middleware('permission:add_document_type');

            // Get a single document type by ID
            Route::get('/{id}', [DocumentTypeController::class, 'show'])->middleware('permission:view_document_type');

            // Update a document type
            Route::put('/{id}', [DocumentTypeController::class, 'update'])->middleware('permission:edit_document_type');

            // Delete a document type
            Route::delete('/{id}', [DocumentTypeController::class, 'destroy'])->middleware('permission:delete_document_type');
        });


        //Platform API
        // Route::apiResource('platforms', PlatformController::class);
        Route::prefix('platforms')->group(function () {
            // Get all platforms
            Route::get('/', [PlatformController::class, 'index'])->middleware('permission:view_platform');

            // Create a new platform
            Route::post('/', [PlatformController::class, 'store'])->middleware('permission:add_platform');

            // Get a single platform by ID
            Route::get('/{id}', [PlatformController::class, 'show'])->middleware('permission:view_platform');

            // Update a platform
            Route::put('/{id}', [PlatformController::class, 'update'])->middleware('permission:edit_platform');

            // Delete a platform
            Route::delete('/{id}', [PlatformController::class, 'destroy'])->middleware('permission:delete_platform');
        });


        //Languages API
        // Route::apiResource('languages', LanguagesController::class);
        Route::prefix('languages')->group(function () {
            // Get all languages
            Route::get('/', [LanguagesController::class, 'index'])->middleware('permission:view_language');

            // Create a new language
            Route::post('/', [LanguagesController::class, 'store'])->middleware('permission:add_language');

            // Get a single language by ID
            Route::get('/{id}', [LanguagesController::class, 'show'])->middleware('permission:view_language');

            // Update a language
            Route::put('/{id}', [LanguagesController::class, 'update'])->middleware('permission:edit_language');

            // Delete a language
            Route::delete('/{id}', [LanguagesController::class, 'destroy'])->middleware('permission:delete_language');
        });


        //Milestones API
        // Route::apiResource('milestones', MilestoneController::class);
        Route::prefix('milestones')->group(function () {
            // Get all milestones
            Route::get('/', [MilestoneController::class, 'index'])->middleware('permission:view_milestone');

            // Create a new milestone
            Route::post('/', [MilestoneController::class, 'store'])->middleware('permission:add_milestone');

            // Get a single milestone by ID
            Route::get('/{id}', [MilestoneController::class, 'show'])->middleware('permission:view_milestone');

            // Update a milestone
            Route::put('/{id}', [MilestoneController::class, 'update'])->middleware('permission:edit_milestone');

            // Delete a milestone
            Route::delete('/{id}', [MilestoneController::class, 'destroy'])->middleware('permission:delete_milestone');
        });

        // Route::post('/project-milestones/update-order', [MilestoneController::class, 'updateMilestoneOrder']);
        Route::post('/project-milestones/update-order', [MilestoneController::class, 'updateMilestoneOrder'])->middleware('permission:edit_milestone');


        //TaskStatus API
        // Route::apiResource('task-status', TaskStatusController::class);
        Route::prefix('task-status')->group(function () {
            // Get all task statuses
            Route::get('/', [TaskStatusController::class, 'index'])->middleware('permission:view_task_status');

            // Create a new task status
            Route::post('/', [TaskStatusController::class, 'store'])->middleware('permission:add_task_status');

            // Get a single task status
            Route::get('/{id}', [TaskStatusController::class, 'show'])->middleware('permission:view_task_status');

            // Update a task status
            Route::put('/{id}', [TaskStatusController::class, 'update'])->middleware('permission:edit_task_status');

            // Delete a task status
            Route::delete('/{id}', [TaskStatusController::class, 'destroy'])->middleware('permission:delete_task_status');
        });



        //Clients API
        Route::prefix('clients')->group(function () {
            Route::middleware(['permission:view_client'])->group(function () {
                Route::get('/', [ClientsController::class, 'index']);
                Route::get('/{id}', [ClientsController::class, 'show']);
            });
            Route::post('/', [ClientsController::class, 'store'])->middleware('permission:add_client');
            Route::post('/{id}', [ClientsController::class, 'update'])->middleware('permission:edit_client');
            Route::delete('/{id}', [ClientsController::class, 'destroy'])->middleware('permission:delete_client');


            // Route::get('/', [ClientsController::class, 'index']); // Get all holidays
            // Route::post('/', [ClientsController::class, 'store']); // Create a holiday
            // Route::get('/{id}', [ClientsController::class, 'show']); // Get holiday by ID
            // Route::post('/{id}', [ClientsController::class, 'update']); // Update a holiday
            // Route::delete('/{id}', [ClientsController::class, 'destroy']); // Delete a holiday
        });

        //Movie Tickets API
        // Route::middleware(['permission:edit_role'])->group(function () {
        //     Route::put('permission/{id}', [PermissionController::class, 'editPermission']);  // Edit Permission
        // });
        Route::prefix('movie-tickets')->group(function () {
            // Add movie ticket
            Route::post('/', [MovieTicketController::class, 'store'])->middleware('permission:add_movie_ticket');

            // View movie tickets
            Route::middleware(['permission:view_movie_ticket'])->group(function () {
                Route::get('/', [MovieTicketController::class, 'index']); // Get all
                Route::get('/{id}', [MovieTicketController::class, 'show']); // Get single
            });

            // Update movie ticket
            Route::post('/{id}', [MovieTicketController::class, 'update'])->middleware('permission:edit_movie_ticket');

            // Delete movie ticket
            Route::delete('/{id}', [MovieTicketController::class, 'destroy'])->middleware('permission:delete_movie_ticket');
        });

        // Route::prefix('movie-tickets')->group(function () {
        //     Route::post('/', [MovieTicketController::class, 'store']); // Create
        //     Route::get('/', [MovieTicketController::class, 'index']); // Get All
        //     Route::get('/{id}', [MovieTicketController::class, 'show']); // Get Single
        //     Route::post('/{id}', [MovieTicketController::class, 'update']); // Update
        //     Route::delete('/{id}', [MovieTicketController::class, 'destroy']); // Delete
        // });

        //Dashboard API
        Route::prefix('dashboard')->group(function () {
            Route::get('/', [DashboardController::class, 'index']); // Get all holidays
        });

        //Tasks API
        Route::prefix('tasks')->group(function () {
            // View tasks
            Route::middleware(['permission:view_task'])->group(function () {
                Route::get('/project-tasks-summary', [TasksController::class, 'getTasksByProject']);
                Route::get('/project-milstones-summary', [TasksController::class, 'getProjectMilestonesSummary']);
                Route::get('/', [TasksController::class, 'index']); // Get all tasks
                Route::get('/{id}', [TasksController::class, 'show']); // Get task by ID
            });

            // Add task
            Route::post('/', [TasksController::class, 'store'])->middleware('permission:add_task');

            // Update task
            Route::post('/{id}', [TasksController::class, 'update'])->middleware('permission:edit_task');

            // Delete task
            Route::delete('/{id}', [TasksController::class, 'destroy'])->middleware('permission:delete_task');
        });


        // Route::prefix('tasks')->group(function () {
        //     //Get task of a project for logedin user
        //     Route::get('/project-tasks-summary', [TasksController::class, 'getTasksByProject']);
        //     Route::get('/project-milstones-summary', [TasksController::class, 'getProjectMilestonesSummary']);

        //     Route::get('/', [TasksController::class, 'index']); // Get all holidays
        //     Route::post('/', [TasksController::class, 'store']); // Create a holiday
        //     Route::get('/{id}', [TasksController::class, 'show']); // Get holiday by ID
        //     Route::post('/{id}', [TasksController::class, 'update']); // Update a holiday
        //     Route::delete('/{id}', [TasksController::class, 'destroy']); // Delete a holiday
        // });

        //Projects API
        Route::prefix('projects')->group(function () {
            // View project routes
            Route::middleware(['permission:view_project'])->group(function () {
                Route::get('/summary', [ProjectsController::class, 'summary']); // Get summary
                Route::get('/', [ProjectsController::class, 'index']); // Get all projects
                Route::get('/{id}', [ProjectsController::class, 'show']); // Get single project
            });

            // Add project
            Route::post('/', [ProjectsController::class, 'store'])->middleware('permission:add_project'); // Add a project

            // Update project
            Route::post('/', [ProjectsController::class, 'store'])->middleware('permission:edit_project'); // Update a project

            // Delete project
            Route::delete('/{id}', [ProjectsController::class, 'destroy'])->middleware('permission:delete_project'); // Delete a project
        });


        // Route::prefix('projects')->group(function () {
        //     Route::get('/summary', [ProjectsController::class, 'summary']); // Get all holidays
        //     Route::get('/', [ProjectsController::class, 'index']); // Get all holidays
        //     Route::post('/', [ProjectsController::class, 'store']); // Create a holiday
        //     Route::get('/{id}', [ProjectsController::class, 'show']); // Get holiday by ID
        //     Route::post('/{id}', [ProjectsController::class, 'update']); // Update a holiday
        //     Route::delete('/{id}', [ProjectsController::class, 'destroy']); // Delete a holiday
        // });

        //Leave Module
        Route::prefix('leaves')->group(function () {

            Route::middleware(['permission:view_leave'])->group(function () {
                Route::get('/leaves-summary', [LeaveController::class, 'getLeaveSummary']); // Get summary
                Route::get('/', [LeaveController::class, 'index']); // Employee views own leaves
                Route::get('/{id}', [LeaveController::class, 'show']); // View specific leave request
            });
            Route::middleware(['permission:add_leave'])->group(function () {
                Route::post('/', [LeaveController::class, 'store']); // Employee requests leave
            });
            Route::middleware(['permission:edit_leave'])->group(function () {
                Route::put('/{id}', [LeaveController::class, 'update']); // Update leave request (only if pending)
            });
            Route::middleware(['permission:cancel_leave'])->group(function () {
                Route::post('/{id}/cancel', [LeaveController::class, 'cancel']); // Cancel leave request (only if start date not passed)
            });
            Route::middleware(['permission:approve_leave'])->group(function () {
                Route::put('/{id}/approve', [LeaveController::class, 'approve']);
            });
            Route::middleware(['permission:reject_leave'])->group(function () {
                Route::put('/{id}/reject', [LeaveController::class, 'reject']);
            });


            // Route::get('/leaves-summary', [LeaveController::class, 'getLeaveSummary']);
            // Route::get('/', [LeaveController::class, 'index']); // Employee views own leaves
            // Route::post('/', [LeaveController::class, 'store']); // Employee requests leave
            // Route::get('/{id}', [LeaveController::class, 'show']); // View specific leave request
            // Route::put('/{id}', [LeaveController::class, 'update']); // Update leave request (only if pending)
            // Route::post('/{id}/cancel', [LeaveController::class, 'cancel']); // Cancel leave request (only if start date not passed)
            // Route::put('/{id}/approve', [LeaveController::class, 'approve']);
            // Route::put('/{id}/reject', [LeaveController::class, 'reject']);
        });


        //Notice Module
        Route::prefix('notices')->group(function () {
            Route::middleware(['permission:view_notice'])->group(function () {
                Route::get('/', [NoticeController::class, 'index']); // Get all notices
                Route::get('/{id}', [NoticeController::class, 'show']); // Get notice details
            });
            Route::middleware(['permission:add_notice'])->group(function () {
                Route::post('/', [NoticeController::class, 'store']); // Create a notice
            });
            Route::middleware(['permission:edit_notice'])->group(function () {
                Route::put('/{id}', [NoticeController::class, 'update']); // Update a notice
            });
            Route::middleware(['permission:change_status'])->group(function () {
                Route::put('/{id}/status', [NoticeController::class, 'changeStatus']);
            });
            Route::middleware(['permission:delete_notice'])->group(function () {
                Route::delete('/{id}', [NoticeController::class, 'destroy']); // Delete a notice
            });


            // Route::get('/', [NoticeController::class, 'index']); // Get all notices
            // Route::post('/', [NoticeController::class, 'store']); // Create a notice
            // Route::get('/{id}', [NoticeController::class, 'show']); // Get notice details
            // Route::put('/{id}', [NoticeController::class, 'update']); // Update a notice
            // Route::put('/{id}/status', [NoticeController::class, 'changeStatus']);
            // Route::delete('/{id}', [NoticeController::class, 'destroy']); // Delete a notice
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

            Route::middleware(['permission:view_holiday'])->group(function () {
                Route::get('/', [HolidayController::class, 'index']); // Get all holidays
                Route::get('/{id}', [HolidayController::class, 'show']); // Get holiday by ID
            });
            Route::middleware(['permission:add_holiday'])->group(function () {
                Route::post('/', [HolidayController::class, 'store']); // Create a holiday
            });
            Route::middleware(['permission:edit_holiday'])->group(function () {
                Route::post('/{id}', [HolidayController::class, 'update']); // Update a holiday
            });
            Route::middleware(['permission:delete_holiday'])->group(function () {
                Route::delete('/{id}', [HolidayController::class, 'destroy']); // Delete a holiday
            });

            // Route::get('/', [HolidayController::class, 'index']); // Get all holidays
            // Route::post('/', [HolidayController::class, 'store']); // Create a holiday
            // Route::get('/{id}', [HolidayController::class, 'show']); // Get holiday by ID
            // Route::post('/{id}', [HolidayController::class, 'update']); // Update a holiday
            // Route::delete('/{id}', [HolidayController::class, 'destroy']); // Delete a holiday
        });

        //Timesheet Module
        Route::prefix('timesheet')->group(function () {

            Route::middleware(['permission:view_timesheet'])->group(function () {
                Route::get('/', [TimesheetController::class, 'index']); // Get all timesheets
                Route::get('/{id}', [TimesheetController::class, 'show']); // Get timesheet by ID
                Route::get('/stop-task/{id}', [TimesheetController::class, 'stopTask']); // Get timesheet by ID
                Route::get('/run-task/{id}', [TimesheetController::class, 'runTask']); // Get timesheet by ID
                Route::get('/complete-task/{id}', [TimesheetController::class, 'completeTask']); // Get timesheet by ID
            });
            Route::middleware(['permission:add_timesheet'])->group(function () {
                Route::post('/', [TimesheetController::class, 'store']); // Create a timesheet
            });
            Route::middleware(['permission:edit_timesheet'])->group(function () {
                Route::post('/{id}', [TimesheetController::class, 'update']); // Update a timesheet
            });
            Route::middleware(['permission:delete_timesheet'])->group(function () {
                Route::delete('/{id}', [TimesheetController::class, 'destroy']); // Delete a timesheet
            });

            // Route::get('/', [TimesheetController::class, 'index']); // Get all timesheets
            // Route::post('/', [TimesheetController::class, 'store']); // Create a timesheet
            // Route::get('/{id}', [TimesheetController::class, 'show']); // Get timesheet by ID
            // Route::get('/stop-task/{id}', [TimesheetController::class, 'stopTask']); // Get timesheet by ID
            // Route::get('/run-task/{id}', [TimesheetController::class, 'runTask']); // Get timesheet by ID
            // Route::get('/complete-task/{id}', [TimesheetController::class, 'completeTask']); // Get timesheet by ID
            // Route::post('/{id}', [TimesheetController::class, 'update']); // Update a timesheet
            // Route::delete('/{id}', [TimesheetController::class, 'destroy']); // Delete a timesheet

        });
        Route::get('/start-break', [TimesheetController::class, 'startBreak']);
        Route::get('/stop-break', [TimesheetController::class, 'stopBreak']);

        //General settings module
        Route::prefix('updategeneralsettings')->group(function () {
            //Route::get('/', [GeneralSettingsController::class, 'index']); // Get all settings
            Route::post('/', [GeneralSettingsController::class, 'update'])->middleware('permission:edit_general_settings'); // Update settings
        });


        //Activity Logs listing
        Route::get('/activity-logs', [ActivityLogController::class, 'getActivityLogs']);
        Route::get('getgeneralsettings/protected', [GeneralSettingsController::class, 'index']); // Update settings
    });
});
Route::get('getgeneralsettings/', [GeneralSettingsController::class, 'index']); // Update settings
