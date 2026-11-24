<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The agency summary of the launch journey: the "What we need from you"
 * lines the agency writes (table launch_summary_lines), the signed link the
 * customer opens them by (launch_summary.php), the customer's text answers
 * and the bell line an answer gives the line's writer.
 *
 * The link's state is the "summary" key of the journey's config_text row
 * (PG_LAUNCH_JOURNEY_ROW): when it was issued, the sha256 of its token, when
 * it ends and when it was withdrawn. The token is an HMAC of the issue time
 * under the site's key, so the same link can be copied again from the
 * drawer; a new link ends the old one. The customer's page shows only the
 * lines and the steps' states, never a setting's value.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

define('PG_LAUNCH_SUMMARY_LINE_MAX', 500);
define('PG_LAUNCH_SUMMARY_ANSWER_MAX', 5000);
define('PG_LAUNCH_SUMMARY_LINES_MAX', 50);

/**
 * How long a new link works, in days, as the drawer offers it; the first is
 * the one picked.
 *
 * @return int[]
 */
function pg_launch_summary_expiry_days()
{
    return array(30, 7, 14, 90);
}

/**
 * A link's token: HMAC-SHA256 of its issue time under the site's key, 64
 * hex; '' without a key or an issue time. Pure.
 *
 * @param int    $issued_at
 * @param string $key
 * @return string
 */
function pg_launch_summary_token($issued_at, $key)
{
    $issued_at = (int) $issued_at;
    $key = (string) $key;

    if (($issued_at <= 0) || ($key === '')) {
        return '';
    }

    return hash_hmac('sha256', 'launch-summary:' . $issued_at, $key);
}

/**
 * The link's stored state, made whole, from the journey's row. Pure.
 *
 * @param string $json the config_text value
 * @return array issued_at, hash, expires_at, withdrawn_at
 */
function pg_launch_summary_link_read($json)
{
    $row = json_decode((string) $json, true);
    $link = (is_array($row) && isset($row['summary']) && is_array($row['summary'])) ? $row['summary'] : array();
    $hash = isset($link['hash']) ? (string) $link['hash'] : '';

    return array(
        'issued_at'    => isset($link['issued_at']) ? max(0, (int) $link['issued_at']) : 0,
        'hash'         => (preg_match('/^[0-9a-f]{64}$/', $hash) === 1) ? $hash : '',
        'expires_at'   => isset($link['expires_at']) ? max(0, (int) $link['expires_at']) : 0,
        'withdrawn_at' => isset($link['withdrawn_at']) ? max(0, (int) $link['withdrawn_at']) : 0,
    );
}

/**
 * The journey's row with the link's state replaced, every other key kept as
 * it was. Pure.
 *
 * @param string $json
 * @param array  $link issued_at, hash, expires_at, withdrawn_at
 * @return string
 */
function pg_launch_summary_link_change($json, $link)
{
    $row = json_decode((string) $json, true);
    $row = is_array($row) ? $row : array();
    $row['summary'] = pg_launch_summary_link_read(json_encode(array('summary' => $link)));

    return (string) json_encode($row);
}

/**
 * A new link's stored state. Pure.
 *
 * @param int    $now
 * @param int    $days one of pg_launch_summary_expiry_days(), else the first
 * @param string $key
 * @return array issued_at, hash, expires_at, withdrawn_at; hash '' without a key
 */
function pg_launch_summary_link_new($now, $days, $key)
{
    $days = in_array((int) $days, pg_launch_summary_expiry_days(), true) ? (int) $days : pg_launch_summary_expiry_days()[0];
    $token = pg_launch_summary_token($now, $key);

    return array(
        'issued_at'    => (int) $now,
        'hash'         => ($token !== '') ? hash('sha256', $token) : '',
        'expires_at'   => (int) $now + $days * 86400,
        'withdrawn_at' => 0,
    );
}

/**
 * What a token opens: invalid (no link, or not this one), withdrawn,
 * expired or open. A withdrawn link keeps its hash, so its page says so
 * until a new link replaces it. Pure.
 *
 * @param array  $link  pg_launch_summary_link_read()
 * @param string $token from the address
 * @param int    $now
 * @return string
 */
function pg_launch_summary_link_state($link, $token, $now)
{
    $token = (string) $token;

    if (($link['hash'] === '') || (preg_match('/^[0-9a-f]{64}$/', $token) !== 1) || !hash_equals($link['hash'], hash('sha256', $token))) {
        return 'invalid';
    }

    if ($link['withdrawn_at'] > 0) {
        return 'withdrawn';
    }

    return ($link['expires_at'] > (int) $now) ? 'open' : 'expired';
}

