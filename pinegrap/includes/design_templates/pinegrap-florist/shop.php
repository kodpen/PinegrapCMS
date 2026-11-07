<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Florist", its shop: the catalog (the Flower Shop group
 * with a group per occasion holding its bouquets of $bouquets, the prepaid
 * subscription bundles of $plans, and the vases, extras and gift cards of
 * $extras), the shop page, the product, cart, checkout and order pages and
 * the order receipt e-mail. Included after parts.php and mail.php in the
 * same scope; returns array('pages' => ..., 'widgets' => ..., 'catalog' => ...).
 *
 * Every page and widget here needs the shop: the shared parts and the
 * record pages never link them, so a site without the shop has none of
 * them and no dead link.
 *
 * The prices are parts.php's own ($bouquet_prices, $plan_total, $extras):
 * the cards write them with $money(), the products are made from the same
 * figures, so the page and the cart show one amount. A bouquet bought here
 * is the regular size; a prepaid bundle is the plan's three months paid at
 * once, delivered on the day the florist agrees with the buyer, and nothing
 * is billed again by itself. The delivery day of an order is the
 * checkout's requested arrival date, which the store sets up in its
 * shipping settings; the template creates none.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$shop_pages = array();

// ── The catalog ─────────────────────────────────────────────────────────
// Made once when the template is first opened (_pg_tpl_catalog()): the
// Flower Shop group under the site's top group, a group per occasion with
// its bouquets, then the subscriptions and the extras. Each product is
// found again by its SKU.
$shop_groups = array(
    'florist_shop' => array(
        'parent'      => 'root',
        'title'       => lang('Flower Shop'),
        'description' => lang('Bouquets for every occasion, prepaid flower subscriptions, vases, extras and gift cards.'),
        'keywords'    => lang('flowers, bouquet, florist, delivery, gift'),
    ),
);
$sort = 0;
foreach ($occasions as $okey => $o) {
    $products = array();
    foreach ($bouquets as $bkey => $b) {
        if ($b['occasion'] !== $okey) continue;
        $products[] = array(
            'sku'         => 'FLORIST-BQ-' . strtoupper(str_replace('-', '', $bkey)),
            'title'       => $b['title'],
            'description' => $b['text'] . ' ' . sprintf(lang('Flowers: %s.'), implode(', ', $b['flowers'])),
            'price'       => $bouquet_prices[$bkey],
            'shippable'   => true,
            'featured'    => !empty($b['featured']),
            'keywords'    => $o['title'] . ', ' . $b['style'] . ', ' . implode(', ', $b['flowers']),
        );
    }
    $shop_groups['fl_' . $okey] = array(
        'parent'      => 'florist_shop',
        'sort_order'  => ++$sort,
        'title'       => $o['title'],
        'description' => $o['text'],
        'products'    => $products,
    );
}
$subscription_products = array();
foreach ($plans as $pkey => $p) {
    $subscription_products[] = array(
        'sku'         => 'FLORIST-SUB-' . strtoupper($pkey),
        'title'       => sprintf(lang('%s, Three Months'), $p['title']),
        'description' => $p['text'] . ' ' . sprintf(lang('%1$d deliveries over three months, paid at once; we agree the day with you after the order.'), (int)$p['deliveries']),
        'price'       => $plan_total($pkey),
        'shippable'   => true,
        'featured'    => !empty($p['featured']),
        'keywords'    => lang('flower subscription, weekly flowers, prepaid, gift'),
    );
}
$shop_groups['fl_subscriptions'] = array(
    'parent'      => 'florist_shop',
    'sort_order'  => ++$sort,
    'title'       => lang('Prepaid Subscriptions'),
    'description' => lang('Three months of fresh flowers paid at once: for your home, or as a gift that keeps arriving.'),
    'products'    => $subscription_products,
);
$extra_products = array();
foreach ($extras as $x) {
    $extra_products[] = array(
        'sku'         => $x['sku'],
        'title'       => $x['title'],
        'description' => $x['text'],
        'price'       => $x['price'],
        'shippable'   => $x['shippable'],
        'featured'    => false,
        'keywords'    => lang('vase, chocolates, gift card, extras, gift'),
    );
}
$shop_groups['fl_extras'] = array(
    'parent'      => 'florist_shop',
    'sort_order'  => ++$sort,
    'title'       => lang('Vases, Extras and Gift Cards'),
    'description' => lang('A vase that fits, a little sweet to go with the flowers, or a gift card sent by e-mail.'),
    'products'    => $extra_products,
);
$catalog = array(
    'folder'  => 'pinegrap_florist_catalog',
    // The Details tab of every product's page.
    'details' => array(
        $cutoff_text() . '; ' . lang('choose the day at the checkout.'),
        lang('A bouquet is the regular size; for a deluxe or grand one, or a card message, send an order request.'),
        lang('A gift card is sent by e-mail within one working day of the order, with its code.'),
        lang('A sample product of the template: change its name, price and pictures in the catalog, or remove it.'),
    ),
    'groups'  => $shop_groups,
);
$sample_product = $shop_groups['fl_love']['products'][0];

