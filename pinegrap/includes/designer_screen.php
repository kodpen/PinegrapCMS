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
            // A classic page's type: anything but 'standard' opens the Page
            // Type pane of the settings modal.
            'page_type'             => (string)(isset($p['page_type']) ? $p['page_type'] : 'standard'),
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
            'pg_comments_attachments'      => (int)(isset($p['pg_comments_attachments']) ? $p['pg_comments_attachments'] : 0),
            'pg_comments_select_name'      => (int)(isset($p['pg_comments_select_name']) ? $p['pg_comments_select_name'] : 0),
            'pg_comments_message'          => (string)(isset($p['pg_comments_message']) ? $p['pg_comments_message'] : ''),
            'pg_comments_closed_message'   => (string)(isset($p['pg_comments_closed_message']) ? $p['pg_comments_closed_message'] : ''),
            'pg_comments_watchers_managed' => (int)(isset($p['pg_comments_watchers_managed']) ? $p['pg_comments_watchers_managed'] : 0),
            'page_meta_tags'        => (string)(isset($p['page_meta_tags']) ? $p['page_meta_tags'] : ''),
            'page_custom_jsonld'    => (string)(isset($p['page_custom_jsonld']) ? $p['page_custom_jsonld'] : ''),
            // The page's own canonical address and robots directives ('' and 0
            // for none; the snippet length as typed, '' when not set).
            'page_canonical_url'    => (string)(isset($p['page_canonical_url']) ? $p['page_canonical_url'] : ''),
            'page_noarchive'        => (int)(isset($p['page_noarchive']) ? $p['page_noarchive'] : 0),
            'page_max_snippet'      => (string)(isset($p['page_max_snippet']) ? $p['page_max_snippet'] : ''),
            // When the page goes on the site and comes off it, as the
            // datetime-local boxes of Page Settings take them ('' for none).
            'page_publish_at'       => (string)(isset($p['page_publish_at']) ? $p['page_publish_at'] : ''),
            'page_unpublish_at'     => (string)(isset($p['page_unpublish_at']) ? $p['page_unpublish_at'] : ''),
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
            'pg_comments_attachments'      => 0,
            'pg_comments_select_name'      => 0,
            'pg_comments_message'          => '',
            'pg_comments_closed_message'   => '',
            'pg_comments_watchers_managed' => 0,
            'page_meta_tags'        => '',
            'page_custom_jsonld'    => '',
            'page_canonical_url'    => '',
            'page_noarchive'        => 0,
            'page_max_snippet'      => '',
            'page_publish_at'       => '',
            'page_unpublish_at'     => '',
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

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/prototype_links.php');
    $sd_proto_share = pg_page_draft_ready() && pg_proto_links_ready();

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/layouts.php');

    // The order and groups of the tabs, for the pages listed above.
    $tab_page_ids = array();
    foreach ($js_pages as $jp) {
        if ($jp['page_id'] > 0) $tab_page_ids[] = $jp['page_id'];
    }
    $layout_pages = ((int)$user['role'] <= 1) ? pg_layout_custom_page_ids($tab_page_ids) : array();
    $active_page_id = 0;
    foreach ($js_pages as $jp) { if ($jp['key'] === $active_key) { $active_page_id = (int)$jp['page_id']; break; } }
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
        // The prototype viewer (prototype.php): the design's saved drafts as
        // a clickable prototype, for the roles it admits (0-2); '' for the
        // rest, and the tab menu then offers no item for it.
        'prototypeUrl'  => ((int)$user['role'] < 3) ? PATH . SOFTWARE_DIRECTORY . '/prototype.php' : '',
        // Share links of the prototype (includes/fn/prototype_links.php):
        // made, listed and revoked by administrators and designers, once
        // the schema has them. False hides the "Share prototype" item.
        'prototypeShare' => ((int)$user['role'] <= 1) && $sd_proto_share,
        // A dynamic region's PHP code opens as a code-view tab for the role
        // the classic Dynamic Region screen admits (administrators); the
        // endpoint (dynamic_region_code) checks the same.
        'dynamicRegionCode' => ((int)$user['role'] === 0),
        // A classic page's custom layout opens as a code-view tab from its
        // system region for the role the classic layout editor admits
        // (designers and administrators); the endpoint (layout_code) checks
        // the same. Read only on a hosted site, which ignores the file.
        'layoutCode'    => ((int)$user['role'] <= 1),
        'layoutPages'   => $layout_pages,
        'layoutReadOnly' => pg_hosted(),
        // ?layout=1 (the toolbar's Ctrl+G button, pg_layout_tab_param()):
        // the page whose layout tab opens once the editor is up, or 0.
        'openLayout'    => pg_layout_open_page_id(isset($_GET['layout']) ? $_GET['layout'] : '', $active_page_id, $layout_pages),
        // A classic page's type fields: read and saved by edit_page.php in
        // JSON (?properties=1), with the rights of that screen.
        'pageTypeUrl'   => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_page.php',
        // A designer region becomes a designer-only shared component for the
        // roles edit_designer_region.php admits (administrators and
        // designers); shared_component's from_designer_region checks the same.
        'designerRegionConvert' => ((int)$user['role'] <= 1),
        'exitUrl'       => pg_safe_back_url($ctx['send_to'], ($ctx['from_pages'] ? 'view_pages.php' : 'view_system_styles.php')),
        'access'        => $access,
        // The schema can open shared components to content editors
        // (includes/designer_access.php); the designer's switch shows then.
        'sharedContentReady' => pg_shared_component_content_ready(),
        // Who is sitting here. The page lock names its holder by user id;
        // when that id is this one, the holder is another tab of the same
        // person and the editor may offer to take the page over.
        'userId'        => (int)$user['id'],
        // The site's own address (Settings → e-mail): a custom form new to
        // its page starts with it as the notification address.
        'siteEmail'     => defined('EMAIL_ADDRESS') ? trim((string)EMAIL_ADDRESS) : '',
        // Site settings the member widgets follow. The front end drops a
        // control whose feature is off; the canvas fades the same control
        // so the designer sees what the visitor will get.
        'siteFlags'     => array(
            'remember_me'          => (bool)(defined('REMEMBER_ME') && REMEMBER_ME),
            'forgot_password_link' => (bool)(defined('FORGOT_PASSWORD_LINK') && FORGOT_PASSWORD_LINK),
            'password_hint'        => (bool)(defined('PASSWORD_HINT') && PASSWORD_HINT),
            'captcha'              => (bool)(defined('CAPTCHA') && CAPTCHA),
            'strong_password'      => (bool)(defined('STRONG_PASSWORD') && STRONG_PASSWORD),
            'google'               => (bool)(function_exists('pg_signin_enabled') && pg_signin_enabled('google')),
            'signin'               => (bool)(function_exists('pg_signin_any_enabled') && pg_signin_any_enabled()),
            // The providers whose buttons the sign-in block prints now, for
            // the hint on that block.
            'signin_providers'     => function_exists('pg_signin_button_state') ? array_values(pg_signin_button_state()['shown']) : array(),
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
            // Scheduled publishing (2026.11.0, includes/fn/page_schedule.php).
            // The times are the site's wall clock; the offset lets the editor
            // tell a time still ahead from one that has passed.
            'schedule'  => array(
                'ready'  => pg_page_draft_ready() && function_exists('pg_page_schedule_ready') && pg_page_schedule_ready(),
                'offset' => (int)date('Z'),
            ),
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
        // The Bootstrap Icons sheet the site loads (pg_design_icons_url()),
        // for the canvas, the assets panel row and the preview.
        'iconsUrl'      => pg_design_icons_url(),
        // The site as the settings modal's address and search result
        // previews show it: its own address (the setting, not the request's
        // host, like a canonical URL), and the title and description a page
        // gets when its own are empty (get_page_content.php).
        'site'          => array(
            'url'         => URL_SCHEME . HOSTNAME_SETTING . PATH,
            'title'       => (string)TITLE,
            'description' => (string)META_DESCRIPTION,
        ),
        // Add mode from a template: the editor asks for its pages
        // (designer/template_prepare) behind the "preparing" curtain.
        'template'      => !empty($ctx['template']) ? $ctx['template'] : null,
        // What to do once open, from the design chooser's link: a new page,
        // a page from another design or a copy of one (pg_designer_start_action()).
        'startAction'   => !empty($ctx['start_action']) ? $ctx['start_action'] : null,
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
            // The form's double opt-in switch exists once the 11.0.27 column
            // is there; until then pg_cf_write_form_settings() drops it.
            'doubleOptIn' => function_exists('pg_fs_ready') && pg_fs_ready(),
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
                <!-- Comments settings — driven by the options panel when a comments_block region or a comments widget is selected; per page -->
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
                <input type="hidden" name="pg_comments_attachments" value="0">
                <input type="hidden" name="pg_comments_select_name" value="0">
                <input type="hidden" name="pg_comments_message" value="">
                <input type="hidden" name="pg_comments_closed_message" value="">
                <input type="hidden" name="pg_comments_watchers_managed" value="0">
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
                            <div class="pg-menu-wrap sd-tabs-add">
                                <button type="button" class="sd-tabs-add-btn" data-bs-toggle="menu" aria-expanded="false" title="' . lang('Add Page') . '">
                                    <span class="bi bi-plus-lg"></span><span class="sd-tabs-add-txt">' . lang('Add Page') . '</span>
                                </button>
                                <ul class="menu dropdown-menu-dark">
                                    <li><button type="button" class="menu-item" id="sd-tab-new-page"><span class="bi bi-file-earmark-plus me-3"></span>' . lang('New Page') . '</button></li>
                                    <li><button type="button" class="menu-item" id="sd-tab-pick-page"><span class="bi bi-files me-3"></span>' . lang('Select Page') . '</button></li>
                                    <li><hr class="menu-divider"></li>
                                    <li><button type="button" class="menu-item" id="sd-tab-import-html"><span class="bi bi-file-earmark-code me-3"></span>' . lang('Import HTML / ZIP') . '</button></li>
                                    <li><button type="button" class="menu-item" id="sd-tab-paste-html"><span class="bi bi-clipboard-plus me-3"></span>' . lang('Paste HTML content') . '</button></li>
                                </ul>
                            </div>
                            <button type="button" class="sd-icon-btn" data-bs-toggle="dialog" data-bs-target="#styleSettingsModal" title="' . lang('Settings') . '"><span class="bi bi-gear"></span></button>
                            ' . ($mode === 'edit' ? $ctx['delete_button'] : '') . '
                        </div>
                        <div class="sd-tabs-scroll" id="sd-tabs-list" role="tablist"></div>
                        <!-- Every page in one list, for when the strip cannot
                             show them all. Shown only then (_pgTabsAllSync()). -->
                        <div class="pg-menu-wrap sd-tabs-all" id="sd-tabs-all" hidden>
                            <button type="button" class="sd-icon-btn sd-tabs-all-btn" data-bs-toggle="menu" data-bs-placement="bottom-end" data-bs-auto-close="outside" aria-expanded="false" title="' . h(lang('All pages')) . '" aria-label="' . h(lang('All pages')) . '"><span class="bi bi-list-ul" aria-hidden="true"></span><span class="sd-tabs-all-count" id="sd-tabs-all-count"></span></button>
                            <div class="menu dropdown-menu-dark pg-menu-end sd-tabs-all-menu">
                                <div class="sd-tabs-all-find"><input type="search" class="form-control form-control-sm" id="sd-tabs-all-filter" placeholder="' . h(lang('Find a page…')) . '" autocomplete="off"></div>
                                <div class="sd-tabs-all-list" id="sd-tabs-all-list"></div>
                            </div>
                        </div>
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
                            <div class="pg-menu-wrap sd-publish-more">
                                <button type="button" id="sd-publish-more" class="sd-icon-btn sd-icon-btn-primary sd-publish-more-btn" data-bs-toggle="menu" data-bs-placement="bottom-end" aria-expanded="false" title="' . h(lang('Publishing options')) . '" aria-label="' . h(lang('Publishing options')) . '"><span class="bi bi-chevron-down" aria-hidden="true"></span></button>
                                <ul class="menu dropdown-menu-dark pg-menu-end">
                                    <li><button type="button" class="menu-item" data-sd-status="page-draft"><span class="bi bi-eye-slash me-3" aria-hidden="true"></span>' . lang('Save this page as a draft') . '</button></li>
                                    <li><button type="button" class="menu-item" data-sd-status="page-publish"><span class="bi bi-rocket-takeoff me-3" aria-hidden="true"></span>' . lang('Publish this page') . '</button></li>
                                    <li><hr class="menu-divider"></li>
                                    <li><button type="button" class="menu-item" data-sd-status="all-draft"><span class="bi bi-cloud-slash me-3" aria-hidden="true"></span>' . lang('Take every page of the design off the site') . '</button></li>
                                    <li><button type="button" class="menu-item" data-sd-status="all-publish"><span class="bi bi-cloud-check me-3" aria-hidden="true"></span>' . lang('Publish every page of the design') . '</button></li>
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
                        <span class="fg-secondary small" id="sd-last-modified-label">' . $last_modified . '</span>
                    </div>
                </div>

                ' . pg_designer_settings_modal($ctx) . '
                ' . pg_designer_pick_page_modal() . '
                ' . pg_designer_import_modal() . '
                ' . pg_designer_make_template_modal() . '
            </form>
        </main>
        ' . ($access === PG_DESIGNER_ACCESS_FULL ? pg_designer_target_modal() : '') . '
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
                'Code Editor'  => lang('Code Editor'),
                'Close'        => lang('Close'),
                'Cancel'       => lang('Cancel'),
                'Save'         => lang('Save'),
                'Code'         => lang('Code'),
                'Syntax error' => lang('Syntax error'),
                'Format code'  => lang('Format code'),
            )) . ';
        </script>
        <script src="assets/js/codemirror_modal.js?v=' . pg_designer_asset_stamp('assets/js/codemirror_modal.js') . '"></script>
        <!-- Smart completion: the predictor and the Bootstrap vocabulary are
             fetched the first time a class input or the code view asks. -->
        <script src="assets/js/pg_style_suggest.js?v=' . pg_designer_asset_stamp('assets/js/pg_style_suggest.js') . '"></script>
        <script>PgStyleSuggest.configure({ predictor: "assets/js/pg_style_predict.js?v=' . h(pg_designer_asset_stamp('assets/js/pg_style_predict.js')) . '", vocab: "assets/js/designer_vocab/bootstrap5.json?v=' . h(pg_designer_asset_stamp('assets/js/designer_vocab/bootstrap5.json')) . '", complete: "assets/js/pg_style_complete.js?v=' . h(pg_designer_asset_stamp('assets/js/pg_style_complete.js')) . '" });</script>
        <!-- The code editor facade is small; its engine (the CodeMirror 6
             bundle) is fetched the first time a code view opens, so the
             designer page does not carry it on every load. Its editors show
             a minimap beside the scroll bar. -->
        <script src="assets/js/pg_code_editor.js?v=' . pg_designer_asset_stamp('assets/js/pg_code_editor.js') . '"></script>
        <script>PgCodeEditor.configure({ minimap: true, bundle: "assets/lib/code_editor/cm6.bundle.js?v=' . h(pg_designer_asset_stamp('assets/lib/code_editor/cm6.bundle.js')) . '" });</script>
        <!-- Text contrast for the audit (window.PgContrast). -->
        <script src="assets/js/pg_contrast.js?v=' . pg_designer_asset_stamp('assets/js/pg_contrast.js') . '"></script>
        <!-- User Styles text helpers (window.PgUserStyles). -->
        <script src="assets/js/pg_user_styles.js?v=' . pg_designer_asset_stamp('assets/js/pg_user_styles.js') . '"></script>
        <!-- Panel input suggestions: the part of a field under the caret (window.PgStyleFields). -->
        <script src="assets/js/pg_style_fields.js?v=' . pg_designer_asset_stamp('assets/js/pg_style_fields.js') . '"></script>
        <!-- Per-element custom style rule in User Styles (window.PgCustomStyle). -->
        <script src="assets/js/pg_custom_style.js?v=' . pg_designer_asset_stamp('assets/js/pg_custom_style.js') . '"></script>
        <!-- The search keyword chips (pgChips). -->
        ' . pg_fields_script() . '
        <script src="assets/js/style_designer.js?v=' . pg_designer_asset_stamp('assets/js/style_designer.js') . '"></script>
        ' . pg_ai_write_assets() . '
        ' . (!empty($sd_ai['enabled']) ? '<script src="assets/js/designer_ai.js?v=' . pg_designer_asset_stamp('assets/js/designer_ai.js') . '"></script>' : '') . '
        <script>
            $(document).ready(function() {
                StyleDesigner.init({
                    regionData: sdRegionData,
                    design: sdDesign
                });

                // The counters and the indexing switches are bound once; each
                // later opening may show another page, whose values were
                // written without an event, so the same events bring the
                // counters and the switches up to date.
                var _sdSettingsBound = false;
                pgUI.dialog.on(document.getElementById("styleSettingsModal"), "shown", function () {
                    if (_sdSettingsBound) {
                        ["pg_page_title", "pg_page_meta_description"].forEach(function (id) {
                            var el = document.getElementById(id);
                            if (el) el.dispatchEvent(new Event("input", { bubbles: true }));
                        });
                        var noindex = document.getElementById("pg_page_noindex");
                        if (noindex) noindex.dispatchEvent(new Event("change", { bubbles: true }));
                        return;
                    }
                    _sdSettingsBound = true;
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
                (SELECT COUNT(*) FROM page WHERE page.page_style = style.style_id AND " . pg_page_visual_design_sql() . pg_designer_not_binned_sql() . ") AS page_count
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
        $rows[$i]['scheduled_count'] = 0;
        $rows[$i]['scheduled_next'] = 0;
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
    // The pages that go on the site or come off it at a set time, and the
    // next of those times (includes/fn/page_schedule.php).
    if ($rows && function_exists('pg_page_schedule_design_counts')) {
        $by_style = pg_page_schedule_design_counts(array_map(function ($r) { return (int)$r['style_id']; }, $rows));
        foreach ($rows as $i => $r) {
            $c = isset($by_style[(int)$r['style_id']]) ? $by_style[(int)$r['style_id']] : null;
            if ($c) { $rows[$i]['scheduled_count'] = $c['scheduled']; $rows[$i]['scheduled_next'] = $c['next']; }
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
 * The pages of each design, as the home screen lists them under the design:
 * the design's own outside the bin, the home page first, then in the order
 * they were made (the editor's own first tab). A page whose folder the
 * operator may not even know about is left out; one they may see but not
 * open is marked locked, as the editor's tab strip shows it
 * (pg_designer_page_access()).
 *
 * @param array $style_ids
 * @param array $user
 * @return array style_id => list of array(id, name, home, draft, locked, publish_at, unpublish_at)
 */
function pg_designer_list_pages($style_ids, $user)
{
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)$style_ids))));
    $out = array();
    foreach ($ids as $id) $out[$id] = array();
    if (!$ids) return $out;

    $rows = db_items(
        "SELECT page_id, page_name, page_style, page_home, page_folder
         FROM page
         WHERE page_style IN (" . implode(',', $ids) . ") AND " . pg_page_visual_design_sql() . pg_designer_not_binned_sql() . "
         ORDER BY (page_home = 'yes') DESC, page_id ASC");
    $full = pg_designer_is_full($user);
    foreach ((array)$rows as $r) {
        $access = $full ? 'edit' : pg_designer_page_access(array('page_id' => (int)$r['page_id'], 'page_folder' => (int)$r['page_folder']), $user);
        if ($access === 'hidden') continue;
        $out[(int)$r['page_style']][] = array(
            'id'     => (int)$r['page_id'],
            'name'   => (string)$r['page_name'],
            'home'   => ((string)$r['page_home'] === 'yes'),
            'draft'  => pg_page_is_draft($r['page_folder']),
            'locked' => ($access !== 'edit'),
            'publish_at'   => 0,
            'unpublish_at' => 0,
        );
    }
    // The pages' scheduled times, read for the pages listed only.
    if (function_exists('pg_page_schedule_times')) {
        $page_ids = array();
        foreach ($out as $list) foreach ($list as $p) $page_ids[] = $p['id'];
        $times = pg_page_schedule_times($page_ids);
        if ($times) {
            foreach ($out as $sid => $list) {
                foreach ($list as $k => $p) {
                    if (!isset($times[$p['id']])) continue;
                    $out[$sid][$k]['publish_at']   = $times[$p['id']]['publish_at'];
                    $out[$sid][$k]['unpublish_at'] = $times[$p['id']]['unpublish_at'];
                }
            }
        }
    }
    return $out;
}

/**
 * How many folders name each design as their default design, for the
 * computer or for phones: deleting the design clears that setting, and the
 * confirmation says so before it happens. A folder with it as both counts
 * once, as pg_designer_delete_design() counts it.
 *
 * @param array $style_ids
 * @return array style_id => count
 */
function pg_designer_list_folder_counts($style_ids)
{
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)$style_ids))));
    $out = array();
    foreach ($ids as $id) $out[$id] = 0;
    if (!$ids) return $out;

    $in = implode(',', $ids);
    foreach ((array)db_items("SELECT folder_style, mobile_style_id FROM folder WHERE folder_style IN ($in) OR mobile_style_id IN ($in)") as $r) {
        $seen = array();
        foreach (array((int)$r['folder_style'], (int)$r['mobile_style_id']) as $sid) {
            if (isset($out[$sid]) && !isset($seen[$sid])) {
                $out[$sid]++;
                $seen[$sid] = true;
            }
        }
    }
    return $out;
}

