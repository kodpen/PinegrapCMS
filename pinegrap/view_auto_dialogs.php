<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
validate_area_access($user, 'manager');

$liveform = new liveform('view_auto_dialogs');

// The columns this list sorts by, in the order the table shows them.
// pg_list_state() keeps the choice in the session as a code, so it survives
// a language change, and falls back to the default for anything else.
$sort_columns = array(
    'name'          => lang('Name'),
    'enabled'       => lang('Enabled'),
    'url'           => lang('URL'),
    'width'         => lang('Width'),
    'height'        => lang('Height'),
    'delay'         => lang('Delay'),
    'frequency'     => lang('Frequency'),
    'page'          => lang('Only on Page'),
    'created'       => lang('Created'),
    'last_modified' => lang('Last Modified'),
);

$sort_default = array('sort' => 'last_modified', 'order' => 'desc');

$list_state = pg_list_state('view_auto_dialogs', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// What each sort code orders by. Only codes pg_list_state() let through
// reach pg_list_order_by(), and it writes nothing but these.
$order_by = pg_list_order_by($sort_levels, array(
    'name'          => 'auto_dialogs.name',
    'enabled'       => 'auto_dialogs.enabled',
    'url'           => 'auto_dialogs.url',
    'width'         => 'auto_dialogs.width',
    'height'        => 'auto_dialogs.height',
    'delay'         => 'auto_dialogs.delay',
    'frequency'     => 'auto_dialogs.frequency',
    'page'          => 'auto_dialogs.page',
    'created'       => 'auto_dialogs.created_timestamp',
    'last_modified' => 'auto_dialogs.last_modified_timestamp',
));

$all_auto_dialogs = db_value("SELECT COUNT(*) FROM auto_dialogs");

// Get all auto dialogs.
$auto_dialogs = db_items(
    "SELECT
        auto_dialogs.id,
        auto_dialogs.name,
        auto_dialogs.enabled,
        auto_dialogs.url,
        auto_dialogs.width,
        auto_dialogs.height,
        auto_dialogs.delay,
        auto_dialogs.frequency,
        auto_dialogs.page,
        created_user.user_username AS created_username,
        auto_dialogs.created_timestamp,
        last_modified_user.user_username AS last_modified_username,
        auto_dialogs.last_modified_timestamp
    FROM auto_dialogs
    LEFT JOIN user AS created_user ON auto_dialogs.created_user_id = created_user.user_id
    LEFT JOIN user AS last_modified_user ON auto_dialogs.last_modified_user_id = last_modified_user.user_id
    ORDER BY $order_by");

echo pg_page_shell(
    array(
        'title'=> lang('Auto Dialogs'),
        'extra classes'=>'page',
        'icon'=>'page',
        'heading'=>lang('Auto Dialogs'),
        'heading_description' => lang('Pop-up dialogs shown to visitors'),
    )
);

$home_page_name = db_value("SELECT page_name FROM page WHERE page_home = 'yes' ORDER BY page_timestamp DESC LIMIT 1");

require('includes/templates/view_auto_dialogs.php');

echo output_footer();

$liveform->remove_form();

?>