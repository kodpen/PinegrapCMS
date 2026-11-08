<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Zoo", its shop: the catalog (the Tickets, Memberships
 * and Adoptions groups: a product for each item of $tickets and
 * $memberships, and one for each level of $adoption_levels of every animal
 * of $animals that can be adopted), the buy tickets and adoption shop
 * pages, the product, cart, checkout and order pages and the order receipt
 * e-mail. Included after parts.php, cards.php and mail.php in the same
 * scope; returns array('pages' => ..., 'widgets' => ..., 'catalog' => ...).
 *
 * Every page and widget here needs the shop: the shared parts and the
 * public pages never link them (the tickets, memberships and adopt pages
 * say the prices as words), so a site without the shop has none of them
 * and no dead link.
 *
 * The prices are parts.php's own ($ticket_prices, $membership_prices,
 * $adoption_prices): the cards write them with $money(), the products are
 * made from the same figures, so the page and the cart show one amount. A
 * ticket is shown at the gate and a membership card is printed at the
 * membership desk, both from the receipt; an adoption pack comes by
 * e-mail. Nothing is shipped, so the checkout asks for no delivery
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
// Made once when the template is first opened (_pg_tpl_catalog()): three
// groups under the site's top group, each product found again by its SKU,
// in the order of the data.
$shop_sku = function ($prefix, $key) {
    return $prefix . strtoupper(str_replace('_', '-', $key));
};
$ticket_products = array();
foreach ($tickets as $key => $t) {
    $ticket_products[] = array(
        'sku'         => $shop_sku('ZOO-TICKET-', $key),
        'title'       => $t['title'],
        'description' => $t['who'] . '. ' . lang('One day in the whole park and the aquarium.'),
        'price'       => $ticket_prices[$key],
        'shippable'   => false,
        'featured'    => $key === 'family',
        'keywords'    => lang('zoo ticket, day ticket, entry, aquarium, family'),
    );
}
$membership_products = array();
foreach ($memberships as $key => $m) {
    $membership_products[] = array(
        'sku'         => $shop_sku('ZOO-MEMBER-', $key),
        'title'       => $m['title'],
        'description' => $m['who'] . '. ' . implode(' · ', $m['benefits']) . '.',
        'price'       => $membership_prices[$key],
        'shippable'   => false,
        'featured'    => $key === 'family',
        'keywords'    => lang('membership, annual pass, free entry, zoo'),
    );
}
// An adoption is one animal at one level for a year: a product for each
// level of each animal that can be adopted, the animal's key and the
// level's in its SKU.
$adoption_products = array();
foreach ($animals as $animal_key => $a) {
    if (!$a['adopt']) {
        continue;
    }
    foreach ($adoption_levels as $level_key => $l) {
        $adoption_products[] = array(
            'sku'         => $shop_sku('ZOO-ADOPT-', $animal_key . '_' . $level_key),
            'title'       => sprintf(lang('Adoption: %1$s, %2$s'), $a['title'], $l['title']),
            'description' => $a['summary'] . ' ' . implode(' · ', $l['benefits']) . '.',
            'price'       => $adoption_prices[$level_key],
            'shippable'   => false,
            'featured'    => $animal_key === 'giraffe' && $level_key === 'keeper',
            'keywords'    => lang('adopt an animal, adoption, gift, conservation, zoo'),
        );
    }
}
$catalog = array(
    'folder'  => 'pinegrap_zoo_catalog',
    // The Details tab of every product's page.
    'details' => array(
        lang('Show the order receipt at the gate on the day of your visit; a membership card is printed at the membership desk beside the gate.'),
        lang('An adoption pack comes by e-mail within three working days, with the animal\'s photo, its story and the keepers\' news.'),
        lang('A sample product of the template: change its name and price in the catalog, or remove it.'),
    ),
    'groups'  => array(
        'tickets' => array(
            'parent'      => 'root',
            'title'       => lang('Tickets'),
            'description' => lang('A day in the whole park and the aquarium, for one visitor or the family.'),
            'products'    => $ticket_products,
        ),
        'memberships' => array(
            'parent'      => 'root',
            'title'       => lang('Memberships'),
            'description' => lang('A year of free entry for one, two or the whole family.'),
            'products'    => $membership_products,
        ),
        'adoptions' => array(
            'parent'      => 'root',
            'title'       => lang('Adoptions'),
            'description' => lang('One animal for a year, at one of three levels.'),
            'products'    => $adoption_products,
        ),
    ),
);

