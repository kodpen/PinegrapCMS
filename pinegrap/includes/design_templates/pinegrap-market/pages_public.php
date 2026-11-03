<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Marketplace", its public pages: home, categories, how it
 * works, sell with us, about, the questions page and the gift guide.
 * Included after parts.php in the same scope (its builders and the market's
 * data); returns array('pages' => ..., 'widgets' => ...).
 *
 * Every page here stands without the shop: the product cards are samples
 * that open the maker list of their category, and every figure (categories,
 * makers, products, prices, promises, steps) comes from parts.php, so a
 * change made there reaches every page here. The home page places two list
 * widgets of other parts: home_sellers (the makers) and home_posts (the
 * Journal).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$public_pages = array();

// A picture that loads when it comes near the screen.
$lazy = array('_attrs' => array($attr('loading', 'lazy')));

// A band of text beside a picture; $flip puts the picture first from lg up.
// The band is a search section under its anchor $id, with the topics a
// visitor may search for that it does not say itself.
$market_band = function ($id, $eyebrow, $title, $text, $points, $picture, $alt, $flip = false, $topics = '', $class = 'py-5')
    use ($el, $heading, $para, $image, $container, $row, $col, $check_list, $searchable, $lazy) {
    $text_col = $col(array(
        $para($eyebrow, 'pmk-eyebrow text-uppercase fw-semibold text-primary mb-2'),
        $heading('h2', $title, 'h2 fw-semibold mb-3'),
        $para($text, 'lead text-body-secondary mb-4'),
        $check_list($points),
    ), '', '6', '', '12', $flip ? 'order-lg-last' : '');
    $picture_col = $col(array(
        $el('div', 'ratio ratio-4x3 rounded-4 overflow-hidden bg-body-tertiary', lang('Picture Frame'), array(
            $image($picture, $alt, 'object-fit-cover', $lazy),
        )),
    ), '', '6');
    return $el('section', $class, $title, array(
        $container(array(
            $row(array($text_col, $picture_col), '4', '', 'g-lg-5 align-items-center'),
        )),
    ), $searchable($id, $topics));
};

// Three tiles of an icon, a title and a line, in a row.
$market_tiles = function ($items) use ($el, $heading, $para, $col, $row, $icon_tile) {
    $cols = array();
    foreach ($items as $v) {
        $cols[] = $col(array(
            $el('div', 'h-100 p-4 rounded-4 bg-body-tertiary', $v['title'], array(
                $icon_tile($v['icon'], 'mb-3'),
                $heading('h3', $v['title'], 'h5 fw-semibold mb-2'),
                $para($v['text'], 'small text-body-secondary mb-0'),
            )),
        ), '', '4');
    }
    return $row($cols, '4');
};

// The numbered steps of the market, as an ordered list.
$market_step_list = function ($steps) use ($el, $step) {
    $items = array();
    foreach (array_values($steps) as $n => $s) $items[] = $step($n + 1, $s[0], $s[1]);
    return $el('ol', 'list-unstyled mb-0', lang('Steps'), $items);
};

// A group of questions under its title; each question is a search section
// of its own ($faq).
$faq_group = function ($id, $title, $items) use ($el, $heading, $faq) {
    $children = array();
    foreach ($items as $n => $q) $children[] = $faq($n + 1, $q[0], $q[1], $q[2], $id);
    return $el('div', 'mb-5', $title, array(
        $heading('h2', $title, 'h4 fw-semibold mb-3'),
        $el('div', 'accordion', lang('Questions'), $children, array('id' => $id)),
    ));
};

