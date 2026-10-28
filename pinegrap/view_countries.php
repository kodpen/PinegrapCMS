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

// only ever appended to further below, so it has to start out empty
$output_rows = '';
$user = validate_user();
validate_ecommerce_access($user);

$number_of_results = 0;

// The columns this list sorts by, in the order the table shows them.
// pg_list_state() keeps the choice in the session as a code, so it survives
// a language change, and falls back to the default for anything else.
$sort_columns = array(
    'name'               => lang('Name'),
    'code'               => lang('Code'),
    'zip_code_required'  => lang('Zip Code Required'),
    'transit_adjustment' => lang('Transit Adjustment'),
    'default'            => lang('Default'),
    'last_modified'      => lang('Last Modified'),
);

$sort_default = array('sort' => 'name', 'order' => 'asc');

$list_state = pg_list_state('view_countries', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// What each sort code orders by. Only codes pg_list_state() let through
// reach pg_list_order_by(), and it writes nothing but these.
$order_by = pg_list_order_by($sort_levels, array(
    'name'               => 'countries.name',
    'code'               => 'countries.code',
    'zip_code_required'  => 'countries.zip_code_required',
    'transit_adjustment' => 'countries.transit_adjustment_days',
    'default'            => 'countries.default_selected',
    'last_modified'      => 'countries.timestamp',
));

$query = "SELECT
            countries.id,
            countries.name,
            countries.code,
            countries.zip_code_required,
            countries.transit_adjustment_days,
            countries.default_selected,
            user.user_username as user,
            countries.timestamp
        FROM countries
        LEFT JOIN user ON countries.user = user.user_id
        ORDER BY $order_by";

$result = mysqli_query(db::$con, $query) or output_error('Query failed.');

$country_rows = array();

while ($row = mysqli_fetch_array($result)) {
    // The name in the panel's language.
    $row['label'] = pg_geo_country_label($row['name'], $row['code']);
    $country_rows[] = $row;
}

// Sorted by name first: by the name on screen in the panel language's
// order, not by the stored English name the query orders by ("Türkiye"
// among the T's, not where "Turkey" was).
if ((($sort_levels[0]['sort'] ?? '') === 'name') && $country_rows) {
    $country_rows = pg_geo_sort_by_label($country_rows);

    if (($sort_levels[0]['order'] ?? 'asc') === 'desc') {
        $country_rows = array_reverse($country_rows);
    }
}

foreach ($country_rows as $row) {
    $id = $row['id'];
    $name = h($row['label']);
    $code = h($row['code']);
    $zip_code_required = $row['zip_code_required'];
    $transit_adjustment_days = $row['transit_adjustment_days'];
    $default_selected = $row['default_selected'];
    $username = $row['user'];
    $timestamp = $row['timestamp'];

    $zip_code_required_check_mark = '';

    if ($zip_code_required) {
        $zip_code_required_check_mark = '<span class="material-icons">task_alt</span>';
    }

    if ($row['default_selected']) {
        $default_selected = '<span class="material-icons">task_alt</span>';
    } else {
        $default_selected = '';
    }
    $output_link_url = 'edit_country.php?id=' . $id;
    
    $number_of_results++;
    
    $output_rows .= '
        <tr>
            <td class="align-middle text-start action-buttons">
                <button type="button" class="m-1 btn-data-control btn btn-outline theme-primary border-2 " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-pencil"></i></button>
            </td>
            <td class="align-middle chart_label">' . $name . '</td>
            <td class="align-middle">' . $code . '</td>
            <td style="text-align: center">' . $zip_code_required_check_mark . '</td>
            <td style="text-align: right;">' . $transit_adjustment_days . '</td>
            <td style="text-align: center">' . $default_selected . '</td>
            <td class="align-middle">' . get_relative_time(array('timestamp' => $timestamp)) . ' ' . lang(array('string'=>'by {var:1}','vars'=>array( h($username) ) ) ) . '</td>
        </tr>';
}

echo
    pg_page_shell([
        'title'=> lang('All Countries'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('All Countries'),
        'heading_description' => lang('All countries that are valid for billing address and shipping address selection.'),
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
            <div class="col-12">
                <div class="row mb-3  flex-wrap">
                    <div class="col-12 text-center md:text-start">
                        
                        <nav id="button_bar" class="navigation " aria-label="Button Bar">
                            <a class="btn btn-sm btn-solid theme-primary m-1 " href="add_country.php"><span class="bi bi-plus-circle me-3"></span>' . lang(array('string'=>'Create') ) . '</a>
                            <div class="pg-toolbar-grow"></div>
                            ' . pg_list_sort_menu('view_countries.php', $sort_columns, $sort_levels, $sort_default) . '
                        </nav>
                    </div>
                </div>
                <div class="card my-7">
                    <div class="card-body p-0 position-relative">
                        <table class="chart table-hover table" style="width:100%;display:none">
                            <thead>
                                <tr>
                                    <th class="noVis">' . lang(array('string'=>'Action') ) . '</th>
                                    <th>' . get_column_heading(lang('Name'), $sort_levels, '', '', 'name') . '</th>
                                    <th>' . get_column_heading(lang('Code'), $sort_levels, '', '', 'code') . '</th>
                                    <th style="text-align: center">' . get_column_heading(lang('Zip Code Required'), $sort_levels, '', '', 'zip_code_required') . '</th>
                                    <th style="text-align: right;">' . get_column_heading(lang('Transit Adjustment'), $sort_levels, '', '', 'transit_adjustment') . '</th>
                                    <th style="text-align: center">' . get_column_heading(lang('Default'), $sort_levels, '', '', 'default') . '</th>
                                    <th>' . get_column_heading(lang('Last Modified'), $sort_levels, '', '', 'last_modified') . '</th>
                                </tr>
                            </thead>
                            <tbody>' . $output_rows . '</tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    
</main>' .
output_footer();