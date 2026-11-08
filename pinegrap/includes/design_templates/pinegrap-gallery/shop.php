<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Art Gallery", its shop: the catalog (the Shop group with
 * a group per range: signed editions, small works and exhibition
 * catalogues), the shop page, the product, cart, checkout and order pages,
 * the order receipt e-mail and the cart link of the account bar. Included
 * after parts.php, cards.php and mail.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ..., 'catalog' => ...).
 *
 * Everything here needs the shop: the shared parts and the public pages
 * never link these pages, so a site without the shop keeps the artists,
 * the works and the enquiries. The works on the walls are never sold
 * online: the shop and product pages send a larger work to the works and
 * its enquiry. Editions, small works and catalogues are shipped.
 *
 * An edition or a small work is by an artist of parts.php ($artists), a
 * catalogue is for one of its exhibitions ($exhibitions); the prices are
 * figures per currency, read with $money() as everywhere in the template.
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
// The ranges of the shop: a group each under the Shop group; a range's key
// is its group's key, so its listing is
// {{page:shop}}/{{product_group_path:<range key>}}.
$shop_ranges = array(
    'editions'    => array('icon' => 'bi-layers', 'title' => lang('Signed Editions'),
        'text' => lang('Prints made with the artists in small editions, each one signed and numbered by hand.')),
    'small_works' => array('icon' => 'bi-flower1', 'title' => lang('Small Works'),
        'text' => lang('Original pieces from the artists\' studios, small enough for a shelf or a desk.')),
    'catalogues'  => array('icon' => 'bi-book', 'title' => lang('Exhibition Catalogues'),
        'text' => lang('The books of our exhibitions, with the works, the curator\'s essay and a conversation with the artists.')),
);

// The sample products of each range. 'artist' is a key of $artists, a
// catalogue's 'exhibition' a key of $exhibitions; prices per currency in
// major units; 'featured' marks the products the Shop group shows first.
$shop_items = array(
    'editions' => array(
        array('artist' => 'aiko', 'featured' => true, 'title' => lang('Bridge at Two, Signed Print'),
            'text'  => lang('A silver gelatin print of the photograph, 30 × 40 cm, in an edition of 25, printed by the artist in her darkroom.'),
            'price' => array('USD' => 420, 'EUR' => 390, 'GBP' => 330, 'TRY' => 14000)),
        array('artist' => 'deniz', 'featured' => false, 'title' => lang('River Map, Signed Print'),
            'text'  => lang('A pigment print on cotton paper, 50 × 70 cm, in an edition of 30, from the ink drawing in the gallery.'),
            'price' => array('USD' => 280, 'EUR' => 260, 'GBP' => 220, 'TRY' => 9500)),
        array('artist' => 'lena', 'featured' => false, 'title' => lang('Window Seat, Lithograph'),
            'text'  => lang('A lithograph in four colours, 40 × 50 cm, in an edition of 20, drawn on the stone by the artist.'),
            'price' => array('USD' => 650, 'EUR' => 600, 'GBP' => 520, 'TRY' => 22000)),
    ),
    'small_works' => array(
        array('artist' => 'mira', 'featured' => true, 'title' => lang('Ash-Glazed Tea Bowl'),
            'text'  => lang('A hand-built bowl from the last wood firing; every bowl is different and comes in a box signed by the artist.'),
            'price' => array('USD' => 180, 'EUR' => 165, 'GBP' => 140, 'TRY' => 6000)),
        array('artist' => 'tomas', 'featured' => false, 'title' => lang('Harbour Stone Study'),
            'text'  => lang('A small study in limestone and forged steel, 20 cm high, made beside the large pieces of the series.'),
            'price' => array('USD' => 950, 'EUR' => 880, 'GBP' => 760, 'TRY' => 32000)),
        array('artist' => 'deniz', 'featured' => false, 'title' => lang('Bird Route, a Small Drawing'),
            'text'  => lang('An ink drawing on handmade paper, 21 × 30 cm, mounted and ready to frame.'),
            'price' => array('USD' => 340, 'EUR' => 315, 'GBP' => 270, 'TRY' => 11500)),
    ),
    'catalogues' => array(
        array('exhibition' => 'after_hours', 'featured' => true, 'title' => lang('After Hours: the Catalogue'),
            'text'  => lang('Eighty pages with every painting of the exhibition, an essay by the curator and a conversation with the artist.'),
            'price' => array('USD' => 35, 'EUR' => 32, 'GBP' => 28, 'TRY' => 1100)),
        array('exhibition' => 'second_life', 'featured' => false, 'title' => lang('Second Life: the Catalogue'),
            'text'  => lang('The steel, stone and clay of the exhibition photographed in the courtyard, with notes from both studios.'),
            'price' => array('USD' => 30, 'EUR' => 28, 'GBP' => 24, 'TRY' => 950)),
        array('exhibition' => 'night_lines', 'featured' => false, 'title' => lang('Night Lines: the Catalogue'),
            'text'  => lang('The photographs of the coming exhibition with the artist\'s notes on each night; sent as soon as it is printed.'),
            'price' => array('USD' => 32, 'EUR' => 30, 'GBP' => 26, 'TRY' => 1000)),
    ),
);
$shop_sku = function ($range, $n) {
    return 'GL-' . strtoupper(str_replace('_', '', $range)) . '-' . (int)$n;
};

