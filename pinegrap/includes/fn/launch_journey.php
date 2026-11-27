<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The launch journey's catalogue: the site's purposes, the set-up steps each
 * purpose walks through, and each step's rule over a facts array: done, to
 * do or unknown, and when to do, whether it blocks going live or is worth a
 * look. Also the payment-live rule per payment driver and the first week
 * after going live.
 *
 * Everything here is pure. The caller reads the facts from the functions
 * that already decide them (check_ssl_status(), pg_ops_certificate_state(),
 * pg_mail_settings_ready(), the mail DNS checks, the legal settings, the
 * robots text, pg_connections_screen_state(), the payment settings,
 * pg_backup_age_state(), the scheduled tasks' health, maintenance mode) and
 * passes their answers; a fact that was not read is a missing key, and a step
 * whose facts are missing is unknown, never a blocker.
 *
 * The facts array (every key optional):
 * - https            bool, the site answers over HTTPS (check_ssl_status())
 * - certificate      pg_ops_certificate_state(): ok, expiring, expired, unknown
 * - mail_ready       bool, pg_mail_settings_ready()
 * - mail_spf, mail_dkim, mail_dmarc
 *                    the state of pg_mail_dns_line(): ok, warning, error, unknown
 * - kvkk_text        bool, a personal-data text is set (pg_legal_kvkk_settings())
 * - consent_enabled  bool, pg_consent_enabled()
 * - legal_documents  bool, a shop's legal documents (pg_legal_documents_enabled())
 * - collects_data    bool, a form or the shop collects personal data
 * - robots_text      string, the robots.txt text with its extras
 * - home_noindex     bool, the home page says noindex
 * - search_console, google_analytics
 *                    pg_connections_screen_state() of that connection
 * - payments         list of array('driver' => key or classic gateway name,
 *                    'settings' => the driver's settings) for each payment
 *                    way switched on that takes money online
 * - gateway_mode     ECOMMERCE_PAYMENT_GATEWAY_MODE: 'test' or 'live'
 * - shop             bool, the shop is switched on (ECOMMERCE)
 * - booking_hours    bool, bookable hours are set
 * - booking_setup    bool, a bookable service exists (read once the
 *                    booking tables are there)
 * - backup           pg_backup_age_state(): pass, warn, fail
 * - cron             ok, stalled (a job that ran stopped), never, unknown
 * - team_signed_in   bool, another user signed in at least once
 * - maintenance      bool, maintenance mode is on
 * - broken_links     int, the seo_issue rows of code link_broken_internal,
 *                    or 'never' when the SEO structure check has never run
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
 * How long the first week lasts after going live, in seconds.
 */
define('PG_LAUNCH_JOURNEY_FIRST_WEEK', 7 * 86400);

/**
 * The purposes a site can have, in the order the choice lists them. Pure.
 *
 * @return string[]
 */
function pg_launch_journey_purposes()
{
    return array('shop', 'company', 'association', 'school', 'clinic', 'agency', 'blog', 'other');
}

/**
 * A stored or posted purpose made one of pg_launch_journey_purposes();
 * anything else is 'other'. Pure.
 *
 * @param mixed $purpose
 * @return string
 */
function pg_launch_journey_purpose($purpose)
{
    return (is_string($purpose) && in_array($purpose, pg_launch_journey_purposes(), true)) ? $purpose : 'other';
}

/**
 * Every step the journey knows, set-up steps and go-live checks alike. Pure.
 *
 * - card  shown on the set-up card; false for a check only the go-live
 *         screen asks (maintenance mode, which "Go live" turns off itself;
 *         broken internal links, found by the SEO check)
 *
 * @return array<string,array>
 */
function pg_launch_journey_catalogue()
{
    return array(
        'domain_ssl'    => array('card' => true),
        'payment_live'  => array('card' => true),
        'booking_hours' => array('card' => true),
        'mail'          => array('card' => true),
        'legal'         => array('card' => true),
        'search_open'   => array('card' => true),
        'search_tools'  => array('card' => true),
        'backups'       => array('card' => true),
        'cron'          => array('card' => true),
        'team'          => array('card' => true),
        'maintenance'   => array('card' => false),
        'broken_links'  => array('card' => false),
    );
}

/**
 * The set-up steps of a purpose, in the order it walks them. Pure.
 *
 * @param string $purpose
 * @return string[]
 */
function pg_launch_journey_steps($purpose)
{
    $tail = array('mail', 'legal', 'search_open', 'search_tools', 'backups', 'cron', 'team');

    switch (pg_launch_journey_purpose($purpose)) {

        case 'shop':
            return array_merge(array('domain_ssl', 'payment_live'), $tail);

        case 'clinic':
            return array_merge(array('domain_ssl', 'booking_hours'), $tail);

        case 'blog':
            return array('domain_ssl', 'search_open', 'search_tools', 'mail', 'legal', 'backups', 'cron', 'team');

        default:
            return array_merge(array('domain_ssl'), $tail);
    }
}

/**
 * Hidden steps as stored, kept to the set-up card's steps, once each. Pure.
 *
 * @param mixed $hidden
 * @return string[]
 */
function pg_launch_journey_hidden_clean($hidden)
{
    $catalogue = pg_launch_journey_catalogue();
    $clean = array();

    foreach ((is_array($hidden) ? $hidden : array()) as $key) {
        if (is_string($key) && isset($catalogue[$key]) && $catalogue[$key]['card'] && !in_array($key, $clean, true)) {
            $clean[] = $key;
        }
    }

    return $clean;
}

/**
 * Whether one payment way takes real money: 'live', 'test', or 'unknown'
 * when its settings cannot tell. Stripe goes by its keys' prefix (one test
 * key is test; the classic Stripe gateway by its secret key), PayTR, PayPal
 * and PayPal Express Checkout by their own mode, iyzico and the other
 * classic gateways by the Ecommerce card's gateway mode. Pure.
 *
 * @param string $driver       a driver key or a classic gateway name
 * @param array  $settings     the driver's settings
 * @param string $gateway_mode ECOMMERCE_PAYMENT_GATEWAY_MODE
 * @return string
 */
function pg_launch_journey_payment_live($driver, $settings, $gateway_mode)
{
    $settings = is_array($settings) ? $settings : array();
    $driver = strtolower((string) $driver);

    if ($driver === 'stripe') {
        $state = 'unknown';

        foreach (array('publishable_key', 'secret_key') as $name) {
            $key = isset($settings[$name]) ? trim((string) $settings[$name]) : '';

            if (preg_match('/^(pk|sk|rk)_test_/', $key)) {
                return 'test';
            }

            if (preg_match('/^(pk|sk|rk)_live_/', $key)) {
                $state = 'live';
            }
        }

        return $state;
    }

    // PayPal Express Checkout, the classic PayPal way, keeps its own
    // sandbox switch apart from the card gateway's mode.
    if (in_array($driver, array('paytr', 'paypal', 'paypal express checkout'), true)) {
        $mode = isset($settings['mode']) ? (string) $settings['mode'] : '';

        if ($mode === '') {
            return 'unknown';
        }

        return in_array($mode, array('test', 'sandbox'), true) ? 'test' : 'live';
    }

    if ($gateway_mode === 'test') {
        return 'test';
    }

    return ($gateway_mode === 'live') ? 'live' : 'unknown';
}

/**
 * The System Status row "Payment Test Mode": null when the row is not drawn
 * (the shop is off, or the site has not gone live), else the drivers of the
 * switched-on ways in test by pg_launch_journey_payment_live(), the card's
 * own rule, so the row and the card never disagree. An empty list passes;
 * a way whose mode cannot be read is not counted. Pure.
 *
 * @param bool   $shop_on      ECOMMERCE
 * @param int    $live_at      the launch journey's go-live time, 0 before
 * @param array  $payments     pg_launch_journey_payment_facts()
 * @param string $gateway_mode ECOMMERCE_PAYMENT_GATEWAY_MODE
 * @return array|null
 */
function pg_launch_journey_payment_test_ways($shop_on, $live_at, $payments, $gateway_mode)
{
    if (!$shop_on || ((int) $live_at < 1)) {
        return null;
    }

    $test = array();

    foreach ((is_array($payments) ? $payments : array()) as $payment) {
        $driver = isset($payment['driver']) ? (string) $payment['driver'] : '';

        if (pg_launch_journey_payment_live($driver, isset($payment['settings']) ? $payment['settings'] : array(), (string) $gateway_mode) === 'test') {
            $test[] = $driver;
        }
    }

    return $test;
}

/**
 * A payment way no driver takes, as the payment-live rule reads it: the
 * card method's classic gateway by its name, PayPal Express Checkout with
 * its own mode, the classic Stripe gateway with its secret key (a test key
 * is test, whatever the gateway mode). Pure.
 *
 * @param string $method  a built-in payment method
 * @param string $gateway ECOMMERCE_PAYMENT_GATEWAY
 * @param array  $legacy  paypal_mode (ECOMMERCE_PAYPAL_EXPRESS_CHECKOUT_MODE),
 *                        stripe_key (ECOMMERCE_STRIPE_API_KEY)
 * @return array driver, settings
 */
function pg_launch_journey_payment_classic($method, $gateway, $legacy)
{
    $legacy = is_array($legacy) ? $legacy : array();
    $read = function ($name) use ($legacy) {
        return isset($legacy[$name]) ? (string) $legacy[$name] : '';
    };

    if ((string) $method === 'PayPal Express Checkout') {
        return array('driver' => 'PayPal Express Checkout', 'settings' => array('mode' => $read('paypal_mode')));
    }

    if ((string) $method !== 'Credit/Debit Card') {
        return array('driver' => (string) $method, 'settings' => array());
    }

    if ((string) $gateway === 'Stripe') {
        return array('driver' => 'Stripe', 'settings' => array('secret_key' => $read('stripe_key')));
    }

    return array('driver' => (string) $gateway, 'settings' => array());
}

/**
 * A payment driver's settings as the driver runs on them: the stored values
 * and secrets, and each declared field's default where nothing was saved
 * (pg_payment_setting() reads them the same way). Pure.
 *
 * @param array $stored pg_payment_settings(): values, secrets
 * @param array $fields pg_payment_fields(): name, default
 * @return array name => value
 */
function pg_launch_journey_payment_driver_settings($stored, $fields)
{
    $settings = array_merge(
        (isset($stored['values']) && is_array($stored['values'])) ? $stored['values'] : array(),
        (isset($stored['secrets']) && is_array($stored['secrets'])) ? $stored['secrets'] : array()
    );

    foreach ((is_array($fields) ? $fields : array()) as $field) {
        if (isset($field['name']) && !array_key_exists($field['name'], $settings) && isset($field['default']) && ((string) $field['default'] !== '')) {
            $settings[$field['name']] = (string) $field['default'];
        }
    }

    return $settings;
}

/**
 * The mail facts of the three records as the mail settings card judged
 * them. A record the sending service signs for the site (DKIM through a
 * service's API) is the service's to check, so it is left out rather than
 * kept as a warning nothing on the site can clear. Pure.
 *
 * @param array $health pg_mail_domain_health()
 * @return array mail_spf, mail_dkim, mail_dmarc
 */
function pg_launch_journey_mail_facts($health)
{
    $facts = array();

    foreach (array('spf', 'dkim', 'dmarc') as $record) {
        $line = (is_array($health) && isset($health[$record]) && is_array($health[$record])) ? $health[$record] : array();

        if (isset($line['reason']) && ($line['reason'] === 'service')) {
            continue;
        }

        $facts['mail_' . $record] = isset($line['state']) ? (string) $line['state'] : 'unknown';
    }

    return $facts;
}

/**
 * One rule's answer. Pure.
 *
 * @param string $state  done, todo or unknown
 * @param string $level  blocker, warning or none (only a step to do has one)
 * @param string $reason a short code naming why, '' when done
 * @return array
 */
function pg_launch_journey_answer($state, $level = 'none', $reason = '')
{
    return array('state' => $state, 'level' => ($state === 'todo') ? $level : 'none', 'reason' => $reason);
}

/**
 * One step's rule over the facts: array('state', 'level', 'reason'). A
 * blocker stops going live, a warning is worth a look, none is a set-up step
 * the go-live check does not ask. A step whose facts were not read is
 * unknown. Pure.
 *
 * @param string $key     a key of pg_launch_journey_catalogue()
 * @param array  $facts   see the file header
 * @param string $purpose
 * @return array
 */
function pg_launch_journey_rule($key, $facts, $purpose)
{
    $facts = is_array($facts) ? $facts : array();
    $has = function ($name) use ($facts) {
        return array_key_exists($name, $facts) && ($facts[$name] !== null);
    };
    $unknown = pg_launch_journey_answer('unknown');

    switch ($key) {

        case 'domain_ssl':
            if (!$has('https')) {
                return $unknown;
            }

            if (!$facts['https']) {
                return pg_launch_journey_answer('todo', 'warning', 'no_https');
            }

            if ($has('certificate') && in_array($facts['certificate'], array('expired', 'expiring'), true)) {
                return pg_launch_journey_answer('todo', 'warning', 'certificate_' . $facts['certificate']);
            }

            return pg_launch_journey_answer('done');

        case 'mail':
            if (!$has('mail_ready')) {
                return $unknown;
            }

            if (!$facts['mail_ready']) {
                return pg_launch_journey_answer('todo', 'blocker', 'not_sending');
            }

            foreach (array('mail_spf', 'mail_dkim', 'mail_dmarc') as $name) {
                if ($has($name) && ($facts[$name] !== 'ok')) {
                    return pg_launch_journey_answer('todo', 'warning', 'dns');
                }
            }

            return pg_launch_journey_answer('done');

        case 'legal':
            if (!$has('kvkk_text')) {
                return $unknown;
            }

            if (!$facts['kvkk_text']) {
                $collects = (pg_launch_journey_purpose($purpose) === 'shop') || ($has('collects_data') && $facts['collects_data']);

                return pg_launch_journey_answer('todo', $collects ? 'blocker' : 'warning', 'no_personal_data_text');
            }

            if ($has('consent_enabled') && !$facts['consent_enabled']) {
                return pg_launch_journey_answer('todo', 'warning', 'no_consent');
            }

            if ((pg_launch_journey_purpose($purpose) === 'shop') && $has('legal_documents') && !$facts['legal_documents']) {
                return pg_launch_journey_answer('todo', 'warning', 'no_legal_documents');
            }

            return pg_launch_journey_answer('done');

        case 'search_open':
            if (!$has('robots_text') && !$has('home_noindex')) {
                return $unknown;
            }

            if ($has('robots_text') && pg_robots_catch_all_closed((string) $facts['robots_text'])) {
                return pg_launch_journey_answer('todo', 'blocker', 'robots_closed');
            }

            if ($has('home_noindex') && $facts['home_noindex']) {
                return pg_launch_journey_answer('todo', 'blocker', 'home_noindex');
            }

            return pg_launch_journey_answer('done');

        case 'search_tools':
            if (!$has('search_console')) {
                return $unknown;
            }

            if ($facts['search_console'] !== 'connected') {
                return pg_launch_journey_answer('todo', 'warning', 'no_search_console');
            }

            if ($has('google_analytics') && ($facts['google_analytics'] !== 'connected')) {
                return pg_launch_journey_answer('todo', 'none', 'no_analytics');
            }

            return pg_launch_journey_answer('done');

        case 'payment_live':
            if (!$has('payments') || !is_array($facts['payments'])) {
                return $unknown;
            }

            if (!$facts['payments']) {
                // The shop switched off is read, not unknown: the step says
                // so and opens the switch.
                return pg_launch_journey_answer('todo', 'warning', ($has('shop') && !$facts['shop']) ? 'shop_off' : 'no_online_payment');
            }

            $mode = $has('gateway_mode') ? (string) $facts['gateway_mode'] : '';
            $states = array();

            foreach ($facts['payments'] as $payment) {
                $states[] = pg_launch_journey_payment_live(
                    isset($payment['driver']) ? $payment['driver'] : '',
                    isset($payment['settings']) ? $payment['settings'] : array(),
                    $mode
                );
            }

            if (in_array('test', $states, true)) {
                return pg_launch_journey_answer('todo', 'blocker', 'test_mode');
            }

            if (in_array('unknown', $states, true)) {
                return pg_launch_journey_answer('todo', 'warning', 'mode_unknown');
            }

            return pg_launch_journey_answer('done');

        case 'booking_hours':
            if (!$has('booking_hours')) {
                return $unknown;
            }

            if ($has('booking_setup') && !$facts['booking_setup']) {
                return pg_launch_journey_answer('todo', 'warning', 'not_set_up');
            }

            return $facts['booking_hours'] ? pg_launch_journey_answer('done') : pg_launch_journey_answer('todo', 'warning', 'no_hours');

        case 'backups':
            if (!$has('backup')) {
                return $unknown;
            }

            if ($facts['backup'] === 'fail') {
                return pg_launch_journey_answer('todo', 'warning', 'none');
            }

            return ($facts['backup'] === 'warn') ? pg_launch_journey_answer('todo', 'warning', 'old') : pg_launch_journey_answer('done');

        case 'cron':
            if (!$has('cron')) {
                return $unknown;
            }

            switch ($facts['cron']) {
                case 'ok':
                    return pg_launch_journey_answer('done');
                case 'stalled':
                    return pg_launch_journey_answer('todo', 'blocker', 'stalled');
                case 'never':
                    return pg_launch_journey_answer('todo', 'warning', 'never');
                default:
                    return $unknown;
            }

        case 'team':
            if (!$has('team_signed_in')) {
                return $unknown;
            }

            return $facts['team_signed_in'] ? pg_launch_journey_answer('done') : pg_launch_journey_answer('todo', 'none', 'alone');

        case 'maintenance':
            if (!$has('maintenance')) {
                return $unknown;
            }

            return $facts['maintenance'] ? pg_launch_journey_answer('todo', 'blocker', 'on') : pg_launch_journey_answer('done');

        case 'broken_links':
            if (!$has('broken_links')) {
                return $unknown;
            }

            // Never a blocker: a broken link is worth fixing, not a reason
            // to keep the site closed.
            if ($facts['broken_links'] === 'never') {
                return pg_launch_journey_answer('todo', 'warning', 'never_checked');
            }

            return ((int) $facts['broken_links'] > 0) ? pg_launch_journey_answer('todo', 'warning', 'broken') : pg_launch_journey_answer('done');
    }

    return $unknown;
}

/**
 * The set-up card's state: its rows (blockers first, then warnings, then the
 * rest, each group in the purpose's order), how many are done, the status
 * (blocked, in_progress, ready) and whether the journey is finished: every
 * step done or hidden. A hidden step stays on the card while it blocks, and
 * is never counted done. Pure.
 *
 * @param string $purpose
 * @param array  $facts
 * @param mixed  $hidden the stored hidden steps
 * @return array rows (key, state, level, reason), done, total, status, finished
 */
function pg_launch_journey_state($purpose, $facts, $hidden)
{
    $hidden = pg_launch_journey_hidden_clean($hidden);
    $groups = array('blocker' => array(), 'warning' => array(), 'none' => array());
    $done = 0;

    foreach (pg_launch_journey_steps($purpose) as $key) {
        $row = array('key' => $key) + pg_launch_journey_rule($key, $facts, $purpose);

        if (in_array($key, $hidden, true) && ($row['level'] !== 'blocker')) {
            continue;
        }

        if ($row['state'] === 'done') {
            $done++;
        }

        $groups[$row['level']][] = $row;
    }

    $rows = array_merge($groups['blocker'], $groups['warning'], $groups['none']);
    $total = count($rows);

    if ($groups['blocker']) {
        $status = 'blocked';
    } else {
        $status = ($done === $total) ? 'ready' : 'in_progress';
    }

    return array(
        'rows'     => $rows,
        'done'     => $done,
        'total'    => $total,
        'status'   => $status,
        'finished' => ($done === $total),
    );
}

/**
 * Whether the set-up card closes itself: every card step done or hidden,
 * and the go-live check has no blocker left. While maintenance mode is on
 * the card stays, so "Go live" can turn it off; the next read after it
 * closes the card. Pure.
 *
 * @param string $purpose
 * @param array  $facts
 * @param mixed  $hidden
 * @return bool
 */
function pg_launch_journey_card_closes($purpose, $facts, $hidden)
{
    return pg_launch_journey_state($purpose, $facts, $hidden)['finished']
        && !pg_launch_journey_golive_check($purpose, $facts, $hidden)['blockers'];
}

/**
 * The go-live check: blockers ("Must be fixed") and warnings ("Worth a
 * look"), each a list of rows (key, state, level, reason) in the purpose's
 * order, maintenance mode first among the blockers, broken internal links
 * last among the warnings. A hidden step's warning
 * is left out; a blocker never is. Pure.
 *
 * @param string $purpose
 * @param array  $facts
 * @param mixed  $hidden
 * @return array blockers, warnings
 */
function pg_launch_journey_golive_check($purpose, $facts, $hidden)
{
    $hidden = pg_launch_journey_hidden_clean($hidden);
    $check = array('blockers' => array(), 'warnings' => array());

    foreach (array_merge(array('maintenance'), pg_launch_journey_steps($purpose), array('broken_links')) as $key) {
        $row = array('key' => $key) + pg_launch_journey_rule($key, $facts, $purpose);

        if ($row['level'] === 'blocker') {
            $check['blockers'][] = $row;
        } elseif (($row['level'] === 'warning') && !in_array($key, $hidden, true)) {
            $check['warnings'][] = $row;
        }
    }

    return $check;
}

/**
 * The first week after going live: whether it is open at $now, its day
 * (1 to 7), when it ends and the seconds left. It ends by itself. Pure.
 *
 * @param int $live_at the go-live time, 0 when the site has not gone live
 * @param int $now
 * @return array open, day, ends_at, left
 */
function pg_launch_journey_first_week($live_at, $now)
{
    $live_at = (int) $live_at;
    $now = (int) $now;
    $ends = ($live_at > 0) ? $live_at + PG_LAUNCH_JOURNEY_FIRST_WEEK : 0;

    if (($live_at < 1) || ($now < $live_at) || ($now >= $ends)) {
        return array('open' => false, 'day' => 0, 'ends_at' => $ends, 'left' => 0);
    }

    return array(
        'open'    => true,
        'day'     => (int) floor(($now - $live_at) / 86400) + 1,
        'ends_at' => $ends,
        'left'    => $ends - $now,
    );
}

/**
 * The name of the journey's config_text row: one JSON object holding the
 * purpose, the purpose a template suggested, the go-live time, the hidden
 * steps and whether the set-up card was closed.
 */
define('PG_LAUNCH_JOURNEY_ROW', 'launch_journey');

/**
 * The stored row made whole: purpose ('' while none was chosen), suggested
 * (a template's purpose, '' for none), live_at, hidden and closed. Anything
 * the row does not hold, or holds wrongly, takes its empty value. Pure.
 *
 * @param string $json the config_text value
 * @return array purpose, suggested, live_at, hidden, closed
 */
function pg_launch_journey_stored_read($json)
{
    $row = json_decode((string) $json, true);
    $row = is_array($row) ? $row : array();
    $known = function ($name) use ($row) {
        return (isset($row[$name]) && in_array($row[$name], pg_launch_journey_purposes(), true)) ? $row[$name] : '';
    };

    return array(
        'purpose'   => $known('purpose'),
        'suggested' => $known('suggested'),
        'live_at'   => isset($row['live_at']) ? max(0, (int) $row['live_at']) : 0,
        'hidden'    => pg_launch_journey_hidden_clean(isset($row['hidden']) ? $row['hidden'] : array()),
        'closed'    => !empty($row['closed']),
    );
}

/**
 * A stored row with changes made, as the JSON to keep. A purpose outside the
 * list, a hidden step the card does not show and any key the row does not
 * know are left out. Keys a later step adds to the row are kept as they were.
 * Pure.
 *
 * @param string $json    the config_text value
 * @param array  $changes purpose, suggested, live_at, hide (one step key),
 *                        closed
 * @return string
 */
function pg_launch_journey_stored_change($json, $changes)
{
    $raw = json_decode((string) $json, true);
    $raw = is_array($raw) ? $raw : array();
    $row = pg_launch_journey_stored_read($json);

    foreach (array('purpose', 'suggested') as $name) {
        if (isset($changes[$name]) && in_array($changes[$name], pg_launch_journey_purposes(), true)) {
            $row[$name] = $changes[$name];
        }
    }

    if (isset($changes['live_at'])) {
        $row['live_at'] = max(0, (int) $changes['live_at']);
    }

    if (isset($changes['hide'])) {
        $row['hidden'] = pg_launch_journey_hidden_clean(array_merge($row['hidden'], array($changes['hide'])));
    }

    if (isset($changes['closed'])) {
        // Brought back, the card starts again with every step on it.
        if ($row['closed'] && !$changes['closed']) {
            $row['hidden'] = array();
        }
        $row['closed'] = (bool) $changes['closed'];
    }

    return (string) json_encode(array_merge($raw, $row));
}

/**
 * The purpose a design template's key suggests, '' for a template the
 * journey does not know; the purpose dialog then starts with nothing
 * picked. Pure.
 *
 * @param string $template a key of pg_design_templates()
 * @return string
 */
function pg_launch_journey_template_purpose($template)
{
    $map = array(
        'shop'        => array('pinegrap-store', 'pinegrap-boutique', 'pinegrap-bookshop', 'pinegrap-florist', 'pinegrap-market'),
        'company'     => array('pinegrap-construction', 'pinegrap-law', 'pinegrap-realestate', 'pinegrap-recruitment', 'pinegrap-homeservices'),
        'association' => array('pinegrap-charity', 'pinegrap-sportsclub'),
        'school'      => array('pinegrap-school', 'pinegrap-courses', 'pinegrap-drivingschool'),
        'clinic'      => array('pinegrap-clinic', 'pinegrap-veterinary', 'pinegrap-salon'),
        'blog'        => array('pinegrap-magazine', 'pinegrap-news', 'pinegrap-podcast'),
    );

    foreach ($map as $purpose => $templates) {
        if (in_array((string) $template, $templates, true)) {
            return $purpose;
        }
    }

    return '';
}

/**
 * The purpose choices as the dialog shows them, in list order. Pure.
 *
 * @return array purpose => name, line, icon, tone
 */
function pg_launch_journey_purpose_cards()
{
    $card = function ($name, $line, $icon, $tone) {
        return array('name' => $name, 'line' => $line, 'icon' => $icon, 'tone' => $tone);
    };

    return array(
        'shop'        => $card(lang('Shop'), lang('Products sold and paid for online'), 'bi-bag', 'shop'),
        'company'     => $card(lang('Company'), lang('A business, its services and how to reach it'), 'bi-building', 'blue'),
        'association' => $card(lang('Association or charity'), lang('Members, donations and news'), 'bi-heart', 'rose'),
        'school'      => $card(lang('School or courses'), lang('Courses, lessons and students'), 'bi-mortarboard', 'violet'),
        'clinic'      => $card(lang('Clinic or bookings'), lang('Appointments and opening hours'), 'bi-calendar-check', 'teal'),
        'agency'      => $card(lang('Agency'), lang('A site built for a customer'), 'bi-briefcase', 'amber'),
        'blog'        => $card(lang('Blog or magazine'), lang('Articles and their readers'), 'bi-journal-richtext', 'content'),
        'other'       => $card(lang('Other'), lang('Something else: the general steps'), 'bi-three-dots', 'gray'),
    );
}

/**
 * A step's face on the set-up card: its name, icon tile and the screen its
 * "Do it" opens, at the section where the step is done. Pure.
 *
 * @param string $key a key of pg_launch_journey_catalogue()
 * @return array name, icon, tone, url
 */
function pg_launch_journey_step_face($key)
{
    $face = function ($name, $icon, $tone, $url) {
        return array('name' => $name, 'icon' => $icon, 'tone' => $tone, 'url' => $url);
    };

    switch ($key) {
        case 'domain_ssl':
            return $face(lang('Domain and SSL'), 'bi-shield-lock', 'teal', '#settings/general/pgset-server');
        case 'payment_live':
            return $face(lang('Payment live'), 'bi-credit-card', 'shop', '#settings/commerce/pgset-payments');
        case 'booking_hours':
            return $face(lang('Booking hours'), 'bi-calendar-check', 'teal', '#settings/bookings/pgset-booking-staff');
        case 'mail':
            return $face(lang('E-mail sending'), 'bi-envelope', 'blue', '#settings/email/pgset-mail-system');
        case 'legal':
            return $face(lang('Legal texts'), 'bi-file-earmark-text', 'violet', '#settings/privacy/pgset-legal');
        case 'search_open':
            return $face(lang('Search engines open'), 'bi-search', 'teal', '#settings/seo/pgset-seo');
        case 'search_tools':
            return $face(lang('Search Console and Analytics'), 'bi-graph-up', 'reports', 'connections.php');
        case 'backups':
            return $face(lang('Backups'), 'bi-hdd-stack', 'amber', '#settings/maintenance/pgset-backup');
        case 'cron':
            return $face(lang('Scheduled tasks'), 'bi-clock-history', 'system', '#settings/maintenance/pgset-cron');
        case 'team':
            return $face(lang('Team'), 'bi-people', 'team', 'add_user.php');
        case 'broken_links':
            return $face(lang('Broken internal links'), 'bi-link-45deg', 'warning', 'view_pages.php?filter=seo_broken_links');
        default:
            return $face(lang('Maintenance mode'), 'bi-cone-striped', 'warning', '#settings/maintenance/pgset-maintenance');
    }
}

/**
 * The card's line "Make your site from a description": it opens the New
 * design window on its "Describe it" start, whose brief takes the card's
 * purpose as its kind. It is not a step: it is not counted in "N of M" and
 * has no "Not for this site". Without Pinegrap AI the line is left out
 * (null), as the start itself is disabled. Pure.
 *
 * @param string $problem why "Describe it" cannot be offered
 *                        (pg_site_brief_problem()), '' when it can
 * @return array|null name, line, icon, tone, go, url
 */
function pg_launch_journey_describe_line($problem)
{
    if (!is_string($problem) || $problem !== '') {
        return null;
    }

    return array(
        'name' => lang('Make your site from a description'),
        'line' => lang('Tell what the business does. Pinegrap AI plans the pages for you to check before anything is made.'),
        'icon' => 'bi-stars',
        'tone' => 'violet',
        'go'   => lang('Start'),
        'url'  => 'view_system_styles.php?start=describe',
    );
}

/**
 * Where a row's "Do it" or "Fix" goes: the step's screen, or the switch the
 * row's reason names (the shop's switch, the bookable services). Pure.
 *
 * @param array $row a row of pg_launch_journey_state()
 * @return string
 */
function pg_launch_journey_step_url($row)
{
    $reason = isset($row['reason']) ? (string) $row['reason'] : '';

    if (($row['key'] === 'payment_live') && ($reason === 'shop_off')) {
        return '#settings/commerce/pgset-store';
    }

    if (($row['key'] === 'booking_hours') && ($reason === 'not_set_up')) {
        return '#settings/bookings/pgset-booking-services';
    }

    return pg_launch_journey_step_face($row['key'])['url'];
}

/**
 * The one line of state under a step's name: what is done, or what is
 * missing. The mail step names each of its three records. Pure.
 *
 * @param array $row   a row of pg_launch_journey_state()
 * @param array $facts
 * @return string
 */
function pg_launch_journey_step_line($row, $facts)
{
    if ($row['state'] === 'unknown') {
        return lang('Could not be checked');
    }

    if (($row['key'] === 'mail') && ($row['reason'] === 'dns')) {
        $parts = array();

        foreach (array('mail_spf' => 'SPF', 'mail_dkim' => 'DKIM', 'mail_dmarc' => 'DMARC') as $name => $record) {
            $state = isset($facts[$name]) ? (string) $facts[$name] : 'unknown';
            $words = array('ok' => '{var:1} found', 'error' => '{var:1} missing', 'warning' => '{var:1} needs a look');
            $parts[] = lang(array('string' => isset($words[$state]) ? $words[$state] : '{var:1} not checked', 'vars' => array($record)));
        }

        return implode(', ', $parts);
    }

    if (($row['key'] === 'broken_links') && ($row['reason'] === 'broken')) {
        $count = isset($facts['broken_links']) ? (int) $facts['broken_links'] : 0;

        return lang(array('string' => 'Internal links point nowhere on {var:1} page{suffix:1}', 'vars' => $count, 'suffix' => (($count == 1) ? '' : 's')));
    }

    $lines = array(
        'domain_ssl:'                      => lang('The site answers over HTTPS'),
        'domain_ssl:no_https'              => lang('The site does not answer over HTTPS'),
        'domain_ssl:certificate_expiring'  => lang('The certificate runs out soon'),
        'domain_ssl:certificate_expired'   => lang('The certificate has run out'),
        'payment_live:'                    => lang('Payments take real money'),
        'payment_live:no_online_payment'   => lang('No online payment is switched on'),
        'payment_live:shop_off'            => lang('The shop is switched off'),
        'payment_live:test_mode'           => lang('A payment way is in test mode'),
        'payment_live:mode_unknown'        => lang('The payment mode could not be read'),
        'booking_hours:'                   => lang('Bookable hours are set'),
        'booking_hours:no_hours'           => lang('No bookable hours are set'),
        'booking_hours:not_set_up'         => lang('Bookings are not set up'),
        'mail:'                            => lang('Mail is sent; SPF, DKIM and DMARC found'),
        'mail:not_sending'                 => lang('No way to send e-mail is set'),
        'legal:'                           => lang('The legal texts are in place'),
        'legal:no_personal_data_text'      => lang('No personal-data text is set'),
        'legal:no_consent'                 => lang('Cookie consent is off'),
        'legal:no_legal_documents'         => lang('Checkout documents are off'),
        'search_open:'                     => lang('Search engines may index the site'),
        'search_open:robots_closed'        => lang('robots.txt closes the whole site'),
        'search_open:home_noindex'         => lang('The home page asks search engines to stay away'),
        'search_tools:'                    => lang('Search Console and Analytics are connected'),
        'search_tools:no_search_console'   => lang('Search Console is not connected'),
        'search_tools:no_analytics'        => lang('Analytics is not connected'),
        'backups:'                         => lang('A recent backup is kept'),
        'backups:none'                     => lang('No backup yet'),
        'backups:old'                      => lang('The newest backup is over a month old'),
        'cron:'                            => lang('Scheduled tasks run'),
        'cron:stalled'                     => lang('A scheduled task stopped'),
        'cron:never'                       => lang('Scheduled tasks have never run'),
        'team:'                            => lang('Someone else has signed in'),
        'team:alone'                       => lang('Nobody else has signed in yet'),
        'maintenance:'                     => lang('Maintenance mode is off'),
        'maintenance:on'                   => lang('Maintenance mode is on'),
        'broken_links:'                    => lang('No internal link points nowhere'),
        'broken_links:never_checked'       => lang('The SEO check has never run on this site'),
    );

    $code = $row['key'] . ':' . (($row['state'] === 'done') ? '' : $row['reason']);

    return isset($lines[$code]) ? $lines[$code] : lang('Could not be checked');
}

/**
 * What the set-up card draws: the head ("5 of 9 done"), the status pill,
 * the rows (blockers first) each with its face, line, level and whether
 * "Not for this site" is offered (never on a blocker or a step done), and
 * whether the journey is finished. Pure.
 *
 * @param string $purpose
 * @param array  $facts
 * @param mixed  $hidden
 * @return array head, status (key, text, tone), rows, finished
 */
function pg_launch_journey_card($purpose, $facts, $hidden)
{
    $state = pg_launch_journey_state($purpose, $facts, $hidden);
    $pills = array(
        'blocked'     => array(lang('Blocked'), 'rose'),
        'in_progress' => array(lang('In progress'), 'amber'),
        'ready'       => array(lang('Ready'), 'teal'),
    );
    $rows = array();

    foreach ($state['rows'] as $row) {
        $face = pg_launch_journey_step_face($row['key']);

        $rows[] = array(
            'key'   => $row['key'],
            'name'  => $face['name'],
            'icon'  => $face['icon'],
            'tone'  => $face['tone'],
            'url'   => pg_launch_journey_step_url($row),
            'line'  => pg_launch_journey_step_line($row, $facts),
            'done'  => ($row['state'] === 'done'),
            'level' => $row['level'],
            'hide'  => ($row['level'] !== 'blocker') && ($row['state'] !== 'done'),
        );
    }

    return array(
        'head'     => lang(array('string' => '{var:1} of {var:2} done', 'vars' => array($state['done'], $state['total']))),
        'status'   => array('key' => $state['status'], 'text' => $pills[$state['status']][0], 'tone' => $pills[$state['status']][1]),
        'rows'     => $rows,
        'finished' => $state['finished'],
    );
}

/**
 * The changes a set-up card request asks for: a purpose from the list, one
 * step of the card hidden ("Not for this site"), or the card closed ("Hide
 * set-up"). Anything else asks for nothing. A hidden step that blocks stays
 * on the card (pg_launch_journey_state()). Pure.
 *
 * @param array $request purpose, hide, close
 * @return array changes for pg_launch_journey_stored_change(), empty for none
 */
function pg_launch_journey_request_changes($request)
{
    $changes = array();

    if (isset($request['purpose']) && in_array($request['purpose'], pg_launch_journey_purposes(), true)) {
        $changes['purpose'] = $request['purpose'];
    }

    if (isset($request['hide']) && (pg_launch_journey_hidden_clean(array($request['hide'])) !== array())) {
        $changes['hide'] = $request['hide'];
    }

    if (!empty($request['close'])) {
        $changes['closed'] = true;
    }

    return $changes;
}

/**
 * A robots.txt text with its catch-all closing taken out: every
 * "Disallow: /" in a group that names "*" is removed, and a group left with
 * no rule loses its User-agent lines too, so they do not join the next
 * group (RFC 9309). Every other line, comment and group stays as written.
 * Pure.
 *
 * @param string $text the operator's robots.txt extras
 * @return string
 */
function pg_launch_journey_robots_opened($text)
{
    $raw = preg_split('/\r\n|\r|\n/', (string) $text);
    $lines = pg_robots_record_lines($text);
    $groups = array();
    $group = null;

    foreach ($lines as $i => $line) {
        if ($line === '') {
            continue;
        }

        if (preg_match('/^user-agent\s*:\s*(\S+)/i', $line, $match)) {
            if (($group === null) || $group['rules']) {
                $groups[] = array('agents' => array(), 'agent_lines' => array(), 'rules' => array());
                $group = &$groups[count($groups) - 1];
            }

            $group['agents'][] = $match[1];
            $group['agent_lines'][] = $i;
            continue;
        }

        if ($group !== null) {
            $group['rules'][] = $i;
        }
    }
    unset($group);

    $drop = array();

    foreach ($groups as $group) {
        if (!in_array('*', $group['agents'], true)) {
            continue;
        }

        $kept = 0;

        foreach ($group['rules'] as $i) {
            if (preg_match('#^disallow\s*:\s*/$#i', $lines[$i])) {
                $drop[$i] = true;
            } else {
                $kept++;
            }
        }

        if (($kept === 0) && $group['rules']) {
            foreach ($group['agent_lines'] as $i) {
                $drop[$i] = true;
            }
        }
    }

    $out = array();

    foreach ($raw as $i => $line) {
        if (!isset($drop[$i])) {
            $out[] = $line;
        }
    }

    // Blank lines left where a group was are folded into one.
    return trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $out)));
}

