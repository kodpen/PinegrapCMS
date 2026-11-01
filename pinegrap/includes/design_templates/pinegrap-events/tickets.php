<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Events and Conferences", its tickets: the tickets page
 * (what each pass includes, how to pay, the common questions), the passes
 * on sale in the shop, the product, cart, checkout and order pages, the
 * order receipt e-mail, and the catalog the shop sells the passes from.
 * Included after parts.php, mail.php and events.php in the same scope;
 * returns array('pages' => ..., 'widgets' => ..., 'catalog' => ...).
 *
 * The tickets page is always there: on a site without the shop its passes
 * lead to the registration form (an invoice, or a free event), so no link
 * of the template leads nowhere. With the shop, the passes are products of
 * the Tickets group, not shipped, and the page lists them with an Add to
 * cart button; the shop pages and the receipt need the shop.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$ticket_pages = array();

// ── The catalog ─────────────────────────────────────────────────────────
// Made once when the template is first opened (_pg_tpl_catalog()): the
// Tickets group under the site's top group, a product for each pass found
// again by its SKU. Prices in the base currency, USD for any currency not
// listed. A pass is not shipped: the cart goes to checkout without a
// shipping step.
$pass_prices = array(
    'day'  => array('USD' => 149, 'EUR' => 139, 'GBP' => 119, 'TRY' => 5999),
    'full' => array('USD' => 249, 'EUR' => 229, 'GBP' => 199, 'TRY' => 9999),
    'vip'  => array('USD' => 349, 'EUR' => 319, 'GBP' => 279, 'TRY' => 13999),
);
$pass_products = array();
foreach ($passes as $key => $p) {
    $pass_products[] = array(
        'sku'         => $p['sku'],
        'title'       => $p['title'],
        'description' => $p['text'] . ' ' . implode(' · ', $p['items']),
        'price'       => $pass_prices[$key],
        'shippable'   => false,
        'featured'    => !empty($p['featured']),
    );
}
$catalog = array(
    'folder'  => 'pinegrap_events_catalog',
    // The Details tab of every pass's page.
    'details' => array(
        lang('Your pass is sent by e-mail with the order receipt; bring it on your phone.'),
        lang('Register each attendee on the registration form with the e-mail the pass was bought with.'),
        lang('A sample product of the template: change its name and price in the catalog, or remove it.'),
    ),
    'groups'  => array(
        'tickets' => array(
            'parent'      => 'root',
            'title'       => lang('Tickets'),
            'description' => lang('The passes of the annual conference.'),
            'products'    => $pass_products,
        ),
    ),
);

// ── Pages ───────────────────────────────────────────────────────────────

// A pass as a card: what it includes, the price, and the way to register
// with it (the pass filled in on the form).
$pass_cards = array();
foreach ($passes as $key => $p) {
    $is_featured = !empty($p['featured']);
    $pass_cards[] = $col(array(
        $el('div', 'card h-100 rounded-4 ' . ($is_featured ? 'border-primary border-2 shadow' : 'border-0 shadow-sm'), $p['title'], array_merge(
            $is_featured ? array($el('div', 'card-header bg-primary text-white text-center small fw-semibold border-0', lang('Badge'), array(), array('text' => lang('Most chosen')))) : array(),
            array($el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $heading('h3', $p['title'], 'h5 fw-bold mb-2'),
                $para($p['text'], 'small text-body-secondary mb-3'),
                $para($p['price'], 'display-6 fw-bold mb-3'),
                $check_list($p['items'], 'list-unstyled small mb-4'),
                $link(lang('Register with this pass'), '{{page:registration}}?pass=' . rawurlencode($p['title']),
                    'btn ' . ($is_featured ? 'btn-primary' : 'btn-outline-primary') . ' mt-auto'),
            )))
        ), $searchable('pass-' . $key, lang('ticket, pass, price'))),
    ), '', '4');
}

