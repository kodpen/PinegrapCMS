<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Sports Club", its shop and members' area: the catalog
 * (the Kit, Season Tickets and Membership groups, a product for each item
 * of $kit, $season_tickets and $memberships), the kit shop, the season
 * tickets and the membership pages, the product, cart, checkout and order
 * pages, the order receipt e-mail, and the members' page in the membership
 * folder. Included after parts.php and mail.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ..., 'catalog' => ...).
 *
 * Every shop page and widget needs the shop: the shared parts never link
 * them, so a site without the shop has none of them and no dead link.
 *
 * Kit is shipped. A season ticket is not (shippable => false): the cart
 * goes to checkout without a shipping step and the ticket is the order. A
 * membership's product renews the buyer's membership by its days once the
 * order is paid (membership_days) and is not shipped; the members' page
 * sits in the members folder, whose access is the membership. The prices
 * are parts.php's own: the cards write them with $money(), the products
 * are made from the same figures, so the page and the cart show one
 * amount.
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
// groups under the site's top group, each product found again by its SKU.
$sh_products = function ($items, $more, $keywords) {
    $out = array();
    $n = 0;
    foreach ($items as $key => $p) {
        $out[] = array_merge(array(
            'sku'         => $p['sku'],
            'title'       => $p['title'],
            'description' => $p['text'] . (isset($p['points']) ? ' ' . implode(' · ', $p['points']) : ''),
            'price'       => $p['price'],
            'featured'    => !empty($p['featured']),
            'sort_order'  => ++$n,
            'keywords'    => $keywords,
        ), $more($p));
    }
    return $out;
};
$catalog = array(
    'folder'  => 'pinegrap_sportsclub_catalog',
    // The Details tab of every product's page.
    'details' => array(
        lang('Every sale goes back into the club: pitches, kit and coaching for every team.'),
        lang('Questions about sizes, tickets or memberships? Write to us through the contact page.'),
        lang('A sample product of the template: change its name and price in the catalog, or remove it.'),
    ),
    'groups'  => array(
        'kit'            => array(
            'parent'      => 'root',
            'title'       => lang('Club Kit'),
            'description' => lang('The club\'s shirts, training tops and scarves, sent to your door.'),
            'products'    => $sh_products($kit, function ($p) { return array(); }, lang('club shirt, football kit, scarf, training top')),
        ),
        'season_tickets' => array(
            'parent'      => 'root',
            'title'       => lang('Season Tickets'),
            'description' => lang('Every home league match of the first team and the women\'s team.'),
            'products'    => $sh_products($season_tickets, function ($p) { return array('shippable' => false); },
                lang('season ticket, home matches, entry, stand')),
        ),
        'memberships'    => array(
            'parent'      => 'root',
            'title'       => lang('Club Membership'),
            'description' => lang('Membership of the club for a season: a vote at the annual meeting and the members\' area.'),
            'products'    => $sh_products($memberships, function ($p) { return array('shippable' => false, 'membership_days' => $p['days']); },
                lang('club membership, member, annual meeting, join the club')),
        ),
    ),
);

// ── Shop pages ──────────────────────────────────────────────────────────

// A page of the shop with a catalog listing, a few lines under it and the
// way to the other two shop pages.
$sh_listing_page = function ($key, $name, $title, $meta, $lead, $widget_key, $notes, $topics) use ($page, $page_header, $el, $container, $row, $col, $widget, $check_list, $link, $searchable) {
    $others = array(
        'kit_shop'       => lang('Club Kit'),
        'season_tickets' => lang('Season Tickets'),
        'membership'     => lang('Club Membership'),
    );
    unset($others[$key]);
    $links = array();
    foreach ($others as $k => $text) $links[] = $link($text, '{{page:' . $k . '}}', 'btn btn-outline-primary px-4');
    return array(
        'key'              => $key,
        'requires'         => 'ecommerce',
        'name'             => $name,
        'folder'           => 'public',
        'title'            => $title,
        'meta_description' => $meta,
        'tree'             => $page(array(
            $page_header($title, $lead),
            $el('section', 'py-5', $title, array(
                $container(array($widget($widget_key))),
            ), $searchable('sp-' . str_replace('_', '-', $key), $topics)),
            $el('section', 'py-5 bg-body-tertiary', lang('Good to Know'), array(
                $container(array(
                    $row(array(
                        $col(array($check_list($notes)), '', '7'),
                        $col(array($el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), $links)), '', '5'),
                    ), '4', '', 'g-lg-5 align-items-center'),
                )),
            )),
        )),
    );
};

