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

/**
 * Cache-busting stamp for one of the editor's own asset files.
 *
 * The browser is meant to keep these until they actually change. A stamp that
 * moves on its own — time() was here — costs a fresh download and a fresh
 * compile of every asset on every load, and the editor script is megabytes of
 * it. The file's own modification time changes exactly when the file does,
 * which is the whole job. Same stamp the rest of the software uses for its
 * assets (includes/erp/invoice_form.php, includes/fn/designer.php).
 *
 * Falls back to the software version if the file cannot be stat'd — still a
 * value that moves on upgrade, which is the case that matters.
 *
 * @param  string $relative_path  path under the software directory
 * @return string
 */
function pg_designer_asset_stamp($relative_path)
{
    $stamp = @filemtime(PG_FUNCTIONS_DIR . '/' . $relative_path);
    if ($stamp) {
        return (string)$stamp;
    }

    return defined('VERSION') ? (string)VERSION : '0';
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
 *                   last_modified_timestamp, last_modified_username,
 *                   tab_layout (style_tab_layout JSON; '' in add mode)
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

    // The design's framework; a style from before 2026.4.5 is Bootstrap 5.
    $sd_framework = pg_design_framework(isset($style['framework']) ? $style['framework'] : '');

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
            'page_draft'            => (int)(isset($p['page_draft']) ? $p['page_draft'] : 0),
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
            'pg_comments_auto_publish' => (int)$p['pg_comments_auto_publish'],
            'pg_comments_show_date'    => (int)$p['pg_comments_show_date'],
            'pg_comments_login'        => (int)$p['pg_comments_login'],
            'pg_comments_email_page'    => (int)(isset($p['pg_comments_email_page']) ? $p['pg_comments_email_page'] : 0),
            'pg_comments_email_subject' => (string)(isset($p['pg_comments_email_subject']) ? $p['pg_comments_email_subject'] : ''),
            'pg_comments_notify_email'  => (string)(isset($p['pg_comments_notify_email']) ? $p['pg_comments_notify_email'] : ''),
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
            // A page the editor creates starts as a draft (Save keeps it
            // off the site, Publish puts it on) where drafts exist and the
            // operator decides them.
            'page_draft'            => ($access === PG_DESIGNER_ACCESS_FULL && pg_page_draft_ready()) ? 1 : 0,
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
            'pg_comments_auto_publish' => 0,
            'pg_comments_show_date'    => 0,
            'pg_comments_login'        => 0,
            'pg_comments_email_page'    => 0,
            'pg_comments_email_subject' => '',
            'pg_comments_notify_email'  => '',
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

    require_once(PG_FUNCTIONS_DIR . '/includes/designer_ai.php');
    $sd_ai = pg_design_ai_editor_config($user);

    // The order and groups of the tabs, for the pages listed above.
    $tab_page_ids = array();
    foreach ($js_pages as $jp) {
        if ($jp['page_id'] > 0) $tab_page_ids[] = $jp['page_id'];
    }
    $tab_layout = pg_designer_tab_layout_normalize(isset($style['tab_layout']) ? $style['tab_layout'] : '', $tab_page_ids);

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
        // The assistant panel (assets/js/designer_ai.js): designers only.
        'ai'            => $sd_ai,
        // The Translate section of the options panel: the site's other
        // languages, null on a site with one language.
        'translate'     => function_exists('pg_tr_editor_config') ? pg_tr_editor_config($user) : null,
        // Drafts (2026.4.7): whether pages can be kept off the site, and
        // whether this operator decides it (designers; a content-level
        // operator sees the state only).
        'drafts'        => array(
            'ready'     => pg_page_draft_ready(),
            'canChange' => pg_page_draft_ready() && ($access === PG_DESIGNER_ACCESS_FULL),
        ),
        // Tab order and groups (2026.4.8): kept in style_tab_layout, written
        // by designer/tab_layout. Without the column the strip is plain.
        'tabLayout'     => array(
            'ready'  => pg_style_tab_layout_ready(),
            'layout' => pg_designer_tab_layout_export($tab_layout),
        ),
        // The site's languages, source first: what the language switcher
        // component lists on the canvas (pg_language_switcher_editor_languages()).
        'siteLanguages' => pg_language_switcher_editor_languages(),
        'autoImport'    => !empty($ctx['auto_import']),
        'autoPasteHtml' => !empty($ctx['auto_paste']),
        // What the design is built on (pg_design_frameworks()). The editor
        // loads the framework's files into the canvas and offers the
        // Bootstrap palette only when the framework is Bootstrap.
        'framework'     => $sd_framework['key'],
        'frameworkInfo' => array(
            'label'     => $sd_framework['label'],
            'css'       => $sd_framework['css'],
            'js'        => $sd_framework['js'],
            'bootstrap' => (bool)$sd_framework['bootstrap'],
        ),
        // Add mode from a template: the editor asks for its pages
        // (designer/template_prepare) behind the "preparing" curtain.
        'template'      => !empty($ctx['template']) ? $ctx['template'] : null,
        // Looks and colour palettes (includes/fn/design_themes.php): every
        // choice with its stylesheet, the saved custom ones included.
        'themes'        => pg_design_theme_catalog(),
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
        <link rel="stylesheet" href="assets/css/style_designer.css?v=' . pg_designer_asset_stamp('assets/css/style_designer.css') . '">
        <main id="content" style="padding:0; max-width:100%;">
            ' . $liveform->output_errors() . '
            ' . $liveform->output_notices() . '
            <!-- disable_shortcut: the editor answers Ctrl+S itself, with what its
                 main button says on the page on screen (style_designer.js). -->
            <form id="style_designer_form" name="style_designer_form" class="disable_shortcut" action="' . $form_action . '" method="post" style="height:100%;">
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
                <input type="hidden" name="pg_comments_auto_publish" value="0">
                <input type="hidden" name="pg_comments_show_date" value="0">
                <input type="hidden" name="pg_comments_login" value="0">
                <input type="hidden" name="pg_comments_email_page" value="0">
                <input type="hidden" name="pg_comments_email_subject" value="">
                <input type="hidden" name="pg_comments_notify_email" value="">
                <!-- Shared design assets — managed by the Assets panel; one set for every page -->
                <input type="hidden" name="style_custom_css" id="sd-custom-css-field" value="' . h($style['style_custom_css']) . '">
                <input type="hidden" name="style_custom_js" id="sd-custom-js-field" value="' . h($style['style_custom_js']) . '">
                <input type="hidden" name="style_custom_fonts" id="sd-custom-fonts-field" value="' . h($style['style_custom_fonts']) . '">
                <!-- Fixed when the design is created; the save of a new design
                     writes them, an update leaves the stored ones alone. -->
                <input type="hidden" name="style_framework" value="' . h($sd_framework['key']) . '">
                <input type="hidden" name="style_template" value="' . h(isset($style['template']) ? $style['template'] : '') . '">
                <input type="hidden" name="style_template_version" value="' . h(isset($style['template_version']) ? $style['template_version'] : '') . '">
                <!-- The look and the colour palette (Settings, Design). -->
                <input type="hidden" name="style_look" id="sd-style-look" value="' . h(isset($style['look']) ? pg_design_look_key($style['look']) : '') . '">
                <input type="hidden" name="style_palette" id="sd-style-palette" value="' . h(isset($style['palette']) ? pg_design_palette_key($style['palette']) : '') . '">

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
                            ' . (!empty($sd_ai['enabled']) ? '<button type="button" id="sd-ai-btn" class="sd-icon-btn sd-ai-btn" title="' . h(lang('Ask the assistant')) . '" aria-expanded="false"><span class="bi bi-stars" aria-hidden="true"></span><span class="sd-ai-btn-txt">' . h(lang('Assistant')) . '</span><span class="sd-ai-btn-dot" aria-hidden="true"></span></button>' : '') . '
                            <button type="button" id="sd-main-undo" class="sd-vb-btn" onclick="StyleDesigner.undo()" title="' . lang('Undo') . ' (Ctrl+Z)" disabled><span class="bi bi-arrow-counterclockwise"></span></button>
                            <button type="button" id="sd-main-redo" class="sd-vb-btn" onclick="StyleDesigner.redo()" title="' . lang('Redo') . ' (Ctrl+Y)" disabled><span class="bi bi-arrow-clockwise"></span></button>
                            <!-- The main action puts every page of the design on the
                                 site, so it says so. The label drops to the icon on
                                 narrow screens; the title still names it. -->
                            <button type="button" id="sd-ajax-save" name="submit_save" value="Save" class="sd-icon-btn sd-icon-btn-primary sd-publish-btn" title="' . lang('Publish') . '"><span class="bi bi-rocket-takeoff" aria-hidden="true"></span><span class="sd-publish-txt">' . lang('Publish') . '</span></button>
                            ' . (($access === PG_DESIGNER_ACCESS_FULL && pg_page_draft_ready()) ? '<!-- A draft page has two actions: the main button saves it
                                 (it stays a draft), this one puts it on the
                                 site. Hidden while the page on screen is on
                                 the site. -->
                            <button type="button" id="sd-publish-page" class="sd-icon-btn sd-icon-btn-primary sd-publish-btn d-none" title="' . h(lang('Publishes this page and saves the design.')) . '"><span class="bi bi-rocket-takeoff" aria-hidden="true"></span><span class="sd-publish-txt">' . lang('Publish') . '</span></button>
                            <!-- Draft or on the site: this page, or every page of the design.
                                 Each item sets the state and saves, the same
                                 save the main button makes. -->
                            <div class="dropdown sd-publish-more">
                                <button type="button" id="sd-publish-more" class="sd-icon-btn sd-icon-btn-primary sd-publish-more-btn" data-bs-toggle="dropdown" aria-expanded="false" title="' . h(lang('Publishing options')) . '" aria-label="' . h(lang('Publishing options')) . '"><span class="bi bi-chevron-down" aria-hidden="true"></span></button>
                                <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">
                                    <li><button type="button" class="dropdown-item" data-sd-status="page-draft"><span class="bi bi-eye-slash me-2" aria-hidden="true"></span>' . lang('Save this page as a draft') . '</button></li>
                                    <li><button type="button" class="dropdown-item" data-sd-status="page-publish"><span class="bi bi-rocket-takeoff me-2" aria-hidden="true"></span>' . lang('Publish this page') . '</button></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><button type="button" class="dropdown-item" data-sd-status="all-draft"><span class="bi bi-cloud-slash me-2" aria-hidden="true"></span>' . lang('Take every page of the design off the site') . '</button></li>
                                    <li><button type="button" class="dropdown-item" data-sd-status="all-publish"><span class="bi bi-cloud-check me-2" aria-hidden="true"></span>' . lang('Publish every page of the design') . '</button></li>
                                </ul>
                            </div>' : '') . '
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
                ' . pg_designer_make_template_modal() . '
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
            // How the site writes money and dates, for the canvas placeholders.
            var software_money_format = ' . (function_exists('pg_money_format_json') ? pg_money_format_json() : 'null') . ';
            var software_date_samples = ' . (function_exists('pg_sw_default_date_format')
                ? json_encode(array(
                    'date'     => date(pg_sw_default_date_format('date')),
                    'datetime' => date(pg_sw_default_date_format('date and time')),
                ), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP)
                : 'null') . ';
            window.PgCodeModalLabels = ' . encode_json(array(
                'Code Editor' => lang('Code Editor'),
                'Close'       => lang('Close'),
                'Cancel'      => lang('Cancel'),
                'Save'        => lang('Save'),
                'Code'        => lang('Code'),
            )) . ';
        </script>
        <script src="assets/js/codemirror_modal.js?v=' . pg_designer_asset_stamp('assets/js/codemirror_modal.js') . '"></script>
        <script src="assets/js/class_suggestions.js?v=' . pg_designer_asset_stamp('assets/js/class_suggestions.js') . '"></script>
        <script src="assets/js/style_designer.js?v=' . pg_designer_asset_stamp('assets/js/style_designer.js') . '"></script>
        ' . (!empty($sd_ai['enabled']) ? '<script src="assets/js/designer_ai.js?v=' . pg_designer_asset_stamp('assets/js/designer_ai.js') . '"></script>' : '') . '
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
    $rows = is_array($rows) ? $rows : array();
    // The framework each design is built on; a design from before the
    // column is Bootstrap 5.
    $fw_by_id = array();
    if ($rows && pg_style_framework_ready()) {
        $ids = array();
        foreach ($rows as $r) $ids[] = (int)$r['style_id'];
        foreach ((array)db_items("SELECT style_id, style_framework FROM style WHERE style_id IN (" . implode(',', $ids) . ")") as $fr) {
            $fw_by_id[(int)$fr['style_id']] = (string)$fr['style_framework'];
        }
    }
    foreach ($rows as $i => $r) {
        $fw = pg_design_framework(isset($fw_by_id[(int)$r['style_id']]) ? $fw_by_id[(int)$r['style_id']] : '');
        $rows[$i]['framework_label'] = $fw['label'];
        $rows[$i]['framework_bootstrap'] = !empty($fw['bootstrap']);
        $rows[$i]['template'] = '';
        $rows[$i]['look'] = '';
        $rows[$i]['palette'] = '';
        $rows[$i]['draft_count'] = 0;
        $rows[$i]['home_count'] = 0;
    }
    // Off the site and on it: the drafts and the home page of each design,
    // for the Status column.
    if ($rows && function_exists('pg_designer_design_draft_counts')) {
        $by_style = pg_designer_design_draft_counts(array_map(function ($r) { return (int)$r['style_id']; }, $rows));
        foreach ($rows as $i => $r) {
            $c = isset($by_style[(int)$r['style_id']]) ? $by_style[(int)$r['style_id']] : null;
            if ($c) { $rows[$i]['draft_count'] = $c['drafts']; $rows[$i]['home_count'] = $c['home']; }
        }
    }
    // The template a design started from, its look and its palette: the
    // thumbnail beside the name and the Theme column.
    if ($rows) {
        $cols = array();
        if (pg_style_framework_ready()) $cols[] = 'style_template';
        if (function_exists('pg_design_look_ready') && pg_design_look_ready()) { $cols[] = 'style_look'; $cols[] = 'style_palette'; }
        if ($cols) {
            $by_id = array();
            foreach ($rows as $i => $r) $by_id[(int)$r['style_id']] = $i;
            foreach ((array)db_items("SELECT style_id, " . implode(', ', $cols) . " FROM style WHERE style_id IN (" . implode(',', array_keys($by_id)) . ")") as $x) {
                $i = $by_id[(int)$x['style_id']];
                if (isset($x['style_template'])) $rows[$i]['template'] = (string)$x['style_template'];
                if (isset($x['style_look']))     $rows[$i]['look'] = (string)$x['style_look'];
                if (isset($x['style_palette']))  $rows[$i]['palette'] = (string)$x['style_palette'];
            }
        }
    }
    return $rows;
}

