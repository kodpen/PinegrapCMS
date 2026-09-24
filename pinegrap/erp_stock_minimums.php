<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - minimum stock: the lowest count each product should have before it
 * is bought again, and the products at or below it now.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
if (!validate_erp_access($user)) {
    exit();
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');
include_once('liveform.class.php');
$liveform = new liveform('erp_stock_minimums');

$readonly = defined('USER_ERP_READONLY') && USER_ERP_READONLY;
$search = mb_substr(trim((string) ($_GET['search'] ?? '')), 0, 100);
$show = in_array((string) ($_GET['show'] ?? ''), array('set', 'low'), true) ? (string) $_GET['show'] : 'all';
$query = http_build_query(array_filter(array('search' => $search, 'show' => ($show !== 'all') ? $show : '')));
$self = PATH . SOFTWARE_DIRECTORY . '/erp_stock_minimums.php' . (($query !== '') ? '?' . $query : '');

if ($_POST) {
    validate_token_field();

    $saved = erp_stock_minimums_save((array) ($_POST['minimum'] ?? array()), (int) $user['id']);

    if (!$saved['success']) {
        $liveform->mark_error('_error', h($saved['error']));
        go($self);
    }

    if ($saved['changed'] > 0) {
        log_activity(lang(array('string' => 'erp minimum stock was changed for {var:1} product(s)', 'vars' => (int) $saved['changed'])), $_SESSION['sessionusername']);
    }

    $liveform->add_notice(h(lang(array('string' => 'Minimum stock saved for {var:1} product(s).', 'vars' => (int) $saved['changed']))));
    go($self);
}

$ready = erp_stock_minimums_ready();

if (!$ready) {
    $liveform->mark_error('', lang('Minimum stock comes with the software update; run the update to use it.'));
}

$rows = $ready ? erp_stock_minimum_rows(array('search' => $search, 'show' => $show, 'limit' => 500)) : array();
$low_count = erp_stock_low_count();

$output_rows = '';

foreach ($rows as $row) {
    $product_id = (int) $row['product_id'];
    $quantity = (int) $row['inventory_quantity'];
    $minimum = (int) $row['min_quantity'];
    $low = ($minimum > 0) && ($quantity <= $minimum);
    $enabled = ((string) $row['enabled'] === '1');

    $output_rows .= '
        <tr' . ($enabled ? '' : ' class="opacity-75"') . '>
            <td class="align-middle">' . h(erp_stock_product_label($row))
                . (((trim((string) $row['product_sku']) !== '') && (trim((string) $row['product_sku']) !== trim(erp_stock_product_label($row)))) ? '<div class="small text-body-secondary font-monospace">' . h((string) $row['product_sku']) . '</div>' : '')
                . ($enabled ? '' : '<span class="badge text-bg-secondary">' . lang('Not on sale') . '</span>') . '</td>
            <td class="align-middle text-end ' . ($low ? 'text-danger fw-bold' : '') . '" data-sort="' . $quantity . '">' . h(erp_quantity_text($quantity))
                . ($low ? ' <span class="badge text-bg-danger">' . lang('Low') . '</span>' : '') . '</td>
            <td class="align-middle" style="width:9rem" data-sort="' . $minimum . '">
                <input type="text" class="form-control form-control-sm text-end" name="minimum[' . $product_id . ']" value="' . (($minimum > 0) ? $minimum : '') . '" data-initial="' . (($minimum > 0) ? $minimum : '') . '" inputmode="numeric" maxlength="7" placeholder="—" aria-label="' . h(lang('Minimum')) . '"' . ($readonly ? ' disabled' : '') . ' />
            </td>
            <td class="align-middle text-end" style="width:7rem">
                <a class="btn btn-sm btn-outline-secondary" href="erp_stock.php?product_id=' . $product_id . '#erp_stock_moves" title="' . h(lang('Stock movements')) . '"><i class="bi bi-list-ul" aria-hidden="true"></i></a>
            </td>
        </tr>';
}

if ($output_rows === '') {
    $output_rows = '<tr data-pg-sort-fixed><td colspan="4" class="text-center text-body-secondary py-4">'
        . (($show === 'low') ? lang('No product on sale is at or below its minimum.') : lang('No product that tracks stock matches.')) . '</td></tr>';
}

$filter_link = function ($value, $label) use ($show, $search) {
    $query = http_build_query(array_filter(array('search' => $search, 'show' => ($value !== 'all') ? $value : '')));

    return '<a class="btn btn-sm ' . (($show === $value) ? 'btn-secondary' : 'btn-outline-secondary') . '" href="erp_stock_minimums.php' . (($query !== '') ? '?' . $query : '') . '">' . $label . '</a>';
};

echo
pg_page_shell([
    'title' => lang('Minimum stock'),
    'extra classes' => 'erp erp_stock',
    'icon' => 'erp',
    'heading' => lang('Minimum stock'),
    'heading_description' => lang('The lowest count each product should have before it is bought again; the ones at or below it are listed on the stock screen and the ERP dashboard.'),
    'cancel' => array('enable' => 'true', 'url' => 'erp_stock.php'),
    'breadcrumb' => array(
        array('label' => lang('Stock and cost'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_stock.php'),
        array('label' => lang('Minimum stock')),
    ),
]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12 col-xxl-9">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . lang('Button Bar') . '">
                <div class="btn-group" role="group">
                    ' . $filter_link('all', lang('All')) . '
                    ' . $filter_link('set', lang('With a minimum')) . '
                    ' . $filter_link('low', h(lang(array('string' => 'Low ({var:1})', 'vars' => $low_count)))) . '
                </div>
                <form method="get" action="erp_stock_minimums.php" class="d-flex gap-1 ms-auto" role="search">
                    ' . (($show !== 'all') ? '<input type="hidden" name="show" value="' . h($show) . '" />' : '') . '
                    <input type="search" class="form-control form-control-sm" name="search" value="' . h($search) . '" placeholder="' . h(lang('Product or code')) . '" aria-label="' . h(lang('Search')) . '" />
                    <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-search" aria-hidden="true"></i></button>
                </form>
            </nav>

            <form method="post" action="erp_stock_minimums.php' . (($query !== '') ? '?' . h($query) : '') . '" id="erp_minimum_form">
                ' . get_token_field() . '
                <div class="card my-4">
                    <div class="card-body p-0 table-responsive">
                        <table class="table table-hover align-middle mb-0" data-pg-sort>
                            <thead>
                                <tr>
                                    <th>' . lang('Product') . '</th>
                                    <th class="text-end">' . lang('Stock') . '</th>
                                    <th>' . lang('Minimum') . '</th>
                                    <th data-pg-sort="none"></th>
                                </tr>
                            </thead>
                            <tbody>' . $output_rows . '</tbody>
                        </table>
                    </div>
                    <div class="form-text px-3 pb-3">' . h(lang(array('string' => 'Products that track stock, at most {var:1} at a time; search to narrow the list. Empty is no minimum. The stock is the store\'s own count, the one orders take from.', 'vars' => 500))) . '</div>
                </div>
                ' . (!$readonly ? '<nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons">
                    <div class="container">
                        <button type="submit" class="btn my-1 btn-success"' . ($ready ? '' : ' disabled') . ' data-loading-content="' . lang(array('string' => 'Saving')) . '"><i class="bi bi-check-circle me-2" aria-hidden="true"></i>' . lang(array('string' => 'Save')) . '</button>
                    </div>
                </nav>' : '') . '
            </form>
        </div>
    </div>
</main>
<script>
// Only the minimums that changed are sent: a long list would otherwise run
// into the server\'s limit on the number of fields in one request.
document.getElementById("erp_minimum_form").addEventListener("submit", function () {
    this.querySelectorAll("input[name^=\\"minimum[\\"]").forEach(function (input) {
        if (input.value.trim() === (input.getAttribute("data-initial") || "")) {
            input.disabled = true;
        }
    });
});
</script>
' .
output_footer();

$liveform->remove_form();
