<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual designer screen, shared by add_system_style.php and
 * edit_system_style.php.
 *
 * One design (a `style` row with style_layout = 'visual_designer') owns any
 * number of pages through page.page_style. The design carries what every page
 * shares — stylesheets, scripts, fonts, theme, body classes — and each page
 * carries its own layout tree. The editor shows the pages as tabs; switching a
 * tab swaps the canvas, the undo stack and the per-page settings, while the
 * assets panel and the theme stay put.
 *
 * Two entry points, one screen: the "add" mode opens with a single unsaved
 * page tab and no style row yet; the first save creates both and the browser
 * is sent to the "edit" URL. Everything after that is identical, so the
 * markup and the POST handler live here rather than in two near-copies.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// ── Screen ──────────────────────────────────────────────────────────────────

/**
 * Echo the whole editor.
 *
 * $ctx keys:
 *   mode            'add' | 'edit'
 *   style_id        int (0 in add mode)
 *   style           shared fields: name, theme_id, collection,
 *                   social_networking_position, additional_body_classes,
 *                   style_head, style_empty_cell_width_percentage,
 *                   style_custom_css, style_custom_js, style_custom_fonts,
 *                   last_modified_timestamp, last_modified_username
 *   pages           array from pg_designer_load_pages() (may be empty in add mode)
 *   active_page_id  int
 *   liveform        liveform instance (already populated)
 *   user            validated user row
 *   send_to         return path
 *   from_pages      bool — opened from the pages list
 *   delete_button   ready HTML for the toolbar (edit mode only)
 */
