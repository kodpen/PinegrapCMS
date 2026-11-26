<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * "New collection with pages" in the visual designer: the collection, its
 * list page and its detail page, made one at a time from the Pages window.
 * Each call makes one of the three - the system widget row and the page
 * that places it - so a stop half way leaves what was made and "Try again"
 * makes only what is missing. The widget layouts come from the editor,
 * built by the code that builds them when a widget is dropped
 * (assets/js/style_designer_collection.js); the page around the widget is
 * built here. Nothing new is stored: a collection is a designer form with
 * the collection flag (includes/fn/collections.php).
 *
 * Loaded by the designer endpoint's sub action collection_pages
 * (includes/panel/designer.php).
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
 * The widget kind each of the three things places.
 *
 * @return array what => regionType
 */
function pg_coll_pages_widget_kinds()
{
    return array('collection' => 'custom_form', 'list' => 'form_list_view', 'detail' => 'form_item_view');
}

/**
 * Checks one "make" request before anything is read or written. Pure.
 *
 * $req: what (collection, list, detail), title (the page's name),
 * name (its address), collection (the collection's name, for "collection"),
 * collection_page_id (for "list" and "detail"), widget_tree, widget_config
 * (JSON text).
 *
 * @param array $req
 * @return array ok, error, field (the field the error is about), plus the
 *               decoded 'tree' and 'config' when ok
 */
function pg_coll_pages_check($req)
{
    $fail = function ($error, $field) {
        return array('ok' => false, 'error' => $error, 'field' => $field);
    };
    $kinds = pg_coll_pages_widget_kinds();
    $what = isset($req['what']) && is_string($req['what']) ? $req['what'] : '';

    if (!isset($kinds[$what])) {
        return $fail(lang('Sorry, we could not accept your request.'), 'what');
    }

    $title = isset($req['title']) && is_string($req['title']) ? trim($req['title']) : '';
    $name = isset($req['name']) && is_string($req['name']) ? trim($req['name']) : '';

    if ($title === '') {
        return $fail(lang('Give the page a name.'), 'title');
    }

    if ($name === '' || mb_strlen($name, 'UTF-8') > 100) {
        return $fail(lang('Give the page an address.'), 'name');
    }

    if (preg_match('/[\/?#\\\\<>"%\s\x00-\x1f\x7f]/u', $name)) {
        return $fail(lang('An address cannot contain spaces, / ? # % \\ < > " or control characters.'), 'name');
    }

    if ($what === 'collection') {
        $coll = isset($req['collection']) && is_string($req['collection']) ? trim($req['collection']) : '';

        if ($coll === '') {
            return $fail(lang('Give the collection a name.'), 'collection');
        }
    } elseif (!isset($req['collection_page_id']) || (int) $req['collection_page_id'] <= 0) {
        return $fail(lang('Choose a collection.'), 'collection');
    }

    $tree = isset($req['widget_tree']) && is_string($req['widget_tree']) ? json_decode($req['widget_tree'], true) : null;
    $config = isset($req['widget_config']) && is_string($req['widget_config']) ? json_decode($req['widget_config'], true) : null;

    if (!is_array($tree) || !isset($tree['type']) || $tree['type'] !== 'root' || !is_array($config)) {
        return $fail(lang('Invalid tree data.'), 'widget_tree');
    }

    // The widget is the kind of the thing, bound to the collection made in
    // the step before (a list or a detail), or to its own page (the form).
    if (!isset($config['regionType']) || $config['regionType'] !== $kinds[$what]) {
        return $fail(lang('Invalid system region config.'), 'widget_config');
    }

    if ($what !== 'collection'
        && (!isset($config['custom_form_page_id']) || (int) $config['custom_form_page_id'] !== (int) $req['collection_page_id'])) {
        return $fail(lang('Invalid system region config.'), 'widget_config');
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'tree' => $tree, 'config' => $config);
}

