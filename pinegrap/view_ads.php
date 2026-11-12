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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/region_list.php');

// A value changed in place on the list (pg_list_edit_request() in
// includes/fn/output.php), answered in JSON before the screen is drawn and
// checked as the ad's screen checks it; a user role changes only the ads of
// its own ad regions and moves them only into those.
if (pg_list_edit_requested()) {
    $edit_regions = (defined('USER_LOGGED_IN') && USER_LOGGED_IN) ? pg_ad_list_regions(validate_user()) : array();
    $edit = pg_list_edit_request(pg_ad_edit_columns($edit_regions), function ($user) use ($edit_regions) {
        return ($user['role'] < 3) || (count($edit_regions) > 0);
    });

    $saved = pg_ad_quick_update($edit['id'], $edit['field'], $edit['value'], $edit['user'], $edit_regions);

    pg_list_edit_reply($saved, $saved['ok'] ? 200 : 422);
}

$user = validate_user();

// if user has a user role and if they do not have access to edit any ad regions, output error
if (($user['role'] == 3) && (count(get_items_user_can_edit('ad_regions', $user['id'])) == 0)) {
    log_activity(lang('access denied because user does not have access to ads'), $_SESSION['sessionusername']);
    output_error(lang('Access denied.'));
}

include_once('liveform.class.php');
$liveform = new liveform('view_ads');

// The ad region filter is kept in the session, so the list opens on the
// region picked last. The sort is kept by pg_list_state() below.
if (isset($_REQUEST['ad_region_id']) && is_string($_REQUEST['ad_region_id'])) {
    $_SESSION['software']['ads']['view_ads']['ad_region_id'] = trim($_REQUEST['ad_region_id']);
}

// if ad region is not set yet, set default to [All]
if (isset($_SESSION['software']['ads']['view_ads']['ad_region_id']) == false) {
    $_SESSION['software']['ads']['view_ads']['ad_region_id'] = '[All]';
}

$sql_join = '';
$where = '';

// if user is a user role, then prepare sql join and where
if ($user['role'] == 3) {
    $sql_join = 'LEFT JOIN users_ad_regions_xref ON ad_regions.id = users_ad_regions_xref.ad_region_id';
    $where = "WHERE users_ad_regions_xref.user_id = '" . escape($user['id']) . "'";
}

// get all ad regions in order to prepare options for ad region pick list
$query = 
    "SELECT
        ad_regions.id,
        ad_regions.name
    FROM ad_regions
    $sql_join
    $where
    ORDER BY name ASC";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');

$ad_regions = array();

// loop through all ad regions in order to add ad regions to array
while ($row = mysqli_fetch_assoc($result)){
    $ad_regions[] = $row;
}

// A remembered region that is gone or out of reach goes back to [All].
$_SESSION['software']['ads']['view_ads']['ad_region_id'] = pg_list_filter_known(
    $_SESSION['software']['ads']['view_ads']['ad_region_id'],
    array_column($ad_regions, 'id'));

$output_ad_region_options = '';

// loop through all ad regions in order to prepare options for ad region pick list
foreach ($ad_regions as $ad_region) {
    // if this ad region is equal to the selected ad region
    if ($ad_region['id'] == ($_SESSION['software']['ads']['view_ads']['ad_region_id'] ?? '')) {
        $selected = ' selected="selected"';
    } else {
        $selected = '';
    }
    
    // get the number of ads that are assigned to this ad region
    $query = "SELECT COUNT(*) FROM ads WHERE ad_region_id = ?";
    $result = db_q($query, array((string) $ad_region['id'])) or output_error('Query failed.');
    $row = mysqli_fetch_row($result);
    $number_of_ads = $row[0];
    
    $output_ad_region_options .= '<option value="' . $ad_region['id'] . '"' . $selected . '>' . h($ad_region['name']) . ' (' . pg_format_number($number_of_ads, 0) . ')</option>';
}

// The sort codes are the list's column keys, kept in the session by
// pg_list_state() and by a heading click (list_table_sort).
$quick_edit = pg_ad_edit_columns(pg_ad_list_regions($user));
$columns = pg_ad_list_columns($quick_edit);
$sort_columns = array();

foreach ($columns as $key => $spec) {
    $sort_columns[$key] = $spec['heading'];
}

$sort_default = array('sort' => 'last_modified', 'order' => 'desc');

$list_state = pg_list_state('view_ads', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

$conditions = array();

// A user role sees the ads of its own ad regions.
if ($user['role'] == 3) {
    $conditions[] = "ads.ad_region_id IN (SELECT ad_region_id FROM users_ad_regions_xref WHERE user_id = '" . (int) $user['id'] . "')";
}

$all_ads = (int) db_value("SELECT COUNT(*) FROM ads" . ($conditions ? " WHERE " . implode(' AND ', $conditions) : ''));

if (($_SESSION['software']['ads']['view_ads']['ad_region_id'] ?? '') != '[All]') {
    $conditions[] = "ads.ad_region_id = '" . e((string) $_SESSION['software']['ads']['view_ads']['ad_region_id']) . "'";
}

// Every ad of the region picked is printed: the list table driver sorts and
// searches them in the browser (browser mode).
$list_rows = array();

foreach ((db_items(pg_ad_list_select() . ($conditions ? " WHERE " . implode(' AND ', $conditions) : '') . " ORDER BY ads.name") ?: array()) as $ad) {
    $list_rows[] = array('id' => $ad['id'], 'cells' => pg_ad_list_cells($ad, $quick_edit));
}

$list = pg_list_table_printed(array(
    'list' => 'view_ads',
    'columns' => $columns,
    'levels' => $sort_levels,
    'rows' => $list_rows,
    'edit' => array('url' => 'view_ads.php', 'columns' => $quick_edit),
));

print
    pg_page_shell([
        'title'=> lang('All My Ads'),
        'extra classes'=>'ads pg-measure-list',
        'icon'=>'ads',
        'heading'=>lang('All My Ads'),
        'heading_description' => lang('All shared content that can be rotated on one or more pages that I can edit.'),
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
               
                ' . pg_screen_toolbar(array(
                    'primary' => array('label' => lang('Create'), 'url' => 'add_ad.php', 'icon' => 'bi-plus-circle'),
                    'search'  => array(
                        pg_list_table_search_box('view_ads'),
                        pg_list_sort_menu('view_ads.php', $sort_columns, $sort_levels, $sort_default),
                        // The ad region filter: its options carry counts, so the
                        // form is drawn here rather than by pg_filter_select().
                        '<form id="search_form" action="view_ads.php" method="get" class="pg-toolbar-filter disable_shortcut">
                            <div class="input-group input-group-sm w-auto flex-nowrap">
                                <label class="input-group-text" title="' . h(lang('Ad Region')) . '" for="ad_region_id"><i class="bi bi-eye" aria-hidden="true"></i></label>
                                <select id="ad_region_id" aria-label="' . h(lang('Ad Region')) . '" name="ad_region_id" class="form-control form-control-sm w-auto" onchange="this.form.submit()"><option value="[All]">[' . lang('All') . '] (' . pg_format_number($all_ads, 0) . ')</option>' . $output_ad_region_options . '</select>
                            </div>
                            <noscript><button type="submit" class="btn btn-sm btn-outline theme-secondary">' . h(lang('Apply')) . '</button></noscript>
                        </form>',
                    ),
                )) . '
                ' . pg_list_table_html($list['source'], $list['page'], lang('All My Ads')) . '
            </div>
        </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') . '
' .
    output_footer();

$liveform->remove_form();
?>