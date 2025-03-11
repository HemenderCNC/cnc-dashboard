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
            ->orderBy('start_date', 'asc')->with('employee')
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
