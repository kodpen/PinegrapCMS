<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Craft Brewery", its shop and the Beer Club: the catalog
 * (the Beer Packs, Glassware and Merch, Gift Cards and Beer Club groups, a
 * product for each item of $packs, $gift_cards and $club_terms), the shop
 * page, the product, cart, checkout and order pages, the order receipt
 * e-mail, the Beer Club page with its terms, and the members' area in the
 * club folder (the members' home and the club releases). Included after
 * parts.php and mail.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ..., 'catalog' => ...).
 *
 * Every page and widget here needs the shop: the shared parts and the
 * record pages never link them, so a site without the shop has none of
 * them and no dead link.
 *
 * Beer, glassware and merch are posted, so their products are shippable
 * and the checkout asks for a delivery address. A gift card is sent by
 * e-mail and is not shippable. A Beer Club term renews the buyer's
 * membership by its days once its order is paid (membership_days) and is
 * not shipped; the members' pages sit in the club folder, whose access is
 * the membership. A term is bought again to renew: nothing is charged by
 * itself. The prices are parts.php's own: the cards write them with
 * $money(), the products are made from the same figures, so the page and
 * the cart show one amount.
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

// The shop's items split into its groups: the beer packs, then the
// glassware and merch.
$merch_keys = array('glass', 'shirt');
$beer_packs = array_diff_key($packs, array_flip($merch_keys));
$merch_items = array_intersect_key($packs, array_flip($merch_keys));

// ── The catalog ─────────────────────────────────────────────────────────
// Made once when the template is first opened (_pg_tpl_catalog()): four
// groups under the site's top group, each product found again by its SKU.
$shop_products = function ($items, $more, $featured, $keywords) {
    $out = array();
    $n = 0;
    foreach ($items as $key => $p) {
        $out[] = array_merge(array(
            'sku'         => $p['sku'],
            'title'       => $p['title'],
            'description' => $p['text'],
            'price'       => $p['price'],
            'featured'    => in_array($key, $featured, true),
            'sort_order'  => ++$n,
            'keywords'    => $keywords,
        ), $more($p));
    }
    return $out;
};
$catalog = array(
    'folder'  => 'pinegrap_brewery_catalog',
    // The Details tab of every product's page.
    'details' => array(
        lang('Beer and glassware are posted within three working days; a gift card is sent by e-mail within one working day, with its code.'),
        lang('A Beer Club term starts as soon as the order is paid and opens the members\' area when you sign in with the address you bought it with.'),
        $brewery['adults'],
        lang('A sample product of the template: change its name and price in the catalog, or remove it.'),
    ),
    'groups'  => array(
        'packs'      => array(
            'parent'      => 'root',
            'title'       => lang('Beer Packs'),
            'description' => lang('Mixed cases, six-packs and cases of a single beer, canned the week you order and posted to your door.'),
            'products'    => $shop_products($beer_packs, function ($p) { return array(); }, array('mixed12', 'core6'),
                lang('beer, craft beer, mixed case, six-pack, cans, IPA, lager')),
        ),
        'merch'      => array(
            'parent'      => 'root',
            'title'       => lang('Glassware and Merch'),
            'description' => lang('The taproom\'s glass and the crane T-shirt, posted to your door.'),
            'products'    => $shop_products($merch_items, function ($p) { return array(); }, array('glass'),
                lang('beer glass, glassware, T-shirt, merch, gift')),
        ),
        'gift_cards' => array(
            'parent'      => 'root',
            'title'       => lang('Brewery Gift Cards'),
            'description' => lang('Gift cards for the shop, a tour or a round in the taproom, sent by e-mail.'),
            'products'    => $shop_products($gift_cards, function ($p) { return array('shippable' => false); }, array(),
                lang('gift card, gift, present, voucher')),
        ),
        'club'       => array(
            'parent'      => 'root',
            'title'       => lang('Beer Club'),
            'description' => lang('A mixed case every month, the first taste of every new beer and the members\' area.'),
            'products'    => $shop_products($club_terms, function ($p) { return array('shippable' => false, 'membership_days' => $p['days']); },
                array('year'), lang('beer club, beer subscription, monthly case, membership')),
        ),
    ),
);