/**
 * Which design the home screen shows large: the only one, or the one the
 * home page is in. Most sites have one design; with several and none (or
 * more than one) holding the home page, they are all shown alike and this
 * is 0.
 *
 * @param array $designs pg_designer_list_designs() rows
 * @return int style_id
 */
function pg_designer_home_featured_id($designs)
{
    $designs = array_values((array)$designs);
    if (count($designs) === 1) return (int)$designs[0]['style_id'];
    $holders = array();
    foreach ($designs as $d) {
        if ((int)$d['home_count'] > 0) $holders[] = (int)$d['style_id'];
    }
    return (count($holders) === 1) ? $holders[0] : 0;
}

/**
 * Where a design stands on the site, under its name: on the site, a draft
 * (the home page stays on it), partly on it, or no pages yet, with one line
 * saying what that means. The counts ride along as data attributes for the
 * page script (assets/js/designer_home.js): they decide which of "Put on the
 * site" and "Take off the site" a menu offers, and the answer of
 * api.php designer/design_publish brings this block anew.
 *
 * @param array $d a pg_designer_list_designs() row
 * @return string
 */
function pg_designer_list_status_cell($d)
{
    $c = array('pages' => (int)$d['page_count'], 'drafts' => (int)$d['draft_count'], 'home' => (int)$d['home_count']);
    $s = pg_designer_design_state($c);

    if ($s['state'] === 'live') {
        $badge = '<span class="badge rounded-pill theme-success"><i class="bi bi-broadcast me-1" aria-hidden="true"></i>' . h(lang('Live on the site')) . '</span>';
        $note  = h(lang('All pages are on the site.'));
    } elseif ($s['state'] === 'draft') {
        $badge = '<span class="badge rounded-pill theme-secondary"><i class="bi bi-eye-slash me-1" aria-hidden="true"></i>' . h(lang('Draft')) . '</span>';
        $note  = ($c['home'] > 0) ? h(lang('The home page stays on the site.')) : h(lang('Visitors cannot open the pages; administrators can.'));
    } elseif ($s['state'] === 'mixed') {
        $badge = '<span class="badge rounded-pill theme-warning"><i class="bi bi-circle-half me-1" aria-hidden="true"></i>' . h(lang('Partly on the site')) . '</span>';
        $note  = h(lang(array('string' => '{var:1} of {var:2} pages are drafts', 'vars' => array($c['drafts'], $c['pages']))));
    } else {
        $badge = '<span class="badge rounded-pill theme-secondary">' . h(lang('Empty')) . '</span>';
        $note  = h(lang('No pages yet. Open it to add one.'));
    }

    // Beside the state: pages that change state at a set time, and when the
    // next change comes.
    $scheduled = isset($d['scheduled_count']) ? (int)$d['scheduled_count'] : 0;
    if ($scheduled > 0 && $s['state'] !== 'empty') {
        $badge .= ' <span class="badge rounded-pill theme-info sdh-scheduled"><i class="bi bi-clock me-1" aria-hidden="true"></i>' . h(lang('On a schedule')) . '</span>';
        $next  = isset($d['scheduled_next']) ? (int)$d['scheduled_next'] : 0;
        $note .= ' · ' . h(($next > 0)
            ? lang(array('string' => '{var:1} scheduled, next change {var:2}', 'vars' => array($scheduled, pg_page_schedule_label($next))))
            : lang(array('string' => '{var:1} scheduled', 'vars' => array($scheduled))));
    }

    return '<div class="sdh-status" data-state="' . h($s['state']) . '" data-pages="' . $c['pages'] . '" data-drafts="' . $c['drafts'] . '" data-home="' . $c['home'] . '" data-offable="' . (int)$s['offable'] . '" data-scheduled="' . $scheduled . '">'
         . $badge . '<span class="sdh-status-note">' . $note . '</span></div>';
}

/**
 * A design's picture: its template's drawing in the design's own look and
 * colours. A design started blank or imported has no picture of its own; it
 * gets the outline of a page with what it is built on in the middle.
 */
function pg_designer_list_thumb($d)
{
    $tpl = !empty($d['template']) ? pg_design_template($d['template']) : null;
    if ($tpl && !empty($d['framework_bootstrap']) && function_exists('pg_design_thumb_svg')) {
        return pg_design_thumb_svg($d['look'], $d['palette'], '', lang(array('string' => 'Preview of {var:1}', 'vars' => $d['style_name'])), isset($tpl['thumb']) ? (string)$tpl['thumb'] : '');
    }
    return '<svg class="sdh-blank" viewBox="0 0 320 200" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">'
         . '<rect x="18" y="14" width="44" height="8" rx="3" class="k"/>'
         . '<rect x="196" y="16" width="22" height="4" rx="2" class="k"/><rect x="226" y="16" width="22" height="4" rx="2" class="k"/><rect x="256" y="16" width="22" height="4" rx="2" class="k"/><rect x="286" y="16" width="16" height="4" rx="2" class="k"/>'
         . '<rect x="18" y="34" width="284" height="66" rx="6" class="g"/>'
         . '<rect x="36" y="50" width="120" height="9" rx="3" class="k"/><rect x="36" y="64" width="88" height="9" rx="3" class="k"/><rect x="36" y="80" width="52" height="10" rx="3" class="k"/>'
         . '<rect x="18" y="114" width="88" height="54" rx="6" class="g"/><rect x="116" y="114" width="88" height="54" rx="6" class="g"/><rect x="214" y="114" width="88" height="54" rx="6" class="g"/>'
         . '<rect x="18" y="182" width="284" height="6" rx="3" class="g"/>'
         . '</svg>'
         . '<span class="sdh-blank-mark" aria-hidden="true"><i class="bi ' . (!empty($d['framework_bootstrap']) ? 'bi-bootstrap' : 'bi-code-slash') . '"></i></span>';
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
        $sw = '<span class="sdh-swatch" aria-hidden="true"><i style="background:' . h($c['primary']) . '"></i><i style="background:' . h($c['secondary']) . '"></i></span>';
    }
    return $sw . h($text);
}

/**
 * The drawing at the top of a start card: what the start does, in the
 * panel's own colours (assets/css/designer_home.css). The template card
 * shows three of the templates themselves, fanned out.
 *
 * @param string $kind      blank | import | paste | template
 * @param array  $templates pg_design_templates(), for the template card
 * @return string
 */
function pg_designer_home_drawing($kind, $templates = array())
{
    if ($kind === 'template') {
        $look0 = function_exists('pg_design_default_look') ? pg_design_default_look() : '';
        $minis = array();
        foreach ((array)$templates as $tpl) {
            if (count($minis) >= 3) break;
            $s = pg_design_template_summary($tpl);
            if ($s['framework'] !== 'bootstrap5' || !function_exists('pg_design_thumb_svg')) continue;
            $minis[] = '<span class="sdh-tpl-mini">'
                     . pg_design_thumb_svg(($s['look'] !== null) ? $s['look'] : $look0, (string)$s['palette'], '', '', $s['thumb'])
                     . '</span>';
        }
        // The first template in the middle, in front.
        if (count($minis) === 3) $minis = array($minis[1], $minis[0], $minis[2]);
        if ($minis) return '<span class="sdh-ill sdh-ill-tpl" aria-hidden="true">' . implode('', $minis) . '</span>';
        $kind = 'template-none';
    }

    // A browser window: the frame, the bar with its three dots, an address.
    $window = function ($x, $w) {
        return '<rect x="' . $x . '" y="10" width="' . $w . '" height="76" rx="8" class="p s"/>'
             . '<path d="M' . $x . ' 24 h' . $w . '" class="s"/>'
             . '<circle cx="' . ($x + 10) . '" cy="17" r="2.2" class="l"/><circle cx="' . ($x + 17) . '" cy="17" r="2.2" class="l"/><circle cx="' . ($x + 24) . '" cy="17" r="2.2" class="l"/>'
             . '<rect x="' . ($x + 33) . '" y="13.5" width="' . min(60, $w - 45) . '" height="7" rx="3.5" class="g"/>';
    };
    // From the source on the left into the page on the right.
    $arrow = '<path d="M78 48 h20" class="sa" stroke-dasharray="3 4"/><path d="M96 43 l6 5 l-6 5" class="sa"/>';

    switch ($kind) {
        case 'blank':
            // An empty canvas, and a block on its way in from the palette.
            $art = $window(66, 158)
                 . '<rect x="78" y="32" width="134" height="44" rx="6" class="dash"/>'
                 . '<circle cx="166" cy="54" r="9" class="as"/><path d="M166 50 v8 M162 54 h8" class="sa"/>'
                 . '<rect x="16" y="14" width="38" height="68" rx="7" class="p s"/>'
                 . '<rect x="23" y="22" width="24" height="10" rx="2.5" class="g"/><rect x="23" y="36" width="24" height="10" rx="2.5" class="g"/>'
                 . '<rect x="23" y="50" width="24" height="10" rx="2.5" class="g"/><rect x="23" y="64" width="24" height="10" rx="2.5" class="g"/>'
                 . '<g transform="rotate(-5 67 50)"><rect x="40" y="40" width="54" height="20" rx="4" class="a"/>'
                 . '<rect x="47" y="46" width="24" height="3" rx="1.5" fill="#fff" fill-opacity=".9"/><rect x="47" y="52" width="36" height="3" rx="1.5" fill="#fff" fill-opacity=".6"/></g>'
                 . '<path d="M88 54 v14 l3.6 -3.4 l2.6 5.8 l2.6 -1.2 l-2.6 -5.7 l4.9 -.3 z" class="cursor"/>';
            break;
        case 'import':
            // A zipped project; its pages arrive as tabs.
            $teeth = '';
            for ($i = 0; $i < 6; $i++) {
                $teeth .= '<rect x="' . (($i % 2) ? 43 : 37) . '" y="' . (16 + $i * 6) . '" width="6" height="3.5" rx="1" class="k2"/>';
            }
            $art = '<path d="M22 10 h31 l13 13 v59 a4 4 0 0 1 -4 4 h-40 a4 4 0 0 1 -4 -4 v-68 a4 4 0 0 1 4 -4 z" class="p s"/>'
                 . '<path d="M53 10 v9 a4 4 0 0 0 4 4 h9" class="s" fill="none"/>'
                 . $teeth
                 . '<rect x="35" y="53" width="12" height="15" rx="3" class="a"/><circle cx="41" cy="63" r="2" class="p"/>'
                 . $arrow
                 . '<rect x="110" y="10" width="114" height="76" rx="8" class="p s"/>'
                 . '<rect x="118" y="15" width="28" height="10" rx="3" class="as"/><rect x="121" y="22.5" width="22" height="2" rx="1" class="a"/>'
                 . '<rect x="150" y="15" width="28" height="10" rx="3" class="g"/><rect x="182" y="15" width="28" height="10" rx="3" class="g"/>'
                 . '<path d="M110 29 h114" class="s"/>'
                 . '<rect x="120" y="37" width="52" height="5" rx="2" class="k"/>'
                 . '<rect x="120" y="46" width="90" height="3" rx="1.5" class="l"/><rect x="120" y="52" width="74" height="3" rx="1.5" class="l"/>'
                 . '<rect x="120" y="61" width="44" height="17" rx="3" class="g"/><rect x="168" y="61" width="44" height="17" rx="3" class="g"/>';
            break;
        case 'paste':
            // Markup on the clipboard; it lands on the page as a section.
            $art = '<rect x="18" y="14" width="54" height="72" rx="7" class="p s"/>'
                 . '<rect x="32" y="9" width="26" height="10" rx="4" class="k2"/>'
                 . '<path d="M31 31 l-5 5 l5 5" class="sa"/><path d="M59 31 l5 5 l-5 5" class="sa"/><path d="M48 30 l-6 12" class="sa"/>'
                 . '<rect x="27" y="52" width="36" height="3.5" rx="1.75" class="l"/><rect x="33" y="59" width="30" height="3.5" rx="1.75" class="l"/>'
                 . '<rect x="33" y="66" width="22" height="3.5" rx="1.75" class="l"/><rect x="27" y="73" width="30" height="3.5" rx="1.75" class="l"/>'
                 . $arrow
                 . $window(110, 114)
                 . '<rect x="120" y="30" width="94" height="6" rx="2" class="g"/>'
                 . '<rect x="120" y="41" width="94" height="27" rx="4" class="as"/><rect x="120" y="41" width="94" height="27" rx="4" class="dash"/>'
                 . '<rect x="128" y="48" width="42" height="5" rx="2" class="a"/><rect x="128" y="57" width="70" height="3.5" rx="1.75" class="l"/>'
                 . '<rect x="120" y="73" width="94" height="6" rx="2" class="g"/>';
            break;
        default:
            // No template to show: three empty frames.
            $art = '<rect x="24" y="20" width="60" height="56" rx="6" class="p s"/><rect x="90" y="14" width="60" height="62" rx="6" class="p s"/><rect x="156" y="20" width="60" height="56" rx="6" class="p s"/>'
                 . '<rect x="98" y="24" width="44" height="18" rx="3" class="g"/><rect x="98" y="48" width="30" height="4" rx="2" class="l"/><rect x="98" y="56" width="38" height="4" rx="2" class="l"/>';
    }

    return '<span class="sdh-ill" aria-hidden="true"><svg viewBox="0 0 240 96" xmlns="http://www.w3.org/2000/svg" focusable="false">' . $art . '</svg></span>';
}

