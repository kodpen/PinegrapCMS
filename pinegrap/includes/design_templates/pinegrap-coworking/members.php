<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Coworking", its shop and members' area: the catalog
 * (the Memberships group with a product for each monthly plan of $plans,
 * the Day Passes group with the day pass), the memberships page, the
 * product, cart, checkout and order pages, the order receipt e-mail, and
 * the members' pages (the members' home with the partners' perks and the
 * house guide) in the membership folder. Included after parts.php and
 * mail.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ..., 'catalog' => ...).
 *
 * Every shop page and widget needs the shop: the shared parts never link
 * them, so a site without the shop has none of them and no dead link; the
 * plans page keeps its tour request there.
 *
 * A plan's product renews the buyer's membership by the plan's days once
 * its order is paid (membership_days: 1 for the day pass, 30 for a monthly
 * plan, the Flex Ten's ten days included), and is not shipped. The
 * members' pages sit in the members folder, whose access is the
 * membership: a member reads them, anybody else is asked to sign in or to
 * buy a plan. The prices are $plans' own (parts.php): the cards write
 * them with $plan_price(), the products are made from the same figures,
 * so the page and the cart show one amount.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$member_pages = array();

// ── The catalog ─────────────────────────────────────────────────────────
// Made once when the template is first opened (_pg_tpl_catalog()): the
// Memberships and Day Passes groups under the site's top group, each
// product found again by its SKU. A one-day plan is a day pass.
$membership_products = array();
$day_pass_products = array();
$n = 0;
foreach ($plans as $key => $p) {
    $product = array(
        'sku'             => $p['sku'],
        'title'           => $p['title'],
        'description'     => $p['text'] . ' ' . implode(' · ', $p['points']),
        'price'           => $p['price'],
        'shippable'       => false,
        'membership_days' => $p['days'],
        'featured'        => $p['featured'],
        'sort_order'      => ++$n,
        'keywords'        => lang('coworking membership, hot desk, day pass, monthly plan'),
    );
    if ($p['days'] === 1) {
        $day_pass_products[] = $product;
    } else {
        $membership_products[] = $product;
    }
}
$catalog = array(
    'folder'  => 'pinegrap_coworking_catalog',
    // The Details tab of every product's page.
    'details' => array(
        lang('Your membership starts as soon as the order is paid; sign in with the address you bought it with to open the members\' pages.'),
        lang('Say your name at the front desk on your first day; it gives you the Wi-Fi password and shows you round.'),
        lang('A sample product of the template: change its name and price in the catalog, or remove it.'),
    ),
    'groups'  => array(
        'memberships' => array(
            'parent'      => 'root',
            'title'       => lang('Memberships'),
            'description' => lang('Monthly coworking memberships: hot desks, a desk of your own and meeting room hours.'),
            'products'    => $membership_products,
        ),
        'day_passes'  => array(
            'parent'      => 'root',
            'title'       => lang('Day Passes'),
            'description' => lang('A day at any free hot desk, coffee included.'),
            'products'    => $day_pass_products,
        ),
    ),
);

// The house guide of the members' pages: what a member needs to know on
// the floor, by topic.
$house_guide = array(
    array('icon' => 'bi-wifi', 'title' => lang('Wi-Fi and Printing'), 'points' => array(
        lang('The members\' network is on your welcome card; give visitors the guest network, never yours.'),
        lang('Print from any laptop to the printer by the kitchen; scan to your e-mail from its screen.'),
        lang('Printing is free up to the pages of your plan; ask the front desk for more.'),
    )),
    array('icon' => 'bi-volume-mute', 'title' => lang('Quiet and Calls'), 'points' => array(
        lang('The library is silent: take calls outside, in a phone booth or on the terrace.'),
        lang('Phone booths are for calls of up to an hour; leave them free for the next person.'),
        lang('Headphones on the open floor; a meeting room for anything longer than a quick chat.'),
    )),
    array('icon' => 'bi-easel', 'title' => lang('Meeting Rooms'), 'points' => array(
        lang('Book a room online; the hours of your plan are counted at the front desk.'),
        lang('Leave the room as you found it: the whiteboard wiped and the screen off.'),
        lang('Cannot come? Cancel the booking so another member can use the room.'),
    )),
    array('icon' => 'bi-cup-hot', 'title' => lang('Kitchen and Coffee'), 'points' => array(
        lang('Coffee, tea and fruit are on us; label what you keep in the fridge.'),
        lang('Wash your cup or put it in the dishwasher; the fridge is emptied every Friday.'),
        lang('Eat at the kitchen tables or on the terrace, not at the desks.'),
    )),
    array('icon' => 'bi-people', 'title' => lang('Guests'), 'points' => array(
        lang('Sign your guests in at the front desk; they stay with you while they are here.'),
        lang('A guest who comes back to work needs a day pass of their own.'),
        lang('Clients for a meeting go straight to the room you booked.'),
    )),
    array('icon' => 'bi-key', 'title' => lang('Key Cards and Late Hours'), 'points' => array(
        lang('Resident desks and offices open the door at any hour with their key card.'),
        lang('The last one out after the front desk closes switches off the lights and closes the windows.'),
        lang('A lost key card is blocked at once: tell the front desk or write to us.'),
    )),
    array('icon' => 'bi-mailbox', 'title' => lang('Post and Lockers'), 'points' => array(
        lang('Post and parcels for your business address wait at the front desk; we e-mail you when one arrives.'),
        lang('Lockers open with a code you choose; empty yours when your membership ends.'),
    )),
    array('icon' => 'bi-recycle', 'title' => lang('Leaving Your Desk'), 'points' => array(
        lang('A hot desk is free again when you leave: take everything with you at the end of the day.'),
        lang('Sort the waste in the kitchen: paper, plastic and the rest.'),
    )),
);

// ── Shop pages ──────────────────────────────────────────────────────────

// How buying a membership works, in three steps.
$membership_steps = array(
    $step(1, lang('Choose a plan'), lang('A day pass for one day, or a monthly plan: ten days, every day or a desk of your own.')),
    $step(2, lang('Pay online'), lang('Pay at the checkout as for any order; the receipt comes by e-mail at once.')),
    $step(3, lang('Come and work'), lang('Your membership opens the members\' pages at once; say your name at the front desk on your first day.')),
);

$member_pages[] = array(
    'key'              => 'memberships',
    'requires'         => 'ecommerce',
    'name'             => lang('buy-a-membership'),
    'folder'           => 'public',
    'title'            => lang('Buy a Membership'),
    'meta_description' => lang('Buy a coworking day pass or a monthly plan online and start working the same day.'),
    'tree'             => $page(array(
        $page_header(lang('Buy a Membership'), lang('Choose a monthly plan or a day pass, pay online and come and work the same day. A monthly plan runs for thirty days; buy it again to keep going.')),
        $el('section', 'py-5', lang('Memberships'), array(
            $container(array(
                $section_header(lang('Monthly'), lang('Monthly plans')),
                $widget('membership_catalog'),
            )),
        ), $searchable('cw-memberships', lang('buy, pay online, membership, monthly plan, price'))),
        $el('section', 'pb-5', lang('Day Passes'), array(
            $container(array(
                $section_header(lang('For one day'), lang('Day passes')),
                $widget('day_pass_catalog'),
            )),
        ), $searchable('cw-day-passes', lang('day pass, one day, drop in, hot desk, price'))),
        $el('section', 'py-5 bg-body-tertiary', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $section_header(lang('Memberships'), lang('How it works')),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), $membership_steps),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'p-4 rounded-3 bg-body border', lang('Box'), array(
                            $heading('h3', lang('Not sure yet?'), 'pcw-display h5 mb-2'),
                            $para(lang('Book a tour first: the front desk shows you round, and your first day at a hot desk is on us.'), 'text-body-secondary mb-3'),
                            $link(lang('Book a Tour'), '{{page:tour}}?' . $query(array('plan' => $tour_choices[0])), 'btn btn-primary px-4'),
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
$member_pages[] = array_merge($shop_page('product', lang('product-detail'), lang('Product'), 'product_detail'),
    array('search' => true, 'sitemap' => true, 'noindex' => false, 'meta_description' => lang('Product details, options and price.')));
$member_pages[] = $shop_page('cart', lang('cart'), lang('Shopping Cart'), 'cart');
$member_pages[] = $shop_page('checkout', lang('checkout'), lang('Checkout'), 'checkout');
$member_pages[] = $shop_page('order', lang('order-summary'), lang('Order Summary'), 'order_view');

// The order receipt, sent to the billing address when an order is placed.
$member_pages[] = array_merge($mail_settings, array(
    'key'      => 'email_order',
    'requires' => 'ecommerce',
    'name'     => lang('email-order-receipt'),
    'title'    => lang('Order Receipt E-mail'),
    'tree'     => $mail_page(array($widget('email_order'))),
));

// ── Members' pages ──────────────────────────────────────────────────────

// A link tile of the members' home: an icon, a title, a line, the link.
$member_tile = function ($icon_name, $title, $text, $href) use ($el, $para, $link, $col, $icon_tile) {
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm pcw-lift', $title, array(
            $el('div', 'card-body p-4', lang('Card Body'), array(
                $icon_tile($icon_name, 'mb-3'),
                $el('h2', 'pcw-display h5 mb-2 position-static', lang('Title'), array(
                    $link($title, $href, 'stretched-link link-body-emphasis text-decoration-none'),
                )),
                $para($text, 'small text-body-secondary mb-0'),
            )),
        )),
    ), '6', '4');
};

