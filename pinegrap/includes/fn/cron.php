<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: Scheduled jobs: the job list, dispatch, run records, WAF AI range refresh.
//
// Loaded by functions.php through require_once, never on its own. The
// functions were moved here from functions.php as they were; PG_FUNCTIONS_DIR
// is the software directory (what dirname(__FILE__) was in functions.php),
// see the note at the top of functions.php.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * Record that a scheduled job finished.
 *
 * The cron entries themselves live outside the software — a crontab, Windows
 * Task Scheduler, a hosting control panel — so nothing here can ask whether a
 * job is scheduled. Recording that one finished is the only evidence the panel
 * will ever have, and it is enough: a job that used to finish and no longer
 * does is a job whose schedule has stopped.
 *
 * Written on COMPLETION, not at the start. One timestamp can carry one fact,
 * and "the work got done" is the more useful of the two: a job that is
 * scheduled correctly but fatals halfway through would report green forever if
 * the write happened first, which is the worst thing a health indicator can do.
 *
 * Not conditional on the request being CLI. Cron reaches these scripts as a
 * shell command on some hosts and as an HTTP request on others, and a manual
 * run from the panel still means the work happened — which is the question
 * this answers. Attributing the run to a scheduler rather than a person would
 * be a distinction without a difference here.
 *
 * Silent when the table is absent: these calls sit at the end of scripts that
 * have to keep working on an installation where the 2026.4.2 upgrade has not
 * been run yet.
 *
 * @param string $job_name Script base name, e.g. "email_campaign_job".
 * @return bool True when the run was recorded.
 */
