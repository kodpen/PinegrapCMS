<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Search and audits screen (search_audits.php): what each of the four
 * background engines (search index, SEO scores, HTML structure,
 * accessibility) is doing, its last full run, its progress and failures,
 * its scheduled job switch, and one bounded tick of Run now
 * (search_audits_run.php).
 *
 * Loaded by the screen and its run endpoint, not by functions.php.
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
 * The engines in the order the screen lists them, keyed by the name the
 * screen and the run endpoint use. 'job' is the pg_cron_jobs() entry whose
 * switch, lock and run times stand for the engine.
 *
 * @return array<string,array{job:string,label:string,icon:string,description:string,report:string,report_label:string}>
 */
function pg_search_audits_engines()
{
    return array(
        'index' => array(
            'job'          => 'update_search_index',
            'label'        => lang('Search index'),
            'icon'         => 'bi-search',
            'description'  => lang('Reads every page, visual editor record and linked file into the index the site search answers from.'),
            'report'       => '',
            'report_label' => '',
        ),
        'seo' => array(
            'job'          => 'seo_score_job',
            'label'        => lang('SEO scores'),
            'icon'         => 'bi-speedometer2',
            'description'  => lang('Scores the titles, descriptions and addresses of pages and products.'),
            'report'       => 'view_pages.php',
            'report_label' => lang('Pages'),
        ),
        'structure' => array(
            'job'          => 'seo_analyze_job',
            'label'        => lang('HTML structure'),
            'icon'         => 'bi-code-slash',
            'description'  => lang('Renders each page as a visitor sees it and checks its headings, links, images and structured data.'),
            'report'       => 'view_pages.php',
            'report_label' => lang('Pages'),
        ),
        'a11y' => array(
            'job'          => 'seo_a11y_job',
            'label'        => lang('Accessibility report'),
            'icon'         => 'bi-universal-access',
            'description'  => lang('Checks the published pages against WCAG 2.2 Level A and the contrast and target size of Level AA.'),
            'report'       => 'view_accessibility.php',
            'report_label' => lang('Accessibility report'),
        ),
    );
}

/**
 * Where an engine stands on its schedule. Pure.
 *
 * 'running' while its lock is held (a dispatched tick, a crontab run or a
 * Run now request); otherwise 'off' when its job is not switched on, 'due'
 * when its next tick time has come, 'scheduled' before that. The next tick
 * is the last tick plus the interval: an unfinished pass moved its last tick
 * back (pg_cron_due_again()), so it is due again in a minute.
 *
 * @param int  $now
 * @param int  $interval     Seconds between runs (pg_cron_jobs()).
 * @param bool $enabled      The job is in the dispatch list and the general job runs it.
 * @param int  $locked_until The job's lock in cron_runs, 0 for none.
 * @param int  $last_tick    The job's last stamped tick, 0 for never.
 * @return array{state:string,next:int}
 */
function pg_search_audits_status($now, $interval, $enabled, $locked_until, $last_tick)
{
    $next = ($last_tick > 0) ? ($last_tick + max(60, (int) $interval)) : (int) $now;

    if ((int) $locked_until > (int) $now) {
        return array('state' => 'running', 'next' => $next);
    }

    if (!$enabled) {
        return array('state' => 'off', 'next' => 0);
    }

    return array('state' => ($next <= $now) ? 'due' : 'scheduled', 'next' => $next);
}

/**
 * A share as a whole percent between 0 and 100, null when there is nothing to
 * count. Pure.
 *
 * @param int $done
 * @param int $total
 * @return int|null
 */
function pg_search_audits_percent($done, $total)
{
    if ((int) $total <= 0) {
        return null;
    }

    return (int) max(0, min(100, floor(((int) $done * 100) / (int) $total)));
}

/**
 * The dispatch list with one job switched on or off. Pure.
 *
 * Kept in the catalogue's order and filtered against the jobs the general job
 * may dispatch: the dispatcher turns these names into file paths, so nothing
 * else may reach the column (as in includes/settings/maintenance.save.php).
 *
 * @param string   $current    The stored list, comma separated.
 * @param string   $name       Job to switch.
 * @param bool     $on
 * @param string[] $dispatchable Names of the catalogue's dispatchable jobs, in order.
 * @return string
 */