$ticket_pages[] = array(
    'key'              => 'tickets',
    'name'             => lang('tickets'),
    'folder'           => 'public',
    'title'            => lang('Tickets'),
    'meta_description' => lang('The conference passes, what each includes and what it costs; buy online or register and pay by invoice.'),
    'tree'             => $page(array(
        $page_header(lang('Tickets'), lang('Three passes for the conference; most of our other events are free. Buy online, or register and we send your company an invoice.')),
        $el('section', 'py-5', lang('Passes'), array(
            $container(array(
                $row($pass_cards, '4', '', 'align-items-stretch'),
                $el('div', 'alert alert-light border d-flex gap-2 mt-4 mb-0', lang('Note'), array(
                    $icon('bi-people', 'mt-1'),
                    $span(lang('Coming as a team of five or more? Write to us for a group price and one invoice.'), '', lang('Text')),
                )),
            )),
        )),
        // The passes on sale in the shop; left out on a site without it.
        $widget('ticket_passes'),
        $el('section', 'py-5 bg-body-tertiary', lang('FAQ'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Good to know'), 'small text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('Tickets and payment'), 'h3 fw-bold mb-3'),
                        $para(lang('Anything else? Write to us and the answer comes to your inbox.'), 'text-body-secondary mb-3'),
                        $link(lang('Contact us'), '{{page:contact}}', 'btn btn-outline-primary px-4'),
                    ), '', '4'),
                    $col(array(
                        $el('div', 'accordion', lang('Questions'), array(
                            $faq(1, lang('Can my company pay by invoice?'), lang('Yes. Choose a pass on the registration form and add the invoice address in the notes; the invoice comes within a working day.'), lang('invoice, bank transfer, company, pay later'), 'ev-ticket-faq'),
                            $faq(2, lang('Can I get a refund?'), lang('Up to 30 days before the conference, in full. After that, pass your ticket to a colleague instead: write under your registration.'), lang('refund, cancel, money back'), 'ev-ticket-faq'),
                            $faq(3, lang('Do I still need to register after buying?'), lang('Yes, once for each attendee, with the e-mail the pass was bought with, so we can print the badges.'), lang('register, badge, attendee, after buying'), 'ev-ticket-faq'),
                            $faq(4, lang('Are there student or non-profit prices?'), lang('A few places at half price for students and non-profits. Write to us before buying.'), lang('student, discount, non-profit, NGO, cheaper'), 'ev-ticket-faq'),
                        ), array('id' => 'ev-ticket-faq')),
                    ), '', '8'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
        $shared('register_band'),
    )),
);

// The shop's own pages: a pass's page, the cart, the checkout and the order.
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
$ticket_pages[] = array_merge($shop_page('product', lang('product-detail'), lang('Product'), 'product_detail'),
    array('search' => true, 'sitemap' => true, 'noindex' => false, 'meta_description' => lang('Product details, options and price.')));
$ticket_pages[] = $shop_page('cart', lang('cart'), lang('Shopping Cart'), 'cart');
$ticket_pages[] = $shop_page('checkout', lang('checkout'), lang('Checkout'), 'checkout');
$ticket_pages[] = $shop_page('order', lang('order-summary'), lang('Order Summary'), 'order_view');

// The order receipt, sent to the billing address when an order is placed.
$ticket_pages[] = array_merge($mail_settings, array(
    'key'      => 'email_order',
    'requires' => 'ecommerce',
    'name'     => lang('email-order-receipt'),
    'title'    => lang('Order Receipt E-mail'),
    'tree'     => $mail_page(array($widget('email_order'))),
));

// ── Widgets ─────────────────────────────────────────────────────────────

// A Bootstrap button component: the pass card's Add to cart, which the
// listing wraps in the form that adds the product.
$button = function ($text, $variant, $class = '', $extra = array()) {
    return array('type' => 'component', 'props' => array_merge(array('componentType' => 'btn', 'btnElement' => 'button', 'btnType' => 'submit',
        'variant' => $variant, 'text' => $text, 'cssClass' => $class), $extra), 'children' => array());
};

// A line of the receipt's totals, shown while its flag holds.
$total_row = function ($name, $label, $token, $flag = '', $class = '', $style = 'text-align:right') use ($el, $span, $bind, $show_when, $mail_style) {
    return $el('tr', $class, $name, array(
        $el('td', 'ps-0' . ($class === '' ? ' text-body-secondary' : ''), lang('Label'), array($span($label, '', lang('Text')))),
        $el('td', 'pe-0 text-end', lang('Value'), array($span(lang('$249.00'), '', lang('Amount'), $bind($token))), $mail_style($style)),
    ), $flag !== '' ? $show_when($flag) : array());
};

