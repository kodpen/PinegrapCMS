<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Supplier feeds screens (view_supplier_feeds.php, edit_supplier_feed.php):
 * the form's values and the one encrypted secret (address, user, password,
 * header name, key), the request built from the chosen sign-in way, the
 * first read of a feed into a mapping (record path, the feed's fields with a
 * sample, the proposed fields), and the test pull that says what a pull
 * would add, change, close and reopen without writing anything.
 *
 * Every read goes through api_http_request() into a file under data/temp/,
 * which is deleted once read. The mapping is kept as JSON in
 * supplier_feeds.mapping: format, record_path, paths (the other places
 * records could sit), keys (the feed's fields with a sample value), fields
 * (product field => feed field), confirmed (saved by a person), records
 * (how many the first read took), read_at.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The largest feed file kept; a larger download is stopped.
define('PG_SUPPLIER_FEED_MAX_BYTES', 209715200);

// Records the first read takes for the mapping (F2).
define('PG_SUPPLIER_FEED_SAMPLE_RECORDS', 200);

// Records a test pull reads at most in one request.
define('PG_SUPPLIER_FEED_TEST_RECORDS', 50000);

// Rows a test pull shows under each figure.
define('PG_SUPPLIER_FEED_TEST_ROWS', 20);

// Held prices the card shows; the rest are in its "Show all" drawer.
define('PG_SUPPLIER_FEED_HELD_ROWS', 20);

/**
 * Whether the three tables are there (2026.11.0, 11.0.255).
 *
 * @return bool
 */
function pg_supplier_feed_ready()
{
    return pg_schema_has('supplier_feeds') && pg_schema_has('supplier_feed_items') && pg_schema_has('supplier_feed_runs');
}

/**
 * The hours a feed may be pulled every.
 *
 * @return array
 */
function pg_supplier_feed_intervals()
{
    return array(1, 3, 6, 12, 24);
}

/**
 * The sign-in ways with their labels, in the order the screen offers them.
 *
 * @return array way => label
 */
function pg_supplier_feed_auth_ways()
{
    return array(
        'none'   => lang('None'),
        'basic'  => lang('User and password'),
        'header' => lang('Key header'),
        'query'  => lang('Key in the address'),
    );
}

/**
 * The product fields a feed's fields are mapped to, the required ones first.
 *
 * @return array field => label
 */
function pg_supplier_feed_field_labels()
{
    return array(
        'supplier_key' => lang('Supplier code'),
        'buy_price'    => lang('Buy price'),
        'stock'        => lang('Stock'),
        'barcode'      => lang('Barcode'),
        'model_code'   => lang('Model code'),
        'name'         => lang('Name'),
        'brand'        => lang('Brand'),
        'category'     => lang('Category'),
        'description'  => lang('Description'),
        'image'        => lang('Picture'),
        'currency'     => lang('Currency'),
        'vat'          => lang('VAT rate'),
    );
}

/**
 * The fields a pull cannot do without.
 *
 * @return array
 */
function pg_supplier_feed_required_fields()
{
    return array('supplier_key', 'buy_price', 'stock');
}

/**
 * The host of an address, the only part of it shown; '' when there is none.
 *
 * @param string $url
 * @return string
 */
function pg_supplier_feed_host($url)
{
    $host = parse_url((string) $url, PHP_URL_HOST);

    return is_string($host) ? strtolower($host) : '';
}

/**
 * The feed form's values, checked. A secret box left empty keeps what was
 * saved; only the chosen sign-in way's fields are kept. An http address
 * needs allow_http and takes only a key in the address (F1).
 *
 * @param array $post   the posted form
 * @param array $stored the saved secret, array() for a new feed
 * @return array 'feed' (columns), 'secret' (to encrypt), 'errors' (texts)
 */
function pg_supplier_feed_input($post, $stored)
{
    $post = (array) $post;
    $stored = (array) $stored;
    $errors = array();
    $text = function ($name) use ($post) {
        return isset($post[$name]) ? trim((string) $post[$name]) : '';
    };

    $format = strtolower($text('format'));
    $interval = (int) $text('interval_hours');
    $currency = strtoupper($text('currency'));

    $feed = array(
        'name'           => $text('name'),
        'format'         => in_array($format, array('xml', 'csv', 'json'), true) ? $format : '',
        'interval_hours' => in_array($interval, pg_supplier_feed_intervals(), true) ? $interval : 1,
        'currency'       => preg_match('/^[A-Z]{3}$/', $currency) ? $currency : '',
        'vat_included'   => empty($post['vat_included']) ? 0 : 1,
        'erp_account_id' => max(0, (int) $text('erp_account_id')),
        'allow_http'     => empty($post['allow_http']) ? 0 : 1,
        'host'           => '',
    );

    if ($feed['name'] === '') {
        $errors[] = lang('Enter a name for the feed.');
    } elseif (mb_strlen($feed['name'], 'UTF-8') > 150) {
        $errors[] = lang('The name is longer than 150 characters.');
    }

    $auth = $text('auth');
    $auth = array_key_exists($auth, pg_supplier_feed_auth_ways()) ? $auth : 'none';

    // A box left empty keeps the saved value.
    $value = function ($name) use ($text, $stored) {
        $posted = $text($name);

        return (($posted === '') && isset($stored[$name])) ? (string) $stored[$name] : $posted;
    };

    $secret = array('address' => $value('address'), 'auth' => $auth);
    $scheme = strtolower((string) parse_url($secret['address'], PHP_URL_SCHEME));

    if ($secret['address'] === '') {
        $errors[] = lang('Enter the feed\'s address.');
    } elseif ((strlen($secret['address']) > 2000) || !in_array($scheme, array('http', 'https'), true) || (pg_supplier_feed_host($secret['address']) === '')) {
        $errors[] = lang('The address must start with https://.');
    } elseif ($scheme === 'http') {
        if (!$feed['allow_http']) {
            $errors[] = lang('An address without https needs "Allow an address without https" ticked.');
        } elseif (!in_array($auth, array('none', 'query'), true)) {
            $errors[] = lang('An address without https takes only a key in the address.');
        }
    }

    $ways = array(
        'basic'  => array('user' => lang('Enter the user name.'), 'password' => lang('Enter the password.')),
        'header' => array('header_name' => lang('Enter the header\'s name.'), 'key' => lang('Enter the key.')),
        'query'  => array('key_param' => lang('Enter the name the key goes under in the address.'), 'key' => lang('Enter the key.')),
    );

    if (isset($ways[$auth])) {
        foreach ($ways[$auth] as $name => $missing) {
            $secret[$name] = $value($name);

            if ($secret[$name] === '') {
                $errors[] = $missing;
            }
        }
    }

    if (($auth === 'header') && ($secret['header_name'] !== '') && !preg_match('/^[A-Za-z0-9-]{1,64}$/', $secret['header_name'])) {
        $errors[] = lang('A header\'s name has only letters, digits and dashes.');
    }

    if (($auth === 'query') && ($secret['key_param'] !== '') && (strlen($secret['key_param']) > 64)) {
        $errors[] = lang('The name the key goes under is longer than 64 characters.');
    }

    $feed['host'] = pg_supplier_feed_host($secret['address']);

    return array('feed' => $feed, 'secret' => $secret, 'errors' => $errors);
}

/**
 * The address and headers a pull asks with, from the saved secret.
 *
 * @param array $secret
 * @return array 'url', 'headers' (list of "Name: value")
 */
function pg_supplier_feed_request($secret)
{
    $secret = (array) $secret;
    $url = isset($secret['address']) ? (string) $secret['address'] : '';
    $auth = isset($secret['auth']) ? (string) $secret['auth'] : 'none';
    $headers = array();

    if ($auth === 'basic') {
        $headers[] = 'Authorization: Basic ' . base64_encode((string) ($secret['user'] ?? '') . ':' . (string) ($secret['password'] ?? ''));
    } elseif ($auth === 'header') {
        $headers[] = (string) ($secret['header_name'] ?? '') . ': ' . (string) ($secret['key'] ?? '');
    } elseif ($auth === 'query') {
        $hash = strpos($url, '#');

        if ($hash !== false) {
            $url = substr($url, 0, $hash);
        }

        $url .= ((strpos($url, '?') === false) ? '?' : '&') . rawurlencode((string) ($secret['key_param'] ?? '')) . '=' . rawurlencode((string) ($secret['key'] ?? ''));
    }

    return array('url' => $url, 'headers' => $headers);
}

/**
 * The secret as one encrypted blob ("<ciphertext>:<iv>").
 *
 * @param array $secret
 * @return string
 */
function pg_supplier_feed_secret_encode($secret)
{
    list($cipher, $iv) = encrypt_string_with_iv(json_encode((array) $secret));

    return $cipher . ':' . $iv;
}

/**
 * The saved secret, array() when there is none or it cannot be opened.
 *
 * @param string|null $stored
 * @return array
 */
function pg_supplier_feed_secret_decode($stored)
{
    $stored = (string) $stored;

    if (($stored === '') || (strpos($stored, ':') === false)) {
        return array();
    }

    list($cipher, $iv) = explode(':', $stored, 2);
    $json = decode_ssl_keys($cipher, $iv);
    $values = ($json === '') ? null : json_decode((string) $json, true);

    return is_array($values) ? $values : array();
}

/**
 * The feed's fields in the order met, each with its first value that is not
 * empty, cut to 80 letters: what the mapping's selects show.
 *
 * @param array $records flat records
 * @param int   $max     fields kept at most
 * @return array key => sample
 */
function pg_supplier_feed_sample($records, $max = 300)
{
    $sample = array();

    foreach ((array) $records as $record) {
        foreach ((array) $record as $key => $value) {
            $key = (string) $key;
            $value = trim((string) $value);

            if (!array_key_exists($key, $sample)) {
                if (count($sample) >= $max) {
                    continue;
                }

                $sample[$key] = '';
            }

            if (($sample[$key] === '') && ($value !== '')) {
                $sample[$key] = (mb_strlen($value, 'UTF-8') > 80) ? mb_substr($value, 0, 80, 'UTF-8') . '…' : $value;
            }
        }
    }

    return $sample;
}

/**
 * A stored mapping with every part present.
 *
 * @param string|null $json supplier_feeds.mapping
 * @return array
 */
function pg_supplier_feed_mapping_decode($json)
{
    $mapping = json_decode((string) $json, true);
    $mapping = is_array($mapping) ? $mapping : array();
    $fields = array();

    foreach (array_keys(pg_supplier_feed_field_labels()) as $field) {
        $fields[$field] = isset($mapping['fields'][$field]) ? (string) $mapping['fields'][$field] : '';
    }

    return array(
        'format'      => isset($mapping['format']) ? (string) $mapping['format'] : '',
        'record_path' => isset($mapping['record_path']) ? (string) $mapping['record_path'] : '',
        'paths'       => isset($mapping['paths']) ? array_values(array_map('strval', (array) $mapping['paths'])) : array(),
        'keys'        => isset($mapping['keys']) ? (array) $mapping['keys'] : array(),
        'fields'      => $fields,
        'confirmed'   => empty($mapping['confirmed']) ? 0 : 1,
        'records'     => isset($mapping['records']) ? (int) $mapping['records'] : 0,
        'read_at'     => isset($mapping['read_at']) ? (int) $mapping['read_at'] : 0,
        'draft_group' => isset($mapping['draft_group']) ? (int) $mapping['draft_group'] : 0,
    );
}

/**
 * The mapping form's choices: a feed field the feed does not have is
 * dropped; the required fields must be chosen.
 *
 * @param array $posted field => feed field
 * @param array $keys   the feed's fields (key => sample)
 * @return array 'fields', 'errors'
 */
function pg_supplier_feed_mapping_input($posted, $keys)
{
    $posted = (array) $posted;
    $fields = array();
    $errors = array();
    $labels = pg_supplier_feed_field_labels();

    foreach ($labels as $field => $label) {
        $key = isset($posted[$field]) ? (string) $posted[$field] : '';
        $fields[$field] = array_key_exists($key, (array) $keys) ? $key : '';

        if (($fields[$field] === '') && in_array($field, pg_supplier_feed_required_fields(), true)) {
            $errors[] = lang(array('string' => 'Choose the feed\'s field for {var:1}.', 'vars' => array($label)));
        }
    }

    return array('fields' => $fields, 'errors' => $errors);
}

/**
 * The group drafts go into, as Fields posted it: a browse product group that
 * is there, else 0 (none).
 *
 * @param mixed $posted
 * @return int
 */
function pg_supplier_feed_draft_group_input($posted)
{
    $id = (int) $posted;

    if ($id <= 0) {
        return 0;
    }

    return ((int) db_value("SELECT COUNT(*) FROM product_groups WHERE id = '" . $id . "' AND display_type = 'browse'") > 0) ? $id : 0;
}

/**
 * The state pill of a feed: never pulled, OK, Failed with the reason's
 * first words, Paused by a brake.
 *
 * @param array      $feed status, pause_reason
 * @param array|null $run  the last run (status, detail), null for none
 * @return array 'tone', 'label', 'note'
 */
function pg_supplier_feed_state($feed, $run)
{
    $first_words = function ($text) {
        $words = preg_split('/\s+/u', trim((string) $text), -1, PREG_SPLIT_NO_EMPTY);

        return (count($words) > 8) ? implode(' ', array_slice($words, 0, 8)) . '…' : implode(' ', $words);
    };

    if ((int) $feed['status'] === 2) {
        return array('tone' => 'amber', 'label' => lang('Paused'), 'note' => $first_words($feed['pause_reason']));
    }

    if ($run === null) {
        return array('tone' => 'gray', 'label' => lang('Never pulled'), 'note' => '');
    }

    if ((int) $run['status'] === 3) {
        return array('tone' => 'rose', 'label' => lang('Failed'), 'note' => $first_words(pg_supplier_feed_detail_reason($run['detail'])));
    }

    if ((int) $run['status'] === 0) {
        return array('tone' => 'blue', 'label' => lang('Pulling'), 'note' => '');
    }

    return array('tone' => 'teal', 'label' => lang('OK'), 'note' => '');
}

/**
 * What a pull of these records would do, nothing written (F3). A record is
 * matched by its code to a product's code, else by its barcode. Added: no
 * product. Closed: stock 0 on an open product, or a product this feed
 * follows missing from the records. Reopened: stock back on a product the
 * feed itself closed. Changed: a tracked product whose stock differs, or a
 * product following the feed's price whose selling price would differ.
 *
 * With $pricing each row carries the record's buy price (net of VAT when the
 * feed's prices include it) and the selling price the rule gives, with
 * whether the floor lifted it. A buy price that would be held (under half or
 * over ten times the last one) changes no price.
 *
 * @param array      $records    flat records
 * @param array      $fields     the mapping's fields
 * @param array      $by_code    lower-case product code => product id
 * @param array      $by_barcode barcode => product id
 * @param array      $products   id => id, name, enabled, inventory,
 *                               inventory_quantity, price, tax_rate
 * @param array      $items      this feed's matched items: product_id,
 *                               closed_by_feed, follow_price, buy_price
 * @param array|null $pricing    rules (the feed's rules with a margin, else
 *                               null), rate, vat_included; null for none
 * @return array added, changed, closed, reopened (each 'count', 'rows' of
 *               code, name, note, buy, price, floor), 'records', 'skipped'
 */
function pg_supplier_feed_test_diff($records, $fields, $by_code, $by_barcode, $products, $items, $pricing = null)
{
    $out = array('records' => 0, 'skipped' => 0);

    foreach (array('added', 'changed', 'closed', 'reopened') as $kind) {
        $out[$kind] = array('count' => 0, 'rows' => array());
    }

    $unpriced = array('buy' => null, 'price' => null, 'floor' => false);
    $add = function ($kind, $code, $name, $note, $priced) use (&$out) {
        $out[$kind]['count']++;

        if (count($out[$kind]['rows']) < PG_SUPPLIER_FEED_TEST_ROWS) {
            $out[$kind]['rows'][] = array('code' => (string) $code, 'name' => (string) $name, 'note' => (string) $note) + $priced;
        }
    };

    $closed_by_feed = array();
    $follow = array();
    $last_buy = array();

    foreach ((array) $items as $item) {
        $id = (int) $item['product_id'];
        $closed_by_feed[$id] = !empty($item['closed_by_feed']);
        $follow[$id] = !isset($item['follow_price']) || !empty($item['follow_price']);
        $last_buy[$id] = isset($item['buy_price']) ? (int) $item['buy_price'] : 0;
    }

    $field = function ($record, $name) use ($fields) {
        $key = isset($fields[$name]) ? (string) $fields[$name] : '';

        return (($key !== '') && isset($record[$key])) ? trim((string) $record[$key]) : '';
    };

    // The buy price net of VAT and the selling price the rule gives.
    $price_of = function ($record, $product) use ($pricing, $field, $unpriced) {
        $text = $field($record, 'buy_price');
        $buy = (($pricing === null) || ($text === '')) ? null : pg_supplier_feed_parse_amount($text, 2);

        if ($buy === null) {
            return $unpriced;
        }

        if (!empty($pricing['vat_included'])) {
            $vat = ($field($record, 'vat') !== '') ? pg_supplier_feed_parse_amount($field($record, 'vat'), 2) : null;
            $vat = ($vat !== null) ? $vat / 100.0 : ((($product !== null) && isset($product['tax_rate']) && ((string) $product['tax_rate'] !== '')) ? (float) $product['tax_rate'] : null);

            if ($vat !== null) {
                $buy = pg_supplier_feed_net_of_vat($buy, $vat);
            }
        }

        $row = array('buy' => (int) $buy, 'price' => null, 'floor' => false);

        if (is_array($pricing['rules'])) {
            $rule = pg_supplier_feed_rule_pick($pricing['rules'], $field($record, 'category'), $field($record, 'brand'));
            $parts = pg_supplier_feed_price_parts($buy, $rule, (float) $pricing['rate']);

            if ($parts['price'] > 0) {
                $row['price'] = $parts['price'];
                $row['floor'] = $parts['floor'];
            }
        }

        return $row;
    };

    $money = function ($minor) {
        return pg_format_number((int) $minor / 100, 2);
    };

    $seen = array();

    foreach ((array) $records as $record) {
        $out['records']++;
        $code = $field($record, 'supplier_key');

        if ($code === '') {
            $out['skipped']++;
            continue;
        }

        $name = $field($record, 'name');
        $stock = pg_supplier_feed_parse_stock($field($record, 'stock'));
        $lower = pg_supplier_feed_lower($code);
        $barcode = $field($record, 'barcode');
        $id = isset($by_code[$lower]) ? (int) $by_code[$lower] : ((($barcode !== '') && isset($by_barcode[$barcode])) ? (int) $by_barcode[$barcode] : 0);

        if (($id <= 0) || !isset($products[$id])) {
            $add('added', $code, $name, lang(array('string' => 'Stock {var:1}', 'vars' => array($stock))), $price_of($record, null));
            continue;
        }

        if (isset($seen[$id])) {
            continue;
        }

        $seen[$id] = true;
        $product = $products[$id];
        $tracked = !empty($product['inventory']);
        $quantity = (int) $product['inventory_quantity'];
        $open = $tracked ? ($quantity > 0) : !empty($product['enabled']);
        $name = ($name !== '') ? $name : (string) $product['name'];
        $priced = $price_of($record, $product);

        if (($priced['buy'] !== null) && pg_supplier_feed_price_held(isset($last_buy[$id]) ? $last_buy[$id] : 0, $priced['buy'])) {
            $priced['price'] = null;
            $priced['floor'] = false;
            $priced['held'] = true;
        }

        if ($stock === 0) {
            if ($open) {
                $add('closed', $product['name'], $name, lang('Stock 0 in the feed'), $priced);
            }
        } elseif (!$open && !empty($closed_by_feed[$id])) {
            $add('reopened', $product['name'], $name, lang(array('string' => 'Stock {var:1}', 'vars' => array($stock))), $priced);
        } else {
            $notes = array();

            if ($tracked && ($quantity !== $stock)) {
                $notes[] = lang(array('string' => 'Stock {var:1} → {var:2}', 'vars' => array($quantity, $stock)));
            }

            if (($priced['price'] !== null) && (!isset($follow[$id]) || $follow[$id]) && ($priced['price'] !== (int) $product['price'])) {
                $notes[] = lang(array('string' => 'Price {var:1} → {var:2}', 'vars' => array($money($product['price']), $money($priced['price']))));
            }

            if ($notes !== array()) {
                $add('changed', $product['name'], $name, implode(' · ', $notes), $priced);
            }
        }
    }

    foreach ($closed_by_feed as $id => $unused) {
        if (isset($seen[$id]) || !isset($products[$id])) {
            continue;
        }

        $product = $products[$id];
        $open = !empty($product['inventory']) ? ((int) $product['inventory_quantity'] > 0) : !empty($product['enabled']);

        if ($open) {
            $add('closed', $product['name'], $product['name'], lang('Not in the feed'), $unpriced);
        }
    }

    return $out;
}

/**
 * The rounding choices of the price rule, in the order the screen offers them.
 *
 * @return array value => label
 */
function pg_supplier_feed_roundings()
{
    return array(
        'none'  => lang('None'),
        'whole' => lang('Whole'),
        '90'    => ',90',
        '99'    => ',99',
    );
}

/**
 * A percent box's value: null when empty, false when it is not a number from
 * 0 to 1000; "12,5" and "%12.5" are read.
 *
 * @param string $text
 * @return float|null|false
 */
function pg_supplier_feed_percent_input($text)
{
    $text = str_replace(array('%', ' ', ','), array('', '', '.'), trim((string) $text));

    if ($text === '') {
        return null;
    }

    if (!is_numeric($text) || ((float) $text < 0) || ((float) $text > 1000)) {
        return false;
    }

    $value = (float) $text;

    return ($value === floor($value)) ? (int) $value : $value;
}

/**
 * The stored price rule for the form: margin and floor_margin '' when not
 * set, fixed in minor units, specific as rows of kind (category or brand),
 * name, margin ('' when not set) and fixed (null when not set).
 *
 * @param string|null $json supplier_feeds.rules
 * @return array margin, fixed, rounding, floor_margin, specific
 */
function pg_supplier_feed_rules_decode($json)
{
    $rules = json_decode((string) $json, true);
    $rules = is_array($rules) ? $rules : array();
    $rounding = isset($rules['rounding']) ? (string) $rules['rounding'] : 'none';
    $number = function ($value) {
        return is_numeric($value) ? $value + 0 : '';
    };
    $rows = array();

    foreach ((isset($rules['specific']) && is_array($rules['specific'])) ? $rules['specific'] : array() as $rule) {
        if (!is_array($rule)) {
            continue;
        }

        $kind = (isset($rule['brand']) && !isset($rule['category'])) ? 'brand' : 'category';
        $rows[] = array(
            'kind'   => $kind,
            'name'   => isset($rule[$kind]) ? (string) $rule[$kind] : '',
            'margin' => isset($rule['margin']) ? $number($rule['margin']) : '',
            'fixed'  => isset($rule['fixed']) ? (int) $rule['fixed'] : null,
        );
    }

    return array(
        'margin'       => isset($rules['margin']) ? $number($rules['margin']) : '',
        'fixed'        => isset($rules['fixed']) ? (int) $rules['fixed'] : 0,
        'rounding'     => array_key_exists($rounding, pg_supplier_feed_roundings()) ? $rounding : 'none',
        'floor_margin' => (isset($rules['floor_margin']) && ((float) $rules['floor_margin'] > 0)) ? $number($rules['floor_margin']) : '',
        'specific'     => $rows,
    );
}

/**
 * The price rule card's values, checked (F4). An empty margin saves no
 * margin, so the sync leaves prices alone and only stock follows. A rule row
 * names a category or a brand and sets a margin, a fixed amount or both;
 * what it leaves empty comes from the feed's own. A row left wholly empty is
 * dropped.
 *
 * @param array $post the posted form: rule_margin, rule_fixed, rule_rounding,
 *                    rule_floor, rule_kind[], rule_name[], rule_row_margin[],
 *                    rule_row_fixed[]
 * @return array 'rules' (to store as JSON), 'errors'
 */
function pg_supplier_feed_rules_input($post)
{
    $post = (array) $post;
    $errors = array();
    $text = function ($value) {
        return is_scalar($value) ? trim((string) $value) : '';
    };

    $rules = array();
    $margin = pg_supplier_feed_percent_input($text($post['rule_margin'] ?? ''));
    $floor = pg_supplier_feed_percent_input($text($post['rule_floor'] ?? ''));
    $fixed_text = $text($post['rule_fixed'] ?? '');
    $fixed = ($fixed_text === '') ? 0 : pg_supplier_feed_parse_amount($fixed_text, 2);
    $rounding = $text($post['rule_rounding'] ?? '');

    if ($margin === false) {
        $errors[] = lang('The margin must be a percent from 0 to 1000.');
    } elseif ($margin !== null) {
        $rules['margin'] = $margin;
    }

    if ($fixed === null) {
        $errors[] = lang('The fixed amount must be a number of 0 or more.');
    } else {
        $rules['fixed'] = (int) $fixed;
    }

    $rules['rounding'] = array_key_exists($rounding, pg_supplier_feed_roundings()) ? $rounding : 'none';

    if ($floor === false) {
        $errors[] = lang('The minimum margin must be a percent from 0 to 1000.');
    } else {
        $rules['floor_margin'] = ($floor === null) ? 0 : $floor;
    }

    $kinds = (array) ($post['rule_kind'] ?? array());
    $names = (array) ($post['rule_name'] ?? array());
    $margins = (array) ($post['rule_row_margin'] ?? array());
    $fixeds = (array) ($post['rule_row_fixed'] ?? array());
    $specific = array();
    $taken = array();

    foreach ($names as $index => $name) {
        $name = $text($name);
        $kind = ($text($kinds[$index] ?? '') === 'brand') ? 'brand' : 'category';
        $row_margin_text = $text($margins[$index] ?? '');
        $row_fixed_text = $text($fixeds[$index] ?? '');

        if (($name === '') && ($row_margin_text === '') && ($row_fixed_text === '')) {
            continue;
        }

        if ($name === '') {
            $errors[] = lang('Enter the category or brand of each rule.');
            continue;
        }

        $name = mb_substr($name, 0, 150, 'UTF-8');
        $row = array($kind => $name);
        $row_margin = pg_supplier_feed_percent_input($row_margin_text);
        $row_fixed = ($row_fixed_text === '') ? null : pg_supplier_feed_parse_amount($row_fixed_text, 2);

        if ($row_margin === false) {
            $errors[] = lang(array('string' => 'The margin of the rule for {var:1} must be a percent from 0 to 1000.', 'vars' => array($name)));
        } elseif ($row_margin !== null) {
            $row['margin'] = $row_margin;
        }

        if (($row_fixed_text !== '') && ($row_fixed === null)) {
            $errors[] = lang(array('string' => 'The fixed amount of the rule for {var:1} must be a number of 0 or more.', 'vars' => array($name)));
        } elseif ($row_fixed !== null) {
            $row['fixed'] = (int) $row_fixed;
        }

        if (($row_margin === null) && ($row_fixed_text === '')) {
            $errors[] = lang(array('string' => 'Enter a margin or a fixed amount for the rule for {var:1}.', 'vars' => array($name)));
            continue;
        }

        $key = $kind . ':' . pg_supplier_feed_lower($name);

        if (isset($taken[$key])) {
            $errors[] = lang(array('string' => 'There are two rules for {var:1}.', 'vars' => array($name)));
            continue;
        }

        $taken[$key] = true;
        $specific[] = $row;
    }

    if ($specific !== array()) {
        $rules['specific'] = $specific;
    }

    return array('rules' => $rules, 'errors' => $errors);
}

/**
 * An amount in minor units as a box shows it, '' for none.
 *
 * @param int|null $minor
 * @return string
 */
function pg_supplier_feed_amount_text($minor)
{
    return (($minor === null) || ((int) $minor === 0)) ? '' : pg_format_number((int) $minor / 100, 2);
}

/**
 * The price rule under card 3's fields (F4): margin, fixed amount, rounding
 * as segments, minimum margin, the formula line, then "Rules by category or
 * brand" as rows with "Add a rule" and each row's remove.
 *
 * @param array  $rules         from pg_supplier_feed_rules_decode()
 * @param string $currency_code the feed's currency, for the fixed amounts
 * @return string HTML
 */
function pg_supplier_feed_rule_fields_html($rules, $currency_code)
{
    $currency = h($currency_code);
    $segments = '';

    foreach (pg_supplier_feed_roundings() as $value => $label) {
        $segments .= '<button type="button" class="pg-seg-item" data-pg-sf-round="' . h($value) . '" aria-pressed="' . (($rules['rounding'] === (string) $value) ? 'true' : 'false') . '">' . h($label) . '</button>';
    }

    $rows = '';

    foreach ($rules['specific'] as $row) {
        $rows .= pg_supplier_feed_rule_row_html($row, $currency);
    }

    return '
                                <h3 class="pg-title-status mt-7 mb-3">' . h(lang('Price rule')) . '</h3>
                                <div class="row gy-5" data-pg-sf-rule>
                                    <div class="col-12 md:col-6">
                                        <label class="pg-field-label" for="sf_rule_margin">' . h(lang('Margin %')) . '</label>
                                        <input type="text" inputmode="decimal" class="pg-input" id="sf_rule_margin" name="rule_margin" value="' . h((string) $rules['margin']) . '" />
                                    </div>
                                    <div class="col-12 md:col-6">
                                        <label class="pg-field-label" for="sf_rule_fixed">' . h(lang('Fixed amount')) . ' <span class="pg-text-meta">' . $currency . '</span></label>
                                        <input type="text" inputmode="decimal" class="pg-input" id="sf_rule_fixed" name="rule_fixed" value="' . h(pg_supplier_feed_amount_text($rules['fixed'])) . '" />
                                    </div>
                                    <div class="col-12 md:col-6">
                                        <span class="pg-field-label d-block" id="sf_rule_rounding_label">' . h(lang('Rounding')) . '</span>
                                        <input type="hidden" name="rule_rounding" id="sf_rule_rounding" value="' . h($rules['rounding']) . '" />
                                        <span class="pg-seg pg-seg-wrap" role="group" aria-labelledby="sf_rule_rounding_label">' . $segments . '</span>
                                    </div>
                                    <div class="col-12 md:col-6">
                                        <label class="pg-field-label" for="sf_rule_floor">' . h(lang('Minimum margin %')) . '</label>
                                        <input type="text" inputmode="decimal" class="pg-input" id="sf_rule_floor" name="rule_floor" value="' . h((string) $rules['floor_margin']) . '" />
                                    </div>
                                    <div class="col-12 pg-field-help">' . h(lang('Buy price x (1 + margin) + fixed amount; never under buy price plus the minimum margin.')) . '</div>
                                </div>
                                <h3 class="pg-title-status mt-7 mb-1">' . h(lang('Rules by category or brand')) . '</h3>
                                <p class="pg-field-help">' . h(lang('The most specific rule wins: a category\'s rule before a brand\'s, else the rule above.')) . '</p>
                                <div data-pg-sf-rules>' . $rows . '</div>
                                <template data-pg-sf-rule-template>' . pg_supplier_feed_rule_row_html(array('kind' => 'category', 'name' => '', 'margin' => '', 'fixed' => null), $currency) . '</template>
                                <button type="button" class="pg-button pg-button-quiet pg-button-sm mt-3" data-pg-sf-rule-add><i class="bi bi-plus-lg" aria-hidden="true"></i>' . h(lang('Add a rule')) . '</button>';
}