function pg_search_audits_switch_list($current, $name, $on, $dispatchable)
{
    $selected = array();

    foreach (explode(',', (string) $current) as $item) {
        $item = trim($item);

        if ($item !== '') {
            $selected[$item] = true;
        }
    }

    if ($on) {
        $selected[$name] = true;
    } else {
        unset($selected[$name]);
    }

    $list = array();

    foreach ($dispatchable as $job) {
        if (isset($selected[$job])) {
            $list[] = $job;
        }
    }

    return implode(',', $list);
}

/**
 * The names of the jobs the general job may dispatch, in catalogue order.
 *
 * @return string[]
 */
function pg_search_audits_dispatchable()
{
    $names = array();

    foreach (pg_cron_jobs() as $name => $job) {
        if (!empty($job['dispatch'])) {
            $names[] = $name;
        }
    }

    return $names;
}

/**
 * Whether the operator chose this job, read from the stored list whatever the
 * master switch says (pg_cron_dispatch_list() is empty while that is off).
 *
 * @param string $job
 * @return bool
 */
function pg_search_audits_selected($job)
{
    $list = defined('JOB_DISPATCH') ? (string) JOB_DISPATCH : '';

    return in_array($job, array_map('trim', explode(',', $list)), true);
}

/**
 * Switch one engine's job on or off in config.job_dispatch.
 *
 * @param string $job
 * @param bool   $on
 * @return bool false before the upgrade that added the column.
 */
function pg_search_audits_switch($job, $on)
{
    if (!pg_schema_has('config', 'job_dispatch')) {
        return false;
    }

    $current = (string) db_value("SELECT job_dispatch FROM config");

    db("UPDATE config SET job_dispatch = '" . e(pg_search_audits_switch_list($current, $job, $on, pg_search_audits_dispatchable())) . "'");

    return true;
}

/**
 * The longest piece of a translated log line that does not come from its
 * values, to find the line again whatever the language put first. Pure.
 *
 * @param string $text   The line translated with every value set to $marker.
 * @param string $marker
 * @return string '' when no piece is long enough to be told apart.
 */
function pg_search_audits_log_needle($text, $marker)
{
    $best = '';

    foreach (explode($marker, (string) $text) as $piece) {
        $piece = trim($piece, " \t\n\r\0\x0B:;,.()");

        if (mb_strlen($piece) > mb_strlen($best)) {
            $best = $piece;
        }
    }

    return (mb_strlen($best) >= 8) ? $best : '';
}

/**
 * Site Log lines a background pass wrote about what it could not read, for
 * an engine whose failures are kept nowhere else.
 *
 * @param string $key Engine key.
 * @return array<int,array{text:string,at:int}> Newest first, at most three.
 */
