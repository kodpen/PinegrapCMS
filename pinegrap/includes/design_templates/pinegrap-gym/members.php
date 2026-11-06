<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Gym", its shop and members' area: the catalog (the
 * Memberships group, a product for each plan of $plans), the memberships
 * page, the product, cart, checkout and order pages, the order receipt
 * e-mail, and the members' pages (the members' home and the workout
 * library) in the membership folder. Included after parts.php and mail.php
 * in the same scope; returns
 * array('pages' => ..., 'widgets' => ..., 'catalog' => ...).
 *
 * Every shop page and widget needs the shop: the shared parts never link
 * them, so a site without the shop has none of them and no dead link; the
 * plans page keeps its join request there.
 *
 * A plan's product renews the buyer's membership by the plan's days once
 * its order is paid (membership_days), and is not shipped. The members'
 * pages sit in the members folder, whose access is the membership: a
 * member reads them, anybody else is asked to sign in or to join. The
 * prices are $plans' own (parts.php): the cards write them with $money(),
 * the products are made from the same figures, so the page and the cart
 * show one amount.
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
// Memberships group under the site's top group, each product found again
// by its SKU.
$membership_products = array();
$n = 0;
foreach ($plans as $key => $p) {
    $membership_products[] = array(
        'sku'             => $p['sku'],
        'title'           => $p['title'],
        'description'     => $p['text'] . ' ' . implode(' · ', $p['points']),
        'price'           => $p['price'],
        'shippable'       => false,
        'membership_days' => $p['days'],
        'featured'        => $p['featured'],
        'sort_order'      => ++$n,
        'keywords'        => lang('gym membership, monthly pass, classes, weights'),
    );
}
$catalog = array(
    'folder'  => 'pinegrap_gym_catalog',
    // The Details tab of every product's page.
    'details' => array(
        lang('Your membership starts as soon as the order is paid; sign in with the address you bought it with to open the members\' pages.'),
        lang('A month of training: buy the plan again before it ends to keep training without a gap.'),
        lang('A sample product of the template: change its name and price in the catalog, or remove it.'),
    ),
    'groups'  => array(
        'memberships' => array(
            'parent'      => 'root',
            'title'       => lang('Memberships'),
            'description' => lang('Monthly gym memberships: the weights floor, the studios and the group classes.'),
            'products'    => $membership_products,
        ),
    ),
);

// The workout library of the members' pages: a session to do alone on the
// floor, what it trains, how long it takes and its blocks.
$workouts = array(
    array('icon' => 'bi-trophy', 'focus' => 'strength', 'minutes' => 50, 'title' => lang('Full-Body Strength A'),
        'text' => lang('Squat, bench press and a row, three sets of five, with a short finisher of carries.'),
        'blocks' => array(lang('Warm-up: ten minutes of mobility'), lang('Back squat 3 × 5'), lang('Bench press 3 × 5'), lang('Barbell row 3 × 8'), lang('Farmer\'s carry 4 × 30 m'))),
    array('icon' => 'bi-trophy', 'focus' => 'strength', 'minutes' => 50, 'title' => lang('Full-Body Strength B'),
        'text' => lang('Deadlift, overhead press and pull-ups: the other half of the week\'s strength work.'),
        'blocks' => array(lang('Warm-up: ten minutes of mobility'), lang('Deadlift 3 × 5'), lang('Overhead press 3 × 5'), lang('Pull-ups 3 × as many as you can'), lang('Plank 3 × 40 seconds'))),
    array('icon' => 'bi-heart-pulse', 'focus' => 'cardio', 'minutes' => 30, 'title' => lang('Rower Intervals'),
        'text' => lang('Eight hard minutes cut into short pieces, for a fitter heart in half an hour.'),
        'blocks' => array(lang('Easy row: five minutes'), lang('8 × 1 minute hard, 1 minute easy'), lang('Easy row and stretch: five minutes'))),
    array('icon' => 'bi-heart-pulse', 'focus' => 'cardio', 'minutes' => 25, 'title' => lang('Air Bike Ladder'),
        'text' => lang('Ten, twenty, thirty seconds and back down: short, sharp and over before you know it.'),
        'blocks' => array(lang('Easy ride: five minutes'), lang('10–20–30–20–10 seconds hard, equal rest, three rounds'), lang('Easy ride: five minutes'))),
    array('icon' => 'bi-flower3', 'focus' => 'mind', 'minutes' => 20, 'title' => lang('Desk Reset'),
        'text' => lang('Hips, back and shoulders after a day in a chair: slow moves on the mat.'),
        'blocks' => array(lang('Cat and cow 10 ×'), lang('Hip flexor stretch 1 minute a side'), lang('Thoracic rotations 10 a side'), lang('Child\'s pose 2 minutes'))),
    array('icon' => 'bi-flower3', 'focus' => 'mind', 'minutes' => 15, 'title' => lang('Recovery Flow'),
        'text' => lang('Foam roller and long holds for the day after a hard session.'),
        'blocks' => array(lang('Foam roll legs and back: five minutes'), lang('Pigeon pose 1 minute a side'), lang('Hamstring stretch 1 minute a side'), lang('Slow breathing: three minutes'))),
);

