<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Veterinary", its shop: the catalog (the Health Plans
 * group, a product for each plan of $plans that pays its year at once; the
 * Pet Care group, a product for each item of $care_products), the shop
 * page, the product, cart, checkout and order pages and the order receipt
 * e-mail. Included after parts.php and mail.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ..., 'catalog' => ...).
 *
 * Every page and widget here needs the shop: the shared parts and the
 * public pages never link them (the health plans page shows the monthly
 * price and opens the sign-up request), so a site without the shop has
 * none of them and no dead link.
 *
 * A plan's product costs its monthly price times its months, per currency,
 * from parts.php's own $plans: the shop card and the health plans page
 * therefore name the same plan at the same rate. A plan bought online gives
 * no membership access: the reception books the first check, as for a
 * sign-up request. A plan is not shipped; the pet care products are.
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

// The pet care products, sold only with the shop. 'species' are the pets
// each one is for.
$care_products = array(
    'dental_kit' => array('icon' => 'bi-emoji-smile', 'species' => array('dog', 'cat'), 'featured' => true,
        'title' => lang('Pet Dental Care Kit'),
        'text'  => lang('A soft finger brush, a long brush and a meat-flavoured toothpaste, with our nurses\' guide to brushing at home.'),
        'price' => array('USD' => 19, 'EUR' => 17, 'GBP' => 15, 'TRY' => 620)),
    'calming'    => array('icon' => 'bi-moon-stars', 'species' => array('cat'), 'featured' => false,
        'title' => lang('Calming Diffuser for Cats'),
        'text'  => lang('A plug-in diffuser with a month\'s refill that helps a cat settle after a move, a visit or a new arrival.'),
        'price' => array('USD' => 29, 'EUR' => 26, 'GBP' => 23, 'TRY' => 950)),
    'hay_mix'    => array('icon' => 'bi-flower2', 'species' => array('small'), 'featured' => false,
        'title' => lang('Meadow Hay and Herb Mix'),
        'text'  => lang('Long-stemmed meadow hay with dried herbs, the food rabbits and guinea pigs need for healthy teeth.'),
        'price' => array('USD' => 12, 'EUR' => 11, 'GBP' => 9, 'TRY' => 390)),
    'carrier'    => array('icon' => 'bi-bag-heart', 'species' => array('cat', 'small'), 'featured' => false,
        'title' => lang('Top-Opening Pet Carrier'),
        'text'  => lang('A sturdy carrier that opens from the top, so a nervous cat or rabbit can be examined without being pulled out.'),
        'price' => array('USD' => 39, 'EUR' => 35, 'GBP' => 31, 'TRY' => 1290)),
);

// A plan's year, per currency: the monthly price times its months.
$plan_year_price = function ($key) use ($plans) {
    $out = array();
    foreach ($plans[$key]['price'] as $currency => $amount) $out[$currency] = $amount * $plans[$key]['months'];
    return $out;
};
// A plan's "… a year" words in the shop.
$plan_year_text = function ($key) use ($money, $plan_year_price) {
    return sprintf(lang('%s a year'), $money($plan_year_price($key)));
};