/**
 * The Status cell of a design on the designs list: on the site, a draft, or
 * partly on the site, and the buttons that take every page off the site or
 * put the drafts back on it (api.php designer/design_publish). The home page
 * stays on the site, so a design that holds it is a draft without it.
 *
 * @param array $d          a pg_designer_list_designs() row
 * @param bool  $can_change the operator decides drafts
 * @return string
 */
function pg_designer_list_status_cell($d, $can_change)
{
    $c = array('pages' => (int)$d['page_count'], 'drafts' => (int)$d['draft_count'], 'home' => (int)$d['home_count']);
    $s = pg_designer_design_state($c);

    if ($s['state'] === 'empty') return '<span class="text-body-secondary">—</span>';

    if ($s['state'] === 'live') {
        $html = '<span class="badge rounded-pill text-bg-success"><i class="bi bi-broadcast me-1" aria-hidden="true"></i>' . lang('Live on the site') . '</span>';
    } elseif ($s['state'] === 'draft') {
        $html = '<span class="badge rounded-pill text-bg-secondary"><i class="bi bi-eye-slash me-1" aria-hidden="true"></i>' . lang('Draft') . '</span>'
              . ($c['home'] > 0 ? '<div class="small text-body-secondary mt-1">' . lang('The home page stays on the site.') . '</div>' : '');
    } else {
        $html = '<span class="badge rounded-pill text-bg-warning"><i class="bi bi-circle-half me-1" aria-hidden="true"></i>' . lang('Partly on the site') . '</span>'
              . '<div class="small text-body-secondary mt-1">' . lang(array('string' => '{var:1} of {var:2} pages are drafts', 'vars' => array($c['drafts'], $c['pages']))) . '</div>';
    }

    if ($can_change && pg_page_draft_ready()) {
        $name = h($d['style_name']);
        $buttons = '';
        if ($s['offable'] > 0) {
            $buttons .= '<button type="button" class="btn btn-sm btn-outline-secondary sd-design-status" data-style-id="' . (int)$d['style_id'] . '" data-name="' . $name . '" data-draft="1" data-count="' . $s['offable'] . '"><i class="bi bi-cloud-slash me-1" aria-hidden="true"></i>' . lang('Take off the site') . '</button>';
        }
        if ($c['drafts'] > 0) {
            $buttons .= '<button type="button" class="btn btn-sm btn-outline-success sd-design-status" data-style-id="' . (int)$d['style_id'] . '" data-name="' . $name . '" data-draft="0" data-count="' . $c['drafts'] . '"><i class="bi bi-cloud-check me-1" aria-hidden="true"></i>' . lang('Put on the site') . '</button>';
        }
        if ($buttons !== '') $html .= '<div class="d-flex flex-wrap gap-1 mt-2">' . $buttons . '</div>';
    }

    return $html;
}

