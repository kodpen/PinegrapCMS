<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Checkout legal documents: the pre-information form and the distance sales
 * contract a customer reads and accepts before paying. A document is a
 * template from the store settings with ^^field^^ placeholders, filled from
 * the order; the filled copy is kept in order_documents at submit time, so a
 * later template edit never changes what a customer agreed to.
 *
 * Template syntax follows the e-mail merge fields:
 *   ^^field^^                    the field's value, HTML-escaped
 *   [[text ^^field^^ text]]      the text only when the field has a value
 *   [[text ^^field^^||other]]    "other" when the field is empty
 * ^^order_lines^^ is the only field that carries markup (the line table, built
 * here with every value escaped).
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
 * The document types a store can require at checkout, keyed by the value
 * stored in order_documents.type.
 *
 * @return array  type => label
 */
function pg_legal_document_types()
{
    return array(
        'preinfo'  => lang('Pre-Information Form'),
        'contract' => lang('Distance Sales Contract'),
    );
}

/**
 * The placeholders a template may use, with a short description each for the
 * settings card.
 *
 * @return array  field name => description
 */
function pg_legal_document_placeholders()
{
    return array(
        'seller_name'       => lang('Organization name'),
        'seller_address'    => lang('Organization address'),
        'seller_email'      => lang('Store e-mail address'),
        'seller_tax_office' => lang('Tax office'),
        'seller_tax_number' => lang('Tax number'),
        'seller_website'    => lang('Website address'),
        'buyer_name'        => lang('Customer name'),
        'buyer_company'     => lang('Customer company'),
        'buyer_address'     => lang('Billing address'),
        'buyer_phone'       => lang('Customer phone'),
        'buyer_email'       => lang('Customer e-mail address'),
        'delivery_address'  => lang('Delivery name and address'),
        'shipping_method'   => lang('Shipping method'),
        'arrival_date'      => lang('Estimated delivery date'),
        'order_lines'       => lang('Table of ordered items'),
        'subtotal'          => lang('Subtotal'),
        'discount'          => lang('Discount'),
        'tax'               => lang('Tax'),
        'shipping'          => lang('Shipping'),
        'surcharge'         => lang('Surcharge'),
        'total'             => lang('Total'),
        'payment_method'    => lang('Payment method'),
        'order_number'      => lang('Order number (empty before the order is placed)'),
        'today'             => lang('Date the document was produced'),
    );
}

/**
 * The placeholder names in a template that are not known fields, in the
 * order they first appear. A misspelt name would print nothing in a document
 * a customer signs, so the settings save refuses it.
 *
 * @param string $template
 * @return string[]
 */
function pg_legal_document_unknown_placeholders($template)
{
    $known = pg_legal_document_placeholders();
    $unknown = array();

    if (preg_match_all('/\^\^(.*?)\^\^/s', (string) $template, $matches)) {
        foreach ($matches[1] as $name) {
            if (!isset($known[$name]) && !in_array($name, $unknown, true)) {
                $unknown[] = $name;
            }
        }
    }

    return $unknown;
}

/**
 * Fill a template.
 *
 * Text values are escaped and their line breaks become <br>; values in $html
 * are inserted as they are and must be built safe by the caller. A field that
 * is neither (an unknown name) prints nothing. Conditionals are resolved
 * first, on the raw values, so [[...]] decides on emptiness and never on the
 * escaped form.
 *
 * @param string $template
 * @param array  $text  field => plain text
 * @param array  $html  field => trusted markup
 * @return string
 */
