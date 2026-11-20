<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the box of a flow that reads the records of a list page
 * (kind list_records): the records that page's RSS feed would give, picked
 * by the feed's own query (pg_sw_form_feed_pick()): the list's filters and
 * its detail page's, complete records only, the newest first, 50 at most.
 * Only a page everybody may open is read, so the box reads only what any
 * visitor sees; it is checked when the box is saved and each time it runs.
 *
 * Its option "only records new since the last run", for a flow that starts
 * at a time, reads a window: from the start moment of the last run of the
 * start's anchor that sent (its flow run done or still open) to this run's
 * start moment, the earlier one in, this one out. A start moment is the
 * anchor run's started_at, written when the anchor is claimed and before
 * the flow starts, so a record made while a run works falls into the next
 * window. With no earlier run that sent, the window opens when the run's
 * version was published: a new flow never reads the archive. Nothing is
 * kept on the flow.
 *
 * A record's date is when it was made (forms.submitted_timestamp, the
 * feed's date), or the later moment of the list's one "date field at most
 * now" filter (the publish_date of the news templates), where it has
 * exactly one. A record dated before the window that was changed inside it
 * (set live from a draft, usually) is not read but counted as left out.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The most records the box gives, as the feed does.
define('WS_FLOW_RECORDS_MAX', 50);

// The most rows one window's query reads before they are split in PHP.
define('WS_FLOW_RECORDS_SCAN', 500);

// The most earlier runs of the anchor looked at for the window's edge.
define('WS_FLOW_RECORDS_RUNS', 100);

// The most characters of a record's summary: the description field is often
// the whole post, and the box's list of 50 records has to fit
// WS_FLOW_LOOP_ITEMS_BYTES.
define('WS_FLOW_RECORDS_SUMMARY', 200);

/**
 * The box's settings: page_id, the list page (0 for none), and only_new,
 * 1 for only the records new since the last run. Pure.
 *
 * @param array $config
 * @return array page_id, only_new
 */
function ws_flow_records_normalize($config)
{
    $config = is_array($config) ? $config : array();
    $page = $config['page_id'] ?? 0;
    $page = is_scalar($page) ? (int) $page : 0;
    $new = $config['only_new'] ?? 0;

    return array(
        'page_id'  => max(0, $page),
        'only_new' => (!empty($new) && ($new !== '0') && ($new !== 'false')) ? 1 : 0,
    );
}

/**
 * What the box lacks before its flow is published: its page. Pure but for
 * lang().
 *
 * @param array  $config ws_flow_records_normalize()
 * @param string $name   the box's name
 * @return string[]
 */
function ws_flow_records_problems($config, $name)
{
    return ((int) ($config['page_id'] ?? 0) > 0)
        ? array()
        : array(lang(array('string' => 'The box {var:1} needs the list page it reads.', 'vars' => $name)));
}

/**
 * Does the flow start at a time? Only such a flow has runs whose start
 * moments make a window. Pure.
 *
 * @param array $graph
 * @return bool
 */
function ws_flow_records_starts_at_time($graph)
{
    foreach ((array) ($graph['nodes'] ?? array()) as $node) {
        if ((string) ($node['kind'] ?? '') === 'schedule') {
            return true;
        }
    }

    return false;
}

/**
 * The option "only records new since the last run" asks for a flow that
 * starts at a time. Pure but for lang().
 *
 * @param array  $config         ws_flow_records_normalize()
 * @param string $name           the box's name
 * @param bool   $starts_at_time ws_flow_records_starts_at_time()
 * @return string[]
 */
function ws_flow_records_time_problems($config, $name, $starts_at_time)
{
    return (empty($config['only_new']) || $starts_at_time)
        ? array()
        : array(lang(array('string' => 'The box {var:1} reads only the records new since the last run: the flow has to start at a time.', 'vars' => $name)));
}

/**
 * Why a page cannot be read, '' when it can: it is there, everybody may open
 * its folder, and it lists the records of a form with a public feed. Pure
 * but for lang().
 *
 * @param array|null  $page   page row (page_id, page_name, page_folder)
 * @param string      $access get_access_control_type() of its folder
 * @param array|null  $feed   pg_sw_page_form_feed_widget()
 * @return string
 */
