<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Ski Resort", its shop: the catalog (the Lift Passes,
 * Season Passes and Rental groups, a product for each item of
 * $lift_passes, $season_passes and $rental), the buy passes and rental shop
 * pages, the product, cart, checkout and order pages and the order receipt
 * e-mail. Included after parts.php, cards.php and mail.php in the same
 * scope; returns array('pages' => ..., 'widgets' => ..., 'catalog' => ...).
 *
 * Every page and widget here needs the shop: the shared parts and the
 * public pages never link them (the passes and rental pages say the prices
 * as words), so a site without the shop has none of them and no dead link.
 *
 * The prices are parts.php's own ($lift_pass_prices, $season_pass_prices,
 * $rental_prices): the cards write them with $money(), the products are
 * made from the same figures, so the page and the cart show one amount. A
 * pass is collected at the ticket office and rental at the rental shop,
 * both from the receipt: nothing is shipped, so the checkout asks for no
 * delivery address.
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
// Made once when the template is first opened (_pg_tpl_catalog()): three
// groups under the site's top group, each product found again by its SKU,
// in the order of the data.
$shop_sku = function ($prefix, $key) {
    return $prefix . strtoupper(str_replace('_', '-', $key));
};
$lift_pass_products = array();
foreach ($lift_passes as $key => $p) {
    $lift_pass_products[] = array(
        'sku'         => $shop_sku('SKI-PASS-', $key),
        'title'       => $p['title'],
        'description' => $p['who'] . '. ' . $p['includes'] . '.',
        'price'       => $lift_pass_prices[$key],
        'shippable'   => false,
        'featured'    => $key === 'day',
        'keywords'    => lang('lift pass, skipass, day pass, ski, lifts'),
    );
}
$season_pass_products = array();
foreach ($season_passes as $key => $p) {
    $season_pass_products[] = array(
        'sku'         => $shop_sku('SKI-SEASON-', $key),
        'title'       => $p['title'],
        'description' => $p['who'] . '. ' . implode(' · ', $p['benefits']) . '.',
        'price'       => $season_pass_prices[$key],
        'shippable'   => false,
        'featured'    => $key === 'family',
        'keywords'    => lang('season pass, whole winter, family pass, ski, lifts'),
    );
}
$rental_products = array();
foreach ($rental as $key => $r) {
    $rental_products[] = array(
        'sku'         => $shop_sku('SKI-RENT-', $key),
        'title'       => sprintf(lang('Rental per day: %s'), $r['title']),
        'description' => $r['includes'] . '.',
        'price'       => $rental_prices[$key],
        'shippable'   => false,
        'featured'    => $key === 'ski_set',
        'keywords'    => lang('rental, hire, skis, snowboard, helmet, equipment'),
    );
}
$catalog = array(
    'folder'  => 'pinegrap_skiresort_catalog',
    // The Details tab of every product's page.
    'details' => array(
        lang('Show the order receipt at the ticket office at the foot of the gondola; your pass is loaded on a card you keep for the next visit.'),
        lang('Rental is collected at the rental shop from the receipt: one item for each day you ski, fitted by our technicians.'),
        lang('A sample product of the template: change its name and price in the catalog, or remove it.'),
    ),
    'groups'  => array(
        'lift_passes' => array(
            'parent'      => 'root',
            'title'       => lang('Lift Passes'),
            'description' => lang('From an afternoon to a whole week, for every lift of the resort.'),
            'products'    => $lift_pass_products,
        ),
        'season_passes' => array(
            'parent'      => 'root',
            'title'       => lang('Season Passes'),
            'description' => lang('Every day of the winter, for one skier or the whole family.'),
            'products'    => $season_pass_products,
        ),
        'rental' => array(
            'parent'      => 'root',
            'title'       => lang('Rental'),
            'description' => lang('Fitted by our technicians at the foot of the gondola.'),
            'products'    => $rental_products,
        ),
    ),
);

// ── Pages ───────────────────────────────────────────────────────────────

// How a pass bought online works, in three steps.
$pass_shop_steps = array(
    $step(1, lang('Choose your passes'), lang('One for each skier; children under six ski free with an adult.')),
    $step(2, lang('Pay online'), lang('The receipt comes by e-mail at once, with the order number.')),
    $step(3, lang('Collect at the ticket office'), lang('Show the receipt at the foot of the gondola from 08:00 and take your card to the lifts.')),
);

