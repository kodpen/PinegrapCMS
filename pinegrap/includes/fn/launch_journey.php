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
 * - booking_hours    bool, bookable hours are set
 * - backup           pg_backup_age_state(): pass, warn, fail
 * - cron             ok, stalled (a job that ran stopped), never, unknown
 * - team_signed_in   bool, another user signed in at least once
 * - maintenance      bool, maintenance mode is on
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
 *         screen asks (maintenance mode, which "Go live" turns off itself)
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
 * key is test), PayTR and PayPal by their own mode, iyzico and the classic
 * gateways by the Ecommerce card's gateway mode. Pure.
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

    if (($driver === 'paytr') || ($driver === 'paypal')) {
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
                return pg_launch_journey_answer('todo', 'warning', 'no_online_payment');
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
 * The go-live check: blockers ("Must be fixed") and warnings ("Worth a
 * look"), each a list of rows (key, state, level, reason) in the purpose's
 * order, maintenance mode first among the blockers. A hidden step's warning
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

    foreach (array_merge(array('maintenance'), pg_launch_journey_steps($purpose)) as $key) {
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
