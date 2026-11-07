<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Theatre", its shop: the catalog (the Tickets group, a
 * product for each ticket band of $tickets; the Friends group, a product
 * for each tier of $tiers; the Gift Vouchers group, a product for each
 * voucher of $gift_vouchers), the tickets and friends pages, the product,
 * cart, checkout and order pages and the order receipt e-mail. Included
 * after parts.php and mail.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ..., 'catalog' => ...).
 *
 * Every page and widget here needs the shop: the shared parts and the
 * public pages never link them (the visit and support pages say the
 * prices as words), so a site without the shop has none of them and no
 * dead link.
 *
 * The prices are parts.php's own ($ticket_prices, $tier_prices) and the
 * vouchers' here: the cards write them with $money(), the products are
 * made from the same figures, so the page and the cart show one amount.
 * A ticket bought online is a seat in its band, not a date: the receipt is
 * changed for a seat at the box office for any performance of the season.
 * Friends cards and vouchers are sent by e-mail: nothing is shipped, so
 * the checkout asks for no delivery address.
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

// The gift vouchers: an amount towards tickets, sold only with the shop.
$gift_vouchers = array(
    'night'     => array('title' => lang('Theatre Gift Voucher: a Night Out'),
        'text'  => lang('Enough for a seat in the circle and an interval drink, valid for a year.'),
        'price' => array('USD' => 50, 'EUR' => 45, 'GBP' => 40, 'TRY' => 1200)),
    'two_seats' => array('title' => lang('Theatre Gift Voucher: Two Seats'),
        'text'  => lang('Two seats in the stalls for any production of the season, valid for a year.'),
        'price' => array('USD' => 100, 'EUR' => 90, 'GBP' => 80, 'TRY' => 2400)),
);

// ── The catalog ─────────────────────────────────────────────────────────
// Made once when the template is first opened (_pg_tpl_catalog()): the
// Tickets, Friends and Gift Vouchers groups under the site's top group,
// each product found again by its SKU, in the order of the data.
$ticket_products = array();
foreach ($tickets as $key => $t) {
    $ticket_products[] = array(
        'sku'         => 'THEATRE-TICKET-' . strtoupper($key),
        'title'       => sprintf(lang('Theatre Ticket: %s'), $t['title']),
        'description' => $t['who'] . '. ' . $t['view'] . '.',
        'price'       => $ticket_prices[$key],
        'shippable'   => false,
        'featured'    => $key === 'stalls',
        'keywords'    => lang('ticket, seat, play, musical, theatre, performance'),
    );
}
$friend_products = array();
foreach ($tiers as $key => $t) {
    $friend_products[] = array(
        'sku'         => 'THEATRE-FRIEND-' . strtoupper($key),
        'title'       => sprintf(lang('Theatre Friends: %s'), $t['title']),
        'description' => $t['who'] . '. ' . implode(' · ', $t['benefits']) . '.',
        'price'       => $tier_prices[$key],
        'shippable'   => false,
        'featured'    => $key === 'best',
        'keywords'    => lang('friends, membership, priority booking, support, patron'),
    );
}
$voucher_products = array();
foreach ($gift_vouchers as $key => $v) {
    $voucher_products[] = array(
        'sku'         => 'THEATRE-GIFT-' . strtoupper($key),
        'title'       => $v['title'],
        'description' => $v['text'],
        'price'       => $v['price'],
        'shippable'   => false,
        'featured'    => $key === 'two_seats',
        'keywords'    => lang('gift voucher, gift, present, theatre tickets'),
    );
}
$catalog = array(
    'folder'  => 'pinegrap_theatre_catalog',
    // The Details tab of every product's page.
    'details' => array(
        lang('Change your receipt for a seat in its band at the box office, for any performance of the season while seats remain.'),
        lang('Friends join for a year from the day of the order; your friends card and a gift voucher come by e-mail within two working days.'),
        lang('A sample product of the template: change its name and price in the catalog, or remove it.'),
    ),
    'groups'  => array(
        'tickets' => array(
            'parent'      => 'root',
            'title'       => lang('Tickets'),
            'description' => lang('Seats in each part of the house, for any performance of the season.'),
            'products'    => $ticket_products,
        ),
        'friends' => array(
            'parent'      => 'root',
            'title'       => lang('Theatre Friends'),
            'description' => lang('A year of priority booking, no booking fees and a closer part in the theatre\'s work.'),
            'products'    => $friend_products,
        ),
        'vouchers' => array(
            'parent'      => 'root',
            'title'       => lang('Gift Vouchers'),
            'description' => lang('An evening at the theatre as a present, sent by e-mail.'),
            'products'    => $voucher_products,
        ),
    ),
);

