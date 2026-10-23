<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Panel actions - the visual designer's shared components.
 *
 *   shared_component  list, load, save, rename and delete shared components
 *
 * Called by pg_panel_dispatch() (includes/panel/actions.php); see that file
 * for the contract.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_PANEL_ACTIONS')) {
    exit;
}

function pg_panel_shared_component($request, $action)
{
    // SHARED COMPONENTS
    // Internal visual-designer endpoint — session + token auth, admin/designer only.
    // shared_ref node: { type:'shared_ref', props:{ sharedId:N, sharedName:'...' }, children:[] }

    // Kept global: helpers read the session user through `global $user`;
    // see includes/panel/actions.php.
    global $user;

    validate_token();
    $user = validate_user();

    // Shared components and system widgets belong to the design: their
    // tree is used by every page that references them, so an edit here
    // is never an edit to "this page". WRITING is administrator (0) and
    // designer (1) only.
    //
    // The check read `role != 0 && role != 2`, written against the old
    // role table where 2 was thought to be the designer. It let MANAGERS
    // rewrite shared components and refused DESIGNERS — the two roles it
    // exists to tell apart.
    //
    // READING stays open to anyone who may open the editor. A page that
    // carries a widget cannot be drawn without the widget's tree: closing
    // the read left a content-level operator looking at a two-line page
    // where the whole layout used to be.
    //
    // The one write below role 1 is `content_update`: a component a designer
    // opened to content editors, saved by an operator it names, under the
    // page's merge (includes/designer_access.php checks both).
    $sub = isset($request['sub_action']) ? $request['sub_action'] : '';
    $_sc_read_only = array('list', 'usage_all', 'prefetch', 'get',
                           'list_custom_forms', 'list_form_item_view_pages', 'list_product_groups',
                           'list_calendars', 'list_calendar_event_pages');
    require_once(PG_FUNCTIONS_DIR . '/includes/designer_access.php');
    if (((int)$user['role'] > 1) && !in_array($sub, $_sc_read_only, true) && ($sub !== 'content_update')) {
        respond(array('status' => 'error', 'message' => lang('Permission denied.')));
    }

    // Defensive: ensure shared_components table exists (migration may not have been run).
    // Returns a clear JSON error instead of a cryptic HTML dump / 500.
    $_sc_check = @mysqli_query(db::$con, "SHOW TABLES LIKE 'shared_components'");
    if (!$_sc_check || mysqli_num_rows($_sc_check) === 0) {
        respond(array(
            'status'  => 'error',
            'message' => 'shared_components table is missing — please run the software upgrade at install/index.php',
        ));
    }

    switch ($sub) {

        // ── LIST ─────────────────────────────────────────────────────────
        case 'list':
            // Returns BOTH regular shared components and system widgets.
            // Each row carries `is_system_widget` (1/0) so the JS can split them
            // into the "Ortak" tab vs. the "Sistem" tab.
            $rows = db_items(
                "SELECT id, name, description, category, updated_at,
                            (system_region_config IS NOT NULL AND system_region_config != '') AS is_system_widget,
                            system_region_config
                     FROM shared_components
                     ORDER BY name ASC"
            );
            respond(array('status' => 'success', 'items' => _sc_content_flags($rows, $user)));
            break;

        // ── USAGE ALL ────────────────────────────────────────────────────
        // For every shared component, count how many styles reference it
        // (via `"sharedId":<id>` in style_tree_json) and return the list of
        // referencing style names. Used by the "Ortak" palette tab to show
        // a "N stilde" badge + pre-populate the delete confirmation.
        case 'usage_all':
            respond(array('status' => 'success', 'usage' => pg_shared_component_usage(null)));
            break;

        // ── PREFETCH ─────────────────────────────────────────────────────
        // Returns id + name + tree_json for a specific set of shared component ids.
        // Called by style_designer.js at init time to warm _sharedCache.
        case 'prefetch':
            $sc_ids_raw = isset($request['ids']) ? $request['ids'] : array();
            $sc_ids     = array();
            if (is_array($sc_ids_raw)) {
                foreach ($sc_ids_raw as $raw_id) {
                    $id = (int)$raw_id;
                    if ($id > 0) $sc_ids[] = $id;
                }
            }
            if (empty($sc_ids)) {
                respond(array('status' => 'success', 'items' => array()));
            }
            $sc_ids_str = implode(',', $sc_ids);
            $rows = db_items(
                "SELECT id, name, tree_json, system_region_config
                     FROM shared_components
                     WHERE id IN ($sc_ids_str)"
            );
            respond(array('status' => 'success', 'items' => _sc_content_flags($rows ? $rows : array(), $user),
                          'content_ready' => pg_shared_component_content_ready() ? 1 : 0));
            break;

        // ── GET ──────────────────────────────────────────────────────────
        case 'get':
            $sc_id = isset($request['id']) ? (int)$request['id'] : 0;
            if ($sc_id <= 0) {
                respond(array('status' => 'error', 'message' => lang('Invalid ID.')));
            }
            $row = db_items(
                "SELECT id, name, description, category, tree_json, system_region_config, updated_at
                     FROM shared_components
                     WHERE id = '$sc_id' LIMIT 1"
            );
            if (!$row) {
                respond(array('status' => 'error', 'message' => lang('Not found.')));
            }
            $row = _sc_content_flags($row, $user);
            respond(array('status' => 'success', 'item' => $row[0]));
            break;

        // ── CREATE ───────────────────────────────────────────────────────
        case 'create':
            $sc_name     = isset($request['name'])               ? trim($request['name'])               : '';
            $sc_tree     = isset($request['tree_json'])           ? $request['tree_json']                : '';
            $sc_desc     = isset($request['description'])         ? trim($request['description'])        : '';
            $sc_src_cfg  = isset($request['system_region_config'])? $request['system_region_config']    : null;
            if ($sc_name === '') {
                respond(array('status' => 'error', 'message' => lang(array('string' => '{var:1} is required', 'vars' => lang('Name')))));
            }
            if ($sc_tree === '' || json_decode($sc_tree) === null) {
                respond(array('status' => 'error', 'message' => lang('Invalid tree data.')));
            }
            // Validate system_region_config when provided
            if ($sc_src_cfg !== null && ($sc_src_cfg === '' || json_decode($sc_src_cfg) === null)) {
                respond(array('status' => 'error', 'message' => lang('Invalid system region config.')));
            }
            // Auto-suffix if name taken: "Hero [1]", "Hero [2]", ...
            $sc_name = _sc_unique_name($sc_name);
            $sc_now  = time();
            $sc_cfg_sql = ($sc_src_cfg !== null) ? "'" . e($sc_src_cfg) . "'" : 'NULL';
            // Only the import's marker is taken ("import:<project>"): it
            // lets a design that is never published discard the rows its
            // import made (pg_design_template_discard()).
            $sc_cat = isset($request['category']) && is_string($request['category']) ? trim($request['category']) : '';
            if (strpos($sc_cat, 'import:') !== 0) $sc_cat = '';
            $sc_cat = mb_substr($sc_cat, 0, 100);
            db("INSERT INTO shared_components (name, description, tree_json, system_region_config, category, created_by, created_at, updated_at)
                    VALUES ('" . e($sc_name) . "', '" . e($sc_desc) . "', '" . e($sc_tree) . "',
                            $sc_cfg_sql, '" . e($sc_cat) . "', '" . (int)$user['id'] . "', '$sc_now', '$sc_now')");
            $sc_new_id = mysqli_insert_id(db::$con);
            respond(array('status' => 'success', 'id' => $sc_new_id, 'name' => $sc_name));
            break;

        // ── UPDATE (tree_json) ────────────────────────────────────────────
        case 'update':
            $sc_id      = isset($request['id'])                   ? (int)$request['id']               : 0;
            $sc_tree    = isset($request['tree_json'])             ? $request['tree_json']              : '';
            $sc_src_cfg = isset($request['system_region_config'])  ? $request['system_region_config']  : false;
            if ($sc_id <= 0) {
                respond(array('status' => 'error', 'message' => lang('Invalid ID.')));
            }
            if ($sc_tree === '' || json_decode($sc_tree) === null) {
                respond(array('status' => 'error', 'message' => lang('Invalid tree data.')));
            }
            // The tree may place other shared components, never this one —
            // directly or through them: that loop could not be drawn.
            $sc_loop = pg_shared_component_cycle(array($sc_id), array($sc_id => json_decode($sc_tree, true)));
            if (!empty($sc_loop)) {
                respond(array('status' => 'error', 'message' => pg_shared_component_loop_message($sc_loop)));
            }
            // Validate system_region_config when provided
            if ($sc_src_cfg !== false && $sc_src_cfg !== null && ($sc_src_cfg === '' || json_decode($sc_src_cfg) === null)) {
                respond(array('status' => 'error', 'message' => lang('Invalid system region config.')));
            }
            $sc_cfg_clause = '';
            if ($sc_src_cfg !== false) {
                $sc_cfg_clause = ($sc_src_cfg === null)
                    ? ', system_region_config = NULL'
                    : ", system_region_config = '" . e($sc_src_cfg) . "'";
            }
            db("UPDATE shared_components
                    SET tree_json = '" . e($sc_tree) . "'" . $sc_cfg_clause . ", updated_at = " . time() . "
                    WHERE id = '$sc_id' LIMIT 1");
            if (function_exists('pg_tr_owner_saved')) {
                pg_tr_owner_saved('shared', $sc_id);
            }
            respond(array('status' => 'success'));
            break;

        // ── CONTENT UPDATE (tree_json, content level) ────────────────────
        // A component a designer opened to content editors, saved by one of
        // them. The submitted tree is not written: the stored tree is walked
        // and only text, an image's address and the inside of editable areas
        // are taken (pg_designer_merge_restricted_tree(), the page's gate).
        // Nested components and code nodes stay as stored. The merged tree is
        // returned so the editor keeps what was actually written.
        case 'content_update':
            $sc_id   = isset($request['id'])        ? (int)$request['id']                 : 0;
            $sc_tree = isset($request['tree_json']) ? json_decode((string)$request['tree_json'], true) : null;
            if ($sc_id <= 0) {
                respond(array('status' => 'error', 'message' => lang('Invalid ID.')));
            }
            if (!is_array($sc_tree)) {
                respond(array('status' => 'error', 'message' => lang('Invalid tree data.')));
            }
            $sc_may = pg_shared_component_content_editable_ids($user, array($sc_id));
            if (empty($sc_may[$sc_id])) {
                respond(array('status' => 'error', 'message' => lang('You do not have access to edit this shared component.')));
            }
            $sc_stored = json_decode((string)db_value("SELECT tree_json FROM shared_components WHERE id = '$sc_id' LIMIT 1"), true);
            if (!is_array($sc_stored)) {
                respond(array('status' => 'error', 'message' => lang('Not found.')));
            }
            $sc_merged = pg_designer_tree_encode(pg_designer_merge_restricted_tree($sc_stored, $sc_tree));
            db("UPDATE shared_components
                    SET tree_json = '" . e($sc_merged) . "', updated_at = " . time() . "
                    WHERE id = '$sc_id' LIMIT 1");
            if (function_exists('pg_tr_owner_saved')) {
                pg_tr_owner_saved('shared', $sc_id);
            }
            respond(array('status' => 'success', 'tree_json' => $sc_merged));
            break;

        // ── SET CONTENT EDITABLE (designer) ──────────────────────────────
        // Opens a component to content editors or closes it again. Closing
        // keeps the users granted it, so opening it later needs no new grants.
        case 'set_content_editable':
            $sc_id = isset($request['id']) ? (int)$request['id'] : 0;
            $sc_on = !empty($request['content_editable']) ? 1 : 0;
            if ($sc_id <= 0) {
                respond(array('status' => 'error', 'message' => lang('Invalid ID.')));
            }
            if (!pg_shared_component_content_ready()) {
                respond(array('status' => 'error', 'message' => lang('Please run the software upgrade to open shared components to content editors.')));
            }
            $sc_row = db_item("SELECT id, name, (system_region_config IS NOT NULL AND system_region_config != '') AS is_widget
                                   FROM shared_components WHERE id = '$sc_id' LIMIT 1");
            if (!is_array($sc_row)) {
                respond(array('status' => 'error', 'message' => lang('Not found.')));
            }
            if ($sc_on && !empty($sc_row['is_widget'])) {
                respond(array('status' => 'error', 'message' => lang('A system widget cannot be opened to content editors.')));
            }
            db("UPDATE shared_components SET content_editable = '$sc_on' WHERE id = '$sc_id' LIMIT 1");
            log_activity($sc_on
                ? lang(array('string' => 'shared component ({var:1}) was opened to content editors', 'vars' => (string)$sc_row['name']))
                : lang(array('string' => 'shared component ({var:1}) was closed to content editors', 'vars' => (string)$sc_row['name'])),
                isset($_SESSION['sessionusername']) ? $_SESSION['sessionusername'] : '');
            respond(array('status' => 'success', 'content_editable' => $sc_on));
            break;

        // ── UPDATE CONFIG (system_region_config only) ─────────────────────
        // Called by Visual Pinegrap Editor when compiled templates change but the
        // visual tree has not been edited (avoids a full tree re-save round-trip).
        case 'update_config':
            $sc_id      = isset($request['id'])                   ? (int)$request['id']               : 0;
            $sc_src_cfg = isset($request['system_region_config'])  ? $request['system_region_config']  : null;
            if ($sc_id <= 0) {
                respond(array('status' => 'error', 'message' => lang('Invalid ID.')));
            }
            if ($sc_src_cfg !== null && ($sc_src_cfg === '' || json_decode($sc_src_cfg) === null)) {
                respond(array('status' => 'error', 'message' => lang('Invalid system region config.')));
            }
            $sc_cfg_val = ($sc_src_cfg === null) ? 'NULL' : "'" . e($sc_src_cfg) . "'";
            db("UPDATE shared_components
                    SET system_region_config = $sc_cfg_val, updated_at = " . time() . "
                    WHERE id = '$sc_id' LIMIT 1");
            respond(array('status' => 'success'));
            break;

        // ── RENAME ───────────────────────────────────────────────────────
        case 'rename':
            $sc_id   = isset($request['id'])   ? (int)$request['id']   : 0;
            $sc_name = isset($request['name'])  ? trim($request['name']) : '';
            if ($sc_id <= 0) {
                respond(array('status' => 'error', 'message' => lang('Invalid ID.')));
            }
            if ($sc_name === '') {
                respond(array('status' => 'error', 'message' => lang(array('string' => '{var:1} is required', 'vars' => lang('Name')))));
            }
            // Auto-suffix, but skip our own current name to allow no-op rename
            $sc_current = db_value("SELECT name FROM shared_components WHERE id = '$sc_id' LIMIT 1");
            if ($sc_current !== $sc_name) {
                $sc_name = _sc_unique_name($sc_name);
            }
            db("UPDATE shared_components
                    SET name = '" . e($sc_name) . "', updated_at = " . time() . "
                    WHERE id = '$sc_id' LIMIT 1");
            respond(array('status' => 'success', 'name' => $sc_name));
            break;

        // ── DELETE ───────────────────────────────────────────────────────
        // Phase 1: no dependency check; Phase 2 will add usage guard + force flag.
        case 'delete':
            $sc_id = isset($request['id']) ? (int)$request['id'] : 0;
            if ($sc_id <= 0) {
                respond(array('status' => 'error', 'message' => lang('Invalid ID.')));
            }
            db("DELETE FROM shared_components WHERE id = '$sc_id' LIMIT 1");
            _sc_delete_grants(array($sc_id));
            respond(array('status' => 'success'));
            break;

        // ── DELETE MANY ──────────────────────────────────────────────────
        // The palette's bulk delete. A row a saved page still places is
        // kept unless the request says `force` — the editor asks for that
        // only after the operator typed the confirmation — so a page saved
        // from another tab since the palette loaded is never broken by
        // accident. Answers with what went and what stayed.
        case 'delete_many':
            $sc_ids = array();
            foreach ((array)(isset($request['ids']) ? $request['ids'] : array()) as $sc_raw) {
                $sc_one = (int)$sc_raw;
                if ($sc_one > 0) $sc_ids[$sc_one] = $sc_one;
            }
            if (empty($sc_ids)) {
                respond(array('status' => 'error', 'message' => lang('Nothing is selected.')));
            }
            if (count($sc_ids) > 500) {
                respond(array('status' => 'error', 'message' => lang('Select at most 500 at a time.')));
            }
            $sc_force = !empty($request['force']);
            $sc_usage = pg_shared_component_usage(array_values($sc_ids));
            $sc_names = array();
            $sc_rows  = db_items("SELECT id, name FROM shared_components WHERE id IN (" . implode(',', $sc_ids) . ")");
            foreach ((is_array($sc_rows) ? $sc_rows : array()) as $sc_row) $sc_names[(int)$sc_row['id']] = (string)$sc_row['name'];
            $sc_deleted = array();
            $sc_kept    = array();
            foreach ($sc_ids as $sc_one) {
                if (!isset($sc_names[$sc_one])) continue;   // already gone
                if (!$sc_force && !empty($sc_usage[$sc_one])) {
                    $sc_kept[] = array('id' => $sc_one, 'name' => $sc_names[$sc_one], 'usage' => $sc_usage[$sc_one]);
                    continue;
                }
                $sc_deleted[] = $sc_one;
            }
            if (!empty($sc_deleted)) {
                db("DELETE FROM shared_components WHERE id IN (" . implode(',', $sc_deleted) . ")");
                _sc_delete_grants($sc_deleted);
                $sc_list = array();
                foreach ($sc_deleted as $sc_one) $sc_list[] = $sc_names[$sc_one];
                log_activity(lang(array(
                    'string' => '{var:1} shared components and system widgets were deleted: {var:2}',
                    'vars'   => array(count($sc_deleted), implode(', ', $sc_list)),
                )), isset($_SESSION['sessionusername']) ? $_SESSION['sessionusername'] : '');
            }
            respond(array('status' => 'success', 'deleted' => $sc_deleted, 'kept' => $sc_kept));
            break;

        // ── LIST CUSTOM FORMS ───────────────────────────────────────────
        // Returns every page with page_type='custom form'. Used by the system widget
        // props panel to populate the "which form's submissions to show?" dropdown.
        // JOINs custom_form_pages for form_name (the user-friendly label) — the
        // canonical FK is page.page_id, and custom_form_pages.page_id maps 1:1.
        case 'list_custom_forms':
            $sc_form_rows = db_items(
                "SELECT
                        page.page_id,
                        page.page_name,
                        page.page_title,
                        page.page_folder,
                        custom_form_pages.form_name
                     FROM page
                     LEFT JOIN custom_form_pages ON custom_form_pages.page_id = page.page_id
                     WHERE " . pg_form_page_sql('page') . "
                     ORDER BY custom_form_pages.form_name ASC, page.page_name ASC"
            );
            respond(array('status' => 'success', 'forms' => _sc_flag_binned_pages($sc_form_rows)));
            break;

        // ── LIST FORM ITEM VIEW PAGES ───────────────────────────────────
        // Returns pages with page_type='form item view'. Used by the
        // system-widget props panel to populate the "detail page" dropdown
        // (each list row's ^^__detail_url^^ token will link to one).
        //
        // When `form_id` is supplied, the result is filtered to ONLY the
        // form-item-view pages whose `custom_form_page_id` matches — i.e.
        // the detail pages that actually know how to render submissions of
        // *that* form. Without this filter, users could pick a detail page
        // bound to a different form, and the detail SQL
        //   WHERE forms.page_id = $custom_form_page_id AND reference_code = ?
        // would silently 404 because the page_id check excludes the row.
        //
        // Backwards compat: if `form_id` is missing or 0, return everything
        // (preserves existing callers).
        case 'list_form_item_view_pages':
            // The custom form's page id, under either name list_form_fields
            // also takes.
            $sc_iv_form_id = _sc_custom_form_page_id($request);
            if ($sc_iv_form_id > 0) {
                $sc_iv_rows = db_items(
                    "SELECT page.page_id, page.page_name, page.page_title
                         FROM page
                         INNER JOIN form_item_view_pages
                                 ON form_item_view_pages.page_id = page.page_id
                                AND form_item_view_pages.collection = 'a'
                                AND form_item_view_pages.custom_form_page_id = '$sc_iv_form_id'
                         WHERE page.page_type = 'form item view'" . pg_designer_not_binned_sql('page.page_folder') . "
                         ORDER BY page.page_title ASC, page.page_name ASC"
                );
            } else {
                $sc_iv_rows = db_items(
                    "SELECT page_id, page_name, page_title
                         FROM page
                         WHERE page_type = 'form item view'" . pg_designer_not_binned_sql('page.page_folder') . "
                         ORDER BY page_title ASC, page_name ASC"
                );
            }
            // Pages built in the visual editor show a record through a
            // form_item_view widget bound to the same form.
            $sc_iv_merged = array();
            foreach ((array)$sc_iv_rows as $sc_iv_p) $sc_iv_merged[(int)$sc_iv_p['page_id']] = $sc_iv_p;
            foreach (pg_sw_widget_pages('form_item_view', function ($cfg) use ($sc_iv_form_id) {
                return $sc_iv_form_id <= 0 || (int)(isset($cfg['custom_form_page_id']) ? $cfg['custom_form_page_id'] : 0) === $sc_iv_form_id;
            }) as $sc_iv_p) $sc_iv_merged[(int)$sc_iv_p['page_id']] = $sc_iv_p;
            respond(array('status' => 'success', 'pages' => array_values($sc_iv_merged)));
            break;

        // ── LIST CALENDARS ──────────────────────────────────────────────
        // The calendars a calendar widget can show — its settings panel
        // lists them as checkboxes.
        case 'list_calendars':
            $sc_cal_rows = db_items("SELECT id, name FROM calendars ORDER BY name ASC");
            respond(array('status' => 'success', 'calendars' => is_array($sc_cal_rows) ? $sc_cal_rows : array()));
            break;

        // ── LIST CALENDAR EVENT PAGES ───────────────────────────────────
        // Where a calendar widget's events can link: legacy Calendar Event
        // View pages and pages carrying a calendar_event_view widget.
        case 'list_calendar_event_pages':
            $sc_ce_merged = array();
            foreach ((array)db_items(
                "SELECT page_id, page_name, page_title FROM page
                     WHERE page_type = 'calendar event view'" . pg_designer_not_binned_sql('page.page_folder') . "
                     ORDER BY page_title ASC, page_name ASC") as $sc_ce_p) {
                $sc_ce_merged[(int)$sc_ce_p['page_id']] = $sc_ce_p;
            }
            foreach (pg_sw_widget_pages('calendar_event_view') as $sc_ce_p) $sc_ce_merged[(int)$sc_ce_p['page_id']] = $sc_ce_p;
            respond(array('status' => 'success', 'pages' => array_values($sc_ce_merged)));
            break;

        // ── LIST PRODUCT GROUPS ─────────────────────────────────────────
        // Returns every product_groups row. Used by the system-widget props panel
        // to populate the "Product Group" dropdown when regionType='catalog_listing'.
        // Indented by parent_id so the user sees the hierarchy at a glance.
        case 'list_product_groups':
            $sc_pg_rows = db_items(
                "SELECT id, name, parent_id, sort_order
                     FROM product_groups
                     ORDER BY parent_id ASC, sort_order ASC, name ASC"
            );
            respond(array('status' => 'success', 'groups' => $sc_pg_rows));
            break;

        // ── LIST CATALOG PAGES ──────────────────────────────────────────
        // Returns every page with page_type='catalog'. Used by the system
        // widget props panel when group_navigation='page' so the designer
        // can pick which catalog page each group click should redirect to.
        case 'list_catalog_pages':
            $sc_cp_rows = db_items(
                "SELECT page_id, page_name, page_title
                     FROM page
                     WHERE page_type = 'catalog'" . pg_designer_not_binned_sql('page.page_folder') . "
                     ORDER BY page_title ASC, page_name ASC"
            );
            respond(array('status' => 'success', 'pages' => $sc_cp_rows));
            break;

        // ── LIST CATALOG LISTING PAGES ──────────────────────────────────
        // Returns pages that contain a catalog_listing system widget.
        // Used by the catalog_item_view widget's "Catalog page"
        // picker — designer chooses which catalog page the breadcrumb
        // crumbs (Main Catalog → Group → …) and cross-sell card URLs
        // should link back to.
        //
        // Mirrors list_catalog_detail_pages but keys on
        // regionType='catalog_listing' instead of catalog_item_view.
        // Same two-source merge: legacy 'catalog' page_type + system
        // pages whose style_tree_json shared_ref's a catalog_listing
        // shared_component.
        case 'list_catalog_listing_pages':
            $sc_cl_legacy = db_items(
                "SELECT page_id, page_name, page_title
                     FROM page
                     WHERE page_type = 'catalog'" . pg_designer_not_binned_sql('page.page_folder')
            );
            $sc_cl_civ_ids = db_items(
                "SELECT id FROM shared_components
                     WHERE system_region_config LIKE '%\"regionType\":\"catalog_listing\"%'"
            );
            $sc_cl_sys = array();
            if (!empty($sc_cl_civ_ids)) {
                $sc_cl_likes = array();
                foreach ($sc_cl_civ_ids as $sc_cl_r) {
                    $sc_cl_cid = (int)$sc_cl_r['id'];
                    if ($sc_cl_cid <= 0) continue;
                    $sc_cl_likes[] = pg_page_tree_sql_expr() . " LIKE '%\"sharedId\":" . $sc_cl_cid . ",%'"
                                   . " OR " . pg_page_tree_sql_expr() . " LIKE '%\"sharedId\":" . $sc_cl_cid . "}%'";
                }
                if (!empty($sc_cl_likes)) {
                    $sc_cl_where = '(' . implode(' OR ', $sc_cl_likes) . ')';
                    $sc_cl_sys = db_items(
                        "SELECT page.page_id, page.page_name, page.page_title
                             FROM page
                             INNER JOIN style ON page.page_style = style.style_id
                             WHERE page.layout_type = 'system'
                               AND $sc_cl_where" . pg_designer_not_binned_sql('page.page_folder')
                    );
                }
            }
            $sc_cl_merged = array();
            foreach ((array)$sc_cl_legacy as $sc_cl_p) { $sc_cl_merged[(int)$sc_cl_p['page_id']] = $sc_cl_p; }
            foreach ((array)$sc_cl_sys    as $sc_cl_p) { $sc_cl_merged[(int)$sc_cl_p['page_id']] = $sc_cl_p; }
            $sc_cl_pages = array_values($sc_cl_merged);
            usort($sc_cl_pages, function ($a, $b) {
                $ta = trim((string)$a['page_title']);
                $tb = trim((string)$b['page_title']);
                if ($ta !== $tb) return strcasecmp($ta, $tb);
                return strcasecmp((string)$a['page_name'], (string)$b['page_name']);
            });
            respond(array('status' => 'success', 'pages' => $sc_cl_pages));
            break;

        // ── LIST CATALOG DETAIL PAGES ───────────────────────────────────
        // Returns the candidate detail pages for a catalog_listing widget's
        // "Detail page" dropdown. Each product row's ^^__detail_url^^ token
        // resolves to PATH . page_name . '/' . product.address_name, so the
        // chosen page must render a single product (or a select-type group).
        //
        // Two sources merged + deduped by page_id:
        //   1) Legacy custom-style pages with page_type='catalog detail'.
        //   2) System-style pages whose linked style_tree_json contains a
        //      shared_ref to a shared_component whose system_region_config
        //      declares regionType='catalog_item_view'. The widget tree
        //      lives on shared_components, NOT on the style tree — that's
        //      why a naive `style_tree_json LIKE '%catalog_item_view%'`
        //      misses every system-style detail page.
        case 'list_catalog_detail_pages':
            $sc_cd_legacy = db_items(
                "SELECT page_id, page_name, page_title
                     FROM page
                     WHERE page_type = 'catalog detail'" . pg_designer_not_binned_sql('page.page_folder')
            );

            // Step 1: ids of shared_components that ARE catalog_item_view widgets.
            $sc_cd_civ_ids = db_items(
                "SELECT id FROM shared_components
                     WHERE system_region_config LIKE '%\"regionType\":\"catalog_item_view\"%'"
            );

            $sc_cd_sys = array();
            if (!empty($sc_cd_civ_ids)) {
                // Step 2: pages linked to a style that shared_ref's at least
                // one of those component ids. Match `"sharedId":<id>` followed
                // by either `,` (more props after) or `}` (last prop) to avoid
                // substring collisions (id=1 vs id=10).
                $sc_cd_likes = array();
                foreach ($sc_cd_civ_ids as $sc_cd_r) {
                    $sc_cd_cid = (int)$sc_cd_r['id'];
                    if ($sc_cd_cid <= 0) continue;
                    $sc_cd_likes[] = pg_page_tree_sql_expr() . " LIKE '%\"sharedId\":" . $sc_cd_cid . ",%'"
                                   . " OR " . pg_page_tree_sql_expr() . " LIKE '%\"sharedId\":" . $sc_cd_cid . "}%'";
                }
                if (!empty($sc_cd_likes)) {
                    $sc_cd_where = '(' . implode(' OR ', $sc_cd_likes) . ')';
                    $sc_cd_sys = db_items(
                        "SELECT page.page_id, page.page_name, page.page_title
                             FROM page
                             INNER JOIN style ON page.page_style = style.style_id
                             WHERE page.layout_type = 'system'
                               AND $sc_cd_where" . pg_designer_not_binned_sql('page.page_folder')
                    );
                }
            }

            // Merge + dedupe by page_id; legacy entries first, system entries
            // overwrite (same page) — both sources carry identical column shape.
            $sc_cd_merged = array();
            foreach ((array)$sc_cd_legacy as $sc_cd_p) { $sc_cd_merged[(int)$sc_cd_p['page_id']] = $sc_cd_p; }
            foreach ((array)$sc_cd_sys    as $sc_cd_p) { $sc_cd_merged[(int)$sc_cd_p['page_id']] = $sc_cd_p; }
            $sc_cd_pages = array_values($sc_cd_merged);
            usort($sc_cd_pages, function ($a, $b) {
                $ta = trim((string)$a['page_title']);
                $tb = trim((string)$b['page_title']);
                if ($ta !== $tb) return strcasecmp($ta, $tb);
                return strcasecmp((string)$a['page_name'], (string)$b['page_name']);
            });
            respond(array('status' => 'success', 'pages' => $sc_cd_pages));
            break;

        // ── LIST SHOPPING CART PAGES ────────────────────────────────────
        // Used by the catalog_item_view widget's "Sonraki Sayfa" picker —
        // after add_to_cart succeeds, the visitor is redirected to a page
        // that should actually render a shopping cart, not just any page.
        //
        // Two sources merged + deduped by page_id (mirrors list_catalog_detail_pages):
        //   1) Legacy custom-style pages with page_type='shopping cart'.
        //   2) System-style pages whose linked style_tree_json contains a
        //      shared_ref to a shared_component whose system_region_config
        //      declares regionType='shopping_cart'. The widget tree lives
        //      on shared_components, NOT on the style tree — so a naive
        //      `style_tree_json LIKE '%shopping_cart%'` would miss every
        //      system-style cart page.
        case 'list_shopping_cart_pages':
            $sc_sc_legacy = db_items(
                "SELECT page_id, page_name, page_title
                     FROM page
                     WHERE page_type = 'shopping cart'" . pg_designer_not_binned_sql('page.page_folder')
            );

            // Step 1: ids of shared_components that ARE shopping_cart widgets.
            $sc_sc_ids = db_items(
                "SELECT id FROM shared_components
                     WHERE system_region_config LIKE '%\"regionType\":\"shopping_cart\"%'"
            );

            $sc_sc_sys = array();
            if (!empty($sc_sc_ids)) {
                // Step 2: pages linked to a style that shared_ref's at least
                // one of those component ids. Same pattern as catalog_detail
                // — match `"sharedId":N,` or `"sharedId":N}` to avoid
                // substring collisions (id=1 vs id=10).
                $sc_sc_likes = array();
                foreach ($sc_sc_ids as $sc_sc_r) {
                    $sc_sc_cid = (int)$sc_sc_r['id'];
                    if ($sc_sc_cid <= 0) continue;
                    $sc_sc_likes[] = pg_page_tree_sql_expr() . " LIKE '%\"sharedId\":" . $sc_sc_cid . ",%'"
                                   . " OR " . pg_page_tree_sql_expr() . " LIKE '%\"sharedId\":" . $sc_sc_cid . "}%'";
                }
                if (!empty($sc_sc_likes)) {
                    $sc_sc_where = '(' . implode(' OR ', $sc_sc_likes) . ')';
                    $sc_sc_sys = db_items(
                        "SELECT page.page_id, page.page_name, page.page_title
                             FROM page
                             INNER JOIN style ON page.page_style = style.style_id
                             WHERE page.layout_type = 'system'
                               AND $sc_sc_where" . pg_designer_not_binned_sql('page.page_folder')
                    );
                }
            }

            // Merge + dedupe by page_id; legacy entries first, system entries
            // overwrite (same page) — both sources carry identical column shape.
            $sc_sc_merged = array();
            foreach ((array)$sc_sc_legacy as $sc_sc_p) { $sc_sc_merged[(int)$sc_sc_p['page_id']] = $sc_sc_p; }
            foreach ((array)$sc_sc_sys    as $sc_sc_p) { $sc_sc_merged[(int)$sc_sc_p['page_id']] = $sc_sc_p; }
            $sc_sc_pages = array_values($sc_sc_merged);
            usort($sc_sc_pages, function ($a, $b) {
                $ta = trim((string)$a['page_title']);
                $tb = trim((string)$b['page_title']);
                if ($ta !== $tb) return strcasecmp($ta, $tb);
                return strcasecmp((string)$a['page_name'], (string)$b['page_name']);
            });
            respond(array('status' => 'success', 'pages' => $sc_sc_pages));
            break;

        // ── LIST ALL PAGES ──────────────────────────────────────────────
        // Returns every page row (page_id, page_name, page_title) sorted by
        // title then name. Used by the my_account widget's login-page picker so
        // the designer can select any page as the login redirect target.
        // Response shape: { status:'success', pages:[{page_id,page_name,page_title,page_type,binned,widgets},...] }
        case 'list_all_pages':
            // Each page also carries its page_type and the system widgets
            // its tree holds, so a picker that asks for a sign-in page
            // lists sign-in pages instead of every page on the site.
            $sc_ap_types = array();
            foreach ((array) db_items("SELECT id, system_region_config FROM shared_components WHERE system_region_config IS NOT NULL AND system_region_config <> ''") as $sc_ap_w) {
                $sc_ap_cfg = json_decode((string) $sc_ap_w['system_region_config'], true);
                if (is_array($sc_ap_cfg) && !empty($sc_ap_cfg['regionType'])) {
                    $sc_ap_types[(int) $sc_ap_w['id']] = (string) $sc_ap_cfg['regionType'];
                }
            }
            // Pages in the Recycle Bin come flagged (binned) rather than
            // left out: the pickers drop them, except the one a setting
            // still names, which they show as deleted instead of "none".
            $sc_ap_rows = _sc_flag_binned_pages(db_items(
                "SELECT page_id, page_name, page_title, page_type, page_folder,
                            IF(page_tree_json LIKE '%sharedId%', page_tree_json, '') AS page_tree_json
                     FROM page
                     ORDER BY page_title ASC, page_name ASC"
            ));
            foreach ($sc_ap_rows as &$sc_ap_row) {
                $sc_ap_found = array();
                if ($sc_ap_types && $sc_ap_row['page_tree_json'] !== '') {
                    preg_match_all('/"sharedId"\s*:\s*"?(\d+)/', (string) $sc_ap_row['page_tree_json'], $sc_ap_m);
                    foreach (array_unique($sc_ap_m[1]) as $sc_ap_sid) {
                        if (isset($sc_ap_types[(int) $sc_ap_sid])) $sc_ap_found[] = $sc_ap_types[(int) $sc_ap_sid];
                    }
                }
                $sc_ap_row['widgets'] = array_values(array_unique($sc_ap_found));
                unset($sc_ap_row['page_tree_json']);
            }
            unset($sc_ap_row);
            respond(array('status' => 'success', 'pages' => $sc_ap_rows));
            break;

        // ── LIST FORM FIELDS ────────────────────────────────────────────
        // Returns the form_fields rows for a given custom form page_id. Used by the
        // data binding section to populate the per-prop field dropdowns with the
        // ACTUAL fields of the selected form (instead of a generic hardcoded list).
        case 'list_form_fields':
            $sc_ff_pid = _sc_custom_form_page_id($request);
            if ($sc_ff_pid <= 0) {
                respond(array('status' => 'error', 'message' => lang('Pass the custom form\'s page ID as form_id or page_id.')));
            }
            // rss_field names a field's role (title / description /
            // media / category), wysiwyg says whether a text area holds
            // markup, office_use_only keeps staff fields off a public
            // layout — the designer builds a form's starter layout from
            // these, in the form's own order.
            $sc_ff_rows = db_items(
                "SELECT id, name, label, type, rss_field, wysiwyg, office_use_only
                     FROM form_fields
                     WHERE page_id = '$sc_ff_pid'
                     ORDER BY sort_order ASC, id ASC"
            );
            respond(array('status' => 'success', 'fields' => $sc_ff_rows));
            break;

        default:
            respond(array('status' => 'error', 'message' => lang('Invalid action.')));
    }
}

// The custom form a request names, by its page id. The form actions take it
// as form_id or page_id alike, so a caller that knows one action's name does
// not get a bare "Invalid ID." from the other.
function _sc_custom_form_page_id($request)
{
    foreach (array('form_id', 'page_id') as $key) {
        if (isset($request[$key]) && is_scalar($request[$key]) && (int)$request[$key] > 0) return (int)$request[$key];
    }
    return 0;
}

// Marks the rows (each with page_folder) whose page is in the Recycle Bin
// with binned = 1 and drops page_folder. The typed page lists above leave
// binned pages out; the full page list and the form list keep them flagged,
// so a picker can still name the page a saved setting points at. A binned
// page carries the name it had before the bin parked it.
function _sc_flag_binned_pages($rows)
{
    $bin = array_flip(array_map('intval', (array)pg_recycle_bin_folder_ids()));
    if ($bin) require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');
    $rows = is_array($rows) ? $rows : array();
    foreach ($rows as &$row) {
        $row['binned'] = isset($bin[(int)$row['page_folder']]) ? 1 : 0;
        unset($row['page_folder']);
        if ($row['binned'] && isset($row['page_name'])) {
            $original = pg_recycle_parked_original($row['page_name'], (int)$row['page_id']);
            if ($original !== '') $row['page_name'] = $original;
        }
    }
    unset($row);
    return $rows;
}

// Returns a name that does not yet exist in shared_components.
// If $desired is already taken, appends " [1]", " [2]", ... until a free slot is found.
function _sc_unique_name($desired)
{
    $base = $desired;
    $name = $base;
    $i    = 1;
    while (db_value("SELECT COUNT(*) FROM shared_components WHERE name = '" . e($name) . "'") > 0) {
        $name = $base . ' [' . $i . ']';
        $i++;
    }
    return $name;
}

// What each component row says about the content level: its flag
// (`content_editable`, 0/1) and whether the session user may edit it there
// (`may_edit_content`). Both 0 before the schema step.
function _sc_content_flags($rows, $user)
{
    if (!is_array($rows) || empty($rows)) return is_array($rows) ? $rows : array();

    $ids = array();
    foreach ($rows as $row) {
        if (isset($row['id']) && (int)$row['id'] > 0) $ids[] = (int)$row['id'];
    }

    $open = array();
    if ($ids && pg_shared_component_content_ready()) {
        $open = array_flip(array_map('intval', (array)db_values(
            "SELECT id FROM shared_components WHERE content_editable = 1 AND id IN (" . implode(',', $ids) . ")")));
    }
    $may = $open ? pg_shared_component_content_editable_ids($user, $ids) : array();

    foreach ($rows as $i => $row) {
        $id = isset($row['id']) ? (int)$row['id'] : 0;
        $rows[$i]['content_editable'] = isset($open[$id]) ? 1 : 0;
        $rows[$i]['may_edit_content'] = isset($may[$id]) ? 1 : 0;
    }

    return $rows;
}

// The grants of deleted components go with them.
function _sc_delete_grants($ids)
{
    $list = array();
    foreach ((array)$ids as $id) {
        if ((int)$id > 0) $list[] = (int)$id;
    }
    if ($list && pg_schema_has('users_shared_components_xref')) {
        db("DELETE FROM users_shared_components_xref WHERE shared_component_id IN (" . implode(',', $list) . ")");
    }
}