/**
 * One of the four ways to start a design. A start opens a choice first (the
 * framework, a template): nothing loads until the operator picks.
 *
 * $opts: modal (the dialog it opens), start (import | paste, read by the
 * framework dialog), disabled (no templates to offer), id.
 */
function pg_designer_home_start_card($drawing, $icon, $title, $text, $opts = array())
{
    $disabled = !empty($opts['disabled']);
    $modal    = isset($opts['modal']) ? (string)$opts['modal'] : '';
    $start    = isset($opts['start']) ? (string)$opts['start'] : '';
    $body = $drawing
          . '<span class="sdh-start-body">'
          .     '<span class="sdh-start-title"><i class="bi ' . h($icon) . '" aria-hidden="true"></i>' . h($title)
          .         ($disabled ? '<span class="badge theme-secondary">' . h(lang('Soon')) . '</span>' : '')
          .     '</span>'
          .     '<span class="sdh-start-text">' . h($text) . '</span>'
          . '</span>';
    $card = $disabled
        ? '<div class="sdh-start" aria-disabled="true">' . $body . '</div>'
        : '<a href="#" role="button" class="sdh-start" data-bs-toggle="dialog" data-bs-target="' . h($modal) . '"'
          . ($start !== '' ? ' data-sd-start="' . h($start) . '"' : '')
          . (!empty($opts['id']) ? ' id="' . h($opts['id']) . '"' : '') . '>' . $body . '</a>';
    return '<div class="col-6 xl:col-3">' . $card . '</div>';
}

/**
 * A design's pages as chips that open the page in the editor. The first
 * $limit are shown; the rest fold behind a "+n more" chip. The house is the
 * home page, a dashed outline a draft, a padlock a page this operator may
 * see but not open (assets/js/designer_home.js repaints a chip whose state
 * changes, the same way).
 */
function pg_designer_home_page_chips($d, $pages, $limit)
{
    $id  = (int)$d['style_id'];
    $out = '';
    foreach (array_values($pages) as $i => $p) {
        $view  = PATH . encode_url_path($p['name']);
        // Only the home page, a locked page and a page with a scheduled
        // time carry an icon; a draft is told by its dashed outline (and in
        // words to a screen reader). The time is the chip's title.
        $when  = !empty($p['publish_at']) ? (int)$p['publish_at'] : (!empty($p['unpublish_at']) ? (int)$p['unpublish_at'] : 0);
        $sched = ($when > 0 && !$p['home']);
        $sched_text = !$sched ? ''
            : (!empty($p['publish_at'])
                ? lang(array('string' => 'Goes on the site {var:1}', 'vars' => array(pg_page_schedule_label($p['publish_at']))))
                : lang(array('string' => 'Comes off the site {var:1}', 'vars' => array(pg_page_schedule_label($p['unpublish_at'])))));
        $state = $p['locked'] ? array('bi-lock', lang('Locked'))
               : ($p['home'] ? array('bi-house-door', lang('Home page'))
               : ($sched ? array('bi-clock', ($p['draft'] ? lang('Draft') . ', ' : '') . $sched_text)
               : ($p['draft'] ? array('', lang('Draft')) : array('', ''))));
        $class = 'sdh-page' . ($p['home'] ? ' is-home' : '') . ($p['draft'] ? ' is-draft' : '') . ($p['locked'] ? ' is-locked' : '') . ($sched ? ' is-scheduled' : '') . ($i >= $limit ? ' is-extra' : '');
        $attrs = ' class="' . $class . '" data-page-id="' . (int)$p['id'] . '" data-name="' . h($p['name']) . '" data-view-url="' . h($view) . '"'
               . ' data-home="' . ($p['home'] ? '1' : '0') . '" data-draft="' . ($p['draft'] ? '1' : '0') . '" data-locked="' . ($p['locked'] ? '1' : '0') . '"'
               . ' data-scheduled="' . h($sched_text) . '"' . ($sched ? ' title="' . h($sched_text) . '"' : '');
        $inner = '<i class="bi ' . $state[0] . ($state[0] === '' ? ' d-none' : '') . '" aria-hidden="true"></i><span class="sdh-page-name">' . h($p['name']) . '</span>'
               . '<span class="visually-hidden sdh-page-state">' . ($state[1] !== '' ? ' (' . h($state[1]) . ')' : '') . '</span>';
        $out .= $p['locked']
            ? '<span' . $attrs . ' tabindex="0">' . $inner . '</span>'
            : '<a href="' . h('edit_system_style.php?id=' . $id . '&page=' . (int)$p['id']) . '"' . $attrs . '>' . $inner . '</a>';
    }
    $more = count($pages) - $limit;
    if ($more > 0) {
        $more_text = lang(array('string' => '+{var:1} more', 'vars' => $more));
        $out .= '<button type="button" class="sdh-page sdh-pages-toggle" aria-expanded="false" data-more="' . h($more_text) . '" data-less="' . h(lang('Show fewer')) . '">' . h($more_text) . '</button>';
    }
    return '<div class="sdh-pages' . ($more > 0 ? ' is-folded' : '') . '">' . $out . '</div>';
}

/**
 * One design on the home screen. The featured one (the site's design, see
 * pg_designer_home_featured_id()) is drawn large, with what it is built on,
 * its theme, its pages and its main buttons; the others are tiles. Both
 * open the same menu (right mouse button, the ⋯ button, a long press), which
 * reads the data attributes written here.
 *
 * $ctx: featured, can_change (a designer: the design's own actions),
 * can_draft (drafts exist on this site), folders (folders that use it as
 * their default design).
 */
function pg_designer_home_design_card($d, $pages, $ctx)
{
    $id       = (int)$d['style_id'];
    $featured = !empty($ctx['featured']);
    $count    = (int)$d['page_count'];
    $home     = ((int)$d['home_count'] > 0);

    // The page the editor and "View on site" open: the home page when the
    // design has it, otherwise the first page this operator may open.
    $open = null;
    foreach ($pages as $p) {
        if (!$p['locked']) { $open = $p; break; }
    }
    $edit_url = 'edit_system_style.php?id=' . $id . ($open ? '&page=' . (int)$open['id'] : '');
    $view_url = $open ? PATH . encode_url_path($open['name']) : '';
    $add_url  = 'edit_system_style.php?id=' . $id . '&new_page=1';
    // A design with no page to open has no address to show.
    $address  = $open ? HOSTNAME . PATH . ($open['home'] ? '' : $open['name']) : '';

    $name = h($d['style_name']);
    $attrs = ' data-style-id="' . $id . '" data-name="' . $name . '" data-pages="' . $count . '" data-home="' . ($home ? '1' : '0') . '"'
           . ' data-folders="' . (int)$ctx['folders'] . '" data-edit-url="' . h($edit_url) . '" data-add-url="' . h($add_url) . '" data-view-url="' . h($view_url) . '"';
    $more = '<button type="button" class="btn btn-sm btn-ghost sdh-more no-popover" data-bs-toggle="tooltip" title="' . h(lang('More actions')) . '" aria-label="' . h(lang('More actions')) . '" aria-haspopup="menu" aria-expanded="false"><i class="bi bi-three-dots" aria-hidden="true"></i></button>';
    // The picture opens the editor too; the name link is the one announced.
    $picture = '<a class="sdh-browser" href="' . h($edit_url) . '" tabindex="-1" aria-hidden="true">'
             .     '<span class="sdh-browser-bar"><i></i><i></i><i></i><span class="sdh-browser-url">' . h($address) . '</span></span>'
             .     '<span class="sdh-browser-view">' . pg_designer_list_thumb($d) . '</span>'
             . '</a>';
    $title = '<a href="' . h($edit_url) . '"><span class="sdh-name-text">' . $name . '</span></a>';
    // Markup of its own (a <time> element), so it is not escaped again.
    $when  = get_relative_time(array('timestamp' => (int)$d['last_modified_timestamp']));

    if (!$featured) {
        return '<article class="sdh-card sdh-tile" tabindex="0" aria-label="' . $name . '"' . $attrs . '>'
             .     $picture
             .     '<div class="sdh-tile-body">'
             .         '<div class="sdh-title-row"><h3 class="sdh-name">' . $title . '</h3>' . $more . '</div>'
             .         pg_designer_list_status_cell($d)
             .         '<div class="sdh-tile-meta d-flex justify-content-between gap-3"><span>' . h(lang(array('string' => '{var:1} page{suffix:1}', 'vars' => $count, 'suffix' => ($count === 1 ? '' : 's')))) . '</span><span class="text-truncate">' . $when . '</span></div>'
             .     '</div>'
             . '</article>';
    }

    $who = ($d['last_modified_username'] !== '' && $d['last_modified_username'] !== null) ? (string)$d['last_modified_username'] : '[' . lang('Unknown') . ']';
    $tpl = !empty($d['template']) ? pg_design_template($d['template']) : null;
    $facts = array(array(lang('Framework'), h($d['framework_label'])));
    if ($tpl) $facts[] = array(lang('Started from'), h(isset($tpl['name']) ? (string)$tpl['name'] : (string)$d['template']));
    $theme = pg_designer_list_theme_label($d);
    $facts[] = array(lang('Theme'), ($theme !== '') ? $theme : '<span class="fg-secondary">—</span>');
    $facts[] = array(lang('Last modified'), lang(array('string' => '{var:1}, by {var:2}', 'vars' => array($when, h($who)))));
    $facts_html = '';
    foreach ($facts as $f) {
        $facts_html .= '<div><dt>' . h($f[0]) . '</dt><dd>' . $f[1] . '</dd></div>';
    }

    // Four buttons at most; everything else is in the menu. "Put on the
    // site" shows while there are drafts (designer_home.js hides it once
    // there are none); taking a live design off the site is left to the
    // menu, where it is not one stray click away.
    $buttons = '<a class="btn btn-sm btn-solid theme-primary rounded-pill px-5" href="' . h($edit_url) . '"><i class="bi bi-pencil-square me-1" aria-hidden="true"></i>' . h(lang('Edit')) . '</a>';
    if (!empty($ctx['can_change'])) {
        $buttons .= '<a class="btn btn-sm btn-ghost" href="' . h($add_url) . '"><i class="bi bi-file-earmark-plus me-1" aria-hidden="true"></i>' . h(lang('Add Page')) . '</a>';
    }
    if ($view_url !== '') {
        $buttons .= '<a class="btn btn-sm btn-ghost" href="' . h($view_url) . '" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>' . h(lang('View on site')) . '</a>';
    }
    if (!empty($ctx['can_draft'])) {
        $buttons .= '<button type="button" class="btn btn-sm btn-ghost' . ((int)$d['draft_count'] > 0 ? '' : ' d-none') . '" data-act="on"><i class="bi bi-cloud-check me-1" aria-hidden="true"></i>' . h(lang('Put on the site')) . '</button>';
    }

    $pages_html = $pages
        ? '<h4 class="sdh-pages-title">' . h(lang('Pages')) . '<span class="sdh-count">' . pg_format_number(count($pages), 0) . '</span></h4>' . pg_designer_home_page_chips($d, $pages, 12)
        : '';

    return '<article class="sdh-card sdh-site" tabindex="0" aria-label="' . $name . '"' . $attrs . '>'
         .     '<div class="row g-7">'
         .         '<div class="col-12 lg:col-5 2xl:col-4">' . $picture . '</div>'
         .         '<div class="col-12 lg:col-7 2xl:col-8">'
         .             ($home ? '<div class="sdh-flag"><i class="bi bi-house-door" aria-hidden="true"></i>' . h(lang('The home page is in this design')) . '</div>' : '')
         .             '<div class="sdh-title-row"><h3 class="sdh-name">' . $title . '</h3>' . $more . '</div>'
         .             pg_designer_list_status_cell($d)
         .             '<dl class="sdh-facts">' . $facts_html . '</dl>'
         .             $pages_html
         .             '<div class="sdh-actions">' . $buttons . '</div>'
         .         '</div>'
         .     '</div>'
         . '</article>';
}

/**
 * The texts assets/js/designer_home.js shows, translated here: the script
 * holds none of its own.
 */
function pg_designer_home_texts()
{
    return array(
        'edit'          => lang('Edit'),
        'newTab'        => lang('Open in new tab'),
        'addPage'       => lang('Add Page'),
        'view'          => lang('View on site'),
        'on'            => lang('Put on the site'),
        'off'           => lang('Take off the site'),
        'rename'        => lang('Rename'),
        'template'      => lang('Turn this design into a template'),
        'delete'        => lang('Delete'),
        'editPage'      => lang('Edit Page'),
        'copy'          => lang('Copy address'),
        'copied'        => lang('Copied.'),
        'copyFailed'    => lang('The address could not be copied.'),
        'homePage'      => lang('Home page'),
        'draft'         => lang('Draft'),
        'locked'        => lang('Locked'),
        'fail'          => lang('Sorry, we could not accept your request.'),
        'network'       => lang('Network error.'),
        'no'            => lang('No'),
        'deleteTitle'   => lang('Delete Design'),
        'deleteYes'     => lang('Yes, delete'),
        'delBinPages'   => lang('The design "{name}" and its {n} page(s) will be moved to the Recycle Bin.'),
        'delBin'        => lang('The design "{name}" will be moved to the Recycle Bin.'),
        'delPlain'      => lang('The design "{name}" will be deleted.'),
        'delPlainPages' => lang('Its {n} page(s) will be moved to the Recycle Bin.'),
        'delPlainNone'  => lang('It has no pages.'),
        'delHome'       => lang('The home page is one of them: until another page is marked as home, the site has none.'),
        'delFolders'    => lang('{var:1} folder(s) use it as their default design; that setting will be cleared.'),
        'offAsk'        => lang('{var} pages will be taken off the site and kept in the private Drafts folder. Visitors will not be able to open them; administrators will.'),
        'homeStays'     => lang('The home page stays on the site.'),
        'onAsk'         => lang('The {var} draft pages of the design will be put on the site: visitors will be able to open them.'),
        'offYes'        => lang('Yes, make it a draft'),
        'usedOn'        => lang('Used on: '),
        'compAsk'       => lang('{var} shared components and system widgets will be deleted for good:'),
        'compUsed'      => lang('{var} of them are still in use: their places on those pages are left empty.'),
        'undo'          => lang('This cannot be undone.'),
        'compTitle'     => lang('Delete shared components and system widgets'),
        'compYes'       => lang('Delete ({var})'),
        'compDone'      => lang('{var} deleted.'),
        'compKept'      => lang('{var} kept: a saved page uses them.'),
    );
}