/**
 * One row of "Rules by category or brand": kind, name, margin, fixed amount
 * and its remove button.
 *
 * @param array  $row      kind, name, margin, fixed
 * @param string $currency escaped currency code
 * @return string HTML
 */
function pg_supplier_feed_rule_row_html($row, $currency)
{
    $kinds = '';

    foreach (array('category' => lang('Category'), 'brand' => lang('Brand')) as $value => $label) {
        $kinds .= '<option value="' . $value . '"' . (($row['kind'] === $value) ? ' selected' : '') . '>' . h($label) . '</option>';
    }

    return '<div class="pg-list-row is-read" data-pg-sf-rule-row><div class="pg-list-row-main"><div class="row gy-2 align-items-center">'
        . '<div class="col-12 md:col-3"><select class="pg-input" name="rule_kind[]" aria-label="' . h(lang('Kind')) . '">' . $kinds . '</select></div>'
        . '<div class="col-12 md:col-4"><input type="text" class="pg-input" name="rule_name[]" maxlength="150" value="' . h($row['name']) . '" aria-label="' . h(lang('Category or brand')) . '" placeholder="' . h(lang('Category or brand')) . '" /></div>'
        . '<div class="col-6 md:col-2"><input type="text" inputmode="decimal" class="pg-input" name="rule_row_margin[]" value="' . h((string) $row['margin']) . '" aria-label="' . h(lang('Margin %')) . '" placeholder="' . h(lang('Margin %')) . '" /></div>'
        . '<div class="col-6 md:col-3"><input type="text" inputmode="decimal" class="pg-input" name="rule_row_fixed[]" value="' . h(pg_supplier_feed_amount_text($row['fixed'])) . '" aria-label="' . h(lang('Fixed amount')) . ' ' . $currency . '" placeholder="' . h(lang('Fixed amount')) . '" /></div>'
        . '</div></div><div class="pg-list-row-side"><button type="button" class="pg-icon-button" data-pg-sf-rule-remove aria-label="' . h(lang('Remove the rule')) . '"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div></div>';
}

