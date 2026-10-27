<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Panel actions - the page designer and the visual designer.
 *
 *   get_design_files                 the design files of a theme
 *   get_designer_region              one designer region
 *   get_designer_regions             the designer regions
 *   get_items_in_style               the regions and files a page style uses
 *   get_layout                       a page style's layout
 *   get_style                        one page style
 *   get_styles                       the page styles
 *   create_design_region             creates a designer region
 *   update_designer_region           saves a designer region
 *   update_file                      saves a design file
 *   update_layout                    saves a page style's layout
 *   update_page_designer_properties  the page designer's own settings, in
 *                                    the session
 *   update_style                     saves a page style
 *   update_toolbar_properties        the front-end toolbar's open or closed
 *                                    state, in the session
 *   designer                         the visual designer's endpoint and its
 *                                    sub-actions
 *   designer_file                    the visual designer's new CSS/JS/JSON
 *                                    files
 *   dynamic_region_code              a dynamic region's PHP code, read and
 *                                    saved from the visual designer's code
 *                                    tab
 *   layout_code                      a page's custom layout (PHP), read,
 *                                    regenerated and saved from the visual
 *                                    designer's code tab
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

function pg_panel_get_design_files($request, $action)
{
    $sql_types = "";

    // If types is an array and has at least one item,
    // then prepare SQL to limit by types.
    if ((is_array($request['types']) == true) && $request['types']) {
        foreach ($request['types'] as $type) {
            if ($sql_types != '') {
                $sql_types .= " OR ";
            }

            $sql_types .= "(type = '" . escape($type) . "')";
        }

        $sql_types = "AND ($sql_types)";
    }

    $sql_search = "";

    if ($request['search'] != '') {
        $sql_search = "AND (name LIKE '%" . escape(escape_like($request['search'])) . "%')";
    }

    $design_files = db_items(
        "SELECT
                id,
                name,
                type,
                theme
            FROM files
            WHERE
                (design = '1')
                $sql_types
                $sql_search
            ORDER BY timestamp DESC"
    );

    // If a specific type of theme was specified, then loop through design files
    // in order to only include that type of theme.
    if ($request['theme_type'] != '') {
        foreach ($design_files as $key => $design_file) {
            // If this file is a CSS theme, then determine what type of theme it is.
            if ($design_file['theme'] == 1) {
                // If this is a system theme, then set that.
                if (db_value("SELECT COUNT(*) FROM system_theme_css_rules WHERE file_id = '" . $design_file['id'] . "'") > 0) {
                    $design_file['theme_type'] = 'system';

                    // Otherwise this is a custom theme, so set that.
                } else {
                    $design_file['theme_type'] = 'custom';
                }

                // If the theme type does not matched the requested theme type,
                // then remove this design file from the array.
                if ($design_file['theme_type'] != $request['theme_type']) {
                    unset($design_files[$key]);
                }
            }
        }
    }

    $response = array(
        'status' => 'success',
        'design_files' => $design_files
    );

    echo encode_json($response);
    exit();
}

function pg_panel_get_designer_region($request, $action)
{
    $designer_region = db_item(
        "SELECT
                cregion_id AS id,
                cregion_name AS name,
                cregion_content AS content
            FROM cregion
            WHERE
                (cregion_designer_type = 'yes')
                AND (cregion_id = '" . escape($request['designer_region']['id']) . "')"
    );

    // If a designer region was not found then respond with an error.
    if (!$designer_region) {
        $response = array(
            'status' => 'error',
            'message' => 'Designer Region could not be found.'
        );

        echo encode_json($response);
        exit();
    }

    $response = array(
        'status' => 'success',
        'designer_region' => $designer_region
    );

    echo encode_json($response);
    exit();
}

function pg_panel_get_designer_regions($request, $action)
{
    $sql_content = "";

    if ($request['content'] == true) {
        $sql_content = ", cregion_content AS content";
    }

    $sql_search = "";

    if ($request['search'] != '') {
        $sql_search = "AND (cregion_name LIKE '%" . escape(escape_like($request['search'])) . "%')";
    }

    $designer_regions = db_items(
        "SELECT
                cregion_id AS id,
                cregion_name AS name
                $sql_content
            FROM cregion
            WHERE
                (cregion_designer_type = 'yes')
                $sql_search
            ORDER BY cregion_timestamp DESC"
    );

    $response = array(
        'status' => 'success',
        'designer_regions' => $designer_regions
    );

    echo encode_json($response);
    exit();
}

function pg_panel_get_items_in_style($request, $action)
{
    $style = db_item(
        "SELECT
                style_id AS id,
                style_code AS code
            FROM style
            WHERE style_id = '" . escape($request['style_id']) . "'"
    );

    // If a style was not found then respond with an error.
    if (!$style) {
        $response = array(
            'status' => 'error',
            'message' => 'Style could not be found.'
        );

        echo encode_json($response);
        exit();
    }

    $content = $style['code'];

    $content = preg_replace('/{path}/i', OUTPUT_PATH, $content);

    $design_files = array();

    // Find all CSS and JS resources in the style content.
    preg_match_all('/["\']\s*([^"\']*\.(css|js)[^"\']*)\s*["\']/i', $content, $matches, PREG_SET_ORDER);

    // Loop through all of the resources in order to determine if they
    // are design files for this site.
    foreach ($matches as $match) {
        $url = trim($match[1]);
        $url = unhtmlspecialchars($url);
        $url_parts = parse_url($url);
        $file_name = basename($url_parts['path']);
        $file_name = rawurldecode($file_name);

        // Check if design file exists for this file name.
        $design_file = db_item(
            "SELECT
                    id,
                    name,
                    type,
                    theme
                FROM files
                WHERE
                    (design = '1')
                    AND (name = '" . escape($file_name) . "')"
        );

        // If a design file was found, then add it to array.
        if ($design_file) {
            // If this file is a CSS theme, then determine what type of theme it is.
            if ($design_file['theme'] == 1) {
                // If this is a system theme, then set that.
                if (db_value("SELECT COUNT(*) FROM system_theme_css_rules WHERE file_id = '" . $design_file['id'] . "'") > 0) {
                    $design_file['theme_type'] = 'system';

                } else {
                    $design_file['theme_type'] = 'custom';
                }
            }

            $design_files[] = $design_file;
        }
    }

    $designer_regions = array();

    // Get all designer regions in the style content.
    preg_match_all('/<cregion>.*?<\/cregion>/i', $content, $regions);

    foreach ($regions[0] as $region) {
        $name = strip_tags($region);

        $designer_region = db_item(
            "SELECT
                    cregion_id AS id,
                    cregion_name AS name
                FROM cregion
                WHERE
                    (cregion_name = '" . escape($name) . "')
                    AND (cregion_designer_type = 'yes')"
        );

        // If a designer region was found, then add it to array.
        if ($designer_region) {
            $designer_regions[] = $designer_region;
        }
    }

    $dynamic_regions = array();

    // Get all dynamic regions in the style content.
    preg_match_all('/<dregion.*?>.*?<\/dregion>/i', $content, $regions);

    foreach ($regions[0] as $region) {
        $name = strip_tags($region);

        $dynamic_region = db_item(
            "SELECT
                    dregion_id AS id,
                    dregion_name AS name
                FROM dregion
                WHERE dregion_name = '" . escape($name) . "'"
        );

        // If a dynamic region was found, then add it to array.
        if ($dynamic_region) {
            $dynamic_regions[] = $dynamic_region;
        }
    }

    $system_regions = array();

    // Get all system regions.
    preg_match_all('/<system>.*?<\/system>/i', $content, $regions);

    foreach ($regions[0] as $region) {
        $name = strip_tags($region);

        // If this is a secondary system region with a page name,
        // then add it to the array.
        if ($name != '') {
            $system_region = array();
            $system_region['name'] = $name;

            $system_regions[] = $system_region;
        }
    }

    $response = array(
        'status' => 'success',
        'design_files' => $design_files,
        'designer_regions' => $designer_regions,
        'dynamic_regions' => $dynamic_regions,
        'system_regions' => $system_regions
    );

    echo encode_json($response);
    exit();
}

function pg_panel_get_layout($request, $action)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/layouts.php');

    $layout = pg_layout_read(isset($request['layout']['id']) ? $request['layout']['id'] : '');

    // If a layout was not found then respond with an error.
    if (!$layout) {
        $response = array(
            'status' => 'error',
            'message' => 'Layout could not be found.'
        );

        echo encode_json($response);
        exit();
    }

    $response = array(
        'status' => 'success',
        'layout' => array(
            'id' => $layout['id'],
            'name' => $layout['name'],
            'modified' => $layout['modified'],
            'content' => $layout['content'],
        )
    );

    echo encode_json($response);
    exit();
}

function pg_panel_get_style($request, $action)
{
    $style = db_item(
        "SELECT
                style_id AS id,
                style_name AS name,
                style_type AS type,
                style_layout AS layout,
                style_empty_cell_width_percentage AS empty_cell_width_percentage,
                style_code AS code,
                style_head AS head,
                social_networking_position,
                additional_body_classes,
                collection,
                layout_type
            FROM style
            WHERE style_id = '" . escape($request['style']['id']) . "'"
    );

    // If a style was not found then respond with an error.
    if (!$style) {
        $response = array(
            'status' => 'error',
            'message' => 'Style could not be found.'
        );

        echo encode_json($response);
        exit();
    }

    $response = array(
        'status' => 'success',
        'style' => $style
    );

    echo encode_json($response);
    exit();
}

