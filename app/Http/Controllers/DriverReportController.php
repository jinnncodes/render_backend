<?php

namespace App\Http\Controllers;

use App\Models\DriverReport;
use App\Models\RequestModel;
use Illuminate\Http\Request;

class DriverReportController extends Controller
{
    // ------------------ List all reports ------------------
    public function index()
    {
        $user = auth()->user();

        if ($user->role === 'driver') {
            // Driver sees only their reports
            $reports = DriverReport::with('request')
                ->where('driver_id', $user->id)
                ->get();
        } else {
            // Admin or coordinator sees all
            $reports = DriverReport::with(['request', 'driver'])->get();
        }

        return response()->json($reports);
    }

    // ------------------ Driver updates report text ------------------
    public function updateReport(Request $request, $id)
    {
        $report = DriverReport::find($id);
        if (!$report) {
            return response()->json(['message' => 'Report not found'], 404);
        }

        $user = auth()->user();
        if ($user->role !== 'driver' || $report->driver_id != $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Validate driver_status
        $request->validate([
            'driver_status' => 'required|string|in:accepted,done',
        ]);

        $driverStatus = $request->driver_status;

        // report_text is required only if done
        if ($driverStatus === 'done') {
            $request->validate([
                'report_text' => 'required|string',
            ]);
        }

        // Update driver report
        $report->status = $driverStatus;

        if ($driverStatus === 'done') {
            $report->report_text = $request->report_text;
            $report->driver_done_date_time = now();
        } elseif ($driverStatus === 'accepted') {
            $report->driver_accepted_date_time = now();
        }

        $report->save();

        // --------------------------
        // Update corresponding request
        // --------------------------
        if ($driverStatus === 'done' && $report->request_id) {
            $requestModel = RequestModel::find($report->request_id);
            if ($requestModel) {
                $requestModel->update([
                    'status' => 'completed',
                    'driver_status' => 'done',
                ]);
            }
        }

        return response()->json([
            'message' => 'Report updated',
            'data' => $report,
        ]);
    }

    // ------------------ Admin/Coordinator review ------------------
    public function reviewReport(Request $request, $id)
    {
        $report = DriverReport::find($id);
        if (!$report) return response()->json(['message' => 'Report not found'], 404);

        $user = auth()->user();
        if (!in_array($user->role, ['admin', 'coordinator'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'status' => 'required|in:pending,reviewed'
        ]);

        $report->update([
            'status' => $request->status
        ]);

        return response()->json(['message' => 'Report reviewed', 'data' => $report]);
    }

    // ------------------ Fetch reports per driver per date ------------------
    public function getReportsByDriver(Request $request, $driverId)
    {
        $user = auth()->user();

        // Optional date filter, default today
        $date = $request->query('date', date('Y-m-d'));

        // Drivers can only fetch their own reports
        if ($user->role === 'driver' && $user->id != $driverId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $reports = DriverReport::with('request')
            ->where('driver_reports.driver_id', $driverId)
            ->whereHas('request', function ($q) use ($date) {
                $q->whereDate('date', $date);
            })
            ->join('requests', 'driver_reports.request_id', '=', 'requests.id')
            ->orderByRaw('TIME(requests.time) ASC')
            ->select('driver_reports.*') // avoid selecting requests.* columns
            ->get();

        return response()->json([
            'driver_id' => $driverId,
            'date' => $date,
            'reports' => $reports,
        ]);
    }

    // -------------------- Fetch Driver per Date ----------------------
    public function getDriversByDate(Request $request)
{
    $date = $request->query('date', date('Y-m-d'));

    $drivers = \App\Models\User::where('role', 'driver')
        ->whereHas('driverReports.request', function ($q) use ($date) {
            $q->whereDate('date', $date);
        })
        ->withCount(['driverReports as report_count' => function ($q) use ($date) {
            $q->whereHas('request', function ($sub) use ($date) {
                $sub->whereDate('date', $date);
            });
        }])
        ->get();

    return response()->json($drivers);
}

}