function pg_cron_ran($job_name)
{
    static $table_exists = null;

    if ($table_exists === null) {
        $table_exists = (bool) db_item("SHOW TABLES LIKE 'cron_runs'");
    }

    if (!$table_exists) {
        return false;
    }

    db(
        "INSERT INTO cron_runs (job_name, last_run_at)
         VALUES ('" . e($job_name) . "', UNIX_TIMESTAMP())
         ON DUPLICATE KEY UPDATE last_run_at = UNIX_TIMESTAMP()");

    return true;
}

/**
 * Last completion time per job, keyed by job name.
 *
 * Returns null — not an empty array — when the table does not exist yet, so a
 * caller can tell "nothing recorded" apart from "cannot know" and stay quiet
 * rather than reporting every job as broken on an installation that simply has
 * not been upgraded.
 *
 * @return array<string,int>|null
 */
function pg_cron_last_runs()
{
    if (!db_item("SHOW TABLES LIKE 'cron_runs'")) {
        return null;
    }

    $runs = array();

    foreach (db_items("SELECT job_name, last_run_at FROM cron_runs") as $row) {
        $runs[$row['job_name']] = (int) $row['last_run_at'];
    }

    return $runs;
}

/**
 * Catalogue of the scheduled jobs this software ships with.
 *
 * One table, three readers: the settings screen builds its switches from it,
 * the maintenance panel builds its health list from it, and the general job
 * dispatches from it. A job present in one of those places and missing from
 * another is the failure this exists to prevent.
 *
 * Keys are the names each script writes through pg_cron_ran(), which is what
 * ties a row in cron_runs back to an entry here.
 *
 * - label       Shown to the operator.
 * - script      File to run, relative to this directory.
 * - interval    How long the dispatcher waits before running it again, in
 *               seconds. Follows the recommended schedule printed next to the
 *               job's command on the settings screen.
 * - stale_after How long the maintenance panel waits before calling a job
 *               that used to finish stalled. Deliberately generous: an alert
 *               that cries wolf teaches the operator to scroll past it.
 * - dispatch    False for the general job, which is the host rather than a
 *               candidate.
 * - lane        Concurrency class for the dispatcher, 'light' or 'heavy'. At
 *               most one job per lane holds a dispatch lock at a time, so a
 *               long heavy job (a backup, a full SEO pass) runs alongside the
 *               short light ones instead of holding them all up, while two
 *               heavy jobs never overlap. Missing means 'light'.
 * - lock        Optional dispatch lock length in seconds, for a job that can
 *               outlast JOB_DISPATCH_LOCK_SECONDS. The lock is the safety net
 *               for a process killed outright; a lock shorter than the run
 *               would let the next tick start the same job a second time.
 *
 * Order is the display order, and it is also the tie-break used when several
 * jobs are equally overdue - which is why the SEO score job is listed before
 * the structure job, whose input it produces.
 *
 * @return array<string,array>
 */
function pg_cron_jobs()
{
    return array(
        'job' => array(
            'label'       => lang('General job'),
            'script'      => 'job.php',
            'interval'    => 60,
            'stale_after' => 21600,
            'dispatch'    => false,
            'lane'        => 'light',
        ),
        'email_campaign_job' => array(
            'label'       => lang('Email Campaigns'),
            'script'      => 'email_campaign_job.php',
            'interval'    => 300,
            'stale_after' => 21600,
            'dispatch'    => true,
            'lane'        => 'light',
        ),
        'recurring_payment_job' => array(
            'label'       => lang('Recurring payments'),
            'script'      => 'recurring_payment_job.php',
            'interval'    => 86400,
            'stale_after' => 172800,
            'dispatch'    => true,
            'lane'        => 'light',
        ),
        'membership_job' => array(
            'label'       => lang('Memberships'),
            'script'      => 'membership_job.php',
            'interval'    => 86400,
            'stale_after' => 172800,
            'dispatch'    => true,
            'lane'        => 'light',
        ),
        'update_exchange_rates' => array(
            'label'       => lang('Exchange rates'),
            'script'      => 'update_exchange_rates.php',
            'interval'    => 86400,
            'stale_after' => 172800,
            'dispatch'    => true,
            'lane'        => 'light',
        ),
        'update_search_index' => array(
            'label'       => lang('Search index'),
            'script'      => 'update_search_index.php',
            'interval'    => 86400,
            'stale_after' => 172800,
            'dispatch'    => true,
            'lane'        => 'heavy',
        ),
        'seo_score_job' => array(
            'label'       => lang('SEO scores'),
            'script'      => 'seo_score_job.php',
            'interval'    => 86400,
            'stale_after' => 172800,
            'dispatch'    => true,
            'lane'        => 'heavy',
        ),
        'seo_analyze_job' => array(
            'label'       => lang('SEO structure'),
            'script'      => 'seo_analyze_job.php',
            'interval'    => 86400,
            'stale_after' => 172800,
            'dispatch'    => true,
            'lane'        => 'heavy',
        ),
        'auto_backup' => array(
            'label'       => lang('Auto backup'),
            'script'      => 'auto_backup.php',
            'interval'    => 604800,
            'stale_after' => 1209600,
            'dispatch'    => true,
            'lane'        => 'heavy',
            'lock'        => 14400,
        ),
        'waf_ranges_job' => array(
            'label'       => lang('Bot IP lists'),
            'script'      => 'waf_ranges_job.php',
            'interval'    => 86400,
            'stale_after' => 604800,
            'dispatch'    => true,
            'lane'        => 'light',
        ),
        // Event notifications wait in a queue until this runs, so its interval
        // is the delay an integration sees between something happening here and
        // hearing about it. A minute is the shortest the dispatcher is worth
        // running at; the queue is empty on a site with no webhooks, which is
        // one indexed read.
        // Runs inline, not in the rotation.
        //
        // The rotation hands out one job per tick and holds a site-wide lock
        // while it runs, so a webhook could sit behind a backup for the length
        // of that backup. The whole value of a webhook is that it is prompt,
        // and this one is cheap enough not to need a turn: on a site with no
        // subscriptions it is a single indexed read. job.php therefore calls
        // it on every tick, and the dispatcher skips it.
        //
        // It stays a real script as well, so an operator who wants delivery
        // within a minute can point a dedicated cron entry at
        // api_webhook_job.php instead of waiting for the general job.
        'api_webhook_job' => array(
            'label'       => lang('Webhook delivery'),
            'script'      => 'api_webhook_job.php',
            'interval'    => 60,
            'stale_after' => 3600,
            'dispatch'    => true,
            'lane'        => 'light',
            'inline'      => true,
        ),
        // Marketplace synchronisation. In the rotation rather than inline, and
        // that is the difference between it and the two queues above.
        //
        // A webhook is a message and is worth being prompt about; a stock level
        // is a state, and being five minutes behind on it costs nothing that
        // being one minute behind would save. What it does cost is a request
        // against a rate limit counted per minute, and a batch that can carry a
        // thousand products - so running it less often makes the batches larger
        // and the calls fewer, which is the right direction for both sides.
        //
        // stale_after is generous because a shop with no marketplace account
        // has nothing for this to do and should not be told a job is overdue.
        'api_sync_job' => array(
            'label'       => lang('Marketplace synchronisation'),
            'script'      => 'api_sync_job.php',
            'interval'    => 300,
            'stale_after' => 86400,
            'dispatch'    => true,
            'lane'        => 'light',
        ),
        // Device notifications wait in the same shape of queue and for the same
        // reason: the request that caused one must not wait on a push service.
        // Runs inline, not in the rotation - a banner that arrives after the
        // operator has already seen the order is not worth sending.
        //
        // The delay an operator feels is this interval plus the wait a chat
        // message serves before it counts as unanswered.
        'push_job' => array(
            'label'       => lang('Device notifications'),
            'script'      => 'push_job.php',
            'interval'    => 60,
            'stale_after' => 3600,
            'dispatch'    => true,
            'lane'        => 'light',
            'inline'      => true,
        ),
        // Queued e-mail (includes/fn/mail_queue.php). Inline, and with no
        // switch: job.php works the queue on every run unconditionally.
        //
        // email() only queues a message while the general job has finished
        // in the last fifteen minutes, and sends it at once otherwise. The
        // queue therefore exists exactly when the general job runs, and the
        // general job is what empties it. A switch an operator could turn off
        // would break that promise: rows would keep being written and never
        // sent. dispatch false keeps it off the settings screen's switches
        // and out of the rotation.
        //
        // mail_job.php remains a script of its own for an operator who wants
        // a dedicated cron entry for mail.
        'mail_job' => array(
            'label'       => lang('Mail queue'),
            'script'      => 'mail_job.php',
            'interval'    => 60,
            'stale_after' => 3600,
            'dispatch'    => false,
            'inline'      => true,
        ),
        // Overdue receivable reminders. Once a day is the finest the setting
        // offers; the script itself decides whether this period's digest is
        // still owed, so the tick it lands on does not matter.
        'erp_overdue_job' => array(
            'label'       => lang('Overdue receivable reminders'),
            'script'      => 'erp_overdue_job.php',
            'interval'    => 86400,
            'stale_after' => 172800,
            'dispatch'    => true,
            'lane'        => 'light',
        ),
        // The accountant's monthly pack. Daily, and the script decides: it
        // builds last month's pack once, on the first run of a month that
        // finds it missing, and only when the accountant card asks for it.
        'erp_accountant_job' => array(
            'label'       => lang('Accountant pack'),
            'script'      => 'erp_accountant_job.php',
            'interval'    => 86400,
            'stale_after' => 172800,
            'dispatch'    => true,
            'lane'        => 'light',
        ),
        // Repeating invoices (maintenance contracts, rents): writes each one
        // that has come due. Daily; a visit to the invoice list runs it too.
        'erp_invoice_recurring_job' => array(
            'label'       => lang('Repeating invoices'),
            'script'      => 'erp_invoice_recurring_job.php',
            'interval'    => 86400,
            'stale_after' => 172800,
            'dispatch'    => true,
            'lane'        => 'light',
        ),
        // Low stock notices: the products newly at or below their minimum,
        // to the bell and the subscribed devices. Hourly, so a sale anywhere
        // is told within the hour; with the notice off, or nothing new, a run
        // is one or two indexed reads.
        'erp_stock_alert_job' => array(
            'label'       => lang('Low stock notices'),
            'script'      => 'erp_stock_alert_job.php',
            'interval'    => 3600,
            'stale_after' => 172800,
            'dispatch'    => true,
            'lane'        => 'light',
        ),
        // Repeating expenses (rent, subscriptions): writes each one that has
        // come due. Daily; a visit to the expenses list runs it too.
        'erp_expense_recurring_job' => array(
            'label'       => lang('Repeating expenses'),
            'script'      => 'erp_expense_recurring_job.php',
            'interval'    => 86400,
            'stale_after' => 172800,
            'dispatch'    => true,
            'lane'        => 'light',
        ),
        // Repeating workspace tasks, and the requests to Claude and Pinegrap AI
        // that waited. Every five minutes, so a copy due today is handed out in
        // the first minutes of the day and a request held back by a failed call
        // is tried again soon after; a run with nothing due is one indexed read.
        //
        // Scheduled actions are not left to this turn. They are set for a
        // minute, and a turn in the rotation can come late behind a long job,
        // so job.php runs them itself on every tick.
        'workspace_recurring_job' => array(
            'label'       => lang('Repeating tasks'),
            'script'      => 'workspace_recurring_job.php',
            'interval'    => 300,
            'stale_after' => 172800,
            'dispatch'    => true,
            'lane'        => 'light',
        ),
        // "Update translations" jobs the server engines work (Google Cloud
        // Translation), and the ones left open for a day to close. Every five
        // minutes: a job started from the panel gets twenty seconds there and
        // the rest is finished here; a run with nothing open is one indexed
        // read.
        'translation_job' => array(
            'label'       => lang('Translations'),
            'script'      => 'translation_job.php',
            'interval'    => 300,
            'stale_after' => 172800,
            'dispatch'    => true,
            'lane'        => 'light',
        ),
    );
}

/**
 * Published IP range lists for AI bots, one URL per waf_bot_ranges row.
 *
 * These are the operators' own declarations of every address their bots
 * egress from. OpenAI publishes one file per bot; Anthropic one combined file
 * for ClaudeBot, Claude-User and Claude-SearchBot (the split of intent between
 * those comes from the UA token - the IP only proves origin); Perplexity one
 * file per bot. All share the same shape: {"prefixes":[{"ipv4Prefix":...}]}.
 */
function pg_waf_ai_range_sources()
{
    return array(
        'openai-chatgpt-user' => 'https://openai.com/chatgpt-user.json',
        'openai-searchbot'    => 'https://openai.com/searchbot.json',
        'anthropic-bots'      => 'https://claude.com/crawling/bots.json',
        'perplexity-user'     => 'https://www.perplexity.ai/perplexity-user.json',
    );
}

/**
 * Refresh the stored AI bot IP ranges from the operators' published lists.
 *
 * waf.php only READS these rows; every outbound fetch lives here, because
 * this function runs from admin screens, the WAF dashboard widgets and the
 * daily cron job - all places where functions.php is loaded and a couple of
 * seconds of network wait is affordable. Nothing in the visitor request path
 * ever waits on a remote server.
 *
 * Failure discipline: a failed or empty fetch leaves the stored row exactly
 * as it was. A stale list still verifies positives correctly (operators grow
 * their lists far more than they shrink them), while overwriting a good list
 * with a bad fetch would instantly misclassify every real AI fetcher as
 * forged. waf_verify_bot_ranges() handles staleness on its own: after 30 days
 * without a refresh a miss stops being treated as proof of forgery.
 *
 * @param  bool $force Skip the attempt throttle and per-row freshness checks
 *                     (the settings-screen button; the daily job passes false)
 * @return array|false Per-provider result rows, or false when unavailable
 */
function pg_waf_refresh_ai_ranges($force = false)
{
    // Schema gate: both objects arrive with 2026.4.4. On an install that took
    // the code without the upgrade this quietly does nothing.
    if (!db_item("SHOW TABLES LIKE 'waf_bot_ranges'")) {
        return false;
    }

    $has_throttle_column = (bool) db_item("SHOW COLUMNS FROM config LIKE 'waf_ai_ranges_checked'");

    // Attempt throttle, 6 hours. The callers piggybacking on panel traffic
    // must not turn every dashboard load into four outbound HTTP requests
    // when the lists' servers are down - the timestamp records the ATTEMPT,
    // not the success, so a broken source is retried a few times a day
    // rather than on every page view.
    if (!$force && $has_throttle_column) {
        $checked = (int) db_value("SELECT waf_ai_ranges_checked FROM config");

        if ($checked > time() - 21600) {
            return false;
        }
    }

    if ($has_throttle_column) {
        db("UPDATE config SET waf_ai_ranges_checked = " . time());
    }

    $current = array();
    $rows = db_items("SELECT provider, fetched_at FROM waf_bot_ranges");

    if (is_array($rows)) {
        foreach ($rows as $row) {
            $current[$row['provider']] = (int) $row['fetched_at'];
        }
    }

    $results = array();

    foreach (pg_waf_ai_range_sources() as $provider => $url) {

        $fetched_at = isset($current[$provider]) ? $current[$provider] : 0;

        // Fresh enough for a daily cadence; the button overrides.
        if (!$force && $fetched_at > time() - 72000) {
            $results[$provider] = array('status' => 'fresh', 'fetched_at' => $fetched_at);
            continue;
        }

        $ch = curl_init($url);

        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 12,
        ));

        // Sent with no User-Agent a request looks like an anonymous client to
        // the receiving server's firewall - how Pinegrap once blocked its own
        // licence checks.
        curl_setopt($ch, CURLOPT_USERAGENT, function_exists('pinegrap_user_agent') ? pinegrap_user_agent() : 'Pinegrap');

        // This channel decides who gets to impersonate a search-visible bot,
        // so it verifies TLS like the update channel does.
        if (function_exists('pg_curl_tls')) {
            pg_curl_tls($ch);
        }

        $body  = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $http  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $http !== 200 || strlen($body) > 1048576) {

            // The reason is carried back to the settings screen. "4 lists
            // failed" with no cause sends the operator hunting blind; "cURL
            // 60" plus the CA-bundle hint names the fix.
            if ($errno) {
                $reason = 'cURL ' . $errno . ': ' . $error;

                if (function_exists('pg_curl_tls_hint')) {
                    $reason .= pg_curl_tls_hint($errno);
                }
            } elseif ($http !== 200) {
                $reason = 'HTTP ' . $http;
            } else {
                $reason = 'oversized response';
            }

            $results[$provider] = array('status' => 'failed', 'fetched_at' => $fetched_at, 'reason' => $reason);
            continue;
        }

        $decoded = json_decode($body, true);

        if (!is_array($decoded) || empty($decoded['prefixes']) || !is_array($decoded['prefixes'])) {
            $results[$provider] = array('status' => 'failed', 'fetched_at' => $fetched_at, 'reason' => 'invalid JSON');
            continue;
        }

        // Validate every prefix down to inet_pton. The stored list feeds a
        // security decision on the visitor path; a malformed entry must fail
        // here, at fetch time, not there.
        $prefixes = array();

        foreach ($decoded['prefixes'] as $entry) {

            if (!is_array($entry)) {
                continue;
            }

            foreach (array('ipv4Prefix', 'ipv6Prefix') as $key) {

                if (empty($entry[$key]) || !is_string($entry[$key])) {
                    continue;
                }

                $cidr = trim($entry[$key]);
                $parts = explode('/', $cidr, 2);
                $address_bin = @inet_pton($parts[0]);

                if ($address_bin === false) {
                    continue;
                }

                $max_bits = strlen($address_bin) * 8;

                if (isset($parts[1])
                    && (!ctype_digit($parts[1]) || (int) $parts[1] > $max_bits)
                ) {
                    continue;
                }

                $prefixes[$cidr] = $cidr;
            }
        }

        $prefixes = array_values($prefixes);

        // An empty result is a failed fetch wearing a 200 status. Keep the
        // old row. The cap bounds the stored blob against a compromised or
        // broken source - the real lists run between 4 and ~200 entries.
        if (!$prefixes || count($prefixes) > 4096) {
            $results[$provider] = array('status' => 'failed', 'fetched_at' => $fetched_at, 'reason' => 'no valid prefixes');
            continue;
        }

        db(
            "INSERT INTO waf_bot_ranges (provider, fetched_at, prefixes)
             VALUES (
                 '" . e($provider) . "',
                 " . time() . ",
                 '" . e(json_encode($prefixes)) . "')
             ON DUPLICATE KEY UPDATE
                 fetched_at = VALUES(fetched_at),
                 prefixes = VALUES(prefixes)"
        );

        $results[$provider] = array(
            'status'     => 'updated',
            'fetched_at' => time(),
            'count'      => count($prefixes),
        );
    }

    return $results;
}

