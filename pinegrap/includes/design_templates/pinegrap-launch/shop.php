<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Phone Launch Site", its shop: the catalog (the Phones
 * group, a product for each model and storage size, and the Accessories
 * group), the buy page, the accessories page, the product, cart, checkout
 * and order pages and the order receipt e-mail. Included after parts.php
 * and mail.php in the same scope; returns array('pages' => ...,
 * 'widgets' => ..., 'catalog' => ...).
 *
 * The buy page always stands: every model with its sizes and prices, the
 * colours, what each order comes with and the accessories, and the ways on
 * without the shop (the launch list, a trade-in quote, a question). With
 * the shop it also places the phones on sale, each with Add to cart; the
 * accessories page, the shop pages and the receipt need the shop.
 *
 * The prices are $phone_prices and the accessories' own (parts.php): the
 * cards write them with $money(), the products are made from the same
 * figures, so the page and the cart show one amount.
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
// Phones and Accessories groups under the site's top group, each product
// found again by its SKU. A phone is one product per model and storage
// size (ORBIT-<MODEL>-<GB>); the colour is not a variant: every colour
// costs the same and the description names them.
$colour_names = array();
foreach ($colours as $c) $colour_names[] = $c['name'];
$colour_line = sprintf(lang('Comes in %s, all at the same price; we confirm your colour by e-mail before the phone ships.'), implode(', ', $colour_names));

$phone_products = array();
foreach ($models as $key => $m) {
    foreach ($m['storage'] as $gb) {
        $phone_products[] = array(
            'sku'         => $phone_sku($key, $gb),
            'title'       => sprintf(lang('%1$s, %2$s'), $m['name'], $storage_sizes[$gb]),
            'description' => $m['text'] . ' ' . $colour_line,
            'price'       => $phone_prices[$key][$gb],
            'featured'    => $m['featured'],
            'keywords'    => lang('phone, smartphone, Orbit'),
        );
    }
}
$accessory_products = array();
foreach ($accessories as $a) {
    $accessory_products[] = array(
        'sku'         => $a['sku'],
        'title'       => $a['title'],
        'description' => $a['text'],
        'price'       => $a['price'],
    );
}
$catalog = array(
    'folder'  => 'pinegrap_launch_catalog',
    // The Details tab of every product's page.
    'details' => array(
        lang('Free tracked delivery in recycled packaging.'),
        lang('30 days to return, two years of warranty.'),
        lang('A sample product of the template: change its name and price in the catalog, or remove it.'),
    ),
    'groups'  => array(
        'phones' => array(
            'parent'      => 'root',
            'title'       => lang('Phones'),
            'description' => lang('Every Orbit model in every storage size.'),
            'products'    => $phone_products,
        ),
        'accessories' => array(
            'parent'      => 'root',
            'title'       => lang('Accessories'),
            'description' => lang('Cases, chargers and earbuds made for Orbit.'),
            'products'    => $accessory_products,
        ),
    ),
);

// ── Pages ───────────────────────────────────────────────────────────────