/**
 * What "Go live" changes itself, in the order the confirmation names them:
 * maintenance mode turned off, the robots.txt rule closing the whole site
 * removed. Each only when it applies; a payment mode is never changed. Pure.
 *
 * @param array $facts
 * @return array list of 'maintenance', 'robots'
 */
function pg_launch_journey_golive_changes($facts)
{
    $changes = array();

    if (!empty($facts['maintenance'])) {
        $changes[] = 'maintenance';
    }

    if (isset($facts['robots_text']) && pg_robots_catch_all_closed((string) $facts['robots_text'])) {
        $changes[] = 'robots';
    }

    return $changes;
}

/**
 * The blockers "Go live" leaves: every blocker of the go-live check but
 * the two it removes itself. Pure.
 *
 * @param array $check pg_launch_journey_golive_check()
 * @return array rows
 */
function pg_launch_journey_golive_left($check)
{
    $left = array();

    foreach ($check['blockers'] as $row) {
        if (($row['key'] === 'maintenance') || (($row['key'] === 'search_open') && ($row['reason'] === 'robots_closed'))) {
            continue;
        }

        $left[] = $row;
    }

    return $left;
}

/**
 * What the go-live check dialog draws: "Must be fixed" and "Worth a look"
 * as rows (key, name, line, url, action: the button's words, '' for
 * "Fix"), the changes "Go live" makes as their sentences, and the blockers
 * it leaves, each named. With the SEO check never run, the broken-links row
 * runs it ("Analyze HTML Structure", which returns to the broken-links view
 * of the Pages list). Pure.
 *
 * @param string $purpose
 * @param array  $facts
 * @param mixed  $hidden
 * @param string $token   the session's form token, for the run link
 * @return array must, worth, changes, left
 */