/**
 * Human wording for a dispatch interval, for the settings screen.
 *
 * A minute, five minutes, a day and a week get a sentence of their own.
 * Anything else shorter than an hour is said in minutes and the rest in
 * hours: rounded to hours, a job that runs every minute was once shown as
 * running once an hour.
 *
 * @param int $seconds
 * @return string
 */
function pg_cron_interval_label($seconds)
{
    $seconds = (int) $seconds;

    if ($seconds <= 60) {
        return lang('every minute');
    }

    if ($seconds == 300) {
        return lang('every 5 minutes');
    }

    if ($seconds < 3600) {
        return lang(array(
            'string' => 'every {var:1} minutes',
            'vars'   => (int) round($seconds / 60),
        ));
    }

    if ($seconds == 86400) {
        return lang('once a day');
    }

    if ($seconds == 604800) {
        return lang('once a week');
    }

    $hours = (int) round($seconds / 3600);

    if ($hours < 1) {
        $hours = 1;
    }

    return lang(array(
        'string' => 'every {var:1} hour{suffix:1}',
        'vars'   => $hours,
        'suffix' => (($hours == 1) ? '' : 's'),
    ));
}

/**
 * Job names the operator has allowed the general job to dispatch.
 *
 * Empty whenever the master switch is off, so a caller never has to check
 * both. The constants come from the config row like every other feature flag,
 * and default to off when the columns are not there yet.
 *
 * @return array<int,string>
 */
