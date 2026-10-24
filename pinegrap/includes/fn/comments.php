<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Comment rules shared by the comment handlers: which thread a comment posted
 * to a page may join, which form record a stored comment is about, the
 * record's comment counters moderation keeps up to date, the name a
 * signed-in member's comment is stored under, and which comment a reply may
 * answer.
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
 * @return int|null
 */
function _pg_comment_db_int($sql)
{
    $result = @mysqli_query(db::$con, $sql);

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
 * uses @mysqli_query() rather than db(), whose failure prints an HTML error
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

    $pair = "(submitted_form_id = '" . $rid . "') AND (page_id = '" . $pid . "')";
    $published = "(page_id = '" . $pid . "') AND (item_id = '" . $rid . "') AND (item_type = 'submitted_form') AND (published = '1')";

    $views = _pg_comment_db_int("SELECT MAX(number_of_views) FROM submitted_form_info WHERE " . $pair);
    $count = _pg_comment_db_int("SELECT COUNT(*) FROM comments WHERE " . $published);

    if (($views === null) || ($count === null)) {
        return false;
    }

    $newest = 0;

    if ($count > 0) {
        $newest = _pg_comment_db_int("SELECT id FROM comments WHERE " . $published . " ORDER BY created_timestamp DESC, id DESC LIMIT 1");

        if ($newest === null) {
            return false;
        }
    }

    if (!@mysqli_query(db::$con, "DELETE FROM submitted_form_info WHERE " . $pair)) {
        _pg_comment_db_failed();
        return false;
    }

    $insert = "INSERT INTO submitted_form_info (submitted_form_id, page_id, number_of_views, number_of_comments, newest_comment_id)
        VALUES ('" . $rid . "', '" . $pid . "', '" . $views . "', '" . $count . "', '" . $newest . "')";

    if (!@mysqli_query(db::$con, $insert)) {
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
