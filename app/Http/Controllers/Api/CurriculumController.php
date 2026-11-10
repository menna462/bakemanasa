<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GradeLevel;
use App\Models\Lesson;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurriculumController extends Controller
{
    /**
     * Get entire curriculum tree (grades -> units -> lessons) matching frontend interfaces.
     */
    public function index(): JsonResponse
    {
        $grades = GradeLevel::with([
            'units' => function ($q) {
                $q->orderBy('unit_number')->with([
                    'lessons' => function ($lq) {
                        $lq->orderBy('order_number');
                    }
                ]);
            }
        ])->orderBy('order_number')->get();

        $formattedGrades = $grades->map(function ($grade) {
            $allLessons = $grade->units->flatMap->lessons;
            $pdfCount = $allLessons->where('has_pdf', true)->count();

            return [
                'id' => (string) $grade->id,
                'title' => $grade->title,
                'subtitle' => $grade->subtitle ?? '',
                'unitsCount' => $grade->units->count(),
                'lessonsCount' => $allLessons->count(),
                'pdfCount' => $pdfCount,
                'isActive' => (bool) $grade->is_active,
                'statusText' => $grade->status_text ?? 'نشط حالياً',
                'statusType' => $grade->status_type ?? 'active',
                'units' => $grade->units->map(function ($unit, $index) {
                    $unitPdfCount = $unit->lessons->where('has_pdf', true)->count();
                    return [
                        'id' => (string) $unit->id,
                        'unitNumber' => (int) $unit->unit_number,
                        'title' => $unit->title,
                        'lessonsCount' => $unit->lessons->count(),
                        'pdfCount' => $unitPdfCount,
                        'isExpanded' => $index === 0,
                        'lessons' => $unit->lessons->map(function ($lesson) {
                            return [
                                'id' => (string) $lesson->id,
                                'orderNumber' => (int) $lesson->order_number,
                                'title' => $lesson->title,
                                'duration' => $lesson->duration ?? '00:00:00',
                                'size' => $lesson->size ?? '',
                                'viewsCount' => (string) $lesson->views_count,
                                'hasPdf' => (bool) $lesson->has_pdf,
                                'pdfTitle' => $lesson->pdf_title,
                                'pdfUrl' => $lesson->pdf_url,
                                'hasQuiz' => (bool) $lesson->has_quiz,
                                'quizTitle' => $lesson->quiz_title,
                                'status' => $lesson->status,
                                'videoSource' => $lesson->video_source,
                                'videoUrl' => $lesson->video_url,
                                'videoProvider' => $lesson->video_provider,
                            ];
                        }),
                    ];
                }),
            ];
        });

        $totalGrades = $grades->count();
        $totalUnits = Unit::count();
        $totalLessons = Lesson::count();
        $totalPdfs = Lesson::where('has_pdf', true)->count();

        return response()->json([
            'success' => true,
            'stats' => [
                'gradesCount' => $totalGrades,
                'unitsCount' => $totalUnits,
                'lessonsCount' => $totalLessons,
                'pdfCount' => $totalPdfs,
            ],
            'data' => $formattedGrades,
        ]);
    }

    /**
     * Store new Grade Level.
     */
    public function storeGrade(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'order_number' => 'nullable|integer',
            'is_active' => 'boolean',
        ], [
            'title.required' => 'اسم المرحلة الدراسية مطلوب.',
        ]);

        $grade = GradeLevel::create([
            'title' => $validated['title'],
            'subtitle' => $validated['subtitle'] ?? '',
            'order_number' => $validated['order_number'] ?? (GradeLevel::max('order_number') + 1),
            'is_active' => $validated['is_active'] ?? true,
            'status_text' => 'نشط حالياً',
            'status_type' => 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء المرحلة الدراسية بنجاح.',
            'data' => $grade,
        ], 201);
    }

    /**
     * Update Grade Level.
     */
    public function updateGrade(Request $request, $id): JsonResponse
    {
        $grade = GradeLevel::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'order_number' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        $grade->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث بيانات المرحلة الدراسية بنجاح.',
            'data' => $grade,
        ]);
    }

    /**
     * Delete Grade Level.
     */
    public function destroyGrade($id): JsonResponse
    {
        $grade = GradeLevel::findOrFail($id);
        $grade->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف المرحلة الدراسية وكافة الوحدات التابعة لها بنجاح.',
        ]);
    }

    /**
     * Store new Unit.
     */
    public function storeUnit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'grade_level_id' => 'required|exists:grade_levels,id',
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'unit_number' => 'nullable|integer',
        ], [
            'title.required' => 'عنوان الوحدة مطلوب.',
            'grade_level_id.required' => 'المرحلة الدراسية مطلوبة.',
        ]);

        $unitNumber = $validated['unit_number'] ?? 
            (Unit::where('grade_level_id', $validated['grade_level_id'])->max('unit_number') + 1);

        $unit = Unit::create([
            'grade_level_id' => $validated['grade_level_id'],
            'unit_number' => $unitNumber,
            'title' => $validated['title'],
            'subtitle' => $validated['subtitle'] ?? '',
            'is_published' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة الوحدة الدراسية بنجاح.',
            'data' => $unit,
        ], 201);
    }

    /**
     * Update Unit.
     */
    public function updateUnit(Request $request, $id): JsonResponse
    {
        $unit = Unit::findOrFail($id);
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'unit_number' => 'nullable|integer',
            'is_published' => 'boolean',
        ]);

        $unit->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'تم تعديل الوحدة الدراسية بنجاح.',
            'data' => $unit,
        ]);
    }

    /**
     * Delete Unit.
     */
    public function destroyUnit($id): JsonResponse
    {
        $unit = Unit::findOrFail($id);
        $unit->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الوحدة الدراسية ومحتوياتها بنجاح.',
        ]);
    }

    /**
     * Store new Lesson.
     */
    public function storeLesson(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'unit_id' => 'required|exists:units,id',
            'title' => 'required|string|max:255',
            'order_number' => 'nullable|integer',
            'duration' => 'nullable|string',
            'size' => 'nullable|string',
            'video_url' => 'nullable|string',
            'video_provider' => 'nullable|in:youtube,bunny,cloudflare,vimeo,custom',
            'has_pdf' => 'boolean',
            'pdf_title' => 'nullable|string',
            'pdf_url' => 'nullable|string',
            'has_quiz' => 'boolean',
            'quiz_title' => 'nullable|string',
            'status' => 'nullable|in:published,draft',
        ], [
            'title.required' => 'عنوان الدرس أو المحاضرة مطلوب.',
            'unit_id.required' => 'الوحدة التابع لها الدرس مطلوبة.',
        ]);

        $unit = Unit::findOrFail($validated['unit_id']);
        $orderNumber = $validated['order_number'] ?? 
            (Lesson::where('unit_id', $unit->id)->max('order_number') + 1);

        $lesson = Lesson::create([
            'unit_id' => $unit->id,
            'grade_level_id' => $unit->grade_level_id,
            'order_number' => $orderNumber,
            'title' => $validated['title'],
            'duration' => $validated['duration'] ?? '00:00:00',
            'size' => $validated['size'] ?? '',
            'video_url' => $validated['video_url'] ?? '',
            'video_provider' => $validated['video_provider'] ?? 'bunny',
            'has_pdf' => $validated['has_pdf'] ?? false,
            'pdf_title' => $validated['pdf_title'] ?? null,
            'pdf_url' => $validated['pdf_url'] ?? null,
            'has_quiz' => $validated['has_quiz'] ?? false,
            'quiz_title' => $validated['quiz_title'] ?? null,
            'status' => $validated['status'] ?? 'published',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم حفظ ونشر المحاضرة بنجاح.',
            'data' => $lesson,
        ], 201);
    }

    /**
     * Update Lesson.
     */
    public function updateLesson(Request $request, $id): JsonResponse
    {
        $lesson = Lesson::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'order_number' => 'nullable|integer',
            'duration' => 'nullable|string',
            'size' => 'nullable|string',
            'video_url' => 'nullable|string',
            'video_provider' => 'nullable|in:youtube,bunny,cloudflare,vimeo,custom',
            'has_pdf' => 'boolean',
            'pdf_title' => 'nullable|string',
            'pdf_url' => 'nullable|string',
            'has_quiz' => 'boolean',
            'quiz_title' => 'nullable|string',
            'status' => 'nullable|in:published,draft',
        ]);

        $lesson->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'تم تعديل بيانات المحاضرة بنجاح.',
            'data' => $lesson,
        ]);
    }

    /**
     * Delete Lesson.
     */
    public function destroyLesson($id): JsonResponse
    {
        $lesson = Lesson::findOrFail($id);
        $lesson->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف المحاضرة بنجاح.',
        ]);
    }
}
