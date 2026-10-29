<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - flow templates saved on the site: a flow's draft kept as a
 * template the gallery offers beside the built-in ones (flow_templates.php),
 * under "Saved on this site". A row of ws_flow_templates holds the graph
 * with the secrets of its boxes dropped and the settings the setup panel
 * asks on Use, in the built-ins' shape; the values in the boxes are the
 * defaults. Its key is site_<id>, never a built-in key. Use goes through the
 * built-ins' own setup and publish checks (ws_flow_template_setup()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The most templates a site keeps saved (binned ones not counted). */
define('WS_FLOW_TEMPLATES_SAVED_MAX', 200);

/**
 * Is the table of saved templates there (2026.11.0, 11.0.115)?
 *
 * @return bool
 */
function ws_flow_templates_saved_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('pg_schema_has') && pg_schema_has('ws_flow_templates') && ws_flows_ready();
    }

    return $ready;
}

/**
 * The key of a saved template.
 *
 * @param int $id
 * @return string
 */
function ws_flow_template_saved_key($id)
{
    return 'site_' . (int) $id;
}

/**
 * The row id a saved template's key names, 0 for any other key.
 *
 * @param mixed $key
 * @return int
 */
function ws_flow_template_saved_id($key)
{
    return (is_string($key) && preg_match('/^site_([1-9]\d{0,9})$/', $key, $match)) ? (int) $match[1] : 0;
}

/**
 * The box settings a saved template asks on Use, by kind: key => setting
 * type (ws_flow_template_setting_types()).
 *
 * @return array kind => (key => type)
 */
function ws_flow_template_setting_keys()
{
    return array(
        'schedule'    => array('time' => 'time'),
        'event'       => array('channel_id' => 'channel'),
        'join'        => array('channel_id' => 'channel'),
        'task'        => array('assignees' => 'person'),
        'post'        => array('channel_id' => 'channel'),
        'email'       => array('to' => 'address'),
        'notify'      => array('user_ids' => 'person'),
        'task_open'   => array('channel_id' => 'channel', 'assignees' => 'person'),
        'webhook'     => array('url' => 'url'),
        'web_check'   => array('url' => 'url'),
        'task_digest' => array('channel_id' => 'channel', 'user_ids' => 'person'),
        'report'      => array('channel_id' => 'channel', 'to' => 'address'),
        'checkin'     => array('channel_id' => 'channel'),
        'lead'        => array('user_ids' => 'person'),
        'approval'    => array('channel_id' => 'channel', 'approvers' => 'person'),
    );
}

/**
 * A graph without the secrets of its boxes: a webhook's address is dropped
 * (a chat or automation hook carries its secret in the path, see
 * ws_flow_url_masked()), and a user and password are taken out of any other
 * address. Values read from earlier boxes ({{node…}}) stay. Pure.
 *
 * @param array $graph ws_flow_graph_normalize()
 * @return array normalized
 */
function ws_flow_template_secrets_dropped($graph)
{
    foreach ($graph['nodes'] as $n => $node) {
        if (!isset($node['config']['url']) || !is_string($node['config']['url'])) {
            continue;
        }

        if ($node['kind'] === 'webhook') {
            $graph['nodes'][$n]['config']['url'] = '';
            continue;
        }

        $graph['nodes'][$n]['config']['url'] = (string) preg_replace('#^([a-z][a-z0-9+.-]*://)[^/?\#@]*@#i', '$1', $node['config']['url']);
    }

    return ws_flow_graph_normalize($graph);
}

/**
 * The settings a saved template asks, in the built-ins' shape: one per type,
 * in the order of the boxes, whose targets are the boxes holding the same
 * value as the first box of that type, so the one answer replaces that value
 * everywhere and a box with a value of its own keeps it. A value the setup
 * panel cannot give is no target: a channel of 0 (any) or -1 (the channel
 * joined), nobody, or a value read from elsewhere ({{…}}). A webhook's
 * address, dropped as a secret, is asked although empty. Pure.
 *
 * @param array $graph ws_flow_template_secrets_dropped()
 * @return array[] type, targets ([node id, key], …)
 */