$shop_pages[] = $sh_listing_page('kit_shop', lang('kit-shop'), lang('Club Kit'),
    lang('Buy the club\'s home and away shirts, a training top or a scarf online; every sale supports the teams.'),
    lang('Wear the colours: the shirts the teams play in, a training top for cold nights and a scarf for the stand.'),
    'kit_catalog', array(
        lang('Adult and children\'s sizes: write the size in the order notes at the checkout.'),
        lang('Sent to your door, or collect it from the clubhouse on a match day.'),
        lang('Members get ten percent off in the kit shop.'),
    ), lang('shop, kit, shirt, scarf, buy, merchandise'));

$shop_pages[] = $sh_listing_page('season_tickets', lang('season-tickets'), lang('Season Tickets'),
    lang('Buy a season ticket online: every home league match of the first team and the women\'s team, with a seat in the stand.'),
    lang('Every home league match of the first team and the women\'s team for one price. Show your order at the gate.'),
    'season_ticket_catalog', array(
        lang('Your order is your ticket: show it on your phone or printed at the gate.'),
        lang('Cup matches and friendlies are paid at the gate on the day.'),
        lang('Children under 16 come in free with an adult; members too.'),
    ), lang('season ticket, tickets, entry, home matches, stand, price'));

$shop_pages[] = $sh_listing_page('membership', lang('club-membership'), lang('Club Membership'),
    lang('Join the club for a season online: a vote at the annual meeting, member prices and the members\' area.'),
    lang('A volunteer club belongs to its members. Join for a season and have your say in how it is run.'),
    'membership_catalog', array(
        lang('Your membership starts as soon as the order is paid; sign in with the address you bought it with.'),
        lang('A membership runs for a season: buy it again before it ends to keep your vote.'),
        lang('Playing for a team? Your subscription is separate: ask your coach.'),
    ), lang('membership, join, member, annual meeting, vote, price'));

// The shop's own pages: a product's page, the cart, the checkout and the
// order.
$sh_page = function ($key, $name, $title, $widget_key) use ($page, $widget) {
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
$shop_pages[] = array_merge($sh_page('product', lang('product-detail'), lang('Product'), 'product_detail'),
    array('search' => true, 'sitemap' => true, 'noindex' => false, 'meta_description' => lang('Product details, options and price.')));
$shop_pages[] = $sh_page('cart', lang('cart'), lang('Shopping Cart'), 'cart');
$shop_pages[] = $sh_page('checkout', lang('checkout'), lang('Checkout'), 'checkout');
$shop_pages[] = $sh_page('order', lang('order-summary'), lang('Order Summary'), 'order_view');

// The order receipt, sent to the billing address when an order is placed.
$shop_pages[] = array_merge($mail_settings, array(
    'key'      => 'email_order',
    'requires' => 'ecommerce',
    'name'     => lang('email-order-receipt'),
    'title'    => lang('Order Receipt E-mail'),
    'tree'     => $mail_page(array($widget('email_order'))),
));

// ── The members' page ───────────────────────────────────────────────────

// A link tile of the members' page: an icon, a title, a line, the link.
$sh_member_tile = function ($icon_name, $title, $text, $href) use ($el, $para, $link, $col, $icon_tile) {
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm psp-lift', $title, array(
            $el('div', 'card-body p-4', lang('Card Body'), array(
                $icon_tile($icon_name, 'mb-3'),
                $el('h2', 'psp-display h5 mb-2 position-static', lang('Title'), array(
                    $link($title, $href, 'stretched-link link-body-emphasis text-decoration-none'),
                )),
                $para($text, 'small text-body-secondary mb-0'),
            )),
        )),
    ), '6', '4');
};

