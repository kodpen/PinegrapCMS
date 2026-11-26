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
    return pg_launch_journey_row_change(function ($current) use ($changes) {
        return pg_launch_journey_stored_change($current, $changes);
    });
}

/**
 * Change the journey's row from its fresh value: the set-up card, "Go live"
 * and the agency summary's link all keep their part of the one row, so a
 * write only lands while the row still holds what was read
 * (pg_config_text_claim()), and is made again on the newer row otherwise.
 *
 * @param callable $change the row's JSON => the JSON to keep
 * @return bool false when the row could not be written
 */
function pg_launch_journey_row_change($change)
{
    if (!pg_schema_has('config_text')) {
        return false;
    }

    $read = function () {
        return (string) db_value("SELECT value FROM config_text WHERE name = '" . e(PG_LAUNCH_JOURNEY_ROW) . "'");
    };
    $claim = function ($expected, $value) {
        return pg_config_text_claim(PG_LAUNCH_JOURNEY_ROW, $expected, $value);
    };

    return pg_launch_journey_row_write($change, $read, $claim);
}

/**
 * The read, change and claim loop of pg_launch_journey_row_change(), its
 * reads and writes given. A change that leaves the row as it is writes
 * nothing.
 *
 * @param callable $change JSON => JSON
 * @param callable $read   () => the row's value now
 * @param callable $claim  (expected, value) => whether the write landed
 * @param int      $tries
 * @return bool
 */
function pg_launch_journey_row_write($change, $read, $claim, $tries = 5)
{
    for ($try = 0; $try < $tries; $try++) {
        $current = (string) $read();
        $value = (string) $change($current);

        if (($value === $current) || ($value === '')) {
            return $value === $current;
        }

        if ($claim($current, $value)) {
            return true;
        }
    }

    return false;
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
 * key and the settings it runs on, or the classic way's name with the
 * setting that says its mode (pg_launch_journey_payment_classic()). The
 * offline payment takes no money online.
 *
 * @return array list of array(driver, settings)
 */
function pg_launch_journey_payment_facts()
{
    $payments = array();
    $settings = function ($key) {
        return pg_launch_journey_payment_driver_settings(pg_payment_settings($key), pg_payment_fields($key));
    };
    $legacy = array(
        'paypal_mode' => defined('ECOMMERCE_PAYPAL_EXPRESS_CHECKOUT_MODE') ? (string) ECOMMERCE_PAYPAL_EXPRESS_CHECKOUT_MODE : '',
        'stripe_key'  => defined('ECOMMERCE_STRIPE_API_KEY') ? (string) ECOMMERCE_STRIPE_API_KEY : '',
    );
    $gateway = defined('ECOMMERCE_PAYMENT_GATEWAY') ? (string) ECOMMERCE_PAYMENT_GATEWAY : '';

    foreach (pg_payment_builtin_switches() as $method => $on) {
        if (!$on || ($method === 'Offline Payment')) {
            continue;
        }

        $key = pg_payment_order_driver($method);

        if ($key === '') {
            $payments[] = pg_launch_journey_payment_classic($method, $gateway, $legacy);
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
        $facts += pg_launch_journey_mail_facts(pg_mail_domain_health());
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

    // Payment: only a shop is asked; the shop switched off takes none.
    $facts['shop'] = $shop;
    if (pg_launch_journey_purpose($purpose) === 'shop') {
        $facts['payments'] = $shop ? pg_launch_journey_payment_facts() : array();
        $facts['gateway_mode'] = defined('ECOMMERCE_PAYMENT_GATEWAY_MODE') ? (string) ECOMMERCE_PAYMENT_GATEWAY_MODE : '';
    }

    // Bookings: a bookable service, and someone bookable with weekly hours.
    // Before the booking tables exist nothing can be read.
    if ((pg_launch_journey_purpose($purpose) === 'clinic') && pg_booking_ready()) {
        $facts['booking_setup'] = (bool) db_value("SELECT 1 FROM booking_services LIMIT 1");
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

    // Broken internal links as the SEO check last stored them; this request
    // crawls nothing. No page ever analyzed means the check never ran.
    if (pg_schema_has('seo_issue') && pg_schema_has('page', 'seo_struct_checked_at')) {
        $seo = db_item("SELECT
            (SELECT COUNT(*) FROM seo_issue WHERE code = 'link_broken_internal') AS broken,
            (SELECT MAX(seo_struct_checked_at) FROM page) AS checked");
        if (is_array($seo)) {
            $facts['broken_links'] = ((int) $seo['checked'] > 0) ? (int) $seo['broken'] : 'never';
        }
    }

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
    if (!pg_schema_has('config', 'maintenance_mode')) {
        $changes = array_values(array_diff($changes, array('maintenance')));
    }
    $robots = isset($facts['robots_text']) ? (string) $facts['robots_text'] : '';
    $applied = array();

    // Both changes in one statement, guarded by what was read: a second
    // press arriving after the first changes no row.
    $query = pg_launch_journey_golive_query($changes, $robots, in_array('robots', $changes, true) ? pg_launch_journey_robots_opened($robots) : '', $user['id']);
    if ($query !== null) {
        if (db_q($query[0], $query[1]) === false) {
            return lang('The change could not be saved.');
        }

        if (mysqli_affected_rows(db::$con) > 0) {
            $applied = $changes;
        } else {
            // Nothing changed: another press did it, or the settings moved
            // since they were read and the site is still closed.
            $now = db_item("SELECT maintenance_mode, additional_robots_content FROM config LIMIT 1");
            if (!is_array($now) || (in_array('maintenance', $changes, true) && ((string) $now['maintenance_mode'] !== '0'))
                || pg_robots_catch_all_closed((string) $now['additional_robots_content'])) {
                return lang('The change could not be saved.');
            }
        }
    }

    $first = false;
    $kept = pg_launch_journey_row_change(function ($current) use (&$first) {
        $first = (pg_launch_journey_stored_read($current)['live_at'] < 1);
        return $first ? pg_launch_journey_stored_change($current, array('live_at' => time())) : $current;
    });
    if (!$kept) {
        return lang('The change could not be saved.');
    }

    $names = array();
    foreach ($left as $row) {
        $names[] = pg_launch_journey_step_face($row['key'])['name'] . ' (' . pg_launch_journey_step_line($row, $facts) . ')';
    }
    foreach (pg_launch_journey_golive_log($applied, $first, $names) as $line) {
        log_activity($line);
    }

    // System Status reads maintenance mode, robots.txt and the go-live time
    // (Payment Mode): told at once rather than after its ten-minute cache.
    if (($applied || $first) && function_exists('pg_system_status_forget')) {
        pg_system_status_forget();
    }

    return '';
}
