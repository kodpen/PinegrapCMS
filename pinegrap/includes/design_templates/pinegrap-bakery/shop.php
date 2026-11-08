<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Bakery", its pickup shop: the catalog (the Bakery Shop
 * group with the Breads, Pastries, Cakes, Gift Boxes and Baking Classes
 * groups), the shop page, the product, cart, checkout and order pages and
 * the order receipt e-mail. Included after parts.php and mail.php in the
 * same scope; returns array('pages' => ..., 'widgets' => ..., 'catalog' => ...).
 *
 * Every page and widget here needs the shop: the shared parts and the
 * record pages never link them, so a site without the shop has none of
 * them and no dead link.
 *
 * The prices are parts.php's own ($bakes, $cakes, $baking_classes): the
 * pages write them with $money(), the products are made from the same
 * figures, so the page and the cart show one amount. A cake bought here is
 * its smallest size; any other size or words on it go through the cake
 * order. Bakes, cakes and gift boxes are collected: the pickup day is the
 * checkout's requested arrival date and the place a pickup shipping method,
 * both set up by the store in its shipping settings (the template creates
 * none). A class place is never collected (shippable false), so an order
 * of class places alone skips the shipping step.
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

// The gift boxes the shop alone sells: what is in each, with the price of
// one box per currency.
$gift_boxes = array(
    'breakfast' => array('name' => lang('Breakfast Box'),
        'price'   => array('USD' => 28, 'EUR' => 26, 'GBP' => 22, 'TRY' => 950),
        'summary' => lang('A Country Sourdough, four butter croissants, two cardamom buns and a jar of the house jam: breakfast for four.')),
    'afternoon' => array('name' => lang('Afternoon Tea Box'),
        'price'   => array('USD' => 32, 'EUR' => 30, 'GBP' => 26, 'TRY' => 1100),
        'summary' => lang('Two slices each of carrot and dark chocolate cake, two fruit tarts and six brown butter cookies.')),
    'biscuits'  => array('name' => lang('Biscuit Tin'),
        'price'   => array('USD' => 18, 'EUR' => 17, 'GBP' => 14, 'TRY' => 600),
        'summary' => lang('Twelve brown butter cookies in a tin that keeps them for a week: a gift that travels well.')),
);

// ── The catalog ─────────────────────────────────────────────────────────
// Made once when the template is first opened (_pg_tpl_catalog()): the
// Bakery Shop group under the site's top group, then a group per kind of
// product. Each product is found again by its SKU.
$bake_product = function ($key) use ($bakes, $categories, $diet_tags) {
    $b = $bakes[$key];
    $tags = array();
    foreach ($b['tags'] as $t) $tags[] = $diet_tags[$t];
    return array(
        'sku'         => 'BAKERY-' . strtoupper($key),
        'title'       => $b['name'],
        'description' => $b['summary'],
        'price'       => $b['price'],
        'shippable'   => true,
        'featured'    => false,
        'keywords'    => implode(', ', array_merge(array($categories[$b['category']]['title']), $tags)),
    );
};
$bake_group = function ($kinds) use ($bakes, $bake_product) {
    $products = array();
    foreach ($bakes as $key => $b) {
        if (in_array($b['category'], $kinds, true)) $products[] = $bake_product($key);
    }
    return $products;
};

// A celebration cake sold whole, at its smallest size; a tiered cake is
// made to order only.
$cake_products = array();
foreach ($cakes as $key => $c) {
    $size = $c['sizes'][0];
    if ($size === 'tiered') continue;
    $cake_products[] = array(
        'sku'         => 'BAKERY-CAKE-' . strtoupper($key),
        'title'       => sprintf(lang('%1$s, %2$s'), $c['name'], $cake_sizes[$size]['title']),
        'description' => $c['summary'] . ' ' . sprintf(lang('Serves %s.'), $cake_sizes[$size]['servings']),
        'price'       => $c['price'],
        'shippable'   => true,
        'featured'    => true,
        'keywords'    => $occasions[$c['occasion']] . ', ' . lang('celebration cake, whole cake, pickup'),
    );
}
// The whole cakes of the day, beside the slices the counter sells.
$cake_products = array_merge($cake_products, $bake_group(array('cake')));

$box_products = array();
foreach ($gift_boxes as $key => $g) {
    $box_products[] = array(
        'sku'         => 'BAKERY-BOX-' . strtoupper($key),
        'title'       => $g['name'],
        'description' => $g['summary'],
        'price'       => $g['price'],
        'shippable'   => true,
        'featured'    => $key === 'breakfast',
        'keywords'    => lang('gift box, hamper, present, pickup'),
    );
}

