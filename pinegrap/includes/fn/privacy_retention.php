<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * How long the audit trail and the consent log keep their lines, and the
 * daily pass of the general job (job.php) that removes older ones; and the
 * retention rules of the Privacy centre for contacts, form submissions,
 * carts and orders, which run at night through the erasure's own entries.
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
 * @return array  audit_default, audit_min, audit_max, consent_years,
 *                record_years (the erasure and rule lines' keep), batch
 */
function pg_privacy_retention_limits()
{
    return array('audit_default' => 2, 'audit_min' => 1, 'audit_max' => 10, 'consent_years' => 3, 'record_years' => 3, 'batch' => 1000);
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
 * The moment before which an erasure's or a rule run's audit line may go:
 * those lines are the destruction records and stay at least three years,
 * whatever the Security screen keeps.
 *
 * @param int $now
 * @return int
 */
function pg_privacy_rule_record_cutoff($now)
{
    return pg_privacy_retention_cutoff(pg_privacy_retention_limits()['record_years'], $now);
}

/**
 * The DELETE of one batch of audit lines past the cutoff, the destruction
 * records (an erasure: action erase on a person; a rule run: privacy_rule)
 * only once they are past their own keep as well. Pure.
 *
 * @param int $cutoff  from the Security screen's years
 * @param int $keep    pg_privacy_rule_record_cutoff()
 * @param int $batch
 * @return string
 */
function pg_audit_retention_delete_query($cutoff, $keep, $batch)
{
    return "DELETE FROM audit_log
        WHERE (created_at < '" . (int) $cutoff . "')
        AND (
            (created_at < '" . (int) $keep . "')
            OR NOT ((entity_type = 'privacy_rule') OR ((entity_type = 'person') AND (action = 'erase')))
        )
        LIMIT " . max(1, (int) $batch);
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
 * channel and purpose. Newer is the order pg_consent_log_current() reads
 * the answer in force by: time, then id, since a line may be written with
 * an earlier time than one already stored (an IYS pull stamps IYS's own
 * time). The newest line of each stays. Pure.
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
                AND ((n.created_at > c.created_at) OR ((n.created_at = c.created_at) AND (n.id > c.id)))
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

    // The audit trail: by its time index, a batch per statement; erasures
    // and rule runs stay at least three years.
    $audit_cutoff = pg_audit_ready() ? pg_privacy_retention_cutoff(pg_audit_retention_years(), $now) : 0;
    $audit_through = ($audit_cutoff === 0);

    while (!$audit_through && (microtime(true) < $deadline)) {
        if (db_q(pg_audit_retention_delete_query($audit_cutoff, pg_privacy_rule_record_cutoff($now), $limits['batch'])) === false) {
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

        if ($ids && (db_q("DELETE FROM consent_log WHERE id IN (" . implode(', ', array_fill(0, count($ids), '?')) . ")", array_map('strval', array_map('intval', $ids))) === false)) {
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

/* ---------------------------------------------------------------------------
   Retention rules for personal records: passive contacts, form submissions,
   incomplete carts and orders. Each is off until switched on; its first run
   is a dry run that waits for a person's yes. The pass removes or
   anonymises through pg_privacy_erase()'s entries and actions, never a list
   of its own, and skips a person something still holds (P8).
   --------------------------------------------------------------------------- */

/**
 * The rules: the unit and bounds of each period, its suggested default
 * (null: never until the shop types one) and the erase entries it may reach
 * (null: every entry, as erasing the person does). Pure.
 *
 * @return array  key => unit (years | days), default, min, max, table, entries
 */
function pg_privacy_rules_catalogue()
{
    return array(
        'contacts' => array('unit' => 'years', 'default' => 3, 'min' => 1, 'max' => 20, 'table' => 'contacts', 'entries' => null),
        'forms'    => array('unit' => 'years', 'default' => 2, 'min' => 1, 'max' => 20, 'table' => 'forms', 'entries' => array('record_revisions', 'files', 'form_data', 'form_signatures', 'forms')),
        // The cart row stays: cart recovery counts open carts from orders.
        'carts'    => array('unit' => 'days', 'default' => 90, 'min' => 31, 'max' => 3650, 'table' => 'orders', 'entries' => array('ship_tos', 'orders')),
        'orders'   => array('unit' => 'years', 'default' => null, 'min' => 1, 'max' => 30, 'table' => 'orders', 'entries' => array('ship_tos', 'order_returns', 'shipment_labels', 'orders')),
    );
}

/**
 * The days between two passes of a rule (at most six months), and how the
 * pass works through its rows. Pure.
 *
 * @return array  default, min, max (days), slice (rows a query), check (rows
 *                a dry run looks at), sample, open_order_days, night_until
 */
function pg_privacy_rules_interval_limits()
{
    return array('default' => 1, 'min' => 1, 'max' => 183, 'slice' => 25, 'check' => 200, 'sample' => 5, 'open_order_days' => 60, 'night_until' => 6);
}

/**
 * The erase entries no rule reaches: invoices, ERP records and the order's
 * legal documents stay whatever a rule says. Pure.
 *
 * @return string[]
 */
function pg_privacy_rules_never_entries()
{
    return array('erp_accounts', 'erp_invoices', 'erp_quotes', 'erp_waybills', 'order_documents');
}

/**
 * The erase entries a rule runs, in the erasure's own order.
 *
 * @param string $key
 * @return string[]
 */
function pg_privacy_rule_entries($key)
{
    $catalogue = pg_privacy_rules_catalogue();
    $all = array_keys(pg_privacy_erase_tables());

    if (!isset($catalogue[$key])) {
        return array();
    }

    $list = ($catalogue[$key]['entries'] === null) ? $all : array_intersect($all, $catalogue[$key]['entries']);

    return array_values(array_diff($list, pg_privacy_rules_never_entries()));
}

/**
 * The columns a cart loses: the orders entry's own field set (personal
 * fields, the contact and user links), so there is no second list.
 *
 * @return array  column => value
 */
function pg_privacy_rule_cart_fields()
{
    $tables = pg_privacy_erase_tables();

    return $tables['orders']['fields'];
}

/**
 * The names the Privacy centre and the audit trail show: each rule, each
 * skip and each refusal of a save.
 *
 * @return array  rules, skips, errors (key => text)
 */
function pg_privacy_rules_labels()
{
    $interval = pg_privacy_rules_interval_limits();

    return array(
        'rules' => array(
            'contacts' => lang('Passive contacts: no order, form, sign-in or consent answer for the period'),
            'forms'    => lang('Form submissions'),
            'carts'    => lang('Incomplete carts: personal fields, contact and account links'),
            'orders'   => lang('Orders: personal fields anonymised'),
        ),
        'skips' => array(
            'staff'       => lang('Staff account'),
            'open_order'  => lang('Open order'),
            'open_return' => lang('Open return'),
            'balance'     => lang('Gift card or store credit balance'),
            'booking'     => lang('Booking ahead'),
            'active'      => lang('Active within the period'),
        ),
        'errors' => array(
            'period'   => lang('Enter a period within the bounds shown.'),
            'pages'    => lang('A form\'s period is outside the bounds.'),
            'interval' => lang(array('string' => 'Run the rules every {var:1} to {var:2} days.', 'vars' => array($interval['min'], $interval['max']))),
        ),
    );
}

/**
 * Stored rules made whole: every rule present, periods within bounds, off
 * and unapproved by default. Pure.
 *
 * @param mixed $stored  the decoded settings text
 * @return array  key => on, period, approved_at, approved_by (forms: pages);
 *                interval
 */
function pg_privacy_rules_normalise($stored)
{
    $stored = is_array($stored) ? $stored : array();
    $limits = pg_privacy_rules_interval_limits();
    $rules = array();

    foreach (pg_privacy_rules_catalogue() as $key => $spec) {
        $row = (isset($stored[$key]) && is_array($stored[$key])) ? $stored[$key] : array();
        $period = array_key_exists('period', $row) ? $row['period'] : $spec['default'];
        $period = (($period === null) || ($period === '')) ? null : max($spec['min'], min($spec['max'], (int) $period));

        $rules[$key] = array(
            'on'          => !empty($row['on']),
            'period'      => $period,
            'approved_at' => isset($row['approved_at']) ? max(0, (int) $row['approved_at']) : 0,
            'approved_by' => isset($row['approved_by']) ? max(0, (int) $row['approved_by']) : 0,
        );

        if ($key === 'forms') {
            $rules[$key]['pages'] = array();

            foreach ((isset($row['pages']) && is_array($row['pages'])) ? $row['pages'] : array() as $page => $years) {
                if (((int) $page > 0) && is_numeric($years)) {
                    $rules[$key]['pages'][(int) $page] = max(0, min($spec['max'], (int) $years));
                }
            }
        }
    }

    $interval = isset($stored['interval']) ? (int) $stored['interval'] : $limits['default'];
    $rules['interval'] = max($limits['min'], min($limits['max'], $interval));

    return $rules;
}

/**
 * A whole number typed within the bounds; '' as null when allowed. Pure.
 *
 * @param mixed $input
 * @param int   $min
 * @param int   $max
 * @param bool  $empty  whether '' is taken (as null)
 * @return array  ok (bool), value (int|null)
 */
function _pg_privacy_rules_number($input, $min, $max, $empty)
{
    $text = is_scalar($input) ? trim((string) $input) : '';

    if ($text === '') {
        return array('ok' => $empty, 'value' => null);
    }

    if (!ctype_digit($text) || ((int) $text < $min) || ((int) $text > $max)) {
        return array('ok' => false, 'value' => null);
    }

    return array('ok' => true, 'value' => (int) $text);
}

/**
 * The rules as posted, checked against the stored ones. A rule switched on,
 * switched off or given another period (or, for forms, other per-form
 * periods) loses its yes: its next run is a dry run again. Pure.
 *
 * @param array $input    key => on, period (forms: pages => page id => years); interval
 * @param array $current  pg_privacy_rules_normalise()
 * @return array  rules, errors (key => period | pages | interval)
 */
function pg_privacy_rules_from_input(array $input, array $current)
{
    $limits = pg_privacy_rules_interval_limits();
    $rules = $current;
    $errors = array();

    foreach (pg_privacy_rules_catalogue() as $key => $spec) {
        $in = (isset($input[$key]) && is_array($input[$key])) ? $input[$key] : array();
        $on = !empty($in['on']);
        $period = $current[$key]['period'];

        if (array_key_exists('period', $in)) {
            $number = _pg_privacy_rules_number($in['period'], $spec['min'], $spec['max'], $spec['default'] === null);

            if (!$number['ok']) {
                $errors[$key] = 'period';
                continue;
            }

            $period = $number['value'];
        }

        if ($on && ($period === null)) {
            $errors[$key] = 'period';
            continue;
        }

        $changed = ($on !== $current[$key]['on']) || ($period !== $current[$key]['period']);

        if ($key === 'forms') {
            $pages = array();

            foreach ((isset($in['pages']) && is_array($in['pages'])) ? $in['pages'] : array() as $page => $years) {
                if (!ctype_digit((string) $page) || ((int) $page <= 0)) {
                    continue;
                }

                $number = _pg_privacy_rules_number($years, 0, $spec['max'], true);

                if (!$number['ok']) {
                    $errors[$key] = 'pages';
                    continue 2;
                }

                if ($number['value'] !== null) {
                    $pages[(int) $page] = $number['value'];
                }
            }

            ksort($pages);
            $changed = $changed || ($pages !== $current[$key]['pages']);
            $rules[$key]['pages'] = $pages;
        }

        $rules[$key]['on'] = $on;
        $rules[$key]['period'] = $period;

        if ($changed || !$on) {
            $rules[$key]['approved_at'] = 0;
            $rules[$key]['approved_by'] = 0;
        }
    }

    if (array_key_exists('interval', $input)) {
        $number = _pg_privacy_rules_number($input['interval'], $limits['min'], $limits['max'], false);

        if ($number['ok']) {
            $rules['interval'] = $number['value'];
        } else {
            $errors['interval'] = 'interval';
        }
    }

    return array('rules' => $rules, 'errors' => $errors);
}

/**
 * Whether an operator may switch a rule, change its period or give the first
 * run's yes: the role that may save Site Settings (pg_settings_reader():
 * role <= 2). Anybody who opens the Privacy centre reads the rules.
 *
 * @param array $operator  role, id
 * @return bool
 */
function pg_privacy_rules_may_change(array $operator)
{
    return isset($operator['role']) && is_numeric($operator['role']) && ((int) $operator['role'] <= 2);
}

/**
 * A settings text read now, not from pg_config_text()'s per-request cache, so
 * a save and the next read in one request agree.
 *
 * @param string $name
 * @return array  the decoded value, empty when not set
 */
function _pg_privacy_rules_read($name)
{
    if (!pg_schema_has('config_text')) {
        return array();
    }

    $value = json_decode((string) db_value("SELECT value FROM config_text WHERE name = '" . e($name) . "'"), true);

    return is_array($value) ? $value : array();
}

/**
 * The stored rules (config_text privacy_rules).
 *
 * @return array  see pg_privacy_rules_normalise()
 */
function pg_privacy_rules()
{
    return pg_privacy_rules_normalise(_pg_privacy_rules_read('privacy_rules'));
}

/**
 * What a rule change's audit line shows: switches and periods, not the yes.
 * Pure.
 *
 * @param array $rules
 * @return array
 */
function pg_privacy_rules_audit_view(array $rules)
{
    $view = array('interval' => (int) $rules['interval']);

    foreach (array_keys(pg_privacy_rules_catalogue()) as $key) {
        $view[$key . '_on'] = $rules[$key]['on'] ? 1 : 0;
        $view[$key . '_period'] = ($rules[$key]['period'] === null) ? '' : (int) $rules[$key]['period'];
    }

    $view['forms_pages'] = json_encode($rules['forms']['pages']);

    return $view;
}

/**
 * Save the rules from the Privacy centre.
 *
 * @param array $input     see pg_privacy_rules_from_input()
 * @param array $operator  role, id
 * @return array  status (refused | invalid | failed | ok), errors, rules
 */
function pg_privacy_rules_save(array $input, array $operator)
{
    if (!pg_privacy_rules_may_change($operator)) {
        return array('status' => 'refused', 'errors' => array(), 'rules' => array());
    }

    $current = pg_privacy_rules();
    $out = pg_privacy_rules_from_input($input, $current);

    if ($out['errors']) {
        return array('status' => 'invalid', 'errors' => $out['errors'], 'rules' => $current);
    }

    if (!pg_config_text_save('privacy_rules', json_encode($out['rules']))) {
        return array('status' => 'failed', 'errors' => array(), 'rules' => $current);
    }

    pg_audit('update', 'privacy_rule', 0, pg_privacy_rules_audit_view($current), pg_privacy_rules_audit_view($out['rules']), '');

    return array('status' => 'ok', 'errors' => array(), 'rules' => $out['rules']);
}

/**
 * The yes after a rule's dry run: from the next night on, the rule runs.
 *
 * @param string   $key
 * @param array    $operator  role, id
 * @param int|null $now
 * @return string  refused | off | failed | ok
 */
function pg_privacy_rule_approve($key, array $operator, $now = null)
{
    $now = ($now === null) ? time() : (int) $now;

    if (!pg_privacy_rules_may_change($operator)) {
        return 'refused';
    }

    $rules = pg_privacy_rules();

    if (!isset(pg_privacy_rules_catalogue()[$key]) || !$rules[$key]['on'] || ($rules[$key]['period'] === null)) {
        return 'off';
    }

    $rules[$key]['approved_at'] = $now;
    $rules[$key]['approved_by'] = (int) ($operator['id'] ?? 0);

    if (!pg_config_text_save('privacy_rules', json_encode($rules))) {
        return 'failed';
    }

    pg_audit('approve', 'privacy_rule', 0, array(), array('rule' => $key, 'period' => $rules[$key]['period']), $key);

    return 'ok';
}

/**
 * The moment before which a rule's rows are due. Pure.
 *
 * @param string   $key
 * @param int|null $period
 * @param int      $now
 * @return int  0 when nothing is due
 */
function pg_privacy_rule_cutoff($key, $period, $now)
{
    $catalogue = pg_privacy_rules_catalogue();

    if (!isset($catalogue[$key]) || ($period === null) || ((int) $period <= 0)) {
        return 0;
    }

    if ($catalogue[$key]['unit'] === 'days') {
        return (int) $now - ((int) $period * 86400);
    }

    return pg_privacy_retention_cutoff((int) $period, $now);
}

/**
 * The condition that a row still holds personal data: one of the field
 * set's columns differs from what anonymising writes. A name placeholder is
 * no sign (any value may stand there); an address column is personal until
 * it holds a person token. Pure.
 *
 * @param array $fields  column => value (an erase entry's fields)
 * @return string  '' for no field
 */
function pg_privacy_rule_pending_where(array $fields)
{
    $conditions = array();

    foreach ($fields as $column => $value) {
        $quoted = '`' . str_replace('`', '', (string) $column) . '`';

        if ($value === '{name}') {
            continue;
        }

        if ($value === '{token}') {
            $conditions[] = "((" . $quoted . " IS NOT NULL) AND (" . $quoted . " <> '') AND (" . $quoted . " NOT REGEXP '^[0-9a-f]{64}$'))";
        } elseif (is_int($value)) {
            $conditions[] = "(" . $quoted . " <> " . $value . ")";
        } else {
            $conditions[] = "((" . $quoted . " IS NOT NULL) AND (" . $quoted . " <> ''))";
        }
    }

    return $conditions ? "(" . implode(' OR ', $conditions) . ")" : '';
}

/**
 * The rows a rule looks at, before its skips. Pure.
 *
 * contacts: unchanged for the period, with no order, form submission or
 *   sign-in since (the pass checks the whole person again, consent answers
 *   included; there is no per-contact record of a campaign click).
 * forms: submitted before the period, or before the period of a form that
 *   says otherwise (0: that form is kept).
 * carts: incomplete orders untouched for the period, still personal.
 * orders: placed before the period, still personal.
 *
 * @param string $key
 * @param array  $rule  one rule of pg_privacy_rules_normalise()
 * @param int    $now
 * @param array  $has   pending (pg_privacy_rule_pending_where() of the field set, for carts and orders)
 * @return string  '' when the rule reaches nothing
 */
function pg_privacy_rule_candidate_where($key, array $rule, $now, array $has)
{
    $cutoff = pg_privacy_rule_cutoff($key, $rule['period'], $now);

    if ($cutoff <= 0) {
        return '';
    }

    $at = "'" . (int) $cutoff . "'";

    if ($key === 'contacts') {
        return "(COALESCE(contacts.timestamp, 0) < " . $at . ")"
            . " AND NOT EXISTS (SELECT 1 FROM orders o WHERE (o.contact_id = contacts.id) AND (o.order_date >= " . $at . "))"
            . " AND NOT EXISTS (SELECT 1 FROM forms f WHERE (f.contact_id = contacts.id) AND (f.submitted_timestamp >= " . $at . "))"
            . " AND NOT EXISTS (SELECT 1 FROM user u WHERE (u.user_contact = contacts.id) AND (u.user_online_timestamp >= " . $at . "))";
    }

    if ($key === 'forms') {
        $pages = isset($rule['pages']) ? $rule['pages'] : array();
        $own = "(submitted_timestamp < " . $at . ")";

        if ($pages) {
            $own = "(" . $own . " AND (page_id NOT IN ('" . implode("', '", array_map('intval', array_keys($pages))) . "')))";
        }

        $parts = array($own);

        foreach ($pages as $page => $years) {
            $page_cutoff = pg_privacy_retention_cutoff((int) $years, $now);

            if ($page_cutoff > 0) {
                $parts[] = "((page_id = '" . (int) $page . "') AND (submitted_timestamp < '" . $page_cutoff . "'))";
            }
        }

        return "(" . implode(' OR ', $parts) . ")";
    }

    $pending = isset($has['pending']) ? (string) $has['pending'] : '';

    if ($pending === '') {
        return '';
    }

    if ($key === 'carts') {
        return "(status = 'incomplete') AND (GREATEST(COALESCE(last_modified_timestamp, 0), COALESCE(order_date, 0)) < " . $at . ") AND " . $pending;
    }

    if ($key === 'orders') {
        return "(status IN ('complete', 'exported', 'cancelled')) AND (order_date > 0) AND (order_date < " . $at . ") AND " . $pending;
    }

    return '';
}

/**
 * Why a person is left alone, in the order they are asked; invoices, ERP
 * records and legal documents are left out by entry
 * (pg_privacy_rules_never_entries()). Pure.
 *
 * @return string[]
 */
function pg_privacy_rule_skip_reasons()
{
    return array('staff', 'open_order', 'open_return', 'balance', 'booking', 'active');
}

/**
 * The first skip a person's facts call for. Pure.
 *
 * @param array $facts  reason => count or bool
 * @return string  '' when nothing holds the person
 */
function pg_privacy_rule_skip_reason(array $facts)
{
    foreach (pg_privacy_rule_skip_reasons() as $reason) {
        if (!empty($facts[$reason])) {
            return $reason;
        }
    }

    return '';
}

/**
 * Whether a user row is staff: a panel role, or a member given a right to
 * manage something. An unknown role counts as staff, so in doubt the person
 * stays. Pure.
 *
 * @param array $row  a user row
 * @return bool
 */
function pg_privacy_rule_is_staff(array $row)
{
    if (!isset($row['user_role']) || !is_numeric($row['user_role']) || ((int) $row['user_role'] < 3)) {
        return true;
    }

    foreach ($row as $column => $value) {
        if ((strpos((string) $column, 'user_manage_') === 0) && ($value === 'yes')) {
            return true;
        }

        if ((strpos((string) $column, 'manage_') === 0) && is_numeric($value) && ((int) $value > 0)) {
            return true;
        }
    }

    return false;
}

/**
 * The orders still open: placed and paid within the last 60 days (delivery
 * and the withdrawal period may still run), or with a refund not settled.
 * Pure.
 *
 * @param int  $now
 * @param bool $has_refund  whether orders.refund_status exists
 * @return string
 */
function pg_privacy_rule_open_order_where($now, $has_refund)
{
    $days = pg_privacy_rules_interval_limits()['open_order_days'];
    $open = array("(order_date >= '" . ((int) $now - $days * 86400) . "')");

    if ($has_refund) {
        $open[] = "(refund_status IN ('pending', 'failed', 'manual_required'))";
    }

    return "(status IN ('complete', 'exported')) AND (" . implode(' OR ', $open) . ")";
}

/**
 * Whether a rule's pass is owed: switched on, with a period and its yes, and
 * either a pass under way or the interval gone since the last one. Pure.
 *
 * @param array $rule
 * @param array $state     last_run_at, started_at
 * @param int   $interval  days
 * @param int   $now
 * @return bool
 */
function pg_privacy_rule_due(array $rule, array $state, $interval, $now)
{
    if (empty($rule['on']) || ($rule['period'] === null) || empty($rule['approved_at'])) {
        return false;
    }

    if (!empty($state['started_at'])) {
        return true;
    }

    return (int) ($state['last_run_at'] ?? 0) <= ((int) $now - max(1, (int) $interval) * 86400);
}

/**
 * Whether it is night in the site's time zone: the rules pass runs between
 * midnight and six. Pure.
 *
 * @param int $now
 * @return bool
 */
function pg_privacy_rules_night($now)
{
    return (int) date('G', (int) $now) < pg_privacy_rules_interval_limits()['night_until'];
}

/**
 * Empty counts of a run. Pure.
 *
 * @return array  checked, changed, skipped, deleted, anonymised
 */
function pg_privacy_rule_counts()
{
    return array('checked' => 0, 'changed' => 0, 'skipped' => array(), 'deleted' => array(), 'anonymised' => array());
}

/**
 * Counts with one row's outcome added. Pure.
 *
 * @param array $counts
 * @param array $one  skip (reason or ''), tables (pg_privacy_erase_run())
 * @return array
 */
function pg_privacy_rule_counts_add(array $counts, array $one)
{
    $counts['checked']++;

    if ((string) $one['skip'] !== '') {
        $counts['skipped'][$one['skip']] = (isset($counts['skipped'][$one['skip']]) ? $counts['skipped'][$one['skip']] : 0) + 1;

        return $counts;
    }

    $counts['changed'] += $one['tables'] ? 1 : 0;

    foreach ($one['tables'] as $entry => $done) {
        $side = ($done['action'] === 'delete') ? 'deleted' : 'anonymised';
        $counts[$side][$entry] = (isset($counts[$side][$entry]) ? $counts[$side][$entry] : 0) + (int) $done['rows'];
    }

    return $counts;
}

/**
 * The after side of a run's audit line: the rule, its period and the
 * counts; no person. Pure.
 *
 * @param string $key
 * @param array  $rule
 * @param array  $counts
 * @return array
 */
function pg_privacy_rule_audit_entry($key, array $rule, array $counts)
{
    $catalogue = pg_privacy_rules_catalogue();

    return array(
        'rule'       => (string) $key,
        'period'     => $rule['period'],
        'unit'       => isset($catalogue[$key]) ? $catalogue[$key]['unit'] : '',
        'checked'    => (int) $counts['checked'],
        'changed'    => (int) $counts['changed'],
        'skipped'    => $counts['skipped'],
        'deleted'    => $counts['deleted'],
        'anonymised' => $counts['anonymised'],
    );
}

/**
 * The field set's pending condition for the orders table, the columns this
 * site has.
 *
 * @return string
 */
function _pg_privacy_rule_orders_pending()
{
    $fields = array_filter(pg_privacy_rule_cart_fields(), function ($column) {
        return pg_schema_has('orders', $column);
    }, ARRAY_FILTER_USE_KEY);

    return pg_privacy_rule_pending_where($fields);
}

/**
 * A rule's candidate condition on this site.
 *
 * @param string $key
 * @param array  $rule
 * @param int    $now
 * @return string
 */
function _pg_privacy_rule_where($key, array $rule, $now)
{
    $catalogue = pg_privacy_rules_catalogue();

    if (!isset($catalogue[$key]) || !pg_schema_has($catalogue[$key]['table'])) {
        return '';
    }

    $has = in_array($key, array('carts', 'orders'), true) ? array('pending' => _pg_privacy_rule_orders_pending()) : array();

    return pg_privacy_rule_candidate_where($key, $rule, $now, $has);
}

/**
 * What holds a person back from a rule (pg_privacy_rule_skip_reasons()).
 *
 * @param array    $found   pg_privacy_export_find()
 * @param array    $scope   the person's erase scope: user_ids, contact_ids,
 *                          emails, tokens, order_ids, form_ids
 * @param int      $now
 * @param int|null $active  the cutoff activity since which keeps a passive
 *                          contact; null for the other rules
 * @return array  reason => count or bool
 */
function pg_privacy_rule_facts(array $found, array $scope, $now, $active = null)
{
    $facts = array();
    $users = $found['users'];

    if ($scope['emails']) {
        $users += _pg_privacy_export_users(_pg_privacy_export_in('user_email', $scope['emails']));
    }

    foreach ($users as $row) {
        $facts['staff'] = !empty($facts['staff']) || pg_privacy_rule_is_staff($row);
    }

    $orders = _pg_privacy_export_in('id', $scope['order_ids']);

    if ($orders !== '') {
        $facts['open_order'] = (int) db_value("SELECT COUNT(*) FROM orders WHERE " . $orders . " AND " . pg_privacy_rule_open_order_where($now, pg_schema_has('orders', 'refund_status')));

        if (pg_schema_has('order_returns', 'status') && function_exists('pg_return_open_statuses')) {
            $facts['open_return'] = (int) db_value("SELECT COUNT(*) FROM order_returns WHERE " . _pg_privacy_export_in('order_id', $scope['order_ids']) . " AND " . _pg_privacy_export_in('status', pg_return_open_statuses()));
        }
    }

    $cards = _pg_privacy_export_any(array(
        pg_schema_has('gift_cards', 'user_id') ? _pg_privacy_export_in('user_id', $scope['user_ids']) : '',
        _pg_privacy_export_in('recipient_email_address', $scope['emails']),
        $orders ? _pg_privacy_export_in('order_id', $scope['order_ids']) : '',
    ));

    if (($cards !== '') && pg_schema_has('gift_cards', 'balance')) {
        $facts['balance'] = (int) db_value("SELECT COUNT(*) FROM gift_cards WHERE (balance > 0) AND " . $cards);
    }

    $bookings = _pg_privacy_export_any(array(_pg_privacy_export_in('user_id', $scope['user_ids']), _pg_privacy_export_in('email', $scope['emails'])));

    if (($bookings !== '') && pg_schema_has('bookings', 'start_at') && function_exists('pg_booking_erase_cancel_where')) {
        $facts['booking'] = (int) db_value("SELECT COUNT(*) FROM bookings WHERE (" . $bookings . ") AND " . pg_booking_erase_cancel_where($now));
    }

    if ($active !== null) {
        $facts['active'] = _pg_privacy_rule_active($found, $scope, (int) $active);
    }

    return $facts;
}

/**
 * Whether a person did anything since the cutoff: an order, a form
 * submission, a sign-in, a change to their contact, a consent answer.
 *
 * @param array $found
 * @param array $scope
 * @param int   $cutoff
 * @return bool
 */
function _pg_privacy_rule_active(array $found, array $scope, $cutoff)
{
    $at = "'" . (int) $cutoff . "'";

    foreach ($found['contacts'] as $row) {
        if ((int) ($row['timestamp'] ?? 0) >= $cutoff) {
            return true;
        }
    }

    foreach ($found['users'] as $row) {
        if ((int) ($row['user_online_timestamp'] ?? 0) >= $cutoff) {
            return true;
        }
    }

    $checks = array(
        array('orders', 'order_date', _pg_privacy_export_in('id', $scope['order_ids'])),
        array('forms', 'submitted_timestamp', _pg_privacy_export_in('id', $scope['form_ids'])),
    );

    if (pg_consent_log_ready()) {
        $checks[] = array('consent_log', 'created_at', _pg_privacy_export_any(array(
            _pg_privacy_export_in('person_token', $scope['tokens']),
            _pg_privacy_export_in('user_id', $scope['user_ids']),
            _pg_privacy_export_in('contact_id', $scope['contact_ids']),
        )));
    }

    foreach ($checks as $check) {
        if (($check[2] !== '') && db_value("SELECT 1 FROM " . $check[0] . " WHERE (" . $check[2] . ") AND (" . $check[1] . " >= " . $at . ") LIMIT 1")) {
            return true;
        }
    }

    return false;
}

/**
 * An erase scope holding only the given lists.
 *
 * @param array $lists  scope key => ids or values
 * @return array
 */
function _pg_privacy_rule_scope(array $lists)
{
    $scope = array();

    foreach (array('user_ids', 'contact_ids', 'emails', 'actors', 'tokens', 'phones', 'order_ids', 'form_ids', 'account_ids', 'conversation_ids', 'file_ids', 'return_ids', 'audit_ids', 'cell_ids', 'revision_form_ids', 'wtable_row_ids', 'wtable_bin_ids') as $key) {
        $scope[$key] = isset($lists[$key]) ? array_values($lists[$key]) : array();
    }

    return $scope;
}

/**
 * One row of a rule: whose it is, whether something holds them, and what
 * the rule does (or, in a dry run, would do) through the erase entries.
 *
 * @param string $key
 * @param int    $id      the row's id in the rule's table
 * @param int    $cutoff
 * @param int    $now
 * @param bool   $apply
 * @return array  skip (reason or ''), tables (pg_privacy_erase_run())
 */
function pg_privacy_rule_one($key, $id, $cutoff, $now, $apply)
{
    $id = (int) $id;
    $input = array();
    $lists = array();

    if ($key === 'contacts') {
        $input = array('contact_id' => $id);
    } elseif ($key === 'forms') {
        $row = db_item("SELECT user_id, contact_id, ip_address FROM forms WHERE id = '" . $id . "'");
        $row = is_array($row) ? $row : array();

        if ((int) ($row['contact_id'] ?? 0) > 0) {
            $input = array('contact_id' => (int) $row['contact_id']);
        } elseif (((int) ($row['user_id'] ?? 0) > 0) && ((int) ($row['ip_address'] ?? 0) !== 0)) {
            $input = array('user_id' => (int) $row['user_id']);
        }

        $files = db_values("SELECT file_id FROM form_data WHERE (file_id > 0) AND (form_id = '" . $id . "')");

        if (pg_schema_has('form_signatures', 'file_id')) {
            $files = array_merge($files, db_values("SELECT file_id FROM form_signatures WHERE (file_id > 0) AND (form_id = '" . $id . "')"));
        }

        $lists = array('form_ids' => array($id), 'file_ids' => _pg_privacy_export_ids($files), 'revision_form_ids' => pg_privacy_erase_revision_form_ids(array($id), array()));
    } else {
        $row = db_item("SELECT user_id, contact_id, billing_email_address" . (pg_schema_has('orders', 'type') ? ", type" : "") . " FROM orders WHERE id = '" . $id . "'");
        $row = is_array($row) ? $row : array();
        $input = array('contact_id' => (int) ($row['contact_id'] ?? 0), 'email' => (string) ($row['billing_email_address'] ?? ''));

        if (($row['type'] ?? '') !== 'local') {
            $input['user_id'] = (int) ($row['user_id'] ?? 0);
        }

        $lists = array('order_ids' => array($id));

        if (pg_schema_has('order_returns', 'order_id')) {
            $lists['return_ids'] = _pg_privacy_export_ids(db_values("SELECT id FROM order_returns WHERE order_id = '" . $id . "'"));
        }
    }

    $found = pg_privacy_export_find($input);
    $person = pg_privacy_export_is_empty($found['person']) ? _pg_privacy_rule_scope(array()) : pg_privacy_erase_scope($found);
    $facts = pg_privacy_rule_facts($found, $person, $now, ($key === 'contacts') ? $cutoff : null);
    $skip = pg_privacy_rule_skip_reason($facts);

    if ($skip !== '') {
        return array('skip' => $skip, 'tables' => array());
    }

    $scope = ($key === 'contacts') ? $person : _pg_privacy_rule_scope($lists);

    return array('skip' => '', 'tables' => pg_privacy_erase_run($scope, $apply, pg_privacy_rule_entries($key)));
}

/**
 * A rule's dry run: how many rows it looks at, and for the first of them
 * what it would do and who it would skip, with a few ids to look at.
 * Nothing is written.
 *
 * @param string   $key
 * @param array    $rule  null for the stored rule
 * @param int|null $now
 * @return array  total, counts (pg_privacy_rule_counts()), sample (ids)
 */
function pg_privacy_rule_dry_run($key, $rule = null, $now = null)
{
    $now = ($now === null) ? time() : (int) $now;
    $limits = pg_privacy_rules_interval_limits();
    $rule = is_array($rule) ? $rule : (pg_privacy_rules()[$key] ?? array('period' => null));
    $result = array('total' => 0, 'counts' => pg_privacy_rule_counts(), 'sample' => array());
    $where = _pg_privacy_rule_where($key, $rule, $now);

    if ($where === '') {
        return $result;
    }

    $table = pg_privacy_rules_catalogue()[$key]['table'];
    $cutoff = pg_privacy_rule_cutoff($key, $rule['period'], $now);
    $result['total'] = (int) db_value("SELECT COUNT(*) FROM `" . $table . "` WHERE " . $where);

    foreach (db_values("SELECT id FROM `" . $table . "` WHERE " . $where . " ORDER BY id LIMIT " . (int) $limits['check']) as $id) {
        $one = pg_privacy_rule_one($key, $id, $cutoff, $now, false);
        $result['counts'] = pg_privacy_rule_counts_add($result['counts'], $one);

        if (($one['skip'] === '') && $one['tables'] && (count($result['sample']) < $limits['sample'])) {
            $result['sample'][] = (int) $id;
        }
    }

    return $result;
}

/**
 * The rules pass's state (config_text privacy_rules_state): per rule the last
 * pass, and of a pass under way its start, the last id done and its counts.
 *
 * @return array  key => last_run_at, started_at, cursor, counts
 */
function pg_privacy_rules_state()
{
    $stored = _pg_privacy_rules_read('privacy_rules_state');
    $state = array();

    foreach (array_keys(pg_privacy_rules_catalogue()) as $key) {
        $row = (isset($stored[$key]) && is_array($stored[$key])) ? $stored[$key] : array();
        $state[$key] = array(
            'last_run_at' => (int) ($row['last_run_at'] ?? 0),
            'started_at'  => (int) ($row['started_at'] ?? 0),
            'cursor'      => (int) ($row['cursor'] ?? 0),
            'counts'      => (isset($row['counts']) && is_array($row['counts'])) ? array_merge(pg_privacy_rule_counts(), $row['counts']) : pg_privacy_rule_counts(),
        );
    }

    return $state;
}

/**
 * Whether the rules pass is owed: at night, a rule due.
 *
 * @param int|null $now
 * @return bool
 */
function pg_privacy_rules_due($now = null)
{
    $now = ($now === null) ? time() : (int) $now;

    if (!pg_privacy_rules_night($now) || !pg_schema_has('config_text')) {
        return false;
    }

    $rules = pg_privacy_rules();
    $state = pg_privacy_rules_state();

    foreach (array_keys(pg_privacy_rules_catalogue()) as $key) {
        if (pg_privacy_rule_due($rules[$key], $state[$key], $rules['interval'], $now)) {
            return true;
        }
    }

    return false;
}

/**
 * The nightly rules pass: each rule due goes through its rows in slices,
 * from the last id done, within the time budget; a pass that reaches its
 * end writes its audit line (pg_privacy_rule_audit_entry()) and the time.
 * A rule switched off or waiting for a new yes drops a pass under way.
 *
 * @param int      $seconds
 * @param int|null $now
 * @return array  key => counts of the rows done in this call
 */
function pg_privacy_rules_run($seconds = 5, $now = null)
{
    $now = ($now === null) ? time() : (int) $now;
    $deadline = microtime(true) + max(1, (int) $seconds);
    $limits = pg_privacy_rules_interval_limits();
    $rules = pg_privacy_rules();
    $state = pg_privacy_rules_state();
    $done = array();

    foreach (pg_privacy_rules_catalogue() as $key => $spec) {
        $rule = $rules[$key];
        $st = $state[$key];

        if (!pg_privacy_rule_due($rule, $st, $rules['interval'], $now)) {
            if ($st['started_at'] && (!$rule['on'] || !$rule['approved_at'])) {
                $state[$key] = array_merge($st, array('started_at' => 0, 'cursor' => 0, 'counts' => pg_privacy_rule_counts()));
                pg_config_text_save('privacy_rules_state', json_encode($state));
            }

            continue;
        }

        if (microtime(true) >= $deadline) {
            break;
        }

        if (!$st['started_at']) {
            $st = array('last_run_at' => $st['last_run_at'], 'started_at' => $now, 'cursor' => 0, 'counts' => pg_privacy_rule_counts());
        }

        $where = _pg_privacy_rule_where($key, $rule, $now);
        $cutoff = pg_privacy_rule_cutoff($key, $rule['period'], $now);
        $done[$key] = pg_privacy_rule_counts();
        $through = ($where === '');

        while (!$through && (microtime(true) < $deadline)) {
            $ids = db_values("SELECT id FROM `" . $spec['table'] . "` WHERE (id > '" . (int) $st['cursor'] . "') AND " . $where . " ORDER BY id LIMIT " . (int) $limits['slice']);
            $through = (count($ids) < $limits['slice']);

            foreach ($ids as $id) {
                if (microtime(true) >= $deadline) {
                    $through = false;
                    break;
                }

                $one = pg_privacy_rule_one($key, $id, $cutoff, $now, true);
                $st['counts'] = pg_privacy_rule_counts_add($st['counts'], $one);
                $done[$key] = pg_privacy_rule_counts_add($done[$key], $one);
                $st['cursor'] = (int) $id;
            }
        }

        if ($through) {
            pg_audit('run', 'privacy_rule', 0, array(), pg_privacy_rule_audit_entry($key, $rule, $st['counts']), $key);
            $st = array('last_run_at' => $now, 'started_at' => 0, 'cursor' => 0, 'counts' => pg_privacy_rule_counts());
        }

        $state[$key] = $st;
        pg_config_text_save('privacy_rules_state', json_encode($state));
    }

    return $done;
}