function pg_designer_screen_render($ctx)
{
    $liveform = $ctx['liveform'];
    $user     = $ctx['user'];
    $mode     = $ctx['mode'];
    $style_id = (int)$ctx['style_id'];
    $style    = $ctx['style'];
    $pages    = is_array($ctx['pages']) ? $ctx['pages'] : array();
    $active   = (int)$ctx['active_page_id'];

    // Social networking position lives on the style.
    $output_modal_social = '';
    if (SOCIAL_NETWORKING == TRUE) {
        $output_modal_social =
            '<div class="mb-3">
                <label class="form-label small">' . lang('Social Networking') . '</label>
                ' . $liveform->output_field(array('type'=>'select', 'name'=>'social_networking_position', 'options'=>array(
                    'Top Left'    => 'top_left',
                    'Top Right'   => 'top_right',
                    'Bottom Left' => 'bottom_left',
                    'Bottom Right'=> 'bottom_right',
                    'Disabled'    => 'disabled',
                ), 'class'=>'form-select form-select-sm')) . '
            </div>';
    }

    // Search-engine switches — only where the columns exist.
    $output_noindex_switches = '';
    $output_noindex_hint = '';
    if (pg_page_noindex_ready() == TRUE) {
        $output_noindex_switches =
            '<div class="form-check form-switch">
                <input type="hidden" name="page_noindex" value="0">
                <input class="form-check-input sd-page-field" type="checkbox" name="page_noindex" id="pg_page_noindex" value="1" data-page-key="page_noindex">
                <label class="form-check-label small" for="pg_page_noindex">' . lang('Close to Search Engines (noindex)') . '</label>
            </div>
            <div class="form-check form-switch">
                <input type="hidden" name="page_nofollow" value="0">
                <input class="form-check-input sd-page-field" type="checkbox" name="page_nofollow" id="pg_page_nofollow" value="1" data-page-key="page_nofollow" disabled="disabled">
                <label class="form-check-label small" for="pg_page_nofollow">' . lang('Do Not Follow Links on This Page (nofollow)') . '</label>
            </div>';
        $output_noindex_hint =
            '<div class="form-text small mb-3" id="pg_noindex_hint" style="display:none">
                ' . lang('The page is served with a noindex robots tag, is blocked in robots.txt and is left out of the site map.') . '
                <span class="text-warning d-block">' . lang('A page blocked in robots.txt is not crawled, so the noindex tag on it is never read. Use this before a page reaches the results; a page that is already listed can take a while to drop out.') . '</span>
            </div>';
    }

    // ── Design payload for the editor ─────────────────────────────────────
    // Trees are decoded and re-encoded with JSON_HEX_TAG: raw JSON straight
    // from the database may contain "</script>" inside a content node and
    // would close the tag early.
    require_once(dirname(__FILE__) . '/designer_access.php');
    $access = pg_designer_access($user);

    $js_pages = array();
    $hidden_pages = 0;
    foreach ($pages as $p) {
        // A design can span folders. What the operator may see is decided by
        // the folder, not by the design — and the two answers differ: a page
        // they cannot edit but may know about is shown and does not open, a
        // page behind a private or membership folder is not listed at all,
        // because the name alone ("prices-2027") is the information.
        $page_access = pg_designer_page_access($p, $user);
        if ($page_access === 'hidden') { $hidden_pages++; continue; }

        $tree = null;
        $warn = false;
        if ($p['tree_json'] !== '') {
            // Object slots that an earlier save stored as `[]` reach the
            // editor as `{}` — see pg_designer_tree_decode().
            $tree = pg_designer_tree_decode($p['tree_json']);
            if ($tree === null) {
                $warn = true;
                log_activity(lang(array('string' => 'page ({var:1}) tree JSON could not be parsed on load — empty canvas shown', 'vars' => array($p['page_name']))), isset($_SESSION['sessionusername']) ? $_SESSION['sessionusername'] : '?');
            }
        }
        $js_pages[] = array(
            'key'                   => 'p' . (int)$p['page_id'],
            'page_id'               => (int)$p['page_id'],
            'page_name'             => (string)$p['page_name'],
            'page_folder'           => (int)$p['page_folder'],
            'page_title'            => (string)$p['page_title'],
            'page_meta_description' => (string)$p['page_meta_description'],
            'page_search'           => (int)$p['page_search'],
            'page_search_keywords'  => (string)$p['page_search_keywords'],
            'page_sitemap'          => (int)$p['page_sitemap'],
            'page_home'             => (int)$p['page_home'],
            'page_noindex'          => (int)$p['page_noindex'],
            'page_nofollow'         => (int)$p['page_nofollow'],
            'pg_comments'           => (int)$p['pg_comments'],
            'pg_comments_label'     => (string)$p['pg_comments_label'],
            'pg_comments_allow_new' => (int)$p['pg_comments_allow_new'],
            'pg_comments_rating'    => (int)$p['pg_comments_rating'],
            'tree'                  => $tree,
            'treeLoadWarning'       => $warn,
            // The page's form-level settings (name, notification, confirmation).
            // The fields themselves are the controls in the widget tree and
            // travel with it.
            'formSettings'          => function_exists('pg_cf_load_page_form_settings')
                                           ? pg_cf_load_page_form_settings((int)$p['page_id']) : array(),
            'access'                => $page_access,
        );
    }
    if (empty($js_pages)) {
        // Add mode, or a design that somehow lost its pages: open one blank
        // tab so the operator has somewhere to work. It is created on save.
        $js_pages[] = array(
            'key'                   => 'new1',
            'page_id'               => 0,
            'page_name'             => '',
            'page_folder'           => 0,
            'page_title'            => '',
            'page_meta_description' => '',
            'page_search'           => 1,
            'page_search_keywords'  => '',
            'page_sitemap'          => 1,
            'page_home'             => 0,
            'page_noindex'          => 0,
            'page_nofollow'         => 0,
            'pg_comments'           => 0,
            'pg_comments_label'     => '',
            'pg_comments_allow_new' => 1,
            'pg_comments_rating'    => 0,
            'tree'                  => null,
            'treeLoadWarning'       => false,
            'formSettings'          => array(),
            'access'                => 'edit',
        );
        $active = 0;
    }
    $active_key = 'p' . $active;
    $found_active = false;
    foreach ($js_pages as $jp) { if ($jp['key'] === $active_key) { $found_active = true; break; } }
    if (!$found_active) $active_key = $js_pages[0]['key'];

    $design_js = json_encode(array(
        'mode'          => $mode,
        'styleId'       => $style_id,
        'styleName'     => (string)$style['name'],
        'activeKey'     => $active_key,
        'pages'         => $js_pages,
        'canSetHome'    => ((int)$user['role'] < 3),
        'noindexReady'  => (pg_page_noindex_ready() == TRUE),
        'apiUrl'        => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/api.php',
        'editUrl'       => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_system_style.php',
        'exitUrl'       => pg_safe_back_url($ctx['send_to'], ($ctx['from_pages'] ? 'view_pages.php' : 'view_system_styles.php')),
        'access'        => $access,
        // Who is sitting here. The page lock names its holder by user id;
        // when that id is this one, the holder is another tab of the same
        // person and the editor may offer to take the page over.
        'userId'        => (int)$user['id'],
        // Site settings the member widgets follow. The front end drops a
        // control whose feature is off; the canvas fades the same control
        // so the designer sees what the visitor will get.
        'siteFlags'     => array(
            'remember_me'          => (bool)(defined('REMEMBER_ME') && REMEMBER_ME),
            'forgot_password_link' => (bool)(defined('FORGOT_PASSWORD_LINK') && FORGOT_PASSWORD_LINK),
            'password_hint'        => (bool)(defined('PASSWORD_HINT') && PASSWORD_HINT),
            'captcha'              => (bool)(defined('CAPTCHA') && CAPTCHA),
            'strong_password'      => (bool)(defined('STRONG_PASSWORD') && STRONG_PASSWORD),
            'google'               => (bool)(defined('OAUTH_GOOGLE_ENABLED') && OAUTH_GOOGLE_ENABLED
                                        && defined('OAUTH_GOOGLE_CLIENT_ID') && OAUTH_GOOGLE_CLIENT_ID !== ''),
        ),
        // The editor's UI text, resolved through lang() for the site's
        // language. Empty when the language is English: the JS keys ARE the
        // English text and _sdT() falls back to the key.
        'i18n'          => pg_designer_i18n_map(),
        'hiddenPages'   => $hidden_pages,
        'autoImport'    => !empty($ctx['auto_import']),
        'autoPasteHtml' => !empty($ctx['auto_paste']),
        // Everything the field panel offers, resolved once here rather than
        // duplicated in JavaScript — a second copy of "which contact fields
        // exist" is the copy that drifts.
        'formMeta'      => array(
            'rss'      => function_exists('pg_cf_rss_fields')     ? pg_cf_rss_fields()     : array(),
            'contact'  => function_exists('pg_cf_contact_fields') ? pg_cf_contact_fields() : array(),
            'folders'  => function_exists('pg_cf_folder_options') ? pg_cf_folder_options() : array(),
            // The registration widget files its new member's contact into one
            // of these; the address book's own list, id and name.
            'contactGroups' => array_map(function ($g) {
                return array('id' => (int)$g['id'], 'name' => (string)$g['name']);
            }, (array)db_items("SELECT id, name FROM contact_groups ORDER BY name")),
        ),
    ), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $last_modified = '';
    if ($mode === 'edit') {
        $lm_user = (string)$style['last_modified_username'];
        if ($lm_user === '') $lm_user = '[Unknown]';
        $last_modified = get_relative_time(array('timestamp' => $style['last_modified_timestamp'])) . ' '
                       . lang(array('string' => 'by {var:1}', 'vars' => array(h($lm_user))));
    }

    $form_action = ($mode === 'edit') ? 'edit_system_style.php' : 'add_system_style.php';

    // No `cancel` key for the shell: the designer draws its own strip with the
    // page tabs in it and puts the back arrow behind the unsaved-changes
    // check. The shell's strip would sit above ours doing nothing.
    print
        pg_page_shell(array(
            'title'        => lang('Visual Page Editor'),
            'extra classes'=> 'design',
            'icon'         => 'design',
            'heading'      => lang('Visual Page Editor'),
            'heading_description' => lang('The pages of the design are the tabs; drop blocks from the palette on the left, edit their options on the right.'),
            'hide_menu'    => true,
            // The shared curtain hides itself when the document is ready.
            // Here the document being ready is the START of the work: panels
            // are built, the canvas iframe is written, the tree is laid out.
            // So we hold it and take it down when the canvas has painted.
            'head'         => '<script>window.pgPreloaderHold = true;</script>',
        )) . '
        <link rel="stylesheet" href="assets/fonts/bootstrap-icons/bootstrap-icons.min.css">
        <link rel="stylesheet" href="assets/css/style_designer.css?v=' . time() . '">
        <main id="content" style="padding:0; max-width:100%;">
            ' . $liveform->output_errors() . '
            ' . $liveform->output_notices() . '
            <form id="style_designer_form" name="style_designer_form" action="' . $form_action . '" method="post" style="height:100%;">
                ' . get_token_field() . '
                ' . $liveform->output_field(array('type'=>'hidden', 'name'=>'id')) . '
                ' . $liveform->output_field(array('type'=>'hidden', 'name'=>'send_to')) . '
                ' . ($ctx['from_pages'] ? '<input type="hidden" name="from" value="pages">' : '') . '
                <!-- Every page of the design, serialised by the editor on save -->
                <input type="hidden" id="sd-pages-json" name="pages_json" value="">
                <!-- This tab\'s presence key. The save reads it to decide which
                     pages this session actually holds the edit lock on; a POST
                     without it can only write pages nobody has claimed. -->
                <input type="hidden" id="sd-collab-key" name="collab_key" value="">
                <!-- Comments settings — driven by the options panel when a comments_block region is selected; per page -->
                <input type="hidden" name="pg_comments" value="0">
                <input type="hidden" name="pg_comments_label" value="">
                <input type="hidden" name="pg_comments_allow_new" value="1">
                <input type="hidden" name="pg_comments_rating" value="0">
                <!-- Shared design assets — managed by the Assets panel; one set for every page -->
                <input type="hidden" name="style_custom_css" id="sd-custom-css-field" value="' . h($style['style_custom_css']) . '">
                <input type="hidden" name="style_custom_js" id="sd-custom-js-field" value="' . h($style['style_custom_js']) . '">
                <input type="hidden" name="style_custom_fonts" id="sd-custom-fonts-field" value="' . h($style['style_custom_fonts']) . '">

                <div class="sd-wrapper">
                    <!-- One toolbar. Left: what you do to pages (add, settings,
                         delete). Middle: the tabs, starting right after, scrolling
                         in their own strip when there are many. Right: undo / redo /
                         save. The page name is not here — it is edited on the tab
                         itself (double-click) or in Page Settings; duplicating a page
                         is on the tab menu (⋮ / right-click), where the page it
                         copies is the one under the cursor. No back button: Ctrl+G,
                         the header link and the browser back button all go through
                         the unsaved-changes guard. -->
                    <div class="sd-toolbar sd-toolbar-tabs">
                        <div class="sd-toolbar-group sd-toolbar-pages">
                            <div class="dropdown sd-tabs-add">
                                <button type="button" class="sd-tabs-add-btn" data-bs-toggle="dropdown" aria-expanded="false" title="' . lang('Add Page') . '">
                                    <span class="bi bi-plus-lg"></span><span class="sd-tabs-add-txt">' . lang('Add Page') . '</span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-dark">
                                    <li><button type="button" class="dropdown-item" id="sd-tab-new-page"><span class="bi bi-file-earmark-plus me-2"></span>' . lang('New Page') . '</button></li>
                                    <li><button type="button" class="dropdown-item" id="sd-tab-pick-page"><span class="bi bi-files me-2"></span>' . lang('Select Page') . '</button></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><button type="button" class="dropdown-item" id="sd-tab-import-html"><span class="bi bi-file-earmark-code me-2"></span>' . lang('Import HTML / ZIP') . '</button></li>
                                    <li><button type="button" class="dropdown-item" id="sd-tab-paste-html"><span class="bi bi-clipboard-plus me-2"></span>' . lang('Paste HTML content') . '</button></li>
                                </ul>
                            </div>
                            <button type="button" class="sd-icon-btn" data-bs-toggle="modal" data-bs-target="#styleSettingsModal" title="' . lang('Settings') . '"><span class="bi bi-gear"></span></button>
                            ' . ($mode === 'edit' ? $ctx['delete_button'] : '') . '
                        </div>
                        <div class="sd-tabs-scroll" id="sd-tabs-list" role="tablist"></div>
                        <div class="sd-toolbar-group sd-toolbar-actions">
                            <!-- Who else has this design open. Empty and
                                 invisible when nobody else does, so a single
                                 operator never sees a feature they are not
                                 using. -->
                            <div class="sd-presence" id="sd-presence"></div>
                            <button type="button" id="sd-main-undo" class="sd-vb-btn" onclick="StyleDesigner.undo()" title="' . lang('Undo') . ' (Ctrl+Z)" disabled><span class="bi bi-arrow-counterclockwise"></span></button>
                            <button type="button" id="sd-main-redo" class="sd-vb-btn" onclick="StyleDesigner.redo()" title="' . lang('Redo') . ' (Ctrl+Y)" disabled><span class="bi bi-arrow-clockwise"></span></button>
                            <button type="button" id="sd-ajax-save" name="submit_save" value="Save" class="sd-icon-btn sd-icon-btn-primary" title="' . lang('Save') . '"><span class="bi bi-floppy"></span></button>
                            <a href="#" id="sd-view-page" class="sd-icon-btn" target="_blank" rel="noopener" title="' . lang('View Page') . '" style="display:none"><span class="bi bi-box-arrow-up-right"></span></a>
                        </div>
                    </div>

                    <!-- 3-Panel Body -->
                    <div class="sd-body">
                        <div class="sd-panel-left" id="sd-components"></div>
                        <div class="sd-canvas-wrapper">
                            <div class="sd-canvas" id="sd-canvas"></div>
                        </div>
                        <div class="sd-panel-right" id="sd-panel-right">
                            <div id="sd-properties"></div>
                        </div>
                    </div>

                    <!-- Status Bar -->
                    <div class="sd-statusbar" id="sd-statusbar">
                        <span class="text-muted small" id="sd-last-modified-label">' . $last_modified . '</span>
                    </div>
                </div>

                ' . pg_designer_settings_modal($ctx, $output_modal_social, $output_noindex_switches, $output_noindex_hint) . '
                ' . pg_designer_pick_page_modal() . '
                ' . pg_designer_import_modal() . '
            </form>
        </main>
        ' . get_codemirror_includes() . '
        ' . get_image_editor_includes() . '
        <!-- The designer\'s data, and its translation map, are declared BEFORE
             the editor script. style_designer.js resolves _sdT() at module
             init for the palette registries (COMPONENTS, CONTENT, the form
             palette); with sdDesign declared after the script those labels
             froze at the English key and no amount of translating tr.json
             could move them. Anything the editor needs at parse time goes
             in this block, not the one below. -->
        <script>
            window.OUTPUT_PATH = "' . h(escape_javascript(OUTPUT_PATH)) . '";
            var sdRegionData = ' . get_style_designer_regions_as_json() . ';
            var sdDesign = ' . $design_js . ';
        </script>
        <script src="assets/js/codemirror_modal.js?v=' . time() . '"></script>
        <script src="assets/js/class_suggestions.js?v=' . time() . '"></script>
        <script src="assets/js/style_designer.js?v=' . time() . '"></script>
        <script>
            $(document).ready(function() {
                StyleDesigner.init({
                    regionData: sdRegionData,
                    design: sdDesign
                });

                document.getElementById("styleSettingsModal").addEventListener("shown.bs.modal", function () {
                    if (typeof initSeoCounters === "function") {
                        initSeoCounters([
                            { sel: "#pg_page_title",            counterId: "seo_c_pg_page_title",            min: 30,  max: 60  },
                            { sel: "#pg_page_meta_description", counterId: "seo_c_pg_page_meta_description", min: 150, max: 160 }
                        ]);
                    }
                    if (typeof bindPageIndexingSwitches === "function") {
                        bindPageIndexingSwitches({
                            noindex:  "pg_page_noindex",
                            nofollow: "pg_page_nofollow",
                            sitemap:  "pg_page_sitemap",
                            hint:     "pg_noindex_hint"
                        });
                    }
                });

                var _saveBtn = document.getElementById("sd-ajax-save");
                if (_saveBtn) {
                    _saveBtn.addEventListener("click", function (e) {
                        e.preventDefault();
                        StyleDesigner.saveAjax({ button: _saveBtn });
                    });
                }
            });
        </script>' .

        // Two people can be in one design at the same time (presence, page
        // locks, notes) — and until this line they had no way to say anything
        // to each other. The launcher is printed here rather than inherited
        // from output_footer(), which this screen deliberately does not call:
        // it is a full-height application and the footer bar would sit under
        // a viewport-sized flex column.
        pg_chat_launcher_html();
}

