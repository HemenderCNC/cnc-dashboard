<?php

namespace App\Http\Controllers;

use App\Models\Timesheet;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Carbon\CarbonInterval;

class ChartController extends Controller
{
    public function getEmployeePerformance(Request $request)
    {
        $filter = $request->query('filter', 'today'); // default to today

        // Determine date range based on filter
        $startDate = Carbon::today();
        $endDate = Carbon::tomorrow();

        switch ($filter) {
            case 'last_week':
                $startDate = Carbon::now()->startOfWeek();
                $endDate = Carbon::now()->endOfWeek();
                break;
            case 'month':
                $startDate = Carbon::now()->startOfMonth();
                $endDate = Carbon::now()->endOfMonth();
                break;
            case 'quarterly':
                $startDate = Carbon::now()->firstOfQuarter();
                $endDate = Carbon::now()->lastOfQuarter();
                break;
            case 'year':
                $startDate = Carbon::now()->startOfYear();
                $endDate = Carbon::now()->endOfYear();
                break;
            case 'today':
            default:
                $startDate = Carbon::today();
                $endDate = Carbon::tomorrow();
                break;
        }

        // Fetch all relevant timesheets
        $timesheets = Timesheet::where('created_at', '>=', $startDate)
            ->where('created_at', '<', $endDate)
            ->get();

        // Group timesheets by employee
        $report = [];

        foreach ($timesheets as $sheet) {
            $employeeId = $sheet->employee_id;

            if (!isset($report[$employeeId])) {
                // Optionally load employee details (adjust field names)
                $employee = User::find($employeeId);

                $report[$employeeId] = [
                    'employee_id' => $employeeId,
                    'employee_detail' => $employee ? [
                        'id' => $employee->id,
                        'name' => $employee->name,
                        'email' => $employee->email,
                        'profile_photo' => $employee->profile_photo,
                        // Add more fields as needed
                    ] : null,
                    'total_minutes' => 0,
                ];
            }

            foreach ($sheet->dates as $dateEntry) {
                $logDate = Carbon::parse($dateEntry['date']);
                if ($logDate->between($startDate, $endDate)) {
                    foreach ($dateEntry['time_log'] as $log) {
                        if (!empty($log['start_time']) && !empty($log['end_time'])) {
                            $start = Carbon::createFromFormat('H:i', $log['start_time']);
                            $end = Carbon::createFromFormat('H:i', $log['end_time']);
                            $minutes = $end->diffInMinutes($start);
                            $report[$employeeId]['total_minutes'] += $minutes;
                        }
                    }
                }
            }
        }

        // Format final output
        $final = array_values(array_map(function ($data) {
            $interval = CarbonInterval::minutes($data['total_minutes'])->cascade();
            $data['total_hours'] = sprintf('%02d.%02d', $interval->hours + ($interval->days * 24), $interval->minutes);
            $data['total_hours'] = round($data['total_hours'], 2);
            unset($data['total_minutes']);
            return $data;
        }, $report));

        return response()->json($final);
    }
}