function ws_flow_records_page_refusal($page, $access, $feed)
{
    if (!is_array($page) || ((int) ($page['page_id'] ?? 0) <= 0)) {
        return lang('The list page is not there.');
    }

    if ((string) $access !== 'public') {
        return lang('Only a page everybody may open is read: this page is not open to everyone.');
    }

    if (!is_array($feed) || ((string) ($feed['type'] ?? '') !== 'form_list_view')) {
        return lang('The page has no list of records open to everyone with an RSS title field.');
    }

    return '';
}

/**
 * A page as the box reads it, checked (ws_flow_records_page_refusal()): a
 * page in the Recycle Bin is not open to anyone.
 *
 * @param int $page_id
 * @return array page, feed, error ('' when it can be read)
 */
function ws_flow_records_page($page_id)
{
    $page_id = (int) $page_id;
    $page = ($page_id > 0) ? db_item("SELECT page_id, page_name, page_title, page_folder FROM page WHERE page_id = '" . $page_id . "'") : null;
    $access = '';
    $feed = null;

    if (is_array($page)) {
        $folder = (int) $page['page_folder'];
        $access = pg_folder_binned($folder) ? 'binned' : (string) get_access_control_type($folder);
        $feed = pg_sw_page_form_feed_widget($page_id);
    }

    return array('page' => $page, 'feed' => $feed, 'error' => ws_flow_records_page_refusal($page, $access, $feed));
}

/**
 * Checks the box when its flow is published and each time it runs: a list
 * page everybody may open.
 *
 * @param array $viewer the flow's owner
 * @param array $config ws_step_config_normalize('list_records')
 * @return array ok, error, action (type, page_id, page_name, only_new)
 */
function ws_step_list_records_check($viewer, $config)
{
    $config = ws_flow_records_normalize($config);
    $read = ws_flow_records_page($config['page_id']);

    if ($read['error'] !== '') {
        return array('ok' => false, 'error' => $read['error'], 'action' => null);
    }

    return array('ok' => true, 'error' => '', 'action' => array(
        'type'      => 'list_records',
        'page_id'   => (int) $read['page']['page_id'],
        'page_name' => (string) $read['page']['page_name'],
        'only_new'  => (int) $config['only_new'],
    ));
}

/**
 * The field a list holds back its records until a moment with: the field of
 * its one filter "is less than (or equal to)" the current date (and time),
 * as the news templates' publish_date; its name as the filters compare it
 * (lower case). '' with none or with such filters on more than one field;
 * the same field found twice is one field. Pure.
 *
 * @param array $filters the filters the list applies (pg_sw_form_feed_pick())
 * @return string
 */
function ws_flow_records_live_field($filters)
{
    $found = array();

    foreach ((array) $filters as $filter) {
        if (!is_array($filter)) {
            continue;
        }

        $field = is_scalar($filter['field'] ?? null) ? trim((string) $filter['field']) : '';
        $value = is_scalar($filter['value'] ?? null) ? (string) $filter['value'] : '';

        if (($field !== '') && ($value === '')
            && in_array((string) ($filter['operator'] ?? ''), array('is less than', 'is less than or equal to'), true)
            && in_array((string) ($filter['dynamic_value'] ?? ''), array('current date and time', 'current date'), true)) {
            $found[mb_strtolower($field, 'UTF-8')] = true;
        }
    }

    // Counted by distinct field: a list's filters merged with its detail
    // page's (pg_sw_merge_record_filters()) can hold the same filter twice.
    return (count($found) === 1) ? (string) key($found) : '';
}

/**
 * The id of the form's own date field that ws_flow_records_live_field()
 * names, 0 when the name is a standard field or no date field of the form.
 *
 * @param int    $form_page_id
 * @param string $name
 * @return int
 */
function ws_flow_records_live_field_id($form_page_id, $name)
{
    if ($name === '') {
        return 0;
    }

    $standard = pg_sw_standard_fields();

    if (isset($standard[$name]) && ($standard[$name]['sql'] !== '') && !pg_sw_form_yields(array($name => true), $form_page_id)) {
        return 0;
    }

    $field = pg_sw_filter_form_fields($form_page_id)[$name] ?? null;

    return (is_array($field) && in_array((string) $field['type'], array('date', 'date and time'), true)) ? (int) $field['id'] : 0;
}