/**
 * The thumbnail beside a design's name: its template's picture in the
 * design's own look and colours. A design started blank or imported has no
 * picture of its own; it gets an empty frame so the column stays even.
 */
function pg_designer_list_thumb($d)
{
    $tpl = !empty($d['template']) ? pg_design_template($d['template']) : null;
    if ($tpl && !empty($d['framework_bootstrap']) && function_exists('pg_design_thumb_svg')) {
        return '<span class="sd-design-thumb" title="' . h(pg_designer_list_theme_text($d)) . '">'
             . pg_design_thumb_svg($d['look'], $d['palette'], '', lang(array('string' => 'Preview of {var:1}', 'vars' => $d['style_name'])), isset($tpl['thumb']) ? (string)$tpl['thumb'] : '')
             . '</span>';
    }
    return '<span class="sd-design-thumb-blank" aria-hidden="true"><i class="bi bi-file-earmark"></i></span>';
}

// "Modern Soft · Ocean" (+ the theme file, when one is chosen).
function pg_designer_list_theme_text($d)
{
    $parts = array();
    if (!empty($d['framework_bootstrap']) && function_exists('pg_design_look_label')) {
        $look = pg_design_look_label(isset($d['look']) ? $d['look'] : '');
        $parts[] = ($look !== '') ? $look : lang('Plain Bootstrap');
        $pal = pg_design_palette_key(isset($d['palette']) ? $d['palette'] : '');
        if ($pal !== '') {
            $fid = pg_design_theme_file_id($pal);
            if ($fid > 0) {
                $files = pg_design_theme_files('palette');
                if (isset($files[$fid])) $parts[] = $files[$fid]['name'];
            } else {
                $all = pg_design_palettes();
                $parts[] = $all[$pal]['name'];
            }
        }
    }
    if (!empty($d['theme_name'])) $parts[] = (string)$d['theme_name'];
    return implode(' · ', $parts);
}

function pg_designer_list_theme_label($d)
{
    $text = pg_designer_list_theme_text($d);
    if ($text === '') return '';
    $sw = '';
    if (!empty($d['framework_bootstrap']) && !empty($d['palette']) && function_exists('pg_design_palette_colors')) {
        $c = pg_design_palette_colors($d['palette']);
        $sw = '<span class="d-inline-flex rounded-circle overflow-hidden me-2 align-middle" style="width:14px;height:14px;transform:rotate(-45deg);box-shadow:0 0 0 1px var(--bs-border-color)" aria-hidden="true">'
            . '<i style="flex:1;background:' . h($c['primary']) . '"></i><i style="flex:1;background:' . h($c['secondary']) . '"></i></span>';
    }
    return $sw . h($text);
}