// ── Pages ───────────────────────────────────────────────────────────────

// How buying works, in three steps.
$shop_steps = array(
    $step(1, lang('Choose the flowers'), lang('Browse by occasion, add a vase or chocolates, or a three-month subscription.')),
    $step(2, lang('Choose the day'), lang('At the checkout, pick the day the flowers should arrive and give the recipient\'s address.')),
    $step(3, lang('We arrange and deliver'), lang('We arrange the flowers by hand on the morning they leave, and tell you when they are delivered.')),
);

$shop_pages[] = array(
    'key'              => 'shop',
    'requires'         => 'ecommerce',
    'name'             => lang('flower-shop'),
    'folder'           => 'public',
    'title'            => lang('Flower Shop'),
    'meta_description' => lang('Buy bouquets by occasion, prepaid flower subscriptions, vases and gift cards, and choose the delivery day at the checkout.'),
    'tree'             => $page(array(
        $page_header(lang('Flower Shop'), lang('Bouquets by occasion, prepaid subscriptions, vases, extras and gift cards: pay now and choose the delivery day at the checkout.')),
        $el('section', 'py-5', lang('Products'), array(
            $container(array($widget('shop_catalog'))),
        ), $searchable('fl-shop', lang('buy flowers, shop, order online, gift card, vase, chocolates, prepaid subscription'))),
        $el('section', 'py-5 bg-body-tertiary', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $section_header(lang('Buying online'), lang('How it works')),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), $shop_steps),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body border', lang('Box'), array(
                            $heading('h3', lang('A card message, another size or a special wish?'), 'pfl-display h5 mb-2'),
                            $para(lang('Send an order request instead: write the card, choose the size and the time, and we confirm the price before anything is paid.'), 'text-body-secondary mb-3'),
                            $link(lang('Order Flowers'), '{{page:order_request}}', 'btn btn-primary rounded-pill px-4'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    )),
);

// The shop's own pages: a product's page, the cart, the checkout and the
// order.
$shop_page = function ($key, $name, $title, $widget_key) use ($page, $widget) {
    return array(
        'key'      => $key,
        'requires' => 'ecommerce',
        'name'     => $name,
        'folder'   => 'public',
        'title'    => $title,
        'search'   => false,
        'sitemap'  => false,
        'noindex'  => true,
        'tree'     => $page(array($widget($widget_key)), false),
    );
};
$shop_pages[] = array_merge($shop_page('product', lang('product-detail'), lang('Product'), 'product_detail'),
    array('search' => true, 'sitemap' => true, 'noindex' => false, 'meta_description' => lang('Product details, options and price.')));
$shop_pages[] = $shop_page('cart', lang('cart'), lang('Shopping Cart'), 'cart');
$shop_pages[] = $shop_page('checkout', lang('checkout'), lang('Checkout'), 'checkout');
$shop_pages[] = $shop_page('order', lang('order-summary'), lang('Order Summary'), 'order_view');

// The order receipt, sent to the billing address when an order is placed.
$shop_pages[] = array_merge($mail_settings, array(
    'key'      => 'email_order',
    'requires' => 'ecommerce',
    'name'     => lang('email-order-receipt'),
    'title'    => lang('Order Receipt E-mail'),
    'tree'     => $mail_page(array($widget('email_order'))),
));

