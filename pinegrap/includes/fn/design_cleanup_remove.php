<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: removing what the "Clean up" window ticked.
//
// Loaded with require_once by the designer endpoint, never on its own.
//
// The lists are read again first (pg_cleanup_design_lists()), and only what
// is still listed goes: a row something has put to use since, or a rule that
// moved, is answered as 'changed' and stays. Files go to the Recycle Bin and
// shared components are deleted, as their own screens do. Style rules and
// variables are not written here: the answer carries the design's
// stylesheets and variables model without them, and the editor puts them
// into its fields and saves, after it has taken the snapshot "Before
// clean-up" that brings them back.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/design_cleanup_list.php');

/**
 * The design's asset list (style_custom_css) with byte ranges cut out of its
 * own stylesheets, each with the line end right after it. A range on a
 * linked or framework row, or past a row's end, is left alone. Pure.
 *
 * @param string $json style_custom_css
 * @param array  $cuts list of array('index', 'start', 'end')
 * @return string the list as JSON, '' when it cannot be read
 */
function pg_cleanup_cut_rules($json, $cuts)
{
    $rows = json_decode((string) $json, true);
    if (!is_array($rows)) {
        return '';
    }
    $rows = array_values($rows);
    $own = array();
    foreach (pg_cleanup_own_sheets($json) as $sheet) {
        $own[$sheet['index']] = true;
    }
    $by = array();
    foreach ((array) $cuts as $cut) {
        $i = (int) $cut['index'];
        if (isset($own[$i])) {
            $by[$i][] = array((int) $cut['start'], (int) $cut['end']);
        }
    }
    foreach ($by as $i => $ranges) {
        // From the last range back, so the earlier offsets stay true.
        usort($ranges, function ($a, $b) {
            return $b[0] - $a[0];
        });
        $css = $rows[$i]['content'];
        foreach ($ranges as $r) {
            if ($r[0] < 0 || $r[1] <= $r[0] || $r[1] > strlen($css)) {
                continue;
            }
            $end = $r[1];
            if (substr($css, $end, 2) === "\r\n") {
                $end += 2;
            } elseif (substr($css, $end, 1) === "\n") {
                $end += 1;
            }
            $css = substr($css, 0, $r[0]) . substr($css, $end);
        }
        $rows[$i]['content'] = $css;
    }
    $out = json_encode($rows, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return is_string($out) ? $out : '';
}

/**
 * A variables model without the own variables named by $keys; the
 * built-in values stay. Pure.
 *
 * @param array|null $model pg_design_variables_stored()
 * @param array      $keys
 * @return array|null
 */
function pg_cleanup_drop_variables($model, $keys)
{
    if (!is_array($model)) {
        return null;
    }
    $drop = array_flip(array_map('strval', (array) $keys));
    $custom = isset($model['custom']) && is_array($model['custom']) ? $model['custom'] : array();
    $model['custom'] = array_values(array_filter($custom, function ($c) use ($drop) {
        return !(is_array($c) && isset($c['key']) && is_scalar($c['key']) && isset($drop[(string) $c['key']]));
    }));
    return $model;
}

/**
 * What of the ticked rows is still listed: the rows of each kind, and every
 * id no longer listed as failed with the reason 'changed'. Rules, files and
 * components are known by their id, variables by their key. Pure.
 *
 * @param array $lists  pg_cleanup_design_lists()
 * @param array $ticked kind => ids
 * @return array rules, files, components (rows), variables (keys), failed
 */
function pg_cleanup_remove_pick($lists, $ticked)
{
    $out = array('rules' => array(), 'files' => array(), 'components' => array(), 'variables' => array(), 'failed' => array());
    foreach (array('rules' => 'id', 'files' => 'id', 'components' => 'id', 'variables' => 'key') as $kind => $field) {
        $rows = array();
        foreach (isset($lists[$kind]['rows']) ? $lists[$kind]['rows'] : array() as $row) {
            $rows[(string) $row[$field]] = $row;
        }
        $asked = isset($ticked[$kind]) && is_array($ticked[$kind]) ? $ticked[$kind] : array();
        foreach (array_unique(array_map('strval', array_filter($asked, 'is_scalar'))) as $id) {
            if (!isset($rows[$id])) {
                $out['failed'][] = array('kind' => $kind, 'id' => $id, 'reason' => 'changed');
            } elseif ($kind === 'variables') {
                $out['variables'][] = $id;
            } else {
                $out[$kind][] = $rows[$id];
            }
        }
    }
    return $out;
}

/**
 * Moves a design file into the Recycle Bin, as the Files screen does:
 * its restore record, its parked name and its audit line.
 *
 * @param int   $file_id
 * @param array $user the operator: id, role
 * @return string '' when it went, else why not
 */
function pg_cleanup_bin_file($file_id, $user)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/explorer/recycle_names.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/explorer/recycle_bin.php');
    $file_id = (int) $file_id;
    $file = db_item("SELECT id, name, folder, design FROM files WHERE id = '$file_id' LIMIT 1");
    if (!$file) {
        return '';
    }
    $bin_id = (int) pg_recycle_folder_id(true);
    if ($bin_id <= 0) {
        return lang('This site has no Recycle Bin, so the file was kept.');
    }
    if (!check_edit_access($file['folder']) || ((int) $file['design'] === 1 && (int) $user['role'] > 1) || pg_recycle_is_inside($file['folder'])) {
        return lang(array('string' => 'Access denied for {var:1}.', 'vars' => $file['name']));
    }
    $audit_before = pg_audit_fetch('files', $file_id);
    db("UPDATE files SET folder = '$bin_id', timestamp = UNIX_TIMESTAMP(), user = '" . (int) $user['id'] . "' WHERE id = '$file_id'");
    pg_recycle_park_name('file', $file_id, $user);
    db("DELETE FROM recycle_bin WHERE (item_type = 'file') AND (item_id = '$file_id')");
    db("INSERT INTO recycle_bin (item_type, item_id, original_parent_id, deleted_at, deleted_by)"
        . " VALUES ('file', '$file_id', '" . (int) $file['folder'] . "', UNIX_TIMESTAMP(), '" . (int) $user['id'] . "')");
    pg_audit_record('recycle', 'files', $file_id, $audit_before);
    return '';
}

