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
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
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
 *   start_variables null, or the start's variables (look, model, css) in add mode
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
    // The start questions' (or the Figma import's) variables of a new
    // design: the model in the hidden input, its stylesheet tried at once.
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/design_variables.php');
    $sd_start_vars = pg_design_variables_start_editor(isset($ctx['start_variables']) ? $ctx['start_variables'] : null);

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
            'ai_fields'             => isset($p['ai_fields']) && is_array($p['ai_fields']) ? $p['ai_fields'] : array(),
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
            // A page of a classic style just opened (?classic=1): its tree is
            // not saved yet, so the tab starts unsaved and Save writes it.
            'converted'             => !empty($p['converted']) ? 1 : 0,
            // The stored tree this tab starts from, sent back with Save: a
            // page somebody else wrote in the meantime is not overwritten
            // (pg_page_base_is_stale()). A converted page's tree is not the
            // stored one, so its base is read from the row.
            'baseHash'              => pg_page_base_hash(!empty($p['converted'])
                                           ? pg_page_tree_json((int)$p['page_id']) : (string)$p['tree_json']),
        );
    }
    if (empty($js_pages)) {
        // Add mode, or a design that somehow lost its pages: open one blank
        // tab so the operator has somewhere to work. It is created on save.
        // A page the editor creates starts as a draft (Save keeps it off the
        // site, Publish puts it on) where drafts exist and the operator
        // decides them. A classic style with no page of its own
        // (?classic=1) starts the tab with the tree placing its frame, so
        // the style's content is on the canvas and in the tree.
        $blank_tree = null;
        if (!empty($ctx['classic_frame_tree'])) {
            $blank_tree = pg_designer_tree_decode((string)$ctx['classic_frame_tree']);
        }
        // A new e-mail design (add_system_style.php?start=email): its one
        // tab starts with the mail frame, in the e-mail designs folder.
        $js_pages[] = !empty($ctx['email_folder_id'])
            ? pg_email_design_new_page((int)$ctx['email_folder_id'], pg_email_design_free_page_name(lang('E-mail design')), isset($ctx['email_tree']) ? $ctx['email_tree'] : null)
            : pg_designer_blank_page(
                ($access === PG_DESIGNER_ACCESS_FULL && pg_page_draft_ready()) ? 1 : 0,
                $blank_tree
            );
        $active = 0;
    }
    // One shared component opened on its own (edit_system_style.php
    // ?component=): its one tab places the component and is never saved as
    // a page; Save writes the component (designer_access.php,
    // pg_designer_component_entry()).
    $sd_component = (isset($ctx['component']) && is_array($ctx['component'])) ? $ctx['component'] : null;
    if ($sd_component) {
        $js_pages = array(array_merge($js_pages[0], array(
            'key'        => 'c' . (int)$sd_component['id'],
            'page_name'  => (string)$sd_component['name'],
            'page_draft' => 0,
            'tree'       => array('type' => 'root', 'props' => new stdClass(), 'children' => array(
                array('type' => 'shared_ref', 'props' => array('sharedId' => (int)$sd_component['id'], 'sharedName' => (string)$sd_component['name']), 'children' => array()),
            )),
            'access'     => 'edit',
        )));
        $active = 0;
    }
    $active_key = 'p' . $active;
    $found_active = false;
    foreach ($js_pages as $jp) { if ($jp['key'] === $active_key) { $found_active = true; break; } }
    if (!$found_active) $active_key = $js_pages[0]['key'];

    // The Workspace tasks of a saved page: the record drawer of the other
    // screens (ws_record_drawer_assets()), opened from the tab menu with the
    // task screens' own rights. Not for a shared component opened alone.
    $sd_ws_tasks = '';
    if (!$sd_component && defined('WORKSPACE_ENABLED') && WORKSPACE_ENABLED) {
        require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
        $sd_ws_tasks = ws_record_drawer_assets($user, 'page');
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/designer_ai.php');
    $sd_ai = pg_design_ai_editor_config($user);
    // The "Clicks" layer's settings, null for whoever may not open Visitors.
    $sd_clicks = pg_page_clicks_designer_config($user, pg_page_clicks_switch_on(), pg_page_clicks_ready(), OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY);

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/prototype_links.php');
    $sd_proto_share = pg_page_draft_ready() && pg_proto_links_ready();
    // "Preview on a phone" (the part 'phone') goes to those who make share
    // links: administrators and designers.
    $sd_phone = ((int) $user['role'] <= 1) && $sd_proto_share;

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/layouts.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/legacy_design_theme.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/listen.php');

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
        // What the request left in the liveform (errors, warnings, notices):
        // shown as the editor's own toasts once the canvas is up, never as
        // the panel's alert boxes, which pushed the full-height editor down.
        'messages'      => pg_designer_screen_messages($liveform->get_errors(), $liveform->get_warning_messages(), $liveform->get_notices()),
        'styleId'       => $style_id,
        'styleName'     => (string)$style['name'],
        'activeKey'     => $active_key,
        'pages'         => $js_pages,
        // A shared component opened on its own: Save writes only it.
        'componentOnly' => $sd_component ? array('id' => (int)$sd_component['id'], 'name' => (string)$sd_component['name']) : null,
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
        // The tab menu's "Workspace tasks" item, on saved pages; false when
        // the Workspace is off or the person is not on the team.
        'workspaceTasks' => ($sd_ws_tasks !== ''),
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
        // Of those, the custom form pages, whose layout converts into a
        // custom_form widget placed beside the region (layout_code convert).
        'layoutFormPages' => pg_layout_form_page_ids($layout_pages),
        // Of those, the pages whose type has a system widget, by page id:
        // its kind and the settings carried from the page's type settings,
        // for the offer to put that widget beside the region.
        'layoutWidgets' => pg_layout_widget_offers($layout_pages),
        // Every page of the design whose classic type has a system widget,
        // custom layout or not, in the same shape: "Convert to design
        // elements" on its own system region puts that widget in its place.
        'classicWidgets' => ((int)$user['role'] <= 1) ? pg_classic_widget_offers($tab_page_ids) : array(),
        // Of those, the pages whose classic type has no widget: a muted line
        // in the System region's panel instead of a converter.
        'classicNoWidget' => ((int)$user['role'] <= 1) ? pg_classic_no_widget_page_ids($tab_page_ids) : array(),
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
            // Whether the product page's Checkout Options table has a row
            // to show; the site leaves the tab out otherwise.
            'payment_methods'      => function_exists('pg_civ_payment_method_rows') && pg_civ_payment_method_rows(_pg_civ_payment_settings(false)) !== array(),
            // The most installments the checkout offers (the iyzico
            // gateway's setting, _eo_render_installment_box()), 0 when off.
            'installments'         => (defined('ECOMMERCE_PAYMENT_GATEWAY') && ECOMMERCE_PAYMENT_GATEWAY === 'Iyzipay'
                && defined('ECOMMERCE_IYZIPAY_INSTALLMENT') && (int)ECOMMERCE_IYZIPAY_INSTALLMENT >= 2) ? (int)ECOMMERCE_IYZIPAY_INSTALLMENT : 0,
        ),
        // The site's words the member widgets print, so the canvas draws
        // them as the page does: the site's name, the consent and member
        // number labels and the strong password rules block.
        'siteWords'     => array(
            'site_name'       => defined('ORGANIZATION_NAME') ? (string)ORGANIZATION_NAME : '',
            'opt_in_label'    => defined('OPT_IN_LABEL') ? (string)OPT_IN_LABEL : '',
            'member_id_label' => defined('MEMBER_ID_LABEL') ? (string)MEMBER_ID_LABEL : '',
            'password_rules'  => function_exists('get_strong_password_requirements')
                ? '<div class="pg-password-rules small text-muted">' . get_strong_password_requirements() . '</div>' : '',
            // The sign-in buttons the member widgets print, drawn on the
            // canvas in their sections (pg_signin_canvas_sample()).
            'signin_buttons'  => function_exists('pg_signin_canvas_sample') ? pg_signin_canvas_sample() : '',
            // The product page's Checkout Options rows (method, description,
            // fee) the store's payment settings give, as the site fills them
            // (_pg_civ_fill_payment_methods()).
            'payment_method_rows' => function_exists('pg_civ_payment_method_rows')
                ? pg_civ_payment_method_rows(_pg_civ_payment_settings(false)) : array(),
            // The booking widget's words, closing words and sample times,
            // drawn on the canvas in the parts its script fills
            // (pg_booking_canvas_sample()).
            'booking' => (function_exists('pg_booking_canvas_sample') && function_exists('pg_booking_texts'))
                ? pg_booking_canvas_sample(pg_booking_widget_policy(), pg_booking_texts()) : null,
        ),
        // The editor's UI text, resolved through lang() for the site's
        // language. Empty when the language is English: the JS keys ARE the
        // English text and _sdT() falls back to the key.
        'i18n'          => pg_designer_i18n_map(),
        'hiddenPages'   => $hidden_pages,
        // The assistant panel (assets/js/designer_ai.js): designers only.
        'ai'            => $sd_ai,
        // The "Clicks" layer (pg_page_clicks_designer_config()); null hides
        // its toggle.
        'clicks'        => $sd_clicks,
        // The Sketch tool's Pinegrap AI actions (includes/fn/sketch_ai.php):
        // designers, on a site where Pinegrap AI writes the drafts.
        'sketchAi'      => $access === PG_DESIGNER_ACCESS_FULL && pg_ai_write_engine() === 'pinegrap',
        // The editor's parts not needed to open a page, run on first use:
        // their file addresses by name, for every part whose file's 'when'
        // holds (includes/designer_parts/, pg_designer_parts_for()).
        'parts'         => (object) pg_designer_part_urls(pg_designer_parts_for(array(
            'designer'     => $access === PG_DESIGNER_ACCESS_FULL,
            'ai'           => !empty($sd_ai['enabled']),
            'comments'     => $access === PG_DESIGNER_ACCESS_FULL && pg_proto_comments_ready(),
            'phone'        => $sd_phone,
            'publishcheck' => $access === PG_DESIGNER_ACCESS_FULL && pg_page_draft_ready() && !$sd_component,
            'clicks'       => !empty($sd_clicks),
        ))),
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
        // Page experiments (2026.11.0, includes/fn/experiments_sheet.php):
        // the sheet's address and each tab page's state, for the top bar's
        // chip and the held variant's Publish.
        'experiments'   => pg_experiment_editor_config($tab_page_ids, $user),
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
        // The Canva start: the Prototype view opens with the Sketch switch on.
        'autoSketch'    => !empty($ctx['auto_sketch']),
        'autoPasteHtml' => !empty($ctx['auto_paste']),
        // ?start=document: the page is built from the rows the New design
        // window left in this tab's sessionStorage (style_designer_document.js).
        'startDocument' => !empty($ctx['start_document']),
        // What the design is built on (pg_design_frameworks()). The editor
        // loads the framework's files into the canvas and offers the
        // Bootstrap palette only when the framework is Bootstrap.
        'framework'     => $sd_framework['key'],
        // A design moved from a classic style loads the theme its classic
        // head printed (pg_legacy_design_classic_theme()) while no Theme
        // file is chosen: the canvas loads it and the hint under "Theme
        // file" names it. Null for any other design.
        'themeFile'     => pg_legacy_design_theme_js($style_id, $sd_framework['key'], isset($style['style_custom_css']) ? $style['style_custom_css'] : ''),
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
        // Add mode from the Figma start: the pages, shared components and
        // name its import made (pg_figma_import_ready()), opened as tabs.
        'figma'         => !empty($ctx['figma']) ? $ctx['figma'] : null,
        // What to do once open, from the design chooser's link: a new page,
        // a page from another design or a copy of one (pg_designer_start_action()).
        'startAction'   => !empty($ctx['start_action']) ? $ctx['start_action'] : null,
        // Looks and colour palettes (includes/fn/design_themes.php): every
        // choice with its stylesheet, the saved custom ones included.
        'themes'        => pg_design_theme_catalog() + array('variables' => pg_design_variables_url($style_id), 'variablesTry' => $sd_start_vars['try']),
        // The design's stored easing curves (its own variables of kind
        // curve), offered in every Easing list.
        'curves'        => pg_design_variables_curves(pg_design_variables_stored($style_id)),
        // Everything the field panel offers, resolved once here rather than
        // duplicated in JavaScript — a second copy of "which contact fields
        // exist" is the copy that drifts.
        'formMeta'      => array(
            'rss'      => function_exists('pg_cf_rss_fields')     ? pg_cf_rss_fields()     : array(),
            'contact'  => function_exists('pg_cf_contact_fields') ? pg_cf_contact_fields() : array(),
            'folders'  => function_exists('pg_cf_folder_options') ? pg_cf_folder_options() : array(),
            // The registration widget files its new member's contact into one
            // of these; the address book's own list, id, name and kind (a
            // group filled by rules is named so in the audience conditions).
            'contactGroups' => pg_audience_group_options((array)db_items("SELECT id, name"
                . ((function_exists('pg_audience_ready') && pg_audience_ready()) ? ', kind' : '')
                . " FROM contact_groups ORDER BY name")),
            // The form's double opt-in switch exists once the 11.0.27 column
            // is there; until then pg_cf_write_form_settings() drops it.
            'doubleOptIn' => function_exists('pg_fs_ready') && pg_fs_ready(),
            // The experiments an audience condition may read the version
            // of, with their variant keys (page_audience.php).
            'experiments' => function_exists('pg_audience_experiment_options') ? pg_audience_experiment_options() : array(),
            // The form's Payment rows exist once the 11.0.158 columns are
            // there and the store is on; the amounts are minor units.
            'payment' => defined('ECOMMERCE') && (ECOMMERCE == true) && function_exists('pg_form_payment_ready') && pg_form_payment_ready(),
            'currencySymbol' => defined('BASE_CURRENCY_SYMBOL') ? html_entity_decode((string)BASE_CURRENCY_SYMBOL, ENT_QUOTES | ENT_HTML5, 'UTF-8') : '',
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
            'title'        => lang('Page Design Studio'),
            'extra classes'=> 'design',
            'icon'         => 'design',
            'heading'      => lang('Page Design Studio'),
            'heading_description' => lang('The pages of the design are the tabs; drop blocks from the palette on the left, edit their options on the right.'),
            'hide_menu'    => true,
            // The main menu as the phone's drawer at every width, opened by
            // the toolbar's first button (#sd-left-toggle): the designer
            // keeps its whole width.
            'menu_drawer'  => true,
            // The head's rel="help" link names the guide's designer topic.
            'agent_guide'  => 'designer',
            // The shared curtain hides itself when the document is ready.
            // Here the document being ready is the START of the work: panels
            // are built, the canvas iframe is written, the tree is laid out.
            // So we hold it and take it down when the canvas has painted.
            'head'         => '<script>window.pgPreloaderHold = true;</script>',
        )) . '
        <link rel="stylesheet" href="assets/fonts/bootstrap-icons/bootstrap-icons.min.css">
        <link rel="stylesheet" href="assets/css/style_designer.css?v=' . pg_designer_asset_stamp('assets/css/style_designer.css') . '">
        <main id="content" style="padding:0; max-width:100%;">
            ' . ((!empty($ctx['classic_folder_pages']) && function_exists('pg_classic_style_folder_pages_field')) ? pg_classic_style_folder_pages_field($ctx['classic_folder_pages']) : '') . '
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
                <!-- The colour mode (Settings, Theme): light, dark, auto or empty. -->
                <input type="hidden" name="style_color_mode" id="sd-style-color-mode" value="' . h(isset($style['color_mode']) ? pg_design_color_mode_key($style['color_mode']) : '') . '">
                <!-- The variables model the Variables tab changed (JSON); empty: unchanged. -->
                <input type="hidden" name="style_variables" id="sd-style-variables" value="' . h($sd_start_vars['input']) . '">

                <div class="sd-wrapper">
                    <!-- One toolbar. Left: what you do to pages (add, settings,
                         delete). Middle: the tabs, starting right after, scrolling
                         in their own strip when there are many. Right: who else is
                         here, undo / redo, the assistant, preview, publish. The page name is not here — it is edited on the tab
                         itself (double-click) or in Page Settings; duplicating a page
                         is on the tab menu (⋮ / right-click), where the page it
                         copies is the one under the cursor. No back button: Ctrl+G,
                         the header link and the browser back button all go through
                         the unsaved-changes guard. -->
                    <div class="sd-toolbar sd-toolbar-tabs">
                        <!-- Opens and shuts the panel main menu, the drawer
                             output_header() prints for menu_drawer; outside the
                             page group so the component editor and read-only
                             mode keep it. Wired by backend.src.js
                             (data-pg-menu-drawer-toggle). -->
                        <button type="button" id="sd-left-toggle" class="sd-icon-btn sd-left-toggle no-popover" data-pg-menu-drawer-toggle aria-controls="menu" aria-expanded="false" title="' . h(lang('Show menu')) . '" aria-label="' . h(lang('Show menu')) . '" data-label-show="' . h(lang('Show menu')) . '" data-label-hide="' . h(lang('Hide menu')) . '"><span class="bi bi-list pg-mt-shut" aria-hidden="true"></span><span class="bi bi-x-lg pg-mt-open" aria-hidden="true"></span></button>
                        <div class="sd-toolbar-group sd-toolbar-pages"' . ($sd_component ? ' hidden' : '') . '>
                            <!-- The menu of the design: its name opens
                                 Settings and, under a divider, Delete (the
                                 delete item comes ready from the caller). The
                                 label follows the name field of Settings. -->
                            <div class="pg-menu-wrap sd-design-menu">
                                <button type="button" class="sd-design-menu-btn" id="sd-design-menu-btn" data-bs-toggle="menu" aria-expanded="false" aria-haspopup="menu" title="' . h(lang('Design menu')) . '" data-sd-untitled="' . h(lang('New design')) . '">
                                    <span class="bi bi-palette2 sd-design-menu-icon" aria-hidden="true"></span><span class="sd-design-menu-name" id="sd-design-menu-name">' . h(isset($style['name']) && trim((string)$style['name']) !== '' ? (string)$style['name'] : lang('New design')) . '</span><span class="bi bi-chevron-down sd-design-menu-caret" aria-hidden="true"></span>
                                </button>
                                <ul data-bs-theme="dark" class="menu">
                                    <li class="sd-design-menu-head" id="sd-design-menu-head" aria-hidden="true"></li>
                                    <li><button type="button" class="menu-item" id="sd-design-settings" data-bs-toggle="dialog" data-bs-target="#styleSettingsModal"><span class="bi bi-gear me-3" aria-hidden="true"></span>' . lang('Settings') . '</button></li>
                                    ' . ($mode === 'edit' ? $ctx['delete_button'] : '') . '
                                </ul>
                            </div>
                            <div class="pg-menu-wrap sd-tabs-add">
                                <button type="button" class="sd-tabs-add-btn" data-bs-toggle="menu" aria-expanded="false" title="' . lang('Add Page') . '">
                                    <span class="bi bi-plus-lg"></span><span class="sd-tabs-add-txt">' . lang('Add Page') . '</span>
                                </button>
                                <ul data-bs-theme="dark" class="menu">
                                    <li><button type="button" class="menu-item" id="sd-tab-new-page"><span class="bi bi-file-earmark-plus me-3"></span>' . lang('New Page') . '</button></li>
                                    <li><button type="button" class="menu-item" id="sd-tab-pick-page"><span class="bi bi-files me-3"></span>' . lang('Select Page') . '</button></li>
                                    <li><hr class="menu-divider"></li>
                                    <li><button type="button" class="menu-item" id="sd-tab-import-html"><span class="bi bi-file-earmark-code me-3"></span>' . lang('Import HTML / ZIP') . '</button></li>
                                    <li><button type="button" class="menu-item" id="sd-tab-paste-html"><span class="bi bi-clipboard-plus me-3"></span>' . lang('Paste HTML content') . '</button></li>
                                </ul>
                            </div>
                        </div>
                        <div class="sd-tabs-scroll" id="sd-tabs-list" role="tablist"></div>
                        <!-- The Pages button: opens the Pages window (the
                             part pages, also Ctrl+P). Shown while the strip
                             cannot show every page (_pgTabsAllSync()). -->
                        <div class="sd-tabs-all" id="sd-tabs-all" hidden>
                            <button type="button" class="sd-icon-btn sd-tabs-all-btn" id="sd-tabs-all-btn" aria-haspopup="dialog" aria-expanded="false" title="' . h(lang('Pages')) . '" aria-label="' . h(lang('Pages')) . '"><span class="bi bi-list-ul" aria-hidden="true"></span><span class="sd-tabs-all-count" id="sd-tabs-all-count"></span></button>
                        </div>
                        <div class="sd-toolbar-group sd-toolbar-actions">
                            <!-- Who else has this design open. Empty and
                                 invisible when nobody else does, so a single
                                 operator never sees a feature they are not
                                 using. -->
                            <div class="sd-presence" id="sd-presence"></div>
                            <!-- The page on screen in an experiment: filled by
                                 _pgExperimentSync(), empty otherwise. -->
                            <a class="badge d-none text-decoration-none" id="sd-experiment-chip" href="#" target="_blank" rel="noopener"></a>
                            <!-- Undo and Redo between two lines: what is done
                                 with the page goes right of the first one. -->
                            <span class="sd-bar-line" aria-hidden="true"></span>
                            <button type="button" id="sd-main-undo" class="sd-icon-btn sd-history-btn" onclick="StyleDesigner.undo()" title="' . h(lang('Undo')) . ' (Ctrl+Z)" aria-label="' . h(lang('Undo')) . '" disabled><span class="bi bi-arrow-counterclockwise" aria-hidden="true"></span></button>
                            <button type="button" id="sd-main-redo" class="sd-icon-btn sd-history-btn" onclick="StyleDesigner.redo()" title="' . h(lang('Redo')) . ' (Ctrl+Y)" aria-label="' . h(lang('Redo')) . '" disabled><span class="bi bi-arrow-clockwise" aria-hidden="true"></span></button>
                            <span class="sd-bar-line" aria-hidden="true"></span>
                            ' . (!empty($sd_ai['enabled']) ? '<button type="button" id="sd-ai-btn" class="sd-icon-btn sd-ai-btn" title="' . h(lang('Ask the assistant')) . '" aria-expanded="false"><span class="bi bi-stars" aria-hidden="true"></span><span class="sd-ai-btn-txt">' . h(lang('Assistant')) . '</span><span class="sd-ai-btn-dot" aria-hidden="true"></span></button>' : '') . '
                            <!-- The Preview menu: the page outside the editor (a new
                                 window, the site as saved, the prototype). Its rows
                                 are drawn by _sdPreviewMenuBuild(). -->
                            <button type="button" id="sd-preview-menu-btn" class="sd-icon-btn sd-preview-btn" aria-haspopup="menu" aria-expanded="false" aria-controls="sd-preview-menu" title="' . h(lang('Preview')) . '"><span class="bi bi-window" aria-hidden="true"></span><span class="sd-preview-txt">' . h(lang('Preview')) . '</span><span class="bi bi-chevron-down sd-preview-caret" aria-hidden="true"></span></button>
                            <!-- The main action puts every page of the design on the
                                 site, so it says so. The label drops to the icon on
                                 narrow screens; the title still names it. -->
                            <button type="button" id="sd-ajax-save" name="submit_save" value="Save" class="sd-icon-btn sd-icon-btn-primary sd-publish-btn" title="' . ($sd_component ? lang('Save') : lang('Publish')) . '"><span class="bi ' . ($sd_component ? 'bi-floppy' : 'bi-rocket-takeoff') . '" aria-hidden="true"></span><span class="sd-publish-txt">' . ($sd_component ? lang('Save') : lang('Publish')) . '</span></button>
                            ' . (($access === PG_DESIGNER_ACCESS_FULL && pg_page_draft_ready() && !$sd_component) ? '<!-- A draft page has two actions: the main button saves it
                                 (it stays a draft), this one opens the Publish
                                 sheet. Hidden while the page on screen is on
                                 the site, where the main button opens it. -->
                            <button type="button" id="sd-publish-page" class="sd-icon-btn sd-icon-btn-primary sd-publish-btn d-none" title="' . h(lang('Choose the pages to put on the site.')) . '"><span class="bi bi-rocket-takeoff" aria-hidden="true"></span><span class="sd-publish-txt">' . lang('Publish') . '</span></button>' : '') . '
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
                ' . pg_designer_ai_alt_template($ctx) . '
                ' . pg_designer_pick_page_modal() . '
                ' . (($access === PG_DESIGNER_ACCESS_FULL && pg_page_draft_ready() && !$sd_component) ? pg_designer_publish_sheet() : '') . '
                ' . pg_designer_import_modal() . '
                ' . pg_designer_make_template_modal() . '
            </form>
        </main>
        ' . ($access === PG_DESIGNER_ACCESS_FULL ? pg_designer_target_modal() : '') . '
        ' . pg_image_editor_includes() . '
        ' . pg_editor_bridge_script() . '
        ' . $sd_ws_tasks . '
        <!-- The designer\'s data, and its translation map, are declared BEFORE
             the editor script. style_designer.min.js resolves _sdT() at module
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
                'Minimap'      => lang('Minimap'),
            )) . ';
            // The Listen block\'s labels in the page\'s language, as the
            // published page gets them (pg_listen_inject()); the Preview
            // writes them before pg_listen.js.
            window.sdListenLabels = ' . encode_json(pg_listen_labels()) . ';
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
        <!-- The search keyword chips (pgChips) and the date-and-time control of the page schedule and the filter rows. -->
        ' . get_date_picker_format() . pg_fields_script() . '
        <!-- For the Prototype view: the graph canvas core, the link resolver and the link
             collector: they only define their globals, so no defer. -->
        ' . pg_js_bundle_tags('assets/js/pg_graph_canvas.min.js', false) . pg_js_bundle_tags('assets/js/pg_link_resolve.min.js', false) . pg_js_bundle_tags('assets/js/pg_proto_hotspots.min.js', false) . '
        <script src="assets/js/pg_designer_publish.js?v=' . pg_designer_asset_stamp('assets/js/pg_designer_publish.js') . '"></script>
        ' . pg_js_bundle_tags('assets/js/style_designer.min.js', false) . '
        ' . pg_ai_write_assets() . '
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
                        StyleDesigner.publish({ button: _saveBtn });
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
        $rows[$i]['framework_legacy'] = !empty($fw['legacy']);
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

    // The state is told by its words and a mark of its own shape (a full,
    // half or empty circle), never by colour alone; only a live design's
    // mark wears the accent.
    $mark = function ($icon, $text) {
        return '<span class="sdh-state"><i class="bi ' . $icon . '" aria-hidden="true"></i>' . h($text) . '</span>';
    };
    if ($s['state'] === 'live') {
        $badge = $mark('bi-circle-fill', lang('Live on the site'));
        $note  = h(lang('All pages are on the site.'));
    } elseif ($s['state'] === 'draft') {
        $badge = $mark('bi-circle', lang('Draft'));
        $note  = ($c['home'] > 0) ? h(lang('The home page stays on the site.')) : h(lang('Visitors cannot open the pages; administrators can.'));
    } elseif ($s['state'] === 'mixed') {
        $badge = $mark('bi-circle-half', lang('Partly on the site'));
        $note  = h(lang(array('string' => '{var:1} of {var:2} pages are drafts', 'vars' => array($c['drafts'], $c['pages']))));
    } else {
        $badge = $mark('bi-dash-circle-dotted', lang('Empty'));
        $note  = h(lang('No pages yet. Open it to add one.'));
    }

    // Beside the state: pages that change state at a set time, and when the
    // next change comes.
    $scheduled = isset($d['scheduled_count']) ? (int)$d['scheduled_count'] : 0;
    if ($scheduled > 0 && $s['state'] !== 'empty') {
        $badge .= '<span class="sdh-state sdh-scheduled"><i class="bi bi-clock" aria-hidden="true"></i>' . h(lang('On a schedule')) . '</span>';
        $next  = isset($d['scheduled_next']) ? (int)$d['scheduled_next'] : 0;
        $note .= ' · ' . h(($next > 0)
            ? lang(array('string' => '{var:1} scheduled, next change {var:2}', 'vars' => array($scheduled, pg_page_schedule_label($next))))
            : lang(array('string' => '{var:1} scheduled', 'vars' => array($scheduled))));
    }

    return '<div class="sdh-status" data-state="' . h($s['state']) . '" data-pages="' . $c['pages'] . '" data-drafts="' . $c['drafts'] . '" data-home="' . $c['home'] . '" data-offable="' . (int)$s['offable'] . '" data-scheduled="' . $scheduled . '">'
         . $badge . '<span class="sdh-status-note">' . $note . '</span></div>';
}

