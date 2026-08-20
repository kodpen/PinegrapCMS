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

/**
 * Shared product building logic for the v2 product screens
 * (add_product.php, edit_product.php, and the variant set list).
 *
 * This file exists to keep ONE rule in ONE place:
 *
 *   The variant matrix decides the shape of what gets created.
 *
 *   more than one combination  ->  one product group (display_type = 'select')
 *                                  plus one product per combination, linked
 *                                  through products_groups_xref
 *   one combination or none    ->  a single product, no new group
 *
 * Restating that rule per screen is exactly how the two shapes drift apart, so
 * every caller goes through pg_pb_save_new_product().
 *
 * Status: v2 screens are development-only. The legacy add_product.php /
 * edit_product.php pair is untouched and remains the
 * reference for correct behaviour.
 */

// Guard against double include: add_product.php and edit_product.php may both
// be pulled in by a future shared controller.
if (defined('PG_PRODUCT_BUILDER')) {
    return;
}
define('PG_PRODUCT_BUILDER', TRUE);

require_once(dirname(__FILE__) . '/seo.php');
require_once(dirname(__FILE__) . '/seo_structure.php');


/* ------------------------------------------------------------------ *
 * Reading reference data
 * ------------------------------------------------------------------ */

/**
 * All product attributes with their options attached, keyed by attribute id.
 * Attributes with no options are still returned; callers decide whether an
 * option-less attribute is worth showing.
 *
 * @return array
 */
