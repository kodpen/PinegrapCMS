<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The reader behind the visual designer's History "Activity" tab: what
 * happened to one design and who did it, read from rows the product
 * already keeps. No table of its own.
 *
 * Stores read, each one a "store" name in an event's key:
 *   revision  page_revisions: a page's version (publish, restore, import,
 *             assistant, release bundle); an applied content change names
 *             its editor from design_proposals (pg_page_revisions_editor())
 *   audit     audit_log: a page moved to the Recycle Bin, brought back,
 *             deleted for good, taken off the site or put on it (type
 *             page); a design file made, saved or deleted (type file); the
 *             design saved, renamed, duplicated, binned or deleted (type
 *             style); a shared component made, saved, renamed, set or
 *             deleted (type shared_component)
 *   link      prototype_links: a share link made
 *   revoke    prototype_links: a share link revoked
 *   verdict   prototype_verdicts: the customer approved a page or asked
 *             for changes
 *   comment   prototype_comments: the first comment of a thread
 *
 * An event holds who, when, the action and the record's name, never the
 * values before and after (those stay on the Audit trail screen). A person
 * is named by the user row of the stored id, so an erased or deleted user
 * shows as "A removed user"; the list keeps no name of its own.
 *
 * Paging: events newest first, then by store name and row id, both
 * descending, so every event has one place. A page is fifty events; the
 * cursor is the last event's "<time>:<store>:<id>", and each store reads
 * only the rows after it (pg_designer_activity_after_sql()), one over the
 * page size, so the merge can tell whether more remain.
 *
 * Rights: the designer roles see every event of the design; a content
 * editor (Manager, User) sees the events of the pages they may edit and no
 * design-level event (a share link, a design file).
 *
 * Loaded on demand by the panel action that serves the tab.
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
 * Events in one page of the list.
 */
define('PG_DESIGNER_ACTIVITY_PAGE', 50);

/**
 * The stores an event's key may name.
 *
 * @return string[]
 */
function pg_designer_activity_stores()
{
    return array('audit', 'comment', 'link', 'revision', 'revoke', 'verdict');
}

/**
 * The cursor that follows an event. Pure.
 *
 * @param array $event store, id, at
 * @return string
 */
function pg_designer_activity_cursor($event)
{
    return (int) $event['at'] . ':' . (string) $event['store'] . ':' . (int) $event['id'];
}

/**
 * A cursor read back; null for anything that is not one. Pure.
 *
 * @param string $cursor
 * @return array|null at, store, id
 */
function pg_designer_activity_cursor_parse($cursor)
{
    if (!is_string($cursor) || !preg_match('/^(\d{1,10}):([a-z]{1,16}):(\d{1,10})$/', $cursor, $m)) {
        return null;
    }

    if (!in_array($m[2], pg_designer_activity_stores(), true)) {
        return null;
    }

    return array('at' => (int) $m[1], 'store' => $m[2], 'id' => (int) $m[3]);
}

/**
 * Whether an event comes after the cursor in the list's order. Pure; the
 * same rule as pg_designer_activity_after_sql().
 *
 * @param array      $event  store, id, at
 * @param array|null $cursor pg_designer_activity_cursor_parse()
 * @return bool
 */
function pg_designer_activity_is_after($event, $cursor)
{
    if (!$cursor) {
        return true;
    }

    $at = (int) $event['at'];

    if ($at !== $cursor['at']) {
        return $at < $cursor['at'];
    }

    $order = strcmp((string) $event['store'], $cursor['store']);

    return ($order !== 0) ? ($order < 0) : ((int) $event['id'] < $cursor['id']);
}

/**
 * The SQL condition that keeps a store's rows after the cursor. Pure; the
 * cursor is parsed, so every value in it is an integer or a known store.
 *
 * @param string     $store  this store's name
 * @param array|null $cursor pg_designer_activity_cursor_parse()
 * @param string     $at     the time column
 * @param string     $id     the id column
 * @return string
 */
function pg_designer_activity_after_sql($store, $cursor, $at, $id)
{
    if (!$cursor) {
        return '1';
    }

    $time  = (int) $cursor['at'];
    $order = strcmp((string) $store, $cursor['store']);

    if ($order < 0) {
        return "($at <= $time)";
    }

    if ($order > 0) {
        return "($at < $time)";
    }

    return "($at < $time OR ($at = $time AND $id < " . (int) $cursor['id'] . '))';
}

/**
 * The stores' events as one page: newest first, cut to `$limit`. Pure.
 *
 * @param array $lists lists of events, each read one over the page size
 * @param int   $limit
 * @return array array(events, bool more, next cursor or '')
 */
