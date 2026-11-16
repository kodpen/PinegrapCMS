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

/* ---------------------------------------------------------------------------
   The designer's side: the list, apply, dismiss, take back
   --------------------------------------------------------------------------- */

// How long a dismissed, applied or taken-back change stays in the list.
define('PG_CONTENT_PROPOSALS_KEEP_DAYS', 30);

// The longest reason a dismissal takes.
define('PG_CONTENT_PROPOSALS_REASON_MAX', 300);

/**
 * May this person apply, dismiss or take back a waiting change? A designer
 * or an administrator.
 *
 * @param array|null $user
 * @return bool
 */
function pg_content_proposals_may_decide($user)
{
    return pg_content_proposals_may_set($user);
}

/**
 * Paths of the nodes of a tree by id: id => list of child indexes.
 *
 * @param array $tree
 * @return array
 */
function pg_content_proposals_paths($tree)
{
    $out = array();
    $walk = function ($node, $path) use (&$walk, &$out) {
        if (!is_array($node)) {
            return;
        }

        if (isset($node['_id']) && ((string) $node['_id'] !== '') && !isset($out[(string) $node['_id']])) {
            $out[(string) $node['_id']] = $path;
        }

        foreach ((isset($node['children']) && is_array($node['children'])) ? $node['children'] : array() as $i => $child) {
            $walk($child, array_merge($path, array($i)));
        }
    };
    $walk($tree, array());

    return $out;
}

/**
 * The node at a path, by reference.
 */
function &_pg_cp_at(&$tree, $path)
{
    $node = &$tree;

    foreach ($path as $i) {
        $node = &$node['children'][$i];
    }

    return $node;
}

/**
 * Puts a waiting change's operations on the page as it is now. Each
 * operation must still find its node holding the value it was made against;
 * otherwise the change is stale: "gone" when a node is missing, "changed"
 * when a designer changed it since. All or nothing.
 *
 * @param array $tree   the stored tree now
 * @param array $fields the page's settings now (pg_content_proposals_stored_fields())
 * @param array $ops
 * @return array ok, tree, fields, error ('' | gone | changed | bad)
 */
function pg_content_proposals_apply_ops($tree, $fields, $ops)
{
    $fail = function ($error) use ($tree, $fields) {
        return array('ok' => false, 'tree' => $tree, 'fields' => $fields, 'error' => $error);
    };

    if (!is_array($tree) || !is_array($ops) || empty($ops)) {
        return $fail('bad');
    }

    $fields = is_array($fields) ? $fields : array();
    $paths = pg_content_proposals_paths($tree);

    foreach ($ops as $op) {
        $name = (is_array($op) && isset($op['op'])) ? (string) $op['op'] : '';

        if ($name === 'tree') {
            if (pg_content_proposals_hash($tree) !== (string) (isset($op['base_hash']) ? $op['base_hash'] : '')) {
                return $fail('changed');
            }

            if (!isset($op['tree']) || !is_array($op['tree'])) {
                return $fail('bad');
            }

            $tree = $op['tree'];
            $paths = pg_content_proposals_paths($tree);
            continue;
        }

        if ($name === 'fields') {
            foreach ((array) (isset($op['fields']) ? $op['fields'] : array()) as $key => $value) {
                if (!in_array((string) $key, pg_content_proposals_field_keys(), true)) {
                    return $fail('bad');
                }

                $was = (isset($op['was']) && is_array($op['was']) && array_key_exists($key, $op['was'])) ? $op['was'][$key] : null;
                $now = array_key_exists($key, $fields) ? $fields[$key] : null;

                if (pg_content_proposals_field_value($now) !== pg_content_proposals_field_value($was)) {
                    return $fail('changed');
                }

                $fields[$key] = $value;
            }
            continue;
        }

        if (!in_array($name, array('text', 'src', 'note', 'area'), true)) {
            return $fail('bad');
        }

        $id = isset($op['node']) ? (string) $op['node'] : '';

        if (($id === '') || !isset($paths[$id])) {
            return $fail('gone');
        }

        $node = &_pg_cp_at($tree, $paths[$id]);
        $was = isset($op['was']) ? (string) $op['was'] : '';

        if ($name === 'text') {
            if (!isset($node['props']) || !is_array($node['props']) || !array_key_exists('text', $node['props']) || ((string) $node['props']['text'] !== $was)) {
                unset($node);
                return $fail('changed');
            }

            $node['props']['text'] = (string) $op['text'];
        } elseif ($name === 'src') {
            if (pg_content_proposals_image_src($node) !== $was) {
                unset($node);
                return $fail('changed');
            }

            pg_content_proposals_set_image_src($node, (string) $op['src']);
        } elseif ($name === 'note') {
            if ((string) (isset($node['props']['_notes']) ? $node['props']['_notes'] : '') !== $was) {
                unset($node);
                return $fail('changed');
            }

            if ((string) $op['note'] === '') {
                unset($node['props']['_notes'], $node['props']['_notes_by'], $node['props']['_notes_at']);
            } else {
                $node['props']['_notes'] = (string) $op['note'];
            }
        } else {
            if ((pg_content_proposals_hash($node) !== $was) || !isset($op['tree']) || !is_array($op['tree'])) {
                unset($node);
                return $fail('changed');
            }

            $node = $op['tree'];
        }

        unset($node);
        $paths = pg_content_proposals_paths($tree);
    }

    return array('ok' => true, 'tree' => $tree, 'fields' => $fields, 'error' => '');
}