/**
 * The page that places the widget: on a list page a heading with the
 * collection's name above it, on the others the widget alone. Pure.
 *
 * @param string $what    pg_coll_pages_widget_kinds() key
 * @param int    $sid     the widget's shared_components id
 * @param string $sname   the widget's name
 * @param string $heading the list page's heading
 * @return array the page's tree
 */
function pg_coll_pages_page_tree($what, $sid, $sname, $heading)
{
    $children = array();

    if ($what === 'list') {
        $children[] = array('_id' => 'sd_1', 'type' => 'container', 'props' => array('fluid' => false, 'cssClass' => 'pt-4'), 'children' => array(
            array('_id' => 'sd_2', 'type' => 'content', 'props' => array('contentType' => 'heading', 'tag' => 'h1', 'text' => (string) $heading, 'cssClass' => 'mb-0'), 'children' => array()),
        ));
    }

    $children[] = array('_id' => 'sd_3', 'type' => 'shared_ref', 'props' => array('sharedId' => (int) $sid, 'sharedName' => (string) $sname), 'children' => array());

    return array('_id' => 'root', 'type' => 'root', 'props' => array(), 'children' => $children);
}

/**
 * The fields the design save takes for a new page. Pure.
 *
 * @param string $name   the address
 * @param string $title  the page's name
 * @param int    $folder 0 for the site's root folder
 * @param array  $tree
 * @param bool   $draft  made as a draft, as "Add Page" makes a page
 * @return array
 */
function pg_coll_pages_page_fields($name, $title, $folder, $tree, $draft)
{
    return array(
        'page_id'               => 0,
        'page_name'             => (string) $name,
        'page_title'            => (string) $title,
        'page_folder'           => (int) $folder,
        'page_meta_description' => '',
        'page_search'           => 1,
        'page_search_keywords'  => '',
        'page_sitemap'          => 1,
        'page_home'             => 0,
        'page_noindex'          => 0,
        'page_nofollow'         => 0,
        'page_draft'            => $draft ? 1 : 0,
        'pg_comments'           => 0,
        'tree_json'             => pg_designer_tree_encode($tree),
    );
}

/**
 * The folder collections' pages are kept in, from the folders directly
 * under the top folder: the first named $name with private access that is
 * not in the recycle bin, else 0. Pure.
 *
 * @param array  $rows   folder_id, folder_name, folder_parent, folder_access_control_type
 * @param string $name   the folder's name (lang('Collections'))
 * @param int    $root   the top folder's id
 * @param array  $binned folder ids in the recycle bin
 * @return int
 */
function pg_coll_pages_folder_pick($rows, $name, $root, $binned)
{
    $found = 0;

    foreach ((array) $rows as $row) {
        $id = (int) $row['folder_id'];

        if ((int) $row['folder_parent'] !== (int) $root || (string) $row['folder_name'] !== (string) $name
            || (string) $row['folder_access_control_type'] !== 'private' || in_array($id, (array) $binned, true)) {
            continue;
        }

        if ($found === 0 || $id < $found) {
            $found = $id;
        }
    }

    return $found;
}

/**
 * The private folder "Collections" directly under the top folder, where a
 * collection made here keeps its page: visitors never open it, staff add the
 * items. Found by its name and access, made on first use the way the Drafts
 * folder is (pg_page_draft_folder_id()); nothing in config names it, so a
 * site whose language changed gets a second one, which harms nothing.
 *
 * @return int 0 when it could not be made
 */