/**
 * "Turn this design into a template" from the designs list: the name and the
 * description the template is listed with (api.php
 * designer/template_from_design, the editor's Settings > Design does the
 * same). The panel's own dialog, light or dark with the panel.
 */
function pg_designer_home_template_dialog()
{
    return '
        <dialog class="dialog pg-dialog-centered" id="sdhTemplateFromModal" aria-labelledby="sdhTemplateFromModalLabel">
                    <div class="dialog-header">
                        <h2 class="dialog-title fs-lg" id="sdhTemplateFromModalLabel"><i class="bi bi-grid-1x2 me-3" aria-hidden="true"></i>' . h(lang('Turn this design into a template')) . '</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="dialog" aria-label="' . h(lang('Close')) . '"></button>
                    </div>
                    <div class="dialog-body">
                        <p class="small fg-secondary">' . h(lang('The template is made from the saved pages and is listed under Choose a Template.')) . '</p>
                        <div class="mb-5">
                            <label class="form-label" for="sdh-tpl-name">' . h(lang('Name')) . '</label>
                            <input type="text" class="form-control" id="sdh-tpl-name" maxlength="255" autocomplete="off">
                        </div>
                        <div>
                            <label class="form-label" for="sdh-tpl-description">' . h(lang('Description')) . '</label>
                            <textarea class="form-control" id="sdh-tpl-description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="dialog-footer">
                        <button type="button" class="btn btn-solid theme-secondary" data-bs-dismiss="dialog">' . h(lang('Cancel')) . '</button>
                        <button type="button" class="btn btn-solid theme-primary" id="sdh-tpl-run"><i class="bi bi-grid-1x2 me-1" aria-hidden="true"></i>' . h(lang('Create template')) . '</button>
                    </div>
        </dialog>';
}

/**
 * The Visual Page Editor home (view_system_styles.php): the ways to start a
 * new design (blank on Bootstrap 5 or custom, from an HTML project, from
 * pasted markup, from a template), each with a drawing of what it does, and
 * the designs that exist. Most sites have one design, so it is not a table:
 * the site's design is shown large, with its pages one click away, and any
 * other design as a tile. A design or a page answers the right mouse button
 * with a menu (assets/js/designer_home.js). The list is the editor's own
 * designs only: a legacy style opened here would show a blank page and
 * nothing to explain why.
 */
function pg_designer_start_screen($ctx)
{
    $liveform   = $ctx['liveform'];
    $user       = $ctx['user'];
    $from_pages = !empty($ctx['from_pages']);
    $designs    = pg_designer_list_designs();

    // Starting a design is a design job (add_system_style.php refuses anyone
    // below designer). Showing the cards to a content-level operator would be
    // offering four doors that all end in an access error.
    require_once(dirname(__FILE__) . '/designer_access.php');
    $can_create = pg_designer_is_full($user);

    // The templates this installation ships (includes/design_templates/).
    $templates = $can_create ? pg_design_templates() : array();

    // A design is deleted from its menu or from the editor's toolbar, one at
    // a time: its pages go to the recycle bin and the folders using it are
    // cleared (pg_designer_delete_design()). Once the bin holds styles the
    // design goes there with them, and the confirmation says so.
    require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');
    $style_bin = pg_recycle_style_ready();
    $can_draft = $can_create && pg_page_draft_ready();
    $can_tpl   = $can_create && function_exists('pg_design_template_custom_ready') && pg_design_template_custom_ready();

    $ids     = array_map(function ($d) { return (int)$d['style_id']; }, $designs);
    $pages   = pg_designer_list_pages($ids, $user);
    $folders = $can_create ? pg_designer_list_folder_counts($ids) : array();

    $featured_id = pg_designer_home_featured_id($designs);
    $featured    = '';
    $tiles       = '';
    foreach ($designs as $d) {
        $sid  = (int)$d['style_id'];
        $card = pg_designer_home_design_card($d, isset($pages[$sid]) ? $pages[$sid] : array(), array(
            'featured'   => ($sid === $featured_id),
            'can_change' => $can_create,
            'can_draft'  => $can_draft,
            'folders'    => isset($folders[$sid]) ? $folders[$sid] : 0,
        ));
        if ($sid === $featured_id) {
            $featured = $card;
        } else {
            $tiles .= '<div class="col sdh-tile-col" data-name="' . h($d['style_name']) . '">' . $card . '</div>';
        }
    }

    // Button and panel come from one query: the button only shows when the
    // panel has rows to list.
    $comp = pg_designer_components_card($user);

    if ($can_create) {
        $start =
            '<section class="sdh-section" aria-labelledby="sdh-start-title">
                <div class="sdh-head">
                    <div class="sdh-head-text">
                        <h2 id="sdh-start-title">' . h(lang('Start a new design')) . '</h2>
                        <p>' . h($designs
                            ? lang('A new design starts with pages of its own; the designs you have stay as they are.')
                            : lang('From a blank page, from your own HTML or from a ready-made template.')) . '</p>
                    </div>
                </div>
                <div class="row g-5">
                    ' . pg_designer_home_start_card(pg_designer_home_drawing('blank'), 'bi-file-earmark-plus', lang('Create New'),
                            lang('An empty page on the canvas, on Bootstrap 5 or as a custom design. Add sections from the palette and build the design up.'),
                            array('modal' => '#sdNewDesignModal')) . '
                    ' . pg_designer_home_start_card(pg_designer_home_drawing('import'), 'bi-file-earmark-zip', lang('Import HTML / ZIP'),
                            lang('Bring in a finished HTML page or a whole project archive. Pages become tabs, files go to the file manager.'),
                            array('modal' => '#sdNewDesignModal', 'start' => 'import')) . '
                    ' . pg_designer_home_start_card(pg_designer_home_drawing('paste'), 'bi-clipboard-plus', lang('Paste HTML content'),
                            lang('Paste the markup straight in — from a code editor, from a page you have open. The design starts from what you paste.'),
                            array('modal' => '#sdNewDesignModal', 'start' => 'paste')) . '
                    ' . pg_designer_home_start_card(pg_designer_home_drawing('template', $templates), 'bi-grid-1x2', lang('Choose a Template'),
                            lang('Start from a ready-made design and change what you like.'),
                            $templates ? array('modal' => '#sdTemplateModal', 'id' => 'sdh-start-template') : array('disabled' => true)) . '
                </div>
            </section>';
    } else {
        $start =
            '<div class="alert theme-secondary py-3 small mb-7">
                <i class="bi bi-info-circle me-1" aria-hidden="true"></i>' . h(lang('Open a design to edit its text and the areas marked for you. New designs are created by designers.')) . '
            </div>';
    }

    if ($designs) {
        // A name to look for once there are more designs than fit a glance.
        $search = (count($designs) > 6)
            ? '<div class="input-group input-group-sm">
                    <span class="input-group-text rounded-start-pill"><i class="bi bi-search" aria-hidden="true"></i></span>
                    <input type="search" class="form-control rounded-end-pill" id="sdh-filter" placeholder="' . h(lang('Find a design')) . '" aria-label="' . h(lang('Find a design')) . '" autocomplete="off">
               </div>'
            : '';
        $list =
            '<section class="sdh-section" aria-labelledby="sdh-designs-title">
                <div class="sdh-head">
                    <div class="sdh-head-text">
                        <h2 id="sdh-designs-title">' . h(lang('Designs')) . (count($designs) > 1 ? '<span class="sdh-count">' . count($designs) . '</span>' : '') . '</h2>
                        <p><span class="sdh-hint-mouse">' . h(lang('Right-click a design or a page for more.')) . '</span><span class="sdh-hint-touch">' . h(lang('Press and hold a design or a page for more.')) . '</span></p>
                    </div>
                    <div class="sdh-head-tools">' . $search . $comp['button'] . '</div>
                </div>
                ' . $featured . '
                ' . ($tiles !== ''
                    ? ($featured !== '' ? '<h3 class="sdh-subhead">' . h(lang('Other designs')) . '</h3>' : '')
                      . '<div class="row row-cols-1 sm:row-cols-2 xl:row-cols-3 2xl:row-cols-4 g-5" id="sdh-tiles">' . $tiles . '</div>'
                      . '<p class="small fg-secondary mt-5 mb-0 d-none" id="sdh-filter-empty">' . h(lang('No design matches this name.')) . '</p>'
                    : '') . '
            </section>';
    } else {
        $list =
            '<section class="sdh-section" aria-labelledby="sdh-empty-title">
                <div class="sdh-empty">
                    <div class="d-flex flex-wrap align-items-start gap-5">
                        <div class="flex-grow-1">
                            <h3 id="sdh-empty-title">' . h(lang('No designs yet')) . '</h3>
                            <p class="mb-0 small fg-secondary">' . h(lang('A design is a set of pages that share stylesheets, scripts, fonts and a theme.')) . '</p>
                        </div>
                        ' . $comp['button'] . '
                    </div>
                    ' . ($can_create
                        ? '<ol class="sdh-steps">
                                <li>' . h(lang('Start it blank, from your own HTML or from a template.')) . '</li>
                                <li>' . h(lang('Build the pages on the canvas: drag sections in and type over the text.')) . '</li>
                                <li>' . h(lang('New pages start as drafts. Put them on the site when they are ready.')) . '</li>
                           </ol>'
                        : '<p class="small mt-5 mb-0">' . h(lang('There is no design to open yet.')) . '</p>') . '
                </div>
            </section>';
    }

    $config = array(
        'canChange'   => $can_create,
        'canDraft'    => $can_draft,
        'canTemplate' => $can_tpl,
        'styleBin'    => $style_bin,
        // A template made from a design is listed once the page is drawn
        // again: the gallery is printed with the page.
        'templateUrl' => 'view_system_styles.php?start=template' . ($from_pages ? '&from=pages' : ''),
        't'           => pg_designer_home_texts(),
    );

    print
        pg_page_shell(array(
            'title'               => lang('Visual Page Editor'),
            'extra classes'       => 'design',
            'icon'                => 'design',
            'heading'             => lang('Visual Page Editor'),
            'heading_description' => lang('A design is a set of pages that share stylesheets, scripts, fonts and a theme.'),
            'head'                => '<link rel="stylesheet" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/css/designer_home.css?v=' . pg_designer_asset_stamp('assets/css/designer_home.css') . '">',
        )) . '
        <main id="content" class="container-fluid sdh">
            ' . $liveform->output_errors() . '
            ' . $liveform->output_notices() . '
            ' . $start . '
            ' . $list . '
        </main>
        ' . $comp['panel'] . '
        ' . ($can_create ? pg_designer_new_design_modal($from_pages) . pg_designer_template_modal($templates, $from_pages) : '') . '
        ' . ($can_tpl ? pg_designer_home_template_dialog() : '') . '
        <div class="menu sdh-menu" id="sdh-menu" role="menu" tabindex="-1"></div>
        <script type="application/json" id="sdh-config">' . json_encode($config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>
        <script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/designer_home.js?v=' . pg_designer_asset_stamp('assets/js/designer_home.js') . '"></script>
        ';
    print output_footer();
}

/**
 * The shared components and system widgets on file, with where each one is
 * placed, in an offcanvas panel opened from above the design list. These
 * rows do not belong to one design: a
 * template's header and widgets outlive the design that opened them, and
 * the next template opened then names its own rows "[1]". Here they can be
 * deleted without opening a design. Editing them needs the canvas, so the
 * card only lists and deletes (shared_component/delete_many, the editor's
 * bulk delete).
 *
 * Returns array('button' => opener, 'panel' => offcanvas markup); both ''
 * below designer, before the table exists and when there are no rows.
 */
