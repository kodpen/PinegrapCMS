<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Pharmacy", its shop: the catalog (the Health Products
 * group with a group per product range), the shop page, the product, cart,
 * checkout and order pages, the order receipt e-mail and the cart link of
 * the account bar. Included after parts.php and mail.php in the same
 * scope; returns array('pages' => ..., 'widgets' => ..., 'catalog' => ...).
 *
 * Everything here needs the shop: a site without it keeps the services,
 * the requests and the ranges on the home page, which never link to these
 * pages. The shop sells health products only, never prescription
 * medicine: the shop and product pages send a prescription to the
 * prescription request, which is handed over against the original
 * prescription.
 *
 * A group's key is its range's key in parts.php ($ranges), so a range's
 * listing is {{page:shop}}/{{product_group_path:<range key>}}. The prices
 * are figures per currency, read with $money() as everywhere in the
 * template.
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
// The sample products of each range: none is a prescription medicine.
// SKU RX-<RANGE>-<N> (N from 1); prices per currency in major units;
// 'featured' marks the products the Health Products group shows first.
$health_products = array(
    'vitamins' => array(
        array('title' => lang('Vitamin D3 Drops'), 'featured' => true,
            'text'  => lang('1,000 units in each drop, for adults and children from one year; one small bottle lasts three months.'),
            'price' => array('USD' => 12, 'EUR' => 11, 'GBP' => 10, 'TRY' => 390)),
        array('title' => lang('Omega-3 Fish Oil Capsules'), 'featured' => false,
            'text'  => lang('Sixty capsules of purified fish oil, with no fishy aftertaste.'),
            'price' => array('USD' => 18, 'EUR' => 17, 'GBP' => 15, 'TRY' => 590)),
        array('title' => lang('Gentle Iron Tablets'), 'featured' => false,
            'text'  => lang('A form of iron that is kind to the stomach, with vitamin C to help it work.'),
            'price' => array('USD' => 10, 'EUR' => 9, 'GBP' => 8, 'TRY' => 320)),
    ),
    'skin' => array(
        array('title' => lang('Sun Cream SPF 50+'), 'featured' => true,
            'text'  => lang('High protection for sensitive skin, water resistant and without perfume.'),
            'price' => array('USD' => 16, 'EUR' => 15, 'GBP' => 13, 'TRY' => 520)),
        array('title' => lang('Repair Cream for Dry Skin'), 'featured' => false,
            'text'  => lang('A rich cream for dry and itchy skin, gentle enough for the whole family.'),
            'price' => array('USD' => 11, 'EUR' => 10, 'GBP' => 9, 'TRY' => 360)),
        array('title' => lang('Soothing Lip Balm'), 'featured' => false,
            'text'  => lang('For dry and cracked lips, in wind and sun alike.'),
            'price' => array('USD' => 5, 'EUR' => 5, 'GBP' => 4, 'TRY' => 150)),
    ),
    'baby' => array(
        array('title' => lang('Nappy Cream'), 'featured' => true,
            'text'  => lang('Zinc cream that soothes and protects a baby\'s skin at every change.'),
            'price' => array('USD' => 8, 'EUR' => 7, 'GBP' => 6, 'TRY' => 250)),
        array('title' => lang('Anti-Colic Feeding Bottle'), 'featured' => false,
            'text'  => lang('A 260 ml bottle with a slow-flow teat and a vent that keeps the air out.'),
            'price' => array('USD' => 14, 'EUR' => 13, 'GBP' => 11, 'TRY' => 450)),
        array('title' => lang('Saline Nose Drops for Babies'), 'featured' => false,
            'text'  => lang('Single-dose drops that clear a blocked little nose, from birth.'),
            'price' => array('USD' => 7, 'EUR' => 6, 'GBP' => 5, 'TRY' => 220)),
    ),
    'first_aid' => array(
        array('title' => lang('Home First-Aid Kit'), 'featured' => true,
            'text'  => lang('Plasters, dressings, bandages, scissors and a guide, in one box for the kitchen drawer.'),
            'price' => array('USD' => 25, 'EUR' => 23, 'GBP' => 20, 'TRY' => 790)),
        array('title' => lang('Assorted Plasters'), 'featured' => false,
            'text'  => lang('Forty plasters in five sizes, breathable and water resistant.'),
            'price' => array('USD' => 4, 'EUR' => 4, 'GBP' => 3, 'TRY' => 120)),
        array('title' => lang('Digital Thermometer'), 'featured' => false,
            'text'  => lang('Reads in ten seconds with a beep, with a flexible tip for children.'),
            'price' => array('USD' => 9, 'EUR' => 8, 'GBP' => 7, 'TRY' => 290)),
    ),
    'devices' => array(
        array('title' => lang('Upper Arm Blood Pressure Monitor'), 'featured' => true,
            'text'  => lang('Measures at the press of a button and keeps the last sixty readings; a pharmacist shows you how to use it.'),
            'price' => array('USD' => 45, 'EUR' => 42, 'GBP' => 37, 'TRY' => 1490)),
        array('title' => lang('Blood Sugar Meter Starter Kit'), 'featured' => false,
            'text'  => lang('A meter, ten test strips and a lancing device, ready for a first reading.'),
            'price' => array('USD' => 29, 'EUR' => 27, 'GBP' => 24, 'TRY' => 950)),
        array('title' => lang('Inhaler Spacer'), 'featured' => false,
            'text'  => lang('Helps the medicine of an inhaler reach the lungs, with a mask for children.'),
            'price' => array('USD' => 15, 'EUR' => 14, 'GBP' => 12, 'TRY' => 490)),
    ),
);
$product_sku = function ($range, $n) {
    return 'RX-' . strtoupper(str_replace('_', '', $range)) . '-' . (int)$n;
};

