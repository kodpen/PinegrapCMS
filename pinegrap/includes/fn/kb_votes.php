<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * "Was this helpful?" on a collection record's page, an article of a help
 * centre: a Yes and a No button bound in the form item view widget
 * (_pg_sw_apply_kb_vote_bindings()), kb_vote.php storing the answer in
 * kb_votes (step 11.0.289), and the two counts on the record list
 * (view_submitted_forms.php). No address, IP or user is kept; the cookie
 * PG_KB_VOTE_COOKIE, listing the records this browser answered, stops a
 * second vote.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The cookie listing the records this browser answered, ids joined by ".",
// the newest last. Read by assets/js/pg_kb_vote.js too, so not HttpOnly; it
// holds nothing but record ids.
define('PG_KB_VOTE_COOKIE', 'pg_kb_votes');
// Records the cookie remembers; the oldest drops out past it.
define('PG_KB_VOTE_COOKIE_IDS', 100);
// A year.
define('PG_KB_VOTE_LIFETIME', 31536000);
// Votes one address may send in PG_KB_VOTE_WINDOW seconds.
define('PG_KB_VOTE_LIMIT', 30);
define('PG_KB_VOTE_WINDOW', 600);

/**
 * Whether the step that adds kb_votes has run.
 *
 * @return bool
 */
function pg_kb_votes_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = pg_schema_has('kb_votes', 'answer');
    }

    return $ready;
}

/**
 * The stored value of a posted answer: 1 for yes, 0 for no, null for
 * anything else. Pure.
 *
 * @param mixed $answer
 * @return int|null
 */
function pg_kb_vote_answer($answer)
{
    if (!is_string($answer)) {
        return null;
    }

    $answer = strtolower(trim($answer));

    if ($answer === 'yes') {
        return 1;
    }

    if ($answer === 'no') {
        return 0;
    }

    return null;
}

/**
 * The record ids a cookie value lists, in its order, each once. Pure.
 *
 * @param mixed $value the cookie's value
 * @return int[]
 */
function pg_kb_vote_cookie_ids($value)
{
    if (!is_string($value) || ($value === '')) {
        return array();
    }

    $ids = array();

    foreach (explode('.', $value) as $part) {
        if (preg_match('/^[1-9][0-9]{0,9}$/', $part) === 1) {
            $ids[(int) $part] = (int) $part;
        }
    }

    return array_values($ids);
}

/**
 * The cookie value with $record_id added last, keeping the newest
 * PG_KB_VOTE_COOKIE_IDS ids. Pure.
 *
 * @param int[] $ids       pg_kb_vote_cookie_ids()
 * @param int   $record_id
 * @return string
 */
function pg_kb_vote_cookie_value($ids, $record_id)
{
    $kept = array();

    foreach ((array) $ids as $id) {
        if ((int) $id !== (int) $record_id) {
            $kept[] = (int) $id;
        }
    }

    $kept[] = (int) $record_id;

    return implode('.', array_slice($kept, -PG_KB_VOTE_COOKIE_IDS));
}

/**
 * Whether this browser has answered the record.
 *
 * @param int $record_id
 * @return bool
 */
function pg_kb_vote_given($record_id)
{
    $ids = pg_kb_vote_cookie_ids(isset($_COOKIE[PG_KB_VOTE_COOKIE]) ? $_COOKIE[PG_KB_VOTE_COOKIE] : '');

    return in_array((int) $record_id, $ids, true);
}

/**
 * Adds the record to this browser's cookie. Needed for the vote to count
 * once, so it is listed under the necessary cookies (pg_consent_catalogue()).
 *
 * @param int $record_id
 * @return bool false when the headers are already out
 */
function pg_kb_vote_remember($record_id)
{
    if (headers_sent()) {
        return false;
    }

    $value = pg_kb_vote_cookie_value(pg_kb_vote_cookie_ids(isset($_COOKIE[PG_KB_VOTE_COOKIE]) ? $_COOKIE[PG_KB_VOTE_COOKIE] : ''), $record_id);
    $expires = time() + PG_KB_VOTE_LIFETIME;
    $secure = function_exists('pg_request_is_https') && pg_request_is_https();
    $path = defined('PATH') ? PATH : '/';

    if (PHP_VERSION_ID >= 70300) {
        setcookie(PG_KB_VOTE_COOKIE, $value, array('expires' => $expires, 'path' => $path, 'secure' => $secure, 'httponly' => false, 'samesite' => 'Lax'));
    } else {
        setcookie(PG_KB_VOTE_COOKIE, $value, $expires, $path . '; samesite=Lax', '', $secure, false);
    }

    $_COOKIE[PG_KB_VOTE_COOKIE] = $value;

    return true;
}

/**
 * Whether the records of a form can be voted on: the table is there and
 * the form is a collection.
 *
 * @param int $form_id custom_form_pages.page_id
 * @return bool
 */