// A place in a baking class: nothing to collect, the place is confirmed by
// e-mail.
$class_products = array();
foreach ($baking_classes as $key => $c) {
    $class_products[] = array(
        'sku'         => 'BAKERY-CLASS-' . strtoupper($key),
        'title'       => sprintf(lang('%s, one place'), $c['name']),
        'description' => $c['summary'] . ' ' . lang('We write to agree the class day after the order.'),
        'price'       => $c['price'],
        'shippable'   => false,
        'featured'    => false,
        'keywords'    => $levels[$c['level']] . ', ' . lang('baking class, workshop, gift'),
    );
}

$shop_groups = array(
    'bakery_shop' => array(
        'parent'      => 'root',
        'title'       => lang('Bakery Shop'),
        'description' => lang('Bread, pastries, whole cakes and gift boxes to collect, and places in our baking classes.'),
        'keywords'    => lang('bakery, bread, pastries, cakes, order ahead, pickup'),
    ),
    'bk_breads' => array(
        'parent'      => 'bakery_shop',
        'sort_order'  => 1,
        'title'       => $categories['bread']['title'],
        'description' => $categories['bread']['text'],
        'products'    => $bake_group(array('bread')),
    ),
    'bk_pastries' => array(
        'parent'      => 'bakery_shop',
        'sort_order'  => 2,
        'title'       => $categories['pastry']['title'],
        'description' => $categories['pastry']['text'] . ' ' . $categories['tart']['text'],
        'products'    => $bake_group(array('pastry', 'tart')),
    ),
    'bk_cakes' => array(
        'parent'      => 'bakery_shop',
        'sort_order'  => 3,
        'title'       => lang('Cakes'),
        'description' => lang('Celebration cakes sold whole at their smallest size, and the cakes of the day by the slice.'),
        'products'    => $cake_products,
    ),
    'bk_boxes' => array(
        'parent'      => 'bakery_shop',
        'sort_order'  => 4,
        'title'       => lang('Gift Boxes'),
        'description' => lang('Boxes and tins filled the morning you collect them: a present, or breakfast for the family.'),
        'products'    => $box_products,
    ),
    'bk_classes' => array(
        'parent'      => 'bakery_shop',
        'sort_order'  => 5,
        'title'       => lang('Baking Classes'),
        'description' => lang('A place at the long table, bought for yourself or as a gift.'),
        'products'    => $class_products,
    ),
);
$catalog = array(
    'folder'  => 'pinegrap_bakery_catalog',
    // The Details tab of every product's page.
    'details' => array(
        $pickup['bakes'],
        $pickup['window'],
        lang('A cake is sold at its smallest size; for another size or words on it, send a cake order.'),
        lang('A class place is confirmed by e-mail with the class day; nothing is collected.'),
        lang('A sample product of the template: change its name, price and pictures in the catalog, or remove it.'),
    ),
    'groups'  => $shop_groups,
);
$sample_product = $shop_groups['bk_breads']['products'][0];

// ── Pages ───────────────────────────────────────────────────────────────

// How buying works, in three steps.
$shop_steps = array(
    $step(1, lang('Choose your bakes'), lang('Bread, pastries, a whole cake or a gift box; a class place for yourself or as a present.')),
    $step(2, lang('Choose the day'), lang('At the checkout, pick the day you collect: order by 14:00 for the next day, two days ahead for a cake.')),
    $step(3, lang('Collect at the counter'), lang('Your order waits with your name on it between 10:00 and 18:00; we mail class places with the class day.')),
);

