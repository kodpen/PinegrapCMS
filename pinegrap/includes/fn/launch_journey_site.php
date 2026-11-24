<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The launch journey on this site: its stored row (config_text
 * launch_journey: the purpose, the go-live time, the hidden steps, whether
 * the set-up card was closed) and the facts its steps are judged on, each
 * read from the function that already decides it, at view time. The rules
 * that judge them are the pure catalogue in includes/fn/launch_journey.php.
 *
 * Nothing here matches a System Status check by its title, and no step is
 * ever ticked by hand: hiding one ("Not for this site") only takes it off
 * the card.
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
 * The stored row, made whole (pg_launch_journey_stored_read()).
 *
 * @return array purpose, suggested, live_at, hidden, closed
 */
function pg_launch_journey_stored()
{
    return pg_launch_journey_stored_read(pg_config_text(PG_LAUNCH_JOURNEY_ROW));
}

/**
 * Write changes to the stored row (pg_launch_journey_stored_change()). The
 * read is fresh, not the request's cached copy, so two changes in one
 * request both land.
 *
 * @param array $changes
 * @return bool false when the row could not be written
 */
function pg_launch_journey_store($changes)
{
    $current = pg_schema_has('config_text')
        ? (string) db_value("SELECT value FROM config_text WHERE name = '" . e(PG_LAUNCH_JOURNEY_ROW) . "'")
        : '';

    return pg_config_text_save(PG_LAUNCH_JOURNEY_ROW, pg_launch_journey_stored_change($current, $changes));
}

/**
 * Note the purpose an installed design template suggests, while no purpose
 * was chosen: the purpose dialog then opens with it picked.
 *
 * @param string $template the template's key
 * @return void
 */
function pg_launch_journey_template_noted($template)
{
    $purpose = pg_launch_journey_template_purpose($template);

    if (($purpose !== '') && (pg_launch_journey_stored()['purpose'] === '')) {
        pg_launch_journey_store(array('suggested' => $purpose));
    }
}

/**
 * The payment ways switched on that take money online, each with its driver
 * key (or the classic gateway's name when no driver takes it) and the
 * driver's settings. The offline payment takes no money online.
 *
 * @return array list of array(driver, settings)
 */
function pg_launch_journey_payment_facts()
{
    $payments = array();
    $settings = function ($key) {
        $stored = pg_payment_settings($key);
        return array_merge($stored['values'], $stored['secrets']);
    };

    foreach (pg_payment_builtin_switches() as $method => $on) {
        if (!$on || ($method === 'Offline Payment')) {
            continue;
        }

        $key = pg_payment_order_driver($method);

        if ($key === '') {
            $key = ($method === 'Credit/Debit Card') ? (defined('ECOMMERCE_PAYMENT_GATEWAY') ? (string) ECOMMERCE_PAYMENT_GATEWAY : '') : $method;
            $payments[] = array('driver' => $key, 'settings' => array());
            continue;
        }

        $payments[] = array('driver' => $key, 'settings' => $settings($key));
    }

    foreach (pg_payment_driver_methods(true) as $key) {
        $payments[] = array('driver' => $key, 'settings' => $settings($key));
    }

    return $payments;
}

/**
 * The scheduled tasks' health as the operations alert judges it: stalled
 * when a job that ran has gone silent, never when nothing has run, ok
 * otherwise; null before the run table exists.
 *
 * @param int $now
 * @return string|null
 */
function pg_launch_journey_cron_fact($now)
{
    $runs = pg_cron_health_last_runs();

    if ($runs === null) {
        return null;
    }

    if (!$runs) {
        return 'never';
    }

    $stalled = pg_ops_cron_stalled(pg_cron_jobs(), $runs, $now, pg_cron_dispatch_list(), (defined('JOB_DISPATCH_ENABLED') && JOB_DISPATCH_ENABLED));

    return $stalled ? 'stalled' : 'ok';
}

/**
 * The facts the steps are judged on (see includes/fn/launch_journey.php),
 * read now. A source that cannot be read leaves its key out, and its step
 * says it could not be checked.
 *
 * @param array  $user    the reader, whose own sign-in does not count as
 *                        the team's
 * @param string $purpose
 * @return array
 */
