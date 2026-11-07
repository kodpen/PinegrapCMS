<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Music Artist", its merch shop: the catalog (the Band
 * Merch group with the shirts, the records, a signed vinyl of every
 * release and the poster, and the Gift Cards group), the merch page, the
 * product, cart, checkout and order pages and the order receipt e-mail.
 * Included after parts.php and mail.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ..., 'catalog' => ...).
 *
 * Every page and widget here needs the shop: the shared parts never link
 * them, so a site without the shop has none of them and no dead link.
 *
 * The prices are parts.php's own ($merch, $gift_cards): the cards write
 * them with $money(), the products are made from the same figures, so the
 * page and the cart show one amount. Merch is posted, so its products are
 * shippable and the checkout asks for a delivery address; a gift card is
 * sent by e-mail and is not shippable.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$merch_pages = array();

// ── The catalog ─────────────────────────────────────────────────────────
// Made once when the template is first opened (_pg_tpl_catalog()): the
// Band Merch and Gift Cards groups under the site's top group, each
// product found again by its SKU.

// A signed vinyl of every release the merch list does not already sell on
// vinyl: an album on a double record, an EP, a single or a live record on
// one.
$vinyl_prices = array(
    'album'  => array('USD' => 32, 'EUR' => 30, 'GBP' => 26, 'TRY' => 1100),
    'ep'     => array('USD' => 22, 'EUR' => 20, 'GBP' => 18, 'TRY' => 750),
    'single' => array('USD' => 15, 'EUR' => 14, 'GBP' => 12, 'TRY' => 520),
    'live'   => array('USD' => 32, 'EUR' => 30, 'GBP' => 26, 'TRY' => 1100),
);
$merch_skus = array();
foreach ($merch as $m) $merch_skus[$m['sku']] = true;
$signed_vinyls = array();
foreach ($releases as $key => $r) {
    $sku = 'MUSIC-VINYL-' . strtoupper($key);
    if (isset($merch_skus[$sku])) continue;
    $signed_vinyls[$key] = array(
        'sku'   => $sku,
        'icon'  => 'bi-vinyl',
        'title' => sprintf(lang('%s on Vinyl, Signed'), $r['title']),
        'text'  => sprintf(lang('%1$s on vinyl, signed by all four of us. %2$s'), $formats[$r['format']]['title'], $r['summary']),
        'price' => $vinyl_prices[$r['format']],
    );
}

// Everything the merch page sells, in its order: the merch of parts.php,
// then the signed vinyls.
$merch_items = $merch + $signed_vinyls;

$merch_products = array();
foreach ($merch_items as $key => $m) {
    $merch_products[] = array(
        'sku'         => $m['sku'],
        'title'       => $m['title'],
        'description' => $m['text'],
        'price'       => $m['price'],
        'featured'    => in_array($key, array('shirt', 'vinyl', 'poster'), true),
        'keywords'    => lang('merch, band shirt, vinyl, record, poster, tote bag'),
    );
}
$gift_card_products = array();
foreach ($gift_cards as $key => $g) {
    $gift_card_products[] = array(
        'sku'         => $g['sku'],
        'title'       => $g['title'],
        'description' => $g['text'],
        'price'       => $g['price'],
        'shippable'   => false,
        'featured'    => false,
        'keywords'    => lang('gift card, gift, present, merch'),
    );
}
$catalog = array(
    'folder'  => 'pinegrap_music_catalog',
    // The Details tab of every product's page.
    'details' => array(
        lang('Merch is posted within three working days of the order; a gift card is sent by e-mail within one working day, with its code.'),
        lang('A signed record is signed by all four of us before it is packed.'),
        lang('A sample product of the template: change its name and price in the catalog, or remove it.'),
    ),
    'groups'  => array(
        'merch' => array(
            'parent'      => 'root',
            'title'       => lang('Band Merch'),
            'description' => lang('Shirts, hoodies, records signed by the band, the tote bag and the tour poster.'),
            'products'    => $merch_products,
        ),
        'gift_cards' => array(
            'parent'      => 'root',
            'title'       => lang('Merch Gift Cards'),
            'description' => lang('Gift cards for anything in the merch shop, sent by e-mail.'),
            'products'    => $gift_card_products,
        ),
    ),
);

// ── Pages ───────────────────────────────────────────────────────────────

// How an order goes, in three steps.
$merch_steps = array(
    $step(1, lang('Choose your merch'), lang('A shirt in your size, a record signed by the band, a poster from the tour.')),
    $step(2, lang('Order it online'), lang('Pay at the checkout; the receipt comes by e-mail at once.')),
    $step(3, lang('We post it'), lang('Within three working days, packed by the band between two shows.')),
);