function pg_legal_document_render($template, array $text, array $html = array())
{
    $template = (string) $template;

    $value_of = function ($name) use ($text, $html) {
        if (array_key_exists($name, $html)) {
            return (string) $html[$name];
        }

        if (array_key_exists($name, $text)) {
            return nl2br(h(trim((string) $text[$name])), false);
        }

        return '';
    };

    $is_filled = function ($name) use ($text, $html) {
        if (array_key_exists($name, $html)) {
            return trim((string) $html[$name]) !== '';
        }

        return array_key_exists($name, $text) && (trim((string) $text[$name]) !== '');
    };

    // [[positive ^^field^^ positive||negative]]: the field named in the
    // positive part decides. Without a negative part an empty field removes
    // the whole bracket.
    $template = preg_replace_callback(
        '/\[\[(.*?\^\^(.*?)\^\^.*?)(\|\|(.*?))?\]\]/s',
        function ($match) use ($is_filled) {
            if ($is_filled($match[2])) {
                return $match[1];
            }

            return isset($match[4]) ? $match[4] : '';
        },
        $template
    );

    return preg_replace_callback(
        '/\^\^(.*?)\^\^/s',
        function ($match) use ($value_of) {
            return $value_of($match[1]);
        },
        $template
    );
}

/**
 * The table of ordered items printed for ^^order_lines^^.
 *
 * @param array    $lines  each: name, quantity, unit_price, line_total (minor units)
 * @param callable $money  minor units => display text
 * @return string
 */
function pg_legal_document_lines_html(array $lines, $money)
{
    $rows = '';

    foreach ($lines as $line) {
        $rows .= '<tr>'
            . '<td>' . h((string) ($line['name'] ?? '')) . '</td>'
            . '<td style="text-align: right">' . h((string) ($line['quantity'] ?? '')) . '</td>'
            . '<td style="text-align: right; white-space: nowrap">' . h((string) $money((int) round($line['unit_price'] ?? 0))) . '</td>'
            . '<td style="text-align: right; white-space: nowrap">' . h((string) $money((int) round($line['line_total'] ?? 0))) . '</td>'
            . '</tr>';
    }

    return '<table class="pg-legal-lines" style="width: 100%; border-collapse: collapse">'
        . '<thead><tr>'
        . '<th style="text-align: left">' . h(lang('Item')) . '</th>'
        . '<th style="text-align: right">' . h(lang('Qty')) . '</th>'
        . '<th style="text-align: right">' . h(lang('Unit Price')) . '</th>'
        . '<th style="text-align: right">' . h(lang('Amount')) . '</th>'
        . '</tr></thead>'
        . '<tbody>' . $rows . '</tbody>'
        . '</table>';
}

/**
 * Join the non-empty parts of an address, one line per part group.
 *
 * @param array $lines  each a string or a list of strings joined by a space
 * @return string
 */
function pg_legal_document_address(array $lines)
{
    $out = array();

    foreach ($lines as $line) {
        $parts = array_filter(array_map('trim', array_map('strval', (array) $line)), 'strlen');

        if ($parts) {
            $out[] = implode(' ', $parts);
        }
    }

    return implode("\n", $out);
}

/**
 * The field values for one order: plain text values and the line table.
 *
 * Amounts are integer minor units; an amount of zero leaves its field empty,
 * so [[...]] can drop a discount, tax or shipping row the order does not
 * have. The total is always printed.
 *
 * @param array    $order      orders row fields (billing_*, subtotal, discount, gift_card_discount, tax, shipping, surcharge, total, order_number)
 * @param array    $lines      see pg_legal_document_lines_html()
 * @param array    $ship_tos   each: name, address (text), method, arrival_date
 * @param array    $seller     name, address, email, tax_office, tax_number, website
 * @param array    $extra      payment_method, today (already formatted)
 * @param callable $money      minor units => display text
 * @return array  array('text' => field => text, 'html' => field => markup)
 */
