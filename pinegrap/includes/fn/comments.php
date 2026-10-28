<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Comment rules shared by the comment handlers: which thread a comment posted
 * to a page may join, which form record a stored comment is about, the
 * record's comment counters moderation keeps up to date, the name a
 * signed-in member's comment is stored under, which comment a reply may
 * answer, and the rules a page shows a thread by (its settings, whether it
 * takes new comments, who reads it, which comments they read, the order they
 * are drawn in), shared by the classic comments block and the comments
 * widget.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: comments.
//
// A thread is (page_id, item_type, item_id): the page the comment was posted
// to, and the item on it the comment is about - '' and 0 for the page itself,
// 'product', 'product_group', 'calendar_event' or 'submitted_form' with that
// item's id. The comment form carries all three as hidden fields, so a post
// can name any thread; the page's comment settings are read by the posted
// page_id, and the item is what the post claims. pg_comment_thread_verify()
// works out again which thread the post may join before add_comment.php
// stores anything.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The item types the comment block of a classic page type names, as
 * get_page_content.php picks the item for it. Pure.
 *
 * - 'catalog': a product group: the one the address names, else the page's
 *   own product group, else the top-level one.
 * - 'catalog detail': the product or product group the address names. The
 *   page opened without one comments on itself.
 * - 'calendar event view': the event in ?id=. The page opened without one
 *   comments on itself.
 *
 * Every other page type comments on the page itself only ('' and 0). A legacy
 * form item view page and a page with a record view widget take a submitted
 * form instead (pg_comment_thread_decision()).
 *
 * @param mixed $page_type the page row's page_type
 * @return array
 */
function pg_comment_classic_item_types($page_type)
{
    switch (is_scalar($page_type) ? (string) $page_type : '') {
        case 'catalog':
            return array('product_group');

        case 'catalog detail':
            return array('product', 'product_group');

        case 'calendar event view':
            return array('calendar_event');
    }

    return array();
}

/**
 * Which thread a comment posted to a page may join. Pure: the caller reads
 * the page, its record view widget and the item (pg_comment_thread_verify()).
 *
 * - A legacy form item view page (page type 'form item view') takes only a
 *   submitted form; its form_item_view_pages row decides who may see which.
 *   A record view widget on such a page is not asked, as get_page_content.php
 *   does not ask it either.
 * - Any other page that carries a form_item_view widget shows the comments of
 *   the record that widget shows, so a post joins that record or nothing:
 *   item type 'submitted_form', and an id the widget resolves from its own
 *   custom form and lets this visitor see (access setting and record
 *   filters). The page's own thread ('' and 0), a product or another form's
 *   record is refused; it would be stored where the page never shows it.
 * - Any other page takes its own thread ('') as posted, or an item of a type
 *   its comment block names (pg_comment_classic_item_types()) that the page
 *   shows ($item_shown). A submitted form, a product on a calendar event
 *   view page, a product on a catalog page (which names product groups) or
 *   an item type no page names is refused for its type; an id the page does
 *   not show is refused as an item.
 *
 * $record_id is the id of the record the widget resolved for the posted id
 * (0 when it found none), $record_visible whether the visitor may see it.
 * $item_shown is whether the page shows the posted product, product group or
 * calendar event (pg_comment_classic_item_shown()).
 *
 * Returns array('accept' => bool, 'refusal' => string, 'item_type',
 * 'item_id'). 'refusal' is '' when accepted, 'item_type' for a type the
 * record view page does not take, 'record' for a record it does not show to
 * this visitor, 'page_type' for an item type a page without a record view
 * widget does not take, 'item' for an item of the right type that page does
 * not show. An accepted record or item carries its id as an integer, the id
 * the database compares; the page's own thread and a legacy form item view
 * page's record carry the posted values unchanged.
 *
 * @param mixed      $page_type
 * @param bool       $has_record_widget
 * @param mixed      $item_type
 * @param mixed      $item_id
 * @param int|string $record_id
 * @param bool       $record_visible
 * @param bool       $item_shown
 * @return array
 */
function pg_comment_thread_decision($page_type, $has_record_widget, $item_type, $item_id, $record_id = 0, $record_visible = false, $item_shown = false)
{
    $page_type = is_scalar($page_type) ? (string) $page_type : '';
    $type = is_scalar($item_type) ? (string) $item_type : '';

    $out = array(
        'accept'    => false,
        'refusal'   => '',
        'item_type' => $item_type,
        'item_id'   => $item_id,
    );

    if ($page_type === 'form item view') {
        if ($type !== 'submitted_form') {
            $out['refusal'] = 'page_type';
            return $out;
        }

        $out['accept'] = true;
        return $out;
    }

    if ($has_record_widget) {
        if ($type !== 'submitted_form') {
            $out['refusal'] = 'item_type';
            return $out;
        }

        $id = is_scalar($item_id) ? (int) $item_id : 0;
        $record_id = is_scalar($record_id) ? (int) $record_id : 0;

        if (($id <= 0) || ($record_id !== $id) || !$record_visible) {
            $out['refusal'] = 'record';
            return $out;
        }

        $out['accept'] = true;
        $out['item_type'] = 'submitted_form';
        $out['item_id'] = $record_id;
        return $out;
    }

    // A type that is no value (item_type[]=) names no thread. A missing field
    // (null) is the page itself, as before.
    $type_is_value = is_scalar($item_type) || ($item_type === null);

    if ($type_is_value && ($type === '')) {
        $out['accept'] = true;
        return $out;
    }

    if (!$type_is_value || !in_array($type, pg_comment_classic_item_types($page_type), true)) {
        $out['refusal'] = 'page_type';
        return $out;
    }

    $id = is_scalar($item_id) ? (int) $item_id : 0;

    if (($id <= 0) || !$item_shown) {
        $out['refusal'] = 'item';
        return $out;
    }

    $out['accept'] = true;
    $out['item_type'] = $type;
    $out['item_id'] = $id;
    return $out;
}

/**
 * Whether the classic page $page_id shows this product, product group or
 * calendar event, by the rule get_page_content.php uses to pick the item of
 * its comment block. The caller asks only for an item type the page type
 * names (pg_comment_classic_item_types()).
 *
 * - A product or product group: any that exists. A catalog or catalog detail
 *   page finds its item by the address name in the URL, with no tie to the
 *   page's own product group, and draws its comment block under a disabled
 *   item too ("not currently available"). An id that names nothing is drawn
 *   only under "the item could not be found" (a 404).
 * - A calendar event: one that exists, is published and sits in a calendar
 *   this calendar event view page takes (calendar_event_views_calendars_xref).
 *   For any other event get_calendar_event_view_screen_content() ends the
 *   request with an error page before the comments are drawn.
 *
 * No page, no id or another item type answers false without a query. A
 * failed query ends the request with an error page (db_value()), as
 * add_comment.php's own queries do.
 *
 * @param mixed $page_id
 * @param mixed $item_type
 * @param mixed $item_id
 * @return bool
 */
function pg_comment_classic_item_shown($page_id, $item_type, $item_id)
{
    $pid = is_scalar($page_id) ? (int) $page_id : 0;
    $type = is_scalar($item_type) ? (string) $item_type : '';
    $id = is_scalar($item_id) ? (int) $item_id : 0;

    if (($pid <= 0) || ($id <= 0)) {
        return false;
    }

    switch ($type) {
        case 'product':
            $sql = "SELECT COUNT(*) FROM products WHERE id = '" . $id . "'";
            break;

        case 'product_group':
            $sql = "SELECT COUNT(*) FROM product_groups WHERE id = '" . $id . "'";
            break;

        case 'calendar_event':
            $sql =
                "SELECT COUNT(*)
                FROM calendar_events
                INNER JOIN calendar_events_calendars_xref ON calendar_events_calendars_xref.calendar_event_id = calendar_events.id
                INNER JOIN calendar_event_views_calendars_xref ON calendar_event_views_calendars_xref.calendar_id = calendar_events_calendars_xref.calendar_id
                WHERE
                    (calendar_events.id = '" . $id . "')
                    AND (calendar_events.published != '0')
                    AND (calendar_event_views_calendars_xref.page_id = '" . $pid . "')";
            break;

        default:
            return false;
    }

    return ((int) db_value($sql) > 0);
}

