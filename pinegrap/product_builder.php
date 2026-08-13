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
 * Shared product building logic for the v2 product screens
 * (add_product2.php, edit_product2.php, view_products2.php).
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
 * edit_product.php / add_product_variants.php trio is untouched and remains the
 * reference for correct behaviour.
 */

// Guard against double include: add_product2.php and edit_product2.php may both
// be pulled in by a future shared controller.
if (defined('PG_PRODUCT_BUILDER')) {
    return;
}
define('PG_PRODUCT_BUILDER', TRUE);


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
 * point, matching the legacy add_product.php parsing exactly — do not get
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
    $value = str_replace(array(',', ' '), '', trim((string) $value));

    if ($value === '') {
        return 0;
    }

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

    $common = array(
        'enabled'                 => $switch('enabled'),
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
function pg_pb_save_new_product()
{
    $variants = pg_pb_decode_variants(isset($_POST['variants_json']) ? $_POST['variants_json'] : '');
    $mode     = pg_pb_mode($variants);

    $common = pg_pb_common_from_post();
    $images = pg_pb_selected_images();

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
            $product['address_name']      = '';

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
            $product_ids[] = pg_pb_create_product($product, array(
                'images'     => $variant_images,
                'group_ids'  => array($group_id),
                'zone_ids'   => $zone_ids,
                'attributes' => $variant['attributes'],
            ));
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
 * Sticky section navigation.
 *
 * The v2 screens are long. Without a map the operator scrolls looking for the
 * SEO fields; with one they jump. Entries whose section is not rendered are not
 * listed — a link to a section that does not exist is worse than no link.
 *
 * @param array $sections list of array('id' => 'sec_basic', 'label' => ..., 'icon' => 'bi-...')
 * @return string
 */
function pg_pb_render_section_nav($sections)
{
    $output_items = '';

    foreach ($sections as $section) {
        $output_items .=
            '<a class="list-group-item list-group-item-action border-0 rounded py-2 px-3 d-flex align-items-center gap-2"
                href="#' . h($section['id']) . '" data-pg-pb-nav="' . h($section['id']) . '">
                <i class="bi ' . h($section['icon']) . ' opacity-75"></i>
                <span class="text-truncate">' . h($section['label']) . '</span>
            </a>';
    }

    return
        '<nav id="pg_pb_nav" class="position-sticky d-none d-lg-block" style="top:1rem;" aria-label="' . lang('Sections') . '">
            <div class="list-group list-group-flush small">' . $output_items . '</div>
        </nav>';
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
function pg_pb_render_dimensions($selected_option_ids = array(), $default_option_ids = array())
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

        $output .= pg_pb_render_attribute_card($attribute, $selected_option_ids, $default_option_id);
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
function pg_pb_render_attribute_card($attribute, $selected_option_ids = array(), $default_option_id = 0)
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
                        <button type="button" class="btn btn-sm btn-link text-decoration-none pg-pb-attr-toggle-all">' . lang('Select All') . '</button>
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

                <div class="d-none align-items-center gap-2 pg-pb-attr-default-wrap">
                    <span class="text-muted small">' . lang('Default Option') . ':</span>
                    <select class="form-select form-select-sm pg-pb-attr-default" style="max-width:220px;">' . $output_default_options . '</select>
                </div>

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
 * did nothing for months (CLAUDE.md, "Sipariş İptal — Tek Akış + Onarım").
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
                        <img class="object-fit-contain w-100 h-100" src="' . h($source) . '" alt="" />
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
        '<div class="bg-body-tertiary rounded p-3">

            <div id="pg_pb_image_drop" ondblclick="software_image_picker({initialize:true});">

                <div id="software_image_picker_container"
                    class="user-select-none sortable-list img-list row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 row-cols-xl-6 g-3">' . $output_tiles . '</div>

                <div id="pg_pb_image_empty" class="d-none text-center text-muted py-4">
                    <i class="bi bi-images d-block mb-2" style="font-size:1.75rem;opacity:.5;"></i>
                    <div class="fw-semibold text-body">' . lang('No images yet') . '</div>
                    <div class="small">' . lang('The first image becomes the cover image shown in the catalog.') . '</div>
                </div>

            </div>

            <div class="d-flex align-items-center flex-wrap gap-2 mt-3">
                <button type="button" class="btn btn-primary" onclick="software_image_picker({initialize:true});"><i class="bi bi-plus-circle me-2"></i>' . lang('Add Image') . '</button>
                ' . $extra_action . '
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
    $checked = !empty($props['checked']) ? ' checked="checked"' : '';
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

    $output_help = ($help !== '')
        ? '<div class="form-text mt-0" style="padding-left:2.5em;">' . $help . '</div>'
        : '';

    $output_panel = '';

    if ($panel !== '') {
        $output_panel =
            '<div class="collapse popover fade bs-popover-bottom p-0 w-100 mb-2" id="' . h($panel_id) . '">
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
            [id^="pg_pb_sec_"] { scroll-margin-top: 1rem; }

            /* Active section in the sticky nav. list-group-item-action has no
               "current" state of its own that survives without an href match. */
            #pg_pb_nav .list-group-item.pg-pb-nav-active {
                background-color: var(--bs-primary-bg-subtle);
                color: var(--bs-primary-text-emphasis);
                font-weight: 600;
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
 * Zone multi-select options.
 *
 * @param array $selected_ids
 * @return string
 */
function pg_pb_render_zone_options($selected_ids = array())
{
    $output = '';

    foreach (pg_pb_zones() as $zone) {
        $selected = in_array((int) $zone['id'], $selected_ids) ? ' selected="selected"' : '';
        $output .= '<option value="' . h($zone['id']) . '"' . $selected . '>' . h($zone['name']) . '</option>';
    }

    return $output;
}
