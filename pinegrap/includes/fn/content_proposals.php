<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Content changes that wait for approval.
 *
 * With the switch on (Site Settings, Features), a content-level editor's
 * save of a live page of a visual design writes no page. The changes the
 * content gate allows - pg_designer_merge_restricted_tree() in
 * includes/designer_access.php - are kept as one waiting change per page and
 * editor in design_proposals, with agent 'editor', for a designer to apply
 * or dismiss. Saving again replaces the waiting change; saving the page as
 * it is withdraws it.
 *
 * A waiting change is kept as operations, each with the value it replaces
 * ("was"), so a designer's later change of the same thing makes it stale
 * instead of being overwritten:
 *
 *   {op: text, node, text, was}      a node's words (props.text)
 *   {op: src, node, src, was}        a picture's address
 *   {op: note, node, note, was}      a node's note (props._notes)
 *   {op: area, node, tree, was}      a marked editable area, whole; was is
 *                                    the fingerprint of the stored area
 *   {op: fields, fields, was}        the page settings a content level writes
 *   {op: tree, tree, base_hash}      the whole tree of a page whose nodes
 *                                    carry no ids, stale once the page changed
 *
 * The operations are not trusted when applied: the gate stays the one
 * authority and runs again over their result. The assistant's readers of
 * design_proposals (includes/designer_ai.php, includes/api/resources/design.php,
 * includes/workspace/threads.php) never return a row of agent 'editor'; only
 * this module reads them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The design_proposals.agent value of a change a content editor made.
define('PG_CONTENT_PROPOSALS_AGENT', 'editor');

// The config_text row the switch and its channel are kept in (JSON).
define('PG_CONTENT_PROPOSALS_SETTING', 'content_approval');

// The channels the card offers to ask in: open public and private ones
// (written out in each query, where tests/workspace_mail_room_hidden_test.php
// reads the kinds).
define('PG_CONTENT_PROPOSALS_CHANNELS', "kind IN ('public', 'private') AND archived_at = 0");

/**
 * May this person turn the hold on or off? Only a designer or an
 * administrator: the Features category also opens for a Manager, whose own
 * saves the hold applies to.
 *
 * @param array|null $user
 * @return bool
 */
function pg_content_proposals_may_set($user)
{
    return is_array($user) && isset($user['role']) && is_numeric($user['role']) && ((int) $user['role'] <= 1);
}

/**
 * Is this a channel the card offers (or 0, none)?
 *
 * @param int $channel_id
 * @return bool
 */
function pg_content_proposals_channel_offered($channel_id)
{
    $channel_id = (int) $channel_id;

    if ($channel_id === 0) {
        return true;
    }

    return ($channel_id > 0) && pg_schema_has('ws_channels')
        && ((int) db_value("SELECT COUNT(*) FROM ws_channels WHERE id = '" . $channel_id . "' AND kind IN ('public', 'private') AND archived_at = 0") > 0);
}

/**
 * Did the write a save's plan needs succeed? db_q() answers false on a
 * failure without stopping the request (a lock timeout, an area larger than
 * max_allowed_packet).
 *
 * @param string $action insert | replace | withdraw | none
 * @param mixed  $result db_q()'s answer, null when nothing was written
 * @return bool
 */
function pg_content_proposals_written($action, $result)
{
    return !in_array($action, array('insert', 'replace', 'withdraw'), true) || ($result !== false);
}

/**
 * The switch and its channel as stored, read without the database.
 *
 * @param mixed $raw the config_text value (JSON)
 * @return array on (bool), channel_id (int)
 */
function pg_content_proposals_settings_read($raw)
{
    $data = is_string($raw) ? json_decode($raw, true) : null;
    $data = is_array($data) ? $data : array();

    return array(
        'on'         => !empty($data['on']),
        'channel_id' => max(0, (int) (isset($data['channel_id']) ? $data['channel_id'] : 0)),
    );
}