/**
 * The Pull now button of a feed whose fields are saved and that is on or
 * paused, '' otherwise. A paused feed's button asks first: its pull goes
 * past the mass-drop brake.
 *
 * @param array  $feed    id, status, pause_reason
 * @param array  $mapping from pg_supplier_feed_mapping_decode()
 * @param string $class   the button's classes
 * @return string HTML
 */
function pg_supplier_feed_pull_button($feed, $mapping, $class)
{
    if (empty($mapping['confirmed']) || !in_array((int) $feed['status'], array(1, 2), true)) {
        return '';
    }

    $confirm = ((int) $feed['status'] === 2)
        ? ' data-confirm-content="' . h(lang(array('string' => 'The feed was paused: {var:1} Pull now writes what the feed holds, even when it closes many products.', 'vars' => array(rtrim((string) $feed['pause_reason'], '.') . '.')))) . '"'
        : '';

    return '<button type="submit" name="pull_now" value="' . (int) $feed['id'] . '" formnovalidate class="' . h($class) . '"' . $confirm . '>' . h(lang('Pull now')) . '</button>';
}

/**
 * A minor-unit amount with its currency code, for a row.
 *
 * @param int    $minor
 * @param string $code
 * @return string
 */
function pg_supplier_feed_money_text($minor, $code)
{
    return trim(pg_format_number((int) $minor / 100, 2) . ' ' . $code);
}

