<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Marketplace", its shop: the catalog (the Marketplace
 * group, a group per category and, under Shops, a group per maker; every
 * product sits in its category and in its maker's shop), the shop page,
 * the product, cart, checkout and order pages, the order receipt e-mail
 * and the cart link of the account bar. Included after parts.php and
 * mail.php in the same scope; returns array('pages' => ..., 'widgets' =>
 * ..., 'catalog' => ...).
 *
 * Everything here needs the shop: a site without it keeps the categories,
 * the makers' pages and their reviews, which never link to these pages.
 *
 * The products are made from $market_products (parts.php): the cards write
 * the same figures with $money(), so a card and the cart show one amount.
 * A group's key is its category's or its maker's key in parts.php, so a
 * maker's shop is {{page:shop}}/{{product_group_path:<maker key>}}.
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
// Made once when the template is first opened (_pg_tpl_catalog()). The
// Marketplace group holds the featured products and, below it, a group per
// category and the Shops group with a group per maker. A product is made
// in the first group that lists it and placed in the next one by its SKU
// (MK-<MAKER>-<N>), so it is one product in two groups: one price, one
// stock, one page.
$market_product = function ($seller_key, $n) use ($sellers, $market_products, $categories, $cities, $product_sku) {
    $s = $sellers[$seller_key];
    $p = $market_products[$seller_key][$n];
    return array(
        'sku'         => $product_sku($seller_key, $n + 1),
        'title'       => $p['title'],
        'description' => $p['text'] . ' ' . sprintf(lang('Made by %1$s of %2$s, %3$s.'), $s['maker'], $s['name'], $cities[$s['city']]),
        'price'       => $p['price'],
        'featured'    => !empty($p['featured']),
        'keywords'    => $s['name'] . ', ' . $categories[$s['category']]['topics'],
    );
};

$featured_products = array();
$category_products = array();
$maker_products = array();
foreach ($sellers as $seller_key => $s) {
    foreach (array_keys($market_products[$seller_key]) as $n) {
        $product = $market_product($seller_key, $n);
        if ($product['featured']) $featured_products[] = $product;
        $category_products[$s['category']][] = $product;
        $maker_products[$seller_key][] = $product;
    }
}

$catalog_groups = array(
    'market' => array(
        'parent'      => 'root',
        'title'       => lang('Marketplace'),
        'description' => lang('Things made by hand by independent makers, by category and by shop.'),
        'products'    => $featured_products,
    ),
);
$sort = 0;
foreach ($categories as $key => $c) {
    $catalog_groups[$key] = array(
        'parent'      => 'market',
        'sort_order'  => ++$sort,
        'title'       => $c['title'],
        'description' => $c['text'],
        'keywords'    => $c['topics'],
        'products'    => isset($category_products[$key]) ? $category_products[$key] : array(),
    );
}
$catalog_groups['shops'] = array(
    'parent'      => 'market',
    'sort_order'  => ++$sort,
    'title'       => lang('Shops'),
    'description' => lang('Every maker of the market, each with a shop of their own.'),
    'products'    => array(),
);
$sort = 0;
foreach ($sellers as $key => $s) {
    $catalog_groups[$key] = array(
        'parent'      => 'shops',
        'sort_order'  => ++$sort,
        'title'       => $s['name'],
        'description' => $s['tagline'] . ' ' . $s['story'],
        'keywords'    => $s['maker'] . ', ' . $cities[$s['city']],
        'products'    => $maker_products[$key],
    );
}

$catalog = array(
    'folder'  => 'pinegrap_market_catalog',
    // The Details tab of every product's page.
    'details' => array(
        lang('Made by hand in small numbers, so each piece is a little different.'),
        lang('Packed and sent by us with tracked delivery; 30 days to return.'),
        lang('A sample product of the template: change its name and price in the catalog, or remove it.'),
    ),
    'groups'  => $catalog_groups,
);

// The address of a group's own listing on the shop page.
$shop_group_url = function ($key) {
    return '{{page:shop}}/{{product_group_path:' . $key . '}}';
};

// ── Pages ───────────────────────────────────────────────────────────────