/**
 * Deletes shared components placed nowhere, as the palette's bulk delete
 * does without `force`: the rows, their grants, the audit and activity lines.
 *
 * @param array $rows the component rows: id, name
 * @return void
 */
function pg_cleanup_delete_components($rows)
{
    $ids = pg_shared_component_delete_ids(array_map(function ($r) {
        return $r['id'];
    }, $rows));
    if (!$ids) {
        return;
    }
    foreach (pg_shared_component_id_slices($ids) as $in) {
        $before = pg_audit_fetch_many('shared_components', explode(',', $in));
        db("DELETE FROM shared_components WHERE id IN (" . $in . ")");
        pg_audit_record_many('delete', 'shared_components', $before);
        if (pg_schema_has('users_shared_components_xref')) {
            db("DELETE FROM users_shared_components_xref WHERE shared_component_id IN (" . $in . ")");
        }
    }
    log_activity(lang(array(
        'string' => '{var:1} shared components and system widgets were deleted: {var:2}',
        'vars'   => array(count($rows), implode(', ', array_map(function ($r) {
            return $r['name'];
        }, $rows))),
    )), isset($_SESSION['sessionusername']) ? $_SESSION['sessionusername'] : '');
}

/**
 * Removes what the window ticked and is still unused. Files and shared
 * components go now; the rules and variables come back as the design's
 * asset list and variables model without them, for the editor to save.
 *
 * @param int   $style_id
 * @param array $ticked kind => ids (rules and files and components by id, variables by key)
 * @param array $user   the operator: id, role
 * @return array|null removed (kind => ids), failed (kind, id, reason, message),
 *                    css ('' when no rule goes), variables (null when none goes);
 *                    null for a design that is not a visual designer one
 */
function pg_cleanup_remove($style_id, $ticked, $user)
{
    $lists = pg_cleanup_design_lists($style_id);
    if ($lists === null) {
        return null;
    }
    $pick = pg_cleanup_remove_pick($lists, $ticked);
    $out = array('removed' => array('rules' => array(), 'files' => array(), 'components' => array(), 'variables' => array()),
        'failed' => $pick['failed'], 'css' => '', 'variables' => null);

    foreach ($pick['files'] as $f) {
        $why = pg_cleanup_bin_file($f['id'], $user);
        if ($why === '') {
            $out['removed']['files'][] = $f['id'];
        } else {
            $out['failed'][] = array('kind' => 'files', 'id' => (string) $f['id'], 'reason' => 'refused', 'message' => $why);
        }
    }
    if ($out['removed']['files']) {
        log_activity(lang(array('string' => '{var:1} item(s) moved to the Recycle Bin', 'vars' => count($out['removed']['files']))),
            isset($_SESSION['sessionusername']) ? $_SESSION['sessionusername'] : '');
    }

    pg_cleanup_delete_components($pick['components']);
    foreach ($pick['components'] as $c) {
        $out['removed']['components'][] = $c['id'];
    }

    if ($pick['rules']) {
        $json = (string) db_value("SELECT style_custom_css FROM style WHERE style_id = '" . (int) $style_id . "' LIMIT 1");
        $out['css'] = pg_cleanup_cut_rules($json, $pick['rules']);
        foreach ($pick['rules'] as $r) {
            if ($out['css'] !== '') {
                $out['removed']['rules'][] = $r['id'];
            } else {
                $out['failed'][] = array('kind' => 'rules', 'id' => $r['id'], 'reason' => 'changed');
            }
        }
    }

    if ($pick['variables']) {
        $out['variables'] = pg_cleanup_drop_variables(pg_design_variables_stored($style_id), $pick['variables']);
        $out['removed']['variables'] = $out['variables'] === null ? array() : $pick['variables'];
    }
    return $out;
}