$shop_pages[] = array(
    'key'              => 'buy_passes',
    'requires'         => 'ecommerce',
    'name'             => lang('buy-passes'),
    'folder'           => 'public',
    'title'            => lang('Buy Lift Passes'),
    'meta_description' => lang('Lift passes and season passes online: pay now and collect your pass at the ticket office from the receipt.'),
    'tree'             => $page(array(
        $page_header(lang('Buy Lift Passes'), lang('Buy your pass online and skip the queue at the ticket office. Children under six ski free.')),
        $el('section', 'py-5', lang('Lift Passes'), array(
            $container(array(
                $section_header(lang('Every lift'), lang('Lift passes')),
                $widget('lift_pass_catalog'),
            )),
        ), $searchable('skr-shop-passes', lang('buy lift pass, skipass, day pass, half day, three days, six days, price'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Season Passes'), array(
            $container(array(
                $section_header(lang('The whole winter'), lang('Season passes')),
                $widget('season_pass_catalog'),
            )),
        ), $searchable('skr-shop-season', lang('buy season pass, family pass, young, senior, whole winter'))),
        $el('section', 'py-5', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $section_header(lang('Online in three steps'), lang('How it works')),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), $pass_shop_steps),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary border mb-4', lang('Box'), array(
                            $heading('h3', lang('Need equipment?'), 'psk-display h5 mb-2'),
                            $para(lang('Book your skis, board or helmet by the day and collect them fitted at the rental shop.'), 'text-body-secondary mb-3'),
                            $link(lang('Book Rental'), '{{page:rental_shop}}', 'btn btn-primary px-4'),
                        )),
                        $el('div', 'p-4 rounded-4 bg-body-tertiary border', lang('Box'), array(
                            $heading('h3', lang('Coming as a group?'), 'psk-display h5 mb-2'),
                            $para(lang('Schools, clubs and companies of ten or more get a group price: tell us your dates and we send an offer.'), 'text-body-secondary mb-3'),
                            $link(lang('Ask About Groups'), '{{page:group_enquiry}}', 'btn btn-outline-primary px-4'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    )),
);

// How rental booked online works, in three steps.
$rental_shop_steps = array(
    $step(1, lang('Choose your equipment'), lang('One item for each day you ski; a set includes boots.')),
    $step(2, lang('Pay online'), lang('The receipt comes by e-mail at once, with the order number.')),
    $step(3, lang('Get fitted at the rental shop'), lang('Show the receipt at the foot of the gondola; our technicians fit your boots and set your skis or board.')),
);

$shop_pages[] = array(
    'key'              => 'rental_shop',
    'requires'         => 'ecommerce',
    'name'             => lang('rental-shop'),
    'folder'           => 'public',
    'title'            => lang('Book Rental'),
    'meta_description' => lang('Book ski sets, snowboard sets, children\'s sets and helmets by the day online and collect them fitted at the rental shop.'),
    'tree'             => $page(array(
        $page_header(lang('Book Rental'), lang('Book your equipment by the day and walk out of the rental shop ready for the slopes.')),
        $el('section', 'py-5', lang('Rental Equipment'), array(
            $container(array($widget('rental_catalog'))),
        ), $searchable('skr-shop-rental', lang('book rental, hire, skis, boots, snowboard, helmet, price per day'))),
        $el('section', 'py-5 bg-body-tertiary', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $section_header(lang('Online in three steps'), lang('How it works')),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), $rental_shop_steps),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body border mb-4', lang('Box'), array(
                            $heading('h3', lang('Not sure of your size?'), 'psk-display h5 mb-2'),
                            $para(lang('Our technicians measure you at the rental shop and swap sizes at no charge during your stay.'), 'text-body-secondary mb-3'),
                            $link(lang('How rental works'), '{{page:rental}}', 'btn btn-outline-primary px-4'),
                        )),
                        $el('div', 'p-4 rounded-4 bg-body border', lang('Box'), array(
                            $heading('h3', lang('Your lift pass'), 'psk-display h5 mb-2'),
                            $para(lang('Buy your pass in the same order and collect both on the morning of your first day.'), 'text-body-secondary mb-3'),
                            $link(lang('Buy Lift Passes'), '{{page:buy_passes}}', 'btn btn-primary px-4'),
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

// A Bootstrap button component: a card's Add to cart, which the listing
// wraps in the form that adds the product.
$shop_button = function ($text, $variant, $class = '', $extra = array()) {
    return array('type' => 'component', 'props' => array_merge(array('componentType' => 'btn', 'btnElement' => 'button', 'btnType' => 'submit',
        'variant' => $variant, 'text' => $text, 'cssClass' => $class), $extra), 'children' => array());
};

// A product's card of a listing: an icon, its name (the link to its
// page), the sample's words, its price and Add to cart.
$shop_card = function ($icon_name, $title, $who, $price, $lg) use ($el, $link, $span, $para, $col, $bind, $icon_tile, $shop_button) {
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm psk-lift', lang('Product Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile($icon_name, 'align-self-start mb-3'),
                $el('h3', 'psk-display h5 mb-1 position-static', lang('Product Name'), array(
                    $link($title, '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => '__short_description', 'href' => '__detail_url'))),
                )),
                $para($who, 'small text-body-secondary mb-0'),
                $el('div', 'd-flex align-items-center justify-content-between gap-3 mt-auto pt-3', lang('Buy Line'), array(
                    $span($price, 'psk-price fw-semibold fs-5', lang('Price'), $bind('__price_formatted')),
                    $shop_button(lang('Add to cart'), 'primary', 'position-relative z-2 text-nowrap',
                        array('size' => 'sm', '_bindings' => array('action' => 'catalog_add_to_cart'))),
                )),
            )),
        )),
    ), '6', $lg);
};