function pg_designer_activity_merge($lists, $limit)
{
    $events = array();

    foreach ((array) $lists as $list) {
        foreach ((array) $list as $event) {
            if (is_array($event)) {
                $events[(string) $event['key']] = $event;
            }
        }
    }

    $events = array_values($events);

    usort($events, function ($a, $b) {
        if ((int) $a['at'] !== (int) $b['at']) {
            return ((int) $a['at'] < (int) $b['at']) ? 1 : -1;
        }

        $order = strcmp((string) $b['store'], (string) $a['store']);

        if ($order !== 0) {
            return $order;
        }

        return ((int) $a['id'] < (int) $b['id']) ? 1 : -1;
    });

    $limit = max(1, (int) $limit);
    $more  = count($events) > $limit;

    if ($more) {
        $events = array_slice($events, 0, $limit);
    }

    $next = ($more && $events) ? pg_designer_activity_cursor($events[count($events) - 1]) : '';

    return array($events, $more, $next);
}

/**
 * The sentence of an action, after the person's name: "published Home".
 * '' for an action the reader does not know.
 *
 * @param string $action
 * @param string $name   the record's name
 * @return string
 */
function pg_designer_activity_sentence($action, $name)
{
    $name = (string) $name;

    // Links may have no start page; the sentence then names none.
    if ($action === 'link_create') {
        return ($name === '') ? lang('made a share link')
            : lang(array('string' => 'made a share link to {var:1}', 'vars' => array($name)));
    }

    if ($action === 'link_revoke') {
        return ($name === '') ? lang('revoked a share link')
            : lang(array('string' => 'revoked the share link to {var:1}', 'vars' => array($name)));
    }

    switch ($action) {
        case 'publish':
            return lang(array('string' => 'published {var:1}', 'vars' => array($name)));
        case 'restore':
            return lang(array('string' => 'restored an earlier version of {var:1}', 'vars' => array($name)));
        case 'import':
            return lang(array('string' => 'imported {var:1}', 'vars' => array($name)));
        case 'ai':
            return lang(array('string' => 'changed {var:1} with the assistant', 'vars' => array($name)));
        case 'bundle':
            return lang(array('string' => 'published {var:1} with a release bundle', 'vars' => array($name)));
        case 'applied':
            return lang(array('string' => 'applied a content change to {var:1}', 'vars' => array($name)));
        case 'page_recycle':
            return lang(array('string' => 'moved {var:1} to the Recycle Bin', 'vars' => array($name)));
        case 'page_restore':
            return lang(array('string' => 'brought {var:1} back from the Recycle Bin', 'vars' => array($name)));
        case 'page_delete':
            return lang(array('string' => 'deleted {var:1} for good', 'vars' => array($name)));
        case 'approved':
            return lang(array('string' => 'approved {var:1}', 'vars' => array($name)));
        case 'changes_requested':
            return lang(array('string' => 'asked for changes to {var:1}', 'vars' => array($name)));
        case 'comment':
            return lang(array('string' => 'commented on {var:1}', 'vars' => array($name)));
        case 'file_create':
            return lang(array('string' => 'made the design file {var:1}', 'vars' => array($name)));
        case 'file_update':
            return lang(array('string' => 'changed the design file {var:1}', 'vars' => array($name)));
        case 'file_delete':
            return lang(array('string' => 'deleted the design file {var:1}', 'vars' => array($name)));
        case 'page_unpublish':
            return lang(array('string' => 'took {var:1} off the site', 'vars' => array($name)));
        case 'page_publish':
            return lang(array('string' => 'put {var:1} on the site', 'vars' => array($name)));
        case 'style_save':
            return lang(array('string' => 'saved the design {var:1}', 'vars' => array($name)));
        case 'style_rename':
            return lang(array('string' => 'renamed the design to {var:1}', 'vars' => array($name)));
        case 'style_create':
            return lang(array('string' => 'made the design {var:1}', 'vars' => array($name)));
        case 'style_copy':
            return lang(array('string' => 'duplicated the design as {var:1}', 'vars' => array($name)));
        case 'style_recycle':
            return lang(array('string' => 'moved the design {var:1} to the Recycle Bin', 'vars' => array($name)));
        case 'style_delete':
            return lang(array('string' => 'deleted the design {var:1}', 'vars' => array($name)));
        case 'component_create':
            return lang(array('string' => 'made the shared component {var:1}', 'vars' => array($name)));
        case 'component_save':
            return lang(array('string' => 'saved the shared component {var:1}', 'vars' => array($name)));
        case 'component_rename':
            return lang(array('string' => 'renamed a shared component to {var:1}', 'vars' => array($name)));
        case 'component_settings':
            return lang(array('string' => 'changed the settings of the shared component {var:1}', 'vars' => array($name)));
        case 'component_delete':
            return lang(array('string' => 'deleted the shared component {var:1}', 'vars' => array($name)));
    }

    return '';
}

/**
 * The person of a stored user id: their user name, "A removed user" when
 * the id finds no user (erased or deleted), '' for no person (id 0).
 *
 * @param int         $user_id
 * @param string|null $username the joined user row's name
 * @return string
 */
function pg_designer_activity_who($user_id, $username)
{
    if ((int) $user_id <= 0) {
        return '';
    }

    return ((string) $username === '') ? lang('A removed user') : (string) $username;
}

