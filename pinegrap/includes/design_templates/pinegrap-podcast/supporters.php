<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Podcast Network", its supporters and its shop: the
 * support page (the tiers, what supporters get, a year given as a gift),
 * the supporters' area (its home and a page for each bonus episode of
 * $bonus_episodes) in the supporters folder, and with the shop the catalog
 * (a group for each tier and one for the gift), the buy boxes on the
 * support page, the product, cart, checkout and order pages and the order
 * receipt e-mail. Included after parts.php and mail.php in the same scope;
 * returns array('pages' => ..., 'widgets' => ..., 'catalog' => ...).
 *
 * A tier's product renews the buyer's membership by $membership_days once
 * its order is paid, and is not shipped. The supporters' pages sit in the
 * supporters folder, whose access is the membership: a supporter hears the
 * bonus episodes, anybody else is asked to sign in or to support. A bonus
 * episode's player is the page's own static audio element: the staff put
 * the recording's file in place in the designer.
 *
 * The gift is a product without membership days: it would renew the
 * buyer's own membership, so the network opens the recipient's year by
 * hand from the order.
 *
 * Without the shop the support page still stands: its buy boxes are left
 * out and the page links the contact page; no tree outside the shop's own
 * pages links a shop page.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$supporter_pages = array();

// ── The catalog ─────────────────────────────────────────────────────────
// Made once when the template is first opened (_pg_tpl_catalog()): the
// Supporters group under the site's top group, a group for each tier and
// one for the gift under it, each with its product found again by its SKU,
// so each buy box lists that one product.
$su_groups = array(
    'supporters' => array(
        'parent'      => 'root',
        'title'       => lang('Supporters'),
        'description' => lang('Monthly support for the network, with bonus episodes for supporters, and a year of support as a gift.'),
        'products'    => array(),
    ),
);
$su_n = 0;
foreach ($tiers as $su_key => $t) {
    $su_groups['support_' . $su_key] = array(
        'parent'      => 'supporters',
        'title'       => $t['title'],
        'description' => $t['text'],
        'sort_order'  => ++$su_n,
        'products'    => array(array(
            'sku'             => $t['sku'],
            'title'           => $t['title'],
            'description'     => $t['text'] . ' ' . implode(' · ', $t['perks']),
            'price'           => $t['price'],
            'shippable'       => false,
            'membership_days' => $membership_days,
            'featured'        => $su_key === 'friend',
            'keywords'        => lang('support the podcast, supporter, membership, bonus episodes'),
        )),
    );
}
foreach ($gift_memberships as $su_key => $g) {
    $su_groups['support_' . $su_key] = array(
        'parent'      => 'supporters',
        'title'       => $g['title'],
        'description' => $g['text'],
        'sort_order'  => ++$su_n,
        'products'    => array(array(
            'sku'         => $g['sku'],
            'title'       => $g['title'],
            'description' => $g['text'],
            'price'       => $g['price'],
            'shippable'   => false,
            'featured'    => false,
            'keywords'    => lang('gift, present, support the podcast, supporter'),
        )),
    );
}
$catalog = array(
    'folder'  => 'pinegrap_podcast_catalog',
    // The Details tab of every product's page.
    'details' => array(
        lang('Your support starts as soon as the order is paid; sign in with the address you paid with to hear the bonus episodes.'),
        lang('A tier runs for a month: buy it again before it ends to keep the bonus episodes. A gift is opened for the person you name within one working day.'),
        lang('A sample product of the template: change its name and price in the catalog, or remove it.'),
    ),
    'groups'  => $su_groups,
);

// ── The support page ────────────────────────────────────────────────────

// How supporting works, in three steps.
$su_steps = array(
    $step(1, lang('Choose a tier'), lang('Listener, Friend or Patron: each runs for a month and you can change it any time.')),
    $step(2, lang('Pay online'), lang('Pay at the checkout as for any order; the receipt comes by e-mail at once.')),
    $step(3, lang('Listen to the bonus episodes'), lang('Sign in with the address you paid with and the supporters\' area opens.')),
);