/**
 * A record's date in the window: when it was made, or the later moment its
 * list's live field holds. Pure.
 *
 * @param int    $submitted forms.submitted_timestamp
 * @param string $live      the live field's value, '' for none
 * @return int
 */
function ws_flow_records_date($submitted, $live)
{
    $submitted = (int) $submitted;
    $at = (trim((string) $live) !== '') ? strtotime((string) $live) : false;

    return (($at !== false) && ($at > $submitted)) ? (int) $at : $submitted;
}

/**
 * Does an earlier run of the anchor count as an edge? Its flow run sent:
 * done, or still open (running, waiting). One that failed, was cancelled
 * or never started leaves its records to the next run. Pure.
 *
 * @param string $status ws_flow_runs.status, '' for no flow run
 * @return bool
 */
function ws_flow_records_counted($status)
{
    return ((string) $status === 'done') || ws_flow_run_open($status);
}

/**
 * Where the window opens: the start moment of the newest earlier anchor run
 * that counts (ws_flow_records_counted()), else the switch-on moment.
 * Pure.
 *
 * @param array[] $earlier   newest first: started_at, flow_status
 * @param int     $switch_on when the run's version was published
 * @return int
 */
function ws_flow_records_edge($earlier, $switch_on)
{
    foreach ((array) $earlier as $run) {
        if (ws_flow_records_counted((string) ($run['flow_status'] ?? ''))) {
            return (int) $run['started_at'];
        }
    }

    return (int) $switch_on;
}

/**
 * The anchor run a flow run was started by: its start key sched:<id>, or
 * the sched_run of its context (a Run now keys the run by the canvas's
 * token). 0 for none. Pure.
 *
 * @param string $start_key
 * @param string $context ws_flow_runs.context
 * @return int
 */
function ws_flow_records_run_sched($start_key, $context)
{
    if (preg_match('/^sched:(\d+)$/', (string) $start_key, $m) === 1) {
        return (int) $m[1];
    }

    $decoded = json_decode((string) $context, true);

    return is_array($decoded) ? max(0, (int) ($decoded['sched_run'] ?? 0)) : 0;
}

/**
 * The earlier runs of an anchor, newest first, each with the status of the
 * flow run it started ('' for none).
 *
 * @param int $flow_id
 * @param int $action_id the anchor
 * @param int $before    the anchor run they come before
 * @return array[] started_at, flow_status
 */
