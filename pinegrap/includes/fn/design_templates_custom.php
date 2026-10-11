<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design templates made from a design. The operator turns a visual design
 * into a template: every saved page of it, the shared components and system
 * widgets the pages place, the folders the pages live in, the page forms and
 * the design's own assets (CSS, JS, fonts). The template is stored as one
 * JSON document in the design_template table, in exactly the shape a
 * template file under includes/design_templates/ returns, so
 * pg_design_template_prepare() and pg_design_template_install() open it the
 * same way as a built-in one, and the document can be carried to another
 * site as it is.
 *
 * The export is the reverse of _pg_tpl_fill(): the addresses of the design's
 * pages become {{page:<key>}}, the page ids in widget and form settings
 * {{tab:<key>}}, the folders {{folder:<key>}}, the contact groups
 * {{contact_group:<key>}}, the site's name {{site_name}} and the year after
 * a copyright sign {{year}}. Every placed shared component becomes a
 * template widget (props.templateWidget) or a template shared component
 * (props.templateShared). The pg_dtc_* helpers do the rewriting without the
 * database; pg_design_template_from_style() reads the design and hands them
 * what they need in $ctx.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// ========================= STORE =================================================================

// The design_template table exists (2026.4.8).
function pg_design_template_custom_ready()
{
    return pg_schema_has('design_template');
}

// One design_template row as a template, in the shape a template file
// returns. The row's own columns win over the copies inside the document
// (they are what a rename would change). null when the document is broken.
function _pg_dtc_row_to_template($row)
{
    if (!is_array($row)) return null;
    $tpl = json_decode((string)$row['template_json'], true);
    if (!is_array($tpl) || empty($tpl['pages']) || !is_array($tpl['pages'])) return null;
    $row_id = (int)$row['id'];
    $tpl['id']          = 'custom-' . $row_id;
    $tpl['name']        = (string)$row['name'];
    $tpl['description'] = (string)$row['description'];
    $tpl['version']     = (string)$row['version'];
    $tpl['framework']   = (string)$row['framework'];
    $tpl['look']        = (string)$row['look_key'];
    $tpl['palette']     = (string)$row['palette_key'];
    $tpl['builtin']     = false;
    $tpl['row_id']      = $row_id;
    // After the built-in templates, in the order they were made.
    $tpl['order']       = 1000 + $row_id;
    $tpl['icon']        = 'bi-person-workspace';
    $tpl['thumb']       = '';
    return $tpl;
}

/**
 * Every template made from a design: array('custom-<id>' => template). A row
 * whose document cannot be read is left out.
 */
