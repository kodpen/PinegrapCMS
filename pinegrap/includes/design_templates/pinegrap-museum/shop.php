<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Museum", its shop: the catalog (the Admission group, a
 * product for each ticket of $admission, and the Membership group, a
 * product for each tier of $tiers), the tickets and membership pages, the
 * product, cart, checkout and order pages and the order receipt e-mail.
 * Included after parts.php and mail.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ..., 'catalog' => ...).
 *
 * Every page and widget here needs the shop: the shared parts and the
 * public pages never link them (the visit and support pages say the
 * prices as words), so a site without the shop has none of them and no
 * dead link.
 *
 * The prices are parts.php's own ($admission_prices, $tier_prices): the
 * cards write them with $money(), the products are made from the same
 * figures, so the page and the cart show one amount. A ticket is shown at
 * the desk from the receipt and a membership card is sent by e-mail:
 * nothing is shipped, so the checkout asks for no delivery address.
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
// Admission and Membership groups under the site's top group, each
// product found again by its SKU, in the order of the data.
$admission_products = array();
foreach ($admission as $key => $a) {
    $admission_products[] = array(
        'sku'         => 'MUSEUM-TICKET-' . strtoupper($key),
        'title'       => sprintf(lang('Admission Ticket: %s'), $a['title']),
        'description' => $a['who'] . '. ' . $a['includes'] . '.',
        'price'       => $admission_prices[$key],
        'shippable'   => false,
        'featured'    => $key === 'adult',
        'keywords'    => lang('ticket, admission, entry, visit, museum'),
    );
}
$membership_products = array();
foreach ($tiers as $key => $t) {
    $membership_products[] = array(
        'sku'         => 'MUSEUM-MEMBER-' . strtoupper($key),
        'title'       => sprintf(lang('Membership: %s'), $t['title']),
        'description' => $t['who'] . '. ' . implode(' · ', $t['benefits']) . '.',
        'price'       => $tier_prices[$key],
        'shippable'   => false,
        'featured'    => $key === 'joint',
        'keywords'    => lang('membership, member, friends, support, free entry'),
    );
}
$catalog = array(
    'folder'  => 'pinegrap_museum_catalog',
    // The Details tab of every product's page.
    'details' => array(
        lang('Show the order receipt on your phone or on paper at the desk on the day of your visit; a ticket is valid for one visit.'),
        lang('A membership starts on the day of the order and lasts a year; your member card comes by e-mail within two working days.'),
        lang('A sample product of the template: change its name and price in the catalog, or remove it.'),
    ),
    'groups'  => array(
        'admission' => array(
            'parent'      => 'root',
            'title'       => lang('Admission'),
            'description' => lang('Tickets for the collection galleries and the ticketed exhibition.'),
            'products'    => $admission_products,
        ),
        'membership' => array(
            'parent'      => 'root',
            'title'       => lang('Membership'),
            'description' => lang('A year of free entry to every exhibition, and more, for you and your family.'),
            'products'    => $membership_products,
        ),
    ),
);

// ── Pages ───────────────────────────────────────────────────────────────

// How a ticket bought online works, in three steps.
$ticket_steps = array(
    $step(1, lang('Choose your tickets'), lang('One for each visitor, or a family ticket; children under six need none.')),
    $step(2, lang('Pay online'), lang('The receipt comes by e-mail at once, with the order number.')),
    $step(3, lang('Show it at the desk'), lang('On your phone or on paper, any opening day; the desk gives you your gallery stickers.')),
);

$shop_pages[] = array(
    'key'              => 'tickets',
    'requires'         => 'ecommerce',
    'name'             => lang('tickets'),
    'folder'           => 'public',
    'title'            => lang('Tickets'),
    'meta_description' => lang('Admission tickets for the collection galleries and the ticketed exhibition: buy online and show the receipt at the desk.'),
    'tree'             => $page(array(
        $page_header(lang('Tickets'), lang('Buy your admission online and walk straight to the desk. Children under six and members visit free.')),
        $el('section', 'py-5', lang('Products'), array(
            $container(array($widget('ticket_catalog'))),
        ), $searchable('mz-tickets', lang('buy tickets, admission, entry fee, price, family ticket, concession, exhibition ticket'))),
        $el('section', 'py-5 bg-body-tertiary', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $section_header(lang('Buying online'), lang('How it works')),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), $ticket_steps),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body border mb-4', lang('Box'), array(
                            $heading('h3', lang('A time to arrive'), 'pmz-display h5 mb-2'),
                            $para(lang('On busy days the ticketed exhibition lets visitors in at set times. Book a free entry time for the day of your visit and bring your ticket with you.'), 'text-body-secondary mb-3'),
                            $link(lang('Book an Entry Time'), '{{page:book_online}}', 'btn btn-primary px-4'),
                        )),
                        $el('div', 'p-4 rounded-4 bg-body border', lang('Box'), array(
                            $heading('h3', lang('Coming often?'), 'pmz-display h5 mb-2'),
                            $para(lang('A membership pays for itself in three visits: a year of free entry to every exhibition, for you and your guests.'), 'text-body-secondary mb-3'),
                            $link(lang('Become a Member'), '{{page:membership}}', 'btn btn-outline-primary px-4'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    )),
);

