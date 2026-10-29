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
$liveform = new liveform('view_containers');

// The columns this list sorts by, in the order the table shows them.
// pg_list_state() keeps the choice in the session as a code, so it survives
// a language change, and falls back to the default for anything else.
$sort_columns = array(
    'name'          => lang('Name'),
    'enabled'       => lang('Enabled'),
    'length'        => lang('Length'),
    'width'         => lang('Width'),
    'height'        => lang('Height'),
    'weight'        => lang('Weight'),
    'cost'          => lang('Cost'),
    'created'       => lang('Created'),
    'last_modified' => lang('Last Modified'),
);

$sort_default = array('sort' => 'last_modified', 'order' => 'desc');

$list_state = pg_list_state('view_containers', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// What each sort code orders by. Only codes pg_list_state() let through
// reach pg_list_order_by(), and it writes nothing but these.
$order_by = pg_list_order_by($sort_levels, array(
    'name'          => 'containers.name',
    'enabled'       => 'containers.enabled',
    'length'        => 'containers.length',
    'width'         => 'containers.width',
    'height'        => 'containers.height',
    'weight'        => 'containers.weight',
    'cost'          => 'containers.cost',
    'created'       => 'containers.created_timestamp',
    'last_modified' => 'containers.last_modified_timestamp',
));

$containers = db_items(
    "SELECT
        containers.id,
        containers.name,
        containers.enabled,
        containers.length,
        containers.width,
        containers.height,
        containers.weight,
        containers.cost / 100 AS cost,
        created_user.user_username AS created_username,
        containers.created_timestamp,
        last_modified_user.user_username AS last_modified_username,
        containers.last_modified_timestamp
    FROM containers
    LEFT JOIN user AS created_user ON containers.created_user_id = created_user.user_id
    LEFT JOIN user AS last_modified_user ON containers.last_modified_user_id = last_modified_user.user_id
    ORDER BY $order_by");

$output_rows = '';

$output_checkmark = '<span class="material-icons">task_alt</span>';

foreach ($containers as $container) {

    $output_enabled = '';

    if ($container['enabled']) {
        $output_enabled = $output_checkmark;
    }

    $output_length = '';

    if ($container['length'] > 0) {
        $output_length = ($container['length'] * 1) . '&Prime;';
    }

    $output_width = '';

    if ($container['width'] > 0) {
        $output_width = ($container['width'] * 1) . '&Prime;';
    }

    $output_height = '';

    if ($container['height'] > 0) {
        $output_height = ($container['height'] * 1) . '&Prime;';
    }

    $output_weight = '';

    if ($container['weight'] > 0) {
        $output_weight = ($container['weight'] * 1) . ' lb';
    }

    $output_cost = '';

    if ($container['cost'] > 0) {
        $output_cost = pg_format_money($container['cost'], BASE_CURRENCY_SYMBOL);
    }

    $created_username = '';

    if ($container['created_username'] != '') {
        $created_username = ' ' . lang(array('string' => 'by {var:1}', 'vars' => array(h($container['created_username']))));
    }

    $last_modified_username = '';

    if ($container['last_modified_username'] != '') {
        $last_modified_username = ' ' . lang(array('string' => 'by {var:1}', 'vars' => array(h($container['last_modified_username']))));
    }

    $output_rows .=
        '<tr>
            <td></td>
            <td class="align-middle text-start">
                <button type="button" class="m-1 btn-data-control btn btn-outline theme-primary border-2" title="' . lang('Edit') . '" onclick="window.location.href=\'edit_container.php?id=' . $container['id'] . '\'"><i class="bi bi-pencil"></i></button>
                <!--<button type="button" class="m-1 btn-data-control btn btn-outline theme-danger border-2" title="' . lang('Delete') . '"><i class="material-icons">delete</i></button>-->
            </td>
            <td class="chart_label align-middle ' . ($container['enabled'] ? 'status_enabled' : 'status_disabled') . '">' . h($container['name']) . '</td>
            <td class="align-middle text-center">' . $output_enabled . '</td>
            <td class="align-middle">' . $output_length . '</td>
            <td class="align-middle">' . $output_width . '</td>
            <td class="align-middle">' . $output_height . '</td>
            <td class="align-middle">' . $output_weight . '</td>
            <td class="align-middle text-end">' . $output_cost . '</td>
            <td class="align-middle">' . get_relative_time(array('timestamp' => $container['created_timestamp'])) . $created_username . '</td>
            <td class="align-middle">' . get_relative_time(array('timestamp' => $container['last_modified_timestamp'])) . $last_modified_username . '</td>
        </tr>';
}

echo
    pg_page_shell([
        'title'=> lang('All Containers'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('All Containers'),
        'heading_description' => lang('All shipping containers (e.g. boxes) that products are packaged in.'),
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
                            <a class="btn btn-sm btn-solid theme-primary m-1" href="add_container.php"><span class="bi bi-plus-circle me-3"></span>' . lang('Create') . '</a>
                            <div class="pg-toolbar-grow"></div>
                            ' . pg_list_sort_menu('view_containers.php', $sort_columns, $sort_levels, $sort_default) . '
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
                                    <th>' . get_column_heading(lang('Name'), $sort_levels, '', '', 'name') . '</th>
                                    <th class="text-center">' . get_column_heading(lang('Enabled'), $sort_levels, '', '', 'enabled') . '</th>
                                    <th>' . get_column_heading(lang('Length'), $sort_levels, '', '', 'length') . '</th>
                                    <th>' . get_column_heading(lang('Width'), $sort_levels, '', '', 'width') . '</th>
                                    <th>' . get_column_heading(lang('Height'), $sort_levels, '', '', 'height') . '</th>
                                    <th>' . get_column_heading(lang('Weight'), $sort_levels, '', '', 'weight') . '</th>
                                    <th class="text-end">' . get_column_heading(lang('Cost'), $sort_levels, '', '', 'cost') . '</th>
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
