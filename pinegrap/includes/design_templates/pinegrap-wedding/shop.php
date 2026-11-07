<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Wedding Planner and Venue", its shop: the catalog (the
 * Deposits and Gift Cards group: the date-hold deposit of each venue
 * package of $packages and the gift cards of $gift_cards), the shop page,
 * the product, cart, checkout and order pages and the order receipt
 * e-mail. Included after parts.php and mail.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ..., 'catalog' => ...).
 *
 * Every page and widget here needs the shop: the shared parts and the
 * record pages never link them, so a site without the shop has none of
 * them and no dead link.
 *
 * The prices are parts.php's own ($deposit, $gift_cards): the cards write
 * them with $money(), the products are made from the same figures, so the
 * page and the cart show one amount. A deposit is paid once the couple has
 * agreed to the proposal written under their enquiry, and a gift card is
 * sent by e-mail, so the products are not shippable and the checkout asks
 * for no delivery address.
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
// Deposits and Gift Cards group under the site's top group, each product
// found again by its SKU. A deposit per venue package first, then the gift
// cards.
$shop_products = array();
foreach ($packages as $key => $p) {
    $shop_products[] = array(
        'sku'         => 'WEDDING-DEPOSIT-' . strtoupper($key),
        'title'       => sprintf(lang('Date-Hold Deposit: %s'), $p['title']),
        'description' => sprintf(lang('The deposit that holds your wedding date with the %s package, once you have agreed to our proposal.'), $p['title']),
        'price'       => $deposit,
        'shippable'   => false,
        'featured'    => !empty($p['featured']),
        'keywords'    => lang('deposit, wedding date, venue, package, wedding'),
    );
}
foreach ($gift_cards as $key => $g) {
    $shop_products[] = array(
        'sku'         => $g['sku'],
        'title'       => $g['title'],
        'description' => $g['text'],
        'price'       => $g['price'],
        'shippable'   => false,
        'featured'    => false,
        'keywords'    => lang('gift card, gift, wedding, anniversary, dinner'),
    );
}
$sample_deposit_title = $shop_products[0]['title'];
$catalog = array(
    'folder'  => 'pinegrap_wedding_catalog',
    // The Details tab of every product's page.
    'details' => array(
        lang('A deposit is paid once you have agreed to our proposal; the rest is paid in parts before the wedding.'),
        lang('A gift card is sent by e-mail within one working day of the order, with its code.'),
        lang('A sample product of the template: change its name and price in the catalog, or remove it.'),
    ),
    'groups'  => array(
        'wedding_shop' => array(
            'parent'      => 'root',
            'title'       => lang('Deposits and Gift Cards'),
            'description' => lang('The deposit that holds your wedding date, and gift cards for a couple you love.'),
            'products'    => $shop_products,
        ),
    ),
);

// ── Pages ───────────────────────────────────────────────────────────────

// How paying works, in three steps.
$shop_steps = array(
    $step(1, lang('Agree to the proposal'), lang('After the consultation we write the proposal under your enquiry, with the date, the package and the price.')),
    $step(2, lang('Pay the deposit'), sprintf(lang('%s for the package of your proposal, at the checkout as for any order; your date is then yours.'), $money($deposit))),
    $step(3, lang('The rest before the wedding'), lang('The balance is paid in parts before the wedding; we write to you under your enquiry.')),
);

