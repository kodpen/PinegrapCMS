<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Panel actions - the table behind api.php.
 *
 * Every action api.php answers is listed in pg_panel_actions() and lives in
 * a group file under includes/panel/ as one function:
 *
 *     function pg_panel_<action>($request, $action)
 *
 * The chat_*, site_chat_* and ws_* actions share one handler per family
 * (pg_panel_chat, pg_panel_site_chat, pg_panel_ws), which hands the action
 * name on to its module.
 *
 * api.php asks pg_panel_gate() which of the general checks an action gets,
 * then calls pg_panel_dispatch(); a listed action is answered there and an
 * unknown one gets "Invalid action.".
 *
 * Unlike a dashboard widget, which only produces data and leaves the answer to
 * api.php, a panel action writes its own answer: the bodies respond() and exit
 * themselves. A handler may also return an array, which the dispatcher
 * respond()s, or nothing, which ends the request with an empty 200 - what
 * software_update_check and an unknown file_explorer type have always
 * answered.
 *
 * Columns:
 *   exempt     true lets the request past api.php's general gate (signed in,
 *              then role 1 or better) to the handler's own checks. Only
 *              this column changes what a request is answered.
 *   any_role   (optional) the general gate asks for the session but not the
 *              role.
 *   token      the handler calls validate_token().
 *   token_by   (optional) the function that checks the form token for a
 *              handler that hands the whole request on.
 *   write      the handler changes something.
 *   public     (optional) a storefront or site visitor endpoint that works
 *              without a login, so it neither asks for a session nor holds
 *              a form token.
 *
 * The access checks stay in the handlers, in the order they always ran. That
 * order decides how a request without a session is refused - "Invalid
 * login." from the general gate, "Invalid token." where validate_token()
 * comes first, a redirect where validate_user() does - so one uniform check
 * here would change answers clients already rely on.
 *
 * What the handlers inherit from api.php:
 *   - respond() and validate_token() stay defined in api.php (the barcode
 *     inventory endpoints define their own copies, so they cannot move to a
 *     file both would load);
 *   - $token stays in api.php's global scope, where validate_token() reads it
 *     with `global $token`;
 *   - the working directory is still api.php's directory, which relative paths
 *     such as 'data/backups/' rely on.
 *
 * A handler that loads the session user declares `global $user` first. The
 * user used to be loaded at api.php's top level, and helpers it reaches -
 * check_folder_access_in_array(), select_folder(), get_page_options(),
 * pg_tour_ready(), the page and widget renderers - still read it through
 * `global $user`; as a local it would be null there and their access checks
 * would fail closed.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The gate every handler file checks.
define('PG_PANEL_ACTIONS', true);

/**
 * @return array action => array('file', 'handler', 'exempt', 'token', 'write'
 *               [, 'any_role'] [, 'token_by'] [, 'public'])
 */
