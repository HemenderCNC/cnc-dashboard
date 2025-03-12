<?php

namespace App\Http\Controllers;
use App\Models\Leave;
use App\Models\Clients;
use App\Models\User;
use App\Models\Holiday;
use App\Models\Project;
use App\Models\Notice;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $now = Carbon::now();
        $today = $now->toDateString();
        $totalUsers = User::count();
        $totalClients = Clients::count();
        $totalProjects = Project::count();
        $notices = Notice::where('status','visible')->where('start_date', '>=', $today)->orderBy('created_at', 'desc')->get();
        $upcomingHolidays = Holiday::where('festival_date', '>=', $today)
            ->orderBy('festival_date', 'asc')
            ->get();
        $upcomingLeaves = Leave::where('start_date', '>=', $today)
        ->where('status', 'approved')
        ->orderBy('start_date', 'asc')
        ->with([
            'employee' => function ($query) {
                $query->select('_id', 'name', 'email', 'profile_photo', 'birthdate', 'blood_group', 'designation_id')
                    ->with('designation:_id,name')
                    ->with([
                        'latestTimesheet' => function ($q) {
                            $q->select('employee_id', 'project_id', 'created_at')
                                ->latestForEmployee()
                                ->with(['project' => function ($p) {
                                    $p->select('_id', 'project_name'); // Ensure name is selected
                                }]);
                        }
                    ]);
            }
        ])
        ->get();

        return response()->json([
            'total_users' => $totalUsers,
            'total_clients' => $totalClients,
            'total_projects' => $totalProjects,
            'notices' => $notices,
            'upcoming_holidays' => $upcomingHolidays,
            'upcoming_leaves' => $upcomingLeaves,
        ]);
    }
}