function pg_designer_components_card($user)
{
    $none = array('button' => '', 'panel' => '');
    require_once(dirname(__FILE__) . '/designer_access.php');
    if (!pg_designer_is_full($user)) return $none;
    if (!pg_schema_has('shared_components')) return $none;

    $comp_rows = db_items("SELECT id, name, category, updated_at, system_region_config FROM shared_components ORDER BY name");
    if (!is_array($comp_rows) || count($comp_rows) === 0) return $none;

    $comp_usage     = pg_shared_component_usage(null);
    $comp_types     = pg_sw_type_labels();
    $comp_templates = pg_design_templates();

    $comp_output_rows = '';
    foreach ($comp_rows as $r) {
        $sid = (int)$r['id'];
        $cfg_raw = trim((string)$r['system_region_config']);
        $is_widget = ($cfg_raw !== '');
        $cfg = $is_widget ? json_decode($cfg_raw, true) : null;
        if (!is_array($cfg)) $cfg = array();

        $type_cell = h($is_widget ? lang('System Widget') : lang('Shared Component'));
        $kind = pg_sw_widget_kind($cfg);
        if ($is_widget && $kind !== '') {
            $type_cell .= '<div class="small fg-secondary">' . h(isset($comp_types[$kind]) ? $comp_types[$kind] : $kind) . '</div>';
        }

        // A template's shared component carries "template:<template>/<key>"
        // in category; its widgets carry the same origin in their settings.
        // An import's rows carry "import:<project>".
        $category = (string)$r['category'];
        $origin   = '';
        if (strpos($category, 'template:') === 0) {
            $origin = substr($category, 9);
        } elseif (isset($cfg['template_origin']) && is_string($cfg['template_origin'])) {
            $origin = $cfg['template_origin'];
        }
        if ($origin !== '') {
            $tpl_id = strstr($origin, '/', true);
            if ($tpl_id === false) $tpl_id = $origin;
            $tpl_name = isset($comp_templates[$tpl_id]['name']) ? (string)$comp_templates[$tpl_id]['name'] : $tpl_id;
            $source_cell = h(lang('Template')) . '<div class="small fg-secondary">' . h($tpl_name) . '</div>';
        } elseif (strpos($category, 'import:') === 0) {
            $source_cell = h(lang('Import')) . '<div class="small fg-secondary">' . h(substr($category, 7)) . '</div>';
        } elseif (strpos($category, 'cregion:') === 0) {
            // Made from a classic designer region (pg_designer_region_component()).
            $source_cell = h(lang('Designer Region'));
        } else {
            $source_cell = '<span class="fg-secondary">—</span>';
        }

        // Where it is placed: page (design), the recycle bin marked. The
        // confirmation reads the same lines from data-usage.
        $usage_lines = array();
        $usage_cell  = '';
        foreach ((isset($comp_usage[$sid]) ? $comp_usage[$sid] : array()) as $u) {
            // A binned page of a deleted design has no design name left: the
            // page alone, never "page ()".
            $page = (string)$u['page_name'];
            if ($page === '') $page = (string)$u['style_name'];
            if ($page === '') $page = '#' . (int)$u['page_id'];
            $line = ((int)$u['page_id'] > 0 && (string)$u['style_name'] !== '') ? $page . ' (' . $u['style_name'] . ')' : $page;
            $usage_lines[] = $line;
            $usage_cell .= '<div>' . h($line)
                . (!empty($u['binned']) ? ' <span class="badge theme-secondary">' . h(lang('Recycle Bin')) . '</span>' : '')
                . '</div>';
        }
        if ($usage_cell === '') {
            $usage_cell = '<span class="badge theme-success">' . h(lang('Not used')) . '</span>';
        }

        $comp_output_rows .= '
                    <tr data-id="' . $sid . '" data-name="' . h($r['name']) . '" data-used="' . ($usage_lines ? '1' : '0') . '" data-usage="' . h(json_encode($usage_lines)) . '">
                        <td><input type="checkbox" class="check sd-comp-pick" value="' . $sid . '" aria-label="' . h(lang('Select')) . '"></td>
                        <td class="fw-semibold">' . h($r['name']) . '</td>
                        <td>' . $type_cell . '</td>
                        <td>' . $source_cell . '</td>
                        <td class="small">' . $usage_cell . '</td>
                        <td nowrap>' . get_relative_time(array('timestamp' => (int)$r['updated_at'])) . '</td>
                    </tr>';
    }

    $comp_button = '<button type="button" class="btn btn-sm btn-outline theme-secondary" id="sd-comp-open" data-bs-toggle="drawer" data-bs-target="#sd-comp-offcanvas" aria-controls="sd-comp-offcanvas"><i class="bi bi-boxes me-1" aria-hidden="true"></i>' . h(lang('Shared components and system widgets')) . ' <span class="badge rounded-pill theme-secondary ms-1" id="sd-comp-total">' . count($comp_rows) . '</span></button>';

    $comp_panel = '
        <dialog class="drawer drawer-end" id="sd-comp-offcanvas" aria-labelledby="sd-comp-offcanvas-title">
            <div class="drawer-header border-bottom">
                <h2 class="drawer-title text-uppercase h5 fg-primary fw-bold mb-0" id="sd-comp-offcanvas-title">' . h(lang('Shared components and system widgets')) . '</h2>
                <button type="button" class="btn-close" data-bs-dismiss="drawer" aria-label="' . h(lang('Close')) . '"></button>
            </div>
            <div class="d-flex justify-content-end px-5 py-3 border-bottom">
                <button type="button" class="btn btn-sm btn-outline theme-warning" id="sd-comp-delete" disabled><i class="bi bi-trash me-1" aria-hidden="true"></i>' . h(lang('Delete selected')) . ' (<span id="sd-comp-count">0</span>)</button>
            </div>
            <div class="drawer-body p-0">
                <p class="small fg-secondary px-5 pt-5 mb-5">' . h(lang('These do not belong to a single design: they stay when a design is deleted, and a template opened later gives its own a numbered name. Delete the ones you no longer need here; they are edited in the editor.')) . '</p>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th><input type="checkbox" class="check" id="sd-comp-all" aria-label="' . h(lang('Select all')) . '"></th>
                                <th>' . h(lang('Name')) . '</th>
                                <th>' . h(lang('Type')) . '</th>
                                <th>' . h(lang('Source')) . '</th>
                                <th>' . h(lang('Used on')) . '</th>
                                <th nowrap>' . h(lang('Last Modified')) . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $comp_output_rows . '
                        </tbody>
                    </table>
                </div>
            </div>
        </dialog>';

    return array('button' => $comp_button, 'panel' => $comp_panel);
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
            '<div class="col-12 md:col-6">
                <a href="add_system_style.php?framework=' . h($framework) . h($extra) . '" class="card h-100 text-decoration-none sd-start-card sd-fw-choice' . ($recommended ? ' border-primary' : '') . '" data-href="add_system_style.php?framework=' . h($framework) . h($extra) . '">
                    <div class="card-body d-flex flex-column align-items-start gap-3">
                        <div class="d-flex w-100 align-items-start">
                            <i class="bi ' . h($icon) . ' fs-3xl ' . ($recommended ? 'fg-primary' : 'fg-secondary') . '" aria-hidden="true"></i>
                            ' . ($recommended ? '<span class="badge theme-primary ms-auto">' . lang('Recommended') . '</span>' : '') . '
                        </div>
                        <span class="h5 mb-0 fg-body">' . h($title) . '</span>
                        <span class="small fg-secondary sd-fw-text" data-text-new="' . h($text) . '" data-text-import="' . h($import_text) . '">' . h($text) . '</span>
                    </div>
                </a>
            </div>';
    };
    return '
        <dialog class="dialog dialog-lg pg-dialog-centered" id="sdNewDesignModal" aria-labelledby="sdNewDesignModalLabel">
                    <div class="dialog-header">
                        <h2 class="dialog-title fs-lg" id="sdNewDesignModalLabel"><i class="bi bi-file-earmark-plus me-3 sd-fw-icon" aria-hidden="true"></i><span class="sd-fw-title" data-text-new="' . h(lang('Create New Design')) . '" data-text-import="' . h(lang('Import HTML / ZIP')) . '" data-text-paste="' . h(lang('Paste HTML content')) . '">' . lang('Create New Design') . '</span></h2>
                        <button type="button" class="btn-close" data-bs-dismiss="dialog" aria-label="' . lang('Close') . '"></button>
                    </div>
                    <div class="dialog-body">
                        <p class="small fg-secondary mb-5 sd-fw-intro" data-text-new="' . h(lang('Choose what the design is built on. The pages are written against it, so it cannot be changed later.')) . '" data-text-import="' . h(lang('What is the project built on? Bootstrap 5 projects use the editor\'s Bootstrap; a project on another framework (Bootstrap 6, Tailwind…) or none goes into a custom design, with its own files. This cannot be changed later.')) . '">' . lang('Choose what the design is built on. The pages are written against it, so it cannot be changed later.') . '</p>
                        <div class="row g-5">
                            ' . $option('bootstrap5', 'bi-bootstrap', lang('Build with Bootstrap 5'),
                                lang('Bootstrap 5.3 is loaded on every page. The grid, the Bootstrap components and the ready-made blocks are in the palette.'), true,
                                lang('For a project on Bootstrap 5. The editor\'s Bootstrap 5.3 is used; the project\'s own Bootstrap and jQuery files are left out.')) . '
                            ' . $option('custom', 'bi-code-slash', lang('Build a Custom Design'),
                                lang('No framework is loaded: bootstrap.css and bootstrap.js stay out. The palette offers plain HTML elements, text, forms and system widgets; the styles are yours.'), false,
                                lang('For any other project (Bootstrap 6, Tailwind, your own CSS). Its CSS and JS files come in as they are, framework included; nothing is added.')) . '
                        </div>
                    </div>
        </dialog>
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
                    if (ic) ic.className = "bi me-3 sd-fw-icon " + (mode === "import" ? "bi-file-earmark-zip" : (mode === "paste" ? "bi-clipboard-plus" : "bi-file-earmark-plus"));
                }
                pgUI.dialog.on(modal, "show", function (e) {
                    var src = e.relatedTarget;
                    setMode(src && src.dataset ? (src.dataset.sdStart || "") : (modal.dataset.sdStart || ""));
                });
                pgUI.dialog.on(modal, "hidden", function () { delete modal.dataset.sdStart; });
                var q = new URLSearchParams(window.location.search).get("start");
                if (q === "import" || q === "paste") {
                    var open = function () {
                        if (!window.pgUI) return;
                        modal.dataset.sdStart = q;
                        pgUI.dialog.show(modal);
                    };
                    if (document.readyState === "complete") open(); else window.addEventListener("load", open);
                }
            })();
        </script>';
}

/**
 * What the editor does once it is open, read from the link the design
 * chooser makes (pg_designer_target_modal()):
 *
 *   ?new_page=1&folder=F  a new page tab, active and named on the tab
 *   ?pick_page=P          page P of another design as a tab; it moves to
 *                         this design when the operator publishes (the
 *                         "Select Page" picker's way)
 *   ?copy_page=P          a copy of page P as a new draft tab, under a name
 *                         no page has yet
 *   ?folder=F             a new design: its first page goes to folder F
 *
 * Design-level only: a content-level operator adds no pages. A folder the
 * operator may not edit is dropped (the page goes to the root folder, as
 * the save does for no folder). Null when there is nothing to do.
 *
 * @param array $user
 * @param int   $style_id 0 for a new design
 * @return array|null kind, folder | page_id, from_id, from_name [, name]
 */
function pg_designer_start_action($user, $style_id)
{
    require_once(dirname(__FILE__) . '/designer_access.php');
    if (!pg_designer_is_full($user)) return null;
    $style_id = (int)$style_id;

    $folder = isset($_GET['folder']) ? (int)$_GET['folder'] : 0;
    if ($folder > 0 && (!db_value("SELECT COUNT(*) FROM folder WHERE folder_id = '$folder'") || !check_edit_access($folder))) {
        $folder = 0;
    }

    if ($style_id <= 0) {
        return $folder > 0 ? array('kind' => 'folder', 'folder' => $folder) : null;
    }
    if (!empty($_GET['new_page'])) {
        return array('kind' => 'new_page', 'folder' => $folder);
    }

    foreach (array('pick_page' => 'pick', 'copy_page' => 'copy') as $param => $kind) {
        $page_id = isset($_GET[$param]) ? (int)$_GET[$param] : 0;
        if ($page_id <= 0) continue;
        $row = db_item(
            "SELECT page.page_id, page.page_name, page.page_folder, page.page_style, style.style_name
             FROM page
             LEFT JOIN style ON style.style_id = page.page_style
             WHERE page.page_id = '$page_id' AND " . pg_page_visual_design_sql() . pg_designer_not_binned_sql() . "
             LIMIT 1");
        // A page already on this design has nowhere to move; one the
        // operator may not edit is not theirs to move or copy.
        if (!is_array($row) || !check_edit_access((int)$row['page_folder'])) return null;
        if ($kind === 'pick' && (int)$row['page_style'] === $style_id) return null;
        $out = array(
            'kind'      => $kind,
            'page_id'   => $page_id,
            'from_id'   => (int)$row['page_style'],
            'from_name' => (string)$row['style_name'],
        );
        if ($kind === 'copy') {
            $out['name'] = get_unique_name(array('name' => $row['page_name'] . '-' . lang('copy'), 'type' => 'page'));
        }
        return $out;
    }

    return null;
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
            ? '<button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-nowrap sd-tpl-theme-apply no-popover" data-bs-toggle="tooltip" title="' . h($made_for) . '">'
              . '<i class="bi bi-palette me-1" aria-hidden="true"></i>' . lang('Use this theme') . '</button>'
            : '';
        // The details live in the info button's popover (pgBindTitlePopovers()):
        // the name on top, then the whole description, the first pages, two
        // highlights and the theme it is made for, kept to a short card.
        // Every piece is escaped, then the markup once more for the attribute.
        $info = array();
        if ($s['description'] !== '') $info[] = '<div class="mb-3">' . h($s['description']) . '</div>';
        if ($s['pages']) {
            $info_pages = array_slice($s['pages'], 0, 6);
            $info_rest  = count($s['pages']) - count($info_pages);
            $info[] = '<div class="mb-3"><strong>' . h(lang(array('string' => 'Pages ({var:1})', 'vars' => count($s['pages'])))) . '</strong><br>'
                    . h(implode(', ', $info_pages)) . ($info_rest > 0 ? ' ' . h(lang(array('string' => '+{var:1} more', 'vars' => $info_rest))) : '') . '</div>';
        }
        if ($s['highlights']) {
            $info[] = '<div class="mb-3"><strong>' . h(lang('Highlights')) . '</strong><ul class="ps-5 mb-0">'
                    . implode('', array_map(function ($line) { return '<li>' . h($line) . '</li>'; }, array_slice($s['highlights'], 0, 2))) . '</ul></div>';
        }
        if ($made_for !== '') $info[] = '<div>' . h($made_for) . '</div>';
        $info_btn = '<button type="button" class="btn btn-link btn-sm p-0 fg-secondary" aria-label="' . h(lang('Template details')) . '"'
                  . ' title="' . h(h($s['name'])) . '" data-bs-content="' . h(implode('', $info)) . '" data-bs-custom-class="sd-tpl-info"><i class="bi bi-info-circle" aria-hidden="true"></i></button>';
        // A template made from a design (builtin false) can be deleted; the
        // ones the installation ships cannot.
        $custom = isset($s['builtin']) && !$s['builtin'];
        $custom_badge = $custom ? '<span class="badge theme-secondary fw-normal text-nowrap">' . lang('Made from a design') . '</span>' : '';
        $delete_btn = $custom
            ? '<button type="button" class="btn btn-sm btn-ghost sd-tpl-delete no-popover" data-template="' . h($s['id']) . '" data-name="' . h($s['name']) . '" data-bs-toggle="tooltip" title="' . h(lang('Delete template')) . '" aria-label="' . h(lang('Delete template')) . '"><i class="bi bi-trash" aria-hidden="true"></i></button>'
            : '';
        $use_href = 'add_system_style.php?start=template&template=' . rawurlencode($s['id']) . $extra;
        $cards .=
            '<div class="col-12 md:col-6 xl:col-4 2xl:col-3">
                <div class="card h-100 overflow-hidden sd-tpl-card"' . $theme_attrs . '>
                    ' . $thumb . '
                    <div class="card-body d-flex flex-column gap-1 p-5">
                        <div class="d-flex flex-wrap align-items-center column-gap-3 row-gap-1">
                            <div class="d-flex align-items-center gap-3 flex-grow-1 sd-tpl-title">
                                <i class="bi ' . h($s['icon']) . ' fs-lg fg-primary" aria-hidden="true"></i>
                                <span class="h6 mb-0 text-truncate">' . h($s['name']) . '</span>
                                ' . $custom_badge . '
                                ' . $info_btn . '
                            </div>
                            <div class="d-flex align-items-center gap-3 ms-auto">
                                ' . $theme_apply . '
                                ' . $delete_btn . '
                                <a href="' . h($use_href) . '" class="btn btn-sm btn-solid theme-primary rounded-pill px-5 text-nowrap sd-tpl-use" data-href="' . h($use_href) . '"><i class="bi bi-magic me-1" aria-hidden="true"></i>' . lang('Use This Template') . '</a>
                            </div>
                        </div>
                        <span class="small fg-secondary text-truncate">' . h($s['description']) . '</span>
                        ' . ($thumb === '' ? '<div class="fg-secondary">' . $ver_badge . '</div>' : '') . '
                    </div>
                </div>
            </div>';
    }
    return '
        <dialog class="dialog dialog-xl pg-dialog-centered dialog-scrollable sd-tpl-dialog" id="sdTemplateModal" aria-labelledby="sdTemplateModalLabel">
                    <div class="dialog-header">
                        <h2 class="dialog-title fs-lg" id="sdTemplateModalLabel"><i class="bi bi-grid-1x2 me-3" aria-hidden="true"></i>' . lang('Choose a Template') . '</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="dialog" aria-label="' . lang('Close') . '"></button>
                    </div>
                    <div class="dialog-body">
                        <p class="small fg-secondary mb-5">' . lang('The template\'s pages open in the editor, ready to change. Nothing is added to the site until you publish.') . '</p>
                        ' . pg_designer_template_theme_picker() . '
                        <div class="row g-5" id="sd-tpl-cards">' . $cards . '</div>
                        <p class="small fg-secondary mb-0' . ($cards !== '' ? ' d-none' : '') . '" id="sd-tpl-empty">' . lang('There are no templates.') . '</p>
                    </div>
        </dialog>
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
            // ?start=template, from the Choose a Template menu entry, opens the gallery on load.
            (function () {
                if (new URLSearchParams(window.location.search).get("start") !== "template") return;
                var open = function () {
                    var modal = document.getElementById("sdTemplateModal");
                    if (modal && window.pgUI) pgUI.dialog.show(modal);
                };
                if (document.readyState === "complete") open(); else window.addEventListener("load", open);
            })();
        </script>';
}

/**
 * The look and the colour palette a template opens in, above the template
 * cards. The cards' pictures follow the choice, and "Use This Template"
 * carries it to the editor (?look=&palette=). Both can be changed later in
 * the editor, under Settings > Theme.
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
                    . '<label class="btn btn-outline theme-secondary rounded-pill no-popover" for="' . $id . '" data-bs-toggle="tooltip" title="' . h($l['description']) . '">' . h($l['name']) . '</label>';
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
                   . '<label class="sd-tpl-pal no-popover" for="' . $id . '" data-bs-toggle="tooltip" title="' . h($p['name']) . '"><i style="background:' . h($p['primary']) . '"></i><i style="background:' . h($p['secondary']) . '"></i>'
                   . '<span class="visually-hidden">' . h($p['name']) . '</span></label>';
    }
    return '
        <div class="border rounded-5 p-5 mb-5 bg-1" id="sd-tpl-theme">
            <div class="mb-5">
                <div class="small fw-semibold mb-1">' . lang('Look') . ' <span class="fw-normal fg-secondary">— ' . lang('corners, shadows, type and buttons') . '</span></div>
                <div class="d-flex flex-wrap gap-1 sd-tpl-look" role="radiogroup" aria-label="' . h(lang('Look')) . '">' . $look_btns . '</div>
            </div>
            <div>
                <div class="small fw-semibold mb-1">' . lang('Colour palette') . ' <span class="fw-normal fg-secondary">— <span id="sd-tpl-pal-name">' . lang('Bootstrap') . '</span></span></div>
                <div class="d-flex flex-wrap gap-3" role="radiogroup" aria-label="' . h(lang('Colour palette')) . '">' . $pal_btns . '</div>
            </div>
            <div class="form-text mt-3">' . lang('You can change both later in the editor, under Settings, Design, Theme.') . '</div>
        </div>
        <script>
            (function () {
                var box = document.getElementById("sd-tpl-theme");
                if (!box) return;
                var modal = box.closest(".dialog");
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
                if (modal) pgUI.dialog.on(modal, "show", sync);
            })();
        </script>';
}

/**
 * The settings modal of the visual designer.
 *
 * Two scopes, named for what they touch, each split into the panes the rail
 * lists: everything under "Shared by all pages" is stored on the style and
 * changes every page of the design; everything under "Only this page" is
 * stored on the page open in the tab bar, and each of its panes names that
 * page. Every setting is a row (pg_designer_setting()): its name and a
 * sentence on what it does, with its control.
 *
 * The modal closes from its own buttons only: a click beside it or Escape
 * used to close it in the middle of typing. What is changed here reaches the
 * design at once; Apply closes the modal, and the button beside it closes it
 * and saves the design like the toolbar's main button (style_designer.js,
 * initSettingsMenu()).
 *
 * A content-level operator never writes the design (pg_designer_is_full()):
 * the design's panes are hidden and the page pane opens first. The fields
 * stay in the form, so the shared values travel unchanged.
 */
