<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Travel Agency", its shop: the catalog (the Tour
 * Packages group: the deposit of each sample tour of $tours and the gift
 * cards of $gift_cards), the packages page, the product, cart, checkout
 * and order pages and the order receipt e-mail. Included after parts.php
 * and mail.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ..., 'catalog' => ...).
 *
 * Every page and widget here needs the shop: the shared parts and the
 * record pages never link them, so a site without the shop has none of
 * them and no dead link.
 *
 * The prices are parts.php's own ($deposit, $gift_cards): the cards write
 * them with $money(), the products are made from the same figures, so the
 * page and the cart show one amount. A deposit is paid once the agency has
 * confirmed a booking request, and a gift card is sent by e-mail, so the
 * products are not shippable and the checkout asks for no delivery
 * address.
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
// Tour Packages group under the site's top group, each product found again
// by its SKU. A deposit per sample tour first, then the gift cards.
$package_products = array();
foreach ($tours as $key => $t) {
    $package_products[] = array(
        'sku'         => 'TRAVEL-DEPOSIT-' . strtoupper($key),
        'title'       => sprintf(lang('Deposit: %s'), $t['name']),
        'description' => sprintf(lang('The deposit that holds one traveller\'s place on %s, once we have confirmed your booking request.'), $t['name']),
        'price'       => $deposit,
        'shippable'   => false,
        'featured'    => !empty($t['featured']),
        'keywords'    => lang('deposit, tour, booking, travel, holiday'),
    );
}
foreach ($gift_cards as $key => $g) {
    $package_products[] = array(
        'sku'         => $g['sku'],
        'title'       => $g['title'],
        'description' => $g['text'],
        'price'       => $g['price'],
        'shippable'   => false,
        'featured'    => $key === 'gift250',
        'keywords'    => lang('gift card, gift, travel, holiday, tour'),
    );
}
$sample_deposit_title = $package_products[0]['title'];
$catalog = array(
    'folder'  => 'pinegrap_travel_catalog',
    // The Details tab of every product's page.
    'details' => array(
        lang('A deposit is paid once we have confirmed your places; the balance is due six weeks before departure.'),
        lang('A gift card is sent by e-mail within one working day of the order, with its code, and is valid for two years.'),
        lang('A sample product of the template: change its name and price in the catalog, or remove it.'),
    ),
    'groups'  => array(
        'packages' => array(
            'parent'      => 'root',
            'title'       => lang('Tour Packages'),
            'description' => lang('The deposits that hold your places on a tour, and gift cards towards any journey.'),
            'products'    => $package_products,
        ),
    ),
);

// ── Pages ───────────────────────────────────────────────────────────────

// How paying works, in three steps.
$package_steps = array(
    $step(1, lang('Book your places'), lang('Send a booking request; we confirm your places on the departure within a day.')),
    $step(2, lang('Pay the deposit'), sprintf(lang('%s per traveller, for the tour we confirmed, at the checkout as for any order.'), $money($deposit))),
    $step(3, lang('The rest before you leave'), lang('The balance is due six weeks before departure; we write to you under your booking request.')),
);

$shop_pages[] = array(
    'key'              => 'packages',
    'requires'         => 'ecommerce',
    'name'             => lang('tour-packages'),
    'folder'           => 'public',
    'title'            => lang('Tour Packages'),
    'meta_description' => lang('Pay the deposit for a confirmed tour, or give a travel gift card sent by e-mail and valid for two years.'),
    'tree'             => $page(array(
        $page_header(lang('Tour Packages'), lang('The deposit that holds your places on a confirmed tour, and gift cards for someone who dreams of a journey.')),
        $el('section', 'py-5', lang('Packages'), array(
            $container(array($widget('package_catalog'))),
        ), $searchable('tv-packages', lang('deposit, pay, gift card, present, voucher'))),
        $el('section', 'py-5 bg-body-tertiary', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $section_header(lang('Paying for a tour'), lang('How it works')),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), $package_steps),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body border', lang('Box'), array(
                            $heading('h3', lang('Pay a deposit only once we confirm'), 'ptv-display h5 mb-2'),
                            $para(lang('A deposit holds places we have confirmed for you. Not booked yet? Send a booking request first; a gift card can be named in its wishes.'), 'text-body-secondary mb-3'),
                            $link(lang('Book a Tour'), '{{page:book}}', 'btn btn-primary rounded-pill px-4'),
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

// A Bootstrap button component: a package card's Add to cart, which the
// listing wraps in the form that adds the product.
$shop_button = function ($text, $variant, $class = '', $extra = array()) {
    return array('type' => 'component', 'props' => array_merge(array('componentType' => 'btn', 'btnElement' => 'button', 'btnType' => 'submit',
        'variant' => $variant, 'text' => $text, 'cssClass' => $class), $extra), 'children' => array());
};

// A package's card of the listing: a suitcase (the same for every product
// the listing repeats it for), its name (the link to its page), its price
// and Add to cart.
$package_card = function ($v) use ($el, $link, $span, $col, $bind, $icon_tile, $shop_button, $money) {
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 ptv-lift', lang('Product Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile('bi-suitcase', 'align-self-start mb-3'),
                $el('h3', 'ptv-display h5 mb-2 position-static', lang('Product Name'), array(
                    $link($v['title'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => '__short_description', 'href' => '__detail_url'))),
                )),
                $el('div', 'd-flex align-items-center justify-content-between gap-3 mt-auto pt-3', lang('Buy Line'), array(
                    $span($money($v['price']), 'ptv-price fw-semibold fs-5', lang('Price'), $bind('__price_formatted')),
                    $shop_button(lang('Add to cart'), 'primary', 'position-relative z-2 text-nowrap rounded-pill',
                        array('size' => 'sm', '_bindings' => array('action' => 'catalog_add_to_cart'))),
                )),
            )),
        )),
    ), '6', '4');
};