/**
 * Designs the visual editor can open: every style made by it, plus any
 * style whose pages carry their own tree. Legacy system layouts (interior,
 * emailer-inline, …) are left out — the editor has nothing to show for them.
 *
 * Returns rows: style_id, style_name, page_count, last_modified_timestamp,
 * last_modified_username.
 */
function pg_designer_list_designs()
{
    $own_tree = pg_multi_page_design_ready()
        ? " OR EXISTS (SELECT 1 FROM page p2 WHERE p2.page_style = style.style_id AND p2.page_tree_json IS NOT NULL AND p2.page_tree_json <> '')"
        : '';
    $rows = db_items(
        "SELECT style.style_id, style.style_name, style.style_timestamp AS last_modified_timestamp,
                user.user_username AS last_modified_username, files.name AS theme_name,
                (SELECT COUNT(*) FROM page WHERE page.page_style = style.style_id AND page.layout_type = 'system'" . pg_designer_not_binned_sql() . ") AS page_count
         FROM style
         LEFT JOIN user ON style.style_user = user.user_id
         LEFT JOIN files ON style.theme_id = files.id
         WHERE (style.style_layout = 'visual_designer'$own_tree)
         ORDER BY style.style_timestamp DESC, style.style_name ASC");
    return is_array($rows) ? $rows : array();
}

/**
 * The Visual Page Editor home (view_system_styles.php): start a new design
 * blank or from an HTML project, or open one of the designs that exist.
 * "From template" is a placeholder for now. The list is the editor's own
 * designs only — a legacy style opened here would show a blank page and
 * nothing to explain why.
 */
function pg_designer_start_screen($ctx)
{
    $liveform   = $ctx['liveform'];
    $from_pages = !empty($ctx['from_pages']);
    $qs         = $from_pages ? '?from=pages' : '';
    $qs_import  = $from_pages ? '?start=import&from=pages' : '?start=import';
    $qs_paste   = $from_pages ? '?start=paste&from=pages'  : '?start=paste';
    $designs    = pg_designer_list_designs();

    // Starting a design is a design job (add_system_style.php refuses anyone
    // below designer). Showing the cards to a content-level operator would be
    // offering three buttons that all end in an access error.
    require_once(dirname(__FILE__) . '/designer_access.php');
    $can_create = pg_designer_is_full($ctx['user']);

    // Rows of the standard admin DataTable (same table as view_styles.php,
    // without the bulk-select column: designs are deleted from the editor's
    // toolbar, one at a time, after their pages).
    $output_rows = '';
    foreach ($designs as $d) {
        $count = (int)$d['page_count'];
        $user_label = ($d['last_modified_username'] !== '' && $d['last_modified_username'] !== null) ? $d['last_modified_username'] : '[' . lang('Unknown') . ']';
        $output_rows .= '
        <tr>
            <td class="align-middle text-start" nowrap>
                <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2" data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'edit_system_style.php?id=' . (int)$d['style_id'] . '\'"><i class="bi bi-pencil"></i></button>
                <button type="button" class="m-1 btn-data-control btn btn-outline-warning border-2 sd-design-delete" title="' . lang('Delete') . '" data-style-id="' . (int)$d['style_id'] . '" data-name="' . h($d['style_name']) . '" data-pages="' . $count . '"><i class="bi bi-trash"></i></button>
            </td>
            <td class="align-middle chart_label" nowrap>' . h($d['style_name']) . ($count == 0 ? ' <span class="badge text-bg-secondary ms-1" title="' . lang('No pages — open it to add one, or delete it from the toolbar') . '">' . lang('Empty') . '</span>' : '') . '</td>
            <td class="align-middle text-center" data-order="' . $count . '">' . number_format($count) . '</td>
            <td class="align-middle">' . h((string)$d['theme_name']) . '</td>
            <td class="align-middle" nowrap data-order="' . (int)$d['last_modified_timestamp'] . '">' . get_relative_time(array('timestamp' => (int)$d['last_modified_timestamp'])) . '  ' . h($user_label) . '</td>
        </tr>';
    }

    $card = function ($href, $icon, $title, $text, $opts = array()) {
        $primary  = !empty($opts['primary']);
        $disabled = !empty($opts['disabled']);
        $badge    = $disabled ? '<span class="badge text-bg-secondary ms-auto">' . lang('Soon') . '</span>' : '';
        $tag_open = $disabled
            ? '<div class="card h-100 sd-start-card sd-start-card-disabled" aria-disabled="true">'
            : '<a href="' . h($href) . '" class="card h-100 text-decoration-none sd-start-card' . ($primary ? ' border-primary' : '') . '" data-loading-content="' . lang('Loading') . '">';
        $tag_close = $disabled ? '</div>' : '</a>';
        return
            '<div class="col-12 col-sm-6 col-xl-3">
                ' . $tag_open . '
                    <div class="card-body d-flex flex-column align-items-start gap-2">
                        <div class="d-flex w-100 align-items-start"><span class="bi ' . h($icon) . ' fs-2 ' . ($primary ? 'text-primary' : ($disabled ? 'text-muted' : 'text-body-secondary')) . '"></span>' . $badge . '</div>
                        <span class="h5 mb-0 ' . ($disabled ? 'text-muted' : 'text-body') . '">' . h($title) . '</span>
                        <span class="small text-muted">' . h($text) . '</span>
                    </div>
                ' . $tag_close . '
            </div>';
    };

    print
        pg_page_shell(array(
            'title'               => lang('Visual Page Editor'),
            'extra classes'       => 'design',
            'icon'                => 'design',
            'heading'             => lang('Visual Page Editor'),
            'heading_description' => lang('A design is a set of pages that share stylesheets, scripts, fonts and a theme.'),
        )) . '
        <style>
            .sd-start-card-disabled { opacity: .55; cursor: not-allowed; }
        </style>
        <main id="content" class="container-fluid">
            ' . $liveform->output_errors() . '
            ' . $liveform->output_notices() . '
            ' . ($can_create ? '
            <div class="row g-3 mb-4">
                ' . $card('add_system_style.php' . $qs, 'bi-file-earmark-plus', lang('Create New'), lang('An empty page on the canvas. Add sections from the palette and build the design up.'), array('primary' => true)) . '
                ' . $card('add_system_style.php' . $qs_import, 'bi-file-earmark-zip', lang('Import HTML / ZIP'), lang('Bring in a finished HTML page or a whole project archive. Pages become tabs, files go to the file manager.')) . '
                ' . $card('add_system_style.php' . $qs_paste, 'bi-clipboard-plus', lang('Paste HTML content'), lang('Paste the markup straight in — from a code editor, from a page you have open. The design starts from what you paste.')) . '
                ' . $card('#', 'bi-grid-1x2', lang('Choose a Template'), lang('Start from a ready-made design and change what you like.'), array('disabled' => true)) . '
            </div>' : '
            <div class="alert alert-secondary py-2 small mb-4">
                <span class="bi bi-info-circle me-1"></span>' . lang('Open a design to edit its text and the areas marked for you. New designs are created by designers.') . '
            </div>') . '
            <div class="card my-4">
                <div class="card-body p-0 position-relative">
                    <table class="chart table-hover table" style="width:100%;display:none">
                        <thead>
                            <tr>
                                <th class="noVis">' . lang('Action') . '</th>
                                <th>' . lang('Name') . '</th>
                                <th class="text-center">' . lang('Pages') . '</th>
                                <th>' . lang('Theme') . '</th>
                                <th nowrap>' . lang('Last Modified') . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_rows . '</tbody>
                    </table>
                </div>
            </div>
        </main>
        <script>
            // Delete a design from its row: the design goes, its pages go to
            // the recycle bin. api.php reads a JSON body.
            document.addEventListener("click", function (e) {
                var btn = e.target.closest(".sd-design-delete");
                if (!btn) return;
                e.preventDefault();
                var id = parseInt(btn.dataset.styleId, 10), name = btn.dataset.name || "", pages = parseInt(btn.dataset.pages, 10) || 0;
                var msg = ' . json_encode(lang('The design "{name}" will be deleted.')) . '.replace("{name}", name) + " " +
                          (pages > 0 ? ' . json_encode(lang('Its {n} page(s) will be moved to the Recycle Bin.')) . '.replace("{n}", pages) : ' . json_encode(lang('It has no pages.')) . ');
                var run = function () {
                    btn.disabled = true;
                    fetch("api.php", { method: "POST", credentials: "same-origin", headers: { "Content-Type": "application/json" },
                           body: JSON.stringify({ action: "designer", sub_action: "design_delete", style_id: id, token: (typeof software_token !== "undefined" ? software_token : "") }) })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (!res || res.status !== "success") { btn.disabled = false; alert((res && res.message) || ' . json_encode(lang('Sorry, we could not accept your request.')) . '); return; }
                        var tr = btn.closest("tr");
                        var table = tr && tr.closest("table");
                        if (table && window.jQuery && jQuery.fn.dataTable && jQuery.fn.dataTable.isDataTable(table)) {
                            jQuery(table).DataTable().row(tr).remove().draw(false);
                        } else if (tr) { tr.remove(); }
                        if (typeof pgToast === "function") pgToast({ message: res.message, variant: "success" });
                    })
                    .catch(function () { btn.disabled = false; alert(' . json_encode(lang('Network error.')) . '); });
                };
                if (typeof window.pgConfirm === "function") {
                    window.pgConfirm({ title: ' . json_encode(lang('Delete Design')) . ', message: msg, confirmText: ' . json_encode(lang('Yes, delete')) . ', cancelText: ' . json_encode(lang('No')) . ', variant: "danger" })
                        .then(function (ok) { if (ok) run(); });
                } else if (window.confirm(msg)) { run(); }
            });
        </script>
        ';
    print output_footer();
}