/**
 * A design's picture: the page the card's Edit opens ($open, a
 * pg_designer_list_pages() row), as a first-time visitor sees it
 * (design_preview.php?page_id=, a live picture). Until it arrives, or when
 * it cannot be drawn, the drawing shows: its template's in the design's own
 * look and colours, else the outline of a page with what it is built on in
 * the middle. A design with no page to open shows the outline.
 */
function pg_designer_list_thumb($d, $open = null)
{
    $src = pg_designer_design_picture_src($open);
    if ($src === '') return pg_designer_list_drawing($d, false);
    return pg_design_live_picture($src, array('fallback' => pg_designer_list_drawing($d, true)));
}

/**
 * The address of a design's picture: its page drawn by design_preview.php;
 * '' when there is no page to open. Pure.
 */
function pg_designer_design_picture_src($open)
{
    $id = (is_array($open) && isset($open['id'])) ? (int)$open['id'] : 0;
    if ($id <= 0) return '';
    $base = (defined('PATH') && defined('SOFTWARE_DIRECTORY')) ? PATH . SOFTWARE_DIRECTORY . '/' : '';
    return $base . 'design_preview.php?page_id=' . $id;
}

/**
 * A design's drawing: with $template, its template's drawing in the
 * design's own look and colours when it has one; otherwise the outline of a
 * page with what it is built on in the middle.
 */