function pg_kb_vote_form_open($form_id)
{
    static $open = array();

    $form_id = (int) $form_id;

    if (($form_id <= 0) || !pg_kb_votes_ready()) {
        return false;
    }

    if (!isset($open[$form_id])) {
        $open[$form_id] = ((int) db_value("SELECT collection FROM custom_form_pages WHERE page_id = '" . $form_id . "' LIMIT 1") === 1);
    }

    return $open[$form_id];
}

/**
 * The form of a record a visitor may vote on: a complete record of a
 * collection form.
 *
 * @param int $record_id forms.id
 * @return int the form's page id, 0 when the record cannot be voted on
 */
function pg_kb_vote_record_form($record_id)
{
    $record_id = (int) $record_id;

    if ($record_id <= 0) {
        return 0;
    }

    $form_id = (int) db_value("SELECT page_id FROM forms WHERE id = '" . $record_id . "' AND complete = '1' LIMIT 1");

    return pg_kb_vote_form_open($form_id) ? $form_id : 0;
}

/**
 * Stores one answer.
 *
 * @param int $form_id
 * @param int $record_id
 * @param int $answer pg_kb_vote_answer()
 * @return bool
 */
function pg_kb_vote_store($form_id, $record_id, $answer)
{
    return db_q(
        "INSERT INTO kb_votes (form_id, record_id, answer, created_at) VALUES (?, ?, ?, ?)",
        array((int) $form_id, (int) $record_id, ((int) $answer === 1) ? 1 : 0, time())
    ) !== false;
}

/**
 * Whether the address has sent more votes than PG_KB_VOTE_LIMIT in
 * PG_KB_VOTE_WINDOW seconds; logged as rate-kb-vote when it has. Works with
 * the firewall off, as the article suggestions' limit does.
 *
 * @return bool
 */
function pg_kb_vote_throttled()
{
    if (!function_exists('waf_rate_exceeded') || !function_exists('waf_client_ip') || !function_exists('waf_ip_subject')) {
        return false;
    }

    $ip = (string) waf_client_ip();

    if ($ip === '') {
        return false;
    }

    if (!waf_rate_exceeded(waf_ip_subject($ip), 'kb_vote', PG_KB_VOTE_LIMIT, PG_KB_VOTE_WINDOW)) {
        return false;
    }

    if (function_exists('waf_log_event') && isset(db::$con) && db::$con) {
        waf_log_event('rate', 'rate-kb-vote', 'rate', 5, 'kb_vote.php', PG_KB_VOTE_LIMIT . '/' . (int) round(PG_KB_VOTE_WINDOW / 60) . 'min');
    }

    if (function_exists('waf_text_log')) {
        waf_text_log('DENY', $ip, array('status' => 429, 'rule' => 'rate-kb-vote'));
    }

    return true;
}

/**
 * The Yes and No counts of records.
 *
 * @param int[] $record_ids
 * @return array record id => array('yes' => n, 'no' => n); a record with no
 *               vote is not listed
 */
function pg_kb_vote_counts($record_ids)
{
    $ids = array();

    foreach ((array) $record_ids as $id) {
        if ((int) $id > 0) {
            $ids[(int) $id] = (int) $id;
        }
    }

    if (!$ids || !pg_kb_votes_ready()) {
        return array();
    }

    $rows = db_items(
        "SELECT record_id, SUM(answer = 1) AS yes_count, SUM(answer = 0) AS no_count
         FROM kb_votes
         WHERE record_id IN (" . implode(',', $ids) . ")
         GROUP BY record_id"
    );

    $counts = array();

    foreach ((array) $rows as $row) {
        $counts[(int) $row['record_id']] = array('yes' => (int) $row['yes_count'], 'no' => (int) $row['no_count']);
    }

    return $counts;
}

/**
 * The record list's form whose votes it counts: the one form the list is
 * narrowed to, when its records can be voted on.
 *
 * @param array $state the list's session filters
 *                     ($_SESSION['software']['forms']['view_submitted_forms'])
 * @return int the form's page id, or 0
 */
function pg_kb_vote_list_form($state)
{
    $form_id = pg_kb_vote_list_form_id(is_array($state) ? $state : array());

    return pg_kb_vote_form_open($form_id) ? $form_id : 0;
}

/**
 * The one form the record list is narrowed to: Custom Form in the simple
 * view, a single form in the advanced one. Pure.
 *
 * @param array $state the list's session filters
 * @return int 0 for all forms or several
 */
function pg_kb_vote_list_form_id($state)
{
    if (!empty($state['advanced_filters'])) {
        $forms = (isset($state['custom_forms']) && is_array($state['custom_forms'])) ? array_values($state['custom_forms']) : array();

        return (count($forms) === 1) ? max(0, (int) $forms[0]) : 0;
    }

    $form = isset($state['custom_form']) ? $state['custom_form'] : '';

    return (is_scalar($form) && preg_match('/^[1-9][0-9]*$/', (string) $form) === 1) ? (int) $form : 0;
}