function pg_panel_get_styles($request, $action)
{
    $sql_code = "";

    if ($request['code'] == true) {
        $sql_code = "style_code AS code,";
    }

    $sql_where = "";

    if (($request['type'] ?? '') != '') {
        $sql_where .= "WHERE (style_type = '" . escape($request['type']) . "')";
    }

    if (($request['search'] ?? '') != '') {
        if ($sql_where == '') {
            $sql_where .= "WHERE ";
        } else {
            $sql_where .= " AND ";
        }

        $sql_where .= "(style_name LIKE '%" . escape(escape_like($request['search'])) . "%')";
    }

    $styles = db_items(
        "SELECT
                style_id AS id,
                style_name AS name,
                style_type AS type,
                style_layout AS layout,
                style_empty_cell_width_percentage AS empty_cell_width_percentage,
                $sql_code
                style_head AS head,
                social_networking_position,
                additional_body_classes,
                collection,
                layout_type
            FROM style
            $sql_where
            ORDER BY style_timestamp DESC"
    );

    // Loop through the styles in order to get cells for system styles.
    foreach ($styles as $key => $style) {
        // If this style is a system style, then get cells.
        if ($style['type'] == 'system') {
            $cells = db_items(
                "SELECT
                        area,
                        `row`, # Backticks for reserved word.
                        col,
                        region_type,
                        region_name
                    FROM system_style_cells
                    WHERE style_id = '" . $style['id'] . "'"
            );

            $styles[$key]['cells'] = $cells;
        }
    }

    $response = array(
        'status' => 'success',
        'styles' => $styles
    );

    echo encode_json($response);
    exit();
}

function pg_panel_create_design_region($request, $action)
{
    // Kept global: helpers read the session user through `global $user`;
    // see includes/panel/actions.php.
    global $user;

    validate_token();
    $user = validate_user();

    if ($user['role'] < 1) {
        $name = trim($request['designer_region']['name']);
        $query = "INSERT INTO cregion (cregion_name, cregion_content, cregion_designer_type, cregion_user, cregion_timestamp) "
            . "VALUES ('" . escape($name) . "', '" . escape($request['designer_region']['content']) . "', 'yes', " . USER_ID . ", UNIX_TIMESTAMP())";
        // insert row into region table
        $result = mysqli_query(db::$con, $query) or output_error('Query failed');
        log_activity(lang(array('string' => '{var:1} ({var:2}) was created', 'vars' => array(lang('designer region'), $name))), $_SESSION['sessionusername']);

        $response = array(
            'status' => 'success',
            'name' => $name
        );
        echo encode_json($response);
    } else {
        $response = array(
            'status' => 'error',
            'message' => lang('Access denied'),
        );
        echo encode_json($response);
    }

    exit();
}

function pg_panel_update_designer_region($request, $action)
{
    validate_token();

    $designer_region = db_item(
        "SELECT cregion_id AS id
            FROM cregion
            WHERE
                (cregion_designer_type = 'yes')
                AND (cregion_id = '" . escape($request['designer_region']['id']) . "')"
    );

    // If a designer region was not found then respond with an error.
    if (!$designer_region) {
        $response = array(
            'status' => 'error',
            'message' => 'Designer Region could not be found.'
        );

        echo encode_json($response);
        exit();
    }

    db(
        "UPDATE cregion
            SET
                cregion_content = '" . escape($request['designer_region']['content']) . "',
                cregion_timestamp = UNIX_TIMESTAMP(),
                cregion_user = '" . USER_ID . "'
            WHERE cregion_id = '" . escape($request['designer_region']['id']) . "'"
    );

    $response = array('status' => 'success');

    echo encode_json($response);
    exit();
}

function pg_panel_update_file($request, $action)
{
    validate_token();

    $file = db_item(
        "SELECT
                id,
                name
            FROM files
            WHERE id = '" . escape($request['file']['id']) . "'"
    );

    // If a file was not found then respond with an error.
    if (!$file) {
        $response = array(
            'status' => 'error',
            'message' => 'File could not be found.'
        );

        echo encode_json($response);
        exit();
    }

    unlink(FILE_DIRECTORY_PATH . '/' . $file['name']);

    file_put_contents(FILE_DIRECTORY_PATH . '/' . $file['name'], $request['file']['content']);

    db(
        "UPDATE files
            SET
                timestamp = UNIX_TIMESTAMP(),
                user = '" . USER_ID . "'
            WHERE id = '" . escape($request['file']['id']) . "'"
    );

    $response = array('status' => 'success');

    echo encode_json($response);
    exit();
}

function pg_panel_update_layout($request, $action)
{
    validate_token();

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/layouts.php');

    $result = pg_layout_save(
        isset($request['layout']['id']) ? $request['layout']['id'] : '',
        isset($request['layout']['content']) && is_string($request['layout']['content']) ? $request['layout']['content'] : ''
    );

    if (!$result['ok']) {
        echo encode_json(array('status' => 'error', 'message' => $result['message']));
        exit();
    }

    $response = array('status' => 'success');

    echo encode_json($response);
    exit();
}

function pg_panel_update_page_designer_properties($request, $action)
{
    validate_token();

    $_SESSION['software']['page_designer']['query'] = $request['query'];

    respond(array('status' => 'success'));
}

function pg_panel_update_style($request, $action)
{
    validate_token();

    $style = db_item(
        "SELECT style_id AS id
            FROM style
            WHERE style_id = '" . escape($request['style']['id']) . "'"
    );

    // If a style was not found then respond with an error.
    if (!$style) {
        $response = array(
            'status' => 'error',
            'message' => 'Style could not be found.'
        );

        echo encode_json($response);
        exit();
    }

    db(
        "UPDATE style
            SET
                style_code = '" . escape($request['style']['code']) . "',
                style_timestamp = UNIX_TIMESTAMP(),
                style_user = '" . USER_ID . "'
            WHERE style_id = '" . escape($request['style']['id']) . "'"
    );

    $response = array('status' => 'success');

    echo encode_json($response);
    exit();
}

function pg_panel_update_toolbar_properties($request, $action)
{
    // Exempt from the general gate, which answered a request without a
    // session this way; the same answer is kept.
    if (!USER_LOGGED_IN) {
        respond(array(
            'status' => 'error',
            'message' => 'Invalid login.'
        ));
    }
    validate_token();

    $_SESSION['software']['toolbar_enabled'] = (bool) ($request['enabled'] ?? false);

    respond(array('status' => 'success'));
}

/**
 * Whether this person may read a page's revisions: the page exists and they
 * may edit it. pg_designer_page_access() answers 'edit' to a designer for
 * any id, so the row is asked for first.
 *
 * @param int   $page_id
 * @param array $user
 * @return bool
 */
function pg_designer_revision_page_ok($page_id, $user)
{
    $page_id = (int)$page_id;
    if ($page_id <= 0 || !db_value("SELECT COUNT(*) FROM page WHERE page_id = '$page_id'")) {
        return false;
    }
    return pg_designer_page_access($page_id, $user) === 'edit';
}