/**
 * The settings modal. Two sections, named for what they touch: everything
 * under "All pages" is stored on the style and changes every page of the
 * design; everything under "This page" is stored on the page that is
 * currently open in the tab bar, and the heading says which one.
 */
function pg_designer_settings_modal($ctx, $output_modal_social, $output_noindex_switches, $output_noindex_hint)
{
    $liveform = $ctx['liveform'];
    $user     = $ctx['user'];

    return '
        <div class="modal fade" id="styleSettingsModal" tabindex="-1" aria-labelledby="styleSettingsModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-fullscreen">
                <div class="modal-content" style="background:#1e2127; color:#c9d1d9; border:none;">

                    <div class="modal-header" style="border-color:#30363d; flex-shrink:0;">
                        <h5 class="modal-title fs-6" id="styleSettingsModalLabel"><span class="bi bi-gear me-2"></span>' . lang('Settings') . '</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body p-0 d-flex overflow-hidden" style="flex:1 1 auto;">

                        <!-- The rail dimensions live in style_designer.css
                             (.sd-settings-nav): below 992px the rail turns into
                             a horizontal strip above the form, and an inline
                             width would have won over that media query. -->
                        <div class="sd-settings-nav">
                            <!-- ONE tablist: Bootstrap deactivates the previous pill by looking
                                 for the active sibling inside the same tablist, so two separate
                                 navs would leave both panes showing. The group headings are plain
                                 divs the pill logic ignores. -->
                            <nav class="nav nav-pills px-2" id="settingsTabs" role="tablist">
                                <div class="sd-settings-nav-title">' . lang('Shared by all pages') . '</div>
                                <button class="nav-link active text-start" id="tab-style-btn"
                                        data-bs-toggle="pill" data-bs-target="#tab-style"
                                        type="button" role="tab">
                                    <span class="bi bi-palette me-2"></span>' . lang('Design') . '
                                </button>
                                <div class="sd-settings-nav-title">' . lang('Only this page') . '</div>
                                <button class="nav-link text-start" id="tab-page-btn"
                                        data-bs-toggle="pill" data-bs-target="#tab-page"
                                        type="button" role="tab">
                                    <span class="bi bi-file-earmark me-2"></span><span id="sd-settings-page-tab-label">' . lang('Page') . '</span>
                                </button>
                            </nav>
                        </div>

                        <div class="tab-content flex-grow-1 overflow-y-auto sd-settings-body">

                            <!-- Shared: the design -->
                            <div class="tab-pane fade show active" id="tab-style" role="tabpanel">
                                <div class="sd-settings-scope"><span class="bi bi-collection me-1"></span>' . lang('These settings apply to every page in this design.') . '</div>

                                <div class="sd-settings-group">
                                    <div class="sd-settings-group-title">' . lang('Design') . '</div>
                                    <div class="mb-3">
                                        <label class="form-label small">' . lang('Design Name') . '</label>
                                        ' . $liveform->output_field(array('type'=>'text', 'name'=>'name', 'id'=>'sd-style-name', 'class'=>'form-control form-control-sm', 'maxlength'=>'100', 'placeholder'=>lang('Defaults to the first page name'))) . '
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small">' . lang('Theme') . '</label>
                                        ' . $liveform->output_field(array('type'=>'select', 'name'=>'theme_id', 'options'=>get_theme_options(), 'class'=>'form-select form-select-sm')) . '
                                        <small class="form-text">' . lang('Loaded after the stylesheets, so its colours win. Shown in the Styles list and the Themes panel.') . '</small>
                                    </div>
                                </div>

                                <div class="sd-settings-group">
                                    <div class="sd-settings-group-title">' . lang('Advanced') . '</div>
                                    <div class="mb-3">
                                        <label class="form-label small">' . lang('Collection') . '</label>
                                        ' . $liveform->output_field(array('type'=>'select', 'name'=>'collection', 'options'=>array('A'=>'a', 'B'=>'b'), 'class'=>'form-select form-select-sm')) . '
                                    </div>
                                    ' . $output_modal_social . '
                                </div>
                                ' . $liveform->output_field(array('type'=>'hidden', 'name'=>'additional_body_classes')) . '
                                ' . $liveform->output_field(array('type'=>'hidden', 'name'=>'style_head')) . '
                                ' . $liveform->output_field(array('type'=>'hidden', 'name'=>'style_empty_cell_width_percentage')) . '
                                <div class="form-text small">
                                    <span class="bi bi-info-circle me-1"></span>' . lang('Stylesheets, scripts and fonts are managed in the Assets panel on the right and are also shared by every page.') . '
                                </div>
                            </div>

                            <!-- Per page -->
                            <div class="tab-pane fade" id="tab-page" role="tabpanel">
                                <div class="sd-settings-scope"><span class="bi bi-file-earmark me-1"></span>' . lang('These settings apply only to') . ' <strong id="sd-settings-page-name">—</strong></div>

                                <div class="sd-settings-group">
                                    <div class="sd-settings-group-title">' . lang('Page') . '</div>
                                    <div class="mb-3">
                                        <label class="form-label small" for="sd-page-name">' . lang('Page Name') . '</label>
                                        <input type="text" id="sd-page-name" class="form-control form-control-sm sd-page-field" data-page-key="page_name" maxlength="100" placeholder="' . lang('Page name...') . '" autocomplete="off">
                                        <small class="form-text">' . lang('The address of the page. Can also be edited by double-clicking its tab.') . '</small>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small">' . lang('Page Folder') . '</label>
                                        <select name="page_folder" id="pg_page_folder" class="form-select form-select-sm sd-page-field" data-page-key="page_folder" style="background-color:#0d1117; color:#c9d1d9; border-color:#30363d;">
                                            ' . select_folder(0) . '
                                        </select>
                                    </div>
                                </div>

                                <div class="sd-settings-group">
                                    <div class="sd-settings-group-title">' . lang('Search Engines') . '</div>
                                    <div class="mb-3">
                                        <label class="form-label small">' . lang('Page Title') . '</label>
                                        <input type="text" name="page_title" id="pg_page_title" class="form-control form-control-sm sd-page-field" data-page-key="page_title">
                                        <div id="seo_c_pg_page_title"></div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small">' . lang('Meta Description') . '</label>
                                        <textarea name="page_meta_description" id="pg_page_meta_description" class="form-control form-control-sm sd-page-field" data-page-key="page_meta_description" rows="3"></textarea>
                                        <div id="seo_c_pg_page_meta_description"></div>
                                    </div>
                                    <div class="sd-settings-switches">
                                        <div class="form-check form-switch">
                                            <input type="hidden" name="page_sitemap" value="0">
                                            <input class="form-check-input sd-page-field" type="checkbox" name="page_sitemap" id="pg_page_sitemap" value="1" data-page-key="page_sitemap">
                                            <label class="form-check-label small" for="pg_page_sitemap">' . lang('Include in the site map') . '</label>
                                        </div>
                                        ' . $output_noindex_switches . '
                                    </div>
                                    ' . $output_noindex_hint . '
                                </div>

                                <div class="sd-settings-group">
                                    <div class="sd-settings-group-title">' . lang('Site') . '</div>
                                    <div class="sd-settings-switches">
                                        ' . (((int)$user['role'] < 3) ? '<div class="form-check form-switch">
                                            <input type="hidden" name="page_home" value="0">
                                            <input class="form-check-input sd-page-field" type="checkbox" name="page_home" id="pg_page_home" value="1" data-page-key="page_home">
                                            <label class="form-check-label small" for="pg_page_home">' . lang('Set As Homepage') . '</label>
                                        </div>' : '') . '
                                        <div class="form-check form-switch">
                                            <input type="hidden" name="page_search" value="0">
                                            <input class="form-check-input sd-page-field" type="checkbox" name="page_search" id="pg_page_search" value="1" data-page-key="page_search"
                                                   onchange="document.getElementById(\'pg_search_kw_row\').style.display=this.checked?\'block\':\'none\'">
                                            <label class="form-check-label small" for="pg_page_search">' . lang('Include in site search') . '</label>
                                        </div>
                                    </div>
                                    <div id="pg_search_kw_row" class="mt-2 mb-0">
                                        <label class="form-label small">' . lang('Search Keywords') . '</label>
                                        <input type="text" value="" name="page_search_keywords" id="pg_search_keywords" class="form-control form-control-sm tagin min-height-tagin sd-page-field" data-page-key="page_search_keywords" data-placeholder="' . lang('Add tags') . '">
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <div class="modal-footer" style="border-color:#30363d; flex-shrink:0;">
                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">' . lang('Close') . '</button>
                        <button type="button" class="btn btn-sm btn-primary" data-bs-dismiss="modal">' . lang('Apply') . '</button>
                    </div>

                </div>
            </div>
        </div>';
}

