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
 *              2016–2025 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

/**
 * Variant sets — v2.
 *
 * Lists product groups whose display_type is 'select'. That value is what makes
 * a group a variant set rather than a browsable category: the catalog renders
 * it as one item with a variant picker, and every product inside it is one form
 * of the same thing.
 *
 * Editing goes to edit_product2.php, not edit_product_group.php — the set and
 * its products are one thing to the operator and are edited together.
 *
 * Every action, row or bulk, goes through variant_set_action.php. See the note
 * on the hidden form below for why the row buttons are not forms.
 *
 * Development screen. view_products.php and view_product_groups.php are
 * untouched.
 */

include('init.php');
$user = validate_user();
validate_ecommerce_access($user);

include_once('liveform.class.php');
include_once('product_builder.php');

$liveform = new liveform('view_products2');


/* ------------------------------------------------------------------ *
 * Filters
 * ------------------------------------------------------------------ */

// Remembered per user, the same way the other list screens do it, so coming
// back from an edit does not drop the filter the operator was working with.
foreach (array('status', 'parent') as $filter_key) {
    if (isset($_GET[$filter_key])) {
        $_SESSION['software']['ecommerce']['view_products2'][$filter_key] = trim($_GET[$filter_key]);
    }
}

$filter_status = isset($_SESSION['software']['ecommerce']['view_products2']['status'])
    ? $_SESSION['software']['ecommerce']['view_products2']['status']
    : '';

$filter_parent = isset($_SESSION['software']['ecommerce']['view_products2']['parent'])
    ? (int) $_SESSION['software']['ecommerce']['view_products2']['parent']
    : 0;

$where = "product_groups.display_type = 'select'";

if ($filter_status === 'enabled') {
    $where .= " AND product_groups.enabled = '1'";
} elseif ($filter_status === 'disabled') {
    $where .= " AND (product_groups.enabled = '' OR product_groups.enabled = '0')";
}

if ($filter_parent) {
    $where .= " AND product_groups.parent_id = '" . $filter_parent . "'";
}

$filters_active = ($filter_status !== '' || $filter_parent !== 0);


/* ------------------------------------------------------------------ *
 * Data
 * ------------------------------------------------------------------ */