// A product is made in the first group that lists it and placed in the
// next one by its SKU, so a featured product is one product in two groups:
// one price, one stock, one page.
$featured_products = array();
$range_products = array();
foreach ($health_products as $range_key => $list) {
    foreach (array_values($list) as $n => $p) {
        $product = array(
            'sku'         => $product_sku($range_key, $n + 1),
            'title'       => $p['title'],
            'description' => $p['text'],
            'price'       => $p['price'],
            'featured'    => !empty($p['featured']),
            'keywords'    => $ranges[$range_key]['title'],
        );
        if ($product['featured']) $featured_products[] = $product;
        $range_products[$range_key][] = $product;
    }
}

$catalog_groups = array(
    'health' => array(
        'parent'      => 'root',
        'title'       => lang('Health Products'),
        'description' => lang('Vitamins, skin care, baby care, first aid and health devices, with a pharmacist\'s advice. Never prescription medicine.'),
        'products'    => $featured_products,
    ),
);
$sort = 0;
foreach ($ranges as $key => $r) {
    $catalog_groups[$key] = array(
        'parent'      => 'health',
        'sort_order'  => ++$sort,
        'title'       => $r['title'],
        'description' => $r['text'],
        'products'    => $range_products[$key],
    );
}

$catalog = array(
    'folder'  => 'pinegrap_pharmacy_catalog',
    // The Details tab of every product's page.
    'details' => array(
        lang('Read the leaflet before use, and ask a pharmacist if you take other medicines, are pregnant or are buying for a child.'),
        lang('Packed the same day and sent tracked, or ready to collect at the counter.'),
        lang('A sample product of the template: change its name and price in the catalog, or remove it.'),
    ),
    'groups'  => $catalog_groups,
);

// The address of a group's own listing on the shop page.
$shop_group_url = function ($key) {
    return '{{page:shop}}/{{product_group_path:' . $key . '}}';
};

// ── Pages ───────────────────────────────────────────────────────────────

// Beside the products: a prescription goes to the request, never to the
// cart, and a pharmacist answers any question about a product.
$shop_advice = function () use ($el, $heading, $para, $link, $row, $col, $icon_tile) {
    $box = function ($icon_name, $title, $text, $link_text, $href, $class) use ($el, $heading, $para, $link, $col, $icon_tile) {
        return $col(array(
            $el('div', 'h-100 p-4 rounded-4 ' . $class, $title, array(
                $icon_tile($icon_name, 'mb-3'),
                $heading('h3', $title, 'prx-display h5 mb-2'),
                $para($text, 'small mb-3'),
                $link($link_text, $href, 'btn btn-sm btn-primary rounded-pill px-3'),
            )),
        ), '6');
    };
    return $row(array(
        $box('bi-prescription2', lang('Prescription medicine is never sold here'),
            lang('Send us your prescription instead: we prepare the medicines and hand them over against the original prescription.'),
            lang('Send a Prescription Request'), '{{page:prescription}}', 'bg-body border'),
        $box('bi-chat-left-dots', lang('Not sure which one?'),
            lang('A pharmacist tells you which product suits you and whether it is safe with your medicines.'),
            lang('Ask a Pharmacist'), '{{page:ask}}', 'bg-body border'),
    ), '4');
};