function pg_designer_list_drawing($d, $template = true)
{
    $tpl = ($template && !empty($d['template'])) ? pg_design_template($d['template']) : null;
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
 * The three starting points a site with no design is shown: Blank,
 * Template and "Bring your own", each opening the New design window
 * (pg_designer_start_window()) on its start. "Bring your own" opens it on the
 * first start of the window's import groups. Pure.
 *
 * @param array $kinds pg_designer_start_kinds()
 * @return array list of array('start', 'icon', 'title', 'line', 'disabled')
 */
function pg_designer_home_first_starts($kinds)
{
    $keys  = array();
    $bring = '';
    foreach ((array)$kinds as $k) {
        $keys[$k['key']] = !empty($k['disabled']);
        if ($bring === '' && $k['group'] !== 'new' && empty($k['disabled'])) $bring = $k['key'];
    }
    $no_tpl = !isset($keys['template']) || $keys['template'];
    return array(
        array('start' => 'blank', 'icon' => pg_designer_start_icon('blank'), 'title' => lang('Blank'),
              'line' => lang('An empty page; add sections from the palette.'), 'disabled' => false),
        array('start' => 'template', 'icon' => pg_designer_start_icon('template'), 'title' => lang('Template'),
              'line' => $no_tpl ? lang('There are no templates.') : lang('A ready-made design to change as you like.'), 'disabled' => $no_tpl),
        array('start' => $bring, 'icon' => 'bi-box-arrow-in-down', 'title' => lang('Bring your own'),
              'line' => isset($keys['figma']) ? lang('Figma, Canva, a document, a ZIP file or pasted markup.') : lang('Canva, a document, a ZIP file or pasted markup.'),
              'disabled' => ($bring === '')),
    );
}

/**
 * The middle of the home while the site has no design: what a design is,
 * the three starting points (pg_designer_home_first_starts()) and the three
 * steps as one grey line. Never shown once a design exists.
 *
 * @param array $kinds pg_designer_start_kinds()
 * @return string
 */
function pg_designer_home_first_visit($kinds)
{
    $cards = '';
    foreach (pg_designer_home_first_starts($kinds) as $s) {
        $cards .= '<button type="button" class="sdh-first-start"' . ($s['disabled'] ? ' disabled' : '')
                . ' data-bs-toggle="dialog" data-bs-target="#sdStartModal" data-sd-start="' . h($s['start']) . '">'
                .     '<span class="pg-icon-tile sdh-first-tile"><i class="bi ' . h($s['icon']) . '" aria-hidden="true"></i></span>'
                .     '<span class="sdh-first-text"><strong>' . h($s['title']) . '</strong><small>' . h($s['line']) . '</small></span>'
                . '</button>';
    }
    $step = function ($n, $text) {
        return '<span><b>' . $n . '</b> ' . h($text) . '</span>';
    };
    $chevron = '<i class="bi bi-chevron-right" aria-hidden="true"></i>';
    return '<div class="sdh-first">'
         .     '<span class="pg-icon-tile pg-icon-tile-lg sdh-first-mark"><i class="bi bi-layout-text-window-reverse" aria-hidden="true"></i></span>'
         .     '<h3>' . h(lang('Start your first design')) . '</h3>'
         .     '<p>' . h(lang('A design is a set of pages that share stylesheets, scripts, fonts and a theme.')) . '</p>'
         .     '<div class="sdh-first-starts">' . $cards . '</div>'
         .     '<p class="sdh-first-steps">' . $step(1, lang('Start a design')) . $chevron . $step(2, lang('Build its pages')) . $chevron . $step(3, lang('Put it on the site')) . '</p>'
         . '</div>';
}

/**
 * The home's head row: "Designs" with the count and the sentence of what a
 * design is, then the search (from seven designs), the shared components
 * button and "New design". Before the first design the sentence stands in
 * the middle (pg_designer_home_first_visit()), so the head has none.
 *
 * @param int    $count       the designs listed
 * @param string $comp_button pg_designer_components_card()'s button
 * @param bool   $can_create  a designer: "New design" is offered
 * @return string
 */
function pg_designer_home_head($count, $comp_button, $can_create)
{
    $count  = (int)$count;
    $search = ($count > 6)
        ? '<label class="pg-input pg-input-sm sdh-search"><i class="bi bi-search" aria-hidden="true"></i>'
          . '<input type="search" id="sdh-filter" placeholder="' . h(lang('Find a design')) . '" aria-label="' . h(lang('Find a design')) . '" autocomplete="off"></label>'
        : '';
    $new = $can_create
        ? '<button type="button" class="pg-button pg-button-fill" data-bs-toggle="dialog" data-bs-target="#sdStartModal" data-sd-start="blank" data-sd-narrow="list"><i class="bi bi-plus-lg" aria-hidden="true"></i>' . h(lang('New design')) . '</button>'
        : '';
    return '<div class="sdh-head">'
         .     '<div class="sdh-head-text">'
         .         '<h2 id="sdh-designs-title">' . h(lang('Designs')) . ($count > 0 ? '<span class="sdh-count">' . $count . '</span>' : '') . '</h2>'
         .         ($count > 0 ? '<p>' . h(lang('A design is a set of pages that share stylesheets, scripts, fonts and a theme.')) . '</p>' : '')
         .     '</div>'
         .     '<div class="sdh-head-tools">' . $search . $comp_button . $new . '</div>'
         . '</div>';
}

/**
 * The head's button to the shared components drawer: the words and the count
 * in a label span, so narrow the button is its icon and the words its
 * tooltip (designer_home.css). Pure.
 *
 * @param int $total the shared components
 * @return string
 */
function pg_designer_home_comp_button($total)
{
    $label = h(lang('Shared components and system widgets'));
    return '<button type="button" class="pg-button pg-button-ghost sdh-comp" id="sd-comp-open" data-bs-toggle="drawer" data-bs-target="#sd-comp-offcanvas" aria-controls="sd-comp-offcanvas" title="' . $label . '">'
         . '<i class="bi bi-puzzle" aria-hidden="true"></i><span class="pg-btn-label sdh-comp-label">' . $label . ' <span class="sdh-count" id="sd-comp-total">' . (int)$total . '</span></span></button>';
}

/**
 * A design's pages as chips that open the page in the editor, in one row.
 * The first $limit are printed shown and the rest fold behind a "+n more"
 * chip; assets/js/designer_home.js then shows as many as fit the row. The house is the
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
    return '<div class="sdh-pages' . ($more > 0 ? ' is-folded' : '') . ' is-row">' . $out . '</div>';
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
    $more = '<button type="button" class="pg-icon-button pg-icon-button-sm sdh-more no-popover" data-bs-toggle="tooltip" title="' . h(lang('More actions')) . '" aria-label="' . h(lang('More actions')) . '" aria-haspopup="menu" aria-expanded="false"><i class="bi bi-three-dots" aria-hidden="true"></i></button>';
    // The picture opens the editor too; the name link is the one announced.
    $picture = '<a class="sdh-browser" href="' . h($edit_url) . '" tabindex="-1" aria-hidden="true">'
             .     '<span class="sdh-browser-bar"><i></i><i></i><i></i><span class="sdh-browser-url">' . h($address) . '</span></span>'
             .     '<span class="sdh-browser-view">' . pg_designer_list_thumb($d, $open) . '</span>'
             . '</a>';
    // A moved classic style says so beside its name (the legacy framework
    // key); the featured card also lists it under Framework.
    $title = '<a href="' . h($edit_url) . '"><span class="sdh-name-text">' . $name . '</span></a>'
           . (!empty($d['framework_legacy']) ? '<span class="badge theme-secondary sdh-legacy">' . h(lang('Legacy')) . '</span>' : '');
    // Markup of its own (a <time> element), so it is not escaped again.
    $when  = get_relative_time(array('timestamp' => (int)$d['last_modified_timestamp']));

    if (!$featured) {
        // A design with no page has no picture: it says so in its place,
        // never as an empty frame.
        if ($count === 0) {
            $picture = '<a class="sdh-nopic" href="' . h($edit_url) . '" tabindex="-1" aria-hidden="true"><i class="bi bi-file-earmark"></i><span>' . h(lang('No pages yet')) . '</span></a>';
        }
        // One line under the name: the state's mark and words, the page
        // count, the time of the last change at the right.
        $pages_text = ($count > 0) ? '<span class="sdh-tile-pages">· ' . h(lang(array('string' => '{var:1} page{suffix:1}', 'vars' => $count, 'suffix' => ($count === 1 ? '' : 's')))) . '</span>' : '';
        return '<article class="sdh-card sdh-tile" tabindex="0" aria-label="' . $name . '"' . $attrs . '>'
             .     $picture
             .     '<div class="sdh-tile-body">'
             .         '<div class="sdh-title-row"><h3 class="sdh-name">' . $title . '</h3>' . $more . '</div>'
             .         '<div class="sdh-tile-line">' . pg_designer_list_status_cell($d) . $pages_text . '<span class="sdh-tile-time">' . $when . '</span></div>'
             .     '</div>'
             . '</article>';
    }

    $who = ($d['last_modified_username'] !== '' && $d['last_modified_username'] !== null) ? (string)$d['last_modified_username'] : '[' . lang('Unknown') . ']';
    $tpl = !empty($d['template']) ? pg_design_template($d['template']) : null;
    $none  = '<span class="fg-secondary">—</span>';
    $theme = pg_designer_list_theme_label($d);
    // Four facts in one row, a label over each value; who changed it last
    // is the time's tooltip.
    $facts = array(
        array(lang('Framework'), h($d['framework_label']), ''),
        array(lang('Template'), $tpl ? h(isset($tpl['name']) ? (string)$tpl['name'] : (string)$d['template']) : $none, ''),
        array(lang('Theme'), ($theme !== '') ? $theme : $none, ''),
        array(lang('Last modified'), $when, lang(array('string' => '{var:1}, by {var:2}', 'vars' => array(trim(strip_tags($when)), $who)))),
    );
    $facts_html = '';
    foreach ($facts as $f) {
        $facts_html .= '<div class="sdh-fact"' . ($f[2] !== '' ? ' title="' . h($f[2]) . '"' : '') . '><small>' . h($f[0]) . '</small><b>' . $f[1] . '</b></div>';
    }

    // Four buttons at most; everything else is in the menu. "Put on the
    // site" shows while there are drafts (designer_home.js hides it once
    // there are none); taking a live design off the site is left to the
    // menu, where it is not one stray click away.
    $buttons = '<a class="pg-button pg-button-sm pg-button-fill" href="' . h($edit_url) . '"><i class="bi bi-pencil" aria-hidden="true"></i>' . h(lang('Edit')) . '</a>';
    if (!empty($ctx['can_change'])) {
        $buttons .= '<a class="pg-button pg-button-sm pg-button-quiet" href="' . h($add_url) . '"><i class="bi bi-plus-lg" aria-hidden="true"></i>' . h(lang('Add page')) . '</a>';
    }
    // A link that leaves the panel: its arrow after the words.
    if ($view_url !== '') {
        $buttons .= '<a class="pg-button pg-button-sm pg-button-ghost" href="' . h($view_url) . '" target="_blank" rel="noopener">' . h(lang('View on site')) . '<i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a>';
    }
    if (!empty($ctx['can_draft'])) {
        $buttons .= '<button type="button" class="pg-button pg-button-sm pg-button-quiet' . ((int)$d['draft_count'] > 0 ? '' : ' d-none') . '" data-act="on"><i class="bi bi-cloud-check" aria-hidden="true"></i>' . h(lang('Put on the site')) . '</button>';
    }

    $pages_html = $pages
        ? '<h4 class="sdh-pages-title">' . h(lang('Pages')) . '<span class="sdh-count">' . pg_format_number(count($pages), 0) . '</span></h4>' . pg_designer_home_page_chips($d, $pages, 7)
        : '';

    return '<article class="sdh-card sdh-site" tabindex="0" aria-label="' . $name . '"' . $attrs . '>'
         .     '<div class="row g-7">'
         .         '<div class="col-12 lg:col-5 2xl:col-4">' . $picture . '</div>'
         .         '<div class="col-12 lg:col-7 2xl:col-8">'
         .             '<div class="sdh-title-row"><h3 class="sdh-name">' . $title . '</h3>' . $more . '</div>'
         .             pg_designer_list_status_cell($d)
         .             ($home ? '<p class="sdh-flag"><i class="bi bi-house-door" aria-hidden="true"></i>' . h(lang('The home page is in this design')) . '</p>' : '')
         .             '<div class="sdh-facts">' . $facts_html . '</div>'
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
        'newTab'        => lang('Open in a new tab'),
        'addPage'       => lang('Add page'),
        'view'          => lang('View on site'),
        'on'            => lang('Put on the site'),
        'off'           => lang('Take off the site'),
        'rename'        => lang('Rename'),
        'template'      => lang('Turn into a template'),
        'delete'        => lang('Delete'),
        'editPage'      => lang('Edit'),
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
        'compMore'      => lang(array('string' => 'and {var:1} more', 'vars' => '{var}')),
        'pagesMore'     => lang(array('string' => '+{var:1} more', 'vars' => '{var}')),
        'noMatch'       => lang('No design matches “{var}”.'),
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
 * The Visual Page Editor home (view_system_styles.php): one head row
 * ("Designs", the search, the shared components and "New design", which
 * opens the New design window), then the designs that exist; a site with
 * none is shown three starting points instead (pg_designer_home_first_visit()).
 * Most sites have one design, so it is not a table:
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
            $tiles .= '<div class="sdh-tile-col" data-name="' . h($d['style_name']) . '">' . $card . '</div>';
        }
    }

    // Button and panel come from one count: the button only shows when the
    // panel has rows to list; the rows load when it opens.
    $comp = pg_designer_components_card($user);

    // Below designer the screen opens designs and says who creates them.
    $note = $can_create ? '' :
        '<p class="sdh-note"><i class="bi bi-info-circle" aria-hidden="true"></i>' . h(lang('Open a design to edit its text and the areas marked for you. New designs are created by designers.')) . '</p>';

    if ($designs) {
        $others = count($designs) - ($featured !== '' ? 1 : 0);
        $body = $featured
              . ($tiles !== ''
                  ? ($featured !== '' ? '<h3 class="sdh-subhead">' . h(lang('Other designs')) . '<span class="sdh-count" id="sdh-others-count">' . $others . '</span></h3>' : '')
                    . '<div class="sdh-tiles" id="sdh-tiles">' . $tiles . '</div>'
                    . '<p class="sdh-filter-empty d-none" id="sdh-filter-empty"><span id="sdh-filter-empty-text"></span>'
                    . '<button type="button" class="pg-button pg-button-sm pg-button-ghost" id="sdh-filter-clear">' . h(lang('Clear the search')) . '</button></p>'
                  : '');
    } elseif ($can_create) {
        // The first visit: the only place the starting points stand.
        $body = pg_designer_home_first_visit(pg_designer_start_kinds(!empty($templates), pg_designer_figma_start_on()));
    } else {
        $body = '<p class="sdh-note">' . h(lang('There is no design to open yet.')) . '</p>';
    }

    $list =
        '<section class="sdh-section" aria-labelledby="sdh-designs-title">'
        .   pg_designer_home_head(count($designs), $comp['button'], $can_create)
        .   $note
        .   $body
        . '</section>';

    $config = array(
        'canChange'   => $can_create,
        'canDraft'    => $can_draft,
        'canTemplate' => $can_tpl,
        'styleBin'    => $style_bin,
        // A template made from a design is listed once the page is drawn
        // again: the gallery is printed with the page.
        'templateUrl' => 'view_system_styles.php?start=template' . ($from_pages ? '&from=pages' : ''),
        // A start's own part (assets/js/designer_<part>.js), loaded on first use.
        'partBase'    => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/',
        'partStamp'   => pg_designer_asset_stamp('assets/js/designer_figma.js'),
        't'           => pg_designer_home_texts(),
    );

    print
        pg_page_shell(array(
            'title'               => lang('Page Design Studio'),
            'extra classes'       => 'design',
            'icon'                => 'design',
            'heading'             => lang('Page Design Studio'),
            'head'                => '<link rel="stylesheet" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/css/designer_home.css?v=' . pg_designer_asset_stamp('assets/css/designer_home.css') . '">',
        )) . '
        <main id="content" class="container-fluid sdh">
            ' . $liveform->output_errors() . '
            ' . $liveform->output_notices() . '
            ' . $list . '
        </main>
        ' . $comp['panel'] . '
        ' . ($can_create ? pg_designer_start_window($templates, $from_pages) : '') . '
        ' . ($can_tpl ? pg_designer_home_template_dialog() : '') . '
        <div class="menu sdh-menu" id="sdh-menu" role="menu" tabindex="-1"></div>
        <script type="application/json" id="sdh-config">' . json_encode($config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>
        ' . pg_js_bundle_tags('assets/js/pg_live_picture.min.js') . '
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

    // Only the number is read here: the rows and where each is placed (a
    // pass over every page tree) come from shared_component/home_rows when
    // the panel first opens, so a closed panel costs one COUNT.
    $comp_total = (int)db_value("SELECT COUNT(*) FROM shared_components");
    if ($comp_total === 0) return $none;

    $comp_button = pg_designer_home_comp_button($comp_total);

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
                                <th><input type="checkbox" class="check" id="sd-comp-all" aria-label="' . h(lang('Select all')) . '" disabled></th>
                                <th>' . h(lang('Name')) . '</th>
                                <th>' . h(lang('Type')) . '</th>
                                <th>' . h(lang('Source')) . '</th>
                                <th>' . h(lang('Used on')) . '</th>
                                <th nowrap>' . h(lang('Last Modified')) . '</th>
                            </tr>
                        </thead>
                        <tbody id="sd-comp-rows">
                            <tr class="sd-comp-wait"><td colspan="6" class="text-center fg-secondary py-5" role="status">' . h(lang('Loading…')) . '</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </dialog>';

    return array('button' => $comp_button, 'panel' => $comp_panel);
}

/**
 * The rows of the shared components panel (pg_designer_components_card()),
 * asked by the panel when it first opens (shared_component/home_rows).
 * Each row carries data-usage, the places the delete confirmation names.
 *
 * Returns array('total' => number of rows, 'html' => the <tr> rows).
 */
function pg_designer_components_rows()
{
    $comp_rows = db_items("SELECT id, name, category, updated_at, system_region_config FROM shared_components ORDER BY name");
    if (!is_array($comp_rows)) $comp_rows = array();
    if (count($comp_rows) === 0) return array('total' => 0, 'html' => '');

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
        } elseif (strpos($category, 'common_region:') === 0) {
            // Made from a common region (pg_common_region_component()).
            $source_cell = h(lang('Common Region'));
        } else {
            $source_cell = '<span class="fg-secondary">—</span>';
        }

        // Where it is placed: page (design), the recycle bin marked. The
        // confirmation reads the same lines from data-usage.
        $usage_lines  = array();
        $usage_cell   = '';
        $region_drawn = false;
        foreach ((isset($comp_usage[$sid]) ? $comp_usage[$sid] : array()) as $u) {
            // A binned page of a deleted design has no design name left: the
            // page alone, never "page ()".
            $page = (string)$u['page_name'];
            if ($page === '') $page = (string)$u['style_name'];
            if ($page === '') $page = '#' . (int)$u['page_id'];
            $line = ((int)$u['page_id'] > 0 && (string)$u['style_name'] !== '') ? $page . ' (' . $u['style_name'] . ')' : $page;
            // Drawn by a converted common region's tag, not placed.
            if (isset($u['common_region'])) {
                $line .= ' — ' . lang(array('string' => 'common region tag "{var:1}"', 'vars' => array((string)$u['common_region'])));
                $region_drawn = true;
            }
            $usage_lines[] = $line;
            $usage_cell .= '<div>' . h($line)
                . (!empty($u['binned']) ? ' <span class="badge theme-secondary">' . h(lang('Recycle Bin')) . '</span>' : '')
                . '</div>';
        }
        if ($region_drawn) {
            $usage_cell .= '<div class="small fg-secondary">' . h(lang('It draws the common region wherever its tag stands; deleting it brings back the region\'s earlier content on those pages.')) . '</div>';
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

    return array('total' => count($comp_rows), 'html' => $comp_output_rows);
}

/**
 * A start's one icon, the same in the window's rail, in its pane's head and
 * on the home's cards; '' for a key that names no start. Pure.
 *
 * @param mixed $key a start of pg_designer_start_kinds(), or one a later plan adds
 * @return string a Bootstrap Icons class
 */
function pg_designer_start_icon($key)
{
    $icons = array(
        'blank'    => 'bi-plus-square',
        'template' => 'bi-grid-1x2',
        'sketch'   => 'bi-pencil',
        'figma'    => 'bi-vector-pen',
        'canva'    => 'bi-images',
        'document' => 'bi-file-earmark-text',
        'import'   => 'bi-file-zip',
        'paste'    => 'bi-code-slash',
        'describe' => 'bi-stars',
    );
    return (is_string($key) && isset($icons[$key])) ? $icons[$key] : '';
}

/**
 * The starts the New design window lists, in the order it lists them, each
 * in its group of the rail: 'new' (Start new), 'tool' (From a design tool),
 * 'have' (From what you have). A row is the start's icon, its title and one
 * short line; its pane opens with the icon, a heading that says the act and
 * one sentence of what happens next. 'go' is the foot's filled button's
 * words ('' where the start's part draws its own). A start built later takes
 * its place in this list with a pane of its own in pg_designer_start_pane().
 *
 * @param bool $has_templates whether a template can be offered
 * @param bool $figma         whether the Figma start is listed
 *                            (pg_designer_figma_start_on())
 * @return array list of array('key', 'group', 'icon', 'title', 'line',
 *               'heading', 'lead', 'go', 'disabled')
 */
function pg_designer_start_kinds($has_templates, $figma = false)
{
    $kind = function ($key, $group, $title, $line, $heading, $lead, $go, $disabled = false) {
        return array('key' => $key, 'group' => $group, 'icon' => pg_designer_start_icon($key), 'title' => $title, 'line' => $line,
                     'heading' => $heading, 'lead' => $lead, 'go' => $go, 'disabled' => $disabled);
    };
    $create = lang('Create design');

    $kinds = array(
        $kind('blank', 'new', lang('Blank'), lang('An empty page'), lang('Start with a blank page'),
              lang('The editor opens on an empty page. Add sections from the palette, then more pages as tabs.'), $create),
        $kind('template', 'new', lang('Template'), lang('A ready design'), lang('Start from a template'),
              lang('A ready-made design with its pages, colours and fonts. Change anything afterwards.'), lang('Use this template'), !$has_templates),
    );
    if ($figma) {
        $kinds[] = $kind('figma', 'tool', lang('Figma'), lang('Frames of a Figma file'), lang('Start from a Figma file'),
              lang('Paste a file\'s link. Its frames are listed with their pictures; the ones you tick become pages.'), '');
    }
    // No connection: the pictures are added in the window (pg_designer_canva_pane()).
    $kinds[] = $kind('canva', 'tool', lang('Canva'), lang('Pages exported from Canva'), lang('Start from Canva pages'),
              lang('Download the pages from Canva as PNG and add them here. Each comes in as a picture to build on, a model for the page, not layers you can edit.'), '');
    $kinds[] = $kind('document', 'have', lang('From a document'), lang('A text or a Word file'), lang('Start from a document'),
              lang('The headings, lists, questions and figures of a text become the sections of a page.'), '');
    $kinds[] = $kind('import', 'have', lang('ZIP file'), lang('An HTML page or a project'), lang('Start from a ZIP file'),
              lang('Choose what the design is built on. The editor then opens and asks for the file; its pages become tabs.'), $create);
    $kinds[] = $kind('paste', 'have', lang('Pasted markup'), lang('HTML and CSS you paste'), lang('Start from pasted markup'),
              lang('Choose what the design is built on. The editor then opens with a box to paste the HTML and CSS into.'), $create);
    return $kinds;
}

/**
 * Whether the New design window lists the Figma start: once the import that
 * turns the ticked frames into pages is there (pg_figma_import_start(), the
 * converter's run), so "Next" never leads nowhere. The connection on
 * Connections works before it.
 *
 * @return bool
 */
function pg_designer_figma_start_on()
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/figma.php');

    return function_exists('pg_figma_import_start');
}

