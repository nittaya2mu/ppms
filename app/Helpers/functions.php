<?php

declare(strict_types=1);

use App\Core\Csrf;

/** Escape for HTML output (NFR-SEC-04). */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function config(string $key, mixed $default = null): mixed
{
    static $config = null;
    $config ??= require dirname(__DIR__, 2) . '/config/app.php';
    $value = $config;
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value;
}

function url(string $path = '/'): string
{
    return rtrim((string) config('base_path', ''), '/') . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $path = ltrim($path, '/');
    // Cache-busting: append the file's modification time so browsers (and the host's cache)
    // fetch a fresh copy after every upload.
    $candidates = [dirname(__DIR__, 2) . '/public/assets/' . $path];
    if (!empty($_SERVER['SCRIPT_FILENAME'])) {
        $candidates[] = dirname((string) $_SERVER['SCRIPT_FILENAME']) . '/assets/' . $path;
    }
    foreach ($candidates as $file) {
        if (is_file($file)) {
            return url('assets/' . $path) . '?v=' . filemtime($file);
        }
    }
    return url('assets/' . $path);
}

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
}

function old(string $key, mixed $default = ''): mixed
{
    return $GLOBALS['__old'][$key] ?? $default;
}

/** @return string|null first validation error for a field */
function error(string $key): ?string
{
    $errors = $GLOBALS['__errors'][$key] ?? null;
    return is_array($errors) ? ($errors[0] ?? null) : $errors;
}

function has_errors(): bool
{
    return !empty($GLOBALS['__errors']);
}

/**
 * Translation lookup (NFR-USA-01). Keys live in resources/lang/{locale}.php.
 */
function __(string $key, array $replace = []): string
{
    static $lines = null;
    if ($lines === null) {
        $file = dirname(__DIR__, 2) . '/resources/lang/' . config('locale', 'th') . '.php';
        $lines = is_file($file) ? require $file : [];
    }
    $text = $lines[$key] ?? $key;
    foreach ($replace as $k => $v) {
        $text = str_replace(':' . $k, (string) $v, $text);
    }
    return $text;
}

/** Thai date with Buddhist Era year (DD-15), e.g. 24 พ.ย. 2569 14:05 */
function thai_date(?string $datetime, bool $withTime = false): string
{
    if ($datetime === null || $datetime === '') {
        return '-';
    }
    $months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $ts = strtotime($datetime);
    if ($ts === false) {
        return '-';
    }
    $out = (int) date('j', $ts) . ' ' . $months[(int) date('n', $ts) - 1] . ' ' . ((int) date('Y', $ts) + 543);
    return $withTime ? $out . ' ' . date('H:i', $ts) : $out;
}

function full_name(array $user, bool $withTitle = true): string
{
    $name = trim(($user['first_name_th'] ?? '') . ' ' . ($user['last_name_th'] ?? ''));
    return $withTitle && !empty($user['title_th']) ? $user['title_th'] . $name : $name;
}

/** Bootstrap badge for a workflow status. */
function status_badge(string $status): string
{
    $colors = [
        'draft' => 'secondary', 'submitted' => 'info', 'verified' => 'primary', 'active' => 'success',
        'inactive' => 'dark', 'advisor_review' => 'info', 'advisor_approved' => 'primary',
        'revision_required' => 'warning', 'admin_review' => 'info', 'topic_approved' => 'success',
        'topic_rejected' => 'danger', 'committee_assignment' => 'primary', 'committee_evaluation' => 'info',
        'evaluation_completed' => 'primary', 're_evaluation' => 'info', 'final_review' => 'primary',
        'approved' => 'success', 'approved_minor' => 'success', 'rejected' => 'danger',
    ];
    $color = $colors[$status] ?? 'secondary';
    $text = in_array($color, ['warning', 'info'], true) ? 'text-dark' : '';
    return '<span class="badge text-bg-' . $color . ' ' . $text . '">' . e(__('status.' . $status)) . '</span>';
}
