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

$filter = (isset($_GET['filter']) && is_string($_GET['filter'])) ? $_GET['filter'] : '';

// Common regions are the list shown for an unknown filter, as before.
if (!isset(pg_region_list_names()[$filter])) {
    $filter = 'all_common_regions';
}

$kinds = array(
    'all_ad_regions' => 'ad',
    'all_login_regions' => 'login',
    'all_dynamic_regions' => 'dynamic',
    'all_designer_regions' => 'designer',
    'all_common_regions' => 'common',
);
$kind = $kinds[$filter];

// The fields each list changes in place.
if ($kind === 'ad') {
    $quick_edit = pg_ad_region_edit_columns();
} elseif ($kind === 'login') {
    $quick_edit = pg_login_region_edit_columns();
} else {
    $quick_edit = pg_content_region_edit_columns($kind);
}

// A value changed in place on the list (pg_list_edit_request() in
// includes/fn/output.php). Answered in JSON before anything below draws the
// screen, with the checks of the region's edit screen. Dynamic regions hold
// PHP code: administrators only, as their screens.
if (pg_list_edit_requested()) {
    $edit = pg_list_edit_request($quick_edit, function ($user) use ($kind) {
        return ($kind === 'dynamic') ? ($user['role'] < 1) : ($user['role'] <= 1);
    });

    if ($kind === 'ad') {
        $saved = pg_ad_region_quick_update($edit['id'], $edit['field'], $edit['value'], (int) $edit['user']['id']);
    } elseif ($kind === 'login') {
        $saved = pg_login_region_quick_update($edit['id'], $edit['field'], $edit['value'], (int) $edit['user']['id']);
    } else {
        $saved = pg_content_region_quick_update($kind, $edit['id'], $edit['field'], $edit['value'], (int) $edit['user']['id']);
    }

    pg_list_edit_reply($saved, $saved['ok'] ? 200 : 422);
}

$user = validate_user();
validate_area_access($user, 'designer');

$liveform = new liveform('view_regions');

$list_name = pg_region_list_names()[$filter];
$create_url = '';
$list_rows = array();

// Every region of the kind is printed: the list table driver sorts and
// searches them in the browser (browser mode) and keeps a heading's sort in
// the session. The sort codes are the column keys.
switch ($kind) {
    case 'ad':
        $heading = lang('All Ad Regions');
        $subheading = lang('All ad regions that can be added to any page style and display rotating ads created by any site manager.');
        $create_url = 'add_ad_region.php';
        $columns = pg_ad_region_list_columns();

        foreach (pg_ad_region_list_rows() as $row) {
            $list_rows[] = array('id' => $row['id'], 'cells' => pg_ad_region_list_row_cells($row, $quick_edit));
        }

        break;

    case 'login':
        $heading = lang('All Login Regions');
        $subheading = lang('All login regions that add the login process to any page.');
        $create_url = 'add_login_region.php';
        $columns = pg_login_region_list_columns();

        foreach (pg_login_region_list_rows() as $row) {
            $list_rows[] = array('id' => $row['id'], 'cells' => pg_login_region_list_cells($row, $quick_edit));
        }

        break;

    default:
        $headings = array(
            'dynamic' => array(lang('All Dynamic Regions'), lang('All dynamic regions that contain PHP code and can be added to any page style.'), 'add_dynamic_region.php'),
            'designer' => array(lang('All Designer Regions'), lang('All designer regions of shared content that can be added to any page style and updated during page editing by any Site Designer.'), 'add_designer_region.php'),
            'common' => array(lang('All Common Regions'), lang('All common regions of shared content that can be added to any page style and updated during page editing by any site manager.'), 'add_common_region.php'),
        );
        list($heading, $subheading, $create_url) = $headings[$kind];
        $columns = pg_content_region_list_columns($kind);

        // Dynamic regions hold PHP code: only administrators see them and
        // add_dynamic_region.php admits administrators only.
        if (($kind !== 'dynamic') || ($user['role'] < 1)) {
            foreach (pg_content_region_list_rows($kind) as $row) {
                $list_rows[] = array('id' => $row['id'], 'cells' => pg_content_region_list_cells($kind, $row, $quick_edit));
            }
        } else {
            $create_url = '';
            $quick_edit = array();
        }
}

// The sort the list opens with: kept in the session by the list's name
// (pg_list_state(), list_table_sort), Last Modified first by default.
$sort_columns = array();

foreach ($columns as $key => $spec) {
    $sort_columns[$key] = $spec['heading'];
}

$sort_default = array('sort' => 'last_modified', 'order' => 'desc');
$list_state = pg_list_state($list_name, $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

$list = pg_list_table_printed(array(
    'list' => $list_name,
    'columns' => $columns,
    'levels' => $sort_levels,
    'rows' => $list_rows,
    'edit' => $quick_edit ? array('url' => 'view_regions.php?filter=' . $filter, 'columns' => $quick_edit) : null,
));

echo
    pg_page_shell([
        'title'=> $heading,
        'extra classes'=>'design pg-measure-list',
        'icon'=>'design',
        'heading'=>$heading,
        'heading_description' => $subheading,
    ]) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '

                ' . pg_screen_toolbar(array(
                    'primary' => ($create_url !== '') ? array('label' => lang('Create'), 'url' => $create_url, 'icon' => 'bi-plus-circle') : '',
                    'search'  => array(pg_list_table_search_box($list_name), pg_list_sort_menu('view_regions.php', $sort_columns, $sort_levels, $sort_default, array('filter' => $filter), $list_name)),
                )) . '
                ' . pg_list_table_html($list['source'], $list['page'], $heading) . '
            </div>
        </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') . '
' .
    output_footer();

$liveform->remove_form();