function pg_search_audits_log_lines($key)
{
    $marker = "\x01";

    if ($key === 'index') {
        $patterns = array(
            lang(array('string' => 'The search index could not read {var:1} item(s): {var:2}', 'vars' => array($marker, $marker))),
            lang(array('string' => 'The search index run stopped before its end: {var:1}', 'vars' => array($marker))),
        );
    } elseif ($key === 'a11y') {
        $patterns = array(
            lang(array('string' => 'accessibility check could not render {var:1} page{suffix:1}', 'vars' => $marker, 'suffix' => '')),
        );
    } else {
        return array();
    }

    $where = array();

    foreach ($patterns as $pattern) {
        $needle = pg_search_audits_log_needle($pattern, $marker);

        if ($needle !== '') {
            $where[] = "(log_description LIKE '%" . e(addcslashes($needle, '%_\\')) . "%')";
        }
    }

    if (!$where) {
        return array();
    }

    // The newest lines only: a key range on the primary key keeps the read
    // short on a log that holds six months.
    $top = (int) db_value("SELECT MAX(log_id) FROM log");

    $lines = array();

    foreach (db_items(
        "SELECT log_description, log_timestamp
        FROM log
        WHERE
            (log_id > " . max(0, $top - 50000) . ")
            AND (log_timestamp >= " . (time() - (8 * 86400)) . ")
            AND (" . implode(' OR ', $where) . ")
        ORDER BY log_id DESC
        LIMIT 3") as $row) {

        $lines[] = array('text' => (string) $row['log_description'], 'at' => (int) $row['log_timestamp']);
    }

    return $lines;
}

/**
 * How far an engine's current pass is.
 *
 * @param string $key Engine key.
 * @return array{unfinished:bool,percent:int|null,text:string,failures:string[]}
 *         failures: items the current pass could not read, named.
 */
function pg_search_audits_progress($key)
{
    $progress = array('unfinished' => false, 'percent' => null, 'text' => '', 'failures' => array());

    if ($key === 'index') {
        $state = pg_search_index_state_read();

        if (!is_array($state) || !empty($state['done']) || !isset($state['stage'])) {
            return $progress;
        }

        $stages = pg_search_index_stages();
        $at = array_search($state['stage'], $stages, true);

        if ($at === false) {
            return $progress;
        }

        $progress['unfinished'] = true;
        $progress['percent'] = pg_search_audits_percent($at, count($stages));
        $progress['text'] = lang(array(
            'string' => 'Stage {var:1} of {var:2}: {var:3}.',
            'vars' => array($at + 1, count($stages), pg_search_index_stage_label($state['stage']))));

        $carry = (isset($state['carry']) && is_array($state['carry'])) ? $state['carry'] : array();

        foreach (array_merge((array) ($carry['failure_notes'] ?? array()), (array) ($state['skipped'] ?? array())) as $note) {
            if (is_scalar($note) && (count($progress['failures']) < 5)) {
                $progress['failures'][] = (string) $note;
            }
        }

        return $progress;
    }

    if ($key === 'seo' || $key === 'structure') {
        $ready = ($key === 'seo') ? pg_seo_schema_ready() : pg_seo_structure_schema_ready();

        if (!$ready) {
            return $progress;
        }

        $total = 0;
        $waiting = 0;

        foreach (pg_search_audits_tables() as $table => $columns) {
            $total += (int) db_value("SELECT COUNT(*) FROM `" . $table . "`");

            $waiting += (int) db_value(($key === 'seo')
                ? "SELECT COUNT(*) FROM `" . $table . "` WHERE seo_analysis_current = 0"
                : "SELECT COUNT(*) FROM `" . $table . "` WHERE (seo_struct_current = 0) OR (seo_struct_checked_at < `" . $columns['timestamp'] . "`)");
        }

        if ($waiting > 0) {
            $progress['unfinished'] = true;
            $progress['percent'] = pg_search_audits_percent($total - $waiting, $total);
            $progress['text'] = lang(array('string' => '{var:1} of {var:2} records waiting.', 'vars' => array($waiting, $total)));
        }

        if ($key === 'structure') {
            foreach (db_items(
                "SELECT page.page_name, seo_issue.detail
                FROM seo_issue
                LEFT JOIN page ON page.page_id = seo_issue.entity_id
                WHERE (seo_issue.entity_type = 'page') AND (seo_issue.code = 'render_failed')
                ORDER BY seo_issue.entity_id
                LIMIT 5") as $row) {

                $progress['failures'][] = (string) $row['page_name'] . ': ' . (string) $row['detail'];
            }
        }

        return $progress;
    }

    if ($key === 'a11y') {
        $state = json_decode((string) @file_get_contents(pg_a11y_state_path()), true);

        if (!is_array($state) || !isset($state['after'])) {
            return $progress;
        }

        $after = (int) $state['after'];
        $ids = array_keys(pg_a11y_pages());
        $done = 0;

        foreach ($ids as $id) {
            if ($id <= $after) {
                $done++;
            }
        }

        $progress['unfinished'] = true;
        $progress['percent'] = pg_search_audits_percent($done, count($ids));
        $progress['text'] = lang(array('string' => '{var:1} of {var:2} pages checked.', 'vars' => array($done, count($ids))));
    }

    return $progress;
}

/**
 * The tables the SEO score and structure passes go through, with each one's
 * edit time column: pages, and products and their groups when the store is on.
 *
 * @return array<string,array{type:string,timestamp:string}>
 */
function pg_search_audits_tables()
{
    $tables = array('page' => array('type' => 'page', 'timestamp' => 'page_timestamp'));

    if (defined('ECOMMERCE') && (ECOMMERCE == true)) {
        $tables['products'] = array('type' => 'product', 'timestamp' => 'timestamp');
        $tables['product_groups'] = array('type' => 'product_group', 'timestamp' => 'timestamp');
    }

    return $tables;
}

/**
 * One bounded tick of an engine's pass, as its job runs it (the index runs in
 * update_search_index.php, which the screen asks directly).
 *
 * $start begins a new full pass when none is unfinished: the SEO score and
 * structure passes otherwise only take the records edited since their last
 * check, and a Run now pressed to see everything checked again would do
 * nothing.
 *
 * @param string $key    'seo', 'structure' or 'a11y'.
 * @param int    $budget Seconds.
 * @param bool   $start  The first tick of a Run now.
 * @return array{ready:bool,done:bool,count:int,failed:int}
 */
function pg_search_audits_tick($key, $budget, $start)
{
    $budget = max(1, (int) $budget);

    if ($key === 'seo') {
        if (!pg_seo_schema_ready()) {
            return array('ready' => false, 'done' => true, 'count' => 0, 'failed' => 0);
        }

        $tables = pg_search_audits_tables();

        if ($start) {
            $queued = 0;

            foreach ($tables as $table => $columns) {
                $queued += (int) db_value("SELECT COUNT(*) FROM `" . $table . "` WHERE seo_analysis_current = 0");
            }

            if (!$queued) {
                foreach ($tables as $table => $columns) {
                    db("UPDATE `" . $table . "` SET seo_analysis_current = 0");
                }
            }
        }

        // Products before pages, as the job: a catalog page's score reads
        // the scores of the products it lists.
        $started = microtime(true);
        $count = 0;
        $remaining = 0;

        foreach (array_reverse($tables, true) as $table => $columns) {
            $left = max(1, (int) ceil($budget - (microtime(true) - $started)));
            $run = pg_seo_recalculate($columns['type'], null, $left);
            $count += (int) $run['processed'];
            $remaining += (int) $run['remaining'];
        }

        if ($remaining === 0) {
            pg_cron_ran('seo_score_job');
        }

        return array('ready' => true, 'done' => ($remaining === 0), 'count' => $count, 'failed' => 0);
    }

    if ($key === 'structure') {
        if (!pg_seo_structure_schema_ready() || !class_exists('DOMDocument')) {
            return array('ready' => false, 'done' => true, 'count' => 0, 'failed' => 0);
        }

        if ($start) {
            $queued = 0;

            foreach (pg_search_audits_tables() as $table => $columns) {
                $queued += (int) db_value("SELECT COUNT(*) FROM `" . $table . "` WHERE seo_struct_current = 0");
            }

            if (!$queued) {
                foreach (pg_search_audits_tables() as $table => $columns) {
                    db("UPDATE `" . $table . "` SET seo_struct_current = 0");
                }
            }
        }

        $run = pg_seo_analyze_batch($budget);
        $done = ((int) $run['remaining'] === 0);

        if ($done) {
            pg_cron_ran('seo_analyze_job');
        }

        return array('ready' => (bool) $run['ready'], 'done' => $done, 'count' => (int) $run['analyzed'], 'failed' => 0);
    }

    if ($key === 'a11y') {
        $run = pg_a11y_run($budget);

        if ($run['done'] && $run['ready']) {
            pg_cron_ran('seo_a11y_job');
        }

        return array('ready' => (bool) $run['ready'], 'done' => (bool) $run['done'], 'count' => (int) $run['checked'], 'failed' => (int) $run['failed']);
    }

    return array('ready' => false, 'done' => true, 'count' => 0, 'failed' => 0);
}

/**
 * Everything the screen shows about one engine.
 *
 * @param string $key
 * @param array  $engine pg_search_audits_engines() entry.
 * @param array  $rows   pg_cron_run_rows() (null before the table exists).
 * @param array  $done   pg_cron_health_last_runs().
 * @param bool|null $selected Whether the job is switched on, when this request
 *        has just changed it (JOB_DISPATCH still holds the list it started with).
 * @return array
 */
function pg_search_audits_engine_state($key, $engine, $rows, $done, $selected = null)
{
    $jobs = pg_cron_jobs();
    $job = $engine['job'];
    $now = time();
    $row = (is_array($rows) && isset($rows[$job])) ? $rows[$job] : array('last_run_at' => 0, 'locked_until' => 0);
    $master = defined('JOB_DISPATCH_ENABLED') && JOB_DISPATCH_ENABLED;
    $selected = ($selected === null) ? pg_search_audits_selected($job) : (bool) $selected;

    $status = pg_search_audits_status($now, isset($jobs[$job]['interval']) ? (int) $jobs[$job]['interval'] : 86400, $master && $selected, (int) $row['locked_until'], (int) $row['last_run_at']);

    return array(
        'key'       => $key,
        'job'       => $job,
        'engine'    => $engine,
        'status'    => $status,
        'master'    => $master,
        'selected'  => $selected,
        'last_done' => isset($done[$job]) ? (int) $done[$job] : 0,
        'progress'  => pg_search_audits_progress($key),
        'log'       => pg_search_audits_log_lines($key),
    );
}

/**
 * One engine's card.
 *
 * @param array $state pg_search_audits_engine_state().
 * @return string HTML
 */
function pg_search_audits_card($state)
{
    $key = $state['key'];
    $engine = $state['engine'];
    $status = $state['status'];
    $progress = $state['progress'];

    $badges = array(
        'running'   => array('theme-primary', lang('Running')),
        'due'       => array('theme-warning', lang('Due now')),
        'scheduled' => array('theme-success', lang('Scheduled')),
        'off'       => array('bg-1 fg-body border', lang('Off')),
    );
    $badge = $badges[$status['state']];

    // HTML: the relative times are <time> elements; every text is escaped here.
    $lines = array();

    $lines[] = $state['last_done']
        ? lang(array('string' => 'Last full run: {var:1}.', 'vars' => array(get_relative_time(array('timestamp' => $state['last_done'])))))
        : h(lang('No full run has finished yet.'));

    if ($status['state'] === 'scheduled') {
        $lines[] = lang(array('string' => 'Next run: {var:1}.', 'vars' => array(get_relative_time(array('timestamp' => $status['next'])))));
    } elseif ($status['state'] === 'due') {
        $lines[] = h(lang('Due now: it runs on the next tick of the general job.'));
    } elseif ($status['state'] === 'off') {
        $lines[] = h($state['master']
            ? lang('Not scheduled: it runs only when Run now is pressed.')
            : lang('Not scheduled: Run Cron Jobs Automatically is off in Site Settings › Updates and maintenance › Cron Jobs.'));
    }

    $output_progress = '';

    if ($progress['unfinished']) {
        $percent = ($progress['percent'] === null) ? 0 : (int) $progress['percent'];

        $output_progress = '
            <div class="mt-5">
                <div class="small mb-1">' . h(lang('A pass is under way.')) . ' ' . h($progress['text']) . '</div>
                <div class="progress" role="progressbar" aria-label="' . h($engine['label']) . '" aria-valuenow="' . $percent . '" aria-valuemin="0" aria-valuemax="100" style="height:0.5rem">
                    <div class="progress-bar" style="width:' . $percent . '%"></div>
                </div>
            </div>';
    }

    $output_failures = '';
    $failure_items = '';

    foreach ($progress['failures'] as $note) {
        $failure_items .= '<li class="text-break">' . h($note) . '</li>';
    }

    foreach ($state['log'] as $line) {
        $failure_items .= '<li class="text-break">' . h($line['text']) . ' <span class="fg-secondary">' . get_relative_time(array('timestamp' => $line['at'])) . '</span></li>';
    }

    if ($failure_items !== '') {
        $output_failures = '
            <div class="mt-5">
                <div class="small fw-semibold fg-emphasis-warning"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>' . h(lang('Could not be read')) . '</div>
                <ul class="small mb-0 ps-6">' . $failure_items . '</ul>
            </div>';
    }

    $switch_id = 'search_audits_switch_' . $key;

    $output_switch = '
        <div class="form-field mb-0">
            <div class="switch"><input type="checkbox" id="' . $switch_id . '" data-pg-audit-switch="' . h($key) . '"' . ($state['selected'] ? ' checked="checked"' : '') . '/></div>
            <label class="small" for="' . $switch_id . '">' . h(lang('Run on schedule')) . '</label>
        </div>';

    $output_report = ($engine['report'] !== '')
        ? '<a class="btn btn-sm btn-text theme-secondary" href="' . h($engine['report']) . '"><i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>' . h($engine['report_label']) . '</a>'
        : '';

    $running = ($status['state'] === 'running');

    return '
        <div class="card h-100" data-pg-audit-card="' . h($key) . '" data-pg-audit-running="' . ($running ? '1' : '0') . '" data-pg-audit-unfinished="' . ($progress['unfinished'] ? '1' : '0') . '">
            <div class="card-header d-flex align-items-center gap-5">
                <i class="bi ' . h($engine['icon']) . '" aria-hidden="true"></i>
                <span class="fw-semibold">' . h($engine['label']) . '</span>
                <span class="badge rounded-pill ' . $badge[0] . ' ms-auto">' . h($badge[1]) . '</span>
            </div>
            <div class="card-body">
                <p class="small fg-secondary">' . h($engine['description']) . '</p>
                <p class="small mb-0">' . implode('<br>', $lines) . '</p>
                ' . $output_progress . $output_failures . '
                <div class="small mt-5" role="status" data-pg-audit-note></div>
            </div>
            <div class="card-footer d-flex flex-wrap align-items-center gap-5">
                ' . $output_switch . '
                <div class="ms-auto d-flex gap-3">
                    ' . $output_report . '
                    <button type="button" class="btn btn-sm btn-solid theme-secondary" data-pg-audit-run="' . h($key) . '"' . ($running ? ' disabled' : '') . '><i class="bi bi-play-fill me-1" aria-hidden="true"></i><span class="pg-btn-label">' . h(lang('Run now')) . '</span></button>
                </div>
            </div>
        </div>';
}

/**
 * Answer a JSON request of the screen or its run endpoint with a refusal and
 * stop: the panel's validate_*() helpers print an HTML page the script cannot
 * read.
 *
 * @param string $code    'session', 'denied', 'token' or another short word.
 * @param string $message In the panel language.
 * @param int    $status  HTTP status.
 */
function pg_search_audits_refuse($code, $message, $status)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(array('ok' => false, 'code' => $code, 'message' => $message), JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * Gate of a JSON request: a signed-in manager or above, read from the
 * session (the run endpoint signs nobody in, so no user is loaded there),
 * and the session's token posted with it. Refuses in JSON otherwise.
 *
 * @return string The caller's user name, for the Site Log.
 */
function pg_search_audits_json_gate()
{
    $user_id = (int) ($_SESSION['sessionuserid'] ?? 0);

    if ($user_id <= 0) {
        pg_search_audits_refuse('session', lang('Your session has expired. Reload the page and sign in again.'), 401);
    }

    if (db_value("SELECT user_id FROM user WHERE (user_id = '" . $user_id . "') AND (user_role < '3')") == '') {
        pg_search_audits_refuse('denied', lang('Access denied.'), 403);
    }

    $session_token = (string) ($_SESSION['software']['token'] ?? '');
    $post_token = (isset($_POST['token']) && is_string($_POST['token'])) ? $_POST['token'] : '';

    if (($session_token === '') || !hash_equals($session_token, $post_token)) {
        pg_search_audits_refuse('token', lang('Your session expired. Reload the page and try again.'), 403);
    }

    return (string) ($_SESSION['sessionusername'] ?? '');
}
