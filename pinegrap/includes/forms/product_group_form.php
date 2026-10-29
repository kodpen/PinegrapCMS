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
            '<td class="chart_label align-middle ' . $ustatus_class . '">' . h($urow['name']) . '</td>' .
            '<td class="align-middle ' . $ustatus_class . '">' . h($urow['short_description']) . '</td>' .
            '<td class="align-middle text-end">' . prepare_amount($urow['price']) . '</td>' .
            '<td class="align-middle"><input class="form-control text-end" type="text" name="sort_order_product_' . $urow['id'] . '" size="5" value="" maxlength="4" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="0" aria-label="' . h(lang('Sort Order') . ': ' . $urow['name']) . '" style="text-align: right;width:60px;" /></td>' .
            '<td></td>' .
            '</tr>';
    }

    return $output_rows;
}

/**
 * The information, catalog display, image and SEO cards.
 *
 * @param liveform $liveform
 * @param array $values  The stored row (raw), array() on create; 'images'
 *                       the picker's file names, cover first
 * @param array $form    'is_new', 'parent_row' (the parent picker markup),
 *                       'attributes' (edit: the attributes block),
 *                       'seo_badge', 'seo_checklist' (edit)
 * @return string
 */
function pg_product_group_form_cards($liveform, $values, $form)
{
    $v = array_merge(array(
        'name' => '', 'enabled' => '1', 'short_description' => '', 'full_description' => '', 'details' => '',
        'code' => '', 'keywords' => '', 'display_type' => 'browse', 'address_name' => '', 'title' => '',
        'meta_description' => '', 'meta_keywords' => '', 'images' => array()), $values);
    $form = array_merge(array('is_new' => false, 'parent_row' => '', 'attributes' => '', 'seo_badge' => '', 'seo_checklist' => ''), $form);

    // A new group's name is a liveform field, so a refused save keeps it.
    if ($form['is_new']) {
        $output_name = $liveform->output_field(array('type'=>'text','id'=>'name','name'=>'name', 'class'=>'form-control ', 'required'=>'required')) . '
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>';
    } else {
        $output_name = '<input type="text" name="name" id="name" class="form-control" value="' . h($v['name']) . '"/>';
    }

    $enabled_checked = $v['enabled'] ? ' checked="checked"' : '';
    $display_type_browse_checked = ($v['display_type'] == 'browse') ? ' checked="checked"' : '';
    $display_type_select_checked = ($v['display_type'] == 'browse') ? '' : ' checked="checked"';

    return '
                        <div class="col-12">
                            <div class="card my-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . ($form['is_new'] ? lang('New Product Group Information') : lang('Product Group Information')) . '
                                </h2>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 md:col-4 my-3">
                                            <label for="name" class="form-label">' . lang('Product Group Name') . '</label>
                                            ' . $output_name . '
                                        </div>
                                        <div class="col-12 my-5">
                                            <div class="form-field">
                                                <div class="switch"><input class="" type="checkbox" id="enabled" name="enabled" value="1"' . $enabled_checked . ' /></div>
                                                <label class="" for="enabled">' . lang('Enabled') . '</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card my-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Catalog Page Display Options') . '
                                </h2>
                                <div class="card-body">
                                    <div class="row">
                                        ' . $form['parent_row'] . '
                                        <div class="col-12 md:col-8 my-3">
                                            <label for="short_description" class="form-label">' . lang('Short Description') . '</label>
                                            <input type="text" name="short_description" placeholder="' . lang('Short Description') . '" id="short_description" maxlength="100" class="form-control" value="' . h($v['short_description']) . '" />
                                        </div>
                                        <div class="col-12 mt-1 mb-3">
                                            <label for="full_description" class="form-label">' . lang('Full Description') . '</label>
                                            <textarea id="full_description" name="full_description">' . h(prepare_rich_text_editor_content_for_output($v['full_description'])) . '</textarea>
                                        </div>

                                        <div class="col-12 my-5">
                                            <div class="col-12">
                                                <label class="form-label">' . lang('Display Type') . '</label>
                                            </div>
                                            <div class="form-field  d-inline-grid me-5" title="' . lang('Display contents for browsing on catalog page') . '">
                                                <input class="radio collapse-switcher"  type="radio" id="browse" name="display_type" value="browse" ' . $display_type_browse_checked . '/>
                                                <label for="browse">' . lang('Browse') . '</label>
                                            </div>
                                            <div class="form-field  d-inline-grid me-5" title="' . lang('Display contents for selection on catalog detail page') . '">
                                                <input class="radio collapse-switcher"  type="radio" id="select" name="display_type" value="select" ' . $display_type_select_checked . ' data-bs-target="#display_type_select_row"/>
                                                <label for="select">' . lang('Select') . '</label>
                                            </div>
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
                            <div class="card my-7">
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
                            <div class="card my-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('SEO') . $form['seo_badge'] . '
                                </h2>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 mt-1 mb-3">
                                            <label for="address_name" class="form-label">' . lang('Catalog Name') . '</label>
                                            <div class="input-group ">
                                                <label class="input-group-text material-icons" aria-hidden="true" title="' . lang('This option determines the url address of the product. Automatically assigned if left blank.') . '" data-bs-content="' . URL_SCHEME . HOSTNAME . OUTPUT_PATH . '{' . lang('Catalog Name') . '}">public</label>
                                                <input type="text" name="address_name" id="address_name" class="form-control" value="' . h($v['address_name']) . '" />
                                            </div>
                                        </div>
                                        <div class="col-12 mt-1 mb-3">
                                            <label for="title" class="form-label">' . lang('Web Browser Title') . '</label>
                                            <input type="text" name="title" id="title" class="form-control" value="' . h($v['title']) . '" />
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
                            <div class="card my-7' . $form['card_class'] . '">
                                <h2 class="card-header bg-reset text-uppercase fg-primary h5 fw-bold">' . $form['title'] . '</h2>
                                <div class="card-body p-0 position-relative">
                                    <table id="' . $form['table_id'] . '" class="chart table-hover table datatable-restricted-mode" style="width:100%;display:none">
                                        <thead>
                                            <tr>
                                                <th class="noVis">
                                                    <div class="form-field">
                                                        <div class="switch"><input class="" title="' . lang(array('string'=>'Select/Deselect All') ) . '" type="checkbox" id="select_all"></div>
                                                    </div>
                                                </th>
                                                <th class="noVis">' . lang(array('string'=>'Action') ) . '</th>
                                                ' . $form['image_header'] . '
                                                <th>' . lang('Name') . '</th>
                                                <th>' . lang('Short Description') . '</th>
                                                <th class="text-end">' . lang('Price') . '</th>
                                                <th>' . lang('Sorting') . '</th>
                                                <th class="noVis"></th>
                                            </tr>
                                        </thead>
                                        <tbody>' . $form['rows'] . '</tbody>
                                    </table>
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
                            var loadMoreBusy = false;

                            $("#btn_load_more_products").on("click", function() {
                                if (loadMoreBusy) return;
                                loadMoreBusy = true;
                                var $btn = $(this);
                                $btn.prop("disabled", true).find("i").removeClass("bi-arrow-down-circle").addClass("bi-hourglass-split");

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
                                        if (response.status === "success" && response.rows) {
                                            var $rows = $(response.rows);
                                            var dt = $("#' . $form['table_id'] . '").DataTable();
                                            $rows.each(function() {
                                                dt.row.add(this);
                                            });
                                            dt.draw(false);

                                            // Immediately resolve lazy images in loaded rows (they are on-demand anyway).
                                            $rows.find("img.lazy").each(function() {
                                                var src = $(this).data("src");
                                                if (src) {
                                                    $(this).attr("src", src).removeClass("lazy");
                                                }
                                            });

                                            loadedOffset += response.loaded;
                                            if (!response.has_more) {
                                                $btn.closest(".border-top").hide();
                                            } else {
                                                $btn.prop("disabled", false).find("i").removeClass("bi-hourglass-split").addClass("bi-arrow-down-circle");
                                            }
                                        }
                                        loadMoreBusy = false;
                                    },
                                    error: function() {
                                        loadMoreBusy = false;
                                        $btn.prop("disabled", false).find("i").removeClass("bi-hourglass-split").addClass("bi-arrow-down-circle");
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
 * Links the ticked products to the group with their sort orders.
 *
 * @param mixed $product_group_id
 * @param array $post
 * @param array $kept_product_ids  Products whose link was left in place
 *                                 (edit: products in the Recycle Bin)
 */
function pg_product_group_form_link_products($product_group_id, $post, $kept_product_ids = array())
{
    if (empty($post['products']) || !is_array($post['products'])) {
        return;
    }

    foreach ($post['products'] as $product_id) {
        // A form opened before the product went to the bin still posts it;
        // its row is the one kept.
        if (in_array((int) $product_id, $kept_product_ids, true)) {
            continue;
        }

        $query = "INSERT INTO products_groups_xref (product, product_group, sort_order) VALUES (?, ?, ?)";
        db_q($query, array((string) $product_id, (string) $product_group_id, (string) ($post['sort_order_product_' . $product_id] ?? ''))) or output_error('Query failed');
    }
}