function ws_flow_template_settings_of($graph)
{
    $keys = ws_flow_template_setting_keys();
    $first = array();
    $settings = array();

    foreach ($graph['nodes'] as $node) {
        foreach ($keys[$node['kind']] ?? array() as $key => $type) {
            $value = $node['config'][$key] ?? null;

            if (($type === 'channel') && ((int) $value <= 0)) {
                continue;
            }

            if (($type === 'person') && (!is_array($value) || empty($value))) {
                continue;
            }

            if (is_string($value) && (strpos($value, '{{') !== false)) {
                continue;
            }

            if (is_string($value) && ($value === '') && !(($type === 'url') && ($node['kind'] === 'webhook'))) {
                continue;
            }

            if (!array_key_exists($type, $first)) {
                $first[$type] = $value;
                $settings[$type] = array('type' => $type, 'targets' => array());
            }

            if ($value === $first[$type]) {
                $settings[$type]['targets'][] = array($node['id'], $key);
            }
        }
    }

    return array_values($settings);
}

/**
 * Title, help and category as written: the title one line of 1-160
 * characters, help one line up to 255, the category one of the built-ins'
 * (ws_flow_template_categories() but saved). Pure but for lang().
 *
 * @param array $data
 * @param bool  $update only the fields sent are checked and set
 * @return array ok, error, status, field, sets
 */
function ws_flow_template_fields_check($data, $update = false)
{
    $sets = array();

    if (!$update || array_key_exists('title', $data)) {
        $sets['title'] = ws_flow_line($data['title'] ?? '', 160);

        if ($sets['title'] === '') {
            return ws_flow_refusal(lang('Give the template a title.'), 422, 'title') + array('sets' => array());
        }
    }

    if (!$update || array_key_exists('help', $data)) {
        $sets['help'] = ws_flow_line($data['help'] ?? '', 255);
    }

    if (!$update || array_key_exists('category', $data)) {
        $sets['category'] = is_string($data['category'] ?? null) ? $data['category'] : 'team';

        if (($sets['category'] === 'saved') || !isset(ws_flow_template_categories()[$sets['category']])) {
            return ws_flow_refusal(lang('Choose one of the groups.'), 422, 'category') + array('sets' => array());
        }
    }

    return array('ok' => true, 'error' => '', 'status' => 200, 'field' => '', 'sets' => $sets);
}

/**
 * May the person change or bin this saved template: its saver, or a staff
 * member at Manager (2) or above, Designer (1) and Administrator (0) being
 * above it. Pure.
 *
 * @param array $viewer
 * @param array $row    ws_flow_templates row
 * @return bool
 */
function ws_flow_template_may_change($viewer, $row)
{
    if (empty($viewer['member']) || ((int) ($viewer['id'] ?? 0) <= 0)) {
        return false;
    }

    return ((int) ($viewer['role'] ?? 3) <= 2) || ((int) $row['created_by'] === (int) $viewer['id']);
}

/**
 * A saved row as a gallery entry, in ws_flow_template_build()'s shape: its
 * category is saved, its own goes in saved.category; start, boxes, threads
 * and motion are computed from the graph; a setting keeps only known types
 * and targets on boxes that are there. Pure.
 *
 * @param array $row    ws_flow_templates row
 * @param array $viewer
 * @return array|null null for a row with no boxes
 */