// ── The catalog ─────────────────────────────────────────────────────────
// Made once when the template is first opened (_pg_tpl_catalog()): the
// Health Plans and Pet Care groups under the site's top group, each
// product found again by its SKU, in the order of the data.
$plan_products = array();
foreach ($plans as $key => $p) {
    $plan_products[] = array(
        'sku'         => $p['sku'],
        'title'       => $p['title'],
        'description' => $p['text'] . ' ' . implode(' · ', $p['points']) . '. ' . sprintf(lang('%1$s, paid for twelve months at once.'), $plan_price($key)),
        'price'       => $plan_year_price($key),
        'shippable'   => false,
        'featured'    => $p['featured'],
        'keywords'    => lang('health plan, pet plan, preventive care, vaccination, wellness exam, flea, worm'),
    );
}
$care_shop_products = array();
foreach ($care_products as $key => $c) {
    $care_shop_products[] = array(
        'sku'         => 'VET-CARE-' . strtoupper(str_replace('_', '-', $key)),
        'title'       => $c['title'],
        'description' => $c['text'],
        'price'       => $c['price'],
        'featured'    => $c['featured'],
        'keywords'    => lang('pet care, pet supplies, dog, cat, rabbit, vet recommended'),
    );
}
$catalog = array(
    'folder'  => 'pinegrap_veterinary_catalog',
    // The Details tab of every product's page.
    'details' => array(
        lang('A health plan starts at your pet\'s first check: the reception calls within a working day to book it.'),
        lang('Pet care products are chosen by our vets and nurses and sent within two working days, or wait for you at the reception.'),
        lang('A sample product of the template: change its name and price in the catalog, or remove it.'),
    ),
    'groups'  => array(
        'plans' => array(
            'parent'      => 'root',
            'title'       => lang('Health Plans'),
            'description' => lang('A year of preventive care for your pet, paid at once.'),
            'products'    => $plan_products,
        ),
        'care'  => array(
            'parent'      => 'root',
            'title'       => lang('Pet Care Products'),
            'description' => lang('Everyday care our vets and nurses use and recommend.'),
            'products'    => $care_shop_products,
        ),
    ),
);

// ── Pages ───────────────────────────────────────────────────────────────

// How a plan bought online starts, in three steps.
$plan_shop_steps = array(
    $step(1, lang('Choose the plan'), lang('One plan for each pet; the plans page says which pets each plan is for.')),
    $step(2, lang('Pay the year online'), lang('The receipt comes by e-mail at once, with the order number.')),
    $step(3, lang('Book the first check'), lang('The reception calls within a working day; the plan runs for twelve months from that check.')),
);