/**
 * The test pull's drawer: four figures, under each its first rows with the
 * buy price and the selling price the rule gives ("Floor" where the floor
 * lifted it); one line says nothing was written. Foot: Close, Pull now.
 * Opened as the page loads.
 *
 * @param array  $test      from pg_supplier_feed_test_pull()
 * @param string $pull_button Pull now (pg_supplier_feed_pull_button()), '' for none
 * @param string $feed_code the feed's currency code
 * @param string $home_code the store's currency code
 * @return string HTML
 */
function pg_supplier_feed_test_drawer_html($test, $pull_button, $feed_code, $home_code)
{
    $diff = $test['diff'];
    $figures = '';
    $rows = '';

    foreach (array('added' => lang('Would be added'), 'changed' => lang('Would be changed'), 'closed' => lang('Would be closed'), 'reopened' => lang('Would be reopened')) as $kind => $label) {
        $figures .= '<div class="pg-card pg-figure"><div class="pg-figure-label">' . h($label) . '</div><div class="pg-figure-value">' . h(pg_format_number($diff[$kind]['count'], 0)) . '</div></div>';

        if ($diff[$kind]['rows'] === array()) {
            continue;
        }

        $rows .= '<h3 class="pg-title-status mt-5">' . h($label) . '</h3><div>';

        foreach ($diff[$kind]['rows'] as $row) {
            $prices = array();

            if (isset($row['buy']) && ($row['buy'] !== null)) {
                $prices[] = h(lang(array('string' => 'Buy {var:1}', 'vars' => array(pg_supplier_feed_money_text($row['buy'], $feed_code)))));
            }

            if (isset($row['price']) && ($row['price'] !== null)) {
                $prices[] = h(lang(array('string' => 'Selling {var:1}', 'vars' => array(pg_supplier_feed_money_text($row['price'], $home_code)))));
            } elseif (!empty($row['held'])) {
                $prices[] = h(lang('Held: the price stays'));
            }

            $rows .= '<div class="pg-list-row is-read" data-pg-sf-test-row><div class="pg-list-row-main"><div class="pg-list-row-head"><span class="pg-list-row-title">' . h($row['code']) . '</span>'
                . (!empty($row['floor']) ? '<span class="pg-pill pg-tone-amber" data-pg-sf-floor>' . h(lang('Floor')) . '</span>' : '') . '</div>'
                . '<div class="pg-list-row-text">' . h(trim($row['name'] . ' · ' . $row['note'], ' ·')) . '</div>'
                . (($prices !== array()) ? '<div class="pg-list-row-text pg-tabular" data-pg-sf-prices>' . implode(' · ', $prices) . '</div>' : '')
                . '</div></div>';
        }

        $rows .= '</div>';

        if ($diff[$kind]['count'] > count($diff[$kind]['rows'])) {
            $rows .= '<p class="pg-text-meta">' . h(lang(array('string' => 'The first {var:1} of {var:2}.', 'vars' => array(count($diff[$kind]['rows']), $diff[$kind]['count'])))) . '</p>';
        }
    }

    $line = lang(array('string' => '{var:1} records read. Nothing was written.', 'vars' => array(pg_format_number($diff['records'], 0))))
        . ($test['cut'] ? ' ' . lang(array('string' => 'Only the first {var:1} records were read.', 'vars' => array(pg_format_number(PG_SUPPLIER_FEED_TEST_RECORDS, 0)))) : '');

    return '
                <dialog class="drawer drawer-end" id="sf_test_pull" aria-labelledby="sf_test_pull_title" data-pg-sf-open-at-load style="width:min(640px,100%)">
                    <div class="drawer-header d-flex align-items-start gap-3">
                        <div class="flex-grow-1 min-w-0"><h5 class="drawer-title mb-0" id="sf_test_pull_title">' . h(lang('Test pull')) . '</h5>
                        <p class="small fg-2 mb-0 mt-1">' . h($line) . '</p></div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="drawer" aria-label="' . h(lang('Close')) . '"></button>
                    </div>
                    <div class="drawer-body"><div class="pg-figures">' . $figures . '</div>' . $rows . '</div>
                    <div class="drawer-footer border-top p-5 d-flex align-items-center gap-3"><span class="flex-grow-1"></span>
                        <button type="button" class="pg-button pg-button-quiet" data-bs-dismiss="drawer">' . h(lang('Close')) . '</button>'
                        . $pull_button . '
                    </div>
                </dialog>';
}

