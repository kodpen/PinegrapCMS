<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The product group form, shared by the create and the edit screens
 * (add_product_group.php, edit_product_group.php): the information,
 * display, image and SEO cards, the products card with its "load more"
 * script and the rows of products not in the group yet; on a save, the
 * address name, the cover image, the search results pages whose tag cloud
 * the group feeds and the product links. The screens keep what differs:
 * the edit screen loads the record, lists the group's own products and
 * sub-groups, offers attributes, the SEO checklist, delete and the bin;
 * the create screen saves the image code template.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The order the products offered for the group are paged in, first batch
 * and Load More alike. Imports and bulk edits stamp many rows with the same
 * second, and MySQL may return tied rows in a different order when the LIMIT
 * differs, so the id breaks the tie and batches never overlap or skip a row.
 *
 * @return string
 */
function pg_product_group_form_unselected_order_sql()
{
    return 'ORDER BY timestamp DESC, id DESC';
}

/**
 * The table rows of products offered for the group (not in it yet): ticked
 * off, no sort order.
 *
 * @param array $products  Rows with id, name, enabled, short_description,
 *                         image_name, price (minor units)
 * @param bool $show_image
 * @return string
 */
function pg_product_group_form_unselected_rows($products, $show_image)
{
    // The pictures behind each product's cover, for the image viewer.
    $more_images = $show_image ? pg_product_more_images(array_column($products, 'id')) : array();
    $output_rows = '';

    foreach ($products as $urow) {
        $urow['price'] = $urow['price'] / 100;
        $ustatus_class = $urow['enabled'] == 1 ? 'fg-success' : 'fg-danger';
        $uoutput_image_column = '';
        if ($show_image) {
            $uoutput_image_column = pg_product_image_cell('product-' . (int) $urow['id'], $urow['image_name'], $urow['name'], $urow['short_description'],
                isset($more_images[(int) $urow['id']]) ? $more_images[(int) $urow['id']] : array());
        }
        $output_rows .=
            '<tr id="' . $urow['id'] . '">' .
            '<td class="select-all align-middle text-start"><input class="check" type="checkbox" name="products[]" value="' . $urow['id'] . '" aria-label="' . h(lang(array('string' => 'Select {var:1}', 'vars' => array($urow['name'])))) . '"/></td>' .
            '<td class="align-middle text-start"><button type="button" class="m-1 btn-data-control btn btn-outline theme-primary border-2" title="' . lang('Edit') . '" onclick="window.location.href=\'edit_product.php?id=' . $urow['id'] . '\'"><i class="bi bi-pencil"></i></button></td>' .
            $uoutput_image_column .
            '<td class="chart_label align-middle ' . $ustatus_class . '">' . h($urow['name']) . (($urow['enabled'] == 1) ? '' : pg_list_status_word(lang('Disabled'))) . '</td>' .
            '<td class="align-middle ' . $ustatus_class . '">' . h($urow['short_description']) . '</td>' .
            '<td class="align-middle text-end" data-sort="' . h((string) $urow['price']) . '">' . prepare_amount($urow['price']) . '</td>' .
            '<td class="align-middle"><input class="form-control text-end" type="text" name="sort_order_product_' . $urow['id'] . '" size="5" value="" maxlength="5" inputmode="numeric" data-inputmask-alias="integer" data-inputmask-min="0" data-inputmask-max="65535" data-inputmask-placeholder="0" aria-label="' . h(lang('Sort Order') . ': ' . $urow['name']) . '" style="text-align: right;width:60px;" /></td>' .
            '<td></td>' .
            '</tr>';
    }

    return $output_rows;
}

