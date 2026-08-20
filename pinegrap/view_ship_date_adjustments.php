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
$liveform = new liveform('view_ship_date_adjustments');

// if the sort is not set yet, then default it to empty so that the switch below falls
// through to its default case
if (isset($_SESSION['software']['ecommerce']['view_ship_date_adjustments']['sort']) == false) {
    $_SESSION['software']['ecommerce']['view_ship_date_adjustments']['sort'] = '';
}

switch (($_SESSION['software']['ecommerce']['view_ship_date_adjustments']['sort'] ?? '')) {
    case lang('Zip Code Prefix'):
        $sort_column = 'ship_date_adjustments.zip_code_prefix';
        break;

    case lang('Shipping Method'):
        $sort_column = 'shipping_methods.name';
        break;

    case lang('Adjustment'):
        $sort_column = 'ship_date_adjustments.adjustment';
        break;

    case lang('Created'):
        $sort_column = 'ship_date_adjustments.created_timestamp';
        break;

    case lang('Last Modified'):
        $sort_column = 'ship_date_adjustments.last_modified_timestamp';
        break;

    default:
        $sort_column = 'ship_date_adjustments.last_modified_timestamp';
        $_SESSION['software']['ecommerce']['view_ship_date_adjustments']['sort'] = lang('Last Modified');
        $_SESSION['software']['ecommerce']['view_ship_date_adjustments']['order'] = 'desc';
        break;
}

// if order is not set, set to ascending
if (isset($_SESSION['software']['ecommerce']['view_ship_date_adjustments']['order']) == false) {
    $_SESSION['software']['ecommerce']['view_ship_date_adjustments']['order'] = 'asc';
}

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
    ORDER BY $sort_column " . escape(($_SESSION['software']['ecommerce']['view_ship_date_adjustments']['order'] ?? '')));

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
                <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2" data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'edit_ship_date_adjustment.php?id=' . $item['id'] . '\'"><i class="bi bi-pencil"></i></button>
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
        'heading'=>lang('All Ship Date Adjustments')
    ]) . '
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '

                <div class="row mb-2 flex-wrap">
                    <div class="col-12 text-center text-md-start">
                        <h2 class="d-inline-block" data-bs-content="' . lang('Allow a ship date for a recipient to be adjusted for a particular zip code prefix and shipping method.') . '" title="' . lang('All Ship Date Adjustments') . '">' . lang('All Ship Date Adjustments') . '</h2>
                        <nav id="button_bar" class="navigation" aria-label="Button Bar">
                            <a class="btn btn-sm btn-primary m-1" href="add_ship_date_adjustment.php" data-loading-content="' . lang('Loading') . '"><span class="bi bi-plus-circle me-2"></span>' . lang('Create') . '</a>
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
                                    <th>' . get_column_heading(lang('Zip Code Prefix'), ($_SESSION['software']['ecommerce']['view_ship_date_adjustments']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_ship_date_adjustments']['order'] ?? '')) . '</th>
                                    <th>' . get_column_heading(lang('Shipping Method'), ($_SESSION['software']['ecommerce']['view_ship_date_adjustments']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_ship_date_adjustments']['order'] ?? '')) . '</th>
                                    <th>' . get_column_heading(lang('Adjustment'), ($_SESSION['software']['ecommerce']['view_ship_date_adjustments']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_ship_date_adjustments']['order'] ?? '')) . '</th>
                                    <th>' . get_column_heading(lang('Created'), ($_SESSION['software']['ecommerce']['view_ship_date_adjustments']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_ship_date_adjustments']['order'] ?? '')) . '</th>
                                    <th>' . get_column_heading(lang('Last Modified'), ($_SESSION['software']['ecommerce']['view_ship_date_adjustments']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_ship_date_adjustments']['order'] ?? '')) . '</th>
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