/**
 * The held prices card: each item whose buy price waits for a person, with
 * the last and the held price, Accept and Reject; "Accept all" at its head.
 * The first PG_SUPPLIER_FEED_HELD_ROWS rows show; the rest are in the
 * "Show all" drawer. '' when none waits.
 *
 * @param array  $items     id, supplier_key, buy_price, held_price, product name
 * @param string $feed_code the feed's currency code
 * @return string HTML
 */
function pg_supplier_feed_held_html($items, $feed_code)
{
    if ($items === array()) {
        return '';
    }

    $row = function ($item) use ($feed_code) {
        $title = ((string) $item['product_name'] !== '') ? $item['product_name'] : $item['supplier_key'];

        return '<div class="pg-list-row is-read" data-pg-sf-held-row><div class="pg-list-row-main">'
            . '<div class="pg-list-row-head"><span class="pg-list-row-title">' . h($title) . '</span></div>'
            . '<div class="pg-list-row-text pg-tabular">' . h(lang(array('string' => 'Buy price {var:1} → {var:2}', 'vars' => array(pg_supplier_feed_money_text($item['buy_price'], $feed_code), pg_supplier_feed_money_text($item['held_price'], $feed_code))))) . '</div>'
            . '<div class="pg-list-row-actions"><button type="submit" name="held_accept" value="' . (int) $item['id'] . '" formnovalidate class="pg-button pg-button-quiet pg-button-sm">' . h(lang('Accept')) . '</button>'
            . '<button type="submit" name="held_reject" value="' . (int) $item['id'] . '" formnovalidate class="pg-button pg-button-quiet pg-button-sm">' . h(lang('Reject')) . '</button></div>'
            . '</div></div>';
    };

    $rows = '';
    $all = '';

    foreach (array_values($items) as $index => $item) {
        if ($index < PG_SUPPLIER_FEED_HELD_ROWS) {
            $rows .= $row($item);
        }

        $all .= $row($item);
    }

    $more = '';

    if (count($items) > PG_SUPPLIER_FEED_HELD_ROWS) {
        $more = '
                                <button type="button" class="pg-button pg-button-quiet pg-button-sm mt-3" data-bs-toggle="drawer" data-bs-target="#sf_held_all" aria-controls="sf_held_all">' . h(lang(array('string' => 'Show all {var:1}', 'vars' => array(count($items))))) . '</button>
                                <dialog class="drawer drawer-end" id="sf_held_all" aria-labelledby="sf_held_all_title" style="width:min(640px,100%)">
                                    <div class="drawer-header d-flex align-items-start gap-3">
                                        <div class="flex-grow-1 min-w-0"><h5 class="drawer-title mb-0" id="sf_held_all_title">' . h(lang('Held prices')) . '</h5></div>
                                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="drawer" aria-label="' . h(lang('Close')) . '"></button>
                                    </div>
                                    <div class="drawer-body">' . $all . '</div>
                                    <div class="drawer-footer border-top p-5 d-flex align-items-center gap-3"><span class="flex-grow-1"></span>
                                        <button type="button" class="pg-button pg-button-quiet" data-bs-dismiss="drawer">' . h(lang('Close')) . '</button>
                                    </div>
                                </dialog>';
    }

    return '
                        <div class="card mb-7" id="sf_held">
                            <div class="card-header bg-reset border-0 d-flex align-items-center gap-3">
                                <h2 class="pg-title-section mb-0 flex-grow-1">' . h(lang('Held prices')) . '</h2>
                                <button type="submit" name="held_accept_all" value="1" formnovalidate class="pg-button pg-button-quiet pg-button-sm">' . h(lang('Accept all')) . '</button>
                            </div>
                            <div class="card-body pt-0">
                                <p class="pg-card-sub">' . h(lang('These buy prices fell under half or rose over ten times the last one. The old price stays until you accept or reject.')) . '</p>
                                <div>' . $rows . '</div>' . $more . '
                            </div>
                        </div>';
}