/**
 * The form column cards: information (name, short and full description),
 * catalog display, image and SEO. The Enabled switch and the parent group
 * sit in the status column (pg_product_group_form_status_card()).
 *
 * @param liveform|null $liveform  Read only on create
 * @param array $values  The stored row (raw), array() on create; 'images'
 *                       the picker's file names, cover first
 * @param array $form    'is_new', 'attributes' (edit: the attributes block),
 *                       'seo_badge', 'seo_checklist' (edit), 'is_root'
 *                       (edit of the top group: its display type is not
 *                       saved, so the choice is shown disabled)
 * @return string
 */
function pg_product_group_form_cards($liveform, $values, $form)
{
    $v = array_merge(array(
        'name' => '', 'short_description' => '', 'full_description' => '', 'details' => '',
        'code' => '', 'keywords' => '', 'display_type' => 'browse', 'address_name' => '', 'title' => '',
        'meta_description' => '', 'meta_keywords' => '', 'images' => array()), $values);
    $form = array_merge(array('is_new' => false, 'attributes' => '', 'seo_badge' => '', 'seo_checklist' => '', 'is_root' => false), $form);

    // A new group's name is a liveform field, so a refused save keeps it.
    if ($form['is_new']) {
        $output_name = $liveform->output_field(array('type'=>'text','id'=>'name','name'=>'name', 'class'=>'form-control ', 'maxlength'=>'100', 'required'=>'required'));
    } else {
        $output_name = '<input type="text" name="name" id="name" class="form-control" maxlength="100" required value="' . h($v['name']) . '"/>';
    }

    $display_type_browse_checked = ($v['display_type'] == 'browse') ? ' checked="checked"' : '';
    $display_type_select_checked = ($v['display_type'] == 'browse') ? '' : ' checked="checked"';

    return '
                        <div class="col-12">
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . ($form['is_new'] ? lang('New Product Group Information') : lang('Product Group Information')) . '
                                </h2>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="name" class="form-label">' . lang('Product Group Name') . '</label>
                                            ' . $output_name . '
                                        </div>
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="short_description" class="form-label">' . lang('Short Description') . '</label>
                                            <input type="text" name="short_description" placeholder="' . lang('Short Description') . '" id="short_description" maxlength="100" class="form-control" value="' . h($v['short_description']) . '" />
                                        </div>
                                        <div class="col-12 mt-1 mb-3">
                                            <label for="full_description" class="form-label">' . lang('Full Description') . '</label>
                                            <textarea id="full_description" name="full_description">' . h(prepare_rich_text_editor_content_for_output($v['full_description'])) . '</textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Catalog Page Display Options') . '
                                </h2>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-3">
                                            <fieldset' . ($form['is_root'] ? ' disabled aria-describedby="display_type_root_note"' : '') . '>
                                                <legend class="form-label fs-md">' . lang('Display Type') . '</legend>
                                                <div class="form-field  d-inline-grid me-5" title="' . lang('Display contents for browsing on catalog page') . '">
                                                    <input class="radio collapse-switcher"  type="radio" id="browse" name="display_type" value="browse" aria-describedby="display_type_browse_help" ' . $display_type_browse_checked . '/>
                                                    <label for="browse">' . lang('Browse') . '</label>
                                                    <span class="visually-hidden" id="display_type_browse_help">' . lang('Display contents for browsing on catalog page') . '</span>
                                                </div>
                                                <div class="form-field  d-inline-grid me-5" title="' . lang('Display contents for selection on catalog detail page') . '">
                                                    <input class="radio collapse-switcher"  type="radio" id="select" name="display_type" value="select" aria-describedby="display_type_select_help" ' . $display_type_select_checked . ' data-bs-target="#display_type_select_row"/>
                                                    <label for="select">' . lang('Select') . '</label>
                                                    <span class="visually-hidden" id="display_type_select_help">' . lang('Display contents for selection on catalog detail page') . '</span>
                                                </div>
                                            </fieldset>
                                            ' . ($form['is_root'] ? '<div class="form-text" id="display_type_root_note">' . h(lang('The top product group is always shown for browsing.')) . '</div>' : '') . '
                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-3 w-100" id="display_type_select_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(111px, 0px);"></div>
                                                <div class="popover-body">
                                                    <div class="row">
                                                        <div class="col-12 my-3">
                                                            <div class="alert theme-primary">' . lang('Product group behaves like a product and provides the ability to select options in product detail page.') . '</div>
                                                        </div>
                                                        <div class="col-12 mt-1 mb-3">
                                                            <label for="details" class="form-label">' . lang('Details') . '</label>
                                                            <textarea id="details" name="details">' . h(prepare_rich_text_editor_content_for_output($v['details'])) . '</textarea>
                                                        </div>
                                                        <div class="col-12 mt-5 mb-3">
                                                            <label for="keywords" class="form-label">' . lang('Search Keywords') . '</label>
                                                            <input type="text" name="keywords" id="keywords" class="form-control tagin min-height-tagin" data-pg-chips data-placeholder="' . lang('Add tags') . '"  maxlength="255" value="' . h($v['keywords']) . '"/>' . pg_fields_script() . '
                                                        </div>
                                                        ' . $form['attributes'] . '
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Image Options') . '
                                </h2>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 mt-5">
                                            ' . pg_pb_render_image_picker(
                                                    $v['images'],
                                                    '<button type="button" class="btn btn-sm btn-outline theme-secondary ms-auto" data-bs-toggle="dialog" data-bs-target="#image_code" title="' . lang('Code') . '"><i class="bi bi-code-slash"></i></button>') . '

                                            <dialog class="dialog dialog-lg" id="image_code" aria-labelledby="image_code_title">
                                                        <div class="dialog-header">
                                                            <h5 class="dialog-title" id="image_code_title">' . lang('Code') . '</h5>
                                                            <button type="button" title="' . lang('Close') . '" class="btn-close" data-bs-dismiss="dialog" aria-label="' . h(lang('Close')) . '"></button>
                                                        </div>
                                                        <div class="dialog-body ">
                                                            <div class="row">
                                                                <div class="col-12 my-3">
                                                                <div class="alert theme-primary">' . lang('Tags') . ':<span title="' . lang('Loop start') . '">^^image_loop_start^^</span>, <span title="' . lang('Short Description') . '">^^image_alt^^</span>, <span title="' . lang('Image Url') . '">^^image_url^^</span>, <span title="' . lang('Loop End') . '">^^image_loop_end^^</span></div>
                                                                </div>
                                                                <div class="col-12 my-3">
                                                                    <textarea id="code" name="code">' . h($v['code']) . '</textarea>
                                                                    ' . pg_code_editor_attach(array('id' => 'code', 'code_type' => 'mixed')) . '
                                                                </div>
                                                            </div>
                                                        </div>
                                            </dialog>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('SEO') . $form['seo_badge'] . '
                                </h2>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 mt-1 mb-3">
                                            <label for="address_name" class="form-label">' . lang('Catalog Name') . '</label>
                                            <div class="input-group ">
                                                <label class="input-group-text material-icons" aria-hidden="true">public</label>
                                                <input type="text" name="address_name" id="address_name" aria-describedby="address_name_hint" class="form-control" value="' . h($v['address_name']) . '" />
                                            </div>
                                            <div class="form-text" id="address_name_hint">' . lang('This option determines the url address of the product. Automatically assigned if left blank.') . ' <code>' . h(URL_SCHEME . HOSTNAME . OUTPUT_PATH . '{' . lang('Catalog Name') . '}') . '</code></div>
                                        </div>
                                        <div class="col-12 mt-1 mb-3">
                                            <label for="title" class="form-label">' . lang('Web Browser Title') . '</label>
                                            <input type="text" name="title" id="title" class="form-control" maxlength="255" value="' . h($v['title']) . '" />
                                        </div>
                                        <div class="col-12 mt-1 mb-3">
                                            <label for="meta_description" class="form-label">' . lang('Web Browser Description') . '</label>
                                            <textarea name="meta_description" id="meta_description" class="form-control" maxlength="255" >' . h($v['meta_description']) . '</textarea>
                                        </div>
                                        <div class="col-12 mt-1 mb-3">
                                            <label for="meta_keywords" class="form-label">' . lang('Web Browser Keywords') . '</label>
                                            <input type="text" name="meta_keywords" id="meta_keywords" class="form-control tagin min-height-tagin" data-pg-chips data-placeholder="' . lang('Add tags') . '"  maxlength="255" value="' . h($v['meta_keywords']) . '"/>' . pg_fields_script() . '
                                        </div>
                                        ' . $form['seo_checklist'] . '
                                    </div>
                                </div>
                            </div>
                        </div>';
}