function pg_launch_journey_golive_view($purpose, $facts, $hidden, $token = '')
{
    $check = pg_launch_journey_golive_check($purpose, $facts, $hidden);
    $rows = function ($list) use ($facts, $token) {
        $out = array();
        foreach ($list as $row) {
            $face = pg_launch_journey_step_face($row['key']);
            $url = pg_launch_journey_step_url($row);
            $action = '';
            if (($row['key'] === 'broken_links') && ($row['reason'] === 'never_checked')) {
                $url = 'audit_run.php?pass=structure&filter=seo_broken_links&token=' . rawurlencode((string) $token);
                $action = lang('Run the SEO check');
            }
            $out[] = array(
                'key'    => $row['key'],
                'name'   => $face['name'],
                'line'   => pg_launch_journey_step_line($row, $facts),
                'url'    => $url,
                'action' => $action,
            );
        }
        return $out;
    };
    $sentences = array(
        'maintenance' => lang('Maintenance mode turns off'),
        'robots'      => lang('Search engines may index the site'),
    );
    $changes = array();

    foreach (pg_launch_journey_golive_changes($facts) as $change) {
        $changes[] = $sentences[$change];
    }

    return array(
        'must'    => $rows($check['blockers']),
        'worth'   => $rows($check['warnings']),
        'changes' => $changes,
        'left'    => $rows(pg_launch_journey_golive_left($check)),
    );
}