// The members' page: in the members folder, so only a member reads it;
// it links pages that stand without the shop.
$shop_pages[] = array(
    'key'              => 'members',
    'name'             => lang('members'),
    'folder'           => 'members',
    'title'            => lang('Members\' Area'),
    'meta_description' => lang('The members\' own page: the annual meeting, club events, fixtures and your account.'),
    'search'           => false,
    'sitemap'          => false,
    'tree'             => $page(array(
        $page_header(lang('Members\' Area'), lang('Welcome, member. Here is what is on at the club and where to have your say.')),
        $el('section', 'py-5', lang('Member Links'), array(
            $container(array(
                $row(array(
                    $sh_member_tile('bi-calendar-event', lang('Club Events'), lang('Social evenings, the awards night and the quiz, with members first.'), '{{page:events}}'),
                    $sh_member_tile('bi-calendar3', lang('Fixtures'), lang('Every match to come, home and away.'), '{{page:fixtures}}'),
                    $sh_member_tile('bi-newspaper', lang('Club News'), lang('Match reports and news from the committee.'), '{{page:blog}}'),
                    $sh_member_tile('bi-clipboard-check', lang('My Registrations'), lang('Your children\'s academy registrations and your trial requests.'), '{{page:my_registrations}}'),
                    $sh_member_tile('bi-envelope', lang('Write to the Committee'), lang('A question or an idea for the club: the committee reads every message.'), '{{page:contact}}'),
                    $sh_member_tile('bi-person-circle', lang('My Account'), lang('Your details, your orders and when your membership ends.'), '{{page:my_account}}'),
                ), '4'),
            )),
        )),
        $el('section', 'pb-5', lang('Member Notes'), array(
            $container(array(
                $el('div', 'p-4 rounded-3 bg-primary-subtle', lang('Annual Meeting'), array(
                    $heading('h2', lang('The annual meeting'), 'psp-display h5 mb-1'),
                    $para(lang('Every member has a vote on the committee, the accounts and the plans for the season. The date and the agenda come by e-mail a month before.'), 'small mb-0'),
                )),
            )),
        )),
    ), false),
);

// ── Widgets ─────────────────────────────────────────────────────────────

// A Bootstrap button component: a card's Add to cart, which the listing
// wraps in the form that adds the product.
$sh_button = function ($text, $variant, $class = '', $extra = array()) {
    return array('type' => 'component', 'props' => array_merge(array('componentType' => 'btn', 'btnElement' => 'button', 'btnType' => 'submit',
        'variant' => $variant, 'text' => $text, 'cssClass' => $class), $extra), 'children' => array());
};

// A product's card of a listing: its icon, its name (the link to its
// page), its price and Add to cart. $title and $price are the sample.
$sh_card = function ($icon_name, $title, $price) use ($el, $link, $span, $col, $bind, $icon_tile, $sh_button) {
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm psp-lift', lang('Product Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile($icon_name, 'align-self-start mb-3'),
                $el('h3', 'psp-display h5 mb-2 position-static', lang('Product Name'), array(
                    $link($title, '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => '__short_description', 'href' => '__detail_url'))),
                )),
                $el('div', 'd-flex align-items-center justify-content-between gap-3 mt-auto pt-3', lang('Buy Line'), array(
                    $span($price, 'fw-semibold fs-5', lang('Price'), $bind('__price_formatted')),
                    $sh_button(lang('Add to cart'), 'primary', 'position-relative z-2 text-nowrap',
                        array('size' => 'sm', '_bindings' => array('action' => 'catalog_add_to_cart'))),
                )),
            )),
        )),
    ), '6', '4');
};

// A catalog listing of one group: its products in sort order on one page.
// The notice after an add links this shop's cart.
$sh_listing = function ($page_key, $group, $empty, $card) use ($widget_root, $row, $loop) {
    return array(
        'page'     => $page_key,
        'requires' => 'ecommerce',
        'slug'     => lang('catalog'),
        'config'   => array(
            'regionType'               => 'catalog_listing',
            'product_group_id'         => '{{product_group:' . $group . '}}',
            'group_navigation'         => 'none',
            'items_per_page'           => 12,
            'order_by_field'           => 'sort_order',
            'order_by_direction'       => 'ASC',
            'detail_page_id'           => '{{tab:product}}',
            'add_to_cart_stay_on_page' => false,
            'add_to_cart_next_page_id' => '{{tab:cart}}',
            'empty_message'            => $empty,
        ),
        'tree'     => $widget_root(array($row(array($loop(array($card))), '4'))),
    );
};

// A line of the receipt's totals, shown while its flag holds.
$sh_total_row = function ($name, $label, $token, $flag = '', $class = '', $style = 'text-align:right') use ($el, $span, $bind, $show_when, $mail_style) {
    return $el('tr', $class, $name, array(
        $el('td', 'ps-0' . ($class === '' ? ' text-body-secondary' : ''), lang('Label'), array($span($label, '', lang('Text')))),
        $el('td', 'pe-0 text-end', lang('Value'), array($span('$45.00', '', lang('Amount'), $bind($token))), $mail_style($style)),
    ), $flag !== '' ? $show_when($flag) : array());
};