/**
 * Sets a picture address where pg_content_proposals_image_src() reads it.
 *
 * @param array  $node
 * @param string $src
 */
function pg_content_proposals_set_image_src(&$node, $src)
{
    if ((isset($node['type']) ? (string) $node['type'] : '') === 'content') {
        $node['props']['src'] = $src;
        return;
    }

    $attrs = (isset($node['props']['_attrs']) && is_array($node['props']['_attrs'])) ? $node['props']['_attrs'] : array();

    foreach ($attrs as $i => $attr) {
        if (is_array($attr) && isset($attr['name']) && ($attr['name'] === 'src')) {
            $attrs[$i]['value'] = $src;
            $node['props']['_attrs'] = $attrs;
            return;
        }
    }

    $attrs[] = array('name' => 'src', 'value' => $src);
    $node['props']['_attrs'] = $attrs;
}

/**
 * A dismissal's reason, or null when it is too long.
 *
 * @param mixed $reason
 * @return string|null
 */
function pg_content_proposals_reason($reason)
{
    $reason = trim(preg_replace('/\s+/u', ' ', (string) $reason));

    return (mb_strlen($reason) > PG_CONTENT_PROPOSALS_REASON_MAX) ? null : $reason;
}

/**
 * May an applied change be taken back? By the person who applied it, while
 * the page is still as the apply left it.
 *
 * @param array  $row     design_proposals row
 * @param int    $user_id
 * @param string $now_hash fingerprint of the stored tree now (pg_content_proposals_stored_hash())
 * @return string '' when it may, else why not: closed | other | saved
 */
function pg_content_proposals_revert_check($row, $user_id, $now_hash)
{
    if (((string) $row['status'] !== 'applied') || ((string) $row['applied_to'] !== 'page') || ((string) $row['before_tree'] === '')) {
        return 'closed';
    }

    if ((int) $row['decided_by'] !== (int) $user_id) {
        return 'other';
    }

    return ((string) $now_hash === (string) $row['after_hash']) ? '' : 'saved';
}

/**
 * Is a row still shown in the list? A waiting or stale one always; one
 * applied, taken back or dismissed for PG_CONTENT_PROPOSALS_KEEP_DAYS days.
 *
 * @param array $row
 * @param int   $now
 * @return bool
 */
function pg_content_proposals_listed($row, $now)
{
    if (in_array((string) $row['status'], array('pending', 'stale'), true)) {
        return true;
    }

    return ((int) $row['decided_at'] > 0) && ((int) $row['decided_at'] >= ((int) $now - PG_CONTENT_PROPOSALS_KEEP_DAYS * 86400));
}

/**
 * The author History shows for a revision a designer applied for an editor.
 *
 * @param string $designer
 * @param string $editor
 * @return string
 */
function pg_content_proposals_revision_by($designer, $editor)
{
    return ((string) $editor === '') ? (string) $designer
        : lang(array('string' => '{var:1} (change by {var:2})', 'vars' => array((string) $designer, (string) $editor)));
}

/**
 * The fingerprint of the page's stored tree as written (also the revision's
 * tree_hash).
 *
 * @param int $page_id
 * @return string
 */
function pg_content_proposals_stored_hash($page_id)
{
    return sha1(pg_page_tree_json((int) $page_id));
}

/**
 * An editor's change by id.
 *
 * @param int $id
 * @return array|null
 */
function pg_content_proposals_get($id)
{
    if (!pg_content_proposals_ready()) {
        return null;
    }

    $row = db_item((string) pg_db_bind("SELECT * FROM design_proposals WHERE id = ? AND agent = ? LIMIT 1",
        array((int) $id, PG_CONTENT_PROPOSALS_AGENT), 'escape'));

    return is_array($row) ? $row : null;
}

/**
 * The changes the list shows, newest first, with their page and people.
 *
 * @return array
 */