function pg_designer_settings_modal($ctx)
{
    $liveform = $ctx['liveform'];
    $user     = $ctx['user'];
    $full     = pg_designer_is_full($user);
    $design_hidden = $full ? '' : ' d-none';

    // The drafts folder is not a place to publish a page to: a draft is a
    // state, set with the switch below the list. Only its own row goes; a
    // folder somebody made inside it keeps its row, or a page living there
    // would open with no folder selected and be moved by the next save.
    $folder_options = select_folder(0);
    $draft_folder_id = pg_page_draft_folder_id(false);

    if ($draft_folder_id > 0) {
        $folder_options = preg_replace('~<option value="' . $draft_folder_id . '"[^>]*>[^<]*</option>~', '', $folder_options, 1);
    }

    // Each folder says who may open the pages in it, inherited from the
    // nearest parent that sets it, for the line under the folder list.
    $folder_options = preg_replace_callback('~<option value="(\d+)"~', function ($m) {
        return $m[0] . ' data-access="' . h(get_access_control_type((int)$m[1])) . '"';
    }, $folder_options);

    // "Write with AI" beside the page's title and meta description
    // (includes/fn/ai_write.php). The page is the tab open when the button
    // is pressed; the saved page's headings and text join the fields.
    $ai_page = array(
        'record'      => 'page',
        'record_id'   => (int) ($ctx['active_page_id'] ?? 0),
        'record_from' => 'designer',
        'theme'       => 'dark',
        'context'     => array('page_title' => 'pg_page_title', 'meta_description' => 'pg_page_meta_description', 'page_name' => 'sd-page-name'),
    );
    $ai_title_button = pg_ai_write_button($user, $ai_page + array('target' => 'seo_title', 'field' => 'pg_page_title'));
    $ai_description_button = pg_ai_write_button($user, $ai_page + array('target' => 'seo_description', 'field' => 'pg_page_meta_description'));

    $rail = function ($pane, $icon, $label, $scope, $active, $hidden = false) use ($design_hidden) {
        return '<button class="nav-link text-start' . ($active ? ' active' : '') . ($scope === 'design' ? $design_hidden : '') . ($hidden ? ' d-none' : '') . '" id="tab-' . $pane . '-btn"
                    data-bs-toggle="pill" data-bs-target="#tab-' . $pane . '" type="button" role="tab"
                    aria-controls="tab-' . $pane . '" aria-selected="' . ($active ? 'true' : 'false') . '" data-sd-settings-scope="' . $scope . '">
                    <span class="bi ' . $icon . '" aria-hidden="true"></span><span class="sd-settings-nav-txt">' . $label . '</span>
                </button>';
    };
    $pane_open = function ($pane, $scope, $active) use ($design_hidden) {
        $scope_line = ($scope === 'design')
            ? '<div class="sd-settings-scope"><span class="bi bi-collection me-1" aria-hidden="true"></span>' . lang('These settings apply to every page in this design.') . '</div>'
            : '<div class="sd-settings-scope"><span class="bi bi-file-earmark me-1" aria-hidden="true"></span>' . lang('These settings apply only to') . ' <strong class="sd-settings-page-name">—</strong></div>';
        return '<div class="tab-pane fade' . ($active ? ' show active' : '') . ($scope === 'design' ? $design_hidden : '') . '" id="tab-' . $pane . '"
                    role="tabpanel" aria-labelledby="tab-' . $pane . '-btn" tabindex="0" data-sd-settings-scope="' . $scope . '">
                    ' . $scope_line;
    };

    // ── Shared: General ────────────────────────────────────────────────
    $general =
        '<section class="sd-settings-group">
            <h3 class="sd-settings-group-title">' . lang('Design') . '</h3>
            ' . pg_designer_setting(lang('Design Name'),
                lang('The name in the list of designs. Visitors never see it; left empty, it takes the name of the first page.'),
                $liveform->output_field(array('type'=>'text', 'name'=>'name', 'id'=>'sd-style-name', 'class'=>'form-control form-control-sm', 'maxlength'=>'100', 'placeholder'=>lang('Defaults to the first page name'))),
                array('for' => 'sd-style-name')) . '
            ' . pg_designer_setting(lang('Pages'),
                lang('The tabs at the top are the pages of this design. All of them use these settings, the theme and the files of the Assets panel.'),
                '<div class="sd-set-value" id="sd-design-summary">—</div>') . '
            ' . pg_designer_settings_framework_rows($ctx) . '
            ' . ($full ? pg_designer_setting(lang('Template'),
                lang('Every saved page of the design, with its shared components, widgets, folders and assets, becomes a template under Choose a Template. Save first: the template is made from what is on the server.'),
                '<button type="button" class="btn btn-sm btn-outline-light" id="sd-make-template"><i class="bi bi-grid-1x2 me-1" aria-hidden="true"></i>' . lang('Turn this design into a template') . '</button>',
                array('id' => 'sd-template-from-design')) : '') . '
        </section>';

    // Where the share buttons go: only offered while the site shows them.
    $social = '';
    if (SOCIAL_NETWORKING == TRUE) {
        $social = pg_designer_setting(lang('Social Networking'),
            lang('Where the share buttons of the site stand on the pages of this design, around the System Region; Disabled leaves them out. They show on public pages only.'),
            $liveform->output_field(array('type'=>'select', 'name'=>'social_networking_position', 'id'=>'sd-style-social', 'options'=>array(
                lang('Top Left')     => 'top_left',
                lang('Top Right')    => 'top_right',
                lang('Bottom Left')  => 'bottom_left',
                lang('Bottom Right') => 'bottom_right',
                lang('Disabled')     => 'disabled',
            ), 'class'=>'form-control form-control-sm')),
            array('for' => 'sd-style-social'));
    }
    $general .=
        '<section class="sd-settings-group">
            <h3 class="sd-settings-group-title">' . lang('Advanced') . '</h3>
            ' . $social . '
            ' . pg_designer_setting(lang('Collection'),
                lang('Only for classic Form List View and Form Item View pages that use this design: they keep two layouts, A and B, and show the one chosen here. The widgets of the visual designer draw their own layout and do not follow it.'),
                $liveform->output_field(array('type'=>'select', 'name'=>'collection', 'id'=>'sd-style-collection', 'options'=>array('A'=>'a', 'B'=>'b'), 'class'=>'form-control form-control-sm')),
                array('for' => 'sd-style-collection')) . '
        </section>';

    // ── Shared: Theme ──────────────────────────────────────────────────
    // The theme file's empty choice says what it is.
    $theme_options = get_theme_options();
    unset($theme_options['']);
    $theme_options = array(lang('— None —') => '') + $theme_options;
    $theme =
        // Look and colour palette: drawn by the editor (sdDesign.themes),
        // written to style_look / style_palette.
        '<section class="sd-settings-group" id="sd-theme-settings">
            <h3 class="sd-settings-group-title">' . lang('Theme') . '</h3>
            <div id="sd-theme-picker"></div>
        </section>
        <section class="sd-settings-group">
            <h3 class="sd-settings-group-title">' . lang('Theme file') . '</h3>
            ' . pg_designer_setting(lang('Theme file'),
                lang('A stylesheet from the file manager, loaded after everything else so it wins over the look and the palette. Shown in the Styles list and the Themes panel.'),
                $liveform->output_field(array('type'=>'select', 'name'=>'theme_id', 'id'=>'sd-style-theme-file', 'options'=>$theme_options, 'class'=>'form-control form-control-sm')),
                array('for' => 'sd-style-theme-file', 'id' => 'sd-theme-file-row')) . '
        </section>';

    // ── Shared: Head and meta tags ─────────────────────────────────────
    $head =
        '<section class="sd-settings-group">
            <h3 class="sd-settings-group-title">' . lang('Head code') . '</h3>
            ' . pg_designer_setting(lang('Code in the head'),
                lang('Written as it is into the head of every page of this design: a site verification tag, an analytics snippet, a link to an outside stylesheet. It runs on the site, not on the canvas or in the preview; a broken tag can break every page.'),
                $liveform->output_field(array('type'=>'textarea', 'name'=>'style_head', 'id'=>'sd-style-head', 'rows'=>'6', 'class'=>'form-control form-control-sm sd-code-field', 'spellcheck'=>'false', 'autocomplete'=>'off', 'placeholder'=>'<meta name="google-site-verification" content="…">')),
                array('for' => 'sd-style-head', 'wide' => true)) . '
        </section>
        ' . ($full ? pg_designer_meta_tags_group('design', isset($ctx['style']['meta_tags']) ? $ctx['style']['meta_tags'] : '') : '') . '
        <p class="sd-settings-note"><span class="bi bi-info-circle me-1" aria-hidden="true"></span>' . lang('Stylesheets, scripts and fonts are managed in the Assets panel on the right and are also shared by every page.') . '</p>';

    // ── Page: the page itself ──────────────────────────────────────────
    // The name, the folder and the home flag are where the page lives, and
    // a content-level save keeps the stored ones (pg_designer_save_page()):
    // shown, not offered.
    $fixed      = $full ? '' : ' ' . lang('Only a designer changes it.');
    $fixed_attr = $full ? '' : ' disabled';
    $page =
        '<section class="sd-settings-group">
            <h3 class="sd-settings-group-title">' . lang('Address and access') . '</h3>
            ' . pg_designer_setting(lang('Page Name'),
                lang('The address of the page: it comes after the site address. Plain letters, digits and hyphens work everywhere (about-us rather than About Us). Can also be edited by double-clicking its tab.') . $fixed,
                '<input type="text" id="sd-page-name" class="form-control form-control-sm sd-page-field" data-page-key="page_name" maxlength="100" placeholder="' . lang('Page name...') . '" autocomplete="off" spellcheck="false"' . ($full ? '' : ' readonly') . '>
                <div class="sd-set-status" id="sd-page-address" aria-live="polite"></div>'
                // A saved page under a new name: its old address may send
                // visitors on (pg_short_link_page_renamed()), shown by
                // _sdSettingsAddress() while the name differs.
                . (($full && pg_short_link_redirects_ready()) ? '
                <div class="form-field mt-1 d-none" id="sd-page-redirect-row">
                    <input class="check" type="checkbox" id="sd-page-name-redirect" value="1" checked>
                    <label class="small" for="sd-page-name-redirect">' . lang('Send visitors of the old address here (301 redirect)') . ' <span class="fg-secondary" id="sd-page-redirect-from"></span></label>
                </div>' : ''),
                array('for' => 'sd-page-name')) . '
            ' . pg_designer_setting(lang('Page Folder'),
                lang('The folder decides who may open the page and where it is listed among the pages; administrators, designers and managers can always open it.') . $fixed,
                '<select name="page_folder" id="pg_page_folder" class="form-control form-control-sm sd-page-field" data-page-key="page_folder"' . $fixed_attr . '>
                    ' . $folder_options . '
                </select>
                <div class="sd-set-status" id="sd-page-access" aria-live="polite"></div>',
                array('for' => 'pg_page_folder')) . '
            ' . (($full && pg_page_draft_ready()) ? pg_designer_setting(lang('Draft - not on the site'),
                lang('A draft is kept in the private Drafts folder: visitors cannot open it, administrators can. Once published it goes back to the folder above. Applied when the design is published.'),
                '<div class="form-field"><div class="switch"><input class="sd-page-field" type="checkbox" role="switch" id="pg_page_draft" value="1" data-page-key="page_draft"></div></div>',
                array('for' => 'pg_page_draft', 'switch' => true)) : '') . '
        </section>' . (($full && pg_page_draft_ready() && function_exists('pg_page_schedule_ready') && pg_page_schedule_ready()) ? '
        <section class="sd-settings-group" id="sd-page-schedule-group">
            <h3 class="sd-settings-group-title">' . lang('Schedule') . '</h3>
            ' . pg_designer_setting(lang('Publish at'),
                lang('The page stays a draft until this time; then the general job puts it on the site, in the folder above. Empty: it is published by hand.'),
                '<input type="datetime-local" id="pg_page_publish_at" class="form-control form-control-sm sd-page-field" data-page-key="page_publish_at" step="60">',
                array('for' => 'pg_page_publish_at')) . '
            ' . pg_designer_setting(lang('Take off the site at'),
                lang('At this time the general job moves the page back to the private Drafts folder; the folder it returns to is kept. Empty: it stays on the site.'),
                '<input type="datetime-local" id="pg_page_unpublish_at" class="form-control form-control-sm sd-page-field" data-page-key="page_unpublish_at" step="60">
                <div class="sd-set-status" id="sd-page-schedule" aria-live="polite"></div>',
                array('for' => 'pg_page_unpublish_at')) . '
            <p class="sd-settings-note"><span class="bi bi-clock me-1" aria-hidden="true"></span>' . h(lang(array('string' => 'Times are in the site\'s time zone, {var:1}. Both apply when the design is published; the home page takes neither.', 'vars' => array(date_default_timezone_get())))) . '</p>
        </section>' : '') . '
        <section class="sd-settings-group">
            <h3 class="sd-settings-group-title">' . lang('On the site') . '</h3>
            ' . (((int)$user['role'] < 3) ? pg_designer_setting(lang('Set As Homepage'),
                lang('The site address on its own (/) opens this page. A home page cannot be a draft; when several pages are marked, the site shows one of them at random.') . $fixed,
                '<input type="hidden" name="page_home" value="0">
                <div class="form-field"><div class="switch"><input class="sd-page-field" type="checkbox" role="switch" name="page_home" id="pg_page_home" value="1" data-page-key="page_home"' . $fixed_attr . '></div></div>',
                array('for' => 'pg_page_home', 'switch' => true)) : '') . '
            ' . pg_designer_setting(lang('Include in site search'),
                lang('The search box of the site finds this page too. Turn it off for pages nobody should land on from a search: a thank-you page, an e-mail page.'),
                '<input type="hidden" name="page_search" value="0">
                <div class="form-field"><div class="switch"><input class="sd-page-field" type="checkbox" role="switch" name="page_search" id="pg_page_search" value="1" data-page-key="page_search"></div></div>',
                array('for' => 'pg_page_search', 'switch' => true)) . '
            ' . pg_designer_setting(lang('Search Keywords'),
                lang('Somebody searching for one of these words gets this page at the top of the results; a search for a part of one finds the page too. Press Enter after each word.'),
                '<input type="text" value="" name="page_search_keywords" id="pg_search_keywords" class="form-control form-control-sm tagin min-height-tagin sd-page-field" data-pg-chips data-page-key="page_search_keywords" data-placeholder="' . lang('Add tags') . '">',
                array('for' => 'pg_search_keywords', 'wide' => true, 'id' => 'pg_search_kw_row')) . '
        </section>';

    // ── Page: search engines ───────────────────────────────────────────
    $noindex_rows = '';
    if (pg_page_noindex_ready() == TRUE) {
        $noindex_rows =
            pg_designer_setting(lang('Close to Search Engines (noindex)'),
                lang('The page is served with a noindex robots tag, is blocked in robots.txt and is left out of the site map.')
                . '<span class="sd-set-warn" id="pg_noindex_hint" style="display:none">' . lang('A page blocked in robots.txt is not crawled, so the noindex tag on it is never read. Use this before a page reaches the results; a page that is already listed can take a while to drop out.') . '</span>',
                '<input type="hidden" name="page_noindex" value="0">
                <div class="form-field"><div class="switch"><input class="sd-page-field" type="checkbox" role="switch" name="page_noindex" id="pg_page_noindex" value="1" data-page-key="page_noindex"></div></div>',
                array('for' => 'pg_page_noindex', 'switch' => true)) .
            pg_designer_setting(lang('Do Not Follow Links on This Page (nofollow)'),
                lang('Leave this off so that search engines keep discovering the items this page links to. Turn it on for a widget page whose links already appear on a page that is indexed.')
                . ' ' . lang('It can be turned on only while the page is closed to search engines.'),
                '<input type="hidden" name="page_nofollow" value="0">
                <div class="form-field"><div class="switch"><input class="sd-page-field" type="checkbox" role="switch" name="page_nofollow" id="pg_page_nofollow" value="1" data-page-key="page_nofollow" disabled="disabled"></div></div>',
                array('for' => 'pg_page_nofollow', 'switch' => true));
    }
    // The page's own canonical address and the robots directives besides
    // noindex (page.canonical_url, page.noarchive, page.max_snippet;
    // 2026.11.0), where the columns exist. Content like the title: every
    // level that edits the page writes them. The editor checks the two
    // boxes as they are typed (_sdSeoExtrasStatus()); the save refuses what
    // does not pass pg_page_canonical_url_check() / pg_page_max_snippet_check().
    $seo_extra_rows = '';
    if (pg_page_seo_extras_ready()) {
        $seo_extra_rows =
            pg_designer_setting(lang('Canonical Address'),
                lang('Leave empty to use the address of this page. Fill it in when the page repeats another one, to name the address search engines should list instead: a full address starting with https://, or a path on this site such as /about-us. Products, posts and other items shown through this page keep their own address.'),
                '<input type="text" id="pg_page_canonical_url" class="form-control form-control-sm sd-page-field" data-page-key="page_canonical_url" maxlength="2000" placeholder="https://" autocomplete="off" spellcheck="false">
                <div class="sd-set-status" id="sd-canonical-status" aria-live="polite"></div>',
                array('for' => 'pg_page_canonical_url', 'wide' => true)) .
            pg_designer_setting(lang('Do Not Keep a Cached Copy (noarchive)'),
                lang('Asks search engines not to keep or show a cached copy of the page. Like the snippet length, it is left out while the page is closed to search engines.'),
                '<div class="form-field"><div class="switch"><input class="sd-page-field" type="checkbox" role="switch" id="pg_page_noarchive" value="1" data-page-key="page_noarchive"></div></div>',
                array('for' => 'pg_page_noarchive', 'switch' => true)) .
            pg_designer_setting(lang('Snippet Length (max-snippet)'),
                lang('The most characters of text a search result may quote from the page. Leave empty to let the search engine decide; -1 is no limit, 0 is no text snippet.'),
                '<input type="number" id="pg_page_max_snippet" class="form-control form-control-sm sd-page-field" data-page-key="page_max_snippet" min="-1" max="' . pg_page_max_snippet_limit() . '" step="1" inputmode="numeric">
                <div class="sd-set-status" id="sd-max-snippet-status" aria-live="polite"></div>',
                array('for' => 'pg_page_max_snippet'));
    }
    $seo =
        '<section class="sd-settings-group">
            <h3 class="sd-settings-group-title">' . lang('Search result') . '</h3>
            ' . pg_designer_setting(lang('Page Title'),
                lang('The title in the browser tab and in search results; 30 to 60 characters read best. Left empty, the title of the site is used.'),
                '<input type="text" name="page_title" id="pg_page_title" class="form-control form-control-sm sd-page-field" data-page-key="page_title" maxlength="255">
                <div id="seo_c_pg_page_title"></div>',
                array('for' => 'pg_page_title', 'wide' => true, 'action' => $ai_title_button)) . '
            ' . pg_designer_setting(lang('Meta Description'),
                lang('The summary under the title in search results and in shared links; 150 to 160 characters read best. Left empty, the description of the site is used.'),
                '<textarea name="page_meta_description" id="pg_page_meta_description" class="form-control form-control-sm sd-page-field" data-page-key="page_meta_description" rows="3" maxlength="255"></textarea>
                <div id="seo_c_pg_page_meta_description"></div>',
                array('for' => 'pg_page_meta_description', 'wide' => true, 'action' => $ai_description_button)) . '
            ' . pg_designer_setting(lang('Preview'),
                lang('Roughly how a search engine lists the page. It may shorten the text or show a passage of the page instead; a page that shows one record (a product, a post) takes the title and description of the record.'),
                '<div class="sd-serp" id="sd-serp" aria-hidden="true">
                    <div class="sd-serp-site"><span class="sd-serp-icon bi bi-globe2"></span><span class="sd-serp-where"><span class="sd-serp-name"></span><span class="sd-serp-url"></span></span></div>
                    <div class="sd-serp-title"></div>
                    <div class="sd-serp-desc"></div>
                </div>
                <div class="sd-set-status" id="sd-serp-note"></div>',
                array('wide' => true)) . '
        </section>
        <section class="sd-settings-group">
            <h3 class="sd-settings-group-title">' . lang('Indexing') . '</h3>
            ' . pg_designer_setting(lang('Include in the site map'),
                lang('Lists the page in sitemap.xml, the list search engines read to find the pages of the site. A page closed to search engines is never listed.'),
                '<input type="hidden" name="page_sitemap" value="0">
                <div class="form-field"><div class="switch"><input class="sd-page-field" type="checkbox" role="switch" name="page_sitemap" id="pg_page_sitemap" value="1" data-page-key="page_sitemap"></div></div>',
                array('for' => 'pg_page_sitemap', 'switch' => true)) . '
            ' . $noindex_rows . '
            ' . $seo_extra_rows . '
        </section>';
    // The page's own JSON-LD (page.custom_jsonld, 2026.4.4), where the
    // column exists. The editor checks it as it is typed
    // (_sdJsonLdStatus()); the save refuses what does not pass
    // pg_page_custom_jsonld_check().
    if (pg_page_custom_jsonld_ready()) {
        $seo .=
        '<section class="sd-settings-group">
            <h3 class="sd-settings-group-title">' . lang('Structured Data') . '</h3>
            ' . pg_designer_setting(lang('JSON-LD for this page'),
                lang('A schema.org block that tells search engines what the page is: a FAQ, an event, a local business. Written in the head of the page while structured data is on in the Site Settings, beside the site-wide block and the ones the site writes itself. It must be valid JSON, and every item needs an @type.'),
                '<textarea id="pg_page_custom_jsonld" class="form-control form-control-sm sd-code-field sd-page-field" data-page-key="page_custom_jsonld" rows="6" spellcheck="false" autocomplete="off" placeholder="' . h('{"@context": "https://schema.org", "@type": "FAQPage", "mainEntity": […]}') . '"></textarea>
                <div class="sd-set-status" id="sd-jsonld-status" aria-live="polite"></div>',
                array('for' => 'pg_page_custom_jsonld', 'wide' => true)) . '
        </section>';
    }

    // ── Page: page type ────────────────────────────────────────────────
    // A classic page placed in the design keeps the fields of its type (a
    // catalog's product group, a gallery's columns) and its Layout Type. The
    // editor shows the pill for such a page only and fills the pane from
    // edit_page.php?properties=1 (includes/fn/page_type_pane.php), which also
    // saves it, with its own button and at once.
    $page_type =
        '<section class="sd-settings-group">
            <h3 class="sd-settings-group-title">' . lang('Page Type') . ': <span id="sd-page-type-name">—</span></h3>
            <p class="sd-set-help">' . lang('This page draws its system region from its page type. These settings are stored with their own button, at once, not with the next Save or Publish.') . '</p>
            <div id="sd-page-type-fields" class="sd-page-type-fields"></div>
            <div id="sd-page-type-links" class="d-flex flex-wrap gap-3 mt-3"></div>
            <div class="d-flex align-items-center gap-3 mt-5">
                <button type="button" class="btn btn-sm btn-solid theme-primary" id="sd-page-type-save" disabled><span class="bi bi-floppy me-1" aria-hidden="true"></span>' . lang('Save') . '</button>
                <span class="sd-set-status" id="sd-page-type-status" aria-live="polite"></span>
            </div>
        </section>';

    // ── Page: meta tags ────────────────────────────────────────────────
    $page_meta = pg_designer_meta_tags_group('page');
    $has_page_meta = ($page_meta !== '');

    return '
        <dialog class="dialog dialog-xl lg-down:dialog-fullscreen sd-settings-dialog sd-settings-content" id="styleSettingsModal" aria-labelledby="styleSettingsModalLabel"
             data-bs-backdrop="static" data-bs-keyboard="false" data-bs-theme="dark">
            <!-- The largest dialog from 1024px up, the whole screen below it.
                 Its height and the scroll chain inside are in
                 style_designer.css (.sd-settings-*): an inline size beats
                 every media query. -->

                    <div class="dialog-header sd-settings-header">
                        <h2 class="dialog-title fs-md" id="styleSettingsModalLabel"><span class="bi bi-gear me-3" aria-hidden="true"></span>' . lang('Settings') . '</h2>
                        <button type="button" class="btn-close fg-white" data-bs-dismiss="dialog" aria-label="' . h(lang('Close')) . '"></button>
                    </div>

                    <div class="dialog-body sd-settings-main">

                        <div class="sd-settings-nav">
                            <!-- ONE tablist: Bootstrap deactivates the previous pill by looking
                                 for the active sibling inside the same tablist, so two separate
                                 navs would leave both panes showing. The group headings are plain
                                 divs the pill logic ignores. -->
                            <nav class="nav nav-pills" id="settingsTabs" role="tablist">
                                <div class="sd-settings-nav-title' . $design_hidden . '" data-sd-settings-scope="design">' . lang('Shared by all pages') . '</div>
                                ' . $rail('style', 'bi-palette', lang('General'), 'design', $full) . '
                                ' . $rail('theme', 'bi-brush', lang('Theme'), 'design', false) . '
                                ' . $rail('head', 'bi-code-slash', lang('Head & meta'), 'design', false) . '
                                <div class="sd-settings-nav-title" data-sd-settings-scope="page">' . lang('Only this page') . '<span class="sd-settings-nav-page" id="sd-settings-page-tab-label">' . lang('Page') . '</span></div>
                                ' . $rail('page', 'bi-file-earmark', lang('Page'), 'page', !$full) . '
                                ' . $rail('seo', 'bi-search', lang('Search Engines'), 'page', false) . '
                                ' . ($has_page_meta ? $rail('page-meta', 'bi-tags', lang('Meta Tags'), 'page', false) : '') . '
                                ' . $rail('page-type', 'bi-sliders', lang('Page Type'), 'page', false, true) . '
                            </nav>
                        </div>

                        <div class="tab-content sd-settings-body">
                            ' . $pane_open('style', 'design', $full) . $general . '</div>
                            ' . $pane_open('theme', 'design', false) . $theme . '</div>
                            ' . $pane_open('head', 'design', false) . $head . '</div>
                            ' . $pane_open('page', 'page', !$full) . $page . '</div>
                            ' . $pane_open('seo', 'page', false) . $seo . '</div>
                            ' . ($has_page_meta ? $pane_open('page-meta', 'page', false) . $page_meta . '</div>' : '') . '
                            ' . $pane_open('page-type', 'page', false) . $page_type . '</div>
                            ' . $liveform->output_field(array('type'=>'hidden', 'name'=>'additional_body_classes')) . '
                            ' . $liveform->output_field(array('type'=>'hidden', 'name'=>'style_empty_cell_width_percentage')) . '
                        </div>
                    </div>

                    <div class="dialog-footer sd-settings-footer">
                        <span class="sd-settings-footer-note"><span class="bi bi-lightning-charge me-1" aria-hidden="true"></span>' . lang('Changes reach the design at once; they are stored with the next Save or Publish.') . '</span>
                        <button type="button" class="btn btn-sm btn-outline-light" data-bs-dismiss="dialog">' . lang('Apply') . '</button>
                        <button type="button" class="btn btn-sm btn-solid theme-primary" id="sd-settings-save"><span class="bi bi-rocket-takeoff me-1" aria-hidden="true"></span><span class="sd-settings-save-txt">' . lang('Publish') . '</span></button>
                    </div>

        </dialog>';
}