function pg_legal_document_values(array $order, array $lines, array $ship_tos, array $seller, array $extra, $money)
{
    $amount = function ($key, $always = false) use ($order, $money) {
        $minor = (int) round($order[$key] ?? 0);

        return (($minor === 0) && !$always) ? '' : (string) $money($minor);
    };

    $deliveries = array();
    $methods = array();
    $arrivals = array();

    foreach ($ship_tos as $ship_to) {
        $deliveries[] = pg_legal_document_address(array($ship_to['name'] ?? '', $ship_to['address'] ?? ''));

        if (trim((string) ($ship_to['method'] ?? '')) !== '' && !in_array($ship_to['method'], $methods, true)) {
            $methods[] = $ship_to['method'];
        }

        if (trim((string) ($ship_to['arrival_date'] ?? '')) !== '' && !in_array($ship_to['arrival_date'], $arrivals, true)) {
            $arrivals[] = $ship_to['arrival_date'];
        }
    }

    $buyer_address = pg_legal_document_address(array(
        $order['billing_address_1'] ?? '',
        $order['billing_address_2'] ?? '',
        array($order['billing_zip_code'] ?? '', $order['billing_city'] ?? '', $order['billing_state'] ?? ''),
        $order['billing_country'] ?? '',
    ));

    $discount = (int) round($order['discount'] ?? 0) + (int) round($order['gift_card_discount'] ?? 0);

    $text = array(
        'seller_name'       => (string) ($seller['name'] ?? ''),
        'seller_address'    => (string) ($seller['address'] ?? ''),
        'seller_email'      => (string) ($seller['email'] ?? ''),
        'seller_tax_office' => (string) ($seller['tax_office'] ?? ''),
        'seller_tax_number' => (string) ($seller['tax_number'] ?? ''),
        'seller_website'    => (string) ($seller['website'] ?? ''),
        'buyer_name'        => trim(($order['billing_first_name'] ?? '') . ' ' . ($order['billing_last_name'] ?? '')),
        'buyer_company'     => (string) ($order['billing_company'] ?? ''),
        'buyer_address'     => $buyer_address,
        'buyer_phone'       => (string) ($order['billing_phone_number'] ?? ''),
        'buyer_email'       => (string) ($order['billing_email_address'] ?? ''),
        // A single recipient whose address is not known yet (a download, a
        // service) is delivered to the buyer.
        'delivery_address'  => $deliveries ? implode("\n\n", array_filter($deliveries, 'strlen')) : $buyer_address,
        'shipping_method'   => implode(', ', $methods),
        'arrival_date'      => implode(', ', $arrivals),
        'subtotal'          => $amount('subtotal', true),
        'discount'          => ($discount === 0) ? '' : (string) $money($discount),
        'tax'               => $amount('tax'),
        'shipping'          => $amount('shipping'),
        'surcharge'         => $amount('surcharge'),
        'total'             => $amount('total', true),
        'payment_method'    => (string) ($extra['payment_method'] ?? ''),
        'order_number'      => (string) ($order['order_number'] ?? ''),
        'today'             => (string) ($extra['today'] ?? ''),
    );

    if ($text['delivery_address'] === '') {
        $text['delivery_address'] = $buyer_address;
    }

    return array(
        'text' => $text,
        'html' => array('order_lines' => pg_legal_document_lines_html($lines, $money)),
    );
}

/**
 * The built-in template for a type in a language: the language's own file
 * when one ships, the English one otherwise.
 *
 * @param string $type      preinfo or contract
 * @param string $language  e.g. tr, en
 * @return string  '' for an unknown type
 */
function pg_legal_document_default_template($type, $language)
{
    if (!isset(pg_legal_document_types()[$type])) {
        return '';
    }

    $language = preg_replace('/[^a-z]/', '', strtolower((string) $language));
    $folder = PG_FUNCTIONS_DIR . '/includes/legal_documents/';

    foreach (array($language, 'en') as $code) {
        if (($code !== '') && is_file($folder . $type . '.' . $code . '.html')) {
            return (string) file_get_contents($folder . $type . '.' . $code . '.html');
        }
    }

    return '';
}

/**
 * Whether checkout documents are switched on and the schema has their place.
 *
 * @return bool
 */
function pg_legal_documents_enabled()
{
    return defined('ECOMMERCE_LEGAL_DOCUMENTS')
        && (ECOMMERCE_LEGAL_DOCUMENTS === true)
        && pg_schema_has('order_documents');
}