$ticket_widgets = array(

    // The passes on sale: the Tickets group's products, each a card with
    // its name (the link to its page), its price and Add to cart. The
    // notice after an add links this shop's cart.
    'ticket_passes' => array(
        'page'     => 'tickets',
        'requires' => 'ecommerce',
        'slug'     => lang('catalog'),
        'config'   => array(
            'regionType'               => 'catalog_listing',
            'product_group_id'         => '{{product_group:tickets}}',
            'group_navigation'         => 'none',
            'items_per_page'           => 12,
            'order_by_field'           => 'sort_order',
            'order_by_direction'       => 'ASC',
            'detail_page_id'           => '{{tab:product}}',
            'add_to_cart_stay_on_page' => true,
            'add_to_cart_next_page_id' => '{{tab:cart}}',
            'empty_message'            => lang('No passes on sale right now.'),
        ),
        'tree'     => $widget_root(array(
            $el('section', 'py-5 border-top', lang('Buy Online'), array(
                $container(array(
                    $section_header(lang('Buy online'), lang('Passes in the shop'), lang('Your cart'), '{{page:cart}}'),
                    $row(array($loop(array($col(array(
                        $el('div', 'card h-100 border-0 shadow-sm rounded-4', lang('Pass Card'), array(
                            $el('div', 'card-body d-flex align-items-center gap-3 p-4', lang('Card Body'), array(
                                $icon_tile('bi-ticket-perforated'),
                                $el('div', 'flex-grow-1', lang('Words'), array(
                                    $el('h3', 'h6 fw-bold mb-1 position-static', lang('Product Name'), array(
                                        $link(lang('Conference Pass'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                                            array('_bindings' => array('text' => '__short_description', 'href' => '__detail_url'))),
                                    )),
                                    $span(lang('$249.00'), 'fw-semibold text-primary', lang('Price'), $bind('__price_formatted')),
                                )),
                                $button(lang('Add to cart'), 'primary', 'position-relative z-2 text-nowrap',
                                    array('size' => 'sm', '_bindings' => array('action' => 'catalog_add_to_cart'))),
                            )),
                        )),
                    ), '6', '4')))), '4'),
                )),
            ), array('id' => 'buy-online')),
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
            'catalog_listing_page_id' => '{{tab:tickets}}',
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
            'cart_section_label'          => lang('Your passes'),
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

    // The receipt: the order's number and date, its passes, the totals,
    // the billing address, and the reminder to register each attendee.
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
                                $span(lang('Conference Pass'), 'fw-semibold', lang('Product Name'), $bind('__item_short_description')),
                                $span(' × ', 'text-body-secondary', lang('Text')),
                                $span('1', '', lang('Quantity'), $bind('__item_qty')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #e0e7ff')),
                            $el('td', 'pe-0 text-end', lang('Total'), array(
                                $span(lang('$249.00'), '', lang('Amount'), $bind('__item_total')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #e0e7ff;text-align:right')),
                        )),
                    )),
                )),
            ), $mail_style('width:100%;border-collapse:collapse;margin:0 0 16px')),
            $el('table', 'table table-sm mb-4', lang('Totals'), array(
                $el('tbody', '', lang('Rows'), array(
                    $total_row(lang('Subtotal'), lang('Subtotal'), '__order_subtotal'),
                    $total_row(lang('Discount'), lang('Discount'), '__order_discount', 'has_discount'),
                    $total_row(lang('Tax'), lang('VAT'), '__order_tax', 'has_tax'),
                    $total_row(lang('Total'), lang('Total'), '__order_total', '', 'fw-bold', 'text-align:right;font-weight:700'),
                )),
            ), $mail_style('width:100%;border-collapse:collapse;margin:0 0 24px')),
            $el('div', '', lang('Billing Address'), array(
                $el('p', 'small text-body-secondary mb-1', lang('Label'), array(), array('text' => lang('Billing Address'))),
                $el('div', '', lang('Address'), array(), array_merge(array('text' => $venue['address']), $bind('__billing_address_html'))),
            ), $mail_style('margin:0 0 16px')),
            $mail_box(array(
                $el('p', 'fw-semibold mb-1', lang('Title'), array(), array_merge(array('text' => lang('One more step: register each attendee')), $mail_style('font-weight:600;margin:0 0 4px'))),
                $mail_para(lang('Fill in the registration form once for each pass, with the e-mail it was bought with, so we can print the badges.'), 'mb-0', 'margin:0'),
            ), lang('Next Step')),
            $mail_button(lang('Register attendees'), '{{page:registration}}'),
            $mail_para(lang('Your order and its receipt are also on the order page:'), 'small mb-1', 'margin:0 0 4px;font-size:13px'),
            $link(lang('View your order'), '{{page:order}}?order_id=^^__order_id^^', 'small', array($attr('style', 'font-size:13px;color:#4338ca'))),
        )),
    ),
);

return array('pages' => $ticket_pages, 'widgets' => $ticket_widgets, 'catalog' => $catalog);