/**
 * The thread a comment posted to $page_id may join: pg_comment_thread_decision()
 * over the page's record view widget and the posted item.
 *
 * A page other than a legacy form item view is asked for its form_item_view
 * widget (pg_sw_record_comment_context()), with the posted id: the widget
 * looks the record up in its own custom form only and applies its access
 * setting and record filters, with no e-mail exception. A page without one
 * that was posted an item of a type its comment block names is asked whether
 * it shows that item (pg_comment_classic_item_shown()).
 *
 * Returns the decision plus 'record': the widget's context (null when the
 * page carries no such widget). add_comment.php treats a page with an
 * accepted record as a form item view page.
 *
 * @param int|string $page_id
 * @param mixed      $page_type the page row's page_type
 * @param mixed      $item_type posted
 * @param mixed      $item_id   posted
 * @return array
 */
function pg_comment_thread_verify($page_id, $page_type, $item_type, $item_id)
{
    $record = null;

    if (!is_scalar($page_type) || ((string) $page_type !== 'form item view')) {
        $record = pg_sw_record_comment_context((int) $page_id, is_scalar($item_id) ? (int) $item_id : 0);
    }

    $record_id = 0;

    if (($record !== null) && is_array($record['form']) && !empty($record['form']['id'])) {
        $record_id = (int) $record['form']['id'];
    }

    $item_shown = false;

    if (
        ($record === null)
        && is_scalar($item_type)
        && in_array((string) $item_type, pg_comment_classic_item_types($page_type), true)
    ) {
        $item_shown = pg_comment_classic_item_shown($page_id, $item_type, $item_id);
    }

    $decision = pg_comment_thread_decision(
        $page_type,
        $record !== null,
        $item_type,
        $item_id,
        $record_id,
        ($record !== null) && !empty($record['visible']),
        $item_shown
    );

    $decision['record'] = $record;

    return $decision;
}

// Moderation of a stored comment: which form record it belongs to, and that
// record's counters.
//
// The stored row is enough to tell. add_comment.php stores item type
// 'submitted_form' only on a page that shows records: a legacy form item view
// page, or a page whose form_item_view widget shows the record
// (pg_comment_thread_verify() above). Such a designed page has page type
// 'standard', so moderation must not decide from the page type, or a designed
// record page keeps a stale comment count and its record's submitter is never
// mailed when a held comment is approved.

/**
 * The id of the form record a stored comment is about, or 0 when it is about
 * a page, product, product group or calendar event. Pure.
 *
 * @param mixed $item_type comments.item_type
 * @param mixed $item_id   comments.item_id
 * @return int
 */
function pg_comment_record_id($item_type, $item_id)
{
    if (!is_scalar($item_type) || ((string) $item_type !== 'submitted_form') || !is_scalar($item_id)) {
        return 0;
    }

    $id = (int) $item_id;

    return ($id > 0) ? $id : 0;
}

/**
 * Whether a comment's first publish mails the submitter of the form record it
 * is about (send_comment_email_to_custom_form_submitter()). Pure.
 *
 * True when the comment is about a form record, the page has a submitter
 * e-mail page, and someone other than the submitter wrote the comment: nobody
 * is mailed about their own comment. 0 is anonymous on both sides, and two
 * anonymous people are not the same person. add_comment.php applies the same
 * writer rule to the visitor who posts.
 *
 * @param mixed $item_type               comments.item_type
 * @param mixed $item_id                 comments.item_id
 * @param mixed $submitter_email_page_id page.comments_submitter_email_page_id
 * @param mixed $writer_user_id          comments.created_user_id
 * @param mixed $submitter_user_id       forms.user_id of the record
 * @return bool
 */
function pg_comment_submitter_mail_due($item_type, $item_id, $submitter_email_page_id, $writer_user_id, $submitter_user_id)
{
    if (pg_comment_record_id($item_type, $item_id) <= 0) {
        return false;
    }

    if ((is_scalar($submitter_email_page_id) ? (int) $submitter_email_page_id : 0) <= 0) {
        return false;
    }

    $writer = is_scalar($writer_user_id) ? (int) $writer_user_id : 0;
    $submitter = is_scalar($submitter_user_id) ? (int) $submitter_user_id : 0;

    return !(($writer > 0) && ($writer === $submitter));
}

/**
 * Logs a failed query of the counter rebuild. error_log() is disabled on some
 * hosts, so it is asked for first.
 */
function _pg_comment_db_failed()
{
    if (function_exists('error_log')) {
        error_log('Pinegrap: query failed: ' . mysqli_error(db::$con));
    }
}

/**
 * The first column of the first row of $sql as an integer (0 for no row or
 * NULL), or null when the query failed.
 *
 * @param string $sql
 * @param array  $params db_q() values of the ? in $sql
 * @return int|null
 */
function _pg_comment_db_int($sql, array $params = array())
{
    $result = db_q($sql, $params);

    if (!($result instanceof mysqli_result)) {
        _pg_comment_db_failed();
        return null;
    }

    $row = mysqli_fetch_row($result);
    mysqli_free_result($result);

    return (is_array($row) && ($row[0] !== null)) ? (int) $row[0] : 0;
}

/**
 * Rebuilds the submitted_form_info row of a form record on the page its
 * comment was written on, after a comment was published, held, scheduled,
 * edited or deleted. Does nothing for a comment that is not about a form
 * record.
 *
 * The form lists read number_of_comments and newest_comment_id from this row
 * (sorting by comment count, the ^^newest_comment^^ tokens, the newest
 * activity date), and so do the subjects of the comment e-mails, so the
 * callers rebuild it before they mail. number_of_views is kept as the highest
 * value of the existing rows, as the list readers take it with MAX(), and
 * duplicate rows for the pair collapse into one.
 *
 * The statements keep the legacy read, DELETE, INSERT shape without a lock on
 * purpose, like the other comment writers. A failed query is logged and
 * answers false: a failed read writes nothing, so the old counters stay. It
 * uses db_q() rather than db(), whose failure prints an HTML error
 * screen, also inside the API and MCP (the same choice as
 * pg_sfv_count_view()).
 *
 * @param mixed $page_id   comments.page_id
 * @param mixed $item_type comments.item_type
 * @param mixed $item_id   comments.item_id
 * @return bool true when the row was rebuilt
 */
function pg_comment_refresh_record_info($page_id, $item_type, $item_id)
{
    $pid = is_scalar($page_id) ? (int) $page_id : 0;
    $rid = pg_comment_record_id($item_type, $item_id);

    // class_exists() first: isset() on a static property of a class that is
    // not declared yet throws. db_connect() declares the class.
    if (($pid <= 0) || ($rid <= 0) || !class_exists('db') || !isset(db::$con) || !db::$con) {
        return false;
    }

    $pair = "(submitted_form_id = ?) AND (page_id = ?)";
    $pair_values = array((string) $rid, (string) $pid);
    $published = "(page_id = ?) AND (item_id = ?) AND (item_type = 'submitted_form') AND (published = '1')";
    $published_values = array((string) $pid, (string) $rid);

    $views = _pg_comment_db_int("SELECT MAX(number_of_views) FROM submitted_form_info WHERE " . $pair, $pair_values);
    $count = _pg_comment_db_int("SELECT COUNT(*) FROM comments WHERE " . $published, $published_values);

    if (($views === null) || ($count === null)) {
        return false;
    }

    $newest = 0;

    if ($count > 0) {
        $newest = _pg_comment_db_int("SELECT id FROM comments WHERE " . $published . " ORDER BY created_timestamp DESC, id DESC LIMIT 1", $published_values);

        if ($newest === null) {
            return false;
        }
    }

    if (!db_q("DELETE FROM submitted_form_info WHERE " . $pair, $pair_values)) {
        _pg_comment_db_failed();
        return false;
    }

    $insert = "INSERT INTO submitted_form_info (submitted_form_id, page_id, number_of_views, number_of_comments, newest_comment_id)
        VALUES (?, ?, ?, ?, ?)";

    if (!db_q($insert, array((string) $rid, (string) $pid, (string) $views, (string) $count, (string) $newest))) {
        _pg_comment_db_failed();
        return false;
    }

    return true;
}