$shop_pages[] = array(
    'key'              => 'shop',
    'requires'         => 'ecommerce',
    'name'             => lang('deposits-and-gift-cards'),
    'folder'           => 'public',
    'title'            => lang('Deposits and Gift Cards'),
    'meta_description' => lang('Pay the date-hold deposit of your agreed proposal, or give a wedding gift card sent by e-mail.'),
    'tree'             => $page(array(
        $page_header(lang('Deposits and Gift Cards'), lang('The deposit that holds your wedding date once the proposal is agreed, and gift cards for a couple you love.')),
        $el('section', 'py-5', lang('Products'), array(
            $container(array($widget('shop_catalog'))),
        ), $searchable('wd-shop', lang('deposit, pay, gift card, present, voucher, anniversary'))),
        $el('section', 'py-5 bg-body-tertiary', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $section_header(lang('Holding your date'), lang('How it works')),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), $shop_steps),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body border', lang('Box'), array(
                            $heading('h3', lang('Pay a deposit only once the proposal is agreed'), 'pwd-display h5 mb-2'),
                            $para(lang('A deposit holds a date we have offered you in a proposal. Not that far yet? Send an enquiry first; a gift card can be named in it.'), 'text-body-secondary mb-3'),
                            $link(lang('Send an Enquiry'), '{{page:enquiry}}', 'btn btn-primary rounded-pill px-4'),
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

// A Bootstrap button component: a product card's Add to cart, which the
// listing wraps in the form that adds the product.
$shop_button = function ($text, $variant, $class = '', $extra = array()) {
    return array('type' => 'component', 'props' => array_merge(array('componentType' => 'btn', 'btnElement' => 'button', 'btnType' => 'submit',
        'variant' => $variant, 'text' => $text, 'cssClass' => $class), $extra), 'children' => array());
};

// A product's card of the listing: a heart (the same for every product the
// listing repeats it for), its name (the link to its page), its price and
// Add to cart.
$shop_card = function ($v) use ($el, $link, $span, $col, $bind, $icon_tile, $shop_button, $money) {
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 pwd-lift', lang('Product Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile('bi-balloon-heart', 'align-self-start mb-3'),
                $el('h3', 'pwd-display h5 mb-2 position-static', lang('Product Name'), array(
                    $link($v['title'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => '__short_description', 'href' => '__detail_url'))),
                )),
                $el('div', 'd-flex align-items-center justify-content-between gap-3 mt-auto pt-3', lang('Buy Line'), array(
                    $span($money($v['price']), 'pwd-price fw-semibold fs-5', lang('Price'), $bind('__price_formatted')),
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
        $el('td', 'pe-0 text-end', lang('Value'), array($span('$1,000.00', '', lang('Amount'), $bind($token))), $mail_style($style)),
    ), $flag !== '' ? $show_when($flag) : array());
};

$shop_widgets = array(

    // The products on sale: the Deposits and Gift Cards group's products,
    // every one on one page. The notice after an add links this shop's
    // cart.
    'shop_catalog' => array(
        'page'     => 'shop',
        'requires' => 'ecommerce',
        'slug'     => lang('catalog'),
        'config'   => array(
            'regionType'               => 'catalog_listing',
            'product_group_id'         => '{{product_group:wedding_shop}}',
            'group_navigation'         => 'none',
            'items_per_page'           => 24,
            'order_by_field'           => 'sort_order',
            'order_by_direction'       => 'ASC',
            'detail_page_id'           => '{{tab:product}}',
            'add_to_cart_stay_on_page' => true,
            'add_to_cart_next_page_id' => '{{tab:cart}}',
            'empty_message'            => lang('No deposits or gift cards on sale right now.'),
        ),
        'tree'     => $widget_root(array(
            $row(array($loop(array($shop_card($shop_products[0])))), '4'),
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
            'catalog_root_label'      => lang('Deposits and Gift Cards'),
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

    // The receipt: the order's number and date, what was bought, the
    // totals, the billing address, what a deposit and a gift card do next
    // and the way to the couple's enquiries.
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
                                $span($sample_deposit_title, 'fw-semibold', lang('Product Name'), $bind('__item_short_description')),
                                $span(' × ', 'text-body-secondary', lang('Text')),
                                $span('1', '', lang('Quantity'), $bind('__item_qty')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #dfe7e0')),
                            $el('td', 'pe-0 text-end', lang('Total'), array(
                                $span('$1,000.00', '', lang('Amount'), $bind('__item_total')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #dfe7e0;text-align:right')),
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
                $el('div', '', lang('Address'), array(), array_merge(array('text' => lang('Selin Aydın')), $bind('__billing_address_html'))),
            ), $mail_style('margin:0 0 16px')),
            $mail_box(array(
                $el('p', 'fw-semibold mb-1', lang('Title'), array(), array_merge(array('text' => lang('What happens next')), $mail_style('font-weight:600;margin:0 0 4px'))),
                $mail_para(lang('A deposit holds the wedding date of the proposal we agreed under your enquiry; we confirm it there. A gift card comes to this address within one working day, with its code.'), 'mb-0', 'margin:0'),
            ), lang('Next Step')),
            $mail_button(lang('My Enquiries'), '{{page:my_enquiries}}'),
            $mail_para(lang('Your order and its receipt are also on the order page:'), 'small mb-1', 'margin:0 0 4px;font-size:13px'),
            $link(lang('View your order'), '{{page:order}}?order_id=^^__order_id^^', 'small', array($attr('style', 'font-size:13px;color:#3f5a46'))),
        )),
    ),
);

return array('pages' => $shop_pages, 'widgets' => $shop_widgets, 'catalog' => $catalog);