$shop_pages[] = array(
    'key'              => 'shop',
    'requires'         => 'ecommerce',
    'name'             => lang('bakery-shop'),
    'folder'           => 'public',
    'title'            => lang('Bakery Shop'),
    'meta_description' => lang('Order bread, pastries, whole cakes and gift boxes to collect, or a place in a baking class, and choose the pickup day at the checkout.'),
    'tree'             => $page(array(
        $page_header(lang('Bakery Shop'), lang('Order ahead and collect at the counter: pay now and choose the pickup day at the checkout.')),
        $el('section', 'py-5', lang('Products'), array(
            $container(array($widget('shop_catalog'))),
        ), $searchable('bky-shop', lang('buy bread, order online, pickup, click and collect, gift box, class gift'))),
        $el('section', 'py-5 bg-body-tertiary', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $section_header(lang('Order ahead'), lang('How it works')),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), $shop_steps),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body border', lang('Box'), array(
                            $heading('h3', lang('Another size, words on the cake or a design of your own?'), 'pbk-display h5 mb-2'),
                            $para(lang('Send a cake order instead: tell us the occasion and your ideas, and we answer with a design and a price before anything is paid.'), 'text-body-secondary mb-3'),
                            $link(lang('Order a Cake'), '{{page:cake_order}}', 'btn btn-primary rounded-pill px-4'),
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

// A line of the receipt's totals, shown while its flag holds.
$shop_total_row = function ($name, $label, $token, $flag = '', $class = '', $style = 'text-align:right') use ($el, $span, $bind, $show_when, $mail_style) {
    return $el('tr', $class, $name, array(
        $el('td', 'ps-0' . ($class === '' ? ' text-body-secondary' : ''), lang('Label'), array($span($label, '', lang('Text')))),
        $el('td', 'pe-0 text-end', lang('Value'), array($span('$8.00', '', lang('Amount'), $bind($token))), $mail_style($style)),
    ), $flag !== '' ? $show_when($flag) : array());
};

$shop_widgets = array(

    // The products on sale, in the editor's listing layout: the Bakery
    // Shop's groups open in place. The notice after an add links this
    // shop's cart.
    'shop_catalog' => array(
        'page'     => 'shop',
        'requires' => 'ecommerce',
        'slug'     => lang('catalog'),
        'config'   => array(
            'regionType'               => 'catalog_listing',
            'product_group_id'         => '{{product_group:bakery_shop}}',
            'group_navigation'         => 'drill_down',
            'search_enabled'           => true,
            'sort_control_enabled'     => true,
            'price_filter_enabled'     => true,
            'items_per_page'           => 12,
            'order_by_field'           => 'sort_order',
            'order_by_direction'       => 'ASC',
            'detail_page_id'           => '{{tab:product}}',
            'add_to_cart_stay_on_page' => true,
            'add_to_cart_next_page_id' => '{{tab:cart}}',
            'empty_message'            => lang('Nothing on sale here right now.'),
        ),
        'tree'     => 'starter',
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
            'catalog_root_label'      => lang('Bakery Shop'),
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
    // totals, the billing address and the pickup or delivery address the
    // checkout took, what happens next and the way to the order page.
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
                                $span($sample_product['title'], 'fw-semibold', lang('Product Name'), $bind('__item_short_description')),
                                $span(' × ', 'text-body-secondary', lang('Text')),
                                $span('1', '', lang('Quantity'), $bind('__item_qty')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #fecdd3')),
                            $el('td', 'pe-0 text-end', lang('Total'), array(
                                $span($money($sample_product['price']), '', lang('Amount'), $bind('__item_total')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #fecdd3;text-align:right')),
                        )),
                    )),
                )),
            ), $mail_style('width:100%;border-collapse:collapse;margin:0 0 16px')),
            $el('table', 'table table-sm mb-4', lang('Totals'), array(
                $el('tbody', '', lang('Rows'), array(
                    $shop_total_row(lang('Subtotal'), lang('Subtotal'), '__order_subtotal'),
                    $shop_total_row(lang('Discount'), lang('Discount'), '__order_discount', 'has_discount'),
                    $shop_total_row(lang('Pickup or delivery'), lang('Pickup or delivery'), '__order_shipping', 'has_shipping'),
                    $shop_total_row(lang('Tax'), lang('VAT'), '__order_tax', 'has_tax'),
                    $shop_total_row(lang('Total'), lang('Total'), '__order_total', '', 'fw-bold', 'text-align:right;font-weight:700'),
                )),
            ), $mail_style('width:100%;border-collapse:collapse;margin:0 0 24px')),
            $el('div', '', lang('Billing Address'), array(
                $el('p', 'small text-body-secondary mb-1', lang('Label'), array(), array('text' => lang('Billing Address'))),
                $el('div', '', lang('Address'), array(), array_merge(array('text' => lang('Ayşe Kaya')), $bind('__billing_address_html'))),
            ), $mail_style('margin:0 0 16px')),
            $el('div', '', lang('Pickup or delivery'), array(
                $el('p', 'small text-body-secondary mb-1', lang('Label'), array(), array('text' => lang('Pickup or delivery'))),
                $el('div', '', lang('Address'), array(), array_merge(array('text' => lang('Ayşe Kaya')), $bind('__shipping_address_html'))),
            ), array_merge($mail_style('margin:0 0 16px'), $show_when('has_shipping'))),
            $mail_box(array(
                $el('p', 'fw-semibold mb-1', lang('Title'), array(), array_merge(array('text' => lang('What happens next')), $mail_style('font-weight:600;margin:0 0 4px'))),
                $mail_para(lang('Your order waits at the counter with your name on it on the day you chose, between 10:00 and 18:00; bring this number. For a class place we write within a day to agree the class day.'), 'mb-0', 'margin:0'),
            ), lang('Next Step')),
            $mail_button(lang('View your order'), '{{page:order}}?order_id=^^__order_id^^'),
            $mail_para(lang('A question about your order? Reply to this e-mail or call us:') . ' ' . $bakery['phone'], 'small mb-0', 'margin:0;font-size:13px'),
        )),
    ),
);

return array('pages' => $shop_pages, 'widgets' => $shop_widgets, 'catalog' => $catalog);