// The name a comment is stored under. Who may type one is decided by the
// comment form (get_page_content.php) and add_comment.php alike: a visitor
// who is not signed in, staff (role < 3), a member with edit rights on the
// page's folder and the record's form editor. Every other signed-in member
// gets the name below; the form shows it and add_comment.php stores it, both
// asking about the comment's own page.

/**
 * The name a signed-in member who may not type one comments under. Pure.
 *
 * On a page of a visual design ($designed, pg_sw_page_is_visual()) it is the
 * account contact's display name (pg_contact_display_name()), else
 * lang('Member'); on any other page the login username. With the page's name
 * select on ($select), the posted name_type picks one of
 * pg_comment_name_choices(); a value the select does not offer this member
 * (empty, unknown, not a string, or an option whose names are missing)
 * counts as no choice and gives the name above.
 *
 * The result is cut to 100 characters, the size of comments.name.
 *
 * @param mixed $username  the login username
 * @param mixed $contact   the account's contact: first_name, last_name and
 *                         nickname (pg_comment_member_contact())
 * @param bool  $designed  whether the comment's page is a page of a visual design
 * @param bool  $select    whether the page lets the member choose the name
 * @param mixed $name_type the posted choice
 * @return string
 */
function pg_comment_member_name($username, $contact, $designed, $select = false, $name_type = '')
{
    if ($select) {
        $choices = pg_comment_name_choices($username, $contact, $designed);
        $type = is_scalar($name_type) ? (string) $name_type : '';

        if (($type !== '') && array_key_exists($type, $choices)) {
            return $choices[$type];
        }
    }

    if ($designed) {
        $contact = is_array($contact) ? $contact : array();
        $name = pg_contact_display_name($contact['first_name'] ?? '', $contact['last_name'] ?? '', $contact['nickname'] ?? '');

        if ($name === '') {
            $name = lang('Member');
        }
    } else {
        $name = is_scalar($username) ? (string) $username : '';
    }

    return mb_substr($name, 0, 100, 'UTF-8');
}

/**
 * A comment's name as the panel shows it: the literal 'Anonymous' the name
 * choice stores, or no name at all, reads in the panel language.
 *
 * @param mixed $name comments.name
 * @return string Plain text.
 */
function pg_comment_display_name($name)
{
    $name = (string) $name;

    return (($name === '') || ($name === 'Anonymous')) ? lang('Anonymous') : $name;
}

/**
 * The names the comment form's name select offers a signed-in member, as
 * value => stored name, in the order of the select. Pure; free of HTML (the
 * caller escapes the labels).
 *
 * - username: the name the member gets without a choice
 *   (pg_comment_member_name()): on a page of a visual design the contact's
 *   display name, else lang('Member'); elsewhere the login username.
 * - first_name: only when the contact has a first name.
 * - first_name_last_initial, first_initial_last_name,
 *   first_initial_last_initial and full_name: only when it has both names.
 * - anonymous: the literal 'Anonymous', stored untranslated.
 *
 * Each name is cut to 100 characters, the size of comments.name.
 *
 * @param mixed $username the login username
 * @param mixed $contact  the account's contact (pg_comment_member_contact())
 * @param bool  $designed whether the comment's page is a page of a visual design
 * @return array
 */
function pg_comment_name_choices($username, $contact, $designed)
{
    $contact = is_array($contact) ? $contact : array();
    $first = (isset($contact['first_name']) && is_scalar($contact['first_name'])) ? (string) $contact['first_name'] : '';
    $last = (isset($contact['last_name']) && is_scalar($contact['last_name'])) ? (string) $contact['last_name'] : '';

    $choices = array('username' => pg_comment_member_name($username, $contact, $designed, false));

    if ($first !== '') {
        $choices['first_name'] = $first;
    }

    if (($first !== '') && ($last !== '')) {
        $first_initial = mb_substr($first, 0, 1, 'UTF-8');
        $last_initial = mb_substr($last, 0, 1, 'UTF-8');

        $choices['first_name_last_initial'] = $first . ' ' . $last_initial . '.';
        $choices['first_initial_last_name'] = $first_initial . '. ' . $last;
        $choices['first_initial_last_initial'] = $first_initial . '. ' . $last_initial . '.';
        $choices['full_name'] = $first . ' ' . $last;
    }

    $choices['anonymous'] = 'Anonymous';

    foreach ($choices as $value => $name) {
        $choices[$value] = mb_substr($name, 0, 100, 'UTF-8');
    }

    return $choices;
}

/**
 * The first name, last name and nickname of a user account's contact, as
 * strings: '' for each when there is no such user or the account has no
 * contact. A failed query ends the request with an error page (db_item()),
 * as the queries of the comment form and add_comment.php did.
 *
 * @param mixed $user_id
 * @return array
 */
function pg_comment_member_contact($user_id)
{
    $contact = array('first_name' => '', 'last_name' => '', 'nickname' => '');
    $id = is_scalar($user_id) ? (int) $user_id : 0;

    if ($id <= 0) {
        return $contact;
    }

    $row = db_item(
        "SELECT
            contacts.first_name,
            contacts.last_name,
            contacts.nickname
        FROM user
        LEFT JOIN contacts ON user.user_contact = contacts.id
        WHERE user.user_id = '" . $id . "'
        LIMIT 1");

    if (is_array($row)) {
        foreach ($contact as $key => $value) {
            $contact[$key] = isset($row[$key]) ? (string) $row[$key] : '';
        }
    }

    return $contact;
}

// Replies. comments.parent_id (2026.11.0, step 11.0.31) is the comment a
// comment answers, 0 for one that answers none. A post may name only a
// comment of its own thread (page_id, item_type, item_id) that the writer can
// see: a published one, one of their own still waiting in this browser
// session, or any for staff and the record's form editor. Anything else is
// refused, never stored as 0, so a reply cannot quietly become a comment of
// its own. Deleting a comment leaves its replies; they then read as comments
// of their own. Copying a page gives each copied reply the copy of the
// comment it answers (pg_comment_copy_parent_id()). The classic comments
// block does not read the column, so a reply shows there as a later comment.

/**
 * Whether comments.parent_id is there (2026.11.0, 11.0.31). False before the
 * step has run, since files land before the upgrade, and without a
 * connection.
 *
 * @return bool
 */
function pg_comment_replies_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('pg_schema_has') && pg_schema_has('comments', 'parent_id');
    }

    return $ready;
}

/**
 * The comment id a post names as the one it answers. Pure.
 *
 * 0 when it names none (nothing posted, an empty value, 0); the id for a
 * whole positive number of up to 10 digits, spaces around it allowed; -1 for
 * anything else (an array, a bool, a sign, a fraction, letters), which the
 * caller refuses rather than reading as none.
 *
 * @param mixed $value the posted parent_id
 * @return int
 */
function pg_comment_parent_posted_id($value)
{
    if ($value === null) {
        return 0;
    }

    if (!is_scalar($value) || is_bool($value)) {
        return -1;
    }

    $text = trim((string) $value);

    if ($text === '') {
        return 0;
    }

    if (preg_match('/^[0-9]{1,10}$/', $text) !== 1) {
        return -1;
    }

    return (int) $text;
}