/**
 * The one statement "Go live" makes its changes with: maintenance mode off
 * and the robots text opened, both or neither. Each is guarded by what was
 * read, so a second press that arrives after the first changes no row.
 * Pure.
 *
 * @param array  $changes     pg_launch_journey_golive_changes()
 * @param string $robots_text the robots text read
 * @param string $opened      pg_launch_journey_robots_opened() of it
 * @param int    $user_id
 * @return array|null array(sql, params); null when nothing changes
 */
function pg_launch_journey_golive_query($changes, $robots_text, $opened, $user_id)
{
    $sets = array();
    $where = array();
    $set_params = array();
    $where_params = array();

    if (in_array('maintenance', (array) $changes, true)) {
        $sets[] = "maintenance_mode = '0'";
        $where[] = "maintenance_mode <> '0'";
    }

    if (in_array('robots', (array) $changes, true)) {
        $sets[] = 'additional_robots_content = ?';
        $set_params[] = (string) $opened;
        $where[] = 'additional_robots_content = ?';
        $where_params[] = (string) $robots_text;
    }

    if (!$sets) {
        return null;
    }

    $sets[] = "last_modified_user_id = '" . (int) $user_id . "'";
    $sets[] = 'last_modified_timestamp = UNIX_TIMESTAMP()';

    return array('UPDATE config SET ' . implode(', ', $sets) . ' WHERE ' . implode(' AND ', $where), array_merge($set_params, $where_params));
}