/**
 * The attributes of a start's pane whose form is drawn by a part of its own
 * (assets/js/designer_<part>.js, loaded by designer_home.js the first time
 * the start is chosen): its name and its file's stamp. '' for the others.
 *
 * @param string $key
 * @return string
 */
function pg_designer_start_part_attrs($key)
{
    if (!in_array($key, array('figma', 'canva', 'document'), true)) {
        return '';
    }
    return ' data-part="' . $key . '" data-part-stamp="' . h(pg_designer_asset_stamp('assets/js/designer_' . $key . '.js')) . '"';
}

/**
 * The Canva start's pane: the one sentence that says what the pictures
 * become, and the drop place. The pages, the busy and refused files and
 * the colours card are drawn by assets/js/designer_canva.js; each picture
 * is stored by designer › canva_page (includes/fn/canva_start.php).
 * "Create design" leads to a Bootstrap 5 design that opens in the Sketch
 * tool (add_system_style.php?start=canva).
 *
 * @param bool $from_pages
 * @return string
 */
function pg_designer_canva_pane($from_pages)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/canva_start.php');
    $limits = pg_upload_limits();
    $texts = array(
        'max'        => pg_canva_max_pages(),
        'bytes'      => (int) $limits['file_max'],
        'href'       => pg_designer_start_go_href('canva', 'bootstrap5', $from_pages),
        'pages'      => lang('{var:1} pages'),
        'page1'      => lang('1 page'),
        'files'      => lang('from {var:1} files'),
        'file1'      => lang('from 1 file'),
        'remove'     => lang('Remove {var:1}'),
        'reading'    => lang('Adding the picture…'),
        'stop'       => lang('Stop'),
        'clear'      => lang('Clear'),
        'notPng'     => lang('{var:1} is not a PNG. Download the page from Canva as PNG.'),
        'pdf'        => lang('{var:1} is a PDF, which this site cannot turn into pictures. Download the pages from Canva as PNG.'),
        'tooBig'     => lang(array('string' => '{var:1} is larger than {var:2}, the biggest single file this server accepts.', 'vars' => array('{var:1}', convert_bytes_to_string($limits['file_max'], 1)))),
        'tooMany'    => lang(array('string' => 'One design takes {var:1} pictures; {var:2} and the files after it were not added.', 'vars' => array(pg_canva_max_pages(), '{var:2}'))),
        'failed'     => lang('The server could not accept {var:1}. Please try again.'),
        'network'    => lang('Network error.'),
        'hint'       => lang('Each picture becomes the model of one page.'),
        'next'       => lang('Next'),
        'back'       => lang('Back'),
        'ask'        => lang('Ask me the questions'),
        'create'     => lang('Create design'),
        'varsTitle'  => lang('Your design\'s variables'),
        'varsLine'   => lang('The colours are measured from your pictures. You can change every one later in Settings, Theme, Variables.'),
        'brand'      => lang('Brand colour'),
        'second'     => lang('Second colour'),
        'brandNote'  => lang('most used in your pictures'),
        'secondNote' => lang('the next most used'),
        'rest'       => lang('Type, feel, room'),
        'restValue'  => lang('The site\'s default'),
        'restNote'   => lang('a picture carries no fonts'),
        'noColour'   => lang('No colour was found in the pictures; the site\'s default stays.'),
        'move'       => lang('Drag to change the order, or press Alt and an arrow key.'),
    );
    $place = function ($small) {
        return '<i class="bi bi-cloud-arrow-up" aria-hidden="true"></i>'
            . '<span class="sd-cv-drop-text"><b><span class="sdh-hint-mouse">' . h($small ? lang('Drop more files here') : lang('Drop PNG files here')) . '</span>'
            . '<span class="sdh-hint-touch">' . h(lang('Add PNG files')) . '</span></b>'
            . '<small>' . h($small ? lang('PNG') : lang('In Canva: Share, Download, then “PNG”.')) . '</small></span>'
            . '<span class="pg-button pg-button-quiet' . ($small ? ' pg-button-sm' : '') . '" aria-hidden="true">' . h(lang('Choose files')) . '</span>';
    };
    return '<div class="sd-cv" data-texts="' . h(json_encode($texts, JSON_UNESCAPED_UNICODE)) . '">'
        . '<div class="sd-cv-head" hidden></div>'
        . '<div class="sd-cv-tiles" role="list" aria-label="' . h(lang('Pages')) . '" hidden></div>'
        . '<label class="sd-cv-drop" data-big="' . h($place(false)) . '" data-small="' . h($place(true)) . '">' . $place(false)
        .   '<input type="file" class="visually-hidden" accept="image/png,image/jpeg,.png,.jpg,.jpeg,application/pdf,.pdf" multiple>'
        . '</label>'
        . '<div class="sd-cv-rows" aria-live="polite"></div>'
        . '<div class="sd-cv-colors" hidden></div>'
        . '</div>';
}