function pg_coll_pages_folder_id()
{
    $root = (int) db_value("SELECT folder_id FROM folder WHERE folder_parent = '0' ORDER BY folder_id LIMIT 1");

    if ($root <= 0) {
        return 0;
    }

    $name = lang('Collections');
    $rows = db_items("SELECT folder_id, folder_name, folder_parent, folder_access_control_type FROM folder
        WHERE folder_parent = '$root' AND folder_name = '" . e($name) . "' AND folder_access_control_type = 'private'");
    $id = pg_coll_pages_folder_pick($rows, $name, $root, pg_recycle_bin_folder_ids());

    if ($id > 0) {
        return $id;
    }

    $level = (int) db_value("SELECT folder_level FROM folder WHERE folder_id = '$root'");
    db("INSERT INTO folder (folder_name, folder_parent, folder_level, folder_order, folder_access_control_type,
            folder_archived, folder_style, mobile_style_id, folder_timestamp, folder_user)
        VALUES ('" . e($name) . "', '$root', '" . ($level + 1) . "', '9997', 'private', '0', '0', '0', UNIX_TIMESTAMP(), '" . (defined('USER_ID') ? (int) USER_ID : 0) . "')");
    $id = (int) mysqli_insert_id(db::$con);

    if ($id > 0) {
        log_activity(lang(array('string' => 'folder ({var:1}) was created', 'vars' => array($name))), isset($_SESSION['sessionusername']) ? $_SESSION['sessionusername'] : '');
    }

    return max(0, $id);
}

/**
 * Every file control of a form's tree uploads into $folder: the folder the
 * list and detail pages return to, never the collection's private one,
 * where an upload stays closed to the readers of those pages. Pure.
 *
 * @param array $tree
 * @param int   $folder
 * @return array
 */
function pg_coll_pages_upload_folder($tree, $folder)
{
    if (isset($tree['props']['_attrs']) && is_array($tree['props']['_attrs'])) {
        foreach ($tree['props']['_attrs'] as $attr) {
            if (isset($attr['name'], $attr['value']) && $attr['name'] === 'type' && $attr['value'] === 'file') {
                $cf = (isset($tree['props']['_cf']) && is_array($tree['props']['_cf'])) ? $tree['props']['_cf'] : array();
                $cf['upload_folder_id'] = (int) $folder;
                $tree['props']['_cf'] = $cf;
            }
        }
    }

    if (isset($tree['children']) && is_array($tree['children'])) {
        foreach ($tree['children'] as $i => $child) {
            if (is_array($child)) {
                $tree['children'][$i] = pg_coll_pages_upload_folder($child, $folder);
            }
        }
    }

    return $tree;
}

/**
 * The site's collections, for "One that is there": each with its fields,
 * its items and the pages that list or show it.
 *
 * @return array rows: page_id, name, fields (name, label, type, kind,
 *               rss_field, wysiwyg, reference_page_id), items, pages (the
 *               names of the pages listing or showing it)
 */
function pg_coll_pages_collections()
{
    if (!pg_coll_ready()) {
        return array();
    }

    $rows = db_items(
        "SELECT page.page_id, page.page_name, custom_form_pages.form_name
            FROM custom_form_pages
            INNER JOIN page ON page.page_id = custom_form_pages.page_id
            WHERE custom_form_pages.collection = 1 AND " . pg_form_page_sql('page') . pg_designer_not_binned_sql('page.page_folder') . "
            ORDER BY custom_form_pages.form_name ASC, page.page_name ASC");

    $out = array();

    foreach ((array) $rows as $row) {
        $pid = (int) $row['page_id'];
        $fields = array();

        foreach (pg_cf_load_page_fields($pid) as $f) {
            if ((string) $f['type'] === 'information') {
                continue;
            }

            $fields[] = array(
                'name' => (string) $f['name'], 'label' => (string) $f['label'], 'type' => (string) $f['type'],
                'kind' => isset($f['kind']) ? (string) $f['kind'] : '', 'rss_field' => (string) $f['rss_field'],
                'wysiwyg' => !empty($f['wysiwyg']) ? 1 : 0,
                'reference_page_id' => isset($f['reference_page_id']) ? (int) $f['reference_page_id'] : 0,
            );
        }

        $shows = function ($cfg) use ($pid) {
            return isset($cfg['custom_form_page_id']) && (int) $cfg['custom_form_page_id'] === $pid;
        };
        $pages = array();

        foreach (array('form_list_view', 'form_item_view') as $kind) {
            foreach (pg_sw_widget_pages($kind, $shows) as $p) {
                $pages[(int) $p['page_id']] = ((string) $p['page_title'] !== '') ? (string) $p['page_title'] : (string) $p['page_name'];
            }
        }

        $out[] = array(
            'page_id' => $pid,
            'name'    => ((string) $row['form_name'] !== '') ? (string) $row['form_name'] : (string) $row['page_name'],
            'fields'  => $fields,
            'items'   => (int) db_value("SELECT COUNT(*) FROM forms WHERE page_id = '$pid'"),
            'pages'   => array_values($pages),
        );
    }

    return $out;
}

/**
 * Which of the addresses asked for are taken, and by what.
 *
 * @param array $names addresses
 * @return array address => the page's name, or '' when it is not a page
 */
function pg_coll_pages_taken($names)
{
    $out = array();

    foreach (array_slice(array_values((array) $names), 0, 3) as $name) {
        $name = is_string($name) ? trim($name) : '';

        if ($name === '') {
            continue;
        }

        $page = db_item("SELECT page_name, page_title FROM page WHERE page_name = '" . e($name) . "' LIMIT 1");

        if ($page) {
            $out[$name] = ((string) $page['page_title'] !== '') ? (string) $page['page_title'] : (string) $page['page_name'];
        } elseif (pg_designer_page_name_taken_elsewhere($name) || pg_tr_is_reserved_name($name)) {
            $out[$name] = '';
        }
    }

    return $out;
}

/**
 * Makes one of the three: the widget row, then the page that places it,
 * through the design save; for the collection its form, with the
 * collection flag. A page the save refuses takes its widget row back with
 * it. A detail page made for a list made just before becomes that list's
 * detail page.
 *
 * @param array $req  pg_coll_pages_check() input, plus style_id, draft,
 *                    list_widget_id (for "detail")
 * @param array $user
 * @return array ok, error, field, page_id, widget_id, name (the address)
 */
function pg_coll_pages_make($req, $user)
{
    $check = pg_coll_pages_check($req);

    if (!$check['ok']) {
        return $check;
    }

    $what = (string) $req['what'];
    $name = trim((string) $req['name']);
    $title = trim((string) $req['title']);
    $fail = function ($error, $field) {
        return array('ok' => false, 'error' => $error, 'field' => $field);
    };

    // The collection's page is named by the editor, not typed: another
    // design's page of that address moves it on to the next free one.
    for ($n = 2; ($what === 'collection') && pg_coll_pages_taken(array($name)) && ($n < 50); $n++) {
        $name = trim((string) $req['name']) . '-' . $n;
    }

    if (pg_coll_pages_taken(array($name))) {
        return $fail(lang(array('string' => 'The address "{var:1}" is taken.', 'vars' => $name)), 'name');
    }

    if ($what === 'collection') {
        $coll = trim((string) $req['collection']);

        foreach (pg_coll_pages_collections() as $c) {
            if (mb_strtolower($c['name'], 'UTF-8') === mb_strtolower($coll, 'UTF-8')) {
                return $fail(lang(array('string' => 'A collection named "{var:1}" is there already.', 'vars' => $coll)), 'collection');
            }
        }
    } else {
        $cid = (int) $req['collection_page_id'];

        if (!pg_coll_ready() || !(int) db_value("SELECT collection FROM custom_form_pages WHERE page_id = '$cid' LIMIT 1")) {
            return $fail(lang('The collection could not be found.'), 'collection');
        }
    }

    $style_id = isset($req['style_id']) ? (int) $req['style_id'] : 0;
    // The collection's page is closed to visitors in the Collections folder
    // and is no draft: it is never on the site, and staff add items at
    // once. The two pages are drafts where "Add Page" makes drafts, in the
    // folder it gives (0: the top folder), where the uploads go as well.
    $folder = 0;
    $draft = !empty($req['draft']);

    if ($what === 'collection') {
        $folder = pg_coll_pages_folder_id();

        if ($folder <= 0) {
            return $fail(lang('The operation failed.'), '');
        }

        $draft = false;
        $check['tree'] = pg_coll_pages_upload_folder($check['tree'], (int) db_value("SELECT folder_id FROM folder WHERE folder_parent = '0' ORDER BY folder_id LIMIT 1"));
    }

    $sname = _sc_unique_name($name . '-' . str_replace('_', '-', $check['config']['regionType']) . '-widget');
    $now = time();
    db("INSERT INTO shared_components (name, description, tree_json, system_region_config, category, created_by, created_at, updated_at)
        VALUES ('" . e($sname) . "', '', '" . e(pg_designer_tree_encode($check['tree'])) . "', '" . e(json_encode($check['config'])) . "', '', '" . (int) $user['id'] . "', '$now', '$now')");
    $sid = (int) mysqli_insert_id(db::$con);

    if ($sid <= 0) {
        return $fail(lang('The operation failed.'), '');
    }

    $tree = pg_coll_pages_page_tree($what, $sid, $sname, $title);
    $fields = pg_coll_pages_page_fields($name, $title, $folder, $tree, $draft);
    $saved = pg_designer_save_page($style_id, $fields, $user, false);

    if (empty($saved['ok']) || (int) $saved['page_id'] <= 0) {
        db("DELETE FROM shared_components WHERE id = '$sid' LIMIT 1");

        return $fail(implode(' ', (array) $saved['errors']), 'name');
    }

    $page_id = (int) $saved['page_id'];
    pg_audit_record('create', 'shared_components', $sid);

    if ($what === 'collection') {
        pg_cf_reconcile_page_form($page_id, (int) $user['id'], array('form_name' => trim((string) $req['collection']), 'collection' => 1));
    }

    if ($what === 'detail' && !empty($req['list_widget_id'])) {
        pg_coll_pages_link_detail((int) $req['list_widget_id'], (int) $req['collection_page_id'], $page_id);
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'page_id' => $page_id, 'widget_id' => $sid, 'name' => $name);
}

/**
 * Makes a detail page the detail page of a list of the same collection.
 *
 * @param int $list_sid   the list widget's row
 * @param int $collection the collection's page id
 * @param int $detail     the detail page's id
 * @return bool written
 */
function pg_coll_pages_link_detail($list_sid, $collection, $detail)
{
    $cfg = json_decode((string) db_value("SELECT system_region_config FROM shared_components WHERE id = '" . (int) $list_sid . "' LIMIT 1"), true);

    if (!is_array($cfg) || !isset($cfg['regionType']) || $cfg['regionType'] !== 'form_list_view'
        || !isset($cfg['custom_form_page_id']) || (int) $cfg['custom_form_page_id'] !== (int) $collection) {
        return false;
    }

    $cfg['detail_page_id'] = (int) $detail;
    db("UPDATE shared_components SET system_region_config = '" . e(json_encode($cfg)) . "', updated_at = '" . time() . "' WHERE id = '" . (int) $list_sid . "' LIMIT 1");

    return true;
}

/**
 * The designer endpoint's sub action collection_pages: op "list" (the
 * collections), "taken" (which addresses are taken), "make" (one thing).
 * Designers only, as adding a page.
 *
 * @param array $request
 * @param array $user
 * @return array the answer
 */
function pg_coll_pages_panel($request, $user)
{
    $op = isset($request['op']) ? (string) $request['op'] : '';

    if ($op === 'list') {
        return array('status' => 'success', 'collections' => pg_coll_pages_collections());
    }

    if ($op === 'taken') {
        return array('status' => 'success', 'taken' => (object) pg_coll_pages_taken(isset($request['names']) ? $request['names'] : array()));
    }

    if ($op === 'make') {
        $res = pg_coll_pages_make($request, $user);

        return $res['ok']
            ? array('status' => 'success', 'page_id' => $res['page_id'], 'widget_id' => $res['widget_id'], 'name' => $res['name'])
            : array('status' => 'error', 'message' => $res['error'], 'field' => $res['field']);
    }

    return array('status' => 'error', 'message' => lang('Sorry, we could not accept your request.'));
}