/**
 * A line's text as kept: one line, spaces folded, at most 500 characters;
 * '' for nothing. Pure.
 *
 * @param mixed $text
 * @return string
 */
function pg_launch_summary_line_clean($text)
{
    $text = is_string($text) ? trim(preg_replace('/\s+/u', ' ', $text)) : '';

    return mb_substr($text, 0, PG_LAUNCH_SUMMARY_LINE_MAX, 'UTF-8');
}

/**
 * An answer as kept: trimmed, line breaks kept, at most 5,000 characters.
 * Pure.
 *
 * @param mixed $text
 * @return string
 */
function pg_launch_summary_answer_clean($text)
{
    $text = is_string($text) ? trim(str_replace(array("\r\n", "\r"), "\n", $text)) : '';

    return mb_substr($text, 0, PG_LAUNCH_SUMMARY_ANSWER_MAX, 'UTF-8');
}

/**
 * The names of the set-up card's steps that are done, in the card's order:
 * the only state of the site the customer's page shows. Pure.
 *
 * @param array $rows pg_launch_journey_card()['rows']
 * @return string[]
 */
function pg_launch_summary_done_steps($rows)
{
    $done = array();

    foreach ((array) $rows as $row) {
        if (!empty($row['done'])) {
            $done[] = (string) $row['name'];
        }
    }

    return $done;
}

/**
 * The drawer's words about the link. Pure.
 *
 * @param array $link
 * @param int   $now
 * @return array state (none, open, expired, withdrawn), text
 */
function pg_launch_summary_link_face($link, $now)
{
    if ($link['hash'] === '') {
        return array('state' => 'none', 'text' => lang('No link yet. Make one to send to the customer.'));
    }

    if ($link['withdrawn_at'] > 0) {
        return array('state' => 'withdrawn', 'text' => lang('Withdrawn. Make a new link to share the summary again.'));
    }

    if ($link['expires_at'] <= (int) $now) {
        return array('state' => 'expired', 'text' => lang('Ended. Make a new link to share the summary again.'));
    }

    return array('state' => 'open', 'text' => lang(array('string' => 'Works until {var:1}', 'vars' => array(pg_launch_summary_date($link['expires_at'])))));
}

/**
 * A day as the panel writes dates, as plain text.
 *
 * @param int $time
 * @return string
 */
function pg_launch_summary_date($time)
{
    // get_absolute_time() answers a <time> element; the drawer prints text.
    return function_exists('get_absolute_time') ? trim(html_entity_decode(strip_tags((string) get_absolute_time(array('timestamp' => (int) $time, 'size' => 'long'))), ENT_QUOTES, 'UTF-8')) : date('Y-m-d', (int) $time);
}

/**
 * Whether the lines table is there (the 2026.11.0 upgrade ran).
 *
 * @return bool
 */
function pg_launch_summary_ready()
{
    return pg_schema_has('launch_summary_lines');
}

/**
 * The lines, in their order.
 *
 * @return array rows of launch_summary_lines
 */
function pg_launch_summary_lines()
{
    if (!pg_launch_summary_ready()) {
        return array();
    }

    $rows = db_items("SELECT id, line_text, answer, answered_at, created_by FROM launch_summary_lines ORDER BY sort_order, id");

    return is_array($rows) ? $rows : array();
}

/**
 * Adds a line at the end.
 *
 * @param string $text
 * @param int    $user_id its writer, whose bell hears the answer
 * @return bool false for an empty line, a full list or a failed write
 */
