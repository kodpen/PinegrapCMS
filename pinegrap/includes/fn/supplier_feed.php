<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Supplier feeds: reading a supplier's XML, CSV or JSON product list that was
 * pulled to a local file, proposing which of its fields is which, and working
 * out a selling price from a buy price. Tables (2026.11.0, 11.0.255):
 * supplier_feeds (one feed, its mapping and rules as JSON, the running pull's
 * cursor in state), supplier_feed_items (one row per feed and supplier record)
 * and supplier_feed_runs (one row per pull).
 *
 * Readers: pg_supplier_feed_read() returns up to $limit records as flat
 * arrays (key => text) and a cursor to go on from, null once the file ends,
 * so a large feed is read over several job ticks. Keys: an XML or JSON record
 * is flattened with "/" between levels ("Kategori/Ad"), an XML attribute as
 * "@name", a repeated element or list item as "key", "key[2]", "key[3]"; a
 * CSV record is keyed by its headings.
 * - XML: XMLReader, record path of element names from the root
 *   ("Root/Urunler/Urun"); the cursor is the count of records read, skipped
 *   without expanding on the next tick. Never resolves entities or the
 *   network (LIBXML_NONET, no LIBXML_NOENT).
 * - CSV: pg_csv_open() and pg_csv_read_separator(); the cursor keeps the
 *   byte offset, the separator and the headings. A value that is not UTF-8
 *   is read as Windows-1254.
 * - JSON: decoded whole on each call (PG_SUPPLIER_FEED_JSON_MAX_BYTES at
 *   most); record path of keys joined with "/", '' for a top-level list; the
 *   cursor is the index of the next record.
 *
 * Prices are integer minor units, rounded with round(), never cut by (int).
 * The price rule (pg_supplier_feed_price()) is pure: buy price net of VAT
 * × (1 + margin %) + fixed amount (in the feed's currency), times the rate to
 * the home currency, rounded (none, whole, ending in ,90 or ,99), never
 * under the floor: buy price × (1 + floor margin %) at the same rate.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// A JSON feed is decoded whole; a larger file is refused as too large.
define('PG_SUPPLIER_FEED_JSON_MAX_BYTES', 67108864);

// Elements the XML record path search reads before it decides.
define('PG_SUPPLIER_FEED_PATH_SCAN_ELEMENTS', 20000);

/**
 * The format told from the first bytes of a feed: 'xml', 'json' or 'csv';
 * '' when there is nothing but a BOM and white space.
 *
 * @param string $head the first bytes of the file
 * @return string
 */
function pg_supplier_feed_format_detect($head)
{
    $head = (string) $head;

    if (substr($head, 0, 3) === "\xEF\xBB\xBF") {
        $head = substr($head, 3);
    }

    $head = ltrim($head);

    if ($head === '') {
        return '';
    }

    $first = $head[0];

    if ($first === '<') {
        return 'xml';
    }

    if (($first === '[') || ($first === '{')) {
        return 'json';
    }

    return 'csv';
}

/**
 * The format of a feed file from its first 4 KB; '' when it cannot be read.
 *
 * @param string $file
 * @return string
 */
function pg_supplier_feed_format_detect_file($file)
{
    $handle = @fopen((string) $file, 'rb');

    if (!$handle) {
        return '';
    }

    $head = (string) fread($handle, 4096);
    fclose($handle);

    return pg_supplier_feed_format_detect($head);
}

/**
 * Finds where the records sit in an XML or JSON feed.
 * XML: the shallowest element that holds other elements and occurs more than
 * once; when none repeats, the one seen most. JSON: the list holding the most
 * objects, the shallower on a tie. '' for CSV or a top-level JSON list, null
 * when nothing is found.
 *
 * @param string $file
 * @param string $format 'xml', 'json' or 'csv'
 * @return string|null
 */
function pg_supplier_feed_record_path($file, $format)
{
    if ($format === 'xml') {
        return pg_supplier_feed_xml_record_path($file);
    }

    if ($format === 'json') {
        $data = pg_supplier_feed_json_load($file, $error);

        if ($error !== '') {
            return null;
        }

        $best = null;
        pg_supplier_feed_json_find_list($data, '', 0, $best);

        return ($best === null) ? null : $best['path'];
    }

    return '';
}

/**
 * The XML half of pg_supplier_feed_record_path().
 *
 * @param string $file
 * @return string|null
 */
function pg_supplier_feed_xml_record_path($file)
{
    $reader = pg_supplier_feed_xml_open($file);

    if ($reader === null) {
        return null;
    }

    $stack = array();
    $counts = array();
    $parents = array();
    $seen = 0;

    while (@$reader->read() && ($seen < PG_SUPPLIER_FEED_PATH_SCAN_ELEMENTS)) {
        if ($reader->nodeType !== XMLReader::ELEMENT) {
            continue;
        }

        $seen++;
        $depth = (int) $reader->depth;
        $stack = array_slice($stack, 0, $depth);
        $stack[$depth] = (string) $reader->localName;
        $path = implode('/', $stack);
        $counts[$path] = isset($counts[$path]) ? $counts[$path] + 1 : 1;

        if ($depth > 0) {
            $parents[implode('/', array_slice($stack, 0, $depth))] = true;
        }
    }

    $reader->close();

    $best = null;
    $best_depth = 0;
    $best_count = 0;

    foreach ($counts as $path => $count) {
        if (!isset($parents[$path]) || ($count < 2)) {
            continue;
        }

        $depth = substr_count($path, '/');

        if (($best === null) || ($depth < $best_depth) || (($depth === $best_depth) && ($count > $best_count))) {
            $best = $path;
            $best_depth = $depth;
            $best_count = $count;
        }
    }

    if ($best !== null) {
        return (string) $best;
    }

    // One record only: the deepest element holding others.
    foreach (array_keys($parents) as $path) {
        if (($best === null) || (substr_count((string) $path, '/') > substr_count($best, '/'))) {
            $best = (string) $path;
        }
    }

    return $best;
}

/**
 * Walks decoded JSON for the list holding the most objects.
 *
 * @param mixed      $node
 * @param string     $path
 * @param int        $depth
 * @param array|null $best array('path', 'count', 'depth'), updated in place
 * @return void
 */
function pg_supplier_feed_json_find_list($node, $path, $depth, &$best)
{
    if (!is_array($node) || ($depth > 8)) {
        return;
    }

    if (pg_supplier_feed_is_list($node)) {
        $objects = 0;

        foreach ($node as $item) {
            if (is_array($item) && ($item !== array()) && !pg_supplier_feed_is_list($item)) {
                $objects++;
            }
        }

        if (($objects > 0) && (($best === null) || ($objects > $best['count']) || (($objects === $best['count']) && ($depth < $best['depth'])))) {
            $best = array('path' => $path, 'count' => $objects, 'depth' => $depth);
        }

        return;
    }

    foreach ($node as $key => $child) {
        pg_supplier_feed_json_find_list($child, ($path === '') ? (string) $key : $path . '/' . $key, $depth + 1, $best);
    }
}

/**
 * Whether an array is a list (keys 0..n-1 in order).
 *
 * @param array $array
 * @return bool
 */
function pg_supplier_feed_is_list($array)
{
    $i = 0;

    foreach ($array as $key => $unused) {
        if ($key !== $i) {
            return false;
        }

        $i++;
    }

    return true;
}

/**
 * Reads up to $limit records of a feed file from a cursor.
 *
 * @param string $file
 * @param string $format      'xml', 'csv' or 'json'
 * @param string $record_path see pg_supplier_feed_record_path()
 * @param array  $cursor      array() to start; the cursor a call returned to go on
 * @param int    $limit
 * @return array 'records' => list of flat arrays, 'cursor' => array or null
 *               when the file has ended, 'error' => '' or 'open', 'parse',
 *               'format', 'too_large', 'no_records'
 */
function pg_supplier_feed_read($file, $format, $record_path, $cursor, $limit)
{
    $cursor = is_array($cursor) ? $cursor : array();
    $limit = max(1, (int) $limit);

    if ($format === 'xml') {
        return pg_supplier_feed_read_xml($file, (string) $record_path, $cursor, $limit);
    }

    if ($format === 'csv') {
        return pg_supplier_feed_read_csv($file, $cursor, $limit);
    }

    if ($format === 'json') {
        return pg_supplier_feed_read_json($file, (string) $record_path, $cursor, $limit);
    }

    return array('records' => array(), 'cursor' => null, 'error' => 'format');
}

/**
 * An XMLReader on a feed file, null when it cannot be opened.
 *
 * @param string $file
 * @return XMLReader|null
 */
function pg_supplier_feed_xml_open($file)
{
    if (!class_exists('XMLReader') || !is_file((string) $file)) {
        return null;
    }

    $reader = new XMLReader();

    return @$reader->open((string) $file, null, LIBXML_NONET | LIBXML_NOCDATA) ? $reader : null;
}

/**
 * The XML reader of pg_supplier_feed_read().
 *
 * @param string $file
 * @param string $record_path
 * @param array  $cursor array('index' => records already read)
 * @param int    $limit
 * @return array
 */
function pg_supplier_feed_read_xml($file, $record_path, $cursor, $limit)
{
    $skip = isset($cursor['index']) ? max(0, (int) $cursor['index']) : 0;
    $reader = pg_supplier_feed_xml_open($file);

    if ($reader === null) {
        return array('records' => array(), 'cursor' => null, 'error' => 'open');
    }

    $previous = libxml_use_internal_errors(true);
    libxml_clear_errors();

    $records = array();
    $index = 0;
    $stack = array();
    $next = null;
    $moved = @$reader->read();

    while ($moved) {
        if ($reader->nodeType === XMLReader::ELEMENT) {
            $depth = (int) $reader->depth;
            $stack = array_slice($stack, 0, $depth);
            $stack[$depth] = (string) $reader->localName;

            if (implode('/', $stack) === $record_path) {
                if (count($records) >= $limit) {
                    $next = array('index' => $index);
                    break;
                }

                if ($index >= $skip) {
                    $node = @$reader->expand();

                    if (!($node instanceof DOMElement)) {
                        break;
                    }

                    $record = array();
                    pg_supplier_feed_flatten_dom($node, '', $record);
                    $records[] = $record;
                }

                $index++;
                $moved = @$reader->next();
                continue;
            }
        }

        $moved = @$reader->read();
    }

    $failed = (libxml_get_errors() !== array());
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $reader->close();

    if ($failed) {
        return array('records' => $records, 'cursor' => null, 'error' => 'parse');
    }

    return array('records' => $records, 'cursor' => $next, 'error' => '');
}

/**
 * Flattens an XML element into $out: attributes as "@name", children by
 * name with "/" between levels, a leaf's trimmed text under its key.
 *
 * @param DOMElement $element
 * @param string     $prefix the key of $element, '' for the record itself
 * @param array      $out
 * @return void
 */
function pg_supplier_feed_flatten_dom($element, $prefix, &$out)
{
    if ($element->hasAttributes()) {
        foreach ($element->attributes as $attribute) {
            pg_supplier_feed_flat_put($out, $prefix . '@' . $attribute->localName, trim((string) $attribute->value));
        }
    }

    $children = false;

    foreach ($element->childNodes as $child) {
        if ($child instanceof DOMElement) {
            $children = true;
            pg_supplier_feed_flatten_dom($child, (($prefix === '') ? '' : $prefix . '/') . $child->localName, $out);
        }
    }

    if (!$children && ($prefix !== '')) {
        pg_supplier_feed_flat_put($out, $prefix, trim((string) $element->textContent));
    }
}

/**
 * Puts a value under a key, "key[2]", "key[3]"... when the key is taken.
 *
 * @param array  $out
 * @param string $key
 * @param string $value
 * @return void
 */
function pg_supplier_feed_flat_put(&$out, $key, $value)
{
    if (!array_key_exists($key, $out)) {
        $out[$key] = $value;

        return;
    }

    $n = 2;

    while (array_key_exists($key . '[' . $n . ']', $out)) {
        $n++;
    }

    $out[$key . '[' . $n . ']'] = $value;
}

/**
 * A CSV value as UTF-8: one that is not valid UTF-8 is read as Windows-1254,
 * the Turkish Excel default.
 *
 * @param string $value
 * @return string
 */
function pg_supplier_feed_utf8($value)
{
    $value = (string) $value;

    if (($value === '') || preg_match('//u', $value)) {
        return $value;
    }

    if (function_exists('mb_convert_encoding')) {
        $converted = @mb_convert_encoding($value, 'UTF-8', 'Windows-1254');

        if (is_string($converted)) {
            return $converted;
        }
    }

    return (string) preg_replace('/[\x80-\xFF]/', '?', $value);
}

/**
 * The CSV reader of pg_supplier_feed_read().
 *
 * @param string $file
 * @param array  $cursor array('offset', 'separator', 'headings') once started
 * @param int    $limit
 * @return array
 */
function pg_supplier_feed_read_csv($file, $cursor, $limit)
{
    $handle = pg_csv_open((string) $file);

    if (!$handle) {
        return array('records' => array(), 'cursor' => null, 'error' => 'open');
    }

    if (isset($cursor['offset'], $cursor['separator'], $cursor['headings'])) {
        $separator = (string) $cursor['separator'];
        $headings = (array) $cursor['headings'];
        fseek($handle, (int) $cursor['offset']);
    } else {
        $separator = pg_csv_read_separator($handle);
        $row = fgetcsv($handle, 0, $separator, '"', '\\');
        $headings = array();

        foreach ((is_array($row) ? $row : array()) as $i => $heading) {
            $heading = trim(pg_supplier_feed_utf8((string) $heading));
            $heading = ($heading === '') ? 'Column ' . ($i + 1) : $heading;
            $base = $heading;
            $n = 2;

            while (in_array($heading, $headings, true)) {
                $heading = $base . '[' . $n . ']';
                $n++;
            }

            $headings[] = $heading;
        }

        if ($headings === array()) {
            fclose($handle);

            return array('records' => array(), 'cursor' => null, 'error' => 'no_records');
        }
    }

    $records = array();
    $next = null;

    while (true) {
        if (count($records) >= $limit) {
            $offset = ftell($handle);

            // A cursor at the very end would cost the next tick nothing but
            // still mean "not done"; peek for one more row.
            if (fgets($handle) !== false) {
                $next = array('offset' => (int) $offset, 'separator' => $separator, 'headings' => $headings);
            }

            break;
        }

        $row = fgetcsv($handle, 0, $separator, '"', '\\');

        if ($row === false) {
            break;
        }

        if (!is_array($row) || pg_csv_row_blank($row)) {
            continue;
        }

        $record = array();

        foreach ($headings as $i => $heading) {
            $record[$heading] = isset($row[$i]) ? trim(pg_supplier_feed_utf8((string) $row[$i])) : '';
        }

        $records[] = $record;
    }

    fclose($handle);

    return array('records' => $records, 'cursor' => $next, 'error' => '');
}

/**
 * Decodes a JSON feed file; $error is '' or 'open', 'too_large', 'parse'.
 *
 * @param string $file
 * @param string $error
 * @return mixed
 */
function pg_supplier_feed_json_load($file, &$error)
{
    $error = '';
    $size = is_file((string) $file) ? @filesize((string) $file) : false;

    if ($size === false) {
        $error = 'open';

        return null;
    }

    if ($size > PG_SUPPLIER_FEED_JSON_MAX_BYTES) {
        $error = 'too_large';

        return null;
    }

    $text = (string) @file_get_contents((string) $file);

    if (substr($text, 0, 3) === "\xEF\xBB\xBF") {
        $text = substr($text, 3);
    }

    $data = json_decode($text, true);

    if (!is_array($data)) {
        $error = 'parse';

        return null;
    }

    return $data;
}

/**
 * The JSON reader of pg_supplier_feed_read().
 *
 * @param string $file
 * @param string $record_path
 * @param array  $cursor array('index' => the next record)
 * @param int    $limit
 * @return array
 */
function pg_supplier_feed_read_json($file, $record_path, $cursor, $limit)
{
    $data = pg_supplier_feed_json_load($file, $error);

    if ($error !== '') {
        return array('records' => array(), 'cursor' => null, 'error' => $error);
    }

    if ($record_path !== '') {
        foreach (explode('/', $record_path) as $key) {
            if (!is_array($data) || !array_key_exists($key, $data)) {
                return array('records' => array(), 'cursor' => null, 'error' => 'no_records');
            }

            $data = $data[$key];
        }
    }

    if (!is_array($data) || !pg_supplier_feed_is_list($data)) {
        return array('records' => array(), 'cursor' => null, 'error' => 'no_records');
    }

    $start = isset($cursor['index']) ? max(0, (int) $cursor['index']) : 0;
    $records = array();
    $total = count($data);

    for ($i = $start; ($i < $total) && (count($records) < $limit); $i++) {
        $record = array();
        pg_supplier_feed_flatten_json($data[$i], '', $record);
        $records[] = $record;
    }

    return array('records' => $records, 'cursor' => ($i < $total) ? array('index' => $i) : null, 'error' => '');
}

/**
 * Flattens a decoded JSON value into $out (see the file header for keys).
 *
 * @param mixed  $value
 * @param string $prefix
 * @param array  $out
 * @return void
 */
function pg_supplier_feed_flatten_json($value, $prefix, &$out)
{
    if (is_array($value)) {
        $list = pg_supplier_feed_is_list($value);

        foreach ($value as $key => $child) {
            $child_key = $list ? $prefix : (($prefix === '') ? (string) $key : $prefix . '/' . $key);
            pg_supplier_feed_flatten_json($child, $child_key, $out);
        }

        return;
    }

    if ($prefix === '') {
        return;
    }

    if (is_bool($value)) {
        $text = $value ? '1' : '0';
    } elseif ($value === null) {
        $text = '';
    } elseif (is_float($value)) {
        $text = rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
    } else {
        $text = trim((string) $value);
    }

    pg_supplier_feed_flat_put($out, $prefix, $text);
}

/**
 * The product fields a feed can be mapped to, each with the names suppliers
 * give it, folded as pg_supplier_feed_fold() folds (Turkish and English).
 * Order matters: a source key goes to the first field that claims it.
 *
 * @return array field => list of names
 */
function pg_supplier_feed_field_names()
{
    return array(
        'supplier_key' => array('stokkodu', 'stokkod', 'urunkodu', 'urunkod', 'sku', 'productcode', 'itemcode', 'code', 'kod', 'productid', 'urunid', 'id', 'stockcode'),
        'barcode' => array('barkod', 'barcode', 'ean', 'ean13', 'gtin', 'upc', 'barkodno'),
        'model_code' => array('modelkodu', 'modelkod', 'model', 'modelcode', 'grupkodu', 'parentsku', 'groupcode', 'varyantgrubu'),
        'buy_price' => array('alisfiyati', 'alisfiyat', 'alis', 'bayifiyati', 'bayifiyat', 'maliyet', 'cost', 'buyprice', 'purchaseprice', 'dealerprice', 'price', 'fiyat', 'netfiyat', 'listefiyati'),
        'stock' => array('stok', 'stokadedi', 'stokmiktari', 'miktar', 'adet', 'stock', 'quantity', 'qty', 'stockquantity', 'inventory'),
        'currency' => array('parabirimi', 'doviz', 'dovizcinsi', 'currency', 'kur'),
        'vat' => array('kdv', 'kdvorani', 'vat', 'vatrate', 'tax', 'taxrate', 'vergi'),
        'name' => array('urunadi', 'urunismi', 'urunad', 'ad', 'adi', 'isim', 'baslik', 'name', 'productname', 'title'),
        'brand' => array('marka', 'brand', 'manufacturer', 'uretici'),
        'category' => array('kategori', 'kategoriadi', 'category', 'categoryname', 'kategoriyolu', 'categorypath'),
        'description' => array('aciklama', 'urunaciklamasi', 'detay', 'description', 'details', 'body'),
        'image' => array('resim', 'resimler', 'gorsel', 'gorseller', 'foto', 'image', 'images', 'picture', 'pictures', 'imageurl', 'resimurl', 'photo'),
    );
}

/**
 * A field name folded for matching: Turkish letters to ASCII, lower case,
 * letters and digits only.
 *
 * @param string $name
 * @return string
 */
function pg_supplier_feed_fold($name)
{
    $name = strtr((string) $name, array(
        'ç' => 'c', 'Ç' => 'c', 'ğ' => 'g', 'Ğ' => 'g', 'ı' => 'i', 'I' => 'i', 'İ' => 'i',
        'ö' => 'o', 'Ö' => 'o', 'ş' => 's', 'Ş' => 's', 'ü' => 'u', 'Ü' => 'u',
    ));

    return (string) preg_replace('/[^a-z0-9]/', '', strtolower($name));
}

/**
 * Proposes which source key holds which product field, from the keys of
 * the first records (F2). A key is folded at its last level and without its
 * "@" ("[n]" keys are left out); a name in the field's list matches whole,
 * or the key starts with one, or holds one. A key goes to one field at
 * most; the better match wins, then the shallower key, the name listed
 * earlier and the field listed first. A field nothing matches
 * gets ''.
 *
 * @param array $records flat records from pg_supplier_feed_read()
 * @return array field => source key or ''
 */
function pg_supplier_feed_propose_fields($records)
{
    $keys = array();

    foreach ((array) $records as $record) {
        foreach (array_keys((array) $record) as $key) {
            $key = (string) $key;

            if (!preg_match('/\[\d+\]$/', $key)) {
                $keys[$key] = true;
            }
        }
    }

    $fields = pg_supplier_feed_field_names();
    $candidates = array();
    $order = 0;

    foreach ($fields as $field => $names) {
        foreach (array_keys($keys) as $key) {
            $key = (string) $key;
            $parts = explode('/', $key);
            $last = pg_supplier_feed_fold(ltrim((string) end($parts), '@'));
            $full = pg_supplier_feed_fold($key);
            $score = 0;

            foreach ($names as $rank => $name) {
                if (($last === $name) || ($full === $name)) {
                    $level = 3;
                } elseif ((strlen($name) >= 3) && (strpos($last, $name) === 0)) {
                    $level = 2;
                } elseif ((strlen($name) >= 4) && (strpos($full, $name) !== false)) {
                    $level = 1;
                } else {
                    continue;
                }

                // A better match first, then a shallower key ("title" over
                // "brand/name"), then a name listed earlier.
                $score = max($score, $level * 100000 - substr_count($key, '/') * 1000 - $rank);
            }

            if ($score > 0) {
                $candidates[] = array($score, $order, $field, $key);
            }
        }

        $order++;
    }

    usort($candidates, function ($a, $b) {
        if ($a[0] !== $b[0]) {
            return ($a[0] > $b[0]) ? -1 : 1;
        }

        return $a[1] - $b[1];
    });

    $proposal = array_fill_keys(array_keys($fields), '');
    $used = array();

    foreach ($candidates as $candidate) {
        list(, , $field, $key) = $candidate;

        if (($proposal[$field] === '') && !isset($used[$key])) {
            $proposal[$field] = $key;
            $used[$key] = true;
        }
    }

    return $proposal;
}

/**
 * An amount as written in a feed, in minor units: Turkish ("1.234,50") and
 * English ("1,234.56") separators, a currency sign or code around it. With
 * both "." and "," the last one is the decimal mark; with one kind used
 * more than once it is a thousands mark; used once and followed by exactly
 * three digits it is a thousands mark too, else the decimal mark. Rounded
 * with round(). Null for no number or a negative one.
 *
 * @param string $text
 * @param int    $decimals minor-unit places of the currency
 * @return int|null
 */
function pg_supplier_feed_parse_amount($text, $decimals = 2)
{
    $text = trim((string) $text);

    if (!preg_match('/-?\d[\d.,\s\x{00A0}\']*/u', $text, $match)) {
        return null;
    }

    $number = (string) preg_replace('/[\s\x{00A0}\']/u', '', $match[0]);
    $number = rtrim($number, '.,');

    if ($number === '' || $number[0] === '-') {
        return null;
    }

    $dot = strrpos($number, '.');
    $comma = strrpos($number, ',');

    if (($dot !== false) && ($comma !== false)) {
        $decimal = ($dot > $comma) ? '.' : ',';
    } elseif (($dot !== false) || ($comma !== false)) {
        $mark = ($dot !== false) ? '.' : ',';
        $after = strlen($number) - strrpos($number, $mark) - 1;
        $leading = substr($number, 0, (int) strpos($number, $mark));

        if ((substr_count($number, $mark) > 1) || (($after === 3) && ($leading !== '0'))) {
            $decimal = '';
        } else {
            $decimal = $mark;
        }
    } else {
        $decimal = '';
    }

    $thousands = ($decimal === '.') ? ',' : (($decimal === ',') ? '.' : '.,');
    $number = str_replace(str_split($thousands), '', $number);

    if ($decimal === ',') {
        $number = str_replace(',', '.', $number);
    }

    if (!is_numeric($number)) {
        return null;
    }

    return (int) round((float) $number * pow(10, max(0, (int) $decimals)));
}

/**
 * A stock figure as written in a feed: "10+" is 10, "5,00" is 5, never
 * below 0, 0 for text.
 *
 * @param string $text
 * @return int
 */
function pg_supplier_feed_parse_stock($text)
{
    $amount = pg_supplier_feed_parse_amount($text, 0);

    return ($amount === null) ? 0 : max(0, $amount);
}

/**
 * A price with VAT included, net of it, in the same minor units.
 *
 * @param int   $amount
 * @param float $vat_percent
 * @return int
 */
function pg_supplier_feed_net_of_vat($amount, $vat_percent)
{
    $vat_percent = (float) $vat_percent;

    if ($vat_percent <= 0) {
        return (int) $amount;
    }

    return (int) round((int) $amount / (1 + $vat_percent / 100));
}

/**
 * The rule that applies to one record (F4): of the feed's specific rules,
 * one naming both the category and the brand first, then one naming the
 * category, then one naming the brand (names compared without regard to
 * case), else the feed's own. A specific rule takes from the feed's own
 * what it does not set.
 *
 * @param array  $rules    the feed's rules JSON decoded: margin, fixed,
 *                         rounding, floor_margin, specific (list of rules
 *                         with category and/or brand)
 * @param string $category
 * @param string $brand
 * @return array margin, fixed, rounding, floor_margin
 */
function pg_supplier_feed_rule_pick($rules, $category, $brand)
{
    $rules = is_array($rules) ? $rules : array();
    $base = array(
        'margin' => isset($rules['margin']) ? $rules['margin'] : 0,
        'fixed' => isset($rules['fixed']) ? $rules['fixed'] : 0,
        'rounding' => isset($rules['rounding']) ? (string) $rules['rounding'] : 'none',
        'floor_margin' => isset($rules['floor_margin']) ? $rules['floor_margin'] : 0,
    );

    $category = pg_supplier_feed_lower($category);
    $brand = pg_supplier_feed_lower($brand);
    $best = null;
    $best_rank = 0;

    foreach ((isset($rules['specific']) && is_array($rules['specific'])) ? $rules['specific'] : array() as $rule) {
        if (!is_array($rule)) {
            continue;
        }

        $rule_category = isset($rule['category']) ? pg_supplier_feed_lower($rule['category']) : '';
        $rule_brand = isset($rule['brand']) ? pg_supplier_feed_lower($rule['brand']) : '';

        if ((($rule_category === '') && ($rule_brand === ''))
            || (($rule_category !== '') && ($rule_category !== $category))
            || (($rule_brand !== '') && ($rule_brand !== $brand))) {
            continue;
        }

        $rank = (($rule_category !== '') && ($rule_brand !== '')) ? 3 : (($rule_category !== '') ? 2 : 1);

        if ($rank > $best_rank) {
            $best = $rule;
            $best_rank = $rank;
        }
    }

    if ($best !== null) {
        foreach (array_keys($base) as $key) {
            if (isset($best[$key])) {
                $base[$key] = ($key === 'rounding') ? (string) $best[$key] : $best[$key];
            }
        }
    }

    return $base;
}

/**
 * A name in lower case for comparing, Turkish İ and I included.
 *
 * @param string $text
 * @return string
 */
function pg_supplier_feed_lower($text)
{
    $text = trim(strtr((string) $text, array('İ' => 'i', 'I' => 'ı')));

    return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
}

/**
 * The selling price of a record (F4), pure.
 *
 * @param int   $buy           buy price net of VAT, minor units of the feed's currency
 * @param array $rule          from pg_supplier_feed_rule_pick(): margin (%), fixed
 *                             (minor units of the feed's currency), rounding
 *                             ('none', 'whole', '90', '99'), floor_margin (%)
 * @param float $rate          home currency per unit of the feed's currency, 1 for the same
 * @param int   $home_decimals minor-unit places of the home currency
 * @param int   $feed_decimals minor-unit places of the feed's currency
 * @return int minor units of the home currency; 0 when there is no buy price
 */
function pg_supplier_feed_price($buy, $rule, $rate, $home_decimals = 2, $feed_decimals = 2)
{
    $buy = (int) $buy;
    $rate = (float) $rate;

    if (($buy <= 0) || ($rate <= 0)) {
        return 0;
    }

    $margin = isset($rule['margin']) ? (float) $rule['margin'] : 0.0;
    $fixed = isset($rule['fixed']) ? (float) $rule['fixed'] : 0.0;
    $floor_margin = isset($rule['floor_margin']) ? (float) $rule['floor_margin'] : 0.0;
    $rounding = isset($rule['rounding']) ? (string) $rule['rounding'] : 'none';
    $scale = $rate * pow(10, (int) $home_decimals - (int) $feed_decimals);

    $price = (int) round(($buy * (1 + $margin / 100) + $fixed) * $scale);
    $floor = ($floor_margin > 0) ? (int) round($buy * (1 + $floor_margin / 100) * $scale) : 0;

    $price = pg_supplier_feed_round_price($price, $rounding, (int) $home_decimals, false);

    if ($price < $floor) {
        $price = pg_supplier_feed_round_price($floor, $rounding, (int) $home_decimals, true);
    }

    return max(0, $price);
}

/**
 * Rounds a price in minor units: 'whole' to the nearest whole unit, '90' or
 * '99' to the nearest price ending in ,90 or ,99 (a tie goes up, never 0 or
 * below). $up takes the first such price at or above instead. A currency
 * without two decimals rounds ,90 and ,99 as whole.
 *
 * @param int    $price
 * @param string $rounding
 * @param int    $decimals
 * @param bool   $up
 * @return int
 */
function pg_supplier_feed_round_price($price, $rounding, $decimals, $up)
{
    $unit = (int) pow(10, max(0, $decimals));

    if (in_array($rounding, array('90', '99'), true) && ($decimals < 2)) {
        $rounding = 'whole';
    }

    if (($rounding === 'whole') && ($unit > 1)) {
        $low = (int) (floor($price / $unit) * $unit);

        if ($low === $price) {
            return $price;
        }

        return ($up || (($price - $low) * 2 >= $unit)) ? $low + $unit : $low;
    }

    if (in_array($rounding, array('90', '99'), true)) {
        $ending = (int) $rounding * (int) pow(10, $decimals - 2);
        $below = (int) (floor(($price - $ending) / $unit) * $unit) + $ending;
        $above = ($below === $price) ? $below : $below + $unit;

        if ($up || ($below <= 0) || (($above - $price) <= ($price - $below))) {
            return $above;
        }

        return $below;
    }

    return $price;
}