// ── Pages ───────────────────────────────────────────────────────────────

// How a ticket bought online works, in three steps.
$ticket_steps = array(
    $step(1, lang('Choose your seats'), lang('One ticket for each person, in the part of the house you like; children need a ticket too.')),
    $step(2, lang('Pay online'), lang('The receipt comes by e-mail at once, with the order number.')),
    $step(3, lang('Pick your performance'), lang('At the box office or on the phone, name the order number and choose the date; your seats are kept at the desk.')),
);

$shop_pages[] = array(
    'key'              => 'tickets',
    'requires'         => 'ecommerce',
    'name'             => lang('tickets'),
    'folder'           => 'public',
    'title'            => lang('Tickets'),
    'meta_description' => lang('Theatre tickets for every part of the house and gift vouchers: buy online, then pick your performance at the box office.'),
    'tree'             => $page(array(
        $page_header(lang('Tickets'), lang('Buy your seats online and choose the performance at the box office. Friends book before anyone else.')),
        $el('section', 'py-5', lang('Products'), array(
            $container(array($widget('ticket_catalog'))),
        ), $searchable('th-tickets-shop', lang('buy tickets, seats, stalls, dress circle, upper circle, concession, under 26'))),
        $el('section', 'py-5 bg-body-tertiary', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $section_header(lang('Buying online'), lang('How it works')),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), $ticket_steps),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body border mb-4', lang('Box'), array(
                            $heading('h3', lang('Which performance?'), 'pth-display h5 mb-2'),
                            $para(lang('See every date from today, with its access performances, and name the one you want at the box office.'), 'text-body-secondary mb-3'),
                            $link(lang('Find a Performance'), '{{page:whats_on}}', 'btn btn-primary px-4'),
                        )),
                        $el('div', 'p-4 rounded-4 bg-body border', lang('Box'), array(
                            $heading('h3', lang('Coming often?'), 'pth-display h5 mb-2'),
                            $para(lang('Theatre Friends book before tickets go on sale and pay no booking fees, all year.'), 'text-body-secondary mb-3'),
                            $link(lang('Become a Friend'), '{{page:friends}}', 'btn btn-outline-primary px-4'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
        $el('section', 'py-5', lang('Gift Vouchers'), array(
            $container(array(
                $section_header(lang('Give the theatre'), lang('Gift vouchers')),
                $para(lang('A voucher is sent by e-mail within two working days, with its code; it pays for tickets to any production for a year.'), 'lead text-body-secondary mb-4'),
                $widget('voucher_catalog'),
            )),
        ), $searchable('th-vouchers', lang('gift, present, voucher, gift card, birthday'))),
    )),
);