// A tier card's buy box (with the shop) over the way to write to us,
// which stands without the shop too.
$su_tier_cards = array();
foreach (array_keys($tiers) as $su_key) {
    $su_tier_cards[] = $tier_card($su_key, $el('div', 'mt-auto', lang('Buy Box'), array(
        $widget('buy_' . $su_key),
        $link(lang('Questions? Write to us'), '{{page:contact}}', 'btn btn-link btn-sm'),
    )));
}

// The gift: what it is, its price, and its buy box.
$su_gift = $gift_memberships['gift'];
$su_gift_box = $el('div', 'p-4 p-lg-5 rounded-4 bg-primary-subtle', lang('Gift'), array(
    $row(array(
        $col(array(
            $icon_tile($su_gift['icon'], 'mb-3'),
            $heading('h2', $su_gift['title'], 'ppd-display h3 mb-2'),
            $para($su_gift['text'], 'mb-2'),
            $para(lang('Pay for it here and tell us in the order\'s notes who it is for: we open their year and write to them within one working day.'), 'small mb-0'),
        ), '', '8'),
        $col(array(
            $el('p', 'mb-3', lang('Price'), array(
                $span($money($su_gift['price']), 'ppd-price display-6 fw-bold', lang('Value')),
                $span(lang('a year'), 'small ms-1', lang('Label')),
            )),
            $widget('buy_gift'),
            $link(lang('Ask about a gift'), '{{page:contact}}', 'btn btn-link btn-sm px-0'),
        ), '', '4', '', '12', 'text-lg-end'),
    ), '4', '', 'align-items-center'),
));

$supporter_pages[] = array(
    'key'              => 'support',
    'name'             => lang('support-us'),
    'folder'           => 'public',
    'title'            => lang('Support Us'),
    'meta_description' => lang('Support the network from a few a month: bonus episodes, episodes without sponsor mentions, questions for the guests and a seat at the live recording.'),
    'tree'             => $page(array(
        $page_header(lang('Support Us'),
            lang('Every podcast of the network is free to hear. Supporters keep it that way, and hear a little more.')),
        $el('section', 'py-5', lang('Tiers'), array(
            $container(array(
                $section_header(lang('Supporters'), lang('Choose how you support')),
                $row($su_tier_cards, '4'),
            )),
        ), $searchable('pd-tiers', lang('support, supporter, membership, tier, price, monthly, bonus episodes'))),
        $el('section', 'py-5 bg-body-tertiary', lang('What Supporters Get'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $section_header(lang('Supporters'), lang('What supporters get')),
                        $check_list(array(
                            lang('Bonus episodes every month, in the supporters\' area'),
                            lang('Every episode without sponsor mentions'),
                            lang('Questions for the guests before we record'),
                            lang('Your name in the episode notes, if you like'),
                            lang('A seat at the live recording each season'),
                        )),
                    ), '', '6'),
                    $col(array(
                        $heading('h2', lang('How it works'), 'ppd-display h4 mb-3'),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), $su_steps),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
        $el('section', 'py-5', lang('Gift'), array(
            $container(array($su_gift_box)),
        ), $searchable('pd-gift', lang('gift, present, a year of support'))),
        $el('section', 'pb-5', lang('FAQ'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Questions about supporting'), 'ppd-display h4 mb-3'),
                        $link(lang('All questions'), '{{page:faq}}', 'btn btn-outline-primary rounded-pill px-4'),
                    ), '', '4'),
                    $col(array(
                        $el('div', 'accordion', lang('Questions'), array(
                            $faq(1, lang('Does my support renew by itself?'), lang('No. A tier runs for a month from the day the order is paid; buy it again to keep the bonus episodes.'), lang('renew, cancel, subscription, automatic'), 'pd-support'),
                            $faq(2, lang('Where do I hear the bonus episodes?'), lang('In the supporters\' area: sign in with the address you paid with and the bonus episodes play in the browser.'), lang('bonus episodes, where, listen, sign in'), 'pd-support'),
                            $faq(3, lang('Can I support without paying online?'), lang('Yes. Write to us and we send you our bank details; your support starts when it arrives.'), lang('bank transfer, pay, without card'), 'pd-support'),
                        ), array('id' => 'pd-support')),
                    ), '', '8'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    )),
);

// ── The supporters' area ────────────────────────────────────────────────

// A link tile of the supporters' home: an icon, a title, a line, the link.
$su_tile = function ($icon_name, $title, $text, $href) use ($el, $para, $link, $col, $icon_tile) {
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 ppd-lift', $title, array(
            $el('div', 'card-body p-4', lang('Card Body'), array(
                $icon_tile($icon_name, 'mb-3'),
                $el('h2', 'ppd-display h5 mb-2 position-static', lang('Title'), array(
                    $link($title, $href, 'stretched-link link-body-emphasis text-decoration-none'),
                )),
                $para($text, 'small text-body-secondary mb-0'),
            )),
        )),
    ), '6', '4');
};