/**
 * The value stored for the switch and its channel.
 *
 * @param bool $on
 * @param int  $channel_id
 * @return string JSON
 */
function pg_content_proposals_settings_encode($on, $channel_id)
{
    return (string) json_encode(array('on' => $on ? 1 : 0, 'channel_id' => max(0, (int) $channel_id)));
}

/**
 * Can the switch be used here: the settings text table and the proposal
 * table with its agent column exist.
 *
 * @return bool
 */
function pg_content_proposals_ready()
{
    return function_exists('pg_schema_has') && pg_schema_has('config_text') && pg_schema_has('design_proposals', 'agent');
}

/**
 * The switch and its channel, read once a request.
 *
 * @param bool $fresh read again (after the settings were saved)
 * @return array on (bool), channel_id (int)
 */
function pg_content_proposals_settings($fresh = false)
{
    static $settings = null;

    if (($settings === null) || $fresh) {
        $settings = pg_content_proposals_settings_read(
            (function_exists('pg_schema_has') && pg_schema_has('config_text')) ? pg_config_text(PG_CONTENT_PROPOSALS_SETTING) : '');
    }

    return $settings;
}

/**
 * Is the switch on?
 *
 * @return bool
 */
function pg_content_proposals_on()
{
    $settings = pg_content_proposals_settings();

    return $settings['on'] && pg_content_proposals_ready();
}

/**
 * Marks the request as the Page Design Studio's own save (set by
 * pg_designer_screen_post()). Only that save is held: the external API, the
 * assistant and every other caller of pg_designer_save_page() are not.
 *
 * @param bool|null $set
 * @return bool
 */
function pg_content_proposals_editor_save($set = null)
{
    static $editor_save = false;

    if ($set !== null) {
        $editor_save = (bool) $set;
    }

    return $editor_save;
}

/**
 * Is this save one that waits? A content-level editor's save, from the
 * Page Design Studio, of a page already on the site (not a draft, not a new
 * page) of a visual design, with the switch on.
 *
 * @param array $ctx on, editor_save, restricted, page_id, visual, has_tree,
 *                   draft_was, draft_want
 * @return bool
 */
function pg_content_proposals_applies($ctx)
{
    $flag = function ($key) use ($ctx) {
        return !empty($ctx[$key]);
    };

    return $flag('on') && $flag('editor_save') && $flag('restricted')
        && ((int) (isset($ctx['page_id']) ? $ctx['page_id'] : 0) > 0)
        && $flag('visual') && $flag('has_tree')
        && !$flag('draft_was') && !$flag('draft_want');
}

/**
 * The page settings a content-level save writes, by the keys the editor
 * sends them with (pg_designer_load_pages()). Name, folder, home page, design,
 * draft state and times are not among them: the save keeps them as stored.
 *
 * @return string[]
 */
function pg_content_proposals_field_keys()
{
    return array(
        'page_title', 'page_meta_description', 'page_search', 'page_search_keywords', 'page_sitemap',
        'page_noindex', 'page_nofollow', 'page_noarchive', 'page_max_snippet', 'page_canonical_url',
        'page_custom_jsonld', 'page_meta_tags',
        'pg_comments', 'pg_comments_label', 'pg_comments_allow_new', 'pg_comments_rating',
        'pg_comments_auto_publish', 'pg_comments_show_date', 'pg_comments_login',
        'pg_comments_email_page', 'pg_comments_email_subject', 'pg_comments_notify_email',
        'pg_comments_attachments', 'pg_comments_select_name', 'pg_comments_message',
        'pg_comments_closed_message', 'pg_comments_watchers_managed',
    );
}

/**
 * A setting's value as it is compared: a switch sent as true and stored as
 * 1 is the same value.
 *
 * @param mixed $value
 * @return string
 */
