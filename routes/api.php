<?php

use App\Http\Controllers\Api\AdminAuthController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\CodeController;
use App\Http\Controllers\Api\CurriculumController;
use App\Http\Controllers\Api\StudentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - CodeKedu Platform
|--------------------------------------------------------------------------
*/

// ==========================================
// 1. Student Portal Routes (Public)
// ==========================================
Route::prefix('student')->group(function () {
    Route::post('/verify-code', [StudentController::class, 'verifyCode']);
    Route::get('/lecture/{code}', [StudentController::class, 'getLecture']);
    Route::post('/progress', [StudentController::class, 'updateProgress']);
    Route::post('/notes', [StudentController::class, 'addNote']);
});

// Public Curriculum view (for landing page & grades overview)
Route::get('/curriculum/tree', [CurriculumController::class, 'index']);


// ==========================================
// 2. Admin Authentication (Public)
// ==========================================
Route::prefix('admin')->group(function () {
    Route::post('/login', [AdminAuthController::class, 'login']);
});


// ==========================================
// 3. Admin Protected Dashboard Routes (auth:sanctum)
// ==========================================
Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    // Current Admin & Team
    Route::get('/me', [AdminAuthController::class, 'me']);
    Route::post('/logout', [AdminAuthController::class, 'logout']);
    Route::get('/team', [AdminAuthController::class, 'team']);
    Route::post('/team', [AdminAuthController::class, 'storeMember']);
    Route::patch('/team/{id}/toggle-status', [AdminAuthController::class, 'toggleStatus']);

    // Activation Codes Management
    Route::prefix('codes')->group(function () {
        Route::get('/', [CodeController::class, 'index']);
        Route::get('/stats', [CodeController::class, 'stats']);
        Route::post('/generate', [CodeController::class, 'generate']);
        Route::get('/export', [CodeController::class, 'export']);
        Route::post('/bulk-delete', [CodeController::class, 'bulkDestroy']);
        Route::post('/bulk-status', [CodeController::class, 'bulkStatus']);
        Route::get('/{id}', [CodeController::class, 'show']);
        Route::delete('/{id}', [CodeController::class, 'destroy']);
    });

    // Curriculum & Content Management
    Route::prefix('curriculum')->group(function () {
        Route::get('/', [CurriculumController::class, 'index']);
        Route::post('/grades', [CurriculumController::class, 'storeGrade']);
        Route::put('/grades/{id}', [CurriculumController::class, 'updateGrade']);
        Route::delete('/grades/{id}', [CurriculumController::class, 'destroyGrade']);

        Route::post('/units', [CurriculumController::class, 'storeUnit']);
        Route::put('/units/{id}', [CurriculumController::class, 'updateUnit']);
        Route::delete('/units/{id}', [CurriculumController::class, 'destroyUnit']);

        Route::post('/lessons', [CurriculumController::class, 'storeLesson']);
        Route::put('/lessons/{id}', [CurriculumController::class, 'updateLesson']);
        Route::delete('/lessons/{id}', [CurriculumController::class, 'destroyLesson']);
    });

    // Analytics & Reports
    Route::prefix('analytics')->group(function () {
        Route::get('/overview', [AnalyticsController::class, 'overview']);
        Route::get('/performance', [AnalyticsController::class, 'lecturePerformance']);
        Route::get('/students-log', [AnalyticsController::class, 'studentsWatchLog']);
        Route::get('/daily-activations', [AnalyticsController::class, 'dailyActivations']);
        Route::get('/distributions', [AnalyticsController::class, 'distributions']);
    });
});
