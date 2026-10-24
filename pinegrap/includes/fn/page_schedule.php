<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Scheduled publishing of visual-designer pages (2026.11.0, 11.0.17).
 *
 * A page can carry two times: page.publish_at, when a draft goes on the site,
 * and page.unpublish_at, when a page on the site goes back to the drafts.
 * Both are unix times, 0 for none, entered in the site's time zone. A page
 * waiting for its publish time is a draft like any other (the private Drafts
 * folder, includes/fn/designer.php). The general job (job.php) makes the move
 * with the same step the designs list and the editor make by hand
 * (pg_page_draft_move()) and clears the time, so each time is a one-off event.
 *
 * pg_designer_save_page() decides what a save does with the two times
 * (pg_page_schedule_plan(), pure): a publish time still ahead keeps the page
 * a draft; a time that has already come is carried out by the save itself,
 * which is also what keeps an editor left open across the job's move from
 * putting the old state back; the home page takes neither time; an end that
 * does not come after the start refuses the save.
 *
 * A content-level operator does not decide where a page lives, so their save
 * leaves both times as stored, like the draft state.
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
 * How many pages one tick of the general job moves in each direction; the
 * rest wait for the next tick.
 */
define('PG_PAGE_SCHEDULE_BATCH', 100);

/**
 * True when the two columns are in the database (2026.11.0, 11.0.17).
 *
 * @return bool
 */
function pg_page_schedule_ready()
{
    return pg_schema_has('page', 'unpublish_at');
}

/**
 * The latest time the two columns hold: they are INT UNSIGNED, and MySQL
 * would clip a later one to this (7 February 2106) without an error.
 */
define('PG_PAGE_SCHEDULE_UNIX_MAX', 4294967295);

/**
 * A time as the editor, the API or a form sends it, read in the site's time
 * zone (the PHP default time zone, init.php): empty or 0 for none, a unix
 * time as digits, a date and time as a datetime-local box writes it
 * ("2026-10-21T09:30", seconds and a space instead of the T accepted), or
 * the same with an offset or Z ("2026-10-21T09:30:00+03:00"). Fractions of
 * a second (JavaScript's toISOString() writes ".000Z") are dropped. A date
 * and time that does not exist ("2026-02-30T10:00"), one before 1970 and one
 * after PG_PAGE_SCHEDULE_UNIX_MAX are not times.
 *
 * @param mixed $value
 * @return array ok, time (unix, 0 for none)
 */
function pg_page_schedule_parse($value)
{
    $fail = array('ok' => false, 'time' => 0);

    if ($value === null || $value === false || $value === 0 || $value === '0') {
        return array('ok' => true, 'time' => 0);
    }

    if (is_int($value) || is_float($value)) {
        return pg_page_schedule_in_range($value);
    }

    if (!is_string($value)) {
        return $fail;
    }

    $value = trim($value);

    if ($value === '') {
        return array('ok' => true, 'time' => 0);
    }

    if (preg_match('/^\d{1,10}$/', $value)) {
        return pg_page_schedule_in_range((int) $value);
    }

    // Date, hours and minutes, then seconds with an optional fraction, then
    // an optional offset: Z, +03:00 or +0300.
    if (!preg_match('/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})(:\d{2}(?:\.\d+)?)?([Zz]|[+-]\d{2}:?\d{2})?$/', $value, $m)) {
        return $fail;
    }

    $text = $m[1] . 'T' . $m[2] . ((isset($m[3]) && $m[3] !== '') ? substr($m[3], 0, 3) : ':00');

    if (!isset($m[4]) || $m[4] === '') {
        $date = DateTime::createFromFormat('!Y-m-d\TH:i:s', $text);
    } else {
        $offset = (strtoupper($m[4]) === 'Z') ? '+00:00' : ((strlen($m[4]) === 5) ? substr($m[4], 0, 3) . ':' . substr($m[4], 3) : $m[4]);

        // An offset past fourteen hours is no time zone anybody keeps.
        if ((int) substr($offset, 1, 2) > 14 || (int) substr($offset, 4, 2) > 59) {
            return $fail;
        }

        $date = DateTime::createFromFormat('!Y-m-d\TH:i:sP', $text . $offset);
    }

    if ($date === false || $date->format('Y-m-d\TH:i:s') !== $text) {
        return $fail;
    }

    return pg_page_schedule_in_range($date->getTimestamp());
}