function pg_cron_dispatch_list()
{
    if (!defined('JOB_DISPATCH_ENABLED') || !JOB_DISPATCH_ENABLED) {
        return array();
    }

    if (!defined('JOB_DISPATCH') || (JOB_DISPATCH === '')) {
        return array();
    }

    $names = array();

    foreach (explode(',', JOB_DISPATCH) as $name) {

        $name = trim($name);

        if ($name !== '') {
            $names[] = $name;
        }
    }

    return $names;
}

/**
 * Whether the operator switched a particular scheduled job on.
 *
 * The inline jobs need this on its own: they never reach pg_cron_dispatch_next(),
 * which is where the selection normally happens.
 */
function pg_cron_job_is_enabled($name)
{
    return in_array($name, pg_cron_dispatch_list(), true);
}

/**
 * Whether cron_runs carries the per-job dispatch lock (2026.4.8).
 *
 * Until the upgrade adds the column the dispatcher keeps the site-wide lock in
 * config.job_dispatch_lock_until; reading a column that is not there would end
 * the general job in output_error() on every tick. Asked once per request: the
 * dispatcher and the shutdown handler that releases its lock must agree on
 * which lock was taken.
 *
 * @return bool
 */
function pg_cron_lock_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = db_item("SHOW TABLES LIKE 'cron_runs'")
            && db_item("SHOW COLUMNS FROM cron_runs WHERE Field = 'locked_until'");
    }

    return $ready;
}