// A bonus episode's card: the podcast it belongs to, its length, its title
// (the link to its page) and what it holds.
$su_bonus_card = function ($key) use ($el, $para, $span, $link, $col, $icon, $bonus_episodes, $podcasts) {
    $b = $bonus_episodes[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 ppd-lift', lang('Bonus Episode Card'), array(
            $el('div', 'card-body p-4', lang('Card Body'), array(
                $el('p', 'd-flex flex-wrap align-items-center gap-2 small text-primary mb-2', lang('Details'), array(
                    $icon('bi-soundwave'),
                    $span($podcasts[$b['podcast']]['title'], 'fw-semibold', lang('Podcast')),
                    $span('·', '', lang('Separator')),
                    $span(sprintf(lang('%d min'), $b['minutes']), '', lang('Length')),
                )),
                $el('h3', 'ppd-display h5 mb-2 position-static', lang('Title'), array(
                    $link($b['title'], '{{page:bonus_' . $key . '}}', 'stretched-link link-body-emphasis text-decoration-none'),
                )),
                $para($b['summary'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), '', '4');
};
$su_bonus_cards = array();
foreach (array_keys($bonus_episodes) as $su_key) $su_bonus_cards[] = $su_bonus_card($su_key);

$supporter_pages[] = array(
    'key'              => 'supporters_home',
    'name'             => lang('supporters'),
    'folder'           => 'supporters',
    'title'            => lang('Supporters\' Area'),
    'meta_description' => lang('The supporters\' own pages: the bonus episodes, questions for the guests and the supporters\' newsletter.'),
    'search'           => false,
    'sitemap'          => false,
    'tree'             => $page(array(
        $page_header(lang('Supporters\' Area'), lang('Thank you for keeping the microphones on. Here are this month\'s bonus episodes, and the ways to take part.')),
        $el('section', 'py-5', lang('Bonus Episodes'), array(
            $container(array(
                $section_header(lang('Only for supporters'), lang('Bonus episodes')),
                $row($su_bonus_cards, '4'),
            )),
        )),
        $el('section', 'py-5 bg-body-tertiary', lang('Supporter Links'), array(
            $container(array(
                $row(array(
                    $su_tile('bi-question-circle', lang('Ask the Next Guest'), lang('Send us your question for a guest before we record.'), '{{page:contact}}'),
                    $su_tile('bi-envelope-paper', lang('The Supporters\' Newsletter'), lang('What we are recording next, sent once a month.'), '{{page:newsletter}}'),
                    $su_tile('bi-person-circle', lang('My Account'), lang('Your details, your orders and when your support ends.'), '{{page:my_account}}'),
                ), '4'),
            )),
        )),
        $el('section', 'py-5', lang('Renewal'), array(
            $container(array(
                $el('div', 'p-4 rounded-4 bg-primary-subtle d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3', lang('Renewal'), array(
                    $el('div', '', lang('Text'), array(
                        $heading('h2', lang('Keep your support going'), 'ppd-display h5 mb-1'),
                        $para(lang('A tier runs for a month. Buy it again before it ends and the bonus episodes stay open without a gap.'), 'small mb-0'),
                    )),
                    $link(lang('Compare the tiers'), '{{page:support}}', 'btn btn-primary rounded-pill px-4 text-nowrap'),
                )),
            )),
        )),
    ), false),
);

// A bonus episode's page: the podcast and length over the title, the
// player, what the episode holds, and the way back to the other bonus
// episodes.
$su_bonus_names = array('outtakes' => lang('bonus-outtakes'), 'askus' => lang('bonus-supporters-questions'), 'live' => lang('bonus-live-recording'));
foreach ($bonus_episodes as $su_key => $b) {
    $su_podcast = $podcasts[$b['podcast']];
    $supporter_pages[] = array(
        'key'              => 'bonus_' . $su_key,
        'name'             => $su_bonus_names[$su_key],
        'folder'           => 'supporters',
        'title'            => $b['title'],
        'meta_description' => $b['summary'],
        'search'           => false,
        'sitemap'          => false,
        'tree'             => $page(array(
            $page_header($b['title'], $b['summary'], array(
                $el('p', 'd-flex flex-wrap align-items-center gap-2 small mt-3 mb-0', lang('Details'), array(
                    $span(lang('Bonus episode'), 'badge rounded-pill text-bg-primary', lang('Badge')),
                    $span($su_podcast['title'], 'fw-semibold', lang('Podcast')),
                    $span('·', '', lang('Separator')),
                    $span(sprintf(lang('%d min'), $b['minutes']), '', lang('Length')),
                )),
            )),
            $el('section', 'py-5', lang('Episode'), array(
                $container(array(
                    $row(array(
                        $col(array(
                            $player($b['title']),
                            $heading('h2', lang('Episode Notes'), 'ppd-display h4 mb-3'),
                            $para($b['summary'], 'mb-3'),
                            $para(lang('Recorded for the network\'s supporters. Please keep it among supporters: it is not in the public feed.'), 'small text-body-secondary mb-4'),
                            $link(lang('All bonus episodes'), '{{page:supporters_home}}', 'btn btn-outline-primary rounded-pill px-4'),
                        ), '', '8'),
                    ), '4', 'center'),
                )),
            )),
        ), false),
    );
}

// ── The shop's pages ────────────────────────────────────────────────────

// The shop's own pages: a product's page, the cart, the checkout and the
// order.
$su_shop_page = function ($key, $name, $title, $widget_key) use ($page, $widget) {
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
$supporter_pages[] = array_merge($su_shop_page('product', lang('product-detail'), lang('Product'), 'product_detail'),
    array('search' => true, 'sitemap' => true, 'noindex' => false, 'meta_description' => lang('Product details, options and price.')));
$supporter_pages[] = $su_shop_page('cart', lang('cart'), lang('Shopping Cart'), 'cart');
$supporter_pages[] = $su_shop_page('checkout', lang('checkout'), lang('Checkout'), 'checkout');
$supporter_pages[] = $su_shop_page('order', lang('order-summary'), lang('Order Summary'), 'order_view');

// The order receipt, sent to the billing address when an order is placed.
$supporter_pages[] = array_merge($mail_settings, array(
    'key'      => 'email_order',
    'requires' => 'ecommerce',
    'name'     => lang('email-order-receipt'),
    'title'    => lang('Order Receipt E-mail'),
    'tree'     => $mail_page(array($widget('email_order'))),
));

// ── Widgets ─────────────────────────────────────────────────────────────

// A Bootstrap button component: a buy box's Add to cart, which the
// listing wraps in the form that adds the product.
$su_button = function ($text, $variant, $class = '', $extra = array()) {
    return array('type' => 'component', 'props' => array_merge(array('componentType' => 'btn', 'btnElement' => 'button', 'btnType' => 'submit',
        'variant' => $variant, 'text' => $text, 'cssClass' => $class), $extra), 'children' => array());
};

// A buy box: the one product of a group, as its button; the cart opens
// after the add. Left out on a site without the shop.
$su_buy_box = function ($group_key, $label) use ($widget_root, $loop, $el, $su_button) {
    return array(
        'page'     => 'support',
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
                    $su_button($label, 'primary', 'rounded-pill', array('_bindings' => array('action' => 'catalog_add_to_cart'))),
                )),
            )),
        )),
    );
};

