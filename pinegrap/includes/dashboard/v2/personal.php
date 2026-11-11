<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The new dashboard's personal parts of a layout: the goals, the pins and
 * the notes. The edit mode never changes them (pg_dashboard_v2_layout_clean()
 * keeps the stored ones); their own cards save goals and pins one part at a
 * time through api.php's dashboard_personal_save action. This file cleans
 * what a card sends, writes it into the person's stored layout and turns a
 * goal into progress. The notes card writes into My Notes instead; the text
 * an older notes card kept in the layout is moved there once
 * (pg_dashboard_v2_notes_move_stored()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_V2')) {
    exit;
}

/**
 * At most this many goals, pins and note characters.
 */
if (!defined('PG_DASHBOARD_V2_MAX_GOALS')) {
    define('PG_DASHBOARD_V2_MAX_GOALS', 3);
}

if (!defined('PG_DASHBOARD_V2_MAX_PINS')) {
    define('PG_DASHBOARD_V2_MAX_PINS', 8);
}

if (!defined('PG_DASHBOARD_V2_MAX_NOTES')) {
    define('PG_DASHBOARD_V2_MAX_NOTES', 2000);
}

/**
 * What a goal may count over its week or month: the "since your last
 * sign-in" chips' counts (pg_dashboard_v2_chip_count()) that make sense as
 * a goal, and the revenue of completed orders.
 *
 * @return array key => array(label, gate) — gate a figure gate
 */
function pg_dashboard_v2_goal_metrics()
{
    return array(
        'visits'      => array('label' => lang('Visits'), 'gate' => 'visitors'),
        'forms'       => array('label' => lang('Form entries'), 'gate' => 'forms'),
        'comments'    => array('label' => lang('Comments'), 'gate' => 'staff'),
        'subscribers' => array('label' => lang('New subscribers'), 'gate' => 'staff'),
        'files'       => array('label' => lang('Files uploaded'), 'gate' => 'files'),
        'tasks_done'  => array('label' => lang('Tasks done'), 'gate' => 'workspace'),
        'orders'      => array('label' => lang('Orders'), 'gate' => 'shop'),
        'revenue'     => array('label' => lang('Revenue'), 'gate' => 'shop_summary'),
    );
}

/**
 * The goal metrics the person may count.
 *
 * @param array $user
 * @return string[]
 */
function pg_dashboard_v2_goal_metrics_allowed($user)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/figures.php');

    $out = array();

    foreach (pg_dashboard_v2_goal_metrics() as $key => $metric) {
        if (pg_dashboard_v2_figure_gate($metric['gate'], $user)) {
            $out[] = $key;
        }
    }

    return $out;
}

/**
 * A line of text a person typed, cleaned: control characters out, spaces
 * squeezed, cut to a length. Pure.
 *
 * @param mixed $text
 * @param int   $most characters
 * @return string
 */
function pg_dashboard_v2_line_clean($text, $most)
{
    if (!is_string($text)) {
        return '';
    }

    $text = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text);
    $text = trim(preg_replace('/\s+/u', ' ', (string) $text));

    return mb_substr($text, 0, $most);
}

/**
 * Goals sent by the card, cleaned: at most three, each with a name, a
 * metric the person may count, a whole target above zero and a span of a
 * week or a month. A goal missing any of them is dropped. Pure.
 *
 * @param mixed    $goals
 * @param string[] $metrics the metric keys the person may count
 * @return array list of name, metric, target, span
 */
function pg_dashboard_v2_goals_clean($goals, $metrics)
{
    $out = array();

    foreach (is_array($goals) ? $goals : array() as $goal) {
        if (!is_array($goal)) {
            continue;
        }

        $name = pg_dashboard_v2_line_clean($goal['name'] ?? '', 60);
        $metric = $goal['metric'] ?? '';
        $target = $goal['target'] ?? 0;
        $target = (is_int($target) || (is_string($target) && ctype_digit($target)) || (is_float($target) && (floor($target) === $target))) ? (int) $target : 0;

        if (($name === '') || !is_string($metric) || !in_array($metric, $metrics, true) || ($target < 1) || ($target > 1000000000)) {
            continue;
        }

        $out[] = array(
            'name'   => $name,
            'metric' => $metric,
            'target' => $target,
            'span'   => (($goal['span'] ?? '') === 'week') ? 'week' : 'month',
        );

        if (count($out) >= PG_DASHBOARD_V2_MAX_GOALS) {
            break;
        }
    }

    return $out;
}