// The shop: the ranges open in place, with the search, the sorting and
// the price filter.
$shop_chips = array();
foreach ($ranges as $key => $r) {
    $shop_chips[] = $el('a', 'btn btn-sm btn-outline-secondary rounded-pill px-3 d-inline-flex align-items-center gap-2', $r['title'], array(
        $icon($r['icon']),
        $span($r['title'], '', lang('Link Text')),
    ), array('href' => $shop_group_url($key)));
}

$shop_pages[] = array(
    'key'              => 'shop',
    'requires'         => 'ecommerce',
    'name'             => lang('shop'),
    'folder'           => 'public',
    'title'            => lang('Shop'),
    'meta_description' => lang('Vitamins, skin care, baby care, first aid and health devices from the pharmacy, with advice from a pharmacist.'),
    'tree'             => $page(array(
        $page_header(lang('Shop'), lang('The health products on our shelves, with a pharmacist to advise you. Open a range, or search, sort and filter everything at once.'), array(
            $el('nav', 'd-flex flex-wrap gap-2 mt-4', lang('Product Ranges'), $shop_chips,
                array('_attrs' => array($attr('aria-label', lang('Product Ranges'))))),
        )),
        $el('section', 'py-5', lang('Products'), array(
            $container(array($widget('shop_catalog'))),
        ), $searchable('rx-shop-products', lang('buy, order, price, vitamins, sun cream, baby care, first aid, blood pressure monitor'))),
        $el('section', 'py-5 border-top bg-body-tertiary', lang('Advice'), array(
            $container(array(
                $section_header(lang('Advice with every order'), lang('Health products, chosen with care'), lang('Our services'), '{{page:services}}'),
                $shop_advice(),
            )),
        )),
    )),
);

// The shop's own pages: the cart, the checkout and the order show the
// editor's layouts for their kinds, out of the search and the sitemap.
$shop_page = function ($key, $name, $title, $main) use ($page) {
    return array(
        'key'      => $key,
        'requires' => 'ecommerce',
        'name'     => $name,
        'folder'   => 'public',
        'title'    => $title,
        'search'   => false,
        'sitemap'  => false,
        'noindex'  => true,
        'tree'     => $page($main, false),
    );
};

// A product: its page, then the advice beside every product.
$shop_pages[] = array_merge($shop_page('product', lang('product-detail'), lang('Product'), array(
    $widget('product_detail'),
    $el('section', 'py-5 border-top bg-body-tertiary', lang('Advice'), array(
        $container(array($shop_advice())),
    )),
)), array('search' => true, 'sitemap' => true, 'noindex' => false, 'meta_description' => lang('Product details, how to use it, options and price.')));
$shop_pages[] = $shop_page('cart', lang('cart'), lang('Shopping Cart'), array($widget('cart')));
$shop_pages[] = $shop_page('checkout', lang('checkout'), lang('Checkout'), array($widget('checkout')));
$shop_pages[] = $shop_page('order', lang('order-summary'), lang('Order Summary'), array($widget('order_view')));

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
        $el('td', 'pe-0 text-end', lang('Value'), array($span('$34.00', '', lang('Amount'), $bind($token))), $mail_style($style)),
    ), $flag !== '' ? $show_when($flag) : array());
};

$sample_product = $health_products['vitamins'][0]['title'];
$sample_price = $money($health_products['vitamins'][0]['price']);