/**
 * An event with the fields every event has.
 *
 * @param string $store
 * @param int    $id
 * @param array  $fields
 * @return array
 */
function _pg_designer_activity_event($store, $id, array $fields)
{
    $event = array_merge(array(
        'store'       => $store,
        'id'          => (int) $id,
        'key'         => $store . ':' . (int) $id,
        'at'          => 0,
        'kind'        => '',
        'action'      => '',
        'page_id'     => 0,
        'folder_id'   => 0,
        'revision_no' => 0,
        'user_id'     => 0,
        'who'         => '',
        'editor'      => '',
        'name'        => '',
    ), $fields);

    $event['sentence'] = pg_designer_activity_sentence($event['action'], $event['name']);

    return $event;
}

/**
 * A page's version. Pure.
 *
 * @param array $row revision_id, page_id, revision_no, source, created_at, user_id,
 *                   user_username, page_name, page_folder, editor (the applied
 *                   content change's editor, '' for none)
 * @return array
 */
function pg_designer_activity_from_revision($row)
{
    $source = (string) $row['source'];

    if (!in_array($source, explode(',', PG_PAGE_REVISION_SOURCES), true)) {
        $source = 'publish';
    }

    $editor = (string) ($row['editor'] ?? '');

    return _pg_designer_activity_event('revision', $row['revision_id'], array(
        'at'          => (int) $row['created_at'],
        'kind'        => 'version',
        'action'      => ($editor !== '') ? 'applied' : $source,
        'page_id'     => (int) $row['page_id'],
        'folder_id'   => (int) $row['page_folder'],
        'revision_no' => (int) $row['revision_no'],
        'user_id'     => (int) $row['user_id'],
        'who'         => pg_designer_activity_who($row['user_id'], $row['user_username']),
        'editor'      => $editor,
        'name'        => (string) $row['page_name'],
    ));
}

/**
 * One field of an audit line's stored side; null when it is not there.
 *
 * @param string|null $json
 * @param string      $field
 * @return string|null
 */
function _pg_designer_activity_line_field($json, $field)
{
    $values = json_decode((string) $json, true);

    return (is_array($values) && isset($values[$field]) && is_scalar($values[$field])) ? (string) $values[$field] : null;
}

/**
 * The names of the fields an audit line changed, from both stored sides.
 * A side too large to keep holds only those names (`fields`). Pure.
 *
 * @param string|null $before_json
 * @param string|null $after_json
 * @return string[] sorted
 */
function pg_designer_activity_line_fields($before_json, $after_json)
{
    $names = array();

    foreach (array($before_json, $after_json) as $json) {
        $side = json_decode((string) $json, true);

        if (!is_array($side)) {
            continue;
        }

        if (!empty($side['truncated']) && isset($side['fields']) && is_array($side['fields'])) {
            $side = array_flip(array_map('strval', $side['fields']));
        }

        foreach (array_keys($side) as $name) {
            $names[(string) $name] = true;
        }
    }

    $names = array_keys($names);
    sort($names);

    return $names;
}

/**
 * The action of a page's `update` line that took it off the site or put it
 * on (into the drafts folder or out of it); '' for any other edit. Pure.
 *
 * @param array $row          before_json, after_json
 * @param int   $draft_folder the drafts folder's id, 0 when there is none
 * @return string page_unpublish | page_publish | ''
 */
function _pg_designer_activity_page_move($row, $draft_folder)
{
    $draft_folder = (int) $draft_folder;
    $was = _pg_designer_activity_line_field($row['before_json'], 'page_folder');
    $now = _pg_designer_activity_line_field($row['after_json'], 'page_folder');

    if ($draft_folder <= 0 || $was === null || $now === null) {
        return '';
    }

    if ((int) $now === $draft_folder && (int) $was !== $draft_folder) {
        return 'page_unpublish';
    }

    if ((int) $was === $draft_folder && (int) $now !== $draft_folder) {
        return 'page_publish';
    }

    return '';
}

/**
 * The action of a design's line, in the list of `$style_id`: its own
 * lines, and the line of a copy made from it. '' for a line of another
 * design. Pure.
 *
 * @param array $row      action, entity_id, before_json, after_json
 * @param int   $style_id the design whose list is read
 * @return string
 */
function _pg_designer_activity_style_action($row, $style_id)
{
    $action = (string) $row['action'];

    if ((int) $row['entity_id'] !== (int) $style_id) {
        $from = _pg_designer_activity_line_field($row['after_json'], 'copied_from');

        return ($action === 'create' && $from !== null && (int) $from === (int) $style_id) ? 'style_copy' : '';
    }

    if ($action === 'update') {
        $fields = pg_designer_activity_line_fields($row['before_json'], $row['after_json']);

        return ($fields === array('style_name')) ? 'style_rename' : 'style_save';
    }

    return in_array($action, array('create', 'recycle', 'delete'), true) ? 'style_' . $action : '';
}