/**
 * Run record and dispatch lock per job, keyed by job name.
 *
 * pg_cron_last_runs() answers the health readers with timestamps alone; the
 * dispatcher needs the lock beside each one. locked_until reads as 0 while the
 * column is not there yet.
 *
 * @return array<string,array{last_run_at:int,locked_until:int}>|null Null when
 *         the table does not exist.
 */
function pg_cron_run_rows()
{
    if (!db_item("SHOW TABLES LIKE 'cron_runs'")) {
        return null;
    }

    $lock_column = pg_cron_lock_ready() ? 'locked_until' : '0 AS locked_until';

    $runs = array();

    foreach (db_items("SELECT job_name, last_run_at, " . $lock_column . " FROM cron_runs") as $row) {
        $runs[$row['job_name']] = array(
            'last_run_at'  => (int) $row['last_run_at'],
            'locked_until' => (int) $row['locked_until'],
        );
    }

    return $runs;
}

/**
 * Dispatch lane of a catalogue entry: 'heavy' when it says so, otherwise
 * 'light', so an entry written without a lane joins the short jobs.
 *
 * @param array $job One pg_cron_jobs() entry.
 * @return string
 */
function pg_cron_lane($job)
{
    return (isset($job['lane']) && ($job['lane'] === 'heavy')) ? 'heavy' : 'light';
}

/**
 * The job each dispatch lane would run next. Pure: no database, no files.
 *
 * A lane is a concurrency class: while any job of a lane holds an unexpired
 * lock, that lane hands out nothing. Otherwise its candidate is the due job
 * that has waited longest - the smallest last_run_at, with the catalogue order
 * deciding a tie because the comparison is strict. A job is due once its
 * interval has passed since it last finished.
 *
 * The lock check covers every dispatchable job in $jobs, $allowed or not: a
 * job switched off while it runs is still running, and its lane is still
 * busy. $allowed only narrows who may be chosen; the caller puts there the
 * jobs that are switched on, present on disk and not held back by their own
 * config gate.
 *
 * @param array    $jobs    Catalogue in pg_cron_jobs() form. Reads dispatch,
 *                          inline, interval and lane (missing lane = light).
 * @param array    $runs    name => array('last_run_at' => int, 'locked_until' => int).
 * @param int      $now     Unix time.
 * @param array|null $allowed Names that may be chosen; null for all.
 * @return array{light:string|null,heavy:string|null}
 */