// A strip that sends the visitor on: a title, a line and one button.
$market_strip = function ($title, $text, $button, $href, $class = 'py-5') use ($el, $heading, $para, $link, $container) {
    return $el('section', $class, $title, array(
        $container(array(
            $el('div', 'p-4 p-lg-5 rounded-4 bg-body-tertiary d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3', lang('Text'), array(
                $el('div', '', lang('Text'), array(
                    $heading('h2', $title, 'h4 fw-semibold mb-1'),
                    $para($text, 'text-body-secondary mb-0'),
                )),
                $link($button, $href, 'btn btn-primary rounded-pill px-4 text-nowrap'),
            )),
        )),
    ));
};

// The featured products, one per maker, in the order of the makers.
$featured_cards = array();
foreach ($market_products as $seller_key => $products) {
    foreach (array_values($products) as $i => $p) {
        if (!empty($p['featured'])) $featured_cards[] = $product_card($seller_key, $i);
    }
}

// The category tiles, in the order of the menu.
$category_tiles = array();
foreach (array_keys($categories) as $key) $category_tiles[] = $category_tile($key);

// ── Home ────────────────────────────────────────────────────────────────
$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('A market of independent makers: ceramics, jewellery, prints, woven bags and pantry goods made by hand, in one cart.'),
    'tree'             => $page(array(
        // The market in one line, the search and the two ways on.
        $el('section', 'py-5 bg-body-tertiary overflow-hidden pmk-hero', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Made by hand, sold by us'), 'pmk-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                        $heading('h1', lang('Things made by people you can name'), 'display-4 fw-semibold mb-3'),
                        $para(lang('Bowls, rings, prints and pantry goods from independent makers, in one cart and one delivery.'), 'lead text-body-secondary mb-4'),
                        $search_box('mk-hero-search', 'mb-4 pmk-search'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Shop by category'), '{{page:categories}}', 'btn btn-primary btn-lg rounded-pill px-4'),
                            $link(lang('Meet the makers'), '{{page:sellers}}', 'btn btn-outline-primary btn-lg rounded-pill px-4'),
                        )),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'ratio ratio-4x3 rounded-4 overflow-hidden bg-body-tertiary', lang('Picture Frame'), array(
                            $image($photo('hero', 1200, 900), lang('A maker glazing a stoneware bowl at a workbench'), 'object-fit-cover'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        // The categories, each opening its part of the categories page.
        $el('section', 'py-5', lang('Categories'), array(
            $container(array(
                $section_header(lang('Browse'), lang('Shop by category'), lang('All categories'), '{{page:categories}}'),
                $row($category_tiles, '3'),
            )),
        )),

        // The makers the home page puts first: the Sellers list.
        $el('section', 'py-5 bg-body-tertiary', lang('Makers'), array(
            $container(array(
                $section_header(lang('The makers'), lang('Shops we love'), lang('All Makers'), '{{page:sellers}}'),
                $widget('home_sellers'),
            )),
        )),

        // A first piece from each maker.
        $el('section', 'py-5', lang('Featured Products'), array(
            $container(array(
                $section_header(lang('Just made'), lang('Something from every maker'), lang('Gift Guide'), '{{page:gift_guide}}'),
                $row($featured_cards, '4'),
            )),
        )),

        // What every order comes with.
        $el('section', 'py-5 border-top', lang('Promises'), array(
            $container(array($promise_row())),
        )),

        // How it works, in four steps beside a picture.
        $el('section', 'py-5 bg-body-tertiary', lang('How it works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'ratio ratio-4x3 rounded-4 overflow-hidden bg-body-tertiary', lang('Picture Frame'), array(
                            $image($photo('parcel', 1200, 900), lang('A parcel wrapped in paper and string on a packing table'), 'object-fit-cover', $lazy),
                        )),
                    ), '', '6'),
                    $col(array(
                        $para(lang('How it works'), 'pmk-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                        $heading('h2', lang('Many makers, one order'), 'h2 fw-semibold mb-4'),
                        $market_step_list($market_steps),
                        $link(lang('How it works'), '{{page:how_it_works}}', 'btn btn-outline-primary rounded-pill px-4 mt-4'),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        // The newest from the Journal.
        $el('section', 'py-5', lang('From the Journal'), array(
            $container(array(
                $section_header(lang('Journal'), lang('Stories from the workshops'), lang('All stories'), '{{page:blog}}'),
                $widget('home_posts'),
            )),
        )),

        $shared('maker_band'),
    )),
);

// ── Categories ──────────────────────────────────────────────────────────
// A section for each category (#category-<key>, the anchor the menu, the
// footer and the tiles open): its picture and line, its makers, their
// products and the link to the maker list filtered to it.
$category_links = array();
$category_sections = array();
$band_n = 0;
foreach ($categories as $key => $c) {
    $category_links[] = $el('li', 'nav-item', lang('Nav Item'), array($link($c['title'], '#category-' . $key, 'nav-link px-0')));
    $maker_names = array();
    $category_cards = array();
    foreach ($sellers as $seller_key => $s) {
        if ($s['category'] !== $key) continue;
        $maker_names[] = $s['name'];
        foreach (array_keys($market_products[$seller_key]) as $i) $category_cards[] = $product_card($seller_key, $i);
    }
    $category_sections[] = $el('section', 'py-5' . ($band_n % 2 === 1 ? ' bg-body-tertiary' : ''), $c['title'], array(
        $container(array(
            $row(array(
                $col(array(
                    $el('div', 'ratio ratio-4x3 rounded-4 overflow-hidden bg-body-tertiary', lang('Picture Frame'), array(
                        $image($photo('category-' . $key, 800, 600), $c['title'], 'object-fit-cover', $lazy),
                    )),
                ), '', '4'),
                $col(array(
                    $el('h2', 'h3 fw-semibold d-flex align-items-center gap-2 mb-2', lang('Title'), array(
                        $icon($c['icon'], 'text-primary'),
                        $span($c['title'], '', lang('Text')),
                    )),
                    $para($c['text'], 'lead text-body-secondary mb-3'),
                    $para(sprintf(lang('Made by %s.'), implode(', ', $maker_names)), 'small text-body-secondary mb-3'),
                    $link(lang('See the makers'), $makers_in($key), 'btn btn-outline-primary rounded-pill px-4'),
                ), '', '8'),
            ), '4', '', 'g-lg-5 align-items-center mb-4'),
            $row($category_cards, '4'),
        )),
    ), $searchable('category-' . $key, $c['topics']));
    $band_n++;
}

$public_pages[] = array(
    'key'              => 'categories',
    'name'             => lang('categories'),
    'folder'           => 'public',
    'title'            => lang('Categories'),
    'meta_description' => lang('Home and living, jewellery, art and prints, bags and clothing, pantry: every category of the market and the makers behind it.'),
    'tree'             => $page(array_merge(
        array($page_header(lang('Categories'), lang('Five corners of the market, each with its makers and what they make.'), array(
            $el('nav', 'mt-3', lang('Jump to'), array(
                $el('ul', 'nav column-gap-4 small', lang('Jump Links'), $category_links),
            ), array('_attrs' => array($attr('aria-label', lang('Categories'))))),
        ))),
        $category_sections,
        array($shared('maker_band'))
    )),
);

// ── How it works ────────────────────────────────────────────────────────
$public_pages[] = array(
    'key'              => 'how_it_works',
    'name'             => lang('how-it-works'),
    'folder'           => 'public',
    'title'            => lang('How it works'),
    'meta_description' => lang('How the market works: one cart for every maker, one payment, tracked delivery and 30 days to return.'),
    'tree'             => $page(array(
        $page_header(lang('How it works'), lang('Many makers, one cart: we sell, pack and send everything, so you pay once and follow one order.')),
        $el('section', 'py-5', lang('Steps'), array(
            $container(array(
                $row(array(
                    $col(array($market_step_list($market_steps)), '', '7'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('In Short'), array(
                            $heading('h2', lang('Who sells what?'), 'h5 fw-semibold mb-2'),
                            $para(lang('Every order is sold and sent by us; the makers make it. That is why one cart, one payment and one return address cover every shop.'), 'small text-body-secondary mb-0'),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('steps', lang('how to buy, order, cart, checkout'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Promises'), array(
            $container(array(
                $section_header(lang('Every order'), lang('What every order comes with')),
                $promise_row(),
            )),
        )),
        $market_band('delivery', lang('Delivery'), lang('Packed once, tracked to your door'),
            lang('Makers send their pieces to us; we check them, pack your whole order together and send it with tracking.'),
            array(lang('Orders usually leave within three working days'), lang('A tracking link by e-mail when it leaves'), lang('Paper and card packing, no plastic')),
            $photo('packing', 1200, 900), lang('Hands wrapping a ceramic cup in paper'), false, lang('delivery, shipping, tracking, packaging')),
        $market_band('returns', lang('Returns'), lang('30 days to change your mind'),
            lang('Send a piece back within 30 days, whichever shop it came from; ask from your account and we tell you how.'),
            array(lang('One return address for every shop'), lang('Refunds to the card you paid with'), lang('Food and made-to-measure pieces are not returnable once opened or made')),
            $photo('returns', 1200, 900), lang('A parcel being taped shut for its return'), true, lang('returns, refund, exchange, money back'), 'py-5 bg-body-tertiary'),
        $market_band('reviews', lang('Reviews'), lang('Tell the maker what you think'),
            lang('Every maker\'s page carries the reviews of the people who bought from them; we read every one, and so do they.'),
            array(lang('Stars and a few words on the maker\'s page'), lang('Photos of the piece at home are welcome'), lang('A problem goes to Help, not a review: we answer it faster')),
            $photo('review', 1200, 900), lang('A handmade bowl of fruit on a kitchen table'), false, lang('reviews, ratings, feedback')),
        $market_strip(lang('Something wrong with an order?'), lang('Tell us from your account: we answer every request and you can follow it there.'), lang('Help with an Order'), '{{page:help}}'),
        $shared('maker_band'),
    )),
);

// ── Sell with us ────────────────────────────────────────────────────────
$sell_reasons = array(
    array('icon' => 'bi-camera', 'title' => lang('We take the photos'), 'text' => lang('Send us your pieces or let us visit: we photograph them and write the listings with you.')),
    array('icon' => 'bi-box-seam', 'title' => lang('We handle the orders'), 'text' => lang('Payments, packing, delivery, returns and questions are ours; you hear what sold.')),
    array('icon' => 'bi-people', 'title' => lang('Buyers who care how it is made'), 'text' => lang('Your shop sits beside a small number of makers, chosen by hand, not thousands.')),
);
$sell_steps = array(
    array(lang('Apply'), lang('Tell us about your work and send a few photos; it takes ten minutes.')),
    array(lang('We get to know you'), lang('We call or visit, see the work and agree the pieces and prices together.')),
    array(lang('Your shop opens'), lang('Your page, story and products go live, and the market tells its readers.')),
    array(lang('You keep making'), lang('We send you the orders to make or restock, and pay for what has sold every month.')),
);
$sell_looking_for = array(
    lang('Pieces you make yourself, or in a small workshop you run'),
    lang('Materials you can name and a process you can show'),
    lang('Enough stock for a few orders, or a lead time you can keep'),
    lang('Photos that show the work as it is'),
);

$public_pages[] = array(
    'key'              => 'sell',
    'name'             => lang('sell-with-us'),
    'folder'           => 'public',
    'title'            => lang('Sell with us'),
    'meta_description' => lang('Open a shop on the market: we take the photos, the orders and the parcels, you keep making. Apply in ten minutes.'),
    'tree'             => $page(array(
        $el('section', 'py-5 bg-primary-subtle pmk-hero', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('For makers'), 'pmk-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                        $heading('h1', lang('Sell with us'), 'display-4 fw-semibold mb-3'),
                        $para(lang('Open a shop with us: we take the photos, the orders and the parcels, you keep making.'), 'lead mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Apply to Sell'), '{{page:apply}}', 'btn btn-primary btn-lg rounded-pill px-4'),
                            $link(lang('Meet the makers'), '{{page:sellers}}', 'btn btn-outline-primary btn-lg rounded-pill px-4'),
                        )),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'ratio ratio-4x3 rounded-4 overflow-hidden bg-body-tertiary', lang('Picture Frame'), array(
                            $image($photo('sell', 1200, 900), lang('A weaver at a wooden loom'), 'object-fit-cover'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),
        $el('section', 'py-5', lang('Why Sell With Us'), array(
            $container(array(
                $section_header(lang('Why us'), lang('You make, we do the rest')),
                $market_tiles($sell_reasons),
            )),
        ), $searchable('why-sell', lang('sell, open a shop, become a maker, seller'))),
        $el('section', 'py-5 bg-body-tertiary', lang('How to Join'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('From application to open shop'), 'h3 fw-semibold mb-4'),
                        $market_step_list($sell_steps),
                    ), '', '7'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body', lang('What We Look For'), array(
                            $heading('h2', lang('What we look for'), 'h5 fw-semibold mb-3'),
                            $check_list($sell_looking_for),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('how-to-join', lang('apply, application, requirements, join'))),
        $el('section', 'py-5', lang('Questions'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $faq_group('mk-faq-sell', lang('Questions from makers'), array(
                            array(lang('What does it cost to open a shop?'), lang('Nothing to open it. We agree a price with you for each piece; the difference to the price in the shop pays for the photos, packing, delivery and the market itself.'), lang('cost, fee, commission, price')),
                            array(lang('Do I send every order myself?'), lang('No. You send your pieces to us in batches, or we collect them; we pack and send the orders.'), lang('shipping, stock, delivery')),
                            array(lang('When am I paid?'), lang('Once a month, for what sold in the month before, to the bank account you give us.'), lang('payment, payout, bank')),
                            array(lang('How long does an application take?'), lang('We answer every application within two weeks; you can follow it, and write to us about it, from your account.'), lang('application, answer, how long')),
                        )),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
        $market_strip(lang('Ready to open your shop?'), lang('Tell us about your work: it takes ten minutes, and we answer every application.'), lang('Apply to Sell'), '{{page:apply}}', 'pb-5'),
    )),
);

// ── About ───────────────────────────────────────────────────────────────
$about_values = array(
    array('icon' => 'bi-person-heart', 'title' => lang('Made by people you can name'), 'text' => lang('Every piece says who made it and where; every maker has a page and a story.')),
    array('icon' => 'bi-hand-thumbs-up', 'title' => lang('Chosen by hand'), 'text' => lang('We meet every maker and see their work before their shop opens, and we keep the market small.')),
    array('icon' => 'bi-recycle', 'title' => lang('Less, and better'), 'text' => lang('Small runs, natural materials and packing without plastic: things meant to be kept.')),
);
$about_figures = array(
    array((string)count($sellers), lang('makers')),
    array((string)count($categories), lang('categories')),
    array((string)count($cities), lang('cities')),
    array('30', lang('days to return')),
);
$figure_cols = array();
foreach ($about_figures as $f) {
    $figure_cols[] = $col(array(
        $el('div', 'text-center', $f[1], array(
            $para($f[0], 'display-5 fw-semibold text-primary mb-0'),
            $para($f[1], 'small text-body-secondary mb-0'),
        )),
    ), '3', '', '', '6');
}

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About Us'),
    'meta_description' => lang('Who we are: a small market of independent makers, chosen by hand, with one cart and one delivery for every shop.'),
    'tree'             => $page(array(
        $page_header(lang('About Us'), lang('A market of independent makers: things made by hand, in small numbers, by people you can name.')),
        $el('section', 'py-5', lang('Our Story'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Why a market of makers'), 'h3 fw-semibold mb-3'),
                        $para(lang('We kept meeting makers whose work deserved more buyers, and buyers who wanted to know who made what they bought. The market puts the two together.'), 'text-body-secondary'),
                        $para(lang('We sell, pack and send every order ourselves, so a maker can stay at the bench and a buyer can order from several shops at once.'), 'text-body-secondary mb-0'),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'ratio ratio-4x3 rounded-4 overflow-hidden bg-body-tertiary', lang('Picture Frame'), array(
                            $image($photo('about', 1200, 900), lang('Makers and the market team around a table of finished pieces'), 'object-fit-cover', $lazy),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('our-story', lang('company, team, who we are'))),
        $el('section', 'py-5 border-top', lang('Figures'), array(
            $container(array($row($figure_cols, '4'))),
        )),
        $el('section', 'py-5 bg-body-tertiary', lang('Our Values'), array(
            $container(array(
                $section_header(lang('What we stand for'), lang('How we choose and sell'), lang('Meet the makers'), '{{page:sellers}}'),
                $market_tiles($about_values),
            )),
        ), $searchable('values', lang('values, sustainability, handmade, small makers'))),
        $market_strip(lang('Press and partners'), lang('Journalists, shops and partners: write to us and the right person answers.'), lang('Contact Us'), '{{page:contact}}'),
        $shared('maker_band'),
    )),
);

// ── Questions ───────────────────────────────────────────────────────────
$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Frequently Asked Questions'),
    'meta_description' => lang('Answers about ordering from several makers, delivery, returns, reviews, your account and selling on the market.'),
    'tree'             => $page(array(
        $page_header(lang('Frequently Asked Questions'), lang('Orders, delivery, returns and the makers. Not answered here? Ask us from Help.')),
        $el('section', 'py-5', lang('Questions'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $faq_group('mk-faq-order', lang('Ordering'), array(
                            array(lang('Can I order from several makers at once?'), lang('Yes. Everything goes into one cart and one checkout, and we send it together.'), lang('several shops, cart, one order')),
                            array(lang('Who sells the products?'), lang('We do: every order is sold and sent by us, the makers make it. Your receipt and your return go to us.'), lang('seller, who sells, receipt, invoice')),
                            array(lang('Can I ask for a piece made to order?'), lang('Some makers take orders to measure. Ask from Help with the piece you have in mind and we ask the maker.'), lang('custom, made to order, personalised')),
                        )),
                        $faq_group('mk-faq-delivery', lang('Delivery and returns'), array(
                            array(lang('How long does delivery take?'), lang('Orders usually leave within three working days and arrive two to five working days later; made-to-order pieces say their own time.'), lang('delivery, shipping, how long')),
                            array(lang('How do I track my order?'), lang('With the link in the e-mail we send when it leaves, or from your account.'), lang('tracking, where is my order')),
                            array(lang('How do I return something?'), lang('Within 30 days of delivery: ask from Help with the order number and we send you the address and a label.'), lang('return, refund, exchange')),
                            array(lang('My piece arrived damaged. What now?'), lang('We are sorry. Send a photo from Help with the order number: we replace it or refund it, and you keep nothing broken.'), lang('damaged, broken, faulty, replace')),
                        )),
                        $faq_group('mk-faq-makers', lang('The makers'), array(
                            array(lang('How are the makers chosen?'), lang('We meet every maker and see their work before their shop opens, and we keep the market small.'), lang('makers, chosen, quality')),
                            array(lang('How do I review a maker?'), lang('On the maker\'s page, under their story: stars and a few words. Every review is read.'), lang('review, rating, feedback')),
                            array(lang('I make things. Can I sell here?'), lang('Tell us about your work: it takes ten minutes, and we answer every application.'), lang('sell, apply, open a shop')),
                        )),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
        $market_strip(lang('Still have a question?'), lang('Send it from Help: we answer every request and you can follow it from your account.'), lang('Help with an Order'), '{{page:help}}', 'pb-5'),
    )),
);

// ── Gift guide ──────────────────────────────────────────────────────────
// Ideas by who the gift is for, each from the makers of matching shops,
// and the smallest piece of each maker as the small gifts. Every card is a
// product of parts.php, so the prices follow its table.
$gift_ideas = array(
    'kitchen' => array('title' => lang('For the kitchen'), 'text' => lang('For whoever cooks for everyone.'), 'sellers' => array('clay', 'timber', 'grove'),
        'topics' => lang('kitchen, cooking, tableware, food gifts')),
    'wear'    => array('title' => lang('Something to wear'), 'text' => lang('Light enough to wear every day.'), 'sellers' => array('silver', 'loom'),
        'topics' => lang('jewellery, scarf, bag, accessories')),
    'walls'   => array('title' => lang('For the walls'), 'text' => lang('Prints in small numbered runs.'), 'sellers' => array('ink'),
        'topics' => lang('art, prints, posters, cards')),
);
$gift_sections = array();
$band_n = 0;
foreach ($gift_ideas as $key => $g) {
    $gift_cards = array();
    foreach ($g['sellers'] as $seller_key) {
        if (!isset($market_products[$seller_key])) continue;
        foreach (array_keys($market_products[$seller_key]) as $i) {
            if (count($gift_cards) < 4) $gift_cards[] = $product_card($seller_key, $i);
        }
    }
    $gift_sections[] = $el('section', 'py-5' . ($band_n % 2 === 1 ? ' bg-body-tertiary' : ''), $g['title'], array(
        $container(array(
            $section_header(lang('Gift ideas'), $g['title']),
            $para($g['text'], 'text-body-secondary mb-4'),
            $row($gift_cards, '4'),
        )),
    ), $searchable('gift-' . $key, $g['topics']));
    $band_n++;
}
// The smallest piece of each maker, by the price the shop shows.
$small_gifts = array();
foreach ($market_products as $seller_key => $products) {
    $cheapest = null;
    foreach (array_values($products) as $i => $p) {
        $amount = isset($p['price'][$base_currency]) ? $p['price'][$base_currency] : $p['price']['USD'];
        if ($cheapest === null || $amount < $cheapest[1]) $cheapest = array($i, $amount);
    }
    if ($cheapest !== null) $small_gifts[] = $product_card($seller_key, $cheapest[0]);
}

$public_pages[] = array(
    'key'              => 'gift_guide',
    'name'             => lang('gift-guide'),
    'folder'           => 'public',
    'title'            => lang('Gift Guide'),
    'meta_description' => lang('Handmade gift ideas from independent makers: for the kitchen, to wear, for the walls, and small gifts from every shop.'),
    'tree'             => $page(array_merge(
        array($page_header(lang('Gift Guide'), lang('Handmade gifts chosen from every shop of the market, by who they are for.'))),
        $gift_sections,
        array(
            $el('section', 'py-5' . ($band_n % 2 === 1 ? ' bg-body-tertiary' : ''), lang('Small Gifts'), array(
                $container(array(
                    $section_header(lang('From every maker'), lang('Small gifts')),
                    $row($small_gifts, '4'),
                )),
            ), $searchable('gift-small', lang('small gifts, cheap, stocking fillers, under budget'))),
            $market_strip(lang('Not sure what to choose?'), lang('Tell us who it is for and what you have in mind: we suggest a few pieces.'), lang('Ask Us'), '{{page:help}}'),
            $shared('maker_band'),
        )
    )),
);

// The home page's lists (home_sellers, home_posts) belong to the parts
// that build those lists; this part places no widget of its own.
$public_widgets = array();

return array('pages' => $public_pages, 'widgets' => $public_widgets);