/**
 * The Figma start's pane (the frame list is drawn by
 * assets/js/designer_figma.js, loaded the first time the start is chosen).
 * Not connected: an empty state leading to Connections, or for who may not
 * open it a line to ask a manager. Connected: the account line, the file
 * link field and the seat line; a token Figma no longer accepts says so
 * above the field.
 *
 * @param array $connection pg_figma_connection()
 * @param bool  $manager    whether the viewer may open Connections
 * @return string
 */
function pg_designer_figma_pane($connection, $manager)
{
    $manage = 'connections.php?service=figma';

    if (!$connection) {
        return '<div class="sd-fg-empty text-center">'
            . '<span class="pg-icon-tile pg-tone-violet" aria-hidden="true"><i class="bi bi-vector-pen"></i></span>'
            . '<strong class="d-block mt-3">' . h(lang('Figma is not connected')) . '</strong>'
            . '<p class="pg-text-2 mt-2">' . h(lang('Starting from a Figma file needs an access token, saved once on Connections.')) . '</p>'
            . ($manager
                ? '<a class="pg-button pg-button-quiet" href="' . h($manage) . '"><i class="bi bi-plug me-1" aria-hidden="true"></i>' . h(lang('Connect in Connections')) . '</a>'
                : '<p class="pg-text-meta mb-0">' . h(lang('Ask a manager of the site to connect Figma.')) . '</p>')
            . '</div>';
    }

    $texts = array(
        'read'      => lang('Read the file'),
        'reading'   => lang('Reading…'),
        'another'   => lang('Another file'),
        'back'      => lang('Back'),
        'next'      => lang('Next'),
        'tickAll'   => lang('Tick all'),
        'untickAll' => lang('Untick all'),
        'sizes'     => lang('{var:1} pages, {var:2} frames'),
        'frames'    => lang('{var:1} frames'),
        'lead'      => lang(array('string' => 'Tick the frames that become pages. One import takes {var:1} frames at most.', 'vars' => pg_figma_max_frames())),
        'count'     => lang('{var:1} of {var:2} frames ticked: {var:1} pages'),
        'over'      => lang('{var:1} of {var:2} frames ticked: untick {var:3}'),
        'noLink'    => lang('This is not a Figma file link. Copy it in Figma with Share, then “Copy link”.'),
        'network'   => lang('Network error.'),
        'max'       => pg_figma_max_frames(),
        // The import (ST5): the variables card, the running import, the report.
        'preparing'   => lang('Reading the frames…'),
        'varsTitle'   => lang('Your design\'s variables'),
        'varsLead'    => lang('Found in the file. The design starts with them; change them later on the Variables tab.'),
        'brand'       => lang('Brand colour'),
        'second'      => lang('Second colour'),
        'more'        => lang('More colours'),
        'fonts'       => lang('Fonts'),
        'feel'        => lang('Feel and room'),
        'siteDefault' => lang('The site\'s default'),
        'fromStyle'   => lang('Style “{var:1}”'),
        'fromFrames'  => lang('The frames\' values'),
        'create'      => lang('Create design'),
        'making'      => lang('Making {var:1} pages'),
        'waiting'     => lang('Waiting'),
        'working'     => lang('Working…'),
        'got'         => lang('{var:1} pictures, {var:2} vectors'),
        'notMade'     => lang('Not made'),
        'stop'        => lang('Stop'),
        'stopTitle'   => lang('Stop the import?'),
        'stopText'    => lang('No design is made, and the pictures fetched so far are removed from the file manager.'),
        'made'        => lang('{var:1} pages made from “{var:2}”'),
        'madeOf'      => lang('{var:1} of {var:2} pages made'),
        'nPictures'   => lang('{var:1} pictures'),
        'nVectors'    => lang('{var:1} vectors'),
        'nShared'     => lang('{var:1} shared components'),
        'nVariables'  => lang('{var:1} variables'),
        'nNotes'      => lang('{var:1} notes'),
        'nNotMade'    => lang('{var:1} not made'),
        'whole'       => lang('Carried whole'),
        'notes'       => lang('{var:1} notes'),
        'folder'      => lang('The pictures are in the file manager, folder {var:1}.'),
        'copy'        => lang('Copy the report'),
        'copied'      => lang('Copied'),
        'open'        => lang('Open the design'),
        'openHref'    => 'add_system_style.php?framework=bootstrap5&start=figma&import=',
    );

    return '<div class="sd-fg" data-texts="' . h(json_encode($texts, JSON_UNESCAPED_UNICODE)) . '">'
        . '<div class="sd-fg-acct pg-card-inner-row">'
        .   '<span class="pg-icon-tile pg-tone-violet" aria-hidden="true"><i class="bi bi-vector-pen"></i></span>'
        .   '<span>' . h(($connection['name'] !== '') ? lang(array('string' => 'Connected as {var:1}', 'vars' => $connection['name'])) : lang('Connected')) . '</span>'
        .   ($manager ? '<a class="ms-auto sd-fg-manage" href="' . h($manage) . '">' . h(lang('Manage in Connections')) . '</a>' : '')
        . '</div>'
        . (($connection['state'] === 'refused')
            ? '<div class="alert theme-warning small py-3 mt-4 mb-0"><strong class="d-block">' . h(lang('Figma no longer accepts the stored token')) . '</strong>'
              . h(lang('It has expired or was removed in Figma. Make a new one in Figma\'s settings and save it here.'))
              . ($manager ? ' <a href="' . h($manage) . '">' . h(lang('Manage in Connections')) . '</a>' : '') . '</div>'
            : '')
        . '<div class="sd-fg-ask mt-5">'
        .   '<label class="pg-field-label" for="sd-fg-link">' . h(lang('Figma file link')) . '</label>'
        .   '<input type="url" class="pg-input" id="sd-fg-link" placeholder="https://www.figma.com/design/…" autocomplete="off" spellcheck="false" aria-describedby="sd-fg-link-help sd-fg-error">'
        .   '<div class="invalid-feedback d-block pg-inline-feedback" id="sd-fg-error" role="alert" hidden></div>'
        .   '<div class="pg-field-help" id="sd-fg-link-help">' . h(lang('In Figma: Share, then “Copy link”.')) . '</div>'
        .   '<p class="pg-text-meta mt-4 mb-0"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>' . h(lang('A View or Collab seat in Figma reads only a few files a month; more imports need a Dev or Full seat.')) . '</p>'
        . '</div>'
        . '<div class="sd-fg-limit" hidden></div>'
        . '<div class="sd-fg-list" hidden></div>'
        . '</div>';
}

/**
 * The start the window opens on, from ?start= or the card that opened it: a
 * start it lists and can offer, the blank one otherwise.
 *
 * @param mixed $value
 * @param array $kinds pg_designer_start_kinds()
 * @return string
 */
function pg_designer_start_key($value, $kinds)
{
    if (is_string($value)) {
        foreach ($kinds as $k) {
            if ($k['key'] === $value && !$k['disabled']) return $value;
        }
    }
    return 'blank';
}

/**
 * Where the window's main button leads for a start built on a framework. The
 * ZIP and paste starts open the editor with its file box or its paste box on
 * top (add_system_style.php). A template's card carries its own address, so
 * the template start has none here.
 *
 * @param string $key       a start of pg_designer_start_kinds()
 * @param string $framework bootstrap5 | custom
 * @param bool   $from_pages
 * @return string
 */
function pg_designer_start_go_href($key, $framework, $from_pages)
{
    if ($key === 'template') return '';
    $href = 'add_system_style.php?framework=' . ($framework === 'custom' ? 'custom' : 'bootstrap5');
    if ($key === 'import' || $key === 'paste' || $key === 'canva' || $key === 'document') $href .= '&start=' . $key;
    return $href . ($from_pages ? '&from=pages' : '');
}

/**
 * The framework a design is built on, as two choice cards named as things,
 * each with one line and three ticks of what it brings. Asked before the
 * editor opens, because the pages are written against it and it does not
 * change. Bootstrap 5 comes chosen; "Recommended" stands on the blank
 * start's card only. A press anywhere on a card chooses it
 * (assets/js/designer_home.js).
 *
 * @param string $key        blank | import | paste
 * @param bool   $from_pages
 * @return string
 */
function pg_designer_start_framework_choice($key, $from_pages)
{
    $choice = function ($framework, $title, $text, $ticks, $pill, $checked) use ($key, $from_pages) {
        $id = 'sd-ns-fw-' . $key . '-' . $framework;
        $list = '';
        foreach ($ticks as $tick) {
            $list .= '<li><i class="bi bi-check-lg" aria-hidden="true"></i>' . h($tick) . '</li>';
        }
        return '<div class="form-field form-field-card sd-ns-fw">'
            . '<input class="radio" type="radio" name="sd_ns_fw_' . h($key) . '" value="' . h($framework) . '" id="' . $id . '" aria-describedby="' . $id . '_help"'
            . ' data-href="' . h(pg_designer_start_go_href($key, $framework, $from_pages)) . '"' . ($checked ? ' checked' : '') . '>'
            . '<label class="pg-start-head" for="' . $id . '"><strong>' . h($title) . '</strong>'
            . ($pill ? '<span class="pg-pill">' . h(lang('Recommended')) . '</span>' : '')
            . '<span class="pg-start-mark" aria-hidden="true"><i class="bi bi-check"></i></span></label>'
            . '<div class="form-text" id="' . $id . '_help">' . h($text)
            . '<ul class="sd-ns-ticks">' . $list . '</ul></div>'
            . '</div>';
    };
    return '<fieldset class="sd-ns-built"><legend class="pg-group-label">' . h(lang('Built on')) . '</legend>'
        . '<div class="pg-start pg-start-choice">'
        . $choice('bootstrap5', lang('Bootstrap 5'), lang('Bootstrap 5.3 is loaded on every page of the design.'),
            array(lang('The grid and its columns'), lang('Bootstrap\'s components'), lang('Ready-made blocks in the palette')), $key === 'blank', true)
        . $choice('custom', lang('Custom'), lang('Nothing is added. For Bootstrap 6, Tailwind or your own CSS.'),
            array(lang('Your CSS and JS files, as they are'), lang('Any framework you bring'), lang('No stylesheet of ours on the page')), false, false)
        . '</div>'
        . '<p class="sd-ns-lock"><i class="bi bi-lock" aria-hidden="true"></i>' . h(lang('This cannot be changed later.')) . '</p>'
        . '</fieldset>';
}