/**
 * "Select Page" picker — lists every visual-designer page not already on this
 * design. Rows are filled by the editor from api.php (designer/selectable_pages).
 */
function pg_designer_pick_page_modal()
{
    return '
        <div class="modal fade" id="sdPickPageModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-scrollable">
                <div class="modal-content" style="background:#1e2127; color:#c9d1d9; border:1px solid #30363d;">
                    <div class="modal-header" style="border-color:#30363d;">
                        <h5 class="modal-title fs-6"><span class="bi bi-files me-2"></span>' . lang('Select Page') . '</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-0">
                        <div class="p-3 pb-2">
                            <input type="text" class="form-control form-control-sm" id="sd-pick-page-filter" placeholder="' . lang('Search...') . '" autocomplete="off">
                        </div>
                        <div class="alert alert-warning py-2 small mx-3 mb-2">
                            <span class="bi bi-exclamation-triangle me-1"></span>' . lang('A page you add here joins this design: it keeps its own layout, but its stylesheets, scripts, fonts and theme are replaced by this design\'s.') . '
                        </div>
                        <div class="list-group list-group-flush" id="sd-pick-page-list">
                            <div class="list-group-item bg-transparent text-muted small">' . lang('Loading...') . '</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
}

/**
 * "Import HTML / ZIP" — one file input, an optional project name (the folder
 * under Import/ in the file manager), and a plain-language note on what
 * happens to the <head>. Posts to designer_import.php from JS.
 */