$shop_pages[] = array(
    'key'              => 'shop',
    'requires'         => 'ecommerce',
    'name'             => lang('shop'),
    'folder'           => 'public',
    'title'            => lang('Shop'),
    'meta_description' => lang('Buy a year of a pet health plan online, and the dental kits, carriers and food our vets recommend.'),
    'tree'             => $page(array(
        $page_header(lang('Shop'), lang('A year of a health plan paid at once, and the everyday care our vets and nurses recommend.')),
        $el('section', 'py-5', lang('Health Plans'), array(
            $container(array(
                $section_header(lang('A year of care'), lang('Health Plans'), lang('Compare the plans'), '{{page:health_plans}}'),
                $widget('plan_catalog'),
            )),
        ), $searchable('vt-shop-plans', lang('buy a health plan, pay the year, pet plan, puppy, kitten, senior, rabbit'))),
        $el('section', 'py-5 bg-body-tertiary', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Buying a plan online'), 'pvt-display h3 mb-4'),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), $plan_shop_steps),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body border mb-4', lang('Box'), array(
                            $heading('h3', lang('Rather pay each month?'), 'pvt-display h5 mb-2'),
                            $para(lang('Send a sign-up request and the reception sets up the monthly payment with you at the first check.'), 'text-body-secondary mb-3'),
                            $link(lang('Join a Health Plan'), '{{page:plan_join}}', 'btn btn-outline-primary rounded-pill px-4'),
                        )),
                        $el('div', 'p-4 rounded-4 bg-body border', lang('Box'), array(
                            $heading('h3', lang('Not sure what your pet needs?'), 'pvt-display h5 mb-2'),
                            $para(lang('Ask at your next visit: the vet checks the plan and the products fit your pet.'), 'text-body-secondary mb-3'),
                            $link(lang('Request an Appointment'), '{{page:appointment}}', 'btn btn-primary rounded-pill px-4'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
        $el('section', 'py-5', lang('Pet Care Products'), array(
            $container(array(
                $section_header(lang('Vet recommended'), lang('Pet Care Products')),
                $para(lang('Sent within two working days, or ready for you at the reception on your next visit.'), 'lead text-body-secondary mb-4'),
                $widget('care_catalog'),
            )),
        ), $searchable('vt-shop-care', lang('pet supplies, toothbrush, toothpaste, calming, diffuser, hay, rabbit food, carrier'))),
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
// page), the pets it is for, the sample's words, its price and Add to
// cart.
$shop_card = function ($icon_name, $title, $pets, $text, $price, $lg) use ($el, $link, $span, $para, $col, $bind, $icon_tile, $species_badges, $shop_button) {
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 pvt-lift', lang('Product Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile($icon_name, 'align-self-start mb-3'),
                $el('h3', 'pvt-display h5 mb-2 position-static', lang('Product Name'), array(
                    $link($title, '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => '__short_description', 'href' => '__detail_url'))),
                )),
                $species_badges($pets),
                $para($text, 'small text-body-secondary mb-0'),
                $el('div', 'd-flex align-items-center justify-content-between gap-3 mt-auto pt-3', lang('Buy Line'), array(
                    $span($price, 'pvt-price fw-semibold fs-5', lang('Price'), $bind('__price_formatted')),
                    $shop_button(lang('Add to cart'), 'primary', 'rounded-pill position-relative z-2 text-nowrap',
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
        'page'     => 'shop',
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

// An address of the receipt under its label.
$shop_address = function ($label, $token, $extra = array()) use ($el, $bind, $mail_style) {
    return $el('div', '', $label, array(
        $el('p', 'small text-body-secondary mb-1', lang('Label'), array(), array('text' => $label)),
        $el('div', '', lang('Address'), array(), array_merge(array('text' => lang('Jane Cooper')), $bind($token))),
    ), array_merge($mail_style('margin:0 0 16px'), $extra));
};

$shop_widgets = array(

    // The plans and the pet care products on sale, each group in its own
    // listing. The sample cards are the plan most owners choose and the
    // dental kit.
    'plan_catalog' => $shop_listing(lang('plan-catalog'), 'plans', lang('No health plans on sale online right now: send a sign-up request instead.'),
        $shop_card($plans['adult']['icon'], $plans['adult']['title'], $plans['adult']['species'], $plans['adult']['text'], $plan_year_text('adult'), '3')),
    'care_catalog' => $shop_listing(lang('care-catalog'), 'care', lang('No pet care products on sale right now: ask at the reception.'),
        $shop_card($care_products['dental_kit']['icon'], $care_products['dental_kit']['title'], $care_products['dental_kit']['species'],
            $care_products['dental_kit']['text'], $money($care_products['dental_kit']['price']), '3')),

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
    // product's breadcrumb starts at the shop page.
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
    // the shipping address while something is sent, the billing address,
    // what comes next for a plan and for the products, and the way to an
    // appointment.
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
                                $span($plans['adult']['title'], 'fw-semibold', lang('Product Name'), $bind('__item_short_description')),
                                $span(' × ', 'text-body-secondary', lang('Text')),
                                $span('1', '', lang('Quantity'), $bind('__item_qty')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #d1fae5')),
                            $el('td', 'pe-0 text-end', lang('Total'), array(
                                $span($money($plan_year_price('adult')), '', lang('Amount'), $bind('__item_total')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #d1fae5;text-align:right')),
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
            $shop_address(lang('Shipping Address'), '__shipping_address_html', $show_when('has_shipping')),
            $shop_address(lang('Billing Address'), '__billing_address_html'),
            $mail_box(array(
                $el('p', 'fw-semibold mb-1', lang('Title'), array(), array_merge(array('text' => lang('What comes next')), $mail_style('font-weight:600;margin:0 0 4px'))),
                $mail_para(lang('For a health plan, the reception calls within a working day to book your pet\'s first check; the plan runs for twelve months from it. Pet care products are sent within two working days, or wait for you at the reception.'), 'mb-0', 'margin:0'),
            ), lang('Next Step')),
            $mail_button(lang('Request an Appointment'), '{{page:appointment}}'),
            $mail_para(lang('Your order and its receipt are also on the order page:'), 'small mb-1', 'margin:0 0 4px;font-size:13px'),
            $link(lang('View your order'), '{{page:order}}?order_id=^^__order_id^^', 'small', array($attr('style', 'font-size:13px;color:#047857'))),
        )),
    ),
);

return array('pages' => $shop_pages, 'widgets' => $shop_widgets, 'catalog' => $catalog);