/**
 * The pull history drawer: the feed's last runs, each with its state, kind,
 * counts, the brake's or failure's reason and its first failed lines.
 *
 * @param array $runs kind, status, started_at, records, changed, closed,
 *                    reopened, held, failed, detail
 * @return string HTML
 */
function pg_supplier_feed_runs_drawer_html($runs)
{
    $states = array(
        0 => array('blue', lang('Pulling')),
        1 => array('teal', lang('OK')),
        2 => array('amber', lang('Stopped')),
        3 => array('rose', lang('Failed')),
    );
    $rows = '';

    foreach ($runs as $run) {
        $state = isset($states[(int) $run['status']]) ? $states[(int) $run['status']] : $states[3];
        $counts = lang(array('string' => '{var:1} records · {var:2} changed · {var:3} closed · {var:4} reopened · {var:5} held · {var:6} failed', 'vars' => array(
            pg_format_number($run['records'], 0), (int) $run['changed'], (int) $run['closed'], (int) $run['reopened'], (int) $run['held'], (int) $run['failed'],
        )));
        $detail = json_decode((string) $run['detail'], true);
        $reason = pg_supplier_feed_detail_reason($run['detail']);
        $lines = '';

        foreach (array_slice((is_array($detail) && isset($detail['failed']) && is_array($detail['failed'])) ? $detail['failed'] : array(), 0, 5) as $line) {
            $lines .= '<div class="pg-gather-line"><b>' . h((string) ($line['line'] ?? '')) . '</b><span>' . h((string) ($line['reason'] ?? '')) . '</span></div>';
        }

        $rows .= '<div class="pg-list-row is-read" data-pg-sf-run><div class="pg-list-row-main">'
            . '<div class="pg-list-row-head"><span class="pg-list-row-title">' . h(get_absolute_time(array('timestamp' => (int) $run['started_at'], 'format' => 'plain_text'))) . '</span>'
            . '<span class="pg-pill pg-tone-' . $state[0] . '">' . h($state[1]) . '</span></div>'
            . '<div class="pg-list-row-text">' . h(((int) $run['kind'] === 1) ? lang('By hand') : lang('Scheduled')) . ' · ' . h($counts) . '</div>'
            . (($reason !== '') ? '<div class="pg-list-row-text">' . h($reason) . '</div>' : '')
            . (($lines !== '') ? '<div class="pg-gather">' . $lines . '</div>' : '')
            . '</div></div>';
    }

    if ($rows === '') {
        $rows = '<p class="pg-text-meta">' . h(lang('The feed was not pulled yet.')) . '</p>';
    }

    return '
                <dialog class="drawer drawer-end" id="sf_runs" aria-labelledby="sf_runs_title" style="width:min(640px,100%)">
                    <div class="drawer-header d-flex align-items-start gap-3">
                        <div class="flex-grow-1 min-w-0"><h5 class="drawer-title mb-0" id="sf_runs_title">' . h(lang('Pull history')) . '</h5>
                        <p class="small fg-2 mb-0 mt-1">' . h(lang(array('string' => 'The pulls of the last {var:1} days, newest first.', 'vars' => array(90)))) . '</p></div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="drawer" aria-label="' . h(lang('Close')) . '"></button>
                    </div>
                    <div class="drawer-body">' . $rows . '</div>
                    <div class="drawer-footer border-top p-5 d-flex align-items-center gap-3"><span class="flex-grow-1"></span>
                        <button type="button" class="pg-button pg-button-quiet" data-bs-dismiss="drawer">' . h(lang('Close')) . '</button>
                    </div>
                </dialog>';
}

/**
 * A text for a reader's or a download's error code.
 *
 * @param string $error
 * @return string
 */
function pg_supplier_feed_error_text($error)
{
    $texts = array(
        'open'       => lang('The downloaded feed could not be opened.'),
        'parse'      => lang('The feed is cut short or broken and could not be read to its end.'),
        'format'     => lang('The feed is not XML, CSV or JSON.'),
        'too_large'  => lang('The feed is too large to read.'),
        'no_records' => lang('No records were found in the feed.'),
    );

    return isset($texts[$error]) ? $texts[$error] : (string) $error;
}

// ---------------------------------------------------------------------------
// Database and network
// ---------------------------------------------------------------------------

/**
 * One feed, null when there is none.
 *
 * @param int $id
 * @return array|null
 */
function pg_supplier_feed_get($id)
{
    $feed = db_item("SELECT * FROM supplier_feeds WHERE id = '" . (int) $id . "' LIMIT 1");

    return is_array($feed) ? $feed : null;
}

/**
 * Every feed with its last run and the products it matched, by name.
 *
 * @return array
 */