$merch_pages[] = array(
    'key'              => 'merch',
    'requires'         => 'ecommerce',
    'name'             => lang('merch'),
    'folder'           => 'public',
    'title'            => lang('Band Merch'),
    'meta_description' => lang('Band shirts, hoodies, signed records, the tote bag and the tour poster, posted within three working days, and gift cards.'),
    'tree'             => $page(array(
        $page_header(lang('Band Merch'), lang('Shirts, records signed by the four of us, the tote bag and the tour poster. Every order helps the van reach the next town.')),
        $el('section', 'py-5', lang('Merch'), array(
            $container(array($widget('merch_catalog'))),
        ), $searchable('mu-merch', lang('merch, t-shirt, hoodie, vinyl, record, cassette, poster, tote bag'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Gift Cards'), array(
            $container(array(
                $section_header(lang('Gift cards'), lang('A gift card for anything in the shop')),
                $widget('gift_card_catalog'),
            )),
        ), $searchable('mu-gift-cards', lang('gift card, gift, present, voucher'))),
        $el('section', 'py-5', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $section_header(lang('Ordering'), lang('How it works')),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), $merch_steps),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('Box'), array(
                            $heading('h3', lang('At the shows'), 'pmu-display h5 mb-2'),
                            $para(lang('The merch table is open at every show, before and after the set. Order online and say so in the order notes, and we bring it to the show of your choice.'), 'text-body-secondary mb-3'),
                            $link(lang('Tour Dates'), '{{page:tour}}', 'btn btn-primary rounded-pill px-4'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    )),
);

// The shop's own pages: a product's page, the cart, the checkout and the
// order.
$merch_shop_page = function ($key, $name, $title, $widget_key) use ($page, $widget) {
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
$merch_pages[] = array_merge($merch_shop_page('product', lang('product-detail'), lang('Product'), 'product_detail'),
    array('search' => true, 'sitemap' => true, 'noindex' => false, 'meta_description' => lang('Product details, options and price.')));
$merch_pages[] = $merch_shop_page('cart', lang('cart'), lang('Shopping Cart'), 'cart');
$merch_pages[] = $merch_shop_page('checkout', lang('checkout'), lang('Checkout'), 'checkout');
$merch_pages[] = $merch_shop_page('order', lang('order-summary'), lang('Order Summary'), 'order_view');

// The order receipt, sent to the billing address when an order is placed.
$merch_pages[] = array_merge($mail_settings, array(
    'key'      => 'email_order',
    'requires' => 'ecommerce',
    'name'     => lang('email-order-receipt'),
    'title'    => lang('Order Receipt E-mail'),
    'tree'     => $mail_page(array($widget('email_order'))),
));

// ── Widgets ─────────────────────────────────────────────────────────────

// A Bootstrap button component: a card's Add to cart, which the listing
// wraps in the form that adds the product.
$merch_button = function ($text, $variant, $class = '', $extra = array()) {
    return array('type' => 'component', 'props' => array_merge(array('componentType' => 'btn', 'btnElement' => 'button', 'btnType' => 'submit',
        'variant' => $variant, 'text' => $text, 'cssClass' => $class), $extra), 'children' => array());
};

// A product's card of the listings: its icon, its name (the link to its
// page), its price and Add to cart.
$merch_card = function ($m) use ($el, $link, $span, $col, $bind, $icon_tile, $merch_button, $money) {
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 pmu-lift', lang('Product Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile($m['icon'], 'align-self-start mb-3'),
                $el('h3', 'pmu-display h5 mb-2 position-static', lang('Product Name'), array(
                    $link($m['title'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => '__short_description', 'href' => '__detail_url'))),
                )),
                $el('div', 'd-flex align-items-center justify-content-between gap-3 mt-auto pt-3', lang('Buy Line'), array(
                    $span($money($m['price']), 'fw-semibold fs-5', lang('Price'), $bind('__price_formatted')),
                    $merch_button(lang('Add to cart'), 'primary', 'position-relative z-2 text-nowrap rounded-pill',
                        array('size' => 'sm', '_bindings' => array('action' => 'catalog_add_to_cart'))),
                )),
            )),
        )),
    ), '6', '4');
};

// A listing of one of the template's groups: every product on one page,
// in the catalog's order. The notice after an add links this shop's cart.
$merch_listing = function ($group, $sample, $empty) use ($widget_root, $row, $loop, $merch_card) {
    return array(
        'page'     => 'merch',
        'requires' => 'ecommerce',
        'slug'     => lang('catalog'),
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
            $row(array($loop(array($merch_card($sample)))), '4'),
        )),
    );
};

