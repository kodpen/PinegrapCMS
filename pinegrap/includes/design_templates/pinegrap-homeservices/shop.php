<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Home Services", its shop: the catalog (the Care Plans
 * group: the care plans and the gift card of $care_plans), the plans page,
 * the product, cart, checkout and order pages and the order receipt
 * e-mail. Included after parts.php and mail.php in the same scope;
 * returns array('pages' => ..., 'widgets' => ..., 'catalog' => ...).
 *
 * Every page and widget here needs the shop: the shared parts and the
 * record pages never link them, so a site without the shop has none of
 * them and no dead link.
 *
 * The prices are parts.php's own ($care_plans): the products are made
 * from the same figures the page writes with $money(), so the page and the
 * cart show one amount. A care plan is a set of visits booked later and a
 * gift card is sent by e-mail, so the products are not shippable and the
 * checkout asks for no delivery address.
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
// Care Plans group under the site's top group, each product found again
// by its SKU, in the order of $care_plans.
$plan_products = array();
foreach ($care_plans as $key => $p) {
    $plan_products[] = array(
        'sku'         => $p['sku'],
        'title'       => $p['title'],
        'description' => $p['text'],
        'price'       => $p['price'],
        'shippable'   => false,
        'featured'    => $key === 'home_care',
        'keywords'    => lang('care plan, maintenance, home, gift card, service'),
    );
}
$sample_plan_title = $plan_products[0]['title'];
$catalog = array(
    'folder'  => 'pinegrap_homeservices_catalog',
    // The Details tab of every product's page.
    'details' => array(
        lang('A care plan runs for a year from the order; book each of its visits on the days that suit you.'),
        lang('A gift card is sent by e-mail within one working day of the order, with its code, and is valid for a year.'),
        lang('A sample product of the template: change its name and price in the catalog, or remove it.'),
    ),
    'groups'  => array(
        'plans' => array(
            'parent'      => 'root',
            'title'       => lang('Care Plans'),
            'description' => lang('Yearly care plans for your home at a lower price than single visits, and gift cards towards any of our services.'),
            'products'    => $plan_products,
        ),
    ),
);

// ── Pages ───────────────────────────────────────────────────────────────

// How a care plan works, in three steps.
$plan_steps = array(
    $step(1, lang('Choose a plan'), lang('Order the plan that fits your home at the checkout, as for any order.')),
    $step(2, lang('Book its visits'), lang('Book each visit of the plan when it suits you and name the plan in the notes.')),
    $step(3, lang('We keep your home in shape'), lang('We come in your time window and write under each booking what we did.')),
);

$shop_pages[] = array(
    'key'              => 'plans',
    'requires'         => 'ecommerce',
    'name'             => lang('care-plans'),
    'folder'           => 'public',
    'title'            => lang('Care Plans'),
    'meta_description' => lang('Yearly home care plans at a lower price than single visits, and gift cards towards any of our services, sent by e-mail.'),
    'tree'             => $page(array(
        $page_header(lang('Care Plans'), lang('A year of regular care for your home at a lower price than single visits, or a gift card for someone who has just moved in.')),
        $el('section', 'py-5', lang('Plans'), array(
            $container(array($widget('plan_catalog'))),
        ), $searchable('hs-plans', lang('care plan, yearly service, maintenance, gift card, present'))),
        $el('section', 'py-5 bg-body-tertiary', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $section_header(lang('How it works'), lang('A plan, then the visits')),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), $plan_steps),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'p-4 rounded-3 bg-body border', lang('Box'), array(
                            $heading('h3', lang('Just one job to do?'), 'phs-display h5 mb-2'),
                            $para(lang('A plan pays off for regular care. For a single job, book a visit or ask for a free estimate; nothing is paid now.'), 'text-body-secondary mb-3'),
                            $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                                $link(lang('Book a Visit'), '{{page:book}}', 'btn btn-primary px-4'),
                                $link(lang('Get a Free Estimate'), '{{page:estimate}}', 'btn btn-outline-primary px-4'),
                            )),
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

// A Bootstrap button component: a plan card's Add to cart, which the
// listing wraps in the form that adds the product.
$shop_button = function ($text, $variant, $class = '', $extra = array()) {
    return array('type' => 'component', 'props' => array_merge(array('componentType' => 'btn', 'btnElement' => 'button', 'btnType' => 'submit',
        'variant' => $variant, 'text' => $text, 'cssClass' => $class), $extra), 'children' => array());
};

// A plan's card of the listing: a house with a tick (the same for every
// product the listing repeats it for), its name (the link to its page),
// its price and Add to cart.
$plan_card = function ($v) use ($el, $link, $span, $col, $bind, $icon_tile, $shop_button, $money) {
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-3 phs-lift', lang('Product Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile('bi-house-check', 'align-self-start mb-3'),
                $el('h3', 'phs-display h5 mb-2 position-static', lang('Product Name'), array(
                    $link($v['title'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => '__short_description', 'href' => '__detail_url'))),
                )),
                $el('div', 'd-flex align-items-center justify-content-between gap-3 mt-auto pt-3', lang('Buy Line'), array(
                    $span($money($v['price']), 'phs-price fw-semibold fs-5', lang('Price'), $bind('__price_formatted')),
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
        $el('td', 'pe-0 text-end', lang('Value'), array($span('$290.00', '', lang('Amount'), $bind($token))), $mail_style($style)),
    ), $flag !== '' ? $show_when($flag) : array());
};