/**
 * The Visual Page Editor home (view_system_styles.php): start a new design
 * blank (on Bootstrap 5, or custom without a framework), from an HTML
 * project or from a template, or open one of the designs that exist. The
 * list is the editor's own designs only — a legacy style opened here would
 * show a blank page and nothing to explain why.
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

    // The templates this installation ships (includes/design_templates/).
    $templates = $can_create ? pg_design_templates() : array();

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
            <td class="align-middle sd-design-thumb-cell">' . pg_designer_list_thumb($d) . '</td>
            <td class="align-middle" nowrap>' . h($d['framework_label']) . '</td>
            <td class="align-middle text-center" data-order="' . $count . '">' . pg_format_number($count, 0) . '</td>
            <td class="align-middle sd-design-status-cell" data-order="' . (int)$d['draft_count'] . '">' . pg_designer_list_status_cell($d, $can_create) . '</td>
            <td class="align-middle">' . pg_designer_list_theme_label($d) . '</td>
            <td class="align-middle" nowrap data-order="' . (int)$d['last_modified_timestamp'] . '">' . get_relative_time(array('timestamp' => (int)$d['last_modified_timestamp'])) . '  ' . h($user_label) . '</td>
        </tr>';
    }

    $card = function ($href, $icon, $title, $text, $opts = array()) {
        $primary  = !empty($opts['primary']);
        $disabled = !empty($opts['disabled']);
        $modal    = !empty($opts['modal']) ? (string)$opts['modal'] : '';
        $start    = !empty($opts['start']) ? (string)$opts['start'] : '';
        $badge    = $disabled ? '<span class="badge text-bg-secondary ms-auto">' . lang('Soon') . '</span>' : '';
        if ($disabled) {
            $tag_open = '<div class="card h-100 sd-start-card sd-start-card-disabled" aria-disabled="true">';
        } elseif ($modal !== '') {
            // Opens a choice first (framework, template); nothing loads yet.
            $tag_open = '<a href="#" role="button" class="card h-100 text-decoration-none sd-start-card' . ($primary ? ' border-primary' : '') . '" data-bs-toggle="modal" data-bs-target="' . h($modal) . '"' . ($start !== '' ? ' data-sd-start="' . h($start) . '"' : '') . '>';
        } else {
            $tag_open = '<a href="' . h($href) . '" class="card h-100 text-decoration-none sd-start-card' . ($primary ? ' border-primary' : '') . '" data-loading-content="' . lang('Loading') . '">';
        }
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
            .pg-design-thumb { display: block; width: 100%; height: auto; }
            .sd-design-thumb-cell { width: 96px; }
            .sd-design-thumb { display: block; width: 88px; border-radius: 6px; overflow: hidden; box-shadow: 0 0 0 1px var(--bs-border-color); background: var(--bs-body-bg); }
            .sd-design-thumb-blank { display: flex; align-items: center; justify-content: center; width: 88px; aspect-ratio: 8 / 5; border-radius: 6px; border: 1px dashed var(--bs-border-color); color: var(--bs-secondary-color); }
            #sdTemplateModal .sd-tpl-dialog { --bs-modal-width: min(96vw, 1600px); }
            .sd-tpl-thumb { position: relative; border-bottom: 1px solid var(--bs-border-color); background: var(--bs-tertiary-bg); }
            .sd-tpl-ver { font-size: .6875rem; line-height: 1.4; white-space: nowrap; }
            .sd-tpl-thumb .sd-tpl-ver { position: absolute; right: .5rem; bottom: .5rem; padding: .1rem .45rem; border-radius: 999px; background: rgba(var(--bs-body-bg-rgb), .82); color: var(--bs-secondary-color); box-shadow: 0 0 0 1px var(--bs-border-color-translucent); }
            .sd-tpl-title { min-width: 0; }
            .sd-tpl-look .btn { --bs-btn-padding-y: .25rem; --bs-btn-padding-x: .6rem; --bs-btn-font-size: .8125rem; }
            .sd-tpl-pal { display: inline-flex; width: 28px; height: 28px; padding: 0; border-radius: 50%; overflow: hidden; border: 2px solid transparent; transform: rotate(-45deg); box-shadow: 0 0 0 1px var(--bs-border-color); cursor: pointer; }
            .sd-tpl-pal i { flex: 1; }
            .btn-check:checked + .sd-tpl-pal { border-color: var(--bs-body-bg); box-shadow: 0 0 0 2px var(--bs-emphasis-color); }
            .btn-check:focus-visible + .sd-tpl-pal { box-shadow: 0 0 0 3px var(--bs-focus-ring-color, rgba(13,110,253,.25)), 0 0 0 1px var(--bs-border-color); }
        </style>
        <main id="content" class="container-fluid">
            ' . $liveform->output_errors() . '
            ' . $liveform->output_notices() . '
            ' . ($can_create ? '
            <div class="row g-3 mb-4">
                ' . $card('#', 'bi-file-earmark-plus', lang('Create New'), lang('An empty page on the canvas, on Bootstrap 5 or as a custom design. Add sections from the palette and build the design up.'), array('primary' => true, 'modal' => '#sdNewDesignModal')) . '
                ' . $card('#', 'bi-file-earmark-zip', lang('Import HTML / ZIP'), lang('Bring in a finished HTML page or a whole project archive. Pages become tabs, files go to the file manager.'), array('modal' => '#sdNewDesignModal', 'start' => 'import')) . '
                ' . $card('#', 'bi-clipboard-plus', lang('Paste HTML content'), lang('Paste the markup straight in — from a code editor, from a page you have open. The design starts from what you paste.'), array('modal' => '#sdNewDesignModal', 'start' => 'paste')) . '
                ' . $card('#', 'bi-grid-1x2', lang('Choose a Template'), lang('Start from a ready-made design and change what you like.'), $templates ? array('modal' => '#sdTemplateModal') : array('disabled' => true)) . '
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
                                <th class="no-sort">' . lang('Preview') . '</th>
                                <th>' . lang('Framework') . '</th>
                                <th class="text-center">' . lang('Pages') . '</th>
                                <th>' . lang('Status') . '</th>
                                <th>' . lang('Theme') . '</th>
                                <th nowrap>' . lang('Last Modified') . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_rows . '</tbody>
                    </table>
                </div>
            </div>
        </main>
        ' . ($can_create ? pg_designer_new_design_modal($from_pages) . pg_designer_template_modal($templates, $from_pages) : '') . '
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

            // Take every page of a design off the site, or put its drafts
            // back on it, from its row. The home page stays on the site. The
            // answer brings the row\'s new Status cell.
            document.addEventListener("click", function (e) {
                var btn = e.target.closest(".sd-design-status");
                if (!btn) return;
                e.preventDefault();
                var id = parseInt(btn.dataset.styleId, 10), off = (btn.dataset.draft === "1"), n = parseInt(btn.dataset.count, 10) || 0;
                var name = btn.dataset.name || "";
                var msg = off
                    ? ' . json_encode(lang('{var} pages will be taken off the site and kept in the private Drafts folder. Visitors will not be able to open them; administrators will.')) . '.replace("{var}", n) + " " + ' . json_encode(lang('The home page stays on the site.')) . '
                    : ' . json_encode(lang('The {var} draft pages of the design will be put on the site: visitors will be able to open them.')) . '.replace("{var}", n);
                var run = function () {
                    var cell = btn.closest("td");
                    cell.querySelectorAll(".sd-design-status").forEach(function (b) { b.disabled = true; });
                    fetch("api.php", { method: "POST", credentials: "same-origin", headers: { "Content-Type": "application/json" },
                           body: JSON.stringify({ action: "designer", sub_action: "design_publish", style_id: id, draft: off ? 1 : 0, token: (typeof software_token !== "undefined" ? software_token : "") }) })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (!res || res.status !== "success") {
                            cell.querySelectorAll(".sd-design-status").forEach(function (b) { b.disabled = false; });
                            if (typeof pgToast === "function") pgToast({ message: (res && res.message) || ' . json_encode(lang('Sorry, we could not accept your request.')) . ', variant: "danger" });
                            else alert((res && res.message) || ' . json_encode(lang('Sorry, we could not accept your request.')) . ');
                            return;
                        }
                        cell.innerHTML = res.cell || "";
                        if (typeof res.drafts !== "undefined") cell.setAttribute("data-order", String(res.drafts));
                        var table = cell.closest("table");
                        if (table && window.jQuery && jQuery.fn.dataTable && jQuery.fn.dataTable.isDataTable(table)) {
                            jQuery(table).DataTable().row(cell.closest("tr")).invalidate("dom");
                        }
                        if (typeof pgToast === "function") pgToast({ message: res.message, variant: "success" });
                    })
                    .catch(function () {
                        cell.querySelectorAll(".sd-design-status").forEach(function (b) { b.disabled = false; });
                        alert(' . json_encode(lang('Network error.')) . ');
                    });
                };
                var title = name + " — " + (off ? ' . json_encode(lang('Take off the site')) . ' : ' . json_encode(lang('Put on the site')) . ');
                if (typeof window.pgConfirm === "function") {
                    window.pgConfirm({ title: title, message: msg,
                                       confirmText: off ? ' . json_encode(lang('Yes, make it a draft')) . ' : ' . json_encode(lang('Put on the site')) . ',
                                       cancelText: ' . json_encode(lang('No')) . ', variant: off ? "warning" : "success" })
                        .then(function (ok) { if (ok) run(); });
                } else if (window.confirm(msg)) { run(); }
            });
        </script>
        ';
    print output_footer();
}

/**
 * "Create New": what the design is built on. Asked once, before the editor
 * opens, because the pages are written against it and it does not change.
 */
