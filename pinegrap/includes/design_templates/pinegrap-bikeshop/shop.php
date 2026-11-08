<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Bike Shop", its shop: the catalog (the Bikes, Parts,
 * Accessories and Workshop Vouchers groups of $product_groups, a product for
 * each item of $products, a bike once per frame size), the shop page, the
 * product, cart, checkout and order pages and the order receipt e-mail.
 * Included after parts.php, cards.php and mail.php in the same scope;
 * returns array('pages' => ..., 'widgets' => ..., 'catalog' => ...).
 *
 * Every page and widget here needs the shop: the header and the footer
 * never link them and the home links the shop page only with the shop on,
 * so a site without the shop has none of them and no dead link.
 *
 * The prices are parts.php's own ($product_prices): the cards write them
 * with $money(), the products are made from the same figures, so the page
 * and the cart show one amount. A bike is built, checked and collected in
 * the shop and a voucher comes by e-mail, so neither is shipped; parts and
 * accessories are posted, and the checkout asks for a delivery address
 * when the cart holds one. A bike in another frame size is another product
 * (the store keeps one row per size; a template's catalog makes no variant
 * set), named "<bike>, <size>" with its own code.
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
// Made once when the template is first opened (_pg_tpl_catalog()): four
// groups under the site's top group, each product found again by its SKU,
// in the order of the data. A bike's code ends with its frame size
// (BKS-BIKE-TARMAC-M); its words say the riders the size fits.
$shop_keywords = array(
    'bikes'       => lang('bike, bicycle, road bike, gravel bike, mountain bike, e-bike, frame size'),
    'parts'       => lang('spare parts, inner tube, chain, brake pads, tyre'),
    'accessories' => lang('accessories, helmet, bike lights, lock, pump'),
    'vouchers'    => lang('workshop voucher, gift, present, service, bike fit'),
);
$shop_groups = array();
foreach ($product_groups as $group_key => $g) {
    $shop_groups[$group_key] = array();
}
foreach ($products as $key => $p) {
    $group_key = $p['group'];
    $item = array(
        'title'       => $p['title'],
        'description' => $p['summary'] . ' ' . implode('. ', $p['details']) . '.',
        'price'       => $product_prices[$key],
        'shippable'   => $product_groups[$group_key]['shippable'],
        'keywords'    => $shop_keywords[$group_key],
    );
    if ($group_key !== 'bikes') {
        $shop_groups[$group_key][] = array_merge($item, array('sku' => $product_sku($key), 'featured' => false));
        continue;
    }
    foreach ($frame_sizes as $size => $riders) {
        $shop_groups[$group_key][] = array_merge($item, array(
            'sku'         => $product_sku($key) . '-' . $size,
            'title'       => sprintf(lang('%1$s, size %2$s'), $p['title'], $size),
            'description' => $item['description'] . ' ' . sprintf(lang('Frame size %1$s: %2$s.'), $size, $riders),
            // The newest bikes of the home, in the middle size.
            'featured'    => !empty($p['featured']) && $size === 'M',
        ));
    }
}
$shop_group_texts = array(
    'bikes'       => lang('Road, gravel, mountain and electric bikes in four frame sizes, built and checked in the workshop and collected in the shop.'),
    'parts'       => lang('Tubes, chains, brake pads and tyres for the repairs you do at home, posted to your door.'),
    'accessories' => lang('Helmets, lights, locks and pumps, posted to your door.'),
    'vouchers'    => lang('A service or a bike fit to give, sent by e-mail and booked when it suits them.'),
);
$catalog_groups = array();
foreach ($product_groups as $group_key => $g) {
    $catalog_groups[$group_key] = array(
        'parent'      => 'root',
        'title'       => $g['title'],
        'description' => $shop_group_texts[$group_key],
        'keywords'    => $shop_keywords[$group_key],
        'products'    => $shop_groups[$group_key],
    );
}
$catalog = array(
    'folder'  => 'pinegrap_bikeshop_catalog',
    // The Details tab of every product's page.
    'details' => array(
        lang('Bikes are built and checked in the workshop and collected in the shop: we write when yours is ready, with its first check free within six weeks.'),
        lang('Parts and accessories are posted within two working days; a workshop voucher comes by e-mail within one working day, with its code.'),
        lang('A sample product of the template: change its name and price in the catalog, or remove it.'),
    ),
    'groups'  => $catalog_groups,
);

// ── Pages ───────────────────────────────────────────────────────────────

// How an order goes, in three steps.
$shop_steps = array(
    $step(1, lang('Choose your bike or part'), lang('A bike in your frame size; parts and accessories one by one.')),
    $step(2, lang('Pay online'), lang('The receipt comes by e-mail at once, with the order number.')),
    $step(3, lang('Collect it or have it posted'), lang('A bike is built and checked, then collected in the shop; the rest is posted.')),
);