function pg_design_templates_custom()
{
    $out = array();
    if (!pg_design_template_custom_ready()) return $out;
    $rows = db_items(
        "SELECT id, name, description, version, framework, look_key, palette_key, template_json
         FROM design_template
         ORDER BY id ASC");
    foreach ((array)$rows as $row) {
        $tpl = _pg_dtc_row_to_template($row);
        if ($tpl) $out[$tpl['id']] = $tpl;
    }
    return $out;
}

/**
 * One template made from a design, read straight from its row and shaped
 * the way pg_design_templates() offers it - for the answer to the request
 * that has just written it, where the list may already be cached. null when
 * there is no such template.
 */
function pg_design_template_custom_get($key)
{
    if (!pg_design_template_custom_ready()) return null;
    if (!preg_match('/^custom-(\d+)$/', is_scalar($key) ? (string)$key : '', $m)) return null;
    $row = db_item(
        "SELECT id, name, description, version, framework, look_key, palette_key, template_json
         FROM design_template
         WHERE id = '" . (int)$m[1] . "'
         LIMIT 1");
    $tpl = _pg_dtc_row_to_template($row);
    return $tpl ? _pg_tpl_normalize($tpl, $tpl['id']) : null;
}

/**
 * Write a template made from a design.
 *
 * $data: 'template' (the template array) and 'source_style_id'. The row is
 * written under a temporary key first - template_key is unique, so two rows
 * written at once cannot both wait under '' - and then takes 'custom-<id>'.
 *
 * Returns array('ok' => true, 'row_id', 'template_id') or array('ok' => false, 'error' => text).
 */
function pg_design_template_custom_save($data, $user)
{
    if (!is_array($user) || empty($user['id']) || !isset($user['role']) || (int)$user['role'] > 1) {
        return array('ok' => false, 'error' => lang('You do not have access to add a design.'));
    }
    if (!pg_design_template_custom_ready()) {
        return array('ok' => false, 'error' => lang('The database has not been upgraded yet. Run the software update first.'));
    }
    $tpl = (isset($data['template']) && is_array($data['template'])) ? $data['template'] : null;
    if (!$tpl || empty($tpl['pages'])) return array('ok' => false, 'error' => lang('The template could not be saved.'));

    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    $json = json_encode($tpl, $flags);
    if ($json === false) return array('ok' => false, 'error' => lang('The template could not be saved.'));

    $name    = mb_substr(trim((string)(isset($tpl['name']) ? $tpl['name'] : '')), 0, 255);
    $version = (isset($tpl['version']) && preg_match('/^\d+\.\d+\.\d+$/', (string)$tpl['version'])) ? (string)$tpl['version'] : '1.0.0';
    $now     = time();
    $temp    = 'new-' . substr(md5(uniqid('', true)), 0, 16);

    db("INSERT INTO design_template
            (template_key, name, description, version, framework, look_key, palette_key,
             source_style_id, template_json, created_by, created_at, updated_at)
        VALUES (
            '" . e($temp) . "',
            '" . e($name) . "',
            '" . e(isset($tpl['description']) ? (string)$tpl['description'] : '') . "',
            '" . e($version) . "',
            '" . e(pg_design_framework_key(isset($tpl['framework']) ? $tpl['framework'] : '')) . "',
            '" . e(mb_substr(isset($tpl['look']) ? (string)$tpl['look'] : '', 0, 100)) . "',
            '" . e(mb_substr(isset($tpl['palette']) ? (string)$tpl['palette'] : '', 0, 100)) . "',
            '" . (int)(isset($data['source_style_id']) ? $data['source_style_id'] : 0) . "',
            '" . e($json) . "',
            '" . (int)$user['id'] . "',
            '$now',
            '$now')");
    $row_id = (int)mysqli_insert_id(db::$con);
    if ($row_id <= 0) return array('ok' => false, 'error' => lang('The template could not be saved.'));

    $key = 'custom-' . $row_id;
    db("UPDATE design_template SET template_key = '" . e($key) . "' WHERE id = '$row_id' LIMIT 1");

    return array('ok' => true, 'row_id' => $row_id, 'template_id' => $key);
}

/**
 * Delete a template made from a design, by its row id or its key
 * ('custom-<id>'). A built-in template cannot be deleted. The widget and
 * shared component rows the template left behind when it was opened and
 * never published go with it (pg_design_template_discard(): only the ones
 * no page places); designs made from it keep everything they have.
 *
 * Returns array('ok' => true, 'name') or array('ok' => false, 'error' => text).
 */
function pg_design_template_custom_delete($row_id_or_key, $user)
{
    if (!is_array($user) || empty($user['id']) || !isset($user['role']) || (int)$user['role'] > 1) {
        return array('ok' => false, 'error' => lang('Permission denied.'));
    }
    $raw = is_scalar($row_id_or_key) ? trim((string)$row_id_or_key) : '';
    $row_id = 0;
    if (ctype_digit($raw)) {
        $row_id = (int)$raw;
    } elseif (preg_match('/^custom-(\d+)$/', $raw, $m)) {
        $row_id = (int)$m[1];
    } elseif ($raw !== '' && pg_design_template($raw)) {
        return array('ok' => false, 'error' => lang('Only the templates made from a design can be deleted.'));
    }
    if ($row_id <= 0 || !pg_design_template_custom_ready()) {
        return array('ok' => false, 'error' => lang('The template could not be found.'));
    }
    $row = db_item("SELECT id, name FROM design_template WHERE id = '$row_id' LIMIT 1");
    if (!is_array($row)) return array('ok' => false, 'error' => lang('The template could not be found.'));

    db("DELETE FROM design_template WHERE id = '$row_id' LIMIT 1");

    $origin = 'custom-' . $row_id . '/';
    $left = db_values(
        "SELECT id FROM shared_components
         WHERE category LIKE 'template:" . e(escape_like($origin)) . "%'
            OR system_region_config LIKE '%\"template_origin\":\"" . e(escape_like($origin)) . "%'");
    foreach (array_chunk(array_map('intval', (array)$left), 100) as $chunk) {
        pg_design_template_discard($chunk);
    }

    log_activity(lang(array('string' => 'design template ({var:1}) was deleted', 'vars' => array((string)$row['name']))),
        isset($user['username']) ? (string)$user['username'] : '');

    return array('ok' => true, 'name' => (string)$row['name']);
}

// ========================= EXPORT HELPERS (no database) ==========================================

/**
 * A template key for a page, a folder, a widget or a shared component:
 * the name in ASCII, lower case, [a-z0-9_-] only, 'page' when nothing is
 * left, numbered (-2, -3 …) against $taken (key => true). The design's home
 * page asks with $is_home and gets 'home', which is the key
 * pg_design_template_install() makes the site's home page; it has to be
 * asked for first, so that another page called "home" becomes home-2.
 */
function pg_dtc_page_key($name, $taken, $is_home = false)
{
    $taken = is_array($taken) ? $taken : array();
    if ($is_home && !isset($taken['home'])) return 'home';
    $base = strtolower(pg_ascii_file_name((string)$name));
    $base = preg_replace('/[^a-z0-9_-]+/', '-', $base);
    // pg_ascii_file_name() writes a space as '_': "Header [1]" would read
    // header_-1.
    $base = preg_replace('/[-_]*-[-_]*/', '-', $base);
    $base = trim(substr(trim($base, '-_'), 0, 48), '-_');
    if ($base === '') $base = 'page';
    for ($n = 1; $n < 1000; $n++) {
        $key = ($n === 1) ? $base : $base . '-' . $n;
        if (!isset($taken[$key])) return $key;
    }
    return $base . '-' . substr(md5(uniqid('', true)), 0, 6);
}

/**
 * One string value of a tree, rewritten with the template's placeholders:
 *   - the address of a page of the design, alone or followed by ?… or #…,
 *     becomes {{page:<key>}} ($ctx['page_urls'], address => key); an address
 *     that only starts the same way (/blog and /blog-post) is not touched;
 *   - a value that is the site's name and nothing else becomes {{site_name}};
 *   - the current year after a copyright sign becomes {{year}}.
 */
function pg_dtc_tokenize_string($value, $ctx)
{
    if (!is_string($value) || $value === '') return $value;

    if (!empty($ctx['page_urls']) && is_array($ctx['page_urls'])) {
        foreach ($ctx['page_urls'] as $url => $key) {
            $url = (string)$url;
            if ($url === '') continue;
            if ($value === $url) {
                $value = '{{page:' . $key . '}}';
                break;
            }
            $len = strlen($url);
            if (strncmp($value, $url, $len) === 0 && isset($value[$len]) && ($value[$len] === '?' || $value[$len] === '#')) {
                $value = '{{page:' . $key . '}}' . substr($value, $len);
                break;
            }
        }
    }

    $site_name = isset($ctx['site_name']) ? (string)$ctx['site_name'] : '';
    if ($site_name !== '' && $value === $site_name) return '{{site_name}}';

    $year = isset($ctx['year']) ? (string)$ctx['year'] : '';
    if (preg_match('/^\d{4}$/', $year) && (strpos($value, '©') !== false || stripos($value, '&copy;') !== false)) {
        $value = preg_replace('/(©|&copy;)\s*' . $year . '(?!\d)/iu', '$1 {{year}}', $value);
    }

    return $value;
}

// Whether a value is a plain positive id (an integer, or a string of digits).
function _pg_dtc_id_value($value)
{
    if (is_int($value)) return $value > 0 ? $value : 0;
    if (is_string($value) && $value !== '' && ctype_digit($value)) return (int)$value;
    return 0;
}

// The props of a node: every string through pg_dtc_tokenize_string(), the
// data bindings left exactly as they are, a folder id of the template's
// folders (a form field's upload folder, props._cf.upload_folder_id) as
// {{folder:<key>}}.
function _pg_dtc_tokenize_props($props, $ctx, $depth = 0)
{
    if (!is_array($props) || $depth > 20) return $props;
    foreach ($props as $k => $v) {
        if ($k === '_bindings') continue;
        if (is_array($v)) {
            $props[$k] = _pg_dtc_tokenize_props($v, $ctx, $depth + 1);
        } elseif (is_string($k) && strpos($k, 'folder_id') !== false && ($id = _pg_dtc_id_value($v)) > 0
            && isset($ctx['folders_by_id'][$id])) {
            $props[$k] = '{{folder:' . $ctx['folders_by_id'][$id] . '}}';
        } elseif (is_string($v)) {
            $props[$k] = pg_dtc_tokenize_string($v, $ctx);
        }
    }
    return $props;
}

/**
 * A page, widget or shared component tree made portable.
 *
 * The editor's own state on a node (_id, _expanded, _sharedDirty) is
 * dropped; the editor gives the nodes their ids again when the template is
 * opened. A shared_ref is pointed at the template's entry for the row it
 * places: props.templateWidget ($ctx['widgets_by_sid'], row id => key) or
 * props.templateShared ($ctx['shared_by_sid']); one placing a row that is
 * in neither (deleted since) is left out and counted in $dropped. Every
 * other string goes through pg_dtc_tokenize_string().
 *
 * Returns the tree, or null for a node that is left out.
 */
function pg_dtc_tokenize_tree($node, $ctx, &$dropped = 0, $depth = 0)
{
    if (!is_array($node) || $depth > 80) return $node;
    unset($node['_id'], $node['_expanded'], $node['_sharedDirty']);

    if (isset($node['type']) && $node['type'] === 'shared_ref') {
        $sid = isset($node['props']['sharedId']) ? (int)$node['props']['sharedId'] : 0;
        if ($sid > 0 && isset($ctx['widgets_by_sid'][$sid])) {
            $node['props'] = array('templateWidget' => (string)$ctx['widgets_by_sid'][$sid]);
        } elseif ($sid > 0 && isset($ctx['shared_by_sid'][$sid])) {
            $node['props'] = array('templateShared' => (string)$ctx['shared_by_sid'][$sid]);
        } else {
            $dropped++;
            return null;
        }
        $node['children'] = array();
        return $node;
    }

    if (isset($node['props']) && is_array($node['props'])) {
        $node['props'] = _pg_dtc_tokenize_props($node['props'], $ctx);
    }
    if (isset($node['children']) && is_array($node['children'])) {
        $children = array();
        foreach ($node['children'] as $child) {
            $child = pg_dtc_tokenize_tree($child, $ctx, $dropped, $depth + 1);
            if ($child !== null) $children[] = $child;
        }
        $node['children'] = $children;
    }
    return $node;
}

/**
 * A system widget's settings (system_region_config) made portable. The mark
 * of the template a widget row was made for (template_origin) goes. A
 * setting whose name holds 'page_id' and names a page of the design becomes
 * {{tab:<key>}}, 'folder_id' a template folder {{folder:<key>}},
 * 'contact_group_id' a template contact group {{contact_group:<key>}}. Any
 * other id - a product group, a page outside the design - stays as it is.
 * A list under such a name is read item by item.
 */
function pg_dtc_tokenize_config($cfg, $ctx, $parent_key = '', $depth = 0)
{
    if (!is_array($cfg) || $depth > 20) return $cfg;
    unset($cfg['template_origin']);
    foreach ($cfg as $k => $v) {
        $name = is_int($k) ? (string)$parent_key : (string)$k;
        if (is_array($v)) {
            $cfg[$k] = pg_dtc_tokenize_config($v, $ctx, $name, $depth + 1);
            continue;
        }
        $id = _pg_dtc_id_value($v);
        if ($id <= 0) continue;
        if (strpos($name, 'page_id') !== false && isset($ctx['page_ids'][$id])) {
            $cfg[$k] = '{{tab:' . $ctx['page_ids'][$id] . '}}';
        } elseif (strpos($name, 'folder_id') !== false && isset($ctx['folders_by_id'][$id])) {
            $cfg[$k] = '{{folder:' . $ctx['folders_by_id'][$id] . '}}';
        } elseif (strpos($name, 'contact_group_id') !== false && isset($ctx['contact_groups_by_id'][$id])) {
            $cfg[$k] = '{{contact_group:' . $ctx['contact_groups_by_id'][$id] . '}}';
        }
    }
    return $cfg;
}

/**
 * A page's form settings (pg_cf_load_page_form_settings()) made portable:
 * the e-mail pages and the next page as {{tab:<key>}} when they are pages of
 * the design, the contact group as {{contact_group:<key>}}, the site's own
 * address as {{site_email}}. What the loader adds about the stored form
 * (exists, orphan) is not a setting and goes.
 */
function pg_dtc_tokenize_form($settings, $ctx)
{
    if (!is_array($settings)) return array();
    unset($settings['exists'], $settings['orphan']);
    foreach (array('notify_page_id', 'confirm_page_id', 'confirmation_page_id') as $k) {
        if (!isset($settings[$k])) continue;
        $id = _pg_dtc_id_value($settings[$k]);
        if ($id > 0 && isset($ctx['page_ids'][$id])) $settings[$k] = '{{tab:' . $ctx['page_ids'][$id] . '}}';
    }
    if (isset($settings['contact_group_id'])) {
        $id = _pg_dtc_id_value($settings['contact_group_id']);
        if ($id > 0 && isset($ctx['contact_groups_by_id'][$id])) {
            $settings['contact_group_id'] = '{{contact_group:' . $ctx['contact_groups_by_id'][$id] . '}}';
        }
    }
    $site_email = isset($ctx['site_email']) ? (string)$ctx['site_email'] : '';
    if ($site_email !== '' && isset($settings['notify_email']) && (string)$settings['notify_email'] === $site_email) {
        $settings['notify_email'] = '{{site_email}}';
    }
    return $settings;
}

/**
 * The comment settings of a page (a row of pg_designer_load_pages()) in the
 * shape a template page carries them ('comments'), or null when the page
 * takes no comments. The keys are the ones pg_design_template_prepare()
 * reads.
 */
function pg_dtc_page_comments($page, $ctx)
{
    if (!is_array($page) || empty($page['pg_comments'])) return null;
    $email_page = _pg_dtc_id_value(isset($page['pg_comments_email_page']) ? $page['pg_comments_email_page'] : 0);
    $notify = isset($page['pg_comments_notify_email']) ? (string)$page['pg_comments_notify_email'] : '';
    $site_email = isset($ctx['site_email']) ? (string)$ctx['site_email'] : '';
    return array(
        'label'         => isset($page['pg_comments_label']) ? (string)$page['pg_comments_label'] : '',
        'allow_new'     => !empty($page['pg_comments_allow_new']) ? 1 : 0,
        'rating'        => !empty($page['pg_comments_rating']) ? 1 : 0,
        'auto_publish'  => !empty($page['pg_comments_auto_publish']) ? 1 : 0,
        'show_date'     => !empty($page['pg_comments_show_date']) ? 1 : 0,
        'login'         => !empty($page['pg_comments_login']) ? 1 : 0,
        'email_page'    => ($email_page > 0 && isset($ctx['page_ids'][$email_page])) ? '{{tab:' . $ctx['page_ids'][$email_page] . '}}' : $email_page,
        'email_subject' => isset($page['pg_comments_email_subject']) ? (string)$page['pg_comments_email_subject'] : '',
        'notify_email'  => ($site_email !== '' && $notify === $site_email) ? '{{site_email}}' : $notify,
    );
}

/**
 * The template's folders: every folder a page of the design is in, and the
 * folders above it up to the site's root folder (the root itself is not
 * one). $rows: folder id => array(folder_name, folder_parent,
 * folder_access_control_type). A parent comes before its children, the
 * order _pg_tpl_folders() needs; a folder right under the root has no
 * 'parent'.
 *
 * Returns array('folders' => key => definition, 'by_id' => folder id => key).
 */
function pg_dtc_folders($page_folder_ids, $rows, $root_id)
{
    $out = array('folders' => array(), 'by_id' => array());
    $root_id = (int)$root_id;
    $taken = array();
    foreach ((array)$page_folder_ids as $fid) {
        $chain = array();
        $cur = (int)$fid;
        while ($cur > 0 && $cur !== $root_id && isset($rows[$cur]) && !in_array($cur, $chain, true) && count($chain) < 50) {
            $chain[] = $cur;
            $cur = (int)$rows[$cur]['folder_parent'];
        }
        foreach (array_reverse($chain) as $id) {
            if (isset($out['by_id'][$id])) continue;
            $row = $rows[$id];
            $name = trim((string)$row['folder_name']);
            $key = pg_dtc_page_key($name, $taken);
            $taken[$key] = true;
            $access = isset($row['folder_access_control_type']) ? (string)$row['folder_access_control_type'] : '';
            $def = array(
                'name'   => $name !== '' ? $name : $key,
                'access' => in_array($access, array('public', 'private', 'registration'), true) ? $access : 'public',
            );
            $parent = (int)$row['folder_parent'];
            if ($parent !== $root_id && isset($out['by_id'][$parent])) $def['parent'] = $out['by_id'][$parent];
            $out['folders'][$key] = $def;
            $out['by_id'][$id] = $key;
        }
    }
    return $out;
}

// The ids of the shared components a tree places (props.sharedId), in the
// order they appear; the trees of those components are not followed.
function pg_dtc_tree_shared_ids($node, $depth = 0)
{
    $ids = array();
    if (!is_array($node) || $depth > 80) return $ids;
    if (isset($node['type']) && $node['type'] === 'shared_ref') {
        $sid = isset($node['props']['sharedId']) ? (int)$node['props']['sharedId'] : 0;
        if ($sid > 0) $ids[$sid] = $sid;
        return array_values($ids);
    }
    if (isset($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as $child) {
            foreach (pg_dtc_tree_shared_ids($child, $depth + 1) as $sid) $ids[$sid] = $sid;
        }
    }
    return array_values($ids);
}

// Whether a widget tree is still the empty placeholder a template writes
// for a widget the editor lays out (a root holding one empty loop area):
// such a widget goes into the template as 'tree' => 'starter'.
function pg_dtc_is_starter_tree($tree)
{
    if (!is_array($tree) || !isset($tree['type']) || $tree['type'] !== 'root') return false;
    if (!empty($tree['props']) || empty($tree['children']) || !is_array($tree['children']) || count($tree['children']) !== 1) return false;
    $la = reset($tree['children']);
    return is_array($la) && isset($la['type']) && $la['type'] === 'loop_area'
        && empty($la['props']) && empty($la['children']);
}

// Every id under a setting whose name holds $needle, at any depth.
function pg_dtc_collect_ids($arr, $needle, $parent_key = '', $depth = 0)
{
    $ids = array();
    if (!is_array($arr) || $depth > 20) return $ids;
    foreach ($arr as $k => $v) {
        $name = is_int($k) ? (string)$parent_key : (string)$k;
        if (is_array($v)) {
            foreach (pg_dtc_collect_ids($v, $needle, $name, $depth + 1) as $id) $ids[$id] = $id;
        } elseif (strpos($name, $needle) !== false && ($id = _pg_dtc_id_value($v)) > 0) {
            $ids[$id] = $id;
        }
    }
    return array_values($ids);
}

// ========================= EXPORT ================================================================

/**
 * Turn a visual design into a template and store it.
 *
 * Reads the design as it is saved - the pages on the server, not what an
 * open editor holds - with every shared component and system widget the
 * pages place (and the ones those place in turn), the folders the pages are
 * in, the page forms, the comment settings and the design's assets.
 *
 * $meta: 'name' (the design's name when empty), 'description'.
 *
 * Returns array('ok' => true, 'template' => array, 'row_id', 'template_id',
 * 'dropped' => number of placements of deleted components left out) or
 * array('ok' => false, 'error' => text).
 */
function pg_design_template_from_style($style_id, $meta, $user)
{
    if (!is_array($user) || empty($user['id']) || !isset($user['role']) || (int)$user['role'] > 1) {
        return array('ok' => false, 'error' => lang('You do not have access to add a design.'));
    }
    if (!pg_design_template_custom_ready() || !pg_multi_page_design_ready()) {
        return array('ok' => false, 'error' => lang('The database has not been upgraded yet. Run the software update first.'));
    }
    $style_id = (int)$style_id;
    $style = ($style_id > 0)
        ? db_item("SELECT * FROM style WHERE style_id = '$style_id' AND style_layout = 'visual_designer' LIMIT 1")
        : null;
    if (!is_array($style)) return array('ok' => false, 'error' => lang('The design could not be found.'));

    $meta = is_array($meta) ? $meta : array();
    $name = trim((string)(isset($meta['name']) ? $meta['name'] : ''));
    if ($name === '') $name = (string)$style['style_name'];
    $name = mb_substr($name, 0, 255);
    $description = trim((string)(isset($meta['description']) ? $meta['description'] : ''));

    // The pages, each with its tree; the home page takes its key first.
    $pages = array();
    foreach (pg_designer_load_pages($style_id, (string)$style['style_name']) as $p) {
        $tree = json_decode((string)$p['tree_json'], true);
        if (!is_array($tree)) continue;
        $p['tree'] = $tree;
        $pages[] = $p;
    }
    if (!$pages) return array('ok' => false, 'error' => lang('The design has no pages to make a template from.'));

    $order = array_keys($pages);
    usort($order, function ($a, $b) use ($pages) {
        if ($pages[$a]['page_home'] !== $pages[$b]['page_home']) return $pages[$b]['page_home'] - $pages[$a]['page_home'];
        return $a - $b;
    });
    $page_keys = array();
    $taken = array();
    $home_done = false;
    foreach ($order as $i) {
        $is_home = !$home_done && !empty($pages[$i]['page_home']);
        $key = pg_dtc_page_key($pages[$i]['page_name'], $taken, $is_home);
        if ($is_home) $home_done = true;
        $taken[$key] = true;
        $page_keys[$i] = $key;
    }

    $ctx = array(
        'page_urls'            => array(),
        'page_ids'             => array(),
        'widgets_by_sid'       => array(),
        'shared_by_sid'        => array(),
        'folders_by_id'        => array(),
        'contact_groups_by_id' => array(),
        'site_name'            => (defined('ORGANIZATION_NAME') && trim((string)ORGANIZATION_NAME) !== '') ? (string)ORGANIZATION_NAME : '',
        'site_email'           => (defined('EMAIL_ADDRESS') && trim((string)EMAIL_ADDRESS) !== '') ? (string)EMAIL_ADDRESS : '',
        'year'                 => date('Y'),
    );
    foreach ($pages as $i => $p) {
        // The address exactly as pg_design_template_prepare() writes it.
        $ctx['page_urls'][(defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . encode_url_path($p['page_name'])] = $page_keys[$i];
        $ctx['page_ids'][(int)$p['page_id']] = $page_keys[$i];
    }

    // The shared components and widgets: the ones the pages place, then the
    // ones those place, each row read once (a cycle ends there).
    $rows = array();
    $missing = array();
    $placed_by = array();
    $nested = array();
    $queue = array();
    foreach ($pages as $i => $p) {
        foreach (pg_dtc_tree_shared_ids($p['tree']) as $sid) {
            $placed_by[$sid][$page_keys[$i]] = true;
            $queue[] = $sid;
        }
    }
    while ($queue && count($rows) < 500) {
        $sid = (int)array_shift($queue);
        if (isset($rows[$sid]) || isset($missing[$sid])) continue;
        $row = db_item("SELECT id, name, tree_json, system_region_config FROM shared_components WHERE id = '$sid' LIMIT 1");
        $tree = is_array($row) ? json_decode((string)$row['tree_json'], true) : null;
        if (!is_array($tree)) {
            $missing[$sid] = true;
            continue;
        }
        $cfg = ((string)$row['system_region_config'] !== '') ? json_decode((string)$row['system_region_config'], true) : null;
        $rows[$sid] = array('name' => (string)$row['name'], 'tree' => $tree, 'config' => (is_array($cfg) && $cfg) ? $cfg : null);
        foreach (pg_dtc_tree_shared_ids($tree) as $child) {
            $nested[$child] = true;
            $queue[] = $child;
        }
    }
    $widget_keys = array();
    $shared_keys = array();
    foreach ($rows as $sid => $r) {
        if ($r['config'] !== null) {
            $key = pg_dtc_page_key($r['name'], $widget_keys);
            $widget_keys[$key] = true;
            $ctx['widgets_by_sid'][$sid] = $key;
        } else {
            $key = pg_dtc_page_key(preg_replace('/\s*\[\d+\]$/', '', $r['name']), $shared_keys);
            $shared_keys[$key] = true;
            $ctx['shared_by_sid'][$sid] = $key;
        }
    }

    // The page forms.
    $forms = array();
    if (function_exists('pg_cf_load_page_form_settings')) {
        foreach ($pages as $i => $p) {
            $fs = pg_cf_load_page_form_settings((int)$p['page_id']);
            if (is_array($fs) && !empty($fs['exists'])) $forms[$i] = $fs;
        }
    }

    // The contact groups the widget settings and the forms name.
    $group_ids = array();
    foreach ($rows as $r) {
        if ($r['config'] !== null) foreach (pg_dtc_collect_ids($r['config'], 'contact_group_id') as $gid) $group_ids[$gid] = $gid;
    }
    foreach ($forms as $fs) {
        foreach (pg_dtc_collect_ids($fs, 'contact_group_id') as $gid) $group_ids[$gid] = $gid;
    }
    $contact_groups = array();
    if ($group_ids) {
        $group_rows = db_items("SELECT id, name, description, email_subscription FROM contact_groups
                                WHERE id IN (" . implode(',', array_map('intval', $group_ids)) . ") ORDER BY id ASC");
        $group_taken = array();
        foreach ((array)$group_rows as $g) {
            $key = pg_dtc_page_key($g['name'], $group_taken);
            $group_taken[$key] = true;
            $ctx['contact_groups_by_id'][(int)$g['id']] = $key;
            $contact_groups[$key] = array('name' => (string)$g['name'], 'description' => (string)$g['description']);
            if (!empty($g['email_subscription'])) $contact_groups[$key]['subscription'] = 1;
        }
    }

    // The folders the pages are in, up to the root folder.
    $root = (int)db_value("SELECT folder_id FROM folder WHERE folder_parent = '0' ORDER BY folder_id LIMIT 1");
    $folder_rows = array();
    foreach ((array)db_items("SELECT folder_id, folder_name, folder_parent, folder_access_control_type FROM folder") as $f) {
        $folder_rows[(int)$f['folder_id']] = $f;
    }
    $page_folders = array();
    foreach ($pages as $p) $page_folders[] = (int)$p['page_folder'];
    $folders = pg_dtc_folders($page_folders, $folder_rows, $root);
    $ctx['folders_by_id'] = $folders['by_id'];

    $dropped = 0;
    $shop_widget_kinds = array('cart_link', 'shopping_cart', 'express_order', 'order_view', 'catalog_listing', 'catalog_item_view');
    $shop_page_kinds   = array('shopping_cart', 'express_order', 'order_view', 'catalog_listing', 'catalog_item_view');

    $widgets = array();
    $shared = array();
    foreach ($rows as $sid => $r) {
        $tree = pg_dtc_tokenize_tree($r['tree'], $ctx, $dropped);
        if (isset($ctx['widgets_by_sid'][$sid])) {
            $kind = isset($r['config']['regionType']) ? (string)$r['config']['regionType'] : '';
            $on = isset($placed_by[$sid]) ? array_keys($placed_by[$sid]) : array();
            $w = array(
                'page'   => (count($on) === 1 && empty($nested[$sid])) ? (string)$on[0] : '',
                'slug'   => $kind !== '' ? str_replace('_', '-', $kind) : 'widget',
            );
            if (in_array($kind, $shop_widget_kinds, true)) $w['requires'] = 'ecommerce';
            $w['config'] = pg_dtc_tokenize_config($r['config'], $ctx);
            $w['tree'] = pg_dtc_is_starter_tree($tree) ? 'starter' : $tree;
            if (is_array($w['tree'])) pg_designer_tree_objects($w['tree']);
            $widgets[$ctx['widgets_by_sid'][$sid]] = $w;
        } else {
            pg_designer_tree_objects($tree);
            $shared[$ctx['shared_by_sid'][$sid]] = array(
                'name' => preg_replace('/\s*\[\d+\]$/', '', $r['name']),
                'tree' => $tree,
            );
        }
    }

    $tpl_pages = array();
    foreach ($pages as $i => $p) {
        $key = $page_keys[$i];
        $entry = array(
            'key'              => $key,
            'name'             => (string)$p['page_name'],
            'title'            => (string)$p['page_title'],
            'meta_description' => (string)$p['page_meta_description'],
            'folder'           => isset($ctx['folders_by_id'][(int)$p['page_folder']]) ? $ctx['folders_by_id'][(int)$p['page_folder']] : '',
            'search'           => !empty($p['page_search']) ? 1 : 0,
            'sitemap'          => !empty($p['page_sitemap']) ? 1 : 0,
            'noindex'          => !empty($p['page_noindex']) ? 1 : 0,
        );
        foreach (pg_dtc_tree_shared_ids($p['tree']) as $sid) {
            $kind = isset($rows[$sid]['config']['regionType']) ? (string)$rows[$sid]['config']['regionType'] : '';
            if (in_array($kind, $shop_page_kinds, true)) {
                $entry['requires'] = 'ecommerce';
                break;
            }
        }
        $comments = pg_dtc_page_comments($p, $ctx);
        if ($comments !== null) $entry['comments'] = $comments;
        if (isset($forms[$i])) $entry['form'] = pg_dtc_tokenize_form($forms[$i], $ctx);
        $tree = pg_dtc_tokenize_tree($p['tree'], $ctx, $dropped);
        pg_designer_tree_objects($tree);
        $entry['tree'] = $tree;
        $tpl_pages[] = $entry;
    }

    $framework = pg_design_framework_key((pg_style_framework_ready() && isset($style['style_framework'])) ? $style['style_framework'] : '');
    $look_ready = function_exists('pg_design_look_ready') && pg_design_look_ready();
    $tpl = array(
        'name'           => $name,
        'version'        => '1.0.0',
        'framework'      => $framework,
        'description'    => $description,
        'icon'           => 'bi-person-workspace',
        'look'           => ($look_ready && isset($style['style_look'])) ? (string)$style['style_look'] : '',
        'palette'        => ($look_ready && isset($style['style_palette'])) ? (string)$style['style_palette'] : '',
        'highlights'     => array(),
        'folders'        => $folders['folders'],
        'contact_groups' => $contact_groups,
        'pages'          => $tpl_pages,
        'widgets'        => $widgets,
        'shared'         => $shared,
        'assets'         => array(
            'css'          => isset($style['style_custom_css']) ? (string)$style['style_custom_css'] : '',
            'js'           => isset($style['style_custom_js']) ? (string)$style['style_custom_js'] : '',
            'fonts'        => isset($style['style_custom_fonts']) ? (string)$style['style_custom_fonts'] : '',
            'head'         => isset($style['style_head']) ? (string)$style['style_head'] : '',
            'body_classes' => isset($style['additional_body_classes']) ? (string)$style['additional_body_classes'] : '',
        ),
    );

    $saved = pg_design_template_custom_save(array('template' => $tpl, 'source_style_id' => $style_id), $user);
    if (empty($saved['ok'])) return $saved;

    log_activity(lang(array('string' => 'design template ({var:1}) was created', 'vars' => array($name))),
        isset($user['username']) ? (string)$user['username'] : '');

    return array(
        'ok'          => true,
        'template'    => $tpl,
        'row_id'      => (int)$saved['row_id'],
        'template_id' => (string)$saved['template_id'],
        'dropped'     => $dropped,
    );
}