// Variant sets with their product counts and price span in one pass. Counting
// per row in PHP would run two queries per set.
$sets = db_items(
    "SELECT
        product_groups.id,
        product_groups.name,
        product_groups.enabled,
        product_groups.short_description,
        product_groups.image_name,
        product_groups.address_name,
        product_groups.parent_id,
        product_groups.timestamp,
        parent_group.name AS parent_name,
        user.user_username AS username,
        COUNT(products.id) AS variant_count,
        MIN(products.price) AS min_price,
        MAX(products.price) AS max_price
    FROM product_groups
    LEFT JOIN product_groups AS parent_group ON product_groups.parent_id = parent_group.id
    LEFT JOIN user ON product_groups.user = user.user_id
    LEFT JOIN products_groups_xref ON products_groups_xref.product_group = product_groups.id
    LEFT JOIN products ON products_groups_xref.product = products.id
    WHERE $where
    GROUP BY
        product_groups.id,
        product_groups.name,
        product_groups.enabled,
        product_groups.short_description,
        product_groups.image_name,
        product_groups.address_name,
        product_groups.parent_id,
        product_groups.timestamp,
        parent_group.name,
        user.user_username
    ORDER BY product_groups.timestamp DESC");

// Which attributes each set varies on. One query for all of them; attaching it
// per row would be a query per set.
$attribute_labels = array();

$attribute_rows = db_items(
    "SELECT
        product_groups_attributes_xref.product_group_id,
        product_attributes.name
    FROM product_groups_attributes_xref
    INNER JOIN product_groups ON product_groups_attributes_xref.product_group_id = product_groups.id
    LEFT JOIN product_attributes ON product_groups_attributes_xref.attribute_id = product_attributes.id
    WHERE product_groups.display_type = 'select'
    ORDER BY product_groups_attributes_xref.sort_order");

foreach ($attribute_rows as $attribute_row) {
    if ($attribute_row['name'] != '') {
        $attribute_labels[$attribute_row['product_group_id']][] = $attribute_row['name'];
    }
}

// Parent groups that actually hold a variant set, so the filter never offers a
// choice that returns nothing.
$parent_options = db_items(
    "SELECT DISTINCT
        parent_group.id,
        parent_group.name
    FROM product_groups
    INNER JOIN product_groups AS parent_group ON product_groups.parent_id = parent_group.id
    WHERE product_groups.display_type = 'select'
    ORDER BY parent_group.name");


/* ------------------------------------------------------------------ *
 * Rows
 * ------------------------------------------------------------------ */

$output_rows = '';

foreach ($sets as $set) {

    $set_id   = (int) $set['id'];
    $edit_url = 'edit_product2.php?group_id=' . $set_id;

    // Thumbnail, or a neutral placeholder — a broken image tile reads as an
    // error rather than as "no image chosen yet".
    if ($set['image_name'] != '') {
        $output_image =
            '<img src="' . OUTPUT_PATH . h($set['image_name']) . '" alt="" class="rounded"
                style="width:44px;height:44px;object-fit:cover;" onerror="this.style.display=\'none\'" />';
    } else {
        $output_image =
            '<span class="d-inline-flex align-items-center justify-content-center bg-body-tertiary rounded text-muted"
                style="width:44px;height:44px;"><i class="bi bi-image"></i></span>';
    }

    $output_enabled = $set['enabled']
        ? '<span class="badge text-bg-success">' . lang('Enabled') . '</span>'
        : '<span class="badge text-bg-secondary">' . lang('Disabled') . '</span>';

    // A set with no products is not a normal state — it means the products were
    // deleted out from under the group, and it is worth surfacing rather than
    // printing a quiet zero.
    if ((int) $set['variant_count'] === 0) {
        $output_variants = '<span class="badge text-bg-warning">' . lang('No products') . '</span>';
    } else {
        $output_variants =
            '<span class="badge text-bg-primary">' .
            lang(array(
                'string' => '{var:1} variant{suffix:1}',
                'vars'   => array((int) $set['variant_count']),
                'suffix' => ((int) $set['variant_count'] === 1) ? '' : 's')) .
            '</span>';
    }

    $output_attributes = '';

    if (!empty($attribute_labels[$set['id']])) {
        foreach ($attribute_labels[$set['id']] as $attribute_label) {
            $output_attributes .= '<span class="badge text-bg-light border me-1">' . h($attribute_label) . '</span>';
        }
    } else {
        $output_attributes = '<span class="text-muted">&mdash;</span>';
    }

    // One price for the whole set when the variants agree, a span when they do
    // not — the catalog shows the same thing.
    // prepare_amount() is not escaped, deliberately. BASE_CURRENCY_SYMBOL is
    // whatever the operator typed in settings, and for most non-Latin
    // currencies that is an HTML entity — "&#8378;" for the lira. Running it
    // through h() escapes the ampersand and the page prints the entity itself
    // instead of the symbol. Every legacy screen outputs it raw for this
    // reason; the value is a setting, not visitor input.
    if ((int) $set['variant_count'] === 0) {
        $output_price = '<span class="text-muted">&mdash;</span>';
    } elseif ((int) $set['min_price'] === (int) $set['max_price']) {
        $output_price = prepare_amount(((int) $set['min_price']) / 100);
    } else {
        $output_price =
            prepare_amount(((int) $set['min_price']) / 100) . ' &ndash; ' .
            prepare_amount(((int) $set['max_price']) / 100);
    }

    $output_parent = ($set['parent_name'] != '')
        ? h($set['parent_name'])
        : '<span class="text-muted">' . lang('None') . '</span>';

    $output_username = ($set['username'] != '')
        ? ' ' . lang(array('string' => 'by {var:1}', 'vars' => array(h($set['username']))))
        : '';

    // Checkbox cell and action cell carry the class names the shared DataTable
    // wiring looks for: ".select-all" is what #select_all and the
    // multiselectCheckbox plugin bind to (shift-click ranges included), and
    // ".action-buttons" is what gets hidden while a selection is active. Naming
    // them anything else means reimplementing both.
    $output_rows .=
        '<tr>
            <td class="select-all align-middle text-start">
                <input class="form-check-input" type="checkbox" name="group_ids[]" value="' . $set_id . '" />
            </td>
            <td class="align-middle text-start action-buttons text-nowrap">
                <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2" data-loading-content=" "
                    title="' . lang('Edit') . '" onclick="window.location.href=\'' . $edit_url . '\'"><i class="bi bi-pencil"></i></button>
                <button type="button" class="m-1 btn-data-control btn btn-outline-secondary border-2" data-loading-content=" "
                    title="' . lang('Duplicate') . '" onclick="window.location.href=\'duplicate_product_group.php?id=' . $set_id . '\'"><i class="bi bi-files"></i></button>
            </td>
            <td class="chart_label align-middle">
                <div class="d-flex align-items-center gap-2">
                    ' . $output_image . '
                    <div>
                        <a href="' . $edit_url . '" class="fw-semibold text-decoration-none">' . h($set['name']) . '</a>
                        <div class="text-muted small">' . h($set['short_description']) . '</div>
                    </div>
                </div>
            </td>
            <td class="align-middle">' . $output_variants . '</td>
            <td class="align-middle">' . $output_attributes . '</td>
            <td class="align-middle">' . $output_price . '</td>
            <td class="align-middle">' . $output_parent . '</td>
            <td class="align-middle">' . $output_enabled . '</td>
            <td class="align-middle">' . get_relative_time(array('timestamp' => $set['timestamp'])) . $output_username . '</td>
        </tr>';
}

// The table is only rendered when there is something in it. DataTables would
// otherwise draw its own "no data available" row next to our empty state, and
// two different ways of saying nothing is here read as a fault.
$output_table = '';

if ($sets) {

    // The table sits inside the POST form, and every action on this screen goes
    // through it. No row ever gets a form element of its own: HTML forbids
    // nested forms and the parser drops the inner one outright, which is how the
    // per-row cancel button in view_orders.php came to do nothing for months
    // (CLAUDE.md, "Sipariş İptal — Tek Akış + Onarım").
    //
    // The bulk bar is rendered with the table rather than always: a permanently
    // disabled row of buttons under an empty screen is furniture.
    $output_table =
        '<form name="form" action="variant_set_action.php" method="post">
            ' . get_token_field() . '
            <input type="hidden" name="action" />
            <input type="hidden" name="status" />

        <table class="chart table-hover table" style="width:100%;display:none">
            <thead>
                <tr>
                    <th class="noVis">
                        <div class="form-check form-switch">
                            <input class="form-check-input" title="' . lang('Select/Deselect All') . '" type="checkbox" id="select_all" />
                        </div>
                    </th>
                    <th class="noVis">' . lang('Action') . '</th>
                    <th>' . lang('Name') . '</th>
                    <th>' . lang('Variants') . '</th>
                    <th>' . lang('Product Attributes') . '</th>
                    <th>' . lang('Unit Price') . '</th>
                    <th>' . lang('Parent Product Group') . '</th>
                    <th>' . lang('Enabled') . '</th>
                    <th>' . lang('Created') . '</th>
                </tr>
            </thead>
            <tbody>' . $output_rows . '</tbody>
        </table>

            <nav class="buttons navigation text-center position-sticky" style="bottom:.5rem;" aria-label="' . lang('Action') . '">
                <div class="container">
                    <div class="btn-group btn-group-sm flex-wrap justify-content-center mb-0 enable-on-selected">
                        <button type="button" value="Enable Selected" class="btn mb-1 mt-1 btn-secondary disabled pg-vs-status" data-pg-vs-status="enabled"><i class="bi bi-toggle-on me-2"></i>' . lang('Enable This') . '</button>
                        <button type="button" value="Disable Selected" class="btn mb-1 mt-1 btn-secondary disabled pg-vs-status" data-pg-vs-status="disabled"><i class="bi bi-toggle-off me-2"></i>' . lang('Disable This') . '</button>
                        <button type="button" value="Delete Selected" class="btn mb-1 mt-1 btn-danger disabled" data-loading-content="' . lang('Deleting') . '" data-confirm-content="' . lang('Delete the selected variant sets and all of their products? This cannot be undone.') . '"><i class="bi bi-trash me-2"></i>' . lang('Delete') . '</button>
                    </div>
                </div>
            </nav>
        </form>';
}

// Two different empty states. "You have not made one yet" and "your filter
// matched nothing" need different answers, and offering "create one" to
// somebody who just filtered is unhelpful.
$output_empty = '';

if (!$sets) {

    if ($filters_active) {
        $output_empty =
            '<div class="p-4">' .
            pg_pb_render_empty_state(
                'bi-funnel',
                lang('No results'),
                lang('No variant set matches the current filters.')) . '
            </div>';
    } else {
        $output_empty =
            '<div class="p-4">' .
            pg_pb_render_empty_state(
                'bi-diagram-3',
                lang('Variant Sets'),
                lang('No variant sets yet. Create a product and tick two or more attribute options to make one.'),
                'add_product2.php',
                lang('Create')) . '
            </div>';
    }
}


/* ------------------------------------------------------------------ *
 * Filter controls
 * ------------------------------------------------------------------ */

$status_options = array(
    ''         => lang('All'),
    'enabled'  => lang('Enabled'),
    'disabled' => lang('Disabled'),
);

$output_status_options = '';

foreach ($status_options as $value => $status_label) {
    $selected = ($filter_status === (string) $value) ? ' selected="selected"' : '';
    $output_status_options .= '<option value="' . h($value) . '"' . $selected . '>' . h($status_label) . '</option>';
}

$output_parent_options = '<option value="0">' . lang('All') . '</option>';

foreach ($parent_options as $parent_option) {
    $selected = ($filter_parent === (int) $parent_option['id']) ? ' selected="selected"' : '';
    $output_parent_options .= '<option value="' . h($parent_option['id']) . '"' . $selected . '>' . h($parent_option['name']) . '</option>';
}


/* ------------------------------------------------------------------ *
 * Output
 * ------------------------------------------------------------------ */

echo
pg_page_shell(array(
    'title'         => lang('Variant Sets'),
    'extra classes' => 'products',
    'icon'          => 'store',
    'heading'       => lang('Variant Sets'),
    'breadcrumb'    => array(
        array('label' => lang('All Products'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_products.php'),
        array('label' => lang('Variant Sets'))),
)) .
pg_pb_render_styles() . '
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <div class="row mb-2 flex-wrap">
                <div class="col-12 col-sm-12 col-md-6 col-xl-9 text-center text-md-start">
                    <h2 class="d-inline-block" data-bs-content="' . lang('Product groups that present several forms of the same product as one catalog item.') . '" title="' . lang('Variant Sets') . '">' . lang('Variant Sets') . '</h2>
                    <nav id="button_bar" class="navigation" aria-label="Button Bar">
                        <a class="btn btn-sm btn-primary m-1" href="add_product2.php" data-loading-content="' . lang('Loading') . '"><i class="bi bi-plus-circle me-2"></i>' . lang('Create') . '</a>
                    </nav>
                </div>

                <!-- Filters live top right, where every other list screen puts
                     them. A GET form so the URL is shareable and the back button
                     behaves; the choice is also kept in the session. -->
                <div class="col-12 col-sm-12 col-md-6 col-xl-3">
                    <div class="row justify-content-center justify-content-md-end">
                        <form id="search_form" action="view_products2.php" method="get" class="search_form col-auto">
                            <div class="input-group input-group-sm">
                                <label class="input-group-text mt-1 mb-1" for="status" title="' . lang('Enabled') . '"><i class="bi bi-eye"></i></label>
                                <select id="status" name="status" class="form-select mt-1 mb-1" title="' . lang('Enabled') . '" onchange="submit_form(\'search_form\')">' . $output_status_options . '</select>
                                <select id="parent" name="parent" class="form-select mt-1 mb-1" title="' . lang('Parent Product Group') . '" onchange="submit_form(\'search_form\')">' . $output_parent_options . '</select>
                                ' . ($filters_active
                                    ? '<a class="btn btn-outline-secondary mt-1 mb-1 no-submit" href="view_products2.php?status=&amp;parent=0" title="' . lang('Clear Filters') . '"><i class="bi bi-x-lg"></i></a>'
                                    : '') . '
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="card my-4">
                <div class="card-body p-0 position-relative">
                    ' . $output_empty . '
                    ' . $output_table . '
                </div>
            </div>
        </div>
    </div>
</main>
<script>
(function ($) {
    "use strict";

    /* "Delete Selected" is handled by the shared button router in
       backend.src.js: it sets document.form.action to "delete", confirms with
       data-confirm-content and submits. Enable and disable are not in that
       router\'s list, so they are wired here — same form, same single endpoint,
       just one extra hidden field. */
    $(document).on("click", ".pg-vs-status", function () {

        if ($(this).hasClass("disabled")) {
            return;
        }

        document.form.action.value  = "status";
        document.form.status.value  = $(this).attr("data-pg-vs-status");
        document.form.submit();
    });

}(jQuery));
</script>' .
output_footer();

$liveform->remove_form();