$shop_widgets = array(

    // The shop: the Health Products group with its ranges opening in
    // place.
    'shop_catalog' => array(
        'page'     => 'shop',
        'requires' => 'ecommerce',
        'slug'     => lang('catalog'),
        'config'   => array(
            'regionType'               => 'catalog_listing',
            'product_group_id'         => '{{product_group:health}}',
            'group_navigation'         => 'drill_down',
            'search_enabled'           => true,
            'sort_control_enabled'     => true,
            'price_filter_enabled'     => true,
            'stock_filter_enabled'     => false,
            'order_by_field'           => 'sort_order',
            'order_by_direction'       => 'ASC',
            'items_per_page'           => 12,
            'detail_page_id'           => '{{tab:product}}',
            'add_to_cart_stay_on_page' => true,
            'add_to_cart_next_page_id' => '{{tab:cart}}',
            'empty_message'            => lang('Nothing here yet. Try another range.'),
        ),
        'tree'     => 'starter',
    ),

    // The way to the cart, in the account bar; left out without the shop.
    'cart_link' => array(
        'page'     => '',
        'requires' => 'ecommerce',
        'slug'     => lang('cart-link'),
        'config'   => array('regionType' => 'cart_link', 'cart_page_id' => '{{tab:cart}}'),
        'tree'     => 'starter',
    ),

    // The shop's pages draw the editor's own layouts for their kinds. A
    // product's breadcrumb starts at the shop.
    'product_detail' => array(
        'page'     => 'product',
        'requires' => 'ecommerce',
        'slug'     => lang('product-detail'),
        'config'   => array(
            'regionType'              => 'catalog_item_view',
            'product_group_id'        => 0,
            'next_page_id'            => '{{tab:cart}}',
            'catalog_listing_page_id' => '{{tab:shop}}',
            'catalog_root_label'      => lang('Shop'),
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

    // The receipt: the order's number and date, its products, the totals,
    // the shipping and billing addresses, what happens next, and the way to
    // ask a pharmacist about a product.
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
                                $span($sample_product, 'fw-semibold', lang('Product Name'), $bind('__item_short_description')),
                                $span(' × ', 'text-body-secondary', lang('Text')),
                                $span('1', '', lang('Quantity'), $bind('__item_qty')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #e5e7eb')),
                            $el('td', 'pe-0 text-end', lang('Total'), array(
                                $span($sample_price, '', lang('Amount'), $bind('__item_total')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #e5e7eb;text-align:right')),
                        )),
                    )),
                )),
            ), $mail_style('width:100%;border-collapse:collapse;margin:0 0 16px')),
            $el('table', 'table table-sm mb-4', lang('Totals'), array(
                $el('tbody', '', lang('Rows'), array(
                    $shop_total_row(lang('Subtotal'), lang('Subtotal'), '__order_subtotal'),
                    $shop_total_row(lang('Discount'), lang('Discount'), '__order_discount', 'has_discount'),
                    $shop_total_row(lang('Shipping'), lang('Shipping'), '__order_shipping', 'has_shipping_cost'),
                    $shop_total_row(lang('Tax'), lang('VAT'), '__order_tax', 'has_tax'),
                    $shop_total_row(lang('Total'), lang('Total'), '__order_total', '', 'fw-bold', 'text-align:right;font-weight:700'),
                )),
            ), $mail_style('width:100%;border-collapse:collapse;margin:0 0 24px')),
            $el('div', '', lang('Shipping Address'), array(
                $el('p', 'small text-body-secondary mb-1', lang('Label'), array(), array('text' => lang('Shipping Address'))),
                $el('div', '', lang('Address'), array(), array_merge(array('text' => lang('Jane Cooper')), $bind('__shipping_address_html'))),
            ), array_merge($mail_style('margin:0 0 16px'), $show_when('has_shipping'))),
            $el('div', '', lang('Billing Address'), array(
                $el('p', 'small text-body-secondary mb-1', lang('Label'), array(), array('text' => lang('Billing Address'))),
                $el('div', '', lang('Address'), array(), array_merge(array('text' => lang('Jane Cooper')), $bind('__billing_address_html'))),
            ), $mail_style('margin:0 0 16px')),
            $mail_box(array(
                $el('p', 'fw-semibold mb-1', lang('Title'), array(), array_merge(array('text' => lang('What happens next')), $mail_style('font-weight:600;margin:0 0 4px'))),
                $mail_para(lang('We pack your order the same day and send it tracked, or have it ready at the counter if you chose to collect it. You hear from us when it is on its way.'), 'mb-0', 'margin:0'),
            ), lang('Next Step')),
            $mail_para(lang('Not sure how to use a product, or whether it suits your medicines? A pharmacist answers you privately.'), 'mb-1', 'margin:0 0 4px'),
            $mail_button(lang('Ask a Pharmacist'), '{{page:ask}}'),
            $mail_para(lang('Your order and its receipt are also on the order page:'), 'small mb-1', 'margin:0 0 4px;font-size:13px'),
            $link(lang('View your order'), '{{page:order}}?order_id=^^__order_id^^', 'small', array($attr('style', 'font-size:13px;color:#4b5563'))),
        )),
    ),
);

return array('pages' => $shop_pages, 'widgets' => $shop_widgets, 'catalog' => $catalog);