/**
 * The status column card: the Enabled switch and the parent group picker,
 * what decides whether and where the group shows in the catalog.
 *
 * @param array $values      The stored row (raw), array() on create
 * @param string $parent_row The parent picker markup (a col-12 cell or a
 *                           hidden field), '' when there is no group to pick
 * @return string
 */
function pg_product_group_form_status_card($values, $parent_row)
{
    $enabled = array_key_exists('enabled', $values) ? (bool) $values['enabled'] : true;

    return '
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Status') . '
                                </h2>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-3">
                                            <div class="form-field">
                                                <div class="switch"><input class="" type="checkbox" id="enabled" name="enabled" value="1"' . ($enabled ? ' checked="checked"' : '') . ' /></div>
                                                <label class="" for="enabled">' . lang('Enabled') . '</label>
                                            </div>
                                        </div>
                                        ' . $parent_row . '
                                    </div>
                                </div>
                            </div>';
}

/**
 * The edit screen layout (ui/edit-screen-wide-layout.md): the form column,
 * the status column with a sticky box, then the products table across the
 * full width (eight columns with pictures do not fit the form column), and
 * the sticky save bar last.
 *
 * @param array $parts  'form', 'status', 'wide' (the products card),
 *                      'buttons' (inside the bar's button group),
 *                      'bar_extra' (after the group), 'row_class'
 * @return string
 */