// The partners' perks: an icon, the partner's offer and a line.
$perk_cards = array();
foreach ($perks as $p) {
    $perk_cards[] = $col(array(
        $el('div', 'd-flex gap-3 h-100 p-3 rounded-3 bg-body border', $p['title'], array(
            $icon_tile($p['icon']),
            $el('div', '', lang('Text'), array(
                $heading('h3', $p['title'], 'pcw-display h6 mb-1'),
                $para($p['text'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), '6', '3');
}

$member_pages[] = array(
    'key'              => 'members',
    'name'             => lang('members'),
    'folder'           => 'members',
    'title'            => lang('Members\' Area'),
    'meta_description' => lang('The members\' own pages: the house guide, meeting room bookings, community events and the partners\' perks.'),
    'search'           => false,
    'sitemap'          => false,
    'tree'             => $page(array(
        $page_header(lang('Members\' Area'), lang('Welcome back. Book a meeting room, sign up for the next event or look up how the house works.')),
        $el('section', 'py-5', lang('Member Links'), array(
            $container(array(
                $row(array(
                    $member_tile('bi-book', lang('House Guide'), lang('Wi-Fi, printing, calls, guests, key cards and the kitchen.'), '{{page:house_guide}}'),
                    $member_tile('bi-easel', lang('Book a meeting room'), lang('Choose a room, a day and one of the free times.'), '{{page:book_online}}'),
                    $member_tile('bi-calendar-event', lang('Community Events'), lang('Breakfasts, talks and Friday drinks with the other members.'), '{{page:events}}'),
                    $member_tile('bi-calendar-check', lang('My Sign-ups'), lang('Your event sign-ups and the front desk\'s answers.'), '{{page:my_signups}}'),
                    $member_tile('bi-grid-3x3-gap', lang('Spaces'), lang('Every desk, office and room of the house.'), '{{page:spaces}}'),
                    $member_tile('bi-person-circle', lang('My Account'), lang('Your details, your orders and when your membership ends.'), '{{page:my_account}}'),
                ), '4'),
            )),
        )),
        $el('section', 'py-5 bg-body-tertiary', lang('Perks'), array(
            $container(array(
                $section_header(lang('Members only'), lang('Perks from our neighbours')),
                $row($perk_cards, '3'),
            )),
        )),
        $el('section', 'py-5', lang('Member Notes'), array(
            $container(array(
                $el('div', 'p-4 rounded-3 bg-primary-subtle d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3', lang('Renewal'), array(
                    $el('div', '', lang('Text'), array(
                        $heading('h2', lang('Keep your membership going'), 'pcw-display h5 mb-1'),
                        $para(lang('A monthly plan runs for thirty days. Buy it again before it ends, or ask at the front desk, and your desk stays yours without a gap.'), 'small mb-0'),
                    )),
                    $link(lang('Compare the plans'), '{{page:plans}}', 'btn btn-primary px-4 text-nowrap'),
                )),
            )),
        )),
    ), false),
);

// The house guide: a card per topic, its points in a list.
$guide_cards = array();
foreach ($house_guide as $g) {
    $guide_cards[] = $col(array(
        $el('article', 'card h-100 border-0 shadow-sm', $g['title'], array(
            $el('div', 'card-body p-4', lang('Card Body'), array(
                $el('div', 'd-flex align-items-center gap-3 mb-3', lang('Title'), array(
                    $icon_tile($g['icon']),
                    $heading('h2', $g['title'], 'pcw-display h5 mb-0'),
                )),
                $check_list($g['points'], 'list-unstyled small mb-0'),
            )),
        )),
    ), '6', '6');
}

$member_pages[] = array(
    'key'              => 'house_guide',
    'name'             => lang('house-guide'),
    'folder'           => 'members',
    'title'            => lang('House Guide'),
    'meta_description' => lang('How the coworking space works for members: Wi-Fi, printing, calls, meeting rooms, guests, key cards and the kitchen.'),
    'search'           => false,
    'sitemap'          => false,
    'tree'             => $page(array(
        $page_header(lang('House Guide'), lang('A few habits keep the house pleasant for everybody. Anything missing? Ask at the front desk.')),
        $el('section', 'py-5', lang('Guide'), array(
            $container(array(
                $row($guide_cards, '4'),
                $el('div', 'd-flex gap-2 small text-body-secondary mt-5', lang('Note'), array(
                    $icon('bi-info-circle', 'text-primary mt-1'),
                    $span(lang('Sample text: write your own house rules before you publish.'), '', lang('Text')),
                )),
            )),
        )),
    ), false),
);

// ── Widgets ─────────────────────────────────────────────────────────────

// A Bootstrap button component: a membership card's Add to cart, which
// the listing wraps in the form that adds the product.
$shop_button = function ($text, $variant, $class = '', $extra = array()) {
    return array('type' => 'component', 'props' => array_merge(array('componentType' => 'btn', 'btnElement' => 'button', 'btnType' => 'submit',
        'variant' => $variant, 'text' => $text, 'cssClass' => $class), $extra), 'children' => array());
};

// A plan's card of a listing: its icon, its name (the link to its page),
// its price and Add to cart.
$membership_card = function ($sample_key) use ($el, $link, $span, $col, $bind, $icon_tile, $shop_button, $plans, $plan_price) {
    $p = $plans[$sample_key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm pcw-lift', lang('Product Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile($p['icon'], 'align-self-start mb-3'),
                $el('h3', 'pcw-display h5 mb-2 position-static', lang('Product Name'), array(
                    $link($p['title'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => '__short_description', 'href' => '__detail_url'))),
                )),
                $el('div', 'd-flex align-items-center justify-content-between gap-3 mt-auto pt-3', lang('Buy Line'), array(
                    $span($plan_price($sample_key), 'pcw-price fw-semibold fs-5', lang('Price'), $bind('__price_formatted')),
                    $shop_button(lang('Add to cart'), 'primary', 'position-relative z-2 text-nowrap',
                        array('size' => 'sm', '_bindings' => array('action' => 'catalog_add_to_cart'))),
                )),
            )),
        )),
    ), '6', '4');
};