function pg_content_proposals_list()
{
    if (!pg_content_proposals_ready()) {
        return array();
    }

    $since = time() - PG_CONTENT_PROPOSALS_KEEP_DAYS * 86400;
    $rows = db_items((string) pg_db_bind(
        "SELECT p.*, page.page_name, page.page_style, editor.user_username AS editor_name, decider.user_username AS decider_name
         FROM design_proposals AS p
         LEFT JOIN page ON page.page_id = p.page_id
         LEFT JOIN user AS editor ON editor.user_id = p.requested_by
         LEFT JOIN user AS decider ON decider.user_id = p.decided_by
         WHERE p.agent = ? AND (p.status IN ('pending', 'stale') OR p.decided_at >= ?)
         ORDER BY p.id DESC LIMIT 200",
        array(PG_CONTENT_PROPOSALS_AGENT, $since), 'escape'));

    return is_array($rows) ? $rows : array();
}

/**
 * The page's settings and tree as the design save wants them.
 */
function _pg_cp_page($row)
{
    $page = db_item((string) pg_db_bind("SELECT page_id, page_style FROM page WHERE page_id = ? LIMIT 1", array((int) $row['page_id']), 'escape'));

    if (!is_array($page) || ((int) $page['page_style'] <= 0)) {
        return null;
    }

    $tree = json_decode(pg_page_tree_json((int) $page['page_id']), true);
    $fields = pg_content_proposals_stored_fields((int) $page['page_style'], (int) $page['page_id']);

    return (is_array($tree) && $fields) ? array('style_id' => (int) $page['page_style'], 'tree' => $tree, 'fields' => $fields) : null;
}

/**
 * Writes a tree and settings to the page through the design save, as the
 * designer; refused while somebody has the page open in the editor.
 */
function _pg_cp_write($user, $row, $style_id, $fields, $tree)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/designer_collab.php');

    if (!pg_collab_may_edit_page('', (int) $row['page_id'])) {
        return lang('Somebody has this page open in the Page Design Studio. Try again when they are done.');
    }

    $fields['tree_json'] = pg_designer_tree_encode($tree);
    $fields['revision_source'] = 'publish';
    $saved = pg_designer_save_page($style_id, $fields, $user);

    return $saved['ok'] ? '' : implode(' ', (array) $saved['errors']);
}

/**
 * Applies an editor's waiting change to the page: the operations on the
 * page as it is now, the result through the content gate again, one save
 * as the designer (one revision). A change that no longer fits is marked
 * stale.
 *
 * @param array $user
 * @param int   $id
 * @return array ok, error
 */
function pg_content_proposals_apply($user, $id)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/designer_access.php');

    if (!pg_content_proposals_may_decide($user)) {
        return array('ok' => false, 'error' => lang('Only a Designer or an Administrator can apply a waiting change.'));
    }

    $row = pg_content_proposals_get($id);

    if (!$row || ((string) $row['status'] !== 'pending')) {
        return array('ok' => false, 'error' => lang('This change is no longer waiting.'));
    }

    $page = _pg_cp_page($row);

    if (!$page) {
        return array('ok' => false, 'error' => lang('The page could not be read.'));
    }

    $ops = json_decode((string) $row['ops'], true);
    $applied = pg_content_proposals_apply_ops($page['tree'], $page['fields'], is_array($ops) ? $ops : array());

    if (!$applied['ok']) {
        db_q("UPDATE design_proposals SET status = 'stale', error = ? WHERE id = ? AND status = 'pending'",
            array(($applied['error'] === 'gone') ? 'node gone' : 'page changed', (int) $row['id']));

        return array('ok' => false, 'error' => lang('The page changed since this change was made, so it cannot be applied.'));
    }

    // The gate stays the authority: what the stored operations make is taken
    // only as far as a content-level save may change the page.
    $tree = pg_designer_merge_restricted_tree($page['tree'], $applied['tree']);
    $before = pg_page_tree_json((int) $row['page_id']);
    $error = _pg_cp_write($user, $row, $page['style_id'], $applied['fields'], $tree);

    if ($error !== '') {
        return array('ok' => false, 'error' => $error);
    }

    db_q("UPDATE design_proposals SET status = 'applied', applied_to = 'page', before_tree = ?, after_hash = ?, decided_by = ?, decided_at = ?, error = '' WHERE id = ?",
        array($before, pg_content_proposals_stored_hash((int) $row['page_id']), (int) $user['id'], time(), (int) $row['id']));

    return array('ok' => true, 'error' => '');
}

/**
 * Dismisses a waiting or stale change, with a reason of at most 300
 * characters kept in the row's steps.
 *
 * @param array  $user
 * @param int    $id
 * @param string $reason
 * @return array ok, error
 */