function pg_product_group_form_layout($parts)
{
    $parts = array_merge(array('form' => '', 'status' => '', 'wide' => '', 'buttons' => '', 'bar_extra' => '', 'row_class' => ''), $parts);

    return '
                    <div class="row' . ($parts['row_class'] !== '' ? ' ' . $parts['row_class'] : '') . '">
                        <div class="col-12 lg:col-8 2xl:col-9 order-1"><div class="row">' . $parts['form'] . '
                        </div></div>
                        <div class="col-12 lg:col-4 2xl:col-3 order-2">
                            <!-- 3.3rem: the fixed control panel header covers the top of the window. -->
                            <div class="position-sticky" style="top:3.3rem;">' . $parts['status'] . '
                            </div>
                        </div>
                        <div class="col-12 order-3"><div class="row">' . $parts['wide'] . '
                        </div></div>
                        <div class="col-12 order-4 position-sticky" style="bottom:0; z-index:3;">
                            <nav class="buttons navigation text-center mb-7" aria-label="' . h(lang('data edit buttons')) . '">
                                <div class="container">
                                    <div class="btn-group flex-wrap justify-content-center">' . $parts['buttons'] . '
                                    </div>' . $parts['bar_extra'] . '
                                </div>
                            </nav>
                        </div>
                    </div>';
}

/**
 * The products card: the table, the "Load More Products" button while more
 * products are left, and the script that loads them.
 *
 * @param array $form  'table_id', 'title', 'card_class', 'image_header',
 *                     'rows', 'total_unselected', 'loaded' (unselected
 *                     rows already in the table), 'product_group_id'
 *                     (0 on create)
 * @return string
 */