// A model on the buy page, at its own anchor (#buy-<model>, where the model
// cards lead): its render, its line, a price for each storage size, the
// colours, and the ways on that stand without the shop.
$buy_model = function ($key) use ($el, $heading, $para, $link, $image, $span, $icon, $container, $row, $col, $attr,
                                  $models, $storage_sizes, $phone_prices, $money, $render, $swatches, $searchable) {
    $m = $models[$key];
    $sizes = array();
    foreach ($m['storage'] as $gb) {
        $sizes[] = $el('li', 'd-flex justify-content-between gap-3 py-2 border-bottom', $storage_sizes[$gb], array(
            $span($storage_sizes[$gb], 'fw-semibold', lang('Storage')),
            $span($money($phone_prices[$key][$gb]), 'plx-price', lang('Price')),
        ));
    }
    $title = array();
    if ($m['featured']) $title[] = $span(lang('Most chosen'), 'badge rounded-pill text-bg-primary mb-2', lang('Badge'));
    $title[] = $heading('h2', $m['name'], 'h3 fw-semibold mb-1');
    $title[] = $para($m['tagline'], 'lead text-body-secondary mb-3');

    return $el('section', 'py-5 border-bottom', $m['name'], array(
        $container(array(
            $row(array(
                $col(array(
                    $el('div', 'ratio ratio-4x3 bg-body-tertiary rounded-4 overflow-hidden', lang('Picture Frame'), array(
                        $image($render($m['name'], 800, 600), $m['name'], 'object-fit-contain p-4'),
                    )),
                ), '', '6'),
                $col(array_merge($title, array(
                    $para($m['text'], 'text-body-secondary mb-4'),
                    $el('h3', 'h6 fw-semibold mb-2', lang('Storage'), array(), array('text' => lang('Choose your storage'))),
                    $el('ul', 'list-unstyled mb-4', lang('Prices'), $sizes),
                    $el('h3', 'h6 fw-semibold mb-3', lang('Colours'), array(), array('text' => lang('Every colour, the same price'))),
                    $swatches('d-flex flex-wrap gap-4 mb-4'),
                    $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                        $link(lang('Get a trade-in quote'), '{{page:tradein}}?model=' . $key, 'btn btn-primary rounded-pill px-4'),
                        $el('a', 'btn btn-outline-secondary rounded-pill px-4 d-inline-flex align-items-center gap-2', lang('Link'), array(
                            $icon('bi-bell'),
                            $span(lang('Hear first'), '', lang('Link Text')),
                        ), array('href' => '{{page:newsletter}}')),
                    )),
                )), '', '6'),
            ), '4', '', 'g-lg-5 align-items-center'),
        )),
    ), array_merge($searchable('buy-' . $key, lang('price, buy, storage, colour, order')), array('_attrs' => array($attr('aria-label', $m['name'])))));
};

$buy_models = array();
foreach (array_keys($models) as $key) $buy_models[] = $buy_model($key);

// What every order comes with.
$promise_cols = array();
foreach ($buy_promises as $p) {
    $promise_cols[] = $col(array(
        $el('div', 'd-flex gap-3', $p['title'], array(
            $icon_tile($p['icon']),
            $el('div', '', lang('Text'), array(
                $heading('h3', $p['title'], 'h6 fw-semibold mb-1'),
                $para($p['text'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), '6', '3');
}

// The accessories, as cards with their price: the buy page's section the
// footer links to (#accessories), standing without the shop.
$accessory_cards = array();
foreach ($accessories as $key => $a) {
    $accessory_cards[] = $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 plx-lift', $a['title'], array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon($a['icon'], 'fs-2 text-primary d-block mb-3'),
                $heading('h3', $a['title'], 'h6 fw-semibold mb-2'),
                $para($a['text'], 'small text-body-secondary mb-3'),
                $para($money($a['price']), 'fw-semibold mt-auto mb-0 plx-price'),
            )),
        )),
    ), '6', '4');
}

$shop_pages[] = array(
    'key'              => 'buy',
    'name'             => lang('buy'),
    'folder'           => 'public',
    'title'            => lang('Buy Orbit'),
    'meta_description' => lang('Every Orbit model with its storage sizes and prices, the four colours, what each order comes with, and the accessories.'),
    'tree'             => $page(array(
        $page_header(lang('Buy Orbit'), lang('Three sizes of one phone. Pick a model and its storage; every colour costs the same.'), array(
            $el('nav', 'd-flex flex-wrap gap-2 mt-4', lang('Models'), array(
                $link($models['mini']['name'], '#buy-mini', 'btn btn-sm btn-outline-secondary rounded-pill px-3'),
                $link($models['one']['name'], '#buy-one', 'btn btn-sm btn-outline-secondary rounded-pill px-3'),
                $link($models['plus']['name'], '#buy-plus', 'btn btn-sm btn-outline-secondary rounded-pill px-3'),
                $link(lang('Accessories'), '#accessories', 'btn btn-sm btn-outline-secondary rounded-pill px-3'),
            ), array('_attrs' => array($attr('aria-label', lang('On this page'))))),
        )),
        $el('div', '', lang('Models'), $buy_models),
        // The phones on sale in the shop; left out on a site without it.
        $widget('buy_phones'),
        $el('section', 'py-5 bg-body-tertiary', lang('With Every Order'), array(
            $container(array(
                $section_header(lang('With every order'), lang('What comes with your Orbit')),
                $row($promise_cols, '4'),
            )),
        )),
        $el('section', 'py-5', lang('Accessories'), array(
            $container(array(
                $section_header(lang('Made for Orbit'), lang('Accessories'), lang('Compare Models'), '{{page:compare}}'),
                $row($accessory_cards, '4'),
            )),
        ), $searchable('accessories', lang('case, charger, earbuds, screen protector, accessories'))),
        $el('section', 'pb-5', lang('Not Ready Yet'), array(
            $container(array(
                $el('div', 'd-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 p-4 rounded-4 border', lang('Box'), array(
                    $el('div', '', lang('Text'), array(
                        $heading('h2', lang('Not ready to order?'), 'h5 fw-semibold mb-1'),
                        $para(lang('Join the launch list for offers and stock news, or ask us anything about the phone.'), 'text-body-secondary mb-0'),
                    )),
                    $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                        $link(lang('Join the launch list'), '{{page:newsletter}}', 'btn btn-primary rounded-pill px-4'),
                        $link(lang('Contact us'), '{{page:contact}}', 'btn btn-outline-secondary rounded-pill px-4'),
                    )),
                )),
            )),
        )),
    )),
);