function ws_flow_records_earlier($flow_id, $action_id, $before)
{
    $runs = (array) db_items("SELECT id, started_at FROM ws_scheduled_runs
        WHERE action_id = '" . (int) $action_id . "' AND id < '" . (int) $before . "'
        ORDER BY id DESC LIMIT " . WS_FLOW_RECORDS_RUNS);

    if (empty($runs)) {
        return array();
    }

    $oldest = min(array_map('intval', array_column($runs, 'started_at')));
    $statuses = array();

    foreach ((array) db_items("SELECT status, start_key, context FROM ws_flow_runs
        WHERE flow_id = '" . (int) $flow_id . "' AND started_at >= '" . $oldest . "'
        ORDER BY id DESC LIMIT " . (WS_FLOW_RECORDS_RUNS * 5)) as $flow_run) {
        $sched = ws_flow_records_run_sched((string) $flow_run['start_key'], (string) $flow_run['context']);

        if (($sched > 0) && !isset($statuses[$sched])) {
            $statuses[$sched] = (string) $flow_run['status'];
        }
    }

    $out = array();

    foreach ($runs as $run) {
        $out[] = array('started_at' => (int) $run['started_at'], 'flow_status' => $statuses[(int) $run['id']] ?? '');
    }

    return $out;
}

/**
 * When a version of a flow was published; 0 for none.
 *
 * @param int $version_id
 * @return int
 */
function ws_flow_records_published_at($version_id)
{
    return ((int) $version_id > 0) ? (int) db_value("SELECT published_at FROM ws_flow_versions WHERE id = '" . (int) $version_id . "'") : 0;
}

/**
 * The window of a run: from the start moment of the last anchor run that
 * sent (or the run's version's publication) to this run's start moment,
 * both read from the anchor run the run's context names (sched_run).
 * Never through the action row (ws_step_action_row()).
 *
 * @param array $context ws_flow_run_context()
 * @param array $run     ws_flow_runs row
 * @return array ok, error, from, to
 */
function ws_flow_records_window($context, $run)
{
    $sched = (int) ($context['sched_run'] ?? 0);
    $this_run = ($sched > 0) ? db_item("SELECT action_id, started_at FROM ws_scheduled_runs WHERE id = '" . $sched . "'") : null;

    if (!is_array($this_run)) {
        return array('ok' => false, 'error' => lang('Records new since the last run are read only in a run started at a time.'), 'from' => 0, 'to' => 0);
    }

    $to = (int) $this_run['started_at'];
    $switch_on = ws_flow_records_published_at((int) ($run['version_id'] ?? 0));
    $from = ws_flow_records_edge(ws_flow_records_earlier((int) $run['flow_id'], (int) $this_run['action_id'], $sched), ($switch_on > 0) ? $switch_on : $to);

    return array('ok' => true, 'error' => '', 'from' => min($from, $to), 'to' => $to);
}

/**
 * The window a run starting now would read, for Try: from the last anchor
 * run that sent (or the live version's publication) to now. Read only.
 *
 * @param array $flow ws_flow_for()
 * @return array ok, error, from, to
 */
function ws_flow_records_window_try($flow)
{
    $to = time();
    $flow_id = (int) ($flow['id'] ?? 0);
    $anchor = (int) db_value("SELECT id FROM ws_scheduled_actions WHERE flow_id = '" . $flow_id . "' ORDER BY id DESC LIMIT 1");
    $version = (int) db_value("SELECT version_id FROM ws_flows WHERE id = '" . $flow_id . "'");
    $switch_on = ws_flow_records_published_at($version);
    $earlier = ($anchor > 0) ? ws_flow_records_earlier($flow_id, $anchor, 4294967295) : array();
    $from = ws_flow_records_edge($earlier, ($switch_on > 0) ? $switch_on : $to);

    return array('ok' => true, 'error' => '', 'from' => min($from, $to), 'to' => $to);
}

/**
 * Splits the rows read into those in the window (from in, to out) and
 * those left out: dated before it but changed inside it. Pure.
 *
 * @param array[] $rows id, date, modified (and anything else, kept)
 * @param int     $from
 * @param int     $to
 * @return array in, left_out (rows), left_out_count
 */
function ws_flow_records_split($rows, $from, $to)
{
    $in = array();
    $out = array();
    $seen = array();

    foreach ((array) $rows as $row) {
        $id = (int) ($row['id'] ?? 0);

        if (isset($seen[$id])) {
            continue;
        }

        $seen[$id] = true;
        $date = (int) ($row['date'] ?? 0);
        $modified = (int) ($row['modified'] ?? 0);

        if (($date >= $from) && ($date < $to)) {
            $in[] = $row;
        } elseif (($date < $from) && ($modified >= $from) && ($modified < $to)) {
            $out[] = $row;
        }
    }

    return array('in' => $in, 'left_out' => $out, 'left_out_count' => count($out));
}

/**
 * The records of a page, picked as its feed picks them, with their dates;
 * with a window, those made, changed or set live inside it, more of them
 * read than the box gives so the split can drop those past its end.
 *
 * @param array      $read   ws_flow_records_page()
 * @param array|null $window ws_flow_records_window(), null for every record
 * @return array pick (pg_sw_form_feed_pick()), rows (id, date, modified, record)
 */
function ws_flow_records_rows($read, $window)
{
    $page = $read['page'];
    $pick = pg_sw_form_feed_pick((int) $page['page_id'], (string) $page['page_name'], $read['feed'], function ($filters, $form_page_id) use ($window) {
        $field = ws_flow_records_live_field_id($form_page_id, ws_flow_records_live_field($filters));
        $extra = array('select' => ', forms.last_modified_timestamp', 'join' => '', 'where' => '', 'limit' => WS_FLOW_RECORDS_MAX);

        if ($field > 0) {
            $extra['select'] .= ', pg_records_live.data AS pg_records_live_value';
            $extra['join'] = " LEFT JOIN form_data AS pg_records_live ON pg_records_live.form_id = forms.id AND pg_records_live.form_field_id = '" . $field . "'";
        }

        if ($window !== null) {
            $from = (int) $window['from'];
            $extra['limit'] = WS_FLOW_RECORDS_SCAN;
            // A date field is compared as its stored text; the day of the
            // edge lets in a little more, and the split decides.
            $extra['where'] = " AND forms.submitted_timestamp < '" . (int) $window['to'] . "'
                AND (forms.submitted_timestamp >= '" . $from . "' OR forms.last_modified_timestamp >= '" . $from . "'"
                . (($field > 0) ? " OR pg_records_live.data >= '" . e(date('Y-m-d', $from)) . "'" : '') . ")";
        }

        return $extra;
    });

    $rows = array();

    foreach ($pick['records'] as $record) {
        $rows[] = array(
            'id'       => (int) $record['id'],
            'date'     => ws_flow_records_date((int) $record['submitted_timestamp'], (string) ($record['pg_records_live_value'] ?? '')),
            'modified' => (int) ($record['last_modified_timestamp'] ?? 0),
            'record'   => $record,
        );
    }

    return array('pick' => $pick, 'rows' => $rows);
}

/**
 * A record's summary as the box gives it: spaces folded, cut at a word to
 * WS_FLOW_RECORDS_SUMMARY characters and marked with an ellipsis. Pure.
 *
 * @param string $text the record's RSS description
 * @return string
 */
function ws_flow_records_summary($text)
{
    $text = trim((string) preg_replace('/\s+/u', ' ', (string) $text));

    if (mb_strlen($text, 'UTF-8') <= WS_FLOW_RECORDS_SUMMARY) {
        return $text;
    }

    $cut = mb_substr($text, 0, WS_FLOW_RECORDS_SUMMARY + 1, 'UTF-8');
    $space = mb_strrpos($cut, ' ', 0, 'UTF-8');
    $cut = ($space !== false) && ($space > (int) (WS_FLOW_RECORDS_SUMMARY / 2)) ? mb_substr($cut, 0, $space, 'UTF-8') : mb_substr($cut, 0, WS_FLOW_RECORDS_SUMMARY, 'UTF-8');

    return rtrim($cut, " \t.,;:-") . '…';
}

/**
 * The items of the rows as the feed gives them: title, summary (cut by
 * ws_flow_records_summary()), picture (an upload everybody may open, or an
 * http(s) address typed), the address of the record's page and its date.
 *
 * @param array   $pick pg_sw_form_feed_pick()
 * @param array[] $rows ws_flow_records_rows() rows
 * @return array[] title, summary, picture, link, date
 */
function ws_flow_records_items($pick, $rows)
{
    $form_page_id = (int) $pick['form_page_id'];
    $site = URL_SCHEME . HOSTNAME_SETTING;
    $base = defined('OUTPUT_PATH') ? OUTPUT_PATH : '/';
    $media_field = (int) db_value("SELECT id FROM form_fields WHERE page_id = '" . $form_page_id . "' AND rss_field = 'media' ORDER BY sort_order ASC LIMIT 1");
    $items = array();

    foreach ((array) $rows as $row) {
        $record = $row['record'];
        $form_id = (int) $record['id'];
        $text = pg_sw_submitted_form_rss_text($form_id);
        $picture = '';

        if ($media_field > 0) {
            $media = db_item("SELECT data, file_id FROM form_data WHERE form_id = '" . $form_id . "' AND form_field_id = '" . $media_field . "' LIMIT 1");

            if (is_array($media) && !empty($media['file_id']) && pg_file_open_to_everyone($media['file_id'])) {
                $name = (string) db_value("SELECT name FROM files WHERE id = '" . (int) $media['file_id'] . "' LIMIT 1");
                $picture = ($name !== '') ? $site . PATH . rawurlencode($name) : '';
            } elseif (is_array($media) && (preg_match('~^https?://~i', trim((string) $media['data'])) === 1) && filter_var(trim((string) $media['data']), FILTER_VALIDATE_URL)) {
                $picture = trim((string) $media['data']);
            }
        }

        $link = ((string) $pick['detail_page_name'] !== '')
            ? $site . pg_sw_record_url((string) $pick['detail_page_name'], (string) $record['reference_code'], (string) $record['address_name'], (bool) $pick['detail_pretty'], $base)
            : '';

        $items[] = array(
            'title'   => (string) $text['title'],
            'summary' => ws_flow_records_summary((string) $text['description']),
            'picture' => $picture,
            'link'    => $link,
            'date'    => date('Y-m-d H:i', (int) $row['date']),
        );
    }

    return $items;
}

/**
 * What the box gives: how many records it read, how many were left out,
 * and the records as the JSON list a For each box reads, whole items only
 * while they fit $bytes. Pure.
 *
 * @param array[] $items ws_flow_records_items()
 * @param int     $left_out
 * @param int     $bytes
 * @return array count, left_out, records
 */
function ws_flow_records_data($items, $left_out, $bytes)
{
    $list = ws_step_rows_get_list($items, $bytes);

    return array('count' => count((array) $items), 'left_out' => (int) $left_out, 'records' => (string) $list['json']);
}

/**
 * Reads the records of a box's page, in its window when it reads only the
 * new ones.
 *
 * @param array      $read   ws_flow_records_page()
 * @param array|null $window ws_flow_records_window()
 * @return array items, left_out (rows)
 */
function ws_flow_records_read($read, $window)
{
    $got = ws_flow_records_rows($read, $window);
    $split = ($window !== null)
        ? ws_flow_records_split($got['rows'], (int) $window['from'], (int) $window['to'])
        : array('in' => $got['rows'], 'left_out' => array());

    return array(
        'items'    => ws_flow_records_items($got['pick'], array_slice($split['in'], 0, WS_FLOW_RECORDS_MAX)),
        'left_out' => $split['left_out'],
        'pick'     => $got['pick'],
    );
}

/**
 * What the box says it read. Pure but for lang().
 *
 * @param string $page     the page's name
 * @param int    $count
 * @param int    $left_out
 * @param bool   $only_new
 * @return string
 */
function ws_step_list_records_said($page, $count, $left_out, $only_new)
{
    $said = $only_new
        ? lang(array('string' => 'New records of the page “{var:1}” since the last run: {var:2}.', 'vars' => array($page, (int) $count)))
        : lang(array('string' => 'Records of the page “{var:1}”: {var:2}.', 'vars' => array($page, (int) $count)));

    if ((int) $left_out > 0) {
        $said .= ' ' . lang(array('string' => 'Left out, dated before the last run: {var:1}.', 'vars' => (int) $left_out));
    }

    return $said;
}

/**
 * Reads the records a box asks for, the page checked again
 * (ws_step_list_records_check()): every record its feed gives, or those
 * of the run's window. A run with no window fails the box.
 *
 * @param array $owner  the flow's owner
 * @param array $action ws_step_list_records_check()
 * @param array $run    ws_flow_runs row
 * @return array ok, text, data
 */
function ws_step_list_records_do($owner, $action, $run)
{
    $check = ws_step_list_records_check($owner, $action);

    if (!$check['ok']) {
        return array('ok' => false, 'text' => $check['error'], 'data' => array());
    }

    $window = null;

    if (!empty($action['only_new'])) {
        $window = ws_flow_records_window(ws_flow_run_context($run), $run);

        if (!$window['ok']) {
            return array('ok' => false, 'text' => $window['error'], 'data' => array());
        }
    }

    $read = ws_flow_records_read(ws_flow_records_page((int) $action['page_id']), $window);

    return array(
        'ok'   => true,
        'text' => ws_step_list_records_said((string) $check['action']['page_name'], count($read['items']), count($read['left_out']), $window !== null),
        'data' => ws_flow_records_data($read['items'], count($read['left_out']), WS_FLOW_LOOP_ITEMS_BYTES),
    );
}

/**
 * What a box that reads a list does, in words; with only the new records,
 * the sentence that says a record dated before the last run is not read.
 * Pure but for lang().
 *
 * @param array $action ws_step_list_records_check()
 * @return string
 */
function ws_step_list_records_text($action)
{
    $page = (string) ($action['page_name'] ?? '');

    return empty($action['only_new'])
        ? lang(array('string' => 'Reads the records of the page “{var:1}”, at most {var:2}.', 'vars' => array($page, WS_FLOW_RECORDS_MAX)))
        : lang(array('string' => 'Reads the records of the page “{var:1}” new since the last run, at most {var:2}.', 'vars' => array($page, WS_FLOW_RECORDS_MAX))) . ' ' . ws_flow_records_left_out_note();
}

/**
 * The sentence under the option: what a record's date is, and what is not
 * read. Pure but for lang().
 *
 * @return string
 */
function ws_flow_records_left_out_note()
{
    return lang('A record is new when it was made, or its list\'s publication date came, since the last run that sent; a record dated before that is not read, and a run that could not send leaves its records to the next one.');
}

/**
 * What Try shows of the box, read only: the records a run now would read,
 * and those left out by title. No window moves.
 *
 * @param array $action ws_step_list_records_check()
 * @param array $flow   ws_flow_for()
 * @return array said, records (title, link, date), left_out (titles)
 */
function ws_step_list_records_try($action, $flow)
{
    $window = !empty($action['only_new']) ? ws_flow_records_window_try($flow) : null;
    $read = ws_flow_records_read(ws_flow_records_page((int) $action['page_id']), $window);
    $titles = array();

    foreach (array_slice($read['left_out'], 0, 20) as $row) {
        $titles[] = (string) pg_sw_submitted_form_rss_text((int) $row['id'])['title'];
    }

    $records = array();

    foreach ($read['items'] as $item) {
        $records[] = array('title' => $item['title'], 'link' => $item['link'], 'date' => $item['date']);
    }

    return array(
        'said'     => ws_step_list_records_said((string) $action['page_name'], count($records), count($read['left_out']), $window !== null),
        'records'  => $records,
        'left_out' => $titles,
        'texts'    => ws_flow_records_try_texts($records, $titles),
    );
}

/**
 * The pages the box's form offers: those with a list of form records shown
 * to everyone (pg_sw_form_widget_public()), in a folder everybody may open,
 * by title. The run checks the page again (ws_flow_records_page()).
 *
 * @return array[] id, name, title
 */
function ws_flow_records_pages()
{
    $out = array();

    foreach (array('form_list_view', 'submitted_forms_list', 'form_list', 'blog_list') as $type) {
        foreach (pg_sw_widget_pages($type, 'pg_sw_form_widget_public') as $page) {
            $id = (int) $page['page_id'];

            if (isset($out[$id]) || (get_access_control_type((int) ($page['page_folder'] ?? 0)) !== 'public')) {
                continue;
            }

            $out[$id] = array('id' => $id, 'name' => (string) $page['page_name'], 'title' => (string) $page['page_title']);
        }
    }

    usort($out, function ($a, $b) {
        return strcasecmp(($a['title'] !== '') ? $a['title'] : $a['name'], ($b['title'] !== '') ? $b['title'] : $b['name']);
    });

    return array_values($out);
}

/**
 * Try's lists in the shape the canvas draws a box's texts: the records a
 * run now would read, one a line with its date, and the titles left out.
 * Pure but for lang().
 *
 * @param array[]  $records  title, date
 * @param string[] $left_out titles
 * @return array[] key, text
 */
function ws_flow_records_try_texts($records, $left_out)
{
    $texts = array();

    if (!empty($records)) {
        $lines = array();

        foreach ($records as $record) {
            $lines[] = '• ' . (string) $record['title'] . ' (' . (string) $record['date'] . ')';
        }

        $texts[] = array('key' => 'records', 'text' => lang('Would read:') . "\n" . implode("\n", $lines));
    }

    if (!empty($left_out)) {
        $texts[] = array('key' => 'left_out', 'text' => lang('Left out, dated before the last run:') . "\n• " . implode("\n• ", array_map('strval', $left_out)));
    }

    return $texts;
}
