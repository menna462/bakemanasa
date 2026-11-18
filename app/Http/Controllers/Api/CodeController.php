<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivationCode;
use App\Models\Lesson;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CodeController extends Controller
{
    /**
     * Get list of codes with filtering, search, and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ActivationCode::query()->with([
            'lesson:id,title,duration',
            'unit:id,title',
            'gradeLevel:id,title',
        ]);

        // Search by code, group name, or student
        if ($search = $request->query('search')) {
            $search = trim($search);
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('group_name', 'like', "%{$search}%")
                  ->orWhere('used_by', 'like', "%{$search}%")
                  ->orWhere('student_phone', 'like', "%{$search}%");
            });
        }

        // Filter by status ('all', 'new', 'used', 'expired')
        if ($status = $request->query('status')) {
            if ($status !== 'all') {
                if ($status === 'expired') {
                    $query->where(function ($q) {
                        $q->where('status', 'expired')
                          ->orWhere(function ($sub) {
                              $sub->whereNotNull('expires_at')
                                  ->where('expires_at', '<=', Carbon::now());
                          });
                    });
                } elseif ($status === 'used') {
                    $query->where('status', 'used');
                } elseif ($status === 'new') {
                    $query->where('status', 'new');
                }
            }
        }

        // Filter by group name
        if ($group = $request->query('group')) {
            $query->where('group_name', $group);
        }

        // Filter by lesson or unit or grade
        if ($lessonId = $request->query('lesson_id')) {
            $query->where('lesson_id', $lessonId);
        }
        if ($unitId = $request->query('unit_id')) {
            $query->where('unit_id', $unitId);
        }
        if ($gradeId = $request->query('grade_level_id')) {
            $query->where('grade_level_id', $gradeId);
        }

        $perPage = (int) $request->query('per_page', 10);
        $paginated = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $items = collect($paginated->items())->map(function ($code) {
            return $this->formatCodeItem($code);
        });

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * Get dashboard summary statistics for activation codes.
     */
    public function stats(): JsonResponse
    {
        $now = Carbon::now();

        $totalCodes = ActivationCode::count();
        $activeAvailable = ActivationCode::where('status', 'new')->count();
        
        $usedActive = ActivationCode::where('status', 'used')
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })->count();

        $expired = ActivationCode::where('status', 'expired')
            ->orWhere(function ($q) use ($now) {
                $q->whereNotNull('expires_at')->where('expires_at', '<=', $now);
            })->count();

        return response()->json([
            'success' => true,
            'stats' => [
                'totalCodes' => $totalCodes,
                'activeAvailable' => $activeAvailable,
                'usedActive' => $usedActive,
                'expired' => $expired,
            ],
        ]);
    }

    /**
     * Generate bulk activation codes.
     * Supports both unit-level and lesson-level codes.
     *   - unitId   → code covers all lessons in that unit
     *   - lessonId → code covers a single lesson only
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'count' => 'required|integer|min:1|max:1000',
            'groupName' => 'required|string|max:255',
            'validityDays' => 'nullable|integer|min:1|max:365',
            'lessonId' => 'nullable|exists:lessons,id',
            'unitId' => 'nullable|exists:units,id',
            'gradeLevelId' => 'nullable|exists:grade_levels,id',
        ], [
            'groupName.required' => 'يرجى إدخال اسم المجموعة أو ملاحظات السنتر.',
            'count.required' => 'يرجى تحديد عدد الأكواد المطلوبة.',
            'count.max' => 'أقصى عدد للتوليد في الدفعة الواحدة هو 1000 كود.',
        ]);

        $count = (int) $validated['count'];
        $groupName = trim($validated['groupName']);
        $validityDays = (int) ($validated['validityDays'] ?? 30);
        $lessonId = $validated['lessonId'] ?? null;
        $unitId = $validated['unitId'] ?? null;
        $gradeLevelId = $validated['gradeLevelId'] ?? null;

        // If unit is specified, don't set lesson_id — the code covers the whole unit
        if ($unitId) {
            $lessonId = null; // unit-level code
            $matchedUnit = Unit::find($unitId);
            if ($matchedUnit && !$gradeLevelId) {
                $gradeLevelId = $matchedUnit->grade_level_id;
            }
        }

        // Auto-link grade level if lesson is specified
        if ($lessonId && !$gradeLevelId) {
            $matchedLesson = Lesson::find($lessonId);
            if ($matchedLesson) {
                $gradeLevelId = $matchedLesson->grade_level_id;
            }
        }

        $adminId = $request->user() ? $request->user()->id : null;

        $createdCodes = [];

        for ($i = 0; $i < $count; $i++) {
            $uniqueCode = $this->generateUniqueCode();

            $record = ActivationCode::create([
                'code' => $uniqueCode,
                'group_name' => $groupName,
                'grade_level_id' => $gradeLevelId,
                'lesson_id' => $lessonId,
                'unit_id' => $unitId,
                'created_by_admin_id' => $adminId,
                'validity_days' => $validityDays,
                'status' => 'new',
            ]);

            // Eager load relations for response formatting
            $record->load(['lesson:id,title,duration', 'unit:id,title']);

            $createdCodes[] = $this->formatCodeItem($record);
        }

        $scopeLabel = $unitId ? 'وحدة كاملة' : ($lessonId ? 'درس محدد' : 'عام');

        return response()->json([
            'success' => true,
            'message' => "تم توليد {$count} كود تفعيل جديد بنجاح للدفعة [{$groupName}] — نطاق: {$scopeLabel}.",
            'count' => $count,
            'data' => $createdCodes,
        ], 201);
    }

    /**
     * Show single code details.
     */
    public function show($id): JsonResponse
    {
        $code = ActivationCode::with(['lesson', 'unit.lessons', 'gradeLevel', 'studentProgress', 'createdByAdmin:id,full_name'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => array_merge($this->formatCodeItem($code), [
                'lesson' => $code->lesson,
                'unit' => $code->unit,
                'gradeLevel' => $code->gradeLevel,
                'createdBy' => $code->createdByAdmin ? $code->createdByAdmin->full_name : null,
                'studentPhone' => $code->student_phone,
                'expiresAt' => $code->expires_at ? $code->expires_at->format('Y/m/d H:i') : null,
                'progress' => $code->studentProgress,
            ]),
        ]);
    }

    /**
     * Delete a single code.
     */
    public function destroy($id): JsonResponse
    {
        $code = ActivationCode::findOrFail($id);
        $code->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف كود التفعيل بنجاح.',
        ]);
    }

    /**
     * Bulk delete codes.
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:activation_codes,id',
        ], [
            'ids.required' => 'يرجى تحديد الأكواد المطلوب حذفها.',
        ]);

        $deletedCount = ActivationCode::whereIn('id', $validated['ids'])->delete();

        return response()->json([
            'success' => true,
            'message' => "تم حذف {$deletedCount} كود بنجاح.",
            'deletedCount' => $deletedCount,
        ]);
    }

    /**
     * Bulk status update (e.g. mark as expired or suspend).
     */
    public function bulkStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:activation_codes,id',
            'status' => 'required|in:new,used,expired',
        ]);

        $count = ActivationCode::whereIn('id', $validated['ids'])->update([
            'status' => $validated['status'],
        ]);

        return response()->json([
            'success' => true,
            'message' => "تم تعديل حالة {$count} كود بنجاح إلى [{$validated['status']}].",
        ]);
    }

    /**
     * Export / Print list of codes.
     */
    public function export(Request $request): JsonResponse
    {
        $query = ActivationCode::query()->with(['lesson:id,title,duration', 'unit:id,title']);

        if ($ids = $request->input('ids')) {
            $query->whereIn('id', (array) $ids);
        } elseif ($group = $request->input('group')) {
            $query->where('group_name', $group);
        }

        $codes = $query->get()->map(function ($c) {
            return $this->formatCodeItem($c);
        });

        return response()->json([
            'success' => true,
            'data' => $codes,
        ]);
    }

    /**
     * Generate a unique 8-digit numeric activation code.
     */
    private function generateUniqueCode(): string
    {
        do {
            $code = (string) random_int(10000000, 99999999);
        } while (ActivationCode::where('code', $code)->exists());

        return $code;
    }

    /**
     * Format ActivationCode model to match frontend CodeItem interface.
     */
    private function formatCodeItem(ActivationCode $code): array
    {
        // Determine the content label: unit name, lesson name, or general
        $contentLabel = 'كود عام (شامل المنهج)';
        $contentScope = 'general'; // 'unit' | 'lesson' | 'general'

        if ($code->unit_id && $code->unit) {
            $contentLabel = 'وحدة: ' . $code->unit->title;
            $contentScope = 'unit';
        } elseif ($code->lesson_id && $code->lesson) {
            $contentLabel = $code->lesson->title;
            $contentScope = 'lesson';
        }

        return [
            'id'            => (string) $code->id,
            'code'          => $code->code,
            'groupName'     => $code->group_name,
            'createdAt'     => $code->created_at ? $code->created_at->format('Y/m/d') : '',
            'validityDays'  => (int) $code->validity_days,
            'status'        => $code->status,
            'usedBy'        => $code->used_by,
            'activatedAt'   => $code->activated_at ? $code->activated_at->format('Y/m/d') : null,
            'expiresAt'     => $code->expires_at ? $code->expires_at->format('Y/m/d') : null,
            'lessonTitle'   => $contentLabel,
            'lessonDuration'=> $code->lesson ? $code->lesson->duration : '—',
            'contentScope'  => $contentScope,
            'unitId'        => $code->unit_id ? (string) $code->unit_id : null,
            'lessonId'      => $code->lesson_id ? (string) $code->lesson_id : null,
        ];
    }
}