function pg_panel_designer($request, $action)
{
    // VISUAL DESIGNER — PAGE TABS
    // Internal endpoint for the multi-page designer. Session + token auth,
    // same gate as the editor screens themselves (designer = role <= 1).

    // Kept global: helpers read the session user and the folder list
    // through `global`; see includes/panel/actions.php.
    global $user, $folders_that_user_has_access_to;

    validate_token();
    $user = validate_user();
    require_once(PG_FUNCTIONS_DIR . '/includes/designer_access.php');

    $sub = isset($request['sub_action']) ? $request['sub_action'] : '';

    // A content-level operator (manager, user) gets the endpoints the
    // editor needs to READ a page and to work inside it. Everything that
    // shapes the design — adding, detaching or deleting pages, saving a
    // theme, rewriting a shared component — stays with designers.
    //
    // The list is an allow-list on purpose: a new endpoint is closed
    // until somebody decides it is safe, which is the right default for
    // a file that grows an action a month.
    // The refusal is JSON, not validate_area_access(): that answers with
    // an HTML error page, and every caller here is waiting for JSON — it
    // would read the page as "unexpected token <" and show the operator
    // nothing about why the button did nothing.
    if (!pg_designer_is_full($user)) {
        $content_ok = array('presence', 'leave', 'lock_release', 'note_save', 'notes_fetch',
                            'page_load', 'seo_check', 'import_fragment',
                            'form_fields', 'preview_widgets', 'widget_ghosts',
                            'revisions_list', 'revision_get');
        if (pg_designer_access($user) === PG_DESIGNER_ACCESS_NONE
            || !in_array($sub, $content_ok, true)) {
            respond(array('status' => 'error', 'message' => lang('Permission denied.')));
        }
    }

    // Collaboration endpoints all speak the same two parameters, so they
    // are resolved once rather than in each branch.
    if (in_array($sub, array('presence', 'leave', 'lock_release', 'note_save', 'notes_fetch'), true)) {
        require_once(PG_FUNCTIONS_DIR . '/includes/designer_collab.php');
    }

    // Page changes proposed by an assistant (includes/designer_ai.php):
    // asking, waiting, applying onto the tab. Designers only - the list
    // above does not open them to a content-level operator.
    if (in_array($sub, array('ai_state', 'ai_ask', 'ai_status', 'ai_cancel', 'ai_apply', 'ai_dismiss'), true)) {
        require_once(PG_FUNCTIONS_DIR . '/includes/designer_ai.php');
        respond(pg_design_ai_panel($sub, $request, $user));
    }

    switch ($sub) {

        // ── PRESENCE ─────────────────────────────────────────────────
        // One call does everything the editor needs on a heartbeat: says
        // this tab is alive, claims (or renews) the lock on the page it
        // is showing, and reports back who else is here and whether this
        // tab may edit. One round trip because it runs every twenty
        // seconds in every open editor — three would be three times the
        // load for the same answer.
        case 'presence':
            $co_key   = isset($request['session_key']) ? (string)$request['session_key'] : '';
            $co_style = isset($request['style_id'])    ? (int)$request['style_id']       : 0;
            $co_page  = isset($request['page_id'])     ? (int)$request['page_id']        : 0;
            if (!pg_collab_ready()) {
                // No schema yet: the editor behaves exactly as it did
                // before this feature existed.
                respond(array('status' => 'success', 'ready' => false,
                              'may_edit' => true, 'peers' => array(), 'holder' => null));
            }
            pg_collab_beat($co_key, $co_style, $co_page, $user['id']);
            // `takeover`: this person's OTHER tab holds the page and they
            // asked for it here. The claim evicts a holder only when it
            // is the same user — the flag cannot take a page from
            // somebody else.
            $co_take = !empty($request['takeover']);
            $co_lock = ($co_page > 0)
                ? pg_collab_claim_page($co_key, $co_page, $co_style, $user['id'], $co_take)
                : array('held' => true, 'holder' => null);
            $co_holder = null;
            if (!$co_lock['held'] && is_array($co_lock['holder'])) {
                $co_brief  = pg_collab_user_brief($co_lock['holder']['user_id']);
                $co_holder = array(
                    'user_id' => (int)$co_lock['holder']['user_id'],
                    'name'    => $co_brief['name'],
                    'avatar'  => $co_brief['avatar'],
                    'since'   => (int)$co_lock['holder']['acquired_at'],
                );
            }
            respond(array(
                'status'   => 'success',
                'ready'    => true,
                'may_edit' => (bool)$co_lock['held'],
                'holder'   => $co_holder,
                'peers'    => pg_collab_peers($co_key, $co_style),
                'beat'     => PG_COLLAB_BEAT,
                // page_id => when a note was last written there. The
                // editor compares this with what it already has and only
                // asks for the notes of a page whose number moved.
                'notes'    => pg_collab_note_stamps($co_style),
            ));
            break;

        // Closing the editor. Explicit so the next person does not have to
        // wait out the staleness window; a crash still resolves on its own.
        case 'leave':
            pg_collab_leave(isset($request['session_key']) ? (string)$request['session_key'] : '');
            respond(array('status' => 'success'));
            break;

        // Switching to another tab inside the editor: drop the lock on the
        // page being left before claiming the next one, so a colleague can
        // pick it up immediately.
        case 'lock_release':
            pg_collab_release_page(
                isset($request['session_key']) ? (string)$request['session_key'] : '',
                isset($request['page_id']) ? (int)$request['page_id'] : 0);
            respond(array('status' => 'success'));
            break;

        // The one write a view-mode session may make. Notes live on the
        // node, in the page tree — which is exactly what a locked-out
        // session cannot save — so writing one has its own endpoint and
        // is deliberately NOT gated on the lock. Bounded to one property
        // on one node, so it cannot become a way around the lock.
        case 'note_save':
            if (!pg_collab_note_save(
                    isset($request['page_id']) ? (int)$request['page_id'] : 0,
                    isset($request['node_id']) ? (string)$request['node_id'] : '',
                    isset($request['note']) ? (string)$request['note'] : '',
                    $user)) {
                respond(array('status' => 'error', 'message' => lang('Sorry, we could not accept your request.')));
            }
            respond(array('status' => 'success'));
            break;

        // The notes on one page, and nothing else.
        //
        // Asked for when the heartbeat says the page's note stamp moved.
        // Not the tree: the person asking has the page open and is
        // part-way through their own edits, so returning a tree would
        // force a choice between discarding their work and merging two
        // layouts. A notification should never be making that choice.
        case 'notes_fetch':
            $nf_page = isset($request['page_id']) ? (int)$request['page_id'] : 0;
            if ($nf_page <= 0) {
                respond(array('status' => 'error', 'message' => lang('Invalid ID.')));
            }
            // Same visibility rule as opening the page: a folder whose
            // pages this operator may not even know about does not leak
            // its notes either.
            $nf_row = db_item("SELECT page_id, page_folder FROM page WHERE page_id = '" . e($nf_page) . "' LIMIT 1");
            if (!is_array($nf_row) || pg_designer_page_access($nf_row, $user) === 'hidden') {
                respond(array('status' => 'error', 'message' => lang('Permission denied.')));
            }
            respond(array(
                'status'  => 'success',
                'page_id' => $nf_page,
                'notes'   => pg_collab_page_notes($nf_page),
            ));
            break;

        // One form, whole: every column the field editor can set, plus
        // the options. Asked for when the widget points at a form that is
        // not this page's own — the boot payload only carries the pages
        // that are open as tabs.
        case 'form_fields':
            $cf_pid = isset($request['page_id']) ? (int)$request['page_id'] : 0;
            if ($cf_pid <= 0 || !function_exists('pg_cf_load_page_fields')) {
                respond(array('status' => 'error', 'message' => lang('Invalid ID.')));
            }
            respond(array(
                'status'   => 'success',
                'fields'   => pg_cf_load_page_fields($cf_pid),
                'settings' => pg_cf_load_page_form_settings($cf_pid),
            ));
            break;

        // Adopt an orphaned form: its own page is gone, so the page that
        // renders it becomes its page. Refused for a form whose page is
        // alive — see pg_cf_form_is_orphaned().
        case 'form_adopt':
            $cf_from = isset($request['from_page_id']) ? (int)$request['from_page_id'] : 0;
            $cf_to   = isset($request['to_page_id'])   ? (int)$request['to_page_id']   : 0;
            if (!function_exists('pg_cf_adopt_form') || !pg_cf_adopt_form($cf_from, $cf_to, $user)) {
                respond(array('status' => 'error', 'message' => lang('Sorry, we could not accept your request.')));
            }
            respond(array(
                'status'   => 'success',
                'fields'   => pg_cf_load_page_fields($cf_to),
                'settings' => pg_cf_load_page_form_settings($cf_to),
            ));
            break;

        // The design chooser (pg_designer_target_modal()): the designs a
        // page can be added to, moved to or copied to, and the one to
        // offer for the folder. Design-level, like adding a page.
        case 'design_targets':
            respond(array('status' => 'success') + pg_designer_design_targets(
                isset($request['folder_id']) ? (int)$request['folder_id'] : 0,
                isset($request['exclude_style_id']) ? (int)$request['exclude_style_id'] : 0
            ));
            break;

        // Pages the "Select Page" picker may offer: every visual-designer
        // page not already on this design, with the design it belongs to
        // now so the picker can say what attaching it will change.
        case 'selectable_pages':
            $style_id = isset($request['style_id']) ? (int)$request['style_id'] : 0;
            respond(array(
                'status' => 'success',
                'pages'  => pg_designer_selectable_pages($style_id),
            ));
            break;

        // One page, shaped exactly like the entries the screen embeds at
        // load time, so a picked page opens as a tab with no special
        // casing. Its page_style is left alone here — the page only moves
        // to the design when the operator saves.
        case 'page_load':
            $page_id = isset($request['page_id']) ? (int)$request['page_id'] : 0;
            if ($page_id <= 0) {
                respond(array('status' => 'error', 'message' => lang('Page not found.')));
            }
            $owner = (int)db_value(
                "SELECT page_style FROM page
                     WHERE page_id = '$page_id' AND " . pg_page_visual_design_sql() . " LIMIT 1");
            if ($owner <= 0) {
                respond(array('status' => 'error', 'message' => lang('Page not found.')));
            }
            $found = null;
            foreach (pg_designer_load_pages($owner) as $p) {
                if ((int)$p['page_id'] === $page_id) { $found = $p; break; }
            }
            if ($found === null) {
                respond(array('status' => 'error', 'message' => lang('Page not found.')));
            }
            $tree = null;
            if ($found['tree_json'] !== '') {
                $tree = json_decode($found['tree_json']);
            }
            unset($found['tree_json']);
            $found['tree'] = $tree;
            $found['key']  = 'p' . $page_id;
            // The page's form-level settings come with it; its fields are
            // the controls in its widget tree.
            $found['formSettings'] = function_exists('pg_cf_load_page_form_settings')
                                         ? pg_cf_load_page_form_settings($page_id) : array();
            respond(array('status' => 'success', 'page' => $found));
            break;

        // Take a page OUT of its design. The page is not deleted — it
        // becomes a design of its own, carrying a copy of the shared
        // assets and theme so it keeps rendering exactly as before. This
        // is the reversible move: "Select Page" brings it back. Deleting a
        // page is the pages list's job.
        //
        // Refused for the design's last page: a design with no pages is a
        // style row nothing renders, and the operator almost certainly
        // meant "delete the design" — which the toolbar offers once the
        // pages are gone.
        case 'page_detach':
            $page_id  = isset($request['page_id']) ? (int)$request['page_id'] : 0;
            $style_id = isset($request['style_id']) ? (int)$request['style_id'] : 0;
            if ($page_id <= 0 || $style_id <= 0) {
                respond(array('status' => 'error', 'message' => lang('Page not found.')));
            }
            $page = db_item(
                "SELECT page_id, page_name, page_style FROM page
                     WHERE page_id = '$page_id' AND page_style = '$style_id' AND " . pg_page_visual_design_sql() . " LIMIT 1");
            if (!$page) {
                respond(array('status' => 'error', 'message' => lang('Page not found.')));
            }
            $siblings = (int)db_value(
                "SELECT COUNT(*) FROM page WHERE page_style = '$style_id' AND " . pg_page_visual_design_sql() . pg_designer_not_binned_sql('page_folder'));
            if ($siblings <= 1) {
                respond(array('status' => 'error', 'message' => lang('The last page cannot be detached from its design. Delete the design instead.')));
            }
            $src = db_item("SELECT * FROM style WHERE style_id = '$style_id' LIMIT 1");
            if (!$src) {
                respond(array('status' => 'error', 'message' => lang('The style could not be found.')));
            }

            $new_name = get_unique_name(array('name' => (string)$page['page_name'], 'type' => 'style'));
            $new_style_id = (int)save_system_style(array(
                'style_id'                          => 0,
                'name'                              => $new_name,
                'theme_id'                          => $src['theme_id'],
                'additional_body_classes'           => $src['additional_body_classes'],
                'collection'                        => $src['collection'],
                'social_networking_position'        => $src['social_networking_position'],
                'style_head'                        => $src['style_head'],
                'style_empty_cell_width_percentage' => $src['style_empty_cell_width_percentage'],
                'user_id'                           => $user['id'],
                // What the page loaded: the files kept to other pages of
                // the design stay behind (pg_design_assets_for_page()).
                'style_custom_css'                  => isset($src['style_custom_css'])   ? pg_design_assets_for_page($src['style_custom_css'], $page_id) : '',
                'style_custom_js'                   => isset($src['style_custom_js'])    ? pg_design_assets_for_page($src['style_custom_js'], $page_id)  : '',
                'style_custom_fonts'                => isset($src['style_custom_fonts']) ? $src['style_custom_fonts'] : '',
                // The page was drawn on the design's framework, in its
                // look and colours.
                'framework'                         => isset($src['style_framework']) ? $src['style_framework'] : '',
                'look'                              => isset($src['style_look']) ? $src['style_look'] : '',
                'palette'                           => isset($src['style_palette']) ? $src['style_palette'] : '',
                // ...and keeps the design's meta tags in its head.
                'style_meta_tags'                   => isset($src['style_meta_tags']) ? (string)$src['style_meta_tags'] : '',
            ));
            if ($new_style_id <= 0) {
                respond(array('status' => 'error', 'message' => lang('The style could not be created.')));
            }
            db("UPDATE page SET page_style = '$new_style_id', page_timestamp = UNIX_TIMESTAMP(), page_user = '" . (int)$user['id'] . "'
                    WHERE page_id = '$page_id'");
            // Un-migrated database: the page has no tree column, so the new
            // style must carry the layout itself.
            if (!pg_multi_page_design_ready()) {
                db("UPDATE style dst, style s
                        SET dst.style_tree_json = s.style_tree_json, dst.style_code = s.style_code
                        WHERE dst.style_id = '$new_style_id' AND s.style_id = '$style_id'");
            }
            log_activity(lang(array('string' => 'page ({var:1}) was detached into its own style ({var:2})', 'vars' => array($page['page_name'], $new_name))), $_SESSION['sessionusername']);
            respond(array(
                'status'       => 'success',
                'new_style_id' => $new_style_id,
                'new_style_name' => $new_name,
                'edit_url'     => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_system_style.php?id=' . $new_style_id,
            ));
            break;

        // Send a page to the recycle bin. This IS the file explorer's
        // delete, called with a single page, so the rules are the same
        // ones the operator meets there: folder edit access, the
        // delete-pages right for a basic user, name parked while the page
        // waits in the bin. The page keeps its page_style, so restoring it
        // from the bin puts it back on this design's tab strip.
        case 'page_delete':
            $page_id  = isset($request['page_id']) ? (int)$request['page_id'] : 0;
            $style_id = isset($request['style_id']) ? (int)$request['style_id'] : 0;
            if ($page_id <= 0 || $style_id <= 0) {
                respond(array('status' => 'error', 'message' => lang('Page not found.')));
            }
            $page = db_item(
                "SELECT page_id, page_name, page_folder FROM page
                     WHERE page_id = '$page_id' AND page_style = '$style_id' AND " . pg_page_visual_design_sql() . " LIMIT 1");
            if (!$page) {
                respond(array('status' => 'error', 'message' => lang('Page not found.')));
            }
            if (($user['role'] == 3) && !$user['delete_pages']) {
                respond(array('status' => 'error', 'message' => lang('You do not have access to delete pages.')));
            }
            // The last page may go too: the design then has no pages,
            // the editor returns to its home screen, and the design is
            // listed there as empty (open it to add a page, or delete
            // it). Keeping the style row is what lets the binned page
            // come back with its assets.
            require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');
            if (pg_recycle_ready() == false) {
                respond(array('status' => 'error', 'message' => lang('The recycle bin is not available. Delete the page from the pages list instead.')));
            }
            $folders_that_user_has_access_to = ($user['role'] == 3) ? get_folders_that_user_has_access_to($user['id']) : array();
            // Replies on its own: {status, binned, errors, message}.
            pg_explorer_handle(
                array('type' => 'explorer_recycle_delete', 'items' => array(array('kind' => 'page', 'id' => $page_id))),
                $user,
                $folders_that_user_has_access_to);
            break;

        // Pasted HTML, as a whole page. Same parser and the same reply
        // shape as designer_import.php, which cannot serve this: it
        // reads multipart because it carries a file, and there is no
        // file here — the markup arrives as text in the JSON body.
        case 'import_paste':
            include_once(PG_FUNCTIONS_DIR . '/includes/designer_import.php');
            $paste_html = isset($request['html']) ? (string)$request['html'] : '';
            if (strlen($paste_html) > 4000000) {
                respond(array('status' => 'error', 'message' => lang('The pasted content is too large.')));
            }
            if (trim($paste_html) === '') {
                respond(array('status' => 'error', 'message' => lang('Paste some HTML first.')));
            }
            $paste_name = isset($request['page_name']) ? trim((string)$request['page_name']) : '';
            if ($paste_name === '') $paste_name = 'page';
            $paste_name = mb_substr(preg_replace('/[\/\\:*?"<>|]+/', '-', $paste_name), 0, 60);
            $paste_skip = array();
            if (!empty($request['skip_names']) && is_array($request['skip_names'])) {
                foreach ($request['skip_names'] as $sn) {
                    if (is_string($sn) && $sn !== '') $paste_skip[] = $sn;
                }
            }
            // A design without a framework keeps the markup's own
            // Bootstrap / jQuery links (pg_designer_import_zip()).
            $paste_res = pg_designer_import_single_html(
                $paste_html, $paste_name . '.html', $user, array('skip_names' => $paste_skip,
                    'keep_framework' => isset($request['framework_bootstrap']) && (string)$request['framework_bootstrap'] === '0'));
            if (!empty($paste_res['errors'])) {
                respond(array('status' => 'error', 'message' => implode(' ', $paste_res['errors'])));
            }
            log_activity(lang(array('string' => 'HTML import ({var:1}): {var:2} page(s)',
                'vars' => array($paste_name, count($paste_res['pages'])))), $_SESSION['sessionusername']);
            $paste_res['status'] = 'success';
            respond($paste_res);
            break;

        // Pasted HTML, as a fragment dropped into the page being edited.
        // Writes nothing and touches no design asset — see
        // pg_designer_import_fragment() for why that is not an omission.
        case 'import_fragment':
            include_once(PG_FUNCTIONS_DIR . '/includes/designer_import.php');
            $frag_html = isset($request['html']) ? (string)$request['html'] : '';
            if (strlen($frag_html) > 2000000) {
                respond(array('status' => 'error', 'message' => lang('The pasted content is too large.')));
            }
            $frag = pg_designer_import_fragment($frag_html, $user);
            respond(array(
                'status'   => 'success',
                'children' => $frag['children'],
                'warnings' => $frag['warnings'],
            ));
            break;

        // Live SEO check for the page open in the editor — the same two
        // halves the site-wide score is made of, run on what is on the
        // canvas RIGHT NOW rather than on the saved row:
        //   meta      pg_seo_evaluate_meta on the settings-panel fields
        //   structure pg_seo_analyze_html on the markup the editor
        //             generates (fragment mode: the <head> the renderer
        //             adds at request time is not there to judge)
        // Nothing is stored; the stored score is refreshed on save.
        case 'seo_check':
            require_once(PG_FUNCTIONS_DIR . '/seo.php');
            require_once(PG_FUNCTIONS_DIR . '/seo_structure.php');
            if (!pg_seo_schema_ready()) {
                respond(array('status' => 'error', 'message' => lang('SEO scoring is not available on this installation.')));
            }
            $page_id = isset($request['page_id']) ? (int)$request['page_id'] : 0;
            $folder_id = isset($request['page_folder']) ? (int)$request['page_folder'] : 0;
            $folder = $folder_id > 0 ? db_item("SELECT folder_access_control_type, folder_archived FROM folder WHERE folder_id = '$folder_id' LIMIT 1") : null;
            $row = array(
                'page_id'               => $page_id,
                'page_name'             => isset($request['page_name']) ? (string)$request['page_name'] : '',
                'page_title'            => isset($request['page_title']) ? (string)$request['page_title'] : '',
                'page_meta_description' => isset($request['page_meta_description']) ? (string)$request['page_meta_description'] : '',
                'page_search_keywords'  => isset($request['page_search_keywords']) ? (string)$request['page_search_keywords'] : '',
                'page_search'           => !empty($request['page_search']) ? '1' : '0',
                'page_type'             => 'standard',
                'sitemap'               => (!empty($request['page_sitemap']) && empty($request['page_noindex'])) ? '1' : '0',
                'folder_public'         => ($folder ? ($folder['folder_access_control_type'] == 'public' || $folder['folder_access_control_type'] == '') : true),
                'folder_archived'       => ($folder ? !empty($folder['folder_archived']) : false),
            );
            $entity  = pg_seo_normalize_entity('page', $row);
            $context = pg_seo_build_context('page', $page_id > 0 ? array($page_id) : array());
            $meta    = pg_seo_evaluate_meta($entity, $context);
            $checks  = array();
            foreach ((isset($meta['analysis']['checks']) ? $meta['analysis']['checks'] : array()) as $c) {
                $checks[] = array('code' => $c['c'], 'status' => $c['s'], 'weight' => (int)$c['w'], 'earned' => (int)$c['e'], 'label' => pg_seo_check_label($c));
            }

            $html = isset($request['html']) ? (string)$request['html'] : '';
            if (strlen($html) > 2000000) $html = substr($html, 0, 2000000);
            $findings = array();
            $structure_score = null;
            if (trim($html) !== '') {
                $raw = pg_seo_analyze_html($html, 'fragment', array(
                    'title'         => $row['page_title'],
                    'in_sitemap'    => ($row['sitemap'] === '1'),
                    'open_graph'    => false,
                    'expect_jsonld' => false,
                ));
                foreach ($raw as $code => $f) {
                    $findings[] = array(
                        'code'        => $code,
                        'severity'    => $f['severity'],
                        'occurrences' => (int)$f['occurrences'],
                        'detail'      => (string)$f['detail'],
                        'label'       => pg_seo_issue_label($code, (int)$f['occurrences'], (string)$f['detail']),
                    );
                }
                // A fragment cannot answer the document-level checks, so
                // pg_seo_analyze_html() skips them and the fragment scores
                // against a shorter rule set than the stored score did. Carry
                // the stored answers over - the same thing this endpoint
                // already does for the link and speed groups - so the canvas
                // and the pages list report the same number, and the ones the
                // operator can act on from here (the title and description in
                // Page Settings, a missing <main>) are listed rather than
                // silently priced in.
                if ($page_id > 0 && function_exists('pg_seo_document_only_codes') && function_exists('pg_seo_load_issues')) {
                    $carried = pg_seo_document_only_codes();
                    foreach (pg_seo_load_issues('page', $page_id) as $issue) {
                        if (!in_array($issue['code'], $carried, TRUE)) continue;
                        if (isset($raw[$issue['code']])) continue;
                        $raw[$issue['code']] = array(
                            'severity'    => $issue['severity'],
                            'occurrences' => (int) $issue['occurrences'],
                            'detail'      => (string) $issue['detail'],
                        );
                        $findings[] = array(
                            'code'        => $issue['code'],
                            'severity'    => $issue['severity'],
                            'occurrences' => (int) $issue['occurrences'],
                            'detail'      => (string) $issue['detail'],
                            'label'       => pg_seo_issue_label($issue['code'], (int) $issue['occurrences'], (string) $issue['detail']),
                            'stored'      => TRUE,
                        );
                    }
                }
                $structure_score = pg_seo_structure_score($raw);
            }
            // The stored score is composed from FOUR groups, not two:
            // meta, structure, links and speed. Composing only the first
            // two here gave the editor a different arithmetic from the
            // pages list - the designer read 48 on the canvas and 46 in
            // the list, with nothing on screen to explain the gap.
            //
            // Meta and structure come from what is on the canvas right
            // now; links and speed cannot - links are counted from the
            // saved markup and speed from measurements that arrive with
            // traffic - so those two are read exactly as the stored
            // score read them. The number therefore moves on save only
            // when the save itself changed the link graph.
            $link_score = null;
            if ($page_id > 0 && pg_seo_link_schema_ready()) {
                $stored = db_value("SELECT seo_link_score FROM page WHERE page_id = '$page_id' LIMIT 1");
                if ($stored !== null && $stored !== '') $link_score = (int)$stored;
            }
            $speed_score = null;
            if ($page_id > 0) {
                $speed = pg_seo_evaluate_speed(
                    isset($context['speed'][$page_id]) ? $context['speed'][$page_id] : null);
                $speed_score = $speed['score'];
            }
            respond(array(
                'status'          => 'success',
                'score'           => pg_seo_compose($meta['score'], $structure_score, $link_score, $speed_score),
                'meta_score'      => $meta['score'],
                'structure_score' => $structure_score,
                'link_score'      => $link_score,
                'speed_score'     => $speed_score,
                'weights'         => pg_seo_group_weights($structure_score, $link_score, $speed_score),
                'checks'          => $checks,
                'findings'        => $findings,
            ));
            break;

        // Delete a design from the home screen list: its live pages go
        // to the recycle bin (each keeps its own tree, so it can be
        // restored and attached to another design with "Select Page"),
        // pages already in the bin stay there, then the style row goes.
        // A template opened in the editor (add_system_style.php?start=template):
        // its pages as unsaved tabs, with unique names, and the rows of
        // the system widgets they place. No page is written here - see
        // pg_design_template_prepare().
        // The editor's Preview: the system widgets its blob page cannot
        // draw, rendered from the editor's own trees and settings, inside
        // the page once it has been saved (pg_designer_preview_widgets()).
        // The canvas's real records: a listing widget's repeated part for
        // up to ten records, drawn after the card being designed
        // (pg_designer_widget_ghosts()).
        case 'widget_ghosts':
            respond(array(
                'status' => 'success',
                'rows'   => pg_designer_widget_ghosts(
                    isset($request['page_id']) ? (int)$request['page_id'] : 0,
                    (isset($request['widget']) && is_array($request['widget'])) ? $request['widget'] : array(),
                    isset($request['limit']) ? (int)$request['limit'] : 10
                ),
            ));
            break;

        // The canvas's read-only picture of a classic page's system region
        // drawn by its custom layout, as the server draws it for the
        // signed-in user (pg_layout_region_preview()). The layout editor's
        // right (role 1 or better), as layout_code asks; '' for a page that
        // draws no custom layout.
        case 'legacy_region_preview':
            if ((int)$user['role'] > 1) {
                respond(array('status' => 'error', 'message' => lang('Permission denied.')));
            }
            require_once(PG_FUNCTIONS_DIR . '/includes/fn/layouts.php');
            respond(array(
                'status' => 'success',
                'html'   => pg_layout_region_preview(isset($request['page_id']) ? (int)$request['page_id'] : 0),
            ));
            break;

        // A page's own regions as blocks of its tree, one per page region
        // node the editor counts on it (pg_page_region_blocks()). Placing
        // them changes the page's structure: the designer's right, as the
        // other region conversions ask. Nothing is written here; the page's
        // Save does.
        case 'page_regions_import':
            $pr_page = isset($request['page_id']) ? (int)$request['page_id'] : 0;
            if ((int)$user['role'] > 1) {
                respond(array('status' => 'error', 'message' => lang('Permission denied.')));
            }
            if ($pr_page <= 0 || !db_value("SELECT page_id FROM page WHERE page_id = '$pr_page' LIMIT 1")) {
                respond(array('status' => 'error', 'message' => lang('Record not found.')));
            }
            require_once(PG_FUNCTIONS_DIR . '/includes/designer_region_import.php');
            $pr = pg_page_region_blocks($pr_page, isset($request['count']) ? (int)$request['count'] : 0);
            respond(array('status' => 'success', 'blocks' => $pr['blocks'], 'warnings' => $pr['warnings']));
            break;

        // A page the user may not know is drawn as an unsaved one: a
        // comments widget then shows no thread of it. The comment settings
        // are the page's as the editor holds them, saved or not, on a page
        // the user may edit; any other page keeps its stored ones
        // (pg_designer_preview_input()).
        case 'preview_widgets':
            $pv_page = isset($request['page_id']) ? (int)$request['page_id'] : 0;
            $pv = pg_designer_preview_input(
                $pv_page,
                isset($request['comment_settings']) ? $request['comment_settings'] : null,
                ($pv_page > 0) ? pg_designer_page_access($pv_page, $user) : 'edit'
            );
            respond(array(
                'status' => 'success',
                'html'   => pg_designer_preview_widgets(
                    $pv['page_id'],
                    (isset($request['widgets']) && is_array($request['widgets'])) ? $request['widgets'] : array(),
                    $pv['comment_settings']
                ),
            ));
            break;

        case 'template_prepare':
            $tp = pg_design_template_prepare(isset($request['template']) ? (string)$request['template'] : '', $user);
            if (empty($tp['ok'])) {
                respond(array('status' => 'error', 'message' => isset($tp['error']) ? $tp['error'] : lang('An error occurred')));
            }
            unset($tp['ok']);
            $tp['status'] = 'success';
            respond($tp);
            break;

        // Design settings › "Turn this design into a template": the
        // design's saved pages, with everything they place, become a
        // template under Choose a Template
        // (pg_design_template_from_style()). The card is read from the
        // new row itself: the template list may already be cached in
        // this request.
        case 'template_from_design':
            $tf = pg_design_template_from_style(
                isset($request['style_id']) ? (int)$request['style_id'] : 0,
                array(
                    'name'        => isset($request['name']) && is_scalar($request['name']) ? (string)$request['name'] : '',
                    'description' => isset($request['description']) && is_scalar($request['description']) ? (string)$request['description'] : '',
                ),
                $user
            );
            if (empty($tf['ok'])) {
                respond(array('status' => 'error', 'message' => isset($tf['error']) ? $tf['error'] : lang('An error occurred')));
            }
            $tf_tpl = pg_design_template_custom_get($tf['template_id']);
            respond(array(
                'status'   => 'success',
                'template' => $tf_tpl ? pg_design_template_summary($tf_tpl) : null,
                'message'  => lang(array('string' => 'The template "{var:1}" is ready. You will find it under Choose a Template.', 'vars' => array((string)$tf['template']['name']))),
            ));
            break;

        // A template made from a design, deleted from the template
        // dialog. The shipped ones cannot be (pg_design_template_custom_delete()).
        case 'template_delete':
            $tdel = pg_design_template_custom_delete(isset($request['template']) && is_scalar($request['template']) ? (string)$request['template'] : '', $user);
            if (empty($tdel['ok'])) {
                respond(array('status' => 'error', 'message' => $tdel['error']));
            }
            respond(array('status' => 'success', 'message' => lang('The template was deleted.')));
            break;

        // Leaving the editor without publishing a template's tabs: the
        // widgets and shared components it made for them go, unless a
        // page places them (pg_design_template_discard()). Sent as a
        // beacon from `pagehide`, so nobody reads the answer.
        case 'template_discard':
            $td_deleted = pg_design_template_discard(isset($request['ids']) && is_array($request['ids']) ? $request['ids'] : array());
            respond(array('status' => 'success', 'deleted' => $td_deleted));
            break;

        // ── TAB LAYOUT ───────────────────────────────────────────────
        // The order of the page tabs and their groups (2026.4.8). Written
        // the moment the operator rearranges the strip, apart from Save:
        // it is how the editor looks, not what the site shows.
        case 'tab_layout':
            $tl = pg_designer_tab_layout_save(
                isset($request['style_id']) ? (int)$request['style_id'] : 0,
                isset($request['layout']) ? $request['layout'] : null,
                $user);
            if (!$tl['ok']) {
                respond(array('status' => 'error', 'message' => $tl['error']));
            }
            respond(array('status' => 'success', 'layout' => $tl['layout']));
            break;

        // Every page of a design off the site, or its drafts back on it,
        // from the designs list (pg_designer_design_set_draft()). The
        // answer carries the design's new status block.
        case 'design_publish':
            $dp_style_id = isset($request['style_id']) ? (int)$request['style_id'] : 0;
            $dp_off      = !empty($request['draft']);
            $dp = pg_designer_design_set_draft($dp_style_id, $dp_off, $user);
            if (!$dp['ok']) {
                respond(array('status' => 'error', 'message' => $dp['error']));
            }
            if ($dp['changed'] === 0) {
                $dp_message = $dp_off ? lang('Nothing to take off the site.') : lang('Nothing to publish.');
            } elseif ($dp_off) {
                $dp_message = ($dp['changed'] > 1) ? str_replace('{var}', $dp['changed'], lang('{var} pages are kept as drafts, off the site.')) : lang('The page is kept as a draft, off the site.');
            } else {
                $dp_message = ($dp['changed'] > 1) ? str_replace('{var}', $dp['changed'], lang('{var} pages were published.')) : lang('The page was published.');
            }
            if ($dp['home'] > 0) $dp_message .= ' ' . lang('The home page stays on the site.');
            require_once(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
            $dp_counts = pg_designer_design_draft_counts(array($dp_style_id));
            $dp_row = array(
                'style_id'    => $dp_style_id,
                'style_name'  => (string)db_value("SELECT style_name FROM style WHERE style_id = '$dp_style_id' LIMIT 1"),
                'page_count'  => $dp_counts[$dp_style_id]['pages'],
                'draft_count' => $dp_counts[$dp_style_id]['drafts'],
                'home_count'  => $dp_counts[$dp_style_id]['home'],
            );
            // A page moved by hand loses the scheduled move it made
            // unnecessary (pg_page_draft_move()): the badge and the chips'
            // clocks are drawn from what is stored now.
            $dp_sched = function_exists('pg_page_schedule_design_counts') ? pg_page_schedule_design_counts(array($dp_style_id)) : array();
            $dp_row['scheduled_count'] = isset($dp_sched[$dp_style_id]) ? $dp_sched[$dp_style_id]['scheduled'] : 0;
            $dp_row['scheduled_next']  = isset($dp_sched[$dp_style_id]) ? $dp_sched[$dp_style_id]['next'] : 0;
            $dp_scheduled = array();
            if ($dp_row['scheduled_count'] > 0 && function_exists('pg_page_schedule_times')) {
                $dp_page_ids = array_map('intval', (array)db_values("SELECT page_id FROM page WHERE page_style = '$dp_style_id'"));
                foreach (pg_page_schedule_times($dp_page_ids) as $dp_pid => $dp_t) {
                    $dp_scheduled[(string)$dp_pid] = ($dp_t['publish_at'] > 0)
                        ? lang(array('string' => 'Goes on the site {var:1}', 'vars' => array(pg_page_schedule_label($dp_t['publish_at']))))
                        : lang(array('string' => 'Comes off the site {var:1}', 'vars' => array(pg_page_schedule_label($dp_t['unpublish_at']))));
                }
            }
            respond(array(
                'status'    => 'success',
                'message'   => $dp_message,
                'changed'   => $dp['changed'],
                'drafts'    => $dp_row['draft_count'],
                'cell'      => pg_designer_list_status_cell($dp_row),
                'scheduled' => (object)$dp_scheduled,
            ));
            break;

        // A design renamed from the designs list (pg_designer_rename_design()).
        case 'design_rename':
            $dr = pg_designer_rename_design(
                isset($request['style_id']) ? (int)$request['style_id'] : 0,
                (isset($request['name']) && is_scalar($request['name'])) ? (string)$request['name'] : '',
                $user);
            if (!$dr['ok']) {
                respond(array('status' => 'error', 'message' => $dr['error']));
            }
            respond(array('status' => 'success', 'name' => $dr['name'], 'message' => lang('The design was renamed.')));
            break;

        case 'design_delete':
            $style_id = isset($request['style_id']) ? (int)$request['style_id'] : 0;
            $style = $style_id > 0 ? db_item("SELECT style_id, style_layout FROM style WHERE style_id = '$style_id' LIMIT 1") : null;
            if (!$style) {
                respond(array('status' => 'error', 'message' => lang('The style could not be found.')));
            }
            if ($style['style_layout'] !== 'visual_designer') {
                respond(array('status' => 'error', 'message' => lang('Only designs made with the visual editor can be deleted here.')));
            }
            $dd = pg_designer_delete_design($style_id, $user);
            if (!$dd['ok']) {
                respond(array('status' => 'error', 'message' => $dd['error']));
            }
            respond(array('status' => 'success', 'binned' => $dd['binned'], 'folders' => $dd['folders'], 'recycled' => $dd['recycled'], 'message' => pg_designer_delete_design_message($dd)));
            break;

        // ── REVISIONS ────────────────────────────────────────────────
        // The published revisions of a page (includes/fn/page_revisions.php):
        // the list for the History panel, without trees, and one revision
        // with its tree for compare and restore. Reading the history of a
        // page takes the right to edit it: a revision carries the whole
        // tree, which a page's viewers are not entitled to.
        case 'revisions_list':
            $rv_page = isset($request['page_id']) ? (int)$request['page_id'] : 0;
            if ($rv_page <= 0 || !pg_designer_revision_page_ok($rv_page, $user)) {
                respond(array('status' => 'error', 'message' => lang('Page not found.')));
            }
            if (!pg_page_revisions_ready()) {
                respond(array('status' => 'success', 'ready' => false, 'revisions' => array(), 'more' => false));
            }
            $rv_before = isset($request['before_no']) ? (int)$request['before_no'] : 0;
            $rv_list   = pg_page_revisions_list($rv_page, 50, $rv_before);
            respond(array('status' => 'success', 'ready' => true, 'revisions' => $rv_list, 'more' => (count($rv_list) === 50)));
            break;

        case 'revision_get':
            $rv_page = isset($request['page_id']) ? (int)$request['page_id'] : 0;
            $rv_no   = isset($request['revision_no']) ? (int)$request['revision_no'] : 0;
            if ($rv_page <= 0 || !pg_designer_revision_page_ok($rv_page, $user)) {
                respond(array('status' => 'error', 'message' => lang('Page not found.')));
            }
            $rv = pg_page_revision_get($rv_page, $rv_no);
            if (!$rv) {
                respond(array('status' => 'error', 'message' => lang('The revision could not be found.')));
            }
            $rv_tree = json_decode($rv['tree_json']);
            unset($rv['tree_json'], $rv['revision_id']);
            $rv['tree'] = is_object($rv_tree) ? $rv_tree : null;
            respond(array('status' => 'success', 'revision' => $rv));
            break;

        // Save a stylesheet as a theme: a design CSS file in the file
        // manager (design = 1 is what get_theme_options() lists), written
        // from the Themes panel's light/dark variable blocks. Nothing is
        // changed on the design itself — the editor selects the new
        // file in the theme list and the operator saves as usual.
        case 'theme_save':
            $name = isset($request['name']) ? trim((string)$request['name']) : '';
            $css  = isset($request['css'])  ? (string)$request['css'] : '';
            if ($name === '' || trim($css) === '') {
                respond(array('status' => 'error', 'message' => lang('A theme name and some CSS are required.')));
            }
            if (strlen($css) > 512000) {
                respond(array('status' => 'error', 'message' => lang('The stylesheet is too large.')));
            }
            if (!preg_match('/\.css$/i', $name)) $name .= '.css';
            $file_name = prepare_file_name($name);
            if ($file_name === '' || $file_name === '.css') {
                respond(array('status' => 'error', 'message' => lang('Please enter a valid theme name.')));
            }
            if (check_name_availability(array('name' => $file_name)) == false) {
                respond(array('status' => 'error', 'message' => lang(array('string' => 'The Theme ({var:1}) already exists.', 'vars' => $file_name))));
            }
            if (@file_put_contents(FILE_DIRECTORY_PATH . '/' . $file_name, $css) === false) {
                respond(array('status' => 'error', 'message' => lang(array('string' => '{var:1} could not be written to disk.', 'vars' => $file_name))));
            }
            $folder_id = (int)db_value("SELECT folder_id FROM folder WHERE folder_parent = '0' ORDER BY folder_id LIMIT 1");
            db("INSERT INTO files (name, folder, description, type, size, user, design, theme, timestamp)
                    VALUES ('" . e($file_name) . "', '$folder_id', '', 'css', '" . (int)strlen($css) . "', '" . (int)$user['id'] . "', '1', '0', UNIX_TIMESTAMP())");
            $theme_id = (int)mysqli_insert_id(db::$con);
            log_activity(lang(array('string' => 'theme ({var:1}) was created', 'vars' => $file_name)), $_SESSION['sessionusername']);
            respond(array('status' => 'success', 'id' => $theme_id, 'name' => $file_name));
            break;

        // The look builder and the palette builder of Settings > Design >
        // Theme (includes/fn/design_themes.php). They send choices - a
        // base look and a few picks, or two colours - never CSS; the
        // stylesheet is written here. *_css answers with the stylesheet
        // for the canvas to try on; *_save writes it to the file manager
        // as a design CSS file every design can wear.
        case 'theme_look_css':
        case 'theme_look_save':
            $base     = isset($request['base']) ? (string)$request['base'] : '';
            $controls = (isset($request['controls']) && is_array($request['controls'])) ? $request['controls'] : array();
            $name     = isset($request['name']) ? trim((string)$request['name']) : '';
            $looks    = pg_design_looks();
            if (!isset($looks[$base])) {
                respond(array('status' => 'error', 'message' => lang('Choose the look to start from.')));
            }
            $css = pg_design_custom_look_css($base, $controls, $name !== '' ? $name : $looks[$base]['name']);
            if ($css === false) {
                respond(array('status' => 'error', 'message' => lang('The look could not be made.')));
            }
            if ($sub === 'theme_look_css') {
                respond(array('status' => 'success', 'css' => $css));
            }
            $saved = pg_design_theme_save_file('look', $name, $css, $user['id']);
            if (!is_array($saved)) {
                respond(array('status' => 'error', 'message' => $saved));
            }
            log_activity(lang(array('string' => 'theme ({var:1}) was created', 'vars' => $saved['file'])), $_SESSION['sessionusername']);
            respond(array('status' => 'success', 'key' => 'file-' . $saved['id'], 'name' => $saved['name'], 'url' => $saved['url'],
                'base' => $base, 'controls' => $controls));
            break;

        case 'theme_palette_css':
        case 'theme_palette_save':
            $primary   = isset($request['primary'])   ? trim((string)$request['primary'])   : '';
            $secondary = isset($request['secondary']) ? trim((string)$request['secondary']) : '';
            $name      = isset($request['name']) ? trim((string)$request['name']) : '';
            $css = pg_design_palette_css($primary, $secondary, $name);
            if ($css === false) {
                respond(array('status' => 'error', 'message' => lang('Enter both colours as #RRGGBB.')));
            }
            if ($sub === 'theme_palette_css') {
                respond(array('status' => 'success', 'css' => $css));
            }
            $p = _pg_theme_hex(_pg_theme_rgb($primary));
            $s = _pg_theme_hex(_pg_theme_rgb($secondary));
            $css = pg_design_theme_header(array('kind' => 'palette', 'name' => $name, 'primary' => $p, 'secondary' => $s)) . "\n" . $css;
            $saved = pg_design_theme_save_file('palette', $name, $css, $user['id']);
            if (!is_array($saved)) {
                respond(array('status' => 'error', 'message' => $saved));
            }
            log_activity(lang(array('string' => 'theme ({var:1}) was created', 'vars' => $saved['file'])), $_SESSION['sessionusername']);
            respond(array('status' => 'success', 'key' => 'file-' . $saved['id'], 'name' => $saved['name'], 'url' => $saved['url'],
                'primary' => $p, 'secondary' => $s));
            break;

        // ── PROTOTYPE SHARE LINKS ────────────────────────────────────
        // The Share prototype dialog (includes/fn/prototype_links.php): a
        // design's links, a new one, a link's address shown again, and
        // revoking one. Designers only, like the Schedule section: the
        // list above does not open them to a content-level operator.
        case 'prototype_links':
        case 'prototype_link_create':
        case 'prototype_link_address':
        case 'prototype_link_revoke':
            require_once(PG_FUNCTIONS_DIR . '/includes/fn/prototype.php');
            require_once(PG_FUNCTIONS_DIR . '/includes/fn/prototype_links.php');
            respond(pg_proto_links_panel($sub, $request, $user));
            break;

        default:
            respond(array('status' => 'error', 'message' => lang('Unknown action.')));
    }
}

function pg_panel_designer_file($request, $action)
{
    // DESIGNER FILES
    // Visual Pinegrap Editor's "New CSS/JS/JSON File" actions create a real
    // file (disk + `files` row) at click time so the asset is available
    // immediately in View Files and is referenced via a stable URL — same
    // mechanism create_file.php uses for manual file creation.

    // Kept global: helpers read the session user through `global $user`;
    // see includes/panel/actions.php.
    global $user;

    validate_token();
    $user = validate_user();

    // Admin (0) and designer (1) only — symmetric with shared_component.
    // Manager is 2 and does not shape the design.
    if ((int) $user['role'] > 1) {
        respond(array('status' => 'error', 'message' => lang('Permission denied.')));
    }

    if (!defined('FILE_DIRECTORY_PATH')) {
        respond(array('status' => 'error', 'message' => 'FILE_DIRECTORY_PATH is not defined.'));
    }

    $df_sub = isset($request['sub_action']) ? $request['sub_action'] : '';

    switch ($df_sub) {

        // LIST_FOLDERS — return both flat data AND the rendered <option>
        // HTML produced by select_folder() so the assets panel modal can
        // either dump the HTML (matching add_file.php's exact look:
        // hierarchical indent + access-control colour + [ARCHIVED] tag)
        // or render its own UI from the data.
        case 'list_folders':
            $df_options_html = select_folder(0);
            respond(array(
                'status'       => 'success',
                'options_html' => $df_options_html,
            ));
            break;

        case 'create':
            $df_type   = isset($request['type'])   ? strtolower(trim($request['type'])) : '';
            $df_base   = isset($request['name'])   ? trim($request['name'])             : '';
            $df_folder = isset($request['folder']) ? (int) $request['folder']            : 0;
            // Design flag (1 = design file). Only honoured for admin (0) /
            // designer (1) — basic users in folder ACLs shouldn't be able
            // to upload "design files" they cannot manage. Manager (2)
            // never reaches this endpoint via the assets panel anyway
            // because they don't have designer-area access.
            $df_design = !empty($request['design']) ? 1 : 0;
            if ((int) $user['role'] > 1) {
                $df_design = 0;
            }
            // Optional file description — same column View Files surfaces.
            $df_desc = isset($request['description']) ? trim((string)$request['description']) : '';

            if (!in_array($df_type, array('css', 'js', 'json'), true)) {
                respond(array('status' => 'error', 'message' => 'Invalid file type.'));
            }
            // Folder is required — view_files.php joins on folder.folder_id
            // and excludes rows where the join misses, so a folder=0 file
            // is invisible in the file manager. Reject the create instead
            // of silently writing an unreachable file.
            if ($df_folder <= 0) {
                respond(array('status' => 'error', 'message' => 'Folder is required.'));
            }
            $df_folder_ok = db_value("SELECT folder_id FROM folder WHERE folder_id = '" . (int)$df_folder . "' LIMIT 1");
            if (!$df_folder_ok) {
                respond(array('status' => 'error', 'message' => 'Folder not found.'));
            }
            // Role-3 (contributor) must actually have access to the folder.
            if ((int)$user['role'] === 3) {
                $df_acl = get_folders_that_user_has_access_to($user['id']);
                if (!in_array($df_folder, $df_acl)) {
                    respond(array('status' => 'error', 'message' => lang('Permission denied.')));
                }
            }
            // Default base name when caller omits it
            if ($df_base === '') {
                $df_base = ($df_type === 'css' ? 'style' : ($df_type === 'js' ? 'script' : 'data')) . '.' . $df_type;
            }
            // Force the extension to match the requested type
            $df_ext = strtolower(pathinfo($df_base, PATHINFO_EXTENSION));
            if ($df_ext !== $df_type) {
                $df_base = preg_replace('/\.[^.]+$/', '', $df_base) . '.' . $df_type;
            }
            $df_base = prepare_file_name($df_base);
            // get_unique_name walks the page/file/folder name space so the URL
            // path won't collide with an existing page route either.
            $df_name = get_unique_name(array('name' => $df_base, 'type' => 'file'));

            // Caller may seed initial content (used by the upload flow,
            // which already has the file body in memory). Empty string and
            // missing key both fall back to a type-appropriate default.
            if (array_key_exists('content', $request) && is_string($request['content']) && $request['content'] !== '') {
                $df_content = $request['content'];
            } else {
                $df_content = ($df_type === 'json') ? '{}' : '';
            }

            $df_path = FILE_DIRECTORY_PATH . '/' . $df_name;
            $df_handle = @fopen($df_path, 'w');
            if (!$df_handle) {
                respond(array('status' => 'error', 'message' => 'Could not create file on disk.'));
            }
            if ($df_content !== '') fwrite($df_handle, $df_content);
            fclose($df_handle);
            $df_size = (int) @filesize($df_path);

            db("INSERT INTO files (name, folder, description, type, size, design, user, timestamp)
                    VALUES (
                        '" . e($df_name) . "',
                        '" . (int)$df_folder . "',
                        '" . e($df_desc) . "',
                        '" . e($df_type) . "',
                        '" . (int)$df_size . "',
                        '" . (int)$df_design . "',
                        '" . (int)$user['id'] . "',
                        UNIX_TIMESTAMP()
                    )");

            log_activity(lang(array('string' => 'file ({var:1}) was created', 'vars' => $df_name)), $_SESSION['sessionusername']);

            respond(array(
                'status'  => 'success',
                'name'    => $df_name,
                'url'     => OUTPUT_PATH . $df_name,
                'type'    => $df_type,
                'content' => $df_content,
            ));
            break;

        // READ — return the on-disk content of a managed file. Bound to
        // `files` rows so we never serve arbitrary paths from the host.
        case 'read':
            $df_name = isset($request['name']) ? trim($request['name']) : '';
            if ($df_name === '') {
                respond(array('status' => 'error', 'message' => 'Name is required.'));
            }
            $df_name_safe = prepare_file_name(basename($df_name));
            if ($df_name_safe === '' || $df_name_safe !== $df_name) {
                respond(array('status' => 'error', 'message' => 'Invalid file name.'));
            }
            $df_row = db_value("SELECT type FROM files WHERE name = '" . e($df_name_safe) . "' LIMIT 1");
            if (!$df_row && $df_row !== '0') {
                respond(array('status' => 'error', 'message' => 'File is not registered in files table.'));
            }
            $df_path = FILE_DIRECTORY_PATH . '/' . $df_name_safe;
            if (!file_exists($df_path)) {
                respond(array('status' => 'error', 'message' => 'File missing on disk.'));
            }
            $df_content = @file_get_contents($df_path);
            if ($df_content === false) {
                respond(array('status' => 'error', 'message' => 'Could not read file.'));
            }
            respond(array(
                'status'  => 'success',
                'name'    => $df_name_safe,
                'content' => $df_content,
            ));
            break;

        // DELETE — destructive: remove the on-disk file + its `files` row.
        // The caller should confirm with the user first; this endpoint
        // does not prompt. Limited to rows already in `files` so callers
        // can't unlink arbitrary host paths.
        case 'delete':
            $df_name = isset($request['name']) ? trim($request['name']) : '';
            if ($df_name === '') {
                respond(array('status' => 'error', 'message' => 'Name is required.'));
            }
            $df_name_safe = prepare_file_name(basename($df_name));
            if ($df_name_safe === '' || $df_name_safe !== $df_name) {
                respond(array('status' => 'error', 'message' => 'Invalid file name.'));
            }
            $df_row_q = mysqli_query(db::$con,
                "SELECT id, folder FROM files WHERE name = '" . e($df_name_safe) . "' LIMIT 1");
            if (!$df_row_q || mysqli_num_rows($df_row_q) === 0) {
                respond(array('status' => 'error', 'message' => 'File is not registered in files table.'));
            }
            $df_row = mysqli_fetch_assoc($df_row_q);
            // Role-3 (contributor) needs explicit access to the file's folder.
            if ((int)$user['role'] === 3) {
                $df_acl = get_folders_that_user_has_access_to($user['id']);
                if (!in_array($df_row['folder'], $df_acl)) {
                    respond(array('status' => 'error', 'message' => lang('Permission denied.')));
                }
            }
            $df_path = FILE_DIRECTORY_PATH . '/' . $df_name_safe;
            if (file_exists($df_path)) {
                if (!@unlink($df_path)) {
                    respond(array('status' => 'error', 'message' => 'Could not delete file from disk.'));
                }
            }
            db("DELETE FROM files WHERE id = '" . (int)$df_row['id'] . "' LIMIT 1");
            log_activity(lang(array('string' => 'file ({var:1}) was deleted', 'vars' => $df_name_safe)), $_SESSION['sessionusername']);
            respond(array('status' => 'success', 'name' => $df_name_safe));
            break;

        // SAVE — overwrite an existing managed file. Limited to file rows
        // already present in `files` (so callers can't write arbitrary paths).
        case 'save':
            $df_name = isset($request['name']) ? trim($request['name']) : '';
            $df_content = isset($request['content']) ? (string)$request['content'] : '';
            if ($df_name === '') {
                respond(array('status' => 'error', 'message' => 'Name is required.'));
            }
            $df_name_safe = prepare_file_name(basename($df_name));
            if ($df_name_safe === '' || $df_name_safe !== $df_name) {
                respond(array('status' => 'error', 'message' => 'Invalid file name.'));
            }
            $df_exists = db_value("SELECT COUNT(*) FROM files WHERE name = '" . e($df_name_safe) . "'");
            if (!$df_exists) {
                respond(array('status' => 'error', 'message' => 'File is not registered in files table.'));
            }
            $df_path = FILE_DIRECTORY_PATH . '/' . $df_name_safe;
            $df_written = @file_put_contents($df_path, $df_content);
            if ($df_written === false) {
                respond(array('status' => 'error', 'message' => 'Could not write file.'));
            }
            db("UPDATE files SET size = '" . (int)strlen($df_content) . "', timestamp = UNIX_TIMESTAMP()
                    WHERE name = '" . e($df_name_safe) . "'");
            respond(array('status' => 'success', 'name' => $df_name_safe, 'size' => (int)strlen($df_content)));
            break;

        default:
            respond(array('status' => 'error', 'message' => 'Unknown sub_action.'));
    }
}

function pg_panel_dynamic_region_code($request, $action)
{
    // The visual designer's code tab of a dynamic region (the region node
    // names it). The code runs as PHP on every page that places the region,
    // so the right is the one edit_dynamic_region.php asks for, and the row
    // and the log are written as that screen writes them.

    // Kept global: helpers read the session user through `global $user`;
    // see includes/panel/actions.php.
    global $user;

    validate_token();
    $user = validate_user();

    // Administrators only, as the classic screen
    // (validate_area_access($user, 'administrator')). The general gate has
    // already let designers (1) through; they place the region but do not
    // write its code.
    if ((int) $user['role'] !== 0) {
        respond(array('status' => 'error', 'message' => lang('Permission denied.')));
    }

    $name = (isset($request['name']) && is_string($request['name'])) ? $request['name'] : '';
    $region = ($name !== '')
        ? db_item("SELECT dregion_id AS id, dregion_name AS name, dregion_code AS code FROM dregion WHERE dregion_name = '" . escape($name) . "'")
        : false;

    if (!$region) {
        respond(array('status' => 'error', 'message' => lang('Record not found.')));
    }

    $sub = isset($request['sub_action']) ? $request['sub_action'] : '';

    if ($sub === 'read') {
        respond(array('status' => 'success', 'id' => (int) $region['id'], 'name' => $region['name'], 'content' => (string) $region['code']));
    }

    if ($sub === 'save') {
        if (!isset($request['content']) || !is_string($request['content'])) {
            respond(array('status' => 'error', 'message' => lang('Invalid request.')));
        }

        db(
            "UPDATE dregion
            SET
                dregion_code = '" . escape($request['content']) . "',
                dregion_user = '" . (int) $user['id'] . "',
                dregion_timestamp = UNIX_TIMESTAMP()
            WHERE dregion_id = '" . (int) $region['id'] . "'"
        );

        log_activity(lang(array('string' => '{var:1} ({var:2}) was modified', 'vars' => array(lang('dynamic region'), $region['name']))), $_SESSION['sessionusername']);

        respond(array('status' => 'success'));
    }

    respond(array('status' => 'error', 'message' => lang('Invalid action.')));
}

function pg_panel_layout_code($request, $action)
{
    // The visual designer's code tab of a page's custom layout (the page's
    // system region opens it). The layout is a PHP file the page render
    // includes, so the right is the one the classic layout editor
    // (page_designer.php?type=layout, get_layout, update_layout) has: the
    // general gate's role 1 or better, checked here again.

    // Kept global: helpers read the session user through `global $user`;
    // see includes/panel/actions.php.
    global $user;

    validate_token();
    $user = validate_user();

    if ((int) $user['role'] > 1) {
        respond(array('status' => 'error', 'message' => lang('Permission denied.')));
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/layouts.php');

    $page_id = isset($request['page_id']) ? $request['page_id'] : '';
    $page = (pg_layout_file_name($page_id) !== '')
        ? db_item("SELECT page_type AS type, layout_type FROM page WHERE page_id = '" . (int) $page_id . "'")
        : false;

    if (!$page) {
        respond(array('status' => 'error', 'message' => lang('Record not found.')));
    }

    // Only a page that draws a custom layout has one to edit.
    if (($page['layout_type'] !== 'custom') || !check_if_page_type_supports_layout($page['type'])) {
        respond(array('status' => 'error', 'message' => lang('This page does not use a custom layout.')));
    }

    $sub = isset($request['sub_action']) ? $request['sub_action'] : '';

    if ($sub === 'read') {
        $layout = pg_layout_read($page_id);

        respond(array(
            'status' => 'success',
            'content' => $layout ? (string) $layout['content'] : '',
            'modified' => $layout ? (int) $layout['modified'] : 0,
            'read_only' => pg_hosted(),
        ));
    }

    // The generated layout, for the editor to show; nothing is saved until
    // the tab is.
    if ($sub === 'regenerate') {
        respond(array('status' => 'success', 'content' => (string) pg_layout_generated((int) $page_id)));
    }

    // A custom form page's layout as the elements of a custom_form widget
    // (includes/fn/layout_convert.php); nothing is written, the editor
    // places the widget beside the region.
    if ($sub === 'convert') {
        if ($page['type'] !== 'custom form') {
            respond(array('status' => 'error', 'message' => lang('Only a custom form layout can be converted.')));
        }

        require_once(PG_FUNCTIONS_DIR . '/includes/fn/layout_convert.php');

        $layout = pg_layout_read($page_id);
        $result = pg_layout_form_convert(
            $layout ? (string) $layout['content'] : '',
            pg_cf_load_page_fields((int) $page_id),
            array(
                'page_id' => (int) $page_id,
                'submit_label' => (string) db_value("SELECT submit_button_label FROM custom_form_pages WHERE page_id = '" . (int) $page_id . "'"),
                'generated' => (string) generate_form_layout_content(array('page_id' => (int) $page_id)),
            )
        );

        if (!$result['ok']) {
            respond(array('status' => 'error', 'message' => lang('The layout could not be read.')));
        }

        respond(array(
            'status' => 'success',
            'tree' => $result['tree'],
            'dropped' => $result['dropped'],
            'missing' => count($result['missing']),
        ));
    }

    if ($sub === 'save') {
        if (!isset($request['content']) || !is_string($request['content'])) {
            respond(array('status' => 'error', 'message' => lang('Invalid request.')));
        }

        $result = pg_layout_save($page_id, $request['content']);

        if (!$result['ok']) {
            respond(array('status' => 'error', 'message' => $result['message']));
        }

        respond(array('status' => 'success', 'modified' => (int) $result['modified']));
    }

    respond(array('status' => 'error', 'message' => lang('Invalid action.')));
}
