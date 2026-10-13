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
validate_ecommerce_access($user);

include_once('liveform.class.php');
$liveform = new liveform('view_tax_zones');

// The columns this list sorts by, in the order the table shows them.
// pg_list_state() keeps the choice in the session as a code, so it survives
// a language change, and falls back to the default for anything else.
$sort_columns = array(
    'name'          => lang('Name'),
    'tax_rate'      => lang('Tax Rate'),
    'last_modified' => lang('Last Modified'),
);

$sort_default = array('sort' => 'last_modified', 'order' => 'desc');

$list_state = pg_list_state('view_tax_zones', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// What each sort code orders by. Only codes pg_list_state() let through
// reach pg_list_order_by(), and it writes nothing but these.
$order_by = pg_list_order_by($sort_levels, array(
    'name'          => 'tax_zones.name',
    'tax_rate'      => 'tax_zones.tax_rate',
    'last_modified' => 'tax_zones.timestamp',
));

$tax_zones = db_items(
    "SELECT
        tax_zones.id,
        tax_zones.name,
        tax_zones.tax_rate,
        user.user_username AS last_modified_username,
        tax_zones.timestamp AS last_modified_timestamp
    FROM tax_zones
    LEFT JOIN user ON tax_zones.user = user.user_id
    ORDER BY $order_by");

$output_rows = '';

foreach ($tax_zones as $tax_zone) {

    $last_modified_username = '';

    if ($tax_zone['last_modified_username'] != '') {
        $last_modified_username = ' ' . lang(array('string' => 'by {var:1}', 'vars' => array(h($tax_zone['last_modified_username']))));
    }

    $output_rows .=
        '<tr>
            <td></td>
            <td class="align-middle text-start">
                <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2" title="' . lang('Edit') . '" onclick="window.location.href=\'edit_tax_zone.php?id=' . $tax_zone['id'] . '\'"><i class="bi bi-pencil"></i></button>
            </td>
            <td class="chart_label align-middle">' . h($tax_zone['name']) . '</td>
            <td class="align-middle">' . h($tax_zone['tax_rate']) . '%</td>
            <td class="align-middle">' . get_relative_time(array('timestamp' => $tax_zone['last_modified_timestamp'])) . $last_modified_username . '</td>
        </tr>';
}

echo
    pg_page_shell([
        'title'=> lang('All Tax Zones'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('All Tax Zones'),
        'heading_description' => lang('All geographic areas where your organization collects tax for any product.'),
    ]) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '

                <div class="row mb-2 flex-wrap">
                    <div class="col-12 text-center text-md-start">
                        
                        <nav id="button_bar" class="navigation" aria-label="Button Bar">
                            <a class="btn btn-sm btn-primary m-1" href="add_tax_zone.php"><span class="bi bi-plus-circle me-2"></span>' . lang('Create') . '</a>
                            <div class="pg-toolbar-grow"></div>
                            ' . pg_list_sort_menu('view_tax_zones.php', $sort_columns, $sort_levels, $sort_default) . '
                        </nav>
                    </div>
                </div>
                <div class="card my-4">
                    <div class="card-body p-0 position-relative">
                        <table class="chart table-hover table" style="width:100%;display:none">
                            <thead>
                                <tr>
                                    <th class="noVis"></th>
                                    <th class="noVis">' . lang('Action') . '</th>
                                    <th>' . get_column_heading(lang('Name'), $sort_levels, '', '', 'name') . '</th>
                                    <th>' . get_column_heading(lang('Tax Rate'), $sort_levels, '', '', 'tax_rate') . '</th>
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

$liveform->remove_form();