function pg_pb_attributes()
{
    static $cache = NULL;

    if ($cache !== NULL) {
        return $cache;
    }

    $attributes = db_items(
        "SELECT
            id,
            name,
            label
        FROM product_attributes
        ORDER BY name", 'id');

    if ($attributes) {
        $options = db_items(
            "SELECT
                id,
                product_attribute_id,
                label,
                sort_order
            FROM product_attribute_options
            ORDER BY
                product_attribute_id,
                sort_order");

        foreach ($options as $option) {
            if (isset($attributes[$option['product_attribute_id']])) {
                $attributes[$option['product_attribute_id']]['options'][] = $option;
            }
        }
    }

    $cache = $attributes ? $attributes : array();

    return $cache;
}


/**
 * All shipping zones ordered by name.
 *
 * @return array
 */
function pg_pb_zones()
{
    static $cache = NULL;

    if ($cache !== NULL) {
        return $cache;
    }

    $zones = db_items("SELECT id, name FROM zones ORDER BY name");
    $cache = $zones ? $zones : array();

    return $cache;
}


/**
 * Does the catalog have any product group at all?
 * When it does not, the parent group field is meaningless and is replaced by a
 * hidden zero — a select with no options is a dead control.
 *
 * @return bool
 */
function pg_pb_has_product_groups()
{
    return (bool) db_value("SELECT COUNT(*) FROM product_groups");
}


/**
 * The product group ids a product belongs to.
 *
 * @param int $product_id
 * @return array list of ints
 */
function pg_pb_product_group_ids($product_id)
{
    $product_id = (int) $product_id;
    $rows = db_items("SELECT product_group FROM products_groups_xref WHERE product = '$product_id'");
    $ids = array();

    foreach ($rows as $row) {
        $ids[] = (int) $row['product_group'];
    }

    return $ids;
}


/**
 * The select-type ("variant set") group a product belongs to, or 0.
 *
 * A product can sit in several groups, but only one of them can be the variant
 * set that owns it — display_type 'select' is what makes a group a variant set
 * rather than a browsable category. If a product somehow sits in two, the
 * lowest id wins so the answer is at least deterministic.
 *
 * @param int $product_id
 * @return int
 */
function pg_pb_variant_group_id($product_id)
{
    $product_id = (int) $product_id;

    $group_id = db_value(
        "SELECT product_groups.id
        FROM products_groups_xref
        INNER JOIN product_groups ON products_groups_xref.product_group = product_groups.id
        WHERE products_groups_xref.product = '$product_id'
            AND product_groups.display_type = 'select'
        ORDER BY product_groups.id
        LIMIT 1");

    return (int) $group_id;
}


/**
 * Products belonging to a variant set, in a stable order.
 *
 * @param int $group_id
 * @return array
 */
function pg_pb_group_products($group_id)
{
    $group_id = (int) $group_id;

    return db_items(
        "SELECT
            products.id,
            products.name,
            products.enabled,
            products.short_description,
            products.price,
            products.image_name,
            products.inventory,
            products.inventory_quantity,
            products.address_name
        FROM products_groups_xref
        INNER JOIN products ON products_groups_xref.product = products.id
        WHERE products_groups_xref.product_group = '$group_id'
        ORDER BY products.name, products.id");
}


/**
 * Attribute/option pairs recorded for a product, in sort order.
 *
 * @param int $product_id
 * @return array
 */
function pg_pb_product_attribute_rows($product_id)
{
    $product_id = (int) $product_id;

    return db_items(
        "SELECT
            products_attributes_xref.attribute_id,
            products_attributes_xref.option_id,
            products_attributes_xref.sort_order,
            product_attributes.name AS attribute_name,
            product_attribute_options.label AS option_label
        FROM products_attributes_xref
        LEFT JOIN product_attributes
            ON products_attributes_xref.attribute_id = product_attributes.id
        LEFT JOIN product_attribute_options
            ON products_attributes_xref.option_id = product_attribute_options.id
        WHERE products_attributes_xref.product_id = '$product_id'
        ORDER BY products_attributes_xref.sort_order");
}


/* ------------------------------------------------------------------ *
 * Value helpers
 * ------------------------------------------------------------------ */

/**
 * Convert a price typed by an operator into integer cents.
 *
 * Prices are stored as integer cents throughout the codebase. The inputmask on
 * these fields uses "," as the thousands separator and "." as the decimal
 * point, matching the parsing of the screen this replaced — do not get
 * clever here, a locale guess that disagrees with the mask silently multiplies
 * or divides the price by a thousand.
 *
 * round() rather than (int): a 33% discount on 1499 produces 1004.33 and
 * truncation loses a cent against the feed and the card charge.
 *
 * @param string $value
 * @return int cents
 */
function pg_pb_price_to_cents($value)
{
    $value = trim((string) $value);

    // Keep digits and separators only; a currency symbol pasted in with the
    // number should not turn the whole thing into zero.
    $value = preg_replace('/[^0-9.,]/', '', $value);

    if ($value === '') {
        return 0;
    }

    // Which separator is the decimal point cannot be assumed. The old screen
    // strips every comma and treats the dot as decimal, which is right for
    // "1,234.56" and catastrophically wrong for "12,50" — the Turkish way of
    // writing twelve lira fifty becomes one thousand two hundred and fifty.
    // The legacy screen gets away with it because an inputmask makes that
    // input impossible; a mask is a UI convenience, not a guarantee, and it is
    // defeated by paste, autofill and any caller that is not that screen.
    //
    // Rule: the last separator is the decimal point when one or two digits
    // follow it and nothing else does. Every other separator groups thousands.
    //
    //   12,50     -> 12.50      1,234.56 -> 1234.56
    //   12.50     -> 12.50      1.234,56 -> 1234.56
    //   1,234     -> 1234       1.234    -> 1234
    if (preg_match('/^(.*)([.,])([0-9]{1,2})$/', $value, $matches)) {
        $whole   = preg_replace('/[.,]/', '', $matches[1]);
        $decimal = $matches[3];
        $value   = ($whole === '' ? '0' : $whole) . '.' . $decimal;
    } else {
        $value = preg_replace('/[.,]/', '', $value);
    }

    // round(), not truncation: a discounted price lands on fractional cents and
    // dropping them puts the feed and the card charge a cent apart.
    return (int) round(((float) $value) * 100);
}


/**
 * Format integer cents back into the input format the price fields expect.
 *
 * @param int $cents
 * @return string
 */
function pg_pb_cents_to_price($cents)
{
    return number_format(((int) $cents) / 100, 2, '.', '');
}


/**
 * The images picked in the image picker, cover first.
 * The picker writes hidden selected_images[] inputs; the first one is the cover
 * image (products.image_name) and the rest go to products_images_xref.
 *
 * @param array|null $posted
 * @return array list of file names
 */
function pg_pb_selected_images($posted = NULL)
{
    if ($posted === NULL) {
        $posted = isset($_POST['selected_images']) ? $_POST['selected_images'] : array();
    }

    if (!is_array($posted)) {
        return array();
    }

    $images = array();

    foreach ($posted as $image) {
        $image = trim((string) $image);
        if ($image !== '') {
            $images[] = $image;
        }
    }

    return $images;
}


/**
 * Insert a row from a column => value map.
 *
 * Column names never come from user input: callers pass literals from the
 * whitelists below. The pattern check is a tripwire for a future caller that
 * forgets that, not a substitute for it.
 *
 * @param string $table
 * @param array  $data column => value
 * @return int insert id
 */
function pg_pb_insert_row($table, $data)
{
    $columns = array();
    $values  = array();

    foreach ($data as $column => $value) {
        if (!preg_match('/^[a-z_][a-z_0-9]*$/', $column)) {
            output_error(lang('Query failed.') . ' (' . h($column) . ')');
        }
        $columns[] = '`' . $column . '`';
        $values[]  = "'" . e($value) . "'";
    }

    db("INSERT INTO `" . $table . "` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ")");

    return (int) mysqli_insert_id(db::$con);
}


/**
 * Update a row from a column => value map.
 *
 * @param string $table
 * @param array  $data column => value
 * @param string $id_column
 * @param int    $id
 * @return void
 */
function pg_pb_update_row($table, $data, $id_column, $id)
{
    if (!$data) {
        return;
    }

    $assignments = array();

    foreach ($data as $column => $value) {
        if (!preg_match('/^[a-z_][a-z_0-9]*$/', $column)) {
            output_error(lang('Query failed.') . ' (' . h($column) . ')');
        }
        $assignments[] = '`' . $column . '` = \'' . e($value) . '\'';
    }

    db(
        "UPDATE `" . $table . "`
        SET " . implode(', ', $assignments) . "
        WHERE `" . $id_column . "` = '" . (int) $id . "'");
}


/* ------------------------------------------------------------------ *
 * Reading the form
 * ------------------------------------------------------------------ */

/**
 * Columns the v2 screens manage on the products table.
 *
 * Everything outside this list (gift cards, memberships, recurring profiles,
 * product forms, order-complete actions...) keeps its database default on
 * insert and is left untouched on update. Those fields are reachable from the
 * legacy edit_product.php screen; rendering dead controls for them here would
 * be worse than not offering them.
 *
 * @return array
 */
function pg_pb_managed_product_columns()
{
    return array(
        'name', 'enabled', 'short_description', 'full_description', 'details',
        'code', 'keywords', 'image_name', 'price', 'taxable', 'selection_type',
        'default_quantity', 'minimum_quantity', 'maximum_quantity',
        'title', 'meta_description', 'address_name',
        'inventory', 'inventory_quantity', 'backorder', 'out_of_stock_message',
        'shippable', 'weight', 'length', 'width', 'height',
        'primary_weight_points', 'secondary_weight_points',
        'container_required', 'preparation_time', 'free_shipping',
        'extra_shipping_cost',
        'gtin', 'brand', 'mpn', 'google_product_category', 'notes',
    );
}


/**
 * Read the settings shared by every product this submission creates.
 *
 * In group mode these values are written to every generated variant: they are
 * the properties of the thing being sold, not of one colour of it. Anything a
 * variant can differ on (name, price, images, stock) is read per row from the
 * matrix instead — see pg_pb_decode_variants().
 *
 * @return array column => value
 */
function pg_pb_common_from_post()
{
    // Weight and dimensions are stored in pounds and inches. The operator may
    // have typed metric, in which case convert on the way in — same factors the
    // legacy screen uses.
    $metric = !empty($_POST['convert_to_metric_system']);

    $weight = isset($_POST['weight']) ? $_POST['weight'] : 0;
    $length = isset($_POST['length']) ? $_POST['length'] : 0;
    $width  = isset($_POST['width'])  ? $_POST['width']  : 0;
    $height = isset($_POST['height']) ? $_POST['height'] : 0;

    if ($metric) {
        $weight = round($weight * 2.20462262185, 2);
        $length = round($length * 0.39370078740158, 2);
        $width  = round($width  * 0.39370078740158, 2);
        $height = round($height * 0.39370078740158, 2);
    }

    $post = function ($key, $default = '') {
        return isset($_POST[$key]) ? $_POST[$key] : $default;
    };

    $switch = function ($key) {
        return empty($_POST[$key]) ? '' : '1';
    };

    // Every column the screen draws a control for. A field on screen that is not
    // in this list is filled in by the operator and thrown away on save, and
    // nothing says so — the value simply is not there the next time they look.
    // Twenty-two of these were missing when the edit screen was built and the
    // gap only showed up because the two halves were finally compared.
    $common = array(
        'enabled'                 => $switch('enabled'),

        // Recurring payments.
        'recurring'                               => $switch('recurring'),
        'recurring_schedule_editable_by_customer' => $switch('recurring_schedule_editable_by_customer'),
        'number_of_payments'                      => $post('number_of_payments'),
        'payment_period'                          => $post('payment_period'),
        'start'                                   => $post('start'),

        // Membership and private access.
        'membership_renewal'   => $post('membership_renewal'),
        'grant_private_access' => $switch('grant_private_access'),
        'private_days'         => $post('private_days'),
        'private_folder'       => $post('private_folder'),

        // Gift card.
        'gift_card'               => $switch('gift_card'),
        'gift_card_email_subject' => $post('gift_card_email_subject'),
        'gift_card_email_format'  => $post('gift_card_email_format'),
        'gift_card_email_body'    => $post('gift_card_email_body'),
        'gift_card_email_page_id' => $post('gift_card_email_page_id'),

        // What happens after the order.
        'order_receipt_message'          => prepare_rich_text_editor_content_for_input($post('order_receipt_message')),
        'order_receipt_bcc_email_address' => $post('order_receipt_bcc_email_address'),
        'email_page'                     => $post('email_page'),
        'email_bcc'                      => $post('email_bcc'),
        'contact_group_id'               => $post('contact_group_id'),
        'send_to_page'                   => $post('send_to_page'),
        'required_product'               => $post('required_product'),
        'reward_points'                  => $post('reward_points'),

        // Submit form: buying the product writes a record on a custom form.
        'submit_form'                     => $switch('submit_form'),
        'submit_form_custom_form_page_id' => $post('submit_form_custom_form_page_id'),
        'submit_form_create'              => $switch('submit_form_create'),
        'submit_form_update'              => $switch('submit_form_update'),
        'submit_form_update_where_field'  => $post('submit_form_update_where_field'),
        'submit_form_update_where_value'  => $post('submit_form_update_where_value'),
        'submit_form_quantity_type'       => $post('submit_form_quantity_type'),

        // Comment posted on a page when the product is bought.
        'add_comment'                            => $switch('add_comment'),
        'add_comment_page_id'                    => $post('add_comment_page_id'),
        'add_comment_name'                       => $post('add_comment_name'),
        'add_comment_message'                    => $post('add_comment_message'),
        'add_comment_only_for_submit_form_update' => $switch('add_comment_only_for_submit_form_update'),

        'taxable'                 => $switch('taxable'),
        'selection_type'          => $post('selection_type'),
        'code'                    => $post('code'),
        'keywords'                => $post('keywords'),
        'default_quantity'        => $post('default_quantity'),
        'minimum_quantity'        => $post('minimum_quantity'),
        'maximum_quantity'        => $post('maximum_quantity'),
        'backorder'               => $switch('backorder'),
        'out_of_stock_message'    => prepare_rich_text_editor_content_for_input($post('out_of_stock_message')),
        'shippable'               => $switch('shippable'),
        'weight'                  => $weight,
        'length'                  => $length,
        'width'                   => $width,
        'height'                  => $height,
        'primary_weight_points'   => $post('primary_weight_points', 0),
        'secondary_weight_points' => $post('secondary_weight_points', 0),
        'container_required'      => $switch('container_required'),
        'preparation_time'        => $post('preparation_time'),
        'free_shipping'           => $switch('free_shipping'),
        'extra_shipping_cost'     => pg_pb_price_to_cents($post('extra_shipping_cost', 0)),
        'gtin'                    => $post('gtin'),
        'brand'                   => $post('brand'),
        'mpn'                     => $post('mpn'),
        'google_product_category' => $post('google_product_category'),
        'notes'                   => $post('notes'),

        // The product form's own settings. The fields themselves are drawn on
        // a separate screen, but these four have to be written now: a product
        // with fields and form = 0 renders nothing, which reads as "the form
        // did not save".
        //
        // POST name is product_form, column is form — the legacy screen's
        // naming, kept so the two forms stay interchangeable.
        'form'                    => $switch('product_form'),
        'form_name'               => $post('form_name'),
        'form_label_column_width' => $post('form_label_column_width'),
        'form_quantity_type'      => $post('form_quantity_type'),
    );

    /* ---------------------------------------------------- checkout */

    $common['required_product'] = $post('required_product');
    $common['custom_field_1']   = $post('custom_field_1');
    $common['custom_field_2']   = $post('custom_field_2');
    $common['custom_field_3']   = $post('custom_field_3');
    $common['custom_field_4']   = $post('custom_field_4');

    /* -------------------------------------------- order complete */

    $common['order_receipt_message']            = prepare_rich_text_editor_content_for_input($post('order_receipt_message'));
    $common['order_receipt_bcc_email_address']  = trim($post('order_receipt_bcc_email_address'));
    $common['email_page']                       = $post('email_page');
    $common['email_bcc']                        = trim($post('email_bcc'));
    $common['contact_group_id']                 = $post('contact_group_id');
    $common['membership_renewal']               = $post('membership_renewal');
    $common['reward_points']                    = $post('reward_points');

    $common['grant_private_access'] = $switch('grant_private_access');
    $common['private_folder']       = $post('private_folder');
    $common['private_days']         = $post('private_days');
    $common['send_to_page']         = $post('send_to_page');

    $common['gift_card']               = $switch('gift_card');
    $common['gift_card_email_subject'] = $post('gift_card_email_subject');
    $common['gift_card_email_format']  = $post('gift_card_email_format');
    $common['gift_card_email_body']    = $post('gift_card_email_body');
    $common['gift_card_email_page_id'] = $post('gift_card_email_page_id');

    /* ------------------------------------------------- recurring */

    $common['recurring']                               = $switch('recurring');
    $common['recurring_schedule_editable_by_customer'] = $switch('recurring_schedule_editable_by_customer');
    $common['number_of_payments']                      = $post('number_of_payments');
    $common['payment_period']                          = $post('payment_period');

    // Columns behind a configuration gate are left out entirely when the gate
    // is closed, so the row keeps its database default. Writing '' instead
    // would be a decision the operator never made — and these three blocks are
    // invisible on the screen when their gate is closed, so there is nothing to
    // have decided.
    if (defined('ECOMMERCE_PAYMENT_GATEWAY') && ECOMMERCE_PAYMENT_GATEWAY != 'ClearCommerce') {
        $common['start'] = $post('start', 0);
    }

    if (defined('AFFILIATE_PROGRAM') && AFFILIATE_PROGRAM == TRUE) {
        $common['commissionable']        = $switch('commissionable');
        $common['commission_rate_limit'] = $post('commission_rate_limit');
    }

    if (defined('ECOMMERCE_CREDIT_DEBIT_CARD') && ECOMMERCE_CREDIT_DEBIT_CARD == TRUE
        && defined('ECOMMERCE_PAYMENT_GATEWAY') && ECOMMERCE_PAYMENT_GATEWAY == 'PayPal Payments Pro') {
        $common['recurring_profile_disabled_perform_actions']     = $switch('recurring_profile_disabled_perform_actions');
        $common['recurring_profile_disabled_expire_membership']   = $switch('recurring_profile_disabled_expire_membership');
        $common['recurring_profile_disabled_revoke_private_access'] = $switch('recurring_profile_disabled_revoke_private_access');
        $common['recurring_profile_disabled_email']               = $switch('recurring_profile_disabled_email');
        $common['recurring_profile_disabled_email_subject']       = $post('recurring_profile_disabled_email_subject');
        $common['recurring_profile_disabled_email_page_id']       = $post('recurring_profile_disabled_email_page_id');
    }

    if (defined('ECOMMERCE_CREDIT_DEBIT_CARD') && ECOMMERCE_CREDIT_DEBIT_CARD == TRUE
        && defined('ECOMMERCE_PAYMENT_GATEWAY') && ECOMMERCE_PAYMENT_GATEWAY == 'Sage') {
        $common['sage_group_id'] = $post('sage_group_id');
    }

    return $common;
}


/**
 * Decode and normalise the variant matrix the browser posted.
 *
 * Shape of one row (same contract assets/product_builder.js writes):
 *
 *   {
 *     "name":              "TSHIRT-RED-S",     // products.name (SKU)
 *     "short_description": "T-Shirt Red / S",
 *     "price":             "129.90",
 *     "inventory":         "1",                // "" when not tracked
 *     "inventory_quantity":"12",
 *     "images":            ["a.jpg", "b.jpg"], // first is the cover
 *     "attributes":        [{"attribute_id": 3, "option_id": 12}, ...]
 *   }
 *
 * Rows with a blank name are dropped rather than rejected: a matrix the
 * operator half-cleared should still save the rows that are complete.
 *
 * @param string $json
 * @return array list of normalised rows
 */
function pg_pb_decode_variants($json)
{
    $json = trim((string) $json);

    if ($json === '') {
        return array();
    }

    $rows = decode_json($json);

    if (!$rows || !is_array($rows)) {
        return array();
    }

    $variants = array();

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $name = isset($row['name']) ? trim($row['name']) : '';

        if ($name === '') {
            continue;
        }

        $images = array();

        if (!empty($row['images']) && is_array($row['images'])) {
            foreach ($row['images'] as $image) {
                $image = trim((string) $image);
                if ($image !== '') {
                    $images[] = $image;
                }
            }
        }

        $attributes = array();

        if (!empty($row['attributes']) && is_array($row['attributes'])) {
            foreach ($row['attributes'] as $attribute) {
                if (!is_array($attribute)) {
                    continue;
                }
                $attribute_id = isset($attribute['attribute_id']) ? (int) $attribute['attribute_id'] : 0;
                $option_id    = isset($attribute['option_id'])    ? (int) $attribute['option_id']    : 0;
                if ($attribute_id && $option_id) {
                    $attributes[] = array(
                        'attribute_id' => $attribute_id,
                        'option_id'    => $option_id,
                    );
                }
            }
        }

        // Recurring schedule, per variant. Absent means "use the product-wide
        // value"; present means this row was edited in its advanced panel. The
        // keys are column names and are checked against the common set before
        // being written, so a value posted for a column the store's gateway
        // does not have cannot slip in.
        $recurring = array();

        if (!empty($row['recurring']) && is_array($row['recurring'])) {

            $recurring_columns = array(
                'recurring_schedule_editable_by_customer',
                'start',
                'number_of_payments',
                'payment_period',
            );

            foreach ($recurring_columns as $recurring_column) {
                if (array_key_exists($recurring_column, $row['recurring'])) {
                    $recurring[$recurring_column] = $row['recurring'][$recurring_column];
                }
            }
        }

        $variants[] = array(
            'name'               => $name,
            'short_description'  => isset($row['short_description']) ? trim($row['short_description']) : '',
            'price'              => isset($row['price']) ? $row['price'] : '0',
            'inventory'          => empty($row['inventory']) ? '' : '1',
            'inventory_quantity' => isset($row['inventory_quantity']) ? trim($row['inventory_quantity']) : '',
            'images'             => $images,
            'attributes'         => $attributes,
            'recurring'          => $recurring,

            // Identifiers name one article, so they never come from the shared
            // fields — see the note on the hidden GTIN field in add_product.php.
            'gtin'               => isset($row['gtin']) ? trim($row['gtin']) : '',
            'barcode'            => isset($row['barcode']) ? trim($row['barcode']) : '',
        );
    }

    return $variants;
}


/* ------------------------------------------------------------------ *
 * The rule
 * ------------------------------------------------------------------ */

/**
 * Which shape does this submission produce?
 *
 * More than one combination means the operator is describing one sellable
 * thing that comes in several forms — that needs a group to hold them together
 * and to be what the catalog links to. One combination, or none, is just a
 * product; wrapping it in a group of one adds a catalog entry nobody asked for.
 *
 * @param array $variants output of pg_pb_decode_variants()
 * @return string 'group' or 'single'
 */
function pg_pb_mode($variants)
{
    return (count($variants) > 1) ? 'group' : 'single';
}


/* ------------------------------------------------------------------ *
 * Writing
 * ------------------------------------------------------------------ */

/**
 * Create the product group that holds a variant set.
 *
 * display_type is always 'select': that is what makes the catalog render the
 * group as one item with a variant picker instead of a browsable folder.
 *
 * @param array $group keys: name, parent_id, enabled, short_description,
 *                     full_description, details, code, keywords, images,
 *                     address_name, title, meta_description,
 *                     attribute_meta
 * @return int group id
 */
function pg_pb_create_group($group)
{
    $images = isset($group['images']) ? $group['images'] : array();
    $cover  = $images ? $images[0] : '';

    $name = get_unique_name(array('name' => trim($group['name']), 'type' => 'product_group'));

    // Address name falls back the same way the product one does: explicit,
    // then short description, then name.
    $address_source = trim(isset($group['address_name']) ? $group['address_name'] : '');

    if ($address_source === '') {
        $address_source = trim(isset($group['short_description']) ? $group['short_description'] : '');
    }

    if ($address_source === '') {
        $address_source = $name;
    }

    // The set's form settings live on the group, and the template fields hang
    // off it. Guarded because an install that took the code without running
    // 2026.4 has no such columns.
    $form_columns = array();

    if (pg_pb_form_template_ready() && !empty($group['form'])) {
        $form_columns = array(
            'form'                    => 1,
            'form_name'               => isset($group['form_name']) ? $group['form_name'] : '',
            'form_label_column_width' => isset($group['form_label_column_width']) ? $group['form_label_column_width'] : '',
            'form_quantity_type'      => isset($group['form_quantity_type']) ? $group['form_quantity_type'] : '',
        );
    }

    $group_id = pg_pb_insert_row('product_groups', array_merge($form_columns, array(
        'name'              => $name,
        'enabled'           => empty($group['enabled']) ? '' : '1',
        'parent_id'         => (int) (isset($group['parent_id']) ? $group['parent_id'] : 0),
        'short_description' => trim(isset($group['short_description']) ? $group['short_description'] : ''),
        'full_description'  => prepare_rich_text_editor_content_for_input(isset($group['full_description']) ? $group['full_description'] : ''),
        'details'           => prepare_rich_text_editor_content_for_input(isset($group['details']) ? $group['details'] : ''),
        'code'              => isset($group['code']) ? $group['code'] : '',
        'keywords'          => isset($group['keywords']) ? $group['keywords'] : '',
        'image_name'        => $cover,
        'display_type'      => 'select',
        'address_name'      => prepare_catalog_item_address_name($address_source),
        'title'             => trim(isset($group['title']) ? $group['title'] : ''),
        'meta_description'  => trim(isset($group['meta_description']) ? $group['meta_description'] : ''),
        'attributes'        => '1',
        'user'              => defined('USER_ID') ? USER_ID : 0,
        'timestamp'         => time(),
    )));

    // Extra images beyond the cover.
    foreach (array_slice($images, 1) as $image) {
        pg_pb_insert_row('product_groups_images_xref', array(
            'product_group' => $group_id,
            'file_name'     => $image,
        ));
    }

    // Which attributes this set varies on, their order, and the option that is
    // preselected on the product page.
    if (!empty($group['attribute_meta']) && is_array($group['attribute_meta'])) {
        $sort_order = 0;

        foreach ($group['attribute_meta'] as $meta) {
            $attribute_id = isset($meta['id']) ? (int) $meta['id'] : 0;

            if (!$attribute_id) {
                continue;
            }

            $sort_order++;

            pg_pb_insert_row('product_groups_attributes_xref', array(
                'product_group_id'  => $group_id,
                'attribute_id'      => $attribute_id,
                'default_option_id' => isset($meta['default_option_id']) ? (int) $meta['default_option_id'] : 0,
                'sort_order'        => $sort_order,
            ));
        }
    }

    return $group_id;
}


/**
 * Create one product row plus its cross-reference rows.
 *
 * @param array $product   column => value, already prepared
 * @param array $relations keys: images, group_ids, zone_ids, attributes
 * @return int product id
 */
function pg_pb_create_product($product, $relations = array())
{
    $images     = isset($relations['images'])     ? $relations['images']     : array();
    $group_ids  = isset($relations['group_ids'])  ? $relations['group_ids']  : array();
    $zone_ids   = isset($relations['zone_ids'])   ? $relations['zone_ids']   : array();
    $attributes = isset($relations['attributes']) ? $relations['attributes'] : array();

    // products.name is the SKU and has to be unique — the matrix can easily
    // produce a collision with an existing product.
    $product['name'] = get_unique_name(array('name' => trim($product['name']), 'type' => 'product'));

    $product['image_name'] = $images ? $images[0] : '';
    $product['user']       = defined('USER_ID') ? USER_ID : 0;
    $product['timestamp']  = time();

    // address_name needs the row's own id to disambiguate, so it is written in
    // a second pass below.
    $address_source = isset($product['address_name']) ? trim($product['address_name']) : '';
    unset($product['address_name']);

    $product_id = pg_pb_insert_row('products', $product);

    if ($address_source === '') {
        $address_source = trim($product['short_description']);
    }

    if ($address_source === '') {
        $address_source = $product['name'];
    }

    pg_pb_update_row(
        'products',
        array('address_name' => prepare_catalog_item_address_name($address_source, $product_id)),
        'id',
        $product_id);

    pg_pb_sync_tag_cloud_keywords(
        $product_id,
        isset($product['keywords']) ? $product['keywords'] : '',
        !empty($product['enabled']));

    pg_pb_save_submit_form_fields($product_id);

    foreach (array_slice($images, 1) as $image) {
        pg_pb_insert_row('products_images_xref', array(
            'product'   => $product_id,
            'file_name' => $image,
        ));
    }

    foreach ($group_ids as $group_id) {
        $group_id = (int) $group_id;
        if ($group_id) {
            pg_pb_insert_row('products_groups_xref', array(
                'product'       => $product_id,
                'product_group' => $group_id,
            ));
        }
    }

    foreach ($zone_ids as $zone_id) {
        $zone_id = (int) $zone_id;
        if ($zone_id) {
            pg_pb_insert_row('products_zones_xref', array(
                'product_id' => $product_id,
                'zone_id'    => $zone_id,
            ));
        }
    }

    $sort_order = 0;

    foreach ($attributes as $attribute) {
        $sort_order++;
        pg_pb_insert_row('products_attributes_xref', array(
            'product_id'   => $product_id,
            'attribute_id' => (int) $attribute['attribute_id'],
            'option_id'    => (int) $attribute['option_id'],
            'sort_order'   => $sort_order,
        ));
    }

    return $product_id;
}


/**
 * Save a whole submission: decide the shape, then write it.
 *
 * This is the only entry point the screens use. Returns a summary the caller
 * turns into a notice and a redirect:
 *
 *   array(
 *     'mode'         => 'single' | 'group',
 *     'group_id'     => int (0 in single mode),
 *     'product_ids'  => array of int,
 *     'name'         => string, the thing that was created
 *   )
 *
 * @return array
 */
/**
 * Remember the image code block as the site default.
 *
 * The field on the screen starts out filled from config.product_image_code_template,
 * so an operator who edits it there is editing what they believe is the site's
 * template. The screen this replaced wrote it back; without that the next
 * product opens with the old block, or with nothing at all if the site never had
 * one — and a catalog whose images are drawn by that code then renders the first
 * product correctly and every later one blank.
 *
 * The three token guard is legacy's and is the point of the check: only a block
 * that actually loops over images is a template. A one-off snippet written for a
 * single product must not become what every future product starts with.
 */
function pg_pb_store_image_code_template($code)
{
    if ((mb_strpos($code, '^^image_url^^') === FALSE)
        or (mb_strpos($code, '^^image_loop_start^^') === FALSE)
        or (mb_strpos($code, '^^image_loop_end^^') === FALSE)) {
        return;
    }

    if (db_value("SELECT product_image_code_template FROM config") === $code) {
        return;
    }

    db("UPDATE config SET product_image_code_template = '" . e($code) . "'");
}
/**
 * Give a freshly created product its barcode, if the screen asked for one.
 *
 * Two ways in, and they are not the same request. A code typed into the row is
 * a code the operator already has printed somewhere, so it is written whether
 * or not the automatic switch is on. An empty one is only filled in when that
 * switch says so — a shop that prints its own labels later does not want a
 * generated code sitting in the way.
 *
 * The feature gate is checked here as well as in the screen. The switch is not
 * rendered when barcodes are off, and a control that is not rendered is not a
 * permission check.
 */
function pg_pb_apply_barcode($product_id, $barcode)
{
    if (!defined('BARCODE_ENABLED') or !BARCODE_ENABLED) {
        return;
    }

    $barcode = trim((string) $barcode);

    if (($barcode === '') && empty($_POST['pg_pb_barcode_auto'])) {
        return;
    }

    pg_assign_product_barcode($product_id, $barcode);
}
function pg_pb_save_new_product()
{
    $variants = pg_pb_decode_variants(isset($_POST['variants_json']) ? $_POST['variants_json'] : '');
    $mode     = pg_pb_mode($variants);

    $common = pg_pb_common_from_post();
    $images = pg_pb_selected_images();

    // Once, before either branch — the block is the same for a single product
    // and for a whole variant set.
    pg_pb_store_image_code_template(isset($_POST['code']) ? $_POST['code'] : '');

    $catalog_group_ids = array();

    if (!empty($_POST['catalog_group_ids']) && is_array($_POST['catalog_group_ids'])) {
        foreach ($_POST['catalog_group_ids'] as $catalog_group_id) {
            $catalog_group_id = (int) $catalog_group_id;
            if ($catalog_group_id) {
                $catalog_group_ids[] = $catalog_group_id;
            }
        }
    }

    $zone_ids = (!empty($_POST['allowed_zones']) && is_array($_POST['allowed_zones']))
        ? $_POST['allowed_zones']
        : array();

    $product_name       = isset($_POST['name']) ? trim($_POST['name']) : '';
    $short_description  = isset($_POST['short_description']) ? trim($_POST['short_description']) : '';
    $full_description   = isset($_POST['full_description']) ? $_POST['full_description'] : '';
    $details            = isset($_POST['details']) ? $_POST['details'] : '';

    /* ---------------------------------------------------------- group mode */

    if ($mode === 'group') {

        $attribute_meta = array();

        if (!empty($_POST['attributes_meta_json'])) {
            $decoded = decode_json($_POST['attributes_meta_json']);
            if (is_array($decoded)) {
                $attribute_meta = $decoded;
            }
        }

        $group_id = pg_pb_create_group(array(
            'name'              => $short_description !== '' ? $short_description : $product_name,
            'parent_id'         => isset($_POST['parent_group_id']) ? (int) $_POST['parent_group_id'] : 0,
            'enabled'           => !empty($_POST['enabled']),
            'short_description' => $short_description,
            'full_description'  => $full_description,
            'details'           => $details,
            'code'              => isset($_POST['code']) ? $_POST['code'] : '',
            'keywords'          => isset($_POST['keywords']) ? $_POST['keywords'] : '',
            'images'            => $images,
            'address_name'      => isset($_POST['address_name']) ? $_POST['address_name'] : '',
            'title'             => isset($_POST['title']) ? $_POST['title'] : '',
            'meta_description'  => isset($_POST['meta_description']) ? $_POST['meta_description'] : '',
            'attribute_meta'    => $attribute_meta,

            // The form belongs to the set, not to any one variant: the fields
            // are drawn once against this group and copied down.
            'form'                    => !empty($_POST['product_form']),
            'form_name'               => isset($_POST['form_name']) ? $_POST['form_name'] : '',
            'form_label_column_width' => isset($_POST['form_label_column_width']) ? $_POST['form_label_column_width'] : '',
            'form_quantity_type'      => isset($_POST['form_quantity_type']) ? $_POST['form_quantity_type'] : '',
        ));

        $product_ids = array();

        foreach ($variants as $variant) {

            $product = $common;

            $product['name']              = $variant['name'];
            $product['short_description'] = $variant['short_description'];
            $product['price']             = pg_pb_price_to_cents($variant['price']);
            $product['full_description']  = prepare_rich_text_editor_content_for_input($full_description);
            $product['details']           = prepare_rich_text_editor_content_for_input($details);
            $product['title']             = isset($_POST['title']) ? trim($_POST['title']) : '';
            $product['meta_description']  = isset($_POST['meta_description']) ? trim($_POST['meta_description']) : '';
            $product['inventory']         = $variant['inventory'];
            $product['inventory_quantity'] = $variant['inventory_quantity'];

            // The shared field is hidden while a set is being built, so whatever
            // is posted in it is stale. The row wins, empty included.
            $product['gtin']              = $variant['gtin'];

            // Each variant needs its own catalog address: short description
            // first, product name otherwise. Inherited from the variant wizard
            // that this screen replaced.
            //
            // This was left empty on the assumption that a variant is only ever
            // reached through its group. It is not: the address is what builds
            // the product's own URL in the sitemap, the RSS feed and the
            // JSON-LD, and an empty one sends prepare_catalog_item_address_name
            // looking for duplicates of "" — so the whole set ends up sharing
            // one blank address with [1], [2] appended.
            $product['address_name'] = ($variant['short_description'] !== '')
                ? $variant['short_description']
                : $variant['name'];

            // Per-variant recurring overrides. Filtered against $common, which
            // is what carries the store's gateway gates: if "start" is not in
            // there because the gateway is ClearCommerce, a posted start is
            // dropped rather than written to a column the screen never showed.
            if (!empty($variant['recurring'])) {
                foreach ($variant['recurring'] as $recurring_column => $recurring_value) {
                    if (array_key_exists($recurring_column, $common)) {
                        $product[$recurring_column] = $recurring_value;
                    }
                }
            }

            // A variant with no images of its own falls back to the set's
            // images, so the cart and the catalog never show a blank tile.
            $variant_images = $variant['images'] ? $variant['images'] : $images;

            // The variant set is the only group a variant belongs to. Putting
            // every variant in a browsable category as well would list the same
            // shirt five times in that category — the group is what the catalog
            // is meant to show, and it reaches the category through its
            // parent_id. That is why the category picker is hidden in group
            // mode rather than quietly ignored.
            $variant_product_id = pg_pb_create_product($product, array(
                'images'     => $variant_images,
                'group_ids'  => array($group_id),
                'zone_ids'   => $zone_ids,
                'attributes' => $variant['attributes'],
            ));

            pg_pb_apply_barcode($variant_product_id, $variant['barcode']);

            $product_ids[] = $variant_product_id;
        }

        return array(
            'mode'        => 'group',
            'group_id'    => $group_id,
            'product_ids' => $product_ids,
            'name'        => $short_description !== '' ? $short_description : $product_name,
            'form'        => !empty($_POST['product_form']),
        );
    }

    /* --------------------------------------------------------- single mode */

    // A single-combination matrix still carries its attribute pair and may
    // carry an overridden SKU or price; an empty matrix means the operator
    // never opened the variant step.
    $single = $variants ? $variants[0] : NULL;

    $product = $common;

    $product['name']               = ($single && $single['name'] !== '') ? $single['name'] : $product_name;
    $product['short_description']  = ($single && $single['short_description'] !== '') ? $single['short_description'] : $short_description;
    $product['price']              = pg_pb_price_to_cents($single ? $single['price'] : (isset($_POST['price']) ? $_POST['price'] : 0));
    $product['full_description']   = prepare_rich_text_editor_content_for_input($full_description);
    $product['details']            = prepare_rich_text_editor_content_for_input($details);
    $product['title']              = isset($_POST['title']) ? trim($_POST['title']) : '';
    $product['meta_description']   = isset($_POST['meta_description']) ? trim($_POST['meta_description']) : '';
    $product['inventory']          = empty($_POST['inventory']) ? '' : '1';
    $product['inventory_quantity'] = isset($_POST['inventory_quantity']) ? trim($_POST['inventory_quantity']) : '';
    $product['address_name']       = isset($_POST['address_name']) ? $_POST['address_name'] : '';

    $single_images = ($single && $single['images']) ? $single['images'] : $images;

    $product_id = pg_pb_create_product($product, array(
        'images'     => $single_images,
        'group_ids'  => $catalog_group_ids,
        'zone_ids'   => $zone_ids,
        'attributes' => $single ? $single['attributes'] : array(),
    ));

    pg_pb_apply_barcode($product_id, isset($_POST['pg_pb_barcode']) ? $_POST['pg_pb_barcode'] : '');

    return array(
        'mode'        => 'single',
        'group_id'    => 0,
        'product_ids' => array($product_id),
        'name'        => $product['name'],
        'form'        => !empty($_POST['product_form']),
    );
}


/* ------------------------------------------------------------------ *
 * Product form template  (schema: upgrade_to_2026_4)
 * ------------------------------------------------------------------ */

/**
 * Is the 2026.4 schema present?
 *
 * The v2 screens ship ahead of the database on installs that take the code but
 * postpone the upgrade. Everything to do with form templates is gated on this
 * so those installs keep working with the feature simply absent, rather than
 * erroring on a missing column.
 *
 * @return bool
 */
function pg_pb_form_template_ready()
{
    static $ready = NULL;

    if ($ready !== NULL) {
        return $ready;
    }

    $ready = (bool) db_item("SHOW COLUMNS FROM form_fields LIKE 'product_group_id'");

    return $ready;
}


/**
 * The template fields of a variant set, in display order.
 *
 * @param int $group_id
 * @return array
 */
function pg_pb_template_fields($group_id)
{
    if (!pg_pb_form_template_ready()) {
        return array();
    }

    $group_id = (int) $group_id;

    return db_items(
        "SELECT *
        FROM form_fields
        WHERE
            (form_type = 'product_group')
            AND (product_group_id = '$group_id')
        ORDER BY sort_order, id");
}


/**
 * Copy one row, minus the columns that identify it.
 *
 * SELECT * plus an unset list rather than a written-out column list: form_fields
 * has picked up columns over the years (rss_field, quiz_question, contact_field,
 * upload_folder_id …) and a hand-maintained list here would quietly stop copying
 * whichever one gets added next.
 *
 * @param array $row
 * @param array $overrides column => value applied after the identity columns are dropped
 * @param array $drop      extra columns to leave out
 * @return array
 */
function pg_pb_copy_row($row, $overrides = array(), $drop = array())
{
    unset($row['id']);

    foreach ($drop as $column) {
        unset($row[$column]);
    }

    foreach ($overrides as $column => $value) {
        $row[$column] = $value;
    }

    return $row;
}


/**
 * Write the group's form template onto every product in the set.
 *
 * Rules, and they are stated in the UI in these words:
 *
 *   Fields that came from the template are rewritten.
 *   Fields added to a variant by hand are kept.
 *
 * The second half is what template_field_id buys: a copy carries the id of the
 * template row it came from, so "delete what I generated last time" is a
 * WHERE clause rather than a guess. Detecting *edits* to a copy was considered
 * and dropped — it needs a hook in edit_field.php and a field-by-field compare
 * to answer a question the sentence above already answers.
 *
 * Runs after every field change rather than behind an "apply" button: a
 * template the operator drew but never applied leaves a set whose products have
 * no form, and nothing on screen says so.
 *
 * @param int $group_id
 * @return array counts: products, fields
 */
function pg_pb_apply_form_template($group_id)
{
    $result = array('products' => 0, 'fields' => 0);

    if (!pg_pb_form_template_ready()) {
        return $result;
    }

    $group_id = (int) $group_id;

    $group = db_items(
        "SELECT id, form, form_name, form_label_column_width, form_quantity_type
        FROM product_groups
        WHERE id = '$group_id'
        LIMIT 1");

    $group = $group ? $group[0] : NULL;

    if (!$group) {
        return $result;
    }

    $template_fields = pg_pb_template_fields($group_id);
    $products        = pg_pb_group_products($group_id);

    foreach ($products as $product) {

        $product_id = (int) $product['id'];

        // Clear the previous generation. Children go first: once the field row
        // is gone there is nothing left to find its options by, and they would
        // sit in the table forever.
        $previous = db_items(
            "SELECT id
            FROM form_fields
            WHERE
                (form_type = 'product')
                AND (product_id = '$product_id')
                AND (template_field_id != '0')");

        foreach ($previous as $previous_field) {
            db("DELETE FROM target_options WHERE trigger_form_field_id = '" . (int) $previous_field['id'] . "'");
            db("DELETE FROM form_field_options WHERE form_field_id = '" . (int) $previous_field['id'] . "'");
            db("DELETE FROM form_fields WHERE id = '" . (int) $previous_field['id'] . "'");
        }

        // Three passes, because the rows point at each other by id and every id
        // changes in the copy. Doing it in one pass leaves the copy's triggers
        // pointing at the template's field and option rows — a variant's form
        // reacting to a field that is not on it.
        $field_map  = array();
        $option_map = array();

        /* pass 1 — fields */
        foreach ($template_fields as $template_field) {

            $copy = pg_pb_copy_row(
                $template_field,
                array(
                    'form_type'         => 'product',
                    'product_id'        => $product_id,
                    'product_group_id'  => $group_id,
                    'template_field_id' => (int) $template_field['id'],
                    'user'              => defined('USER_ID') ? USER_ID : 0,
                    'timestamp'         => time(),
                ));

            $field_map[(int) $template_field['id']] = pg_pb_insert_row('form_fields', $copy);

            $result['fields']++;
        }

        /* pass 2 — options */
        foreach ($template_fields as $template_field) {

            $template_field_id = (int) $template_field['id'];

            $options = db_items(
                "SELECT *
                FROM form_field_options
                WHERE form_field_id = '$template_field_id'
                ORDER BY sort_order, id");

            foreach ($options as $option) {

                $overrides = array(
                    'form_field_id'    => $field_map[$template_field_id],
                    'product_id'       => $product_id,
                    'product_group_id' => 0,
                );

                // An option can point at another field on the same form. Remap
                // it, or drop it if that field is not part of the template.
                if (array_key_exists('target_form_field_id', $option) && (int) $option['target_form_field_id']) {
                    $target = (int) $option['target_form_field_id'];
                    $overrides['target_form_field_id'] = isset($field_map[$target]) ? $field_map[$target] : 0;
                }

                $option_map[(int) $option['id']] = pg_pb_insert_row(
                    'form_field_options',
                    pg_pb_copy_row($option, $overrides));
            }
        }

        /* pass 3 — triggers */
        foreach ($template_fields as $template_field) {

            $triggers = db_items(
                "SELECT *
                FROM target_options
                WHERE trigger_form_field_id = '" . (int) $template_field['id'] . "'");

            foreach ($triggers as $trigger) {

                $trigger_option = (int) $trigger['trigger_option_id'];

                if (!isset($option_map[$trigger_option])) {
                    continue;
                }

                pg_pb_insert_row('target_options', pg_pb_copy_row(
                    $trigger,
                    array(
                        'trigger_form_field_id' => $field_map[(int) $template_field['id']],
                        'trigger_option_id'     => $option_map[$trigger_option],
                        'product_id'            => $product_id,
                        'product_group_id'      => 0,
                    )));
            }
        }

        // The four settings that live next to the fields. A product with rows
        // but form = 0 renders nothing, which reads as "the template did not
        // apply".
        pg_pb_update_row('products', array(
            'form'                    => $group['form'] ? '1' : '',
            'form_name'               => $group['form_name'],
            'form_label_column_width' => $group['form_label_column_width'],
            'form_quantity_type'      => $group['form_quantity_type'],
        ), 'id', $product_id);

        $result['products']++;
    }

    return $result;
}


/**
 * Remove a set's template and everything generated from it.
 *
 * Used when the operator turns the form off for a set. Fields they added to a
 * single variant by hand survive, same rule as applying.
 *
 * @param int $group_id
 * @return void
 */
function pg_pb_clear_form_template($group_id)
{
    if (!pg_pb_form_template_ready()) {
        return;
    }

    $group_id = (int) $group_id;

    $rows = db_items(
        "SELECT id
        FROM form_fields
        WHERE
            (product_group_id = '$group_id')
            AND ((form_type = 'product_group') OR (template_field_id != '0'))");

    foreach ($rows as $row) {
        db("DELETE FROM target_options WHERE trigger_form_field_id = '" . (int) $row['id'] . "'");
        db("DELETE FROM form_field_options WHERE form_field_id = '" . (int) $row['id'] . "'");
        db("DELETE FROM form_fields WHERE id = '" . (int) $row['id'] . "'");
    }

    foreach (pg_pb_group_products($group_id) as $product) {
        pg_pb_update_row('products', array('form' => ''), 'id', (int) $product['id']);
    }

    pg_pb_update_row('product_groups', array('form' => 0), 'id', $group_id);
}


/* ------------------------------------------------------------------ *
 * Google product taxonomy
 *
 * The feed writes products.google_product_category into
 * <g:google_product_category> verbatim (functions.php). Google accepts either
 * the numeric id or the full path there, and the id is what gets stored: it is
 * language-independent, so a Turkish feed is not broken by an English path, and
 * it survives Google renaming a category.
 *
 * The taxonomy is ~5,595 categories. Rendering it as <option> elements is about
 * 785 KB of markup and 5,595 DOM nodes, and select2 copies every one of them
 * into its own results list and rescans them on each keystroke. So the list
 * never reaches the browser: the field is a select2 in AJAX mode and the search
 * happens here.
 *
 * The files are shipped with the software and edited by hand — Google last
 * revised the taxonomy in 2021, so there is nothing to keep in sync and no
 * reason to depend on an outbound connection that half of the hosts this runs
 * on cannot make.
 *
 *   assets/google_taxonomy/google_taxonomy_tr.json
 *   assets/google_taxonomy/google_taxonomy_en.json
 *
 * Shape: a flat object of id => full path.
 *
 *   { "371": "Home & Garden > Kitchen & Dining > ...", ... }
 * ------------------------------------------------------------------ */

/**
 * Which taxonomy file to use.
 *
 * Google publishes one list per language and the feed has to carry the category
 * in the feed's own language. Defaults to the software language, so a Turkish
 * install picks up the Turkish list without configuring anything.
 *
 * @return string 'tr', 'en', ...
 */
function pg_pb_google_taxonomy_locale()
{
    if (defined('ECOMMERCE_GOOGLE_TAXONOMY_LOCALE') && ECOMMERCE_GOOGLE_TAXONOMY_LOCALE != '') {
        return ECOMMERCE_GOOGLE_TAXONOMY_LOCALE;
    }

    $language = lang(array('info' => TRUE));

    return ($language === 'tr') ? 'tr' : 'en';
}


/**
 * Full path of the taxonomy file for the active language, or '' when it is not
 * installed.
 *
 * The language is used as part of a file name, so it is constrained to letters.
 * Without that, a config value of "../../data/config" would read whatever the
 * web server can read.
 *
 * @return string
 */
function pg_pb_google_taxonomy_file()
{
    $locale = pg_pb_google_taxonomy_locale();

    if (!preg_match('/^[A-Za-z]{2,8}$/', $locale)) {
        return '';
    }

    $path = dirname(__FILE__) . '/assets/google_taxonomy/google_taxonomy_' . $locale . '.json';

    return is_readable($path) ? $path : '';
}


/**
 * The whole map, id => path.
 *
 * Decoded once per request. A single search decodes it anyway, and the edit
 * screen looks up a stored id right after rendering the field, so caching turns
 * two decodes into one.
 *
 * @return array
 */
function pg_pb_google_taxonomy_map()
{
    static $cache = NULL;

    if ($cache !== NULL) {
        return $cache;
    }

    $cache = array();

    $path = pg_pb_google_taxonomy_file();

    if ($path === '') {
        return $cache;
    }

    $decoded = decode_json((string) @file_get_contents($path));

    // A malformed file is treated as no file at all: the field falls back to
    // free text rather than the screen erroring on something the operator can
    // only fix by editing JSON.
    if (is_array($decoded)) {
        $cache = $decoded;
    }

    return $cache;
}


/**
 * Search the taxonomy.
 *
 * Leaf matches come first: somebody typing "coffee" wants
 * "... > Coffee Makers & Espresso Machines", not every category whose ancestor
 * mentions coffee.
 *
 * Matched against the path only. Nobody searches for "371", and including the
 * id would make a query like "12" match a hundred unrelated categories by their
 * number.
 *
 * @param string $query
 * @param int    $limit
 * @return array list of array('id' => string, 'path' => string)
 */
function pg_pb_google_taxonomy_search($query, $limit = 30)
{
    $query = trim($query);

    if (mb_strlen($query) < 2) {
        return array();
    }

    $leaf_matches = array();
    $path_matches = array();

    foreach (pg_pb_google_taxonomy_map() as $id => $path) {

        $path = (string) $path;

        if (mb_stripos($path, $query) === FALSE) {
            continue;
        }

        $segments = explode('>', $path);
        $leaf     = trim(end($segments));

        $entry = array('id' => (string) $id, 'path' => $path);

        if (mb_stripos($leaf, $query) !== FALSE) {
            $leaf_matches[] = $entry;
        } else {
            $path_matches[] = $entry;
        }

        // Enough to fill the list twice over; stopping early keeps the scan
        // bounded on a query like "ar" that matches nearly everything.
        if (count($leaf_matches) + count($path_matches) >= ($limit * 4)) {
            break;
        }
    }

    return array_slice(array_merge($leaf_matches, $path_matches), 0, $limit);
}


/**
 * The readable path for a stored category value.
 *
 * The column holds an id for anything picked from the list, and free text for
 * anything typed by hand or entered before this screen existed. The feed
 * accepts both, so neither is corrected — this only decides what to show.
 *
 * @param string $value
 * @return string
 */
function pg_pb_google_taxonomy_lookup($value)
{
    $value = trim($value);

    // Anything that is not a bare number is already a path, or something the
    // operator typed on purpose. Nothing to look up.
    if ($value === '' || !preg_match('/^[0-9]+$/', $value)) {
        return $value;
    }

    $map = pg_pb_google_taxonomy_map();

    return isset($map[$value]) ? (string) $map[$value] : $value;
}


/**
 * What is installed for the active language.
 *
 * @return array installed (bool), count (int), locale
 */
function pg_pb_google_taxonomy_status()
{
    $map = pg_pb_google_taxonomy_map();

    return array(
        'installed' => (count($map) > 0),
        'count'     => count($map),
        'locale'    => pg_pb_google_taxonomy_locale(),
    );
}


/* ------------------------------------------------------------------ *
 * Deleting
 * ------------------------------------------------------------------ */

/**
 * Delete one product and everything that references it.
 *
 * The statement list mirrors the delete branch of edit_products.php. That
 * screen is production and stays the reference: if a table is added to its
 * cleanup, it has to be added here too. Duplicating it is the lesser evil —
 * the alternative is posting to a bulk screen from a JSON action and hoping the
 * redirect lands somewhere useful.
 *
 * Order history is unaffected: order_items keeps its own product_name and price
 * snapshot, so a deleted product does not blank out past orders.
 *
 * @param int $product_id
 * @return void
 */
function pg_pb_delete_product($product_id)
{
    $product_id = (int) $product_id;

    if (!$product_id) {
        return;
    }

    db("DELETE FROM products WHERE id = '$product_id'");

    // Stored SEO structure findings. Guarded on the table existing, because
    // a product can be deleted on an installation that has not run the
    // upgrade that creates it.
    if (db_item("SHOW TABLES LIKE 'seo_issue'")) {
        db("DELETE FROM seo_issue WHERE (entity_type = 'product') AND (entity_id = '$product_id')");
    }

    if (db_item("SHOW TABLES LIKE 'seo_link'")) {
        db("DELETE FROM seo_link WHERE (from_type = 'product') AND (from_id = '$product_id')");
        db("DELETE FROM seo_link WHERE (to_type = 'product') AND (to_id = '$product_id')");
    }

    db("DELETE FROM products_groups_xref WHERE product = '$product_id'");
    db("DELETE FROM products_zones_xref WHERE product_id = '$product_id'");
    db("DELETE FROM products_images_xref WHERE product = '$product_id'");
    db("DELETE FROM products_attributes_xref WHERE product_id = '$product_id'");
    db("DELETE FROM product_submit_form_fields WHERE product_id = '$product_id'");
    db("DELETE FROM offer_rules_products_xref WHERE product_id = '$product_id'");

    // The "!= 0" guard is inherited from edit_products.php: product_id 0 means
    // "belongs to a page", and dropping that guard deletes every page form.
    db("DELETE FROM form_fields WHERE (product_id = '$product_id') AND (product_id != '0')");
    db("DELETE FROM form_field_options WHERE (product_id = '$product_id') AND (product_id != '0')");
    db("DELETE FROM target_options WHERE (product_id = '$product_id') AND (product_id != '0')");

    db("DELETE FROM tag_cloud_keywords WHERE (item_id = '$product_id') AND (item_type = 'product')");
    db("DELETE FROM tag_cloud_keywords_xref WHERE (item_id = '$product_id') AND (item_type = 'product')");

    db("DELETE FROM short_links WHERE (destination_type = 'product') AND (product_id = '$product_id')");
}


/**
 * Delete a variant set: the group and, by default, the products in it.
 *
 * Products are deleted along with the set because a variant only exists as one
 * form of that set. Keeping them would leave rows that show up in the product
 * list, belong to no catalog group, and cannot be reached from the storefront —
 * rubbish the operator has to find and clear by hand later.
 *
 * The group half mirrors the delete branch of edit_product_group.php, including
 * the search-results tag cloud rebuild: the keywords for a search page are
 * derived from the groups under it, so they have to be dropped before the group
 * disappears and rebuilt afterwards.
 *
 * @param int  $group_id
 * @param bool $delete_products
 * @return array counts: group (0/1) and products
 */
function pg_pb_delete_variant_set($group_id, $delete_products = TRUE)
{
    $group_id = (int) $group_id;

    $result = array('group' => 0, 'products' => 0);

    if (!$group_id) {
        return $result;
    }

    $group = db_items("SELECT id, name, parent_id FROM product_groups WHERE id = '$group_id' LIMIT 1");
    $group = $group ? $group[0] : NULL;

    if (!$group) {
        return $result;
    }

    // Same refusal edit_product_group.php makes: the root group is the catalog
    // itself, and deleting it takes every group with it.
    if ((int) $group['parent_id'] === 0) {
        return $result;
    }

    if ($delete_products) {
        foreach (pg_pb_group_products($group_id) as $product) {
            pg_pb_delete_product($product['id']);
            $result['products']++;
        }
    }

    // Search results pages whose tag cloud covers this group.
    $affected_pages = array();

    $search_results_pages = db_items(
        "SELECT page_id, product_group_id
        FROM search_results_pages
        WHERE search_catalog_items = '1'");

    foreach ($search_results_pages as $search_results_page) {

        $tree = get_product_groups_in_product_group_tree($search_results_page['product_group_id']);

        foreach ($tree as $tree_group) {
            if ((int) $tree_group['id'] === $group_id) {
                $affected_pages[] = $search_results_page;
                break;
            }
        }
    }

    foreach ($affected_pages as $affected_page) {
        delete_tag_cloud_keywords_for_search_results_page($affected_page['page_id']);
    }

    db("DELETE FROM product_groups WHERE id = '$group_id'");

    // Stored SEO structure findings, guarded the same way as the product
    // path above.
    if (db_item("SHOW TABLES LIKE 'seo_issue'")) {
        db("DELETE FROM seo_issue WHERE (entity_type = 'product_group') AND (entity_id = '$group_id')");
    }

    if (db_item("SHOW TABLES LIKE 'seo_link'")) {
        db("DELETE FROM seo_link WHERE (from_type = 'product_group') AND (from_id = '$group_id')");
        db("DELETE FROM seo_link WHERE (to_type = 'product_group') AND (to_id = '$group_id')");
    }

    db("DELETE FROM products_groups_xref WHERE product_group = '$group_id'");
    db("DELETE FROM product_groups_images_xref WHERE product_group = '$group_id'");
    db("DELETE FROM product_groups_attributes_xref WHERE product_group_id = '$group_id'");
    db("DELETE FROM short_links WHERE (destination_type = 'product_group') AND (product_group_id = '$group_id')");

    foreach ($affected_pages as $affected_page) {
        update_tag_cloud_keywords_for_search_results_page_product_group(
            $affected_page['page_id'],
            $affected_page['product_group_id']);
    }

    $result['group'] = 1;
    $result['name']  = $group['name'];

    return $result;
}


/* ------------------------------------------------------------------ *
 * Shared markup
 * ------------------------------------------------------------------ */

/**
 * Empty state for "there is nothing to pick yet".
 *
 * An empty area with no explanation reads as a bug. Say what is missing and
 * link to where it is created.
 *
 * @param string $icon    Bootstrap Icons class
 * @param string $title
 * @param string $message
 * @param string $action_url
 * @param string $action_label
 * @return string
 */
function pg_pb_render_empty_state($icon, $title, $message, $action_url = '', $action_label = '')
{
    $output_action = '';

    if ($action_url !== '' && $action_label !== '') {
        $output_action =
            '<a href="' . h($action_url) . '" class="btn btn-sm btn-outline-primary mt-2">
                <i class="bi bi-plus-circle me-2"></i>' . h($action_label) . '</a>';
    }

    return
        '<div class="text-center text-muted border border-2 border-dashed rounded py-4 px-3">
            <i class="bi ' . h($icon) . ' d-block mb-2" style="font-size:1.75rem;opacity:.5;"></i>
            <div class="fw-semibold text-body">' . h($title) . '</div>
            <div class="small">' . h($message) . '</div>
            ' . $output_action . '
        </div>';
}



/**
 * Two wireframes of the product being typed, plus what is still missing.
 *
 * Deliberately not a rendering of the real catalog template. The store's own
 * card is a designer artefact that varies per site, and a preview that claims
 * to be exact is worse than one that admits it is a sketch: the operator starts
 * treating small differences as bugs. Grey bars say "this is where the short
 * description goes" without promising a font.
 *
 * What it is for is the part that is exact — whether a cover image was picked,
 * whether the short description is empty, what price a visitor will see. Those
 * come from the fields as they are typed.
 *
 * The detail wireframe stays symbolic on purpose; the layout of that page is
 * not decided yet, so anything more specific would have to be redrawn.
 */
function pg_pb_render_preview($show_summary = TRUE)
{
    $bar = '<div class="pg-pb-wf-bar"></div>';

    return
        '<div class="card mb-3" id="pg_pb_preview">
            <div class="card-body p-2">

                <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="bi bi-eye text-primary"></i>
                    <span class="small fw-semibold">' . lang('Preview') . '</span>
                </div>

                <!--
                    What the save button is about to do. Only while creating: on
                    the edit screen the product already exists and whether a
                    group is created is not a question any more, so the line
                    would be describing a decision nobody is making.
                -->
                <!--
                    (create only) What the save button is about to do. It used to be an alert
                    box wrapped around the button itself, which made the loudest
                    thing on the screen a sentence the operator had already read.
                    Here it introduces the sketches below it, which is what it
                    actually describes.
                -->
' . ($show_summary
                    ? '<div id="pg_pb_summary" class="d-flex align-items-start gap-2 small text-body-secondary border-bottom pb-2 mb-3">
                        <i id="pg_pb_summary_icon" class="bi bi-box mt-1"></i>
                        <span id="pg_pb_summary_text">' . lang('A single product will be created. No catalog group is added.') . '</span>
                    </div>'
                    : '') . '

                <!-- catalog card -->
                <div class="pg-pb-wf mb-2">
                    <div class="pg-pb-wf-caption">' . lang('In the catalog') . '</div>
                    <div class="pg-pb-wf-box pg-pb-wf-card">
                        <div class="pg-pb-wf-image" id="pg_pb_wf_image">
                            <i class="bi bi-image"></i>
                        </div>
                        <div class="pg-pb-wf-line fw-semibold" id="pg_pb_wf_name">' . $bar . '</div>
                        <div class="pg-pb-wf-line text-muted" id="pg_pb_wf_short">' . $bar . '</div>
                        <div class="pg-pb-wf-line" id="pg_pb_wf_price">' . $bar . '</div>
                    </div>
                </div>

                <!-- detail page, symbolic -->
                <div class="pg-pb-wf mb-2">
                    <div class="pg-pb-wf-caption">' . lang('On the product page') . '</div>
                    <div class="pg-pb-wf-box">
                        <div class="d-flex gap-2">
                            <div class="pg-pb-wf-image pg-pb-wf-image-sm flex-shrink-0" id="pg_pb_wf_image2">
                                <i class="bi bi-image"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="pg-pb-wf-line fw-semibold" id="pg_pb_wf_name2">' . $bar . '</div>
                                <div class="pg-pb-wf-line text-muted" id="pg_pb_wf_short2">' . $bar . '</div>
                                <div class="pg-pb-wf-line" id="pg_pb_wf_price2">' . $bar . '</div>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-1 mt-2" id="pg_pb_wf_options"></div>
                        <div class="pg-pb-wf-block mt-2">
                            <div class="pg-pb-wf-blocklabel">' . lang('Details') . '</div>
                            <div id="pg_pb_wf_details">' . $bar . $bar . $bar . '</div>
                        </div>
                    </div>
                </div>

                <!--
                    Search result. The browser title and the browser description
                    are the two fields on this screen whose whole purpose is to
                    be read somewhere the operator never looks, so showing them
                    in the shape they will appear in is most of the point.

                    Clamped to two lines like a real result: a description that
                    runs to four lines here is one that will be cut off there,
                    and seeing where the cut falls is worth more than a
                    character count on its own.
                -->
                <div class="pg-pb-wf mb-2">
                    <div class="pg-pb-wf-caption">' . lang('In search results') . '</div>
                    <div class="pg-pb-wf-box">
                        <div class="pg-pb-serp-url" id="pg_pb_wf_url"></div>
                        <div class="pg-pb-serp-title" id="pg_pb_wf_title">' . $bar . '</div>
                        <div class="pg-pb-serp-desc" id="pg_pb_wf_desc">' . $bar . $bar . '</div>
                    </div>
                </div>

                <!-- what is not filled in yet -->
                <div id="pg_pb_todo" class="small"></div>

            </div>
        </div>';
}


/**
 * The attribute / option picker that drives the matrix.
 *
 * Options are toggle chips (Bootstrap btn-check), not a wall of checkboxes: a
 * store with six attributes and forty options is unreadable as checkboxes, and
 * the operator's actual question here is "which of these does this product come
 * in", which reads better as a set of pills that light up.
 *
 * The JS reads data-attr-id / data-attr-name off the rows and the value /
 * data-label off each input; the markup around them is free to change.
 *
 * @param array $selected_option_ids option ids to tick
 * @param array $default_option_ids  attribute_id => option_id
 * @return string
 */
function pg_pb_render_dimensions($selected_option_ids = array(), $default_option_ids = array(), $single_choice = FALSE)
{
    $attributes = pg_pb_attributes();

    $output   = '';
    $rendered = 0;

    foreach ($attributes as $attribute) {

        if (empty($attribute['options'])) {
            continue;
        }

        $rendered++;

        $default_option_id = isset($default_option_ids[$attribute['id']]) ? (int) $default_option_ids[$attribute['id']] : 0;

        $output .= pg_pb_render_attribute_card($attribute, $selected_option_ids, $default_option_id, $single_choice);
    }

    // The empty state is a sibling, not a replacement: an attribute created
    // through the modal has to land somewhere, and the list container has to
    // exist for that even when it starts out empty.
    $empty_class = $rendered ? ' d-none' : '';

    return
        '<div id="pg_pb_attributes">' . $output . '</div>
        <div id="pg_pb_attributes_empty" class="' . trim($empty_class) . '">' .
            pg_pb_render_empty_state(
                'bi-tags',
                lang('No product attributes found.'),
                lang('Attributes such as colour or size are what a variant set is built from.')) . '
        </div>';
}


/**
 * One option chip.
 *
 * btn-check keeps this a real checkbox for the browser and for the JS, while
 * rendering as a pill. No custom CSS involved.
 *
 * Also called by product_attribute_action.php so a freshly created option is
 * built by the same code as the ones drawn on page load — the alternative is a
 * PHP markup twin in JavaScript that silently drifts.
 *
 * @param array $option  id, label
 * @param bool  $checked
 * @return string
 */
function pg_pb_render_option_chip($option, $checked = FALSE)
{
    $checked_attribute = $checked ? ' checked="checked"' : '';

    return
        '<input type="checkbox" class="btn-check pg-pb-option" autocomplete="off"
            id="pg_pb_opt_' . h($option['id']) . '"
            value="' . h($option['id']) . '"
            data-label="' . h($option['label']) . '"' . $checked_attribute . ' />
        <label class="btn btn-sm btn-outline-primary rounded-pill" for="pg_pb_opt_' . h($option['id']) . '">' . h($option['label']) . '</label>';
}


/**
 * One attribute card: the option chips plus the controls that act on them.
 *
 * Shared with product_attribute_action.php, which returns the rendered card for
 * a newly created attribute rather than asking the browser to rebuild this
 * markup from JSON.
 *
 * @param array $attribute          id, name, options[]
 * @param array $selected_option_ids
 * @param int   $default_option_id
 * @return string
 */
function pg_pb_render_attribute_card($attribute, $selected_option_ids = array(), $default_option_id = 0, $single_choice = FALSE)
{
    $output_default_options = '<option value="">-' . lang('None') . '-</option>';
    $output_options         = '';

    foreach ($attribute['options'] as $option) {

        $option_selected = (((int) $default_option_id) === (int) $option['id']) ? ' selected="selected"' : '';

        $output_default_options .=
            '<option value="' . h($option['id']) . '"' . $option_selected . '>' . h($option['label']) . '</option>';

        $output_options .= pg_pb_render_option_chip($option, in_array((int) $option['id'], $selected_option_ids));
    }

    return
        '<div class="pg-pb-attribute card mb-2" data-attr-id="' . h($attribute['id']) . '" data-attr-name="' . h($attribute['name']) . '">
            <div class="card-body py-2 px-3">

                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="fw-semibold">' . h($attribute['name']) . '</span>
                    <span class="badge text-bg-primary pg-pb-attr-count d-none">0</span>
                    <div class="ms-auto d-flex align-items-center gap-1">
                        ' . ($single_choice
                            /* A product carries one option per attribute, so
                               "select all" would be a one-click way to describe
                               something that has to be several products. */
                            ? ''
                            : '<button type="button" class="btn btn-sm btn-link text-decoration-none pg-pb-attr-toggle-all">' . lang('Select All') . '</button>') . '
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-secondary pg-pb-attr-up" title="' . lang('Move Up') . '"><i class="bi bi-arrow-up"></i></button>
                            <button type="button" class="btn btn-outline-secondary pg-pb-attr-down" title="' . lang('Move Down') . '"><i class="bi bi-arrow-down"></i></button>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-1 align-items-center mb-2 pg-pb-chiprow">' . $output_options . '
                    <span class="pg-pb-option-add d-inline-flex align-items-center gap-1">
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill pg-pb-option-add-open" title="' . lang('Add Option') . '"><i class="bi bi-plus-lg"></i></button>
                        <span class="pg-pb-option-add-form d-none input-group input-group-sm" style="width:16rem;">
                            <input type="text" class="form-control pg-pb-option-add-input" maxlength="255" placeholder="' . lang('Label') . '" />
                            <button type="button" class="btn btn-primary pg-pb-option-add-save" title="' . lang('Save') . '"><i class="bi bi-check-lg"></i></button>
                            <button type="button" class="btn btn-outline-secondary pg-pb-option-add-cancel" title="' . lang('Cancel') . '"><i class="bi bi-x-lg"></i></button>
                        </span>
                    </span>
                </div>

                ' . ($single_choice
                    /* The default option belongs to a variant set: it says which
                       one a visitor lands on. A single product has no choice to
                       default to — it is the choice. */
                    ? ''
                    : '<div class="d-none align-items-center gap-2 pg-pb-attr-default-wrap">
                    <span class="text-muted small">' . lang('Default Option') . ':</span>
                    <select class="form-select form-select-sm pg-pb-attr-default" style="max-width:220px;">' . $output_default_options . '</select>
                </div>') . '

            </div>
        </div>';
}


/**
 * The "new attribute" modal.
 *
 * MUST be rendered as a sibling of the product form, never inside it. Inside,
 * pressing Enter in one of these inputs submits the product form, and if this
 * markup ever grows its own <form> the HTML parser drops the inner one
 * outright — which is exactly why the per-row cancel button in view_orders.php
 * did nothing for months.
 *
 * Every button here is type="button"; the save goes through fetch().
 *
 * @return string
 */
function pg_pb_render_attribute_modal()
{
    return
        '<div class="modal fade" id="pg_pb_attribute_modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-tags me-2"></i>' . lang('New Attribute') . '</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' . lang('Close') . '"></button>
                    </div>

                    <div class="modal-body">

                        <div id="pg_pb_attribute_modal_error" class="alert alert-danger d-none"></div>

                        <div class="row">
                            <div class="col-12 col-sm-5 mb-3">
                                <label class="form-label" for="pg_pb_attr_name">*' . lang('Name') . '</label>
                                <input type="text" id="pg_pb_attr_name" class="form-control" maxlength="100" placeholder="' . lang('Attribute Name') . '" />
                            </div>
                            <div class="col-12 col-sm-7 mb-3">
                                <label class="form-label" for="pg_pb_attr_label">' . lang('Label') . '</label>
                                <input type="text" id="pg_pb_attr_label" class="form-control" maxlength="255" placeholder="' . lang('Attribute Label') . '" />
                            </div>
                            <div class="col-12">
                                <label class="form-label">' . lang('Atribute Options') . '</label>
                                <div id="pg_pb_attr_options"></div>
                                <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="pg_pb_attr_add_option"><i class="bi bi-plus-circle me-2"></i>' . lang('Add Option') . '</button>
                            </div>
                        </div>

                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . lang('Cancel') . '</button>
                        <button type="button" class="btn btn-success" id="pg_pb_attr_save"><i class="bi bi-check-circle me-2"></i>' . lang('Create') . '</button>
                    </div>

                </div>
            </div>
        </div>';
}


/**
 * The image picker block, reused by every v2 screen.
 *
 * The picker itself lives in backend.src.js. Existing images are rendered here
 * with the *same* markup the picker appends, so the cover badge, the sort
 * handle and the remove button work identically whether an image came from the
 * database or was just picked. Rendering bare hidden inputs instead — which is
 * what this did first — meant an edit screen showed no images at all until you
 * added one.
 *
 * @param array  $images       existing file names, cover first
 * @param string $extra_action optional extra button for the action row (the
 *                             image code modal trigger on the add screen)
 * @return string
 */
function pg_pb_render_image_picker($images = array(), $extra_action = '')
{
    $output_tiles = '';

    foreach ($images as $image) {

        // Absolute URLs (stock photo services) are stored whole; local files
        // are relative to the site root.
        $source = preg_match('#^https?://#i', $image) ? $image : OUTPUT_PATH . $image;

        $output_tiles .=
            '<div class="item col">
                <div class="card bg-transparent border-0 shadow-none cursor-pointer image">
                    <div class="card-header d-flex justify-content-end p-1 border-0 bg-transparent">
                        <button type="button" class="btn btn-link link-danger bi bi-x-lg p-0" title="' . lang('Remove') . '" onclick="$(this).closest(\'.item\').remove();"></button>
                    </div>
                    <div class="card-body overflow-hidden position-relative rounded ratio ratio-2x1 w-100" style="--bs-aspect-ratio: 80%;background: radial-gradient(transparent, #00000024);">
                        <input type="hidden" name="selected_images[]" value="' . h($image) . '" />
                        <img class="object-fit-contain w-100 h-100" src="' . h($source) . '" alt="" draggable="false" />
                    </div>
                </div>
            </div>';
    }

    // One surface. The tiles, the "nothing here yet" message and the buttons all
    // live inside the same tinted box, because they are one control — an empty
    // state floating under a separate empty grey rectangle reads as two broken
    // things rather than one waiting one.
    //
    // The double-click-to-open handler sits on the drop area only. Putting it on
    // the whole box would make a double-click on "Add Image" open two pickers.
    return
        '<div class="card">
            <div class="card-body">

                <!-- The drop area doubles as the sortable list. Files dropped
                     here upload straight to the site; double-clicking still
                     opens the full picker for files already uploaded. -->
                <div id="pg_pb_image_drop" class="pg-pb-dropzone rounded p-3"
                    ondblclick="software_image_picker({initialize:true});">

                    <div id="software_image_picker_container"
                        class="user-select-none sortable-list img-list row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 row-cols-xl-6 g-3">' . $output_tiles . '</div>

                    <div id="pg_pb_image_empty" class="d-none text-center text-muted py-4">
                        <i class="bi bi-cloud-arrow-up d-block mb-2" style="font-size:2rem;opacity:.5;"></i>
                        <div class="fw-semibold text-body">' . lang('Drop images here') . '</div>
                        <div class="small">' . lang('Or choose from files already uploaded. The first image becomes the cover image.') . '</div>
                    </div>

                    <div id="pg_pb_image_overlay" class="pg-pb-dropzone-overlay d-none">
                        <div class="text-center">
                            <i class="bi bi-cloud-arrow-up d-block mb-2" style="font-size:2rem;"></i>
                            <div class="fw-semibold">' . lang('Drop images here') . '</div>
                        </div>
                    </div>

                </div>

                <div id="pg_pb_image_progress" class="progress mt-3 d-none" style="height:.4rem;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%"></div>
                </div>

                <div class="d-flex align-items-center flex-wrap gap-2 mt-3">
                    <button type="button" class="btn btn-primary" onclick="software_image_picker({initialize:true});"><i class="bi bi-images me-2"></i>' . lang('Choose from Files') . '</button>
                    <button type="button" class="btn btn-outline-primary" id="pg_pb_image_upload"><i class="bi bi-upload me-2"></i>' . lang('Upload') . '</button>
                    <input type="file" id="pg_pb_image_file" class="d-none" accept="image/jpeg,image/png,image/gif,image/webp,image/bmp,image/tiff" multiple />

                    <!--
                        Where an upload lands. This is not a convenience: without
                        it the file was filed under no folder at all and never
                        appeared in the file manager again, because the manager
                        lists a folder at a time.

                        Named pg_pb_upload_folder rather than "folder" because
                        this sits inside the product form and would otherwise be
                        posted along with the product.
                    -->
                    <!--
                        width:auto on a select sizes it to its longest option,
                        and this list is the whole folder tree with the nesting
                        spelled out in spaces — so it grew past the card and took
                        the layout with it. It is a flex item now: min-width:0
                        lets it shrink below that intrinsic width (flex items
                        refuse to by default), max-width stops it hogging the row
                        on a wide screen, and the label is pushed to its own line
                        rather than squeezed when there is no room.
                    -->
                    <div class="d-flex align-items-center gap-2 ms-md-auto flex-grow-1 flex-md-grow-0" style="min-width:0;">
                        <label for="pg_pb_upload_folder" class="form-label mb-0 small text-muted text-nowrap">' . lang('Upload to') . '</label>
                        <select class="form-select form-select-sm" id="pg_pb_upload_folder" name="pg_pb_upload_folder" style="min-width:0; max-width:18rem;">' . select_folder(0, 0) . '</select>
                    </div>
                    ' . $extra_action . '
                </div>

            </div>
        </div>';
}


/**
 * A full-width row holding one or more switches.
 *
 * Two rules, both learned the hard way:
 *
 *   A switch never shares a grid row with input fields, and never sits in a
 *   col-4 / col-6. Next to a labelled input a switch reads as one more field,
 *   and when the row wraps on a narrow screen it lands under an unrelated
 *   label. It is always col-12.
 *
 *   A switch row gets real space above and below (my-3). A switch that reveals
 *   a panel needs the eye to see where its own block starts and ends; packed
 *   tight against neighbours, the revealed content looks like it belongs to
 *   whatever is above it.
 *
 * @param string $content switch markup, from pg_pb_render_switch()
 * @param string $extra_class
 * @return string
 */
function pg_pb_render_switch_row($content, $extra_class = '')
{
    $extra_class = ($extra_class !== '') ? ' ' . $extra_class : '';

    return '<div class="col-12 my-3' . $extra_class . '">' . $content . '</div>';
}


/**
 * The values the product screen is currently drawing.
 *
 * A switch is rendered by pg_pb_render_switch(), which is called from inside a
 * markup string and cannot see the caller's variables. Passing the value down
 * through every call was tried and does not survive: the calls are long, some
 * are nested inside a 'panel' of another switch, and a call that forgets the
 * argument silently renders itself off — a stored setting quietly lost the
 * first time the operator opens the product.
 *
 * So the screen publishes its values once and the switch reads them. An
 * explicit 'checked' in the call still wins, for the few switches that are a
 * view preference rather than a stored column.
 */
function pg_pb_form_values($set = NULL)
{
    static $values = array();

    if (is_array($set)) {
        $values = $set;
    }

    return $values;
}
/**
 * One switch, optionally revealing a panel when it is on.
 *
 * The revealed panel uses the legacy "collapse popover" shell, arrow included.
 * A plain tinted card is a different surface but says nothing about *why* it
 * appeared — with several switches on one screen, an open panel reads as
 * content that was always there. The arrow points back at the switch that
 * opened it, and that is the whole job.
 *
 * The "popover" class is also load-bearing: the collapse-switcher handler in
 * backend.src.js toggles the "show" class only for targets that carry it.
 *
 * Properties:
 *   id       required, also seeds the panel id
 *   name     posted field name; omit for a UI-only switch
 *   label    required
 *   checked  bool
 *   help     small text under the label
 *   panel    HTML for the revealed panel; expected to be grid columns
 *
 * @param array $props
 * @return string
 */
function pg_pb_render_switch($props)
{
    $id      = $props['id'];
    $label   = $props['label'];
    if (array_key_exists('checked', $props)) {
        $checked = !empty($props['checked']) ? ' checked="checked"' : '';
    } else {
        $form_values = pg_pb_form_values();
        $checked     = !empty($form_values[$id]) ? ' checked="checked"' : '';
    }
    $help    = isset($props['help']) ? $props['help'] : '';
    $panel   = isset($props['panel']) ? $props['panel'] : '';

    // A switch with no name is not posted. Used for controls that only reveal
    // UI — sending a field the server ignores invites someone to later "wire it
    // up" to something that is already decided elsewhere.
    $output_name = isset($props['name']) ? ' name="' . h($props['name']) . '"' : '';

    $classes = 'form-check-input';
    $target  = '';

    if ($panel !== '') {
        $panel_id = $id . '_row';
        $classes .= ' collapse-switcher';
        $target   = ' data-bs-target="#' . h($panel_id) . '"';
    }

    // Some switches drive something other than a collapse panel — the metric
    // conversion one points at the fields it converts, and the handler for that
    // lives in backend.src.js. An explicit target wins over the panel's.
    if (isset($props['target']) && $props['target'] !== '') {
        $target = ' data-bs-target="' . h($props['target']) . '"';
    }

    $output_help = ($help !== '')
        ? '<div class="form-text mt-0" style="padding-left:2.5em;">' . $help . '</div>'
        : '';

    $output_panel = '';

    if ($panel !== '') {
        $output_panel =
            // Open when the switch is on. The collapse-switcher handler in
            // backend.src.js only reacts to a change event, so a panel that is
            // already meant to be open renders shut on an edit screen and the
            // operator sees an enabled feature with no settings under it.
            '<div class="collapse popover fade bs-popover-bottom p-0 w-100 mb-2' . (($checked !== '') ? ' show' : '') . '" id="' . h($panel_id) . '">
                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                <div class="popover-body">
                    <div class="row">' . $panel . '</div>
                </div>
            </div>';
    }

    return
        '<div class="form-check form-switch">
            <input class="' . $classes . '" type="checkbox" role="switch" id="' . h($id) . '"' . $output_name . ' value="1"' . $checked . $target . ' />
            <label class="form-check-label fw-semibold" for="' . h($id) . '">' . $label . '</label>
        </div>
        ' . $output_help . '
        ' . $output_panel;
}


/**
 * Styles the v2 screens need that Bootstrap 5.3 has no utility for.
 *
 * Kept to the minimum and kept in one function so both screens stay identical:
 * a dashed border, the sticky nav's active state, and the image cover badge.
 * Everything else on these screens is stock Bootstrap.
 *
 * @return string
 */
function pg_pb_render_styles()
{
    return
        '<style>
            .border-dashed { border-style: dashed !important; }

            /* Nav links scroll to a section; without this the heading lands
               flush against the top of the viewport and reads as cut off. */
            [id^="pg_pb_sec_"] { scroll-margin-top: 3.3rem; }

            /* Active section in the sticky nav. list-group-item-action has no
               "current" state of its own that survives without an href match. */
            /* Wireframe previews. Grey bars, not a copy of the storefront —
               see pg_pb_render_preview(). Sized to the side column: the point is
               "what will a visitor see roughly", not a rendering. */
            .pg-pb-wf-caption {
                font-size: .7rem;
                text-transform: uppercase;
                letter-spacing: .04em;
                color: var(--bs-secondary-color);
                margin-bottom: .25rem;
            }
            .pg-pb-wf-box {
                border: 1px solid var(--bs-border-color);
                border-radius: var(--bs-border-radius);
                padding: .4rem;
                background: var(--bs-tertiary-bg);
            }
            /* The catalog card is capped rather than filling the column. Its
               image is 4:3, so every pixel of width costs three quarters of a
               pixel of height, and in a wider column the sketch grew into the
               largest thing on the screen — which is backwards for a thumbnail
               of a thumbnail. The other two wireframes are mostly text and use
               the full width. */
            .pg-pb-wf-card {
                max-width: 11rem;
            }
            .pg-pb-wf-image {
                aspect-ratio: 4 / 3;
                border-radius: calc(var(--bs-border-radius) - .125rem);
                background: var(--bs-secondary-bg) center / cover no-repeat;
                display: flex;
                align-items: center;
                justify-content: center;
                color: var(--bs-secondary-color);
                font-size: 1.5rem;
                margin-bottom: .4rem;
                overflow: hidden;
            }
            .pg-pb-wf-image-sm {
                width: 3.6rem;
                aspect-ratio: 1;
                margin-bottom: 0;
                font-size: 1rem;
            }
            /* An image was chosen, so the placeholder glyph goes away. */
            .pg-pb-wf-image.has-image i { display: none; }
            .pg-pb-wf-line {
                font-size: .78rem;
                line-height: 1.3;
                margin-bottom: .25rem;
                word-break: break-word;
            }
            .pg-pb-wf-line:last-child { margin-bottom: 0; }
            /* The bar is what a line looks like before anything is typed. */
            .pg-pb-wf-bar {
                height: .5rem;
                border-radius: 1rem;
                background: var(--bs-secondary-bg);
                margin: .25rem 0;
            }
            .pg-pb-wf-line .pg-pb-wf-bar:nth-child(2) { width: 70%; }
            .pg-pb-wf-block .pg-pb-wf-bar:nth-child(2) { width: 85%; }
            .pg-pb-wf-block .pg-pb-wf-bar:nth-child(3) { width: 60%; }
            /* Stands for the attribute dropdowns on the product page. Shaped
               like a select — border, caret, fixed-ish width — rather than
               drawn as a list of every combination, which is not what that page
               shows and would not fit here either. */
            .pg-pb-wf-select {
                display: inline-flex;
                align-items: center;
                justify-content: space-between;
                gap: .25rem;
                min-width: 0;
                max-width: 7rem;
                font-size: .62rem;
                padding: .1rem .35rem;
                border: 1px solid var(--bs-border-color);
                border-radius: calc(var(--bs-border-radius) - .15rem);
                background: var(--bs-body-bg);
                color: var(--bs-secondary-color);
            }
            .pg-pb-wf-select i {
                font-size: .55rem;
                flex-shrink: 0;
            }
            /* Search result. Sketched, not pixel-copied: the colours come from
               the theme so it reads in dark mode, and the point is the shape —
               URL line, one blue title, two lines of description. */
            .pg-pb-serp-url {
                font-size: .68rem;
                color: var(--bs-secondary-color);
                margin-bottom: .15rem;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .pg-pb-serp-title {
                font-size: .82rem;
                line-height: 1.25;
                color: var(--bs-link-color);
                margin-bottom: .2rem;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
                word-break: break-word;
            }
            .pg-pb-serp-desc {
                font-size: .72rem;
                line-height: 1.35;
                color: var(--bs-secondary-color);
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
                word-break: break-word;
            }
            /* Placeholder bars keep their own height inside a clamped box. */
            .pg-pb-serp-title .pg-pb-wf-bar { width: 80%; margin: .2rem 0; }
            .pg-pb-serp-desc .pg-pb-wf-bar:nth-child(2) { width: 65%; }
            /* The block stands for the details tab on the product page. It is
               labelled even while empty, so the operator can see what that part
               of the page is before deciding whether to fill it in. */
            .pg-pb-wf-blocklabel {
                font-size: .65rem;
                text-transform: uppercase;
                letter-spacing: .04em;
                color: var(--bs-secondary-color);
                border-bottom: 1px solid var(--bs-border-color);
                padding-bottom: .15rem;
                margin-bottom: .3rem;
            }
            #pg_pb_wf_details {
                font-size: .7rem;
                line-height: 1.35;
                color: var(--bs-secondary-color);
                display: -webkit-box;
                -webkit-line-clamp: 3;
                -webkit-box-orient: vertical;
                overflow: hidden;
                word-break: break-word;
            }

            /* Drop area. Dashed until something is dragged over it, then the
               overlay takes over so the target is unmistakable — a subtle
               border change on a tall area is easy to miss mid-drag. */
            .pg-pb-dropzone {
                position: relative;
                border: 2px dashed var(--bs-border-color);
                background: var(--bs-tertiary-bg);
                transition: border-color .15s ease, background-color .15s ease;
            }
            .pg-pb-dropzone.pg-pb-dragging {
                border-color: var(--bs-primary);
                background: var(--bs-primary-bg-subtle);
            }
            .pg-pb-dropzone-overlay {
                position: absolute;
                inset: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                background: var(--bs-primary-bg-subtle);
                color: var(--bs-primary-text-emphasis);
                border-radius: inherit;
                pointer-events: none;
                z-index: 2;
            }

            /* Rounded tiles, and a grab cursor so it reads as draggable. */
            #software_image_picker_container .item .card-body { border-radius: var(--bs-border-radius); }
            #software_image_picker_container .item { cursor: grab; }
            #software_image_picker_container .item:active { cursor: grabbing; }

            /* An <img> is draggable by default, and that native drag competes
               with the sort. Pressing on a tile started both: the browser tore
               the picture out as a drag payload, the drop area lit up as if a
               file were incoming, and the sort never received its mouseup — so
               its floating copy of the tile was left behind in the list looking
               like a duplicate. The list is sorted with mouse events, so
               nothing here needs the native drag. */
            #software_image_picker_container img,
            #software_image_picker_container .item {
                -webkit-user-drag: none;
                user-drag: none;
            }

            /* Cover badge on the first picked image. Added by JS so it follows
               the image when the list is reordered. */
            #software_image_picker_container .item { position: relative; }
            #software_image_picker_container .item .pg-pb-cover-badge {
                position: absolute;
                top: .35rem;
                left: .35rem;
                z-index: 2;
                pointer-events: none;
            }

            /* The support chat bubble is a third-party component pinned to the
               bottom-right of the viewport by output_footer(), and it draws over
               anything the page pins there. The save button is pinned there, so
               the bar keeps a lane clear rather than fighting it with z-index —
               winning that fight would just bury the chat button instead.

               The reservation applies at every width: the content column is
               centred, so even on a 1400px screen its right edge is closer to
               the viewport edge than the bubble is wide. */
            #pg_pb_summary { padding-right: 5rem; }

            /* Compact image strip inside a variant row. */
            .pg-pb-strip { display: flex; flex-wrap: wrap; gap: .25rem; align-items: center; }
            .pg-pb-strip .pg-pb-chip {
                position: relative;
                width: 34px; height: 34px;
                border: 1px solid var(--bs-border-color);
                border-radius: var(--bs-border-radius-sm);
                overflow: hidden;
                background: var(--bs-tertiary-bg);
            }
            .pg-pb-strip .pg-pb-chip img { width: 100%; height: 100%; object-fit: cover; }
            .pg-pb-strip .pg-pb-chip .pg-pb-chip-remove {
                position: absolute; inset: 0;
                display: none;
                align-items: center; justify-content: center;
                background: rgba(0,0,0,.55); color: #fff;
                border: 0; padding: 0; font-size: .7rem;
            }
            .pg-pb-strip .pg-pb-chip:hover .pg-pb-chip-remove { display: flex; }

            /* Variant table: the header row is hidden below lg and each cell
               becomes a block, so a row reads as a small card. Bootstrap has
               the display utilities but not the per-cell labels, which are
               plain elements toggled with d-lg-none. */
            @media (max-width: 991.98px) {
                .pg-pb-vtable tbody tr { border-bottom: 2px solid var(--bs-border-color); }
            }
        </style>';
}


/**
 * Mark options as selected in an already-rendered <option> list.
 *
 * The option builders in functions.php take a single selected id, which is all
 * a dropdown needs. A product sits in several groups and ships to several
 * zones, so those are multi-selects and the answer is a list. Marking the
 * markup afterwards keeps this out of the shared builders, which every other
 * screen relies on.
 */
function pg_pb_mark_selected($options_html, $selected)
{
    if (!is_array($selected) or !$selected) {
        return $options_html;
    }

    foreach ($selected as $value) {
        $value = (string) $value;

        // Only untouched options — an option the builder already marked must
        // not gain a second attribute.
        $options_html = preg_replace(
            '/<option value="' . preg_quote($value, '/') . '"(?![^>]*selected)/',
            '<option value="' . h($value) . '" selected="selected"',
            $options_html,
            1);
    }

    return $options_html;
}
/**
 * Zone multi-select options.
 *
 * $default_all belongs on a creation screen only.
 *
 * An empty zone list does not mean "no restriction" — it means the product
 * ships nowhere. get_valid_zones_for_order_items() (functions.php) intersects
 * the zone lists of everything in the cart, and intersecting with an empty set
 * empties the result, so one product with no zones blocks shipping for the
 * whole order. Starting a new product with none ticked hands the operator a
 * product that cannot be delivered, with nothing on screen saying so.
 *
 * On an edit screen the flag must stay off: there, empty means the operator
 * emptied it.
 *
 * @param array $selected_ids
 * @param bool  $default_all  tick everything when nothing is selected
 * @return string
 */
function pg_pb_render_zone_options($selected_ids = array(), $default_all = FALSE)
{
    $output = '';
    $all    = ($default_all && !$selected_ids);

    foreach (pg_pb_zones() as $zone) {
        $selected = ($all || in_array((int) $zone['id'], $selected_ids)) ? ' selected="selected"' : '';
        $output .= '<option value="' . h($zone['id']) . '"' . $selected . '>' . h($zone['name']) . '</option>';
    }

    return $output;
}


/* ------------------------------------------------------------------ *
 * Variant set listing
 * ------------------------------------------------------------------ */

/**
 * The "Variant Sets" half of view_products.php.
 *
 * It was its own screen (view_products2.php) and is now a mode of the product
 * list, because they answer the same question — "what is in my catalog" — and
 * two addresses for that meant the operator had to know which one they were on
 * before they could read the page. The choice is remembered per session, so
 * coming back from an edit lands where they were.
 *
 * Kept as a function rather than an include so there is no second entry point:
 * a file that renders a full page is reachable by typing its name, whatever the
 * menu says.
 */
function pg_pb_variant_sets_screen()
{
    global $user;

    $liveform = new liveform('view_products');



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
        ? ($_SESSION['software']['ecommerce']['view_products2']['status'] ?? '')
        : '';

    $filter_parent = isset($_SESSION['software']['ecommerce']['view_products2']['parent'])
        ? (int) ($_SESSION['software']['ecommerce']['view_products2']['parent'] ?? '')
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
        // The set is edited on the group screen, which already lists the
        // products inside it and links to each one. Sending this button to a
        // product editor instead would put a variant matrix on a screen whose
        // job is to edit one product, and from there an operator fixing a
        // variant could add another variant underneath it — a variant of a
        // variant, which is not a thing.
        $edit_url = 'edit_product_group.php?id=' . $set_id;

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
                    'add_product.php',
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
            // ?mode=products is required, not tidy: without it the session sends
            // the operator straight back here and the breadcrumb becomes a loop.
            array('label' => lang('All Products'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_products.php?mode=products'),
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
                            <a class="btn btn-sm btn-primary m-1" href="add_product.php" data-loading-content="' . lang('Loading') . '"><i class="bi bi-plus-circle me-2"></i>' . lang('Create') . '</a>
                            <a class="btn btn-sm btn-outline-secondary m-1" href="view_products.php?mode=products" data-loading-content="' . lang('Loading') . '"><i class="bi bi-list-ul me-2"></i>' . lang('All Products') . '</a>
                        </nav>
                    </div>

                    <!-- Filters live top right, where every other list screen puts
                         them. A GET form so the URL is shareable and the back button
                         behaves; the choice is also kept in the session. -->
                    <div class="col-12 col-sm-12 col-md-6 col-xl-3">
                        <div class="row justify-content-center justify-content-md-end">
                            <form id="search_form" action="view_products.php?mode=variant_sets" method="get" class="search_form col-auto">
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
}


/* ------------------------------------------------------------------ *
 * The product screen
 * ------------------------------------------------------------------ */

/**
 * Every field of a product, drawn once.
 *
 * Creating a product and editing one are the same screen with different values
 * in the boxes, so they are the same code. Written twice they would drift, and
 * the way that failure shows up is the worst kind: a field added to one screen
 * and not the other, so a value the operator set while creating quietly
 * disappears the first time they edit.
 *
 * @param array $values current field values; empty for a new product
 * @param array $context mode ('create' or 'edit'), product_id, and the
 *                       edit-only extras the create screen has no use for
 */
function pg_pb_render_product_screen($values = array(), $context = array())
{
    global $user, $liveform;

    // NOTE: $output_gift_card_variables is never assigned anywhere, so the gift card variable
    // chips below render empty.  Starting it empty keeps that and stops the warning.
    $output_gift_card_variables = '';

    $pg_mode       = isset($context['mode']) ? $context['mode'] : 'create';
    $pg_product_id = isset($context['product_id']) ? (int) $context['product_id'] : 0;

    // What a new product starts as. Written as values rather than as markup
    // defaults so that the create screen and the edit screen read from the same
    // place: a switch is on because a value says so, never because of which
    // file drew it.
    if ($pg_mode !== 'edit') {
        $values = array_merge(array(
            'enabled'   => TRUE,
            'taxable'   => (defined('ECOMMERCE_TAX') && ECOMMERCE_TAX == TRUE),
            'shippable' => (defined('ECOMMERCE_SHIPPING') && ECOMMERCE_SHIPPING == TRUE),
        ), $values);
    }

    // Published for pg_pb_render_switch(), which is called from inside markup
    // strings and cannot see this scope.
    pg_pb_form_values($values);

    // Reads a stored value, falling back to what a new product starts with.
    // Every field goes through this so that neither screen can be given a value
    // the other one ignores.
    $v = function ($key, $default = '') use ($values) {
        return isset($values[$key]) ? $values[$key] : $default;
    };

    // Saved-score panel for the SEO card. Edit mode only: a record that is
    // still being created has nothing computed to show.
    $output_seo_badge = '';
    $output_seo_checklist = '';

    if ($pg_mode === 'edit') {
        // The caller passes the id explicitly; the loaded row carries it too,
        // and either is enough to look the structure findings up.
        $seo_record_id = (int) (isset($context['product_id']) ? $context['product_id'] : $v('id', 0));

        // seo_checked_at as well: pg_seo_row_scored() asks it first, and this
        // screen marks the record stale on every save, so without it the panel
        // would claim the score had never been calculated each time.
        $seo_row = array(
            'seo_score' => $v('seo_score', 0),
            'seo_flags' => $v('seo_flags', 0),
            'seo_checked_at' => $v('seo_checked_at', 0),
            'seo_analysis' => $v('seo_analysis'),
            'seo_analysis_current' => $v('seo_analysis_current', 0),
        );

        $output_seo_badge = '<span class="ms-auto">' . pg_seo_render_badge($seo_row) . '</span>';
        $output_seo_checklist = '
                                    <div class="col-12">
                                        ' . pg_seo_render_checklist($seo_row, 'product', $seo_record_id) . '
                                    </div>';
    }

    // The submit-form block, moved from edit_product.php (:1368-1453). It keeps
    // the ids the JavaScript in backend.src.js hooks — the field pickers, the
    // "where" selector and the add-field buttons are all driven from there, and
    // renaming anything here would quietly disconnect them.
    $output_submit_form_create_javascript = $v('pg_pb_submit_form_create_js');
    $output_submit_form_update_javascript = $v('pg_pb_submit_form_update_js');

    $output_submit_form_block = '' . '
                                            <div class="collapse popover  fade bs-popover-bottom p-0 mb-2" id="submit_form_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                <div class="popover-body">
                                                    <div class="row">
                                                        <div class="col-12 my-1">
                                                            <label class="form-label" for="submit_form_custom_form_page_id">' . lang('Custom Form') . '</label>
                                                            <select class="form-select collapse-if-selected" id="submit_form_custom_form_page_id" name="submit_form_custom_form_page_id" onchange="product_submit_form_update_custom_form_fields()" data-bs-target="#submit_form_custom_form_page_row"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Form')) )) . '-</option>' .  select_page($v('submit_form_custom_form_page_id'), 'custom form') . '</select>
                                                            <script>product_submit_form_update_custom_form_fields();</script>
                                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="submit_form_custom_form_page_row">
                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                                <div class="popover-body">
                                                                    <div class="row">
                                                                        <div class="col-12 my-1">
                                                                            <div class="form-check form-switch">
                                                                                <input value="1" ' . ($v('submit_form_create') ? ' checked="checked"' : '') . ' id="submit_form_create" name="submit_form_create" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#submit_form_create_row" />
                                                                                <label class="form-check-label" for="submit_form_create">' . lang('Create Submitted Form') . '</label>
                                                                            </div>
                                                                            <div class="collapse popover  fade bs-popover-bottom p-0 mb-2" id="submit_form_create_row">
                                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                                                <div class="popover-body">
                                                                                    <div class="row">
                                                                                        <div class="col-12 my-1">
                                                                                            <div class="alert alert-primary">' . lang(array('string'=>'Please configure the fields below that should be set when a Submitted Form is {var:1}.','vars'=>array( lang('created') ) )) . '</div>
                                                                                            <div id="submit_form_create_field"></div>
                                                                                            <button type="button" onclick="product_submit_form_add_field({action: \'create\'})" class="btn btn-sm btn-primary my-3"><span class="bi bi-plus-circle me-2"></span>' . lang('Add Field') . '</button>
                                                                                            <input type="hidden" id="last_submit_form_create_field_number" name="last_submit_form_create_field_number" value="0" />
                                                                                            <script>
                                                                                                var last_submit_form_field_number = [];                            
                                                                                                last_submit_form_field_number["create"] = 0;
                                                                                                ' . $output_submit_form_create_javascript . '
                                                                                            </script>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 my-1">
                                                                            <div class="form-check form-switch">
                                                                                <input value="1" ' . ($v('submit_form_update') ? ' checked="checked"' : '') . ' id="submit_form_update" name="submit_form_update" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#submit_form_update_row" />
                                                                                <label class="form-check-label" for="submit_form_update">' . lang('Update Submitted Form') . '</label>
                                                                            </div>
                                                                            <div class="collapse popover  fade bs-popover-bottom p-0 mb-2" id="submit_form_update_row">
                                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                                                <div class="popover-body">
                                                                                    <div class="row">
                                                                                        <div class="col-12 my-1">
                                                                                            <div class="alert alert-primary">' . lang(array('string'=>'Please configure the fields below that should be set when a Submitted Form is {var:1}.','vars'=>array( lang('updated') ) )) . '</div>
                                                                                            <div id="submit_form_update_field"></div>
                                                                                            <button type="button" onclick="product_submit_form_add_field({action: \'update\'})" class="btn btn-sm btn-primary my-3"><span class="bi bi-plus-circle me-2"></span>' . lang('Add Field') . '</button>
                                                                                            <input type="hidden" id="last_submit_form_update_field_number" name="last_submit_form_update_field_number" value="0" />
                                                                                            <script>
                                                                                                last_submit_form_field_number["update"] = 0;
                                                                                                ' . $output_submit_form_update_javascript . '
                                                                                            </script>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 my-1">
                                                                            <div class="alert alert-primary">' . lang('Please specify which Submitted Form should be updated.') . '</div>
                                                                            <div class="input-group w-100">
                                                                                <span class="input-group-text">
                                                                                    <label class="form-label" for="submit_form_update_where_field">' . lang('Where:') . '</label>
                                                                                    <select class="form-select" id="submit_form_update_where_field" name="submit_form_update_where_field"></select>
                                                                                </span>
                                                                                <span class="input-group-text">
                                                                                    <label class="form-label" for="submit_form_update_where_value">' . lang('is equal to:') . '</label>
                                                                                    <input class="form-control" type="text" id="submit_form_update_where_value" name="submit_form_update_where_value" value="' . h($v('submit_form_update_where_value')) . '" maxlength="255">
                                                                                </span>
                                                                                <script>init_product_submit_form_update_where("' . escape_javascript($v('submit_form_update_where_field')) . '")</script>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 my-1">
                                            <div class="form-check form-switch">
                                                <input value="1" ' . '' . ' id="add_comment" name="add_comment" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#add_comment_row" />
                                                <label class="form-check-label" for="add_comment">' . lang('Add Comment') . '</label>
                                            </div>
';

    // The editable barcode card from edit_product.php, moved rather than
    // rewritten: it already carries the JsBarcode preview, the list of codes on
    // this product, the label template editor and printing.
    //
    // Edit only. Before the product exists there is nothing to preview, list or
    // print, so the create screen keeps the plain field instead.
    $output_barcode_card = '';

    if (($pg_mode === 'edit') && defined('BARCODE_ENABLED') && BARCODE_ENABLED) {
        $bc_row = db_item(
            "SELECT barcode, barcode_type FROM product_barcodes WHERE product_id = '" . e($pg_product_id) . "' LIMIT 1");
        $bc_value = $bc_row['barcode']      ?? '';
        $bc_type  = $bc_row['barcode_type'] ?? BARCODE_DEFAULT_TYPE;

        // Attributes for label preview
        $attr_labels = array();
        $attr_rows = db_items(
            "SELECT product_attribute_options.label
             FROM products_attributes_xref
             JOIN product_attribute_options
               ON products_attributes_xref.option_id = product_attribute_options.id
             WHERE products_attributes_xref.product_id = '" . e($pg_product_id) . "'");
        foreach ($attr_rows as $ar) { $attr_labels[] = $ar['label']; }
        $bc_attributes = implode(', ', $attr_labels);

        $output_barcode_card =
            '<div class="mb-3" id="pg-barcode-card">' .
                '<div class="card">' .
                    // Header: Edit Template (left) + Type select (right)
                    '<div class="card-header bg-transparent border-bottom py-1 px-2 d-flex align-items-center justify-content-between gap-1">' .
                        '<button type="button" class="btn btn-link btn-sm p-0 text-secondary text-nowrap" title="' . lang('Edit Template') . '" onclick="editBarcodeTemplate(window._pgBarcodeOpts)">' .
                            '<i class="bi bi-pencil-square me-1"></i><small>' . lang('Edit Template') . '</small></button>' .
                        '<select id="pg-barcode-type" class="form-select form-select-sm" style="max-width:105px;">' .
                            '<option value="CODE128"' . ($bc_type === 'CODE128' ? ' selected' : '') . '>Code 128</option>' .
                            '<option value="EAN13"'   . ($bc_type === 'EAN13'   ? ' selected' : '') . '>EAN-13</option>' .
                            '<option value="CODE39"'  . ($bc_type === 'CODE39'  ? ' selected' : '') . '>Code 39</option>' .
                            '<option value="UPC"'     . ($bc_type === 'UPC'     ? ' selected' : '') . '>UPC-A</option>' .
                        '</select>' .
                    '</div>' .
                    '<div class="card-body p-2 pt-1">' .
                        // Barcode SVG preview
                        '<div class="text-center mb-1" style="min-height:42px;">' .
                            '<svg id="pg-barcode-svg" style="max-width:100%;height:42px;"></svg>' .
                        '</div>' .
                        // Input group: pencil icon (left) + barcode input + save icon (right)
                        '<div class="input-group input-group-sm">' .
                            '<span class="input-group-text"><i class="bi bi-pencil"></i></span>' .
                            '<input type="text" id="pg-barcode-input" class="form-control font-monospace" ' .
                                'value="' . h($bc_value) . '" placeholder="' . lang('Barcode value') . '" maxlength="100" />' .
                            '<button type="button" id="pg-btn-save-barcode" class="btn btn-outline-primary" title="' . lang('Save') . '">' .
                                '<i class="bi bi-floppy"></i></button>' .
                        '</div>' .
                        '<div id="pg-barcode-status" class="form-text" style="min-height:1.2em;"></div>' .
                        // Bottom row: Generate | List | Print
                        '<div class="d-flex gap-1 mt-1">' .
                            '<button type="button" id="pg-btn-generate" class="btn btn-outline-secondary btn-sm flex-fill" title="' . lang('Generate') . '">' .
                                '<i class="bi bi-magic me-1"></i>' . lang('Generate') . '</button>' .
                            '<button type="button" id="pg-btn-barcode-list" class="btn btn-outline-secondary btn-sm position-relative" title="' . lang('Barcodes') . '">' .
                                '<i class="bi bi-list-ul"></i>' .
                                '<span id="pg-barcode-count" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary" style="font-size:9px;display:none;"></span>' .
                            '</button>' .
                            '<button type="button" id="pg-btn-print-barcode" class="btn btn-outline-success btn-sm" title="' . lang('Print') . '">' .
                                '<i class="bi bi-printer"></i></button>' .
                        '</div>' .
                    '</div>' .
                '</div>' .
                // Inline JS init data for this product
                '<script>
                window._pgBarcodeOpts = {
                    productId:        ' . (int) $pg_product_id . ',
                    barcodeValue:     ' . json_encode($bc_value) . ',
                    barcodeType:      ' . json_encode($bc_type)  . ',
                    shortDescription: ' . json_encode($values['short_description']) . ',
                    sku:              ' . json_encode($values['name']) . ',
                    price:            ' . json_encode($values['price']) . ',
                    attributes:       ' . json_encode($bc_attributes) . ',
                    labelTemplate:    ' . json_encode(BARCODE_LABEL_TEMPLATE) . ',
                    productImageSrc:  ' . json_encode($values['image_name'] ? OUTPUT_PATH . $values['image_name'] : '') . ',
                    apiToken:         ' . json_encode($_SESSION['software']['token'] ?? '') . '
                };
                </script>' .
            '</div>';
    }

    /* ------------------------------------------------------------------ *
     * Form
     * ------------------------------------------------------------------ */

    // Switch defaults follow the store configuration: a shop with tax on should not
    // need the operator to remember to tick "taxable" on every product.
    $tax_checked       = (defined('ECOMMERCE_TAX') && ECOMMERCE_TAX == TRUE) ? ' checked="checked"' : '';
    $shippable_checked = (defined('ECOMMERCE_SHIPPING') && ECOMMERCE_SHIPPING == TRUE) ? ' checked="checked"' : '';

    $image_code_template = db_value("SELECT product_image_code_template FROM config");

    // Barcodes. Nothing is rendered when the feature is off in settings, which is
    // also what the save path checks — an operator who cannot see the switch must
    // not be able to post one.
    //
    // Two controls, because the two cases are different questions. The text box is
    // the code the operator already has, printed on the box in their hand. The
    // switch is "make one up for me", which is the only sensible answer once there
    // are nine variants and nobody is going to type nine codes.
    // What ticking "Email Gift Card" actually does. The box was rendered with an
    // $output_gift_card_effects that was never assigned anywhere, so an empty <ul>
    // sat inside a grey alert and the operator was shown a blank box that appeared
    // to mean something.
    //
    // None of this is guessable from a switch: the behaviour is spread across
    // shopping_cart.php (:402), submit_order.php (:6146) and the settings.
    $output_gift_card_effects =
        '<li>' . lang('The customer is asked who the card is for and when to send it.') . '</li>'
        . '<li>' . lang('One card code is generated per item ordered, up to 100 per order line.') . '</li>'
        . '<li>' . lang('The card is worth the price paid for it.') . '</li>'
        . (defined('ECOMMERCE_GIFT_CARD_VALIDITY_DAYS') && ECOMMERCE_GIFT_CARD_VALIDITY_DAYS
            ? '<li>' . lang(array(
                'string' => 'Cards expire {var:1} days after they are sent.',
                'vars'   => array((int) ECOMMERCE_GIFT_CARD_VALIDITY_DAYS))) . '</li>'
            : '<li>' . lang('Cards do not expire. Settings can set a validity period.') . '</li>')
        . '<li>' . lang('An order made up only of gift cards cannot be paid for with a gift card.') . '</li>';

    $output_barcode_fields = '';
    $barcode_format = pg_barcode_format();

    // Create screen only. While editing, the barcode is handled by the card at
    // the top of the screen — it previews the code, lists the ones already on
    // the product and prints labels, none of which mean anything before the
    // product exists.
    //
    // The automatic switch goes with it: on an existing product the operator
    // presses Generate and sees what they got, rather than being promised a
    // code that appears somewhere after saving.
    if (($pg_mode !== 'edit') && defined('BARCODE_ENABLED') && BARCODE_ENABLED) {

        // The box is shaped by the type the store is set to: thirteen digits and a
        // numeric keypad for EAN13, twelve for UPC, anything for CODE128. Typing a
        // code that cannot be encoded produces a label that will not scan, and that
        // is discovered at the till rather than here.
        $barcode_attributes =
            ' maxlength="' . ($barcode_format['length'] ? $barcode_format['length'] : 100) . '"'
            . ($barcode_format['digits_only'] ? ' inputmode="numeric"' : '')
            . ($barcode_format['pattern'] !== '' ? ' pattern="' . h($barcode_format['pattern']) . '"' : '');

        // In the label, not under the box — the same reason as the variant panel:
        // one field taller than its neighbours drags the row out of line.
        $barcode_label = lang('Barcode')
            . (($barcode_format['hint'] !== '')
                ? ' &middot; ' . h($barcode_format['type']) . ' ' . h($barcode_format['hint'])
                : '');

        $output_barcode_fields =
            '<div class="col-12 col-lg-3 my-2" id="pg_pb_barcode_field">
                <label for="pg_pb_barcode" class="form-label">' . $barcode_label . '</label>
                <input type="text" name="pg_pb_barcode" id="pg_pb_barcode" class="form-control font-monospace"' . $barcode_attributes . ' placeholder="' . lang('Leave blank to generate') . '" value="' . h($v('pg_pb_barcode')) . '" />
            </div>

            <div class="col-12 my-2">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" name="pg_pb_barcode_auto" id="pg_pb_barcode_auto" value="1" checked="checked" />
                    <label class="form-check-label" for="pg_pb_barcode_auto">' . lang('Assign a barcode automatically') . '</label>
                    <div class="form-text" id="pg_pb_barcode_auto_help">' . lang('A code is generated for any product left blank.') . '</div>
                </div>
            </div>';
    }

    $has_groups = pg_pb_has_product_groups();

    // Parent group (group mode) and catalog groups (single mode) are two different
    // questions, so they are two different controls — see the note in
    // pg_pb_save_new_product() about why a variant does not join browse categories.
    $output_parent_group_field = '';
    $output_catalog_group_field = '';

    if ($has_groups) {

        $output_parent_group_field =
            '<div class="col-12 col-lg-8 my-2 pg-pb-group-only d-none">
                <label for="parent_group_id" class="form-label">' . lang('Parent Product Group') . '</label>
                <select style="width:100%" class="select2 form-select" id="parent_group_id" name="parent_group_id" data-placeholder="' . lang('Select Parent Group') . '">
                    <option value="0">-' . lang('None') . '-</option>
                    ' . get_product_group_options(0, 0, 0, 0, array(), FALSE) . '
                </select>
                <div class="form-text">' . lang('The new variant group is placed under this group in the catalog.') . '</div>
            </div>';

        $output_catalog_group_field =
            '<div class="col-12 col-lg-8 my-2 pg-pb-single-only">
                <label for="catalog_group_ids" class="form-label">' . lang('Include product in to selected groups') . '</label>
                <select style="width:100%" class="select2 form-select" id="catalog_group_ids" name="catalog_group_ids[]" multiple="multiple" data-placeholder="' . lang('Include product in to selected groups') . '">
                    ' . pg_pb_mark_selected(get_product_group_options(0, 0, 0, 0, array(), TRUE), $v('catalog_group_ids', array())) . '
                </select>
                <div class="form-text">' . lang('A single product can sit in several groups.') . '</div>
            </div>';
    } else {
        $output_parent_group_field = '<div class="col-12">' .
            pg_pb_render_empty_state(
                'bi-folder',
                lang('No product groups'),
                lang('The catalog has no groups yet, so this product is not placed under one.')) . '</div>';
    }

    // Everything ticked by default — see pg_pb_render_zone_options(): an empty list
    // means the product ships nowhere, not everywhere.
    // On create every zone is ticked (an empty list means the product ships
    // nowhere); on edit the stored list wins, empty included.
    $output_zone_options = ($pg_mode === 'edit')
        ? pg_pb_render_zone_options($v('allowed_zones', array()), FALSE)
        : pg_pb_render_zone_options(array(), TRUE);

    $output_zones_field = '';

    if ($output_zone_options !== '') {
        $output_zones_field =
            '<div class="col-12 my-2">
                <label for="allowed_zones" class="form-label">' . lang('Allowed Zones') . '</label>
                <select style="width:100%" class="select2 form-select" data-placeholder="' . lang('Click to select shipping zone(s)') . '" id="allowed_zones" name="allowed_zones[]" multiple="multiple">' . $output_zone_options . '</select>
            </div>';
    }

    /* ------------------------------------------------------------------ *
     * Blocks behind a configuration gate
     *
     * Each of these is absent, not disabled, when its gate is closed. A greyed-out
     * control for a payment gateway the store does not use is a question the
     * operator cannot answer.
     * ------------------------------------------------------------------ */

    $output_commissionable = '';

    if (defined('AFFILIATE_PROGRAM') && AFFILIATE_PROGRAM == TRUE) {
        // Rendered as a bare switch, not a row of its own: it joins the other
        // product-wide switches in the main card.
        $output_commissionable = pg_pb_render_switch(array(
                'id'    => 'commissionable',
                'name'  => 'commissionable',
                'label' => lang('Commissionable'),
                'panel' =>
                    '<div class="col-12 col-sm-6 col-lg-4">
                        <label for="commission_rate_limit" class="form-label">' . lang('Commission Rate Limit') . '</label>
                        <div class="input-group">
                            <input type="text" name="commission_rate_limit" id="commission_rate_limit" class="form-control" size="3" maxlength="3" inputmode="numeric" style="text-align:right;" value="' . h($v('commission_rate_limit')) . '" />
                            <label for="commission_rate_limit" class="input-group-text">%</label>
                        </div>
                        <div class="form-text">(' . lang('leave blank for no limit') . ')</div>
                    </div>',
            ));
    }

    // Shipping is a store-wide feature: with it off, the switch and every dimension
    // behind it are noise.
    $output_shippable = '';

    if (defined('ECOMMERCE_SHIPPING') && ECOMMERCE_SHIPPING == TRUE) {
        $output_shippable = pg_pb_render_switch(array(
                                                'id'      => 'shippable',
                                                'name'    => 'shippable',
                                                'label'   => lang('Shippable'),
                                                
                                                'panel'   =>

                                                    pg_pb_render_switch_row(
                                                        pg_pb_render_switch(array(
                                                            'id'    => 'convert_to_metric_system',
                                                            'name'  => 'convert_to_metric_system',
                                                            'label' => lang('Convert to metric system'),
                                                            'help'  => lang('Weight and dimensions are stored in pounds and inches; tick this to type them in kilograms and centimetres.'),
                                                            // The handler in backend.src.js converts the typed
                                                            // values and swaps the unit labels. It finds both
                                                            // through this target list and through a label.unit
                                                            // sitting in the same input-group, so the fields
                                                            // below have to be input-groups with those labels —
                                                            // without them the switch changes nothing on screen.
                                                            'target' => '#weight,#length,#width,#height',
                                                        )), 'mt-0') .

                                                    '<div class="col-6 col-lg-3 my-1">
                                                        <label for="weight" class="form-label">' . lang('Weight') . '</label>
                                                        <div class="input-group">
                                                            <input value="' . h($v('weight', '0')) . '" type="text" name="weight" id="weight" class="form-control" inputmode="decimal" style="text-align:right;" />
                                                            <label class="input-group-text unit" for="weight">lbs</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-6 col-lg-3 my-1">
                                                        <label for="length" class="form-label">' . lang('Length') . '</label>
                                                        <div class="input-group">
                                                            <input value="' . h($v('length', '0')) . '" type="text" name="length" id="length" class="form-control" inputmode="decimal" style="text-align:right;" />
                                                            <label class="input-group-text unit" for="length">inc</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-6 col-lg-3 my-1">
                                                        <label for="width" class="form-label">' . lang('Width') . '</label>
                                                        <div class="input-group">
                                                            <input value="' . h($v('width', '0')) . '" type="text" name="width" id="width" class="form-control" inputmode="decimal" style="text-align:right;" />
                                                            <label class="input-group-text unit" for="width">inc</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-6 col-lg-3 my-1">
                                                        <label for="height" class="form-label">' . lang('Height') . '</label>
                                                        <div class="input-group">
                                                            <input value="' . h($v('height', '0')) . '" type="text" name="height" id="height" class="form-control" inputmode="decimal" style="text-align:right;" />
                                                            <label class="input-group-text unit" for="height">inc</label>
                                                        </div>
                                                    </div>

                                                    <div class="col-6 col-lg-3 my-1">
                                                        <label for="primary_weight_points" class="form-label">' . lang('Primary Weight Points') . '</label>
                                                        <input value="' . h($v('primary_weight_points', '0')) . '" type="number" step="0.01" min="0" name="primary_weight_points" id="primary_weight_points" class="form-control" />
                                                    </div>
                                                    <div class="col-6 col-lg-3 my-1">
                                                        <label for="secondary_weight_points" class="form-label">' . lang('Secondary Weight Points') . '</label>
                                                        <input value="' . h($v('secondary_weight_points', '0')) . '" type="number" step="0.01" min="0" name="secondary_weight_points" id="secondary_weight_points" class="form-control" />
                                                    </div>
                                                    <div class="col-6 col-lg-3 my-1">
                                                        <label for="preparation_time" class="form-label">' . lang('Preparation Time') . '</label>
                                                        <input type="number" min="0" name="preparation_time" id="preparation_time" class="form-control" value="' . h($v('preparation_time', '')) . '" />
                                                    </div>
                                                    <div class="col-6 col-lg-3 my-1">
                                                        <label for="extra_shipping_cost" class="form-label">' . lang('Extra Shipping Cost') . '</label>
                                                        <div class="input-group">
                                                            <input value="' . h($v('extra_shipping_cost', '0')) . '" type="text" name="extra_shipping_cost" id="extra_shipping_cost" class="form-control" maxlength="12" inputmode="decimal" inputmask="" data-inputmask-alias="currency" data-inputmask-groupSeparator="," data-inputmask-digits="2" data-inputmask-digitsOptional="false" data-inputmask-placeholder="0" style="text-align:right;" />
                                                            <label class="input-group-text" for="extra_shipping_cost">' . BASE_CURRENCY_SYMBOL . '</label>
                                                        </div>
                                                    </div>

                                                    ' . $output_zones_field .

                                                    pg_pb_render_switch_row(
                                                        pg_pb_render_switch(array(
                                                            'id'    => 'free_shipping',
                                                            'name'  => 'free_shipping',
                                                            'label' => lang('Free Shipping'),
                                                        )) .
                                                        pg_pb_render_switch(array(
                                                            'id'    => 'container_required',
                                                            'name'  => 'container_required',
                                                            'label' => lang('Container Required'),
                                                        ))),
                                            ));
    }

    $output_sage_group_id = '';

    if (defined('ECOMMERCE_CREDIT_DEBIT_CARD') && ECOMMERCE_CREDIT_DEBIT_CARD == TRUE
        && defined('ECOMMERCE_PAYMENT_GATEWAY') && ECOMMERCE_PAYMENT_GATEWAY == 'Sage') {
        $output_sage_group_id =
            '<div class="col-12 col-sm-6 col-lg-4 my-2">
                <label class="form-label" for="sage_group_id">' . lang('Sage Group ID') . '</label>
                <input type="text" name="sage_group_id" id="sage_group_id" class="form-control" maxlength="50" value="' . h($v('sage_group_id')) . '" />
            </div>';
    }

    // Recurring start day: ClearCommerce starts a profile immediately and has no
    // concept of a delay, so the field would be a lie there.
    $output_recurring_start = '';

    if (defined('ECOMMERCE_PAYMENT_GATEWAY') && ECOMMERCE_PAYMENT_GATEWAY != 'ClearCommerce') {
        $output_recurring_start =
            '<div class="col-12 col-sm-6 col-lg-4 my-1">
                <label for="start" class="form-label">' . lang('Start (days)') . '</label>
                <div class="input-group">
                    <input type="text" name="start" id="start" class="form-control" value="' . h($v('start', '0')) . '" size="7" maxlength="7" inputmode="numeric" style="text-align:right;" />
                    <span class="input-group-text" title="' . lang('day(s) from order date') . '">' . lang('day(s)') . '</span>
                </div>
                <div class="form-text text-end">' . lang('0 to start immediately') . '</div>
            </div>';
    }

    $output_recurring_profile_disabled = '';

    if (defined('ECOMMERCE_CREDIT_DEBIT_CARD') && ECOMMERCE_CREDIT_DEBIT_CARD == TRUE
        && defined('ECOMMERCE_PAYMENT_GATEWAY') && ECOMMERCE_PAYMENT_GATEWAY == 'PayPal Payments Pro') {
        $output_recurring_profile_disabled = pg_pb_render_switch_row(
            pg_pb_render_switch(array(
                'id'    => 'recurring_profile_disabled_perform_actions',
                'name'  => 'recurring_profile_disabled_perform_actions',
                'label' => lang('Perform action(s) if profile is disabled'),
                'panel' =>
                    '<div class="col-12">
                        <div class="alert alert-warning">' . lang('requires recurring payment job') . '</div>
                    </div>' .
                    pg_pb_render_switch_row(
                        pg_pb_render_switch(array(
                            'id'    => 'recurring_profile_disabled_expire_membership',
                            'name'  => 'recurring_profile_disabled_expire_membership',
                            'label' => lang('Expire Membership'),
                        )) .
                        pg_pb_render_switch(array(
                            'id'    => 'recurring_profile_disabled_revoke_private_access',
                            'name'  => 'recurring_profile_disabled_revoke_private_access',
                            'label' => lang('Revoke Private Access'),
                        )), 'mt-0') .
                    pg_pb_render_switch_row(
                        pg_pb_render_switch(array(
                            'id'    => 'recurring_profile_disabled_email',
                            'name'  => 'recurring_profile_disabled_email',
                            'label' => lang('Send E-mail to Customer'),
                            'panel' =>
                                '<div class="col-12 col-lg-6">
                                    <label class="form-label" for="recurring_profile_disabled_email_subject">' . lang('Subject') . '</label>
                                    <input type="text" id="recurring_profile_disabled_email_subject" name="recurring_profile_disabled_email_subject" class="form-control" maxlength="100" value="' . h($v('recurring_profile_disabled_email_subject')) . '" />
                                </div>
                                <div class="col-12 col-lg-6">
                                    <label class="form-label" for="recurring_profile_disabled_email_page_id">' . lang('Page') . '</label>
                                    <select name="recurring_profile_disabled_email_page_id" id="recurring_profile_disabled_email_page_id" class="form-select">
                                        <option value="">-' . lang(array('string' => 'Select {var:1}', 'vars' => array(lang('Page')))) . '-</option>' . select_page($v('recurring_profile_disabled_email_page_id')) . '
                                    </select>
                                </div>',
                        ))),
            )));
    }

    // What actually happens when a membership product is bought is spread across
    // submit_order.php and membership_job.php. The operator types a number of days
    // into a box and has no way of knowing the rest, so the screen states it.
    $output_membership_effects = '';

    $membership_contact_group_name = '';

    if (defined('MEMBERSHIP_CONTACT_GROUP_ID') && (int) MEMBERSHIP_CONTACT_GROUP_ID !== 0) {
        $membership_contact_group_name = db_value(
            "SELECT name FROM contact_groups WHERE id = '" . (int) MEMBERSHIP_CONTACT_GROUP_ID . "' LIMIT 1");
    }

    $membership_effects = array(
        // submit_order.php extends from the later of today and the current expiry,
        // so buying early does not cost the customer the days they already paid for.
        lang('An existing membership is extended rather than restarted.'),
        // The days are multiplied by the ordered quantity.
        lang('The number of days is multiplied by the ordered quantity.'),
        // contacts.member_id is set from the order reference code the first time,
        // and every contact sharing that member id is extended together.
        lang('Customers who share a membership number are extended together.'),
    );

    if ($membership_contact_group_name != '') {
        $membership_effects[] = lang(array(
            'string' => 'The customer is added to the "{var:1}" contact group.',
            'vars'   => array($membership_contact_group_name)));
    }

    if (defined('MEMBERSHIP_EXPIRATION_WARNING_EMAIL') && MEMBERSHIP_EXPIRATION_WARNING_EMAIL == TRUE) {
        $membership_effects[] = lang('A warning e-mail is sent before the membership expires.');
    } else {
        $membership_effects[] = lang('No expiry warning e-mail is configured in settings.');
    }

    foreach ($membership_effects as $membership_effect) {
        $output_membership_effects .= '<li>' . $membership_effect . '</li>';
    }

    // The category list is a file shipped with the software. Saying how many
    // entries were loaded is the difference between "the picker is broken" and
    // "the file for this language is missing".
    $taxonomy_status = pg_pb_google_taxonomy_status();

    if ($taxonomy_status['installed']) {
        $output_taxonomy_status = h(lang(array(
            'string' => '{var:1} categories loaded ({var:2}). Pick one, or type a category yourself.',
            'vars'   => array($taxonomy_status['count'], $taxonomy_status['locale']))));
    } else {
        $output_taxonomy_status = h(lang(array(
            'string' => 'assets/google_taxonomy/google_taxonomy_{var:1}.json is missing, so there is nothing to pick from. Type the category or its number yourself.',
            'vars'   => array($taxonomy_status['locale']))));
    }

    // Custom product fields are named by the operator in settings; an unnamed one
    // is an unused one and does not appear.
    $output_custom_product_fields = '';

    for ($custom_field_number = 1; $custom_field_number <= 4; $custom_field_number++) {

        $custom_field_constant = 'ECOMMERCE_CUSTOM_PRODUCT_FIELD_' . $custom_field_number . '_LABEL';

        if (!defined($custom_field_constant) || constant($custom_field_constant) == '') {
            continue;
        }

        $output_custom_product_fields .=
            '<div class="col-12 col-lg-6 my-2">
                <label for="custom_field_' . $custom_field_number . '" class="form-label">' . h(constant($custom_field_constant)) . '</label>
                <input class="form-control" type="text" id="custom_field_' . $custom_field_number . '" name="custom_field_' . $custom_field_number . '" maxlength="255" />
            </div>';
    }

    // Every string the builder script renders comes from here, so nothing escapes
    // lang(). {count} is substituted client-side.
    $labels = array(
        'Product ID / SKU'   => lang('Product ID / SKU'),
        'Short Description'  => lang('Short Description'),
        'Unit Price'         => lang('Unit Price'),
        'Inventory Quantity' => lang('Inventory Quantity'),

        // The per-variant advanced panel. These were reaching label() without ever
        // being declared here, and label() falls back to its own key — so the panel
        // printed English on a Turkish screen even though tr.json had every one of
        // them. A string used in the JS has to be listed here as well; the file
        // cannot call lang().
        'Advanced Settings'              => lang('Advanced Settings'),
        'Allow customer to set schedule' => lang('Allow customer to set schedule'),
        'Number of Payments'             => lang('Number of Payments'),
        'Payment Period'                 => lang('Payment Period'),
        'Start (days)'                   => lang('Start (days)'),

        'Cover image'        => lang('Cover image'),
        'Save & Continue'    => lang('Save & Continue'),
        'Web Browser Title'       => lang('Web Browser Title'),
        'Web Browser Description' => lang('Web Browser Description'),
        'Still empty'        => lang('Still empty'),
        'Ready to create'    => lang('Ready to create'),
        'GTIN'               => lang('GTIN'),
        'Barcode'            => lang('Barcode'),
        'Leave blank to generate' => lang('Leave blank to generate'),
        'A code is generated for any product left blank.'  => lang('A code is generated for any product left blank.'),
        'A code is generated for every variant left blank.' => lang('A code is generated for every variant left blank.'),
        'Images'             => lang('Images'),
        'Add Image'          => lang('Add Image'),
        'Remove'             => lang('Remove'),
        'Cover'              => lang('Cover'),
        'Select All'         => lang('Select All'),
        'Clear'              => lang('Clear'),
        'Combinations'       => lang('Combinations'),
        'Label'              => lang('Label'),
        "'No Thanks' Option" => lang('\'No Thanks\' Option'),
        'required_sku'       => lang(array('string' => '{var:1} is required', 'vars' => array(lang('Product ID / SKU')))),
        'request_failed'     => lang('Sorry, we could not accept your request.'),
        'no_images_to_apply' => lang('There are no images to apply.'),
        'upload_rejected'    => lang('Not uploaded, these are not image files: {files}'),
        'images_applied'     => lang('Images applied to {count} variants.'),
        'Create'             => lang('Create'),
        'Create & Continue'  => lang('Create & Continue'),
        'membership_summary' => lang('Buying this extends the customer\'s membership by {days} days.'),
        'Google Product Category' => lang('Google Product Category'),
        'single_summary'     => lang('A single product will be created. No catalog group is added.'),
        'group_summary'      => lang('{count} products and one product group covering them will be created.'),
    );

    // The nav lists only sections that are actually rendered — a link to a section
    // that is not on the page is a dead control.
    // Inventory sits above Variants on purpose: the matrix seeds every row's stock
    // from the quantity typed here, so entering it afterwards means generating the
    // rows, scrolling down, typing, scrolling back and pressing "apply to all".
    // Parent groups sit below Variants for the same reason in reverse — whether
    // this is one product in several groups or several products under one group is
    // only settled once the attribute selection is made.
    // The section list and its side navigation were removed: the panel
    // duplicated a scroll and, on a short viewport, was tall enough to push the
    // preview it shared a column with off the screen. The section ids stay in
    // the markup — they are anchors, and other screens link to them.
    //
    // These two gates lived in the middle of that list and are not about
    // navigation at all: they decide whether the gift card and product form
    // sections are drawn. Removing the list took them with it and both sections
    // silently disappeared.

    // A variant set's form template needs the 2026.4 columns. Without them the
    // switch would create a set whose form has nowhere to live, so the section
    // stays off the screen entirely rather than offering something that cannot
    // work. A single product's form is older than that and always available.
    $form_feature_ready = pg_pb_form_template_ready();

    // Gift cards are a store-wide feature. With it switched off in settings the
    // whole section goes, rather than offering a switch that produces a product
    // nothing in the checkout knows how to sell.
    $gift_cards_enabled = (defined('ECOMMERCE_GIFT_CARD') && ECOMMERCE_GIFT_CARD == TRUE);

    print
    pg_page_shell(array(
        'title'         => ($pg_mode === 'edit') ? lang('Edit Product') : lang('Create Product'),
        'extra classes' => 'products',
        'head'          => (($pg_mode === 'edit') && defined('BARCODE_ENABLED') && BARCODE_ENABLED
            ? '<script src="assets/jsbarcode/JsBarcode.all.min.js"></script>'
            : ''),
        'icon'          => 'store',
        // The heading names the product being edited. "Edit Product" on every
        // one of them tells the operator nothing they did not already know, and
        // with several tabs open it is the only thing that tells them apart.
        'heading'       => ($pg_mode === 'edit')
            ? (($v('short_description') !== '') ? $v('short_description') : $v('name'))
            : lang('Create Product'),
        'cancel'        => array('enable' => 'true', 'url' => 'view_products.php'),
        'breadcrumb'    => array(
            array('label' => lang('All Products'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_products.php'),
            array('label' => ($pg_mode === 'edit') ? lang('Edit Product') : lang('Create Product'))),
    )) .
    get_wysiwyg_editor_code(array('full_description', 'details', 'out_of_stock_message', 'order_receipt_message')) .
    pg_pb_render_styles() . '

    ' . (($pg_mode === 'edit')
        /*
            No "view on site" link here, and it is not an omission.

            A product address is only half of its URL: the catalog builds
            {catalog page}/{address_name} (get_catalog.php:533, :622), and the
            page half depends on which catalog the visitor arrived through. The
            same product sits in several groups, each shown by a different page
            with its own detail page setting, so /Software is not a shorter form
            of /shop-product-sidebar/Software — it is a URL that resolves to
            whatever page happens to be called Software, or to nothing.

            Legacy edit_product.php offers no such link either. Listing every
            catalog the product appears in would be truthful, but the page a
            group is shown on lives in that page's settings blob rather than a
            column, so answering it means reading every catalog page and walking
            the group tree for each.
        */
        ? '<nav class="navigation mb-3" aria-label="' . lang('Button Bar') . '">
                <a class="btn btn-sm btn-link link-secondary" data-loading-content="' . lang('Duplicating') . '" href="duplicate_product.php?id=' . h($pg_product_id) . get_token_query_string_field() . '"><i class="bi bi-copy me-1"></i>' . lang('Duplicate') . '</a>
                ' . (($v('product_form') or $v('pg_pb_has_form_fields'))
                    ? '<a class="btn btn-sm btn-link link-secondary" href="view_fields.php?product_id=' . h($pg_product_id) . '"><i class="bi bi-ui-checks me-1"></i>' . lang('Product Form') . '</a>'
                    : '') . '
            </nav>'
        : '') . '

    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
        </div>
    </div>

    <form name="form" id="pg_pb_form" action="' . (($pg_mode === 'edit') ? 'edit_product.php' : 'add_product.php') . '" method="post">
    ' . (($pg_mode === 'edit') ? '<input type="hidden" name="id" value="' . h($pg_product_id) . '" />' : '') . '
        ' . get_token_field() . '
        <input type="hidden" id="variants_json" name="variants_json" value="" />
        <input type="hidden" id="attributes_meta_json" name="attributes_meta_json" value="" />

        <div class="row">

            <!--
                The side column sits on the right, after the form in source order, so
                keyboard and screen-reader users reach the fields before the
                navigation and the previews. order-lg-2 puts it on the right
                visually; the form takes order-lg-1.

                Below lg it drops under the form, which is where a summary belongs on
                a narrow screen — above it, it would push the first field off screen.
            -->
            <div class="col-12 col-lg-8 col-xxl-9 order-1">

                <div class="row">

                    <!-- ----------------------------------------------- images -->
                    <!-- No card: images are the first thing an operator drops in and
                         the picker is already a bordered drop area. Wrapping it in a
                         second bordered surface with a header just adds chrome
                         around a field. -->
                    <div class="col-12 mb-4" id="pg_pb_sec_images">
                        ' . pg_pb_render_image_picker(
                                // Stored images, cover first. On create this is
                                // empty and the picker draws its empty state.
                                $v('selected_images', array()),
                                '<button type="button" class="btn btn-sm btn-outline-secondary ms-auto" data-bs-toggle="modal" data-bs-target="#image_code" title="' . lang('Code') . '"><i class="bi bi-code-slash"></i></button>') . '

                        <div class="modal fade" id="image_code" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">' . lang('Code') . '</h5>
                                        <button type="button" title="' . lang('Close') . '" class="btn-close" data-bs-dismiss="modal" aria-label="' . lang('Close') . '"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row">
                                            <div class="col-12 my-2">
                                                <div class="alert alert-primary">' . lang('Tags') . ': <span>^^image_loop_start^^</span>, <span>^^image_alt^^</span>, <span>^^image_url^^</span>, <span>^^image_loop_end^^</span></div>
                                            </div>
                                            <div class="col-12 my-2">
                                                <textarea id="code" name="code">' . h($image_code_template) . '</textarea>
                                                ' . get_codemirror_includes() . '
                                                ' . get_codemirror_javascript(array('id' => 'code', 'code_type' => 'mixed')) . '
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ---------------------------------------------- product -->
                    <div class="col-12" id="pg_pb_sec_basic">
                        <div class="card mb-4">
                            <div class="card-header bg-reset border-0 d-flex align-items-center gap-2">
                                <i class="bi bi-info-circle text-primary"></i>
                                <span class="h5 mb-0 text-primary fw-bold">' . lang('Main Informations') . '</span>
                            </div>
                            <div class="card-body">
                                <div class="row">

                                    <div class="col-12 col-lg-4 my-2">
                                        <label for="name" class="form-label">*' . lang('Product ID / SKU') . '</label>
                                        <input type="text" name="name" id="name" class="form-control" required value="' . h($v('name')) . '" />
                                        <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        <div class="form-text pg-pb-group-only d-none">' . lang('Used as the prefix of every variant SKU.') . '</div>
                                    </div>

                                    <div class="col-12 col-lg-5 my-2">
                                        <label for="short_description" class="form-label">' . lang('Short Description') . '</label>
                                        <input type="text" name="short_description" id="short_description" class="form-control" maxlength="255" value="' . h($v('short_description')) . '" />
                                        <div class="form-text pg-pb-group-only d-none">' . lang('Becomes the product group name shown in the catalog.') . '</div>
                                    </div>

                                    <div class="col-12 col-lg-3 my-2">
                                        <label for="price" class="form-label">' . lang('Unit Price') . '</label>
                                        <div class="input-group">
                                            <input value="' . h($v('price', '0')) . '" type="text" name="price" id="price" class="form-control" maxlength="12" inputmode="decimal" inputmask="" data-inputmask-alias="currency" data-inputmask-groupSeparator="," data-inputmask-digits="2" data-inputmask-digitsOptional="false" data-inputmask-placeholder="0" style="text-align:right;" />
                                            <label class="input-group-text" for="price">' . BASE_CURRENCY_SYMBOL . '</label>
                                        </div>
                                        <div class="form-text pg-pb-group-only d-none">' . lang('Starting price for every variant; each row can differ.') . '</div>
                                    </div>

                                    <div class="col-6 col-lg-3 my-2">
                                        <label for="selection_type" class="form-label">' . lang('Selection Type') . '</label>
                                        <select name="selection_type" id="selection_type" class="form-select">' . select_selection_type($v('selection_type', 'quantity')) . '</select>
                                    </div>
                                    <div class="col-6 col-lg-3 my-2">
                                        <label for="default_quantity" class="form-label">' . lang('Default Quantity') . '</label>
                                        <input class="form-control" value="' . h($v('default_quantity', '1')) . '" type="number" min="0" name="default_quantity" id="default_quantity" />
                                    </div>
                                    <div class="col-6 col-lg-3 my-2">
                                        <label for="minimum_quantity" class="form-label">' . lang('Min. Quantity') . '</label>
                                        <input class="form-control" value="' . h($v('minimum_quantity', '')) . '" type="number" min="0" name="minimum_quantity" id="minimum_quantity" />
                                    </div>
                                    <div class="col-6 col-lg-3 my-2">
                                        <label for="maximum_quantity" class="form-label">' . lang('Max. Quantity') . '</label>
                                        <input class="form-control" value="' . h($v('maximum_quantity', '')) . '" type="number" min="0" name="maximum_quantity" id="maximum_quantity" />
                                    </div>

                                    <div class="col-12 mt-3 mb-2">
                                        <label for="full_description" class="form-label">' . lang('Full Description') . '</label>
                                        <textarea id="full_description" name="full_description">' . h($v('full_description')) . '</textarea>
                                    </div>

                                    <div class="col-12 my-2">
                                        <label for="details" class="form-label">' . lang('Details') . '</label>
                                        <textarea id="details" name="details">' . h($v('details')) . '</textarea>
                                    </div>

                                    ' . pg_pb_render_switch_row(
                                            pg_pb_render_switch(array(
                                                'id'      => 'enabled',
                                                'name'    => 'enabled',
                                                'label'   => lang('Enabled'),
                                                
                                            )) .
                                            pg_pb_render_switch(array(
                                                'id'      => 'taxable',
                                                'name'    => 'taxable',
                                                'label'   => lang('Taxable'),
                                                
                                            )) .
                                            // Recurring lives here rather than in a card of its own:
                                            // it changes what the variant rows offer, so it has to be
                                            // decided before the operator gets to them.
                                            pg_pb_render_switch_row(
                                                pg_pb_render_switch(array(
                                                    'id'    => 'recurring',
                                                    'name'  => 'recurring',
                                                    'label' => lang('Recurring Payment'),
                                                    'help'  => lang('Charge the customer on a schedule instead of once.'),
                                                    'panel' =>
                                                        pg_pb_render_switch_row(
                                                            pg_pb_render_switch(array(
                                                                'id'    => 'recurring_schedule_editable_by_customer',
                                                                'name'  => 'recurring_schedule_editable_by_customer',
                                                                'label' => lang('Allow customer to set schedule'),
                                                                'help'  => lang('You may select default values for the schedule below'),
                                                            )), 'mt-0') .
    
                                                        $output_recurring_start .
    
                                                        '<div class="col-12 col-sm-6 col-lg-4 my-1">
                                                            <label for="number_of_payments" class="form-label">' . lang('Number of Payments') . '</label>
                                                            <input type="text" name="number_of_payments" id="number_of_payments" class="form-control" size="7" maxlength="7" inputmode="numeric" value="' . h($v('number_of_payments')) . '" />
                                                            <div class="form-text">' . get_number_of_payments_message() . '</div>
                                                        </div>
                                                        <div class="col-12 col-sm-6 col-lg-4 my-1">
                                                            <label for="payment_period" class="form-label">' . lang('Payment Period') . '</label>
                                                            <select name="payment_period" id="payment_period" class="form-select">' . select_payment_period($v('payment_period', 'Monthly')) . '</select>
                                                        </div>' .
    
                                                        $output_recurring_profile_disabled,
                                                ))) .
                                            // Inventory tracking sits with the other
                                            // product-wide switches: like recurring, it
                                            // decides what the variant rows are seeded
                                            // with, so it belongs above them.
                                            pg_pb_render_switch(array(
                                                'id'    => 'inventory',
                                                'name'  => 'inventory',
                                                'label' => lang('Inventory Tracking'),
                                                'panel' =>
                                                    '<div class="col-12 col-sm-6 col-lg-4">
                                                        <label for="inventory_quantity" class="form-label">' . lang('Inventory Quantity') . '</label>
                                                        <input type="number" min="0" name="inventory_quantity" id="inventory_quantity" class="form-control" value="' . h($v('inventory_quantity', '')) . '" />
                                                        <div class="form-text pg-pb-group-only d-none">' . lang('Starting stock for every variant; each row can differ.') . '</div>
                                                    </div>
                                                    <div class="col-12 mt-3">
                                                        <label for="out_of_stock_message" class="form-label">' . lang('Out of Stock Message') . '</label>
                                                        <textarea id="out_of_stock_message" name="out_of_stock_message">' . h($v('out_of_stock_message')) . '</textarea>
                                                    </div>
                                                    ' . pg_pb_render_switch_row(
                                                            pg_pb_render_switch(array(
                                                                'id'    => 'backorder',
                                                                'name'  => 'backorder',
                                                                'label' => lang('Backorder'),
                                                                'help'  => lang('Let customers order this even when stock reaches zero.'),
                                                            ))),
                                            )) .
                                            // Shipping and commission are product-wide switches too, and
                                            // both decide what the rest of the screen is even asking. The
                                            // shipping one carries its dimensions with it rather than
                                            // pointing at a card further down the page.
                                            $output_shippable .
                                            $output_commissionable) . '

                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- --------------------------------------------- variants -->
                    <div class="col-12" id="pg_pb_sec_variants">
                        <div class="card mb-4">
                            <div class="card-header bg-reset border-0 d-flex align-items-center gap-2">
                                <i class="bi bi-diagram-3 text-primary"></i>
                                <span class="h5 mb-0 text-primary fw-bold">' . (($pg_mode === 'edit') ? lang('Product Attributes') : lang('Variants')) . '</span>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    ' . pg_pb_render_switch_row(
                                            pg_pb_render_switch(array(
                                                // No name: this only reveals UI. What the server
                                                // acts on is the combination count, and that is
                                                // read from the matrix, not from this switch.
                                                'id'    => 'pg_pb_has_variants',

                                                // Ticked and its panel open while editing. The
                                                // switch decides whether a create turns into a
                                                // variant set, which is a question that only exists
                                                // before the product does; here the options are just
                                                // what this product is, so hiding them behind a
                                                // toggle asks the operator to reveal a fact about
                                                // their own product.
                                                'checked' => ($pg_mode === 'edit') ? TRUE : FALSE,
                                                'label' => ($pg_mode === 'edit')
                                                    ? lang('Product Attributes')
                                                    : lang('This product has variants'),
                                                'help'  => ($pg_mode === 'edit')
                                                    ? lang('Which options this product is sold in. One per attribute — a product is a single variant.')
                                                    : lang('Tick the options this product comes in. Two or more combinations create a variant group; one or none creates a single product.'),
                                                'panel' =>
                                                    '<div class="col-12 d-flex justify-content-end mb-2">
                                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#pg_pb_attribute_modal">
                                                            <i class="bi bi-plus-circle me-2"></i>' . lang('New Attribute') . '</button>
                                                    </div>
                                                    <div class="col-12">
                                                        ' . pg_pb_render_dimensions($v('pg_pb_selected_option_ids', array()), array(), ($pg_mode === 'edit')) . '
                                                    </div>
                                                    <div class="col-12 mt-3 d-none" id="pg_pb_combo_preview"></div>

                                                    <div class="col-12 col-lg-6 mt-3 pg-pb-group-only d-none">
                                                        <label for="sku_template" class="form-label">' . lang('SKU Template') . '</label>
                                                        <input type="text" id="sku_template" name="sku_template" class="form-control" placeholder="' . lang('e.g. {Color}-{Size}') . '" value="' . h($v('sku_template')) . '" />
                                                        <div class="pg-pb-tokens d-flex flex-wrap gap-1 mt-1" data-pg-pb-target="sku_template"></div>
                                                        <div class="form-text">' . lang('Added after the SKU above. Use attribute names in curly braces.') . '</div>
                                                    </div>
                                                    <div class="col-12 col-lg-6 mt-3 pg-pb-group-only d-none">
                                                        <label for="short_description_template" class="form-label">' . lang('Short Description Template') . '</label>
                                                        <input type="text" id="short_description_template" name="short_description_template" class="form-control" placeholder="' . lang('e.g. T-Shirt {Color}-{Size}') . '" value="' . h($v('short_description_template')) . '" />
                                                        <div class="pg-pb-tokens d-flex flex-wrap gap-1 mt-1" data-pg-pb-target="short_description_template"></div>
                                                        <div class="form-text">' . lang('The whole description. Leave blank to use the one above plus the combination.') . '</div>
                                                    </div>

                                                    <!-- The matrix belongs to this switch, so it lives
                                                         inside its panel rather than in a card of its
                                                         own further down the page. -->
                                                    <div class="col-12 mt-4 d-none" id="pg_pb_matrix_wrapper">
                                                        <hr class="mt-0" />
                                                        <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                                                            <i class="bi bi-grid-3x3-gap text-primary"></i>
                                                            <span class="h6 mb-0 fw-bold">' . lang('Variants to Create') . '</span>
                                                            <span id="pg_pb_variant_count" class="badge text-bg-primary">0</span>
                                                            <div class="d-flex flex-wrap gap-1 ms-auto">
                                                                <button type="button" id="pg_pb_apply_price" class="btn btn-sm btn-outline-secondary"><i class="bi bi-currency-exchange me-1"></i>' . lang('Apply Price to All') . '</button>
                                                                <button type="button" id="pg_pb_apply_stock" class="btn btn-sm btn-outline-secondary"><i class="bi bi-boxes me-1"></i>' . lang('Apply Stock to All') . '</button>
                                                                <button type="button" id="pg_pb_apply_images" class="btn btn-sm btn-outline-secondary"><i class="bi bi-images me-1"></i>' . lang('Apply Images to All') . '</button>
                                                            </div>
                                                        </div>

                                                        <!-- Below lg the header row is hidden and every cell becomes a
                                                             block, so a row reads as a small card; each cell carries its
                                                             own label for that width. Bootstrap display utilities only,
                                                             no table-responsive: the table is not overflowing, it is
                                                             being squeezed. -->
                                                        <table class="table table-hover align-middle mb-0 pg-pb-vtable">
                                                            <!--
                                                                The classes go on the <tr>, not the <thead>.
                                                                Bootstrap has no d-*-table-header-group utility —
                                                                its display list stops at table, table-row and
                                                                table-cell — so d-lg-table-header-group matched
                                                                no rule and the d-none beside it kept the header
                                                                hidden at every width. Nobody could see what the
                                                                columns were.
                                                            -->
                                                            <thead>
                                                                <tr class="d-none d-lg-table-row">
                                                                    <th>' . lang('Combinations') . '</th>
                                                                    <th style="width:16%">' . lang('Product ID / SKU') . '</th>
                                                                    <th style="width:22%">' . lang('Short Description') . '</th>
                                                                    <th style="width:12rem">' . lang('Unit Price') . '</th>
                                                                    <th style="width:7rem">' . lang('Inventory Quantity') . '</th>
                                                                    <th style="width:11rem">' . lang('Images') . '</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody id="pg_pb_matrix"></tbody>
                                                        </table>
                                                    </div>',
                                            ))) . '
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- --------------------------------------- parent groups -->
                    <!-- Below Variants, not above: which question this control is
                         even asking depends on the attribute selection. One product
                         joins several groups; a set of products gets one group of
                         its own placed under a parent. -->
                    <div class="col-12" id="pg_pb_sec_groups">
                        <div class="card mb-4">
                            <div class="card-header bg-reset border-0 d-flex align-items-center gap-2">
                                <i class="bi bi-folder text-primary"></i>
                                <span class="h5 mb-0 text-primary fw-bold">' . lang('Parent Product Groups') . '</span>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    ' . $output_parent_group_field . '
                                    ' . $output_catalog_group_field . '
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ------------------------------------- checkout options -->
                    <div class="col-12" id="pg_pb_sec_checkout">
                        <div class="card mb-4">
                            <div class="card-header bg-reset border-0 d-flex align-items-center gap-2">
                                <i class="bi bi-credit-card text-primary"></i>
                                <span class="h5 mb-0 text-primary fw-bold">' . lang('Checkout Options') . '</span>
                            </div>
                            <div class="card-body">
                                <div class="row">

                                    <div class="col-12 col-lg-8 my-2">
                                        <label class="form-label" for="required_product">' . lang('Requires Product') . '</label>
                                        <select class="form-select" id="required_product" name="required_product">
                                            <option value="">-' . lang(array('string' => 'Select {var:1}', 'vars' => array(lang('Product')))) . '-</option>' . select_product() . '
                                        </select>
                                        <div class="form-text">' . lang('The customer must also have this product in the cart.') . '</div>
                                    </div>

                                    ' . $output_sage_group_id . '

                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- -------------------------------------------------- seo -->
                    <div class="col-12" id="pg_pb_sec_seo">
                        <div class="card mb-4">
                            <div class="card-header bg-reset border-0 d-flex align-items-center gap-2">
                                <i class="bi bi-search text-primary"></i>
                                <span class="h5 mb-0 text-primary fw-bold">' . lang('Site Search & SEO') . '</span>
                                ' . $output_seo_badge . '
                            </div>
                            <div class="card-body">
                                <div class="row">

                                    <!-- Site search, not a search engine: these words feed the
                                         on-site search and the tag cloud. Full width and a tag
                                         input because it is a list, not a sentence. -->
                                    <div class="col-12 my-2">
                                        <label for="keywords" class="form-label">' . lang('Search Keywords') . '</label>
                                        <input type="text" name="keywords" id="keywords" class="form-control tagin min-height-tagin" data-placeholder="' . lang('Add tags') . '" maxlength="255" value="' . h($v('keywords')) . '" />
                                        <script>
                                            if (document.body.contains(document.querySelector("input#keywords"))) {
                                                tagin(document.querySelector("#keywords"));
                                            }
                                        </script>
                                        <div class="form-text">' . lang('Used by the search on your own site, not by search engines.') . '</div>
                                    </div>

                                    <div class="col-12 col-lg-6 my-2">
                                        <label for="address_name" class="form-label">' . lang('Catalog Name') . '</label>
                                        <input type="text" name="address_name" id="address_name" class="form-control" value="' . h($v('address_name')) . '" />
                                        <div class="form-text">' . lang('This option determines the url address of the product. Automatically assigned if left blank.') . '</div>
                                    </div>
                                    <div class="col-12 col-lg-6 my-2">
                                        <label for="title" class="form-label">' . lang('Web Browser Title') . '</label>
                                        <input type="text" name="title" id="title" class="form-control" maxlength="255" value="' . h($v('title')) . '" />
                                        <div id="seo_c_title"></div>
                                    </div>
                                    <div class="col-12 my-2">
                                        <label for="meta_description" class="form-label">' . lang('Web Browser Description') . '</label>
                                        <textarea name="meta_description" id="meta_description" class="form-control" maxlength="255">' . h($v('meta_description')) . '</textarea>
                                        <div id="seo_c_meta_description"></div>
                                    </div>
                                    ' . $output_seo_checklist . '

                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ------------------------------------------ identifiers -->
                    <div class="col-12" id="pg_pb_sec_identifiers">
                        <div class="card mb-4">
                            <div class="card-header bg-reset border-0 d-flex align-items-center gap-2">
                                <i class="bi bi-upc-scan text-primary"></i>
                                <span class="h5 mb-0 text-primary fw-bold">' . lang('RSS Feed') . '</span>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-12 col-lg-3 my-2">
                                        <label for="brand" class="form-label">' . lang('Brand') . '</label>
                                        <input type="text" name="brand" id="brand" class="form-control" maxlength="100" value="' . h($v('brand')) . '" />
                                    </div>
                                    <!--
                                        Hidden as soon as a variant set is being built. A GTIN names
                                        one article: the medium blue shirt has its own, and a feed
                                        that repeats one code across nine variants is rejected. In
                                        that case the field lives per variant, under the row.
                                    -->
                                    <div class="col-12 col-lg-3 my-2" id="pg_pb_gtin_field">
                                        <label for="gtin" class="form-label">' . lang('GTIN') . '</label>
                                        <input type="text" name="gtin" id="gtin" class="form-control" maxlength="50" placeholder="' . lang('e.g. UPC') . '" value="' . h($v('gtin')) . '" />
                                    </div>
                                    <div class="col-12 col-lg-3 my-2 d-none" id="pg_pb_gtin_moved">
                                        <label class="form-label">' . lang('GTIN') . '</label>
                                        <div class="form-control-plaintext small text-muted">
                                            <i class="bi bi-arrow-down-circle me-1"></i>' . lang('Set per variant, under each row.') . '
                                        </div>
                                    </div>
                                    <div class="col-12 col-lg-3 my-2">
                                        <label for="mpn" class="form-label">' . lang('MPN') . '</label>
                                        <input type="text" name="mpn" id="mpn" class="form-control" maxlength="50" placeholder="' . lang('i.e. manufacturer product number') . '" value="' . h($v('mpn')) . '" />
                                    </div>
                                    <div class="col-12 col-lg-3 my-2">
                                        <label for="google_product_category" class="form-label">' . lang('Google Product Category') . '</label>
                                        <select name="google_product_category" id="google_product_category" class="form-select" style="width:100%"></select>
                                        <div class="form-text" id="pg_pb_taxonomy_status">' . $output_taxonomy_status . '</div>
                                    </div>
                                    ' . $output_barcode_fields . '
                                </div>
                            </div>
                        </div>
                    </div>


                    <!-- ------------------------------------- helpful contents -->
                    <!-- ------------------------------------- advanced settings -->
                    <!-- Five sections that most products never touch. Collapsed by
                         default and gathered into one card: on screen they were half
                         the page, and the operator scrolled past all of them to reach
                         the save button.

                         No data-bs-parent, so more than one can be open at once.
                         Collapsing a half-filled section because the operator opened
                         another one is worse than a longer page.

                         Apostrophes are avoided in this comment on purpose: the
                         whole block sits inside a PHP single-quoted string, and one
                         stray quote ends it. -->
                    <div class="col-12" id="pg_pb_sec_advanced">
                        <div class="card mb-4">
                            <div class="card-header bg-reset border-0 d-flex align-items-center gap-2">
                                <i class="bi bi-sliders text-primary"></i>
                                <span class="h5 mb-0 text-primary fw-bold">' . lang('Advanced Settings') . '</span>
                            </div>
                            <div class="card-body">
                                <div class="accordion" id="pg_pb_advanced">

                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button' . ($v('membership_renewal') ? '' : ' collapsed') . '" type="button" data-bs-toggle="collapse" data-bs-target="#pg_pb_adv_membership">
                                                <i class="bi bi-person-badge me-2"></i>' . lang('Membership') . '
                                            </button>
                                        </h2>
                                        <div id="pg_pb_adv_membership" class="accordion-collapse collapse' . ($v('membership_renewal') ? ' show' : '') . '">
                                            <div class="accordion-body">
                                                <div class="row">

                                    <div class="col-12 col-lg-4 my-2">
                                        <label for="membership_renewal" class="form-label">' . lang('Add Days to Customer\'s Membership') . '</label>
                                        <div class="input-group">
                                            <input type="text" name="membership_renewal" id="membership_renewal" class="form-control" size="7" maxlength="7" inputmode="numeric" style="text-align:right;" value="' . h($v('membership_renewal')) . '" />
                                            <span class="input-group-text">' . lang('day(s)') . '</span>
                                        </div>
                                        <div class="form-text text-end">' . lang('0 for none') . '</div>
                                    </div>

                                    <!-- What buying this actually does lives in submit_order.php and
                                         membership_job.php. The operator types a number into a box
                                         and cannot see the rest, so it is written out here. Hidden
                                         until the box has a value: with none of this happening, the
                                         list would be describing a thing that does not occur. -->
                                    <div class="col-12 col-lg-8 my-2 d-none" id="pg_pb_membership_effects">
                                        <div class="alert alert-secondary mb-0">
                                            <div class="fw-semibold mb-1" id="pg_pb_membership_summary"></div>
                                            <ul class="mb-0 ps-3 small">' . $output_membership_effects . '</ul>
                                        </div>
                                    </div>

                                    ' . pg_pb_render_switch_row(
                                            pg_pb_render_switch(array(
                                                'id'    => 'grant_private_access',
                                                'name'  => 'grant_private_access',
                                                'label' => lang('Grant Private Access to Customer'),
                                                'panel' =>
                                                    '<div class="col-12 col-md-6 col-lg-4">
                                                        <label class="form-label" for="private_folder">' . lang('Set "View" Access to Folder') . '</label>
                                                        <select class="form-select" id="private_folder" name="private_folder">
                                                            <option value="">-' . lang(array('string' => 'Select {var:1}', 'vars' => array(lang('Folder')))) . '-</option>' . select_folder(0, 0, 0, 0, array(), array(), 'private') . '
                                                        </select>
                                                    </div>
                                                    <div class="col-12 col-md-6 col-lg-4">
                                                        <label for="private_days" class="form-label">' . lang('Length') . '</label>
                                                        <div class="input-group">
                                                            <input type="text" name="private_days" id="private_days" class="form-control" size="7" maxlength="7" inputmode="numeric" style="text-align:right;" value="' . h($v('private_days')) . '" />
                                                            <span class="input-group-text">' . lang('day(s)') . '</span>
                                                        </div>
                                                        <div class="form-text text-end">' . lang('leave blank for no expiration') . '</div>
                                                    </div>
                                                    <div class="col-12 col-md-6 col-lg-4">
                                                        <label class="form-label" for="send_to_page">' . lang('Set Customer\'s Start Page to') . '</label>
                                                        <select class="form-select" id="send_to_page" name="send_to_page">
                                                            <option value="">-' . lang(array('string' => 'Select {var:1}', 'vars' => array(lang('Page')))) . '-</option>' . select_page($v('send_to_page')) . '
                                                        </select>
                                                    </div>',
                                            ))) . '
                                                </div>
                                            </div>
                                        </div>
                                    </div>
    ' . ($gift_cards_enabled ? '
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button' . ($v('gift_card') ? '' : ' collapsed') . '" type="button" data-bs-toggle="collapse" data-bs-target="#pg_pb_adv_giftcard">
                                                <i class="bi bi-gift me-2"></i>' . lang('Email Gift Card') . '
                                            </button>
                                        </h2>
                                        <div id="pg_pb_adv_giftcard" class="accordion-collapse collapse' . ($v('gift_card') ? ' show' : '') . '">
                                            <div class="accordion-body">
                                                <div class="row">

                                    <!-- What ordering this does lives in submit_order.php and the
                                         e-mail queue; none of it is guessable from a switch. -->
                                    <div class="col-12 d-none" id="pg_pb_giftcard_effects">
                                        <div class="alert alert-secondary mb-0">
                                            <ul class="mb-0 ps-3 small">' . $output_gift_card_effects . '</ul>
                                        </div>
                                    </div>

                                    ' . pg_pb_render_switch_row(
                                                pg_pb_render_switch(array(
                                                    'id'    => 'gift_card',
                                                    'name'  => 'gift_card',
                                                    'label' => lang('Email Gift Card'),
                                                    'panel' =>
                                                        '<div class="col-12 col-lg-8">
                                                            <label class="form-label" for="gift_card_email_subject">' . lang('Subject') . '</label>
                                                            <input type="text" id="gift_card_email_subject" name="gift_card_email_subject" class="form-control" maxlength="100" value="' . h($v('gift_card_email_subject')) . '" />
                                                            <div class="d-flex flex-wrap gap-1 mt-1" data-pg-pb-giftvar-target="gift_card_email_subject">' . $output_gift_card_variables . '</div>
                                                        </div>
    
                                                        <div class="col-12 mt-3">
                                                            <label class="form-label">' . lang('Format') . '</label>
                                                            <div class="form-check form-check-inline">
                                                                <input class="form-check-input collapse-switcher" type="radio" id="gift_card_email_format_plain_text" name="gift_card_email_format" checked="checked" value="plain_text" data-bs-target="#gift_card_email_format_plain_text_row" />
                                                                <label class="form-check-label" for="gift_card_email_format_plain_text">' . lang('Plain Text') . '</label>
                                                            </div>
                                                            <div class="form-check form-check-inline">
                                                                <input class="form-check-input collapse-switcher" type="radio" id="gift_card_email_format_html" name="gift_card_email_format" value="html"' . (($v('gift_card_email_format') === 'html') ? ' checked="checked"' : '') . ' data-bs-target="#gift_card_email_format_html_row" />
                                                                <label class="form-check-label" for="gift_card_email_format_html">' . lang('HTML') . '</label>
                                                            </div>
                                                        </div>
    
                                                        <div class="col-12 collapse popover fade bs-popover-bottom p-0 w-100 mb-2 mt-2" id="gift_card_email_format_plain_text_row">
                                                            <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(18px, 0px);"></div>
                                                            <div class="popover-body">
                                                                <label for="gift_card_email_body" class="form-label">' . lang('Body') . '</label>
                                                                <textarea class="form-control" id="gift_card_email_body" name="gift_card_email_body" rows="4">' . h($v('gift_card_email_body')) . '</textarea>
                                                                <div class="form-label small text-muted mt-2 mb-1">' . lang('Insert a variable') . '</div>
                                                                <div class="d-flex flex-wrap gap-1" data-pg-pb-giftvar-target="gift_card_email_body">' . $output_gift_card_variables . '</div>
                                                            </div>
                                                        </div>
    
                                                        <div class="col-12 collapse popover fade bs-popover-bottom p-0 w-100 mb-2 mt-2" id="gift_card_email_format_html_row">
                                                            <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(111px, 0px);"></div>
                                                            <div class="popover-body">
                                                                <div class="row">
                                                                    <div class="col-12 col-lg-8">
                                                                        <label class="form-label" for="gift_card_email_page_id">' . lang('Page') . '</label>
                                                                        <select class="form-select" id="gift_card_email_page_id" name="gift_card_email_page_id">
                                                                            <option value="">-' . lang(array('string' => 'Select {var:1}', 'vars' => array(lang('Page')))) . '-</option>' . select_page($v('gift_card_email_page_id')) . '
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>',
                                                ))) . '
                                                </div>
                                            </div>
                                        </div>
                                    </div>
    ' : '') . '
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#pg_pb_adv_complete">
                                                <i class="bi bi-bag-check me-2"></i>' . lang('Order Complete Options') . '
                                            </button>
                                        </h2>
                                        <div id="pg_pb_adv_complete" class="accordion-collapse collapse">
                                            <div class="accordion-body">
                                                <div class="row">

                                    <div class="col-12 my-2">
                                        <label class="form-label" for="order_receipt_message">' . lang('Order Receipt Page Message') . '</label>
                                        <textarea id="order_receipt_message" name="order_receipt_message">' . h($v('order_receipt_message')) . '</textarea>
                                    </div>

                                    <div class="col-12 col-lg-4 my-2">
                                        <label class="form-label" for="order_receipt_bcc_email_address">' . lang('Order Receipt BCC E-mail Address') . '</label>
                                        <input type="text" class="form-control" id="order_receipt_bcc_email_address" name="order_receipt_bcc_email_address" maxlength="100" inputmode="email" value="' . h($v('order_receipt_bcc_email_address')) . '" />
                                    </div>
                                    <div class="col-12 col-lg-4 my-2">
                                        <label class="form-label" for="email_page">' . lang('E-mail Additional Page to Customer') . '</label>
                                        <select class="form-select" id="email_page" name="email_page">
                                            <option value="">-' . lang(array('string' => 'Select {var:1}', 'vars' => array(lang('Page')))) . '-</option>' . select_page($v('email_page')) . '
                                        </select>
                                    </div>
                                    <div class="col-12 col-lg-4 my-2">
                                        <label class="form-label" for="email_bcc">' . lang('BCC E-mail Address') . '</label>
                                        <input type="text" class="form-control" id="email_bcc" name="email_bcc" maxlength="100" inputmode="email" value="' . h($v('email_bcc')) . '" />
                                    </div>

                                    <div class="col-12 col-lg-4 my-2">
                                        <label class="form-label" for="contact_group_id">' . lang('Add to Contact Group') . '</label>
                                        <select class="form-select" id="contact_group_id" name="contact_group_id">
                                            <option value="">-' . lang(array('string' => 'Select {var:1}', 'vars' => array(lang('Contact Group')))) . '-</option>' . select_contact_group(0, $user) . '
                                        </select>
                                    </div>
                                    <div class="col-12 col-lg-4 my-2">
                                        <label for="reward_points" class="form-label">' . lang('Reward Points') . '</label>
                                        <input type="text" name="reward_points" id="reward_points" class="form-control" size="5" maxlength="9" inputmode="numeric" style="text-align:right;" value="' . h($v('reward_points')) . '" />
                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
    ' . ($form_feature_ready ? '
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button' . ($v('product_form') ? '' : ' collapsed') . '" type="button" data-bs-toggle="collapse" data-bs-target="#pg_pb_adv_form">
                                                <i class="bi bi-ui-checks me-2"></i>' . lang('Product Form') . '
                                            </button>
                                        </h2>
                                        <div id="pg_pb_adv_form" class="accordion-collapse collapse' . ($v('product_form') ? ' show' : '') . '">
                                            <div class="accordion-body">
                                                <div class="row">
                                    ' . pg_pb_render_switch_row(
                                            pg_pb_render_switch(array(
                                                'id'    => 'product_form',
                                                'name'  => 'product_form',
                                                'label' => lang('Enable Product Form'),
                                                'help'  => lang('Ask the customer for information when they order this. The fields are drawn on the next screen.'),
                                                'panel' =>
                                                    '<div class="col-12 mb-3">
                                                        <div class="alert alert-primary mb-0 pg-pb-group-only d-none">
                                                            <i class="bi bi-info-circle me-2"></i>' . lang('One form is drawn for the whole set and written to every variant.') . '
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-lg-5">
                                                        <label class="form-label" for="form_name">' . lang('Form Title for Display') . '</label>
                                                        <input type="text" id="form_name" name="form_name" class="form-control" maxlength="100" value="' . h($v('form_name')) . '" />
                                                    </div>
                                                    <div class="col-12 col-lg-3">
                                                        <label class="form-label" for="form_label_column_width">' . lang('Label Column Width') . '</label>
                                                        <div class="input-group">
                                                            <input type="text" id="form_label_column_width" name="form_label_column_width" class="form-control" size="3" maxlength="3" inputmode="numeric" value="' . h($v('form_label_column_width')) . '" />
                                                            <label class="input-group-text" for="form_label_column_width">%</label>
                                                        </div>
                                                        <div class="form-text">' . lang('leave blank for auto') . '</div>
                                                    </div>
                                                    <div class="col-12 col-lg-4">
                                                        <label class="form-label">' . lang('Quantity Type') . '</label>
                                                        <div class="form-check">
                                                            <input value="One Form per Quantity" class="form-check-input" type="radio" id="form_quantity_type_one_form_per_quantity" name="form_quantity_type" checked="checked" />
                                                            <label class="form-check-label" for="form_quantity_type_one_form_per_quantity">' . lang('One form per quantity') . '</label>
                                                        </div>
                                                        <div class="form-check">
                                                            <input value="One Form per Product" class="form-check-input" type="radio" id="form_quantity_type_one_form_per_product" name="form_quantity_type" />
                                                            <label class="form-check-label" for="form_quantity_type_one_form_per_product">' . lang('One form per product') . '</label>
                                                        </div>
                                                    </div>',
                                            ))) . '
                                                </div>
                                            </div>
                                        </div>
                                    </div>
    ' : '') . '
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button' . ($v('submit_form') ? '' : ' collapsed') . '" type="button" data-bs-toggle="collapse" data-bs-target="#pg_pb_adv_submitform" aria-expanded="false">
                                                <i class="bi bi-file-earmark-text me-2"></i>' . lang('Submit Form') . '
                                            </button>
                                        </h2>
                                        <div id="pg_pb_adv_submitform" class="accordion-collapse collapse' . ($v('submit_form') ? ' show' : '') . '">
                                            <div class="accordion-body">
                                                <div class="row">
                                    ' . pg_pb_render_switch_row(
                                            pg_pb_render_switch(array(
                                                'id'    => 'submit_form',
                                                'name'  => 'submit_form',
                                                'label' => lang('Submit Form'),
                                                'help'  => lang('Buying this creates or updates a record on one of your custom forms.'),
                                                'panel' => '<div class="col-12">' . $output_submit_form_block . '</div>',
                                            ))) . '
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button' . ($v('add_comment') ? '' : ' collapsed') . '" type="button" data-bs-toggle="collapse" data-bs-target="#pg_pb_adv_comment" aria-expanded="false">
                                                <i class="bi bi-chat-left-text me-2"></i>' . lang('Add Comment') . '
                                            </button>
                                        </h2>
                                        <div id="pg_pb_adv_comment" class="accordion-collapse collapse' . ($v('add_comment') ? ' show' : '') . '">
                                            <div class="accordion-body">
                                                <div class="row">
                                    ' . pg_pb_render_switch_row(
                                            pg_pb_render_switch(array(
                                                'id'    => 'add_comment',
                                                'name'  => 'add_comment',
                                                'label' => lang('Add Comment'),
                                                'help'  => lang('Buying this posts a comment on a page, as the customer.'),
                                                'panel' =>
                                                    '<div class="col-12 col-lg-4">
                                                        <label class="form-label" for="add_comment_page_id">' . lang('Page') . '</label>
                                                        <select class="form-select" id="add_comment_page_id" name="add_comment_page_id">
                                                            <option value="">-' . lang(array('string' => 'Select {var:1}', 'vars' => array(lang('Page')))) . '-</option>' . select_page($v('add_comment_page_id')) . '
                                                        </select>
                                                    </div>
                                                    <div class="col-12 col-lg-4">
                                                        <label class="form-label" for="add_comment_name">' . lang('Name') . '</label>
                                                        <input type="text" class="form-control" id="add_comment_name" name="add_comment_name" maxlength="100" value="' . h($v('add_comment_name')) . '" />
                                                        <div class="form-text">' . lang('Leave blank to use the name on the order.') . '</div>
                                                    </div>
                                                    <div class="col-12 my-2">
                                                        <label class="form-label" for="add_comment_message">' . lang('Message') . '</label>
                                                        <textarea class="form-control" id="add_comment_message" name="add_comment_message" rows="3">' . h($v('add_comment_message')) . '</textarea>
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" role="switch" value="1" id="add_comment_only_for_submit_form_update" name="add_comment_only_for_submit_form_update"' . ($v('add_comment_only_for_submit_form_update') ? ' checked="checked"' : '') . ' />
                                                            <label class="form-check-label" for="add_comment_only_for_submit_form_update">' . lang('Only when a submitted form is updated') . '</label>
                                                        </div>
                                                    </div>

                                                    <!-- One setting, used by both features — a quantity of
                                                         three either writes three records or one, and the
                                                         comment follows whatever the form does. It sits
                                                         here because this is the panel that is open more
                                                         often; the column is submit_form_quantity_type. -->
                                                    <div class="col-12 mt-2">
                                                        <label class="form-label">' . lang('Form/Comment Quantity Type') . '</label>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="submit_form_quantity_type" id="submit_form_quantity_type_quantity" value="One Form per Quantity"' . (($v('submit_form_quantity_type') === 'One Form per Quantity') ? ' checked="checked"' : '') . ' />
                                                            <label class="form-check-label" for="submit_form_quantity_type_quantity">' . lang('One form/comment per quantity') . '</label>
                                                        </div>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="submit_form_quantity_type" id="submit_form_quantity_type_product" value="One Form per Product"' . (($v('submit_form_quantity_type') !== 'One Form per Quantity') ? ' checked="checked"' : '') . ' />
                                                            <label class="form-check-label" for="submit_form_quantity_type_product">' . lang('One form/comment per product') . '</label>
                                                        </div>
                                                    </div>',
                                            ))) . '
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#pg_pb_adv_helpful">
                                                <i class="bi bi-journal-text me-2"></i>' . lang('Helpful Contents') . '
                                            </button>
                                        </h2>
                                        <div id="pg_pb_adv_helpful" class="accordion-collapse collapse">
                                            <div class="accordion-body">
                                                <div class="row">
                                    ' . $output_custom_product_fields . '
                                    <div class="col-12 my-2">
                                        <label for="notes" class="form-label">' . lang('Notes') . '</label>
                                        <textarea name="notes" id="notes" class="form-control" rows="3">' . h($v('notes')) . '</textarea>
                                        <div class="form-text">' . lang('Internal note. Customers never see it.') . '</div>
                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

            </div>

            <!-- ------------------------------------------------- side column -->
            <div class="col-12 col-lg-4 col-xxl-3 order-2">
                <!-- 3.3rem, not 1: the control panel header is fixed and the
                     column slides under it. -->
                <div class="position-sticky" style="top:3.3rem;">
                    <!--
                        Barcode above the preview. It is the one panel here the
                        operator acts on rather than reads, so it goes where the
                        eye lands first; the sketches below it are for glancing
                        at while typing.

                        The section navigation used to sit under these and is
                        gone: it duplicated a scroll, and on a short viewport it
                        was tall enough to push the preview off the screen — the
                        panel it was sharing the column with.
                    -->
                    ' . $output_barcode_card . '
                    ' . pg_pb_render_preview($pg_mode !== 'edit') . '
                </div>
            </div>

            <!--
                Its own column, and last in the row.

                On a narrow screen the columns stack in source order, so while this
                lived inside the form column the save bar came before the preview:
                the operator was asked to commit and only afterwards shown what they
                had built. order-3 puts it after both, which is the order the work
                actually happens in — fill in, look, save.

                Width matches the form column so it stays under the fields on a wide
                screen rather than running beneath the preview; 8 + 4 already fills
                the row, so this wraps onto its own line.
            -->
            <!--
                Same save bar as every other edit screen: a
                centred button in a sticky nav, nothing else. The alert box that used
                to sit here said what the button would do, but it said it in a shape
                no other screen uses and at a size that competed with the form.

                The sentence itself was worth keeping, so it moved to the top of the
                preview panel — which is where the operator is already looking to
                check their work, and where it reads as a description of the product
                rather than as a warning attached to a button.

                The stickiness sits on the column, not on a div inside it: a sticky
                box is confined to its own parent, and a wrapper only as tall as the
                bar gives it nothing to travel within. The parent of this column is
                the row, which is as tall as the whole form.
            -->
            <div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">
                <nav class="buttons navigation text-center mb-4" aria-label="' . lang('data edit buttons') . '">
                    <div class="container">
                        <div class="btn-group flex-wrap justify-content-center">
                            <button type="submit" id="pg_pb_create" name="submit_create" value="Create" class="btn my-1 btn-success" data-loading-content="' . (($pg_mode === 'edit') ? lang(array('string' => 'Saving')) : lang(array('string' => 'Creating'))) . '">
                                <span class="bi ' . (($pg_mode === 'edit') ? 'bi-check-circle' : 'bi-plus-circle') . ' me-2"></span><span class="btn-text">' . (($pg_mode === 'edit') ? lang(array('string' => 'Save')) : lang(array('string' => 'Create'))) . '</span>
                            </button>
                            ' . (($pg_mode === 'edit')
                                ? '<button type="submit" name="submit_delete" value="Delete" class="btn my-1 btn-danger" data-loading-content="' . lang(array('string' => 'Deleting')) . '" data-confirm-content="' . lang(array('string' => 'WARNING: This {var:1} will be permanently deleted.', 'vars' => array(lang('product')))) . '"><span class="bi bi-trash me-2"></span><span class="btn-text">' . lang(array('string' => 'Delete')) . '</span></button>'
                                : '') . '
                        </div>
                    </div>
                </nav>
            </div>
        </div>
    </form>

    <!-- Outside the form on purpose — see pg_pb_render_attribute_modal(). -->
    ' . pg_pb_render_attribute_modal() . '
    </main>
    <script>
        /* "<\/" is escaped below because this JSON sits inside a script element: a
           translation containing a literal closing script tag would end the block
           early and dump the rest of the page as markup.
           The same applies to this comment, which is why the tag is written with a
           backslash here — spelling it out plainly terminated the block and left
           window.PinegrapProductBuilder undefined, so every request went out with
           an empty CSRF token. */
        window.PinegrapProductBuilder = {
            currencySymbol: ' . str_replace('</', '<\/', encode_json(BASE_CURRENCY_SYMBOL)) . ',
            token: ' . str_replace('</', '<\/', encode_json($_SESSION['software']['token'])) . ',
            // Host only, the way a search result prints it — no scheme.
            siteUrl: ' . str_replace('</', '<\/', encode_json(HOSTNAME . PATH)) . ',
            // One option per attribute while editing: a product is one variant, and
        // a screen that lets it hold two options of the same attribute is a
        // screen that quietly turns it into a set.
        mode: ' . str_replace('</', '<\/', encode_json($pg_mode)) . ',
        singleOptionPerAttribute: ' . (($pg_mode === 'edit') ? 'true' : 'false') . ',
        seoCounters: true,
            barcodeFormat: ' . str_replace('</', '<\/', encode_json($barcode_format)) . ',
            labels: ' . str_replace('</', '<\/', encode_json($labels)) . '
        };
    </script>
    <script>
    ' . (($pg_mode === 'edit') ? '
    /* The card ships its options in window._pgBarcodeOpts; this is the line
       that starts it, ported from edit_product.php:1614. */
    $(function () {
        if (window.PgBarcode && window._pgBarcodeOpts) {
            PgBarcode.initProductBarcode(window._pgBarcodeOpts);
        }
    });
    ' : '') . '
        /* Same thresholds as settings.php — the counter is telling the operator what
           a search result will fit, and that does not change per screen. */
        initSeoCounters([
            { sel: "#title",            counterId: "seo_c_title",            min: 50,  max: 60  },
            { sel: "#meta_description", counterId: "seo_c_meta_description", min: 150, max: 160 }
        ]);
    </script>
    <script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/product_builder.js?v=' . @filemtime(dirname(__FILE__) . '/assets/product_builder.js') . '"></script>
    ' . output_footer();

    $liveform->remove_form();
}