// What every membership gives, in a list beside the tiers on sale.
$member_benefits = array(
    lang('Free entry to every exhibition, as often as you like'),
    lang('Member previews before each exhibition opens'),
    lang('10% off in the shop and the café'),
    lang('The members\' magazine three times a year'),
);

$shop_pages[] = array(
    'key'              => 'membership',
    'requires'         => 'ecommerce',
    'name'             => lang('membership'),
    'folder'           => 'public',
    'title'            => lang('Membership'),
    'meta_description' => lang('Join the museum: a year of free entry to every exhibition, member previews and a discount in the shop and the café.'),
    'tree'             => $page(array(
        $page_header(lang('Membership'), lang('Join for a year: free entry to every exhibition and a part in the museum\'s work, from the first gift to the next.')),
        $el('section', 'py-5', lang('Products'), array(
            $container(array($widget('membership_catalog'))),
        ), $searchable('mz-membership-shop', lang('join, become a member, annual pass, friends, patron, gift membership'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Benefits'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $section_header(lang('Every member'), lang('What you get')),
                        $check_list($member_benefits),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body border', lang('Box'), array(
                            $heading('h3', lang('Your member card'), 'pmz-display h5 mb-2'),
                            $para(lang('Your membership starts on the day of the order and your member card comes by e-mail within two working days. Until then, show the order receipt at the desk.'), 'text-body-secondary mb-3'),
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
        $el('article', 'card h-100 border-0 shadow-sm pmz-lift', lang('Product Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile($icon_name, 'align-self-start mb-3'),
                $el('h3', 'pmz-display h5 mb-1 position-static', lang('Product Name'), array(
                    $link($title, '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => '__short_description', 'href' => '__detail_url'))),
                )),
                $para($who, 'small text-body-secondary mb-0'),
                $el('div', 'd-flex align-items-center justify-content-between gap-3 mt-auto pt-3', lang('Buy Line'), array(
                    $span($price, 'pmz-price fw-semibold fs-5', lang('Price'), $bind('__price_formatted')),
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
        $el('td', 'pe-0 text-end', lang('Value'), array($span('$18.00', '', lang('Amount'), $bind($token))), $mail_style($style)),
    ), $flag !== '' ? $show_when($flag) : array());
};

$shop_widgets = array(

    // The tickets and the tiers on sale, each group on its own page.
    'ticket_catalog'     => $shop_listing('tickets', lang('ticket-catalog'), 'admission', lang('No tickets on sale online right now: buy them at the desk.'),
        $shop_card('bi-ticket-perforated', $admission['adult']['title'], $admission['adult']['who'], $money($admission_prices['adult']), '4')),
    'membership_catalog' => $shop_listing('membership', lang('membership-catalog'), 'membership', lang('No memberships on sale online right now: ask at the desk.'),
        $shop_card('bi-person-heart', $tiers['joint']['title'], $tiers['joint']['who'], $money($tier_prices['joint']), '3')),

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
    // the billing address, what comes next for a ticket and a membership,
    // and the way to book an entry time.
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
                                $span(sprintf(lang('Admission Ticket: %s'), $admission['adult']['title']), 'fw-semibold', lang('Product Name'), $bind('__item_short_description')),
                                $span(' × ', 'text-body-secondary', lang('Text')),
                                $span('1', '', lang('Quantity'), $bind('__item_qty')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #dde3ea')),
                            $el('td', 'pe-0 text-end', lang('Total'), array(
                                $span('$18.00', '', lang('Amount'), $bind('__item_total')),
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
                $mail_para(lang('Show this receipt at the desk on the day of your visit: each ticket is valid for one visit. A membership starts today and your member card comes to this address within two working days.'), 'mb-0', 'margin:0'),
            ), lang('Next Step')),
            $mail_button(lang('Book an Entry Time'), '{{page:book_online}}'),
            $mail_para(lang('Your order and its receipt are also on the order page:'), 'small mb-1', 'margin:0 0 4px;font-size:13px'),
            $link(lang('View your order'), '{{page:order}}?order_id=^^__order_id^^', 'small', array($attr('style', 'font-size:13px;color:var(--bs-primary,#9f1239)'))),
        )),
    ),
);

return array('pages' => $shop_pages, 'widgets' => $shop_widgets, 'catalog' => $catalog);