// ── Shop pages ──────────────────────────────────────────────────────────

// How buying a membership works, in three steps.
$membership_steps = array(
    $step(1, lang('Choose a plan'), lang('Off-peak, unlimited or unlimited with personal training; each runs for a month.')),
    $step(2, lang('Pay online'), lang('Pay at the checkout as for any order; the receipt comes by e-mail at once.')),
    $step(3, lang('Start training'), lang('Your membership opens the members\' pages at once; show your name at the front desk on your first visit.')),
);

$member_pages[] = array(
    'key'              => 'memberships',
    'requires'         => 'ecommerce',
    'name'             => lang('buy-a-membership'),
    'folder'           => 'public',
    'title'            => lang('Buy a Membership'),
    'meta_description' => lang('Buy a month of off-peak, unlimited or unlimited training with personal sessions online, and start the same day.'),
    'tree'             => $page(array(
        $page_header(lang('Buy a Membership'), lang('Choose your plan, pay online and start training the same day. Every plan runs for a month; buy it again to keep going.')),
        $el('section', 'py-5', lang('Memberships'), array(
            $container(array($widget('membership_catalog'))),
        ), $searchable('gy-memberships', lang('buy, pay online, membership, monthly pass, price'))),
        $el('section', 'py-5 bg-body-tertiary', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $section_header(lang('Memberships'), lang('How it works')),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), $membership_steps),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'p-4 rounded-3 bg-body border', lang('Box'), array(
                            $heading('h3', lang('Not sure yet?'), 'pgy-display h5 mb-2'),
                            $para(lang('Ask for a free trial pass first: a coach shows you round, and you choose your plan after your first class.'), 'text-body-secondary mb-3'),
                            $link(lang('Get a Free Pass'), '{{page:join}}?' . $query(array('plan' => lang('Free trial pass'))), 'btn btn-primary px-4'),
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
$member_tile = function ($icon_name, $title, $text, $href) use ($el, $heading, $para, $link, $col, $icon_tile) {
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm pgy-lift', $title, array(
            $el('div', 'card-body p-4', lang('Card Body'), array(
                $icon_tile($icon_name, 'mb-3'),
                $el('h2', 'pgy-display h5 mb-2 position-static', lang('Title'), array(
                    $link($title, $href, 'stretched-link link-body-emphasis text-decoration-none'),
                )),
                $para($text, 'small text-body-secondary mb-0'),
            )),
        )),
    ), '6', '4');
};

$member_pages[] = array(
    'key'              => 'members',
    'name'             => lang('members'),
    'folder'           => 'members',
    'title'            => lang('Members\' Area'),
    'meta_description' => lang('The members\' own pages: the workout library, class bookings and personal training.'),
    'search'           => false,
    'sitemap'          => false,
    'tree'             => $page(array(
        $page_header(lang('Members\' Area'), lang('Welcome back. Book your next class, pick a workout for the floor or book a session with your coach.')),
        $el('section', 'py-5', lang('Member Links'), array(
            $container(array(
                $row(array(
                    $member_tile('bi-journal-text', lang('Workout Library'), lang('Sessions to do alone on the floor, written by our coaches.'), '{{page:workouts}}'),
                    $member_tile('bi-calendar-week', lang('Class Timetable'), lang('Every group class of the week, with a link to book your place.'), '{{page:timetable}}'),
                    $member_tile('bi-calendar-check', lang('My Class Bookings'), lang('Your requests, their status and the front desk\'s answers.'), '{{page:my_bookings}}'),
                    $member_tile('bi-person-check', lang('Personal Training'), lang('Book a session with a trainer at a free time.'), '{{page:book_online}}'),
                    $member_tile('bi-people', lang('Our Trainers'), lang('Who coaches what, and how long they have been at it.'), '{{page:trainers}}'),
                    $member_tile('bi-person-circle', lang('My Account'), lang('Your details, your orders and when your membership ends.'), '{{page:my_account}}'),
                ), '4'),
            )),
        )),
        $el('section', 'pb-5', lang('Member Notes'), array(
            $container(array(
                $el('div', 'p-4 rounded-3 bg-primary-subtle d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3', lang('Renewal'), array(
                    $el('div', '', lang('Text'), array(
                        $heading('h2', lang('Keep your membership going'), 'pgy-display h5 mb-1'),
                        $para(lang('A plan runs for a month. Buy it again before it ends, or ask at the front desk, and your training carries on without a gap.'), 'small mb-0'),
                    )),
                    $link(lang('Compare the plans'), '{{page:plans}}', 'btn btn-primary px-4 text-nowrap'),
                )),
            )),
        )),
    ), false),
);