// ── Pages ───────────────────────────────────────────────────────────────

// How a ticket bought online works, in three steps.
$ticket_shop_steps = array(
    $step(1, lang('Choose your tickets'), lang('One for each visitor; children under three come in free.')),
    $step(2, lang('Pay online'), lang('The receipt comes by e-mail at once, with the order number.')),
    $step(3, lang('Show the receipt at the gate'), lang('On your phone or printed; a membership card is printed at the desk beside the gate.')),
);

$shop_pages[] = array(
    'key'              => 'buy_tickets',
    'requires'         => 'ecommerce',
    'name'             => lang('buy-tickets'),
    'folder'           => 'public',
    'title'            => lang('Buy Tickets'),
    'meta_description' => lang('Day tickets and memberships online: pay now and show the receipt at the gate of the zoo.'),
    'tree'             => $page(array(
        $page_header(lang('Buy Tickets'), lang('Buy your tickets online and walk past the queue at the gate. Children under three come in free.')),
        $el('section', 'py-5', lang('Tickets'), array(
            $container(array(
                $section_header(lang('Day tickets'), lang('The whole park and the aquarium for the day.')),
                $widget('ticket_catalog'),
            )),
        ), $searchable('zoo-shop-tickets', lang('buy tickets, zoo ticket, adult, child, senior, family, price'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Memberships'), array(
            $container(array(
                $section_header(lang('A year of visits'), lang('Free entry every day for a year.')),
                $widget('membership_catalog'),
            )),
        ), $searchable('zoo-shop-memberships', lang('buy membership, annual pass, couple, family, free entry'))),
        $el('section', 'py-5', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Online in three steps'), 'pzo-display h3 mb-4'),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), $ticket_shop_steps),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('Box'), array(
                            $heading('h2', lang('Adopt an animal'), 'pzo-display h5 mb-2'),
                            $para(lang('Feed a giraffe, a penguin or an orangutan for a year and help its cousins in the wild.'), 'text-body-secondary mb-3'),
                            $link(lang('Adoption Shop'), '{{page:adopt_shop}}', 'btn btn-success rounded-pill px-4'),
                        )),
                        $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('Box'), array(
                            $heading('h2', lang('Coming as a group?'), 'pzo-display h5 mb-2'),
                            $para(lang('Schools and groups of ten or more ask for a visit with a keeper and a group price.'), 'text-body-secondary mb-3'),
                            $link(lang('Ask for a group visit'), '{{page:visit_request}}', 'btn btn-outline-success rounded-pill px-4'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    )),
);

// How an adoption bought online works, in three steps.
$adoption_shop_steps = array(
    $step(1, lang('Choose an animal and a level'), lang('Friend, Keeper or Guardian: the same three levels for every animal.')),
    $step(2, lang('Pay online'), lang('The receipt comes by e-mail at once, with the order number.')),
    $step(3, lang('Receive the adoption pack'), lang('The certificate, the photo and the animal\'s story come by e-mail within three working days.')),
);

$shop_pages[] = array(
    'key'              => 'adopt_shop',
    'requires'         => 'ecommerce',
    'name'             => lang('adoption-shop'),
    'folder'           => 'public',
    'title'            => lang('Adoption Shop'),
    'meta_description' => lang('Adopt a giraffe, a lion, an orangutan, a penguin or a sea turtle online for a year, as a gift or for yourself.'),
    'tree'             => $page(array(
        $page_header(lang('Adoption Shop'), lang('An adoption feeds an animal here for a year and helps its cousins in the wild.')),
        $el('section', 'py-5', lang('Adoptions'), array(
            $container(array($widget('adoption_catalog'))),
        ), $searchable('zoo-shop-adoptions', lang('adopt online, adoption gift, giraffe, penguin, orangutan, lion, sea turtle'))),
        $el('section', 'py-5 bg-body-tertiary', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Online in three steps'), 'pzo-display h3 mb-4'),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), $adoption_shop_steps),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body shadow-sm mb-4', lang('Box'), array(
                            $heading('h2', lang('What each level gives'), 'pzo-display h5 mb-2'),
                            $para(lang('From the certificate and the keepers\' news to a meeting with the animal and its keeper.'), 'text-body-secondary mb-3'),
                            $link(lang('Compare the levels'), '{{page:adopt}}', 'btn btn-outline-success rounded-pill px-4'),
                        )),
                        $el('div', 'p-4 rounded-4 bg-body shadow-sm', lang('Box'), array(
                            $heading('h2', lang('Come and see them'), 'pzo-display h5 mb-2'),
                            $para(lang('Buy your day tickets in the same order and visit your animal at its feeding time.'), 'text-body-secondary mb-3'),
                            $link(lang('Buy Tickets'), '{{page:buy_tickets}}', 'btn btn-success rounded-pill px-4'),
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

// A product's card of a listing, cut like the tickets page's stubs: an
// icon, its name (the link to its page), the sample's words, then its
// price and Add to cart under a dashed edge.
$shop_card = function ($icon_name, $title, $who, $price, $lg) use ($el, $link, $span, $para, $col, $bind, $icon_tile, $shop_button) {
    return $col(array(
        $el('article', 'card h-100 rounded-4 border-2 border-success-subtle', lang('Product Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile($icon_name, 'align-self-start mb-3'),
                $el('h3', 'pzo-display h5 mb-1 position-static', lang('Product Name'), array(
                    $link($title, '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => '__short_description', 'href' => '__detail_url'))),
                )),
                $para($who, 'small text-body-secondary mb-0'),
                $el('div', 'd-flex align-items-center justify-content-between gap-3 mt-auto pt-3 border-top border-2 border-success-subtle', lang('Buy Line'), array(
                    $span($price, 'pzo-price fw-bold fs-5 text-success-emphasis', lang('Price'), $bind('__price_formatted')),
                    $shop_button(lang('Add to cart'), 'success', 'position-relative z-2 rounded-pill text-nowrap',
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

    // The tickets and memberships on sale on the buy tickets page, the
    // adoptions on their own.
    'ticket_catalog'     => $shop_listing('buy_tickets', lang('ticket-catalog'), 'tickets', lang('No tickets on sale online right now: buy them at the gate.'),
        $shop_card('bi-ticket-perforated', $tickets['family']['title'], $tickets['family']['who'], $money($ticket_prices['family']), '3')),
    'membership_catalog' => $shop_listing('buy_tickets', lang('membership-catalog'), 'memberships', lang('No memberships on sale online right now: join at the membership desk.'),
        $shop_card('bi-person-badge', $memberships['family']['title'], $memberships['family']['who'], $money($membership_prices['family']), '4')),
    'adoption_catalog'   => $shop_listing('adopt_shop', lang('adoption-catalog'), 'adoptions', lang('No adoptions on sale online right now: ask at the gate desk.'),
        $shop_card('bi-heart', sprintf(lang('Adoption: %1$s, %2$s'), $animals['giraffe']['title'], $adoption_levels['keeper']['title']),
            $animals['giraffe']['summary'], $money($adoption_prices['keeper']), '4')),

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
    // the billing address, what comes next for a ticket, a membership and
    // an adoption, and the way to today's feeding times.
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
                                $span($tickets['family']['title'], 'fw-semibold', lang('Product Name'), $bind('__item_short_description')),
                                $span(' × ', 'text-body-secondary', lang('Text')),
                                $span('1', '', lang('Quantity'), $bind('__item_qty')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #dbe4d6')),
                            $el('td', 'pe-0 text-end', lang('Total'), array(
                                $span($money($ticket_prices['family']), '', lang('Amount'), $bind('__item_total')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #dbe4d6;text-align:right')),
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
                $mail_para(lang('Show this receipt at the gate on the day of your visit; a membership card is printed at the membership desk beside the gate. An adoption pack comes by e-mail within three working days.'), 'mb-0', 'margin:0'),
            ), lang('Next Step')),
            $mail_button(lang('Today at the Zoo'), '{{page:today}}'),
            $mail_para(lang('Your order and its receipt are also on the order page:'), 'small mb-1', 'margin:0 0 4px;font-size:13px'),
            $link(lang('View your order'), '{{page:order}}?order_id=^^__order_id^^', 'small', array($attr('style', 'font-size:13px;color:#1f6b46;color:var(--bs-link-color,#1f6b46)'))),
        )),
    ),
);

return array('pages' => $shop_pages, 'widgets' => $shop_widgets, 'catalog' => $catalog);