$shop_widgets = array(

    // The plans on sale: the Care Plans group's products, every one on one
    // page. The notice after an add links this shop's cart.
    'plan_catalog' => array(
        'page'     => 'plans',
        'requires' => 'ecommerce',
        'slug'     => lang('catalog'),
        'config'   => array(
            'regionType'               => 'catalog_listing',
            'product_group_id'         => '{{product_group:plans}}',
            'group_navigation'         => 'none',
            'items_per_page'           => 24,
            'order_by_field'           => 'sort_order',
            'order_by_direction'       => 'ASC',
            'detail_page_id'           => '{{tab:product}}',
            'add_to_cart_stay_on_page' => true,
            'add_to_cart_next_page_id' => '{{tab:cart}}',
            'empty_message'            => lang('No care plans on sale right now.'),
        ),
        'tree'     => $widget_root(array(
            $row(array($loop(array($plan_card($plan_products[0])))), '4'),
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
    // product's breadcrumb starts at the plans page.
    'product_detail' => array(
        'page'     => 'product',
        'requires' => 'ecommerce',
        'slug'     => lang('product-detail'),
        'config'   => array(
            'regionType'              => 'catalog_item_view',
            'product_group_id'        => 0,
            'next_page_id'            => '{{tab:cart}}',
            'catalog_listing_page_id' => '{{tab:plans}}',
            'catalog_root_label'      => lang('Care Plans'),
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
    // totals, the billing address, what a plan and a gift card do next and
    // the way to book the plan's first visit.
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
                                $span($sample_plan_title, 'fw-semibold', lang('Product Name'), $bind('__item_short_description')),
                                $span(' × ', 'text-body-secondary', lang('Text')),
                                $span('1', '', lang('Quantity'), $bind('__item_qty')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #e2e8f0')),
                            $el('td', 'pe-0 text-end', lang('Total'), array(
                                $span('$290.00', '', lang('Amount'), $bind('__item_total')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #e2e8f0;text-align:right')),
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
                $el('p', 'fw-semibold mb-1', lang('Title'), array(), array_merge(array('text' => lang('What happens next')), $mail_style('font-weight:600;margin:0 0 4px'))),
                $mail_para(lang('A care plan runs for a year from today: book its first visit when it suits you and name the plan in the notes. A gift card comes to this address within one working day, with its code.'), 'mb-0', 'margin:0'),
            ), lang('Next Step')),
            $mail_button(lang('Book a Visit'), '{{page:book}}'),
            $mail_para(lang('Your order and its receipt are also on the order page:'), 'small mb-1', 'margin:0 0 4px;font-size:13px'),
            $link(lang('View your order'), '{{page:order}}?order_id=^^__order_id^^', 'small', array($attr('style', 'font-size:13px;color:#3e5c76'))),
        )),
    ),
);

return array('pages' => $shop_pages, 'widgets' => $shop_widgets, 'catalog' => $catalog);