/**
 * One setting of the settings modal: its name and a sentence on what it
 * does, beside its control, or above it with 'wide' (text boxes, lists,
 * previews). A switch stays on the right of its text at every width. The
 * stylesheet lays the row out (.sd-set in style_designer.css); the editor
 * points the control's aria-describedby at the sentence.
 *
 * $name and $help are lang() text. $opts: 'for' (the control's id, makes
 * the name its label), 'wide', 'switch', 'id' (the row's), 'action' (a
 * button beside the name, such as pg_ai_write_button(); its panel opens
 * under the name).
 */
function pg_designer_setting($name, $help, $control, $opts = array())
{
    $class = 'sd-set';
    if (!empty($opts['wide']))   $class .= ' sd-set-wide';
    if (!empty($opts['switch'])) $class .= ' sd-set-switch';
    $for  = isset($opts['for']) ? (string)$opts['for'] : '';
    $name = ($for !== '')
        ? '<label class="sd-set-name" for="' . h($for) . '">' . $name . '</label>'
        : '<div class="sd-set-name">' . $name . '</div>';
    if (!empty($opts['action'])) {
        $name = pg_ai_write_label_row($name, (string)$opts['action']);
    }
    return '<div class="' . $class . '"' . (isset($opts['id']) ? ' id="' . h($opts['id']) . '"' : '') . '>
                <div class="sd-set-text">' . $name . ($help !== '' ? '<div class="sd-set-help">' . $help . '</div>' : '') . '</div>
                <div class="sd-set-ctrl">' . $control . '</div>
            </div>';
}

/**
 * A Meta Tags group of the settings modal: the design's list ('design', in
 * Head & meta) or the open page's ('page', its own pane). The list is a
 * hidden input holding the JSON the column stores; the editor draws a row
 * per tag from it and writes it back as the rows change
 * (_sdMetaListMount()). The design's input is posted with the form as
 * style_meta_tags; the page's is a page field (page_meta_tags) and travels
 * with the page. Nothing is drawn before the columns exist (11.0.3).
 */
function pg_designer_meta_tags_group($scope, $value = '')
{
    if (!pg_meta_tags_ready()) {
        return '';
    }

    if ($scope === 'design') {
        $input = '<input type="hidden" name="style_meta_tags" id="sd-style-meta-tags" value="' . h(pg_meta_tags_encode(pg_meta_tags_normalize((string)$value))) . '">';
        $help  = lang('Written in the head of every page of this design. A tag of the same name in the Meta Tags of a page replaces it on that page.');
    } else {
        $input = '<input type="hidden" id="pg_page_meta_tags" class="sd-page-field" data-page-key="page_meta_tags" value="">';
        $help  = lang('Written in the head of this page only. A tag here replaces a design tag of the same name.');
    }

    return '<section class="sd-settings-group" data-sd-meta-scope="' . h($scope) . '">
        <h3 class="sd-settings-group-title">' . lang('Meta Tags') . '</h3>
        <p class="sd-set-help sd-settings-group-help">' . $help . ' ' . lang('Names starting with og:, fb: or article: are written as property, the others as name, and a tag replaces the Open Graph or Twitter tag of the same name the site writes. viewport, description and robots come from the page settings.') . '</p>
        ' . $input . '
        <div class="sd-meta-list"></div>
        <button type="button" class="btn btn-sm btn-outline-light sd-meta-add"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . lang('Add Meta Tag') . '</button>
    </section>';
}

/**
 * The framework the design is built on and the template it started from,
 * read-only: both are fixed when the design is created.
 */