function pg_panel_actions()
{
    $actions = array(
        // Every panel page's background jobs and the dashboard.
        // user_online_check and sitemap_check are fired from every panel
        // page whatever the visitor's role, and the general gate would turn
        // away anyone above role 1; the handlers check the session and the
        // token for themselves.
        'user_online_check' => array('file' => 'dashboard.php', 'handler' => 'pg_panel_user_online_check', 'exempt' => true, 'token' => true, 'write' => true),
        'sitemap_check' => array('file' => 'dashboard.php', 'handler' => 'pg_panel_sitemap_check', 'exempt' => true, 'token' => true, 'write' => true),
        'get_widget_data' => array('file' => 'dashboard.php', 'handler' => 'pg_panel_get_widget_data', 'exempt' => true, 'token' => false, 'write' => false),
        'get_widgets_data' => array('file' => 'dashboard.php', 'handler' => 'pg_panel_get_widgets_data', 'exempt' => true, 'token' => false, 'write' => false),
        'get_dashboard_brief' => array('file' => 'dashboard.php', 'handler' => 'pg_panel_get_dashboard_brief', 'exempt' => true, 'token' => false, 'write' => false),
        // Personal settings: the pinned apps live in the caller's own user
        // row. Every role; the handler checks the session and the token.
        'user_pinned_app_update' => array('file' => 'dashboard.php', 'handler' => 'pg_panel_user_pinned_app_update', 'exempt' => true, 'token' => true, 'write' => true),
        // Arranging the dashboard is open to every backend role; the handler
        // checks the session and the token for itself.
        'update_dashboard_widgets' => array('file' => 'dashboard.php', 'handler' => 'pg_panel_update_dashboard_widgets', 'exempt' => true, 'token' => true, 'write' => true),
        // Site-wide, so it keeps the general gate.
        'update_dashboard_appearance' => array('file' => 'dashboard.php', 'handler' => 'pg_panel_update_dashboard_appearance', 'exempt' => false, 'token' => true, 'write' => true),
        // Noting that someone has watched a guided tour is theirs to do
        // whatever their role; the general gate would leave a basic user
        // watching the same tour on every visit.
        'tour_seen' => array('file' => 'dashboard.php', 'handler' => 'pg_panel_tour_seen', 'exempt' => true, 'token' => true, 'write' => true),
        // The account menu's quick form for one's own name: every role.
        'contact_quick_save' => array('file' => 'dashboard.php', 'handler' => 'pg_panel_contact_quick_save', 'exempt' => true, 'token' => true, 'write' => true),

        // The bell is drawn for every backend role.
        'check_unread_notifications' => array('file' => 'notifications.php', 'handler' => 'pg_panel_check_unread_notifications', 'exempt' => true, 'token' => false, 'write' => false),
        'edit_notifications' => array('file' => 'notifications.php', 'handler' => 'pg_panel_edit_notifications', 'exempt' => true, 'token' => true, 'write' => true),
        'get_notifications' => array('file' => 'notifications.php', 'handler' => 'pg_panel_get_notifications', 'exempt' => true, 'token' => true, 'write' => true),
        'remove_notifications' => array('file' => 'notifications.php', 'handler' => 'pg_panel_remove_notifications', 'exempt' => true, 'token' => true, 'write' => true),
        // Browser notifications for this device. Each touches only the
        // caller's own subscriptions and devices, so every role may use them.
        'push_config' => array('file' => 'notifications.php', 'handler' => 'pg_panel_push_config', 'exempt' => true, 'token' => true, 'write' => true),
        'push_subscribe' => array('file' => 'notifications.php', 'handler' => 'pg_panel_push_subscribe', 'exempt' => true, 'token' => true, 'write' => true),
        'push_unsubscribe' => array('file' => 'notifications.php', 'handler' => 'pg_panel_push_unsubscribe', 'exempt' => true, 'token' => true, 'write' => true),
        'push_test' => array('file' => 'notifications.php', 'handler' => 'pg_panel_push_test', 'exempt' => true, 'token' => true, 'write' => true),
        // Asked by the service worker, which cannot hold a form token. It
        // only reads what the bell would show the same person, so the
        // handler asks for the session alone.
        'push_pending' => array('file' => 'notifications.php', 'handler' => 'pg_panel_push_pending', 'exempt' => true, 'token' => false, 'write' => false),

        // Storefront endpoints: no login, no form token. The writes are the
        // visitor's own cart and the shipping rate caches.
        'get_installment_options' => array('file' => 'commerce.php', 'handler' => 'pg_panel_get_installment_options', 'exempt' => true, 'token' => false, 'write' => false, 'public' => true),
        'eo_get_installments' => array('file' => 'commerce.php', 'handler' => 'pg_panel_eo_get_installments', 'exempt' => true, 'token' => false, 'write' => false, 'public' => true),
        'eo_default_tree' => array('file' => 'commerce.php', 'handler' => 'pg_panel_eo_default_tree', 'exempt' => true, 'token' => false, 'write' => false, 'public' => true),
        'eo_required_sections' => array('file' => 'commerce.php', 'handler' => 'pg_panel_eo_required_sections', 'exempt' => true, 'token' => false, 'write' => false, 'public' => true),
        'add_to_cart' => array('file' => 'commerce.php', 'handler' => 'pg_panel_add_to_cart', 'exempt' => true, 'token' => false, 'write' => true, 'public' => true),
        'get_cross_sell_items' => array('file' => 'commerce.php', 'handler' => 'pg_panel_get_cross_sell_items', 'exempt' => true, 'token' => false, 'write' => false, 'public' => true),
        'get_cross_sell_for_product' => array('file' => 'commerce.php', 'handler' => 'pg_panel_get_cross_sell_for_product', 'exempt' => true, 'token' => false, 'write' => false, 'public' => true),
        'get_delivery_date' => array('file' => 'commerce.php', 'handler' => 'pg_panel_get_delivery_date', 'exempt' => true, 'token' => false, 'write' => true, 'public' => true),
        'get_product' => array('file' => 'commerce.php', 'handler' => 'pg_panel_get_product', 'exempt' => true, 'token' => false, 'write' => false, 'public' => true),
        'get_shipping_methods' => array('file' => 'commerce.php', 'handler' => 'pg_panel_get_shipping_methods', 'exempt' => true, 'token' => false, 'write' => true, 'public' => true),
        'delete_order' => array('file' => 'commerce.php', 'handler' => 'pg_panel_delete_order', 'exempt' => false, 'token' => true, 'write' => true),
        // The refund dialog is offered to the order screen's people with the
        // ERP right, past role 1; the handler checks them. Read only.
        'erp_refund_preview' => array('file' => 'commerce.php', 'handler' => 'pg_panel_erp_refund_preview', 'exempt' => true, 'token' => false, 'write' => false),
        'update_order' => array('file' => 'commerce.php', 'handler' => 'pg_panel_update_order', 'exempt' => false, 'token' => true, 'write' => true),
        // The product screens admit whoever manages the store (roles 0-2, or
        // a basic user with manage_ecommerce); the handlers apply that same
        // rule, so an endpoint never refuses a button its screen offered.
        'update_product_status' => array('file' => 'commerce.php', 'handler' => 'pg_panel_update_product_status', 'exempt' => true, 'token' => true, 'write' => true),
        'update_product_group_status' => array('file' => 'commerce.php', 'handler' => 'pg_panel_update_product_group_status', 'exempt' => true, 'token' => true, 'write' => true),
        'getproductlist' => array('file' => 'commerce.php', 'handler' => 'pg_panel_getproductlist', 'exempt' => false, 'token' => false, 'write' => false),
        'get_unselected_products' => array('file' => 'commerce.php', 'handler' => 'pg_panel_get_unselected_products', 'exempt' => true, 'token' => false, 'write' => false),
        'get_product_barcodes' => array('file' => 'commerce.php', 'handler' => 'pg_panel_get_product_barcodes', 'exempt' => true, 'token' => true, 'write' => false),
        'generate_product_barcode' => array('file' => 'commerce.php', 'handler' => 'pg_panel_generate_product_barcode', 'exempt' => true, 'token' => true, 'write' => false),
        'save_product_barcode' => array('file' => 'commerce.php', 'handler' => 'pg_panel_save_product_barcode', 'exempt' => true, 'token' => true, 'write' => true),
        'delete_product_barcode' => array('file' => 'commerce.php', 'handler' => 'pg_panel_delete_product_barcode', 'exempt' => true, 'token' => true, 'write' => true),
        'bulk_assign_barcodes' => array('file' => 'commerce.php', 'handler' => 'pg_panel_bulk_assign_barcodes', 'exempt' => true, 'token' => true, 'write' => true),
        'save_barcode_template' => array('file' => 'commerce.php', 'handler' => 'pg_panel_save_barcode_template', 'exempt' => true, 'token' => true, 'write' => true),
        'product_name_check' => array('file' => 'commerce.php', 'handler' => 'pg_panel_product_name_check', 'exempt' => true, 'token' => true, 'write' => false),
        // The offer editor belongs to whoever manages the store (roles 0-2,
        // or a basic user with manage_ecommerce); pg_offer_editor_handle()
        // checks the token, the session and that rule itself.
        'offer_editor' => array('file' => 'commerce.php', 'handler' => 'pg_panel_offer_editor', 'exempt' => true, 'token' => false, 'token_by' => 'pg_offer_editor_handle', 'write' => true),

        // Folder rights are checked per folder by the handler.
        'upload_file' => array('file' => 'content.php', 'handler' => 'pg_panel_upload_file', 'exempt' => true, 'token' => true, 'write' => true),
        // get_common_regions, get_folders, get_pages and test are called by
        // the LiveSite migration on another site, with a user name and a
        // password in the body; their names and answers are a contract.
        'get_common_regions' => array('file' => 'content.php', 'handler' => 'pg_panel_get_common_regions', 'exempt' => false, 'token' => false, 'write' => false),
        'get_dynamic_region' => array('file' => 'content.php', 'handler' => 'pg_panel_get_dynamic_region', 'exempt' => false, 'token' => false, 'write' => false),
        'get_dynamic_regions' => array('file' => 'content.php', 'handler' => 'pg_panel_get_dynamic_regions', 'exempt' => false, 'token' => false, 'write' => false),
        'get_file' => array('file' => 'content.php', 'handler' => 'pg_panel_get_file', 'exempt' => false, 'token' => false, 'write' => false),
        'get_folders' => array('file' => 'content.php', 'handler' => 'pg_panel_get_folders', 'exempt' => false, 'token' => false, 'write' => false),
        // Forms decide who may read them in forms.php, so the general gate
        // only asks for the session.
        'get_form' => array('file' => 'content.php', 'handler' => 'pg_panel_get_form', 'exempt' => false, 'any_role' => true, 'token' => false, 'write' => false),
        'get_forms' => array('file' => 'content.php', 'handler' => 'pg_panel_get_forms', 'exempt' => false, 'any_role' => true, 'token' => false, 'write' => false),
        'get_page' => array('file' => 'content.php', 'handler' => 'pg_panel_get_page', 'exempt' => false, 'token' => false, 'write' => false),
        'get_pages' => array('file' => 'content.php', 'handler' => 'pg_panel_get_pages', 'exempt' => false, 'token' => false, 'write' => false),
        'test' => array('file' => 'content.php', 'handler' => 'pg_panel_test', 'exempt' => false, 'token' => false, 'write' => false),
        'create_dynamic_region' => array('file' => 'content.php', 'handler' => 'pg_panel_create_dynamic_region', 'exempt' => false, 'token' => true, 'write' => true),
        'update_dynamic_region' => array('file' => 'content.php', 'handler' => 'pg_panel_update_dynamic_region', 'exempt' => false, 'token' => true, 'write' => true),
        // A basic user sorts the menus they may edit.
        'sort_menu_items' => array('file' => 'content.php', 'handler' => 'pg_panel_sort_menu_items', 'exempt' => true, 'token' => true, 'write' => true),

        // The page designer (assets/js/page_designer.js): designers only.
        'get_design_files' => array('file' => 'designer.php', 'handler' => 'pg_panel_get_design_files', 'exempt' => false, 'token' => false, 'write' => false),
        'get_designer_region' => array('file' => 'designer.php', 'handler' => 'pg_panel_get_designer_region', 'exempt' => false, 'token' => false, 'write' => false),
        'get_designer_regions' => array('file' => 'designer.php', 'handler' => 'pg_panel_get_designer_regions', 'exempt' => false, 'token' => false, 'write' => false),
        'get_items_in_style' => array('file' => 'designer.php', 'handler' => 'pg_panel_get_items_in_style', 'exempt' => false, 'token' => false, 'write' => false),
        'get_layout' => array('file' => 'designer.php', 'handler' => 'pg_panel_get_layout', 'exempt' => false, 'token' => false, 'write' => false),
        'get_style' => array('file' => 'designer.php', 'handler' => 'pg_panel_get_style', 'exempt' => false, 'token' => false, 'write' => false),
        'get_styles' => array('file' => 'designer.php', 'handler' => 'pg_panel_get_styles', 'exempt' => false, 'token' => false, 'write' => false),
        'create_design_region' => array('file' => 'designer.php', 'handler' => 'pg_panel_create_design_region', 'exempt' => false, 'token' => true, 'write' => true),
        'update_designer_region' => array('file' => 'designer.php', 'handler' => 'pg_panel_update_designer_region', 'exempt' => false, 'token' => true, 'write' => true),
        'update_file' => array('file' => 'designer.php', 'handler' => 'pg_panel_update_file', 'exempt' => false, 'token' => true, 'write' => true),
        'update_layout' => array('file' => 'designer.php', 'handler' => 'pg_panel_update_layout', 'exempt' => false, 'token' => true, 'write' => true),
        'update_page_designer_properties' => array('file' => 'designer.php', 'handler' => 'pg_panel_update_page_designer_properties', 'exempt' => false, 'token' => true, 'write' => true),
        'update_style' => array('file' => 'designer.php', 'handler' => 'pg_panel_update_style', 'exempt' => false, 'token' => true, 'write' => true),
        // The front-end toolbar's open/closed state lives in the caller's own
        // session. Every role; the handler checks the session and the token.
        'update_toolbar_properties' => array('file' => 'designer.php', 'handler' => 'pg_panel_update_toolbar_properties', 'exempt' => true, 'token' => true, 'write' => true),
        // The visual editor is no longer designer-only. A manager or a user
        // with content rights opens it to edit text, images and the areas
        // marked for them, and the editor needs its endpoints to do that -
        // presence, page load, the SEO check. The handler has its own
        // allow-list and refuses everything that shapes the design, so the
        // general gate would only be a second, blunter copy of a decision
        // made properly there.
        'designer' => array('file' => 'designer.php', 'handler' => 'pg_panel_designer', 'exempt' => true, 'token' => true, 'write' => true),
        'designer_file' => array('file' => 'designer.php', 'handler' => 'pg_panel_designer_file', 'exempt' => true, 'token' => true, 'write' => true),
        // A dynamic region's PHP code from the visual designer's code tab.
        // The general gate (role 1 or better) runs first; the handler then
        // admits administrators only, the right of edit_dynamic_region.php.
        'dynamic_region_code' => array('file' => 'designer.php', 'handler' => 'pg_panel_dynamic_region_code', 'exempt' => false, 'token' => true, 'write' => true),
        // A page's custom layout from the visual designer's code tab; the
        // general gate (role 1 or better) is the classic layout editor's right.
        'layout_code' => array('file' => 'designer.php', 'handler' => 'pg_panel_layout_code', 'exempt' => false, 'token' => true, 'write' => true),
        'shared_component' => array('file' => 'shared_component.php', 'handler' => 'pg_panel_shared_component', 'exempt' => true, 'token' => true, 'write' => true),

        'file_explorer' => array('file' => 'explorer.php', 'handler' => 'pg_panel_file_explorer', 'exempt' => true, 'token' => true, 'write' => true),

        'backend_search' => array('file' => 'search.php', 'handler' => 'pg_panel_backend_search', 'exempt' => true, 'token' => false, 'write' => false),

        // "Write with AI" is for whoever edits the record: roles 0-2, and a
        // basic user with the store right (products), edit rights to the
        // page's folder (pages) or the forms right with edit rights to the
        // form's folder (form records). The handler checks that, the session
        // and the token; it saves nothing but spends the site's daily
        // allowance.
        'ai_write' => array('file' => 'ai_write.php', 'handler' => 'pg_panel_ai_write', 'exempt' => true, 'token' => true, 'write' => true),

        'software_backup' => array('file' => 'software.php', 'handler' => 'pg_panel_software_backup', 'exempt' => true, 'token' => true, 'write' => true),
        'software_update_check' => array('file' => 'software.php', 'handler' => 'pg_panel_software_update_check', 'exempt' => true, 'token' => true, 'write' => true),
        'software_update' => array('file' => 'software.php', 'handler' => 'pg_panel_software_update', 'exempt' => true, 'token' => true, 'write' => true),
        'software_rollback' => array('file' => 'software.php', 'handler' => 'pg_panel_software_rollback', 'exempt' => false, 'token' => true, 'write' => true),

        // The System Status widget's jobs. Each checks the role it needs for
        // itself -- the table scan and the cache purge stand at manager, the
        // rule writer at administrator -- while the general gate wants role 1
        // or better and would turn away the manager the widget draws the
        // tiles for. A tile offered to somebody the endpoint then refuses is
        // worse than a tile that was never drawn. The two CA bundle jobs are
        // administrator-only and keep the general gate.
        'database_deep_check' => array('file' => 'system.php', 'handler' => 'pg_panel_database_deep_check', 'exempt' => true, 'token' => true, 'write' => true),
        'server_config_repair' => array('file' => 'system.php', 'handler' => 'pg_panel_server_config_repair', 'exempt' => true, 'token' => true, 'write' => true),
        'ca_bundle_config_repair' => array('file' => 'system.php', 'handler' => 'pg_panel_ca_bundle_config_repair', 'exempt' => false, 'token' => true, 'write' => true),
        'write_permissions_repair' => array('file' => 'system.php', 'handler' => 'pg_panel_write_permissions_repair', 'exempt' => true, 'token' => true, 'write' => true),
        'purge_cache' => array('file' => 'system.php', 'handler' => 'pg_panel_purge_cache', 'exempt' => true, 'token' => true, 'write' => true),
        'ca_bundle_update' => array('file' => 'system.php', 'handler' => 'pg_panel_ca_bundle_update', 'exempt' => false, 'token' => true, 'write' => true),
    );

    // Live chat in the panel is open to every backend role, role 3 included;
    // the handler checks the session and the token, and the pairing,
    // ownership and role rules are chat.php's.
    foreach (array(
        'chat_bootstrap', 'chat_online_users', 'chat_open', 'chat_send',
        'chat_poll', 'chat_unread_check', 'chat_mark_read',
        'chat_conversations', 'chat_close', 'chat_delete', 'chat_attach',
    ) as $chat_action) {
        $actions[$chat_action] = array('file' => 'chat.php', 'handler' => 'pg_panel_chat', 'exempt' => true, 'token' => true, 'write' => true);
    }

    // Live chat on the site works without a login; the visitor's token,
    // ownership, captcha and rate limiting live inside chat.php.
    foreach (array(
        'site_chat_bootstrap', 'site_chat_captcha', 'site_chat_send',
        'site_chat_poll', 'site_chat_update_identity', 'site_chat_attach',
    ) as $site_chat_action) {
        $actions[$site_chat_action] = array('file' => 'chat.php', 'handler' => 'pg_panel_site_chat', 'exempt' => true, 'token' => false, 'write' => true, 'public' => true);
    }

    // The workspace is open to its team, role 3 members included; the
    // handler checks the session and the token, the module decides the rest.
    foreach (array(
        'ws_bootstrap', 'ws_channels', 'ws_channel_open', 'ws_channel_get',
        'ws_brief_recap',
        'ws_message_seen', 'ws_scheduled_list', 'ws_scheduled_get',
        'ws_scheduled_targets', 'ws_scheduled_save', 'ws_scheduled_status',
        'ws_scheduled_run_now', 'ws_scheduled_preview', 'ws_scheduled_draft',
        'ws_scheduled_message_save', 'ws_scheduled_messages',
        'ws_scheduled_message_delete', 'ws_scheduled_message_send',
        'ws_sync', 'ws_messages_before', 'ws_send', 'ws_command_suggest', 'ws_edit', 'ws_delete',
        'ws_mark', 'ws_attach', 'ws_library_browse', 'ws_library_channels',
        'ws_library_attach', 'ws_channel_create', 'ws_templates',
        'ws_template_apply', 'ws_template_from_channel',
        'ws_channel_update', 'ws_customer_links', 'ws_channel_members_add',
        'ws_mention_invite', 'ws_channel_member_remove', 'ws_channel_join',
        'ws_channel_leave', 'ws_channel_make_public',
        'ws_channel_make_private', 'ws_channel_eras', 'ws_pin',
        'ws_pin_hide', 'ws_forward', 'ws_block_search', 'ws_block_pull',
        'ws_channel_clear', 'ws_channel_era_rename', 'ws_group_save',
        'ws_group_move', 'ws_group_remove', 'ws_group_access',
        'ws_group_access_grant', 'ws_group_access_revoke',
        'ws_channel_group', 'ws_channel_archive', 'ws_channel_audit',
        'ws_audit_list', 'ws_timeline', 'ws_home', 'ws_tick', 'ws_calc',
        'ws_notes', 'ws_note_get', 'ws_note_save', 'ws_note_delete',
        'ws_note_from_message', 'ws_note_share', 'ws_note_channel',
        'ws_note_unshare', 'ws_note_file', 'ws_note_claude',
        'ws_note_claude_deliver', 'ws_note_menu_pin', 'ws_file_office', 'ws_file_office_save',
        'ws_file_text', 'ws_file_text_save', 'ws_file_history', 'ws_channel_notify',
        'ws_channel_pin', 'ws_channel_order', 'ws_channel_summary',
        'ws_channel_decisions', 'ws_channel_files', 'ws_channel_tasks',
        'ws_search', 'ws_palette', 'ws_channel_board', 'ws_ref_search',
        'ws_record_refs', 'ws_record_fields', 'ws_record_fields_save',
        'ws_record_create',
        'ws_task_get', 'ws_task_check', 'ws_task_save',
        'ws_task_status', 'ws_task_move', 'ws_tasks', 'ws_board',
        'ws_conflicts', 'ws_calendar', 'ws_event_save', 'ws_event_get',
        'ws_event_delete', 'ws_meeting_note', 'ws_meeting_transfer', 'ws_inbox', 'ws_inbox_read', 'ws_react',
        'ws_check', 'ws_check_task', 'ws_poll_create', 'ws_poll_vote', 'ws_poll_close',
        'ws_poll_edit', 'ws_approval_create', 'ws_approval_decide',
        'ws_approval_close', 'ws_ack_request', 'ws_ack', 'ws_ack_people',
        'ws_status_facts', 'ws_status_ai', 'ws_status_post', 'ws_checkin_start',
        'ws_checkin_answer',
        'ws_task_tick', 'ws_task_item_add', 'ws_task_note_add',
        'ws_task_note_edit', 'ws_task_note_delete',
        'ws_task_recurrence_end', 'ws_channel_claude', 'ws_ai_draft_accept',
        'ws_ai_draft_dismiss', 'ws_ai_change_apply', 'ws_ai_change_dismiss',
        'ws_ai_bulk_step', 'ws_ai_design_apply', 'ws_ai_design_revert',
        'ws_ai_design_dismiss', 'ws_claude_kick', 'ws_ai_kick', 'ws_mcp_agent_kick',
        'ws_mcp_agent_cancel', 'ws_channel_ai', 'ws_guest_start', 'ws_guest_relink',
        'ws_guest_end', 'ws_channel_share', 'ws_thread_start',
        'ws_thread_join', 'ws_thread_close', 'ws_thread_rename',
        'ws_thread_leave', 'ws_share_relink', 'ws_share_end',
        'ws_task_time_start', 'ws_task_time_stop', 'ws_task_time_add',
        'ws_task_time_delete', 'ws_task_time_channel',
        'ws_task_time_invoice', 'ws_task_time_unlink', 'ws_task_time_mine',
        'ws_task_link_add', 'ws_task_link_remove', 'ws_task_delegate',
        'ws_task_delegation', 'ws_task_delegation_stop',
        'ws_task_approval_create', 'ws_approval_guest_resend',
        'ws_mcp_connect', 'ws_mcp_disconnect_me',
        'ws_flow_list', 'ws_flow_get', 'ws_flow_create', 'ws_flow_save',
        'ws_flow_publish', 'ws_flow_pause', 'ws_flow_bin',
        'ws_flow_run_start', 'ws_flow_runs_list', 'ws_flow_run_get', 'ws_flow_run_cancel',
        'ws_flow_step_mend', 'ws_flow_box_try', 'ws_flow_template_setup',
        'ws_flow_shares', 'ws_flow_share_set', 'ws_flow_share_remove',
        'ws_channel_customer', 'ws_channel_customer_history',
        'ws_channel_customer_followup',
    ) as $ws_action) {
        $actions[$ws_action] = array('file' => 'workspace.php', 'handler' => 'pg_panel_ws', 'exempt' => true, 'token' => true, 'write' => true);
    }

    return $actions;
}

