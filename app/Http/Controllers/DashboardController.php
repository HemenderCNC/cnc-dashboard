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
        $notices = Notice::where('status','visible')->where('start_date', '<=', $today)->where('end_date', '>=', $today)->orderBy('created_at', 'desc')->get();
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


        // ✅ Upcoming Birthdays (Next 2 Months)
        $today = now();

        $upcomingBirthdays = User::select('_id', 'name', 'last_name', 'email', 'profile_photo', 'birthdate')
            ->get()
            ->filter(function ($user) use ($today) {
                if (!$user->birthdate) {
                    return false; // Skip users without a birthdate
                }

                $birthdate = Carbon::parse($user->birthdate);
                $thisYearBirthday = Carbon::create($today->year, $birthdate->month, $birthdate->day);

                return $thisYearBirthday->gte($today); // ✅ Only upcoming birthdays
            })
            ->sortBy(function ($user) use ($today) {
                $birthdate = Carbon::parse($user->birthdate);
                return Carbon::create($today->year, $birthdate->month, $birthdate->day)->timestamp;
            })
            ->map(function ($user) {
                return [
                    '_id' => $user->_id,
                    'name' => $user->name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'profile_photo' => $user->profile_photo,
                    'birthdate' => $user->birthdate,
                ];
            })
            ->values() // Reset array indexes
            ->toArray();



        return response()->json([
            'total_users' => $totalUsers,
            'total_clients' => $totalClients,
            'total_projects' => $totalProjects,
            'notices' => $notices,
            'upcoming_holidays' => $upcomingHolidays,
            'upcoming_leaves' => $upcomingLeaves,
            'upcoming_birthdays' => $upcomingBirthdays,
        ]);
    }
}