function pg_supplier_feed_list()
{
    $feeds = (array) db_items(
        "SELECT
            supplier_feeds.*,
            (SELECT COUNT(DISTINCT supplier_feed_items.product_id) FROM supplier_feed_items
                WHERE supplier_feed_items.feed_id = supplier_feeds.id AND supplier_feed_items.product_id > 0) AS matched
        FROM supplier_feeds
        ORDER BY supplier_feeds.name, supplier_feeds.id");

    foreach ($feeds as $index => $feed) {
        $feeds[$index]['run'] = pg_supplier_feed_last_run($feed['id']);
    }

    return $feeds;
}

/**
 * The feed's last run, null when it was never pulled.
 *
 * @param int $id
 * @return array|null
 */
function pg_supplier_feed_last_run($id)
{
    $run = db_item("SELECT status, started_at, ended_at, detail FROM supplier_feed_runs WHERE feed_id = '" . (int) $id . "' ORDER BY started_at DESC, id DESC LIMIT 1");

    return is_array($run) ? $run : null;
}

/**
 * The products a feed matched, from its items.
 *
 * @param int $id
 * @return int
 */
function pg_supplier_feed_matched_count($id)
{
    return (int) db_value("SELECT COUNT(DISTINCT product_id) FROM supplier_feed_items WHERE feed_id = '" . (int) $id . "' AND product_id > 0");
}

/**
 * The ERP accounts a feed may name as its supplier, with Accounting on.
 *
 * @return array id => title
 */
function pg_supplier_feed_erp_accounts()
{
    if (!defined('ERP_ENABLED') || !ERP_ENABLED || !pg_schema_has('erp_accounts')) {
        return array();
    }

    $accounts = array();

    foreach ((array) db_items("SELECT id, title FROM erp_accounts WHERE kind IN ('supplier', 'both') AND status = 'active' ORDER BY title, id") as $account) {
        $accounts[(int) $account['id']] = (string) $account['title'];
    }

    return $accounts;
}

/**
 * Saves a feed's columns; a new one is added off, with its creator.
 *
 * @param int   $id      0 for a new feed
 * @param array $columns column => value
 * @param int   $user_id
 * @return int the feed's id, 0 when the write failed
 */
function pg_supplier_feed_save($id, $columns, $user_id)
{
    $columns['updated_at'] = time();

    if ((int) $id <= 0) {
        $columns['created_at'] = $columns['updated_at'];
        $columns['created_by'] = (int) $user_id;
    }

    $set = array();

    foreach ($columns as $column => $value) {
        $set[] = $column . ' = ' . (($value === null) ? 'NULL' : "'" . escape((string) $value) . "'");
    }

    if ((int) $id > 0) {
        return (db("UPDATE supplier_feeds SET " . implode(', ', $set) . " WHERE id = '" . (int) $id . "'") === false) ? 0 : (int) $id;
    }

    return (db("INSERT INTO supplier_feeds SET " . implode(', ', $set)) === false) ? 0 : (int) mysqli_insert_id(db::$con);
}

/**
 * Deletes a feed with its items and runs. Its products stay as they are and
 * no longer follow it.
 *
 * @param int $id
 * @return void
 */
function pg_supplier_feed_delete($id)
{
    db("DELETE FROM supplier_feed_items WHERE feed_id = '" . (int) $id . "'");
    db("DELETE FROM supplier_feed_runs WHERE feed_id = '" . (int) $id . "'");
    db("DELETE FROM supplier_feeds WHERE id = '" . (int) $id . "'");

    foreach (array(pg_supplier_feed_temp_file($id), PG_FUNCTIONS_DIR . '/data/temp/supplier_feed_' . (int) $id . '_pull.dat') as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }

    if (function_exists('pg_supplier_feed_spool_clear')) {
        pg_supplier_feed_spool_clear($id);
    }
}

/**
 * Where a feed is downloaded to while it is read.
 *
 * @param int $id
 * @return string
 */
function pg_supplier_feed_temp_file($id)
{
    return PG_FUNCTIONS_DIR . '/data/temp/supplier_feed_' . (int) $id . '.dat';
}

/**
 * Downloads a feed into its file under data/temp/, never past
 * PG_SUPPLIER_FEED_MAX_BYTES.
 *
 * @param int   $id
 * @param array $secret
 * @param bool  $allow_http
 * @return array 'ok', 'error' (text), 'file'
 */
function pg_supplier_feed_download($id, $secret, $allow_http)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php');

    $file = pg_supplier_feed_temp_file($id);
    $fail = function ($error) use ($file) {
        if (is_file($file)) {
            @unlink($file);
        }

        return array('ok' => false, 'error' => $error, 'file' => '');
    };

    if (!pg_dir_writable(dirname($file))) {
        return $fail(lang('The server could not write a temporary file.'));
    }

    $handle = @fopen($file, 'wb');

    if ($handle === false) {
        return $fail(lang('The server could not write a temporary file.'));
    }

    $request = pg_supplier_feed_request($secret);
    $written = 0;
    $over = false;

    $result = api_http_request('GET', $request['url'], array(
        'headers'    => $request['headers'],
        'timeout'    => 120,
        'max_body'   => 2000,
        'agent'      => 'supplier-feed',
        'allow_http' => (bool) $allow_http,
        'decode'     => true,
        'write'      => function ($chunk) use ($handle, &$written, &$over) {
            $written += strlen($chunk);

            if ($written > PG_SUPPLIER_FEED_MAX_BYTES) {
                $over = true;

                return false;
            }

            return fwrite($handle, $chunk) !== false;
        },
    ));

    fclose($handle);

    if ($over) {
        return $fail(lang('The feed is larger than 200 MB.'));
    }

    if (!$result['ok']) {
        $status = (int) $result['status'];

        return $fail(($status > 0)
            ? lang(array('string' => 'The supplier answered with status {var:1}.', 'vars' => array($status)))
            : lang(array('string' => 'The feed could not be downloaded: {var:1}', 'vars' => array((string) $result['error']))));
    }

    if ($written === 0) {
        return $fail(lang('The supplier sent an empty answer.'));
    }

    return array('ok' => true, 'error' => '', 'file' => $file);
}

/**
 * The first read (F2): downloads the feed, tells its format and where its
 * records sit, reads up to PG_SUPPLIER_FEED_SAMPLE_RECORDS records and
 * proposes the fields. Fields a person saved before are kept where the feed
 * still has them; the rest are proposed.
 *
 * @param array       $feed        the saved row
 * @param string|null $record_path a place the person chose, null to find it
 * @return array 'ok', 'error', 'mapping'
 */
function pg_supplier_feed_first_read($feed, $record_path = null)
{
    $download = pg_supplier_feed_download($feed['id'], pg_supplier_feed_secret_decode($feed['secret']), !empty($feed['allow_http']));

    if (!$download['ok']) {
        return array('ok' => false, 'error' => $download['error'], 'mapping' => null);
    }

    $file = $download['file'];
    $format = ((string) $feed['format'] !== '') ? (string) $feed['format'] : pg_supplier_feed_format_detect_file($file);
    $paths = pg_supplier_feed_record_paths($file, $format);
    $path = (($record_path !== null) && in_array($record_path, $paths, true)) ? $record_path : (($paths !== array()) ? $paths[0] : '');
    $read = pg_supplier_feed_read($file, $format, $path, array(), PG_SUPPLIER_FEED_SAMPLE_RECORDS);

    @unlink($file);

    if ($read['records'] === array()) {
        return array('ok' => false, 'error' => pg_supplier_feed_error_text(($read['error'] !== '') ? $read['error'] : 'no_records'), 'mapping' => null);
    }

    $old = pg_supplier_feed_mapping_decode($feed['mapping']);
    $keys = pg_supplier_feed_sample($read['records']);
    $fields = pg_supplier_feed_propose_fields($read['records']);

    foreach ($old['fields'] as $field => $key) {
        if ($old['confirmed'] && ($key !== '') && array_key_exists($key, $keys)) {
            $fields[$field] = $key;
        }
    }

    return array('ok' => true, 'error' => '', 'mapping' => array(
        'format'      => $format,
        'record_path' => $path,
        'paths'       => $paths,
        'keys'        => $keys,
        'fields'      => $fields,
        'confirmed'   => 0,
        'records'     => count($read['records']),
        'read_at'     => time(),
        'draft_group' => $old['draft_group'],
    ));
}

/**
 * A test pull (F3): downloads and reads the feed, then says what a pull
 * would add, change, close and reopen. Nothing is written.
 *
 * @param array $feed the saved row, with a confirmed mapping
 * @return array 'ok', 'error', 'diff', 'cut' (more records than were read)
 */