/**
 * Names the general gate never stops, listed or not: a chat_*, site_chat_*
 * or ws_* action the table does not know reaches "Invalid action." rather
 * than a login error, as it always has.
 *
 * @return array
 */
function pg_panel_exempt_prefixes()
{
    return array('chat_', 'site_chat_', 'ws_');
}

/**
 * @param mixed $action
 * @return array|null the action's row, or null when it is not in the table
 */
function pg_panel_action($action)
{
    if (!is_string($action)) {
        return null;
    }

    $actions = pg_panel_actions();

    return isset($actions[$action]) ? $actions[$action] : null;
}

/**
 * What api.php's general gate asks of a request for this action:
 *   'exempt'   nothing, the handler checks for itself;
 *   'session'  a signed-in user, any role;
 *   'role'     a signed-in user with role 1 or better - also the answer for
 *              any name the table does not know.
 *
 * @param mixed $action
 * @return string
 */
function pg_panel_gate($action)
{
    $entry = pg_panel_action($action);

    if ($entry !== null) {
        if ($entry['exempt']) {
            return 'exempt';
        }

        return empty($entry['any_role']) ? 'role' : 'session';
    }

    if (is_string($action)) {
        foreach (pg_panel_exempt_prefixes() as $prefix) {
            if (strpos($action, $prefix) === 0) {
                return 'exempt';
            }
        }
    }

    return 'role';
}

/**
 * Answers a listed action and ends the request; returns false for any other
 * action so api.php can answer "Invalid action.".
 *
 * @param mixed $action
 * @param array $request
 * @return bool
 */
function pg_panel_dispatch($action, $request)
{
    $entry = pg_panel_action($action);

    if ($entry === null) {
        return false;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/panel/' . $entry['file']);

    // A write that may change what pages show makes every page the page
    // cache keeps old once the request ends (includes/fn/page_cache.php).
    if (function_exists('pg_page_cache_panel_dirties') && pg_page_cache_panel_dirties($action, $entry, is_array($request) ? $request : array())) {
        pg_page_cache_mark_dirty();
    }

    if (!function_exists($entry['handler'])) {
        respond(array('status' => 'error', 'message' => 'Invalid action.'));
    }

    $result = $entry['handler']($request, $action);

    if (is_array($result)) {
        respond($result);
    }

    exit;
}