function pg_launch_journey_facts($user, $purpose)
{
    $now = time();
    $shop = defined('ECOMMERCE') && (ECOMMERCE === true);
    $facts = array();

    // Domain and SSL: Secure Mode's own answer; the certificate's end date
    // as the general job last read it, never a call from this request.
    $facts['https'] = (check_ssl_status() === 'success');
    $alerts = pg_ops_alert_state();
    if (is_array($alerts) && !empty($alerts['cert']['ends'])) {
        $facts['certificate'] = pg_ops_certificate_state((int) $alerts['cert']['ends'], $now, pg_ops_alert_thresholds()['cert_days']);
    }

    // Mail: a way to send is set, and the three records of the sending
    // domain as the mail settings card judges them.
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/mail_settings.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/mail_domain_health.php');
    $facts['mail_ready'] = pg_mail_settings_ready();
    if ($facts['mail_ready']) {
        $health = pg_mail_domain_health();
        foreach (array('spf', 'dkim', 'dmarc') as $record) {
            $facts['mail_' . $record] = isset($health[$record]['state']) ? (string) $health[$record]['state'] : 'unknown';
        }
    }

    // Legal texts: the personal-data notice, cookie consent, and a shop's
    // checkout documents; a form or the shop collects personal data.
    $facts['kvkk_text'] = (pg_legal_kvkk_settings()['url'] !== '');
    $facts['consent_enabled'] = pg_consent_enabled();
    $facts['legal_documents'] = pg_legal_documents_enabled();
    $facts['collects_data'] = $shop
        || (defined('FORMS') && (FORMS === true) && (bool) db_value("SELECT 1 FROM page WHERE page_type = 'custom form' LIMIT 1"));

    // Search engines: the operator's robots.txt text and the home page.
    $facts['robots_text'] = (string) db_value("SELECT additional_robots_content FROM config");
    if (pg_page_noindex_ready()) {
        $facts['home_noindex'] = (bool) db_value("SELECT noindex FROM page WHERE page_home = 'yes' ORDER BY page_id LIMIT 1");
    }

    // Search Console and Analytics as the Connections screen reads them.
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_screen.php');
    $registry = pg_connections_registry();
    $connections = pg_connections_screen_facts();
    foreach (array('search_console', 'google_analytics') as $id) {
        if (isset($registry[$id])) {
            $facts[$id] = pg_connections_screen_state($registry[$id], $connections);
        }
    }

    // Payment: only a shop is asked.
    if ($shop && (pg_launch_journey_purpose($purpose) === 'shop')) {
        $facts['payments'] = pg_launch_journey_payment_facts();
        $facts['gateway_mode'] = defined('ECOMMERCE_PAYMENT_GATEWAY_MODE') ? (string) ECOMMERCE_PAYMENT_GATEWAY_MODE : '';
    }

    // Bookings: someone bookable has weekly hours.
    if ((pg_launch_journey_purpose($purpose) === 'clinic') && pg_booking_ready()) {
        $facts['booking_hours'] = false;
        foreach (db_values("SELECT hours FROM booking_staff") as $hours) {
            if (pg_booking_hours_clean($hours)) {
                $facts['booking_hours'] = true;
                break;
            }
        }
    }

    // Backups: the newest one in the folders, judged as System Status does.
    $facts['backup'] = pg_backup_age_state(pg_backup_newest()['newest'], $now);

    $cron = pg_launch_journey_cron_fact($now);
    if ($cron !== null) {
        $facts['cron'] = $cron;
    }

    // Team: another person signed in at least once (every sign-in stamps
    // the person's dashboard row).
    if (pg_schema_has('dashboard_layout')) {
        $facts['team_signed_in'] = (bool) db_value("SELECT 1 FROM dashboard_layout
            WHERE user_id > 0 AND user_id <> '" . (int) $user['id'] . "' AND signed_in_at > 0 LIMIT 1");
    }

    $facts['maintenance'] = pg_maintenance_holds(pg_maintenance_settings()['on'], pg_maintenance_settings()['until'], $now);

    return $facts;
}

/**
 * "Go live": turns maintenance mode off and removes the robots.txt rule that
 * closes the whole site, each written to the Site Log, then keeps the
 * go-live time (the first one stays, so the first week counts from it). A
 * payment mode is never changed. The check is read again here, never taken
 * from the request; with a blocker left the request must carry "I know, go
 * live anyway", and the blockers it went live with are named in the log.
 *
 * @param array $user
 * @param array $request anyway
 * @return string '' when the site went live, else the reason in words
 */
function pg_launch_journey_go_live($user, $request)
{
    $stored = pg_launch_journey_stored();
    $purpose = ($stored['purpose'] !== '') ? $stored['purpose'] : 'other';
    $facts = pg_launch_journey_facts($user, $purpose);
    $check = pg_launch_journey_golive_check($purpose, $facts, $stored['hidden']);
    $left = pg_launch_journey_golive_left($check);

    if (!pg_launch_journey_golive_allowed($left, $request)) {
        return lang('Some things must still be fixed. Confirm to go live anyway.');
    }

    $changes = pg_launch_journey_golive_changes($facts);

    if (in_array('maintenance', $changes, true) && pg_schema_has('config', 'maintenance_mode')) {
        if (db("UPDATE config SET maintenance_mode = '0', last_modified_user_id = '" . (int) $user['id'] . "', last_modified_timestamp = UNIX_TIMESTAMP()") === false) {
            return lang('The change could not be saved.');
        }
        log_activity(lang('Go live: maintenance mode was switched off.'));
    }

    if (in_array('robots', $changes, true)) {
        $opened = pg_launch_journey_robots_opened($facts['robots_text']);
        if (db("UPDATE config SET additional_robots_content = '" . e($opened) . "', last_modified_user_id = '" . (int) $user['id'] . "', last_modified_timestamp = UNIX_TIMESTAMP()") === false) {
            return lang('The change could not be saved.');
        }
        log_activity(lang('Go live: the robots.txt rule closing the whole site to search engines was removed.'));
    }

    if (($stored['live_at'] < 1) && !pg_launch_journey_store(array('live_at' => time()))) {
        return lang('The change could not be saved.');
    }

    if ($left) {
        $names = array();
        foreach ($left as $row) {
            $names[] = pg_launch_journey_step_face($row['key'])['name'] . ' (' . pg_launch_journey_step_line($row, $facts) . ')';
        }
        log_activity(lang(array('string' => 'The site went live with these left to fix: {var:1}', 'vars' => array(implode('; ', $names)))));
    } else {
        log_activity(lang('The site went live.'));
    }

    // System Status reads maintenance mode and robots.txt: told at once
    // rather than after its ten-minute cache.
    if ($changes && function_exists('pg_system_status_forget')) {
        pg_system_status_forget();
    }

    return '';
}