function pg_supplier_feed_test_pull($feed)
{
    $mapping = pg_supplier_feed_mapping_decode($feed['mapping']);
    $download = pg_supplier_feed_download($feed['id'], pg_supplier_feed_secret_decode($feed['secret']), !empty($feed['allow_http']));

    if (!$download['ok']) {
        return array('ok' => false, 'error' => $download['error'], 'diff' => null, 'cut' => false);
    }

    $records = array();
    $cursor = array();
    $error = '';

    do {
        $read = pg_supplier_feed_read($download['file'], $mapping['format'], $mapping['record_path'], $cursor, 1000);
        $error = $read['error'];
        $records = array_merge($records, $read['records']);
        $cursor = $read['cursor'];
    } while (($error === '') && ($cursor !== null) && (count($records) < PG_SUPPLIER_FEED_TEST_RECORDS));

    @unlink($download['file']);

    if (($error !== '') || ($records === array())) {
        return array('ok' => false, 'error' => pg_supplier_feed_error_text(($error !== '') ? $error : 'no_records'), 'diff' => null, 'cut' => false);
    }

    $codes = array();
    $barcodes = array();

    foreach ($records as $record) {
        foreach (array('supplier_key' => &$codes, 'barcode' => &$barcodes) as $field => &$list) {
            $key = $mapping['fields'][$field];

            if (($key !== '') && isset($record[$key]) && (trim((string) $record[$key]) !== '')) {
                $list[trim((string) $record[$key])] = true;
            }
        }

        unset($list);
    }

    $products = array();
    $by_code = array();
    $by_barcode = array();
    $columns = "products.id, products.name, products.enabled, products.inventory, products.inventory_quantity, products.price, products.tax_rate";

    foreach (array_chunk(array_keys($codes), 500) as $chunk) {
        foreach ((array) db_items("SELECT " . $columns . " FROM products WHERE products.name IN ('" . implode("', '", array_map('escape', $chunk)) . "')") as $product) {
            $products[(int) $product['id']] = $product;
            $by_code[pg_supplier_feed_lower($product['name'])] = (int) $product['id'];
        }
    }

    foreach (array_chunk(array_keys($barcodes), 500) as $chunk) {
        foreach ((array) db_items("SELECT product_barcodes.barcode, " . $columns . " FROM product_barcodes INNER JOIN products ON products.id = product_barcodes.product_id WHERE product_barcodes.barcode IN ('" . implode("', '", array_map('escape', $chunk)) . "')") as $product) {
            $products[(int) $product['id']] = $product;
            $by_barcode[(string) $product['barcode']] = (int) $product['id'];
        }
    }

    $items = (array) db_items("SELECT supplier_feed_items.product_id, supplier_feed_items.closed_by_feed, supplier_feed_items.follow_price, supplier_feed_items.buy_price, " . $columns . "
        FROM supplier_feed_items INNER JOIN products ON products.id = supplier_feed_items.product_id
        WHERE supplier_feed_items.feed_id = '" . (int) $feed['id'] . "' AND supplier_feed_items.product_id > 0 AND supplier_feed_items.active = 1");

    foreach ($items as $item) {
        $products[(int) $item['product_id']] = $item;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/supplier_feed_sync.php');
    $context = pg_supplier_feed_context($feed);

    return array(
        'ok'    => true,
        'error' => '',
        'diff'  => pg_supplier_feed_test_diff($records, $mapping['fields'], $by_code, $by_barcode, $products, $items, array('rules' => $context['rules'], 'rate' => $context['rate'], 'vat_included' => $context['vat_included'])),
        'cut'   => ($cursor !== null),
    );
}

/**
 * The items of a feed whose buy price is held, with their product's name.
 *
 * @param int $feed_id
 * @return array
 */
function pg_supplier_feed_held_items($feed_id)
{
    return (array) db_items(
        "SELECT supplier_feed_items.id, supplier_feed_items.supplier_key, supplier_feed_items.buy_price, supplier_feed_items.held_price,
            COALESCE(products.name, '') AS product_name
        FROM supplier_feed_items LEFT JOIN products ON products.id = supplier_feed_items.product_id
        WHERE supplier_feed_items.feed_id = '" . (int) $feed_id . "' AND supplier_feed_items.held_price > 0
        ORDER BY supplier_feed_items.changed_at DESC, supplier_feed_items.id
        LIMIT 500");
}

/**
 * Accept or Reject of a held buy price. Accept keeps it as the item's buy
 * price; the selling price follows at the next pull, which reads it in range.
 * Reject drops it; a pull with the same price holds it again.
 *
 * @param int  $feed_id
 * @param int  $item_id
 * @param bool $accept
 * @return bool whether a held price was there
 */
function pg_supplier_feed_held_decide($feed_id, $item_id, $accept)
{
    $where = " WHERE id = '" . (int) $item_id . "' AND feed_id = '" . (int) $feed_id . "' AND held_price > 0";

    if ((int) db_value("SELECT COUNT(*) FROM supplier_feed_items" . $where) === 0) {
        return false;
    }

    db("UPDATE supplier_feed_items SET " . ($accept ? "buy_price = held_price, " : "") . "held_price = 0, changed_at = '" . time() . "'" . $where);

    return true;
}

/**
 * Accept all: every held buy price of the feed becomes its buy price.
 *
 * @param int $feed_id
 * @return int the prices accepted
 */
function pg_supplier_feed_held_accept_all($feed_id)
{
    if (db("UPDATE supplier_feed_items SET buy_price = held_price, held_price = 0, changed_at = '" . time() . "'
        WHERE feed_id = '" . (int) $feed_id . "' AND held_price > 0") === false) {
        return 0;
    }

    return (int) mysqli_affected_rows(db::$con);
}

/**
 * The feed's last runs, newest first.
 *
 * @param int $feed_id
 * @param int $limit
 * @return array
 */
function pg_supplier_feed_runs($feed_id, $limit = 30)
{
    return (array) db_items(
        "SELECT kind, status, started_at, records, changed, closed, reopened, held, failed, detail
        FROM supplier_feed_runs WHERE feed_id = '" . (int) $feed_id . "'
        ORDER BY started_at DESC, id DESC LIMIT " . max(1, (int) $limit));
}

/**
 * Pull now: a paused feed is switched on again and pulled past the
 * mass-drop brake, since a person asked for it; the pull runs for up to
 * $budget seconds here and the job goes on with the rest.
 *
 * @param array $feed
 * @param int   $budget seconds
 * @return array 'result' (pg_supplier_feed_pull()'s), 'run' (the last run row or null)
 */
function pg_supplier_feed_pull_now($feed, $budget = 20)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/supplier_feed_sync.php');

    $drop_ok = ((int) $feed['status'] === 2);

    if ($drop_ok) {
        db("UPDATE supplier_feeds SET status = 1, pause_reason = '' WHERE id = '" . (int) $feed['id'] . "'");
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit(300);
    }

    $result = pg_supplier_feed_pull((int) $feed['id'], microtime(true) + max(1, (int) $budget), 1, $drop_ok);

    return array('result' => $result, 'run' => db_item("SELECT * FROM supplier_feed_runs WHERE feed_id = '" . (int) $feed['id'] . "' ORDER BY started_at DESC, id DESC LIMIT 1"));
}

/**
 * The product screen's Supplier feed card: each feed carrying the product,
 * its last buy price and when it was last seen, and the "Price from the
 * feed" switch (F5). '' for a product no feed carries.
 *
 * @param int $product_id
 * @return string HTML
 */
function pg_supplier_feed_product_card($product_id)
{
    if (((int) $product_id <= 0) || !pg_supplier_feed_ready()) {
        return '';
    }

    $items = (array) db_items(
        "SELECT supplier_feed_items.feed_id, supplier_feed_items.buy_price, supplier_feed_items.currency, supplier_feed_items.seen_at,
            supplier_feed_items.follow_price, supplier_feed_items.active, supplier_feeds.name
        FROM supplier_feed_items INNER JOIN supplier_feeds ON supplier_feeds.id = supplier_feed_items.feed_id
        WHERE supplier_feed_items.product_id = '" . (int) $product_id . "' AND supplier_feed_items.dismissed = 0
        ORDER BY supplier_feed_items.active DESC, supplier_feeds.name");

    return pg_supplier_feed_product_card_html($items);
}

/**
 * pg_supplier_feed_product_card()'s markup.
 *
 * @param array $items feed_id, name, buy_price, currency, seen_at, follow_price, active
 * @return string HTML
 */
function pg_supplier_feed_product_card_html($items)
{
    if ($items === array()) {
        return '';
    }

    $follow = true;
    $rows = '';
    $several = count($items) > 1;

    foreach ($items as $item) {
        $follow = $follow && !empty($item['follow_price']);
        $seen = ((int) $item['seen_at'] > 0) ? get_relative_time(array('timestamp' => (int) $item['seen_at'], 'format' => 'plain_text')) : lang('Never');
        $chosen = ($several && !empty($item['active'])) ? ' ' . pg_pill(lang('In use'), 'success', 'data-pg-sf-followed') : '';
        $rows .= '<dt><a href="edit_supplier_feed.php?id=' . (int) $item['feed_id'] . '">' . h($item['name']) . '</a>' . $chosen . '</dt>'
            . '<dd>' . h(lang(array('string' => 'Buy {var:1} · seen {var:2}', 'vars' => array(pg_supplier_feed_money_text($item['buy_price'], (string) $item['currency']), $seen)))) . '</dd>';
    }

    return '
                    <div class="card mb-5" id="pg_supplier_feed_card">
                        <h2 class="card-header bg-reset border-0 pg-title-section">' . h(lang('Supplier feed')) . '</h2>
                        <div class="card-body pt-0">
                            <dl class="pg-kv">' . $rows . '</dl>'
                            . ($several ? '<div class="pg-field-help mt-2">' . h(lang('The product follows the feed that has stock with the lowest buy price, for price and stock both.')) . '</div>' : '') . '
                            <input type="hidden" name="supplier_feed_follow_was" value="' . ($follow ? 1 : 0) . '" />
                            <div class="d-flex align-items-start gap-3 mt-3">
                                <input type="checkbox" class="pg-switch" id="supplier_feed_follow_price" name="supplier_feed_follow_price" value="1"' . ($follow ? ' checked' : '') . ' aria-describedby="supplier_feed_follow_help" />
                                <div><label class="pg-text-2" for="supplier_feed_follow_price">' . h(lang('Price from the feed')) . '</label>
                                <div class="pg-field-help" id="supplier_feed_follow_help">' . h(lang('Off: the price stays as you set it; stock still follows the feed.')) . '</div></div>
                            </div>
                        </div>
                    </div>';
}

/**
 * Saves the product's "Price from the feed" switch on all its feed items.
 *
 * @param int  $product_id
 * @param bool $on
 * @return void
 */
function pg_supplier_feed_follow_save($product_id, $on)
{
    if (((int) $product_id <= 0) || !pg_supplier_feed_ready()) {
        return;
    }

    db("UPDATE supplier_feed_items SET follow_price = '" . ($on ? 1 : 0) . "' WHERE product_id = '" . (int) $product_id . "'");
}
