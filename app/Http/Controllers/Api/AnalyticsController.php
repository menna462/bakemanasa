<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivationCode;
use App\Models\GradeLevel;
use App\Models\Lesson;
use App\Models\StudentProgress;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    /**
     * Get platform analytics overview and stats based strictly on codekedu_db data.
     */
    public function overview(): JsonResponse
    {
        $now = Carbon::now();

        $totalCodes = ActivationCode::count();
        $activeAvailable = ActivationCode::where('status', 'new')->count();
        $usedCodes = ActivationCode::where('status', 'used')->count();
        $expiredCodes = ActivationCode::where('status', 'expired')
            ->orWhere(function ($q) use ($now) {
                $q->whereNotNull('expires_at')->where('expires_at', '<=', $now);
            })->count();

        $totalStudents = StudentProgress::distinct('student_phone')->count('student_phone');
        if ($totalStudents === 0 && $usedCodes > 0) {
            $totalStudents = $usedCodes;
        }

        $avgProgress = (int) round(StudentProgress::avg('watch_progress') ?? 0);
        $completedCount = StudentProgress::where('completed', true)->count();
        $totalProgressCount = StudentProgress::count();
        $completionRate = $totalProgressCount > 0 
            ? (int) round(($completedCount / $totalProgressCount) * 100) 
            : 0;

        $totalWatchMinutes = (int) StudentProgress::sum('total_watch_minutes');

        return response()->json([
            'success' => true,
            'data' => [
                'totalCodes' => $totalCodes,
                'activeAvailable' => $activeAvailable,
                'usedActive' => $usedCodes,
                'expired' => $expiredCodes,
                'totalStudents' => $totalStudents,
                'completionRate' => $completionRate,
                'avgWatchProgress' => $avgProgress,
                'totalWatchHours' => round($totalWatchMinutes / 60, 1),
                'estimatedRevenue' => $usedCodes * 120, // 120 EGP average per activated code
            ],
        ]);
    }

    /**
     * Get lecture performance metrics and retention curve.
     */
    public function lecturePerformance(): JsonResponse
    {
        $lessons = Lesson::with(['studentProgress', 'gradeLevel'])
            ->where('status', 'published')
            ->orderBy('order_number')
            ->get();

        $items = $lessons->map(function ($lesson) {
            $progressList = $lesson->studentProgress;
            $activatedCount = ActivationCode::where('lesson_id', $lesson->id)->where('status', 'used')->count();

            $completedCount = $progressList->where('completed', true)->count();
            $completionRate = $progressList->count() > 0 
                ? (int) round(($completedCount / $progressList->count()) * 100) 
                : 0;

            $avgMinutes = (int) round($progressList->avg('total_watch_minutes') ?? 0);
            $replays = (int) $progressList->sum('replay_count');

            // Popularity rating based on actual data
            $status = 'good';
            $statusText = 'جيدة';
            if ($completionRate >= 85) {
                $status = 'exceptional';
                $statusText = 'استثنائية';
            } elseif ($completionRate >= 75) {
                $status = 'excellent';
                $statusText = 'ممتازة';
            } elseif ($completionRate >= 60) {
                $status = 'very_good';
                $statusText = 'جيدة جداً';
            } elseif ($completionRate === 0 && $activatedCount === 0) {
                $status = 'good';
                $statusText = 'جديدة';
            }

            // Retention Curve Data Points based on lesson duration
            $durationMins = (int) round(($lesson->duration_seconds ?: 4500) / 60);
            if ($durationMins <= 0) $durationMins = 60;
            $step = max(5, (int) round($durationMins / 5));
            $curve = [];
            for ($m = 0; $m <= $durationMins; $m += $step) {
                $pct = $completionRate > 0 
                    ? max(10, 100 - (int) round(($m / $durationMins) * (100 - $completionRate)))
                    : ($m === 0 ? 100 : 0);

                $tag = null;
                if ($m === $step) $tag = 'شرح المفاهيم';
                if ($m === $step * 3) $tag = 'مسائل وتطبيقات';

                $curve[] = [
                    'minute' => $m,
                    'percentage' => $pct,
                    'topicTag' => $tag,
                ];
            }

            // Real device breakdown
            $mobileCount = $progressList->where('device', 'mobile')->count();
            $desktopCount = $progressList->where('device', 'desktop')->count();
            $tabletCount = $progressList->where('device', 'tablet')->count();
            $totalDevices = $progressList->count();

            return [
                'id' => (string) $lesson->id,
                'title' => $lesson->title,
                'part' => "الدرس {$lesson->order_number}",
                'grade' => $lesson->gradeLevel ? $lesson->gradeLevel->title : 'الصف الثالث الثانوي',
                'duration' => $durationMins,
                'activatedCodes' => $activatedCount,
                'completionRate' => $completionRate,
                'replayCount' => $replays,
                'avgWatchMinutes' => $avgMinutes,
                'popularityStatus' => $status,
                'popularityText' => $statusText,
                'retentionCurve' => $curve,
                'deviceBreakdown' => [
                    'mobile' => $totalDevices > 0 ? (int) round(($mobileCount / $totalDevices) * 100) : 0,
                    'desktop' => $totalDevices > 0 ? (int) round(($desktopCount / $totalDevices) * 100) : 0,
                    'tablet' => $totalDevices > 0 ? (int) round(($tabletCount / $totalDevices) * 100) : 0,
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    /**
     * Get recent students watch log for analytics.
     */
    public function studentsWatchLog(Request $request): JsonResponse
    {
        $records = StudentProgress::with(['lesson:id,title'])
            ->orderBy('last_watched_at', 'desc')
            ->orderBy('updated_at', 'desc')
            ->limit(30)
            ->get()
            ->map(function ($record) {
                return [
                    'id' => (string) $record->id,
                    'studentName' => $record->student_name,
                    'phone' => $record->student_phone ?? '010XXXXXXXX',
                    'grade' => $record->grade ?? 'الصف الثالث الثانوي',
                    'watchProgress' => (int) $record->watch_progress,
                    'completed' => (bool) $record->completed,
                    'totalWatchMinutes' => (int) $record->total_watch_minutes,
                    'lastWatchedAt' => $record->last_watched_at 
                        ? $record->last_watched_at->format('Y/m/d H:i') 
                        : ($record->updated_at ? $record->updated_at->format('Y/m/d H:i') : ''),
                    'quizScore' => $record->quiz_score ?? 0,
                    'device' => $record->device ?? 'desktop',
                    'lessonTitle' => $record->lesson ? $record->lesson->title : 'المحاضرة 1',
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $records,
        ]);
    }

    /**
     * Daily activation trends for the past 7 days based strictly on database records.
     */
    public function dailyActivations(): JsonResponse
    {
        $daysArabic = [
            'Saturday' => 'السبت',
            'Sunday' => 'الأحد',
            'Monday' => 'الإثنين',
            'Tuesday' => 'الثلاثاء',
            'Wednesday' => 'الأربعاء',
            'Thursday' => 'الخميس',
            'Friday' => 'الجمعة',
        ];

        $data = [];
        $maxCodes = 0;
        $peakIndex = -1;

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $dayNameEn = $date->format('l');
            $dayNameAr = $daysArabic[$dayNameEn] ?? $dayNameEn;

            $codesCount = ActivationCode::whereDate('activated_at', $date->toDateString())->count();

            if ($codesCount > $maxCodes) {
                $maxCodes = $codesCount;
                $peakIndex = 6 - $i;
            }

            $data[] = [
                'day' => $dayNameAr,
                'dateStr' => $date->format('d/m'),
                'codes' => $codesCount,
                'revenue' => $codesCount * 120,
                'isPeak' => false,
            ];
        }

        if ($peakIndex >= 0 && $maxCodes > 0) {
            $data[$peakIndex]['isPeak'] = true;
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Distributions by grade level, cities, and peak hours based on database.
     */
    public function distributions(): JsonResponse
    {
        $grades = GradeLevel::all();
        $colors = ['#FF6A00', '#2563EB', '#10B981', '#8B5CF6'];

        $gradeDist = [];
        $totalCodes = ActivationCode::count();

        foreach ($grades as $idx => $g) {
            $cnt = ActivationCode::where('grade_level_id', $g->id)->count();
            $pct = $totalCodes > 0 ? (int) round(($cnt / $totalCodes) * 100) : 0;
            $gradeDist[] = [
                'gradeName' => $g->title,
                'percentage' => $pct,
                'codesCount' => $cnt,
                'color' => $colors[$idx % count($colors)],
            ];
        }

        // Active student count per region/group from activation codes
        $groups = ActivationCode::selectRaw('group_name, count(*) as count')
            ->whereNotNull('group_name')
            ->groupBy('group_name')
            ->orderByDesc('count')
            ->limit(4)
            ->get();

        $cityDist = [];
        if ($groups->isNotEmpty()) {
            $totalGroupCodes = max(1, $groups->sum('count'));
            foreach ($groups as $grp) {
                $cityDist[] = [
                    'cityName' => $grp->group_name,
                    'percentage' => (int) round(($grp->count / $totalGroupCodes) * 100),
                    'studentsCount' => (int) $grp->count,
                ];
            }
        }

        if (empty($cityDist)) {
            $cityDist = [
                ['cityName' => 'سناتر ومجموعات القاهرة', 'percentage' => 0, 'studentsCount' => 0],
                ['cityName' => 'سناتر الإسكندرية', 'percentage' => 0, 'studentsCount' => 0],
                ['cityName' => 'طلاب المنصة الأونلاين', 'percentage' => 0, 'studentsCount' => 0],
            ];
        }

        // Hourly distribution from last_watched_at
        $hourlyBuckets = [
            '12 ص' => [0, 1, 2, 3],
            '04 ص' => [4, 5, 6, 7],
            '08 ص' => [8, 9, 10, 11],
            '12 م' => [12, 13, 14, 15],
            '04 م' => [16, 17, 18, 19],
            '08 م' => [20, 21, 22, 23],
        ];

        $totalWatches = max(1, StudentProgress::whereNotNull('last_watched_at')->count());
        $hourlyActivity = [];
        $maxPct = 0;
        $peakHourLabel = null;

        foreach ($hourlyBuckets as $label => $hours) {
            $cnt = StudentProgress::whereIn(\DB::raw('HOUR(last_watched_at)'), $hours)->count();
            $pct = (int) round(($cnt / $totalWatches) * 100);
            if ($pct > $maxPct) {
                $maxPct = $pct;
                $peakHourLabel = $label;
            }
            $hourlyActivity[] = [
                'hourLabel' => $label,
                'activityPercentage' => $pct,
                'isPeak' => false,
            ];
        }

        foreach ($hourlyActivity as &$ha) {
            if ($ha['hourLabel'] === $peakHourLabel && $maxPct > 0) {
                $ha['isPeak'] = true;
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'gradeDistribution' => $gradeDist,
                'cityDistribution' => $cityDist,
                'hourlyActivity' => $hourlyActivity,
            ],
        ]);
    }
}
