<?php

declare(strict_types=1);

use App\Controllers\Admin\SettingsController;
use App\Controllers\Admin\UserController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\EvaluationController;
use App\Controllers\FileController;
use App\Controllers\GroupController;
use App\Controllers\NotificationController;
use App\Controllers\ProjectController;
use App\Controllers\ProjectFormsController;
use App\Controllers\QueueController;
use App\Controllers\ReportController;
use App\Core\Auth;
use App\Core\Response;
use App\Core\Router;

/** @var Router $router */

$router->get('/', fn () => Response::redirect(Auth::check() ? '/dashboard' : '/login'));

// Guest
$router->group('', ['guest'], function (Router $r) {
    $r->get('/login', [AuthController::class, 'showLogin']);
    $r->post('/login', [AuthController::class, 'login'], ['csrf']);
});

// Authenticated, password change allowed even when forced
$router->group('', ['auth'], function (Router $r) {
    $r->post('/logout', [AuthController::class, 'logout'], ['csrf']);
    $r->get('/password', [AuthController::class, 'showChangePassword']);
    $r->post('/password', [AuthController::class, 'changePassword'], ['csrf']);
});

// Authenticated and password already changed
$router->group('', ['auth', 'password'], function (Router $r) {
    $r->get('/dashboard', [DashboardController::class, 'index']);
    $r->post('/role', [AuthController::class, 'switchRole'], ['csrf']);
    $r->get('/notifications', [NotificationController::class, 'index']);
    $r->post('/notifications/read', [NotificationController::class, 'markRead'], ['csrf']);
    $r->get('/files/{id}', [FileController::class, 'download']);

    // Project Groups (all roles see what they are allowed to; checks inside)
    $r->get('/groups', [GroupController::class, 'index']);
    $r->get('/groups/{id}', [GroupController::class, 'showPage']);
    $r->post('/groups/{id}/advisor-response', [GroupController::class, 'respondAdvisor'], ['role:advisor', 'csrf']);
    $r->group('', ['role:student', 'csrf'], function (Router $r) {
        $r->post('/groups', [GroupController::class, 'store']);
        $r->post('/groups/invitations/{member}', [GroupController::class, 'respond']);
        $r->post('/groups/{id}/rename', [GroupController::class, 'rename']);
        $r->post('/groups/{id}/invite', [GroupController::class, 'invite']);
        $r->post('/groups/{id}/members/{member}/remove', [GroupController::class, 'removeMember']);
        $r->post('/groups/{id}/submit', [GroupController::class, 'submit']);
        $r->post('/groups/{id}/advisor-request', [GroupController::class, 'requestAdvisor']);
    });
    $r->group('', ['role:admin', 'csrf'], function (Router $r) {
        $r->post('/groups/{id}/verify', [GroupController::class, 'verify']);
        $r->post('/groups/{id}/reject', [GroupController::class, 'reject']);
        $r->post('/groups/{id}/advisor', [GroupController::class, 'assignAdvisor']);
        $r->post('/groups/{id}/members/add', [GroupController::class, 'adminAddMember']);
        $r->post('/groups/{id}/members/{member}/admin', [GroupController::class, 'adminMember']);
    });

    // Project page (topic + proposal)
    $r->get('/projects/{id}', [ProjectController::class, 'show']);
    $r->get('/projects/{id}/summary', [ProjectController::class, 'summary']);
    $r->get('/projects/{id}/topic/print', [ProjectController::class, 'topicPrint']);
    $r->get('/projects/{id}/proposal/print', [ProjectController::class, 'proposalPrint']);
    $r->post('/projects/{id}/chapters/{review}/review', [ProjectController::class, 'reviewChapter'], ['role:advisor', 'csrf']);
    $r->get('/projects/{id}/progress', [ProjectFormsController::class, 'progress']);
    $r->get('/projects/{id}/progress/print', [ProjectFormsController::class, 'progressPrint']);
    $r->get('/projects/{id}/scope', [ProjectFormsController::class, 'scope']);
    $r->get('/projects/{id}/scope/print', [ProjectFormsController::class, 'scopePrint']);
    $r->group('', ['role:student', 'csrf'], function (Router $r) {
        $r->post('/projects/{id}/progress', [ProjectFormsController::class, 'progressStore']);
        $r->post('/projects/{id}/progress/{report}', [ProjectFormsController::class, 'progressUpdate']);
        $r->post('/projects/{id}/scope', [ProjectFormsController::class, 'scopeStore']);
    });
    $r->post('/projects/{id}/progress/{report}/review', [ProjectFormsController::class, 'progressReview'], ['role:advisor', 'csrf']);
    $r->post('/projects/{id}/scope/decide', [ProjectFormsController::class, 'scopeDecide'], ['role:advisor,committee', 'csrf']);
    $r->group('', ['role:student', 'csrf'], function (Router $r) {
        $r->post('/projects/{id}/topic', [ProjectController::class, 'saveTopic']);
        $r->post('/projects/{id}/topic/documents', [ProjectController::class, 'uploadTopicDocument']);
        $r->post('/projects/{id}/proposal', [ProjectController::class, 'submitProposal']);
    });
    $r->group('', ['role:advisor', 'csrf'], function (Router $r) {
        $r->post('/projects/{id}/topic/advisor', [ProjectController::class, 'topicAdvisorDecision']);
        $r->post('/projects/{id}/proposal/advisor', [ProjectController::class, 'proposalAdvisorDecision']);
    });
    $r->group('', ['role:admin', 'csrf'], function (Router $r) {
        $r->post('/projects/{id}/topic/admin', [ProjectController::class, 'topicAdminDecision']);
        $r->post('/projects/{id}/committee', [ProjectController::class, 'assignCommittee']);
        $r->post('/projects/{id}/committee/replace', [ProjectController::class, 'replaceMember']);
        $r->post('/projects/{id}/mode', [ProjectController::class, 'revisionMode']);
        $r->post('/projects/{id}/decision', [ProjectController::class, 'finalDecision']);
        $r->post('/projects/{id}/evaluations/reopen', [ProjectController::class, 'reopenEvaluation']);
    });

    // Work lists (menu)
    $r->get('/topics', [QueueController::class, 'topics']);
    $r->get('/topic-approval', [QueueController::class, 'topicApproval']);
    $r->get('/proposals', [QueueController::class, 'proposals']);
    $r->get('/advisor-review', [QueueController::class, 'advisorReview']);
    $r->get('/committee-assignment', [QueueController::class, 'committeeAssignment']);
    $r->get('/final-approval', [QueueController::class, 'finalApproval']);
    $r->get('/evaluations', [QueueController::class, 'evaluations']);

    // Committee evaluation form
    $r->get('/progress', [QueueController::class, 'progress']);
    $r->get('/scope-approval', [QueueController::class, 'scope']);
    $r->group('', ['role:advisor,committee'], function (Router $r) {
        $r->get('/evaluations/{id}', [EvaluationController::class, 'edit']);
        $r->post('/evaluations/{id}', [EvaluationController::class, 'save'], ['csrf']);
    });

    // Reports (admin; advisors get a subset, checked in the controller)
    $r->group('', ['role:admin,advisor'], function (Router $r) {
        $r->get('/reports', [ReportController::class, 'index']);
        $r->get('/reports/{key}', [ReportController::class, 'show']);
    });

    // Administrator
    $r->group('/admin', ['role:admin'], function (Router $r) {
        $r->get('/users', [UserController::class, 'index']);
        $r->get('/users/create', [UserController::class, 'create']);
        $r->post('/users', [UserController::class, 'store'], ['csrf']);
        $r->get('/users/{id}/edit', [UserController::class, 'edit']);
        $r->post('/users/{id}', [UserController::class, 'update'], ['csrf']);
        $r->post('/users/{id}/reset-password', [UserController::class, 'resetPassword'], ['csrf']);
        $r->post('/users/{id}/delete', [UserController::class, 'deactivate'], ['csrf']);

        $r->get('/settings', [SettingsController::class, 'system']);
        $r->post('/settings', [SettingsController::class, 'saveSystem'], ['csrf']);
        $r->get('/settings/academic', [SettingsController::class, 'academic']);
        $r->post('/settings/years', [SettingsController::class, 'saveYear'], ['csrf']);
        $r->post('/settings/semesters', [SettingsController::class, 'saveSemester'], ['csrf']);
        $r->post('/settings/courses', [SettingsController::class, 'saveCourse'], ['csrf']);
        $r->post('/settings/offerings', [SettingsController::class, 'createOffering'], ['csrf']);
        $r->post('/settings/offerings/{id}/status', [SettingsController::class, 'offeringStatus'], ['csrf']);
        $r->get('/settings/deadlines', [SettingsController::class, 'deadlines']);
        $r->post('/settings/deadlines', [SettingsController::class, 'saveDeadlines'], ['csrf']);
        $r->get('/settings/rubric', [SettingsController::class, 'rubric']);
        $r->post('/settings/rubric', [SettingsController::class, 'saveRubric'], ['csrf']);
    });
});