/**
 * The records a pin may open, as the panel's search names them: the screen
 * that edits one and its icon.
 *
 * @return array type => array(script, icon)
 */
function pg_dashboard_v2_pin_types()
{
    return array(
        'page'           => array('edit_page.php', 'bi-file-earmark-text'),
        'form'           => array('edit_page.php', 'bi-ui-checks'),
        'product'        => array('edit_product.php', 'bi-box-seam'),
        'product_group'  => array('edit_product_group.php', 'bi-boxes'),
        'offer'          => array('edit_offer.php', 'bi-percent'),
        'menu'           => array('edit_menu.php', 'bi-menu-button'),
        'calendar'       => array('edit_calendar.php', 'bi-calendar3'),
        'email_campaign' => array('edit_email_campaign_profile.php', 'bi-megaphone'),
        'user'           => array('edit_user.php', 'bi-person-circle'),
        'contact'        => array('edit_contact.php', 'bi-person-vcard'),
        'common_region'  => array('edit_common_region.php', 'bi-columns-gap'),
        'design_region'  => array('edit_designer_region.php', 'bi-code-square'),
        'login_region'   => array('edit_login_region.php', 'bi-shield-lock'),
        'short_link'     => array('edit_short_link.php', 'bi-link-45deg'),
        'order'          => array('view_order.php', 'bi-receipt'),
    );
}

/**
 * Pins sent by the card, cleaned. A pin is a record ({t, id, l}: a type of
 * pg_dashboard_v2_pin_types(), its id and the name the search showed) or a
 * panel screen ({t: "screen", u, l, i}: a script of the panel with an
 * optional query, never a path, scheme or host). At most eight, each once.
 * Pure.
 *
 * @param mixed $pins
 * @return array list of pins
 */
function pg_dashboard_v2_pins_clean($pins)
{
    $types = pg_dashboard_v2_pin_types();
    $out = array();
    $seen = array();

    foreach (is_array($pins) ? $pins : array() as $pin) {
        if (!is_array($pin) || !isset($pin['t']) || !is_string($pin['t'])) {
            continue;
        }

        $label = pg_dashboard_v2_line_clean($pin['l'] ?? '', 80);

        if ($label === '') {
            continue;
        }

        if ($pin['t'] === 'screen') {
            $url = $pin['u'] ?? '';
            $icon = $pin['i'] ?? '';

            if (!is_string($url) || !preg_match('/^[a-z0-9_]+\.php(\?[A-Za-z0-9_=&%.,:+-]*)?$/', $url)) {
                continue;
            }

            $clean = array('t' => 'screen', 'u' => $url, 'l' => $label, 'i' => (is_string($icon) && preg_match('/^bi-[a-z0-9-]{1,40}$/', $icon)) ? $icon : 'bi-window');
            $key = 'screen:' . $url;
        } else {
            $id = $pin['id'] ?? 0;
            $id = (is_int($id) || (is_string($id) && ctype_digit($id))) ? (int) $id : 0;

            if (!isset($types[$pin['t']]) || ($id < 1)) {
                continue;
            }

            $clean = array('t' => $pin['t'], 'id' => $id, 'l' => $label);
            $key = $pin['t'] . ':' . $id;
        }

        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $out[] = $clean;

        if (count($out) >= PG_DASHBOARD_V2_MAX_PINS) {
            break;
        }
    }

    return $out;
}

/**
 * Where a cleaned pin goes, and its icon. Pure.
 *
 * @param array $pin
 * @return array url, icon
 */
function pg_dashboard_v2_pin_target($pin)
{
    if ($pin['t'] === 'screen') {
        return array('url' => $pin['u'], 'icon' => $pin['i']);
    }

    $types = pg_dashboard_v2_pin_types();

    return array('url' => $types[$pin['t']][0] . '?id=' . (int) $pin['id'], 'icon' => $types[$pin['t']][1]);
}

/**
 * The notes sent by the card, cleaned: plain text, line ends as "\n",
 * control characters but tabs and line ends out, at most 2,000 characters.
 * Pure.
 *
 * @param mixed $text
 * @return string
 */
function pg_dashboard_v2_notes_clean($text)
{
    if (!is_string($text)) {
        return '';
    }

    $text = str_replace(array("\r\n", "\r"), "\n", $text);
    $text = preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/u', '', $text);

    return mb_substr((string) $text, 0, PG_DASHBOARD_V2_MAX_NOTES);
}