// The listing of one group: every product on one page, the notice after an
// add names this shop's cart.
$shop_listing = function ($page_key, $slug, $group, $empty, $card) use ($widget_root, $row, $loop) {
    return array(
        'page'     => $page_key,
        'requires' => 'ecommerce',
        'slug'     => $slug,
        'config'   => array(
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
        ),
        'tree'     => $widget_root(array(
            $row(array($loop(array($card))), '4'),
        )),
    );
};

// A line of the receipt's totals, shown while its flag holds.
$shop_total_row = function ($name, $label, $token, $flag = '', $class = '', $style = 'text-align:right') use ($el, $span, $bind, $show_when, $mail_style) {
    return $el('tr', $class, $name, array(
        $el('td', 'ps-0' . ($class === '' ? ' text-body-secondary' : ''), lang('Label'), array($span($label, '', lang('Text')))),
        $el('td', 'pe-0 text-end', lang('Value'), array($span('$55.00', '', lang('Amount'), $bind($token))), $mail_style($style)),
    ), $flag !== '' ? $show_when($flag) : array());
};

$shop_widgets = array(

    // The passes on sale on the buy passes page, the rental on its own.
    'lift_pass_catalog'   => $shop_listing('buy_passes', lang('lift-pass-catalog'), 'lift_passes', lang('No lift passes on sale online right now: buy them at the ticket office.'),
        $shop_card('bi-ticket-perforated', $lift_passes['day']['title'], $lift_passes['day']['who'], $money($lift_pass_prices['day']), '3')),
    'season_pass_catalog' => $shop_listing('buy_passes', lang('season-pass-catalog'), 'season_passes', lang('No season passes on sale online right now: ask at the ticket office.'),
        $shop_card('bi-snow2', $season_passes['family']['title'], $season_passes['family']['who'], $money($season_pass_prices['family']), '3')),
    'rental_catalog'      => $shop_listing('rental_shop', lang('rental-catalog'), 'rental', lang('No rental on sale online right now: come to the rental shop.'),
        $shop_card($rental['ski_set']['icon'], $rental['ski_set']['title'], $rental['ski_set']['includes'], $money($rental_prices['ski_set']), '3')),

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
    // product's breadcrumb starts at the buy passes page.
    'product_detail' => array(
        'page'     => 'product',
        'requires' => 'ecommerce',
        'slug'     => lang('product-detail'),
        'config'   => array(
            'regionType'              => 'catalog_item_view',
            'product_group_id'        => 0,
            'next_page_id'            => '{{tab:cart}}',
            'catalog_listing_page_id' => '{{tab:buy_passes}}',
            'catalog_root_label'      => lang('Buy Lift Passes'),
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
    // the billing address, where a pass and rental are collected, and the
    // way to today's conditions.
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
                                $span($lift_passes['day']['title'], 'fw-semibold', lang('Product Name'), $bind('__item_short_description')),
                                $span(' × ', 'text-body-secondary', lang('Text')),
                                $span('1', '', lang('Quantity'), $bind('__item_qty')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #dde3ea')),
                            $el('td', 'pe-0 text-end', lang('Total'), array(
                                $span($money($lift_pass_prices['day']), '', lang('Amount'), $bind('__item_total')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #dde3ea;text-align:right')),
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
                $el('p', 'fw-semibold mb-1', lang('Title'), array(), array_merge(array('text' => lang('What comes next')), $mail_style('font-weight:600;margin:0 0 4px'))),
                $mail_para(lang('Show this receipt at the ticket office at the foot of the gondola to collect your pass, and at the rental shop to collect your equipment. Both open at 08:00.'), 'mb-0', 'margin:0'),
            ), lang('Next Step')),
            $mail_button(lang('Today\'s Conditions'), '{{page:conditions}}'),
            $mail_para(lang('Your order and its receipt are also on the order page:'), 'small mb-1', 'margin:0 0 4px;font-size:13px'),
            $link(lang('View your order'), '{{page:order}}?order_id=^^__order_id^^', 'small', array($attr('style', 'font-size:13px;color:#075985;color:var(--bs-link-color,#075985)'))),
        )),
    ),
);

return array('pages' => $shop_pages, 'widgets' => $shop_widgets, 'catalog' => $catalog);
