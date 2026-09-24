<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the catalogue as the document editors see it.
 *
 * A product on an invoice or a delivery note line is picked from the store's
 * own catalogue: found by its name (short_description - the name column is
 * the SKU), its SKU or its barcode, and it brings its price, its VAT rate,
 * what is in stock, and the automatic campaign the store is running on it.
 * Nothing here writes; the editors read and the operator decides.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_ERP_ENTRY')) {
    exit;
}

/**
 * The store's own state as a tax-zone state code, when the tax zones are
 * drawn by state for the store's country and the store's state is in one of
 * them; '' otherwise. A US shop that collects sales tax sets a zone per
 * state; a Turkish shop has one zone for the country and its province plays
 * no part. The state is read from the organization's address.
 *
 * @return string
 */
function erp_store_tax_state()
{
    static $code = null;

    if ($code === null) {
        $code = erp_zone_state(defined('ORGANIZATION_STATE') ? (string) ORGANIZATION_STATE : '', erp_default_country_code());
    }

    return $code;
}

/**
 * A state as the tax zones know it: its code when the state belongs to a tax
 * zone of that country, '' otherwise. A state the zones do not name is not
 * handed to get_tax_rate_for_address(), which reads a known state outside
 * every zone as no zone at all.
 *
 * @param string $state  A state code or name
 * @param string $country_code
 * @return string
 */