function pg_product_group_form_products_card($form)
{
    return '
                        <div class="col-12">
                            <div class="card mb-7' . $form['card_class'] . '">
                                <h2 class="card-header bg-reset text-uppercase fg-primary h5 fw-bold">' . $form['title'] . '</h2>
                                <div class="card-body p-0 position-relative">
                                    <div class="table-responsive">
                                    <table id="' . $form['table_id'] . '" class="table table-hover mb-0" data-pg-sort>
                                        <thead>
                                            <tr>
                                                <th data-pg-sort="none">
                                                    <div class="form-field">
                                                        <div class="switch"><input class="" title="' . h(lang('Select/Deselect All')) . '" aria-label="' . h(lang('Select/Deselect All')) . '" type="checkbox" id="select_all"></div>
                                                    </div>
                                                </th>
                                                <th data-pg-sort="none">' . lang('Action') . '</th>
                                                ' . $form['image_header'] . '
                                                <th>' . lang('Name') . '</th>
                                                <th>' . lang('Short Description') . '</th>
                                                <th class="text-end">' . lang('Price') . '</th>
                                                <th data-pg-sort="none">' . lang('Sorting') . '</th>
                                                <th data-pg-sort="none"></th>
                                            </tr>
                                        </thead>
                                        <tbody>' . $form['rows'] . '</tbody>
                                    </table>
                                    </div>
                                </div>
                                ' . ($form['total_unselected'] > $form['loaded'] ? '
                                <div class="p-5 border-top">
                                    <button id="btn_load_more_products" type="button" class="btn btn-outline theme-secondary btn-sm">
                                        <i class="bi bi-arrow-down-circle me-3"></i>' . lang('Load More Products') . '
                                    </button>
                                </div>' : '') . '
                            </div>
                        </div>
                        <script>
                        (function() {
                            var loadedOffset = ' . (int) $form['loaded'] . ';
                            var $productsTable = $("#' . $form['table_id'] . '");

                            // The rows hold form fields, so the table is a plain one
                            // (every row stays in the form); Select All ticks every
                            // product box, rows loaded later included, and shows a
                            // mixed state while only some are ticked.
                            function syncSelectAll() {
                                var $boxes = $productsTable.find("tbody .select-all input[type=checkbox]").not(":disabled");
                                var ticked = $boxes.filter(":checked").length;
                                $("#select_all")
                                    .prop("indeterminate", (ticked > 0) && (ticked < $boxes.length))
                                    .prop("checked", (ticked > 0) && (ticked === $boxes.length));
                                $boxes.each(function() { $(this).closest("tr").toggleClass("selected", this.checked); });
                            }
                            $productsTable.on("change", "#select_all", function() {
                                $productsTable.find("tbody .select-all input[type=checkbox]").not(":disabled").prop("checked", this.checked);
                                syncSelectAll();
                            });
                            $productsTable.on("change", "tbody .select-all input[type=checkbox]", syncSelectAll);
                            syncSelectAll();
                            var loadMoreBusy = false;

                            // The button is enabled again or hidden on every answer: a batch
                            // can come back empty when products were binned, deleted or added
                            // to the group elsewhere since the screen opened.
                            function loadMoreRestore($btn) {
                                $btn.prop("disabled", false).find("i").removeClass("bi-hourglass-split").addClass("bi-arrow-down-circle");
                            }

                            $("#btn_load_more_products").on("click", function() {
                                if (loadMoreBusy) return;
                                loadMoreBusy = true;
                                var $btn = $(this);
                                $btn.prop("disabled", true).find("i").removeClass("bi-arrow-down-circle").addClass("bi-hourglass-split");

                                // A network failure, an expired session (the sign-in page is
                                // not JSON) and refused commerce access all end here.
                                function loadMoreFailed(message) {
                                    loadMoreBusy = false;
                                    loadMoreRestore($btn);
                                    if (typeof window.pgToast === "function") {
                                        window.pgToast({ variant: "danger", message: message || ' . json_encode(lang('Query failed.')) . ' });
                                    }
                                }

                                $.ajax({
                                    url: "api.php",
                                    type: "POST",
                                    contentType: "application/json",
                                    dataType: "json",
                                    data: JSON.stringify({
                                        action: "get_unselected_products",
                                        product_group_id: ' . (int) $form['product_group_id'] . ',
                                        offset: loadedOffset
                                    }),
                                    success: function(response) {
                                        if (!(response && response.status === "success")) {
                                            loadMoreFailed(response && response.message);
                                            return;
                                        }

                                        var $table = $productsTable;
                                        var $rows = response.rows ? $(response.rows).filter("tr") : $();
                                        // A product already in the table is not added again. The tick
                                        // box is the key: a selected child group row uses its group id
                                        // as the row id, which can equal a product id.
                                        $rows = $rows.filter(function() {
                                            var id = $(this).find("input[name=\"products[]\"]").val();
                                            return !id || !$table.find("input[name=\"products[]\"][value=\"" + id + "\"]").length;
                                        });
                                        if ($rows.length) {
                                            $table.children("tbody").append($rows);
                                            syncSelectAll();

                                            // Immediately resolve lazy images in loaded rows (they are on-demand anyway).
                                            $rows.find("img.lazy").each(function() {
                                                var src = $(this).data("src");
                                                if (src) {
                                                    $(this).attr("src", src).removeClass("lazy");
                                                }
                                            });

                                            if (typeof pgInitInjected === "function") {
                                                pgInitInjected($rows);
                                            }
                                        }

                                        loadedOffset += parseInt(response.loaded, 10) || 0;
                                        loadMoreBusy = false;
                                        if (!response.has_more) {
                                            $btn.closest(".border-top").hide();
                                        } else {
                                            loadMoreRestore($btn);
                                        }

                                        // Focus goes to the first row the batch brought; when none came
                                        // and the strip holding the button is gone, to Select All.
                                        var $first = $rows.find("input.check").first();
                                        if ($first.length && $first.is(":visible")) {
                                            $first.trigger("focus");
                                        } else if (!response.has_more) {
                                            $("#select_all").trigger("focus");
                                        }
                                    },
                                    error: function() {
                                        loadMoreFailed();
                                    }
                                });
                            });
                        })();
                        </script>';
}