/**
 * A count cell of the record list: the number, or an empty cell. Pure.
 *
 * @param array|null $counts pg_kb_vote_counts() for the record
 * @param string     $key    yes or no
 * @return string HTML
 */
function pg_kb_vote_count_cell($counts, $key)
{
    $count = (is_array($counts) && isset($counts[$key])) ? (int) $counts[$key] : 0;

    return ($count > 0) ? h(number_format($count)) : '';
}

// The "Was this helpful?" buttons, bound rather than hand-wired:
//   button   _bindings.action = 'kb_vote_yes' or 'kb_vote_no' → a plain
//            button with data-pg-kb-vote (yes or no) and data-pg-kb-record,
//            the shown record's id
// Each starts hidden twice over (hidden and d-none, as the in-browser
// summary's button); pg_kb_vote.js shows them for a record this browser has
// not answered, or says thank you in their place. On a form that is not a
// collection, or before the step ran, they stay hidden and the script is not
// added. A link bound to either becomes a button: it has nowhere to go.
function _pg_sw_apply_kb_vote_bindings(&$node, $open)
{
    if (!is_array($node)) return;
    $b    = (isset($node['props']['_bindings']) && is_array($node['props']['_bindings'])) ? $node['props']['_bindings'] : array();
    $type = isset($node['type']) ? $node['type'] : '';
    $tag  = isset($node['props']['tag']) ? strtolower((string)$node['props']['tag']) : '';

    if (isset($b['action']) && in_array($b['action'], array('kb_vote_yes', 'kb_vote_no'), true)) {
        $add = array('hidden' => '');
        if ($open) {
            $add['data-pg-kb-vote']   = ($b['action'] === 'kb_vote_yes') ? 'yes' : 'no';
            $add['data-pg-kb-record'] = '^^submitted_form_id^^';
        }
        $is_btn  = ($type === 'component' && isset($node['props']['componentType']) && $node['props']['componentType'] === 'btn');
        $is_link = ($type === 'semantic' && $tag === 'a')
                || ($type === 'content' && isset($node['props']['contentType']) && $node['props']['contentType'] === 'link');
        if ($is_btn) {
            $node['props']['btnElement'] = 'button';
            $node['props']['btnType']    = 'button';
        } elseif ($type === 'semantic' && $tag === 'button') {
            $add = array('type' => 'button') + $add;
        } elseif ($is_link) {
            $node['props']['href'] = '#';
            $add = array('role' => 'button') + $add;
        }
        $kept = array();
        foreach ((isset($node['props']['_attrs']) && is_array($node['props']['_attrs'])) ? $node['props']['_attrs'] : array() as $a) {
            if (is_array($a) && isset($a['name']) && !array_key_exists(strtolower((string)$a['name']), $add)) $kept[] = $a;
        }
        foreach ($add as $name => $value) $kept[] = array('name' => $name, 'value' => $value);
        $node['props']['_attrs'] = $kept;
        $class = isset($node['props']['cssClass']) ? trim((string)$node['props']['cssClass']) : '';
        if (!preg_match('/(^|\s)d-none(\s|$)/', $class)) $node['props']['cssClass'] = trim($class . ' d-none');
    }
    if (!empty($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as &$child) _pg_sw_apply_kb_vote_bindings($child, $open);
        unset($child);
    }
}

/**
 * The buttons' script, for a page whose HTML holds a button bound to a vote
 * (data-pg-kb-vote). Asked once every shared component and system widget is
 * drawn in (get_page_content.php). The labels are in the page's language.
 *
 * @param string $content page HTML
 * @return string
 */
function pg_kb_vote_inject($content)
{
    if (strpos($content, 'data-pg-kb-vote=') === false) return $content;
    if (strpos($content, '/assets/js/pg_kb_vote.js') !== false) return $content;

    $config = array(
        'url'    => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/kb_vote.php',
        'cookie' => PG_KB_VOTE_COOKIE,
        'thanks' => lang('Thank you for your feedback.'),
        'failed' => lang('Your answer could not be saved. Please try again later.'),
    );
    $file = '<script>window.pgKbVote = ' . encode_json($config) . ';</script>' . "\n"
          . '<script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/pg_kb_vote.js?v='
          . @filemtime(PG_FUNCTIONS_DIR . '/assets/js/pg_kb_vote.js') . '" defer></script>' . "\n";

    // The last </body>: an earlier one can belong to markup the page shows
    // as text or carries in an attribute.
    $body = strripos($content, '</body>');
    return ($body !== false) ? substr_replace($content, $file, $body, 0) : $content . $file;
}
