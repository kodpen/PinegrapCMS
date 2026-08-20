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
 *              2016–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
validate_area_access($user, 'manager');

$liveform = new liveform('view_auto_dialogs');

// store all values collected in request to session
foreach ($_REQUEST as $key => $value) {
    // if the value is a string then add it to the session
    // we have to do this check because cookie arrays are sometimes included in the $_REQUEST array,
    // for certain php.ini settings
    if (is_string($value) == TRUE) {
        $_SESSION['software']['view_auto_dialogs'][$key] = trim($value);
    }
}

// if the sort is not set yet, then default it to empty so that the switch below falls
// through to its default case
if (isset($_SESSION['software']['view_auto_dialogs']['sort']) == false) {
    $_SESSION['software']['view_auto_dialogs']['sort'] = '';
}

switch (($_SESSION['software']['view_auto_dialogs']['sort'] ?? '')) {
    case lang('Name'):
        $sort_column = 'auto_dialogs.name';
        break;

    case lang('Enabled'):
        $sort_column = 'auto_dialogs.enabled';
        break;

    case lang('URL'):
        $sort_column = 'auto_dialogs.url';
        break;

    case lang('Width'):
        $sort_column = 'auto_dialogs.width';
        break;

    case lang('Height'):
        $sort_column = 'auto_dialogs.height';
        break;

    case lang('Delay'):
        $sort_column = 'auto_dialogs.delay';
        break;

    case lang('Frequency'):
        $sort_column = 'auto_dialogs.frequency';
        break;

    case lang('Only on Page'):
        $sort_column = 'auto_dialogs.page';
        break;

    case lang('Created'):
        $sort_column = 'auto_dialogs.created_timestamp';
        break;

    case lang('Last Modified'):
        $sort_column = 'auto_dialogs.last_modified_timestamp';
        break;

    default:
        $sort_column = 'auto_dialogs.last_modified_timestamp';
        $_SESSION['software']['view_auto_dialogs']['sort'] = lang('Last Modified');
        $_SESSION['software']['view_auto_dialogs']['order'] = 'desc';
        break;
}

// if order is not set, set to ascending
if (isset($_SESSION['software']['view_auto_dialogs']['order']) == false) {
    $_SESSION['software']['view_auto_dialogs']['order'] = 'asc';
}

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
    ORDER BY $sort_column " . e(($_SESSION['software']['view_auto_dialogs']['order'] ?? '')));

echo pg_page_shell(
    array(
        'title'=> lang('Auto Dialogs'),
        'extra classes'=>'page',
        'icon'=>'page',
        'heading'=>lang('Auto Dialogs'),
        'auto_main'=>false,
    )
);

$home_page_name = db_value("SELECT page_name FROM page WHERE page_home = 'yes' ORDER BY page_timestamp DESC LIMIT 1");

require('assets/templates/view_auto_dialogs.php');

echo output_footer();

$liveform->remove_form();

?>