/**
 * The Site Log lines of a "Go live": one per change this request made, then
 * the went-live line, with the blockers left named. A press that changed
 * nothing and found the go-live time already kept writes none. Pure.
 *
 * @param array $applied the changes this request made (maintenance, robots)
 * @param bool  $first   whether this request kept the go-live time
 * @param array $left    each blocker left as "name (line)"
 * @return string[]
 */
function pg_launch_journey_golive_log($applied, $first, $left)
{
    $lines = array();

    if (in_array('maintenance', (array) $applied, true)) {
        $lines[] = lang('Go live: maintenance mode was switched off.');
    }

    if (in_array('robots', (array) $applied, true)) {
        $lines[] = lang('Go live: the robots.txt rule closing the whole site to search engines was removed.');
    }

    if (!$applied && !$first) {
        return $lines;
    }

    $lines[] = $left
        ? lang(array('string' => 'The site went live with these left to fix: {var:1}', 'vars' => array(implode('; ', $left))))
        : lang('The site went live.');

    return $lines;
}

/**
 * Whether a "Go live" request may go ahead: with a blocker left it must
 * carry "I know, go live anyway". Pure.
 *
 * @param array $left    pg_launch_journey_golive_left()
 * @param array $request anyway
 * @return bool
 */
function pg_launch_journey_golive_allowed($left, $request)
{
    return !$left || !empty($request['anyway']);
}