// ── Widgets ─────────────────────────────────────────────────────────────

// A line of the receipt's totals, shown while its flag holds.
$shop_total_row = function ($name, $label, $token, $flag = '', $class = '', $style = 'text-align:right') use ($el, $span, $bind, $show_when, $mail_style) {
    return $el('tr', $class, $name, array(
        $el('td', 'ps-0' . ($class === '' ? ' text-body-secondary' : ''), lang('Label'), array($span($label, '', lang('Text')))),
        $el('td', 'pe-0 text-end', lang('Value'), array($span('$75.00', '', lang('Amount'), $bind($token))), $mail_style($style)),
    ), $flag !== '' ? $show_when($flag) : array());
};

$shop_widgets = array(

    // The products on sale, in the editor's listing layout: the Flower
    // Shop's groups (an occasion, the subscriptions, the extras) open in
    // place. The notice after an add links this shop's cart.
    'shop_catalog' => array(
        'page'     => 'shop',
        'requires' => 'ecommerce',
        'slug'     => lang('catalog'),
        'config'   => array(
            'regionType'               => 'catalog_listing',
            'product_group_id'         => '{{product_group:florist_shop}}',
            'group_navigation'         => 'drill_down',
            'search_enabled'           => true,
            'sort_control_enabled'     => true,
            'price_filter_enabled'     => true,
            'items_per_page'           => 12,
            'order_by_field'           => 'sort_order',
            'order_by_direction'       => 'ASC',
            'detail_page_id'           => '{{tab:product}}',
            'add_to_cart_stay_on_page' => true,
            'add_to_cart_next_page_id' => '{{tab:cart}}',
            'empty_message'            => lang('No flowers on sale here right now.'),
        ),
        'tree'     => 'starter',
    ),

    // The way to the cart, beside the login region; left out without the
    // shop.
    'cart_link' => array(
        'page'     => '',
        'requires' => 'ecommerce',
        'slug'     => lang('cart-link'),
        'config'   => array('regionType' => 'cart_link', 'cart_page_id' => '{{tab:cart}}'),
        'tree'     => 'starter',
    ),

    // The shop's pages draw the editor's own layouts for their kinds. A
    // product's breadcrumb starts at the shop page.
    'product_detail' => array(
        'page'     => 'product',
        'requires' => 'ecommerce',
        'slug'     => lang('product-detail'),
        'config'   => array(
            'regionType'              => 'catalog_item_view',
            'product_group_id'        => 0,
            'next_page_id'            => '{{tab:cart}}',
            'catalog_listing_page_id' => '{{tab:shop}}',
            'catalog_root_label'      => lang('Flower Shop'),
            'not_found_message'       => lang('Product not found.'),
        ),
        'tree'     => 'starter',
    ),
    'cart' => array(
        'page'     => 'cart',
        'requires' => 'ecommerce',
        'slug'     => lang('cart'),
        'config'   => array(
            'regionType'                    => 'shopping_cart',
            'next_page_id_with_shipping'    => '{{tab:checkout}}',
            'next_page_id_without_shipping' => '{{tab:checkout}}',
            'detail_page_id'                => '{{tab:product}}',
        ),
        'tree'     => 'starter',
    ),
    'checkout' => array(
        'page'     => 'checkout',
        'requires' => 'ecommerce',
        'slug'     => lang('checkout'),
        'config'   => array(
            'regionType'                  => 'express_order',
            'next_page_id'                => '{{tab:order}}',
            'order_receipt_email_page_id' => '{{tab:email_order}}',
            'order_receipt_email_subject' => lang('Order Receipt #'),
            'cart_section_label'          => lang('Your cart'),
            'purchase_button_label'       => lang('Complete Order'),
            'update_button_label'         => lang('Update'),
        ),
        'tree'     => 'starter',
    ),
    'order_view' => array(
        'page'     => 'order',
        'requires' => 'ecommerce',
        'slug'     => lang('order'),
        'config'   => array('regionType' => 'order_view', 'date_format' => ''),
        'tree'     => 'starter',
    ),

    // The receipt: the order's number and date, what was bought, the
    // totals, the billing and delivery addresses, what happens next and
    // the way to the order page.
    'email_order' => array(
        'page'     => 'email_order',
        'requires' => 'ecommerce',
        'slug'     => lang('order'),
        'config'   => array('regionType' => 'order_view', 'date_format' => ''),
        'tree'     => $widget_root(array(
            $mail_heading(lang('Thank you for your order')),
            $el('p', 'mb-3', lang('Order Details'), array(
                $span(lang('Order Number') . ': ', 'text-body-secondary', lang('Label')),
                $span('PG-12345', 'fw-semibold', lang('Order Number'), $bind('__order_no')),
                $span(' · ', 'text-body-secondary', lang('Text')),
                $span(lang('Date'), '', lang('Date'), $bind('__order_date')),
            ), $mail_style('margin:0 0 16px')),
            $el('table', 'table align-middle mb-3', lang('Order Items'), array(
                $el('tbody', '', lang('Rows'), array(
                    $loop(array(
                        $el('tr', '', lang('Row'), array(
                            $el('td', 'ps-0', lang('Product'), array(
                                $span($sample_product['title'], 'fw-semibold', lang('Product Name'), $bind('__item_short_description')),
                                $span(' × ', 'text-body-secondary', lang('Text')),
                                $span('1', '', lang('Quantity'), $bind('__item_qty')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #f1dfe2')),
                            $el('td', 'pe-0 text-end', lang('Total'), array(
                                $span($money($sample_product['price']), '', lang('Amount'), $bind('__item_total')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #f1dfe2;text-align:right')),
                        )),
                    )),
                )),
            ), $mail_style('width:100%;border-collapse:collapse;margin:0 0 16px')),
            $el('table', 'table table-sm mb-4', lang('Totals'), array(
                $el('tbody', '', lang('Rows'), array(
                    $shop_total_row(lang('Subtotal'), lang('Subtotal'), '__order_subtotal'),
                    $shop_total_row(lang('Discount'), lang('Discount'), '__order_discount', 'has_discount'),
                    $shop_total_row(lang('Delivery'), lang('Delivery'), '__order_shipping', 'has_shipping'),
                    $shop_total_row(lang('Tax'), lang('VAT'), '__order_tax', 'has_tax'),
                    $shop_total_row(lang('Total'), lang('Total'), '__order_total', '', 'fw-bold', 'text-align:right;font-weight:700'),
                )),
            ), $mail_style('width:100%;border-collapse:collapse;margin:0 0 24px')),
            $el('div', '', lang('Billing Address'), array(
                $el('p', 'small text-body-secondary mb-1', lang('Label'), array(), array('text' => lang('Billing Address'))),
                $el('div', '', lang('Address'), array(), array_merge(array('text' => lang('Ayşe Kaya')), $bind('__billing_address_html'))),
            ), $mail_style('margin:0 0 16px')),
            $el('div', '', lang('Delivery Address'), array(
                $el('p', 'small text-body-secondary mb-1', lang('Label'), array(), array('text' => lang('Delivery Address'))),
                $el('div', '', lang('Address'), array(), array_merge(array('text' => lang('Elif Demir')), $bind('__shipping_address_html'))),
            ), array_merge($mail_style('margin:0 0 16px'), $show_when('has_shipping'))),
            $mail_box(array(
                $el('p', 'fw-semibold mb-1', lang('Title'), array(), array_merge(array('text' => lang('What happens next')), $mail_style('font-weight:600;margin:0 0 4px'))),
                $mail_para(lang('We arrange the flowers by hand on the morning of the day you chose and tell you when they are delivered. For a subscription we write to agree the day of the first delivery; a gift card comes to this address within one working day, with its code.'), 'mb-0', 'margin:0'),
            ), lang('Next Step')),
            $mail_button(lang('View your order'), '{{page:order}}?order_id=^^__order_id^^'),
            $mail_para(lang('A question about the delivery? Reply to this e-mail or call us:') . ' ' . $florist['phone'], 'small mb-0', 'margin:0;font-size:13px'),
        )),
    ),
);

return array('pages' => $shop_pages, 'widgets' => $shop_widgets, 'catalog' => $catalog);