/**
 * The stored template for a type, or the built-in one when none is stored.
 *
 * @param string $type
 * @return string
 */
function pg_legal_document_template($type)
{
    $columns = array('preinfo' => 'ecommerce_preinfo_template', 'contract' => 'ecommerce_contract_template');

    if (!isset($columns[$type])) {
        return '';
    }

    $stored = pg_schema_has('config', $columns[$type])
        ? (string) db_value("SELECT " . $columns[$type] . " FROM config LIMIT 1")
        : '';

    return (trim($stored) !== '') ? $stored : pg_legal_document_default_template($type, lang(array('info' => true)));
}

/**
 * The seller block, from the organization and ERP settings.
 *
 * @return array
 */
function pg_legal_document_seller()
{
    $constant = function ($name) {
        return defined($name) ? trim((string) constant($name)) : '';
    };

    $email = $constant('ECOMMERCE_EMAIL_ADDRESS');

    return array(
        'name'       => $constant('ORGANIZATION_NAME'),
        'address'    => pg_legal_document_address(array(
            $constant('ORGANIZATION_ADDRESS_1'),
            $constant('ORGANIZATION_ADDRESS_2'),
            array($constant('ORGANIZATION_ZIP_CODE'), $constant('ORGANIZATION_CITY'), $constant('ORGANIZATION_STATE')),
            $constant('ORGANIZATION_COUNTRY'),
        )),
        'email'      => ($email !== '') ? $email : $constant('EMAIL_ADDRESS'),
        'tax_office' => $constant('ERP_SELLER_TAX_OFFICE'),
        'tax_number' => $constant('ERP_SELLER_VKN'),
        'website'    => $constant('ERP_WEB_ADDRESS'),
    );
}

/**
 * The customer-facing name of a stored payment method.
 *
 * @param string $payment_method  one of the four orders.payment_method values
 * @return string
 */
function pg_legal_document_payment_label($payment_method)
{
    if (($payment_method === 'Offline Payment') && !empty($_SESSION['ecommerce']['offline_payment_label'])) {
        return (string) $_SESSION['ecommerce']['offline_payment_label'];
    }

    $labels = array(
        'Credit/Debit Card'       => lang('Credit/Debit Card'),
        'PayPal Express Checkout' => lang('PayPal Express Checkout'),
        'Offline Payment'         => lang('Offline Payment'),
        'Pay With Iyzico'         => lang('Pay With Iyzico'),
    );

    return isset($labels[$payment_method]) ? $labels[$payment_method] : (string) $payment_method;
}

/**
 * Fill every document type for an order from its stored rows.
 *
 * Amounts are shown in the visitor's currency, as the checkout showed them.
 *
 * @param int $order_id
 * @return array  type => html; empty when the order is not found
 */
