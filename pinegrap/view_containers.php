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
validate_ecommerce_access($user);

include_once('liveform.class.php');
$liveform = new liveform('view_containers');

// if the sort is not set yet, then default it to empty so that the switch below falls
// through to its default case
if (isset($_SESSION['software']['ecommerce']['view_containers']['sort']) == false) {
    $_SESSION['software']['ecommerce']['view_containers']['sort'] = '';
}

switch (($_SESSION['software']['ecommerce']['view_containers']['sort'] ?? '')) {
    case lang('Name'):
        $sort_column = 'containers.name';
        break;

    case lang('Enabled'):
        $sort_column = 'containers.enabled';
        break;

    case lang('Length'):
        $sort_column = 'containers.length';
        break;

    case lang('Width'):
        $sort_column = 'containers.width';
        break;

    case lang('Height'):
        $sort_column = 'containers.height';
        break;

    case lang('Weight'):
        $sort_column = 'containers.weight';
        break;

    case lang('Cost'):
        $sort_column = 'containers.cost';
        break;

    case lang('Created'):
        $sort_column = 'containers.created_timestamp';
        break;

    case lang('Last Modified'):
        $sort_column = 'containers.last_modified_timestamp';
        break;

    default:
        $sort_column = 'containers.last_modified_timestamp';
        $_SESSION['software']['ecommerce']['view_containers']['sort'] = lang('Last Modified');
        $_SESSION['software']['ecommerce']['view_containers']['order'] = 'desc';
        break;
}

// if order is not set, set to ascending
if (isset($_SESSION['software']['ecommerce']['view_containers']['order']) == false) {
    $_SESSION['software']['ecommerce']['view_containers']['order'] = 'asc';
}

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
    ORDER BY $sort_column " . escape(($_SESSION['software']['ecommerce']['view_containers']['order'] ?? '')));

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
        $output_cost = BASE_CURRENCY_SYMBOL . number_format($container['cost'], 2);
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
                <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2" data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'edit_container.php?id=' . $container['id'] . '\'"><i class="bi bi-pencil"></i></button>
                <!--<button type="button" class="m-1 btn-data-control btn btn-outline-danger border-2" data-loading-content=" " title="' . lang('Delete') . '"><i class="material-icons">delete</i></button>-->
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
        'heading'=>lang('All Containers')
    ]) . '
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '

                <div class="row mb-2 flex-wrap">
                    <div class="col-12 text-center text-md-start">
                        <h2 class="d-inline-block" data-bs-content="' . lang('All shipping containers (e.g. boxes) that products are packaged in.') . '" title="' . lang('All Containers') . '">' . lang('All Containers') . '</h2>
                        <nav id="button_bar" class="navigation" aria-label="Button Bar">
                            <a class="btn btn-sm btn-primary m-1" href="add_container.php" data-loading-content="' . lang('Loading') . '"><span class="bi bi-plus-circle me-2"></span>' . lang('Create') . '</a>
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
                                    <th>' . get_column_heading(lang('Name'), ($_SESSION['software']['ecommerce']['view_containers']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_containers']['order'] ?? '')) . '</th>
                                    <th class="text-center">' . get_column_heading(lang('Enabled'), ($_SESSION['software']['ecommerce']['view_containers']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_containers']['order'] ?? '')) . '</th>
                                    <th>' . get_column_heading(lang('Length'), ($_SESSION['software']['ecommerce']['view_containers']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_containers']['order'] ?? '')) . '</th>
                                    <th>' . get_column_heading(lang('Width'), ($_SESSION['software']['ecommerce']['view_containers']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_containers']['order'] ?? '')) . '</th>
                                    <th>' . get_column_heading(lang('Height'), ($_SESSION['software']['ecommerce']['view_containers']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_containers']['order'] ?? '')) . '</th>
                                    <th>' . get_column_heading(lang('Weight'), ($_SESSION['software']['ecommerce']['view_containers']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_containers']['order'] ?? '')) . '</th>
                                    <th class="text-end">' . get_column_heading(lang('Cost'), ($_SESSION['software']['ecommerce']['view_containers']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_containers']['order'] ?? '')) . '</th>
                                    <th>' . get_column_heading(lang('Created'), ($_SESSION['software']['ecommerce']['view_containers']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_containers']['order'] ?? '')) . '</th>
                                    <th>' . get_column_heading(lang('Last Modified'), ($_SESSION['software']['ecommerce']['view_containers']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_containers']['order'] ?? '')) . '</th>
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