/**
 * The action of a shared component's line. Pure.
 *
 * @param array $row action, before_json, after_json
 * @return string
 */
function _pg_designer_activity_component_action($row)
{
    $action = (string) $row['action'];

    if ($action !== 'update') {
        return in_array($action, array('create', 'delete'), true) ? 'component_' . $action : '';
    }

    $fields = pg_designer_activity_line_fields($row['before_json'], $row['after_json']);

    if (in_array('tree_json', $fields, true)) {
        return 'component_save';
    }

    return ($fields === array('name')) ? 'component_rename' : 'component_settings';
}

/**
 * An audit trail line of a page, a design file, the design or a shared
 * component; null for a line the list does not show. Pure.
 *
 * The page's folder is the one the page was in: before the bin for a
 * recycle or a delete (read from the line), the page's own for a restore
 * or a move on or off the site. A line the scheduled job wrote names no
 * person: the list says the schedule did it.
 *
 * @param array $row     id, created_at, user_id, user_username, source, action, entity_type,
 *                       entity_id, label, before_json, after_json, page_folder (the live page's)
 * @param array $context style_id (the design whose list is read), draft_folder (the
 *                       drafts folder's id, 0 for none)
 * @return array|null
 */
function pg_designer_activity_from_audit($row, $context = array())
{
    $type   = (string) $row['entity_type'];
    $action = (string) $row['action'];
    $fields = array(
        'at'      => (int) $row['created_at'],
        'user_id' => (int) $row['user_id'],
        'who'     => ((string) ($row['source'] ?? '') === 'job') ? lang('The schedule')
                         : pg_designer_activity_who($row['user_id'], $row['user_username']),
        'name'    => (string) $row['label'],
    );

    if ($type === 'page' && $action === 'update') {
        $move = _pg_designer_activity_page_move($row, (int) ($context['draft_folder'] ?? 0));

        return ($move === '') ? null : _pg_designer_activity_event('audit', $row['id'], $fields + array(
            'kind'      => 'page',
            'action'    => $move,
            'page_id'   => (int) $row['entity_id'],
            'folder_id' => (int) $row['page_folder'],
        ));
    }

    if ($type === 'style') {
        $style_action = _pg_designer_activity_style_action($row, (int) ($context['style_id'] ?? 0));

        return ($style_action === '') ? null : _pg_designer_activity_event('audit', $row['id'], $fields + array(
            'kind'   => 'design',
            'action' => $style_action,
        ));
    }

    if ($type === 'shared_component') {
        $component_action = _pg_designer_activity_component_action($row);

        return ($component_action === '') ? null : _pg_designer_activity_event('audit', $row['id'], $fields + array(
            'kind'   => 'component',
            'action' => $component_action,
        ));
    }

    if ($type === 'page' && in_array($action, array('recycle', 'restore', 'delete'), true)) {
        $folder = ($action === 'restore')
            ? (int) $row['page_folder']
            : (int) _pg_designer_activity_line_field($row['before_json'], 'page_folder');

        return _pg_designer_activity_event('audit', $row['id'], $fields + array(
            'kind'      => 'page',
            'action'    => 'page_' . $action,
            'page_id'   => (int) $row['entity_id'],
            'folder_id' => $folder,
        ));
    }

    if ($type === 'file' && in_array($action, array('create', 'update', 'delete'), true)) {
        return _pg_designer_activity_event('audit', $row['id'], $fields + array(
            'kind'   => 'file',
            'action' => 'file_' . $action,
        ));
    }

    return null;
}

/**
 * A share link made, or revoked (`$revoked`). Pure.
 *
 * @param array $row     id, created_at, created_by, creator_username, revoked_at, revoked_by,
 *                       revoker_username, start_page_name
 * @param bool  $revoked
 * @return array
 */
function pg_designer_activity_from_link($row, $revoked)
{
    $by = $revoked ? 'revoked_by' : 'created_by';
    $username = $revoked ? 'revoker_username' : 'creator_username';

    return _pg_designer_activity_event($revoked ? 'revoke' : 'link', $row['id'], array(
        'at'      => (int) ($revoked ? $row['revoked_at'] : $row['created_at']),
        'kind'    => 'share',
        'action'  => $revoked ? 'link_revoke' : 'link_create',
        'user_id' => (int) $row[$by],
        'who'     => pg_designer_activity_who($row[$by], $row[$username]),
        'name'    => (string) $row['start_page_name'],
    ));
}

/**
 * The name a guest typed, or "The customer" when there is none or it was
 * erased ({name}).
 *
 * @param string $typed
 * @return string
 */
function _pg_designer_activity_guest($typed)
{
    $typed = trim((string) $typed);

    return ($typed === '' || $typed === '{name}') ? lang('The customer') : $typed;
}

/**
 * The customer's answer on a page; null for a verdict the list does not
 * know. Pure.
 *
 * @param array $row id, page_id, verdict, author_name, created_at, page_name, page_folder
 * @return array|null
 */
