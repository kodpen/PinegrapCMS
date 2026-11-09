<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Planetarium", its shop: the catalog (the Tickets and
 * Memberships groups, a product for each item of $tickets and
 * $memberships), the buy tickets page, the product, cart, checkout and
 * order pages and the order receipt e-mail. Included after parts.php,
 * cards.php and mail.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ..., 'catalog' => ...).
 *
 * Every page and widget here needs the shop: the shared parts and the
 * public pages never link them (the tickets and memberships pages say the
 * prices as words), so a site without the shop has none of them and no
 * dead link.
 *
 * The prices are parts.php's own ($ticket_prices, $membership_prices): the
 * cards write them with $money(), the products are made from the same
 * figures, so the page and the cart show one amount. A ticket is shown at
 * the door from the receipt and a membership starts when the box office
 * adds the name on it: nothing is shipped, so the checkout asks for no
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
// Made once when the template is first opened (_pg_tpl_catalog()): two
// groups under the site's top group, each product found again by its SKU,
// in the order of the data.
$shop_sku = function ($prefix, $key) {
    return $prefix . strtoupper(str_replace('_', '-', $key));
};
$ticket_products = array();
foreach ($tickets as $key => $t) {
    $ticket_products[] = array(
        'sku'         => $shop_sku('PLM-TICKET-', $key),
        'title'       => $t['title'],
        'description' => $t['who'] . '.',
        'price'       => $ticket_prices[$key],
        'shippable'   => false,
        'featured'    => $key === 'dome_adult',
        'keywords'    => $key === 'night'
            ? lang('telescope night, ticket, observatory, stargazing')
            : lang('dome show, ticket, planetarium, show'),
    );
}
$membership_products = array();
foreach ($memberships as $key => $m) {
    $membership_products[] = array(
        'sku'         => $shop_sku('PLM-MEMBER-', $key),
        'title'       => $m['title'],
        'description' => $m['who'] . '. ' . implode(' · ', $m['benefits']) . '.',
        'price'       => $membership_prices[$key],
        'shippable'   => false,
        'featured'    => $key === 'family',
        'keywords'    => lang('membership, a year, free dome shows, telescope nights'),
    );
}
$catalog = array(
    'folder'  => 'pinegrap_planetarium_catalog',
    // The Details tab of every product's page.
    'details' => array(
        lang('Your receipt is your ticket: show it at the door of the dome or the observatory on your phone or on paper.'),
        lang('A membership starts on the day the box office adds your name from the receipt and runs for twelve months.'),
        lang('A sample product of the template: change its name and price in the catalog, or remove it.'),
    ),
    'groups'  => array(
        'tickets' => array(
            'parent'      => 'root',
            'title'       => lang('Tickets'),
            'description' => lang('Dome show tickets for adults, children and families, and telescope night tickets.'),
            'products'    => $ticket_products,
        ),
        'memberships' => array(
            'parent'      => 'root',
            'title'       => lang('Memberships'),
            'description' => lang('A year of the sky for one or the whole family.'),
            'products'    => $membership_products,
        ),
    ),
);

// ── Pages ───────────────────────────────────────────────────────────────

// How a ticket bought online works, in three steps.
$ticket_shop_steps = array(
    $step(1, lang('Choose your tickets'), lang('One for each visitor; under three free on a lap.')),
    $step(2, lang('Pay online'), lang('The receipt comes by e-mail at once, with the order number.')),
    $step(3, lang('Show your receipt'), lang('Your receipt is your ticket at the door of the dome or the observatory.')),
);