// The accessories on sale, a page of the shop.
$shop_pages[] = array(
    'key'              => 'accessories',
    'requires'         => 'ecommerce',
    'name'             => lang('accessories'),
    'folder'           => 'public',
    'title'            => lang('Accessories'),
    'meta_description' => lang('Cases, chargers, a charging stand, earbuds and screen glass made for Orbit.'),
    'tree'             => $page(array(
        $page_header(lang('Accessories'), lang('Made for Orbit, in the same recycled materials and the same colours.')),
        $el('section', 'py-5', lang('Products'), array(
            $container(array($widget('accessory_catalog'))),
        )),
        $shared('launch_band'),
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

// A Bootstrap button component: a product card's Add to cart, which the
// listing wraps in the form that adds the product.
$shop_button = function ($text, $variant, $class = '', $extra = array()) {
    return array('type' => 'component', 'props' => array_merge(array('componentType' => 'btn', 'btnElement' => 'button', 'btnType' => 'submit',
        'variant' => $variant, 'text' => $text, 'cssClass' => $class), $extra), 'children' => array());
};

// A product card of the listings: its icon, its name (the link to its
// page), its price and Add to cart.
$shop_product_card = function ($icon_name, $sample_name, $sample_price) use ($el, $link, $span, $col, $bind, $icon_tile, $shop_button) {
    return $col(array(
        $el('div', 'card h-100 border-0 shadow-sm rounded-4', lang('Product Card'), array(
            $el('div', 'card-body d-flex align-items-center gap-3 p-4', lang('Card Body'), array(
                $icon_tile($icon_name),
                $el('div', 'flex-grow-1', lang('Words'), array(
                    $el('h3', 'h6 fw-semibold mb-1 position-static', lang('Product Name'), array(
                        $link($sample_name, '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                            array('_bindings' => array('text' => '__short_description', 'href' => '__detail_url'))),
                    )),
                    $span($sample_price, 'fw-semibold text-primary plx-price', lang('Price'), $bind('__price_formatted')),
                )),
                $shop_button(lang('Add to cart'), 'primary', 'position-relative z-2 text-nowrap rounded-pill',
                    array('size' => 'sm', '_bindings' => array('action' => 'catalog_add_to_cart'))),
            )),
        )),
    ), '6', '4');
};

// A listing of one group of the catalog, every product on one page. The
// notice after an add links this shop's cart.
$shop_listing = function ($group, $empty) {
    return array(
        'regionType'               => 'catalog_listing',
        'product_group_id'         => '{{product_group:' . $group . '}}',
        'group_navigation'         => 'none',
        'items_per_page'           => 24,
        'order_by_field'           => 'sort_order',
        'order_by_direction'       => 'ASC',
        'detail_page_id'           => '{{tab:product}}',
        'add_to_cart_stay_on_page' => true,
        'add_to_cart_next_page_id' => '{{tab:cart}}',
        'empty_message'            => $empty,
    );
};

// A line of the receipt's totals, shown while its flag holds.
$shop_total_row = function ($name, $label, $token, $flag = '', $class = '', $style = 'text-align:right') use ($el, $span, $bind, $show_when, $mail_style) {
    return $el('tr', $class, $name, array(
        $el('td', 'ps-0' . ($class === '' ? ' text-body-secondary' : ''), lang('Label'), array($span($label, '', lang('Text')))),
        $el('td', 'pe-0 text-end', lang('Value'), array($span('$899.00', '', lang('Amount'), $bind($token))), $mail_style($style)),
    ), $flag !== '' ? $show_when($flag) : array());
};

$sample_phone = sprintf(lang('%1$s, %2$s'), $models['one']['name'], $storage_sizes['256']);

$shop_widgets = array(

    // The phones on sale: the Phones group's products, each model in each
    // storage size, with Add to cart.
    'buy_phones' => array(
        'page'     => 'buy',
        'requires' => 'ecommerce',
        'slug'     => lang('catalog'),
        'config'   => $shop_listing('phones', lang('No phones on sale right now.')),
        'tree'     => $widget_root(array(
            $el('section', 'py-5 border-bottom', lang('Order Online'), array(
                $container(array(
                    $section_header(lang('Order online'), lang('Choose your Orbit'), lang('Accessories'), '{{page:accessories}}'),
                    $row(array($loop(array($shop_product_card('bi-phone', $sample_phone, $money($phone_prices['one']['256']))))), '4'),
                    $para($colour_line, 'small text-body-secondary mt-4 mb-0'),
                )),
            ), array('id' => 'buy-online')),
        )),
    ),

    // The accessories on sale, on their page.
    'accessory_catalog' => array(
        'page'     => 'accessories',
        'requires' => 'ecommerce',
        'slug'     => lang('catalog'),
        'config'   => $shop_listing('accessories', lang('No accessories on sale right now.')),
        'tree'     => $widget_root(array(
            $row(array($loop(array($shop_product_card('bi-bag', $accessories['case']['title'], $money($accessories['case']['price']))))), '4'),
        )),
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
    // product's breadcrumb starts at the buy page.
    'product_detail' => array(
        'page'     => 'product',
        'requires' => 'ecommerce',
        'slug'     => lang('product-detail'),
        'config'   => array(
            'regionType'              => 'catalog_item_view',
            'product_group_id'        => 0,
            'next_page_id'            => '{{tab:cart}}',
            'catalog_listing_page_id' => '{{tab:buy}}',
            'catalog_root_label'      => lang('Buy Orbit'),
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
    // the shipping and billing addresses, the colour note and the way to a
    // trade-in quote for the old phone.
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
                                $span($sample_phone, 'fw-semibold', lang('Product Name'), $bind('__item_short_description')),
                                $span(' × ', 'text-body-secondary', lang('Text')),
                                $span('1', '', lang('Quantity'), $bind('__item_qty')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #ede9fe')),
                            $el('td', 'pe-0 text-end', lang('Total'), array(
                                $span('$899.00', '', lang('Amount'), $bind('__item_total')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #ede9fe;text-align:right')),
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
                $el('p', 'fw-semibold mb-1', lang('Title'), array(), array_merge(array('text' => lang('Your colour')), $mail_style('font-weight:600;margin:0 0 4px'))),
                $mail_para(lang('We write to you before the phone ships to confirm the colour you chose. Ordered accessories ship with it.'), 'mb-0', 'margin:0'),
            ), lang('Next Step')),
            $mail_para(lang('Trading in your old phone? Ask for a quote; the credit comes back to you once we have checked it.'), 'mb-1', 'margin:0 0 4px'),
            $mail_button(lang('Get a trade-in quote'), '{{page:tradein}}'),
            $mail_para(lang('Your order and its receipt are also on the order page:'), 'small mb-1', 'margin:0 0 4px;font-size:13px'),
            $link(lang('View your order'), '{{page:order}}?order_id=^^__order_id^^', 'small', array($attr('style', 'font-size:13px;color:#5b21b6'))),
        )),
    ),
);

return array('pages' => $shop_pages, 'widgets' => $shop_widgets, 'catalog' => $catalog);