// A line of the receipt's totals, shown while its flag holds.
$shop_total_row = function ($name, $label, $token, $flag = '', $class = '', $style = 'text-align:right') use ($el, $span, $bind, $show_when, $mail_style) {
    return $el('tr', $class, $name, array(
        $el('td', 'ps-0' . ($class === '' ? ' text-body-secondary' : ''), lang('Label'), array($span($label, '', lang('Text')))),
        $el('td', 'pe-0 text-end', lang('Value'), array($span('$250.00', '', lang('Amount'), $bind($token))), $mail_style($style)),
    ), $flag !== '' ? $show_when($flag) : array());
};

$shop_widgets = array(

    // The packages on sale: the Tour Packages group's products, every one
    // on one page. The notice after an add links this shop's cart.
    'package_catalog' => array(
        'page'     => 'packages',
        'requires' => 'ecommerce',
        'slug'     => lang('catalog'),
        'config'   => array(
            'regionType'               => 'catalog_listing',
            'product_group_id'         => '{{product_group:packages}}',
            'group_navigation'         => 'none',
            'items_per_page'           => 24,
            'order_by_field'           => 'sort_order',
            'order_by_direction'       => 'ASC',
            'detail_page_id'           => '{{tab:product}}',
            'add_to_cart_stay_on_page' => true,
            'add_to_cart_next_page_id' => '{{tab:cart}}',
            'empty_message'            => lang('No tour packages on sale right now.'),
        ),
        'tree'     => $widget_root(array(
            $row(array($loop(array($package_card($package_products[0])))), '4'),
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
    // product's breadcrumb starts at the packages page.
    'product_detail' => array(
        'page'     => 'product',
        'requires' => 'ecommerce',
        'slug'     => lang('product-detail'),
        'config'   => array(
            'regionType'              => 'catalog_item_view',
            'product_group_id'        => 0,
            'next_page_id'            => '{{tab:cart}}',
            'catalog_listing_page_id' => '{{tab:packages}}',
            'catalog_root_label'      => lang('Tour Packages'),
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
    // totals, the billing address, what a deposit and a gift card do next
    // and the way to the traveller's bookings.
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
                                $span($sample_deposit_title, 'fw-semibold', lang('Product Name'), $bind('__item_short_description')),
                                $span(' × ', 'text-body-secondary', lang('Text')),
                                $span('1', '', lang('Quantity'), $bind('__item_qty')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #e0f2fe')),
                            $el('td', 'pe-0 text-end', lang('Total'), array(
                                $span('$250.00', '', lang('Amount'), $bind('__item_total')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #e0f2fe;text-align:right')),
                        )),
                    )),
                )),
            ), $mail_style('width:100%;border-collapse:collapse;margin:0 0 16px')),
            $el('table', 'table table-sm mb-4', lang('Totals'), array(
                $el('tbody', '', lang('Rows'), array(
                    $shop_total_row(lang('Subtotal'), lang('Subtotal'), '__order_subtotal'),
                    $shop_total_row(lang('Discount'), lang('Discount'), '__order_discount', 'has_discount'),
                    $shop_total_row(lang('Tax'), lang('VAT'), '__order_tax', 'has_tax'),
                    $shop_total_row(lang('Total'), lang('Total'), '__order_total', '', 'fw-bold', 'text-align:right;font-weight:700'),
                )),
            ), $mail_style('width:100%;border-collapse:collapse;margin:0 0 24px')),
            $el('div', '', lang('Billing Address'), array(
                $el('p', 'small text-body-secondary mb-1', lang('Label'), array(), array('text' => lang('Billing Address'))),
                $el('div', '', lang('Address'), array(), array_merge(array('text' => lang('Jane Cooper')), $bind('__billing_address_html'))),
            ), $mail_style('margin:0 0 16px')),
            $mail_box(array(
                $el('p', 'fw-semibold mb-1', lang('Title'), array(), array_merge(array('text' => lang('What happens next')), $mail_style('font-weight:600;margin:0 0 4px'))),
                $mail_para(lang('A deposit holds your places on the departure we confirmed under your booking request. A gift card comes to this address within one working day, with its code.'), 'mb-0', 'margin:0'),
            ), lang('Next Step')),
            $mail_button(lang('My Bookings'), '{{page:my_bookings}}'),
            $mail_para(lang('Your order and its receipt are also on the order page:'), 'small mb-1', 'margin:0 0 4px;font-size:13px'),
            $link(lang('View your order'), '{{page:order}}?order_id=^^__order_id^^', 'small', array($attr('style', 'font-size:13px;color:#0369a1'))),
        )),
    ),
);

return array('pages' => $shop_pages, 'widgets' => $shop_widgets, 'catalog' => $catalog);
