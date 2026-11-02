<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Online Courses", its shop: the catalog the courses and
 * the membership are sold from, the buy box on each course page and on the
 * pricing page, the product, cart, checkout and order pages, and the order
 * receipt e-mail. Included after parts.php and mail.php in the same scope;
 * returns array('pages' => ..., 'widgets' => ..., 'catalog' => ...).
 *
 * A course is a product of its own group under the Courses group, so its
 * page's buy box lists that one product; the product grants the course's
 * private folder once its order is paid (no end date), and is not shipped.
 * The membership is a product of the Membership group that renews the
 * buyer's membership by its days. Everything here needs the shop: on a site
 * without it the course pages keep their enrolment request.
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
// Courses group under the site's top group, a group for each course and one
// for the membership under it, each with its product found again by its
// SKU. Prices in the base currency, USD for any currency not listed.
$course_groups = array(
    'courses' => array(
        'parent'      => 'root',
        'title'       => lang('Courses'),
        'description' => lang('The school\'s online courses and the all-access membership.'),
        'products'    => array(),
    ),
);
$n = 0;
foreach ($courses as $key => $c) {
    $course_groups['courses_' . $key] = array(
        'parent'      => 'courses',
        'title'       => $c['title'],
        'description' => $c['summary'],
        'sort_order'  => ++$n,
        'products'    => array(array(
            'sku'            => $c['sku'],
            'title'          => $c['title'],
            'description'    => $c['summary'] . ' ' . implode(' · ', $c['outcomes']),
            'price'          => $c['prices'],
            'shippable'      => false,
            'private_folder' => $c['folder'],
            'private_days'   => 0,
        )),
    );
}
$course_groups['membership'] = array(
    'parent'      => 'courses',
    'title'       => lang('Membership'),
    'description' => $membership['text'],
    'sort_order'  => ++$n,
    'products'    => array(array(
        'sku'             => $membership['sku'],
        'title'           => $membership['title'],
        'description'     => $membership['text'] . ' ' . implode(' · ', $membership['items']),
        'price'           => $membership['prices'],
        'shippable'       => false,
        'membership_days' => $membership['days'],
        'featured'        => true,
    )),
);
$catalog = array(
    'folder'  => 'pinegrap_courses_catalog',
    // The Details tab of every product's page.
    'details' => array(
        lang('The course opens in My Courses as soon as the order is paid; sign in with the address you bought it with.'),
        lang('Yours with no end date: every lesson, its project and the instructor\'s feedback.'),
        lang('A sample product of the template: change its name and price in the catalog, or remove it.'),
    ),
    'groups'  => $course_groups,
);

// ── Pages ───────────────────────────────────────────────────────────────

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

// A Bootstrap button component: the buy box's Add to cart, which the
// listing wraps in the form that adds the product.
$button = function ($text, $variant, $class = '', $extra = array()) {
    return array('type' => 'component', 'props' => array_merge(array('componentType' => 'btn', 'btnElement' => 'button', 'btnType' => 'submit',
        'variant' => $variant, 'text' => $text, 'cssClass' => $class), $extra), 'children' => array());
};

// A buy box: the one product of a group, as its Add to cart button; the
// cart opens after the add. Left out on a site without the shop.
$buy_box = function ($page_key, $group_key, $label) use ($widget_root, $loop, $el, $button) {
    return array(
        'page'     => $page_key,
        'requires' => 'ecommerce',
        'slug'     => lang('catalog'),
        'config'   => array(
            'regionType'               => 'catalog_listing',
            'product_group_id'         => '{{product_group:' . $group_key . '}}',
            'group_navigation'         => 'none',
            'items_per_page'           => 1,
            'max_results'              => 1,
            'order_by_field'           => 'sort_order',
            'order_by_direction'       => 'ASC',
            'detail_page_id'           => '{{tab:product}}',
            'add_to_cart_stay_on_page' => false,
            'add_to_cart_next_page_id' => '{{tab:cart}}',
            'empty_message'            => '',
        ),
        'tree'     => $widget_root(array(
            $loop(array(
                $el('div', 'd-grid mb-2', lang('Buy Button'), array(
                    $button($label, 'primary', 'btn-lg', array('_bindings' => array('action' => 'catalog_add_to_cart'))),
                )),
            )),
        )),
    );
};

// A line of the receipt's totals, shown while its flag holds.
$total_row = function ($name, $label, $token, $flag = '', $class = '', $style = 'text-align:right') use ($el, $span, $bind, $show_when, $mail_style) {
    return $el('tr', $class, $name, array(
        $el('td', 'ps-0' . ($class === '' ? ' text-body-secondary' : ''), lang('Label'), array($span($label, '', lang('Text')))),
        $el('td', 'pe-0 text-end', lang('Value'), array($span(lang('$79.00'), '', lang('Amount'), $bind($token))), $mail_style($style)),
    ), $flag !== '' ? $show_when($flag) : array());
};

$first_course = reset($courses);
$shop_widgets = array();
foreach ($courses as $key => $c) {
    $shop_widgets['buy_' . $key] = $buy_box('course_' . $key, 'courses_' . $key, lang('Buy the course'));
}
$shop_widgets['buy_membership'] = $buy_box('pricing', 'membership', lang('Become a member'));

$shop_widgets += array(

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
            'catalog_listing_page_id' => '{{tab:courses}}',
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
            'cart_section_label'          => lang('Your courses'),
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

    // The receipt: the order's number and date, its courses, the totals,
    // the billing address, and the way into the classroom.
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
                                $span($first_course['title'], 'fw-semibold', lang('Product Name'), $bind('__item_short_description')),
                                $span(' × ', 'text-body-secondary', lang('Text')),
                                $span('1', '', lang('Quantity'), $bind('__item_qty')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #ccfbf1')),
                            $el('td', 'pe-0 text-end', lang('Total'), array(
                                $span(lang('$79.00'), '', lang('Amount'), $bind('__item_total')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #ccfbf1;text-align:right')),
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
                $el('div', '', lang('Address'), array(), array_merge(array('text' => lang('Jane Cooper, Sample Street 1, Istanbul')), $bind('__billing_address_html'))),
            ), $mail_style('margin:0 0 16px')),
            $mail_box(array(
                $el('p', 'fw-semibold mb-1', lang('Title'), array(), array_merge(array('text' => lang('Your course is ready')), $mail_style('font-weight:600;margin:0 0 4px'))),
                $mail_para(lang('Once the payment is confirmed, the course opens in My Courses. Sign in with the address you bought it with.'), 'mb-0', 'margin:0'),
            ), lang('Next Step')),
            $mail_button(lang('Go to My Courses'), '{{page:my_courses}}'),
            $mail_para(lang('Your order and its receipt are also on the order page:'), 'small mb-1', 'margin:0 0 4px;font-size:13px'),
            $link(lang('View your order'), '{{page:order}}?order_id=^^__order_id^^', 'small', array($attr('style', 'font-size:13px;color:#0f766e'))),
        )),
    ),
);

return array('pages' => $shop_pages, 'widgets' => $shop_widgets, 'catalog' => $catalog);