function pg_legal_documents_for_order($order_id)
{
    $order_id = (int) $order_id;

    $order = db_item(
        "SELECT
            orders.order_number,
            orders.billing_first_name,
            orders.billing_last_name,
            orders.billing_company,
            orders.billing_address_1,
            orders.billing_address_2,
            orders.billing_city,
            orders.billing_state,
            orders.billing_zip_code,
            IFNULL(countries.name, orders.billing_country) AS billing_country,
            orders.billing_phone_number,
            orders.billing_email_address,
            orders.subtotal,
            orders.discount,
            orders.gift_card_discount,
            orders.tax,
            orders.shipping,
            orders.surcharge,
            orders.total,
            orders.payment_method
        FROM orders
        LEFT JOIN countries ON countries.code = orders.billing_country
        WHERE orders.id = '" . $order_id . "'"
    );

    if (!$order) {
        return array();
    }

    $lines = array();

    foreach ((array) db_items("SELECT product_name, quantity, price FROM order_items WHERE order_id = '" . $order_id . "' ORDER BY id") as $item) {
        $quantity = (int) $item['quantity'];
        $lines[] = array(
            'name'       => $item['product_name'],
            'quantity'   => $quantity,
            'unit_price' => (int) round($item['price']),
            'line_total' => (int) round($item['price'] * $quantity),
        );
    }

    $ship_tos = array();

    foreach ((array) db_items(
        "SELECT
            ship_tos.first_name,
            ship_tos.last_name,
            ship_tos.company,
            ship_tos.address_1,
            ship_tos.address_2,
            ship_tos.city,
            ship_tos.state,
            ship_tos.zip_code,
            IFNULL(countries.name, ship_tos.country) AS country,
            ship_tos.arrival_date,
            shipping_methods.name AS shipping_method_name
        FROM ship_tos
        LEFT JOIN shipping_methods ON shipping_methods.id = ship_tos.shipping_method_id
        LEFT JOIN countries ON countries.code = ship_tos.country
        WHERE ship_tos.order_id = '" . $order_id . "'
        ORDER BY ship_tos.id"
    ) as $ship_to) {
        $arrival = (string) $ship_to['arrival_date'];

        $ship_tos[] = array(
            'name'         => trim($ship_to['first_name'] . ' ' . $ship_to['last_name'] . (($ship_to['company'] !== '') ? ' (' . $ship_to['company'] . ')' : '')),
            'address'      => pg_legal_document_address(array(
                $ship_to['address_1'],
                $ship_to['address_2'],
                array($ship_to['zip_code'], $ship_to['city'], $ship_to['state']),
                $ship_to['country'],
            )),
            'method'       => (string) $ship_to['shipping_method_name'],
            'arrival_date' => (($arrival !== '') && ($arrival !== '0000-00-00')) ? prepare_form_data_for_output($arrival, 'date', false) : '',
        );
    }

    // The plain-text price keeps a symbol stored as an entity (&#8378;); the
    // renderer escapes every value, so the entity becomes the character here.
    $money = function ($minor) {
        return html_entity_decode(prepare_price_for_output($minor, false, '', 'plain_text'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    };

    $values = pg_legal_document_values(
        $order,
        $lines,
        $ship_tos,
        pg_legal_document_seller(),
        array(
            'payment_method' => pg_legal_document_payment_label((string) $order['payment_method']),
            'today'          => prepare_form_data_for_output(date('Y-m-d'), 'date', false),
        ),
        $money
    );

    $documents = array();

    foreach (array_keys(pg_legal_document_types()) as $type) {
        $documents[$type] = pg_legal_document_render(pg_legal_document_template($type), $values['text'], $values['html']);
    }

    return $documents;
}

/**
 * Keep the filled documents with a placed order.
 *
 * Runs once the order row has its number. Every type is stored, accepted or
 * not, so the order shows what was in front of the customer; accepted_at is
 * set only for the types the customer ticked. A second call for the same
 * order stores nothing (the copy is the one from the moment of the order).
 *
 * @param int      $order_id
 * @param string[] $accepted_types  types the customer accepted
 * @return int  number of documents stored
 */
function pg_legal_documents_store($order_id, array $accepted_types)
{
    $order_id = (int) $order_id;

    if (($order_id <= 0) || !pg_legal_documents_enabled()) {
        return 0;
    }

    if (db_value("SELECT COUNT(*) FROM order_documents WHERE order_id = '" . $order_id . "' AND type IN ('preinfo', 'contract')")) {
        return 0;
    }

    $now = time();
    $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $agent = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    $stored = 0;

    foreach (pg_legal_documents_for_order($order_id) as $type => $html) {
        if (trim($html) === '') {
            continue;
        }

        $accepted = in_array($type, $accepted_types, true) ? "'" . $now . "'" : 'NULL';

        if (db(
            "INSERT INTO order_documents (order_id, type, html, accepted_at, ip, user_agent, created_at)
            VALUES ('" . $order_id . "', '" . e($type) . "', '" . e($html) . "', " . $accepted . ", '" . e($ip) . "', '" . e($agent) . "', '" . $now . "')"
        ) !== false) {
            $stored++;
        }
    }

    return $stored;
}