// A listing of one group's products on the memberships page. The notice
// after an add links this shop's cart.
$plan_listing = function ($group, $slug, $sample_key, $empty) use ($widget_root, $row, $loop, $membership_card) {
    return array(
        'page'     => 'memberships',
        'requires' => 'ecommerce',
        'slug'     => $slug,
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
        'tree'     => $widget_root(array(
            $row(array($loop(array($membership_card($sample_key)))), '4'),
        )),
    );
};

// A line of the receipt's totals, shown while its flag holds.
$shop_total_row = function ($name, $label, $token, $flag = '', $class = '', $style = 'text-align:right') use ($el, $span, $bind, $show_when, $mail_style) {
    return $el('tr', $class, $name, array(
        $el('td', 'ps-0' . ($class === '' ? ' text-body-secondary' : ''), lang('Label'), array($span($label, '', lang('Text')))),
        $el('td', 'pe-0 text-end', lang('Value'), array($span('$240.00', '', lang('Amount'), $bind($token))), $mail_style($style)),
    ), $flag !== '' ? $show_when($flag) : array());
};

$member_widgets = array(

    // The monthly plans and the day pass on sale.
    'membership_catalog' => $plan_listing('memberships', lang('catalog'), 'open', lang('No memberships on sale right now. Book a tour and your first day is free.')),
    'day_pass_catalog'   => $plan_listing('day_passes', lang('day-passes'), 'day', lang('No day passes on sale right now. Ask at the front desk.')),

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
            'catalog_listing_page_id' => '{{tab:memberships}}',
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

    // The receipt: the order's number and date, its plans, the totals,
    // the billing address, and the way into the members' pages.
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
                                $span($plans['open']['title'], 'fw-semibold', lang('Product Name'), $bind('__item_short_description')),
                                $span(' × ', 'text-body-secondary', lang('Text')),
                                $span('1', '', lang('Quantity'), $bind('__item_qty')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #e5e7eb')),
                            $el('td', 'pe-0 text-end', lang('Total'), array(
                                $span('$240.00', '', lang('Amount'), $bind('__item_total')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #e5e7eb;text-align:right')),
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
                $el('div', '', lang('Address'), array(), array_merge(array('text' => lang('Jane Cooper, Sample Street 1, Istanbul')), $bind('__billing_address_html'))),
            ), $mail_style('margin:0 0 16px')),
            $mail_box(array(
                $el('p', 'fw-semibold mb-1', lang('Title'), array(), array_merge(array('text' => lang('Your membership is ready')), $mail_style('font-weight:600;margin:0 0 4px'))),
                $mail_para(lang('Once the payment is confirmed, your membership starts and the members\' pages open. Sign in with the address you bought it with, and say your name at the front desk on your first day.'), 'mb-0', 'margin:0'),
            ), lang('Next Step')),
            $mail_button(lang('Go to the Members\' Area'), '{{page:members}}'),
            $mail_para(lang('Your order and its receipt are also on the order page:'), 'small mb-1', 'margin:0 0 4px;font-size:13px'),
            $link(lang('View your order'), '{{page:order}}?order_id=^^__order_id^^', 'small', array($attr('style', 'font-size:13px;color:#e11d48'))),
        )),
    ),
);

return array('pages' => $member_pages, 'widgets' => $member_widgets, 'catalog' => $catalog);