function pg_designer_activity_from_verdict($row)
{
    $verdict = (string) $row['verdict'];

    if (!in_array($verdict, array('approved', 'changes_requested'), true)) {
        return null;
    }

    return _pg_designer_activity_event('verdict', $row['id'], array(
        'at'        => (int) $row['created_at'],
        'kind'      => 'share',
        'action'    => $verdict,
        'page_id'   => (int) $row['page_id'],
        'folder_id' => (int) $row['page_folder'],
        'who'       => _pg_designer_activity_guest($row['author_name']),
        'name'      => (string) $row['page_name'],
    ));
}

/**
 * The first comment of a prototype thread: a staff member named by their
 * user row, a guest by the name they typed. Pure.
 *
 * @param array $row id, page_id, user_id, user_username, author_name, created_at, page_name, page_folder
 * @return array
 */
function pg_designer_activity_from_comment($row)
{
    $user_id = (int) $row['user_id'];

    return _pg_designer_activity_event('comment', $row['id'], array(
        'at'        => (int) $row['created_at'],
        'kind'      => 'share',
        'action'    => 'comment',
        'page_id'   => (int) $row['page_id'],
        'folder_id' => (int) $row['page_folder'],
        'user_id'   => $user_id,
        'who'       => ($user_id > 0) ? pg_designer_activity_who($user_id, $row['user_username']) : _pg_designer_activity_guest($row['author_name']),
        'name'      => (string) $row['page_name'],
    ));
}

/**
 * The events this person may see. Pure.
 *
 * @param array    $events
 * @param bool     $full    a designer role (pg_designer_is_full())
 * @param callable $may_page function (int page_id, int folder_id): bool, a content editor's own page
 * @return array
 */
function pg_designer_activity_visible($events, $full, $may_page)
{
    if ($full) {
        return array_values((array) $events);
    }

    $out = array();

    foreach ((array) $events as $event) {
        $page_id = (int) $event['page_id'];

        if ($page_id > 0 && $may_page($page_id, (int) $event['folder_id'])) {
            $out[] = $event;
        }
    }

    return $out;
}

/**
 * An event as the tab receives it: the store's internals left out.
 *
 * @param array $event
 * @return array
 */
function pg_designer_activity_public($event)
{
    unset($event['store'], $event['id'], $event['folder_id']);

    return $event;
}

/**
 * The kinds the Kind chooser offers, in its order.
 *
 * @return string[]
 */
function pg_designer_activity_kinds()
{
    return array('version', 'page', 'share', 'design', 'component', 'file');
}

/**
 * The three choosers of the Activity tab as the reader takes them. Pure.
 *
 * Person: '' everyone, 'me' the person asking, a user id, 'customers' (the
 * answers and a guest's comments on share links) or 'schedule' (the
 * scheduled job's lines). Page: a page id, '' every page. Kind: one of
 * pg_designer_activity_kinds(), '' everything. A value it does not know
 * widens that chooser.
 *
 * @param string $person
 * @param string $page
 * @param string $kind
 * @param array  $user   the panel user (id)
 * @return array person ('', 'user', 'customers', 'schedule'), user_id, page_id, kind
 */
function pg_designer_activity_filter($person, $page, $kind, $user)
{
    $person  = (string) $person;
    $page    = (string) $page;
    $kind    = (string) $kind;
    $filter  = array('person' => '', 'user_id' => 0, 'page_id' => 0, 'kind' => '');

    if ($person === 'me' && isset($user['id']) && (int) $user['id'] > 0) {
        $filter['person'] = 'user';
        $filter['user_id'] = (int) $user['id'];
    } elseif (ctype_digit($person) && (int) $person > 0) {
        $filter['person'] = 'user';
        $filter['user_id'] = (int) $person;
    } elseif ($person === 'customers' || $person === 'schedule') {
        $filter['person'] = $person;
    }

    if (ctype_digit($page) && (int) $page > 0) {
        $filter['page_id'] = (int) $page;
    }

    if (in_array($kind, pg_designer_activity_kinds(), true)) {
        $filter['kind'] = $kind;
    }

    return $filter;
}

/**
 * Whether any of the three choosers narrows the list. Pure.
 *
 * @param array $filter pg_designer_activity_filter()
 * @return bool
 */
function pg_designer_activity_filter_narrowed($filter)
{
    return $filter['person'] !== '' || $filter['page_id'] > 0 || $filter['kind'] !== '';
}

/**
 * The SQL condition the three choosers put on one part of a store's read;
 * false when the part can hold no event they keep. Pure; the filter is
 * normalised, so every value in it is an integer or a known word.
 *
 * Parts: revision (r), audit_page, audit_file, audit_style,
 * audit_component (a), link and revoke (l), verdict (v), comment (c). A
 * part with no page column is the design's, never one page's; a trail line
 * the scheduled job wrote (source 'job') is the schedule's, never a
 * person's.
 *
 * @param string $part
 * @param array  $filter pg_designer_activity_filter()
 * @return string|false
 */
