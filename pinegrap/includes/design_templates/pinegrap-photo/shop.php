<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Photography Studio", its shop: the catalog (the Photo
 * Sessions group: a shoot deposit for each package of $packages and the
 * gift vouchers of $gift_vouchers), the vouchers page, the product, cart,
 * checkout and order pages and the order receipt e-mail. Included after
 * parts.php and mail.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ..., 'catalog' => ...).
 *
 * Every page and widget here needs the shop: the shared parts never link
 * them, so a site without the shop has none of them and no dead link.
 *
 * The prices are parts.php's own ($package_deposits, $gift_vouchers): the
 * price table and the cards write them with $money(), the products are
 * made from the same figures, so the page and the cart show one amount. A
 * deposit is paid once the studio confirms a date, a voucher is sent by
 * e-mail: nothing is shipped, so the checkout asks for no delivery
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
// Photo Sessions group under the site's top group, each product found
// again by its SKU. The deposits come first, in the packages' order, then
// the vouchers.
$session_products = array();
foreach ($packages as $key => $p) {
    $session_products[] = array(
        'sku'         => 'PHOTO-DEPOSIT-' . strtoupper($key),
        'title'       => sprintf(lang('Shoot Deposit: %s'), $p['title']),
        'description' => lang('Pay it once the studio confirms your date: it holds the date and is taken off the price of the session.'),
        'price'       => $package_deposits[$key],
        'shippable'   => false,
        'featured'    => false,
        'keywords'    => lang('deposit, shoot, session, photographer, booking'),
    );
}
foreach ($gift_vouchers as $key => $v) {
    $session_products[] = array(
        'sku'         => $v['sku'],
        'title'       => $v['title'],
        'description' => $v['text'],
        'price'       => $v['price'],
        'shippable'   => false,
        'featured'    => $key === 'gift150',
        'keywords'    => lang('gift voucher, gift, photo shoot, portrait, family photos'),
    );
}
$catalog = array(
    'folder'  => 'pinegrap_photo_catalog',
    // The Details tab of every product's page.
    'details' => array(
        lang('A deposit is paid after the studio confirms your date in your booking request; it is taken off the price of the session.'),
        lang('A gift voucher is sent by e-mail within one working day of the order, with its code, and is valid for a year.'),
        lang('A sample product of the template: change its name and price in the catalog, or remove it.'),
    ),
    'groups'  => array(
        'sessions' => array(
            'parent'      => 'root',
            'title'       => lang('Photo Sessions'),
            'description' => lang('Shoot deposits for each package and gift vouchers, sent by e-mail.'),
            'products'    => $session_products,
        ),
    ),
);

// ── Pages ───────────────────────────────────────────────────────────────

// How a voucher works, in three steps.
$voucher_steps = array(
    $step(1, lang('Choose a voucher'), lang('An amount towards any session, for a birthday, a new family or a wedding gift.')),
    $step(2, lang('Order it online'), lang('Pay at the checkout as for any order; the receipt comes by e-mail at once.')),
    $step(3, lang('We send it by e-mail'), lang('Within one working day, with its code, ready to print or to forward.')),
);

