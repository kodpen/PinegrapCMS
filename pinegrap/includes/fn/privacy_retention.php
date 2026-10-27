<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * How long the audit trail and the consent log keep their lines, and the
 * daily pass of the general job (job.php) that removes older ones.
 *
 * The audit trail keeps its lines for the years set on the Security screen
 * (config.audit_log_retention_years, 2026.11.0, 11.0.47; 0 keeps every
 * line). The consent log keeps its history for three years, but never the
 * newest line of a person's channel and purpose: that line is the answer in
 * force and its proof, however old it is. A cookie answer without a person
 * proves nothing about anybody and goes with the rest.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The bounds: the audit trail's default and allowed years, the consent
 * log's fixed years, and how many lines one statement removes.
 *
 * @return array  audit_default, audit_min, audit_max, consent_years, batch
 */
function pg_privacy_retention_limits()
{
    return array('audit_default' => 2, 'audit_min' => 1, 'audit_max' => 10, 'consent_years' => 3, 'batch' => 1000);
}

/**
 * The audit trail's years as typed on the Security screen: 0 (keep every
 * line) or a whole number within the bounds; null for anything else.
 *
 * @param mixed $input
 * @return int|null
 */
function pg_audit_retention_years_from_input($input)
{
    $text = is_scalar($input) ? trim((string) $input) : '';
    $limits = pg_privacy_retention_limits();

    if (($text === '') || !ctype_digit($text)) {
        return null;
    }

    $years = (int) $text;

    return (($years === 0) || (($years >= $limits['audit_min']) && ($years <= $limits['audit_max']))) ? $years : null;
}

/**
 * The audit trail's years as stored; the default before the step has run,
 * the nearest bound for a value outside them.
 *
 * @return int  0 keeps every line
 */
function pg_audit_retention_years()
{
    $limits = pg_privacy_retention_limits();

    if (!pg_schema_has('config', 'audit_log_retention_years')) {
        return $limits['audit_default'];
    }

    $years = (int) db_value("SELECT audit_log_retention_years FROM config");

    return ($years <= 0) ? 0 : max($limits['audit_min'], min($limits['audit_max'], $years));
}

/**
 * The moment before which lines go: the same day and time the given number
 * of years ago, in the site's time zone.
 *
 * @param int $years
 * @param int $now
 * @return int  0 when nothing goes
 */
function pg_privacy_retention_cutoff($years, $now)
{
    $years = (int) $years;

    if ($years <= 0) {
        return 0;
    }

    $cutoff = strtotime('-' . $years . ' years', (int) $now);

    return ($cutoff === false) ? 0 : (int) $cutoff;
}

/**
 * Where the daily pass keeps when it last went through.
 *
 * @return string
 */
function pg_privacy_retention_state_file()
{
    return PG_FUNCTIONS_DIR . '/data/temp/privacy_retention.json';
}

/**
 * Whether the daily pass is owed: a table to look at and no pass through in
 * the last day.
 *
 * @return bool
 */
function pg_privacy_retention_due()
{
    if (!pg_audit_ready() && !pg_consent_log_ready()) {
        return false;
    }

    $state = @json_decode((string) @file_get_contents(pg_privacy_retention_state_file()), true);
    $done = (is_array($state) && isset($state['done_at'])) ? (int) $state['done_at'] : 0;

    return $done < (time() - 86400);
}

/**
 * The SELECT of the consent lines that may go: older than the cutoff, and
 * either without a person or followed by a newer line of the same person,
 * channel and purpose. The newest line of each stays. Pure.
 *
 * @param int $cutoff
 * @param int $batch
 * @return string
 */
function pg_consent_log_trim_query($cutoff, $batch)
{
    return "SELECT c.id FROM consent_log c
        WHERE (c.created_at < '" . (int) $cutoff . "')
        AND (
            ((c.person_token = '') AND (c.user_id = '0') AND (c.contact_id = '0'))
            OR EXISTS (
                SELECT 1 FROM consent_log n
                WHERE (n.person_token = c.person_token)
                AND (n.user_id = c.user_id)
                AND (n.contact_id = c.contact_id)
                AND (n.channel = c.channel)
                AND (n.purpose = c.purpose)
                AND (n.id > c.id)
            )
        )
        ORDER BY c.id
        LIMIT " . max(1, (int) $batch);
}

/**
 * The daily pass: audit lines past their years, then consent history past
 * three years, a batch at a time within the time budget. The state file is
 * written only once both are through, so a large backlog goes on the next
 * ticks. A statement that fails ends the pass; nothing it was asked for is
 * lost, it is asked again.
 *
 * @param int $seconds
 * @return array  audit, consent (lines removed), through (bool)
 */
function pg_privacy_retention_run($seconds = 5)
{
    $limits = pg_privacy_retention_limits();
    $deadline = microtime(true) + max(1, (int) $seconds);
    $now = time();
    $result = array('audit' => 0, 'consent' => 0, 'through' => false);

    // The audit trail: by its time index, a batch per statement.
    $audit_cutoff = pg_audit_ready() ? pg_privacy_retention_cutoff(pg_audit_retention_years(), $now) : 0;
    $audit_through = ($audit_cutoff === 0);

    while (!$audit_through && (microtime(true) < $deadline)) {
        if (@mysqli_query(db::$con, "DELETE FROM audit_log WHERE created_at < '" . $audit_cutoff . "' LIMIT " . $limits['batch']) === false) {
            return $result;
        }

        $affected = max(0, (int) mysqli_affected_rows(db::$con));
        $result['audit'] += $affected;
        $audit_through = ($affected < $limits['batch']);
    }

    // The consent log: the ids that may go, then those ids.
    $consent_cutoff = pg_consent_log_ready() ? pg_privacy_retention_cutoff($limits['consent_years'], $now) : 0;
    $consent_through = ($consent_cutoff === 0);

    while ($audit_through && !$consent_through && (microtime(true) < $deadline)) {
        $ids = db_values(pg_consent_log_trim_query($consent_cutoff, $limits['batch']));

        if ($ids && (@mysqli_query(db::$con, "DELETE FROM consent_log WHERE id IN ('" . implode("', '", array_map('intval', $ids)) . "')") === false)) {
            return $result;
        }

        $result['consent'] += $ids ? max(0, (int) mysqli_affected_rows(db::$con)) : 0;
        $consent_through = (count($ids) < $limits['batch']);
    }

    if ($audit_through && $consent_through) {
        $result['through'] = true;
        @file_put_contents(pg_privacy_retention_state_file(), json_encode(array('done_at' => $now)), LOCK_EX);
    }

    return $result;
}
