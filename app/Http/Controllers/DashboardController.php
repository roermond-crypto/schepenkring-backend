<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Yacht;
use App\Models\Task;
use App\Models\Bid;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Get summary statistics for the admin dashboard.
     */
    public function summary(Request $request)
    {
        $now = Carbon::now();
        $oneWeekAgo = Carbon::now()->subDays(7);
        $twoWeeksAgo = Carbon::now()->subDays(14);
        $oneMonthAgo = Carbon::now()->subMonth();

        // 1. Active Bids (Current vs Previous Week)
        $activeBidsCurrent = Yacht::where('status', 'For Bid')->count();
        // Since we don't track historical status changes easily, we'll approximate trend 
        // by looking at recently updated "For Bid" yachts in the last 7 days vs previous 7 days
        // Or we can just build a logical trend line for the last 7 days based on created_at / updated_at
        
        // Helper to generate a 7-day sparkline array (Count of records created/updated per day)
        $generateSparkline = function ($queryModel, $dateColumn = 'created_at', $extraConditions = null) use ($now) {
            $sparkline = [];
            for ($i = 6; $i >= 0; $i--) {
                $dateStart = clone $now;
                $dateStart->subDays($i)->startOfDay();
                $dateEnd = clone $now;
                $dateEnd->subDays($i)->endOfDay();

                $q = $queryModel::whereBetween($dateColumn, [$dateStart, $dateEnd]);
                if ($extraConditions) {
                    $extraConditions($q);
                }
                $sparkline[] = $q->count();
            }
            return $sparkline;
        };

        // Active Bids sparkline (Bids placed in the last 7 days)
        $activeBidsSparkline = $generateSparkline(Bid::class);

        // 2. Pending Tasks (Tasks not Done)
        $pendingTasksCurrent = Task::where('status', '!=', 'Done')->count();
        $pendingTasksSparkline = $generateSparkline(Task::class, 'created_at', function($q) {
            $q->where('status', '!=', 'Done');
        });

        // 3. Fleet Intake (Draft or For Sale)
        $fleetIntakeCurrent = Yacht::whereIn('status', ['Draft', 'For Sale'])->count();
        $fleetIntakeSparkline = $generateSparkline(Yacht::class, 'created_at', function($q) {
            $q->whereIn('status', ['Draft', 'For Sale']);
        });

        // 4. Completed Sales
        // Total completely sold amount
        $salesCurrent = Yacht::where('status', 'Sold')->sum('price');
        
        // Sparkline for sales count or amount over last 7 days. Let's do count of sold yachts for the chart.
        $salesSparkline = $generateSparkline(Yacht::class, 'updated_at', function($q) {
            $q->where('status', 'Sold');
        });

        // Calculate % changes (comparing last 7 days to previous 7 days)
        $calculateChange = function ($currentCount, $previousCount) {
            if ($previousCount == 0) return $currentCount > 0 ? 100 : 0;
            return round((($currentCount - $previousCount) / $previousCount) * 100);
        };

        // Active Bids Change
        $bidsLast7 = Bid::where('created_at', '>=', $oneWeekAgo)->count();
        $bidsPrev7 = Bid::whereBetween('created_at', [$twoWeeksAgo, $oneWeekAgo])->count();
        $activeBidsChange = $calculateChange($bidsLast7, $bidsPrev7);

        // Pending Tasks Change
        $tasksLast7 = Task::where('status', '!=', 'Done')->where('created_at', '>=', $oneWeekAgo)->count();
        $tasksPrev7 = Task::where('status', '!=', 'Done')->whereBetween('created_at', [$twoWeeksAgo, $oneWeekAgo])->count();
        $tasksChange = $calculateChange($tasksLast7, $tasksPrev7);

        // Fleet Intake Change
        $intakeLast7 = Yacht::whereIn('status', ['Draft', 'For Sale'])->where('created_at', '>=', $oneWeekAgo)->count();
        $intakePrev7 = Yacht::whereIn('status', ['Draft', 'For Sale'])->whereBetween('created_at', [$twoWeeksAgo, $oneWeekAgo])->count();
        $intakeChange = $calculateChange($intakeLast7, $intakePrev7);

        // Sales Change
        $salesLastMonth = Yacht::where('status', 'Sold')->where('updated_at', '>=', $oneMonthAgo)->sum('price');
        $salesPrevMonth = Yacht::where('status', 'Sold')->whereBetween('updated_at', [$oneMonthAgo->copy()->subMonth(), $oneMonthAgo])->sum('price');
        $salesChange = $salesPrevMonth == 0 ? ($salesLastMonth > 0 ? 100 : 0) : round((($salesLastMonth - $salesPrevMonth) / $salesPrevMonth) * 100);

        return response()->json([
            'activeBids' => [
                'count' => $activeBidsCurrent,
                'change' => $activeBidsChange,
                'sparkline' => $activeBidsSparkline
            ],
            'pendingTasks' => [
                'count' => $pendingTasksCurrent,
                'change' => $tasksChange,
                'sparkline' => $pendingTasksSparkline
            ],
            'fleetIntake' => [
                'count' => $fleetIntakeCurrent,
                'change' => $intakeChange,
                'sparkline' => $fleetIntakeSparkline
            ],
            'completedSales' => [
                'count' => $salesCurrent,
                'change' => $salesChange,
                'sparkline' => $salesSparkline
            ]
        ]);
    }
}