// A product is made in the first group that lists it and placed in the
// next one by its SKU, so a featured product is one product in two groups:
// one price, one stock, one page. Every product ships.
$featured_products = array();
$range_products = array();
foreach ($shop_items as $range_key => $list) {
    foreach (array_values($list) as $n => $p) {
        $by = isset($p['artist']) ? $artists[$p['artist']]['name'] : $exhibitions[$p['exhibition']]['title'];
        $product = array(
            'sku'         => $shop_sku($range_key, $n + 1),
            'title'       => $p['title'],
            'description' => $p['text'],
            'price'       => $p['price'],
            'featured'    => !empty($p['featured']),
            'keywords'    => $shop_ranges[$range_key]['title'] . ', ' . $by,
        );
        if ($product['featured']) $featured_products[] = $product;
        $range_products[$range_key][] = $product;
    }
}

$catalog_groups = array(
    'gallery_shop' => array(
        'parent'      => 'root',
        'title'       => lang('Gallery Shop'),
        'description' => lang('Signed editions, small works and exhibition catalogues by the artists of the gallery.'),
        'products'    => $featured_products,
    ),
);
$sort = 0;
foreach ($shop_ranges as $key => $r) {
    $catalog_groups[$key] = array(
        'parent'      => 'gallery_shop',
        'sort_order'  => ++$sort,
        'title'       => $r['title'],
        'description' => $r['text'],
        'products'    => $range_products[$key],
    );
}

$catalog = array(
    'folder'  => 'pinegrap_gallery_catalog',
    // The Details tab of every product's page.
    'details' => array(
        lang('Every edition and small work comes with a certificate signed by the artist.'),
        lang('Packed by our team in the gallery and sent insured, or ready to collect at the front desk.'),
        lang('A sample product of the template: change its name and price in the catalog, or remove it.'),
    ),
    'groups'  => $catalog_groups,
);

// The address of a group's own listing on the shop page.
$shop_group_url = function ($key) {
    return '{{page:shop}}/{{product_group_path:' . $key . '}}';
};

// ── Pages ───────────────────────────────────────────────────────────────

// Beside the products: a work on the walls is bought through an enquiry,
// never in the cart, and the team answers any question about an edition.
$shop_works_note = function () use ($el, $heading, $para, $link, $row, $col, $icon_tile) {
    $box = function ($icon_name, $title, $text, $link_text, $href) use ($el, $heading, $para, $link, $col, $icon_tile) {
        return $col(array(
            $el('div', 'h-100 p-4 border bg-body', $title, array(
                $icon_tile($icon_name, 'mb-3'),
                $heading('h3', $title, 'pgl-display h5 mb-2'),
                $para($text, 'small text-body-secondary mb-3'),
                $link($link_text, $href, 'btn btn-sm btn-outline-primary rounded-0 px-3'),
            )),
        ), '6');
    };
    return $row(array(
        $box('bi-easel2', lang('Looking for a larger work?'),
            lang('The paintings, sculpture and photographs on our walls are sold through the gallery, never online. See what is available and ask us about any of them.'),
            lang('See the Works'), '{{page:works}}'),
        $box('bi-envelope-paper', lang('A question about an edition?'),
            lang('Ask about the paper, the frame or a number of an edition; we answer within a working day.'),
            lang('Send an Enquiry'), '{{page:enquire}}'),
    ), '4');
};

// The shop: the ranges open in place, with the search, the sorting and
// the price filter.
$shop_chips = array();
foreach ($shop_ranges as $key => $r) {
    $shop_chips[] = $el('a', 'btn btn-sm btn-outline-secondary rounded-0 px-3 d-inline-flex align-items-center gap-2', $r['title'], array(
        $icon($r['icon']),
        $span($r['title'], '', lang('Link Text')),
    ), array('href' => $shop_group_url($key)));
}

$shop_pages[] = array(
    'key'              => 'shop',
    'requires'         => 'ecommerce',
    'name'             => lang('shop'),
    'folder'           => 'public',
    'title'            => lang('Shop'),
    'meta_description' => lang('Signed editions, small works and exhibition catalogues by the artists of the gallery, packed with care and sent insured.'),
    'tree'             => $page(array(
        $page_header(lang('Shop'), lang('Signed editions, small works and the catalogues of our exhibitions, by the artists we represent. Open a range, or search, sort and filter everything at once.'), array(
            $el('nav', 'd-flex flex-wrap gap-2 mt-4', lang('Product Ranges'), $shop_chips,
                array('_attrs' => array($attr('aria-label', lang('Product Ranges'))))),
        )),
        $el('section', 'py-5', lang('Products'), array(
            $container(array($widget('shop_catalog'))),
        ), $searchable('gl-shop-products', lang('buy, order, price, edition, print, signed, small work, ceramics, catalogue, book, gift'))),
        $el('section', 'py-5 border-top bg-body-tertiary', lang('The Works'), array(
            $container(array(
                $section_header(lang('Beyond the shop'), lang('The works on our walls'), lang('Art advisory'), '{{page:advisory}}'),
                $shop_works_note(),
            )),
        )),
    )),
);