function ws_flow_template_saved_entry($row, $viewer)
{
    $decoded = json_decode((string) $row['graph'], true);
    $graph = ws_flow_graph_normalize(is_array($decoded) ? $decoded : array());

    if (empty($graph['nodes'])) {
        return null;
    }

    $ids = array_column($graph['nodes'], 'id');
    $types = ws_flow_template_setting_types();
    $settings = array();
    $stored = json_decode((string) $row['settings'], true);

    foreach (is_array($stored) ? $stored : array() as $setting) {
        if (!is_array($setting) || !in_array($setting['type'] ?? null, $types, true)) {
            continue;
        }

        $targets = array();

        foreach ((array) ($setting['targets'] ?? array()) as $target) {
            if (is_array($target) && in_array($target[0] ?? null, $ids, true) && is_string($target[1] ?? null)) {
                $targets[] = array($target[0], $target[1]);
            }
        }

        if (!empty($targets)) {
            $settings[] = array('type' => $setting['type'], 'targets' => $targets);
        }
    }

    return array(
        'key'      => ws_flow_template_saved_key($row['id']),
        'category' => 'saved',
        'title'    => (string) $row['title'],
        'help'     => (string) $row['help'],
        'icon'     => ((string) $row['icon'] !== '') ? (string) $row['icon'] : 'bi-bookmark-star',
        'start'    => $graph['nodes'][0]['kind'],
        'graph'    => $graph,
        'boxes'    => count($graph['nodes']),
        'threads'  => count(ws_flow_template_threads($graph)),
        'settings' => $settings,
        'motion'   => ws_flow_template_motion($graph),
        'saved'    => array(
            'id'         => (int) $row['id'],
            'category'   => (string) $row['category'],
            'created_by' => (int) $row['created_by'],
            'source'     => (int) $row['source_flow_id'],
            'updated_at' => (int) $row['updated_at'],
            'edit'       => ws_flow_template_may_change($viewer, $row),
        ),
    );
}

/**
 * The saved templates offered to the person, the latest changed first: to
 * everyone who builds flows (ws_can_schedule()).
 *
 * @param array $viewer
 * @return array[] ws_flow_template_saved_entry()
 */
function ws_flow_templates_saved($viewer)
{
    if (!ws_flow_templates_saved_ready() || !ws_can_schedule($viewer)) {
        return array();
    }

    $out = array();

    foreach ((array) db_items("SELECT * FROM ws_flow_templates WHERE binned_at = 0 ORDER BY updated_at DESC, id DESC LIMIT " . WS_FLOW_TEMPLATES_SAVED_MAX) as $row) {
        $entry = ws_flow_template_saved_entry($row, $viewer);

        if ($entry !== null) {
            $out[] = $entry;
        }
    }

    return $out;
}

/**
 * One saved template, not binned, as the person may change it; null when
 * there is none or they may not.
 *
 * @param array $viewer
 * @param int   $id
 * @return array|null the row
 */
function ws_flow_template_saved_for_change($viewer, $id)
{
    if (!ws_flow_templates_saved_ready() || ((int) $id <= 0) || !ws_can_schedule($viewer)) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_flow_templates WHERE id = '" . (int) $id . "' AND binned_at = 0");

    return (is_array($row) && ws_flow_template_may_change($viewer, $row)) ? $row : null;
}

/**
 * Saves a flow's draft as a template: the flow one the person may edit
 * (ws_flow_for() at edit), its draft without secrets, the settings taken
 * from its boxes, title, help and category as written.
 *
 * @param array $viewer
 * @param array $flow   ws_flow_for() at edit
 * @param array $data   title, help, category
 * @return array ok, error, status, field, template (the entry)
 */
