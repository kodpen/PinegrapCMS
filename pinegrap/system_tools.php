<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * System tools: the screens that sit beside Site Settings (logs, connections,
 * store lists, jobs, infrastructure), one card per group. The groups and the
 * gate of every link come from pg_settings_tool_groups()
 * (includes/settings/registry.php), the list the search palette reads too, so
 * a link is offered here exactly when its screen admits the user.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
validate_area_access($user, 'manager');

if (!defined('PG_SETTINGS_MENU')) {
    define('PG_SETTINGS_MENU', true);
}

include_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');

$system_tools_groups = pg_settings_tool_groups($user);

if (pg_settings_tool_count($system_tools_groups) === 0) {
    log_activity(lang('access denied because user attempted to access area for higher role'), $_SESSION['sessionusername']);
    output_error(lang('Access denied') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
}

echo
pg_page_shell(array(
        'title'               => lang('System tools'),
        'extra classes'       => 'setting',
        'icon'                => 'setting',
        'heading'             => lang('System tools'),
        'heading_description' => lang('Logs, connections, maintenance jobs and backups, beside Site Settings.'),
        'cancel'              => array('enable' => 'true', 'url' => pg_settings_link(), 'title' => lang('Site Settings')),
        'breadcrumb'          => array(
            array('label' => lang('Site Settings'), 'url' => pg_settings_link()),
            array('label' => lang('System tools')),
        ),
    )) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">' . pg_settings_tool_cards($system_tools_groups, PATH . SOFTWARE_DIRECTORY . '/') . '
        </div>
    </div>
</main>
' .
output_footer();