// The workout library: a card per workout, its blocks in order.
$workout_cards = array();
foreach ($workouts as $w) {
    $blocks = array();
    foreach ($w['blocks'] as $b) $blocks[] = $el('li', 'mb-1', lang('Block'), array(), array('text' => $b));
    $workout_cards[] = $col(array(
        $el('article', 'card h-100 border-0 shadow-sm', $w['title'], array(
            $el('div', 'card-body p-4', lang('Card Body'), array(
                $el('p', 'd-flex flex-wrap align-items-center gap-2 small text-primary mb-2', lang('Kind'), array(
                    $icon($w['icon']),
                    $span($focuses[$w['focus']]['title'], '', lang('Focus')),
                    $span('·', '', lang('Separator')),
                    $span(sprintf(lang('%d min'), $w['minutes']), '', lang('Length')),
                )),
                $heading('h2', $w['title'], 'pgy-display h5 mb-2'),
                $para($w['text'], 'small text-body-secondary mb-3'),
                $el('ol', 'small ps-3 mb-0', lang('Blocks'), $blocks),
            )),
        )),
    ), '6', '4');
}

$member_pages[] = array(
    'key'              => 'workouts',
    'name'             => lang('workout-library'),
    'folder'           => 'members',
    'title'            => lang('Workout Library'),
    'meta_description' => lang('Workouts for members to do alone on the weights floor, in the cardio zone or on the mat.'),
    'search'           => false,
    'sitemap'          => false,
    'tree'             => $page(array(
        $page_header(lang('Workout Library'), lang('Sessions written by our coaches for the days you train alone. Ask anyone on the floor to check your form.')),
        $el('section', 'py-5', lang('Workouts'), array(
            $container(array(
                $row($workout_cards, '4'),
                $el('div', 'd-flex gap-2 small text-body-secondary mt-5', lang('Note'), array(
                    $icon('bi-info-circle', 'text-primary mt-1'),
                    $span(lang('New to a lift? Book a session with a coach first, or come to a class that teaches it.'), '', lang('Text')),
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

// A membership's card of the listing: its icon, its name (the link to its
// page), its price and Add to cart.
$membership_card = function ($sample_key) use ($el, $link, $span, $col, $bind, $icon_tile, $shop_button, $plans, $plan_price) {
    $p = $plans[$sample_key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm pgy-lift', lang('Product Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile($p['icon'], 'align-self-start mb-3'),
                $el('h3', 'pgy-display h5 mb-2 position-static', lang('Product Name'), array(
                    $link($p['title'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => '__short_description', 'href' => '__detail_url'))),
                )),
                $el('div', 'd-flex align-items-center justify-content-between gap-3 mt-auto pt-3', lang('Buy Line'), array(
                    $span($plan_price($sample_key), 'pgy-price fw-semibold fs-5', lang('Price'), $bind('__price_formatted')),
                    $shop_button(lang('Add to cart'), 'primary', 'position-relative z-2 text-nowrap',
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
        $el('td', 'pe-0 text-end', lang('Value'), array($span('$55.00', '', lang('Amount'), $bind($token))), $mail_style($style)),
    ), $flag !== '' ? $show_when($flag) : array());
};

$member_widgets = array(

    // The memberships on sale: the Memberships group's products on one
    // page. The notice after an add links this shop's cart.
    'membership_catalog' => array(
        'page'     => 'memberships',
        'requires' => 'ecommerce',
        'slug'     => lang('catalog'),
        'config'   => array(
            'regionType'               => 'catalog_listing',
            'product_group_id'         => '{{product_group:memberships}}',
            'group_navigation'         => 'none',
            'items_per_page'           => 12,
            'order_by_field'           => 'sort_order',
            'order_by_direction'       => 'ASC',
            'detail_page_id'           => '{{tab:product}}',
            'add_to_cart_stay_on_page' => false,
            'add_to_cart_next_page_id' => '{{tab:cart}}',
            'empty_message'            => lang('No memberships on sale right now. Ask for a free trial pass instead.'),
        ),
        'tree'     => $widget_root(array(
            $row(array($loop(array($membership_card('unlimited')))), '4'),
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

    // The receipt: the order's number and date, its memberships, the
    // totals, the billing address, and the way into the members' pages.
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
                                $span($plans['unlimited']['title'], 'fw-semibold', lang('Product Name'), $bind('__item_short_description')),
                                $span(' × ', 'text-body-secondary', lang('Text')),
                                $span('1', '', lang('Quantity'), $bind('__item_qty')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #e5e7eb')),
                            $el('td', 'pe-0 text-end', lang('Total'), array(
                                $span('$55.00', '', lang('Amount'), $bind('__item_total')),
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
                $mail_para(lang('Once the payment is confirmed, your membership starts and the members\' pages open. Sign in with the address you bought it with, and show your name at the front desk on your first visit.'), 'mb-0', 'margin:0'),
            ), lang('Next Step')),
            $mail_button(lang('Go to the Members\' Area'), '{{page:members}}'),
            $mail_para(lang('Your order and its receipt are also on the order page:'), 'small mb-1', 'margin:0 0 4px;font-size:13px'),
            $link(lang('View your order'), '{{page:order}}?order_id=^^__order_id^^', 'small', array($attr('style', 'font-size:13px;color:#b91c1c'))),
        )),
    ),
);

return array('pages' => $member_pages, 'widgets' => $member_widgets, 'catalog' => $catalog);