function ws_flow_template_save($viewer, $flow, $data)
{
    if (!ws_flow_templates_saved_ready() || !ws_can_schedule($viewer)) {
        return ws_flow_refusal(lang('You cannot save flow templates.'), 403) + array('template' => null);
    }

    $fields = ws_flow_template_fields_check($data);

    if (!$fields['ok']) {
        return $fields + array('template' => null);
    }

    $graph = ws_flow_template_secrets_dropped(ws_flow_graph_of($flow));

    if (empty($graph['nodes'])) {
        return ws_flow_refusal(lang('The flow has no boxes to keep as a template.'), 422, 'flow_id') + array('template' => null);
    }

    if ((int) db_value("SELECT COUNT(*) FROM ws_flow_templates WHERE binned_at = 0") >= WS_FLOW_TEMPLATES_SAVED_MAX) {
        return ws_flow_refusal(lang(array('string' => 'The site keeps at most {var:1} flow templates. Delete one first.', 'vars' => WS_FLOW_TEMPLATES_SAVED_MAX)), 422)
            + array('template' => null);
    }

    $now = time();
    $sets = $fields['sets'];

    // The key is site_<id>, known once the row is in: a passing key that
    // keeps the UNIQUE index apart until then.
    db("INSERT INTO ws_flow_templates (template_key, category, title, help, icon, graph, settings, source_flow_id, created_by, updated_by, created_at, updated_at)
        VALUES ('" . e('new_' . bin2hex(random_bytes(12))) . "', '" . e($sets['category']) . "', '" . e($sets['title']) . "', '" . e($sets['help']) . "', '',
        '" . e((string) json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . "',
        '" . e((string) json_encode(ws_flow_template_settings_of($graph), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . "',
        '" . (int) $flow['id'] . "', '" . (int) $viewer['id'] . "', '" . (int) $viewer['id'] . "', '" . $now . "', '" . $now . "')");
    $id = (int) mysqli_insert_id(db::$con);
    db("UPDATE ws_flow_templates SET template_key = '" . e(ws_flow_template_saved_key($id)) . "' WHERE id = '" . $id . "'");

    log_activity(lang(array('string' => 'workspace flow template ({var:1}) was saved', 'vars' => $sets['title'])), (string) ($_SESSION['sessionusername'] ?? ''));

    $row = db_item("SELECT * FROM ws_flow_templates WHERE id = '" . $id . "'");

    return array('ok' => true, 'error' => '', 'status' => 200, 'field' => '', 'template' => is_array($row) ? ws_flow_template_saved_entry($row, $viewer) : null);
}

/**
 * Changes a saved template's title, help or category (those sent).
 *
 * @param array $viewer
 * @param int   $id
 * @param array $data title, help, category
 * @return array ok, error, status, field, template
 */
function ws_flow_template_update($viewer, $id, $data)
{
    $row = ws_flow_template_saved_for_change($viewer, $id);

    if ($row === null) {
        return ws_flow_refusal(lang('That template could not be found, or you cannot change it.'), 404) + array('template' => null);
    }

    $fields = ws_flow_template_fields_check($data, true);

    if (!$fields['ok']) {
        return $fields + array('template' => null);
    }

    $sql = array();

    foreach ($fields['sets'] as $column => $value) {
        $sql[] = $column . " = '" . e($value) . "'";
        $row[$column] = $value;
    }

    $row['updated_at'] = time();
    db("UPDATE ws_flow_templates SET " . implode(', ', array_merge($sql, array("updated_by = '" . (int) $viewer['id'] . "'", "updated_at = '" . $row['updated_at'] . "'"))) . "
        WHERE id = '" . (int) $row['id'] . "'");

    return array('ok' => true, 'error' => '', 'status' => 200, 'field' => '', 'template' => ws_flow_template_saved_entry($row, $viewer));
}

/**
 * Bins a saved template: the gallery no longer offers it; flows made from
 * it keep their boxes.
 *
 * @param array $viewer
 * @param int   $id
 * @return array ok, error, status, field
 */
function ws_flow_template_bin($viewer, $id)
{
    $row = ws_flow_template_saved_for_change($viewer, $id);

    if ($row === null) {
        return ws_flow_refusal(lang('That template could not be found, or you cannot change it.'), 404);
    }

    $now = time();
    db("UPDATE ws_flow_templates SET binned_at = '" . $now . "', updated_by = '" . (int) $viewer['id'] . "', updated_at = '" . $now . "'
        WHERE id = '" . (int) $row['id'] . "' AND binned_at = 0");

    log_activity(lang(array('string' => 'workspace flow template ({var:1}) was deleted', 'vars' => $row['title'])), (string) ($_SESSION['sessionusername'] ?? ''));

    return array('ok' => true, 'error' => '', 'status' => 200, 'field' => '');
}