function pg_cron_pick($jobs, $runs, $now, $allowed = null)
{
    $now = (int) $now;
    $locked = array('light' => false, 'heavy' => false);
    $due = array('light' => array(), 'heavy' => array());

    foreach ($jobs as $name => $job) {

        // The general job is the host, and an inline job is run by job.php
        // itself on every tick; neither takes a turn or a lock here.
        if (empty($job['dispatch']) || !empty($job['inline'])) {
            continue;
        }

        $lane = pg_cron_lane($job);

        $last_run = isset($runs[$name]['last_run_at']) ? (int) $runs[$name]['last_run_at'] : 0;
        $locked_until = isset($runs[$name]['locked_until']) ? (int) $runs[$name]['locked_until'] : 0;

        if ($locked_until > $now) {
            $locked[$lane] = true;
            continue;
        }

        if (($allowed !== null) && !in_array($name, $allowed, true)) {
            continue;
        }

        // A job that also has its own crontab entry writes last_run_at itself,
        // whoever started it, so it never looks overdue here and is never run
        // twice.
        if (($now - $last_run) < (int) $job['interval']) {
            continue;
        }

        $due[$lane][$name] = $last_run;
    }

    $pick = array('light' => null, 'heavy' => null);

    foreach (array('light', 'heavy') as $lane) {

        if ($locked[$lane]) {
            continue;
        }

        $selected_run = 0;

        foreach ($due[$lane] as $name => $last_run) {

            if (($pick[$lane] === null) || ($last_run < $selected_run)) {
                $pick[$lane] = $name;
                $selected_run = $last_run;
            }
        }
    }

    return $pick;
}

/**
 * Choose the job the general job should run in this tick, and take its lock.
 *
 * Returns a path rather than running anything itself, because the caller has
 * to include it at global scope: included from inside a function, a job's
 * top-level code would execute in that function's local scope and every
 * variable it sets would be invisible to the functions it calls.
 *
 * Still one job per tick, though two lanes may be busy at once. Several of the
 * dispatched scripts call exit() from inside their own flow, and each one is
 * written as a whole request, so two of them cannot be included one after the
 * other in the same process. Parallelism comes from the ticks instead: tick N
 * starts the backup in the heavy lane, tick N+1 finds that lane locked and
 * hands a light job its turn while the backup is still running.
 *
 * @return string Absolute path of the script to include, or '' for nothing.
 */