// A line of the receipt's totals, shown while its flag holds.
$su_total_row = function ($name, $label, $token, $sample, $flag = '', $class = '', $style = 'text-align:right') use ($el, $span, $bind, $show_when, $mail_style) {
    return $el('tr', $class, $name, array(
        $el('td', 'ps-0' . ($class === '' ? ' text-body-secondary' : ''), lang('Label'), array($span($label, '', lang('Text')))),
        $el('td', 'pe-0 text-end', lang('Value'), array($span($sample, '', lang('Amount'), $bind($token))), $mail_style($style)),
    ), $flag !== '' ? $show_when($flag) : array());
};
$su_sample_price = $money($tiers['friend']['price']);

$supporter_widgets = array();
foreach (array_keys($tiers) as $su_key) {
    $supporter_widgets['buy_' . $su_key] = $su_buy_box('support_' . $su_key, lang('Become a supporter'));
}
$supporter_widgets['buy_gift'] = $su_buy_box('support_gift', lang('Give a year'));

$supporter_widgets += array(

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
            'catalog_listing_page_id' => '{{tab:support}}',
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

    // The receipt: the order's number and date, its tiers, the totals,
    // the billing address, and the way into the supporters' area.
    'email_order' => array(
        'page'     => 'email_order',
        'requires' => 'ecommerce',
        'slug'     => lang('order'),
        'config'   => array('regionType' => 'order_view', 'date_format' => ''),
        'tree'     => $widget_root(array(
            $mail_heading(lang('Thank you for your support')),
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
                                $span($tiers['friend']['title'], 'fw-semibold', lang('Product Name'), $bind('__item_short_description')),
                                $span(' × ', 'text-body-secondary', lang('Text')),
                                $span('1', '', lang('Quantity'), $bind('__item_qty')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #fed7aa')),
                            $el('td', 'pe-0 text-end', lang('Total'), array(
                                $span($su_sample_price, '', lang('Amount'), $bind('__item_total')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #fed7aa;text-align:right')),
                        )),
                    )),
                )),
            ), $mail_style('width:100%;border-collapse:collapse;margin:0 0 16px')),
            $el('table', 'table table-sm mb-4', lang('Totals'), array(
                $el('tbody', '', lang('Rows'), array(
                    $su_total_row(lang('Subtotal'), lang('Subtotal'), '__order_subtotal', $su_sample_price),
                    $su_total_row(lang('Discount'), lang('Discount'), '__order_discount', $su_sample_price, 'has_discount'),
                    $su_total_row(lang('Tax'), lang('VAT'), '__order_tax', $su_sample_price, 'has_tax'),
                    $su_total_row(lang('Total'), lang('Total'), '__order_total', $su_sample_price, '', 'fw-bold', 'text-align:right;font-weight:700'),
                )),
            ), $mail_style('width:100%;border-collapse:collapse;margin:0 0 24px')),
            $el('div', '', lang('Billing Address'), array(
                $el('p', 'small text-body-secondary mb-1', lang('Label'), array(), array('text' => lang('Billing Address'))),
                $el('div', '', lang('Address'), array(), array_merge(array('text' => lang('Jane Cooper, Sample Street 1, Istanbul')), $bind('__billing_address_html'))),
            ), $mail_style('margin:0 0 16px')),
            $mail_box(array(
                $el('p', 'fw-semibold mb-1', lang('Title'), array(), array_merge(array('text' => lang('Your bonus episodes are waiting')), $mail_style('font-weight:600;margin:0 0 4px'))),
                $mail_para(lang('Once the payment is confirmed, the supporters\' area opens. Sign in with the address you paid with. A gift is opened for the person you named within one working day.'), 'mb-0', 'margin:0'),
            ), lang('Next Step')),
            $mail_button(lang('Go to the Supporters\' Area'), '{{page:supporters_home}}'),
            $mail_para(lang('Your order and its receipt are also on the order page:'), 'small mb-1', 'margin:0 0 4px;font-size:13px'),
            $link(lang('View your order'), '{{page:order}}?order_id=^^__order_id^^', 'small', array($attr('style', 'font-size:13px;color:#c2410c'))),
        )),
    ),
);

return array('pages' => $supporter_pages, 'widgets' => $supporter_widgets, 'catalog' => $catalog);