// The shop: the categories and the makers' shops open in place, with the
// search, the sorting and the price filter.
$shop_chips = array();
foreach ($categories as $key => $c) {
    $shop_chips[] = $el('a', 'btn btn-sm btn-outline-secondary rounded-pill px-3 d-inline-flex align-items-center gap-2', $c['title'], array(
        $icon($c['icon']),
        $span($c['title'], '', lang('Link Text')),
    ), array('href' => $shop_group_url($key)));
}
$shop_chips[] = $el('a', 'btn btn-sm btn-outline-primary rounded-pill px-3 d-inline-flex align-items-center gap-2', lang('Shops'), array(
    $icon('bi-shop-window'),
    $span(lang('Shop by maker'), '', lang('Link Text')),
), array('href' => $shop_group_url('shops')));

$shop_pages[] = array(
    'key'              => 'shop',
    'requires'         => 'ecommerce',
    'name'             => lang('shop'),
    'folder'           => 'public',
    'title'            => lang('Shop'),
    'meta_description' => lang('Everything in the market by category or by maker, ready to order in one cart.'),
    'tree'             => $page(array(
        $page_header(lang('Shop'), lang('Open a category or a maker\'s shop, or search, sort and filter everything at once.'), array(
            $el('nav', 'd-flex flex-wrap gap-2 mt-4', lang('Categories'), $shop_chips,
                array('_attrs' => array($attr('aria-label', lang('Categories'))))),
        )),
        $el('section', 'py-5', lang('Products'), array(
            $container(array($widget('shop_catalog'))),
        ), $searchable('shop-products', lang('buy, order, price, handmade, gift'))),
        $el('section', 'py-5 border-top bg-body-tertiary', lang('With Every Order'), array(
            $container(array(
                $section_header(lang('With every order'), lang('One cart, whichever shop it came from'), lang('How it works'), '{{page:how_it_works}}'),
                $promise_row(),
            )),
        )),
        $shared('maker_band'),
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

// A product: its page, then where it comes from and what the order brings.
// The maker is named in the product's own description.
$shop_pages[] = array_merge($shop_page('product', lang('product-detail'), lang('Product'), array(
    $widget('product_detail'),
    $el('section', 'py-5 border-top bg-body-tertiary', lang('With Every Order'), array(
        $container(array(
            $promise_row(),
            $el('div', 'd-flex flex-wrap gap-2 mt-4', lang('Buttons'), array(
                $link(lang('Meet the makers'), '{{page:sellers}}', 'btn btn-outline-primary rounded-pill px-4'),
                $link(lang('Help with an Order'), '{{page:help}}', 'btn btn-outline-secondary rounded-pill px-4'),
            )),
        )),
    )),
)), array('search' => true, 'sitemap' => true, 'noindex' => false, 'meta_description' => lang('Product details, its maker, options and price.')));
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

$sample_product = $market_products['clay'][0]['title'];
$sample_price = $money($market_products['clay'][0]['price']);

$shop_widgets = array(

    // The shop: the Marketplace group with its categories and the Shops
    // group opening in place (a maker's shop is a group under Shops).
    'shop_catalog' => array(
        'page'     => 'shop',
        'requires' => 'ecommerce',
        'slug'     => lang('catalog'),
        'config'   => array(
            'regionType'               => 'catalog_listing',
            'product_group_id'         => '{{product_group:market}}',
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
            'empty_message'            => lang('Nothing here yet. Try another category or maker.'),
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
    // the shipping and billing addresses, how the pieces of several shops
    // reach the buyer, and the way to help with the order.
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
                $mail_para(lang('Each maker gets your piece ready; we check it, pack your order and send it tracked. You hear from us when it is on its way.'), 'mb-0', 'margin:0'),
            ), lang('Next Step')),
            $mail_para(lang('A question about your order? Ask us from your account and follow the answer there.'), 'mb-1', 'margin:0 0 4px'),
            $mail_button(lang('Help with an Order'), '{{page:help}}'),
            $mail_para(lang('Your order and its receipt are also on the order page:'), 'small mb-1', 'margin:0 0 4px;font-size:13px'),
            $link(lang('View your order'), '{{page:order}}?order_id=^^__order_id^^', 'small', array($attr('style', 'font-size:13px;color:#4b5563'))),
        )),
    ),
);

return array('pages' => $shop_pages, 'widgets' => $shop_widgets, 'catalog' => $catalog);