/**
 * The text an older notes card kept in a stored layout, cleaned. Pure.
 *
 * @param string $json the stored layout
 * @return string '' when there is none
 */
function pg_dashboard_v2_notes_stored($json)
{
    $layout = json_decode((string) $json, true);

    return (is_array($layout) && isset($layout['notes'])) ? pg_dashboard_v2_notes_clean($layout['notes']) : '';
}

/**
 * A stored layout with the old card's text taken out and every other part
 * left as it was. Pure.
 *
 * @param string $json
 * @return string|null null when the layout cannot be read
 */
function pg_dashboard_v2_notes_cleared($json)
{
    $layout = json_decode((string) $json, true);

    if (!is_array($layout)) {
        return null;
    }

    $layout['notes'] = '';
    $out = json_encode($layout, JSON_UNESCAPED_UNICODE);

    return ($out === false) ? null : $out;
}

/**
 * Moves the old card's text into My Notes: it becomes one note, and only
 * once that note is written is the text taken out of the layout. An empty
 * text makes no note, and a text of blanks is cleared without one; a note
 * that could not be written leaves the text for the next visit. Once
 * cleared there is nothing left to move, so a second run writes nothing.
 * Pure but for the three callables.
 *
 * @param callable $read  () => string, the text as stored now
 * @param callable $save  (string $body) => int, the new note's id, 0 on failure
 * @param callable $clear () => bool, takes the text out of the layout
 * @return int the new note's id, 0 when nothing was moved
 */
function pg_dashboard_v2_notes_move($read, $save, $clear)
{
    $text = (string) $read();

    if ($text === '') {
        return 0;
    }

    if (trim($text) === '') {
        $clear();
        return 0;
    }

    $note_id = (int) $save($text);

    if ($note_id <= 0) {
        return 0;
    }

    $clear();

    return $note_id;
}

/**
 * The move of pg_dashboard_v2_notes_move() on the person's stored layout,
 * through the notes' own save (ws_note_save(), with its rights and limits).
 * A named lock holds it to one request at a time, and the text is read
 * again inside it, so two cards drawn at once never write two notes; a
 * request that finds the lock taken leaves the move to the one holding it.
 *
 * @param array $user
 * @param array $viewer ws_viewer() of the person
 * @return int the new note's id, 0 when nothing was moved
 */
function pg_dashboard_v2_notes_move_stored($user, $viewer)
{
    $user_id = (int) $user['id'];
    $row = pg_dashboard_v2_row($user_id);

    // Nothing stored: no lock taken on most visits.
    if (($user_id <= 0) || !function_exists('ws_note_save') || (pg_dashboard_v2_notes_stored($row['layout'] ?? '') === '')) {
        return 0;
    }

    // GET_LOCK names belong to the whole server, so the database is part
    // of the name.
    $lock = "CONCAT('pg_dash_notes.', MD5(CONCAT(DATABASE(), '." . $user_id . "')))";

    if ((int) db_value("SELECT GET_LOCK(" . $lock . ", 0)") !== 1) {
        return 0;
    }

    $note_id = pg_dashboard_v2_notes_move(
        function () use ($user_id) {
            $row = pg_dashboard_v2_row($user_id);
            return pg_dashboard_v2_notes_stored($row['layout'] ?? '');
        },
        function ($body) use ($viewer) {
            $saved = ws_note_save($viewer, array('note_id' => 0, 'title' => '', 'body' => $body));
            return !empty($saved['ok']) ? (int) $saved['note_id'] : 0;
        },
        function () use ($user_id) {
            $row = pg_dashboard_v2_row($user_id);
            $json = pg_dashboard_v2_notes_cleared($row['layout'] ?? '');
            return ($json !== null) && pg_dashboard_v2_layout_write($user_id, $json);
        }
    );

    db_value("SELECT RELEASE_LOCK(" . $lock . ")");

    return $note_id;
}

/**
 * When a goal's span began and ends: Monday 00:00 to the next Monday, or
 * the first of the month to the first of the next, in the site's time.
 * Pure.
 *
 * @param string $span week or month
 * @param int    $now  unix time
 * @return array start, end (unix times)
 */
function pg_dashboard_v2_goal_span($span, $now)
{
    if ($span === 'week') {
        $start = strtotime('monday this week', $now);
        return array('start' => $start, 'end' => strtotime('+1 week', $start));
    }

    $start = strtotime(date('Y-m-01', $now));

    return array('start' => $start, 'end' => strtotime('+1 month', $start));
}