/**
 * One start's pane: what the start asks before the design opens.
 */
function pg_designer_start_pane($kind, $templates, $from_pages)
{
    switch ($kind['key']) {
        case 'template':
            return pg_designer_template_gallery($templates, $from_pages);
        case 'import':
            return pg_designer_start_framework_choice('import', $from_pages);
        case 'figma':
            require_once(PG_FUNCTIONS_DIR . '/includes/fn/figma.php');
            // The window is a designer's (role 1 or 0), who may open
            // Connections (a manager's screen) too.
            return pg_designer_figma_pane(pg_figma_connection(), true);
        case 'document':
            // The form and the rows are drawn by assets/js/pg_document_rows.js,
            // loaded the first time the start is chosen.
            require_once(PG_FUNCTIONS_DIR . '/includes/fn/document_start.php');
            return '<div class="sd-doc" data-texts="' . h(json_encode(pg_document_start_texts(), JSON_UNESCAPED_UNICODE)) . '"'
                . ' data-href="' . h(pg_designer_start_go_href('document', 'bootstrap5', $from_pages)) . '"></div>';
        case 'canva':
            return pg_designer_canva_pane($from_pages);
        case 'paste':
            return pg_designer_start_framework_choice('paste', $from_pages);
        default:
            return pg_designer_start_framework_choice('blank', $from_pages);
    }
}

/**
 * The New design window (#sdStartModal): the ways to start a design in one
 * place, at most 1120 x 720 px. On the left the rail: the starts in three
 * labelled groups, each a row of its icon tile, title and one line; on the
 * right the chosen start's pane, opening with the start's icon, the act and
 * one sentence; in the foot Cancel and the one filled button, which leads
 * where the pane's choice says (on the template start, the chosen card).
 * The home's start cards open it on their own start (data-sd-start), and
 * ?start=<key> opens it on load (assets/js/designer_home.js).
 *
 * @param array $templates pg_design_templates()
 * @param bool  $from_pages
 * @return string
 */
