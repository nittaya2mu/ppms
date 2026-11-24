<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AcademicService;
use App\Services\AuditLogger;
use App\Services\OfferingService;
use App\Services\Rules\RuleViolation;
use App\Services\SettingsService;

/** System Settings menu: academic structure, deadlines, rubric, configurable rules. */
final class SettingsController extends Controller
{
    public function academic(Request $request): Response
    {
        $staff = Database::connection()->fetchAll(
            "SELECT DISTINCT u.id, u.title_th, u.first_name_th, u.last_name_th FROM users u
               JOIN user_roles ur ON ur.user_id = u.id AND ur.is_active = 1 JOIN roles r ON r.id = ur.role_id AND r.code = 'admin'
              WHERE u.is_active = 1 AND u.deleted_at IS NULL ORDER BY u.first_name_th"
        );
        return $this->view('admin/settings/academic', [
            'years' => AcademicService::years(), 'semesters' => AcademicService::semesters(),
            'courses' => AcademicService::courses(), 'offerings' => AcademicService::offerings(), 'staff' => $staff,
            'tab' => 'academic',
        ]);
    }

    public function saveYear(Request $r): Response
    {
        return $this->attempt($r, '/admin/settings/academic', fn () => AcademicService::saveYear(
            (int) $r->input('year_be'), (string) $r->input('start_date'), (string) $r->input('end_date'), $r->input('is_current') !== null, $this->userId()
        ), __('settings.saved'));
    }

    public function saveSemester(Request $r): Response
    {
        return $this->attempt($r, '/admin/settings/academic', fn () => AcademicService::saveSemester(
            (int) $r->input('academic_year_id'), (int) $r->input('term_no'), (string) $r->input('start_date'), (string) $r->input('end_date'), $this->userId()
        ), __('settings.saved'));
    }

    public function saveCourse(Request $r): Response
    {
        return $this->attempt($r, '/admin/settings/academic', function () use ($r) {
            $code = trim((string) $r->input('course_code'));
            $nameTh = trim((string) $r->input('name_th'));
            if ($code === '' || $nameTh === '') {
                throw new RuleViolation('required_fields', 'Required');
            }
            AcademicService::saveCourse($r->input('id') ? (int) $r->input('id') : null, $code, $nameTh, trim((string) $r->input('name_en')) ?: $nameTh, (string) $r->input('credits'), $this->userId());
        }, __('settings.saved'));
    }

    public function createOffering(Request $r): Response
    {
        return $this->attempt($r, '/admin/settings/academic', fn () => AcademicService::createOffering(
            (int) $r->input('course_id'), (int) $r->input('semester_id'), (int) $r->input('coordinator_id'), $this->userId()
        ), __('settings.offering_created'));
    }

    public function offeringStatus(Request $r): Response
    {
        return $this->attempt($r, '/admin/settings/academic', fn () => AcademicService::setOfferingStatus(
            $this->id($r), (string) $r->input('status'), $this->userId()
        ), __('settings.saved'));
    }

    public function deadlines(Request $request): Response
    {
        $o = $this->offering();
        return $this->view('admin/settings/deadlines', ['offering' => $o, 'deadlines' => AcademicService::deadlines((int) $o['id']), 'tab' => 'deadlines']);
    }

    public function saveDeadlines(Request $r): Response
    {
        $o = $this->offering();
        $values = [];
        foreach (AcademicService::DEADLINE_TYPES as $t) {
            $values[$t] = [
                'due_at'        => (string) $r->input("due_$t"),
                'allow_late'    => $r->input("late_$t") !== null,
                'reminder_days' => (int) $r->input("remind_$t", 2),
            ];
        }
        return $this->attempt($r, '/admin/settings/deadlines', fn () => AcademicService::saveDeadlines((int) $o['id'], $values, $this->userId()), __('settings.saved'));
    }

    public function rubric(Request $request): Response
    {
        $o = $this->offering();
        return $this->view('admin/settings/rubric', [
            'offering' => $o, 'criteria' => AcademicService::rubric((int) $o['id']),
            'locked' => AcademicService::rubricLocked((int) $o['id']), 'tab' => 'rubric',
        ]);
    }

    public function saveRubric(Request $r): Response
    {
        $o = $this->offering();
        $rows = [];
        foreach ((array) ($r->all()['criteria'] ?? []) as $c) {
            $rows[] = [
                'id' => isset($c['id']) && $c['id'] !== '' ? (int) $c['id'] : null,
                'name_th' => (string) ($c['name_th'] ?? ''), 'name_en' => (string) ($c['name_en'] ?? ''),
                'description' => (string) ($c['description'] ?? ''), 'weight' => (string) ($c['weight'] ?? '0'),
                'is_active' => isset($c['is_active']),
            ];
        }
        return $this->attempt($r, '/admin/settings/rubric', fn () => AcademicService::saveRubric((int) $o['id'], $rows, $this->userId()), __('settings.saved'));
    }

    public function system(Request $request): Response
    {
        return $this->view('admin/settings/system', ['settings' => SettingsService::all(), 'tab' => 'system']);
    }

    public function saveSystem(Request $r): Response
    {
        return $this->attempt($r, '/admin/settings', function () use ($r) {
            $input = (array) ($r->all()['settings'] ?? []);
            foreach (SettingsService::all() as $s) {
                $key = $s['setting_key'];
                $field = str_replace('.', '__', $key);
                $value = match ($s['value_type']) {
                    'bool' => isset($input[$field]) ? 'true' : 'false',
                    'int'  => (string) max(0, (int) ($input[$field] ?? $s['setting_value'])),
                    'json' => self::json((string) ($input[$field] ?? $s['setting_value']), $key),
                    default => trim((string) ($input[$field] ?? $s['setting_value'])),
                };
                if ($value !== $s['setting_value']) {
                    SettingsService::set($key, $value, $this->userId());
                    AuditLogger::log('setting.changed', 'setting', (int) $s['id'], ['value' => $s['setting_value']], ['value' => $value]);
                }
            }
            OfferingService::flush();
        }, __('settings.saved'));
    }

    private static function json(string $value, string $key): string
    {
        $decoded = json_decode($value, true);
        if ($decoded === null && trim($value) !== 'null') {
            throw new RuleViolation('setting_json', "Invalid JSON for $key");
        }
        return json_encode($decoded, JSON_UNESCAPED_UNICODE);
    }
}