function pg_designer_import_modal()
{
    return '
        <div class="modal fade" id="sdImportModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content" style="background:#1e2127; color:#c9d1d9; border:1px solid #30363d;">
                    <div class="modal-header" style="border-color:#30363d;">
                        <h5 class="modal-title fs-6"><span class="bi bi-file-earmark-code me-2"></span>' . lang('Import HTML / ZIP') . '</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label small" for="sd-import-file">' . lang('File') . '</label>
                            <input type="file" class="form-control form-control-sm" id="sd-import-file" accept=".html,.htm,.zip">
                            <div class="form-text small">' . lang('A single .html file, or a .zip with the whole project (HTML pages plus their CSS, JavaScript, images and fonts).') . '</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small" for="sd-import-project">' . lang('Project name') . '</label>
                            <input type="text" class="form-control form-control-sm" id="sd-import-project" maxlength="60" placeholder="' . lang('Defaults to the file name') . '" autocomplete="off">
                            <div class="form-text small">' . lang('Files are stored under Import / this name in the file manager, keeping the project\'s folders.') . '</div>
                        </div>
                        <div class="alert alert-secondary py-2 small mb-0">
                            <div><span class="bi bi-info-circle me-1"></span>' . lang('Every .html becomes a page (index.html → index). Its head is replaced by the design\'s: stylesheets and scripts arrive as design assets, Google Fonts in the fonts list, Bootstrap and jQuery are dropped in favour of the design\'s own.') . '</div>
                            <div class="mt-1">' . lang('Pages open as unsaved tabs — review them, then save.') . '</div>
                        </div>
                        <div id="sd-import-status" class="small mt-3" style="display:none"></div>
                    </div>
                    <div class="modal-footer" style="border-color:#30363d;">
                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">' . lang('Cancel') . '</button>
                        <button type="button" class="btn btn-sm btn-primary" id="sd-import-run"><span class="bi bi-upload me-1"></span>' . lang('Import') . '</button>
                    </div>
                </div>
            </div>
        </div>';
}

// ── POST handler ────────────────────────────────────────────────────────────

/**
 * Save the design and every page in it. Shared by both entry points.
 *
 * Order matters: all pages are validated first, and only when every one of
 * them passes is anything written. The tables are MyISAM, so there is no
 * transaction to lean on — a save that wrote three pages and refused the
 * fourth would leave the operator with a half-applied design and a red
 * toast. Validation is cheap; running it twice is the price of atomicity.
 *
 * Reply is JSON on AJAX, else a redirect — same contract as before, plus a
 * `pages` map (client key → page_id) so new tabs learn their ids, and
 * `style_id` so the add screen can turn into the edit screen.
 */
/**
 * Turn `tab:<key>` values into page ids.
 *
 * The editor lets a page picker choose a tab that is not saved yet — it has
 * no id, so the choice is written as the tab's key. Once the save has handed
 * out ids, every such value becomes the id (or 0 when the key matches no
 * page: the tab was closed before the save). Walks nested arrays; touches
 * nothing else.
 */
function pg_designer_resolve_tab_values($value, $tab_ids)
{
    if (is_array($value)) {
        foreach ($value as $k => $v) $value[$k] = pg_designer_resolve_tab_values($v, $tab_ids);
        return $value;
    }
    if (is_string($value) && strncmp($value, 'tab:', 4) === 0) {
        $key = substr($value, 4);
        return isset($tab_ids[$key]) ? (int)$tab_ids[$key] : 0;
    }
    return $value;
}

/**
 * Resolve `tab:<key>` references in the system widgets these pages place.
 *
 * Only the widgets referenced by the saved trees are read; each config with
 * a key in it is rewritten once. The editor makes the same substitution in
 * its cache from the save reply, so the two sides agree without a reload.
 */
