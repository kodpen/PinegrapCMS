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
$liveform = new liveform('view_ship_date_adjustments');

// The columns this list sorts by, in the order the table shows them.
// pg_list_state() keeps the choice in the session as a code, so it survives
// a language change, and falls back to the default for anything else.
$sort_columns = array(
    'zip_code_prefix' => lang('Zip Code Prefix'),
    'shipping_method' => lang('Shipping Method'),
    'adjustment'      => lang('Adjustment'),
    'created'         => lang('Created'),
    'last_modified'   => lang('Last Modified'),
);

$sort_default = array('sort' => 'last_modified', 'order' => 'desc');

$list_state = pg_list_state('view_ship_date_adjustments', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// What each sort code orders by. Only codes pg_list_state() let through
// reach pg_list_order_by(), and it writes nothing but these.
$order_by = pg_list_order_by($sort_levels, array(
    'zip_code_prefix' => 'ship_date_adjustments.zip_code_prefix',
    'shipping_method' => 'shipping_methods.name',
    'adjustment'      => 'ship_date_adjustments.adjustment',
    'created'         => 'ship_date_adjustments.created_timestamp',
    'last_modified'   => 'ship_date_adjustments.last_modified_timestamp',
));

$ship_date_adjustments = db_items(
    "SELECT
        ship_date_adjustments.id,
        ship_date_adjustments.zip_code_prefix,
        shipping_methods.name AS shipping_method_name,
        shipping_methods.code AS shipping_method_code,
        ship_date_adjustments.adjustment,
        created_user.user_username AS created_username,
        ship_date_adjustments.created_timestamp,
        last_modified_user.user_username AS last_modified_username,
        ship_date_adjustments.last_modified_timestamp
    FROM ship_date_adjustments
    LEFT JOIN shipping_methods ON ship_date_adjustments.shipping_method_id = shipping_methods.id
    LEFT JOIN user AS created_user ON ship_date_adjustments.created_user_id = created_user.user_id
    LEFT JOIN user AS last_modified_user ON ship_date_adjustments.last_modified_user_id = last_modified_user.user_id
    ORDER BY $order_by");

$output_rows = '';

foreach ($ship_date_adjustments as $item) {

    $output_shipping_method = h($item['shipping_method_name']);

    if ($item['shipping_method_code'] != '') {
        $output_shipping_method .= ' (' . h($item['shipping_method_code']) . ')';
    }

    if ($item['adjustment'] < 0) {
        $days = abs((int)$item['adjustment']);
        $suffix = ($days > 1) ? 's' : '';
        $output_adjustment = lang(array('string' => '{var:1} day{suffix} earlier', 'vars' => array($days), 'suffix' => $suffix));
    } else {
        $days = (int)$item['adjustment'];
        $suffix = ($days > 1) ? 's' : '';
        $output_adjustment = lang(array('string' => '{var:1} day{suffix} later', 'vars' => array($days), 'suffix' => $suffix));
    }

    $created_username = '';

    if ($item['created_username'] != '') {
        $created_username = ' ' . lang(array('string' => 'by {var:1}', 'vars' => array(h($item['created_username']))));
    }

    $last_modified_username = '';

    if ($item['last_modified_username'] != '') {
        $last_modified_username = ' ' . lang(array('string' => 'by {var:1}', 'vars' => array(h($item['last_modified_username']))));
    }

    $output_rows .=
        '<tr>
            <td></td>
            <td class="align-middle text-start">
                <button type="button" class="m-1 btn-data-control btn btn-outline theme-primary border-2" title="' . lang('Edit') . '" onclick="window.location.href=\'edit_ship_date_adjustment.php?id=' . $item['id'] . '\'"><i class="bi bi-pencil"></i></button>
            </td>
            <td class="chart_label align-middle">' . h($item['zip_code_prefix']) . '</td>
            <td class="align-middle">' . $output_shipping_method . '</td>
            <td class="align-middle">' . $output_adjustment . '</td>
            <td class="align-middle">' . get_relative_time(array('timestamp' => $item['created_timestamp'])) . $created_username . '</td>
            <td class="align-middle">' . get_relative_time(array('timestamp' => $item['last_modified_timestamp'])) . $last_modified_username . '</td>
        </tr>';
}

echo
    pg_page_shell([
        'title'=> lang('All Ship Date Adjustments'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('All Ship Date Adjustments'),
        'heading_description' => lang('Allow a ship date for a recipient to be adjusted for a particular zip code prefix and shipping method.'),
    ]) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '

                <div class="row mb-3 flex-wrap">
                    <div class="col-12 text-center md:text-start">
                        
                        <nav id="button_bar" class="navigation" aria-label="' . h(lang('Button Bar')) . '">
                            <a class="btn btn-sm btn-solid theme-primary m-1" href="add_ship_date_adjustment.php"><span class="bi bi-plus-circle me-3"></span>' . lang('Create') . '</a>
                            <div class="pg-toolbar-grow"></div>
                            ' . pg_list_sort_menu('view_ship_date_adjustments.php', $sort_columns, $sort_levels, $sort_default) . '
                        </nav>
                    </div>
                </div>
                <div class="card my-7">
                    <div class="card-body p-0 position-relative">
                        <table class="chart table-hover table" style="width:100%;display:none">
                            <thead>
                                <tr>
                                    <th class="noVis"></th>
                                    <th class="noVis">' . lang('Action') . '</th>
                                    <th>' . get_column_heading(lang('Zip Code Prefix'), $sort_levels, '', '', 'zip_code_prefix') . '</th>
                                    <th>' . get_column_heading(lang('Shipping Method'), $sort_levels, '', '', 'shipping_method') . '</th>
                                    <th>' . get_column_heading(lang('Adjustment'), $sort_levels, '', '', 'adjustment') . '</th>
                                    <th>' . get_column_heading(lang('Created'), $sort_levels, '', '', 'created') . '</th>
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