function erp_zone_state($state, $country_code)
{
    static $cache = array();

    $state = trim((string) $state);
    $country = strtoupper(trim((string) $country_code));

    if (($state === '') || ($country === '')) {
        return '';
    }

    $key = $country . '|' . $state;

    if (!isset($cache[$key])) {
        $found = erp_state_code($state, $country);
        $cache[$key] = (($found !== '') && ((int) db_value("SELECT COUNT(*) FROM tax_zones_states_xref x
                INNER JOIN states s ON s.id = x.state_id
                INNER JOIN countries c ON c.id = s.country_id
                WHERE c.code = '" . escape($country) . "' AND s.code = '" . escape($found) . "'") > 0)) ? $found : '';
    }

    return $cache[$key];
}

/**
 * Whether the tax zones of a country are drawn by state: some state of it
 * belongs to a zone. Then a buyer in a state outside every zone pays no
 * sales tax, which is how a US shop collects only where it must.
 *
 * @param string $country_code
 * @return bool
 */
function erp_country_has_state_zones($country_code)
{
    static $cache = array();

    $country = strtoupper(trim((string) $country_code));

    if ($country === '') {
        return false;
    }

    if (!isset($cache[$country])) {
        $cache[$country] = ((int) db_value("SELECT COUNT(*) FROM tax_zones_states_xref x
            INNER JOIN states s ON s.id = x.state_id
            INNER JOIN countries c ON c.id = s.country_id
            WHERE c.code = '" . escape($country) . "'") > 0);
    }

    return $cache[$country];
}

/**
 * The tax rate a product without one of its own is sold at to an account:
 * the tax zone of the buyer's address, the way the checkout taxes a buyer by
 * where the goods go. The store's rate (erp_default_tax_rate()) stands in
 * when the account has no country, or is in the store's country with no
 * state to go by. A buyer in no zone at all - abroad, or in a state the
 * store does not collect for - is charged none.
 *
 * @param int $account_id
 * @return float
 */
function erp_account_zone_rate($account_id)
{
    static $cache = array();

    $account_id = (int) $account_id;

    if (isset($cache[$account_id])) {
        return $cache[$account_id];
    }

    $account = ($account_id > 0) ? erp_account($account_id) : null;
    $country = is_array($account) ? strtoupper(trim((string) $account['country_code'])) : '';

    if (($country === '') || !function_exists('get_tax_rate_for_address')) {
        return $cache[$account_id] = erp_default_tax_rate();
    }

    $state_given = trim((string) ($account['state'] ?? ''));
    $state = erp_zone_state($state_given, $country);

    if (($state === '') && erp_country_has_state_zones($country)) {
        if (($country === erp_default_country_code()) && ($state_given === '')) {
            return $cache[$account_id] = erp_default_tax_rate();
        }

        return $cache[$account_id] = 0.0;
    }

    $rate = get_tax_rate_for_address($country, $state);

    return $cache[$account_id] = ($rate === false) ? 0.0 : (float) $rate;
}

/**
 * The tax zone rate of a sale made at the store, the way the checkout would
 * tax a buyer standing at the counter: the store's country, and its state
 * where the zones are drawn by state. false when the store is in no zone,
 * which is what get_default_tax_rate() answers too.
 *
 * @return string|false
 */
function erp_store_zone_rate()
{
    static $rate = null;

    if ($rate === null) {
        $rate = false;
        $country = erp_default_country_code();

        if (($country !== '') && function_exists('get_tax_rate_for_address')) {
            $rate = get_tax_rate_for_address($country, erp_store_tax_state());
        }
    }

    return $rate;
}

/**
 * The tax rate a product without one of its own is sold at from the store:
 * the store's tax zone (erp_store_zone_rate()). Read once per request.
 *
 * @return float
 */
function erp_default_tax_rate()
{
    return (float) erp_store_zone_rate();
}

/**
 * The name the store gives its sales tax (config.erp_tax_name), '' when it
 * keeps the default, VAT. Read once per request.
 *
 * @return string
 */
function erp_tax_name()
{
    static $name = null;

    if ($name === null) {
        $name = (function_exists('waf_table_has_column') && waf_table_has_column('config', 'erp_tax_name'))
            ? trim((string) db_value("SELECT erp_tax_name FROM config LIMIT 1"))
            : '';
    }

    return $name;
}

/**
 * A caption that names the tax, in the store's word for it: VAT (KDV) by
 * default, or the name set in the ERP settings (Sales tax, GST, ...). The
 * default keeps its own translations; a custom name is set into a phrase.
 *
 * @param string $which  tax | amount | rate | percent | calculated |
 *                       deductible | report | invoice_tax | included |
 *                       excluding | subtotal_excl | added
 * @return string
 */
function erp_tax_label($which = 'tax')
{
    $name = erp_tax_name();

    if ($name === '') {
        $labels = array(
            'tax' => lang('VAT'),
            'amount' => lang('VAT Amount'),
            'rate' => lang('VAT Rate'),
            'percent' => lang('VAT %'),
            'calculated' => lang('Calculated VAT'),
            'deductible' => lang('Deductible VAT'),
            'report' => lang('VAT report'),
            'invoice_tax' => lang('Invoice VAT'),
            'included' => lang('VAT included'),
            'excluding' => lang('Excluding VAT'),
            'subtotal_excl' => lang('Subtotal (excl. VAT)'),
            'added' => lang('VAT added at the total'),
        );
    } else {
        $labels = array(
            'tax' => $name,
            'amount' => lang(array('string' => '{var:1} amount', 'vars' => $name)),
            'rate' => lang(array('string' => '{var:1} rate', 'vars' => $name)),
            'percent' => $name . ' %',
            'calculated' => lang(array('string' => 'Calculated {var:1}', 'vars' => $name)),
            'deductible' => lang(array('string' => 'Deductible {var:1}', 'vars' => $name)),
            'report' => lang(array('string' => '{var:1} report', 'vars' => $name)),
            'invoice_tax' => lang(array('string' => 'Invoice {var:1} total', 'vars' => $name)),
            'included' => lang(array('string' => '{var:1} included', 'vars' => $name)),
            'excluding' => lang(array('string' => 'Excluding {var:1}', 'vars' => $name)),
            'subtotal_excl' => lang(array('string' => 'Subtotal (excl. {var:1})', 'vars' => $name)),
            'added' => lang(array('string' => '{var:1} added at the total', 'vars' => $name)),
        );
    }

    return isset($labels[$which]) ? $labels[$which] : $labels['tax'];
}

/**
 * The heading over a line's tax on a document that carries the second tax:
 * the line's amount holds both taxes (tax_total), so the column is named for
 * both - "VAT + PST", "VAT + PST amount" - while the totals name each one.
 * Without a second tax it is erp_tax_label($which).
 *
 * @param string $which     tax | amount
 * @param bool   $has_tax2  Whether the document carries the second tax
 * @return string
 */
function erp_line_tax_label($which, $has_tax2)
{
    if (!$has_tax2) {
        return erp_tax_label($which);
    }

    $pair = erp_tax_label('tax') . ' + ' . ((erp_tax2_name() !== '') ? erp_tax2_name() : lang('Second tax'));

    return ($which === 'amount') ? lang(array('string' => '{var:1} amount', 'vars' => $pair)) : $pair;
}

/**
 * How the store has the ERP tax an order's shipping and surcharge
 * (config.erp_shipping_tax, 4.76): 'included', 'none', or '' for the
 * store's country to decide (erp_shipping_taxed()). Read once per request.
 *
 * @return string
 */
function erp_shipping_tax_setting()
{
    static $value = null;

    if ($value === null) {
        $value = (function_exists('waf_table_has_column') && waf_table_has_column('config', 'erp_shipping_tax'))
            ? trim((string) db_value("SELECT erp_shipping_tax FROM config LIMIT 1"))
            : '';

        if (!in_array($value, array('', 'included', 'none'), true)) {
            $value = '';
        }
    }

    return $value;
}

/**
 * Whether an order's shipping and surcharge carry tax on its invoice. Where
 * VAT applies the charge for delivering goods is part of the supply and is
 * taxed with them; a United States store's checkout collects sales tax on the
 * goods alone, so there the charges are left untaxed unless the settings say
 * otherwise.
 *
 * @return bool
 */
function erp_shipping_taxed()
{
    $setting = erp_shipping_tax_setting();

    if ($setting !== '') {
        return ($setting === 'included');
    }

    return (erp_default_country_code() !== 'US');
}

/**
 * The name of the second tax a line may carry (config.erp_tax2_name), ''
 * when there is none. Some places charge two taxes on the same amount - a
 * Canadian sale carries GST and PST, each at its own rate on the line's net -
 * and a store there names the second one in the ERP settings.
 *
 * @return string
 */
function erp_tax2_name()
{
    static $name = null;

    if ($name === null) {
        $name = (function_exists('waf_table_has_column') && waf_table_has_column('config', 'erp_tax2_name'))
            ? trim((string) db_value("SELECT erp_tax2_name FROM config LIMIT 1"))
            : '';
    }

    return $name;
}

/**
 * Whether invoice lines carry the second tax columns (4.75).
 *
 * @return bool
 */
function erp_invoice_lines_have_tax2()
{
    static $has = null;

    if ($has === null) {
        $has = function_exists('waf_table_has_column') && waf_table_has_column('erp_invoice_items', 'tax2_amount')
            && waf_table_has_column('erp_invoices', 'tax2_total');
    }

    return $has;
}

/**
 * Whether new lines take a second tax: the store names one and the columns
 * are there. A document that already carries one keeps showing it whatever
 * the setting says now.
 *
 * @return bool
 */
function erp_tax2_enabled()
{
    return (erp_tax2_name() !== '') && erp_invoice_lines_have_tax2();
}

/**
 * The SET pairs that store a line's second tax, or '' before the upgrade.
 *
 * @param array $line  tax2_rate, tax2_amount
 * @return string  SQL ending in a comma, or ''
 */
function erp_tax2_line_sql($line)
{
    if (!erp_invoice_lines_have_tax2()) {
        return '';
    }

    return "
                tax2_rate = '" . escape(number_format((float) ($line['tax2_rate'] ?? 0), 3, '.', '')) . "',
                tax2_amount = '" . (int) ($line['tax2_amount'] ?? 0) . "',";
}

/**
 * The SET pair that stores a document's second tax total, or '' before the
 * upgrade.
 *
 * @param array $totals  tax2_total
 * @return string  SQL ending in a comma, or ''
 */
function erp_tax2_total_sql($totals)
{
    if (!erp_invoice_lines_have_tax2()) {
        return '';
    }

    return "
            tax2_total = '" . (int) ($totals['tax2_total'] ?? 0) . "',";
}

/**
 * The columns a line needs from a product row.
 *
 * @return string
 */
function erp_product_columns($alias = 'p')
{
    return $alias . ".id, " . $alias . ".name, " . $alias . ".short_description, " . $alias . ".price, " . $alias . ".tax_rate,
        " . $alias . ".enabled, " . $alias . ".inventory, " . $alias . ".inventory_quantity, " . $alias . ".out_of_stock,
        " . $alias . ".image_name";
}

/**
 * Products matching what was typed.
 *
 * The name (short_description) is what people know a product by; the SKU
 * (name) is what the shelf label says; a barcode is what the scanner reads.
 * All three are searched, exact barcode hits first. Disabled products are
 * offered too - a bill for a product no longer on sale is still a bill -
 * marked so the editor can say so.
 *
 * @param string $query
 * @param int    $limit
 * @param int    $account_id  The account the document is for (erp_account_zone_rate())
 * @return array  See erp_product_for_line()
 */
function erp_product_search($query, $limit = 20, $account_id = 0)
{
    $query = trim((string) $query);

    if ($query === '') {
        return array();
    }

    $like = escape(escape_like(mb_substr($query, 0, 100)));
    $limit = max(1, min(50, (int) $limit));

    $results = array();
    $seen = array();

    // A scanner types the whole code and nothing else; an exact barcode is
    // the product, before any name that happens to contain the digits.
    $by_barcode = erp_product_by_barcode($query);

    if (is_array($by_barcode)) {
        $results[] = erp_product_for_line($by_barcode, $account_id);
        $seen[(int) $by_barcode['id']] = true;
    }

    $rows = (array) db_items("SELECT " . erp_product_columns('p') . "
        FROM products p
        WHERE p.short_description LIKE '%" . $like . "%' OR p.name LIKE '%" . $like . "%'
        ORDER BY p.enabled DESC, p.short_description ASC, p.name ASC, p.id ASC
        LIMIT " . $limit);

    foreach ($rows as $row) {
        if (isset($seen[(int) $row['id']])) {
            continue;
        }
        $results[] = erp_product_for_line($row, $account_id);
    }

    return array_slice($results, 0, $limit);
}

/**
 * The product a barcode names, or null.
 *
 * With the barcode feature on, the product_barcodes table; without it the
 * SKU doubles as the code, the way the local-sale screen reads a scan.
 *
 * @param string $barcode
 * @return array|null  A products row
 */
function erp_product_by_barcode($barcode)
{
    $barcode = trim((string) $barcode);

    if (($barcode === '') || (mb_strlen($barcode) > 100)) {
        return null;
    }

    $product_id = 0;

    if (defined('BARCODE_ENABLED') && BARCODE_ENABLED && function_exists('waf_table_has_column') && waf_table_has_column('product_barcodes', 'barcode')) {
        $product_id = (int) db_value("SELECT product_id FROM product_barcodes WHERE barcode = '" . escape($barcode) . "' LIMIT 1");
    }

    if ($product_id <= 0) {
        $product_id = (int) db_value("SELECT id FROM products WHERE name = '" . escape($barcode) . "' LIMIT 1");
    }

    if ($product_id <= 0) {
        return null;
    }

    $row = db_item("SELECT " . erp_product_columns('p') . " FROM products p WHERE p.id = '" . $product_id . "' LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * One product, by id, in the line shape.
 *
 * @param int $product_id
 * @return array|null
 */
function erp_product_line_data($product_id)
{
    $row = db_item("SELECT " . erp_product_columns('p') . " FROM products p WHERE p.id = '" . (int) $product_id . "' LIMIT 1");

    return is_array($row) ? erp_product_for_line($row) : null;
}

/**
 * A products row as the editor wants it.
 *
 * price is kurus, the way the editor works with money. tax_rate is the rate
 * the line starts with: the product's own, or the store's zone rate when the
 * product leaves it to the zone (tax_rate_source says which). stock is null
 * when the product is not stock-tracked. offer is the automatic campaign on
 * the product, or null.
 *
 * @param array $row
 * @return array
 */
function erp_product_for_line($row, $account_id = 0)
{
    $price = (int) $row['price'];
    $tracked = ((int) ($row['inventory'] ?? 0) === 1);

    $own_rate = (isset($row['tax_rate']) && ($row['tax_rate'] !== null) && ($row['tax_rate'] !== ''));

    // What the product has cost the store (includes/erp/stock.php): the line
    // editor prices a purchase at the last purchase and shows the average on
    // a sale. Null before the first purchase.
    $cost = function_exists('erp_stock_cost') ? erp_stock_cost((int) $row['id']) : null;

    return array(
        'id' => (int) $row['id'],
        'name' => (trim((string) ($row['short_description'] ?? '')) !== '') ? trim((string) $row['short_description']) : (string) $row['name'],
        'sku' => (string) $row['name'],
        'price' => $price,
        'tax_rate' => $own_rate ? (float) $row['tax_rate'] : (((int) $account_id > 0) ? erp_account_zone_rate($account_id) : erp_default_tax_rate()),
        'tax_rate_source' => $own_rate ? 'product' : 'zone',
        'enabled' => ((int) ($row['enabled'] ?? 1) === 1),
        'stock' => $tracked ? (int) $row['inventory_quantity'] : null,
        'out_of_stock' => $tracked ? (((int) ($row['out_of_stock'] ?? 0) === 1) || ((int) $row['inventory_quantity'] <= 0)) : false,
        'offer' => erp_product_offer((int) $row['id'], $price),
        // What this account has agreed for the product (includes/erp/
        // price_lists.php): its own price, or a discount on the list price.
        // The editor applies it on a sale in place of the campaign.
        'account_terms' => function_exists('erp_account_price_terms') ? erp_account_price_terms((int) $account_id, (int) $row['id']) : null,
        'image' => (string) ($row['image_name'] ?? ''),
        // Ready to put in a src: the editor draws it without knowing
        // where the store keeps its files.
        'image_url' => (trim((string) ($row['image_name'] ?? '')) !== '') ? (PATH . (string) $row['image_name']) : '',
        'cost' => is_array($cost) ? array(
            'avg' => (int) $cost['avg_cost'],
            'avg_text' => erp_money_out((int) $cost['avg_cost']),
            'last' => (int) $cost['last_cost'],
            'last_text' => erp_money_out((int) $cost['last_cost']),
            'last_date' => ((string) $cost['last_cost_date'] > '0000-00-00') ? (string) prepare_form_data_for_output((string) $cost['last_cost_date'], 'date') : '',
        ) : null,
    );
}

/**
 * A product's picture at thumbnail size, or the placeholder that stands in
 * for one, or nothing at all.
 *
 * Nothing at all when the store has turned pictures off in tables: the
 * setting is "Show Product Images in Tables" (ECOMMERCE_SHOW_PRODUCT_IMAGES),
 * already honoured by the catalogue screens, and a line table is a table.
 * The markup is the one those screens use - lazy loading through data-src,
 * an img-thumbnail box, a grey SVG when the product has no picture - so a
 * product looks the same wherever it is listed.
 *
 * @param string $image_name  products.image_name, as stored
 * @param int    $px          Box size in pixels
 * @return string  HTML, or '' when pictures are off
 */
function erp_product_thumb($image_name, $px = 40)
{
    if (defined('ECOMMERCE_SHOW_PRODUCT_IMAGES') && !((int) ECOMMERCE_SHOW_PRODUCT_IMAGES === 1)) {
        return '';
    }

    $px = max(16, min(120, (int) $px));
    $image_name = trim((string) $image_name);

    if ($image_name === '') {
        return '<svg class="bd-placeholder-img img-thumbnail" width="' . $px . '" height="' . $px . '" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="' . h(lang(array('string' => 'No Image'))) . '"><rect width="100%" height="100%" fill="#868e96"></rect></svg>';
    }

    return '<img style="width:' . $px . 'px;height:' . $px . 'px;" class="img-fluid img-thumbnail lazy" alt=""'
        . ' src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/images/loading.gif"'
        . ' data-src="' . PATH . h($image_name) . '" />';
}

/**
 * Whether the line tables draw a picture column at all.
 *
 * @return bool
 */
function erp_product_thumbs_on()
{
    return !defined('ECOMMERCE_SHOW_PRODUCT_IMAGES') || ((int) ECOMMERCE_SHOW_PRODUCT_IMAGES === 1);
}

/**
 * The automatic campaign the store is running on a product, as a discount
 * rate, or null.
 *
 * Only what can be known from the product alone counts: an enabled offer
 * within its dates that needs no code, is not an upsell, has no cart rule
 * (minimum subtotal or quantity) and no customer conditions, with a
 * 'discount product' action aimed at this product or at a group it is in. An
 * offer that depends on the rest of the cart or on who is buying is the
 * checkout's to judge, not a typed invoice's. The biggest discount wins, the
 * way the cart applies the best offer.
 *
 * An amount discount is turned into a rate to three decimals so the line can
 * carry one figure; on a price under a thousand lira the difference is under
 * a kurus.
 *
 * @param int $product_id
 * @param int $price  Kurus, for turning an amount into a rate
 * @return array|null  ['id' => int, 'code' => string, 'rate' => float, 'label' => string]
 */
function erp_product_offer($product_id, $price)
{
    static $cache = array();

    $product_id = (int) $product_id;
    $price = (int) $price;

    if (($product_id <= 0) || ($price <= 0)) {
        return null;
    }

    if (!isset($cache[$product_id])) {
        $cache[$product_id] = null;

        if (!function_exists('pg_offer_action_targets_product') || !function_exists('pg_offer_action_target')) {
            return null;
        }

        $has_group_column = function_exists('waf_table_has_column') && waf_table_has_column('offer_actions', 'discount_product_group_id');
        $has_conditions = function_exists('waf_table_has_column') && waf_table_has_column('offer_conditions', 'offer_id');

        $rows = (array) db_items("SELECT o.id AS offer_id, o.code, o.description,
                a.id AS action_id, a.discount_product_product_id, a.discount_product_amount, a.discount_product_percentage"
                . ($has_group_column ? ", a.discount_product_group_id, a.discount_product_target" : "") . "
            FROM offers o
            INNER JOIN offers_offer_actions_xref x ON x.offer_id = o.id
            INNER JOIN offer_actions a ON a.id = x.offer_action_id
            WHERE o.status = 'enabled'
                AND o.start_date <= CURRENT_DATE()
                AND (o.end_date = '0000-00-00' OR CURRENT_DATE() <= o.end_date)
                AND o.require_code = 0
                AND o.upsell = 0
                AND o.offer_rule_id = 0
                AND a.type = 'discount product'
                AND (a.discount_product_amount <> 0 OR a.discount_product_percentage <> 0)");

        $best = null;

        foreach ($rows as $row) {
            // The cheapest-line target cannot be answered from the product.
            if (pg_offer_action_target($row) === 'cheapest') {
                continue;
            }
            if (!pg_offer_action_targets_product($row, $product_id)) {
                continue;
            }
            if ($has_conditions && ((int) db_value("SELECT COUNT(*) FROM offer_conditions WHERE offer_id = '" . (int) $row['offer_id'] . "'") > 0)) {
                continue;
            }

            if ((int) $row['discount_product_amount'] > 0) {
                $discount = min($price, (int) $row['discount_product_amount']);
                $rate = round(($discount * 100) / $price, 3);
            } else {
                $rate = round((float) $row['discount_product_percentage'], 3);
                $discount = erp_apply_rate($price, $rate);
            }

            if (($rate <= 0) || ($rate > 100)) {
                continue;
            }

            if (($best === null) || ($discount > $best['discount'])) {
                $best = array(
                    'id' => (int) $row['offer_id'],
                    'code' => trim((string) $row['code']),
                    'rate' => $rate,
                    'discount' => $discount,
                    'label' => (trim((string) $row['description']) !== '') ? trim((string) $row['description']) : trim((string) $row['code']),
                );
            }
        }

        if ($best !== null) {
            unset($best['discount']);
        }

        $cache[$product_id] = $best;
    }

    return $cache[$product_id];
}
