<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\AuthService;
use Tests\DatabaseTestCase;

/** FR-AUTH-01, FR-AUTH-03, FR-AUTH-04, FR-AUTH-06. */
final class AuthServiceTest extends DatabaseTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
    }

    public function testLoginByUsernameOrEmail(): void
    {
        $auth = new AuthService();
        $this->assertSame(AuthService::OK, $auth->attempt('advisor1', 'Demo@1234'));
        $this->assertSame(101, $_SESSION['user_id']);
        $this->assertSame(AuthService::OK, $auth->attempt('committee1@ppms.local', 'Demo@1234'));
    }

    public function testWrongPasswordAndUnknownUser(): void
    {
        $auth = new AuthService();
        $this->assertSame(AuthService::INVALID, $auth->attempt('advisor1', 'nope'));
        $this->assertSame(AuthService::INVALID, $auth->attempt('ghost', 'nope'));
        $this->assertSame(1, (int) $this->db->value('SELECT failed_login_count FROM users WHERE id = 101'));
    }

    public function testAccountLocksAfterRepeatedFailures(): void
    {
        $auth = new AuthService();
        for ($i = 0; $i < 4; $i++) {
            $this->assertSame(AuthService::INVALID, $auth->attempt('advisor1', 'nope'));
        }
        $this->assertSame(AuthService::LOCKED, $auth->attempt('advisor1', 'nope'));
        // Even the correct password is refused while locked.
        $this->assertSame(AuthService::LOCKED, $auth->attempt('advisor1', 'Demo@1234'));
    }

    public function testInactiveAndSoftDeletedUsersCannotLogIn(): void
    {
        $this->db->query('UPDATE users SET is_active = 0 WHERE id = 103');
        $this->assertSame(AuthService::INACTIVE, (new AuthService())->attempt('committee1', 'Demo@1234'));
        $this->db->query('UPDATE users SET deleted_at = NOW() WHERE id = 104');
        $this->assertSame(AuthService::INVALID, (new AuthService())->attempt('committee2', 'Demo@1234'));
    }

    public function testChangeAndResetPassword(): void
    {
        $auth = new AuthService();
        $this->assertFalse($auth->changePassword(101, 'wrong', 'NewPass123'));
        $this->assertTrue($auth->changePassword(101, 'Demo@1234', 'NewPass123'));
        $this->assertSame(AuthService::OK, $auth->attempt('advisor1', 'NewPass123'));

        $temp = $auth->resetPassword(101, 1);
        $this->assertSame(1, (int) $this->db->value('SELECT must_change_password FROM users WHERE id = 101'));
        $this->assertSame(AuthService::OK, $auth->attempt('advisor1', $temp));
        $this->assertSame(1, (int) $this->db->value("SELECT COUNT(*) FROM audit_logs WHERE action = 'password.reset'"));
    }

    public function testPasswordsNeverReachTheAuditLog(): void
    {
        (new AuthService())->attempt('advisor1', 'Demo@1234');
        $this->assertSame(0, (int) $this->db->value("SELECT COUNT(*) FROM audit_logs WHERE new_values LIKE '%Demo@1234%' OR old_values LIKE '%Demo@1234%'"));
    }
}
