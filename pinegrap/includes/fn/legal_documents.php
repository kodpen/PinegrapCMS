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
 * The name an ordered item goes by in the documents: what the shop calls the
 * product, its short description, as the cart and the order receipt show it.
 * order_items.product_name is the Product ID / SKU at the time of sale; it is
 * printed only when the product has no short description or no longer exists.
 *
 * @param string|null $short_description  products.short_description, null for a deleted product
 * @param string|null $product_name       order_items.product_name
 * @return string
 */
function pg_legal_document_item_name($short_description, $product_name)
{
    $short_description = trim((string) $short_description);

    return ($short_description !== '') ? $short_description : (string) $product_name;
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
 * A cart's order fields for the documents shown at checkout.
 *
 * A cart row has no totals until the order is placed. Subtotal, tax and
 * shipping are the sums of its item and recipient rows (the same sums the
 * placed order stores); a recurring line that starts later is charged on its
 * own once it starts and counts in none of them, as at the checkout
 * (pg_order_line_charged_today()). The total is the one the checkout page
 * showed and submit_order.php compares against, so offers, gift cards and
 * rounding are the checkout's own; the difference to the row sums is placed
 * below. The cart's order discount ($seen['order_discount']) is taken first
 * and the tax taken again per rate on what it leaves of the lines
 * (pg_order_charged_tax()), so the discount and the tax read as the
 * checkout's.
 * Billing fields the visitor has typed or the page filled in replace the
 * row's, which a cart has only after an earlier submit. A field the page drew
 * empty says nothing and keeps the row's value; a field the checkout link
 * posted empty ($seen['typed'], pg_legal_document_typed_snapshot()) is one
 * the visitor cleared, and clears it.
 *
 * @param array $order     orders row fields
 * @param array $lines     each with line_total and tax (minor units), rate (the line's tax rate,
 *                         null when not kept) and charged (false for a line charged later; true
 *                         when missing), in the order of their ids
 * @param array $ship_tos  each with cost (minor units)
 * @param array $seen      total (minor units or null), order_discount (minor units), billing (field => value), payment_method, typed (bool),
 *                         settle_rates (bool, pg_order_charges_settle_rates())
 * @return array  the order fields with amounts, billing and payment method filled
 */
function pg_legal_document_cart_order(array $order, array $lines, array $ship_tos, array $seen)
{
    $subtotal = 0;
    $tax = 0;
    $shipping = 0;
    $charged = array();

    foreach ($lines as $line) {
        if (isset($line['charged']) && !$line['charged']) {
            continue;
        }

        $subtotal += (int) round($line['line_total'] ?? 0);
        $tax += (int) round($line['tax'] ?? 0);
        $charged[] = array(
            'amount' => (int) round($line['line_total'] ?? 0),
            'rate' => $line['rate'] ?? null,
            'tax' => (int) round($line['tax'] ?? 0),
        );
    }

    foreach ($ship_tos as $ship_to) {
        $shipping += (int) round($ship_to['cost'] ?? 0);
    }

    // The order discount the cart worked out (apply_offers_to_cart()) lowers
    // the tax as submit_order.php and the checkout pages lower it: taken
    // again per rate on what the discount leaves of the lines; never more
    // than the subtotal.
    $order_discount = (int) round((float) ($seen['order_discount'] ?? 0));
    $order_discount = max(0, min($order_discount, max(0, $subtotal)));

    if (($order_discount > 0) && ($subtotal > 0)) {
        $tax = pg_order_charged_tax($charged, $order_discount);
    }

    // Where the shipping holds tax on the order's invoice it is a minor unit
    // lower when it would leave a rate's total no amount reaches, as the
    // checkout shows and submit_order.php charges it
    // (pg_order_charges_reachable()).
    if (!empty($seen['settle_rates']) && ($shipping > 0)) {
        $shipping = pg_order_charges_reachable($charged, $order_discount, array('shipping' => $shipping))['shipping'];
    }

    $goods = $subtotal - $order_discount + $tax;
    $before = $goods + $shipping;
    $total = (isset($seen['total']) && is_int($seen['total']) && ($seen['total'] >= 0)) ? $seen['total'] : $before;
    $discount = $order_discount;

    // A recipient row keeps the cost of a shipping method the checkout no
    // longer offers or has not had chosen yet; the checkout then shows no
    // shipping, and its total is the goods with their tax. Any other shortfall
    // is what else the checkout took off (gift cards); a higher total is
    // shipping the rows do not know yet.
    if (($total !== $before) && ($total === $goods)) {
        $shipping = 0;
    } elseif ($total < $before) {
        $discount += $before - $total;
    } elseif ($total > $before) {
        $shipping += $total - $before;
    }

    $order['subtotal'] = $subtotal;
    $order['tax'] = $tax;
    $order['shipping'] = $shipping;
    $order['discount'] = $discount;
    $order['gift_card_discount'] = 0;
    // The card surcharge depends on the payment method chosen at the very
    // end; the total shown is the one without it.
    $order['surcharge'] = 0;
    $order['total'] = $total;
    $order['order_number'] = '';

    $billing = (isset($seen['billing']) && is_array($seen['billing'])) ? $seen['billing'] : array();
    $typed = !empty($seen['typed']);

    foreach (pg_legal_document_billing_fields() as $field) {
        // The country arrives as a code; the caller names it.
        if (($field === 'billing_country') || !array_key_exists($field, $billing)) {
            continue;
        }

        $value = trim((string) $billing[$field]);

        if (($value !== '') || $typed) {
            $order[$field] = $value;
        }
    }

    if (trim((string) ($seen['payment_method'] ?? '')) !== '') {
        $order['payment_method'] = (string) $seen['payment_method'];
    }

    return $order;
}

/**
 * The billing fields a checkout page posts and the documents print.
 *
 * @return string[]
 */
function pg_legal_document_billing_fields()
{
    return array(
        'billing_first_name', 'billing_last_name', 'billing_company',
        'billing_address_1', 'billing_address_2', 'billing_city', 'billing_state',
        'billing_zip_code', 'billing_country', 'billing_phone_number', 'billing_email_address',
    );
}

/**
 * What a rendered checkout page shows: its total, the billing values in its
 * fields and the payment method ticked.
 *
 * The total is the hidden "total" field (major units, as submit_order.php
 * reads it). Only input values and the selected option of the country list
 * are read; a field the page draws empty is left out.
 *
 * @param string $html
 * @return array  total (minor units or null), billing (field => value), payment_method
 */
function pg_legal_document_form_snapshot($html)
{
    $html = (string) $html;
    $wanted = array_flip(pg_legal_document_billing_fields());
    $snapshot = array('total' => null, 'billing' => array(), 'payment_method' => '');

    $attribute = function ($tag, $name) {
        if (preg_match('/\s' . $name . '\s*=\s*"([^"]*)"/i', $tag, $match) || preg_match("/\\s" . $name . "\\s*=\\s*'([^']*)'/i", $tag, $match)) {
            return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return null;
    };

    if (preg_match_all('/<input\b[^>]*>/i', $html, $inputs)) {
        foreach ($inputs[0] as $tag) {
            $name = $attribute($tag, 'name');
            $value = $attribute($tag, 'value');
            $type = strtolower((string) $attribute($tag, 'type'));

            if (($name === null) || ($value === null)) {
                continue;
            }

            if (($name === 'total') && ($snapshot['total'] === null)) {
                $amount = str_replace(',', '', trim($value));

                if (is_numeric($amount)) {
                    $snapshot['total'] = (int) round((float) $amount * 100);
                }
            } elseif (($name === 'payment_method') && in_array($type, array('radio', 'checkbox'), true)) {
                if (preg_match('/\schecked\b/i', $tag)) {
                    $snapshot['payment_method'] = $value;
                }
            } elseif (isset($wanted[$name]) && !in_array($type, array('radio', 'checkbox', 'hidden'), true) && (trim($value) !== '')) {
                $snapshot['billing'][$name] = $value;
            }
        }
    }

    if (preg_match('/<select\b[^>]*\sname\s*=\s*"billing_country"[^>]*>(.*?)<\/select>/is', $html, $select)
        && preg_match('/<option\b[^>]*\sselected\b[^>]*>/i', $select[1], $option)) {
        $code = $attribute($option[0], 'value');

        if (($code !== null) && (trim($code) !== '')) {
            $snapshot['billing']['billing_country'] = trim($code);
        }
    }

    return $snapshot;
}

/**
 * The page's snapshot with what the checkout form holds now laid over it.
 *
 * The checkout's document link posts the form's billing fields and payment
 * method at the moment of the click (assets/js/pg_legal_documents.js), so
 * the document shows what the visitor has typed but not yet sent. Only the
 * billing fields and a known payment method are taken, each as one line of
 * plain text of bounded length. A field that was posted, empty included,
 * replaces the snapshot's and marks the result `typed`: an empty one is a
 * value the visitor cleared. The total stays the page's: the form's hidden
 * total can carry an instalment fee the documents do not count as part of it.
 *
 * Nothing here is stored; the values are for the one page they were posted to.
 *
 * @param array $seen    pg_legal_document_form_snapshot() for this cart, or array()
 * @param array $posted  the request's fields
 * @return array  total, billing, payment_method, typed
 */
function pg_legal_document_typed_snapshot(array $seen, array $posted)
{
    $seen += array('total' => null, 'billing' => array(), 'payment_method' => '');

    if (!is_array($seen['billing'])) {
        $seen['billing'] = array();
    }

    foreach (pg_legal_document_billing_fields() as $field) {
        if (!isset($posted[$field]) || !is_string($posted[$field])) {
            continue;
        }

        // One line of text, as the input holds it: no control characters,
        // and no longer than a field of the order could hold. A value that is
        // not UTF-8 is left out rather than cut into one that is.
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $posted[$field]);

        if ($value === null) {
            continue;
        }

        $seen['billing'][$field] = mb_substr(trim($value), 0, ($field === 'billing_country') ? 10 : 255, 'UTF-8');
        $seen['typed'] = true;
    }

    if (isset($posted['payment_method']) && is_string($posted['payment_method'])
        && isset(pg_legal_document_payment_methods()[$posted['payment_method']])) {
        $seen['payment_method'] = $posted['payment_method'];
    }

    return $seen;
}

/**
 * Does a posted form token belong to this session? The comparison
 * validate_token_field() makes, answered rather than enforced: the document
 * page falls back to the values it was drawn with instead of failing.
 *
 * @param mixed $session_token
 * @param mixed $posted_token
 * @return bool
 */
function pg_legal_document_token_matches($session_token, $posted_token)
{
    return is_string($session_token) && is_string($posted_token)
        && ($session_token !== '') && hash_equals($session_token, $posted_token);
}

/**
 * The two acceptance checkboxes for a checkout form, each linking to its
 * document filled from the visitor's cart.
 *
 * @param array  $checked  field => value from the visitor's last submit
 * @param string $url      the document page's address, without the type
 * @return string
 */
function pg_legal_documents_checkboxes_html(array $checked, $url)
{
    $labels = array(
        'preinfo'  => lang('the Pre-Information Form'),
        'contract' => lang('the Distance Sales Contract'),
    );

    $rows = '';

    foreach ($labels as $type => $label) {
        $field = 'legal_' . $type;
        $link = '<a href="' . h($url . (strpos($url, '?') === false ? '?' : '&') . 'type=' . $type) . '" target="_blank" rel="noopener">' . h($label) . '</a>';
        $text = ($type === 'preinfo')
            ? lang(array('string' => 'I have read and approve {var:1}.', 'vars' => array($link)))
            : lang(array('string' => 'I have read and accept {var:1}.', 'vars' => array($link)));

        $rows .= '<div class="form-check pg-legal-document">'
            . '<input class="form-check-input software_input_checkbox" type="checkbox" name="' . $field . '" id="' . $field . '" value="1" required'
            . (((string) ($checked[$field] ?? '') === '1') ? ' checked' : '') . '>'
            . ' <label class="form-check-label" for="' . $field . '">' . $text . '</label>'
            . '</div>';
    }

    return '<div class="pg-legal-documents mb-3">' . $rows . '</div>';
}

/**
 * Put a block in front of a checkout form's purchase button (the tag named
 * submit_purchase_now). A page without that button is returned as it is.
 *
 * @param string $html
 * @param string $block
 * @return string
 */
function pg_legal_documents_insert_before_purchase($html, $block)
{
    $html = (string) $html;
    $at = strpos($html, 'name="submit_purchase_now"');

    if ($at === false) {
        return $html;
    }

    $tag = strrpos(substr($html, 0, $at), '<');

    if ($tag === false) {
        return $html;
    }

    return substr($html, 0, $tag) . $block . substr($html, $tag);
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
 * Whether a site installed from a starter site begins with the checkout
 * documents switched on. Turkish distance-selling rules require both
 * documents, so a Turkish starter site starts with them; its template
 * columns stay empty and read the built-in templates. Any other
 * language, and a restored backup (no starter language), starts off, as an
 * upgrade does.
 *
 * @param string $starter_language  the starter site's language, '' for a backup
 * @return bool
 */
function pg_legal_documents_on_for_new_site($starter_language)
{
    return strtolower(trim((string) $starter_language)) === 'tr';
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
 * The payment methods an order can carry (orders.payment_method), with the
 * name a customer reads.
 *
 * @return array  stored value => label
 */
function pg_legal_document_payment_methods()
{
    $methods = array(
        'Credit/Debit Card'       => lang('Credit/Debit Card'),
        'PayPal Express Checkout' => lang('PayPal Express Checkout'),
        'Offline Payment'         => lang('Offline Payment'),
        'Pay With Iyzico'         => lang('Pay With Iyzico'),
    );

    // Each redirect driver's method, under the name the service writes.
    foreach (array_keys(pg_payment_driver_methods()) as $method) {
        $methods[$method] = pg_payment_method_label($method);
    }

    return $methods;
}

/**
 * The customer-facing name of a stored payment method.
 *
 * @param string $payment_method  an orders.payment_method value
 * @return string
 */
function pg_legal_document_payment_label($payment_method)
{
    if (($payment_method === 'Offline Payment') && !empty($_SESSION['ecommerce']['offline_payment_label'])) {
        return (string) $_SESSION['ecommerce']['offline_payment_label'];
    }

    $labels = pg_legal_document_payment_methods();

    return isset($labels[$payment_method]) ? $labels[$payment_method] : (string) $payment_method;
}

/**
 * Fill every document type for an order from its stored rows.
 *
 * Amounts are shown in the visitor's currency, as the checkout showed them.
 * A placed order is read as stored. A cart (status incomplete) carries no
 * totals on its row yet, so its amounts come from its item and recipient
 * rows and from what the checkout page showed ($seen, see
 * pg_legal_document_cart_order()).
 *
 * @param int   $order_id
 * @param array $seen  the checkout page's snapshot for this cart
 * @return array  type => html; empty when the order is not found
 */
function pg_legal_documents_for_order($order_id, array $seen = array())
{
    $order_id = (int) $order_id;

    $order = db_item(
        "SELECT
            orders.status,
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
    // A cart's totals count the lines the order charges when it is placed, as
    // the checkout's do (submit_order.php); a placed order's are stored.
    $clearcommerce = defined('ECOMMERCE_CREDIT_DEBIT_CARD') && ECOMMERCE_CREDIT_DEBIT_CARD
        && defined('ECOMMERCE_PAYMENT_GATEWAY') && (ECOMMERCE_PAYMENT_GATEWAY == 'ClearCommerce');
    $today = date('Y-m-d');

    // A line saved for later is no part of the order.
    foreach ((array) db_items(
        "SELECT
            order_items.product_name,
            products.short_description,
            order_items.quantity,
            order_items.price,
            order_items.tax_total,
            order_items.recurring_start_date,
            products.recurring,
            products.start" . (pg_schema_has('order_items', 'tax_rate') ? ",
            order_items.tax_rate" : "") . "
        FROM order_items
        LEFT JOIN products ON products.id = order_items.product_id
        WHERE order_items.order_id = '" . $order_id . "'" . pg_order_item_active_sql() . "
        ORDER BY order_items.id"
    ) as $item) {
        $quantity = (int) $item['quantity'];
        $lines[] = array(
            'name'       => pg_legal_document_item_name($item['short_description'], $item['product_name']),
            'quantity'   => $quantity,
            'unit_price' => (int) round($item['price']),
            'line_total' => (int) round($item['price'] * $quantity),
            'tax'        => (int) round((float) $item['tax_total']),
            'rate'       => $item['tax_rate'] ?? null,
            'charged'    => pg_order_line_charged_today($item['recurring'], $item['recurring_start_date'], $item['start'], $today, $clearcommerce),
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
            ship_tos.shipping_cost,
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
            'cost'         => (int) round((float) $ship_to['shipping_cost']),
        );
    }

    if ($order['status'] === 'incomplete') {
        if (!array_key_exists('settle_rates', $seen)) {
            $seen['settle_rates'] = pg_order_charges_settle_rates();
        }

        $order = pg_legal_document_cart_order($order, $lines, $ship_tos, $seen);

        // The checkout posts a country code; the documents name the country.
        // A country the visitor took off the form (typed, empty) is none.
        $seen_country = trim((string) ($seen['billing']['billing_country'] ?? ''));

        if ($seen_country !== '') {
            $country_name = (string) db_value("SELECT name FROM countries WHERE code = '" . e($seen_country) . "' LIMIT 1");
            $order['billing_country'] = ($country_name !== '') ? $country_name : $seen_country;
        } elseif (!empty($seen['typed']) && is_array($seen['billing'] ?? null) && array_key_exists('billing_country', $seen['billing'])) {
            $order['billing_country'] = '';
        }
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
 * Each stored and accepted type also writes a granted contract row to the
 * consent log (pg_legal_documents_consent_events()).
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
    // The visitor's address as the firewall resolves it: behind a CDN or a
    // trusted proxy REMOTE_ADDR is the proxy, the same for every customer.
    $ip = substr((string) (function_exists('waf_client_ip') ? waf_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? '')), 0, 45);
    $agent = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    $stored = array();

    foreach (pg_legal_documents_for_order($order_id) as $type => $html) {
        if (trim($html) === '') {
            continue;
        }

        $accepted = in_array($type, $accepted_types, true) ? "'" . $now . "'" : 'NULL';

        if (db(
            "INSERT INTO order_documents (order_id, type, html, accepted_at, ip, user_agent, created_at)
            VALUES ('" . $order_id . "', '" . e($type) . "', '" . e($html) . "', " . $accepted . ", '" . e($ip) . "', '" . e($agent) . "', '" . $now . "')"
        ) !== false) {
            $stored[] = (string) $type;
        }
    }

    // Each accepted document is also a consent the buyer gave, kept in the
    // consent log as proof that outlives the order's personal fields.
    if ($stored && function_exists('pg_consent_log_record')) {
        $order = db_item("SELECT billing_email_address, user_id, contact_id FROM orders WHERE id = '" . $order_id . "'");

        foreach (pg_legal_documents_consent_events(is_array($order) ? $order : array(), $stored, $accepted_types, $ip, $agent, $now) as $event) {
            pg_consent_log_record($event);
        }
    }

    return count($stored);
}

/**
 * The consent rows an order's stored documents give: one granted row on the
 * contract channel per type that was both stored and accepted, for the
 * order's buyer, with the ip, user agent and time the document was kept with.
 *
 * @param array    $order           orders row: billing_email_address, user_id, contact_id
 * @param string[] $stored_types    types written to order_documents
 * @param string[] $accepted_types  types the customer accepted
 * @param string   $ip
 * @param string   $agent
 * @param int      $now             the documents' accepted_at
 * @return array[]  events for pg_consent_log_record()
 */
function pg_legal_documents_consent_events(array $order, array $stored_types, array $accepted_types, $ip, $agent, $now)
{
    $events = array();

    foreach ($stored_types as $type) {
        if (!in_array($type, $accepted_types, true)) {
            continue;
        }

        $events[] = array(
            'channel'    => 'contract',
            'purpose'    => (string) $type,
            'status'     => 'granted',
            'source'     => 'checkout',
            'email'      => (string) ($order['billing_email_address'] ?? ''),
            'user_id'    => (int) ($order['user_id'] ?? 0),
            'contact_id' => (int) ($order['contact_id'] ?? 0),
            'ip'         => (string) $ip,
            'user_agent' => (string) $agent,
            'created_at' => (int) $now,
        );
    }

    return $events;
}

/**
 * A liveform's values as the visitor last submitted them.
 *
 * Read before a checkout renderer runs: the designer's checkout drops the
 * form from the session while it draws its messages.
 *
 * @param string $form  liveform name (express_order, order_preview)
 * @return array  field => value
 */
function pg_legal_documents_form_values($form)
{
    $values = array();

    foreach ((array) ($_SESSION['software']['liveforms'][(string) $form][0] ?? array()) as $field => $data) {
        if (is_array($data) && isset($data['value']) && is_scalar($data['value'])) {
            $values[$field] = (string) $data['value'];
        }
    }

    return $values;
}

/**
 * The address of the page that shows a document filled from the cart.
 *
 * @return string
 */
function pg_legal_document_page_url()
{
    // Unescaped: pg_legal_documents_checkboxes_html() escapes the link once.
    return PATH . SOFTWARE_DIRECTORY . '/legal_document.php';
}

/**
 * The address of the cart's document of $type on the checkout being drawn,
 * in the language that checkout is served in.
 *
 * The document page is a script of its own, not a routed page, so it learns
 * the language only from pg_lang (init.php), as a form posted from a page in
 * another language does. Without it a visitor on /en/... read the document in
 * the source language, while the copy kept with the order at submit (whose
 * form carries pg_lang) was the target language's: two different texts.
 *
 * @param string      $type      a key of pg_legal_document_types(); '' for the
 *                               address the checkboxes add their type to
 * @param string|null $language  pg_lang value, '' for none; null for the
 *                               language the current page is drawn in
 * @param string|null $page_url  the document page's address; null for the site's
 * @return string  unescaped
 */
function pg_legal_document_cart_url($type = '', $language = null, $page_url = null)
{
    if ($page_url === null) {
        $page_url = pg_legal_document_page_url();
    }

    if ($language === null) {
        $language = (function_exists('pg_tr_active') && pg_tr_active()) ? pg_tr_language() : '';
    }

    $query = array();

    if ((string) $type !== '') {
        $query[] = 'type=' . rawurlencode((string) $type);
    }

    if ((string) $language !== '') {
        $query[] = 'pg_lang=' . rawurlencode((string) $language);
    }

    return $page_url . ($query ? '?' . implode('&', $query) : '');
}

/**
 * Give a rendered checkout page its acceptance checkboxes.
 *
 * Every checkout (the designer's express order, the classic express order
 * and the classic order preview) passes its output through here. The page's
 * total and billing values are kept for the document page the checkboxes
 * link to, so the documents show what the visitor is looking at. A page that
 * already carries the fields (a designer tree that binds them) gets none
 * added; a page without a purchase button needs none. Either way a page with
 * the documents gets the script that makes their links post what the visitor
 * has typed since the page was drawn.
 *
 * @param string $html
 * @param array  $values  pg_legal_documents_form_values() taken before rendering
 * @return string
 */
function pg_legal_documents_inject($html, array $values = array())
{
    $html = (string) $html;

    if ((strpos($html, 'name="submit_purchase_now"') === false) || !pg_legal_documents_enabled()) {
        return $html;
    }

    $_SESSION['ecommerce']['legal_documents_seen'] = array('order_id' => (int) ($_SESSION['ecommerce']['order_id'] ?? 0)) + pg_legal_document_form_snapshot($html);

    if (strpos($html, 'name="legal_preinfo"') !== false) {
        return $html . pg_legal_documents_script();
    }

    return pg_legal_documents_insert_before_purchase($html, pg_legal_documents_checkboxes_html($values, pg_legal_document_cart_url())) . pg_legal_documents_script();
}

/**
 * The script tag of assets/js/pg_legal_documents.js, stamped with the file's
 * time: a click on a document link posts the checkout form's billing fields
 * and payment method to the document page, so the document is filled with
 * what the form holds at that moment. Without the script, or opened in a new
 * tab by a modified click, the link stays a plain GET.
 *
 * @param string|null $base  the software directory's address; null for the site's
 * @return string
 */
function pg_legal_documents_script($base = null)
{
    $file = '/assets/js/pg_legal_documents.js';

    if ($base === null) {
        $base = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY;
    }

    return '<script src="' . h($base . $file . '?v=' . (int) @filemtime(PG_FUNCTIONS_DIR . $file)) . '" defer></script>';
}

/**
 * The documents for the visitor's current cart, as the checkout shows them.
 *
 * @param array $posted  the fields the checkout's document link posted (see
 *                       pg_legal_document_typed_snapshot()); empty for the
 *                       values the page was drawn with
 * @return array  type => html; empty without a cart
 */
function pg_legal_documents_for_cart(array $posted = array())
{
    $order_id = (int) ($_SESSION['ecommerce']['order_id'] ?? 0);

    if ($order_id <= 0) {
        return array();
    }

    $seen = (array) ($_SESSION['ecommerce']['legal_documents_seen'] ?? array());

    if ((int) ($seen['order_id'] ?? 0) !== $order_id) {
        $seen = array();
    }

    if (!empty($posted)) {
        $seen = pg_legal_document_typed_snapshot($seen, $posted);
    }

    // The cart's order discount as the checkout last worked it out; the
    // documents split it from the tax the way the checkout does.
    $seen['order_discount'] = (int) round((float) ($_SESSION['ecommerce']['order_discount'] ?? 0));

    return pg_legal_documents_for_order($order_id, $seen);
}


/**
 * A document as a page of its own: the shape the cart document page, a
 * stored copy and an e-mail attachment share.
 *
 * @param string $title     escaped here
 * @param string $body      the document's markup
 * @param string $language  html lang code
 * @param string $note      plain text shown under the document ('' for none)
 * @return string
 */
function pg_legal_document_page_html($title, $body, $language, $note = '')
{
    return '<!DOCTYPE html>
<html lang="' . h((string) $language) . '">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>' . h((string) $title) . '</title>
<style>
body { margin: 0; padding: 1.5rem 1rem 3rem; background: #fff; color: #212529; font: 15px/1.6 system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; }
main { max-width: 52rem; margin: 0 auto; }
h2 { font-size: 1.5rem; margin: 0 0 1rem; }
h3 { font-size: 1.05rem; margin: 1.5rem 0 .5rem; }
table { width: 100%; border-collapse: collapse; margin: .5rem 0 1rem; }
th, td { padding: .35rem .5rem; border-bottom: 1px solid #dee2e6; vertical-align: top; }
th { font-weight: 600; }
.pg-legal-empty, .pg-legal-note { padding: 1rem; border: 1px solid #dee2e6; border-radius: .375rem; }
.pg-legal-note { margin-top: 2rem; color: #495057; }
@media print { body { padding: 0; } }
</style>
</head>
<body>
<main>' . $body . ((trim((string) $note) !== '') ? '<p class="pg-legal-note">' . h((string) $note) . '</p>' : '') . '</main>
</body>
</html>';
}

/**
 * The file name an e-mailed copy is attached under: the document's name and
 * the order number, ASCII only (a Turkish name would reach some mail clients
 * as "=?utf-8?...").
 *
 * @param string $label         the document's name
 * @param string $order_number
 * @return string
 */
function pg_legal_document_file_name($label, $order_number)
{
    $base = strtolower(pg_transliterate_to_ascii((string) $label));
    $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', $base), '-');
    $number = preg_replace('/[^0-9A-Za-z]+/', '', (string) $order_number);

    return (($base !== '') ? $base : 'document') . (($number !== '') ? '-' . $number : '') . '.html';
}

/**
 * A moment as the documents print it: the date in the site's order, the
 * time on the site's clock (Site Settings: 12 or 24 hours). The form
 * formatter (prepare_form_data_for_output()) prints AM/PM whatever the
 * setting.
 *
 * @param int $timestamp
 * @return string
 */
function pg_legal_document_time_text($timestamp)
{
    $date = (defined('DATE_FORMAT') && (DATE_FORMAT == 'month_day')) ? 'n/j/Y' : 'j/n/Y';
    $clock = (defined('TIME_FORMAT') && (TIME_FORMAT == 'twelve_hours')) ? 'g:i A' : 'H:i';

    return date($date . ' ' . $clock, (int) $timestamp);
}

/**
 * The note printed under a stored copy: when it was accepted, or that it
 * was not.
 *
 * @param array $row  order_documents row
 * @return string
 */
function pg_legal_document_acceptance_note(array $row)
{
    $accepted = (int) ($row['accepted_at'] ?? 0);

    if ($accepted <= 0) {
        return lang('This copy was kept with the order without being accepted.');
    }

    return lang(array('string' => 'Accepted by the customer on {var:1}.', 'vars' => array(pg_legal_document_time_text($accepted))));
}

/**
 * The documents kept with an order, by type.
 *
 * @param int $order_id
 * @return array  type => order_documents row; empty without the table
 */
function pg_legal_documents_of_order($order_id)
{
    $order_id = (int) $order_id;
    $documents = array();

    if (($order_id <= 0) || !pg_schema_has('order_documents')) {
        return $documents;
    }

    foreach ((array) db_items("SELECT id, type, html, accepted_at, ip, user_agent, created_at FROM order_documents WHERE order_id = '" . $order_id . "' ORDER BY id") as $row) {
        if (!isset($documents[$row['type']])) {
            $documents[$row['type']] = $row;
        }
    }

    return $documents;
}

/**
 * Whether the visitor may read the documents of a placed order: the
 * customer the order belongs to, the guest who has just placed it (the
 * receipt's order in the session), or staff who manage the store.
 *
 * @param array $order  orders row with id and user_id
 * @return bool
 */
function pg_legal_document_may_view(array $order)
{
    if (defined('USER_MANAGE_ECOMMERCE') && (USER_MANAGE_ECOMMERCE === true)) {
        return true;
    }

    $user_id = defined('USER_ID') ? (int) USER_ID : 0;

    if (($user_id > 0) && ((int) ($order['user_id'] ?? 0) === $user_id)) {
        return true;
    }

    $completed = (int) ($_SESSION['ecommerce']['completed_order_id'] ?? 0);

    return ($completed > 0) && ($completed === (int) ($order['id'] ?? 0));
}

/**
 * The kept copies of an order as e-mail attachments, one standalone HTML
 * page each, for the order receipt.
 *
 * @param int    $order_id
 * @param string $order_number
 * @return array  attachments for email(): name, content, type
 */
function pg_legal_documents_attachments($order_id, $order_number)
{
    $types = pg_legal_document_types();
    $attachments = array();

    foreach (pg_legal_documents_of_order($order_id) as $type => $row) {
        if (!isset($types[$type]) || (trim((string) $row['html']) === '')) {
            continue;
        }

        $attachments[] = array(
            'name'    => pg_legal_document_file_name($types[$type], $order_number),
            'content' => pg_legal_document_page_html($types[$type], (string) $row['html'], lang(array('info' => true)), pg_legal_document_acceptance_note($row)),
            'type'    => 'text/html',
        );
    }

    return $attachments;
}

/**
 * The address of a document's copy kept with a placed order: the document
 * page with the order's id and the type. Unescaped.
 *
 * @param int         $order_id
 * @param string      $type      a key of pg_legal_document_types()
 * @param string|null $page_url  the document page's address; null for the site's
 * @return string
 */
function pg_legal_document_copy_url($order_id, $type, $page_url = null)
{
    if ($page_url === null) {
        $page_url = pg_legal_document_page_url();
    }

    return $page_url . '?order_id=' . (int) $order_id . '&type=' . rawurlencode((string) $type);
}

/**
 * The links a customer's order view gives to the copies kept with the
 * order: one per document type, '' for a type the order has no copy of.
 *
 * @param array       $kept      type => order_documents row (pg_legal_documents_of_order())
 * @param int         $order_id
 * @param string|null $page_url  the document page's address; null for the site's
 * @return array  type => address (unescaped), every type of pg_legal_document_types() in its order
 */
function pg_legal_document_copy_urls(array $kept, $order_id, $page_url = null)
{
    $urls = array();

    foreach (array_keys(pg_legal_document_types()) as $type) {
        $urls[$type] = isset($kept[$type]) ? pg_legal_document_copy_url($order_id, $type, $page_url) : '';
    }

    return $urls;
}

/**
 * The last day of an order's right of withdrawal, from the withdrawal window
 * of each of its deliveries (pg_return_window()).
 *
 * For the goods of one order delivered separately the 14 days run from the
 * last delivery, so the latest deadline is the order's. A delivery with no
 * known or planned date leaves the last delivery unknown: no date is given
 * rather than one that could be too early. The date is an estimate while any
 * delivery is estimated, since that delivery may still arrive later.
 *
 * @param array $windows  pg_return_window() of every delivery (null for one
 *                        with no date)
 * @return array|null  deadline (Y-m-d), estimated, open; null when the order
 *                     has no delivery or one of them has no date
 */
function pg_legal_withdrawal_deadline(array $windows)
{
    $last = null;
    $estimated = false;

    foreach ($windows as $window) {
        if (!is_array($window) || !isset($window['deadline']) || (pg_return_date((string) $window['deadline']) === '')) {
            return null;
        }

        if (!empty($window['estimated'])) {
            $estimated = true;
        }

        if (($last === null) || (strcmp($window['deadline'], $last['deadline']) > 0)) {
            $last = $window;
        }
    }

    if ($last === null) {
        return null;
    }

    return array(
        'deadline'  => (string) $last['deadline'],
        'estimated' => $estimated,
        'open'      => !empty($last['open']),
    );
}

/**
 * The withdrawal deadline of an order, read from its deliveries.
 *
 * Only recipients with goods to ship count (an order of downloads or services
 * alone has no delivery to start the period). A cancelled order has none.
 * Each delivery's window is the returns flow's (pg_return_delivery_state()),
 * so the arrival dates of its parcels count: a known delivery once every
 * parcel has arrived, and the latest arrival in the estimate while some have.
 *
 * @param int    $order_id
 * @param string $today  Y-m-d in the site's time zone; '' for today
 * @return array|null  pg_legal_withdrawal_deadline()
 */
function pg_legal_withdrawal_of_order($order_id, $today = '')
{
    $order_id = (int) $order_id;

    if ($order_id <= 0) {
        return null;
    }

    if ((string) db_value("SELECT status FROM orders WHERE id = '" . $order_id . "'") === 'cancelled') {
        return null;
    }

    $ship_tos = db_items(
        "SELECT ship_tos.id
        FROM ship_tos
        WHERE
            (ship_tos.order_id = '" . $order_id . "')
            AND EXISTS (
                SELECT 1 FROM order_items
                WHERE (order_items.order_id = '" . $order_id . "') AND (order_items.ship_to_id = ship_tos.id))");

    if (!is_array($ship_tos) || empty($ship_tos)) {
        return null;
    }

    if (pg_return_date($today) === '') {
        $today = date('Y-m-d');
    }

    $windows = array();

    foreach ($ship_tos as $ship_to) {
        $state = pg_return_delivery_state($order_id, (int) $ship_to['id'], $today);
        $windows[] = is_array($state) ? $state['window'] : null;
    }

    return pg_legal_withdrawal_deadline($windows);
}

/**
 * The line an order's legal documents card prints under the links: the last
 * day of the right of withdrawal, and whether it is an estimate.
 *
 * @param array|null $withdrawal  pg_legal_withdrawal_deadline()
 * @param string     $date_text   the deadline as the page writes dates
 * @return string  plain text, '' when there is no deadline
 */
function pg_legal_withdrawal_text($withdrawal, $date_text)
{
    $date_text = trim((string) $date_text);

    if (!is_array($withdrawal) || ($date_text === '')) {
        return '';
    }

    if (!$withdrawal['open']) {
        return lang(array('string' => 'The right of withdrawal ended on {var:1}.', 'vars' => array($date_text)));
    }

    return $withdrawal['estimated']
        ? lang(array('string' => 'Last day of the right of withdrawal: {var:1} (estimated; the 14 days run from delivery).', 'vars' => array($date_text)))
        : lang(array('string' => 'Last day of the right of withdrawal: {var:1}', 'vars' => array($date_text)));
}

/**
 * The order screen's card listing the documents kept with the order: each
 * one's name, whether and when it was accepted, from which address, and a
 * link to the copy.
 *
 * @param int $order_id
 * @return string  '' when nothing is kept
 */
function pg_legal_documents_panel_card($order_id)
{
    $types = pg_legal_document_types();
    $rows = '';

    foreach (pg_legal_documents_of_order($order_id) as $type => $row) {
        if (!isset($types[$type])) {
            continue;
        }

        $accepted = (int) $row['accepted_at'];
        $status = ($accepted > 0)
            ? h(pg_legal_document_time_text($accepted))
            : '<span class="fg-danger">' . h(lang('Not accepted')) . '</span>';

        $rows .= '<div class="row">
                        <span class="col fg-secondary"><a class="pg-link-secondary" href="' . h(pg_legal_document_copy_url($order_id, $type)) . '" target="_blank" rel="noopener">' . h($types[$type]) . '</a>:</span>
                        <span class="col text-end" title="' . h((string) $row['ip']) . '">' . $status . '</span>
                    </div>';
    }

    if ($rows === '') {
        return '';
    }

    // The last day the customer may still withdraw: the deadline of the last
    // delivery, marked when it rests on planned dates rather than a delivery.
    $withdrawal = pg_legal_withdrawal_of_order($order_id);

    if (is_array($withdrawal)) {
        $note = !$withdrawal['open']
            ? lang('Ended')
            : ($withdrawal['estimated'] ? lang('Estimated') : '');

        $rows .= '<div class="row">
                        <span class="col fg-secondary">' . h(lang('Right of withdrawal until')) . ':</span>
                        <span class="col text-end"' . ($withdrawal['estimated'] ? ' title="' . h(lang('Estimated from the ship date or the expected delivery date; the 14 days run from delivery.')) . '"' : '') . '>'
                            . h(pg_return_day_text($withdrawal['deadline']))
                            . (($note !== '') ? ' <span class="fg-secondary small">(' . h($note) . ')</span>' : '')
                        . '</span>
                    </div>';
    }

    return '<div class="card mt-5">
                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                    ' . h(lang('Legal Documents')) . '
                </div>
                <div class="card-body">
                    ' . $rows . '
                </div>
            </div>';
}