/**
 * A unix time the columns can hold, or a refusal: none before 1970 (which
 * would otherwise read as "no time") and none after PG_PAGE_SCHEDULE_UNIX_MAX.
 *
 * @param int|float $time
 * @return array ok, time
 */
function pg_page_schedule_in_range($time)
{
    if ($time < 0 || $time > PG_PAGE_SCHEDULE_UNIX_MAX) {
        return array('ok' => false, 'time' => 0);
    }

    return array('ok' => true, 'time' => (int) $time);
}

/**
 * A time as a datetime-local box shows it, in the site's time zone; '' for
 * none.
 *
 * @param int $time
 * @return string
 */
function pg_page_schedule_format($time)
{
    $time = (int) $time;

    return ($time > 0) ? date('Y-m-d\TH:i', $time) : '';
}

/**
 * What a save does with a page's two times (pure). $draft is the state the
 * save would leave the page in without them (pg_designer_page_draft_plan()).
 *
 * - An end that does not come after the start: error 'order', nothing else
 *   decided.
 * - The home page takes neither time and stays on the site; a time it was
 *   given is dropped with a note ('home').
 * - A publish time that has come puts the page on the site now and is
 *   cleared ('published_now' when that changed the state); one still ahead
 *   keeps the page a draft until then.
 * - A time to come off the site that has come takes the page off now and is
 *   cleared ('unpublished_now' when that changed the state); one still ahead
 *   is kept.
 *
 * @param int  $publish_at
 * @param int  $unpublish_at
 * @param bool $draft
 * @param bool $home
 * @param int  $now
 * @return array draft, publish_at, unpublish_at, error ('' or 'order'), notes (codes)
 */
function pg_page_schedule_plan($publish_at, $unpublish_at, $draft, $home, $now)
{
    $publish_at   = max(0, (int) $publish_at);
    $unpublish_at = max(0, (int) $unpublish_at);
    $draft        = (bool) $draft;
    $now          = (int) $now;

    $out = array('draft' => $draft, 'publish_at' => $publish_at, 'unpublish_at' => $unpublish_at, 'error' => '', 'notes' => array());

    if ($publish_at > 0 && $unpublish_at > 0 && $unpublish_at <= $publish_at) {
        $out['error'] = 'order';
        return $out;
    }

    if ($home) {
        if ($publish_at > 0 || $unpublish_at > 0) {
            $out['notes'][] = 'home';
        }
        $out['draft']        = false;
        $out['publish_at']   = 0;
        $out['unpublish_at'] = 0;
        return $out;
    }

    if ($publish_at > 0) {
        if ($publish_at <= $now) {
            if ($out['draft']) {
                $out['notes'][] = 'published_now';
            }
            $out['draft']      = false;
            $out['publish_at'] = 0;
        } else {
            $out['draft'] = true;
        }
    }

    if ($unpublish_at > 0 && $unpublish_at <= $now) {
        if (!$out['draft']) {
            $out['notes'][] = 'unpublished_now';
        }
        $out['draft']        = true;
        $out['unpublish_at'] = 0;
    }

    return $out;
}

/**
 * The sentence for a plan's error or note, naming the page.
 *
 * @param string $code order, home, published_now, unpublished_now, publish_invalid, unpublish_invalid
 * @param string $name
 * @return string
 */
