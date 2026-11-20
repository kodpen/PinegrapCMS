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
        return array('tone' => 'rose', 'label' => lang('Failed'), 'note' => $first_words($run['detail']));
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
 * feed itself closed. Changed: a tracked product whose stock differs.
 *
 * @param array $records    flat records
 * @param array $fields     the mapping's fields
 * @param array $by_code    lower-case product code => product id
 * @param array $by_barcode barcode => product id
 * @param array $products   id => id, name, enabled, inventory, inventory_quantity
 * @param array $items      this feed's matched items: product_id, closed_by_feed
 * @return array added, changed, closed, reopened (each 'count', 'rows' of
 *               code, name, note), 'records', 'skipped'
 */
function pg_supplier_feed_test_diff($records, $fields, $by_code, $by_barcode, $products, $items)
{
    $out = array('records' => 0, 'skipped' => 0);

    foreach (array('added', 'changed', 'closed', 'reopened') as $kind) {
        $out[$kind] = array('count' => 0, 'rows' => array());
    }

    $add = function ($kind, $code, $name, $note) use (&$out) {
        $out[$kind]['count']++;

        if (count($out[$kind]['rows']) < PG_SUPPLIER_FEED_TEST_ROWS) {
            $out[$kind]['rows'][] = array('code' => (string) $code, 'name' => (string) $name, 'note' => (string) $note);
        }
    };

    $closed_by_feed = array();

    foreach ((array) $items as $item) {
        $closed_by_feed[(int) $item['product_id']] = !empty($item['closed_by_feed']);
    }

    $field = function ($record, $name) use ($fields) {
        $key = isset($fields[$name]) ? (string) $fields[$name] : '';

        return (($key !== '') && isset($record[$key])) ? trim((string) $record[$key]) : '';
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
            $add('added', $code, $name, lang(array('string' => 'Stock {var:1}', 'vars' => array($stock))));
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

        if ($stock === 0) {
            if ($open) {
                $add('closed', $product['name'], $name, lang('Stock 0 in the feed'));
            }
        } elseif (!$open && !empty($closed_by_feed[$id])) {
            $add('reopened', $product['name'], $name, lang(array('string' => 'Stock {var:1}', 'vars' => array($stock))));
        } elseif ($tracked && ($quantity !== $stock)) {
            $add('changed', $product['name'], $name, lang(array('string' => 'Stock {var:1} → {var:2}', 'vars' => array($quantity, $stock))));
        }
    }

    foreach ($closed_by_feed as $id => $unused) {
        if (isset($seen[$id]) || !isset($products[$id])) {
            continue;
        }

        $product = $products[$id];
        $open = !empty($product['inventory']) ? ((int) $product['inventory_quantity'] > 0) : !empty($product['enabled']);

        if ($open) {
            $add('closed', $product['name'], $product['name'], lang('Not in the feed'));
        }
    }

    return $out;
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

    $file = pg_supplier_feed_temp_file($id);

    if (is_file($file)) {
        @unlink($file);
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
    $columns = "products.id, products.name, products.enabled, products.inventory, products.inventory_quantity";

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

    $items = (array) db_items("SELECT DISTINCT supplier_feed_items.product_id, supplier_feed_items.closed_by_feed, " . $columns . "
        FROM supplier_feed_items INNER JOIN products ON products.id = supplier_feed_items.product_id
        WHERE supplier_feed_items.feed_id = '" . (int) $feed['id'] . "' AND supplier_feed_items.product_id > 0");

    foreach ($items as $item) {
        $products[(int) $item['product_id']] = $item;
    }

    return array(
        'ok'    => true,
        'error' => '',
        'diff'  => pg_supplier_feed_test_diff($records, $mapping['fields'], $by_code, $by_barcode, $products, $items),
        'cut'   => ($cursor !== null),
    );
}