$shop_pages[] = array(
    'key'              => 'vouchers',
    'requires'         => 'ecommerce',
    'name'             => lang('gift-vouchers'),
    'folder'           => 'public',
    'title'            => lang('Gift Vouchers and Deposits'),
    'meta_description' => lang('Gift vouchers for a portrait, family or newborn session, sent by e-mail, and the deposit that holds the date of your shoot.'),
    'tree'             => $page(array(
        $page_header(lang('Gift Vouchers and Deposits'), lang('Give a photo session as a present, or pay the deposit that holds the date the studio confirmed for you.')),
        $el('section', 'py-5', lang('Products'), array(
            $container(array($widget('session_catalog'))),
        ), $searchable('ph-vouchers', lang('gift, present, voucher, gift card, deposit, pay online'))),
        $el('section', 'py-5 bg-body-tertiary', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $section_header(lang('Gift vouchers'), lang('How it works')),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), $voucher_steps),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body border mb-4', lang('Box'), array(
                            $heading('h3', lang('Paying a deposit'), 'pph-display h5 mb-2'),
                            $para(lang('Pay the deposit of your package only after the studio confirms your date in your booking request, and name the order number in a message there. The rest is paid on the day of the shoot.'), 'text-body-secondary mb-3'),
                            $link(lang('Book a Shoot'), '{{page:book}}', 'btn btn-primary rounded-pill px-4'),
                        )),
                        $el('div', 'p-4 rounded-4 bg-body border', lang('Box'), array(
                            $heading('h3', lang('Using a voucher'), 'pph-display h5 mb-2'),
                            $para(lang('Name the voucher\'s code in the notes of your booking request. A voucher is valid for a year from the order and may be spent on any package.'), 'text-body-secondary mb-3'),
                            $link(lang('See the Packages'), '{{page:packages}}', 'btn btn-outline-primary rounded-pill px-4'),
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

// A product's card of the listing: an icon, its name (the link to its
// page), its price and Add to cart.
$session_card = function ($sample_key) use ($el, $link, $span, $col, $bind, $icon_tile, $shop_button, $gift_vouchers, $money) {
    $v = $gift_vouchers[$sample_key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 pph-lift', lang('Product Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile($v['icon'], 'align-self-start mb-3'),
                $el('h3', 'pph-display h5 mb-2 position-static', lang('Product Name'), array(
                    $link($v['title'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => '__short_description', 'href' => '__detail_url'))),
                )),
                $el('div', 'd-flex align-items-center justify-content-between gap-3 mt-auto pt-3', lang('Buy Line'), array(
                    $span($money($v['price']), 'pph-price fw-semibold fs-5', lang('Price'), $bind('__price_formatted')),
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
        $el('td', 'pe-0 text-end', lang('Value'), array($span('$150.00', '', lang('Amount'), $bind($token))), $mail_style($style)),
    ), $flag !== '' ? $show_when($flag) : array());
};

$shop_widgets = array(

    // The deposits and vouchers on sale: the Photo Sessions group's
    // products, every one on one page. The notice after an add links this
    // shop's cart.
    'session_catalog' => array(
        'page'     => 'vouchers',
        'requires' => 'ecommerce',
        'slug'     => lang('catalog'),
        'config'   => array(
            'regionType'               => 'catalog_listing',
            'product_group_id'         => '{{product_group:sessions}}',
            'group_navigation'         => 'none',
            'items_per_page'           => 24,
            'order_by_field'           => 'sort_order',
            'order_by_direction'       => 'ASC',
            'detail_page_id'           => '{{tab:product}}',
            'add_to_cart_stay_on_page' => true,
            'add_to_cart_next_page_id' => '{{tab:cart}}',
            'empty_message'            => lang('No gift vouchers on sale right now.'),
        ),
        'tree'     => $widget_root(array(
            $row(array($loop(array($session_card('gift150')))), '4'),
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
    // product's breadcrumb starts at the vouchers page.
    'product_detail' => array(
        'page'     => 'product',
        'requires' => 'ecommerce',
        'slug'     => lang('product-detail'),
        'config'   => array(
            'regionType'              => 'catalog_item_view',
            'product_group_id'        => 0,
            'next_page_id'            => '{{tab:cart}}',
            'catalog_listing_page_id' => '{{tab:vouchers}}',
            'catalog_root_label'      => lang('Photo Sessions'),
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
    // the billing address, what comes next for a deposit and a voucher,
    // and the way to book a shoot.
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
                                $span($gift_vouchers['gift150']['title'], 'fw-semibold', lang('Product Name'), $bind('__item_short_description')),
                                $span(' × ', 'text-body-secondary', lang('Text')),
                                $span('1', '', lang('Quantity'), $bind('__item_qty')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #dde3ea')),
                            $el('td', 'pe-0 text-end', lang('Total'), array(
                                $span('$150.00', '', lang('Amount'), $bind('__item_total')),
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
                $mail_para(lang('A deposit holds the date the studio confirmed: name this order\'s number in a message on your booking request. A gift voucher comes to this address within one working day, with its code, ready to print or to forward.'), 'mb-0', 'margin:0'),
            ), lang('Next Step')),
            $mail_button(lang('Book a Shoot'), '{{page:book}}'),
            $mail_para(lang('Your order and its receipt are also on the order page:'), 'small mb-1', 'margin:0 0 4px;font-size:13px'),
            $link(lang('View your order'), '{{page:order}}?order_id=^^__order_id^^', 'small', array($attr('style', 'font-size:13px;color:#3e5c76'))),
        )),
    ),
);

return array('pages' => $shop_pages, 'widgets' => $shop_widgets, 'catalog' => $catalog);