function pg_page_schedule_message($code, $name)
{
    $name = trim((string) $name);

    switch ($code) {
        case 'order':
            return lang(array('string' => 'The page "{var:1}" would come off the site before it goes on: the time to take it off has to be after the publish time.', 'vars' => array($name)));
        case 'home':
            return lang(array('string' => 'The page "{var:1}" is the home page, so it stays on the site and its scheduled times were left out.', 'vars' => array($name)));
        case 'published_now':
            return lang(array('string' => 'The publish time of the page "{var:1}" has passed, so it was published now.', 'vars' => array($name)));
        case 'unpublished_now':
            return lang(array('string' => 'The time to take the page "{var:1}" off the site has passed, so it was taken off now.', 'vars' => array($name)));
        case 'publish_invalid':
            return lang(array('string' => 'The publish time of the page "{var:1}" is not a date and time.', 'vars' => array($name)));
        case 'unpublish_invalid':
            return lang(array('string' => 'The time to take the page "{var:1}" off the site is not a date and time.', 'vars' => array($name)));
    }

    return '';
}

/**
 * What a save of one page does with its times (pg_designer_save_page()):
 * the times sent as page_publish_at / page_unpublish_at, or the stored ones
 * when a key is not sent, put through pg_page_schedule_plan(). A
 * content-level operator's save (restricted) and a site without the
 * columns or without drafts leave the columns alone (apply false).
 *
 * @param int    $page_id 0 for a new page
 * @param array  $page    the page as the save received it
 * @param bool   $restricted
 * @param array  $draft_plan pg_designer_page_draft_plan() result
 * @param string $name
 * @param int    $now
 * @return array apply, draft, publish_at, unpublish_at, errors, warnings
 */
function pg_page_schedule_save_plan($page_id, $page, $restricted, $draft_plan, $name, $now = null)
{
    $out = array('apply' => false, 'draft' => !empty($draft_plan['want']), 'publish_at' => 0, 'unpublish_at' => 0,
                 'errors' => array(), 'warnings' => array());

    if ($restricted || empty($draft_plan['ready']) || !pg_page_schedule_ready()) {
        return $out;
    }

    $page_id = (int) $page_id;
    $stored  = ($page_id > 0) ? db_item("SELECT publish_at, unpublish_at FROM page WHERE page_id = '$page_id' LIMIT 1") : null;
    $times   = array(
        'publish_at'   => is_array($stored) ? (int) $stored['publish_at'] : 0,
        'unpublish_at' => is_array($stored) ? (int) $stored['unpublish_at'] : 0,
    );

    foreach (array('publish_at' => 'publish_invalid', 'unpublish_at' => 'unpublish_invalid') as $column => $invalid) {
        if (!array_key_exists('page_' . $column, $page)) continue;
        $parsed = pg_page_schedule_parse($page['page_' . $column]);
        if (!$parsed['ok']) {
            $out['errors'][] = pg_page_schedule_message($invalid, $name);
            continue;
        }
        $times[$column] = $parsed['time'];
    }

    if ($out['errors']) {
        return $out;
    }

    $plan = pg_page_schedule_plan($times['publish_at'], $times['unpublish_at'], !empty($draft_plan['want']),
                                  !empty($draft_plan['home']), ($now === null) ? time() : (int) $now);

    if ($plan['error'] !== '') {
        $out['errors'][] = pg_page_schedule_message($plan['error'], $name);
        return $out;
    }

    foreach ($plan['notes'] as $note) {
        $out['warnings'][] = pg_page_schedule_message($note, $name);
    }

    $out['apply']        = true;
    $out['draft']        = $plan['draft'];
    $out['publish_at']   = $plan['publish_at'];
    $out['unpublish_at'] = $plan['unpublish_at'];

    return $out;
}

/**
 * The general job's pass (job.php): every page whose publish time has come
 * goes on the site, then every page whose time to come off has come goes
 * back to the drafts; each time is cleared as it is carried out. The column
 * is cleared before the move and only by the run that read the same time,
 * so two runs that overlap move a page once. A page already where its time
 * would put it only has the time cleared; the home page stays on the site.
 * Pages in the recycle bin are left as they are.
 *
 * @param int|null $now
 * @param int      $limit pages per direction
 * @return array published (names), unpublished (names), home (names kept on the site), cleared (count)
 */
