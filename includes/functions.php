<?php
/**
 * General helpers: output escaping, CSRF, flash messages, date formatting,
 * and the small pieces of "business logic" (status labels, overdue/on-time
 * calculation, version numbering) that several pages share.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------
// Output escaping — always escape user-controlled data before printing it.
// ---------------------------------------------------------------------
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// ---------------------------------------------------------------------
// CSRF protection
// ---------------------------------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || $token === '' || empty($_SESSION['csrf_token'])
        || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(400);
        echo 'Your session expired or the form was resubmitted. Please go back and try again.';
        exit;
    }
}

function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        echo 'Method not allowed.';
        exit;
    }
    verify_csrf();
}

// ---------------------------------------------------------------------
// Flash messages (one-time notices shown after a redirect)
// ---------------------------------------------------------------------
function flash(string $type, string $text): void
{
    $_SESSION['flash'] = ['type' => $type, 'text' => $text];
}

function get_flash(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

// ---------------------------------------------------------------------
// IDs & timestamps
// ---------------------------------------------------------------------
function now_iso(): string
{
    return (new DateTimeImmutable('now'))->format(DateTimeImmutable::ATOM);
}

function parse_dt(?string $iso): ?DateTimeImmutable
{
    if (!$iso) {
        return null;
    }
    try {
        return new DateTimeImmutable($iso);
    } catch (Exception $e) {
        return null;
    }
}

function format_dt(?string $iso, string $fmt = 'd M Y, h:i A'): string
{
    $dt = parse_dt($iso);
    return $dt ? $dt->format($fmt) : '—';
}

function format_date(?string $iso): string
{
    return format_dt($iso, 'd M Y');
}

/** Convert a <input type="datetime-local"> value ("2026-08-31T20:42") to ISO 8601. */
function local_input_to_iso(string $localValue): ?string
{
    $localValue = trim($localValue);
    if ($localValue === '') {
        return null;
    }
    try {
        $dt = new DateTimeImmutable($localValue);
        return $dt->format(DateTimeImmutable::ATOM);
    } catch (Exception $e) {
        return null;
    }
}

/** Inverse of local_input_to_iso(), for pre-filling edit forms. */
function iso_to_local_input(?string $iso): string
{
    $dt = parse_dt($iso);
    return $dt ? $dt->format('Y-m-d\TH:i') : '';
}

// ---------------------------------------------------------------------
// Video status — single source of truth for labels/colors used everywhere.
// ---------------------------------------------------------------------
const STATUS_ASSIGNED = 'assigned';
const STATUS_IN_REVIEW = 'in_review';
const STATUS_CHANGES_REQUESTED = 'changes_requested';
const STATUS_FINAL = 'final';

function status_label(string $status): string
{
    return [
        STATUS_ASSIGNED => 'Assigned',
        STATUS_IN_REVIEW => 'In Review',
        STATUS_CHANGES_REQUESTED => 'Changes Requested',
        STATUS_FINAL => 'Final',
    ][$status] ?? ucfirst($status);
}

function status_badge_class(string $status): string
{
    return [
        STATUS_ASSIGNED => 'badge badge-assigned',
        STATUS_IN_REVIEW => 'badge badge-review',
        STATUS_CHANGES_REQUESTED => 'badge badge-changes',
        STATUS_FINAL => 'badge badge-final',
    ][$status] ?? 'badge';
}

function is_overdue(array $video): bool
{
    if (($video['status'] ?? '') === STATUS_FINAL) {
        return false;
    }
    $deadline = parse_dt($video['deadline_at'] ?? null);
    if (!$deadline) {
        return false;
    }
    return $deadline < new DateTimeImmutable('now');
}

/** true = on time, false = late, null = not yet finalized. */
function is_on_time(array $video): ?bool
{
    if (($video['status'] ?? '') !== STATUS_FINAL || empty($video['finalized_at'])) {
        return null;
    }
    $deadline = parse_dt($video['deadline_at'] ?? null);
    $finalized = parse_dt($video['finalized_at'] ?? null);
    if (!$deadline || !$finalized) {
        return null;
    }
    return $finalized <= $deadline;
}

// ---------------------------------------------------------------------
// Versions
// ---------------------------------------------------------------------

/** All versions for a video, oldest first. */
function versions_for_video(string $videoId): array
{
    $versions = array_values(array_filter(
        Storage::versions()->all(),
        fn ($v) => ($v['video_id'] ?? null) === $videoId
    ));
    usort($versions, fn ($a, $b) => ($a['version_number'] ?? 0) <=> ($b['version_number'] ?? 0));
    return $versions;
}

function latest_version(string $videoId): ?array
{
    $versions = versions_for_video($videoId);
    return $versions ? end($versions) : null;
}

function next_version_number(string $videoId): int
{
    return count(versions_for_video($videoId)) + 1;
}

// ---------------------------------------------------------------------
// Lookups
// ---------------------------------------------------------------------
function find_by_id(array $rows, ?string $id): ?array
{
    if ($id === null) {
        return null;
    }
    foreach ($rows as $row) {
        if (($row['id'] ?? null) === $id) {
            return $row;
        }
    }
    return null;
}

function index_by_id(array $rows): array
{
    $out = [];
    foreach ($rows as $row) {
        if (isset($row['id'])) {
            $out[$row['id']] = $row;
        }
    }
    return $out;
}

// ---------------------------------------------------------------------
// Validation helpers
// ---------------------------------------------------------------------
function trimmed(string $key, array $source): string
{
    return trim((string) ($source[$key] ?? ''));
}

function is_valid_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function is_valid_url(string $url): bool
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true);
}