/**
 * Whether a post to the thread ($page_id, $item_type, $item_id) may name
 * $posted as the comment it answers. Pure: the caller reads the parent
 * (pg_comment_parent_row()) and works out the viewer.
 *
 * Decided in this order:
 * - none posted (pg_comment_parent_posted_id() is 0): accepted as a comment
 *   that answers none;
 * - a malformed value: refusal 'value';
 * - no such comment ($parent is not that comment's row): refusal 'missing';
 * - a comment of another thread (a posted item type that is not a value
 *   reads as '', an item id as 0): refusal 'thread';
 * - a comment the writer cannot see: refusal 'hidden'. Visible are a
 *   published comment, one of the writer's own of this browser session
 *   ($viewer['own_ids']), and any comment for staff ($viewer['staff']) and
 *   for the record's form editor ($viewer['form_editor']). A scheduled
 *   comment is not published yet. A $viewer that is not an array is nobody.
 * The thread is checked before visibility.
 *
 * add_comment.php answers every refusal with the same sentence, so a comment
 * the writer cannot see reads as one that does not exist.
 *
 * @param mixed $posted    the posted parent_id
 * @param mixed $parent    that comment's row (id, page_id, item_type, item_id,
 *                         published), null when there is none
 * @param mixed $page_id   the thread the post joins
 * @param mixed $item_type
 * @param mixed $item_id
 * @param mixed $viewer    array('staff' => bool, 'form_editor' => bool, 'own_ids' => int[])
 * @return array 'accept' (bool), 'refusal' ('', 'value', 'missing', 'thread'
 *               or 'hidden') and 'parent_id' (the accepted parent, else 0)
 */
function pg_comment_parent_ok($posted, $parent, $page_id, $item_type, $item_id, $viewer)
{
    $out = array('accept' => false, 'refusal' => '', 'parent_id' => 0);

    $id = pg_comment_parent_posted_id($posted);

    if ($id === 0) {
        $out['accept'] = true;
        return $out;
    }

    if ($id < 0) {
        $out['refusal'] = 'value';
        return $out;
    }

    if (!is_array($parent) || !isset($parent['id']) || !is_scalar($parent['id']) || ((int) $parent['id'] !== $id)) {
        $out['refusal'] = 'missing';
        return $out;
    }

    $parent_page = (isset($parent['page_id']) && is_scalar($parent['page_id'])) ? (int) $parent['page_id'] : 0;
    $parent_type = (isset($parent['item_type']) && is_scalar($parent['item_type'])) ? (string) $parent['item_type'] : '';
    $parent_item = (isset($parent['item_id']) && is_scalar($parent['item_id'])) ? (int) $parent['item_id'] : 0;

    if (
        ($parent_page !== (is_scalar($page_id) ? (int) $page_id : 0))
        || ($parent_type !== (is_scalar($item_type) ? (string) $item_type : ''))
        || ($parent_item !== (is_scalar($item_id) ? (int) $item_id : 0))
    ) {
        $out['refusal'] = 'thread';
        return $out;
    }

    $viewer = is_array($viewer) ? $viewer : array();
    $own_ids = array();

    if (isset($viewer['own_ids']) && is_array($viewer['own_ids'])) {
        foreach ($viewer['own_ids'] as $own_id) {
            if (is_scalar($own_id) && ((int) $own_id > 0)) {
                $own_ids[] = (int) $own_id;
            }
        }
    }

    $published = isset($parent['published']) && is_scalar($parent['published']) && ((int) $parent['published'] === 1);

    if (!$published && !in_array($id, $own_ids, true) && empty($viewer['staff']) && empty($viewer['form_editor'])) {
        $out['refusal'] = 'hidden';
        return $out;
    }

    $out['accept'] = true;
    $out['parent_id'] = $id;

    return $out;
}

/**
 * The comment a post names as the one it answers: its id, page_id,
 * item_type, item_id, published and name, or null when the value is not an
 * id, there is no such comment, or comments.parent_id is not there yet.
 * Before 11.0.31 a reply is therefore refused as 'missing' rather than
 * stored as a comment of its own. A failed query ends the request with an
 * error page (db_item()), as add_comment.php's own queries do.
 *
 * @param mixed $parent_id the posted parent_id
 * @return array|null
 */