// ── Pages ───────────────────────────────────────────────────────────────

// How an order goes, in three steps.
$shop_steps = array(
    $step(1, lang('Fill your case'), lang('A mixed case to try them all, a case of your favourite or a glass to drink it from.')),
    $step(2, lang('Order it online'), lang('Pay at the checkout; the receipt comes by e-mail at once.')),
    $step(3, lang('We post it'), lang('Canned the week you order and posted within three working days, packed by the brewers.')),
);

// The shop: the beer packs, the glassware and merch, and the gift cards,
// each a listing of its group, then how ordering works and the club.
$shop_pages[] = array(
    'key'              => 'shop',
    'requires'         => 'ecommerce',
    'name'             => lang('shop'),
    'folder'           => 'public',
    'title'            => lang('Brewery Shop'),
    'meta_description' => lang('Order our beer online: mixed cases, six-packs, cases of a single beer, the taproom glass, the crane T-shirt and gift cards.'),
    'tree'             => $page(array(
        $page_header(lang('Brewery Shop'), lang('Our beer at your door, canned the week you order. Fill a case, pick a glass or send a gift card.')),
        $el('section', 'py-5', lang('Beer Packs'), array(
            $container(array(
                $section_header(lang('Canned this week'), lang('Beer Packs')),
                $widget('pack_catalog'),
                $el('p', 'd-flex gap-2 small text-body-secondary mt-4 mb-0', lang('Adults Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span($brewery['adults'], '', lang('Text')),
                )),
            )),
        ), $searchable('bw-shop-packs', lang('buy beer, order online, mixed case, six-pack, cans, delivery'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Glassware and Merch'), array(
            $container(array(
                $section_header(lang('From the taproom'), lang('Glassware and Merch')),
                $widget('merch_catalog'),
            )),
        ), $searchable('bw-shop-merch', lang('beer glass, glassware, T-shirt, merch'))),
        $el('section', 'py-5', lang('Gift Cards'), array(
            $container(array(
                $section_header(lang('For a beer lover'), lang('Gift cards')),
                $widget('gift_card_catalog'),
            )),
        ), $searchable('bw-shop-gift-cards', lang('gift card, gift, present, voucher'))),
        $el('section', 'py-5 bg-body-tertiary', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $section_header(lang('Ordering'), lang('How it works')),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), $shop_steps),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body shadow-sm', lang('Beer Club'), array(
                            $para(lang('Every month, a new case'), 'pbw-eyebrow text-uppercase fw-semibold text-primary mb-1'),
                            $heading('h3', lang('Join the Beer Club'), 'pbw-display h4 mb-3'),
                            $check_list($club_perks, 'list-unstyled small mb-4'),
                            $link(lang('See the club terms'), '{{page:club}}', 'btn btn-primary rounded-pill px-4'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    )),
);

// The Beer Club: what a member gets, the terms on sale, how it works.
$club_steps = array(
    $step(1, lang('Choose a term'), lang('Three, six or twelve months; the longer the term, the more comes with it.')),
    $step(2, lang('Your first case comes'), lang('Posted in the first week of every month, with tasting notes from the brewers.')),
    $step(3, lang('Open the members\' area'), lang('Sign in with the address you bought it with: the club-only beers and the release dates are there.')),
);
$shop_pages[] = array(
    'key'              => 'club',
    'requires'         => 'ecommerce',
    'name'             => lang('beer-club'),
    'folder'           => 'public',
    'title'            => lang('Beer Club'),
    'meta_description' => lang('Join the Beer Club: a mixed case every month, the first taste of every new beer, ten per cent off and the club-only beers.'),
    'tree'             => $page(array(
        $page_header(lang('Beer Club'), lang('A mixed case of new and favourite beers every month, and a seat at every release party.')),
        $el('section', 'py-5', lang('What Members Get'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $section_header(lang('Members first'), lang('What members get')),
                        $check_list($club_perks),
                    ), '', '6'),
                    $col(array(
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), $club_steps),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('bw-club-perks', lang('beer club, subscription, monthly case, member perks, discount'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Club Terms'), array(
            $container(array(
                $section_header(lang('Join for a term'), lang('Choose your term')),
                $widget('club_catalog'),
                $para(lang('A term is bought once: buy it again before it ends to keep your cases coming. Nothing is charged by itself.'), 'small text-body-secondary mt-4 mb-0'),
            )),
        ), $searchable('bw-club-terms', lang('price, join, three months, six months, twelve months, renew'))),
        $el('section', 'py-5', lang('Members\' Area'), array(
            $container(array(
                $el('div', 'p-4 rounded-4 bg-primary-subtle d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3', lang('Members\' Area'), array(
                    $el('div', '', lang('Text'), array(
                        $heading('h2', lang('Already a member?'), 'pbw-display h5 mb-1'),
                        $para(lang('Sign in with the address you bought your term with to open the members\' area.'), 'small mb-0'),
                    )),
                    $link(lang('Open the Members\' Area'), '{{page:club_home}}', 'btn btn-primary rounded-pill px-4 text-nowrap'),
                )),
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

// ── The members' area ───────────────────────────────────────────────────

// A link tile of the members' home: an icon, a title, a line, the link.
$club_tile = function ($icon_name, $title, $text, $href) use ($el, $para, $link, $col, $icon_tile) {
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 pbw-lift', $title, array(
            $el('div', 'card-body p-4', lang('Card Body'), array(
                $icon_tile($icon_name, 'mb-3'),
                $el('h2', 'pbw-display h5 mb-2 position-static', lang('Title'), array(
                    $link($title, $href, 'stretched-link link-body-emphasis text-decoration-none'),
                )),
                $para($text, 'small text-body-secondary mb-0'),
            )),
        )),
    ), '6', '4');
};

// The members' home: in the club folder, so only a member reads it.
$shop_pages[] = array(
    'key'              => 'club_home',
    'requires'         => 'ecommerce',
    'name'             => lang('beer-club-members'),
    'folder'           => 'club',
    'title'            => lang('Beer Club Members'),
    'meta_description' => lang('The Beer Club\'s own pages: the club-only beers, the release parties and your account.'),
    'search'           => false,
    'sitemap'          => false,
    'tree'             => $page(array(
        $page_header(lang('Beer Club Members'), lang('Welcome back, member. Here is what is in the case, what is coming and where to taste it first.')),
        $el('section', 'py-5', lang('Member Links'), array(
            $container(array(
                $row(array(
                    $club_tile('bi-stars', lang('Club Releases'), lang('The beers brewed for members only, and when they reach your case.'), '{{page:club_releases}}'),
                    $club_tile('bi-calendar-event', lang('Taproom Events'), lang('Release parties, quizzes and live music; members taste the new beer first.'), '{{page:events}}'),
                    $club_tile('bi-cup-straw', lang('Our Beers'), lang('Every beer we brew, with the tasting notes and what to eat with it.'), '{{page:beers}}'),
                    $club_tile('bi-gear-wide-connected', lang('Brewery Tours'), lang('A twelve-month term brings a tour for two: ask for your day.'), '{{page:tours}}'),
                    $club_tile('bi-bag', lang('Brewery Shop'), lang('Cases, glassware and gift cards, posted to your door.'), '{{page:shop}}'),
                    $club_tile('bi-person-circle', lang('My Account'), lang('Your details, your orders and when your membership ends.'), '{{page:my_account}}'),
                ), '4'),
            )),
        )),
        $el('section', 'pb-5', lang('Member Notes'), array(
            $container(array(
                $el('div', 'p-4 rounded-4 bg-primary-subtle d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3', lang('Renewal'), array(
                    $el('div', '', lang('Text'), array(
                        $heading('h2', lang('Keep your cases coming'), 'pbw-display h5 mb-1'),
                        $para(lang('A term runs for its months. Buy it again before it ends and your next case comes without a gap.'), 'small mb-0'),
                    )),
                    $link(lang('See the club terms'), '{{page:club}}', 'btn btn-primary rounded-pill px-4 text-nowrap'),
                )),
            )),
        )),
    ), false),
);

// The club releases: the beers brewed for members, page content the
// brewers change in the designer as each one is brewed. A release names
// its season in words, never a month.
$club_releases = array(
    array('icon' => 'bi-moon-stars', 'title' => lang('Barrel-aged Anchor Stout'), 'when' => lang('In the winter case'),
        'strength' => '9.5% ABV · 50 IBU',
        'text' => lang('Our winter stout rested for six months in oak barrels: vanilla, dark fruit and a long, warm finish.')),
    array('icon' => 'bi-flower1', 'title' => lang('Fresh Hop Harvest IPA'), 'when' => lang('In the autumn case'),
        'strength' => '6.8% ABV · 55 IBU',
        'text' => lang('Brewed the day the hops are picked, with a resinous, green aroma you only get once a year.')),
    array('icon' => 'bi-sun', 'title' => lang('Cherry Tide Sour Reserve'), 'when' => lang('In the summer case'),
        'strength' => '5.5% ABV · 6 IBU',
        'text' => lang('The cherry sour aged a year longer on twice the fruit: deep, tart and only for members.')),
);
$release_cards = array();
foreach ($club_releases as $r) {
    $release_cards[] = $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4', $r['title'], array(
            $el('div', 'card-body p-4', lang('Card Body'), array(
                $el('div', 'd-flex align-items-center justify-content-between gap-2 mb-3', lang('Top Line'), array(
                    $icon_tile($r['icon']),
                    $span($r['when'], 'badge rounded-pill text-bg-primary', lang('Season')),
                )),
                $heading('h2', $r['title'], 'pbw-display h5 mb-1'),
                $para($r['strength'], 'pbw-abv small fw-semibold text-primary mb-2'),
                $para($r['text'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), '6', '4');
}
$shop_pages[] = array(
    'key'              => 'club_releases',
    'requires'         => 'ecommerce',
    'name'             => lang('club-releases'),
    'folder'           => 'club',
    'title'            => lang('Club Releases'),
    'meta_description' => lang('The beers the brewery brews for Beer Club members only, and the case each one comes in.'),
    'search'           => false,
    'sitemap'          => false,
    'tree'             => $page(array(
        $page_header(lang('Club Releases'), lang('Brewed in small batches for members only. Each comes in your case and is poured first at its release party.')),
        $el('section', 'py-5', lang('Club Releases'), array(
            $container(array(
                $row($release_cards, '4'),
                $el('p', 'mt-4 mb-0', lang('Text'), array(
                    $link(lang('See the release parties'), '{{page:events}}', 'fw-semibold link-primary'),
                )),
            )),
        )),
    ), false),
);

// ── Widgets ─────────────────────────────────────────────────────────────

// A Bootstrap button component: a card's Add to cart, which the listing
// wraps in the form that adds the product.
$shop_button = function ($text, $variant, $class = '', $extra = array()) {
    return array('type' => 'component', 'props' => array_merge(array('componentType' => 'btn', 'btnElement' => 'button', 'btnType' => 'submit',
        'variant' => $variant, 'text' => $text, 'cssClass' => $class), $extra), 'children' => array());
};

// A product's card of the listings: its icon, its name (the link to its
// page), its price and Add to cart. $p is the sample.
$shop_card = function ($p, $icon_name) use ($el, $link, $span, $col, $bind, $icon_tile, $shop_button, $money) {
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 pbw-lift', lang('Product Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile($icon_name, 'align-self-start mb-3'),
                $el('h3', 'pbw-display h5 mb-2 position-static', lang('Product Name'), array(
                    $link($p['title'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => '__short_description', 'href' => '__detail_url'))),
                )),
                $el('div', 'd-flex align-items-center justify-content-between gap-3 mt-auto pt-3', lang('Buy Line'), array(
                    $span($money($p['price']), 'pbw-price fw-semibold fs-5', lang('Price'), $bind('__price_formatted')),
                    $shop_button(lang('Add to cart'), 'primary', 'position-relative z-2 text-nowrap rounded-pill',
                        array('size' => 'sm', '_bindings' => array('action' => 'catalog_add_to_cart'))),
                )),
            )),
        )),
    ), '6', '4');
};