function pg_designer_new_design_modal($from_pages)
{
    $extra = $from_pages ? '&from=pages' : '';
    // $import_text: what the option means when the design starts from an
    // import or a paste (the modal is shared, see the script below).
    $option = function ($framework, $icon, $title, $text, $recommended, $import_text) use ($extra) {
        return
            '<div class="col-12 col-md-6">
                <a href="add_system_style.php?framework=' . h($framework) . h($extra) . '" class="card h-100 text-decoration-none sd-start-card sd-fw-choice' . ($recommended ? ' border-primary' : '') . '" data-href="add_system_style.php?framework=' . h($framework) . h($extra) . '" data-loading-content="' . lang('Loading') . '">
                    <div class="card-body d-flex flex-column align-items-start gap-2">
                        <div class="d-flex w-100 align-items-start">
                            <i class="bi ' . h($icon) . ' fs-2 ' . ($recommended ? 'text-primary' : 'text-body-secondary') . '" aria-hidden="true"></i>
                            ' . ($recommended ? '<span class="badge text-bg-primary ms-auto">' . lang('Recommended') . '</span>' : '') . '
                        </div>
                        <span class="h5 mb-0 text-body">' . h($title) . '</span>
                        <span class="small text-muted sd-fw-text" data-text-new="' . h($text) . '" data-text-import="' . h($import_text) . '">' . h($text) . '</span>
                    </div>
                </a>
            </div>';
    };
    return '
        <div class="modal fade" id="sdNewDesignModal" tabindex="-1" aria-labelledby="sdNewDesignModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title fs-5" id="sdNewDesignModalLabel"><i class="bi bi-file-earmark-plus me-2 sd-fw-icon" aria-hidden="true"></i><span class="sd-fw-title" data-text-new="' . h(lang('Create New Design')) . '" data-text-import="' . h(lang('Import HTML / ZIP')) . '" data-text-paste="' . h(lang('Paste HTML content')) . '">' . lang('Create New Design') . '</span></h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' . lang('Close') . '"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted mb-3 sd-fw-intro" data-text-new="' . h(lang('Choose what the design is built on. The pages are written against it, so it cannot be changed later.')) . '" data-text-import="' . h(lang('What is the project built on? Bootstrap 5 projects use the editor\'s Bootstrap; a project on another framework (Bootstrap 6, Tailwind…) or none goes into a custom design, with its own files. This cannot be changed later.')) . '">' . lang('Choose what the design is built on. The pages are written against it, so it cannot be changed later.') . '</p>
                        <div class="row g-3">
                            ' . $option('bootstrap5', 'bi-bootstrap', lang('Build with Bootstrap 5'),
                                lang('Bootstrap 5.3 is loaded on every page. The grid, the Bootstrap components and the ready-made blocks are in the palette.'), true,
                                lang('For a project on Bootstrap 5. The editor\'s Bootstrap 5.3 is used; the project\'s own Bootstrap and jQuery files are left out.')) . '
                            ' . $option('custom', 'bi-code-slash', lang('Build a Custom Design'),
                                lang('No framework is loaded: bootstrap.css and bootstrap.js stay out. The palette offers plain HTML elements, text, forms and system widgets; the styles are yours.'), false,
                                lang('For any other project (Bootstrap 6, Tailwind, your own CSS). Its CSS and JS files come in as they are, framework included; nothing is added.')) . '
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script>
            // One modal for three starts: a blank design, an import and a
            // paste all ask the framework first. The card that opened it says
            // which (data-sd-start); ?start=import|paste opens it on load.
            (function () {
                var modal = document.getElementById("sdNewDesignModal");
                if (!modal) return;
                function setMode(start) {
                    var mode = (start === "import" || start === "paste") ? start : "";
                    var t = modal.querySelector(".sd-fw-title");
                    if (t) t.textContent = mode === "paste" ? t.dataset.textPaste : (mode ? t.dataset.textImport : t.dataset.textNew);
                    modal.querySelectorAll(".sd-fw-intro, .sd-fw-text").forEach(function (el) {
                        el.textContent = mode ? el.dataset.textImport : el.dataset.textNew;
                    });
                    modal.querySelectorAll(".sd-fw-choice").forEach(function (a) {
                        a.href = a.dataset.href + (mode ? "&start=" + mode : "");
                    });
                    var ic = modal.querySelector(".sd-fw-icon");
                    if (ic) ic.className = "bi me-2 sd-fw-icon " + (mode === "import" ? "bi-file-earmark-zip" : (mode === "paste" ? "bi-clipboard-plus" : "bi-file-earmark-plus"));
                }
                modal.addEventListener("show.bs.modal", function (e) {
                    var src = e.relatedTarget;
                    setMode(src && src.dataset ? (src.dataset.sdStart || "") : (modal.dataset.sdStart || ""));
                });
                modal.addEventListener("hidden.bs.modal", function () { delete modal.dataset.sdStart; });
                var q = new URLSearchParams(window.location.search).get("start");
                if (q === "import" || q === "paste") {
                    var open = function () {
                        if (!window.bootstrap || !window.bootstrap.Modal) return;
                        modal.dataset.sdStart = q;
                        window.bootstrap.Modal.getOrCreateInstance(modal).show();
                    };
                    if (document.readyState === "complete") open(); else window.addEventListener("load", open);
                }
            })();
        </script>';
}

/**
 * "Choose a Template": the templates this installation ships. Choosing one
 * opens the editor with the template's pages as unsaved tabs; nothing is
 * created until the design is published.
 */