function pg_page_schedule_run($now = null, $limit = PG_PAGE_SCHEDULE_BATCH)
{
    $out = array('published' => array(), 'unpublished' => array(), 'home' => array(), 'cleared' => 0);

    if (!pg_page_schedule_ready() || !pg_page_draft_ready()) {
        return $out;
    }

    $now    = ($now === null) ? time() : (int) $now;
    $limit  = max(1, (int) $limit);
    $binned = pg_designer_not_binned_sql('page_folder');

    // Publish first: a page whose two times have both come goes on and then
    // off, as it would have with the job on time.
    foreach (array('publish_at', 'unpublish_at') as $column) {
        $rows = db_items(
            "SELECT page_id, page_name, page_folder, page_home, $column AS due
             FROM page
             WHERE $column > 0 AND $column <= '$now' AND layout_type = 'system'$binned
             ORDER BY $column, page_id
             LIMIT $limit");

        foreach ((array) $rows as $r) {
            $page_id = (int) $r['page_id'];

            db("UPDATE page SET $column = 0 WHERE page_id = '$page_id' AND $column = '" . (int) $r['due'] . "'");

            if (mysqli_affected_rows(db::$con) !== 1) continue;

            $draft = pg_page_is_draft($r['page_folder']);

            if ($column === 'publish_at') {
                if ($draft && pg_page_draft_move($page_id, false, (int) $r['page_folder'], null)) {
                    $out['published'][] = (string) $r['page_name'];
                } else {
                    $out['cleared']++;
                }
            } elseif ((string) $r['page_home'] === 'yes') {
                $out['home'][] = (string) $r['page_name'];
            } elseif (!$draft && pg_page_draft_move($page_id, true, (int) $r['page_folder'], null)) {
                $out['unpublished'][] = (string) $r['page_name'];
            } else {
                $out['cleared']++;
            }
        }
    }

    // A page that came on or went off the site changes menus and lists on
    // other pages: every page the page cache keeps is made old.
    if (($out['published'] || $out['unpublished']) && function_exists('pg_page_cache_bump')) {
        pg_page_cache_bump();
    }

    return $out;
}

/**
 * How many of each design's pages carry a time and the next time among
 * them (the designs list). The pages are the design's own outside the bin.
 *
 * @param array $style_ids
 * @return array style_id => array(scheduled, next)
 */
function pg_page_schedule_design_counts($style_ids)
{
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) $style_ids))));
    $out = array();

    foreach ($ids as $id) $out[$id] = array('scheduled' => 0, 'next' => 0);

    if (!$ids || !pg_page_schedule_ready()) return $out;

    $rows = db_items(
        "SELECT page_style,
                SUM(publish_at > 0 OR unpublish_at > 0) AS scheduled,
                MIN(NULLIF(publish_at, 0)) AS next_publish,
                MIN(NULLIF(unpublish_at, 0)) AS next_unpublish
         FROM page
         WHERE page_style IN (" . implode(',', $ids) . ") AND layout_type = 'system'" . pg_designer_not_binned_sql('page_folder') . "
         GROUP BY page_style");

    foreach ((array) $rows as $r) {
        $next = array_filter(array((int) $r['next_publish'], (int) $r['next_unpublish']));
        $out[(int) $r['page_style']] = array('scheduled' => (int) $r['scheduled'], 'next' => $next ? min($next) : 0);
    }

    return $out;
}

/**
 * The times of every page of the given designs, for the chips under each
 * design: page_id => array(publish_at, unpublish_at), only the pages that
 * carry one.
 *
 * @param array $page_ids
 * @return array
 */
function pg_page_schedule_times($page_ids)
{
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) $page_ids))));
    $out = array();

    if (!$ids || !pg_page_schedule_ready()) return $out;

    foreach ((array) db_items("SELECT page_id, publish_at, unpublish_at FROM page
                               WHERE page_id IN (" . implode(',', $ids) . ") AND (publish_at > 0 OR unpublish_at > 0)") as $r) {
        $out[(int) $r['page_id']] = array('publish_at' => (int) $r['publish_at'], 'unpublish_at' => (int) $r['unpublish_at']);
    }

    return $out;
}

/**
 * A time as the panel prints it: the site's date and time format, in the
 * site's time zone.
 *
 * @param int $time
 * @return string
 */