/**
 * A goal's progress: the share reached and a tone measured against the
 * share of the span gone by, so 40% on the third day of a month is ahead
 * and 40% on its last day is behind. Pure.
 *
 * @param int|float $value  counted so far
 * @param int       $target
 * @param float     $gone   share of the span gone by, 0-1
 * @return array pct (whole, may pass 100), ring (0-1), state (done, ahead, behind)
 */
function pg_dashboard_v2_goal_progress($value, $target, $gone)
{
    $target = max(1, (int) $target);
    $share = max(0, (float) $value) / $target;
    $gone = min(1, max(0, (float) $gone));

    if ($share >= 1) {
        $state = 'done';
    } elseif ($share >= $gone) {
        $state = 'ahead';
    } else {
        $state = 'behind';
    }

    return array(
        'pct'   => (int) floor($share * 100),
        'ring'  => min(1, $share),
        'state' => $state,
    );
}

/**
 * The layout a personal part is written into. The person's own layout when
 * it holds a cards list; else the one the page draws for them (their role's
 * default, pg_dashboard_v2_row_for()), with the default's goals, pins and
 * notes replaced by the person's own; else the fallback (the first preset).
 * Pure.
 *
 * @param string $own       the person's stored layout JSON
 * @param string $effective the layout JSON the page draws
 * @param array  $fallback  the layout used when neither has cards
 * @return array
 */
function pg_dashboard_v2_personal_base($own, $effective, $fallback)
{
    $mine = json_decode((string) $own, true);

    if (is_array($mine) && isset($mine['cards']) && is_array($mine['cards'])) {
        return $mine;
    }

    $drawn = json_decode((string) $effective, true);
    $base = (is_array($drawn) && isset($drawn['cards']) && is_array($drawn['cards'])) ? $drawn : $fallback;

    foreach (array('goals' => array(), 'pins' => array(), 'notes' => '') as $part => $empty) {
        $base[$part] = (is_array($mine) && isset($mine[$part])) ? $mine[$part] : $empty;
    }

    return $base;
}

/**
 * Write one personal part into the person's stored layout. With nothing
 * stored yet the layout starts as the page draws it (the role default, else
 * the first preset), so saving a pin never changes or empties the grid.
 *
 * @param array  $user
 * @param string $part  goals or pins
 * @param mixed  $value what the card sent
 * @return array|null the cleaned part, null when it could not be written
 */
function pg_dashboard_v2_personal_save($user, $part, $value)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/presets.php');

    switch ((string) $part) {
        case 'goals':
            $clean = pg_dashboard_v2_goals_clean($value, pg_dashboard_v2_goal_metrics_allowed($user));
            break;
        case 'pins':
            $clean = pg_dashboard_v2_pins_clean($value);
            break;
        // The notes card writes into My Notes; the layout's old text is
        // only ever cleared (pg_dashboard_v2_notes_move_stored()).
        default:
            return null;
    }

    $row = pg_dashboard_v2_row($user['id']);
    $own = (string) ($row['layout'] ?? '');
    $mine = json_decode($own, true);
    $effective = '';

    if (!is_array($mine) || !isset($mine['cards']) || !is_array($mine['cards'])) {
        $drawn = pg_dashboard_v2_row_for($user);
        $effective = (string) ($drawn['layout'] ?? '');
    }

    $layout = pg_dashboard_v2_personal_base($own, $effective, pg_dashboard_v2_preset_layout('', defined('ECOMMERCE') && (ECOMMERCE === true)));

    $layout[$part] = $clean;
    $json = json_encode($layout, JSON_UNESCAPED_UNICODE);

    if (($json === false) || (strlen($json) > PG_DASHBOARD_V2_MAX_LAYOUT) || !pg_dashboard_v2_layout_write($user['id'], $json)) {
        return null;
    }

    return array('value' => $clean);
}

/**
 * One personal part of the person's stored layout, as stored.
 *
 * @param array  $user
 * @param string $part goals, pins or notes
 * @return mixed array for goals and pins, string for notes
 */
function pg_dashboard_v2_personal($user, $part)
{
    $row = pg_dashboard_v2_row($user['id']);
    $layout = json_decode((string) ($row['layout'] ?? ''), true);
    $value = is_array($layout) ? ($layout[$part] ?? null) : null;

    if ($part === 'notes') {
        return is_string($value) ? $value : '';
    }

    return is_array($value) ? $value : array();
}