function pg_designer_start_window($templates, $from_pages)
{
    $kinds  = pg_designer_start_kinds(!empty($templates), pg_designer_figma_start_on());
    $groups = array('new' => lang('Start new'), 'tool' => lang('From a design tool'), 'have' => lang('From what you have'));
    $tabs   = '';
    $panes  = '';
    $group  = '';
    foreach ($kinds as $i => $k) {
        $key = $k['key'];
        // A group's label is not a stop: the arrow keys walk every row.
        if ($k['group'] !== $group) {
            $group = $k['group'];
            $tabs .= '<p class="pg-group-label sd-ns-group" aria-hidden="true">' . h($groups[$group]) . '</p>';
        }
        $tabs .= '<button type="button" class="pg-sm-link sd-ns-item' . ($i === 0 ? ' is-active' : '') . '" role="tab" id="sd-ns-tab-' . $key . '" aria-controls="sd-ns-pane-' . $key . '"'
               . ' aria-selected="' . ($i === 0 ? 'true' : 'false') . '" tabindex="' . ($i === 0 ? '0' : '-1') . '" data-start="' . $key . '"'
               . ($k['disabled'] ? ' aria-disabled="true"' : '') . '>'
               . '<span class="pg-icon-tile sd-ns-tile"><i class="bi ' . h($k['icon']) . '" aria-hidden="true"></i></span>'
               . '<span class="sd-ns-text"><span class="sd-ns-title">' . h($k['title']) . '</span>'
               . '<span class="sd-ns-line">' . h($k['disabled'] ? lang('There are no templates.') : $k['line']) . '</span></span>'
               . '<i class="bi bi-chevron-right sd-ns-chev" aria-hidden="true"></i>'
               . '</button>';
        $panes .= '<section class="sd-ns-pane" role="tabpanel" id="sd-ns-pane-' . $key . '" aria-labelledby="sd-ns-tab-' . $key . '" tabindex="0"'
                . ' data-go="' . h($k['go']) . '"' . pg_designer_start_part_attrs($key) . ($i === 0 ? '' : ' hidden') . '>'
                . '<div class="sd-ns-head"><span class="pg-icon-tile sd-ns-head-tile" aria-hidden="true"><i class="bi ' . h($k['icon']) . '"></i></span>'
                . '<div><h3 class="pg-title-section">' . h($k['heading']) . '</h3>'
                . '<p class="pg-sm-desc pg-text-2">' . h($k['lead']) . '</p></div></div>'
                . ($k['disabled'] ? '' : pg_designer_start_pane($k, $templates, $from_pages))
                . '</section>';
    }
    return '
        <dialog class="dialog dialog-xl md-down:dialog-fullscreen pg-dialog-centered dialog-scrollable sd-ns-dialog" id="sdStartModal" aria-labelledby="sdStartModalLabel">
            <div class="dialog-header">
                <h2 class="dialog-title fs-lg" id="sdStartModalLabel">' . h(lang('New design')) . '</h2>
                <button type="button" class="btn-close" data-bs-dismiss="dialog" aria-label="' . h(lang('Close')) . '"></button>
            </div>
            <div class="dialog-body sd-ns">
                <div class="sd-ns-list" role="tablist" aria-orientation="vertical" aria-label="' . h(lang('Ways to start')) . '">' . $tabs . '</div>
                <div class="sd-ns-panes"><div class="sd-ns-back"><button type="button" class="pg-button pg-button-sm pg-button-ghost" id="sd-ns-back"><i class="bi bi-chevron-left" aria-hidden="true"></i>' . h(lang('All starts')) . '</button></div>' . $panes . '</div>
            </div>
            <div class="dialog-footer">
                <span class="sd-ns-count me-auto" aria-live="polite" hidden></span>
                <button type="button" class="pg-button pg-button-quiet" data-bs-dismiss="dialog">' . h(lang('Cancel')) . '</button>
                <span class="sd-ns-part-foot d-contents" hidden></span>
                <a href="' . h(pg_designer_start_go_href('blank', 'bootstrap5', $from_pages)) . '" class="pg-button pg-button-fill" id="sd-ns-go">' . h(lang('Create design')) . '</a>
            </div>
        </dialog>'
        // The document start's "From the file manager".
        . (function_exists('pg_picker_script') ? pg_picker_script() : '');
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
 * The look and palette a template card opens in, for the picker's choice
 * ($look, $palette: a key, '' for plain Bootstrap, or '@template' for the
 * template's own). The template's own is what its file names; a template
 * that names no look wears the default look (pg_design_default_look()), as
 * the drawings and the installer do. $summary: pg_design_template_summary().
 * Pure.
 *
 * @return array look, palette
 */
function pg_designer_template_card_theme($summary, $look, $palette)
{
    $own_look = (is_array($summary) && isset($summary['look']) && is_string($summary['look'])) ? $summary['look'] : (function_exists('pg_design_default_look') ? pg_design_default_look() : '');
    $own_palette = (is_array($summary) && isset($summary['palette']) && is_string($summary['palette'])) ? $summary['palette'] : '';
    return array(
        'look' => ((string)$look === '@template') ? $own_look : (string)$look,
        'palette' => ((string)$palette === '@template') ? $own_palette : (string)$palette,
    );
}

/**
 * The address of a template's picture: its home page in the given look and
 * palette, without its scripts (design_preview.php ...&still=1); '' when the template has no page a preview
 * can show, so its drawing stays.
 */
function pg_designer_template_picture_src($tpl, $look = '', $palette = '')
{
    if (!is_array($tpl) || !isset($tpl['id']) || !pg_design_template_preview_pages($tpl)) return '';
    return pg_designer_template_theme_query(pg_design_preview_address('panel', array('template' => (string)$tpl['id'], 'still' => true)), $look, $palette);
}

/**
 * A preview address with the look and palette added, as the picker's script
 * adds them (twin: themed() in pg_designer_template_theme_picker()): only
 * the ones pg_design_looks()/pg_design_palettes() know. Pure.
 */
function pg_designer_template_theme_query($src, $look, $palette)
{
    $src = (string)$src;
    if ($src === '') return '';
    $looks = function_exists('pg_design_looks') ? pg_design_looks() : array();
    $palettes = function_exists('pg_design_palettes') ? pg_design_palettes() : array();
    if ((string)$look !== '' && isset($looks[(string)$look])) $src .= '&look=' . rawurlencode((string)$look);
    if ((string)$palette !== '' && isset($palettes[(string)$palette])) $src .= '&palette=' . rawurlencode((string)$palette);
    return $src;
}

/**
 * The template start's pane of the New design window: the templates this
 * installation ships. Choosing one opens the editor with the template's
 * pages as unsaved tabs; nothing is created until the design is published.
 */
function pg_designer_template_gallery($templates, $from_pages)
{
    $extra = $from_pages ? '&from=pages' : '';
    $look0 = function_exists('pg_design_default_look') ? pg_design_default_look() : '';
    $cards = '';
    // Every card opens in the template's own theme until the picker above
    // says otherwise (pg_designer_template_theme_picker()).
    foreach ((array)$templates as $tpl) {
        $s = pg_design_template_summary($tpl);
        // Template and framework version, readable at a glance: on the
        // picture when there is one, under the description otherwise.
        $fw_version = isset($s['framework_version']) ? (string)$s['framework_version'] : '';
        $ver_badge  = '<span class="sd-tpl-ver">' . h('v' . $s['version'] . ' · ' . $s['framework_label'] . ($fw_version !== '' ? ' ' . $fw_version : '')) . '</span>';
        // The template's home page in the theme the card opens in (its own:
        // the picker's first choices); the drawing until the page arrives,
        // or alone when the template has no page a preview can show.
        $theme = pg_designer_template_card_theme($s, '@template', '@template');
        $picture_src = pg_designer_template_picture_src($tpl, $theme['look'], $theme['palette']);
        $tour_src = ($picture_src !== '') ? pg_design_preview_address('panel', array('template' => $s['id'], 'tour' => true)) : '';
        $drawing = (pg_design_framework_has($s['framework'], 'bootstrap') && function_exists('pg_design_thumb_svg'))
            ? pg_design_thumb_svg(($s['look'] !== null) ? $s['look'] : $look0, (string)$s['palette'], 'sd-tpl-thumb-svg', '', $s['thumb'])
            : '';
        $label = lang(array('string' => 'Preview of {var:1}', 'vars' => $s['name']));
        if ($picture_src !== '') {
            $thumb = '<div class="sd-tpl-thumb">' . pg_design_live_picture($picture_src, array('label' => $label, 'fallback' => $drawing)) . $ver_badge . '</div>';
        } elseif ($drawing !== '') {
            $thumb = '<div class="sd-tpl-thumb">' . pg_design_thumb_svg(($s['look'] !== null) ? $s['look'] : $look0, (string)$s['palette'], 'sd-tpl-thumb-svg', $label, $s['thumb']) . $ver_badge . '</div>';
        } else {
            $thumb = '';
        }
        // The theme the template is made for: the picker's first choices
        // ("Template's theme", "Template's palette") open each card in its
        // own; any other choice opens every card in that one.
        $theme_attrs = ' data-look="' . h(($s['look'] !== null) ? (string)$s['look'] : $look0) . '" data-palette="' . h((string)$s['palette']) . '"'
                     . ' data-has-look="' . ($s['look'] !== null ? '1' : '0') . '" data-has-palette="' . ($s['palette'] !== null ? '1' : '0') . '"'
                     . ' data-picture="' . h($picture_src !== '' ? pg_design_preview_address('panel', array('template' => $s['id'], 'still' => true)) : '') . '"'
                     . ' data-tour="' . h($tour_src) . '"';
        $theme_names = '';
        if ($s['look'] !== null || $s['palette'] !== null) {
            $parts = array();
            if ($s['look'] !== null && function_exists('pg_design_look_label')) $parts[] = pg_design_look_label($s['look']);
            if ($s['palette'] !== null && $s['palette'] !== '') {
                $pals = pg_design_palettes();
                if (isset($pals[$s['palette']])) $parts[] = $pals[$s['palette']]['name'];
            }
            $theme_names = implode(' · ', array_filter($parts, 'strlen'));
        }
        $made_for = ($theme_names !== '') ? lang(array('string' => 'Made for: {var:1}', 'vars' => $theme_names)) : '';
        // The tour of every page and e-mail, in a new tab, in the card's theme.
        $tour_btn = ($tour_src !== '')
            ? '<a href="' . h(pg_designer_template_theme_query($tour_src, $theme['look'], $theme['palette'])) . '" target="_blank" rel="noopener" class="btn btn-sm btn-outline theme-secondary rounded-pill px-5 text-nowrap sd-tpl-tour">'
              . '<i class="bi bi-eye me-1" aria-hidden="true"></i>' . lang('Preview') . '<span class="visually-hidden"> ' . h(lang('(opens in a new tab)')) . '</span></a>'
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
            ? '<button type="button" class="btn btn-sm btn-text theme-secondary sd-tpl-delete no-popover" data-template="' . h($s['id']) . '" data-name="' . h($s['name']) . '" data-bs-toggle="tooltip" title="' . h(lang('Delete template')) . '" aria-label="' . h(lang('Delete template')) . '"><i class="bi bi-trash" aria-hidden="true"></i></button>'
            : '';
        // A press chooses the card, the foot's "Use this template" leads to
        // data-use; a double press or Enter uses it at once. The theme picker
        // keeps data-use on its choice, from data-href.
        $use_href = 'add_system_style.php?start=template&template=' . rawurlencode($s['id']) . $extra;
        $use_now = $use_href . '&look=' . rawurlencode($theme['look']) . '&palette=' . rawurlencode($theme['palette']);
        $cards .=
            '<div class="col-6 xl:col-4">
                <div class="card h-100 overflow-hidden sd-tpl-card" tabindex="0" data-use="' . h($use_now) . '" data-href="' . h($use_href) . '" aria-label="' . h($s['name']) . '"' . $theme_attrs . '>
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
                                ' . $delete_btn . '
                                ' . $tour_btn . '
                            </div>
                        </div>
                        <span class="small fg-secondary text-truncate">' . h($s['description']) . '</span>
                        ' . ($thumb === '' ? '<div class="fg-secondary">' . $ver_badge . '</div>' : '') . '
                    </div>
                </div>
            </div>';
    }
    return '
        ' . pg_designer_template_theme_picker() . '
        <div class="row g-5" id="sd-tpl-cards">' . $cards . '</div>
        <p class="small fg-secondary mb-0' . ($cards !== '' ? ' d-none' : '') . '" id="sd-tpl-empty">' . lang('There are no templates.') . '</p>
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
 * cards. The first choices, checked at first, are each template's own
 * ("Template's theme", "Template's palette"); any other opens every
 * template in that one. The cards' pictures follow the choice
 * (PGLivePicture.set(), 300 ms after the last change, the cards in view
 * first), and "Use this template" and "Preview" carry it
 * (?look=&palette=). Both can be changed later in the editor, under
 * Settings > Theme.
 */
function pg_designer_template_theme_picker()
{
    if (!function_exists('pg_design_looks')) return '';
    $looks = array(
        '@template' => array('name' => lang('Template\'s theme'), 'description' => lang('Each template in the look it is made for.')),
        '' => array('name' => lang('Plain Bootstrap'), 'description' => lang('Plain Bootstrap 5.3, as it comes.')),
    );
    $looks = array_merge($looks, pg_design_looks());
    $look_btns = '';
    $n = 0;
    foreach ($looks as $key => $l) {
        $id = 'sd-tpl-look-' . (++$n);
        // Each look is drawn small before its name (pg_design_look_drawing()).
        $icon = ($key === '@template')
            ? '<i class="bi bi-stars me-1" aria-hidden="true"></i>'
            : pg_design_look_drawing($key === '' ? null : $l['preview']);
        $look_btns .= '<input type="radio" class="btn-check" name="sd_tpl_look" id="' . $id . '" value="' . h($key) . '"' . ($key === '@template' ? ' checked' : '') . ' autocomplete="off">'
                    . '<label class="btn btn-outline theme-secondary rounded-pill no-popover" for="' . $id . '" data-bs-toggle="tooltip" title="' . h($l['description']) . '">' . $icon . h($l['name']) . '</label>';
    }
    $own = lang('the template\'s own palette');
    $pal_btns = '<input type="radio" class="btn-check" name="sd_tpl_palette" id="sd-tpl-pal-0" value="@template" checked data-name="' . h($own) . '" autocomplete="off">'
              . '<label class="btn btn-outline theme-secondary rounded-pill no-popover sd-tpl-pal-own" for="sd-tpl-pal-0"><i class="bi bi-stars me-1" aria-hidden="true"></i>' . h(lang('Template\'s palette')) . '</label>';
    $bs = pg_design_bootstrap_colors();
    $palettes = array('' => array('name' => lang('Bootstrap'), 'primary' => $bs['primary'], 'secondary' => $bs['secondary']));
    $palettes = array_merge($palettes, pg_design_palettes());
    foreach (pg_design_theme_files('palette') as $f) {
        if (!isset($f['meta']['primary'], $f['meta']['secondary'])) continue;
        $palettes['file-' . $f['id']] = array('name' => $f['name'], 'primary' => $f['meta']['primary'], 'secondary' => $f['meta']['secondary']);
    }
    $n = 0;
    foreach ($palettes as $key => $p) {
        $id = 'sd-tpl-pal-' . (++$n);
        $pal_btns .= '<input type="radio" class="btn-check" name="sd_tpl_palette" id="' . $id . '" value="' . h($key) . '"'
                   . ' data-name="' . h($p['name']) . '" autocomplete="off">'
                   . '<label class="sd-tpl-pal no-popover" for="' . $id . '" data-bs-toggle="tooltip" title="' . h($p['name']) . '"><i style="background:' . h($p['primary']) . '"></i><i style="background:' . h($p['secondary']) . '"></i>'
                   . '<span class="visually-hidden">' . h($p['name']) . '</span></label>';
    }
    // The preview addresses take only the looks and palettes these lists
    // know (pg_design_preview_address()); a palette from a theme file shows
    // in plain Bootstrap colours on the picture and opens in its own.
    $known = array('looks' => array_keys(pg_design_looks()), 'palettes' => array_keys(pg_design_palettes()));
    return '
        <div class="border rounded-5 p-5 mb-5 bg-1" id="sd-tpl-theme" data-known="' . h(json_encode($known)) . '">
            <div class="mb-5">
                <div class="small fw-semibold mb-1">' . lang('Look') . ' <span class="fw-normal fg-secondary">— ' . lang('corners, shadows, type and buttons') . '</span></div>
                <div class="d-flex flex-wrap gap-1 sd-tpl-look" role="radiogroup" aria-label="' . h(lang('Look')) . '">' . $look_btns . '</div>
            </div>
            <div>
                <div class="small fw-semibold mb-1">' . lang('Colour palette') . ' <span class="fw-normal fg-secondary">— <span id="sd-tpl-pal-name">' . h($own) . '</span></span></div>
                <div class="d-flex flex-wrap align-items-center gap-3" role="radiogroup" aria-label="' . h(lang('Colour palette')) . '">' . $pal_btns . '</div>
            </div>
            <div class="form-text mt-3">' . lang('You can change both later in the editor, under Settings, Design, Theme.') . '</div>
        </div>
        <script>
            (function () {
                var box = document.getElementById("sd-tpl-theme");
                if (!box) return;
                var modal = box.closest(".dialog");
                var known = { looks: [], palettes: [] };
                try { known = JSON.parse(box.getAttribute("data-known")) || known; } catch (e) {}
                var timer = null;
                // What a card opens in (twin of pg_designer_template_card_theme()).
                function theme(card, look, palette) {
                    return {
                        look: look === "@template" ? (card.getAttribute("data-look") || "") : look,
                        palette: palette === "@template" ? (card.getAttribute("data-palette") || "") : palette
                    };
                }
                // A preview address with the theme (twin of pg_designer_template_theme_query()).
                function themed(src, t) {
                    if (!src) return "";
                    if (t.look && known.looks.indexOf(t.look) >= 0) src += "&look=" + encodeURIComponent(t.look);
                    if (t.palette && known.palettes.indexOf(t.palette) >= 0) src += "&palette=" + encodeURIComponent(t.palette);
                    return src;
                }
                function inView(el) {
                    var r = el.getBoundingClientRect();
                    return r.bottom > 0 && r.top < (window.innerHeight || document.documentElement.clientHeight);
                }
                // The pictures follow the choice once it settles, the cards in view first.
                function redraw() {
                    if (!window.PGLivePicture) return;
                    var first = [], rest = [];
                    modal.querySelectorAll(".sd-tpl-card [data-pg-live-picture]").forEach(function (pic) {
                        var want = pic.getAttribute("data-want") || "";
                        if (!want || want === (pic.getAttribute("data-src") || "")) return;
                        (inView(pic) ? first : rest).push(pic);
                    });
                    first.concat(rest).forEach(function (pic) { PGLivePicture.set(pic, pic.getAttribute("data-want")); });
                }
                function sync() {
                    var l = box.querySelector("input[name=sd_tpl_look]:checked");
                    var p = box.querySelector("input[name=sd_tpl_palette]:checked");
                    var look = l ? l.value : "@template", palette = p ? p.value : "@template";
                    var nm = document.getElementById("sd-tpl-pal-name");
                    if (nm && p) nm.textContent = p.getAttribute("data-name");
                    modal.querySelectorAll(".sd-tpl-card").forEach(function (card) {
                        var t = theme(card, look, palette);
                        card.setAttribute("data-use", card.getAttribute("data-href") + "&look=" + encodeURIComponent(t.look) + "&palette=" + encodeURIComponent(t.palette));
                        var tour = card.querySelector(".sd-tpl-tour");
                        if (tour) tour.href = themed(card.getAttribute("data-tour"), t);
                        var pic = card.querySelector("[data-pg-live-picture]");
                        if (pic) pic.setAttribute("data-want", themed(card.getAttribute("data-picture"), t));
                    });
                    clearTimeout(timer);
                    timer = setTimeout(redraw, 300);
                }
                box.addEventListener("change", sync);
                // The cards come after this script in the page.
                if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", sync); else sync();
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
    // The mark of a text written with AI (page.ai_fields), drawn hidden and
    // shown by the editor for the open page; it saves with the page.
    $ai_marks_ready = pg_ai_fields_ready('page');
    $ai_title_marker = $ai_marks_ready ? pg_ai_fields_marker('page_title', 'pg_page_title', false) : '';
    $ai_description_marker = $ai_marks_ready ? pg_ai_fields_marker('page_meta_description', 'pg_page_meta_description', false) : '';

    $rail = function ($pane, $icon, $label, $scope, $active, $hidden = false) use ($design_hidden) {
        return '<button class="nav-link text-start' . ($active ? ' active' : '') . ($scope === 'design' ? $design_hidden : '') . ($hidden ? ' d-none' : '') . '" id="tab-' . $pane . '-btn"
                    data-bs-toggle="tab" data-bs-target="#tab-' . $pane . '" type="button" role="tab"
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
                lang('Only for classic Form List View and Form Item View pages that use this design: they keep two layouts, A and B, and show the one chosen here. The widgets of the Page Design Studio draw their own layout and do not follow it.'),
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
                $liveform->output_field(array('type'=>'select', 'name'=>'theme_id', 'id'=>'sd-style-theme-file', 'options'=>$theme_options, 'class'=>'form-control form-control-sm')) . '
                <div class="sd-set-status" id="sd-theme-file-status" aria-live="polite"></div>',
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
            ' . (($full && ((int)$user['role'] <= 1) && pg_experiment_sheet_ready()) ? pg_designer_setting(lang('Experiments'),
                lang('Show part of the visitors a draft variant of this page and count which one reaches the goal more often. Saved pages on the site only.'),
                '<a id="sd-page-experiments" class="btn btn-sm btn-outline theme-secondary" href="#" target="_blank" rel="noopener"><span class="bi bi-signpost-split me-1" aria-hidden="true"></span>' . lang('Open the Experiments sheet') . '</a>
                <div class="sd-set-status" id="sd-page-experiment-status" aria-live="polite"></div>',
                array()) : '') . '
        </section>' . (($full && pg_page_draft_ready() && function_exists('pg_page_schedule_ready') && pg_page_schedule_ready()) ? '
        <section class="sd-settings-group" id="sd-page-schedule-group">
            <h3 class="sd-settings-group-title">' . lang('Schedule') . '</h3>
            ' . pg_designer_setting(lang('Publish at'),
                lang('The page stays a draft until this time; then the general job puts it on the site, in the folder above. Empty: it is published by hand.'),
                '<input type="datetime-local" id="pg_page_publish_at" class="form-control form-control-sm sd-page-field" data-page-key="page_publish_at" step="60" data-pg-datetime>',
                array('for' => 'pg_page_publish_at')) . '
            ' . pg_designer_setting(lang('Take off the site at'),
                lang('At this time the general job moves the page back to the private Drafts folder; the folder it returns to is kept. Empty: it stays on the site.'),
                '<input type="datetime-local" id="pg_page_unpublish_at" class="form-control form-control-sm sd-page-field" data-page-key="page_unpublish_at" step="60" data-pg-datetime>
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
                array('for' => 'pg_page_title', 'wide' => true, 'action' => $ai_title_button, 'marker' => $ai_title_marker)) . '
            ' . pg_designer_setting(lang('Meta Description'),
                lang('The summary under the title in search results and in shared links; 150 to 160 characters read best. Left empty, the description of the site is used.'),
                '<textarea name="page_meta_description" id="pg_page_meta_description" class="form-control form-control-sm sd-page-field" data-page-key="page_meta_description" rows="3" maxlength="255"></textarea>
                <div id="seo_c_pg_page_meta_description"></div>',
                array('for' => 'pg_page_meta_description', 'wide' => true, 'action' => $ai_description_button, 'marker' => $ai_description_marker)) . '
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
                            <!-- ONE tablist: Bootstrap deactivates the previous tab by looking
                                 for the active sibling inside the same tablist, so two separate
                                 navs would leave both panes showing. The rail buttons are
                                 data-bs-toggle="tab" (the Bootstrap 6 Tab listens to no "pill"),
                                 drawn as pills by nav-pills. The group headings are plain
                                 divs the tab logic ignores. -->
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
 * "Write with AI" for the alt text of the image selected on the canvas,
 * written by the browser's model from the image itself (target image_alt).
 * The properties panel is drawn by style_designer.js, which takes a copy of
 * this button for the image's Alt row (_sdAiAltButton()); '' when the person
 * or the site cannot ask.
 *
 * @param array $ctx the designer's context (user, active_page_id)
 * @return string a template element, or ''
 */
function pg_designer_ai_alt_template($ctx)
{
    $button = pg_ai_write_button($ctx['user'], array(
        'record'      => 'page',
        'record_id'   => (int) ($ctx['active_page_id'] ?? 0),
        'record_from' => 'designer',
        'theme'       => 'dark',
        'target'      => 'image_alt',
        'field'       => 'sd-prop-img-alt',
        'image'       => 'sd-prop-img-src',
        'context'     => array('page_title' => 'pg_page_title', 'page_name' => 'sd-page-name', 'image' => 'sd-prop-img-src', 'image_alt' => 'sd-prop-img-alt'),
    ));

    return ($button !== '') ? '<template id="sd-ai-alt-button">' . $button . '</template>' : '';
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
 * under the name), 'marker' (a mark after the name, pg_ai_fields_marker()).
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
    if (!empty($opts['action']) || !empty($opts['marker'])) {
        $name = pg_ai_write_label_row($name, (string)($opts['action'] ?? ''), (string)($opts['marker'] ?? ''));
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
        <dialog class="dialog dialog-scrollable" id="sdPickPageModal" data-bs-theme="dark" style="background:#1e2127; color:#c9d1d9; border:1px solid #30363d;">
                    <div class="dialog-header" style="border-color:#30363d;">
                        <h5 class="dialog-title fs-md"><span class="bi bi-files me-3"></span>' . lang('Select Page') . '</h5>
                        <button type="button" class="btn-close fg-white" data-bs-dismiss="dialog" aria-label="' . h(lang('Close')) . '"></button>
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
 * The Publish sheet (assets/js/pg_designer_publish.js): every page of the
 * design with its state and whether it changed, checkboxes for the pages to
 * put on the site, "Publish selected", and the two actions that keep pages
 * off the site. The rows are drawn by the script; the texts it shows are
 * the hidden data-sdp-text spans. During the save the sheet stays open and
 * the Publish button shows progress; after it, data-sdp-result lists where
 * each page went and Done closes the sheet.
 */
function pg_designer_publish_sheet()
{
    $texts = array(
        'state-live'      => lang('On the site'),
        'state-draft'     => lang('Draft'),
        'state-scheduled' => lang('Scheduled'),
        'state-held'      => lang('Held by an experiment'),
        'changed'         => lang('Changed'),
        'compare'         => lang('Compare with the live page'),
        'locked'          => lang('No content rights'),
        'busy'            => lang('Publishing…'),
        'result-live'     => lang('On the site now'),
        'result-draft'    => lang('Stayed drafts, off the site'),
        'result-kept'     => lang('Not saved: the changes stay in the editor'),
        'anyway'          => lang('Publish anyway'),
        'anyway-reason'   => lang('Why is it published without approval? The reason goes to the Site Log.'),
        'anyway-check'    => lang('Why is it published with a blocking finding? The reason goes to the Site Log.'),
        'checking'        => lang('Checking…'),
        'checking-n'      => lang('Checking {n} pages…'),
        'result-ready'    => lang('Ready'),
        'result-look'     => lang('{n} to look at'),
        'result-block'    => lang('{n} blocking'),
        'result-failed'   => lang('Not checked'),
    );
    $spans = '';
    foreach ($texts as $k => $v) {
        $spans .= '<span data-sdp-text="' . h($k) . '">' . h($v) . '</span>';
    }

    return '
        <dialog class="dialog dialog-scrollable" id="sdPublishSheet" aria-labelledby="sdPublishSheetLabel" data-bs-theme="dark" style="background:#1e2127; color:#c9d1d9; border:1px solid #30363d;">
                    <div class="dialog-header" style="border-color:#30363d;">
                        <h5 class="dialog-title fs-md" id="sdPublishSheetLabel"><span class="bi bi-rocket-takeoff me-3" aria-hidden="true"></span>' . h(lang('Publish')) . '</h5>
                        <button type="button" class="btn-close fg-white" data-bs-dismiss="dialog" aria-label="' . h(lang('Close')) . '"></button>
                    </div>
                    <div class="dialog-body p-0">
                        <div class="d-flex align-items-center gap-3 px-5 pt-4 pb-3" data-sdp-pick>
                            <p class="small fg-secondary mb-0 flex-grow-1">' . h(lang('The checked pages go on the site with one save. Drafts left unchecked are saved and stay off the site.')) . '</p>
                            <button type="button" class="btn btn-sm btn-text theme-primary text-nowrap" data-sdp-act="all">' . h(lang('Select all')) . '</button>
                        </div>
                        <div class="list-group list-group-flush" data-sdp-list></div>
                        <div class="d-none pb-3" data-sdp-result aria-live="polite"></div>
                        <p class="small fg-warning px-5 pt-3 mb-0 d-none" data-sdp-hold-note><span class="bi bi-exclamation-triangle me-1" aria-hidden="true"></span>' . h(lang('Unchecked changed pages are not saved: their changes stay in the editor and the site keeps the version it has.')) . '</p>
                        <div class="d-none" aria-hidden="true">' . $spans . '</div>
                    </div>
                    <div class="dialog-footer flex-wrap gap-2" style="border-color:#30363d;">
                        <p class="small fg-secondary w-100 mb-0 d-none" data-sdp-checking aria-live="polite"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span><span data-sdp-checking-text></span></p>
                        <button type="button" class="btn btn-sm btn-outline theme-secondary d-none" data-sdp-act="page-draft"><span class="bi bi-eye-slash me-2" aria-hidden="true"></span>' . h(lang('Save this page as a draft')) . '</button>
                        <button type="button" class="btn btn-sm btn-outline theme-secondary d-none" data-sdp-act="all-draft"><span class="bi bi-cloud-slash me-2" aria-hidden="true"></span>' . h(lang('Take every page of the design off the site')) . '</button>
                        <button type="button" class="btn btn-sm btn-solid theme-primary ms-auto" data-sdp-act="publish"><span class="bi bi-rocket-takeoff me-2" aria-hidden="true"></span>' . h(lang('Publish selected')) . '</button>
                        <button type="button" class="btn btn-sm btn-solid theme-primary ms-auto d-none" data-sdp-act="done">' . h(lang('Done')) . '</button>
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
        <dialog class="dialog" id="sdImportModal" data-bs-theme="dark" style="background:#1e2127; color:#c9d1d9; border:1px solid #30363d;">
                    <div class="dialog-header" style="border-color:#30363d;">
                        <h5 class="dialog-title fs-md"><span class="bi bi-file-earmark-code me-3"></span>' . lang('Import HTML / ZIP') . '</h5>
                        <button type="button" class="btn-close fg-white" data-bs-dismiss="dialog" aria-label="' . h(lang('Close')) . '"></button>
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
    $tree_like = "(tree_json LIKE '%\"page_id\":\"tab:%' OR tree_json LIKE '%\"ref\":\"tab:%')";
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
    // A design opened from a template and never saved: the page names it
    // opened with are held by nothing, so one the site took since then moves
    // on to the next free one and the links follow
    // (pg_design_template_first_save_names()).
    $tpl_link_map = array();
    if ($mode !== 'edit' && $style_id <= 0
        && isset($_POST['style_template']) && (string)$_POST['style_template'] !== ''
        && pg_design_template((string)$_POST['style_template'])) {
        $tpl_names = pg_design_template_first_save_names($pages);
        $pages = $tpl_names['pages'];
        $tpl_link_map = $tpl_names['map'];
        foreach ($tpl_names['renamed'] as $tpl_old => $tpl_new) {
            $warnings[] = lang(array(
                'string' => 'The page name "{var:1}" was taken in the meantime, so the page was saved as "{var:2}".',
                'vars'   => array($tpl_old, $tpl_new)));
        }
    }
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
        // The lock can lapse (a tab asleep longer than PG_COLLAB_STALE) and
        // be claimed again after somebody else saved the page and left. The
        // tab's tree is then older than the stored one; writing it would
        // silently undo that save, so the page is skipped like a locked one.
        $base_pid = isset($p['page_id']) ? (int)$p['page_id'] : 0;
        if ($base_pid > 0 && isset($p['base_hash'])
            && pg_page_base_is_stale($base_pid, (string)$p['base_hash'], pg_page_tree_json($base_pid))) {
            $locked_pages[$base_pid] = true;
            $warnings[] = lang(array(
                'string' => 'The page "{var:1}" was changed by somebody else after you opened it, so your changes to it were not saved. Reload the editor to see the latest version.',
                'vars'   => ($nm !== '' ? $nm : (string)$base_pid)));
            continue;
        }
        $r = pg_designer_save_page($style_id, $p, $user, true);
        foreach ($r['errors'] as $e) $errors[] = $e;
        foreach ($r['warnings'] as $w) $warnings[] = $w;
    }
    if ($style_name === '' && empty($errors)) $errors[] = lang('Name is required.');
    if (!empty($errors)) $fail($errors);
    if ($tpl_link_map) {
        require_once(dirname(__FILE__) . '/designer_access.php');
        if (pg_designer_is_full($user)) pg_design_template_relink_rows($pages, $tpl_link_map);
    }

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

    // A classic style opened in the editor (edit_system_style.php
    // ?classic=1) becomes a visual design with this save
    // (save_system_style()). Only a designer converts one; its page regions,
    // system region and layout override stay as they were.
    $converting = ($mode === 'edit' && $style_id > 0)
        && ((string)db_value("SELECT style_layout FROM style WHERE style_id = '$style_id' LIMIT 1") !== 'visual_designer');
    if ($converting && !$can_write_design) {
        $fail(array(lang('Only a designer can open a classic style in the Page Design Studio.')));
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
    // The colour mode: an editor loaded before the column existed posts
    // none, and save_system_style() then leaves the stored mode alone.
    if (isset($_POST['style_color_mode'])) {
        $style_data['color_mode'] = (string)$_POST['style_color_mode'];
    }
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
        // One trail line per save that changed the design's row; a save of
        // pages alone leaves the row as it was and writes none.
        $audit_before = ($style_id > 0) ? pg_audit_fetch('style', $style_id) : array();
        $style_id = (int)save_system_style($style_data);
        // A failed INSERT returns 0 (queries do not throw). Writing the
        // pages against style 0 would leave them on no design at all and
        // send the browser to edit_system_style.php?id=0.
        if ($style_id <= 0) {
            $fail(array(lang('The design could not be saved. Please try again.')));
        }
        pg_audit_record($audit_before ? 'update' : 'create', 'style', $style_id, $audit_before);
        log_activity(lang(array('string' => ($mode === 'edit' ? 'style ({var:1}) was modified' : 'style ({var:1}) was added'), 'vars' => array($style_name))), $_SESSION['sessionusername']);
        // The design's variables (Settings, Theme, Variables): the model the
        // tab posts, or, when the tab was not used, the stored one carried
        // over to the look and palette just saved. No file, no change.
        $sd_vars_post = isset($_POST['style_variables']) ? trim((string)$_POST['style_variables']) : '';
        $sd_vars_model = ($sd_vars_post !== '') ? json_decode($sd_vars_post, true) : null;
        $sd_vars_saved = pg_design_variables_save($style_id, is_array($sd_vars_model) ? $sd_vars_model : null,
            $style_data['look'], $style_data['palette'], $user['id']);
        if ($sd_vars_saved !== true) $warnings[] = $sd_vars_saved;
        // An update never changes a design's framework; a converted style
        // takes the one the editor opened it with (custom: the theme keeps
        // its own Bootstrap and scripts).
        if ($converting) {
            require_once(PG_FUNCTIONS_DIR . '/includes/fn/classic_style_design.php');
            pg_classic_style_mark_design($style_id, $style_data['framework'], $_SESSION['sessionusername']);
        }
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
    // Only this save may be held for a designer's approval
    // (includes/fn/content_proposals.php); the API and the assistant are not.
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/content_proposals.php');
    pg_content_proposals_editor_save(true);
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
        // Kept as the editor's waiting change: nothing was written, so the
        // page's form, short links and search entry stay as they are.
        if ($r['ok'] && !empty($r['held'])) {
            $id_map[] = array('key' => $key, 'page_id' => (int)$r['page_id'], 'draft' => 0, 'held' => (string)$r['held']);
            if ((string)$r['held_message'] !== '') $warnings[] = $r['held_message'];
            continue;
        }
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
    // A reference field keeps its form only while that form exists, so a
    // form is made after the forms its fields point into; one in a loop of
    // references is written once more when the others exist.
    $cf_plan = $cf_jobs ? pg_cf_reconcile_plan(array_keys($cf_jobs)) : array('order' => array(), 'again' => array());
    foreach ($cf_plan['order'] as $cf_page_id) {
        $cf_set = pg_designer_resolve_tab_values($cf_jobs[$cf_page_id], $tab_ids);
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
    foreach ($cf_plan['again'] as $cf_page_id) {
        pg_cf_reconcile_page_form($cf_page_id, $user['id'], pg_designer_resolve_tab_values($cf_jobs[$cf_page_id], $tab_ids));
    }

    // A converted classic style's folder pages, when the editor's "Also
    // convert" box was left on: each gets the style and the frame's tree
    // (pg_classic_style_convert_folder_pages()). Before the asset clean-up
    // below, which clears their per-page copies too.
    if ($converting && $can_write_design && !empty($_POST['classic_folder_pages'])) {
        require_once(PG_FUNCTIONS_DIR . '/includes/fn/classic_style_design.php');
        $folder_done = pg_classic_style_convert_folder_pages($style_id, $user, $legacy_body_classes);
        foreach ($folder_done as $fid) $written[] = (int)$fid;
        if ($folder_done) {
            $notes[] = count($folder_done) === 1
                ? lang('The page that took the style from its folder was converted.')
                : lang(array('string' => '{var:1} pages that took the style from their folder were converted.', 'vars' => array(count($folder_done))));
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
        // The base the next save of each written page starts from: the tree
        // as it is stored now, after the merges and references above.
        foreach ($id_map as $mi => $m) {
            $pid = (int)$m['page_id'];
            if ($pid > 0 && in_array($pid, $written, true)) {
                $id_map[$mi]['base_hash'] = pg_page_base_hash(pg_page_tree_json($pid));
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