/* ------------------------------------------------------------------ *
 * Loading a product back into the screen
 * ------------------------------------------------------------------ */

/**
 * Everything the product screen needs to draw an existing product.
 *
 * Returns the same shape the screen writes, so a field that round-trips
 * correctly does so because the two halves agree on one name, not because
 * somebody remembered to map it. Columns are taken as they are; only the
 * things the form represents differently are converted.
 *
 * @return array|NULL NULL when there is no such product
 */
function pg_pb_load_product($product_id)
{
    $product_id = (int) $product_id;

    if (!$product_id) {
        return NULL;
    }

    $row = db_item("SELECT * FROM products WHERE id = '" . e($product_id) . "' LIMIT 1");

    if (!$row or ($row['id'] == '')) {
        return NULL;
    }

    // Prices are stored in cents; the box shows currency.
    $row['price'] = number_format(((int) $row['price']) / 100, 2, '.', '');

    // The cover is products.image_name and the rest live in the xref, in the
    // order they were added. The screen treats them as one list whose first
    // entry is the cover, which is what the sortable list on screen means.
    $images = array();

    if ($row['image_name'] != '') {
        $images[] = $row['image_name'];
    }

    foreach (db_items("SELECT file_name FROM products_images_xref WHERE product = '" . e($product_id) . "'") as $image) {
        if ($image['file_name'] != '') {
            $images[] = $image['file_name'];
        }
    }

    $row['selected_images'] = $images;

    $row['catalog_group_ids'] = array();

    foreach (db_items("SELECT product_group FROM products_groups_xref WHERE product = '" . e($product_id) . "'") as $group) {
        $row['catalog_group_ids'][] = (int) $group['product_group'];
    }

    $row['allowed_zones'] = array();

    foreach (db_items("SELECT zone_id FROM products_zones_xref WHERE product_id = '" . e($product_id) . "'") as $zone) {
        $row['allowed_zones'][] = (int) $zone['zone_id'];
    }

    // The barcode is its own table and a product can hold several; the field on
    // screen edits the first, which is the one the label was printed from.
    $row['pg_pb_barcode'] = '';

    if (defined('BARCODE_ENABLED') && BARCODE_ENABLED) {
        $barcode = db_item("SELECT barcode FROM product_barcodes WHERE product_id = '" . e($product_id) . "' ORDER BY id LIMIT 1");
        if ($barcode and ($barcode['barcode'] != '')) {
            $row['pg_pb_barcode'] = $barcode['barcode'];
        }
    }

    // Field mappings for the submit-form feature, as the JavaScript calls that
    // recreate the rows on screen. product_submit_form_add_field() lives in
    // backend.src.js and is what the "Add Field" button uses, so a saved row and
    // a new one are built by the same code.
    $row['pg_pb_submit_form_create_js'] = '';
    $row['pg_pb_submit_form_update_js'] = '';

    foreach (db_items(
        "SELECT action, form_field_id, value
        FROM product_submit_form_fields
        WHERE product_id = '" . e($product_id) . "'
        ORDER BY id") as $field) {

        $call = 'product_submit_form_add_field(' . encode_json($field) . ');' . "\n";

        if ($field['action'] == 'create') {
            $row['pg_pb_submit_form_create_js'] .= $call;
        } else {
            $row['pg_pb_submit_form_update_js'] .= $call;
        }
    }

    // Which options this product carries. On the edit screen these are the
    // product's attributes, one per attribute — not a matrix.
    $row['pg_pb_selected_option_ids'] = array();

    foreach (pg_pb_product_attribute_rows($product_id) as $attribute_row) {
        $row['pg_pb_selected_option_ids'][] = (int) $attribute_row['option_id'];
    }

    // The switch is called product_form and the column is called form. The
    // switch reads its state by its own id, so without this line a product that
    // has a form opens with the switch off — and saving that screen turns the
    // form off. A rename would have been cleaner, but "form" is the column name
    // in every query in the codebase and "product_form" is what the JS hooks.
    $row['product_form'] = !empty($row['form']);

    // Does it actually have fields? A form switched on with nothing drawn is
    // still a form; a product with neither has no form screen worth offering.
    $row['pg_pb_has_form_fields'] = (int) db_value(
        "SELECT COUNT(*) FROM form_fields
        WHERE product_id = '" . e($product_id) . "' AND form_type = 'product'");

    // Which variant this product is, if it is one. Read-only on this screen:
    // editing a product and editing a variant set are separate screens.
    $row['pg_pb_variant_of'] = pg_pb_variant_group_id($product_id);

    return $row;
}


/**
 * Write the product screen back to an existing product.
 *
 * Mirrors pg_pb_save_new_product() for the single-product case, with three
 * differences that only exist when editing:
 *
 *   The SKU is not passed through get_unique_name(). On create that guards
 *   against a matrix producing a name that is taken; on edit it would rename
 *   the product to "shirt[1]" every time the operator pressed save without
 *   touching the field.
 *
 *   The xref tables are rewritten rather than added to, so unticking a
 *   category actually removes it.
 *
 *   The attribute link is left alone. Which variant this product is belongs to
 *   the set, and this screen does not draw that control.
 */
function pg_pb_update_product($product_id)
{
    $product_id = (int) $product_id;

    if (!$product_id) {
        return array('name' => '');
    }

    $product = pg_pb_common_from_post();
    $images  = pg_pb_selected_images();

    pg_pb_store_image_code_template(isset($_POST['code']) ? $_POST['code'] : '');

    $product['name']               = isset($_POST['name']) ? trim($_POST['name']) : '';
    $product['short_description']  = isset($_POST['short_description']) ? trim($_POST['short_description']) : '';
    $product['price']              = pg_pb_price_to_cents(isset($_POST['price']) ? $_POST['price'] : 0);
    $product['full_description']   = prepare_rich_text_editor_content_for_input(isset($_POST['full_description']) ? $_POST['full_description'] : '');
    $product['details']            = prepare_rich_text_editor_content_for_input(isset($_POST['details']) ? $_POST['details'] : '');
    $product['title']              = isset($_POST['title']) ? trim($_POST['title']) : '';
    $product['meta_description']   = isset($_POST['meta_description']) ? trim($_POST['meta_description']) : '';
    $product['inventory']          = empty($_POST['inventory']) ? '' : '1';
    $product['inventory_quantity'] = isset($_POST['inventory_quantity']) ? trim($_POST['inventory_quantity']) : '';
    $product['image_name']         = $images ? $images[0] : '';

    // The address is only recalculated when the operator changed it or when the
    // product never had one. Rebuilding it on every save would silently move a
    // published product to a new URL because its short description was edited.
    //
    // The fallback order is the one edit_product.php uses (:1794-1806):
    // typed address, then short description, then name, then the id — an
    // address of some kind is required, and the id is the last thing that is
    // guaranteed to exist.
    $address_source = isset($_POST['address_name']) ? trim($_POST['address_name']) : '';
    $current        = db_value("SELECT address_name FROM products WHERE id = '" . e($product_id) . "'");

    if (($address_source !== '') && ($address_source !== $current)) {
        $product['address_name'] = prepare_catalog_item_address_name($address_source, $product_id);
    } elseif ($current == '') {
        if ($product['short_description'] !== '') {
            $source = $product['short_description'];
        } elseif ($product['name'] !== '') {
            $source = $product['name'];
        } else {
            $source = (string) $product_id;
        }
        $product['address_name'] = prepare_catalog_item_address_name($source, $product_id);
    }

    // Last modified. Reports and the "recently changed" ordering read it, so a
    // save that leaves it alone makes the product look untouched.
    $product['timestamp'] = time();

    // Everything this screen manages feeds the SEO score, so the stored
    // analysis is stale after any save from here.
    $product['seo_analysis_current'] = 0;

    pg_pb_update_row('products', $product, 'id', $product_id);

    pg_pb_sync_tag_cloud_keywords(
        $product_id,
        isset($_POST['keywords']) ? $_POST['keywords'] : '',
        !empty($_POST['enabled']));

    // Rewritten, not appended: unticking has to mean something.
    db("DELETE FROM products_images_xref WHERE product = '" . e($product_id) . "'");

    foreach (array_slice($images, 1) as $image) {
        pg_pb_insert_row('products_images_xref', array(
            'product'   => $product_id,
            'file_name' => $image,
        ));
    }

    db("DELETE FROM products_groups_xref WHERE product = '" . e($product_id) . "'");

    if (!empty($_POST['catalog_group_ids']) && is_array($_POST['catalog_group_ids'])) {
        foreach ($_POST['catalog_group_ids'] as $group_id) {
            $group_id = (int) $group_id;
            if ($group_id) {
                pg_pb_insert_row('products_groups_xref', array(
                    'product'       => $product_id,
                    'product_group' => $group_id,
                ));
            }
        }
    }

    db("DELETE FROM products_zones_xref WHERE product_id = '" . e($product_id) . "'");

    if (!empty($_POST['allowed_zones']) && is_array($_POST['allowed_zones'])) {
        foreach ($_POST['allowed_zones'] as $zone_id) {
            $zone_id = (int) $zone_id;
            if ($zone_id) {
                pg_pb_insert_row('products_zones_xref', array(
                    'product_id' => $product_id,
                    'zone_id'    => $zone_id,
                ));
            }
        }
    }

    pg_pb_save_submit_form_fields($product_id);

    // Attributes. The edit screen allows one option per attribute — see the
    // note on singleOptionPerAttribute — so what comes back is this product's
    // own description, not a matrix. Rewritten wholesale: unticking an
    // attribute has to remove it.
    $variants = pg_pb_decode_variants(isset($_POST['variants_json']) ? $_POST['variants_json'] : '');

    if ($variants) {

        db("DELETE FROM products_attributes_xref WHERE product_id = '" . e($product_id) . "'");

        $sort_order = 0;

        foreach ($variants[0]['attributes'] as $attribute) {
            pg_pb_insert_row('products_attributes_xref', array(
                'product_id'   => $product_id,
                'attribute_id' => $attribute['attribute_id'],
                'option_id'    => $attribute['option_id'],
                'sort_order'   => $sort_order++,
            ));
        }
    }

    // Only ever adds. pg_assign_product_barcode() leaves a product that already
    // has one alone, which is what stops a save from printing a second label
    // for the same box.
    pg_pb_apply_barcode($product_id, isset($_POST['pg_pb_barcode']) ? $_POST['pg_pb_barcode'] : '');

    return array('name' => $product['name']);
}


/* ------------------------------------------------------------------ *
 * Side effects of saving a product
 * ------------------------------------------------------------------ */

/**
 * Keep the tag cloud in step with the product's site-search keywords.
 *
 * Ported from edit_product.php (:1841-1919), including its two conditions,
 * which are easy to read as bugs and are not:
 *
 *   Only products that already have a tag cloud xref record are updated. The
 *   xref says which clouds a product belongs to and is managed elsewhere; a
 *   product in no cloud has no keywords to keep in step.
 *
 *   A disabled product loses its keywords entirely. The cloud is a visitor
 *   facing list, and a disabled product is not for sale.
 */
function pg_pb_sync_tag_cloud_keywords($product_id, $keywords, $enabled)
{
    $product_id = (int) $product_id;

    if (!$product_id) {
        return;
    }

    if (!$enabled) {
        db("DELETE FROM tag_cloud_keywords WHERE (item_id = '" . e($product_id) . "') AND (item_type = 'product')");
        return;
    }

    $in_a_cloud = db_value(
        "SELECT COUNT(*) FROM tag_cloud_keywords_xref
        WHERE (item_id = '" . e($product_id) . "') AND (item_type = 'product')");

    if (!$in_a_cloud) {
        return;
    }

    $wanted = array();

    foreach (explode(',', (string) $keywords) as $keyword) {
        $keyword = trim($keyword);
        if ($keyword !== '') {
            $wanted[$keyword] = $keyword;
        }
    }

    $current = array();

    foreach (db_items("SELECT keyword FROM tag_cloud_keywords WHERE (item_id = '" . e($product_id) . "') AND (item_type = 'product')") as $row) {
        $current[$row['keyword']] = $row['keyword'];
    }

    // Only the difference is written. Deleting everything and re-inserting
    // would work, but tag_cloud_keywords is read by the storefront and would
    // briefly show the product with no keywords at all.
    foreach (array_diff_key($current, $wanted) as $keyword) {
        db("DELETE FROM tag_cloud_keywords
            WHERE (keyword = '" . e($keyword) . "')
                AND (item_id = '" . e($product_id) . "')
                AND (item_type = 'product')");
    }

    foreach (array_diff_key($wanted, $current) as $keyword) {
        pg_pb_insert_row('tag_cloud_keywords', array(
            'keyword'   => $keyword,
            'item_id'   => $product_id,
            'item_type' => 'product',
        ));
    }
}


/**
 * The checks edit_product.php runs before it writes anything.
 *
 * Kept together so that neither screen can save while skipping one. Each stops
 * the request the way the legacy screen does, because there is nothing sensible
 * to write once one of them fails.
 */
function pg_pb_validate_product_post($user, $product_id = 0)
{
    // A contributor may only file a product under a contact group they can
    // reach. Without this they can hand their product to any group on the site
    // by editing the select, and the group owner starts receiving it.
    if ($user['role'] == 3) {

        $new_group = isset($_POST['contact_group_id']) ? $_POST['contact_group_id'] : '';
        $current   = $product_id
            ? db_value("SELECT contact_group_id FROM products WHERE id = '" . e((int) $product_id) . "'")
            : '';

        if (($new_group != $current) && $new_group && (validate_contact_group_access($user, $new_group) == FALSE)) {
            output_error(lang('Access denied.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }
    }

    foreach (array('order_receipt_bcc_email_address', 'email_bcc') as $field) {
        if (!empty($_POST[$field]) && (validate_email_address($_POST[$field]) == FALSE)) {
            output_error(lang('The e-mail address is invalid.') . ' <a href="javascript:history.go(-1);">' . lang('Go back') . '</a>.');
        }
    }
}


/**
 * Rewrite a product's submit-form field mappings from the posted rows.
 *
 * Ported from edit_product.php (:2165-2247) with its two guards intact:
 *
 *   A field is only stored if it really belongs to the selected custom form.
 *   The row ids come from the browser, and without the check an operator could
 *   point a product at one form and then set fields on another.
 *
 *   The same field is only stored once. The screen can produce two rows for it,
 *   and two values for one field is not a thing the order code can act on.
 */
function pg_pb_save_submit_form_fields($product_id)
{
    $product_id = (int) $product_id;
    $page_id    = isset($_POST['submit_form_custom_form_page_id']) ? $_POST['submit_form_custom_form_page_id'] : '';

    if (!$product_id) {
        return;
    }

    db("DELETE FROM product_submit_form_fields WHERE product_id = '" . e($product_id) . "'");

    if (!$page_id) {
        return;
    }

    foreach (array('create', 'update') as $action) {

        $added = array();
        $last  = isset($_POST['last_submit_form_' . $action . '_field_number'])
            ? (int) $_POST['last_submit_form_' . $action . '_field_number']
            : 0;

        for ($number = 1; $number <= $last; $number++) {

            $prefix   = 'submit_form_' . $action . '_field_' . $number . '_';
            $field_id = isset($_POST[$prefix . 'form_field_id']) ? $_POST[$prefix . 'form_field_id'] : '';

            if (!$field_id or in_array($field_id, $added)) {
                continue;
            }

            $belongs = db_value(
                "SELECT id FROM form_fields
                WHERE (id = '" . e($field_id) . "') AND (page_id = '" . e($page_id) . "')");

            if (!$belongs) {
                continue;
            }

            pg_pb_insert_row('product_submit_form_fields', array(
                'product_id'    => $product_id,
                'action'        => $action,
                'form_field_id' => $field_id,
                'value'         => isset($_POST[$prefix . 'value']) ? trim($_POST[$prefix . 'value']) : '',
            ));

            $added[] = $field_id;
        }
    }
}