function pg_designer_activity_filter_sql($part, $filter)
{
    // kind, page column, user column, customers condition, job lines
    $parts = array(
        'revision'        => array('version', 'r.page_id', 'r.user_id', null, false),
        'audit_page'      => array('page', 'a.entity_id', 'a.user_id', null, true),
        'audit_file'      => array('file', null, 'a.user_id', null, true),
        'audit_style'     => array('design', null, 'a.user_id', null, true),
        'audit_component' => array('component', null, 'a.user_id', null, true),
        'link'            => array('share', null, 'l.created_by', null, false),
        'revoke'          => array('share', null, 'l.revoked_by', null, false),
        'verdict'         => array('share', 'v.page_id', null, '1', false),
        'comment'         => array('share', 'c.page_id', 'c.user_id', '(c.user_id = 0)', false),
    );

    if (!isset($parts[$part])) {
        return false;
    }

    list($kind, $page_col, $user_col, $guests, $job) = $parts[$part];
    $where = array();

    if ($filter['kind'] !== '' && $filter['kind'] !== $kind) {
        return false;
    }

    if ($filter['page_id'] > 0) {
        if ($page_col === null) {
            return false;
        }

        $where[] = '(' . $page_col . ' = ' . (int) $filter['page_id'] . ')';
    }

    if ($filter['person'] === 'user') {
        if ($user_col === null) {
            return false;
        }

        $where[] = '(' . $user_col . ' = ' . (int) $filter['user_id'] . ($job ? " AND a.source <> 'job'" : '') . ')';
    } elseif ($filter['person'] === 'customers') {
        if ($guests === null) {
            return false;
        }

        if ($guests !== '1') {
            $where[] = $guests;
        }
    } elseif ($filter['person'] === 'schedule') {
        if (!$job) {
            return false;
        }

        $where[] = "(a.source = 'job')";
    }

    return $where ? implode(' AND ', $where) : '1';
}

/**
 * A comma list of integer ids for an IN (); '0' for none.
 *
 * @param array $ids
 * @return string
 */
function _pg_designer_activity_in(array $ids)
{
    $ids = array_values(array_unique(array_map('intval', $ids)));

    return $ids ? implode(',', $ids) : '0';
}

/**
 * One page of a design's events for this person, newest first.
 *
 * @param int    $style_id the design
 * @param array  $user     the panel user (id, role)
 * @param string $before   the cursor of the page before ('' for the first)
 * @param array  $filter   the three choosers (pg_designer_activity_filter()),
 *                         put on each store's read so a narrowed list still
 *                         comes fifty at a time; null for everything
 * @return array events (pg_designer_activity_public()), more, next
 */