// A listing of one of the template's groups: every product on one page,
// in the catalog's order. The notice after an add links this shop's cart.
$shop_listing = function ($page_key, $group, $card, $empty) use ($widget_root, $row, $loop) {
    return array(
        'page'     => $page_key,
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
            $row(array($loop(array($card))), '4'),
        )),
    );
};

// A line of the receipt's totals, shown while its flag holds.
$shop_total_row = function ($name, $label, $token, $flag = '', $class = '', $style = 'text-align:right') use ($el, $span, $bind, $show_when, $mail_style) {
    return $el('tr', $class, $name, array(
        $el('td', 'ps-0' . ($class === '' ? ' text-body-secondary' : ''), lang('Label'), array($span($label, '', lang('Text')))),
        $el('td', 'pe-0 text-end', lang('Value'), array($span('$42.00', '', lang('Amount'), $bind($token))), $mail_style($style)),
    ), $flag !== '' ? $show_when($flag) : array());
};

$shop_widgets = array(

    'pack_catalog'      => $shop_listing('shop', 'packs', $shop_card($packs['mixed12'], $packs['mixed12']['icon']),
        lang('No beer packs on sale right now. Come and fill a growler in the taproom.')),

    'merch_catalog'     => $shop_listing('shop', 'merch', $shop_card($packs['glass'], $packs['glass']['icon']),
        lang('No glassware or merch on sale right now.')),

    'gift_card_catalog' => $shop_listing('shop', 'gift_cards', $shop_card($gift_cards['gift25'], $gift_cards['gift25']['icon']),
        lang('No gift cards on sale right now.')),

    'club_catalog'      => $shop_listing('club', 'club', $shop_card($club_terms['half'], 'bi-box2-heart'),
        lang('No Beer Club terms on sale right now. Ask in the taproom how to join.')),

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
            'catalog_listing_page_id' => '{{tab:shop}}',
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

    // The receipt: the order's number and date, its items, the totals,
    // the billing address, and what comes next for each kind of item.
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
                                $span($packs['mixed12']['title'], 'fw-semibold', lang('Product Name'), $bind('__item_short_description')),
                                $span(' × ', 'text-body-secondary', lang('Text')),
                                $span('1', '', lang('Quantity'), $bind('__item_qty')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #fde68a')),
                            $el('td', 'pe-0 text-end', lang('Total'), array(
                                $span('$42.00', '', lang('Amount'), $bind('__item_total')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #fde68a;text-align:right')),
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
                $el('div', '', lang('Address'), array(), array_merge(array('text' => lang('Jane Cooper, Sample Street 1, Istanbul')), $bind('__billing_address_html'))),
            ), $mail_style('margin:0 0 16px')),
            $mail_box(array(
                $el('p', 'fw-semibold mb-1', lang('Title'), array(), array_merge(array('text' => lang('What happens next')), $mail_style('font-weight:600;margin:0 0 4px'))),
                $mail_para(lang('Beer and glassware are posted within three working days. A gift card comes by e-mail within one working day, with its code. A Beer Club term starts once the payment is confirmed: your first case is posted in the first week of the month, and the members\' area opens when you sign in with this address.'), 'mb-0', 'margin:0'),
            ), lang('Next Step')),
            $mail_para($brewery['adults'], 'small text-body-secondary mb-3', 'font-size:13px;color:#4b5563;margin:0 0 16px'),
            $mail_button(lang('View your order'), '{{page:order}}?order_id=^^__order_id^^'),
        )),
    ),
);

return array('pages' => $shop_pages, 'widgets' => $shop_widgets, 'catalog' => $catalog);