/**
 * The text a group's catalog address is made from: the address name typed,
 * else the short description, else the name. prepare_catalog_item_address_name()
 * then makes it an address.
 *
 * @param array $post
 * @return string
 */
function pg_product_group_form_address_source($post)
{
    foreach (array('address_name', 'short_description', 'name') as $field) {
        $value = (isset($post[$field]) && is_scalar($post[$field])) ? (string) $post[$field] : '';

        if (($value !== '') || ($field === 'name')) {
            return $value;
        }
    }

    return '';
}

/**
 * Whether a group may be saved under the parent its form posts. The list on
 * the screen leaves the group and its sub-groups out, but it was drawn when
 * the screen opened: a group moved under this one meanwhile would close the
 * tree into a ring, and both would drop out of the catalogue. The parent must
 * be a live group (or the one it already has) outside the group's own
 * subtree; 0 is the root's own "no parent". Pure.
 *
 * @param array $map               live groups, id => row with parent_id (pg_catalog_group_map())
 * @param int   $group_id          the group being saved
 * @param mixed $parent_id         the posted parent
 * @param int   $current_parent_id the parent stored now
 * @return bool
 */
function pg_product_group_form_parent_ok($map, $group_id, $parent_id, $current_parent_id)
{
    if (!is_scalar($parent_id)) {
        return false;
    }

    $parent_id = (int) $parent_id;

    if ($parent_id === 0) {
        return true;
    }

    if (!isset($map[$parent_id]) && ($parent_id !== (int) $current_parent_id)) {
        return false;
    }

    return !pg_catalog_group_is_within($map, $parent_id, (int) $group_id);
}