// A section of the shop page: one group's listing under its header, a
// search section of its own.
$shop_section = function ($group_key, $lead, $widget_key, $topics, $class = '', $more = array()) use ($el, $container, $section_header, $widget, $searchable, $product_groups) {
    return $el('section', trim('py-5 ' . $class), $product_groups[$group_key]['title'], array(
        $container(array_merge(array(
            $section_header($product_groups[$group_key]['title'], $lead),
            $widget($widget_key),
        ), $more)),
    ), $searchable('bikeshop-shop-' . $group_key, $topics));
};

// The shop: the bikes with the way to the frame sizes, the parts, the
// accessories and the vouchers, each a listing of its group, then how an
// order goes beside the workshop and the fit.
$shop_pages[] = array(
    'key'              => 'shop',
    'requires'         => 'ecommerce',
    'name'             => lang('shop'),
    'folder'           => 'public',
    'title'            => lang('Bikes and Parts'),
    'meta_description' => lang('Buy a road, gravel, mountain or electric bike in your frame size, parts, accessories and workshop vouchers online.'),
    'tree'             => $page(array(
        $page_header(lang('Bikes and Parts'), lang('Bikes built and checked by our mechanics, the parts we fit every day and the kit to ride with.')),
        $shop_section('bikes', lang('Every bike in four frame sizes: pick the size the chart gives you.'), 'bike_catalog',
            lang('buy bike, road bike, gravel bike, mountain bike, e-bike, frame size, price'), '', array(
                $el('p', 'd-flex flex-wrap align-items-center gap-3 mt-4 mb-0', lang('Size Note'), array(
                    $span(lang('Not sure of your size?'), 'text-body-secondary', lang('Text')),
                    $link(lang('Find Your Frame Size'), '{{page:sizes}}', 'btn btn-sm btn-outline-dark rounded-0'),
                    $link(lang('Ask for a Bike Fit'), '{{page:fit_request}}', 'btn btn-sm btn-outline-dark rounded-0'),
                )),
            )),
        $shop_section('parts', lang('The parts our mechanics fit every day.'), 'part_catalog',
            lang('spare parts, inner tube, chain, brake pads, tyre'), 'bg-body-tertiary'),
        $shop_section('accessories', lang('Helmets, lights, locks and pumps.'), 'accessory_catalog',
            lang('helmet, bike lights, lock, pump'), ''),
        $shop_section('vouchers', lang('A service or a fit to give.'), 'voucher_catalog',
            lang('workshop voucher, gift, present'), 'bg-body-tertiary'),
        $el('section', 'py-5', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $section_header(lang('Ordering online')),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), $shop_steps),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'p-4 bg-body-tertiary border-top border-4 border-dark mb-4', lang('Box'), array(
                            $heading('h3', lang('Your bike needs work, not a new one?'), 'pbs-display h5 text-uppercase mb-2'),
                            $para(lang('A repair or a service is asked for, not bought: send a service request and the workshop writes back with a date and a quote.'), 'text-body-secondary mb-3'),
                            $link(lang('Request a Service'), '{{page:service_request}}', 'btn btn-primary rounded-0 px-4 me-2 mb-2'),
                            $link(lang('Workshop Prices'), '{{page:workshop}}', 'btn btn-outline-dark rounded-0 px-4 mb-2'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('bikeshop-shop-how', lang('collection, delivery, postage, order online, receipt'))),
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
        'tree'     => $page(array($widget($widget_key))),
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

// A product's card of a listing: the group's steel tile, its name (the
// link to its page), the sample's words, its price and Add to cart, on a
// graphite edge like the workshop's job cards.
$shop_card = function ($group_key, $title, $summary, $price, $lg) use ($el, $link, $span, $para, $col, $bind, $icon_tile, $shop_button, $product_groups) {
    return $col(array(
        $el('article', 'card h-100 rounded-0 border-0 border-top border-4 border-dark shadow-sm', lang('Product Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile($product_groups[$group_key]['icon'], 'align-self-start mb-3'),
                $el('h3', 'pbs-display h5 text-uppercase mb-1 position-static', lang('Product Name'), array(
                    $link($title, '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => '__short_description', 'href' => '__detail_url'))),
                )),
                $para($summary, 'small text-body-secondary mb-0'),
                $el('div', 'd-flex align-items-center justify-content-between gap-3 mt-auto pt-3', lang('Buy Line'), array(
                    $span($price, 'pbs-price pg-bike-num fw-bold fs-5', lang('Price'), $bind('__price_formatted')),
                    $shop_button(lang('Add to cart'), 'primary', 'rounded-0 position-relative z-2 text-nowrap',
                        array('size' => 'sm', '_bindings' => array('action' => 'catalog_add_to_cart'))),
                )),
            )),
        )),
    ), '6', $lg);
};

// The listing of one group: every product on one page, the notice after an
// add names this shop's cart. The sample card is the group's first item.
$shop_listing = function ($slug, $group_key, $empty, $lg) use ($widget_root, $row, $loop, $shop_card, $products, $product_prices, $money, $frame_sizes) {
    $first = '';
    foreach ($products as $key => $p) {
        if ($p['group'] === $group_key) {
            $first = $key;
            break;
        }
    }
    $title = $products[$first]['title'];
    if ($group_key === 'bikes') {
        $sizes = array_keys($frame_sizes);
        $title = sprintf(lang('%1$s, size %2$s'), $title, $sizes[1]);
    }
    return array(
        'page'     => 'shop',
        'requires' => 'ecommerce',
        'slug'     => $slug,
        'config'   => array(
            'regionType'               => 'catalog_listing',
            'product_group_id'         => '{{product_group:' . $group_key . '}}',
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
            $row(array($loop(array($shop_card($group_key, $title, $products[$first]['summary'], $money($product_prices[$first]), $lg)))), '4'),
        )),
    );
};

// A line of the receipt's totals, shown while its flag holds.
$shop_total_row = function ($name, $label, $token, $flag = '', $class = '', $style = 'text-align:right') use ($el, $span, $bind, $show_when, $mail_style) {
    return $el('tr', $class, $name, array(
        $el('td', 'ps-0' . ($class === '' ? ' text-body-secondary' : ''), lang('Label'), array($span($label, '', lang('Text')))),
        $el('td', 'pe-0 text-end', lang('Value'), array($span('$38.00', '', lang('Amount'), $bind($token))), $mail_style($style)),
    ), $flag !== '' ? $show_when($flag) : array());
};

$shop_widgets = array(

    // The four groups on sale on the shop page.
    'bike_catalog'      => $shop_listing(lang('bike-catalog'), 'bikes', lang('No bikes on sale online right now: come and see them in the shop.'), '3'),
    'part_catalog'      => $shop_listing(lang('part-catalog'), 'parts', lang('No parts on sale online right now: ask at the workshop.'), '3'),
    'accessory_catalog' => $shop_listing(lang('accessory-catalog'), 'accessories', lang('No accessories on sale online right now: ask in the shop.'), '3'),
    'voucher_catalog'   => $shop_listing(lang('voucher-catalog'), 'vouchers', lang('No vouchers on sale online right now: ask in the shop.'), '4'),

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
            'catalog_root_label'      => lang('Bikes and Parts'),
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

    // The receipt: the order's number and date, its products, the totals
    // with the postage, the billing address, the delivery address when a
    // part is posted, what comes next for a bike, a parcel and a voucher,
    // and the way to the order.
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
                                $span($products['chain']['title'], 'fw-semibold', lang('Product Name'), $bind('__item_short_description')),
                                $span(' × ', 'text-body-secondary', lang('Text')),
                                $span('1', '', lang('Quantity'), $bind('__item_qty')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #d5dbe1')),
                            $el('td', 'pe-0 text-end', lang('Total'), array(
                                $span($money($product_prices['chain']), '', lang('Amount'), $bind('__item_total')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #d5dbe1;text-align:right')),
                        )),
                    )),
                )),
            ), $mail_style('width:100%;border-collapse:collapse;margin:0 0 16px')),
            $el('table', 'table table-sm mb-4', lang('Totals'), array(
                $el('tbody', '', lang('Rows'), array(
                    $shop_total_row(lang('Subtotal'), lang('Subtotal'), '__order_subtotal'),
                    $shop_total_row(lang('Discount'), lang('Discount'), '__order_discount', 'has_discount'),
                    $shop_total_row(lang('Shipping'), lang('Shipping'), '__order_shipping', 'has_shipping'),
                    $shop_total_row(lang('Tax'), lang('VAT'), '__order_tax', 'has_tax'),
                    $shop_total_row(lang('Total'), lang('Total'), '__order_total', '', 'fw-bold', 'text-align:right;font-weight:700'),
                )),
            ), $mail_style('width:100%;border-collapse:collapse;margin:0 0 24px')),
            $el('div', '', lang('Billing Address'), array(
                $el('p', 'small text-body-secondary mb-1', lang('Label'), array(), array('text' => lang('Billing Address'))),
                $el('div', '', lang('Address'), array(), array_merge(array('text' => lang('Jane Cooper')), $bind('__billing_address_html'))),
            ), $mail_style('margin:0 0 16px')),
            $el('div', '', lang('Shipping Address'), array(
                $el('p', 'small text-body-secondary mb-1', lang('Label'), array(), array('text' => lang('Shipping Address'))),
                $el('div', '', lang('Address'), array(), array_merge(array('text' => lang('Jane Cooper')), $bind('__shipping_address_html'))),
            ), array_merge($mail_style('margin:0 0 16px'), $show_when('has_shipping'))),
            $mail_box(array(
                $el('p', 'fw-semibold mb-1', lang('Title'), array(), array_merge(array('text' => lang('What happens next')), $mail_style('font-weight:600;margin:0 0 4px'))),
                $mail_para(lang('A bike is built and checked in the workshop: we write when it is ready to collect, and its first check within six weeks is free. Parts and accessories are posted within two working days. A workshop voucher comes by e-mail within one working day, with its code.'), 'mb-0', 'margin:0'),
            ), lang('Next Step')),
            $mail_button(lang('View your order'), '{{page:order}}?order_id=^^__order_id^^'),
        )),
    ),
);

return array('pages' => $shop_pages, 'widgets' => $shop_widgets, 'catalog' => $catalog);