// A line of the receipt's totals, shown while its flag holds.
$merch_total_row = function ($name, $label, $token, $flag = '', $class = '', $style = 'text-align:right') use ($el, $span, $bind, $show_when, $mail_style) {
    return $el('tr', $class, $name, array(
        $el('td', 'ps-0' . ($class === '' ? ' text-body-secondary' : ''), lang('Label'), array($span($label, '', lang('Text')))),
        $el('td', 'pe-0 text-end', lang('Value'), array($span('$28.00', '', lang('Amount'), $bind($token))), $mail_style($style)),
    ), $flag !== '' ? $show_when($flag) : array());
};

$merch_widgets = array(

    'merch_catalog' => $merch_listing('merch', $merch['shirt'], lang('No merch on sale right now. The table at the shows is always open.')),

    'gift_card_catalog' => $merch_listing('gift_cards', $gift_cards['gift25'], lang('No gift cards on sale right now.')),

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
    // product's breadcrumb starts at the merch page.
    'product_detail' => array(
        'page'     => 'product',
        'requires' => 'ecommerce',
        'slug'     => lang('product-detail'),
        'config'   => array(
            'regionType'              => 'catalog_item_view',
            'product_group_id'        => 0,
            'next_page_id'            => '{{tab:cart}}',
            'catalog_listing_page_id' => '{{tab:merch}}',
            'catalog_root_label'      => lang('Band Merch'),
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

    // The receipt: the order's number and date, its items, the totals with
    // the postage, the delivery and billing addresses, when the parcel
    // leaves and the way to the tour dates.
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
                                $span($merch['shirt']['title'], 'fw-semibold', lang('Product Name'), $bind('__item_short_description')),
                                $span(' × ', 'text-body-secondary', lang('Text')),
                                $span('1', '', lang('Quantity'), $bind('__item_qty')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #bfdbfe')),
                            $el('td', 'pe-0 text-end', lang('Total'), array(
                                $span('$28.00', '', lang('Amount'), $bind('__item_total')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #bfdbfe;text-align:right')),
                        )),
                    )),
                )),
            ), $mail_style('width:100%;border-collapse:collapse;margin:0 0 16px')),
            $el('table', 'table table-sm mb-4', lang('Totals'), array(
                $el('tbody', '', lang('Rows'), array(
                    $merch_total_row(lang('Subtotal'), lang('Subtotal'), '__order_subtotal'),
                    $merch_total_row(lang('Discount'), lang('Discount'), '__order_discount', 'has_discount'),
                    $merch_total_row(lang('Shipping'), lang('Shipping'), '__order_shipping', 'has_shipping_cost'),
                    $merch_total_row(lang('Tax'), lang('VAT'), '__order_tax', 'has_tax'),
                    $merch_total_row(lang('Total'), lang('Total'), '__order_total', '', 'fw-bold', 'text-align:right;font-weight:700'),
                )),
            ), $mail_style('width:100%;border-collapse:collapse;margin:0 0 24px')),
            $el('div', '', lang('Shipping Address'), array(
                $el('p', 'small text-body-secondary mb-1', lang('Label'), array(), array('text' => lang('Shipping Address'))),
                $el('div', '', lang('Address'), array(), array_merge(array('text' => lang('Jane Cooper')), $bind('__shipping_address_html'))),
            ), array_merge($mail_style('margin:0 0 16px'), $show_when('has_shipping'))),
            $el('div', '', lang('Billing Address'), array(
                $el('p', 'small text-body-secondary mb-1', lang('Label'), array(), array('text' => lang('Billing Address'))),
                $el('div', '', lang('Address'), array(), array_merge(array('text' => lang('Jane Cooper')), $bind('__billing_address_html'))),
            ), $mail_style('margin:0 0 16px')),
            $mail_box(array(
                $el('p', 'fw-semibold mb-1', lang('Title'), array(), array_merge(array('text' => lang('What happens next')), $mail_style('font-weight:600;margin:0 0 4px'))),
                $mail_para(lang('We post your merch within three working days and send a gift card to this address within one working day, with its code.'), 'mb-0', 'margin:0'),
            ), lang('Next Step')),
            $mail_para(lang('See you at a show? The tour dates are on our site.'), 'mb-1', 'margin:0 0 4px'),
            $mail_button(lang('Tour Dates'), '{{page:tour}}'),
            $mail_para(lang('Your order and its receipt are also on the order page:'), 'small mb-1', 'margin:0 0 4px;font-size:13px'),
            $link(lang('View your order'), '{{page:order}}?order_id=^^__order_id^^', 'small', array($attr('style', 'font-size:13px;color:#1e3a8a'))),
        )),
    ),
);

return array('pages' => $merch_pages, 'widgets' => $merch_widgets, 'catalog' => $catalog);