function pg_designer_template_modal($templates, $from_pages)
{
    $extra = $from_pages ? '&from=pages' : '';
    $look0 = function_exists('pg_design_default_look') ? pg_design_default_look() : '';
    $cards = '';
    foreach ((array)$templates as $tpl) {
        $s = pg_design_template_summary($tpl);
        // Template and framework version, readable at a glance: on the
        // picture when there is one, under the description otherwise.
        $fw_version = isset($s['framework_version']) ? (string)$s['framework_version'] : '';
        $ver_badge  = '<span class="sd-tpl-ver">' . h('v' . $s['version'] . ' · ' . $s['framework_label'] . ($fw_version !== '' ? ' ' . $fw_version : '')) . '</span>';
        $thumb = ($s['framework'] === 'bootstrap5' && function_exists('pg_design_thumb_svg'))
            ? '<div class="sd-tpl-thumb">' . pg_design_thumb_svg($look0, '', 'sd-tpl-thumb-svg', lang(array('string' => 'Preview of {var:1}', 'vars' => $s['name'])), $s['thumb']) . $ver_badge . '</div>'
            : '';
        // The theme the template is made for: offered on the card, applied
        // to the picker above only when the operator asks. The picker is
        // what every template opens in: "No theme" and "Bootstrap" there
        // mean plain Bootstrap, whatever a template suggests.
        $theme_attrs = '';
        $theme_names = '';
        if ($s['look'] !== null || $s['palette'] !== null) {
            $theme_attrs = ' data-look="' . h((string)$s['look']) . '" data-palette="' . h((string)$s['palette']) . '"'
                         . ' data-has-look="' . ($s['look'] !== null ? '1' : '0') . '" data-has-palette="' . ($s['palette'] !== null ? '1' : '0') . '"';
            $parts = array();
            if ($s['look'] !== null && function_exists('pg_design_look_label')) $parts[] = pg_design_look_label($s['look']);
            if ($s['palette'] !== null && $s['palette'] !== '') {
                $pals = pg_design_palettes();
                if (isset($pals[$s['palette']])) $parts[] = $pals[$s['palette']]['name'];
            }
            $theme_names = implode(' · ', array_filter($parts, 'strlen'));
        }
        $made_for = ($theme_names !== '') ? lang(array('string' => 'Made for: {var:1}', 'vars' => $theme_names)) : '';
        // "Use this theme" stays inside .sd-tpl-card: the picker script finds
        // the card's data-look / data-palette from the button.
        $theme_apply = ($made_for !== '')
            ? '<button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-nowrap sd-tpl-theme-apply" title="' . h(h($made_for)) . '">'
              . '<i class="bi bi-palette me-1" aria-hidden="true"></i>' . lang('Use this theme') . '</button>'
            : '';
        // The details live in the info button's title, which the panel turns
        // into an HTML popover (pgBindTitlePopovers()): every piece escaped,
        // then the whole markup escaped once more for the attribute.
        $info = array();
        if ($s['description'] !== '') $info[] = h($s['description']);
        if ($s['pages']) {
            $info[] = '<strong>' . h(lang(array('string' => 'Pages ({var:1})', 'vars' => count($s['pages'])))) . '</strong><br>' . h(implode(', ', $s['pages']));
        }
        if ($s['highlights']) {
            $info[] = '<strong>' . h(lang('Highlights')) . '</strong><br>• ' . implode('<br>• ', array_map('h', $s['highlights']));
        }
        if ($made_for !== '') $info[] = h($made_for);
        $info[] = '<strong>' . h(lang('Framework')) . '</strong> ' . h($s['framework_label'])
                . '<br><strong>' . h(lang('Template version')) . '</strong> ' . h($s['version']);
        $info_btn = '<button type="button" class="btn btn-link btn-sm p-0 text-body-secondary" aria-label="' . h(lang('Template details')) . '"'
                  . ' title="' . h(implode('<br>', $info)) . '"><i class="bi bi-info-circle" aria-hidden="true"></i></button>';
        // A template made from a design (builtin false) can be deleted; the
        // ones the installation ships cannot.
        $custom = isset($s['builtin']) && !$s['builtin'];
        $custom_badge = $custom ? '<span class="badge text-bg-secondary fw-normal text-nowrap">' . lang('Made from a design') . '</span>' : '';
        $delete_btn = $custom
            ? '<button type="button" class="btn btn-sm btn-ghost sd-tpl-delete" data-template="' . h($s['id']) . '" data-name="' . h($s['name']) . '" title="' . h(lang('Delete template')) . '" aria-label="' . h(lang('Delete template')) . '"><i class="bi bi-trash" aria-hidden="true"></i></button>'
            : '';
        $use_href = 'add_system_style.php?start=template&template=' . rawurlencode($s['id']) . $extra;
        $cards .=
            '<div class="col-12 col-md-6 col-xl-4 col-xxl-3">
                <div class="card h-100 overflow-hidden sd-tpl-card"' . $theme_attrs . '>
                    ' . $thumb . '
                    <div class="card-body d-flex flex-column gap-1 p-3">
                        <div class="d-flex flex-wrap align-items-center column-gap-2 row-gap-1">
                            <div class="d-flex align-items-center gap-2 flex-grow-1 sd-tpl-title">
                                <i class="bi ' . h($s['icon']) . ' fs-5 text-primary" aria-hidden="true"></i>
                                <span class="h6 mb-0 text-truncate">' . h($s['name']) . '</span>
                                ' . $custom_badge . '
                                ' . $info_btn . '
                            </div>
                            <div class="d-flex align-items-center gap-2 ms-auto">
                                ' . $theme_apply . '
                                ' . $delete_btn . '
                                <a href="' . h($use_href) . '" class="btn btn-sm btn-primary rounded-pill px-3 text-nowrap sd-tpl-use" data-href="' . h($use_href) . '" data-loading-content="' . lang('Loading') . '"><i class="bi bi-magic me-1" aria-hidden="true"></i>' . lang('Use This Template') . '</a>
                            </div>
                        </div>
                        <span class="small text-muted text-truncate">' . h($s['description']) . '</span>
                        ' . ($thumb === '' ? '<div class="text-body-secondary">' . $ver_badge . '</div>' : '') . '
                    </div>
                </div>
            </div>';
    }
    return '
        <div class="modal fade" id="sdTemplateModal" tabindex="-1" aria-labelledby="sdTemplateModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable sd-tpl-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title fs-5" id="sdTemplateModalLabel"><i class="bi bi-grid-1x2 me-2" aria-hidden="true"></i>' . lang('Choose a Template') . '</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' . lang('Close') . '"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted mb-3">' . lang('The template\'s pages open in the editor, ready to change. Nothing is added to the site until you publish.') . '</p>
                        ' . pg_designer_template_theme_picker() . '
                        <div class="row g-3" id="sd-tpl-cards">' . $cards . '</div>
                        <p class="small text-muted mb-0' . ($cards !== '' ? ' d-none' : '') . '" id="sd-tpl-empty">' . lang('There are no templates.') . '</p>
                    </div>
                </div>
            </div>
        </div>
        <script>
            // Delete a template made from a design. The shipped ones carry no
            // button. api.php reads a JSON body.
            document.addEventListener("click", function (e) {
                var btn = e.target.closest ? e.target.closest(".sd-tpl-delete") : null;
                if (!btn) return;
                e.preventDefault();
                var tpl = btn.dataset.template || "", name = btn.dataset.name || "";
                var msg = ' . json_encode(lang('Delete the template "{var:1}"? Designs made from it are not affected.')) . '.replace("{var:1}", name);
                var fail = function (text) {
                    btn.disabled = false;
                    if (typeof pgToast === "function") pgToast({ message: text, variant: "danger" });
                    else alert(text);
                };
                var run = function () {
                    btn.disabled = true;
                    fetch("api.php", { method: "POST", credentials: "same-origin", headers: { "Content-Type": "application/json" },
                           body: JSON.stringify({ action: "designer", sub_action: "template_delete", template: tpl, token: (typeof software_token !== "undefined" ? software_token : "") }) })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (!res || res.status !== "success") { fail((res && res.message) || ' . json_encode(lang('Sorry, we could not accept your request.')) . '); return; }
                        var col = btn.closest(".sd-tpl-card");
                        col = col ? col.parentNode : null;
                        if (col && col.parentNode) col.parentNode.removeChild(col);
                        var cards = document.getElementById("sd-tpl-cards");
                        var empty = document.getElementById("sd-tpl-empty");
                        if (cards && empty && !cards.querySelector(".sd-tpl-card")) empty.classList.remove("d-none");
                        if (typeof pgToast === "function" && res.message) pgToast({ message: res.message, variant: "success" });
                    })
                    .catch(function () { fail(' . json_encode(lang('Network error.')) . '); });
                };
                if (typeof window.pgConfirm === "function") {
                    window.pgConfirm({ title: ' . json_encode(lang('Delete template')) . ', message: msg, confirmText: ' . json_encode(lang('Yes, delete')) . ', cancelText: ' . json_encode(lang('No')) . ', variant: "danger" })
                        .then(function (ok) { if (ok) run(); });
                } else if (window.confirm(msg)) { run(); }
            });
        </script>';
}

/**
 * The look and the colour palette a template opens in, above the template
 * cards. The cards' pictures follow the choice, and "Use This Template"
 * carries it to the editor (?look=&palette=). Both can be changed later in
 * the editor, under Settings > Design > Theme.
 */