function pg_designer_resolve_tab_refs($pages, $tab_ids)
{
    $sids = array();
    foreach ((array)$pages as $p) {
        if (!is_array($p) || !isset($p['tree_json'])) continue;
        $json = (string)$p['tree_json'];
        if (strpos($json, '"sharedId"') === false) continue;
        if (preg_match_all('/"sharedId":\s*"?(\d+)"?/', $json, $mm)) {
            foreach ($mm[1] as $sid) { $sid = (int)$sid; if ($sid > 0) $sids[$sid] = true; }
        }
    }
    if (empty($sids)) return;
    $rows = db_items("SELECT id, system_region_config FROM shared_components
                      WHERE id IN (" . implode(',', array_keys($sids)) . ")
                        AND system_region_config LIKE '%tab:%'");
    foreach ((array)$rows as $row) {
        $cfg = json_decode((string)$row['system_region_config'], true);
        if (!is_array($cfg)) continue;
        $resolved = pg_designer_resolve_tab_values($cfg, $tab_ids);
        if ($resolved === $cfg) continue;
        db("UPDATE shared_components
            SET system_region_config = '" . e(json_encode($resolved, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . "',
                updated_at = " . time() . "
            WHERE id = '" . (int)$row['id'] . "' LIMIT 1");
    }
}

function pg_designer_screen_post($ctx)
{
    $liveform = $ctx['liveform'];
    $user     = $ctx['user'];
    $mode     = $ctx['mode'];
    $style_id = (int)$ctx['style_id'];

    $is_ajax = (
        (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || !empty($_POST['ajax'])
    );

    $fail = function ($errors) use ($liveform, $is_ajax, $mode, $style_id) {
        $errors = array_values(array_filter((array)$errors));
        if ($is_ajax) {
            header('Content-Type: application/json; charset=utf-8');
            echo encode_json(array(
                'status'  => 'error',
                'message' => $errors ? implode(' ', $errors) : lang('An error occurred'),
                'errors'  => $errors,
            ));
            exit();
        }
        foreach ($errors as $e) $liveform->add_error($e);
        $back = ($mode === 'edit')
            ? 'edit_system_style.php?id=' . $style_id . '&send_to=' . urlencode($liveform->get_field_value('send_to'))
            : 'add_system_style.php';
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/' . $back);
        exit();
    };

    // ── Pages payload ─────────────────────────────────────────────────────
    $pages_raw = isset($_POST['pages_json']) ? (string)$_POST['pages_json'] : '';
    $pages = json_decode($pages_raw, true);
    if (!is_array($pages) || empty($pages)) {
        $fail(array(lang('No page data was received. Refresh the editor and try again.')));
    }

    // ── Style name ────────────────────────────────────────────────────────
    // Defaults to the first page's name so the styles list never shows a
    // blank row; the operator can rename it in Settings → Design.
    $style_name  = trim((string)$liveform->get_field_value('name'));
    $name_is_own = ($style_name !== '');
    if ($style_name === '') {
        $style_name = trim((string)(isset($pages[0]['page_name']) ? $pages[0]['page_name'] : ''));
    }
    $errors = array();
    $style_name_taken = function ($name) use ($style_id) {
        return (int)db_value(
            "SELECT style_id FROM style
             WHERE style_name = '" . e($name) . "'"
             . ($style_id > 0 ? " AND style_id <> '$style_id'" : '') . " LIMIT 1") > 0;
    };
    if ($style_name !== '') {
        if ($name_is_own) {
            // A name the operator typed is theirs to fix.
            if ($style_name_taken($style_name)) {
                $errors[] = lang('The name that you entered is already in use, so please enter a different name.');
            }
        } else {
            // A name nobody typed cannot be "already in use" from where the
            // operator sits — there is no field on screen with it. Take the
            // next free variant instead, the way shared components do.
            $base = $style_name;
            for ($n = 2; $style_name_taken($style_name) && $n < 1000; $n++) {
                $style_name = $base . ' [' . $n . ']';
            }
        }
    }
    // An empty derived name means the first page has no name; that page
    // reports it below in its own words, so it is not repeated here.

    // ── Validate every page before writing any ────────────────────────────
    // A name used twice WITHIN this save can't be caught by the per-page
    // database check (neither row exists yet), so it is checked here.
    // Who is saving, as far as the edit locks are concerned. The editor sends
    // the key its heartbeat uses; a POST without one holds no lock and can
    // therefore only write pages nobody has claimed.
    require_once(dirname(__FILE__) . '/designer_collab.php');
    $collab_key = isset($_POST['collab_key']) ? (string)$_POST['collab_key'] : '';

    $seen_names   = array();
    $warnings     = array();
    $locked_pages = array();
    foreach ($pages as $i => $p) {
        if (!is_array($p)) { $errors[] = lang('Invalid page data.'); continue; }
        $nm = trim((string)(isset($p['page_name']) ? $p['page_name'] : ''));
        if ($nm !== '') {
            $lower = mb_strtolower($nm, 'UTF-8');
            if (isset($seen_names[$lower])) {
                $errors[] = lang(array('string' => 'The page name "{var:1}" is used by two tabs.', 'vars' => $nm));
            }
            $seen_names[$lower] = true;
        }
        // The lock is a permission, not a hint on screen. A save that would
        // overwrite somebody else's open page is refused HERE — disabling the
        // Save button only stops the operator who is looking at it, and this
        // POST does not have to come from that screen.
        //
        // Skipped, not fatal: the editor saves every tab at once, and one
        // page somebody else is holding must not cost the operator the work
        // they did on the other four.
        if (!pg_collab_may_edit_page($collab_key, isset($p['page_id']) ? (int)$p['page_id'] : 0)) {
            $locked_pages[(int)$p['page_id']] = true;
            $warnings[] = lang(array(
                'string' => 'The page "{var:1}" is open for editing by somebody else, so your changes to it were not saved.',
                'vars'   => ($nm !== '' ? $nm : (string)(isset($p['page_id']) ? $p['page_id'] : ''))));
            continue;
        }
        $r = pg_designer_save_page($style_id, $p, $user, true);
        foreach ($r['errors'] as $e) $errors[] = $e;
        foreach ($r['warnings'] as $w) $warnings[] = $w;
    }
    if ($style_name === '' && empty($errors)) $errors[] = lang('Name is required.');
    if (!empty($errors)) $fail($errors);

    // ── Write: style first (pages need its id and body classes) ───────────
    //
    // The style is the DESIGN: stylesheets, scripts, fonts, theme, body
    // classes. It is shared by every page in it, so a content-level operator
    // never writes it — their save keeps the stored row exactly as it is and
    // touches only the pages they may edit.
    require_once(dirname(__FILE__) . '/designer_access.php');
    $can_write_design = pg_designer_is_full($user);
    if (!$can_write_design && $style_id <= 0) {
        $fail(array(lang('You do not have access to add a design.')));
    }

    $style_data = array(
        'style_id'                          => $style_id,
        'name'                              => $style_name,
        'theme_id'                          => $liveform->get_field_value('theme_id'),
        'additional_body_classes'           => $liveform->get_field_value('additional_body_classes'),
        'collection'                        => $liveform->get_field_value('collection'),
        'social_networking_position'        => $liveform->get_field_value('social_networking_position'),
        'style_head'                        => $liveform->get_field_value('style_head'),
        'style_empty_cell_width_percentage' => $liveform->get_field_value('style_empty_cell_width_percentage'),
        'user_id'                           => $user['id'],
        // Hidden inputs the assets panel writes to are not registered with
        // liveform, so they are read straight from POST.
        'style_custom_css'                  => isset($_POST['style_custom_css'])   ? (string)$_POST['style_custom_css']   : '',
        'style_custom_js'                   => isset($_POST['style_custom_js'])    ? (string)$_POST['style_custom_js']    : '',
        'style_custom_fonts'                => isset($_POST['style_custom_fonts']) ? (string)$_POST['style_custom_fonts'] : '',
    );
    // On an un-migrated database the style still carries the tree; keep the
    // first page's so the old readers see something.
    if (!pg_multi_page_design_ready() && isset($pages[0]['tree_json'])) {
        $style_data['style_tree_json'] = (string)$pages[0]['tree_json'];
    }
    if ($can_write_design) {
        $style_id = (int)save_system_style($style_data);
        // A failed INSERT returns 0 (queries do not throw). Writing the
        // pages against style 0 would leave them on no design at all and
        // send the browser to edit_system_style.php?id=0.
        if ($style_id <= 0) {
            $fail(array(lang('The design could not be saved. Please try again.')));
        }
        log_activity(lang(array('string' => ($mode === 'edit' ? 'style ({var:1}) was modified' : 'style ({var:1}) was added'), 'vars' => array($style_name))), $_SESSION['sessionusername']);
    }

    // ── Design-wide body classes: fold them onto the pages, once ──────────
    //
    // "Additional Body Classes" was a design-level text field, and the body
    // it described is a PAGE. Every page in a design got the same classes and
    // no page could have its own — which read as "page body classes do not
    // work", because they did not.
    //
    // The setting is gone. Whatever it held is merged into each page's own
    // body classes on the first save after the upgrade and the column is
    // cleared: dropping it would silently strip classes a live site is
    // styled with, and leaving it would keep applying a value with no editor
    // behind it.
    $legacy_body_classes = ($mode === 'edit' && $style_id > 0)
        ? trim((string)db_value("SELECT additional_body_classes FROM style WHERE style_id = '$style_id' LIMIT 1"))
        : '';
    if ($legacy_body_classes !== '') {
        foreach ($pages as $pi => $pp) {
            if (!is_array($pp) || !isset($pp['tree_json'])) continue;
            $pt = json_decode((string)$pp['tree_json'], true);
            if (!is_array($pt)) continue;
            if (!isset($pt['props']) || !is_array($pt['props'])) $pt['props'] = array();
            $have = isset($pt['props']['cssClass']) ? trim((string)$pt['props']['cssClass']) : '';
            $merged = array();
            foreach (preg_split('/\s+/', $legacy_body_classes . ' ' . $have) as $cls) {
                $cls = trim($cls);
                if ($cls !== '' && !in_array($cls, $merged, true)) $merged[] = $cls;
            }
            $pt['props']['cssClass'] = implode(' ', $merged);
            $pages[$pi]['tree_json'] = pg_designer_tree_encode($pt);
        }
        if ($can_write_design) {
            db("UPDATE style SET additional_body_classes = '' WHERE style_id = '$style_id'");
        }
        $warnings[] = lang('The design-wide body classes were moved onto each page. Edit them on the page body from now on.');
    }

    // ── Write: pages ──────────────────────────────────────────────────────
    $id_map  = array();
    $cf_jobs = array();   // page_id => form settings, reconciled once every page has its id
    foreach ($pages as $p) {
        $key = isset($p['key']) ? (string)$p['key'] : '';
        // Warned about above; the id still goes into the map so the tab keeps
        // working, it just does not get written.
        if (isset($locked_pages[(int)(isset($p['page_id']) ? $p['page_id'] : 0)])) {
            $id_map[] = array('key' => $key, 'page_id' => (int)$p['page_id']);
            continue;
        }
        $r = pg_designer_save_page($style_id, $p, $user, false);
        if ($r['ok']) {
            $id_map[] = array('key' => $key, 'page_id' => (int)$r['page_id']);
            // The page's form. Its fields are the controls its custom_form
            // widget draws, read from the widget tree the editor flushed just
            // before this request; the editor sends only the form-level
            // settings (`forms[0].settings`). A page created in this very
            // save had no id when the editor serialised it, which is why
            // the settings travel with the page rather than in a request of
            // their own — and why the reconcile waits until the loop is
            // done: its "next page" may be a tab saved later in this loop.
            if (function_exists('pg_cf_reconcile_page_form')) {
                $cf_set = array();
                if (isset($p['forms'])) {
                    $cf_forms = is_array($p['forms']) ? $p['forms'] : json_decode((string)$p['forms'], true);
                    foreach ((array)$cf_forms as $cf_form) {
                        if (!is_array($cf_form)) continue;
                        $cf_pid = (int)(isset($cf_form['page_id']) ? $cf_form['page_id'] : 0);
                        if ($cf_pid > 0 && $cf_pid !== (int)$r['page_id']) continue;   // another page's form is shown here, never written from here
                        if (isset($cf_form['settings']) && is_array($cf_form['settings'])) $cf_set = $cf_form['settings'];
                    }
                }
                // A form with no name yet takes the page title, as the
                // legacy screen would have.
                if (trim((string)(isset($cf_set['form_name']) ? $cf_set['form_name'] : '')) === '') {
                    unset($cf_set['form_name']);
                }
                $cf_jobs[(int)$r['page_id']] = $cf_set;
            }
        } else {
            // Validation already passed once; reaching here means the
            // database changed under us. Report, don't hide.
            foreach ($r['errors'] as $e) $warnings[] = $e;
        }
    }

    // ── Pages named before they existed ───────────────────────────────────
    // A widget setting or a form's "next page" may point at a tab that had
    // no id when the editor wrote it (`tab:<key>`). Every page has its id
    // now; the keys become ids in the widget rows and in the settings about
    // to be written. A key that matches no page (the tab was closed) is
    // "nothing chosen".
    $tab_ids = array();
    foreach ($id_map as $m) {
        if ($m['key'] !== '' && $m['page_id'] > 0) $tab_ids[$m['key']] = (int)$m['page_id'];
    }
    pg_designer_resolve_tab_refs($pages, $tab_ids);
    foreach ($cf_jobs as $cf_page_id => $cf_set) {
        $cf_set = pg_designer_resolve_tab_values($cf_set, $tab_ids);
        $cf_res = pg_cf_reconcile_page_form($cf_page_id, $user['id'], $cf_set);
        if (!empty($cf_res['deleted'])) {
            $warnings[] = lang(array(
                'string' => '{var:1} form field{suffix:1} deleted, along with {var:2} submitted value{suffix:2}.',
                'vars'   => array((int)$cf_res['deleted'], (int)$cf_res['lost_values']),
                'suffix' => array((int)$cf_res['deleted'] === 1 ? '' : 's',
                                  (int)$cf_res['lost_values'] === 1 ? '' : 's'),
            ));
        }
    }

    // Assets now live on the design. Clear the per-page copies so the
    // render-time fallback can never resurrect a stylesheet the operator
    // deleted from the design.
    if ($can_write_design && pg_multi_page_design_ready()) {
        db("UPDATE page SET page_custom_css = '', page_custom_js = '', page_custom_fonts = ''
            WHERE page_style = '$style_id' AND layout_type = 'system'");
    }

    // Asset files follow the design's stylesheets and scripts, which a
    // content-level operator did not write.
    if ($can_write_design) {
        pg_designer_sync_asset_files($style_data['style_custom_css'], $style_data['style_custom_js'], $user['id']);
    }

    // Score what was just saved. Both halves changed hands in this request
    // — settings (meta) and layout (structure) — and the pages list is one
    // click away; a number describing the previous version would be worse
    // than none. Same pair of calls save_region_content.php makes.
    $seo_ids = array();
    foreach ($id_map as $m) { if ($m['page_id'] > 0) $seo_ids[] = (int)$m['page_id']; }
    if (count($seo_ids) > 0) {
        require_once(dirname(__FILE__) . '/../seo.php');
        if (pg_seo_schema_ready()) {
            require_once(dirname(__FILE__) . '/../seo_structure.php');
            foreach ($seo_ids as $sid) {
                try { pg_seo_analyze_record('page', $sid); } catch (Throwable $t) { /* the score is advisory; the save is not */ }
            }
            try { pg_seo_recalculate('page', $seo_ids); } catch (Throwable $t) {}
        }
    }

    $notice = lang('The style has been saved.');
    foreach ($warnings as $w) $liveform->add_notice($w);

    if ($is_ajax) {
        $liveform->remove_form();
        header('Content-Type: application/json; charset=utf-8');
        // Each page's form as it now stands on the server — the row ids the
        // reconcile handed out. The editor writes them onto its cached
        // controls so a control renamed before the next save is still the
        // same row, and every submission attached to it keeps its value.
        $forms_now = array();
        if (function_exists('pg_cf_load_page_fields')) {
            foreach ($id_map as $m) {
                $pid = (int)$m['page_id'];
                if ($pid <= 0) continue;
                $forms_now[] = array(
                    'key'      => $m['key'],
                    'page_id'  => $pid,
                    'fields'   => pg_cf_load_page_fields($pid),
                    'settings' => function_exists('pg_cf_load_page_form_settings') ? pg_cf_load_page_form_settings($pid) : array(),
                );
            }
        }
        $reply = array(
            'status'     => 'success',
            'message'    => $notice,
            'saved_ts'   => time(),
            'saved_by'   => isset($_SESSION['sessionusername']) ? $_SESSION['sessionusername'] : '',
            'style_id'   => $style_id,
            'pages'      => $id_map,
            'forms'      => $forms_now,
            'warnings'   => $warnings,
        );
        // Add mode: the page must move to the edit URL so the next save is
        // an UPDATE and the toolbar gains Duplicate / Delete.
        if ($mode !== 'edit') {
            $reply['redirect_url'] = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_system_style.php?id=' . $style_id
                                   . ($ctx['from_pages'] ? '&send_to=' . urlencode(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_pages.php') : '');
        }
        echo encode_json($reply);
        exit();
    }

    $liveform->remove_form();
    if ($mode !== 'edit') {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_system_style.php?id=' . $style_id);
        exit();
    }
    if ($liveform->get_field_value('send_to') != '') {
        header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path($liveform->get_field_value('send_to')));
    } else {
        $lf_view = new liveform('view_system_styles');
        $lf_view->add_notice($notice);
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_system_styles.php');
    }
    exit();
}