$shop_pages[] = array(
    'key'              => 'friends',
    'requires'         => 'ecommerce',
    'name'             => lang('theatre-friends'),
    'folder'           => 'public',
    'title'            => lang('Theatre Friends'),
    'meta_description' => lang('Join the Theatre Friends: a year of priority booking, no booking fees and invitations to open rehearsals.'),
    'tree'             => $page(array(
        $page_header(lang('Theatre Friends'), lang('Join for a year: book before anyone else and help the theatre make new work for everyone.')),
        $el('section', 'py-5', lang('Products'), array(
            $container(array($widget('friends_catalog'))),
        ), $searchable('th-friends-shop', lang('join, become a friend, membership, patron, priority booking, gift membership'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Benefits'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $section_header(lang('Every friend'), lang('What you get')),
                        $check_list($tiers['friend']['benefits']),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body border', lang('Box'), array(
                            $heading('h3', lang('Your friends card'), 'pth-display h5 mb-2'),
                            $para(lang('Your year starts on the day of the order and your friends card comes by e-mail within two working days. Until then, name the order number at the box office.'), 'text-body-secondary mb-3'),
                            $link(lang('Other ways to support us'), '{{page:support}}', 'btn btn-outline-primary px-4'),
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
        $el('article', 'card h-100 border-0 shadow-sm pth-lift', lang('Product Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile($icon_name, 'align-self-start mb-3'),
                $el('h3', 'pth-display h5 mb-1 position-static', lang('Product Name'), array(
                    $link($title, '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => '__short_description', 'href' => '__detail_url'))),
                )),
                $para($who, 'small text-body-secondary mb-0'),
                $el('div', 'd-flex align-items-center justify-content-between gap-3 mt-auto pt-3', lang('Buy Line'), array(
                    $span($price, 'pth-price fw-semibold fs-5', lang('Price'), $bind('__price_formatted')),
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
        $el('td', 'pe-0 text-end', lang('Value'), array($span('$45.00', '', lang('Amount'), $bind($token))), $mail_style($style)),
    ), $flag !== '' ? $show_when($flag) : array());
};

$shop_widgets = array(

    // The ticket bands, the friends tiers and the vouchers on sale, each
    // group in its own listing.
    'ticket_catalog'  => $shop_listing('tickets', lang('ticket-catalog'), 'tickets', lang('No tickets on sale online right now: book at the box office.'),
        $shop_card('bi-ticket-perforated', $tickets['stalls']['title'], $tickets['stalls']['who'], $money($ticket_prices['stalls']), '4')),
    'voucher_catalog' => $shop_listing('tickets', lang('voucher-catalog'), 'vouchers', lang('No gift vouchers on sale right now.'),
        $shop_card('bi-gift', $gift_vouchers['two_seats']['title'], $gift_vouchers['two_seats']['text'], $money($gift_vouchers['two_seats']['price']), '4')),
    'friends_catalog' => $shop_listing('friends', lang('friends-catalog'), 'friends', lang('No friends tiers on sale online right now: ask at the box office.'),
        $shop_card('bi-person-heart', $tiers['best']['title'], $tiers['best']['who'], $money($tier_prices['best']), '3')),

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
    // product's breadcrumb starts at the tickets page.
    'product_detail' => array(
        'page'     => 'product',
        'requires' => 'ecommerce',
        'slug'     => lang('product-detail'),
        'config'   => array(
            'regionType'              => 'catalog_item_view',
            'product_group_id'        => 0,
            'next_page_id'            => '{{tab:cart}}',
            'catalog_listing_page_id' => '{{tab:tickets}}',
            'catalog_root_label'      => lang('Tickets'),
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
    // the billing address, what comes next for tickets, friends and
    // vouchers, and the way to the performances.
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
                                $span(sprintf(lang('Theatre Ticket: %s'), $tickets['stalls']['title']), 'fw-semibold', lang('Product Name'), $bind('__item_short_description')),
                                $span(' × ', 'text-body-secondary', lang('Text')),
                                $span('1', '', lang('Quantity'), $bind('__item_qty')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #dde3ea')),
                            $el('td', 'pe-0 text-end', lang('Total'), array(
                                $span('$45.00', '', lang('Amount'), $bind('__item_total')),
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
                $mail_para(lang('For tickets, name this order number at the box office or on the phone and choose your performance; your seats wait at the desk. A friends card or a gift voucher comes to this address within two working days.'), 'mb-0', 'margin:0'),
            ), lang('Next Step')),
            $mail_button(lang('Find a Performance'), '{{page:whats_on}}'),
            $mail_para(lang('Your order and its receipt are also on the order page:'), 'small mb-1', 'margin:0 0 4px;font-size:13px'),
            $link(lang('View your order'), '{{page:order}}?order_id=^^__order_id^^', 'small', array($attr('style', 'font-size:13px;color:var(--bs-primary,#b91c1c)'))),
        )),
    ),
);

return array('pages' => $shop_pages, 'widgets' => $shop_widgets, 'catalog' => $catalog);
