<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivationCode;
use App\Models\Lesson;
use App\Models\Note;
use App\Models\StudentProgress;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    /**
     * Verify activation code entered by the student on the platform home page.
     */
    public function verifyCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string',
            'student_name' => 'nullable|string|max:255',
            'student_phone' => 'nullable|string|max:50',
            'device' => 'nullable|in:mobile,desktop,tablet',
        ], [
            'code.required' => 'يرجى إدخال كود التفعيل للدخول.',
        ]);

        $rawCode = strtoupper(trim($validated['code']));

        $codeModel = ActivationCode::with(['lesson.unit.gradeLevel', 'gradeLevel'])
            ->where('code', $rawCode)
            ->first();

        if (!$codeModel) {
            return response()->json([
                'success' => false,
                'message' => 'كود التفعيل المدخل غير صحيح، يرجى التأكد من كتابة الكود كما هو مدون على الكارت.',
            ], 404);
        }

        $now = Carbon::now();

        // Check if expired
        if ($codeModel->status === 'expired' || ($codeModel->expires_at && $codeModel->expires_at->isPast())) {
            if ($codeModel->status !== 'expired') {
                $codeModel->update(['status' => 'expired']);
            }
            return response()->json([
                'success' => false,
                'message' => 'انتهت صلاحية كود التفعيل هذا. يرجى التواصل مع فريق الدعم الفني أو السنتر لتجديد الكود.',
            ], 403);
        }

        // If code is new, activate it
        if ($codeModel->status === 'new') {
            $studentName = $validated['student_name'] ?? 'طالب منصة';
            $studentPhone = $validated['student_phone'] ?? null;

            $codeModel->update([
                'status' => 'used',
                'used_by' => $studentName,
                'student_phone' => $studentPhone,
                'activated_at' => $now,
                'expires_at' => $now->copy()->addDays($codeModel->validity_days ?: 30),
            ]);

            // Create initial progress record if lesson exists
            $lessonId = $codeModel->lesson_id ?? Lesson::where('status', 'published')->value('id');
            if ($lessonId) {
                StudentProgress::firstOrCreate([
                    'activation_code_id' => $codeModel->id,
                    'lesson_id' => $lessonId,
                ], [
                    'student_name' => $studentName,
                    'student_phone' => $studentPhone,
                    'grade' => $codeModel->gradeLevel ? $codeModel->gradeLevel->title : 'الصف الثالث الثانوي',
                    'watch_progress' => 0,
                    'current_seconds' => 0,
                    'device' => $validated['device'] ?? 'desktop',
                    'last_watched_at' => $now,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'تم التحقق من كود التفعيل بنجاح! جاري تحويلك إلى المحاضرة...',
            'redirectUrl' => "/lecture/{$codeModel->code}",
            'code' => $codeModel->code,
            'status' => $codeModel->status,
            'expiresAt' => $codeModel->expires_at ? $codeModel->expires_at->format('Y/m/d') : null,
        ]);
    }

    /**
     * Get complete lecture payload for the Lecture Viewer page.
     */
    public function getLecture(Request $request, $code): JsonResponse
    {
        $rawCode = strtoupper(trim($code));

        $codeModel = ActivationCode::with(['lesson.unit.gradeLevel', 'gradeLevel'])
            ->where('code', $rawCode)
            ->first();

        // Fallback to first available lesson if code is general demo
        $targetLesson = null;
        if ($codeModel && $codeModel->lesson) {
            $targetLesson = $codeModel->lesson;
        } else {
            $targetLesson = Lesson::with('unit.gradeLevel')->where('status', 'published')->first();
        }

        if (!$targetLesson) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على المحاضرة المطلوبة.',
            ], 404);
        }

        // Get student progress for this code
        $progress = null;
        if ($codeModel) {
            $progress = StudentProgress::where('activation_code_id', $codeModel->id)
                ->where('lesson_id', $targetLesson->id)
                ->first();
        }

        // Fetch curriculum units hierarchy for accordion
        $gradeLevelId = $targetLesson->grade_level_id;
        $units = Unit::with(['lessons' => function ($q) {
            $q->orderBy('order_number');
        }])
        ->where('grade_level_id', $gradeLevelId)
        ->orderBy('unit_number')
        ->get()
        ->map(function ($unit) use ($targetLesson) {
            return [
                'id' => (string) $unit->id,
                'number' => (int) $unit->unit_number,
                'title' => $unit->title,
                'subtitle' => $unit->subtitle ?? "{$unit->lessons->count()} محاضرات",
                'lessons' => $unit->lessons->map(function ($l) use ($targetLesson) {
                    $isCurrent = $l->id === $targetLesson->id;
                    $status = $isCurrent ? 'playing' : ($l->order_number < $targetLesson->order_number ? 'completed' : 'locked');
                    return [
                        'id' => (string) $l->id,
                        'number' => (int) $l->order_number,
                        'title' => $l->title,
                        'duration' => $l->duration ?? '00:00:00',
                        'status' => $status,
                        'completedPercent' => $isCurrent ? 35 : ($status === 'completed' ? 100 : 0),
                        'badge' => $isCurrent ? 'الآن' : ($status === 'completed' ? 'تم' : null),
                    ];
                }),
                'quiz' => [
                    'title' => 'امتحان تقييمي إلكتروني على الوحدة',
                    'questionsCount' => 30,
                    'duration' => '40 دقيقة',
                ],
            ];
        });

        // Fetch saved notes for this lesson and code
        $notes = Note::where('lesson_id', $targetLesson->id)
            ->where(function ($q) use ($codeModel) {
                if ($codeModel) {
                    $q->where('activation_code_id', $codeModel->id)
                      ->orWhereNull('activation_code_id');
                }
            })
            ->orderBy('time_seconds', 'asc')
            ->get()
            ->map(function ($note) {
                return [
                    'id' => "n-{$note->id}",
                    'time' => $note->time_formatted,
                    'text' => $note->content,
                ];
            });

        return response()->json([
            'success' => true,
            'code' => $codeModel ? $codeModel->code : $rawCode,
            'lesson' => [
                'id' => (string) $targetLesson->id,
                'title' => $targetLesson->title,
                'description' => $targetLesson->description,
                'duration' => $targetLesson->duration,
                'durationSeconds' => $targetLesson->duration_seconds ?: 0,
                'videoSource' => $targetLesson->video_source,
                'videoUrl' => $targetLesson->video_url,
                'hasPdf' => (bool) ($targetLesson->has_pdf && !empty($targetLesson->pdf_url)),
                'pdfTitle' => (!empty($targetLesson->pdf_url)) ? $targetLesson->pdf_title : null,
                'pdfUrl' => $targetLesson->pdf_url,
                'hasQuiz' => (bool) $targetLesson->has_quiz,
                'quizTitle' => $targetLesson->quiz_title,
                'quizQuestionsCount' => $targetLesson->quiz_questions_count,
                'quizDuration' => $targetLesson->quiz_duration,
                'gradeTitle' => $targetLesson->unit && $targetLesson->unit->gradeLevel ? $targetLesson->unit->gradeLevel->title : ($codeModel && $codeModel->gradeLevel ? $codeModel->gradeLevel->title : null),
                'unitTitle' => $targetLesson->unit ? $targetLesson->unit->title : null,
            ],
            'progress' => [
                'currentSeconds' => $progress ? (int) $progress->current_seconds : 0,
                'watchProgress' => $progress ? (int) $progress->watch_progress : 0,
                'completed' => $progress ? (bool) $progress->completed : false,
            ],
            'validity' => [
                'validityDays' => $codeModel ? (int) $codeModel->validity_days : 30,
                'status' => $codeModel ? $codeModel->status : 'used',
                'activatedAt' => $codeModel && $codeModel->activated_at ? $codeModel->activated_at->format('Y/m/d') : null,
                'expiresAt' => $codeModel && $codeModel->expires_at ? $codeModel->expires_at->format('Y/m/d') : null,
            ],
            'units' => $units,
            'savedNotes' => $notes,
        ]);
    }

    /**
     * Update student watch progress.
     */
    public function updateProgress(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string',
            'lesson_id' => 'required|exists:lessons,id',
            'current_seconds' => 'required|integer|min:0',
            'watch_progress' => 'required|integer|min:0|max:100',
            'completed' => 'nullable|boolean',
            'device' => 'nullable|in:mobile,desktop,tablet',
        ]);

        $codeModel = ActivationCode::where('code', strtoupper(trim($validated['code'])))->first();

        $progress = StudentProgress::updateOrCreate([
            'activation_code_id' => $codeModel ? $codeModel->id : null,
            'lesson_id' => $validated['lesson_id'],
        ], [
            'student_name' => $codeModel ? ($codeModel->used_by ?? 'طالب') : 'طالب',
            'student_phone' => $codeModel ? $codeModel->student_phone : null,
            'current_seconds' => $validated['current_seconds'],
            'watch_progress' => $validated['watch_progress'],
            'completed' => $validated['completed'] ?? ($validated['watch_progress'] >= 90),
            'device' => $validated['device'] ?? 'desktop',
            'last_watched_at' => Carbon::now(),
            'total_watch_minutes' => (int) round($validated['current_seconds'] / 60),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم حفظ تقدم المشاهدة.',
            'progress' => $progress,
        ]);
    }

    /**
     * Add student note on current timestamp in lecture.
     */
    public function addNote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'nullable|string',
            'lesson_id' => 'required|exists:lessons,id',
            'time_formatted' => 'required|string',
            'time_seconds' => 'nullable|integer',
            'text' => 'required|string|max:1000',
        ], [
            'text.required' => 'يرجى كتابة نص الملاحظة.',
        ]);

        $codeModel = null;
        if (!empty($validated['code'])) {
            $codeModel = ActivationCode::where('code', strtoupper(trim($validated['code'])))->first();
        }

        $note = Note::create([
            'lesson_id' => $validated['lesson_id'],
            'activation_code_id' => $codeModel ? $codeModel->id : null,
            'student_name' => $codeModel ? $codeModel->used_by : 'طالب',
            'time_formatted' => $validated['time_formatted'],
            'time_seconds' => $validated['time_seconds'] ?? 0,
            'content' => trim($validated['text']),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم حفظ الملاحظة بنجاح.',
            'note' => [
                'id' => "n-{$note->id}",
                'time' => $note->time_formatted,
                'text' => $note->content,
            ],
        ], 201);
    }
}