function pg_content_proposals_field_value($value)
{
    if ($value === null) {
        return '';
    }

    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    if (is_array($value)) {
        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    return (string) $value;
}

/**
 * The page settings the save changes against the stored ones.
 *
 * @param array $stored the page as pg_designer_load_pages() gives it
 * @param array $sent   the page as the editor sent it
 * @return array fields => sent values, was => stored values (both empty when nothing changed)
 */
function pg_content_proposals_field_changes($stored, $sent)
{
    $out = array('fields' => array(), 'was' => array());
    $stored = is_array($stored) ? $stored : array();

    foreach (pg_content_proposals_field_keys() as $key) {
        if (!is_array($sent) || !array_key_exists($key, $sent)) {
            continue;
        }

        $was = array_key_exists($key, $stored) ? $stored[$key] : null;

        if (pg_content_proposals_field_value($sent[$key]) !== pg_content_proposals_field_value($was)) {
            $out['fields'][$key] = $sent[$key];
            $out['was'][$key] = $was;
        }
    }

    return $out;
}

/**
 * Does every node under the root carry an id? Only then can a change be
 * kept per node.
 *
 * @param array $tree
 * @return bool
 */
function pg_content_proposals_tree_has_ids($tree)
{
    foreach ((isset($tree['children']) && is_array($tree['children'])) ? $tree['children'] : array() as $child) {
        if (!is_array($child) || !isset($child['_id']) || ((string) $child['_id'] === '')) {
            return false;
        }

        if (!pg_content_proposals_tree_has_ids($child)) {
            return false;
        }
    }

    return true;
}

/**
 * A node's picture address where the gate moves one (_pg_dm_take_image_src()):
 * an image content node's props.src, a semantic img's src attribute.
 *
 * @param array $node
 * @return string|null null when the node has no picture address
 */
function pg_content_proposals_image_src($node)
{
    $type = isset($node['type']) ? (string) $node['type'] : '';

    if (($type === 'content') && ((isset($node['props']['contentType']) ? $node['props']['contentType'] : '') === 'image')) {
        return isset($node['props']['src']) ? (string) $node['props']['src'] : '';
    }

    if (($type === 'semantic') && (strtolower((string) (isset($node['props']['tag']) ? $node['props']['tag'] : '')) === 'img')) {
        foreach ((isset($node['props']['_attrs']) && is_array($node['props']['_attrs'])) ? $node['props']['_attrs'] : array() as $attr) {
            if (is_array($attr) && isset($attr['name']) && ($attr['name'] === 'src')) {
                return isset($attr['value']) ? (string) $attr['value'] : '';
            }
        }

        return '';
    }

    return null;
}

/**
 * The fingerprint of a tree or part of one, as design_proposals keeps it
 * (pg_design_ai_hash()).
 *
 * @param array $tree
 * @return string
 */
function pg_content_proposals_hash($tree)
{
    return sha1(pg_designer_tree_encode($tree));
}

/**
 * Walks the stored tree beside the gate's result, matching children by id
 * as the gate does, and adds an operation for each change.
 */
function _pg_cp_walk($stored, $merged, &$ops)
{
    if (!is_array($stored) || !is_array($merged)) {
        return;
    }

    // A shared component is never the page's to change.
    if ((isset($stored['type']) ? (string) $stored['type'] : '') === 'shared_ref') {
        return;
    }

    $id = isset($stored['_id']) ? (string) $stored['_id'] : '';

    // A marked area is taken whole by the gate, so it is kept whole.
    if (!empty($stored['props']['_editable'])) {
        $was = pg_content_proposals_hash($stored);

        if (($id !== '') && (pg_content_proposals_hash($merged) !== $was)) {
            $ops[] = array('op' => 'area', 'node' => $id, 'tree' => $merged, 'was' => $was);
        }

        return;
    }

    if ($id !== '') {
        $s_props = (isset($stored['props']) && is_array($stored['props'])) ? $stored['props'] : array();
        $m_props = (isset($merged['props']) && is_array($merged['props'])) ? $merged['props'] : array();

        if (array_key_exists('text', $s_props)) {
            $was = (string) $s_props['text'];
            $now = array_key_exists('text', $m_props) ? (string) $m_props['text'] : $was;

            if ($now !== $was) {
                $ops[] = array('op' => 'text', 'node' => $id, 'text' => $now, 'was' => $was);
            }
        }

        $was = pg_content_proposals_image_src($stored);
        $now = pg_content_proposals_image_src($merged);

        if (($was !== null) && ($now !== null) && ($now !== $was)) {
            $ops[] = array('op' => 'src', 'node' => $id, 'src' => $now, 'was' => $was);
        }

        $was = isset($s_props['_notes']) ? (string) $s_props['_notes'] : '';
        $now = isset($m_props['_notes']) ? (string) $m_props['_notes'] : '';

        if ($now !== $was) {
            $ops[] = array('op' => 'note', 'node' => $id, 'note' => $now, 'was' => $was);
        }
    }

    $mates = array();

    foreach ((isset($merged['children']) && is_array($merged['children'])) ? $merged['children'] : array() as $child) {
        if (is_array($child) && isset($child['_id']) && ((string) $child['_id'] !== '')) {
            $mates[(string) $child['_id']] = $child;
        }
    }

    foreach ((isset($stored['children']) && is_array($stored['children'])) ? $stored['children'] : array() as $child) {
        $cid = (is_array($child) && isset($child['_id'])) ? (string) $child['_id'] : '';

        if (($cid !== '') && isset($mates[$cid])) {
            _pg_cp_walk($child, $mates[$cid], $ops);
        }
    }
}

/**
 * The operations that turn the stored page into what the gate let the
 * editor save.
 *
 * @param array $stored        the stored tree
 * @param array $merged        pg_designer_merge_restricted_tree() of the save
 * @param array $stored_fields the page as pg_designer_load_pages() gives it
 * @param array $sent_fields   the page as the editor sent it
 * @return array operations, empty when the save changes nothing
 */
function pg_content_proposals_ops($stored, $merged, $stored_fields, $sent_fields)
{
    $ops = array();

    if (is_array($stored) && is_array($merged)) {
        if (!pg_content_proposals_tree_has_ids($stored)) {
            $base = pg_content_proposals_hash($stored);

            if (pg_content_proposals_hash($merged) !== $base) {
                $ops[] = array('op' => 'tree', 'tree' => $merged, 'base_hash' => $base);
            }
        } else {
            _pg_cp_walk($stored, $merged, $ops);
        }
    }

    $fields = pg_content_proposals_field_changes($stored_fields, $sent_fields);

    if (!empty($fields['fields'])) {
        $ops[] = array('op' => 'fields', 'fields' => $fields['fields'], 'was' => $fields['was']);
    }

    return $ops;
}

/**
 * What each operation does, in words, for the row's steps.
 *
 * @param array $ops
 * @return string[]
 */
function pg_content_proposals_lines($ops)
{
    $lines = array();

    foreach ((array) $ops as $op) {
        $name = isset($op['op']) ? (string) $op['op'] : '';

        if ($name === 'fields') {
            $lines[] = 'fields: ' . implode(', ', array_keys((array) $op['fields']));
        } elseif ($name === 'tree') {
            $lines[] = 'tree: the whole page';
        } else {
            $lines[] = $name . ' ' . (isset($op['node']) ? (string) $op['node'] : '');
        }
    }

    return $lines;
}

/**
 * What a save does with the editor's waiting change of the page.
 *
 * @param bool $waiting the editor has a waiting change of the page
 * @param bool $changes the save changes something
 * @return string insert | replace | withdraw | none
 */
function pg_content_proposals_plan($waiting, $changes)
{
    if ($changes) {
        return $waiting ? 'replace' : 'insert';
    }

    return $waiting ? 'withdraw' : 'none';
}

/**
 * The editor's waiting change of a page.
 *
 * @param int $page_id
 * @param int $user_id
 * @return array|null design_proposals row
 */
function pg_content_proposals_waiting($page_id, $user_id)
{
    $row = db_item((string) pg_db_bind(
        "SELECT * FROM design_proposals WHERE page_id = ? AND agent = ? AND requested_by = ? AND status = 'pending' ORDER BY id DESC LIMIT 1",
        array((int) $page_id, PG_CONTENT_PROPOSALS_AGENT, (int) $user_id), 'escape'));

    return is_array($row) ? $row : null;
}

/**
 * The page's settings as the editor sends them, read from the stored row.
 *
 * @param int $style_id
 * @param int $page_id
 * @return array
 */
function pg_content_proposals_stored_fields($style_id, $page_id)
{
    foreach (pg_designer_load_pages($style_id) as $row) {
        if ((int) $row['page_id'] === (int) $page_id) {
            return $row;
        }
    }

    return array();
}

/**
 * Keeps a content-level save of a live page as the editor's waiting change
 * instead of writing it (pg_designer_save_page(), after the gate).
 *
 * @param array $user
 * @param int   $page_id
 * @param int   $style_id
 * @param array $merged the gate's result
 * @param array $page   the page as the editor sent it
 * @return array ok (false when the write failed), action (insert | replace |
 *               withdraw | none), id, message ('' for none), error
 */
function pg_content_proposals_hold($user, $page_id, $style_id, $merged, $page)
{
    $page_id = (int) $page_id;
    $stored = json_decode(pg_page_tree_json($page_id), true);
    $ops = pg_content_proposals_ops($stored, $merged, pg_content_proposals_stored_fields($style_id, $page_id), $page);
    $waiting = pg_content_proposals_waiting($page_id, $user['id']);
    $action = pg_content_proposals_plan($waiting !== null, !empty($ops));
    $name = (string) db_value((string) pg_db_bind("SELECT page_name FROM page WHERE page_id = ? LIMIT 1", array($page_id), 'escape'));
    $now = time();
    $id = $waiting ? (int) $waiting['id'] : 0;
    $ops_json = (string) json_encode($ops, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $steps_json = (string) json_encode(array('lines' => pg_content_proposals_lines($ops)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $base = is_array($stored) ? pg_content_proposals_hash($stored) : '';

    $written = null;

    if ($action === 'insert') {
        $written = db_q("INSERT INTO design_proposals (agent, page_id, style_id, ops, steps, base_hash, status, requested_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?)",
            array(PG_CONTENT_PROPOSALS_AGENT, $page_id, (int) $style_id, $ops_json, $steps_json, $base, (int) $user['id'], $now));
        $id = ($written !== false) ? (int) mysqli_insert_id(db::$con) : 0;
    } elseif ($action === 'replace') {
        $written = db_q("UPDATE design_proposals SET style_id = ?, ops = ?, steps = ?, base_hash = ?, created_at = ? WHERE id = ? AND status = 'pending'",
            array((int) $style_id, $ops_json, $steps_json, $base, $now, $id));
    } elseif ($action === 'withdraw') {
        $written = db_q("UPDATE design_proposals SET status = 'dismissed', decided_by = requested_by, decided_at = ? WHERE id = ? AND status = 'pending'",
            array($now, $id));
    }

    if (!pg_content_proposals_written($action, $written)) {
        return array('ok' => false, 'action' => $action, 'id' => $id, 'message' => '',
            'error' => lang(array('string' => 'Your change to "{var:1}" could not be kept for approval, so nothing was saved. Try again.', 'vars' => array($name))));
    }

    $message = '';

    if (($action === 'insert') || ($action === 'replace')) {
        $message = lang(array('string' => 'Your change to "{var:1}" waits for a designer\'s approval. Visitors see the page as it was until it is applied.', 'vars' => array($name)));
    } elseif ($action === 'withdraw') {
        $message = lang(array('string' => 'Your waiting change to "{var:1}" was withdrawn: the page was saved as it is on the site.', 'vars' => array($name)));
    }

    return array('ok' => true, 'action' => $action, 'id' => $id, 'message' => $message, 'error' => '');
}