function pg_page_schedule_label($time)
{
    return get_absolute_time(array('timestamp' => (int) $time, 'format' => 'plain_text', 'timezone_type' => 'site'));
}

/**
 * The earliest unix time the external API takes as one (2001-09-09): a
 * smaller number is a date written as digits (20261021) or a flag (1), and
 * read as a time it would be long past and publish the page at once.
 */
define('PG_PAGE_SCHEDULE_UNIX_MIN', 1000000000);

/**
 * A time as the external API takes it: one that names its moment without
 * the site's clock. Empty, null or 0 is none; digits are a unix time; a date
 * and time carries an offset or Z ("2026-10-21T09:30:00+03:00"). A date and
 * time without one is refused ('offset'): a client cannot see the site's time
 * zone, and a guess moves the page by hours.
 *
 * @param mixed $value
 * @return array ok, time (unix, 0 for none), error ('', 'offset' or 'invalid')
 */
function pg_page_schedule_parse_absolute($value)
{
    if (is_string($value) && preg_match('/^\s*\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(:\d{2}(\.\d+)?)?\s*$/', $value)) {
        return array('ok' => false, 'time' => 0, 'error' => 'offset');
    }

    $parsed = pg_page_schedule_parse($value);

    if (!$parsed['ok'] || ($parsed['time'] > 0 && $parsed['time'] < PG_PAGE_SCHEDULE_UNIX_MIN)) {
        return array('ok' => false, 'time' => 0, 'error' => 'invalid');
    }

    return array('ok' => true, 'time' => $parsed['time'], 'error' => '');
}

/**
 * What a write through the external API does with a page's two times
 * (pure): the times sent laid over the stored ones and put through
 * pg_page_schedule_plan(). Where the editor drops a time with a note, the API
 * refuses the request, since a 200 that changed nothing reads as done:
 *
 * - 'home': a time sent for the home page, which stays on the site;
 * - 'passed': a time that has come, for a page being created - a new page
 *   starts as a draft, so a publish time would put it on the site at once
 *   and a time to come off it would be cleared without doing anything;
 * - 'order': the end does not come after the start.
 *
 * A passed time for a page that exists is carried out, as by any save.
 *
 * @param array $sent     publish_at / unpublish_at as sent (unix, 0 to clear); a key not sent keeps the stored time
 * @param array $stored   publish_at, unpublish_at as stored (zeros for a new page)
 * @param bool  $draft    whether the page is a draft before the write
 * @param bool  $home
 * @param int   $now
 * @param bool  $creating
 * @return array error ('' or a code above), field (the time the error is about), plan (pg_page_schedule_plan() result, null on error)
 */
function pg_page_schedule_request_plan($sent, $stored, $draft, $home, $now, $creating = false)
{
    $sent  = (array) $sent;
    $out   = array('error' => '', 'field' => '', 'plan' => null);
    $times = array();

    foreach (array('publish_at', 'unpublish_at') as $key) {
        $times[$key] = array_key_exists($key, $sent)
            ? max(0, (int) $sent[$key])
            : (isset($stored[$key]) ? max(0, (int) $stored[$key]) : 0);
    }

    foreach (array('publish_at', 'unpublish_at') as $key) {
        if ($home && !empty($sent[$key])) {
            $out['error'] = 'home';
            $out['field'] = $key;
            return $out;
        }
    }

    foreach (array('publish_at', 'unpublish_at') as $key) {
        if ($creating && !empty($sent[$key]) && (int) $sent[$key] <= (int) $now) {
            $out['error'] = 'passed';
            $out['field'] = $key;
            return $out;
        }
    }

    $plan = pg_page_schedule_plan($times['publish_at'], $times['unpublish_at'], $draft, $home, $now);

    if ($plan['error'] !== '') {
        $out['error'] = $plan['error'];
        $out['field'] = array_key_exists('unpublish_at', $sent) ? 'unpublish_at' : 'publish_at';
        return $out;
    }

    $out['plan'] = $plan;

    return $out;
}