$shop_pages[] = array(
    'key'              => 'buy_tickets',
    'requires'         => 'ecommerce',
    'name'             => lang('buy-tickets'),
    'folder'           => 'public',
    'title'            => lang('Buy Tickets'),
    'meta_description' => lang('Dome show and telescope night tickets and memberships online: pay now and show the receipt at the door.'),
    'tree'             => $page(array(
        $page_header(lang('Buy Tickets'), lang('Buy your seat in the dome or at the eyepiece online and skip the box office queue.'), array(), false),
        $el('section', 'py-5', lang('Tickets'), array(
            $container(array(
                $section_header(lang('Tickets'), lang('One ticket for one show or one night.')),
                $widget('ticket_catalog'),
            )),
        ), $searchable('plm-shop-tickets', lang('buy ticket, dome show, telescope night, adult, child, family, price'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Memberships'), array(
            $container(array(
                $section_header(lang('Memberships'), lang('A year of the sky, and the observatory kept open for everyone.')),
                $widget('membership_catalog'),
            )),
        ), $searchable('plm-shop-memberships', lang('buy membership, single, family, patron, a year'))),
        $el('section', 'py-5', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $section_header(lang('Online in three steps')),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), $ticket_shop_steps),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary border mb-4', lang('Box'), array(
                            $heading('h3', lang('Which show, which night?'), 'ppm-display h5 mb-2'),
                            $para(lang('Pick the day and the showtime from the programme; a telescope night is called on the day by our astronomers.'), 'text-body-secondary mb-3'),
                            $link(lang('All Dome Shows'), '{{page:shows}}', 'btn btn-primary px-4 me-2 mb-2'),
                            $link(lang('Telescope Nights'), '{{page:nights}}', 'btn btn-outline-primary px-4 mb-2'),
                        )),
                        $el('div', 'p-4 rounded-4 bg-body-tertiary border', lang('Box'), array(
                            $heading('h3', lang('Coming with a class?'), 'ppm-display h5 mb-2'),
                            $para(lang('School and group visits are asked for, not bought: tell us your date and we write back.'), 'text-body-secondary mb-3'),
                            $link(lang('Request a School Visit'), '{{page:visit_request}}', 'btn btn-outline-primary px-4'),
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
        'tree'     => $page(array($widget($widget_key))),
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
        $el('article', 'ppm-card card h-100 border-0 shadow-sm', lang('Product Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile($icon_name, 'align-self-start mb-3'),
                $el('h3', 'ppm-display h5 mb-1 position-static', lang('Product Name'), array(
                    $link($title, '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => '__short_description', 'href' => '__detail_url'))),
                )),
                $para($who, 'small text-body-secondary mb-0'),
                $el('div', 'd-flex align-items-center justify-content-between gap-3 mt-auto pt-3', lang('Buy Line'), array(
                    $span($price, 'ppm-price fw-semibold fs-5', lang('Price'), $bind('__price_formatted')),
                    $shop_button(lang('Add to cart'), 'primary', 'position-relative z-2 text-nowrap',
                        array('size' => 'sm', '_bindings' => array('action' => 'catalog_add_to_cart'))),
                )),
            )),
        )),
    ), '6', $lg);
};

// The listing of one group: every product on one page, the notice after an
// add names this shop's cart.
$shop_listing = function ($slug, $group, $empty, $card) use ($widget_root, $row, $loop) {
    return array(
        'page'     => 'buy_tickets',
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

    // The tickets and the memberships on sale on the buy tickets page.
    'ticket_catalog'     => $shop_listing(lang('ticket-catalog'), 'tickets', lang('No tickets on sale online right now: buy them at the box office.'),
        $shop_card('bi-ticket-perforated', $tickets['dome_adult']['title'], $tickets['dome_adult']['who'], $money($ticket_prices['dome_adult']), '3')),
    'membership_catalog' => $shop_listing(lang('membership-catalog'), 'memberships', lang('No memberships on sale online right now: ask at the box office.'),
        $shop_card('bi-stars', $memberships['family']['title'], $memberships['family']['who'], $money($membership_prices['family']), '4')),

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
    // product's breadcrumb starts at the buy tickets page.
    'product_detail' => array(
        'page'     => 'product',
        'requires' => 'ecommerce',
        'slug'     => lang('product-detail'),
        'config'   => array(
            'regionType'              => 'catalog_item_view',
            'product_group_id'        => 0,
            'next_page_id'            => '{{tab:cart}}',
            'catalog_listing_page_id' => '{{tab:buy_tickets}}',
            'catalog_root_label'      => lang('Buy Tickets'),
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
    // the billing address, how a ticket and a membership are used, and the
    // way to the dome shows.
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
                                $span($tickets['dome_adult']['title'], 'fw-semibold', lang('Product Name'), $bind('__item_short_description')),
                                $span(' × ', 'text-body-secondary', lang('Text')),
                                $span('1', '', lang('Quantity'), $bind('__item_qty')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #dde3ea')),
                            $el('td', 'pe-0 text-end', lang('Total'), array(
                                $span($money($ticket_prices['dome_adult']), '', lang('Amount'), $bind('__item_total')),
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
                $mail_para(lang('This receipt is your ticket: show it at the door of the dome or the observatory. For a membership, show it at the box office, which adds your name to the members\' list.'), 'mb-0', 'margin:0'),
            ), lang('Next Step')),
            $mail_button(lang('All Dome Shows'), '{{page:shows}}'),
            $mail_para(lang('Your order and its receipt are also on the order page:'), 'small mb-1', 'margin:0 0 4px;font-size:13px'),
            $link(lang('View your order'), '{{page:order}}?order_id=^^__order_id^^', 'small', array($attr('style', 'font-size:13px;color:#075985;color:var(--bs-link-color,#075985)'))),
        )),
    ),
);

return array('pages' => $shop_pages, 'widgets' => $shop_widgets, 'catalog' => $catalog);