function pg_designer_activity_read($style_id, $user, $before = '', $filter = null)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/designer_access.php');

    $style_id = (int) $style_id;
    $cursor   = pg_designer_activity_cursor_parse((string) $before);
    $full     = pg_designer_is_full($user);
    $over     = PG_DESIGNER_ACTIVITY_PAGE + 1;
    $empty    = array('events' => array(), 'more' => false, 'next' => '');
    $filter   = is_array($filter) ? $filter : pg_designer_activity_filter('', '', '', $user);
    $where    = function ($part) use ($filter) {
        return pg_designer_activity_filter_sql($part, $filter);
    };

    if ($style_id <= 0) {
        return $empty;
    }

    // The design's pages, binned ones too (a binned page keeps its row in
    // its bin folder); a content editor reads only those they may edit.
    $pages = (array) db_items("SELECT page_id, page_name, page_folder FROM page WHERE page_style = '$style_id'", 'page_id');
    $own   = array();

    foreach ($pages as $page_id => $page) {
        if ($full || pg_designer_page_access($page, $user) === 'edit') {
            $own[(int) $page_id] = true;
        }
    }

    $may_page = function ($page_id, $folder_id) use ($own, $user) {
        if (isset($own[$page_id])) {
            return true;
        }

        // A page binned or deleted: the folder it was in.
        return $folder_id > 0 && pg_designer_page_access(array('page_id' => $page_id, 'page_folder' => $folder_id), $user) === 'edit';
    };

    $page_of = function ($page_id, $field) use ($pages) {
        return isset($pages[$page_id][$field]) ? $pages[$page_id][$field] : '';
    };

    $own_in = _pg_designer_activity_in(array_keys($own));
    $all_in = _pg_designer_activity_in(array_keys($pages));
    $lists  = array();

    if ($own && $where('revision') !== false && pg_page_revisions_ready()) {
        $lists[] = _pg_designer_activity_revisions($style_id, $own_in, $cursor, $over, $page_of, $where('revision'));
    }

    if (pg_audit_ready()) {
        $lists[] = _pg_designer_activity_audit($style_id, $all_in, $full, $cursor, $over, $page_of, $where);
    }

    if ($full) {
        require_once(PG_FUNCTIONS_DIR . '/includes/fn/prototype_links.php');

        if (pg_proto_links_ready()) {
            $lists[] = _pg_designer_activity_links($style_id, $cursor, $over, $page_of, $where);
        }
    }

    if ($own && $where('verdict') !== false && pg_proto_approval_ready()) {
        $rows = db_items(
            "SELECT v.id, v.page_id, v.verdict, v.author_name, v.created_at
             FROM prototype_verdicts AS v
             WHERE v.style_id = '$style_id' AND v.page_id IN ($own_in)
               AND " . pg_designer_activity_after_sql('verdict', $cursor, 'v.created_at', 'v.id') . "
               AND " . $where('verdict') . "
             ORDER BY v.created_at DESC, v.id DESC
             LIMIT $over");
        $list = array();

        foreach ((array) $rows as $row) {
            $row['page_name'] = $page_of((int) $row['page_id'], 'page_name');
            $row['page_folder'] = (int) $page_of((int) $row['page_id'], 'page_folder');
            $list[] = pg_designer_activity_from_verdict($row);
        }

        $lists[] = array_filter($list);
    }

    if ($own && $where('comment') !== false && pg_proto_comments_ready()) {
        $rows = db_items(
            "SELECT c.id, c.page_id, c.user_id, c.author_name, c.created_at, user.user_username
             FROM prototype_comments AS c
             LEFT JOIN user ON user.user_id = c.user_id
             WHERE c.style_id = '$style_id' AND c.parent_id = 0 AND c.page_id IN ($own_in)
               AND " . pg_designer_activity_after_sql('comment', $cursor, 'c.created_at', 'c.id') . "
               AND " . $where('comment') . "
             ORDER BY c.created_at DESC, c.id DESC
             LIMIT $over");
        $list = array();

        foreach ((array) $rows as $row) {
            $row['page_name'] = $page_of((int) $row['page_id'], 'page_name');
            $row['page_folder'] = (int) $page_of((int) $row['page_id'], 'page_folder');
            $list[] = pg_designer_activity_from_comment($row);
        }

        $lists[] = $list;
    }

    list($events, $more, $next) = pg_designer_activity_merge($lists, PG_DESIGNER_ACTIVITY_PAGE);

    return array(
        'events' => array_map('pg_designer_activity_public', pg_designer_activity_visible($events, $full, $may_page)),
        'more'   => $more,
        'next'   => $next,
    );
}

/**
 * The versions of the design's pages, with the editor of an applied
 * content change.
 *
 * @param string $chosen the choosers' condition (pg_designer_activity_filter_sql())
 * @return array events
 */
function _pg_designer_activity_revisions($style_id, $own_in, $cursor, $over, $page_of, $chosen = '1')
{
    $rows = db_items(
        "SELECT r.revision_id, r.page_id, r.revision_no, r.source, r.created_at, r.user_id, r.tree_hash,
                user.user_username
         FROM page_revisions AS r
         LEFT JOIN user ON user.user_id = r.user_id
         WHERE r.page_id IN ($own_in) AND r.style_id = '$style_id'
           AND " . pg_designer_activity_after_sql('revision', $cursor, 'r.created_at', 'r.revision_id') . "
           AND $chosen
         ORDER BY r.created_at DESC, r.revision_id DESC
         LIMIT $over");

    if (!$rows) {
        return array();
    }

    // The applied content changes of these pages only, by page.
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/content_proposals.php');
    $changes = array();

    if (pg_content_proposals_ready()) {
        $pages_in = _pg_designer_activity_in(array_column($rows, 'page_id'));

        foreach ((array) db_items(
            "SELECT p.page_id, p.after_hash, p.steps, p.decided_at, user.user_username
             FROM design_proposals AS p
             LEFT JOIN user ON user.user_id = p.requested_by
             WHERE p.page_id IN ($pages_in) AND p.agent = 'editor' AND p.applied_to = 'page' AND p.after_hash <> ''") as $change) {
            $changes[(int) $change['page_id']][] = $change;
        }
    }

    $events = array();

    foreach ($rows as $row) {
        $page_id = (int) $row['page_id'];
        $row['editor'] = isset($changes[$page_id]) ? pg_page_revisions_editor($row, $changes[$page_id]) : '';
        $row['page_name'] = $page_of($page_id, 'page_name');
        $row['page_folder'] = (int) $page_of($page_id, 'page_folder');
        $events[] = pg_designer_activity_from_revision($row);
    }

    return $events;
}

/**
 * The audit trail's lines of the design: its pages binned, brought back,
 * deleted for good, taken off the site or put on it, and, for the designer
 * roles, the design's own lines, the lines of copies made from it, the
 * design files and the shared components.
 *
 * A deleted page has no row any more; its line is found by the design kept
 * in the line's stored row. Design files (`files.design`) and shared
 * components are the site's, not one design's, so their lines are in every
 * design's list. A page's other edits are not read: only an `update` line
 * that names the drafts folder on one side is a move on or off the site.
 *
 * @param callable|null $where function (string part): string|false, the choosers' condition
 * @return array events
 */
function _pg_designer_activity_audit($style_id, $all_in, $full, $cursor, $over, $page_of, $where = null)
{
    $after  = pg_designer_activity_after_sql('audit', $cursor, 'a.created_at', 'a.id');
    $select = "SELECT a.id, a.created_at, a.user_id, a.source, a.action, a.entity_type, a.entity_id, a.label,
                      %s, user.user_username
               FROM audit_log AS a
               LEFT JOIN user ON user.user_id = a.user_id
               WHERE %s AND $after
               ORDER BY a.created_at DESC, a.id DESC
               LIMIT $over";
    $json   = 'a.before_json, NULL AS after_json';
    $none   = 'NULL AS before_json, NULL AS after_json';
    $style  = '%"page_style":"' . $style_id . '"%';

    $sides  = 'a.before_json, a.after_json';
    $draft  = pg_page_draft_ready() ? (int) pg_page_draft_folder_id(false) : 0;
    $folder = '%"page_folder":"' . $draft . '"%';

    // Each query with the part of the trail it reads, for the choosers.
    $queries = array(
        array('audit_page', $json, "a.entity_type = 'page' AND a.entity_id IN ($all_in) AND a.action IN ('recycle', 'restore')"),
        array('audit_page', $json, "a.entity_type = 'page' AND a.action = 'delete' AND a.before_json LIKE '" . e($style) . "'"),
    );

    if ($draft > 0) {
        $queries[] = array('audit_page', $sides, "a.entity_type = 'page' AND a.entity_id IN ($all_in) AND a.action = 'update'
                                               AND (a.after_json LIKE '" . e($folder) . "' OR a.before_json LIKE '" . e($folder) . "')");
    }

    if ($full) {
        $queries[] = array('audit_file', $none, "a.entity_type = 'file' AND a.entity_id IN (SELECT id FROM files WHERE design = 1)");
        $queries[] = array('audit_file', $none, "a.entity_type = 'file' AND a.action = 'delete' AND a.before_json LIKE '%\"design\":\"1\"%'");
        $queries[] = array('audit_style', $sides, "a.entity_type = 'style' AND a.entity_id = '" . (int) $style_id . "'");
        $queries[] = array('audit_style', $sides, "a.entity_type = 'style' AND a.action = 'create' AND a.after_json LIKE '"
                                              . e('%"copied_from":"' . (int) $style_id . '"%') . "'");
        $queries[] = array('audit_component', $sides, "a.entity_type = 'shared_component'");
    }

    $context = array('style_id' => (int) $style_id, 'draft_folder' => $draft);

    $events = array();

    foreach ($queries as $query) {
        list($part, $columns, $condition) = $query;
        $chosen = $where ? $where($part) : '1';

        if ($chosen === false) {
            continue;
        }

        foreach ((array) db_items(sprintf($select, $columns, '(' . $condition . ') AND ' . $chosen)) as $row) {
            $page_id = (int) $row['entity_id'];
            $row['page_folder'] = ($row['entity_type'] === 'page') ? (int) $page_of($page_id, 'page_folder') : 0;

            $event = pg_designer_activity_from_audit($row, $context);

            if ($event) {
                $events[] = $event;
            }
        }
    }

    return $events;
}

/**
 * The design's share links made and revoked.
 *
 * @param callable|null $where function (string part): string|false, the choosers' condition
 * @return array events
 */
function _pg_designer_activity_links($style_id, $cursor, $over, $page_of, $where = null)
{
    $select = "SELECT l.id, l.start_page_id, l.created_at, l.created_by, l.revoked_at, l.revoked_by,
                      cu.user_username AS creator_username, ru.user_username AS revoker_username
               FROM prototype_links AS l
               LEFT JOIN user AS cu ON cu.user_id = l.created_by
               LEFT JOIN user AS ru ON ru.user_id = l.revoked_by
               WHERE l.style_id = '$style_id' AND %s
               ORDER BY %s DESC, l.id DESC
               LIMIT $over";

    $events = array();

    foreach (array(false, true) as $revoked) {
        $chosen = $where ? $where($revoked ? 'revoke' : 'link') : '1';

        if ($chosen === false) {
            continue;
        }

        $at = $revoked ? 'l.revoked_at' : 'l.created_at';
        $condition = ($revoked ? 'l.revoked_at > 0 AND ' : '')
            . pg_designer_activity_after_sql($revoked ? 'revoke' : 'link', $cursor, $at, 'l.id')
            . ' AND ' . $chosen;

        foreach ((array) db_items(sprintf($select, $condition, $at)) as $row) {
            $row['start_page_name'] = $page_of((int) $row['start_page_id'], 'page_name');
            $events[] = pg_designer_activity_from_link($row, $revoked);
        }
    }

    return $events;
}