function pg_designer_settings_framework_rows($ctx)
{
    $style = isset($ctx['style']) && is_array($ctx['style']) ? $ctx['style'] : array();
    $fw    = pg_design_framework(isset($style['framework']) ? $style['framework'] : '');
    $out = pg_designer_setting(lang('Framework'),
        lang('Chosen when the design was created. The pages are built on it, so it does not change.'),
        '<input type="text" class="form-control form-control-sm" id="sd-style-framework" value="' . h($fw['label']) . '" readonly>',
        array('for' => 'sd-style-framework'));
    $tpl_id = isset($style['template']) ? (string)$style['template'] : '';
    if ($tpl_id !== '') {
        $tpl  = pg_design_template($tpl_id);
        $name = $tpl ? (string)$tpl['name'] : $tpl_id;
        $ver  = isset($style['template_version']) ? (string)$style['template_version'] : '';
        $out .= pg_designer_setting(lang('Started from'),
            lang('The template the design was made from, and its version. For reference only: a later version of the template does not change this design.'),
            '<input type="text" class="form-control form-control-sm" id="sd-style-template" value="' . h($name . ($ver !== '' ? ' ' . $ver : '')) . '" readonly>',
            array('for' => 'sd-style-template'));
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
        <dialog class="dialog dialog-scrollable" id="sdPickPageModal" style="background:#1e2127; color:#c9d1d9; border:1px solid #30363d;">
                    <div class="dialog-header" style="border-color:#30363d;">
                        <h5 class="dialog-title fs-md"><span class="bi bi-files me-3"></span>' . lang('Select Page') . '</h5>
                        <button type="button" class="btn-close fg-white" data-bs-dismiss="dialog"></button>
                    </div>
                    <div class="dialog-body p-0">
                        <div class="p-5 pb-3">
                            <input type="text" class="form-control form-control-sm" id="sd-pick-page-filter" placeholder="' . lang('Search...') . '" autocomplete="off">
                        </div>
                        <div class="alert theme-warning py-3 small mx-5 mb-3">
                            <span class="bi bi-exclamation-triangle me-1"></span>' . lang('A page you add here joins this design: it keeps its own layout, but its stylesheets, scripts, fonts and theme are replaced by this design\'s.') . '
                        </div>
                        <div class="list-group list-group-flush" id="sd-pick-page-list">
                            <div class="list-group-item bg-transparent fg-secondary small">' . lang('Loading...') . '</div>
                        </div>
                    </div>
        </dialog>';
}

/**
 * "Import HTML / ZIP" — one file input, an optional project name (the folder
 * under Import/ in the file manager), and a plain-language note on what
 * happens to the <head>. Posts to designer_import.php from JS.
 */
function pg_designer_import_modal()
{
    return '
        <dialog class="dialog" id="sdImportModal" style="background:#1e2127; color:#c9d1d9; border:1px solid #30363d;">
                    <div class="dialog-header" style="border-color:#30363d;">
                        <h5 class="dialog-title fs-md"><span class="bi bi-file-earmark-code me-3"></span>' . lang('Import HTML / ZIP') . '</h5>
                        <button type="button" class="btn-close fg-white" data-bs-dismiss="dialog"></button>
                    </div>
                    <div class="dialog-body">
                        <div class="mb-5">
                            <label class="form-label small" for="sd-import-file">' . lang('File') . '</label>
                            <input type="file" class="form-control form-control-sm" id="sd-import-file" accept=".html,.htm,.zip">
                            <div class="form-text small">' . lang('A single .html file, or a .zip with the whole project (HTML pages plus their CSS, JavaScript, images and fonts).') . '</div>
                        </div>
                        <div class="mb-5">
                            <label class="form-label small" for="sd-import-project">' . lang('Project name') . '</label>
                            <input type="text" class="form-control form-control-sm" id="sd-import-project" maxlength="60" placeholder="' . lang('Defaults to the file name') . '" autocomplete="off">
                            <div class="form-text small">' . lang('Files are stored under Import / this name in the file manager, keeping the project\'s folders.') . '</div>
                        </div>
                        <div class="alert theme-secondary py-3 small mb-0">
                            <div><span class="bi bi-info-circle me-1"></span>' . lang('Every .html becomes a page (index.html → index). Its head is replaced by the design\'s: stylesheets and scripts arrive as design assets, Google Fonts in the fonts list, Bootstrap and jQuery are dropped in favour of the design\'s own.') . '</div>
                            <div class="mt-1">' . lang('Pages open as unsaved tabs — review them, then publish.') . '</div>
                        </div>
                        <div id="sd-import-status" class="small mt-5" style="display:none"></div>
                    </div>
                    <div class="dialog-footer" style="border-color:#30363d;">
                        <button type="button" class="btn btn-sm btn-solid theme-secondary" data-bs-dismiss="dialog">' . lang('Cancel') . '</button>
                        <button type="button" class="btn btn-sm btn-solid theme-primary" id="sd-import-run"><span class="bi bi-upload me-1"></span>' . lang('Import') . '</button>
                    </div>
        </dialog>';
}

/**
 * "Turn this design into a template" (Settings > General): the name and the
 * description the template is listed with. The fields carry no name: the
 * modal sits inside the design form, and the editor posts them to api.php
 * (designer/template_from_design) itself.
 */
function pg_designer_make_template_modal()
{
    return '
        <dialog class="dialog" id="sdMakeTemplateModal" aria-labelledby="sdMakeTemplateModalLabel" style="background:#1e2127; color:#c9d1d9; border:1px solid #30363d;">
                    <div class="dialog-header" style="border-color:#30363d;">
                        <h5 class="dialog-title fs-md" id="sdMakeTemplateModalLabel"><span class="bi bi-grid-1x2 me-3" aria-hidden="true"></span>' . lang('Turn this design into a template') . '</h5>
                        <button type="button" class="btn-close fg-white" data-bs-dismiss="dialog" aria-label="' . h(lang('Close')) . '"></button>
                    </div>
                    <div class="dialog-body">
                        <div class="mb-5">
                            <label class="form-label small" for="sd-make-template-name">' . lang('Name') . '</label>
                            <input type="text" class="form-control form-control-sm" id="sd-make-template-name" maxlength="255" autocomplete="off">
                        </div>
                        <div class="mb-0">
                            <label class="form-label small" for="sd-make-template-description">' . lang('Description') . '</label>
                            <textarea class="form-control form-control-sm" id="sd-make-template-description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="dialog-footer" style="border-color:#30363d;">
                        <button type="button" class="btn btn-sm btn-solid theme-secondary" data-bs-dismiss="dialog">' . lang('Cancel') . '</button>
                        <button type="button" class="btn btn-sm btn-solid theme-primary" id="sd-make-template-run"><span class="bi bi-grid-1x2 me-1" aria-hidden="true"></span>' . lang('Create template') . '</button>
                    </div>
        </dialog>';
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
 * a key in it is rewritten once. A widget's tree may name a page by its key
 * too: the page a link to another page opens (props._bindParams.*.page_id,
 * pg_sw_page_link_resolve_tabs()); such a tree is written back through
 * pg_designer_tree_encode() in the same update. The editor makes the same
 * substitution in its cache from the save reply, so the two sides agree
 * without a reload.
 *
 * A widget can sit inside a shared component the page places (the login
 * region in an account bar) rather than on the page itself, so the trees of
 * the components found are read too, as deep as the renderer follows them.
 *
 * A page sent as unchanged carries no tree, yet its widgets may name a new
 * tab: picking a page in a widget's settings writes the widget row at once
 * and leaves the page's own tree and settings as they were. Such a page is
 * read from its stored tree, and only when some widget still holds a key.
 */
function pg_designer_resolve_tab_refs($pages, $tab_ids)
{
    $find_ids = function ($json, &$into) {
        $json = (string)$json;
        if (strpos($json, '"sharedId"') === false) return;
        if (preg_match_all('/"sharedId":\s*"?(\d+)"?/', $json, $mm)) {
            foreach ($mm[1] as $sid) { $sid = (int)$sid; if ($sid > 0) $into[$sid] = true; }
        }
    };
    // A '_' in the pattern matches any character, which only widens the
    // match; the tree walker reads the key exactly.
    $tree_like = "tree_json LIKE '%\"page_id\":\"tab:%'";
    if (!(int)db_value("SELECT COUNT(*) FROM shared_components WHERE system_region_config LIKE '%\"tab:%' OR $tree_like")) return;
    $sids = array();
    foreach ((array)$pages as $p) {
        if (!is_array($p)) continue;
        if (isset($p['tree_json'])) {
            $find_ids($p['tree_json'], $sids);
        } elseif (!empty($p['page_id']) && (int)$p['page_id'] > 0) {
            $find_ids(pg_page_tree_json((int)$p['page_id']), $sids);
        }
    }
    if (empty($sids)) return;

    // The components inside the ones found, level by level; a cycle or a
    // chain deeper than the renderer draws (8) stops here as it does there.
    $frontier = $sids;
    for ($depth = 0; $depth < 8 && $frontier; $depth++) {
        $nested = array();
        foreach ((array)db_items("SELECT tree_json FROM shared_components
                                  WHERE id IN (" . implode(',', array_keys($frontier)) . ")
                                    AND tree_json LIKE '%sharedId%'") as $row) {
            $find_ids($row['tree_json'], $nested);
        }
        $frontier = array_diff_key($nested, $sids);
        $sids += $frontier;
    }
    $rows = db_items("SELECT id, system_region_config, tree_json FROM shared_components
                      WHERE id IN (" . implode(',', array_keys($sids)) . ")
                        AND (system_region_config LIKE '%tab:%' OR $tree_like)");
    foreach ((is_array($rows) ? $rows : array()) as $row) {
        $set = array();
        $cfg = json_decode((string)$row['system_region_config'], true);
        if (is_array($cfg)) {
            $resolved = pg_designer_resolve_tab_values($cfg, $tab_ids);
            if ($resolved !== $cfg) {
                $set[] = "system_region_config = '" . e(json_encode($resolved, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . "'";
            }
        }
        if (strpos((string)$row['tree_json'], 'tab:') !== false) {
            $tree = json_decode((string)$row['tree_json'], true);
            if (is_array($tree)) {
                $resolved_tree = pg_sw_page_link_resolve_tabs($tree, $tab_ids);
                $tree_json = ($resolved_tree !== $tree) ? pg_designer_tree_encode($resolved_tree) : '';
                if ($tree_json !== '') $set[] = "tree_json = '" . e($tree_json) . "'";
            }
        }
        if (!$set) continue;
        db("UPDATE shared_components
            SET " . implode(', ', $set) . ",
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
        foreach ($errors as $e) $liveform->add_error(h($e));
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
    // blank row; the operator can rename it in Settings → General.
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

    // ── Pages the editor left as they are ─────────────────────────────────
    // A saved page nothing touched since the last save arrives marked
    // `unchanged` and without its tree. Its stored row stays as it is: a save
    // no longer writes, scores, translates and logs every page of the design
    // each time. The one save that does rewrite every page — folding the old
    // design-wide body classes onto them, below — reads their stored trees
    // back and takes them like any other page.
    $abc_pending = ($mode === 'edit' && $style_id > 0)
        ? trim((string)db_value("SELECT additional_body_classes FROM style WHERE style_id = '$style_id' LIMIT 1"))
        : '';
    if ($abc_pending === '') $abc_pending = trim((string)$liveform->get_field_value('additional_body_classes'));
    foreach ($pages as $pi => $pp) {
        if (!is_array($pp) || empty($pp['unchanged'])) continue;
        $upid = isset($pp['page_id']) ? (int)$pp['page_id'] : 0;
        if ($upid > 0 && $abc_pending === '') continue;
        unset($pages[$pi]['unchanged']);
        $stored_tree = ($upid > 0) ? pg_page_tree_json($upid) : '';
        if ($stored_tree !== '') $pages[$pi]['tree_json'] = $stored_tree;
    }

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
        // Left as stored: nothing to check against the locks or the rows.
        if (!empty($p['unchanged'])) continue;
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
    // The design's meta tags: the hidden input exists only where the column
    // does (pg_designer_meta_tags_group()), and save_system_style() leaves
    // the stored list alone when the key is missing.
    if (isset($_POST['style_meta_tags'])) {
        $style_data['style_meta_tags'] = (string)$_POST['style_meta_tags'];
    }
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
    $written = array();   // ids of the pages this save wrote
    $notes   = array();   // what a renamed page's short links became
    $cf_jobs = array();   // page_id => form settings, reconciled once every page has its id
    foreach ($pages as $p) {
        $key = isset($p['key']) ? (string)$p['key'] : '';
        // Left as stored: the tab keeps its id and the state it is in.
        if (!empty($p['unchanged'])) {
            $id_map[] = array('key' => $key, 'page_id' => (int)$p['page_id'], 'draft' => !empty($p['page_draft']) ? 1 : 0);
            continue;
        }
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
            $id_entry = array('key' => $key, 'page_id' => (int)$r['page_id'], 'draft' => !empty($r['draft']) ? 1 : 0);
            // The times as the save left them: one that had come is cleared.
            if (isset($r['publish_at'])) {
                $id_entry['publish_at']   = $r['publish_at'];
                $id_entry['unpublish_at'] = $r['unpublish_at'];
            }
            $id_map[] = $id_entry;
            $written[] = (int)$r['page_id'];
            // A renamed page's old address, sent on or not.
            if (!empty($r['renamed'])) {
                $renamed_said = pg_short_link_page_renamed_messages($r['renamed'], PATH);
                foreach ($renamed_said['notes'] as $note) $notes[] = $note;
                foreach ($renamed_said['warnings'] as $w) $warnings[] = $w;
            }
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
    // A design file limited to some pages names a page saved for the first
    // time by its tab, the same way (pg_design_asset_on_page()).
    if ($can_write_design && $tab_ids && pg_multi_page_design_ready()) {
        $asset_css = pg_designer_resolve_asset_page_refs($style_data['style_custom_css'], 'tab:', $tab_ids);
        $asset_js  = pg_designer_resolve_asset_page_refs($style_data['style_custom_js'], 'tab:', $tab_ids);
        if ($asset_css !== $style_data['style_custom_css'] || $asset_js !== $style_data['style_custom_js']) {
            $style_data['style_custom_css'] = $asset_css;
            $style_data['style_custom_js']  = $asset_js;
            db("UPDATE style
                SET style_custom_css = '" . escape($asset_css) . "', style_custom_js = '" . escape($asset_js) . "'
                WHERE style_id = '" . (int)$style_id . "'");
        }
    }
    foreach ($cf_jobs as $cf_page_id => $cf_set) {
        $cf_set = pg_designer_resolve_tab_values($cf_set, $tab_ids);
        $cf_res = pg_cf_reconcile_page_form($cf_page_id, $user['id'], $cf_set);
        if (!empty($cf_res['addresses'])) {
            $warnings[] = lang(array(
                'string' => '{var:1} record{suffix:1} of the form now open at their own address.',
                'vars'   => array((int)$cf_res['addresses']),
                'suffix' => array((int)$cf_res['addresses'] === 1 ? '' : 's'),
            ));
        }
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
            WHERE page_style = '$style_id' AND " . pg_page_visual_design_sql());
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
    //
    // Within a time budget: scoring renders the page, and a save that wrote
    // dozens of pages at once (a design's first save, an import) spent most
    // of its seconds here. The pages past the budget go into the analysis
    // queue without a score; the nightly job and the Pages screen's button
    // work through it.
    $seo_ids = array();
    foreach ($written as $wid) { if ($wid > 0) $seo_ids[] = $wid; }
    if (count($seo_ids) > 0) {
        require_once(dirname(__FILE__) . '/../seo.php');
        if (pg_seo_schema_ready()) {
            require_once(dirname(__FILE__) . '/../seo_structure.php');
            $seo_started = microtime(true);
            $seo_queued  = array();
            foreach ($seo_ids as $sid) {
                if ((microtime(true) - $seo_started) > 2) { $seo_queued[] = (int)$sid; continue; }
                try { pg_seo_analyze_record('page', $sid); } catch (Throwable $t) { /* the score is advisory; the save is not */ }
            }
            if ($seo_queued && pg_seo_structure_schema_ready()) {
                db("UPDATE page
                    SET seo_struct_score = NULL, seo_struct_current = 0, seo_analysis_current = 0
                    WHERE page_id IN (" . implode(',', $seo_queued) . ")");
            }
            try { pg_seo_recalculate('page', $seo_ids); } catch (Throwable $t) {}
        }
    }

    $notice = lang('The style has been saved.');
    foreach ($warnings as $w) $liveform->add_notice($w);
    foreach ($notes as $note) $liveform->add_notice($note);

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
                // A page left as stored keeps the form the editor holds.
                if ($pid <= 0 || !in_array($pid, $written, true)) continue;
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
            'notes'      => $notes,
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