/**
 * What GET /launch answers: the purpose the steps follow, the card's status
 * and count, every step of the purpose with its state, level, reason and
 * line ("hidden" for "Not for this site"), the go-live check's blockers and
 * warnings, and what "Go live" would change itself. The same rules as the
 * set-up card and the go-live check: nothing is judged here again. live_at
 * stays a timestamp; the caller writes it as a moment. Pure.
 *
 * @param array $stored pg_launch_journey_stored_read()
 * @param array $facts
 * @return array
 */
function pg_launch_journey_report($stored, $facts)
{
    $purpose = ($stored['purpose'] !== '') ? $stored['purpose'] : 'other';
    $hidden = pg_launch_journey_hidden_clean($stored['hidden']);
    $state = pg_launch_journey_state($purpose, $facts, $hidden);
    $check = pg_launch_journey_golive_check($purpose, $facts, $hidden);
    $row_out = function ($row) use ($facts, $hidden) {
        return array(
            'key'    => $row['key'],
            'name'   => pg_launch_journey_step_face($row['key'])['name'],
            'state'  => $row['state'],
            'level'  => $row['level'],
            'reason' => $row['reason'],
            'line'   => pg_launch_journey_step_line($row, $facts),
            'hidden' => in_array($row['key'], $hidden, true),
        );
    };
    $steps = array();

    foreach (pg_launch_journey_steps($purpose) as $key) {
        $steps[] = $row_out(array('key' => $key) + pg_launch_journey_rule($key, $facts, $purpose));
    }

    return array(
        'purpose'        => $purpose,
        'purpose_chosen' => ($stored['purpose'] !== ''),
        'status'         => $state['status'],
        'done'           => $state['done'],
        'total'          => $state['total'],
        'finished'       => $state['finished'],
        'live_at'        => (int) $stored['live_at'],
        'steps'          => $steps,
        'blockers'       => array_map($row_out, $check['blockers']),
        'warnings'       => array_map($row_out, $check['warnings']),
        'go_live_changes' => pg_launch_journey_golive_changes($facts),
    );
}