function pg_comment_parent_row($parent_id)
{
    $id = pg_comment_parent_posted_id($parent_id);

    if (($id <= 0) || !pg_comment_replies_ready()) {
        return null;
    }

    $row = db_item(
        "SELECT
            id,
            page_id,
            item_type,
            item_id,
            published,
            name
        FROM comments
        WHERE id = '" . $id . "'
        LIMIT 1");

    return (is_array($row) && !empty($row['id'])) ? $row : null;
}

/**
 * The comment a ?reply_to= address asks to answer, when the visitor may
 * answer it in this thread: its row (pg_comment_parent_row()), else null (no
 * value, not an id, no such comment, another thread, or one the visitor
 * cannot see: pg_comment_parent_ok()). A comment form asks it before it
 * prefills anything from ?reply_to=, and again for a parent_id kept in the
 * form state after a refused post, so the name or text of a comment the
 * visitor cannot see never reaches the form.
 *
 * @param mixed $value     ?reply_to=, or the parent_id kept in the form state
 * @param mixed $page_id   the thread the form posts to
 * @param mixed $item_type
 * @param mixed $item_id
 * @param mixed $viewer    as pg_comment_parent_ok() takes it
 * @return array|null
 */
function pg_comment_reply_to($value, $page_id, $item_type, $item_id, $viewer)
{
    if (pg_comment_parent_posted_id($value) <= 0) {
        return null;
    }

    $row = pg_comment_parent_row($value);
    $check = pg_comment_parent_ok($value, $row, $page_id, $item_type, $item_id, $viewer);

    return ($check['accept'] && ($check['parent_id'] > 0)) ? $row : null;
}

/**
 * The line of the staff e-mail about a new comment that names the comment it
 * answers, "In reply to: <name> (#<id>)" and a blank line, or '' for a
 * comment that answers none. Plain text: the stored name has its line breaks
 * turned into spaces, so it cannot add lines of its own to the e-mail.
 *
 * @param mixed $parent the parent's row (pg_comment_parent_row()), or null
 * @return string
 */
function pg_comment_parent_mail_line($parent)
{
    if (!is_array($parent) || !isset($parent['id']) || !is_scalar($parent['id']) || ((int) $parent['id'] <= 0)) {
        return '';
    }

    $name = (isset($parent['name']) && is_scalar($parent['name'])) ? (string) $parent['name'] : '';
    $name = str_replace(array("\r", "\n"), ' ', $name);

    return lang('In reply to') . ': ' . $name . ' (#' . (int) $parent['id'] . ')' . "\n\n";
}

/**
 * The addresses the watcher e-mail about a new comment goes to, each once
 * (compared without case): for each watcher the e-mail of the user it is
 * connected to, else its own address. The comment's writer gets none,
 * neither through their user's watcher row ($writer_user_id) nor through a
 * row that carries their address ($writer_email). Pure.
 *
 * @param mixed $watchers       rows with user_id, user_email_address and watcher_email_address
 * @param mixed $writer_user_id the comment's created_user_id (0 for a guest)
 * @param mixed $writer_email   the writer's user e-mail ('' for a guest)
 * @return array
 */
function pg_comment_watcher_addresses($watchers, $writer_user_id, $writer_email)
{
    $value = function ($row, $key) {
        return (isset($row[$key]) && is_scalar($row[$key]) && !is_bool($row[$key])) ? trim((string) $row[$key]) : '';
    };

    $writer = (is_scalar($writer_user_id) && !is_bool($writer_user_id)) ? (int) $writer_user_id : 0;
    $own = is_string($writer_email) ? mb_strtolower(trim($writer_email)) : '';
    $addresses = array();
    $seen = array();

    foreach (is_array($watchers) ? $watchers : array() as $watcher) {
        if (!is_array($watcher) || (($writer > 0) && ((int) $value($watcher, 'user_id') === $writer))) {
            continue;
        }

        $address = $value($watcher, 'user_email_address');

        if ($address === '') {
            $address = $value($watcher, 'watcher_email_address');
        }

        $key = mb_strtolower($address);

        if (($address === '') || ($key === $own) || isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $addresses[] = $address;
    }

    return $addresses;
}

/**
 * The parent_id a reply gets when its page is copied: the new id of the copy
 * of the comment it answers, or 0 when it answers none or that comment was
 * not copied (deleted, so the reply already reads as a comment of its own).
 * Pure. duplicate_page_f.php copies a page's comments oldest first and keeps
 * old id => new id in $copied; a reply always has a higher id than the
 * comment it answers, which existed when the reply was stored.
 *
 * @param mixed $parent_id the original reply's parent_id
 * @param mixed $copied    array(original comment id => id of its copy)
 * @return int
 */
function pg_comment_copy_parent_id($parent_id, $copied)
{
    $id = (is_scalar($parent_id) && !is_bool($parent_id)) ? (int) $parent_id : 0;

    if (($id <= 0) || !is_array($copied) || !isset($copied[$id]) || !is_scalar($copied[$id])) {
        return 0;
    }

    return max(0, (int) $copied[$id]);
}

// Links to one comment. They name it with comment=<id>: a comments widget
// opens the page of the thread that holds it and leaves the featured filter;
// the classic comments block ignores the parameter and shows every comment
// (comments=all) as before. The caller appends the comment's anchor,
// '#c-<id>'.

/**
 * The query a link to one comment ends with: $query_string (what the page
 * needs to show the comment's item: '', '?r=<code>', '?id=<n>' or
 * '/<address name>', get_query_string_for_page_url()) followed by
 * comments=all and comment=<id>. Pure. A query string that is not a value
 * reads as ''.
 *
 * @param mixed $query_string
 * @param mixed $comment_id
 * @return string
 */
function pg_comment_view_query($query_string, $comment_id)
{
    $query_string = (is_scalar($query_string) && !is_bool($query_string)) ? (string) $query_string : '';
    $id = (is_scalar($comment_id) && !is_bool($comment_id)) ? (int) $comment_id : 0;

    return $query_string . ((mb_strpos($query_string, '?') === false) ? '?' : '&') . 'comments=all&comment=' . $id;
}

/**
 * The address add_comment.php sends the writer back to after a post: the
 * page they posted from ($path, pg_safe_redirect_path()'s answer), without
 * the comment they answered (reply_to), the page of the thread they were on
 * (comments_page) and an earlier comment=, then comment=<the new id>, so a
 * comments widget opens the page that holds the new comment. While the
 * thread has featured comments ($featured) the view shows every comment
 * (comments=all), as before. Pure.
 *
 * The query is read with parse_str() and written again with
 * http_build_query(): a value keeps its meaning, an array parameter is
 * written with numbered keys. A path parse_url() cannot read, or one
 * without a path, gets comment=<id> appended as it is. No fragment: the
 * caller adds '#c-<id>'.
 *
 * @param mixed $path
 * @param mixed $comment_id
 * @param bool  $featured
 * @return string
 */
function pg_comment_return_url($path, $comment_id, $featured)
{
    $path = (is_scalar($path) && !is_bool($path)) ? (string) $path : '';
    $id = (is_scalar($comment_id) && !is_bool($comment_id)) ? (int) $comment_id : 0;
    $parts = parse_url($path);

    if (!is_array($parts) || !isset($parts['path'])) {
        return $path . ((strpos($path, '?') === false) ? '?' : '&') . 'comment=' . $id;
    }

    $query = array();
    parse_str(isset($parts['query']) ? $parts['query'] : '', $query);
    unset($query['reply_to'], $query['comments_page'], $query['comment']);

    if ($featured) {
        unset($query['comments']);
        $query['comments'] = 'all';
    }

    $query['comment'] = $id;

    return $parts['path'] . '?' . http_build_query($query, '', '&');
}

// The thread as a page shows it. The plural pg_comments_* helpers decide what
// a page's comment thread is: the page's comment settings, whether the thread
// takes new comments, who reads it, which comments they read and the order
// they are drawn in. The classic comments block (get_page_content.php) asks
// them only for these decisions and keeps building its own HTML;
// add_comment.php asks the same allow-new rule. pg_comments_rows() sends the
// statements the block always sent, character for character apart from
// whitespace: rows are sorted by created_timestamp alone, so comments posted
// in the same second come in whatever order the server's plan returns for
// that statement, and a different statement (a tie-break, an extra column, a
// reshaped WHERE) could reorder them. Each helper keeps the block's loose
// comparisons on the raw values, so every PHP version answers as it did.

/**
 * Whether a page row's column holds a value equal (==) to $value. A missing
 * column, or one that is not a value (an array: the rows a first blank
 * <system> region left in the page's comments switch), is false.
 *
 * @param array  $row
 * @param string $key
 * @param mixed  $value the operand the block compares with ('1' or 1)
 * @return bool
 */
function _pg_comments_setting_is($row, $key, $value)
{
    return isset($row[$key]) && is_scalar($row[$key]) && ($row[$key] == $value);
}

/**
 * A page row's text column as a string, '' when it is missing or not a value.
 *
 * @param array  $row
 * @param string $key
 * @return string
 */
function _pg_comments_setting_text($row, $key)
{
    return (isset($row[$key]) && is_scalar($row[$key])) ? (string) $row[$key] : '';
}

/**
 * A page row's id column as a whole number, 0 when it is missing, not a
 * value, a bool or below 0.
 *
 * @param array  $row
 * @param string $key
 * @return int
 */
function _pg_comments_setting_id($row, $key)
{
    $id = (isset($row[$key]) && is_scalar($row[$key]) && !is_bool($row[$key])) ? (int) $row[$key] : 0;

    return max(0, $id);
}

/**
 * A page's comment settings, read from its page row (the page table's
 * comments_* columns, or the region's $system_region_properties). Pure.
 *
 * Each switch keeps the operator the classic block and the handlers test the
 * column with: 'enabled' (comments) and 'auto_publish'
 * (comments_automatic_publish) are == '1', 'rating' (comments_rating) is not
 * empty, every other switch is == 1. A column that is missing or not a value
 * is off, '' or 0. The classic block takes only 'enabled' and 'allow_new'
 * from here; its stars and rating field read the drawn page's
 * $comments_rating, which a named <system> region does not change.
 *
 * @param mixed $page_row the page row, an array of comments_* columns
 * @return array 'enabled', 'label', 'message', 'rating', 'allow_new',
 *               'closed_message', 'auto_publish', 'select_name',
 *               'require_login', 'attachments', 'show_date', 'admin_email',
 *               'conditional_admins', 'submitter_email_page_id',
 *               'watcher_email_page_id', 'watchers_by_submitter'
 */
function pg_comments_settings($page_row)
{
    $row = is_array($page_row) ? $page_row : array();

    return array(
        'enabled' => _pg_comments_setting_is($row, 'comments', '1'),
        'label' => _pg_comments_setting_text($row, 'comments_label'),
        'message' => _pg_comments_setting_text($row, 'comments_message'),
        'rating' => isset($row['comments_rating']) && is_scalar($row['comments_rating']) && !empty($row['comments_rating']),
        'allow_new' => _pg_comments_setting_is($row, 'comments_allow_new_comments', 1),
        'closed_message' => _pg_comments_setting_text($row, 'comments_disallow_new_comment_message'),
        'auto_publish' => _pg_comments_setting_is($row, 'comments_automatic_publish', '1'),
        'select_name' => _pg_comments_setting_is($row, 'comments_allow_user_to_select_name', 1),
        'require_login' => _pg_comments_setting_is($row, 'comments_require_login_to_comment', 1),
        'attachments' => _pg_comments_setting_is($row, 'comments_allow_file_attachments', 1),
        'show_date' => _pg_comments_setting_is($row, 'comments_show_submitted_date_and_time', 1),
        'admin_email' => _pg_comments_setting_text($row, 'comments_administrator_email_to_email_address'),
        'conditional_admins' => _pg_comments_setting_is($row, 'comments_administrator_email_conditional_administrators', 1),
        'submitter_email_page_id' => _pg_comments_setting_id($row, 'comments_submitter_email_page_id'),
        'watcher_email_page_id' => _pg_comments_setting_id($row, 'comments_watcher_email_page_id'),
        'watchers_by_submitter' => _pg_comments_setting_is($row, 'comments_watchers_managed_by_submitter', 1),
    );
}

/**
 * The page comment settings the visual designer edits, by the key the editor
 * sends them under (the hidden pg_comments* inputs, a page tab's fields).
 * Pure.
 *
 * Each key is array('kind', 'columns', 'max'):
 * - 'flag': a switch, written 1 or 0;
 * - 'page': a page id, one page for both comment e-mails (to the record's
 *   sender and to the watchers);
 * - 'text': trimmed and cut to 'max' characters; the e-mail subject fills
 *   the subject of both e-mails.
 *
 * The save (pg_designer_save_page()), the Preview's page override
 * (pg_comments_page_override()), the editor's field list (_PG_CMT_FIELDS in
 * style_designer.js, the same keys in the same order) and the editor screen
 * follow this map.
 *
 * @return array
 */
function pg_comment_editor_keys()
{
    $flag = function ($column) {
        return array('kind' => 'flag', 'columns' => array($column), 'max' => 0);
    };
    $text = function ($columns, $max) {
        return array('kind' => 'text', 'columns' => $columns, 'max' => $max);
    };

    return array(
        'pg_comments' => $flag('comments'),
        'pg_comments_label' => $text(array('comments_label'), 100),
        'pg_comments_allow_new' => $flag('comments_allow_new_comments'),
        'pg_comments_rating' => $flag('comments_rating'),
        'pg_comments_auto_publish' => $flag('comments_automatic_publish'),
        'pg_comments_show_date' => $flag('comments_show_submitted_date_and_time'),
        'pg_comments_login' => $flag('comments_require_login_to_comment'),
        'pg_comments_email_page' => array('kind' => 'page', 'columns' => array('comments_submitter_email_page_id', 'comments_watcher_email_page_id'), 'max' => 0),
        'pg_comments_email_subject' => $text(array('comments_submitter_email_subject', 'comments_watcher_email_subject'), 255),
        'pg_comments_notify_email' => $text(array('comments_administrator_email_to_email_address'), 100),
        'pg_comments_attachments' => $flag('comments_allow_file_attachments'),
        'pg_comments_select_name' => $flag('comments_allow_user_to_select_name'),
        'pg_comments_message' => $text(array('comments_message'), 255),
        'pg_comments_closed_message' => $text(array('comments_disallow_new_comment_message'), 255),
        'pg_comments_watchers_managed' => $flag('comments_watchers_managed_by_submitter'),
    );
}

/**
 * The page columns, and their values, for the comment settings the editor
 * sent (pg_comment_editor_keys()). Pure; the values are not escaped for SQL.
 *
 * Only the keys present in $fields are written; any other key is left out.
 * A switch is 1 for 1, '1' or true, else 0. A page is a whole number above 0,
 * else 0: an unsaved tab (`tab:<key>`, written once the save knows its id),
 * a negative number or anything that is not a value. A text is trimmed and
 * cut to its length in characters; anything that is not a value is ''.
 *
 * @param mixed $fields editor key => value
 * @return array column => value
 */
function pg_comment_editor_columns($fields)
{
    $out = array();

    if (!is_array($fields)) {
        return $out;
    }

    foreach (pg_comment_editor_keys() as $key => $def) {
        if (!array_key_exists($key, $fields)) {
            continue;
        }

        $raw = $fields[$key];

        if ($def['kind'] === 'flag') {
            $value = ($raw === 1 || $raw === '1' || $raw === true) ? 1 : 0;
        } elseif ($def['kind'] === 'page') {
            $value = (is_scalar($raw) && !is_bool($raw) && preg_match('/^\s*\d+\s*$/', (string) $raw)) ? (int) $raw : 0;
            $value = max(0, $value);
        } else {
            $value = (is_scalar($raw) && !is_bool($raw)) ? mb_substr(trim((string) $raw), 0, $def['max']) : '';
        }

        foreach ($def['columns'] as $column) {
            $out[$column] = $value;
        }
    }

    return $out;
}

/**
 * Whether a thread takes new comments. Pure: pg_comments_allow_new() reads
 * the item's row.
 *
 * An item's thread (an item id that is a value and != 0, the block's loose
 * test on the raw id) whose allow_new_comments_for_items row was found takes
 * them when that row's value == 1. Any other thread follows the page's
 * switch. An item id that is not a value (a forged address or post) reads as
 * no item.
 *
 * @param mixed $item_id      the thread's item id, as the caller holds it
 * @param mixed $item_setting the row's allow_new_comments, '' when the row
 *                            lacks it, null when there is no row
 * @param mixed $page_allows  the page's switch (pg_comments_settings()'s
 *                            'allow_new')
 * @return bool
 */
function pg_comments_allow_new_decision($item_id, $item_setting, $page_allows)
{
    if (is_scalar($item_id) && ($item_id != 0) && ($item_setting !== null)) {
        return is_scalar($item_setting) && ($item_setting == 1);
    }

    return (bool) $page_allows;
}

/**
 * A value for a quoted SQL literal: escape() of the value as a string, '' for
 * null and for anything that is not a value. The same text the block's
 * escape($raw) gave for every value and null.
 *
 * @param mixed $value
 * @return string
 */
function _pg_comments_sql_value($value)
{
    return escape((is_scalar($value) || ($value === null)) ? (string) $value : '');
}

/**
 * Whether the thread (page, item type, item id) takes new comments: an
 * item's own switch in allow_new_comments_for_items when it has one, else
 * the page's. The classic comment form and add_comment.php ask the same.
 *
 * Only an item's thread (pg_comments_allow_new_decision()) is looked up, with
 * the statement both callers always sent; the first row decides. A failed
 * query ends on the db helpers' error page.
 *
 * @param mixed    $page_id
 * @param mixed    $item_type
 * @param mixed    $item_id
 * @param mixed    $page_allows the page's switch, as pg_comments_settings()
 *                              reads it ('allow_new')
 * @param callable $fetch       runs a SELECT and returns its rows (db_items)
 * @return bool
 */
function pg_comments_allow_new($page_id, $item_type, $item_id, $page_allows, $fetch = 'db_items')
{
    $setting = null;

    if (is_scalar($item_id) && ($item_id != 0)) {
        $rows = call_user_func($fetch,
            "SELECT allow_new_comments
            FROM allow_new_comments_for_items
            WHERE
                (page_id = '" . _pg_comments_sql_value($page_id) . "')
                AND (item_id = '" . _pg_comments_sql_value($item_id) . "')
                AND (item_type = '" . _pg_comments_sql_value($item_type) . "')");

        if (is_array($rows) && isset($rows[0]) && is_array($rows[0])) {
            $setting = isset($rows[0]['allow_new_comments']) ? $rows[0]['allow_new_comments'] : '';
        }
    }

    return pg_comments_allow_new_decision($item_id, $setting, $page_allows);
}

/**
 * Who reads a thread. Pure: pg_comments_viewer() takes the user and the
 * session from the request.
 *
 * - 'staff': edit access to the page; reads every comment.
 * - 'form_editor': the signed-in user ($user_id > 0) is the shown record's
 *   form editor: the record names a username that is not '' and its editor
 *   id is that user. Reads every comment, as staff. Never on a thread that
 *   is not a record's ($record_form null).
 * - 'own_ids': this browser session's comments, perhaps still awaiting
 *   approval, as positive whole numbers in the session's order (duplicates
 *   kept); none for the search index.
 * - 'search_index': the search index stores what an anonymous visitor reads,
 *   so it reads the published comments only, whoever runs it.
 *
 * The array is the viewer pg_comment_parent_ok() and pg_comment_reply_to()
 * take.
 *
 * @param mixed $staff        edit access to the page
 * @param mixed $record_form  the shown record's form (form_editor_user_id,
 *                            form_editor_username), null for a thread that is
 *                            not a record's
 * @param mixed $search_index the page is drawn for the search index
 * @param mixed $user_id      the signed-in user's id, 0 when signed out
 * @param mixed $session_ids  $_SESSION['software']['added_comments']
 * @return array 'staff' (bool), 'form_editor' (bool), 'own_ids' (int[]),
 *               'search_index' (bool)
 */
function pg_comments_viewer_decision($staff, $record_form, $search_index, $user_id, $session_ids)
{
    $uid = (is_scalar($user_id) && !is_bool($user_id)) ? (int) $user_id : 0;
    $form_editor = false;

    if (is_array($record_form) && ($uid > 0)) {
        $name = isset($record_form['form_editor_username']) ? $record_form['form_editor_username'] : null;
        $editor = isset($record_form['form_editor_user_id']) ? $record_form['form_editor_user_id'] : null;
        $form_editor = is_scalar($name) && ((string) $name !== '')
            && is_scalar($editor) && !is_bool($editor) && ((int) $editor === $uid);
    }

    $own_ids = array();

    if (!$search_index && is_array($session_ids)) {
        foreach ($session_ids as $id) {
            if (is_scalar($id) && !is_bool($id) && ((int) $id > 0)) {
                $own_ids[] = (int) $id;
            }
        }
    }

    return array(
        'staff' => (bool) $staff,
        'form_editor' => $form_editor,
        'own_ids' => $own_ids,
        'search_index' => (bool) $search_index,
    );
}

/**
 * Who reads a thread on this request (pg_comments_viewer_decision()): the
 * signed-in user and this browser session's comments.
 *
 * @param mixed $staff        edit access to the page
 * @param mixed $record_form  the shown record's form (form_editor_user_id,
 *                            form_editor_username), or null for a thread that
 *                            is not a record's
 * @param mixed $search_index the page is drawn for the search index
 * @return array as pg_comments_viewer_decision() returns it
 */
function pg_comments_viewer($staff, $record_form, $search_index = false)
{
    $user_id = (defined('USER_LOGGED_IN') && USER_LOGGED_IN && defined('USER_ID')) ? USER_ID : 0;
    $session_ids = (isset($_SESSION['software']['added_comments']) && is_array($_SESSION['software']['added_comments']))
        ? $_SESSION['software']['added_comments']
        : array();

    return pg_comments_viewer_decision($staff, $record_form, $search_index, $user_id, $session_ids);
}

/**
 * The SQL filter of an item's thread, as the classic block wrote it: an AND
 * clause on item_id and item_type, each value escaped (anything that is not
 * a value reads ''). Null, or anything that is not an array, gives '': every
 * comment on the page, as the block reads a page that shows no item,
 * whatever item a comment was posted for. The block's watcher queries take
 * the same text.
 *
 * @param mixed $thread array('item_type' => ..., 'item_id' => ...), or null
 * @return string
 */
function pg_comments_thread_sql($thread)
{
    if (!is_array($thread)) {
        return '';
    }

    return " AND (item_id = '" . _pg_comments_sql_value(isset($thread['item_id']) ? $thread['item_id'] : null) . "')"
        . " AND (item_type = '" . _pg_comments_sql_value(isset($thread['item_type']) ? $thread['item_type'] : null) . "')";
}

/**
 * The SQL filter of the comments a viewer reads, as the classic block wrote
 * it: none for staff and the form editor; otherwise the published comments
 * and the viewer's own of this session, one (comments.id = 'N') each; the
 * published only for the search index. A viewer that is not an array is
 * nobody.
 *
 * @param mixed $viewer as pg_comments_viewer() returns it
 * @return string
 */
function pg_comments_visible_sql($viewer)
{
    $viewer = is_array($viewer) ? $viewer : array();
    $search_index = !empty($viewer['search_index']);

    if ((!empty($viewer['staff']) || !empty($viewer['form_editor'])) && !$search_index) {
        return '';
    }

    $own = '';

    if (!$search_index && isset($viewer['own_ids']) && is_array($viewer['own_ids'])) {
        foreach ($viewer['own_ids'] as $id) {
            if (!is_scalar($id) || is_bool($id) || ((int) $id <= 0)) {
                continue;
            }

            if ($own !== '') {
                $own .= " OR ";
            }

            $own .= "(comments.id = '" . (int) $id . "')";
        }

        if ($own !== '') {
            $own = " OR (" . $own . ")";
        }
    }

    return " AND ((comments.published = '1')" . $own . ")";
}

/**
 * Whether only the featured comments are read. Pure.
 *
 * Only in the 'filter' mode (the classic block's), while featured comments
 * exist, and when ?comments is unset or exactly 'featured': the same answer
 * as the block's isset($_GET['comments']) == false ||
 * $_GET['comments'] == 'featured' for every value ?comments can carry. The
 * widget's 'first' and 'none' modes never filter.
 *
 * @param mixed $has_featured whether the thread has featured comments
 * @param mixed $mode         'filter', 'first' or 'none'
 * @param mixed $view         the ?comments value, null when unset
 * @return bool
 */
function pg_comments_featured_filter($has_featured, $mode, $view)
{
    return ($mode === 'filter') && $has_featured && (($view === null) || (is_string($view) && ($view === 'featured')));
}

/**
 * The first value of the first row as a whole number, 0 for no row or a
 * value that is not one.
 *
 * @param mixed $rows
 * @return int
 */
function _pg_comments_first_int($rows)
{
    if (!is_array($rows) || !isset($rows[0]) || !is_array($rows[0]) || !$rows[0]) {
        return 0;
    }

    $value = reset($rows[0]);

    return (is_scalar($value) && !is_bool($value)) ? (int) $value : 0;
}

/**
 * The comments a viewer reads on a thread, oldest first, with the counts the
 * classic block shows.
 *
 * Sends, in this order, the block's three statements: the count of what the
 * viewer reads, the count of the featured ones among them, then the rows.
 * When only the featured comments are read (pg_comments_featured_filter()),
 * the rows statement alone filters them, so the count is of all.
 *
 * The default statements must not change: no tie-break and no extra column.
 * Rows are sorted by created_timestamp alone, and the order of comments
 * posted in the same second is whatever the server's plan returns for the
 * statement.
 *
 * Options:
 * - 'featured': 'filter' (default, the block's) reads only the featured
 *   comments while some exist and the view is null (?comments unset) or
 *   exactly 'featured' (pg_comments_featured_filter()); '', 'all' or any
 *   other string reads every comment. Any other mode applies no filter, and
 *   the caller pins or ignores featured comments.
 * - 'view': the ?comments value; pass null, not '', when ?comments is unset,
 *   or the filter mode reads every comment.
 * - 'parent_id': true adds comments.parent_id after created_timestamp
 *   (0 AS parent_id before 11.0.31). The block never asks.
 * - 'thread_key': true adds comments.item_type and comments.item_id after
 *   those, so a caller that reads every comment on a page (a null thread)
 *   knows which thread each row belongs to. The block never asks.
 *
 * A failed query ends on the db helpers' error page.
 *
 * @param mixed    $page_id
 * @param mixed    $thread  pg_comments_thread_sql()'s thread, null for every
 *                          comment on the page
 * @param mixed    $viewer  as pg_comments_viewer() returns it
 * @param mixed    $options see above
 * @param callable $fetch   runs a SELECT and returns its rows (db_items)
 * @return array 'count' (int), 'has_featured' (bool), 'featured_view'
 *               (bool), 'rows' (the rows as read, a list)
 */
function pg_comments_rows($page_id, $thread, $viewer, $options = array(), $fetch = 'db_items')
{
    $options = is_array($options) ? $options : array();
    $mode = (isset($options['featured']) && is_string($options['featured'])) ? $options['featured'] : 'filter';
    $view = array_key_exists('view', $options) ? $options['view'] : null;
    $page = _pg_comments_sql_value($page_id);
    $where = pg_comments_thread_sql($thread) . pg_comments_visible_sql($viewer);

    $count = _pg_comments_first_int(call_user_func($fetch,
        "SELECT COUNT(*) FROM comments WHERE comments.page_id = '" . $page . "'" . $where));

    $featured_count = _pg_comments_first_int(call_user_func($fetch,
        "SELECT COUNT(*)
        FROM comments
        WHERE
            (comments.page_id = '" . $page . "')
            " . $where . "
            AND comments.featured = '1'"));

    $has_featured = ($featured_count > 0);
    $featured_view = pg_comments_featured_filter($has_featured, $mode, $view);

    if ($featured_view) {
        $where .= " AND (comments.featured = '1')";
    }

    $parent = '';

    if (!empty($options['parent_id'])) {
        $parent = pg_comment_replies_ready()
            ? ",
            comments.parent_id"
            : ",
            0 AS parent_id";
    }

    if (!empty($options['thread_key'])) {
        $parent .= ",
            comments.item_type,
            comments.item_id";
    }

    $rows = call_user_func($fetch,
        "SELECT
            comments.id,
            comments.name,
            comments.message,
            files.name as file_name,
            files.size as file_size,
            comments.published,
            comments.publish_date_and_time,
            comments.publish_cancel,
            comments.featured,
            comments.rating,
            " . (pg_reviews_ready() ? "comments.verified_order_id," : "0 AS verified_order_id,") . "
            " . (pg_review_replies_ready() ? "comments.seller_reply, comments.seller_reply_at," : "'' AS seller_reply, 0 AS seller_reply_at,") . "
            user.user_username as username,
            user.user_role,
            user.user_id,
            user.user_badge,
            user.user_badge_label,
            comments.created_timestamp" . $parent . "
        FROM comments
        LEFT JOIN files ON comments.file_id = files.id
        LEFT JOIN user ON comments.created_user_id = user.user_id
        WHERE comments.page_id = '" . $page . "'" . $where . "
        ORDER BY comments.created_timestamp ASC");

    return array(
        'count' => $count,
        'has_featured' => $has_featured,
        'featured_view' => $featured_view,
        'rows' => is_array($rows) ? array_values($rows) : array(),
    );
}

/**
 * A row's id column as a whole number, 0 when it is missing, not a value, a
 * bool or below 0.
 *
 * @param mixed  $row
 * @param string $key
 * @return int
 */
function _pg_comments_row_int($row, $key)
{
    return (is_array($row) && isset($row[$key]) && is_scalar($row[$key]) && !is_bool($row[$key])) ? max(0, (int) $row[$key]) : 0;
}

/**
 * The order a thread's comments are drawn in. Pure.
 *
 * - 'threaded': depth-first under each top-level comment, replies in the
 *   order read; the depth is capped at $max_depth (1-3; anything else
 *   clamps, default 1): a reply deeper than that is drawn at the cap and
 *   keeps its real parent for its quote.
 * - any other layout ('flat'): the order read, every depth 0.
 *
 * A reply's parent counts only when it is among the rows (the first row with
 * that id). A parent that is not (deleted, someone else's pending comment,
 * cut by the featured filter) or the comment itself makes a top-level
 * comment, as do rows without parent_id (the classic block's). A loop is cut
 * at the first of its rows met walking up from each row in the order read;
 * that row becomes top-level. Entries that are not rows are skipped. The walk keeps its own stack, so a
 * long thread needs no recursion.
 *
 * The comments widget is the first caller; the classic block ignores
 * parent_id and draws the order read.
 *
 * @param mixed $rows      comment rows (id, parent_id, ...), oldest first
 * @param mixed $layout    'threaded' or 'flat'
 * @param mixed $max_depth 1-3
 * @return array one entry per row: 'row' (the row as given), 'id',
 *               'parent_id' (the parent among the rows, else 0), 'depth',
 *               'root_id' (its top-level comment), 'replies' (direct replies
 *               among the rows)
 */
function pg_comments_thread_order($rows, $layout, $max_depth)
{
    $threaded = ($layout === 'threaded');
    $cap = (is_scalar($max_depth) && !is_bool($max_depth)) ? (int) $max_depth : 1;
    $cap = min(3, max(1, $cap));

    $list = array();

    foreach ((is_array($rows) ? $rows : array()) as $row) {
        if (is_array($row)) {
            $list[] = $row;
        }
    }

    $count = count($list);

    if ($count === 0) {
        return array();
    }

    $ids = array();
    $first = array();

    foreach ($list as $i => $row) {
        $ids[$i] = _pg_comments_row_int($row, 'id');

        if (($ids[$i] > 0) && !isset($first[$ids[$i]])) {
            $first[$ids[$i]] = $i;
        }
    }

    // Each row's link to the row it answers, -1 for none.
    $link = array();

    foreach ($list as $i => $row) {
        $parent = _pg_comments_row_int($row, 'parent_id');
        $link[$i] = (($parent > 0) && isset($first[$parent]) && ($first[$parent] !== $i)) ? $first[$parent] : -1;
    }

    // Break loops. Walking up from each row in the order read (0 not seen,
    // 1 on this walk, 2 done), a row met twice on the same walk closes a
    // loop and is cut from its parent.
    $state = array_fill(0, $count, 0);

    for ($i = 0; $i < $count; $i++) {
        $path = array();
        $p = $i;

        while (($p !== -1) && ($state[$p] === 0)) {
            $state[$p] = 1;
            $path[] = $p;
            $p = $link[$p];
        }

        if (($p !== -1) && ($state[$p] === 1)) {
            $link[$p] = -1;
        }

        foreach ($path as $q) {
            $state[$q] = 2;
        }
    }

    $children = array_fill(0, $count, array());
    $roots = array();

    for ($i = 0; $i < $count; $i++) {
        if ($link[$i] === -1) {
            $roots[] = $i;
        } else {
            $children[$link[$i]][] = $i;
        }
    }

    // Depth-first from each top-level row, replies in the order read.
    $order = array();
    $depth = array();
    $root = array();

    foreach ($roots as $r) {
        $stack = array(array($r, 0));

        while ($stack) {
            list($n, $d) = array_pop($stack);
            $order[] = $n;
            $depth[$n] = $d;
            $root[$n] = $r;

            for ($c = count($children[$n]) - 1; $c >= 0; $c--) {
                $stack[] = array($children[$n][$c], $d + 1);
            }
        }
    }

    $out = array();

    foreach (($threaded ? $order : array_keys($list)) as $i) {
        $out[] = array(
            'row' => $list[$i],
            'id' => $ids[$i],
            'parent_id' => ($link[$i] === -1) ? 0 : $ids[$link[$i]],
            'depth' => $threaded ? min($depth[$i], $cap) : 0,
            'root_id' => $ids[$root[$i]],
            'replies' => count($children[$i]),
        );
    }

    return $out;
}