$shop_widgets = array(
    'kit_catalog'           => $sh_listing('kit_shop', 'kit', lang('No kit on sale right now. Ask at the clubhouse on a match day.'),
        $sh_card($kit['home']['icon'], $kit['home']['title'], $money($kit['home']['price']))),
    'season_ticket_catalog' => $sh_listing('season_tickets', 'season_tickets', lang('No season tickets on sale right now. Pay at the gate on the day.'),
        $sh_card('bi-ticket-perforated', $season_tickets['adult']['title'], $season_price($season_tickets['adult']['price']))),
    'membership_catalog'    => $sh_listing('membership', 'memberships', lang('No memberships on sale right now. Ask the club how to join.'),
        $sh_card($memberships['adult']['icon'], $memberships['adult']['title'], $season_price($memberships['adult']['price']))),

    // The way to the cart, beside the login region; left out without the
    // shop.
    'cart_link' => array(
        'page'     => '',
        'requires' => 'ecommerce',
        'slug'     => lang('cart-link'),
        'config'   => array('regionType' => 'cart_link', 'cart_page_id' => '{{tab:cart}}'),
        'tree'     => 'starter',
    ),

    // The shop's pages draw the editor's own layouts for their kinds.
    'product_detail' => array(
        'page'     => 'product',
        'requires' => 'ecommerce',
        'slug'     => lang('product-detail'),
        'config'   => array(
            'regionType'              => 'catalog_item_view',
            'product_group_id'        => 0,
            'next_page_id'            => '{{tab:cart}}',
            'catalog_listing_page_id' => '{{tab:kit_shop}}',
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

    // The receipt: the order's number and date, its items, the totals,
    // the billing address, and what comes next for each kind of item.
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
                                $span($kit['home']['title'], 'fw-semibold', lang('Product Name'), $bind('__item_short_description')),
                                $span(' × ', 'text-body-secondary', lang('Text')),
                                $span('1', '', lang('Quantity'), $bind('__item_qty')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #e5e7eb')),
                            $el('td', 'pe-0 text-end', lang('Total'), array(
                                $span('$45.00', '', lang('Amount'), $bind('__item_total')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #e5e7eb;text-align:right')),
                        )),
                    )),
                )),
            ), $mail_style('width:100%;border-collapse:collapse;margin:0 0 16px')),
            $el('table', 'table table-sm mb-4', lang('Totals'), array(
                $el('tbody', '', lang('Rows'), array(
                    $sh_total_row(lang('Subtotal'), lang('Subtotal'), '__order_subtotal'),
                    $sh_total_row(lang('Discount'), lang('Discount'), '__order_discount', 'has_discount'),
                    $sh_total_row(lang('Shipping'), lang('Shipping'), '__order_shipping', 'has_shipping'),
                    $sh_total_row(lang('Tax'), lang('VAT'), '__order_tax', 'has_tax'),
                    $sh_total_row(lang('Total'), lang('Total'), '__order_total', '', 'fw-bold', 'text-align:right;font-weight:700'),
                )),
            ), $mail_style('width:100%;border-collapse:collapse;margin:0 0 24px')),
            $el('div', '', lang('Billing Address'), array(
                $el('p', 'small text-body-secondary mb-1', lang('Label'), array(), array('text' => lang('Billing Address'))),
                $el('div', '', lang('Address'), array(), array_merge(array('text' => lang('Jane Cooper, Sample Street 1, Istanbul')), $bind('__billing_address_html'))),
            ), $mail_style('margin:0 0 16px')),
            $mail_box(array(
                $el('p', 'fw-semibold mb-1', lang('Title'), array(), array_merge(array('text' => lang('What happens next')), $mail_style('font-weight:600;margin:0 0 4px'))),
                $mail_para(lang('Kit is sent within a few days. A season ticket is this order: show it at the gate. A membership starts once the payment is confirmed and opens the members\' area when you sign in with this address.'), 'mb-0', 'margin:0'),
            ), lang('Next Step')),
            $mail_button(lang('View your order'), '{{page:order}}?order_id=^^__order_id^^'),
        )),
    ),
);

return array('pages' => $shop_pages, 'widgets' => $shop_widgets, 'catalog' => $catalog);