// The shop's own pages: the cart, the checkout and the order show the
// editor's layouts for their kinds, out of the search and the sitemap.
$shop_page = function ($key, $name, $title, $main) use ($page) {
    return array(
        'key'      => $key,
        'requires' => 'ecommerce',
        'name'     => $name,
        'folder'   => 'public',
        'title'    => $title,
        'search'   => false,
        'sitemap'  => false,
        'noindex'  => true,
        'tree'     => $page($main, false),
    );
};

// A product: its page, then the note on the works beside every product.
$shop_pages[] = array_merge($shop_page('product', lang('product-detail'), lang('Product'), array(
    $widget('product_detail'),
    $el('section', 'py-5 border-top bg-body-tertiary', lang('The Works'), array(
        $container(array($shop_works_note())),
    )),
)), array('search' => true, 'sitemap' => true, 'noindex' => false, 'meta_description' => lang('An edition, a small work or a catalogue from the gallery: the artist, the size, the edition and the price.')));
$shop_pages[] = $shop_page('cart', lang('cart'), lang('Shopping Cart'), array($widget('cart')));
$shop_pages[] = $shop_page('checkout', lang('checkout'), lang('Checkout'), array($widget('checkout')));
$shop_pages[] = $shop_page('order', lang('order-summary'), lang('Order Summary'), array($widget('order_view')));

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
        $el('td', 'pe-0 text-end', lang('Value'), array($span('$420.00', '', lang('Amount'), $bind($token))), $mail_style($style)),
    ), $flag !== '' ? $show_when($flag) : array());
};

$sample_product = $shop_items['editions'][0]['title'];
$sample_price = $money($shop_items['editions'][0]['price']);

$shop_widgets = array(

    // The shop: the Shop group with its ranges opening in place.
    'shop_catalog' => array(
        'page'     => 'shop',
        'requires' => 'ecommerce',
        'slug'     => lang('catalog'),
        'config'   => array(
            'regionType'               => 'catalog_listing',
            'product_group_id'         => '{{product_group:gallery_shop}}',
            'group_navigation'         => 'drill_down',
            'search_enabled'           => true,
            'sort_control_enabled'     => true,
            'price_filter_enabled'     => true,
            'stock_filter_enabled'     => false,
            'order_by_field'           => 'sort_order',
            'order_by_direction'       => 'ASC',
            'items_per_page'           => 12,
            'detail_page_id'           => '{{tab:product}}',
            'add_to_cart_stay_on_page' => true,
            'add_to_cart_next_page_id' => '{{tab:cart}}',
            'empty_message'            => lang('Nothing here yet. Try another range.'),
        ),
        'tree'     => 'starter',
    ),

    // The way to the cart, in the account bar; left out without the shop.
    'cart_link' => array(
        'page'     => '',
        'requires' => 'ecommerce',
        'slug'     => lang('cart-link'),
        'config'   => array('regionType' => 'cart_link', 'cart_page_id' => '{{tab:cart}}'),
        'tree'     => 'starter',
    ),

    // The shop's pages draw the editor's own layouts for their kinds. A
    // product's breadcrumb starts at the shop.
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
    // the shipping and billing addresses, what happens next, and the way to
    // ask the gallery about an edition.
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
                                $span($sample_product, 'fw-semibold', lang('Product Name'), $bind('__item_short_description')),
                                $span(' × ', 'text-body-secondary', lang('Text')),
                                $span('1', '', lang('Quantity'), $bind('__item_qty')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #e7e5e4')),
                            $el('td', 'pe-0 text-end', lang('Total'), array(
                                $span($sample_price, '', lang('Amount'), $bind('__item_total')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #e7e5e4;text-align:right')),
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
                $mail_para(lang('We wrap every piece by hand in the gallery and send it insured within three working days, with its certificate; or keep it at the front desk if you chose to collect it. You hear from us when it is on its way.'), 'mb-0', 'margin:0'),
            ), lang('Next Step')),
            $mail_para(lang('A question about the paper, the frame or the number of your edition? Write to us and we answer within a working day.'), 'mb-1', 'margin:0 0 4px'),
            $mail_button(lang('Send an Enquiry'), '{{page:enquire}}'),
            $mail_para(lang('Your order and its receipt are also on the order page:'), 'small mb-1', 'margin:0 0 4px;font-size:13px'),
            $link(lang('View your order'), '{{page:order}}?order_id=^^__order_id^^', 'small', array($attr('style', 'font-size:13px;color:#57534e'))),
        )),
    ),
);

return array('pages' => $shop_pages, 'widgets' => $shop_widgets, 'catalog' => $catalog);