/**
 * Splits the picked images into the cover (stored in image_name) and the
 * rest (product_groups_images_xref), in the order picked.
 *
 * @param array $images  pg_pb_selected_images()
 * @return array  array(cover, rest); cover '' when none was picked
 */
function pg_product_group_form_images($images)
{
    $images = array_values($images);

    if (!$images) {
        return array('', array());
    }

    return array((string) $images[0], array_slice($images, 1));
}

/**
 * The search results pages that search catalog items and whose product
 * group tree holds the group, or its parent: their tag clouds are cleared
 * and rebuilt around a change.
 *
 * @param mixed $product_group_id
 * @param mixed $parent_id  null when only the group itself is asked
 * @return array  'current' and 'parent' lists of page_id, product_group_id
 */
function pg_product_group_form_search_results_pages($product_group_id, $parent_id = null)
{
    $pages = array('current' => array(), 'parent' => array());

    // get data from all search result pages that have "search products" enabled
    $query = "SELECT page_id, product_group_id FROM search_results_pages WHERE search_catalog_items = '1'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $search_results_pages = array();

    while ($row = mysqli_fetch_assoc($result)) {
        $search_results_pages[] = $row;
    }

    // A page is listed once for each group of its tree that matches.
    foreach ($search_results_pages as $search_results_page) {
        foreach (get_product_groups_in_product_group_tree($search_results_page['product_group_id']) as $product_group) {
            if ($product_group['id'] == $product_group_id) {
                $pages['current'][] = $search_results_page;

            } elseif (($parent_id !== null) && ($product_group['id'] == $parent_id)) {
                $pages['parent'][] = $search_results_page;
            }
        }
    }

    return $pages;
}

/**
 * The product ids to link from the posted products[] values: each once, in
 * the order posted, without values that are no product id.
 * products_groups_xref has no unique key, so a doubled value would link the
 * product twice.
 *
 * @param array $posted
 * @return int[]
 */
function pg_product_group_form_link_ids($posted)
{
    $ids = array();
    foreach ($posted as $value) {
        if (!is_scalar($value) || !ctype_digit((string) $value)) {
            continue;
        }
        $id = (int) $value;
        if ($id > 0 && !in_array($id, $ids, true)) {
            $ids[] = $id;
        }
    }
    return $ids;
}

/**
 * Splits the group's stored member rows into those the save rewrites and
 * those it leaves alone.
 *
 * Edit posts the ids of the members it listed when it was opened. Only their
 * rows are deleted (ticked ones are inserted again with their sort orders);
 * a product linked to the group after the screen was opened (Create or Edit
 * Product, Catalog Manager, API) is not on the form and keeps its row. A
 * form posted without the list rewrites every row, as before.
 *
 * @param mixed $listed      Comma-separated ids from listed_product_ids,
 *                           null when the field was not posted
 * @param array $stored_ids  Products linked to the group now
 * @return array             'delete' => int[], 'keep' => int[]
 */
function pg_product_group_form_member_rows($listed, $stored_ids)
{
    $stored = pg_product_group_form_link_ids(array_map('strval', array_filter((array) $stored_ids, 'is_scalar')));

    if ($listed === null) {
        return array('delete' => $stored, 'keep' => array());
    }

    $listed_ids = is_string($listed) ? pg_product_group_form_link_ids(explode(',', $listed)) : array();

    $rows = array('delete' => array(), 'keep' => array());
    foreach ($stored as $id) {
        $rows[in_array($id, $listed_ids, true) ? 'delete' : 'keep'][] = $id;
    }
    return $rows;
}

/**
 * The posted value of a sort order box as the number stored: an empty box
 * is 0, and false when the value is no whole number from 0 to 65535. The
 * sort_order columns are smallint unsigned, so a negative or decimal value
 * would otherwise be stored as 0 without a word.
 *
 * @param mixed $value
 * @return int|false
 */