function pg_launch_summary_line_add($text, $user_id)
{
    $text = pg_launch_summary_line_clean($text);

    if (($text === '') || !pg_launch_summary_ready() || (count(pg_launch_summary_lines()) >= PG_LAUNCH_SUMMARY_LINES_MAX)) {
        return false;
    }

    $order = (int) db_value("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM launch_summary_lines");

    return (bool) db_q("INSERT INTO launch_summary_lines (line_text, answer, answered_at, sort_order, created_by, created_at, updated_at)
        VALUES (?, NULL, 0, ?, ?, UNIX_TIMESTAMP(), UNIX_TIMESTAMP())", array($text, $order, (int) $user_id));
}

/**
 * Removes a line, its answer with it.
 *
 * @param int $id
 * @return bool
 */
function pg_launch_summary_line_remove($id)
{
    if (((int) $id <= 0) || !pg_launch_summary_ready()) {
        return false;
    }

    return (bool) db_q("DELETE FROM launch_summary_lines WHERE id = ?", array((int) $id));
}

/**
 * Keeps the customer's answer to a line and tells the line's writer. An
 * answer sent again unchanged writes nothing.
 *
 * @param int    $id
 * @param string $answer
 * @return string saved, same, empty or missing
 */
function pg_launch_summary_answer_save($id, $answer)
{
    $answer = pg_launch_summary_answer_clean($answer);

    if ($answer === '') {
        return 'empty';
    }

    $line = pg_launch_summary_ready() ? db_item("SELECT id, line_text, answer, created_by FROM launch_summary_lines WHERE id = '" . (int) $id . "'") : null;

    if (!$line) {
        return 'missing';
    }

    if ((string) $line['answer'] === $answer) {
        return 'same';
    }

    db_q("UPDATE launch_summary_lines SET answer = ?, answered_at = UNIX_TIMESTAMP(), updated_at = UNIX_TIMESTAMP() WHERE id = ?", array($answer, (int) $id));
    pg_launch_summary_bell($line);

    return 'saved';
}

/**
 * The bell line for an answer, addressed to the line's writer: at most one
 * an hour for a line, so an answer changed a few times is told once.
 *
 * @param array $line id, line_text, created_by
 * @return bool whether a line was written
 */
function pg_launch_summary_bell($line)
{
    $writer = (int) $line['created_by'];

    if ($writer <= 0) {
        return false;
    }

    include_once(PG_FUNCTIONS_DIR . '/includes/notifications.php');

    if (!pg_notification_targets_available()) {
        return false;
    }

    if (function_exists('waf_rate_exceeded') && waf_rate_exceeded((string) (int) $line['id'], 'launch-summary-bell', 1, 3600)) {
        return false;
    }

    return (bool) db_q("INSERT INTO notifications (action, type, title, target_user_id, reference_id, user, readed, timestamp)
        VALUES ('launch_answer', 'info', ?, ?, ?, '', '0', UNIX_TIMESTAMP())",
        array(mb_substr((string) $line['line_text'], 0, 250, 'UTF-8'), $writer, (int) $line['id']));
}

/**
 * The link's stored state.
 *
 * @return array pg_launch_summary_link_read()
 */
function pg_launch_summary_link()
{
    return pg_launch_summary_link_read(pg_config_text(PG_LAUNCH_JOURNEY_ROW));
}

/**
 * Writes the link's state into the journey's row, read fresh.
 *
 * @param array $link
 * @return bool
 */
function pg_launch_summary_link_store($link)
{
    $current = pg_schema_has('config_text')
        ? (string) db_value("SELECT value FROM config_text WHERE name = '" . e(PG_LAUNCH_JOURNEY_ROW) . "'")
        : '';

    return pg_config_text_save(PG_LAUNCH_JOURNEY_ROW, pg_launch_summary_link_change($current, $link));
}

/**
 * The customer's address of a link, '' with no working link or no key.
 *
 * @param array $link
 * @return string
 */
function pg_launch_summary_url($link)
{
    $token = pg_launch_summary_token($link['issued_at'], pg_booking_key());

    if (($token === '') || !hash_equals((string) $link['hash'], hash('sha256', $token))) {
        return '';
    }

    return URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/launch_summary.php?t=' . $token;
}

/**
 * What the drawer draws: the lines and the link.
 *
 * @return array lines (id, text, answer, answered), link (state, text, url), days, ready
 */
function pg_launch_summary_drawer_data()
{
    $now = time();
    $lines = array();

    foreach (pg_launch_summary_lines() as $row) {
        $lines[] = array(
            'id'       => (int) $row['id'],
            'text'     => (string) $row['line_text'],
            'answer'   => (string) $row['answer'],
            'answered' => ((int) $row['answered_at'] > 0) ? lang(array('string' => 'Answered {var:1}', 'vars' => array(pg_launch_summary_date($row['answered_at'])))) : lang('Not answered yet'),
        );
    }

    $link = pg_launch_summary_link();
    $face = pg_launch_summary_link_face($link, $now);

    return array(
        'ready' => pg_launch_summary_ready() && (pg_booking_key() !== ''),
        'lines' => $lines,
        'full'  => (count($lines) >= PG_LAUNCH_SUMMARY_LINES_MAX),
        'link'  => $face + array('url' => ($face['state'] === 'open') ? pg_launch_summary_url($link) : ''),
        'days'  => pg_launch_summary_expiry_days(),
    );
}

/**
 * The bell's words for an answer: the line's text from the row's title.
 *
 * @param array $notification
 * @return array title, short, brief, url
 */
function pg_launch_summary_bell_display($notification)
{
    $line = isset($notification['title']) ? (string) $notification['title'] : '';

    return array(
        'title' => lang(array('string' => 'The customer answered "{var:1}"', 'vars' => array($line))),
        'short' => lang('The customer answered'),
        'brief' => $line,
        'url'   => 'welcome.php#launch-summary',
    );
}