function pg_content_proposals_dismiss($user, $id, $reason)
{
    if (!pg_content_proposals_may_decide($user)) {
        return array('ok' => false, 'error' => lang('Only a Designer or an Administrator can dismiss a waiting change.'));
    }

    $reason = pg_content_proposals_reason($reason);

    if ($reason === null) {
        return array('ok' => false, 'error' => lang(array('string' => 'The reason can be at most {var:1} characters.', 'vars' => array(PG_CONTENT_PROPOSALS_REASON_MAX))));
    }

    $row = pg_content_proposals_get($id);

    if (!$row || !in_array((string) $row['status'], array('pending', 'stale'), true)) {
        return array('ok' => false, 'error' => lang('This change is no longer waiting.'));
    }

    $steps = json_decode((string) $row['steps'], true);
    $steps = is_array($steps) ? $steps : array();
    $steps['reason'] = $reason;

    $done = db_q("UPDATE design_proposals SET status = 'dismissed', steps = ?, decided_by = ?, decided_at = ? WHERE id = ? AND status IN ('pending', 'stale')",
        array((string) json_encode($steps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (int) $user['id'], time(), (int) $row['id']));

    return ($done === false) ? array('ok' => false, 'error' => lang('The change could not be dismissed. Try again.')) : array('ok' => true, 'error' => '');
}

/**
 * Takes an applied change back: the page gets the tree and settings it had
 * before, while nobody has saved it since. By the designer who applied it.
 *
 * @param array $user
 * @param int   $id
 * @return array ok, error
 */
function pg_content_proposals_revert($user, $id)
{
    if (!pg_content_proposals_may_decide($user)) {
        return array('ok' => false, 'error' => lang('Only a Designer or an Administrator can take a change back.'));
    }

    $row = pg_content_proposals_get($id);
    $check = $row ? pg_content_proposals_revert_check($row, (int) $user['id'], pg_content_proposals_stored_hash((int) $row['page_id'])) : 'closed';

    if ($check === 'other') {
        return array('ok' => false, 'error' => lang('Only the person who applied the change can take it back.'));
    }

    if ($check === 'saved') {
        return array('ok' => false, 'error' => lang('The page was saved again after this change, so it cannot be taken back here. Use History in the Page Design Studio instead.'));
    }

    $page = ($check === '') ? _pg_cp_page($row) : null;
    $before = $page ? json_decode((string) $row['before_tree'], true) : null;

    if (!is_array($before)) {
        return array('ok' => false, 'error' => lang('Only an applied change can be taken back.'));
    }

    // The settings it changed go back to what they were.
    $fields = $page['fields'];

    foreach ((array) json_decode((string) $row['ops'], true) as $op) {
        if (is_array($op) && (($op['op'] ?? '') === 'fields') && isset($op['was']) && is_array($op['was'])) {
            foreach ($op['was'] as $key => $value) {
                $fields[$key] = $value;
            }
        }
    }

    $error = _pg_cp_write($user, $row, $page['style_id'], $fields, $before);

    if ($error !== '') {
        return array('ok' => false, 'error' => $error);
    }

    db_q("UPDATE design_proposals SET status = 'reverted', decided_at = ? WHERE id = ?", array(time(), (int) $row['id']));

    return array('ok' => true, 'error' => '');
}

/**
 * The list's order: waiting changes first, then stale ones, then those
 * closed in the last PG_CONTENT_PROPOSALS_KEEP_DAYS days; each newest first.
 * Rows the list no longer shows are left out.
 *
 * @param array $rows
 * @param int   $now
 * @return array
 */
function pg_content_proposals_order($rows, $now)
{
    $rank = array('pending' => 0, 'stale' => 1);
    $kept = array();

    foreach ((array) $rows as $row) {
        if (is_array($row) && pg_content_proposals_listed($row, $now)) {
            $kept[] = $row;
        }
    }

    usort($kept, function ($a, $b) use ($rank) {
        $ra = isset($rank[(string) $a['status']]) ? $rank[(string) $a['status']] : 2;
        $rb = isset($rank[(string) $b['status']]) ? $rank[(string) $b['status']] : 2;

        if ($ra !== $rb) {
            return $ra - $rb;
        }

        // The closed ones by when they were decided, the open ones by when saved.
        $ta = ($ra === 2) ? (int) $a['decided_at'] : (int) $a['created_at'];
        $tb = ($rb === 2) ? (int) $b['decided_at'] : (int) $b['created_at'];

        return ($tb <=> $ta) ?: ((int) $b['id'] <=> (int) $a['id']);
    });

    return $kept;
}

/**
 * Is a picture address one of the site's own files, whose small image the
 * comparison may draw? A path, never another site or a scheme.
 *
 * @param string $src
 * @return bool
 */
function pg_content_proposals_local_picture($src)
{
    $src = trim((string) $src);

    return ($src !== '') && (strpos($src, '//') !== 0) && !preg_match('~^[a-z][a-z0-9+.-]*:~i', $src);
}
