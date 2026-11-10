<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Role;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdminAuthController extends Controller
{
    /**
     * Admin login and token issuance.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ], [
            'email.required' => 'يرجى إدخال البريد الإلكتروني.',
            'email.email' => 'صيغة البريد الإلكتروني غير صالحة.',
            'password.required' => 'يرجى إدخال كلمة المرور.',
        ]);

        $admin = Admin::where('email', $validated['email'])->first();

        if (!$admin || !Hash::check($validated['password'], $admin->password)) {
            return response()->json([
                'success' => false,
                'message' => 'بيانات الاعتماد المدخلة غير صحيحة. يرجى التحقق والمحاولة مجدداً.',
            ], 401);
        }

        if (!$admin->is_active || $admin->status === 'suspended') {
            return response()->json([
                'success' => false,
                'message' => 'تم تعطيل أو تعليق هذا الحساب. يرجى مراجعة إدارة المنصة.',
            ], 403);
        }

        // Update activity
        $admin->update([
            'last_login_at' => Carbon::now(),
            'last_login_ip' => $request->ip(),
            'last_activity' => 'متصل الآن',
            'last_activity_type' => 'online',
        ]);

        // Revoke older tokens if needed or issue a new one
        $token = $admin->createToken('admin-api-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الدخول بنجاح! مرحباً بك في لوحة تحكم المنصة.',
            'token' => $token,
            'admin' => [
                'id' => (string) $admin->id,
                'fullName' => $admin->full_name,
                'shortTitle' => $admin->short_title ?? 'أ/',
                'initials' => $admin->initials ?? mb_substr($admin->full_name, 0, 2),
                'email' => $admin->email,
                'role' => $admin->role,
                'status' => $admin->status,
                'lastActivity' => $admin->last_activity ?? 'متصل الآن',
                'lastActivityType' => $admin->last_activity_type ?? 'online',
                'avatarColor' => $admin->avatar_color,
                'isFounder' => (bool) $admin->is_founder,
            ],
        ]);
    }

    /**
     * Get the authenticated admin details.
     */
    public function me(Request $request): JsonResponse
    {
        $admin = $request->user();

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح بالدخول.',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'admin' => [
                'id' => (string) $admin->id,
                'fullName' => $admin->full_name,
                'shortTitle' => $admin->short_title ?? 'أ/',
                'initials' => $admin->initials ?? mb_substr($admin->full_name, 0, 2),
                'email' => $admin->email,
                'role' => $admin->role,
                'status' => $admin->status,
                'lastActivity' => $admin->last_activity ?? 'متصل الآن',
                'lastActivityType' => $admin->last_activity_type ?? 'online',
                'avatarColor' => $admin->avatar_color,
                'isFounder' => (bool) $admin->is_founder,
                'role_info' => $admin->roleRef,
            ],
        ]);
    }

    /**
     * Logout and revoke current token.
     */
    public function logout(Request $request): JsonResponse
    {
        $admin = $request->user();

        if ($admin && $admin->currentAccessToken()) {
            $admin->currentAccessToken()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الخروج بنجاح.',
        ]);
    }

    /**
     * Get team members list (for Admin Team view).
     */
    public function team(): JsonResponse
    {
        $members = Admin::orderBy('is_founder', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($admin) {
                return [
                    'id' => (string) $admin->id,
                    'fullName' => $admin->full_name,
                    'shortTitle' => $admin->short_title ?? 'أ/',
                    'initials' => $admin->initials ?? mb_substr($admin->full_name, 0, 2),
                    'email' => $admin->email,
                    'role' => $admin->role,
                    'status' => $admin->status,
                    'lastActivity' => $admin->last_activity ?? 'اليوم',
                    'lastActivityType' => $admin->last_activity_type ?? 'today',
                    'avatarColor' => $admin->avatar_color,
                    'isFounder' => (bool) $admin->is_founder,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $members,
        ]);
    }

    /**
     * Add a new admin or team member.
     */
    public function storeMember(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fullName' => 'required|string|max:255',
            'email' => 'required|email|unique:admins,email',
            'tempPassword' => 'required|string|min:6',
            'role' => 'required|in:super_admin,code_manager,content_manager',
            'isActive' => 'boolean',
            'shortTitle' => 'nullable|string|max:10',
        ], [
            'email.unique' => 'هذا البريد الإلكتروني مستخدم بالفعل لمشرف آخر.',
            'fullName.required' => 'اسم المشرف مطلوب.',
            'tempPassword.min' => 'كلمة المرور يجب أن لا تقل عن 6 أحرف.',
        ]);

        $colors = ['#FF6A00', '#2563EB', '#10B981', '#7C3AED', '#EC4899', '#F59E0B'];
        $randomColor = $colors[array_rand($colors)];

        $roleModel = Role::where('name', $validated['role'])->first();

        // compute initials from fullName
        $words = explode(' ', trim($validated['fullName']));
        $initials = '';
        foreach ($words as $w) {
            if (!empty($w)) {
                $initials .= mb_substr($w, 0, 1);
            }
            if (mb_strlen($initials) >= 2) break;
        }

        $admin = Admin::create([
            'full_name' => $validated['fullName'],
            'short_title' => $validated['shortTitle'] ?? 'أ/',
            'initials' => $initials ?: 'أد',
            'email' => $validated['email'],
            'password' => Hash::make($validated['tempPassword']),
            'role' => $validated['role'],
            'role_id' => $roleModel ? $roleModel->id : null,
            'status' => ($validated['isActive'] ?? true) ? 'active' : 'suspended',
            'is_active' => $validated['isActive'] ?? true,
            'avatar_color' => $randomColor,
            'is_founder' => false,
            'last_activity' => 'جديد',
            'last_activity_type' => 'today',
        ]);

        if ($roleModel) {
            $admin->roles()->syncWithoutDetaching([$roleModel->id]);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة المشرف الجديد بنجاح.',
            'data' => [
                'id' => (string) $admin->id,
                'fullName' => $admin->full_name,
                'shortTitle' => $admin->short_title,
                'initials' => $admin->initials,
                'email' => $admin->email,
                'role' => $admin->role,
                'status' => $admin->status,
                'lastActivity' => $admin->last_activity,
                'lastActivityType' => $admin->last_activity_type,
                'avatarColor' => $admin->avatar_color,
                'isFounder' => false,
            ],
        ], 201);
    }

    /**
     * Toggle status (active/suspended) of a team member.
     */
    public function toggleStatus(Request $request, $id): JsonResponse
    {
        $admin = Admin::findOrFail($id);

        if ($admin->is_founder) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن تعديل حالة المؤسس الرئيسي للمنصة.',
            ], 403);
        }

        $newStatus = $admin->status === 'active' ? 'suspended' : 'active';
        $admin->update([
            'status' => $newStatus,
            'is_active' => $newStatus === 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث حالة المشرف بنجاح.',
            'status' => $newStatus,
        ]);
    }
}