function pg_cron_dispatch_next()
{
    $allowed = pg_cron_dispatch_list();

    if (!$allowed) {
        return '';
    }

    if (!pg_cron_lock_ready()) {
        return pg_cron_dispatch_next_legacy();
    }

    // null means the cron_runs table does not exist, and that table is the
    // whole basis of the decision below. Without it every job would look
    // overdue on every tick.
    $runs = pg_cron_run_rows();

    if ($runs === null) {
        return '';
    }

    $jobs = pg_cron_jobs();
    $candidates = array();

    foreach ($jobs as $name => $job) {

        if (empty($job['dispatch']) || !empty($job['inline'])) {
            continue;
        }

        if (!in_array($name, $allowed, true)) {
            continue;
        }

        if (!file_exists(PG_FUNCTIONS_DIR . '/' . $job['script'])) {
            continue;
        }

        // A job whose config gate is closed would start, log a complaint and
        // exit without doing anything. Skipping it here keeps that noise out
        // of the activity log entirely.
        if (!pg_cron_job_active($name)) {
            continue;
        }

        $candidates[] = $name;
    }

    $pick = pg_cron_pick($jobs, $runs, time(), $candidates);

    // Both lanes free and both with a job due: the one that has waited longer
    // goes first, light on a tie. The other lane is offered again next tick.
    $selected = $pick['light'];

    if ($pick['heavy'] !== null) {

        $light_run = ($selected !== null && isset($runs[$selected])) ? $runs[$selected]['last_run_at'] : 0;
        $heavy_run = isset($runs[$pick['heavy']]) ? $runs[$pick['heavy']]['last_run_at'] : 0;

        if (($selected === null) || ($heavy_run < $light_run)) {
            $selected = $pick['heavy'];
        }
    }

    if ($selected === null) {
        return '';
    }

    // The lock expires on its own. pg_cron_dispatch_finished() releases it at
    // the end of a normal run and after a fatal error, but a process killed
    // outright runs no shutdown handler, and a lock nothing can clear would
    // keep the job, and its lane, out of the rotation for good.
    if (isset($jobs[$selected]['lock'])) {
        $lock_seconds = (int) $jobs[$selected]['lock'];
    } else {
        $lock_seconds = defined('JOB_DISPATCH_LOCK_SECONDS') ? (int) JOB_DISPATCH_LOCK_SECONDS : 3600;
    }

    if ($lock_seconds < 60) {
        $lock_seconds = 60;
    }

    // Claimed in one statement, because a job that never ran has no row yet
    // and a read followed by a write would let two ticks both take it. The new
    // row carries last_run_at 0, which every reader takes for "never ran".
    //
    // With ON DUPLICATE KEY UPDATE, affected rows is 1 for an insert, 2 for an
    // update that changed the row, and 0 when the row was left as it was -
    // here, when the IF() kept an unexpired lock held by another tick. Two
    // ticks claiming in the same second also see 0 on the second claim,
    // because the first one's lock has not expired.
    db(
        "INSERT INTO cron_runs (job_name, last_run_at, locked_until)
         VALUES ('" . e($selected) . "', 0, UNIX_TIMESTAMP() + " . (int) $lock_seconds . ")
         ON DUPLICATE KEY UPDATE
             locked_until = IF(locked_until < UNIX_TIMESTAMP(), VALUES(locked_until), locked_until)");

    if (mysqli_affected_rows(db::$con) < 1) {
        return '';
    }

    // The claim is atomic for the job, the lane check above was a read. Two
    // ticks that read the same moment can claim two different jobs of one
    // lane; whichever sees the other's lock after claiming gives its own
    // back. Both may give back, which runs nothing this tick - the safe
    // direction.
    $lane = pg_cron_lane($jobs[$selected]);
    $lane_mates = array();

    foreach ($jobs as $name => $job) {

        if (($name === $selected) || empty($job['dispatch']) || !empty($job['inline'])) {
            continue;
        }

        if (pg_cron_lane($job) === $lane) {
            $lane_mates[] = "'" . e($name) . "'";
        }
    }

    if ($lane_mates && db_value(
        "SELECT COUNT(*)
         FROM cron_runs
         WHERE
             (job_name IN (" . implode(', ', $lane_mates) . "))
             AND (locked_until > UNIX_TIMESTAMP())")
    ) {
        db("UPDATE cron_runs SET locked_until = 0 WHERE job_name = '" . e($selected) . "'");

        return '';
    }

    pg_cron_dispatch_current($selected);

    return PG_FUNCTIONS_DIR . '/' . $jobs[$selected]['script'];
}

/**
 * The dispatcher as it works before the 2026.4.8 upgrade: one site-wide lock
 * in config.job_dispatch_lock_until, whatever the job.
 *
 * Kept for an installation that took the code without the upgrade, where
 * cron_runs has no locked_until column to lock per job.
 *
 * @return string Absolute path of the script to include, or '' for nothing.
 */
function pg_cron_dispatch_next_legacy()
{
    $allowed = pg_cron_dispatch_list();

    if (!$allowed) {
        return '';
    }

    // null means the cron_runs table does not exist, and that table is the
    // whole basis of the decision below. Without it every job would look
    // overdue on every tick.
    $runs = pg_cron_last_runs();

    if ($runs === null) {
        return '';
    }

    $jobs = pg_cron_jobs();
    $now = time();
    $due = array();

    foreach ($jobs as $name => $job) {

        if (!$job['dispatch']) {
            continue;
        }

        // An inline job is run by job.php itself, before this choice is made.
        // Leaving it in the rotation would run it twice on the tick it won.
        if (!empty($job['inline'])) {
            continue;
        }

        if (!in_array($name, $allowed, true)) {
            continue;
        }

        if (!file_exists(PG_FUNCTIONS_DIR . '/' . $job['script'])) {
            continue;
        }

        // A job whose config gate is closed would start, log a complaint and
        // exit without doing anything. Skipping it here keeps that noise out
        // of the activity log entirely.
        if (!pg_cron_job_active($name)) {
            continue;
        }

        $last_run = isset($runs[$name]) ? (int) $runs[$name] : 0;

        // A job that also has its own crontab entry writes this timestamp
        // itself, whoever started it, so it never looks overdue here and is
        // never run twice. That is the entire double-run guard - the measure
        // corrects itself and there is no second bookkeeping to keep in step.
        if (($now - $last_run) < (int) $job['interval']) {
            continue;
        }

        $due[$name] = $last_run;
    }

    if (!$due) {
        return '';
    }

    // Longest waiting first, so a rotation forms by itself and nothing
    // starves. Strict comparison keeps the catalogue order on a tie, which is
    // what puts the SEO score job ahead of the structure job on a site where
    // both were just switched on.
    $selected = '';
    $selected_run = 0;

    foreach ($due as $name => $last_run) {

        if (($selected === '') || ($last_run < $selected_run)) {
            $selected = $name;
            $selected_run = $last_run;
        }
    }

    // One dispatched job at a time for the whole site. The general job runs
    // every minute or every few minutes and several of these take longer than
    // that, so without a lock a slow campaign send or a large backup would be
    // started again while the first one is still running.
    //
    // The lock expires on its own. pg_cron_dispatch_finished() releases it at
    // the end of a normal run and after a fatal error, but a process killed
    // outright runs no shutdown handler, and a lock that nothing can clear
    // would stop every tick from then on.
    $lock_seconds = defined('JOB_DISPATCH_LOCK_SECONDS') ? (int) JOB_DISPATCH_LOCK_SECONDS : 3600;

    if ($lock_seconds < 60) {
        $lock_seconds = 60;
    }

    db(
        "UPDATE config
        SET job_dispatch_lock_until = UNIX_TIMESTAMP() + " . (int) $lock_seconds . "
        WHERE job_dispatch_lock_until < UNIX_TIMESTAMP()");

    // Nothing updated means another tick holds the lock. Two ticks landing in
    // the same second also report nothing updated, because the value would be
    // identical - which errs towards running nothing, the safe direction.
    if (mysqli_affected_rows(db::$con) < 1) {
        return '';
    }

    pg_cron_dispatch_current($selected);

    return PG_FUNCTIONS_DIR . '/' . $jobs[$selected]['script'];
}

/**
 * Name of the job the general job is running in this tick.
 *
 * Set once by pg_cron_dispatch_next(); read by the output handler, which has
 * no other way to know whose message it is holding.
 *
 * @param string|null $name
 * @return string
 */
function pg_cron_dispatch_current($name = null)
{
    static $value = '';

    if ($name !== null) {
        $value = (string) $name;
    }

    return $value;
}

/**
 * Output buffer handler for a dispatched job.
 *
 * These scripts are each written to be a whole request, and some of them print
 * an error page and stop when their own settings are missing - the campaign job
 * does exactly that without SMTP settings. Printed from inside the general job
 * that lands in its output: mailed by cron every five minutes on a
 * misconfigured site, or rendered into the page when someone opens job.php in a
 * browser to trigger it by hand.
 *
 * Returning an empty string discards it. A buffer handler is used rather than a
 * shutdown handler because it runs however the buffer ends - a normal return,
 * exit() from the middle of the job's own flow, or a fatal error - and does not
 * depend on the order shutdown handlers were registered in.
 *
 * The message is not lost: it goes to the site log, once a day per job. The
 * job's health signal is unaffected either way, because a script that stops
 * early never reaches its own pg_cron_ran() call and the maintenance panel
 * goes on reporting it as never having finished.
 *
 * @param string $buffer
 * @return string
 */
function pg_cron_dispatch_output($buffer)
{
    $message = trim(preg_replace('/\s+/', ' ', strip_tags((string) $buffer)));

    if ($message === '') {
        return '';
    }

    // A fatal error can end the run with the connection already gone, and
    // there is no reporting anything from here without it.
    if (!isset(db::$con) || !db::$con) {
        return '';
    }

    $name = pg_cron_dispatch_current();

    if ($name === '') {
        return '';
    }

    // Once a day per job, the same throttle the structure job uses for its
    // missing-DOM warning. A job that prints because its own settings are
    // missing prints on every turn, which is 288 identical log lines a day.
    $marker = $name . '_output';
    $last_logged = (int) db_value("SELECT last_run_at FROM cron_runs WHERE job_name = '" . e($marker) . "'");

    if ($last_logged < (time() - 86400)) {

        $jobs = pg_cron_jobs();
        $label = isset($jobs[$name]) ? $jobs[$name]['label'] : $name;

        log_activity(
            lang(array(
                'string' => '{var:1} printed a message while running with the general job: {var:2}',
                'vars'   => array($label, truncate($message, 500)),
            )),
            '');

        pg_cron_ran($marker);
    }

    return '';
}

/**
 * Release the dispatch lock.
 *
 * Registered as a shutdown handler by the general job so that it also runs
 * when the dispatched script calls exit(), which several of them do from
 * inside their own control flow, and when the run ends in a fatal error.
 *
 * Releases the lock pg_cron_dispatch_next() took: the job's own row in
 * cron_runs, or the site-wide one in config before the 2026.4.8 upgrade.
 * Clearing a lock that is already clear changes nothing, so a second call
 * does no harm.
 *
 * @return void
 */
function pg_cron_dispatch_finished()
{
    // Rotation must advance even when the dispatched script declined to run.
    //
    // email_campaign_job.php exits on its config gate BEFORE its own
    // pg_cron_ran() call. With no run ever recorded its last_run stayed at
    // zero, the longest-waiting rule selected it again on every single tick,
    // and every other job starved behind it while the log filled with the
    // same "not activated" line. Ten visits to job.php ran nothing but the
    // general job.
    //
    // Registered as a shutdown function, this runs after a clean finish, an
    // exit() and a fatal alike - so the attempt is recorded here regardless
    // of what the script did. A job that stamps itself is merely re-stamped
    // with the same clock second; a job that declined or crashed waits out
    // its interval instead of monopolising every tick.
    $name = pg_cron_dispatch_current();

    if ($name !== '') {
        pg_cron_ran($name);
    }

    // Stamped above before the lock is released here, so a tick that finds
    // the lock gone also finds the job no longer due and does not start it
    // again.
    if (pg_cron_lock_ready()) {

        if ($name !== '') {
            db("UPDATE cron_runs SET locked_until = 0 WHERE job_name = '" . e($name) . "'");
        }

        return;
    }

    db("UPDATE config SET job_dispatch_lock_until = 0");
}

/**
 * Whether the running job script was started in the background.
 *
 * True for a command-line run (the crontab entries the settings screen hands
 * out) and for a script the general job pulled in through the dispatcher,
 * which defines PG_CRON_DISPATCH right before the include. Anything else is an
 * ordinary web request and has to authenticate like a panel screen: the job
 * scripts once skipped that whenever the redirect parameter was absent, so an
 * anonymous GET could run them.
 */
function pg_cron_is_background_run()
{
    return (PHP_SAPI === 'cli') || defined('PG_CRON_DISPATCH');
}

/**
 * Whether a catalogued job's own config gate would let it do any work.
 *
 * The dispatcher skips inactive jobs instead of starting a script whose first
 * act is to log a complaint and exit, and the settings screen prints the
 * reason next to the job's switch. Only jobs with a hard config gate belong
 * here; everything else is active by definition.
 *
 * This is an optimisation on top of pg_cron_dispatch_finished()'s recorded
 * attempt, not a replacement for it: the two layers fail independently, so a
 * future job with a gate nobody registers here still cannot starve the queue.
 */
function pg_cron_job_active($name)
{
    if ($name === 'email_campaign_job') {
        return email_campaign_job_enabled();
    }

    return true;
}