function pg_designer_template_theme_picker()
{
    if (!function_exists('pg_design_looks')) return '';
    $look0 = pg_design_default_look();
    $looks = array('' => array('name' => lang('Plain Bootstrap'), 'description' => lang('Plain Bootstrap 5.3, as it comes.'),
                               'preview' => array('radius' => 6, 'btn_radius' => 6, 'shadow' => 'none', 'border' => 1)));
    $looks = array_merge($looks, pg_design_looks());
    $look_btns = '';
    $n = 0;
    foreach ($looks as $key => $l) {
        $id = 'sd-tpl-look-' . (++$n);
        $look_btns .= '<input type="radio" class="btn-check" name="sd_tpl_look" id="' . $id . '" value="' . h($key) . '"' . ($key === $look0 ? ' checked' : '')
                    . ' data-preview="' . h(json_encode($l['preview'])) . '" autocomplete="off">'
                    . '<label class="btn btn-outline-secondary rounded-pill" for="' . $id . '" title="' . h($l['description']) . '">' . h($l['name']) . '</label>';
    }
    $bs = pg_design_bootstrap_colors();
    $palettes = array('' => array('name' => lang('Bootstrap'), 'primary' => $bs['primary'], 'secondary' => $bs['secondary']));
    $palettes = array_merge($palettes, pg_design_palettes());
    foreach (pg_design_theme_files('palette') as $f) {
        if (!isset($f['meta']['primary'], $f['meta']['secondary'])) continue;
        $palettes['file-' . $f['id']] = array('name' => $f['name'], 'primary' => $f['meta']['primary'], 'secondary' => $f['meta']['secondary']);
    }
    $pal_btns = '';
    $n = 0;
    foreach ($palettes as $key => $p) {
        $id = 'sd-tpl-pal-' . (++$n);
        $pal_btns .= '<input type="radio" class="btn-check" name="sd_tpl_palette" id="' . $id . '" value="' . h($key) . '"' . ($key === '' ? ' checked' : '')
                   . ' data-primary="' . h($p['primary']) . '" data-secondary="' . h($p['secondary']) . '" data-name="' . h($p['name']) . '" autocomplete="off">'
                   . '<label class="sd-tpl-pal" for="' . $id . '" title="' . h($p['name']) . '"><i style="background:' . h($p['primary']) . '"></i><i style="background:' . h($p['secondary']) . '"></i>'
                   . '<span class="visually-hidden">' . h($p['name']) . '</span></label>';
    }
    return '
        <div class="border rounded-3 p-3 mb-3 bg-body-tertiary" id="sd-tpl-theme">
            <div class="mb-3">
                <div class="small fw-semibold mb-1">' . lang('Look') . ' <span class="fw-normal text-muted">— ' . lang('corners, shadows, type and buttons') . '</span></div>
                <div class="d-flex flex-wrap gap-1 sd-tpl-look" role="radiogroup" aria-label="' . h(lang('Look')) . '">' . $look_btns . '</div>
            </div>
            <div>
                <div class="small fw-semibold mb-1">' . lang('Colour palette') . ' <span class="fw-normal text-muted">— <span id="sd-tpl-pal-name">' . lang('Bootstrap') . '</span></span></div>
                <div class="d-flex flex-wrap gap-2" role="radiogroup" aria-label="' . h(lang('Colour palette')) . '">' . $pal_btns . '</div>
            </div>
            <div class="form-text mt-2">' . lang('You can change both later in the editor, under Settings, Design, Theme.') . '</div>
        </div>
        <script>
            (function () {
                var box = document.getElementById("sd-tpl-theme");
                if (!box) return;
                var modal = box.closest(".modal");
                // Twin of pg_design_thumb_vars().
                function vars(pv, p, s) {
                    pv = pv || {};
                    var hex = /^#?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(p || "");
                    var rgb = hex ? [parseInt(hex[1], 16), parseInt(hex[2], 16), parseInt(hex[3], 16)].join(", ") : "13, 110, 253";
                    var sh = { none: "none", soft: "drop-shadow(0 2px 3px rgba(16, 24, 40, 0.16))", crisp: "drop-shadow(0 1px 1px rgba(0, 0, 0, 0.18))",
                               glow: "drop-shadow(0 3px 4px rgba(" + rgb + ", 0.35))", hard: "drop-shadow(2px 2px 0 #111827)", deep: "drop-shadow(0 4px 6px rgba(0, 0, 0, 0.28))" };
                    var r = Math.min(+pv.radius || 0, 14) * 0.5, br = (+pv.btn_radius >= 50) ? 5.5 : Math.min(+pv.btn_radius || 0, 14) * 0.5;
                    return { "--tp": p, "--ts": s, "--tr": r + "px", "--tbr": br + "px", "--tsh": sh[pv.shadow] || "none",
                             "--tbw": ((+pv.border || 0) * 0.75) + "px", "--tbc": pv.shadow === "hard" ? "#111827" : "#e5e7eb" };
                }
                function radio(name, value) {
                    var found = null;
                    box.querySelectorAll("input[name=" + name + "]").forEach(function (r) { if (r.value === value) found = r; });
                    return found;
                }
                // Every template opens in what the picker shows.
                function sync() {
                    var l = box.querySelector("input[name=sd_tpl_look]:checked");
                    var p = box.querySelector("input[name=sd_tpl_palette]:checked");
                    var pv = {};
                    try { pv = JSON.parse(l ? l.getAttribute("data-preview") : "{}") || {}; } catch (e) {}
                    var v = vars(pv, p ? p.getAttribute("data-primary") : "#0d6efd", p ? p.getAttribute("data-secondary") : "#6c757d");
                    modal.querySelectorAll(".sd-tpl-thumb-svg").forEach(function (svg) {
                        Object.keys(v).forEach(function (k) { svg.style.setProperty(k, v[k]); });
                    });
                    var nm = document.getElementById("sd-tpl-pal-name");
                    if (nm && p) nm.textContent = p.getAttribute("data-name");
                    var q = "&look=" + encodeURIComponent(l ? l.value : "") + "&palette=" + encodeURIComponent(p ? p.value : "");
                    modal.querySelectorAll(".sd-tpl-use").forEach(function (a) { a.href = a.getAttribute("data-href") + q; });
                }
                box.addEventListener("change", sync);
                // "Use this theme" on a card puts its theme in the picker.
                modal.addEventListener("click", function (e) {
                    var btn = e.target.closest ? e.target.closest(".sd-tpl-theme-apply") : null;
                    if (!btn) return;
                    var card = btn.closest(".sd-tpl-card");
                    if (!card) return;
                    var r;
                    if (card.getAttribute("data-has-look") === "1" && (r = radio("sd_tpl_look", card.getAttribute("data-look") || ""))) r.checked = true;
                    if (card.getAttribute("data-has-palette") === "1" && (r = radio("sd_tpl_palette", card.getAttribute("data-palette") || ""))) r.checked = true;
                    sync();
                });
                // The cards come after this script in the page.
                if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", sync); else sync();
                if (modal) modal.addEventListener("show.bs.modal", sync);
            })();
        </script>';
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

    // The drafts folder is not a place to publish a page to: a draft is a
    // state, set with the switch below the list. Only its own row goes; a
    // folder somebody made inside it keeps its row, or a page living there
    // would open with no folder selected and be moved by the next save.
    $folder_options = select_folder(0);
    $draft_folder_id = pg_page_draft_folder_id(false);

    if ($draft_folder_id > 0) {
        $folder_options = preg_replace('~<option value="' . $draft_folder_id . '"[^>]*>[^<]*</option>~', '', $folder_options, 1);
    }

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
                                    ' . pg_designer_settings_framework_rows($ctx) . '
                                    ' . (pg_designer_is_full($user) ? '<div class="mb-3" id="sd-template-from-design">
                                        <label class="form-label small">' . lang('Template') . '</label>
                                        <div><button type="button" class="btn btn-sm btn-outline-secondary" id="sd-make-template"><i class="bi bi-grid-1x2 me-1" aria-hidden="true"></i>' . lang('Turn this design into a template') . '</button></div>
                                        <small class="form-text">' . lang('Every saved page of the design, with its shared components, widgets, folders and assets, becomes a template under Choose a Template. Save first: the template is made from what is on the server.') . '</small>
                                    </div>' : '') . '
                                </div>

                                <!-- Look and colour palette: filled by the editor
                                     (sdDesign.themes), written to style_look /
                                     style_palette. -->
                                <div class="sd-settings-group" id="sd-theme-settings">
                                    <div class="sd-settings-group-title">' . lang('Theme') . '</div>
                                    <div id="sd-theme-picker"></div>
                                </div>

                                <div class="sd-settings-group">
                                    <div class="sd-settings-group-title">' . lang('Advanced') . '</div>
                                    <div class="mb-3">
                                        <label class="form-label small">' . lang('Theme file') . '</label>
                                        ' . $liveform->output_field(array('type'=>'select', 'name'=>'theme_id', 'options'=>get_theme_options(), 'class'=>'form-select form-select-sm')) . '
                                        <small class="form-text">' . lang('A stylesheet from the file manager, loaded after everything else so it wins over the look and the palette. Shown in the Styles list and the Themes panel.') . '</small>
                                    </div>
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
                                            ' . $folder_options . '
                                        </select>
                                    </div>
                                    ' . ((pg_designer_is_full($user) && pg_page_draft_ready()) ? '<div class="form-check form-switch mb-0">
                                        <input class="form-check-input sd-page-field" type="checkbox" id="pg_page_draft" value="1" data-page-key="page_draft">
                                        <label class="form-check-label small" for="pg_page_draft">' . lang('Draft - not on the site') . '</label>
                                        <small class="form-text d-block">' . lang('A draft is kept in the private Drafts folder: visitors cannot open it, administrators can. Once published it goes back to the folder above. Applied when the design is published.') . '</small>
                                    </div>' : '') . '
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
 * The framework the design is built on and the template it started from,
 * read-only: both are fixed when the design is created.
 */
function pg_designer_settings_framework_rows($ctx)
{
    $style = isset($ctx['style']) && is_array($ctx['style']) ? $ctx['style'] : array();
    $fw    = pg_design_framework(isset($style['framework']) ? $style['framework'] : '');
    $out =
        '<div class="mb-3">
            <label class="form-label small" for="sd-style-framework">' . lang('Framework') . '</label>
            <input type="text" class="form-control form-control-sm" id="sd-style-framework" value="' . h($fw['label']) . '" readonly>
            <small class="form-text">' . lang('Chosen when the design was created. The pages are built on it, so it does not change.') . '</small>
        </div>';
    $tpl_id = isset($style['template']) ? (string)$style['template'] : '';
    if ($tpl_id !== '') {
        $tpl  = pg_design_template($tpl_id);
        $name = $tpl ? (string)$tpl['name'] : $tpl_id;
        $ver  = isset($style['template_version']) ? (string)$style['template_version'] : '';
        $out .=
            '<div class="mb-3">
                <label class="form-label small" for="sd-style-template">' . lang('Started from') . '</label>
                <input type="text" class="form-control form-control-sm" id="sd-style-template" value="' . h($name . ($ver !== '' ? ' ' . $ver : '')) . '" readonly>
            </div>';
    }
    return $out;
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
                            <div class="mt-1">' . lang('Pages open as unsaved tabs — review them, then publish.') . '</div>
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

/**
 * "Turn this design into a template" (Settings > Design): the name and the
 * description the template is listed with. The fields carry no name: the
 * modal sits inside the design form, and the editor posts them to api.php
 * (designer/template_from_design) itself.
 */
function pg_designer_make_template_modal()
{
    return '
        <div class="modal fade" id="sdMakeTemplateModal" tabindex="-1" aria-labelledby="sdMakeTemplateModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content" style="background:#1e2127; color:#c9d1d9; border:1px solid #30363d;">
                    <div class="modal-header" style="border-color:#30363d;">
                        <h5 class="modal-title fs-6" id="sdMakeTemplateModalLabel"><span class="bi bi-grid-1x2 me-2" aria-hidden="true"></span>' . lang('Turn this design into a template') . '</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="' . h(lang('Close')) . '"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label small" for="sd-make-template-name">' . lang('Name') . '</label>
                            <input type="text" class="form-control form-control-sm" id="sd-make-template-name" maxlength="255" autocomplete="off">
                        </div>
                        <div class="mb-0">
                            <label class="form-label small" for="sd-make-template-description">' . lang('Description') . '</label>
                            <textarea class="form-control form-control-sm" id="sd-make-template-description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer" style="border-color:#30363d;">
                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">' . lang('Cancel') . '</button>
                        <button type="button" class="btn btn-sm btn-primary" id="sd-make-template-run"><span class="bi bi-grid-1x2 me-1" aria-hidden="true"></span>' . lang('Create template') . '</button>
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

/**
 * Page settings that name a page by its tab key - the comment e-mail page.
 *
 * pg_designer_save_page() writes such a value as "none" because the page it
 * names may be saved later in the same loop; once every page has its id the
 * value is resolved and written here. Pages this session could not write
 * (held by someone else) are left alone.
 */
function pg_designer_resolve_page_tab_refs($pages, $id_map, $tab_ids, $locked_pages = array())
{
    $ids_by_key = array();
    foreach ((array)$id_map as $m) {
        if ($m['key'] !== '') $ids_by_key[$m['key']] = (int)$m['page_id'];
    }
    foreach ((array)$pages as $p) {
        if (!is_array($p)) continue;
        $raw = isset($p['pg_comments_email_page']) ? $p['pg_comments_email_page'] : '';
        if (!is_string($raw) || strncmp($raw, 'tab:', 4) !== 0) continue;
        if (isset($locked_pages[(int)(isset($p['page_id']) ? $p['page_id'] : 0)])) continue;
        $key     = isset($p['key']) ? (string)$p['key'] : '';
        $page_id = isset($ids_by_key[$key]) ? $ids_by_key[$key] : (int)(isset($p['page_id']) ? $p['page_id'] : 0);
        if ($page_id <= 0) continue;
        $mail = (int)pg_designer_resolve_tab_values($raw, $tab_ids);
        db("UPDATE page
            SET comments_submitter_email_page_id = '$mail', comments_watcher_email_page_id = '$mail'
            WHERE page_id = '$page_id' LIMIT 1");
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
        // Written for a new design only (save_system_style()). The template
        // has to be one this installation ships.
        'framework'                         => isset($_POST['style_framework']) ? (string)$_POST['style_framework'] : '',
        'template'                          => (isset($_POST['style_template']) && pg_design_template((string)$_POST['style_template'])) ? (string)$_POST['style_template'] : '',
        'template_version'                  => isset($_POST['style_template_version']) ? (string)$_POST['style_template_version'] : '',
        // Hidden inputs of the Theme settings; validated by save_system_style().
        'look'                              => isset($_POST['style_look']) ? (string)$_POST['style_look'] : '',
        'palette'                           => isset($_POST['style_palette']) ? (string)$_POST['style_palette'] : '',
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
            // `draft`: the state the page ended up in, which is not always
            // the one asked for (the home page stays on the site).
            $id_map[] = array('key' => $key, 'page_id' => (int)$r['page_id'], 'draft' => !empty($r['draft']) ? 1 : 0);
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
    pg_designer_resolve_page_tab_refs($pages, $id_map, $tab_ids, $locked_pages);
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
