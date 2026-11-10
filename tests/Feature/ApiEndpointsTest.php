<?php

namespace Tests\Feature;

use App\Models\ActivationCode;
use App\Models\Admin;
use App\Models\Lesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }
    public function test_admin_can_login_and_fetch_profile(): void
    {
        $response = $this->postJson('/api/admin/login', [
            'email' => 'admin@ahmedhussein.edu',
            'password' => 'AdminSecret@2026',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'token',
                'admin' => ['id', 'fullName', 'email', 'role', 'status'],
            ]);

        $token = $response->json('token');

        // Test /api/admin/me
        $meResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/admin/me');

        $meResponse->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /**
     * Test student verify code and lecture data.
     */
    public function test_student_can_verify_code_and_get_lecture(): void
    {
        $verifyRes = $this->postJson('/api/student/verify-code', [
            'code' => 'AH-2026-PHYS',
            'student_name' => 'طالب تجريبي للاختبار',
        ]);

        $verifyRes->assertStatus(200)
            ->assertJson(['success' => true]);

        // Test fetching lecture
        $lectureRes = $this->getJson('/api/student/lecture/AH-2026-PHYS');

        $lectureRes->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'code',
                'lesson' => ['id', 'title', 'videoUrl', 'duration'],
                'units',
                'savedNotes',
            ]);
    }

    /**
     * Test activation code generation and stats.
     */
    public function test_codes_stats_and_generation(): void
    {
        // Login as admin
        $loginRes = $this->postJson('/api/admin/login', [
            'email' => 'admin@ahmedhussein.edu',
            'password' => 'AdminSecret@2026',
        ]);
        $token = $loginRes->json('token');

        // Test stats
        $statsRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/admin/codes/stats');

        $statsRes->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'stats' => ['totalCodes', 'activeAvailable', 'usedActive', 'expired'],
            ]);

        // Test generating codes
        $genRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/admin/codes/generate', [
                'count' => 5,
                'groupName' => 'مجموعة الاختبار السريع',
                'validityDays' => 30,
            ]);

        $genRes->assertStatus(201)
            ->assertJson(['success' => true, 'count' => 5]);
    }

    /**
     * Test curriculum and analytics endpoints.
     */
    public function test_curriculum_and_analytics_endpoints(): void
    {
        $loginRes = $this->postJson('/api/admin/login', [
            'email' => 'admin@ahmedhussein.edu',
            'password' => 'AdminSecret@2026',
        ]);
        $token = $loginRes->json('token');

        // Curriculum tree
        $currRes = $this->getJson('/api/curriculum/tree');
        $currRes->assertStatus(200)
            ->assertJsonStructure(['success', 'stats', 'data']);

        // Analytics overview
        $analyticsRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/admin/analytics/overview');
        $analyticsRes->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);

        // Daily activations
        $dailyRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/admin/analytics/daily-activations');
        $dailyRes->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);
    }
}