function pg_product_group_form_sort_order($value)
{
    if (($value !== null) && !is_scalar($value)) {
        return false;
    }

    $value = pg_featured_sort_order_value((string) $value);

    return ($value === '') ? 0 : $value;
}

/**
 * What a posted create or edit form has wrong before it is saved, as
 * field name => 'required', 'too_long' or 'sort_order'. The name is
 * required once trimmed; the name and the short description are refused
 * past the column length instead of being cut. The sort order boxes checked
 * are those of the ticked products and of the child groups.
 *
 * @param array $post
 * @return array
 */
function pg_product_group_form_post_problems($post)
{
    $problems = array();

    $name = is_scalar($post['name'] ?? null) ? trim((string) $post['name']) : '';

    if ($name === '') {
        $problems['name'] = 'required';
    } elseif (pg_name_too_long($name)) {
        $problems['name'] = 'too_long';
    }

    if (is_scalar($post['short_description'] ?? null) && pg_name_too_long((string) $post['short_description'])) {
        $problems['short_description'] = 'too_long';
    }

    $fields = array();

    if (!empty($post['products']) && is_array($post['products'])) {
        foreach (pg_product_group_form_link_ids($post['products']) as $product_id) {
            $fields[] = 'sort_order_product_' . $product_id;
        }
    }

    foreach (array_keys($post) as $key) {
        if (preg_match('/^sort_order_product_group_[0-9]+$/', (string) $key)) {
            $fields[] = (string) $key;
        }
    }

    foreach ($fields as $field) {
        if (array_key_exists($field, $post) && (pg_product_group_form_sort_order($post[$field]) === false)) {
            $problems[$field] = 'sort_order';
        }
    }

    return $problems;
}

/**
 * Marks the problems of pg_product_group_form_post_problems() on the form;
 * the sort order message is given once, however many boxes are wrong.
 *
 * @param liveform $liveform
 * @param array    $problems
 * @param string   $required_message  The screen's own "name is required"
 * @return bool    Whether anything was marked
 */
function pg_product_group_form_mark_problems($liveform, $problems, $required_message)
{
    $sort_order_marked = false;

    foreach ($problems as $field => $problem) {
        if ($problem === 'required') {
            $liveform->mark_error($field, $required_message);
        } elseif ($problem === 'too_long') {
            $liveform->mark_error($field, ($field === 'name')
                ? lang(array('string' => 'The name can be at most {var:1} characters long.', 'vars' => array(pg_name_max_length())))
                : lang(array('string' => 'The short description can be at most {var:1} characters long.', 'vars' => array(pg_name_max_length()))));
        } else {
            $liveform->mark_error($field, $sort_order_marked ? '' : lang('Please enter a whole number from 0 to 65535 as the sort order.'));
            $sort_order_marked = true;
        }
    }

    return !empty($problems);
}

/**
 * Links the ticked products to the group with their sort orders.
 *
 * @param mixed $product_group_id
 * @param array $post
 * @param array $kept_product_ids  Products whose link was left in place
 *                                 (edit: products in the Recycle Bin and
 *                                 products the form did not list)
 */
function pg_product_group_form_link_products($product_group_id, $post, $kept_product_ids = array())
{
    if (empty($post['products']) || !is_array($post['products'])) {
        return;
    }

    foreach (pg_product_group_form_link_ids($post['products']) as $product_id) {
        // A form opened before the product went to the bin still posts it;
        // its row is the one kept.
        if (in_array((int) $product_id, $kept_product_ids, true)) {
            continue;
        }

        $query = "INSERT INTO products_groups_xref (product, product_group, sort_order) VALUES (?, ?, ?)";
        db_q($query, array((string) $product_id, (string) $product_group_id, (string) (int) pg_product_group_form_sort_order($post['sort_order_product_' . $product_id] ?? ''))) or output_error('Query failed');
    }
}
