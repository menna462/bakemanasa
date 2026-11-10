<?php

namespace Database\Seeders;

use App\Models\ActivationCode;
use App\Models\Admin;
use App\Models\GradeLevel;
use App\Models\Lesson;
use App\Models\Note;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StudentProgress;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with initial demo & frontend matching data.
     */
    public function run(): void
    {
        // 1. Roles & Permissions
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin'], [
            'display_name' => 'المشرف العام (Super Admin)',
            'description' => 'صلاحيات كاملة للتحكم في المنصة وفريق العمل والمحتوى والأكواد',
        ]);

        $codeManagerRole = Role::firstOrCreate(['name' => 'code_manager'], [
            'display_name' => 'مدير الأكواد والمجموعات',
            'description' => 'توليد وتصدير وإدارة أكواد التفعيل وتتبع الاستهلاك',
        ]);

        $contentManagerRole = Role::firstOrCreate(['name' => 'content_manager'], [
            'display_name' => 'مدير المحتوى التعليمي',
            'description' => 'رفع المحاضرات والمذكرات وإدارة المناهج والوحدات',
        ]);

        // Default Permissions
        $permissions = [
            ['name' => 'codes.view', 'display_name' => 'عرض الأكواد', 'group' => 'codes'],
            ['name' => 'codes.create', 'display_name' => 'توليد أكواد جديدة', 'group' => 'codes'],
            ['name' => 'codes.export', 'display_name' => 'تصدير وطباعة كروت الأكواد', 'group' => 'codes'],
            ['name' => 'curriculum.manage', 'display_name' => 'إدارة المنهج والمحاضرات', 'group' => 'curriculum'],
            ['name' => 'admins.manage', 'display_name' => 'إدارة فريق العمل والمشرفين', 'group' => 'admins'],
            ['name' => 'analytics.view', 'display_name' => 'الاطلاع على الإحصائيات وسجل المشاهدة', 'group' => 'analytics'],
        ];

        foreach ($permissions as $p) {
            $perm = Permission::firstOrCreate(['name' => $p['name']], $p);
            $superAdminRole->permissions()->syncWithoutDetaching([$perm->id]);
            if (str_starts_with($p['name'], 'codes')) {
                $codeManagerRole->permissions()->syncWithoutDetaching([$perm->id]);
            }
            if (str_starts_with($p['name'], 'curriculum')) {
                $contentManagerRole->permissions()->syncWithoutDetaching([$perm->id]);
            }
        }

        // 2. Founder Admin & Staff Members
        $founder = Admin::firstOrCreate(['email' => 'admin@emadhussein.edu'], [
            'full_name' => 'عماد حسين',
            'short_title' => 'أ/',
            'initials' => 'عح',
            'password' => Hash::make('AdminSecret@2026'),
            'role' => 'super_admin',
            'role_id' => $superAdminRole->id,
            'status' => 'active',
            'is_active' => true,
            'avatar_color' => '#FF6A00',
            'is_founder' => true,
            'last_activity' => 'متصل الآن',
            'last_activity_type' => 'online',
        ]);
        $founder->roles()->syncWithoutDetaching([$superAdminRole->id]);

        $adminAlt = Admin::firstOrCreate(['email' => 'emad.hussein@codekedu.com'], [
            'full_name' => 'عماد حسين',
            'short_title' => 'أ/',
            'initials' => 'عح',
            'password' => Hash::make('password123'),
            'role' => 'super_admin',
            'role_id' => $superAdminRole->id,
            'status' => 'active',
            'is_active' => true,
            'avatar_color' => '#FF6A00',
            'is_founder' => true,
            'last_activity' => 'متصل الآن',
            'last_activity_type' => 'online',
        ]);
        $adminAlt->roles()->syncWithoutDetaching([$superAdminRole->id]);

        $staffCodeManager = Admin::firstOrCreate(['email' => 'tarek.codes@codekedu.com'], [
            'full_name' => 'طارق الزيات',
            'short_title' => 'م/',
            'initials' => 'طز',
            'password' => Hash::make('password123'),
            'role' => 'code_manager',
            'role_id' => $codeManagerRole->id,
            'status' => 'active',
            'is_active' => true,
            'avatar_color' => '#2563EB',
            'is_founder' => false,
            'last_activity' => 'منذ ساعتين',
            'last_activity_type' => 'today',
        ]);
        $staffCodeManager->roles()->syncWithoutDetaching([$codeManagerRole->id]);

        // 3. Curriculum: Grade Level
        $grade3 = GradeLevel::firstOrCreate(['title' => 'الصف الثالث الثانوي'], [
            'subtitle' => 'دفعة الثانوية العامة 2025/2026',
            'order_number' => 1,
            'is_active' => true,
            'status_text' => 'نشط حالياً',
            'status_type' => 'active',
        ]);

        $grade2 = GradeLevel::firstOrCreate(['title' => 'الصف الثاني الثانوي'], [
            'subtitle' => 'كورس الترم الأول والتأسيس',
            'order_number' => 2,
            'is_active' => true,
            'status_text' => 'متاح للتسجيل',
            'status_type' => 'published',
        ]);

        // 4. Units
        $unit1 = Unit::firstOrCreate([
            'grade_level_id' => $grade3->id,
            'unit_number' => 1,
        ], [
            'title' => 'الوحدة 1: الاتزان والتأسيس الشامل',
            'subtitle' => '4 محاضرات • 3 ساعات و 45 دقيقة',
            'description' => 'مفاهيم الاتزان الكيميائي وقوانين لوشاتيليه ومسائل ثابت الاتزان Kc',
            'is_published' => true,
        ]);

        $unit2 = Unit::firstOrCreate([
            'grade_level_id' => $grade3->id,
            'unit_number' => 2,
        ], [
            'title' => 'الوحدة 2: الكيمياء التحليلية والتطبيقات',
            'subtitle' => '3 محاضرات • ساعتان ونصف',
            'description' => 'التحليل الوصفي والكمي وطرق المعايرة وحل مسائل الترسيب',
            'is_published' => true,
        ]);

        // 5. Lessons
        $lesson1 = Lesson::firstOrCreate([
            'unit_id' => $unit1->id,
            'order_number' => 1,
        ], [
            'grade_level_id' => $grade3->id,
            'title' => 'المحاضرة 1: التأسيس الشامل ومفهوم الاتزان',
            'description' => 'مدخل شامل لمفاهيم الاتزان الكيميائي الديناميكي وتطبيقاته العملية',
            'duration' => '01:15:30',
            'duration_seconds' => 4530,
            'size' => '480 MB',
            'views_count' => 1250,
            'status' => 'published',
            'video_source' => 'external',
            'video_url' => 'https://iframe.videodelivery.net/demo-sample',
            'video_provider' => 'bunny',
            'has_pdf' => true,
            'pdf_title' => 'مذكرة المحاضرة الأولى _ ملخص القوانين والمسائل.pdf',
            'pdf_url' => '/uploads/pdfs/lesson-1-summary.pdf',
            'has_quiz' => true,
            'quiz_title' => 'امتحان إلكتروني تقييمي على المحاضرة الأولى',
            'quiz_questions_count' => 20,
            'quiz_duration' => '30 دقيقة',
            'is_free' => false,
        ]);

        $lesson2 = Lesson::firstOrCreate([
            'unit_id' => $unit1->id,
            'order_number' => 2,
        ], [
            'grade_level_id' => $grade3->id,
            'title' => 'المحاضرة 2: حل التطبيقات وقاعدة لوشاتيليه',
            'description' => 'تطبيقات عملية وأفكار الامتحانات السابقة على قاعدة لوشاتيليه',
            'duration' => '00:45:00',
            'duration_seconds' => 2700,
            'size' => '310 MB',
            'views_count' => 980,
            'status' => 'published',
            'video_source' => 'external',
            'video_url' => 'https://iframe.videodelivery.net/demo-sample-2',
            'video_provider' => 'bunny',
            'has_pdf' => true,
            'pdf_title' => 'شيت تدريبات المحاضرة الثانية.pdf',
            'pdf_url' => '/uploads/pdfs/lesson-2-sheet.pdf',
            'has_quiz' => true,
            'quiz_title' => 'كويز سريع: 10 أسئلة مهارات عليا',
            'quiz_questions_count' => 10,
            'quiz_duration' => '15 دقيقة',
            'is_free' => false,
        ]);

        $lesson3 = Lesson::firstOrCreate([
            'unit_id' => $unit1->id,
            'order_number' => 3,
        ], [
            'grade_level_id' => $grade3->id,
            'title' => 'المحاضرة 3: القوانين الذهبية والأفكار العالية',
            'description' => 'أسرار التعامل مع مسائل الاتزان الصعبة وتريكات الوزارة',
            'duration' => '01:05:00',
            'duration_seconds' => 3900,
            'size' => '420 MB',
            'views_count' => 640,
            'status' => 'published',
            'video_source' => 'external',
            'video_url' => 'https://iframe.videodelivery.net/demo-sample-3',
            'video_provider' => 'bunny',
            'has_pdf' => true,
            'pdf_title' => 'ملخص تريكات ثابت الاتزان.pdf',
            'pdf_url' => '/uploads/pdfs/lesson-3-tricks.pdf',
            'has_quiz' => false,
            'is_free' => false,
        ]);

        // 6. Activation Codes
        $codes = [
            [
                'code' => '5820264891',
                'group_name' => 'كود تجريبي — منصة عماد حسين',
                'grade_level_id' => $grade3->id,
                'lesson_id' => $lesson1->id,
                'validity_days' => 365,
                'status' => 'new',
            ],
            [
                'code' => '4839231047',
                'group_name' => 'سنتر الأمل — شهر 10',
                'grade_level_id' => $grade3->id,
                'lesson_id' => $lesson1->id,
                'validity_days' => 30,
                'status' => 'new',
            ],
            [
                'code' => '7741209384',
                'group_name' => 'سنتر الأمل — شهر 10',
                'grade_level_id' => $grade3->id,
                'lesson_id' => $lesson1->id,
                'validity_days' => 30,
                'status' => 'new',
            ],
            [
                'code' => '9920381746',
                'group_name' => 'مراجعة الباب الأول — كيمياء',
                'grade_level_id' => $grade3->id,
                'lesson_id' => $lesson1->id,
                'validity_days' => 15,
                'status' => 'used',
                'used_by' => 'محمد كريم (سنتر النور)',
                'student_phone' => '01012345678',
                'activated_at' => Carbon::now()->subDays(2),
                'expires_at' => Carbon::now()->addDays(13),
            ],
            [
                'code' => '3412857629',
                'group_name' => 'لغات المعادي — الصف الثالث',
                'grade_level_id' => $grade3->id,
                'lesson_id' => $lesson1->id,
                'validity_days' => 60,
                'status' => 'used',
                'used_by' => 'سارة طارق (أونلاين)',
                'student_phone' => '01198765432',
                'activated_at' => Carbon::now()->subDays(5),
                'expires_at' => Carbon::now()->addDays(55),
            ],
            [
                'code' => '6120934715',
                'group_name' => 'مجموعة الأوائل — مكثف',
                'grade_level_id' => $grade3->id,
                'lesson_id' => $lesson1->id,
                'validity_days' => 15,
                'status' => 'expired',
                'used_by' => 'عمر خالد (انتهت الصلاحية)',
                'student_phone' => '01234567890',
                'activated_at' => Carbon::now()->subDays(30),
                'expires_at' => Carbon::now()->subDays(15),
            ],
        ];

        foreach ($codes as $c) {
            $c['created_by_admin_id'] = $founder->id;
            $codeModel = ActivationCode::firstOrCreate(['code' => $c['code']], $c);

            // 7. Student progress if used
            if ($codeModel->status === 'used') {
                StudentProgress::firstOrCreate([
                    'activation_code_id' => $codeModel->id,
                    'lesson_id' => $lesson1->id,
                ], [
                    'student_name' => $codeModel->used_by,
                    'student_phone' => $codeModel->student_phone,
                    'grade' => 'الصف الثالث الثانوي',
                    'watch_progress' => 85,
                    'current_seconds' => 3800,
                    'total_watch_minutes' => 65,
                    'completed' => true,
                    'quiz_score' => 95,
                    'replay_count' => 2,
                    'device' => 'desktop',
                    'last_watched_at' => Carbon::now()->subHours(4),
                ]);
            }
        }

        // 8. Demo Notes on Lesson 1
        $demoCode = ActivationCode::where('code', '5820264891')->first();
        Note::firstOrCreate([
            'lesson_id' => $lesson1->id,
            'time_formatted' => '15:20',
        ], [
            'activation_code_id' => $demoCode ? $demoCode->id : null,
            'student_name' => 'طالب تجريبي',
            'time_seconds' => 920,
            'content' => 'التركيز على شروط تطبيق قاعدة لوشاتيليه عند تغير الضغط فقط في الغازات.',
        ]);

        Note::firstOrCreate([
            'lesson_id' => $lesson1->id,
            'time_formatted' => '24:15',
        ], [
            'activation_code_id' => $demoCode ? $demoCode->id : null,
            'student_name' => 'طالب تجريبي',
            'time_seconds' => 1455,
            'content' => 'معادلة ثابت الاتزان Kc وتأثير درجة الحرارة على التفاعل الماص والطار.',
        ]);
    }
}
