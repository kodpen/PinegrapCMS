<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Bookshop": a bookshop and stationery store. A home page
 * with the staff picks of the week, the bestsellers, new arrivals, a
 * stationery corner, the coming events and a gift card band; a header with a
 * wide search and a category bar, the shop with its filters, one product
 * page for every product, the cart, checkout and the order summary, an
 * events page, a book club page whose sign-up form welcomes the new member
 * by e-mail, the about and contact pages, the sign-in and account pages and
 * a newsletter that collects e-mail addresses. Built on Bootstrap 5.
 * Read by pg_design_templates() (includes/fn/designer.php).
 *
 * Opening the template gives the site the catalog the pages list ('catalog':
 * a bookshop group with four categories and their products, the pictures
 * from includes/design_templates/pinegrap-bookshop/) and the newsletter's
 * contact group ('contact_groups'), each made once and found again by its
 * name the next time (_pg_tpl_catalog(), _pg_tpl_contact_groups()).
 *
 * Three pages own a form: the newsletter (its page, and the band at the foot
 * of the other pages that shows the same form), the contact page and the
 * book club. Each sends its own e-mail pages from the private folder.
 *
 * Placeholders, filled when the template is opened (pg_design_template_prepare()):
 *   {{page:<key>}}                  address of the template page with that key
 *   {{tab:<key>}}                   that page in a widget setting; its id once published
 *   {{folder:<key>}}                id of that template folder
 *   {{product_group:<key>}}         id of a group of the catalog below ('root': the site's top group)
 *   {{product_group_path:<key>}}    that group's address on the shop page
 *   {{product_group_image:<key>}}   that group's picture
 *   {{contact_group:<key>}}         id of a contact group below
 *   {{site_name}}, {{site_email}}, {{year}}
 * See includes/design_templates/hello-pinegrap.php for widgets, shared
 * components and 'requires'.
 *
 * Every structural node carries its role in '_label' (the designer's own
 * label, shown beside the element's tag in the layer tree).
 *
 * The version follows the template, not the software: raise it whenever the
 * pages change, so a design records which edition it started from.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// ── Node builders ───────────────────────────────────────────────────────
// Same shapes the editor's createNode() produces; the editor gives every
// node its id when the tabs open. $name is the node's label in the layer
// tree ('_label'): the tag stays visible beside it.
$el = function ($tag, $class, $name, $children = array(), $extra = array()) {
    $props = array('tag' => $tag, 'cssClass' => $class);
    if ($name !== '') $props['_label'] = $name;
    return array('type' => 'semantic', 'props' => array_merge($props, $extra), 'children' => $children);
};
$heading = function ($tag, $text, $class, $extra = array()) {
    return array('type' => 'content', 'props' => array_merge(array('contentType' => 'heading', 'tag' => $tag, 'text' => $text, 'cssClass' => $class), $extra), 'children' => array());
};
$para = function ($text, $class = '', $extra = array()) {
    return array('type' => 'content', 'props' => array_merge(array('contentType' => 'paragraph', 'text' => $text, 'cssClass' => $class), $extra), 'children' => array());
};
$link = function ($text, $href, $class, $attrs = array(), $extra = array()) {
    $props = array('contentType' => 'link', 'text' => $text, 'href' => $href, 'cssClass' => $class);
    if ($attrs) $props['_attrs'] = $attrs;
    return array('type' => 'content', 'props' => array_merge($props, $extra), 'children' => array());
};
$image = function ($src, $alt, $class, $extra = array()) {
    return array('type' => 'content', 'props' => array_merge(array('contentType' => 'image', 'src' => $src, 'alt' => $alt,
        'fluid' => false, 'rounded' => false, 'cssClass' => $class), $extra), 'children' => array());
};
$attr = function ($name, $value) {
    return array('name' => $name, 'value' => $value);
};
// A decorative icon: the words beside it say what it means.
$icon = function ($name, $class = '') use ($el, $attr) {
    return $el('i', trim('bi ' . $name . ' ' . $class), lang('Icon'), array(), array('_attrs' => array($attr('aria-hidden', 'true'))));
};
$span = function ($text, $class, $name, $extra = array()) use ($el) {
    return $el('span', $class, $name, array(), array_merge(array('text' => $text), $extra));
};
$container = function ($children, $class = '') {
    return array('type' => 'container', 'props' => array('fluid' => false, 'cssClass' => $class), 'children' => $children);
};
// A gutter above 4 is wider than the container's side padding and pushes a
// phone screen sideways; wider gaps are given from lg up (g-4 g-lg-5).
$row = function ($children, $gutter = '', $justify = '', $class = '') {
    return array('type' => 'row', 'props' => array('gutter' => $gutter, 'justify' => $justify, 'align' => '', 'cssClass' => $class), 'children' => $children);
};
$col = function ($children, $md = '', $lg = '', $sm = '', $xs = '12', $class = '') {
    return array('type' => 'col', 'props' => array('xs' => $xs, 'sm' => $sm, 'md' => $md, 'lg' => $lg, 'xl' => '', 'xxl' => '',
                                                  'offsetXs' => '', 'offsetMd' => '', 'order' => '', 'cssClass' => $class), 'children' => $children);
};
$root = function ($children) {
    return array('type' => 'root', 'props' => array('cssClass' => 'd-flex flex-column min-vh-100'), 'children' => $children);
};
$widget_root = function ($children) {
    return array('type' => 'root', 'props' => array(), 'children' => $children);
};
$loop = function ($children) {
    return array('type' => 'loop_area', 'props' => array(), 'children' => $children);
};
$widget = function ($key) {
    return array('type' => 'shared_ref', 'props' => array('templateWidget' => $key), 'children' => array());
};
$shared = function ($key) {
    return array('type' => 'shared_ref', 'props' => array('templateShared' => $key), 'children' => array());
};
$bind = function ($token) {
    return array('_bindings' => array('text' => $token));
};
$show_when = function ($flag) {
    return array('_bindings' => array('eo_visible_if' => $flag));
};
// The language switcher button: the server draws it for each request and
// leaves it out while the site has a single language, so it can stay in the
// header of every site.
$lang_switcher = function ($class = '', $variant = 'secondary') {
    return array('type' => 'component', 'props' => array(
        'componentType' => 'language_switcher',
        'variant' => $variant, 'outline' => true, 'size' => 'sm',
        'align' => 'end', 'display' => 'name', 'icon' => 'translate',
        'cssClass' => $class,
    ), 'children' => array());
};
// A Bootstrap button component.
$button = function ($text, $variant, $class = '', $extra = array()) {
    return array('type' => 'component', 'props' => array_merge(array('componentType' => 'btn', 'btnElement' => 'button', 'btnType' => 'submit',
        'variant' => $variant, 'text' => $text, 'cssClass' => $class), $extra), 'children' => array());
};
// The product card's Add to basket: the listing wraps the card in the form
// that adds the product. Lifted above the card's stretched link.
$add_to_basket = function ($variant, $class = '', $outline = false) use ($button) {
    return $button(lang('Add to basket'), $variant, trim('position-relative z-2 ' . $class),
        array('size' => 'sm', 'outline' => $outline, '_bindings' => array('action' => 'catalog_add_to_cart')));
};

// The sample picture a product card shows until the widget fills it in.
$sample_product = 'https://picsum.photos/seed/pinegrap-bookshop-product/600/600';

// ── The shop's categories and events ────────────────────────────────────

// The categories of the catalog below, in the order the menus list them,
// each with the tint of its tile on the home page.
$categories = array(
    'books'     => array('title' => lang('Books'), 'icon' => 'bi-book', 'tint' => 'bg-primary-subtle',
                         'text' => lang('Novels, poetry, cookbooks and audiobooks, picked by people who read them.')),
    'notebooks' => array('title' => lang('Notebooks & Journals'), 'icon' => 'bi-journal', 'tint' => 'bg-secondary-subtle',
                         'text' => lang('Dotted, lined and plain pages, planners and reading journals.')),
    'pens'      => array('title' => lang('Pens & Art'), 'icon' => 'bi-palette', 'tint' => 'bg-body-tertiary',
                         'text' => lang('Fountain pens, pencils, brushes and paints for every kind of hand.')),
    'cards'     => array('title' => lang('Cards & Gifts'), 'icon' => 'bi-gift', 'tint' => 'bg-success-subtle',
                         'text' => lang('Cards, gift cards, wrapping and small things for the desk.')),
);
$category_url = function ($key) {
    return '{{page:shop}}/{{product_group_path:' . $key . '}}';
};

// The coming events, newest first: the home page shows the first three, the
// events page all of them. Sample dates; change them on the pages.
$events = array(
    array('datetime' => '2026-10-15T19:00', 'month' => lang('Oct'), 'day' => '15', 'weekday' => lang('Thursday'), 'time' => '19:00',
          'type' => lang('Reading night'), 'title' => lang('Poems after Dark'),
          'text' => lang('An evening of poems read aloud by local writers, with tea and an open microphone for anyone brave enough.'),
          'place' => lang('The reading room, upstairs')),
    array('datetime' => '2026-10-17T14:00', 'month' => lang('Oct'), 'day' => '17', 'weekday' => lang('Saturday'), 'time' => '14:00',
          'type' => lang('Signing'), 'title' => lang('Jane Cooper signs The Lighthouse Year'),
          'text' => lang('Meet the author, have your copy signed and hear how the book was written.'),
          'place' => lang('At the front counter')),
    array('datetime' => '2026-10-29T19:00', 'month' => lang('Oct'), 'day' => '29', 'weekday' => lang('Thursday'), 'time' => '19:00',
          'type' => lang('Book club'), 'title' => lang('Book club: The Lighthouse Year'),
          'text' => lang('This month\'s book, talked over with coffee and cake. New members are always welcome.'),
          'place' => lang('The reading room, upstairs')),
    array('datetime' => '2026-11-07T10:30', 'month' => lang('Nov'), 'day' => '7', 'weekday' => lang('Saturday'), 'time' => '10:30',
          'type' => lang('Workshop'), 'title' => lang('Brush lettering for beginners'),
          'text' => lang('Learn the basic strokes with brush pens in two hours; pens and paper are provided.'),
          'place' => lang('The workshop table')),
    array('datetime' => '2026-11-19T19:00', 'month' => lang('Nov'), 'day' => '19', 'weekday' => lang('Thursday'), 'time' => '19:00',
          'type' => lang('Reading night'), 'title' => lang('Short stories by candlelight'),
          'text' => lang('Three short stories, three readers and the lights turned low.'),
          'place' => lang('The reading room, upstairs')),
);

// The date of an event as a small calendar leaf.
$date_box = function ($e, $class = '') use ($el, $span, $attr) {
    return $el('time', trim('d-flex flex-column align-items-center justify-content-center flex-shrink-0 rounded-3 px-3 py-2 bg-primary-subtle text-primary-emphasis ' . $class), lang('Date'), array(
        $span($e['month'], 'small text-uppercase fw-semibold', lang('Month')),
        $span($e['day'], 'fs-3 fw-bold lh-1', lang('Day')),
    ), array('_attrs' => array($attr('datetime', $e['datetime']))));
};

// ── The parts every page repeats ────────────────────────────────────────

// The line in the account bar: when the shop is open. A shared component.
$notice = function () use ($el, $icon, $span) {
    return $el('p', 'd-flex align-items-center gap-2 mb-0 text-primary-emphasis', lang('Notice'), array(
        $icon('bi-clock'),
        $span(lang('Open every day · Order online and collect in the shop'), '', lang('Text')),
    ));
};

// The bar above the header: the notice on the left, the language switcher,
// the basket and the login region on the right. The last two are system
// widgets and a shared component cannot hold one yet, so the bar is part of
// each page.
$account_bar = function () use ($el, $widget, $shared, $lang_switcher) {
    return $el('div', 'border-bottom small bg-primary-subtle', lang('Account Bar'), array(
        $el('div', 'container d-flex flex-wrap align-items-center justify-content-between gap-2 py-1', lang('Account Area'), array(
            $el('div', 'd-none d-md-block', lang('Announcement Area'), array($shared('notice'))),
            $el('div', 'd-flex align-items-center gap-2 ms-auto', lang('Account Links'), array(
                $lang_switcher(), $widget('cart_link'), $widget('login_region'),
            )),
        )),
    ));
};

// The header: the shop's name, a wide search over the catalog, the way to
// the shop's door, and under them the category bar whose first item opens
// every category. The links to the current page are marked active on the
// server (smart active state), which is what lets one header serve every
// page.
$header = function () use ($el, $link, $icon, $span, $categories, $category_url, $attr) {
    $browse = array();
    foreach ($categories as $key => $c) {
        $browse[] = $el('li', '', lang('Menu Item'), array(
            $el('a', 'dropdown-item d-flex align-items-center gap-2', lang('Category Link'), array(
                $icon($c['icon'], 'text-primary'),
                $span($c['title'], '', lang('Category Name')),
            ), array('href' => $category_url($key))),
        ));
    }
    $browse[] = $el('li', '', lang('Divider'), array($el('hr', 'dropdown-divider', lang('Divider Line'))));
    $browse[] = $el('li', '', lang('Menu Item'), array($link(lang('Everything in the shop'), '{{page:shop}}', 'dropdown-item fw-semibold')));

    $items = array(
        $el('li', 'nav-item dropdown', lang('Dropdown'), array(
            $el('a', 'nav-link dropdown-toggle fw-semibold d-flex align-items-center gap-2', lang('Dropdown Toggle'), array(
                $icon('bi-grid-3x3-gap'),
                $span(lang('Browse'), '', lang('Text')),
            ), array('href' => '#', '_attrs' => array(
                $attr('role', 'button'),
                $attr('data-bs-toggle', 'dropdown'),
                $attr('aria-expanded', 'false'),
            ))),
            $el('ul', 'dropdown-menu shadow-sm', lang('Dropdown Menu'), $browse),
        )),
    );
    foreach ($categories as $key => $c) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($c['title'], $category_url($key), 'nav-link')));
    }
    $items[] = $el('li', 'nav-item ms-lg-auto', lang('Nav Item'), array($link(lang('Events'), '{{page:events}}', 'nav-link')));
    $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link(lang('Book Club'), '{{page:book_club}}', 'nav-link')));
    $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link(lang('About'), '{{page:about}}', 'nav-link')));

    $quick = function ($icon_name, $text, $href) use ($el, $icon, $span) {
        return $el('a', 'd-inline-flex align-items-center gap-1 link-body-emphasis text-decoration-none', lang('Link'), array(
            $icon($icon_name, 'text-primary'),
            $span($text, '', lang('Link Text')),
        ), array('href' => $href));
    };

    return $el('header', 'bg-body', lang('Site Header'), array(
        $el('div', 'container d-flex flex-wrap align-items-center column-gap-4 row-gap-3 py-3', lang('Header Top'), array(
            $el('a', 'd-flex align-items-center gap-2 text-decoration-none link-body-emphasis', lang('Brand'), array(
                $el('span', 'd-inline-flex p-2 rounded-3 text-bg-primary lh-1', lang('Icon Box'), array($icon('bi-book-half', 'fs-5'))),
                $span('{{site_name}}', 'fs-4 fw-bold', lang('Name')),
            ), array('href' => '{{page:home}}')),
            // The shop page's own search: its listing reads ?query=.
            $el('form', 'col-12 col-lg order-last order-lg-0', lang('Search Form'), array(
                $el('label', 'visually-hidden', lang('Label'), array(), array('text' => lang('Search the shop'), '_attrs' => array($attr('for', 'bk-search')))),
                $el('div', 'input-group', lang('Search Box'), array(
                    $el('span', 'input-group-text bg-body border-end-0 text-body-secondary', lang('Search Icon'), array($icon('bi-search'))),
                    $el('input', 'form-control border-start-0 ps-0', lang('Field'), array(), array('id' => 'bk-search', '_attrs' => array(
                        $attr('type', 'search'),
                        $attr('name', 'query'),
                        $attr('placeholder', lang('Title, author or keyword…')),
                    ))),
                    $el('button', 'btn btn-primary px-4', lang('Search Button'), array(), array('text' => lang('Search'), '_attrs' => array($attr('type', 'submit')))),
                )),
            ), array('_attrs' => array($attr('action', '{{page:shop}}'), $attr('method', 'get'), $attr('role', 'search')))),
            $el('nav', 'd-none d-lg-flex align-items-center gap-3 small', lang('Quick Links'), array(
                $quick('bi-geo-alt', lang('Find the shop'), '{{page:contact}}'),
                $quick('bi-telephone', '+90 555 000 00 00', 'tel:+905550000000'),
            ), array('_attrs' => array($attr('aria-label', lang('Visit the shop'))))),
            // Outside the navbar, so an icon of its own rather than
            // .navbar-toggler-icon, whose picture the navbar supplies.
            $el('button', 'btn btn-outline-secondary d-lg-none ms-auto', lang('Toggler'), array(
                $icon('bi-list', 'fs-5'),
            ), array('_attrs' => array(
                $attr('type', 'button'),
                $attr('data-bs-toggle', 'collapse'),
                $attr('data-bs-target', '#bk-nav'),
                $attr('aria-controls', 'bk-nav'),
                $attr('aria-expanded', 'false'),
                $attr('aria-label', lang('Toggle navigation')),
            ))),
        )),
        $el('nav', 'navbar navbar-expand-lg py-0 border-top border-bottom bg-body-tertiary', lang('Category Bar'), array(
            $el('div', 'container', lang('Category Bar Container'), array(
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $el('ul', 'navbar-nav w-100 column-gap-lg-3 py-2 py-lg-0', lang('Nav Menu'), $items),
                ), array('id' => 'bk-nav')),
            )),
        ), array('smartActive' => true, '_attrs' => array($attr('aria-label', lang('Shop categories'))))),
    ));
};

// The opening hours, as a definition list: the day, then the hours.
$hours = function ($class = 'row small mb-0') use ($el) {
    $days = array(
        array(lang('Monday–Friday'), '09:00–20:00'),
        array(lang('Saturday'), '10:00–20:00'),
        array(lang('Sunday'), '11:00–18:00'),
    );
    $children = array();
    foreach ($days as $d) {
        $children[] = $el('dt', 'col-6 fw-normal text-body-secondary', lang('Day'), array(), array('text' => $d[0]));
        $children[] = $el('dd', 'col-6 mb-2', lang('Time'), array(), array('text' => $d[1]));
    }
    return $el('dl', $class, lang('List'), $children);
};

// The footer, on a dark band: the opening hours, the links, how to reach
// the shop; under them the payment marks and the copyright.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $hours, $categories, $category_url, $attr) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $items);
    };
    $shop_links = array();
    foreach ($categories as $key => $c) $shop_links[] = $link($c['title'], $category_url($key), $plain);
    $shop_links[] = $link(lang('Everything in the shop'), '{{page:shop}}', $plain);
    $social = function ($name, $label) use ($el, $icon, $attr) {
        return $el('a', 'link-body-emphasis fs-5', $label, array($icon($name)), array('href' => '#', '_attrs' => array($attr('aria-label', $label))));
    };
    $contact_line = function ($icon_name, $child, $name) use ($el, $icon) {
        return $el('p', 'd-flex align-items-start gap-2 small mb-2', $name, array($icon($icon_name, 'text-primary'), $child));
    };

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-md-6 col-lg-4', lang('Opening hours'), array(
                    $heading('h2', lang('Opening hours'), 'h6 text-uppercase small fw-bold mb-3'),
                    $hours(),
                    $para(lang('Late opening until 21:00 on reading nights.'), 'small text-body-secondary mt-2 mb-0'),
                )),
                $el('div', 'col-12 col-md-6 col-lg-4', lang('Footer Links'), array(
                    $heading('h2', lang('Browse'), 'h6 text-uppercase small fw-bold mb-3'),
                    $el('div', 'row g-2', lang('Row'), array(
                        $el('div', 'col-6', lang('Column'), array($list($shop_links))),
                        $el('div', 'col-6', lang('Column'), array($list(array(
                            $link(lang('Events'), '{{page:events}}', $plain),
                            $link(lang('Book Club'), '{{page:book_club}}', $plain),
                            $link(lang('About'), '{{page:about}}', $plain),
                            $link(lang('Newsletter'), '{{page:newsletter}}', $plain),
                            $link(lang('My Account'), '{{page:my_account}}', $plain),
                        )))),
                    )),
                )),
                $el('div', 'col-12 col-lg-4', lang('Contact Details'), array(
                    $heading('h2', lang('Visit us'), 'h6 text-uppercase small fw-bold mb-3'),
                    $contact_line('bi-geo-alt', $span(lang('Sample Street 1, 34000 Istanbul, Türkiye'), '', lang('Text')), lang('Address')),
                    $contact_line('bi-telephone', $link('+90 555 000 00 00', 'tel:+905550000000', $plain), lang('Phone')),
                    $contact_line('bi-envelope', $link('hello@example.com', 'mailto:hello@example.com', $plain . ' text-break'), lang('Email')),
                    $el('div', 'd-flex gap-3 mt-3', lang('Social Links'), array(
                        $social('bi-instagram', 'Instagram'),
                        $social('bi-facebook', 'Facebook'),
                        $social('bi-youtube', 'YouTube'),
                    )),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $el('p', 'd-flex align-items-center gap-3 fs-4 text-body-secondary mb-0', lang('Payment Methods'), array(
                    $span(lang('We accept cards, PayPal and bank transfer.'), 'visually-hidden', lang('Text')),
                    $icon('bi-credit-card-2-front'),
                    $icon('bi-paypal'),
                    $icon('bi-bank'),
                )),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The gift card band: a full-width band in the palette's colour. Shared, so
// the home, events and about pages carry the same offer.
$gift_band = function () use ($el, $heading, $para, $link, $icon, $container, $row, $col, $category_url) {
    return $el('section', 'py-5 text-bg-primary', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $el('div', 'd-flex align-items-start gap-3', lang('Text'), array(
                        $icon('bi-gift', 'fs-1 lh-1'),
                        $el('div', '', lang('Words'), array(
                            $heading('h2', lang('Not sure what to give? Give a gift card.'), 'h3 fw-bold mb-2'),
                            $para(lang('Gift cards from $10, sent by e-mail or in an envelope from our counter, spent on anything in the shop.'), 'mb-0 opacity-75'),
                        )),
                    )),
                ), '', '8'),
                $col(array(
                    $link(lang('Choose a gift card'), $category_url('cards'), 'btn btn-light btn-lg px-4'),
                ), '', '4', '', '12', 'text-lg-end'),
            ), '4', '', 'align-items-center'),
        )),
    ));
};

// A page of the shop: the account bar, the shared header, the page's own
// content, the newsletter band (left off where it would be in the way: the
// cart, checkout, the account pages) and the shared footer.
$page = function ($main, $newsletter = true) use ($root, $account_bar, $shared, $el, $widget) {
    $children = array(
        $account_bar(),
        $shared('header'),
        $el('main', '', lang('Main Content'), $main),
    );
    if ($newsletter) $children[] = $widget('newsletter_band');
    $children[] = $shared('footer');
    return $root($children);
};

// The top of the inner pages: where the page sits, its title and one
// sentence, and whatever the page adds under them.
$page_header = function ($title, $lead, $more = array()) use ($el, $heading, $para, $link, $container, $attr) {
    return $el('header', 'py-4 py-lg-5 border-bottom', lang('Page Header'), array(
        $container(array_merge(array(
            $el('nav', '', lang('Breadcrumb'), array(
                $el('ol', 'breadcrumb small mb-2', lang('Breadcrumb List'), array(
                    $el('li', 'breadcrumb-item', lang('Breadcrumb Item'), array($link(lang('Home'), '{{page:home}}', 'link-secondary text-decoration-none'))),
                    $el('li', 'breadcrumb-item active', lang('Breadcrumb Item'), array(), array('text' => $title, '_attrs' => array($attr('aria-current', 'page')))),
                )),
            ), array('_attrs' => array($attr('aria-label', lang('Breadcrumb'))))),
            $heading('h1', $title, 'display-6 fw-bold mb-2'),
            $para($lead, 'lead text-body-secondary mb-0'),
        ), $more)),
    ));
};

// The heading over a shelf of the home page: a small line above, the title,
// a link to the rest of the shelf, and a rule under them.
$shelf_header = function ($eyebrow, $title, $more_text = '', $more_href = '') use ($el, $heading, $para, $icon, $span) {
    $children = array($el('div', '', lang('Section Title'), array(
        $para($eyebrow, 'small text-uppercase fw-semibold text-primary mb-1'),
        $heading('h2', $title, 'h3 fw-bold mb-0'),
    )));
    if ($more_text !== '') {
        $children[] = $el('a', 'd-inline-flex align-items-center gap-1 fw-semibold link-primary text-decoration-none text-nowrap', lang('Link'), array(
            $span($more_text, '', lang('Link Text')),
            $icon('bi-arrow-right'),
        ), array('href' => $more_href));
    }
    return $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-2 pb-3 mb-4 border-bottom', lang('Section Header'), $children);
};

// ── Product cards: three layouts for the home page's shelves ────────────

// The product's name, linked to its page. position-static lets the link
// stretch over the whole card rather than the heading alone.
$product_name = function ($tag, $class) use ($el, $link) {
    return $el($tag, trim($class . ' position-static'), lang('Product Name'), array(
        $link(lang('Hardback Novel'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
            array('_bindings' => array('text' => '__short_description', 'href' => '__detail_url'))),
    ));
};
$product_picture = function ($frame) use ($el, $image, $sample_product, $attr) {
    return $el('div', $frame, lang('Picture Frame'), array(
        $image($sample_product, lang('Product'), 'object-fit-cover', array(
            '_attrs'    => array($attr('loading', 'lazy')),
            '_bindings' => array('src' => '__image_url', 'alt' => '__short_description'),
        )),
    ));
};
$price = function ($class) use ($span, $bind) {
    return $span(lang('$24.00'), $class, lang('Price'), $bind('__price_formatted'));
};

// Bestseller: a horizontal card, the cover on the left, the name, the price
// and the button on the right, two to a row.
$card_bestseller = function () use ($el, $col, $icon, $span, $product_name, $product_picture, $price, $add_to_basket) {
    return $col(array(
        $el('div', 'card h-100 border-0 shadow-sm overflow-hidden', lang('Product Card'), array(
            $el('div', 'row g-0 h-100', lang('Card Row'), array(
                $el('div', 'col-4', lang('Cover'), array(
                    $product_picture('ratio ratio-1x1 h-100 bg-body-tertiary'),
                )),
                $el('div', 'col-8', lang('Details'), array(
                    $el('div', 'card-body d-flex flex-column h-100 p-3', lang('Card Body'), array(
                        $el('span', 'badge rounded-pill bg-secondary-subtle text-secondary-emphasis align-self-start d-inline-flex align-items-center gap-1 mb-2', lang('Badge'), array(
                            $icon('bi-star-fill'),
                            $span(lang('Bestseller'), '', lang('Text')),
                        )),
                        $product_name('h3', 'h6 card-title mb-2'),
                        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mt-auto', lang('Price and Button'), array(
                            $price('fw-bold'),
                            $add_to_basket('primary'),
                        )),
                    )),
                )),
            )),
        )),
    ), '6');
};

// New arrival: an upright card, a 4:3 picture over the words.
$card_new = function () use ($el, $col, $span, $product_name, $product_picture, $price, $add_to_basket) {
    return $col(array(
        $el('div', 'card h-100', lang('Product Card'), array(
            $product_picture('ratio ratio-4x3 bg-body-tertiary card-img-top overflow-hidden'),
            $el('div', 'card-body d-flex flex-column', lang('Card Body'), array(
                $span(lang('New in'), 'badge text-bg-primary align-self-start mb-2', lang('Badge')),
                $product_name('h3', 'h6 card-title mb-1'),
                $price('fw-semibold text-body-secondary mb-3'),
                $add_to_basket('primary', 'mt-auto w-100', true),
            )),
        )),
    ), '6', '3', '', '6');
};

// Stationery corner: small square cards, six to a row on a wide screen.
$card_compact = function () use ($el, $col, $product_name, $product_picture, $price, $add_to_basket) {
    return $col(array(
        $el('div', 'card h-100 border-0 bg-transparent', lang('Product Card'), array(
            $product_picture('ratio ratio-1x1 rounded-3 overflow-hidden bg-body-tertiary mb-2'),
            $product_name('h3', 'small fw-semibold text-truncate mb-1'),
            $price('small text-body-secondary mb-2'),
            $add_to_basket('secondary', 'mt-auto w-100', true),
        )),
    ), '4', '2', '', '6');
};

// A home page shelf: the products of one category, in one of the layouts
// above, without the search, the sorting or the pages.
$home_listing = function ($group, $card, $count) use ($widget_root, $row, $loop) {
    return array(
        'page'     => 'home',
        'requires' => 'ecommerce',
        'slug'     => lang('catalog'),
        'config'   => array(
            'regionType'               => 'catalog_listing',
            'product_group_id'         => '{{product_group:' . $group . '}}',
            'group_navigation'         => 'none',
            'items_per_page'           => $count,
            'max_results'              => $count,
            'order_by_field'           => 'sort_order',
            'order_by_direction'       => 'ASC',
            'detail_page_id'           => '{{tab:product}}',
            'add_to_cart_stay_on_page' => true,
            // The notice after an add names this shop's cart, not the one
            // the visitor happened to see last on a site with two shops.
            'add_to_cart_next_page_id' => '{{tab:cart}}',
            'empty_message'            => lang('Product not found.'),
        ),
        'tree'     => $widget_root(array(
            $row(array($loop(array($card()))), '4'),
        )),
    );
};

// ── Form fields ─────────────────────────────────────────────────────────
$field = function ($id, $label, $control, $class = 'mb-3') use ($el, $attr) {
    return $el('div', $class, $label, array(
        $el('label', 'form-label', lang('Label'), array(), array('text' => $label, '_attrs' => array($attr('for', $id)))),
        $control,
    ));
};
$input = function ($id, $type, $name, $autocomplete = '', $required = true, $cf = array()) use ($el, $attr) {
    $attrs = array($attr('type', $type), $attr('name', $name));
    if ($required) $attrs[] = $attr('required', '');
    if ($autocomplete !== '') $attrs[] = $attr('autocomplete', $autocomplete);
    $props = array('id' => $id, '_attrs' => $attrs);
    if ($cf) $props['_cf'] = $cf;
    return $el('input', 'form-control', lang('Field'), array(), $props);
};
$textarea = function ($id, $name, $rows, $required = true) use ($el, $attr) {
    $attrs = array($attr('name', $name), $attr('rows', (string)$rows));
    if ($required) $attrs[] = $attr('required', '');
    return $el('textarea', 'form-control', lang('Field'), array(), array('id' => $id, '_attrs' => $attrs));
};
// A pick list: the first, empty option is the prompt, not an answer. Each
// answer's value is its own words, so the e-mails and the subject line read
// as the visitor chose.
$select = function ($id, $name, $prompt, $options, $required = true) use ($el, $attr) {
    $children = array($el('option', '', lang('Option'), array(), array('text' => $prompt, '_attrs' => array($attr('value', '')))));
    foreach ($options as $text) {
        $children[] = $el('option', '', lang('Option'), array(), array('text' => $text, '_attrs' => array($attr('value', $text))));
    }
    $attrs = array($attr('name', $name));
    if ($required) $attrs[] = $attr('required', '');
    return $el('select', 'form-select', lang('Field'), $children, array('id' => $id, '_attrs' => $attrs));
};
$submit = function ($text, $class = 'btn btn-primary px-4') use ($el, $attr) {
    return $el('button', $class, lang('Send Button'), array(), array('text' => $text, '_attrs' => array($attr('type', 'submit'))));
};
$captcha = function () use ($el) {
    return $el('div', 'mb-3', lang('CAPTCHA'), array(), array('_bindings' => array('section' => 'captcha')));
};
// The consent box under the newsletter's e-mail field: what was agreed to
// is stored with the sign-up.
$consent = function ($id) use ($el, $attr) {
    return $el('div', 'form-check small', lang('Consent'), array(
        $el('input', 'form-check-input', lang('Field'), array(), array('id' => $id, '_attrs' => array(
            $attr('type', 'checkbox'), $attr('name', 'consent'), $attr('value', 'yes'), $attr('required', ''),
        ))),
        $el('label', 'form-check-label', lang('Label'), array(), array(
            'text'   => lang('I would like to receive the bookshop letter by e-mail. I can unsubscribe at any time.'),
            '_attrs' => array($attr('for', $id)),
        )),
    ));
};
// The newsletter's e-mail field and button, in one line. The address is the
// contact's, so the sign-up becomes a contact of the newsletter's group.
$newsletter_line = function ($id) use ($el, $attr, $submit) {
    return $el('div', 'input-group mb-3', lang('E-mail and Button'), array(
        $el('label', 'visually-hidden', lang('Label'), array(), array('text' => lang('Email'), '_attrs' => array($attr('for', $id)))),
        $el('input', 'form-control', lang('Field'), array(), array('id' => $id, '_cf' => array('contact_field' => 'email_address'), '_attrs' => array(
            $attr('type', 'email'), $attr('name', 'email'), $attr('required', ''), $attr('autocomplete', 'email'),
            $attr('placeholder', lang('Your e-mail address')),
        ))),
        $submit(lang('Subscribe')),
    ));
};

// ── Pages ───────────────────────────────────────────────────────────────

$pages = array();

// Home: the staff picks, the shelves, the coming events and the gift cards.

// One of the three small staff picks beside the big one: a square picture
// and why the staff love it.
$pick = function ($key, $eyebrow, $title, $quote, $who) use ($el, $heading, $para, $image, $category_url, $attr) {
    return $el('a', 'card flex-fill border-0 shadow-sm overflow-hidden text-decoration-none link-body-emphasis', lang('Card'), array(
        $el('div', 'row g-0 h-100 align-items-center', lang('Card Row'), array(
            $el('div', 'col-4 col-sm-3 col-lg-4', lang('Cover'), array(
                $el('div', 'ratio ratio-1x1 bg-body-tertiary', lang('Picture Frame'), array(
                    $image('{{product_group_image:' . $key . '}}', $title, 'object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
                )),
            )),
            $el('div', 'col-8 col-sm-9 col-lg-8', lang('Details'), array(
                $el('div', 'card-body py-2 px-3', lang('Card Body'), array(
                    $para($eyebrow, 'small text-primary fw-semibold mb-1'),
                    $heading('h2', $title, 'h6 fw-bold mb-1'),
                    $para($quote, 'small fst-italic text-body-secondary mb-1'),
                    $para($who, 'small text-body-secondary mb-0'),
                )),
            )),
        )),
    ), array('href' => $category_url($key)));
};

$category_tiles = array();
foreach ($categories as $key => $c) {
    $category_tiles[] = $el('a', 'col-6 col-lg-3 text-decoration-none link-body-emphasis', $c['title'], array(
        $el('div', 'h-100 d-flex flex-column rounded-4 p-3 p-lg-4 ' . $c['tint'], lang('Category Card'), array(
            $icon($c['icon'], 'display-6 text-primary mb-3'),
            $heading('h3', $c['title'], 'h5 fw-bold mb-2'),
            $para($c['text'], 'small text-body-secondary mb-3'),
            $el('span', 'd-inline-flex align-items-center gap-1 fw-semibold small text-primary mt-auto', lang('Link Text'), array(
                $span(lang('Browse'), '', lang('Text')),
                $icon('bi-arrow-right'),
            )),
        )),
    ), array('href' => $category_url($key)));
}

// The first three events, as small cards.
$event_cards = array();
foreach (array_slice($events, 0, 3) as $e) {
    $event_cards[] = $col(array(
        $el('div', 'card h-100 border-0 shadow-sm', lang('Event'), array(
            $el('div', 'card-body d-flex gap-3', lang('Card Body'), array(
                $date_box($e, 'align-self-start'),
                $el('div', '', lang('Event Details'), array(
                    $para($e['type'], 'small text-uppercase fw-semibold text-primary mb-1'),
                    $heading('h3', $e['title'], 'h6 fw-bold mb-2'),
                    $el('p', 'd-flex flex-wrap column-gap-2 small text-body-secondary mb-0', lang('Meta'), array(
                        $span($e['weekday'], '', lang('Weekday')),
                        $span($e['time'], 'fw-semibold', lang('Time')),
                        $span($e['place'], '', lang('Location')),
                    )),
                )),
            )),
        )),
    ), '', '4');
}

$pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('Books, notebooks, pens and cards, picked by booksellers who read, with events and a book club.'),
    'tree'             => $page(array(
        // Staff picks: the book of the week on a large panel, three smaller
        // picks beside it.
        $el('section', 'py-4 py-lg-5', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'h-100 d-flex flex-column rounded-4 p-4 p-lg-5 text-bg-primary', lang('Staff Pick'), array(
                            $el('span', 'badge rounded-pill text-bg-light align-self-start d-inline-flex align-items-center gap-1 mb-3', lang('Badge'), array(
                                $icon('bi-bookmark-heart'),
                                $span(lang('Staff picks of the week'), '', lang('Text')),
                            )),
                            $heading('h1', lang('Books we cannot stop talking about'), 'display-6 fw-bold mb-3'),
                            $para(lang('Every week the people behind the counter choose the books, notebooks and pens they would press into your hands.'), 'lead opacity-75 mb-4'),
                            $el('div', 'd-flex align-items-center gap-3 rounded-3 p-3 mb-4 bg-white bg-opacity-10', lang('Featured'), array(
                                $el('div', 'flex-shrink-0 w-25', lang('Cover'), array(
                                    $el('div', 'ratio ratio-1x1 rounded-3 overflow-hidden', lang('Picture Frame'), array(
                                        $image('{{product_group_image:books}}', lang('The Lighthouse Year'), 'object-fit-cover'),
                                    )),
                                )),
                                $el('div', '', lang('Text'), array(
                                    $para(lang('Book of the week'), 'small text-uppercase fw-semibold opacity-75 mb-1'),
                                    $heading('h2', lang('The Lighthouse Year'), 'h4 fw-bold mb-1'),
                                    $para(lang('by Jane Cooper · “Quiet, funny and impossible to put down.”'), 'small mb-0 opacity-75'),
                                )),
                            )),
                            $el('div', 'd-flex flex-wrap gap-2 mt-auto', lang('Buttons'), array(
                                $link(lang('See the bestsellers'), $category_url('books'), 'btn btn-light px-4'),
                                $link(lang('Join the book club'), '{{page:book_club}}', 'btn btn-outline-light px-4'),
                            )),
                        )),
                    ), '', '7'),
                    $col(array(
                        $el('div', 'd-flex flex-column gap-3 h-100', lang('More Picks'), array(
                            $pick('notebooks', lang('Notebooks & Journals'), lang('Dotted Journal A5'),
                                lang('“Paper that takes a fountain pen without a shadow on the back.”'), lang('Picked by Alex, stationery')),
                            $pick('pens', lang('Pens & Art'), lang('Brush Pen Set'),
                                lang('“The set our lettering workshop starts with, and the one we keep at home.”'), lang('Picked by Sam, workshops')),
                            $pick('cards', lang('Cards & Gifts'), lang('Letterpress Card Set'),
                                lang('“Pressed by hand; people keep these cards long after the birthday.”'), lang('Picked by Jane, the counter')),
                        )),
                    ), '', '5'),
                ), '4'),
            )),
        )),

        // The four categories, as tinted boxes.
        $el('section', 'pb-5', lang('Categories'), array(
            $container(array(
                $shelf_header(lang('The shelves'), lang('What are you looking for?'), lang('Everything in the shop'), '{{page:shop}}'),
                $el('div', 'row g-3 g-lg-4', lang('Row'), $category_tiles),
            )),
        )),

        // Books: the bestsellers, side by side.
        $el('section', 'py-5 bg-body-tertiary', lang('Bestsellers'), array(
            $container(array(
                $shelf_header(lang('Bestsellers'), lang('The books everyone is buying'), lang('All books'), $category_url('books')),
                $widget('home_bestsellers'),
            )),
        )),

        // Notebooks: the new arrivals, upright cards.
        $el('section', 'py-5', lang('New arrivals'), array(
            $container(array(
                $shelf_header(lang('New arrivals'), lang('Fresh notebooks and journals'), lang('All notebooks'), $category_url('notebooks')),
                $widget('home_new'),
            )),
        )),

        $shared('gift_band'),

        // Pens & Art: the stationery corner, small square cards.
        $el('section', 'py-5', lang('Stationery corner'), array(
            $container(array(
                $shelf_header(lang('Stationery corner'), lang('Pens, pencils and paints'), lang('All pens & art'), $category_url('pens')),
                $widget('home_stationery'),
            )),
        )),

        // The coming events, three of them, and the way to the rest.
        $el('section', 'py-5 bg-body-tertiary', lang('Events'), array(
            $container(array(
                $shelf_header(lang('In the shop'), lang('Upcoming events'), lang('All events'), '{{page:events}}'),
                $row($event_cards, '4'),
            )),
        )),
    )),
);

// The shop: every category, opened in place, with the search, the sorting
// and the filters.
$chips = array();
foreach ($categories as $key => $c) {
    $chips[] = $el('li', '', lang('List Item'), array($link($c['title'], $category_url($key), 'btn btn-sm btn-outline-secondary rounded-pill')));
}
$pages[] = array(
    'key'              => 'shop',
    'requires'         => 'ecommerce',
    'name'             => lang('shop'),
    'folder'           => 'public',
    'title'            => lang('Shop'),
    'meta_description' => lang('Every book, notebook, pen and card in the shop, ready to order online or collect.'),
    'tree'             => $page(array(
        $page_header(lang('Shop'), lang('Every book, notebook, pen and card in the shop. Search, sort and filter to find yours.'), array(
            $el('ul', 'list-unstyled d-flex flex-wrap gap-2 mt-3 mb-0', lang('Categories'), $chips),
        )),
        $widget('shop_catalog'),
    )),
);

// One product: the page every product's link opens, with the shop's
// promises under it.
$promise = function ($icon_name, $title, $text) use ($el, $icon, $para, $col) {
    return $col(array(
        $el('div', 'd-flex align-items-start gap-3', lang('Benefit'), array(
            $icon($icon_name, 'fs-3 lh-1 text-primary'),
            $el('div', '', lang('Text'), array(
                $para($title, 'fw-semibold mb-1'),
                $para($text, 'small text-body-secondary mb-0'),
            )),
        )),
    ), '4');
};
$pages[] = array(
    'key'              => 'product',
    'requires'         => 'ecommerce',
    'name'             => lang('product-detail'),
    'folder'           => 'public',
    'title'            => lang('Product'),
    'meta_description' => lang('Product details, options and price.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('product_detail'),
        $el('section', 'py-5 border-top bg-body-tertiary', lang('Benefits'), array(
            $container(array(
                $row(array(
                    $promise('bi-shop', lang('Click & collect'), lang('Order online and pick it up at the counter the same day.')),
                    $promise('bi-gift', lang('Gift wrapping'), lang('Ask at checkout and we wrap it in kraft paper with a ribbon.')),
                    $promise('bi-book', lang('Any book, ordered in'), lang('Not on our shelves? We order it for you, usually within three days.')),
                ), '4'),
            )),
        )),
    )),
);
$pages[] = array(
    'key'      => 'cart',
    'requires' => 'ecommerce',
    'name'     => lang('cart'),
    'folder'   => 'public',
    'title'    => lang('Shopping Cart'),
    'search'   => false,
    'sitemap'  => false,
    'noindex'  => true,
    'tree'     => $page(array($widget('cart')), false),
);
$pages[] = array(
    'key'      => 'checkout',
    'requires' => 'ecommerce',
    'name'     => lang('checkout'),
    'folder'   => 'public',
    'title'    => lang('Checkout'),
    'search'   => false,
    'sitemap'  => false,
    'noindex'  => true,
    'tree'     => $page(array($widget('checkout')), false),
);
$pages[] = array(
    'key'      => 'order',
    'requires' => 'ecommerce',
    'name'     => lang('order-summary'),
    'folder'   => 'public',
    'title'    => lang('Order Summary'),
    'search'   => false,
    'sitemap'  => false,
    'noindex'  => true,
    'tree'     => $page(array($widget('order_view')), false),
);

// Events: every coming event in a list, each with the way to reserve a seat
// (a message from the contact page).
$event_rows = array();
foreach ($events as $e) {
    $event_rows[] = $el('div', 'list-group-item p-3 p-lg-4', lang('Event'), array(
        $el('div', 'd-flex flex-column flex-sm-row gap-3', lang('Event Row'), array(
            $date_box($e, 'align-self-start'),
            $el('div', 'flex-grow-1', lang('Event Details'), array(
                $span($e['type'], 'badge bg-secondary-subtle text-secondary-emphasis mb-2', lang('Badge')),
                $heading('h3', $e['title'], 'h5 fw-bold mb-1'),
                $para($e['text'], 'text-body-secondary mb-2'),
                $el('p', 'd-flex flex-wrap column-gap-3 row-gap-1 small mb-0', lang('Meta'), array(
                    $el('span', 'd-inline-flex align-items-center gap-1', lang('When'), array($icon('bi-clock', 'text-primary'), $span($e['weekday'], '', lang('Weekday')), $span($e['time'], '', lang('Time')))),
                    $el('span', 'd-inline-flex align-items-center gap-1', lang('Location'), array($icon('bi-geo-alt', 'text-primary'), $span($e['place'], '', lang('Text')))),
                )),
            )),
            $el('div', 'flex-shrink-0 align-self-sm-center', lang('Action'), array(
                $el('a', 'btn btn-sm btn-outline-primary', lang('Link'), array(
                    $span(lang('Reserve a seat'), '', lang('Link Text')),
                    $span($e['title'], 'visually-hidden', lang('Name')),
                ), array('href' => '{{page:contact}}')),
            )),
        )),
    ));
}
$pages[] = array(
    'key'              => 'events',
    'name'             => lang('events'),
    'folder'           => 'public',
    'title'            => lang('Events'),
    'meta_description' => lang('Reading nights, signings, workshops and the book club: what is on in the shop.'),
    'tree'             => $page(array(
        $page_header(lang('Events'), lang('Reading nights, signings, workshops and the book club. Seats are free; reserve one so we keep a chair for you.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Coming up'), 'h4 fw-bold mb-3'),
                        $el('div', 'list-group', lang('List'), $event_rows),
                    ), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('Info'), array(
                            $heading('h2', lang('How to reserve a seat'), 'h5 fw-bold mb-3'),
                            $el('ol', 'small text-body-secondary ps-3 mb-0', lang('List'), array(
                                $el('li', 'mb-2', lang('Step'), array(), array('text' => lang('Press Reserve a seat beside the event.'))),
                                $el('li', 'mb-2', lang('Step'), array(), array('text' => lang('Write the event and how many seats you need.'))),
                                $el('li', '', lang('Step'), array(), array('text' => lang('We answer by e-mail to confirm your seats.'))),
                            )),
                        )),
                        $el('div', 'p-4 rounded-4 border', lang('Call to Action'), array(
                            $heading('h2', lang('A reading of your own?'), 'h5 fw-bold mb-2'),
                            $para(lang('The reading room seats thirty. Schools, clubs and launches can book it in the evenings.'), 'small text-body-secondary mb-3'),
                            $link(lang('Ask about the room'), '{{page:contact}}', 'btn btn-sm btn-primary'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
        $shared('gift_band'),
    )),
);

// The book club: how it works, this month's book, the questions people ask
// and the sign-up. The form tells the staff and welcomes the new member
// with its own e-mail.
$step = function ($n, $title, $text) use ($el, $heading, $para, $span) {
    return $el('li', 'd-flex gap-3 mb-4', lang('Step'), array(
        $span((string)$n, 'badge rounded-pill text-bg-primary fs-6 align-self-start', lang('Step Number')),
        $el('div', '', lang('Text'), array(
            $heading('h3', $title, 'h6 fw-bold mb-1'),
            $para($text, 'text-body-secondary mb-0'),
        )),
    ));
};
$faq = function ($n, $question, $answer) use ($el, $para, $attr) {
    $id = 'bk-faq-' . $n;
    return $el('div', 'accordion-item', lang('Question'), array(
        $el('h3', 'accordion-header', lang('Question Header'), array(
            $el('button', 'accordion-button collapsed', lang('Question'), array(), array(
                'text'   => $question,
                '_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#' . $id),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-controls', $id),
                ),
            )),
        )),
        $el('div', 'accordion-collapse collapse', lang('Answer'), array(
            $el('div', 'accordion-body text-body-secondary', lang('Body'), array($para($answer, 'mb-0'))),
        ), array('id' => $id, '_attrs' => array($attr('data-bs-parent', '#bk-faq')))),
    ));
};
$pages[] = array(
    'key'              => 'book_club',
    'name'             => lang('book-club'),
    'folder'           => 'public',
    'title'            => lang('Book Club'),
    'meta_description' => lang('One book a month, three groups to choose from and a discount on the book. Join the book club.'),
    'form'             => array(
        'form_name'            => lang('Book Club'),
        'confirmation_message' => lang('Welcome to the book club! We have sent you an e-mail with the details.'),
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New book club member: ^^first_name^^ ^^last_name^^'),
        'confirm_email'        => 1,
        'confirm_subject'      => lang('Welcome to the book club'),
        'confirm_page_id'      => '{{tab:email_club}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Book Club'), lang('One book a month, read at your own pace and talked over together. Anyone can join.')),
        $el('section', 'py-5', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('How the club works'), 'h3 fw-bold mb-4'),
                        $el('ol', 'list-unstyled mb-0', lang('List'), array(
                            $step(1, lang('Choose a group'), lang('Thursday evenings or Saturday mornings in the shop, or the online group on a video call.')),
                            $step(2, lang('Read the month\'s book'), lang('We pick one book a month; members get 10% off it at the counter.')),
                            $step(3, lang('Come and talk it over'), lang('An hour and a half of conversation, coffee and cake. Finished the book or not, you are welcome.')),
                        )),
                    ), '', '7'),
                    $col(array(
                        $el('div', 'card border-0 shadow-sm overflow-hidden', lang('Card'), array(
                            $el('div', 'row g-0', lang('Card Row'), array(
                                $el('div', 'col-5', lang('Cover'), array(
                                    $el('div', 'ratio ratio-1x1 h-100 bg-body-tertiary', lang('Picture Frame'), array(
                                        $image('{{product_group_image:books}}', lang('The Lighthouse Year'), 'object-fit-cover'),
                                    )),
                                )),
                                $el('div', 'col-7', lang('Details'), array(
                                    $el('div', 'card-body', lang('Card Body'), array(
                                        $span(lang('This month\'s pick'), 'badge text-bg-primary mb-2', lang('Badge')),
                                        $heading('h3', lang('The Lighthouse Year'), 'h5 fw-bold mb-1'),
                                        $para(lang('by Jane Cooper'), 'small text-body-secondary mb-2'),
                                        $el('p', 'd-flex align-items-start gap-2 small mb-3', lang('Meeting'), array(
                                            $icon('bi-calendar-event', 'text-primary'),
                                            $span(lang('We meet on Thursday 29 October at 19:00.'), '', lang('Text')),
                                        )),
                                        $link(lang('Find it in the shop'), $category_url('books'), 'btn btn-sm btn-primary'),
                                    )),
                                )),
                            )),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),
        $el('section', 'py-5 border-top', lang('Frequently Asked Questions'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Questions'), 'small text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('Before you join'), 'h3 fw-bold mb-3'),
                        $para(lang('Something else on your mind? Ask at the counter or write to us.'), 'text-body-secondary mb-0'),
                    ), '', '4'),
                    $col(array(
                        $el('div', 'accordion', lang('FAQ'), array(
                            $faq(1, lang('Does it cost anything to join?'), lang('No. The club is free; you only buy the book, with 10% off as a member.')),
                            $faq(2, lang('What if I have not finished the book?'), lang('Come anyway. We warn before the ending is talked about, and many members finish it afterwards.')),
                            $faq(3, lang('Can I change groups later?'), lang('Yes. Tell us at the counter or by e-mail and we move you to the other group.')),
                            $faq(4, lang('Who chooses the books?'), lang('The staff suggest three books each season and the members vote for the order.')),
                        ), array('id' => 'bk-faq')),
                    ), '', '8'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
        $el('section', 'py-5 bg-body-tertiary', lang('Join'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array(
                                $heading('h2', lang('Join the book club'), 'h3 fw-bold mb-2'),
                                $para(lang('Leave your name and choose a group; the welcome e-mail tells you when we meet.'), 'text-body-secondary mb-4'),
                                $widget('club_form'),
                            )),
                        )),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// About: the shop's story, what it offers and the people behind the counter.
$value = function ($icon_name, $title, $text) use ($el, $icon, $heading, $para, $col) {
    return $col(array(
        $el('div', 'h-100 p-4 rounded-4 border', lang('Feature'), array(
            $icon($icon_name, 'fs-2 text-primary d-block mb-3'),
            $heading('h3', $title, 'h5 fw-bold mb-2'),
            $para($text, 'text-body-secondary mb-0'),
        )),
    ), '', '4');
};
$person = function ($name, $role, $reading, $initials) use ($el, $heading, $para, $image, $col) {
    return $col(array(
        $el('div', 'd-flex align-items-center gap-3', lang('Person'), array(
            $image('https://placehold.co/96x96/3f6212/ffffff?text=' . $initials, $name, 'rounded-circle flex-shrink-0', array('width' => '72', 'height' => '72')),
            $el('div', '', lang('Text'), array(
                $heading('h3', $name, 'h6 fw-bold mb-0'),
                $para($role, 'small text-primary mb-1'),
                $para($reading, 'small text-body-secondary mb-0'),
            )),
        )),
    ), '6', '4');
};
$pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About Us'),
    'meta_description' => lang('A neighbourhood bookshop and stationery store, run by people who read.'),
    'tree'             => $page(array(
        $page_header(lang('About Us'), lang('A neighbourhood bookshop and stationery store, run by people who read.')),
        $el('section', 'py-5', lang('Our Story'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Our story'), 'small text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('A shop on the corner, full of books, paper and pens'), 'h3 fw-bold mb-3'),
                        $para(lang('We opened with two walls of books and a drawer of fountain pens. The walls have multiplied, the drawer has become a corner, and we still read everything we recommend.'), 'text-body-secondary'),
                        $para(lang('Come in to browse, to sit in the reading room, or to ask for the book you half remember. If it exists, we will find it.'), 'text-body-secondary mb-0'),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'ratio ratio-4x3 rounded-4 overflow-hidden bg-body-tertiary', lang('Picture Frame'), array(
                            $image('https://picsum.photos/seed/pinegrap-bookshop-shelves/1200/900', lang('Shelves of books in the shop'), 'object-fit-cover'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),
        $el('section', 'pb-5', lang('Features'), array(
            $container(array(
                $row(array(
                    $value('bi-chat-quote', lang('Booksellers who read'), lang('Ask what to read next; the answer comes from someone who has read it.')),
                    $value('bi-truck', lang('Any book, ordered in'), lang('Not on our shelves? We order it for you, usually within three days.')),
                    $value('bi-palette', lang('A table for workshops'), lang('Lettering, sketching and journaling evenings, with the materials on the table.')),
                ), '4'),
            )),
        )),
        $el('section', 'py-5 border-top', lang('Our Team'), array(
            $container(array(
                $heading('h2', lang('Behind the counter'), 'h3 fw-bold mb-4'),
                $row(array(
                    $person(lang('Jane Cooper'), lang('Owner, fiction'), lang('Reading now: a family saga in three volumes.'), 'JC'),
                    $person(lang('Alex Morgan'), lang('Notebooks and paper'), lang('Reading now: a history of the pencil.'), 'AM'),
                    $person(lang('Sam Lee'), lang('Workshops and art supplies'), lang('Reading now: a book of short poems.'), 'SL'),
                ), '4'),
            )),
        )),
        $shared('gift_band'),
    )),
);

// Contact: a message to the shop; the staff are told, the sender gets a
// reply. Seats for the events are reserved here too.
$pages[] = array(
    'key'              => 'contact',
    'name'             => lang('contact-us'),
    'folder'           => 'public',
    'title'            => lang('Contact us'),
    'meta_description' => lang('Questions about an order, a book or an event? Write to us or come by the shop.'),
    'form'             => array(
        'form_name'            => lang('Contact Form'),
        'confirmation_message' => lang('Thank you, your message has reached us. We will get back to you soon.'),
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New message: ^^topic^^'),
        'notify_page_id'       => '{{tab:email_new_message}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your message'),
        'confirm_page_id'      => '{{tab:email_message_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Contact us'), lang('A question about an order, a book you are looking for, a seat at an event: write to us.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('contact_form')), '', '7'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('Contact Details'), array(
                            $heading('h2', lang('Visit the shop'), 'h5 fw-bold mb-3'),
                            $el('p', 'd-flex align-items-start gap-2 mb-2', lang('Address'), array(
                                $icon('bi-geo-alt', 'text-primary'),
                                $span(lang('Sample Street 1, 34000 Istanbul, Türkiye'), '', lang('Text')),
                            )),
                            $el('p', 'd-flex align-items-center gap-2 mb-2', lang('Phone'), array(
                                $icon('bi-telephone', 'text-primary'),
                                $link('+90 555 000 00 00', 'tel:+905550000000', 'link-body-emphasis text-decoration-none'),
                            )),
                            $el('p', 'd-flex align-items-center gap-2 mb-4', lang('Email'), array(
                                $icon('bi-envelope', 'text-primary'),
                                $link('hello@example.com', 'mailto:hello@example.com', 'link-body-emphasis text-decoration-none'),
                            )),
                            $heading('h3', lang('Opening hours'), 'h6 fw-bold mb-2'),
                            $hours(),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    )),
);

// The newsletter: its page owns the form. What is sent adds the address to
// the newsletter's contact group, and the subscriber gets the welcome
// e-mail.
$pages[] = array(
    'key'              => 'newsletter',
    'name'             => lang('newsletter'),
    'folder'           => 'public',
    'title'            => lang('Newsletter'),
    'meta_description' => lang('New books, events and staff picks, once a month in your inbox.'),
    'form'             => array(
        'form_name'            => lang('Newsletter'),
        'confirmation_message' => lang('Thank you! You are now subscribed to our newsletter.'),
        'contact_group_id'     => '{{contact_group:newsletter}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('Welcome to the bookshop letter'),
        'confirm_page_id'      => '{{tab:email_welcome}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Newsletter'), lang('New books, events and staff picks, once a month in your inbox.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 p-lg-5 rounded-4 border border-2', lang('Form Card'), array($widget('newsletter_form'))),
                    ), '', '7'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('What You Get'), array(
                            $heading('h2', lang('In every letter'), 'h5 fw-bold mb-3'),
                            $el('ul', 'list-unstyled mb-0', lang('Check List'), array(
                                $el('li', 'd-flex gap-2 mb-2', lang('List Item'), array($icon('bi-bookmark-check', 'text-primary'), $span(lang('The staff picks of the month'), '', lang('Text')))),
                                $el('li', 'd-flex gap-2 mb-2', lang('List Item'), array($icon('bi-bookmark-check', 'text-primary'), $span(lang('The events, before the seats are gone'), '', lang('Text')))),
                                $el('li', 'd-flex gap-2 mb-0', lang('List Item'), array($icon('bi-bookmark-check', 'text-primary'), $span(lang('One e-mail a month, never your address to anyone else'), '', lang('Text')))),
                            )),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// Signing in and out.
$sign_page = function ($key, $name, $title, $widget_key) use ($page, $widget) {
    return array(
        'key'     => $key,
        'name'    => $name,
        'folder'  => 'public',
        'title'   => $title,
        'search'  => false,
        'sitemap' => false,
        'tree'    => $page(array($widget($widget_key)), false),
    );
};
$pages[] = $sign_page('login', lang('login'), lang('Log In'), 'login');
$pages[] = $sign_page('register', lang('register'), lang('Sign Up'), 'register');
$pages[] = $sign_page('forgot_password', lang('forgot-password'), lang('Forgot Password'), 'forgot_password');
$pages[] = $sign_page('set_password', lang('set-password'), lang('Set Password'), 'set_password');
$pages[] = $sign_page('logout', lang('logout'), lang('Logout'), 'logout');

// My account: the customer's own pages, behind the registration folder.
$pages[] = array(
    'key'     => 'my_account',
    'name'    => lang('my-account'),
    'folder'  => 'registration',
    'title'   => lang('My Account'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array($widget('my_account')), false),
);
$pages[] = array_merge($sign_page('profile', lang('my-profile'), lang('My Profile'), 'profile'), array('folder' => 'registration'));
$pages[] = array_merge($sign_page('change_password', lang('change-password'), lang('Change Password'), 'change_password'), array('folder' => 'registration'));
$pages[] = array_merge($sign_page('email_preferences', lang('email-preferences'), lang('Email Preferences'), 'email_preferences'), array('folder' => 'registration'));
$pages[] = array_merge($sign_page('address_book', lang('address-book'), lang('Address Book'), 'address_book'), array('folder' => 'registration', 'requires' => 'ecommerce'));

// The site's error page.
$pages[] = array(
    'key'     => 'error',
    'name'    => lang('page-not-found'),
    'folder'  => 'public',
    'title'   => lang('Page Not Found'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'tree'    => $page(array($widget('error_page')), false),
);

// ── E-mail pages ────────────────────────────────────────────────────────
// Sent as the body of an e-mail, never visited. E-mail programs drop linked
// stylesheets, so the few rules that shape them are written on the elements
// next to the Bootstrap classes.
$mail_style = function ($css) use ($attr) {
    return array('_attrs' => array($attr('style', $css)));
};
$mail_page = function ($children) use ($el, $shared, $mail_style) {
    return array('type' => 'root', 'props' => array('cssClass' => ''), 'children' => array(
        $el('div', 'bg-body-tertiary py-4 px-3', lang('E-mail Frame'), array(
            $el('div', 'mx-auto bg-body border rounded-3 overflow-hidden', lang('E-mail Card'), array(
                $shared('email_header'),
                $el('div', 'p-4', lang('E-mail Body'), $children, $mail_style('padding:24px')),
                $shared('email_footer'),
            ), $mail_style('max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #e7e2d6;border-radius:10px;overflow:hidden')),
        ), $mail_style('background:#f6f2e9;padding:24px 12px;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#1f2a1c')),
    ));
};
$mail_heading = function ($text) use ($heading, $attr) {
    return $heading('h1', $text, 'h4 fw-bold mb-3', array('_attrs' => array($attr('style', 'font-size:22px;font-weight:700;margin:0 0 16px;color:#1f2a1c'))));
};
$mail_para = function ($text, $class = 'mb-3') use ($para, $attr) {
    return $para($text, $class, array('_attrs' => array($attr('style', 'margin:0 0 16px'))));
};
$mail_button = function ($text, $href) use ($el, $link, $mail_style, $attr) {
    return $el('p', 'my-4', lang('Button'), array(
        $link($text, $href, 'btn btn-primary px-4', array($attr('style',
            'display:inline-block;padding:10px 22px;border-radius:6px;font-weight:600;text-decoration:none;'
            . 'background-color:#3f6212;background-color:var(--bs-primary);color:#ffffff;color:var(--pg-on-primary,#ffffff)'))),
    ), $mail_style('margin:24px 0'));
};
$mail_line = function ($label, $values) use ($el, $span, $mail_style, $attr) {
    return $el('p', 'pg-hide-if-empty mb-1', lang('Line'), array(
        $span($label . ': ', 'text-body-secondary', lang('Label'), array('_attrs' => array($attr('style', 'color:#6b6a5e')))),
        $el('span', 'pg-field-value fw-semibold', lang('Value'), $values),
    ), $mail_style('margin:0 0 4px'));
};
$mail_box = function ($children, $name) use ($el, $mail_style) {
    return $el('div', 'bg-body-tertiary rounded-3 p-3 my-3', $name, $children,
        $mail_style('background:#f6f2e9;border-radius:8px;padding:16px;margin:16px 0'));
};
$mail_greeting = function () use ($el, $span, $bind, $mail_style) {
    return $el('p', 'mb-3', lang('Greeting'), array(
        $span(lang('Hello') . ' ', '', lang('Text')),
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
    ), $mail_style('margin:0 0 16px'));
};

$mail_settings = array('folder' => 'private', 'search' => false, 'sitemap' => false, 'noindex' => true);

// The contact form's notification: what the staff receive for a message.
$pages[] = array_merge($mail_settings, array(
    'key'   => 'email_new_message',
    'name'  => lang('email-new-message'),
    'title' => lang('New Message E-mail'),
    'tree'  => $mail_page(array($widget('email_message_admin'))),
));
// The contact form's reply to the sender.
$pages[] = array_merge($mail_settings, array(
    'key'   => 'email_message_received',
    'name'  => lang('email-message-received'),
    'title' => lang('Message Received E-mail'),
    'tree'  => $mail_page(array($widget('email_message_sender'))),
));
// The newsletter's welcome, sent to a new subscriber.
$pages[] = array_merge($mail_settings, array(
    'key'   => 'email_welcome',
    'name'  => lang('email-newsletter-welcome'),
    'title' => lang('Newsletter Welcome E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('Welcome to the bookshop letter')),
        $mail_para(lang('Thank you for subscribing. Once a month you hear about new books, the staff picks and the events before the seats are gone.')),
        $mail_para(lang('Changed your mind? Every e-mail we send has a link to unsubscribe.'), 'small text-body-secondary mb-3'),
        $mail_button(lang('See the staff picks'), '{{page:home}}'),
    )),
));
// The book club's welcome, sent to a new member.
$pages[] = array_merge($mail_settings, array(
    'key'   => 'email_club',
    'name'  => lang('email-book-club-welcome'),
    'title' => lang('Book Club Welcome E-mail'),
    'tree'  => $mail_page(array($widget('email_club'))),
));
// The order receipt, sent to the billing address when an order is placed.
$pages[] = array_merge($mail_settings, array(
    'key'      => 'email_order',
    'requires' => 'ecommerce',
    'name'     => lang('email-order-receipt'),
    'title'    => lang('Order Receipt E-mail'),
    'tree'     => $mail_page(array($widget('email_order'))),
));

// ── Widgets ─────────────────────────────────────────────────────────────

$login_item = function ($text, $token, $flag = '') use ($el, $link) {
    $extra = ($flag !== '') ? array('_bindings' => array('eo_visible_if' => $flag)) : array();
    return $el('li', '', lang('Menu Item'), array(
        $link($text, '#', 'dropdown-item', array(), array('_bindings' => array('href' => $token))),
    ), $extra);
};
$login_divider = function () use ($el) {
    return $el('li', '', lang('Divider'), array($el('hr', 'dropdown-divider', lang('Divider Line'))));
};
$account_link = function ($icon_name, $text, $href, $token = '') use ($el, $icon, $span) {
    $extra = array('href' => $href);
    if ($token !== '') $extra['_bindings'] = array('href' => $token);
    return $el('a', 'list-group-item list-group-item-action d-flex align-items-center gap-2', lang('Menu Item'), array(
        $icon($icon_name, 'text-primary'),
        $span($text, '', lang('Link Text')),
    ), $extra);
};

$widgets = array(

    // The account bar's view of the session.
    'login_region' => array(
        'page'   => '',
        'slug'   => lang('login-region'),
        'config' => array(
            'regionType'         => 'login_region',
            'login_page_id'      => '{{tab:login}}',
            'register_page_id'   => '{{tab:register}}',
            'show_register_link' => true,
            'account_page_id'    => '{{tab:my_account}}',
        ),
        'tree'   => $widget_root(array(
            $el('div', 'd-flex align-items-center gap-2', lang('Signed Out'), array(
                $link(lang('Log In'), '#', 'btn btn-sm btn-link link-body-emphasis text-decoration-none text-nowrap', array(), array('_bindings' => array('href' => '__login_url'))),
                $link(lang('Sign Up'), '#', 'btn btn-sm btn-outline-primary text-nowrap', array(), array('_bindings' => array('href' => '__register_url'))),
            ), $show_when('is_signed_out')),
            $el('div', 'dropdown', lang('Signed In'), array(
                $el('a', 'd-flex align-items-center gap-2 link-body-emphasis text-decoration-none dropdown-toggle', lang('Account Button'), array(
                    $image('https://placehold.co/64x64/3f6212/ffffff?text=JC', lang('Jane Cooper'), 'rounded-circle object-fit-cover',
                        array('width' => '24', 'height' => '24', '_bindings' => array('src' => '__user_avatar_url', 'alt' => '__user_name'))),
                    $span(lang('Jane'), 'fw-semibold', lang('First Name'), $bind('__user_first_name')),
                ), array('href' => '#', '_attrs' => array(
                    $attr('role', 'button'),
                    $attr('data-bs-toggle', 'dropdown'),
                    $attr('aria-expanded', 'false'),
                ))),
                $el('ul', 'dropdown-menu dropdown-menu-end shadow-sm', lang('Account Menu'), array(
                    $el('li', 'px-3 py-2', lang('Account Summary'), array(
                        $span(lang('Jane Cooper'), 'd-block fw-semibold', lang('Full Name'), $bind('__user_name')),
                        $span(lang('jane.cooper@example.com'), 'd-block small text-body-secondary', lang('Email'), $bind('__user_email')),
                    )),
                    $login_divider(),
                    $login_item(lang('My Account'), '__my_account_url'),
                    $login_item(lang('Edit my profile'), '__profile_url'),
                    $login_item(lang('Control panel'), '__panel_url', 'has_panel_access'),
                    $login_divider(),
                    $login_item(lang('Log out'), '__logout_url'),
                )),
            ), $show_when('is_signed_in')),
            $loop(array()),
        )),
    ),

    // The way to the cart, beside the login region.
    'cart_link' => array(
        'page'     => '',
        'requires' => 'ecommerce',
        'slug'     => lang('cart-link'),
        'config'   => array('regionType' => 'cart_link', 'cart_page_id' => '{{tab:cart}}'),
        'tree'     => 'starter',
    ),

    // The home page's three shelves, each in its own layout.
    'home_bestsellers' => $home_listing('books', $card_bestseller, 4),
    'home_new'         => $home_listing('notebooks', $card_new, 4),
    'home_stationery'  => $home_listing('pens', $card_compact, 6),

    // The shop: the bookshop's categories open in place; the filters
    // (price, in stock, the products' attributes) sit in the side panel.
    'shop_catalog' => array(
        'page'     => 'shop',
        'requires' => 'ecommerce',
        'slug'     => lang('catalog'),
        'config'   => array(
            'regionType'               => 'catalog_listing',
            'product_group_id'         => '{{product_group:bookshop}}',
            'group_navigation'         => 'drill_down',
            'search_enabled'           => true,
            'sort_control_enabled'     => true,
            'price_filter_enabled'     => true,
            'stock_filter_enabled'     => true,
            'show_attribute_filters'   => true,
            'order_by_field'           => 'sort_order',
            'order_by_direction'       => 'ASC',
            'items_per_page'           => 12,
            'detail_page_id'           => '{{tab:product}}',
            'add_to_cart_stay_on_page' => true,
            'add_to_cart_next_page_id' => '{{tab:cart}}',
            'empty_message'            => lang('Product not found.'),
        ),
        'tree'     => 'starter',
    ),
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
            'cart_section_label'          => lang('Your basket'),
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

    // The newsletter's own form, on its page: the address and the consent.
    'newsletter_form' => array(
        'page'   => 'newsletter',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $icon('bi-envelope-open', 'fs-1 text-primary d-block mb-2'),
            $heading('h2', lang('The bookshop letter'), 'h4 fw-bold mb-2'),
            $para(lang('Leave your e-mail address; we write once a month, when the new books are in.'), 'text-body-secondary mb-4'),
            $newsletter_line('bk-newsletter-email'),
            $consent('bk-newsletter-consent'),
            $captcha(),
            $loop(array()),
        )),
    ),

    // The band at the foot of the pages: the same form, the newsletter
    // page's, so every sign-up lands in one list.
    'newsletter_band' => array(
        'page'   => '',
        'slug'   => lang('newsletter'),
        'config' => array(
            'regionType'          => 'custom_form',
            'form_source'         => 'existing',
            'form_id'             => '{{tab:newsletter}}',
            'custom_form_page_id' => '{{tab:newsletter}}',
        ),
        'tree'   => $widget_root(array(
            $el('section', 'py-5', lang('Newsletter'), array(
                $container(array(
                    $el('div', 'rounded-4 border border-2 p-4 p-lg-5', lang('Newsletter Band'), array(
                        $row(array(
                            $col(array(
                                $el('div', 'd-flex align-items-start gap-3', lang('Text'), array(
                                    $icon('bi-envelope-open', 'fs-1 lh-1 text-primary'),
                                    $el('div', '', lang('Words'), array(
                                        $heading('h2', lang('The bookshop letter'), 'h4 fw-bold mb-1'),
                                        $para(lang('New books, events and staff picks, once a month. Unsubscribe whenever you like.'), 'text-body-secondary mb-0'),
                                    )),
                                )),
                            ), '', '5'),
                            $col(array(
                                $newsletter_line('bk-band-email'),
                                $consent('bk-band-consent'),
                                $captcha(),
                            ), '', '7'),
                        ), '4', '', 'align-items-center'),
                    )),
                )),
            )),
            $loop(array()),
        )),
    ),

    // Contact: the fields are the page's form.
    'contact_form' => array(
        'page'   => 'contact',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $row(array(
                $col(array($field('bk-contact-first-name', lang('First Name'), $input('bk-contact-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
                $col(array($field('bk-contact-last-name', lang('Last Name'), $input('bk-contact-last-name', 'text', 'last_name', 'family-name', false, array('contact_field' => 'last_name')))), '6'),
                $col(array($field('bk-contact-email', lang('Email'), $input('bk-contact-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
                $col(array($field('bk-contact-phone', lang('Phone'), $input('bk-contact-phone', 'tel', 'phone', 'tel', false))), '6'),
            ), '3'),
            $field('bk-contact-topic', lang('What is it about?'), $select('bk-contact-topic', 'topic', lang('Choose a topic…'), array(
                lang('A question about an order'),
                lang('Reserving a seat at an event'),
                lang('Ordering a book you do not have'),
                lang('Something else'),
            ))),
            $field('bk-contact-message', lang('Your Message'), $textarea('bk-contact-message', 'message', 6)),
            $captcha(),
            $submit(lang('Send Message')),
            $loop(array()),
        )),
    ),

    // The book club: the fields are the page's form.
    'club_form' => array(
        'page'   => 'book_club',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $row(array(
                $col(array($field('bk-club-first-name', lang('First Name'), $input('bk-club-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
                $col(array($field('bk-club-last-name', lang('Last Name'), $input('bk-club-last-name', 'text', 'last_name', 'family-name', false, array('contact_field' => 'last_name')))), '6'),
                $col(array($field('bk-club-email', lang('Email'), $input('bk-club-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
                $col(array($field('bk-club-group', lang('Group'), $select('bk-club-group', 'club_group', lang('Choose a group…'), array(
                    lang('Thursday evenings, in the shop'),
                    lang('Saturday mornings, in the shop'),
                    lang('Online, on a video call'),
                )))), '6'),
            ), '3'),
            $field('bk-club-reading', lang('What have you enjoyed reading lately?'), $textarea('bk-club-reading', 'favourite_books', 3, false)),
            $captcha(),
            $submit(lang('Join the club')),
            $loop(array()),
        )),
    ),

    // The member's account: who is signed in and the way to each account
    // page on the left, the orders on the right.
    'my_account' => array(
        'page'   => 'my_account',
        'slug'   => lang('my-account'),
        'config' => array(
            'regionType'       => 'my_account',
            'login_page_id'    => '{{tab:login}}',
            'show_submissions' => false,
        ),
        'tree'   => $widget_root(array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-3', lang('Account Card'), array(
                            $para(lang('Welcome back'), 'small text-body-secondary mb-1'),
                            $heading('h1', lang('Jane Cooper'), 'h4 fw-bold mb-1', $bind('__display_name')),
                            $para(lang('jane.cooper@example.com'), 'small text-body-secondary mb-3', $bind('email_address')),
                            $link(lang('Log out'), '#', 'btn btn-sm btn-outline-secondary', array(), array('_bindings' => array('href' => '__logout_url'))),
                        )),
                        $el('nav', 'list-group', lang('Account Menu'), array(
                            $account_link('bi-person-vcard', lang('My Profile'), '#', '__profile_url'),
                            $account_link('bi-journal-bookmark', lang('Address Book'), '#', '__address_book_url'),
                            $account_link('bi-shield-lock', lang('Change Password'), '#', '__change_password_url'),
                            $account_link('bi-envelope-paper', lang('Email Preferences'), '#', '__email_preferences_url'),
                            $account_link('bi-people', lang('Book Club'), '{{page:book_club}}'),
                            $account_link('bi-basket', lang('Continue Shopping'), '{{page:shop}}'),
                        ), array('_attrs' => array($attr('aria-label', lang('My Account'))))),
                    ), '', '4'),
                    $col(array(
                        $heading('h2', lang('My Orders'), 'h5 fw-bold mb-3', $show_when('has_orders')),
                        $el('div', '', lang('Order History'), array(), array('_bindings' => array('section' => 'order_history'))),
                    ), '', '8'),
                ), '4', '', 'g-lg-5'),
                $loop(array()),
            ), 'py-5'),
        )),
    ),

    // Signing in, up and out, and the password: the layouts their widgets
    // get from the palette.
    'login' => array(
        'page'   => 'login',
        'slug'   => lang('login'),
        'config' => array(
            'regionType'              => 'login_form',
            'show_remember_me'        => true,
            'register_page_id'        => '{{tab:register}}',
            'show_register_link'      => true,
            'forgot_password_page_id' => '{{tab:forgot_password}}',
        ),
        'tree'   => 'starter',
    ),
    'register' => array(
        'page'   => 'register',
        'slug'   => lang('registration'),
        'config' => array(
            'regionType'       => 'registration',
            'show_remember_me' => true,
            'login_page_id'    => '{{tab:login}}',
            'redirect_page_id' => '{{tab:my_account}}',
        ),
        'tree'   => 'starter',
    ),
    'forgot_password' => array(
        'page'   => 'forgot_password',
        'slug'   => lang('forgot-password'),
        'config' => array('regionType' => 'forgot_password', 'login_page_id' => '{{tab:login}}'),
        'tree'   => 'starter',
    ),
    'set_password' => array(
        'page'   => 'set_password',
        'slug'   => lang('set-password'),
        'config' => array('regionType' => 'set_password', 'redirect_page_id' => '{{tab:my_account}}', 'forgot_password_page_id' => '{{tab:forgot_password}}'),
        'tree'   => 'starter',
    ),
    'logout' => array(
        'page'   => 'logout',
        'slug'   => lang('logout'),
        'config' => array('regionType' => 'logout', 'login_page_id' => '{{tab:login}}'),
        'tree'   => 'starter',
    ),
    'profile' => array(
        'page'   => 'profile',
        'slug'   => lang('profile'),
        'config' => array('regionType' => 'account_profile', 'my_account_page_id' => '{{tab:my_account}}'),
        'tree'   => 'starter',
    ),
    'change_password' => array(
        'page'   => 'change_password',
        'slug'   => lang('change-password'),
        'config' => array('regionType' => 'change_password', 'my_account_page_id' => '{{tab:my_account}}'),
        'tree'   => 'starter',
    ),
    'email_preferences' => array(
        'page'   => 'email_preferences',
        'slug'   => lang('email-preferences'),
        'config' => array('regionType' => 'email_preferences', 'my_account_page_id' => '{{tab:my_account}}'),
        'tree'   => 'starter',
    ),
    'address_book' => array(
        'page'     => 'address_book',
        'requires' => 'ecommerce',
        'slug'     => lang('address-book'),
        'config'   => array('regionType' => 'address_book', 'my_account_page_id' => '{{tab:my_account}}'),
        'tree'     => 'starter',
    ),
    'error_page' => array(
        'page'   => 'error',
        'slug'   => lang('error'),
        'config' => array('regionType' => 'error_page'),
        'tree'   => 'starter',
    ),

    // The e-mail pages' widgets: drawn for the submission just sent and the
    // order just placed.
    'email_message_admin' => array(
        'page'   => 'email_new_message',
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:contact}}',
            'access_control'      => 'public',
            'not_found_message'   => lang('The message could not be found.'),
        ),
        'tree'   => $widget_root(array(
            $mail_heading(lang('A new message has arrived')),
            $mail_para(lang('It came from the contact form of the shop. Answer the sender by e-mail.')),
            $mail_box(array(
                $mail_line(lang('Sender'), array(
                    $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
                    $span(' ', '', lang('Text')),
                    $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
                )),
                $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
                $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
                $mail_line(lang('Subject'), array($span(lang('Reserving a seat at an event'), '', lang('Subject'), $bind('topic')))),
            ), lang('Sender')),
            $el('div', 'lh-lg', lang('Message'), array(), array_merge(
                array('text' => lang('Hello, I would like two seats for the reading night on Thursday.')), $bind('message'))),
            $el('p', 'small text-body-secondary mt-4 mb-0', lang('Reference'), array(
                $span(lang('Reference Code') . ': ', '', lang('Label')),
                $span('ABC123', '', lang('Reference Code'), $bind('reference_code')),
            ), $mail_style('font-size:13px;color:#6b6a5e;margin:24px 0 0')),
        )),
    ),
    'email_message_sender' => array(
        'page'   => 'email_message_received',
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:contact}}',
            'access_control'      => 'public',
            'not_found_message'   => lang('The message could not be found.'),
        ),
        'tree'   => $widget_root(array(
            $mail_heading(lang('Your message has reached us')),
            $mail_greeting(),
            $mail_para(lang('Thank you for writing to us. We answer every day the shop is open, usually on the same day.')),
            $mail_box(array(
                $el('p', 'fw-semibold mb-2', lang('Subject'), array(), array_merge(
                    array('text' => lang('Reserving a seat at an event'), '_attrs' => array($attr('style', 'font-weight:600;margin:0 0 8px'))), $bind('topic'))),
                $el('div', 'lh-lg', lang('Message'), array(), array_merge(
                    array('text' => lang('Hello, I would like two seats for the reading night on Thursday.')), $bind('message'))),
            ), lang('Your Message')),
            $mail_button(lang('Visit the shop'), '{{page:home}}'),
        )),
    ),
    'email_club' => array(
        'page'   => 'email_club',
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:book_club}}',
            'access_control'      => 'public',
            'not_found_message'   => lang('The sign-up could not be found.'),
        ),
        'tree'   => $widget_root(array(
            $mail_heading(lang('Welcome to the book club')),
            $mail_greeting(),
            $mail_para(lang('Thank you for joining. Your place is saved; here is what happens next.')),
            $mail_box(array(
                $mail_line(lang('Your group'), array($span(lang('Thursday evenings, in the shop'), '', lang('Group'), $bind('club_group')))),
                $mail_line(lang('This month\'s pick'), array($span(lang('The Lighthouse Year by Jane Cooper'), '', lang('Text')))),
                $mail_line(lang('Next meeting'), array($span(lang('Thursday 29 October at 19:00'), '', lang('Date')))),
            ), lang('Details')),
            $mail_para(lang('Pick up the book at the counter, with 10% off as a member, and come along on the evening. We send a reminder a week before.')),
            $mail_button(lang('See this month\'s pick'), '{{page:book_club}}'),
        )),
    ),
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
                $span('27.09.2026', '', lang('Date'), $bind('__order_date')),
            ), $mail_style('margin:0 0 16px')),
            $el('table', 'table align-middle mb-3', lang('Order Items'), array(
                $el('tbody', '', lang('Rows'), array(
                    $loop(array(
                        $el('tr', '', lang('Row'), array(
                            $el('td', 'ps-0', lang('Product'), array(
                                $span(lang('Hardback Novel'), 'fw-semibold', lang('Product Name'), $bind('__item_short_description')),
                                $span(' × ', 'text-body-secondary', lang('Text')),
                                $span('1', '', lang('Quantity'), $bind('__item_qty')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #e7e2d6')),
                            $el('td', 'pe-0 text-end', lang('Total'), array(
                                $span(lang('$24.00'), '', lang('Amount'), $bind('__item_total')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #e7e2d6;text-align:right')),
                        )),
                    )),
                )),
            ), $mail_style('width:100%;border-collapse:collapse;margin:0 0 16px')),
            $el('table', 'table table-sm mb-4', lang('Totals'), array(
                $el('tbody', '', lang('Rows'), array(
                    $el('tr', '', lang('Subtotal'), array(
                        $el('td', 'ps-0 text-body-secondary', lang('Label'), array($span(lang('Subtotal'), '', lang('Text')))),
                        $el('td', 'pe-0 text-end', lang('Value'), array($span(lang('$24.00'), '', lang('Amount'), $bind('__order_subtotal'))), $mail_style('text-align:right')),
                    )),
                    $el('tr', '', lang('Discount'), array(
                        $el('td', 'ps-0 text-body-secondary', lang('Label'), array($span(lang('Discount'), '', lang('Text')))),
                        $el('td', 'pe-0 text-end', lang('Value'), array($span(lang('$24.00'), '', lang('Amount'), $bind('__order_discount'))), $mail_style('text-align:right')),
                    ), $show_when('has_discount')),
                    $el('tr', '', lang('Shipping'), array(
                        $el('td', 'ps-0 text-body-secondary', lang('Label'), array($span(lang('Shipping'), '', lang('Text')))),
                        $el('td', 'pe-0 text-end', lang('Value'), array($span(lang('$24.00'), '', lang('Amount'), $bind('__order_shipping'))), $mail_style('text-align:right')),
                    ), $show_when('has_shipping_cost')),
                    $el('tr', '', lang('Tax'), array(
                        $el('td', 'ps-0 text-body-secondary', lang('Label'), array($span(lang('VAT'), '', lang('Text')))),
                        $el('td', 'pe-0 text-end', lang('Value'), array($span(lang('$24.00'), '', lang('Amount'), $bind('__order_tax'))), $mail_style('text-align:right')),
                    ), $show_when('has_tax')),
                    $el('tr', 'fw-bold', lang('Total'), array(
                        $el('td', 'ps-0', lang('Label'), array($span(lang('Total'), '', lang('Text')))),
                        $el('td', 'pe-0 text-end', lang('Value'), array($span(lang('$24.00'), '', lang('Amount'), $bind('__order_total'))), $mail_style('text-align:right;font-weight:700')),
                    )),
                )),
            ), $mail_style('width:100%;border-collapse:collapse;margin:0 0 24px')),
            $el('div', 'row g-3', lang('Addresses'), array(
                $el('div', 'col-sm-6', lang('Billing Address'), array(
                    $el('p', 'small text-body-secondary mb-1', lang('Label'), array(), array('text' => lang('Billing Address'))),
                    $el('div', '', lang('Address'), array(), array_merge(array('text' => lang('Sample Street 1, 34000 Istanbul, Türkiye')), $bind('__billing_address_html'))),
                )),
                $el('div', 'col-sm-6', lang('Shipping Address'), array(
                    $el('p', 'small text-body-secondary mb-1', lang('Label'), array(), array('text' => lang('Shipping Address'))),
                    $el('div', '', lang('Address'), array(), array_merge(array('text' => lang('Sample Street 1, 34000 Istanbul, Türkiye')), $bind('__shipping_address_html'))),
                ), $show_when('has_shipping')),
            )),
            $mail_button(lang('View your order'), '{{page:order}}?order_id=^^__order_id^^'),
        )),
    ),
);

// ── The catalog ─────────────────────────────────────────────────────────
// Made once when the template is first opened (_pg_tpl_catalog()): a group
// is found again by its title under its parent, a product by its SKU.
// Prices in the base currency, USD for any currency not listed; the pictures
// are in includes/design_templates/pinegrap-bookshop/.
$product = function ($sku, $image, $title, $description, $usd, $try, $flags = array()) {
    return array_merge(array(
        'sku'         => $sku,
        'image'       => $image,
        'title'       => $title,
        'description' => $description,
        'price'       => array('USD' => $usd, 'EUR' => $usd, 'GBP' => $usd, 'TRY' => $try),
    ), $flags);
};
$catalog = array(
    'folder'  => 'pinegrap_bookshop_catalog',
    // The Details tab of every product page.
    'details' => array(
        lang('Ready to collect at the counter on the day you order.'),
        lang('Sent within one working day; delivered in 1–3 working days.'),
        lang('A sample product of the template: change its name, price and pictures in the catalog, or remove it.'),
    ),
    'groups' => array(
        'bookshop' => array(
            'parent'      => 'root',
            'title'       => lang('Bookshop'),
            'description' => lang('Every book, notebook, pen and card in the shop, by category.'),
        ),
        'books' => array(
            'parent' => 'bookshop', 'sort_order' => 1,
            'title' => $categories['books']['title'], 'description' => $categories['books']['text'],
            'image' => 'bk-category-books.jpg',
            'products' => array(
                $product('PGK-1001', 'bk-books-book.jpg', lang('Hardback Novel'), lang('This season\'s most talked-about novel, in a cloth-bound hardback with a ribbon marker.'), 24, 899, array('featured' => true)),
                $product('PGK-1002', 'bk-books-book-half.jpg', lang('Paperback Classic'), lang('A well-loved classic in a light paperback that fits a coat pocket.'), 12, 449),
                $product('PGK-1003', 'bk-books-journal-bookmark.jpg', lang('Poetry Collection'), lang('Short poems for long evenings, from a voice the whole shop has fallen for.'), 16, 599, array('new' => true)),
                $product('PGK-1004', 'bk-books-headphones.jpg', lang('Audiobook Download'), lang('A download code for the unabridged audiobook, read by the author.'), 19, 699, array('new' => true)),
                $product('PGK-1005', 'bk-books-bookmark.jpg', lang('Leather Bookmark'), lang('A bookmark cut from vegetable-tanned leather that softens with every book.'), 8, 299),
            ),
        ),
        'notebooks' => array(
            'parent' => 'bookshop', 'sort_order' => 2,
            'title' => $categories['notebooks']['title'], 'description' => $categories['notebooks']['text'],
            'image' => 'bk-category-notebooks.jpg',
            'products' => array(
                $product('PGK-2001', 'bk-notebooks-journal.jpg', lang('Dotted Journal A5'), lang('160 numbered pages of dotted paper that takes ink without bleeding through.'), 18, 649, array('new' => true, 'featured' => true)),
                $product('PGK-2002', 'bk-notebooks-journal-text.jpg', lang('Lined Notebook'), lang('A slim lined notebook with a kraft cover, for lists, lectures and letters.'), 9, 329, array('new' => true)),
                $product('PGK-2003', 'bk-notebooks-sticky.jpg', lang('Sticky Notes Pack'), lang('Six pads of sticky notes in soft paper colours.'), 5, 179),
                $product('PGK-2004', 'bk-notebooks-calendar3.jpg', lang('Weekly Planner'), lang('A week on two pages, with room for notes and a ribbon for today.'), 22, 799, array('new' => true)),
                $product('PGK-2005', 'bk-notebooks-journal-richtext.jpg', lang('Reading Journal'), lang('Pages to record every book you read: the date, the quote, what you thought.'), 16, 579),
            ),
        ),
        'pens' => array(
            'parent' => 'bookshop', 'sort_order' => 3,
            'title' => $categories['pens']['title'], 'description' => $categories['pens']['text'],
            'image' => 'bk-category-pens.jpg',
            'products' => array(
                $product('PGK-3001', 'bk-pens-pen.jpg', lang('Fountain Pen'), lang('A steel-nib fountain pen that writes smoothly from the first line, with a converter.'), 45, 1699, array('featured' => true)),
                $product('PGK-3002', 'bk-pens-pencil.jpg', lang('Graphite Pencil Set'), lang('Twelve pencils from 4H to 8B, for writing, sketching and shading.'), 11, 399),
                $product('PGK-3003', 'bk-pens-vector-pen.jpg', lang('Calligraphy Nib Set'), lang('A holder and six nibs for pointed-pen and broad-edge lettering.'), 15, 549, array('new' => true)),
                $product('PGK-3004', 'bk-pens-brush.jpg', lang('Brush Pen Set'), lang('Six brush pens with flexible tips, for lettering and loose sketches.'), 19, 699),
                $product('PGK-3005', 'bk-pens-palette.jpg', lang('Watercolour Pan Set'), lang('Twelve artist-grade half pans in a tin that doubles as a palette.'), 29, 1099, array('new' => true)),
                $product('PGK-3006', 'bk-pens-highlighter.jpg', lang('Pastel Highlighters'), lang('Six soft highlighters that mark the page without hiding the words.'), 7, 249),
            ),
        ),
        'cards' => array(
            'parent' => 'bookshop', 'sort_order' => 4,
            'title' => $categories['cards']['title'], 'description' => $categories['cards']['text'],
            'image' => 'bk-category-cards.jpg',
            'products' => array(
                $product('PGK-4001', 'bk-cards-postcard.jpg', lang('Illustrated Postcards'), lang('A set of ten postcards drawn by local illustrators.'), 6, 219),
                $product('PGK-4002', 'bk-cards-envelope-paper.jpg', lang('Letterpress Card Set'), lang('Five hand-pressed cards on cotton paper, with envelopes.'), 14, 499, array('featured' => true)),
                $product('PGK-4003', 'bk-cards-gift.jpg', lang('Gift Card'), lang('A gift card for anything in the shop, in an envelope ready to give.'), 25, 900, array('new' => true)),
                $product('PGK-4004', 'bk-cards-scissors.jpg', lang('Gift Wrap Kit'), lang('Kraft paper, cotton ribbon, tags and a small pair of scissors.'), 12, 429),
                $product('PGK-4005', 'bk-cards-paperclip.jpg', lang('Brass Paper Clips'), lang('A tin of brass paper clips, for letters, receipts and pages to come back to.'), 6, 199),
            ),
        ),
    ),
);

return array(
    'name'        => lang('Bookshop'),
    'version'     => '1.0.1',
    'framework'   => 'bootstrap5',
    'order'       => 40,
    'icon'        => 'bi-book-half',
    // The picture on the template's card and beside the designs made from
    // it: a bookshop front (pg_design_thumb_svg()).
    'thumb'       => 'bookshop',
    'requires'    => 'ecommerce',
    'description' => lang('A bookshop and stationery store: staff picks, bestsellers, events, the shop with filters, product, cart, checkout and account pages.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor.
    'look'        => 'corporate',
    'palette'     => 'forest',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('A catalog to start from: books, notebooks, pens and cards, with pictures.'),
        lang('An events page and a book club whose sign-up form welcomes each new member by e-mail.'),
        lang('A newsletter form on the pages: every address joins one contact group, ready for e-mail campaigns.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_bookshop', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
    ),

    'catalog'        => $catalog,

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Bookshop Newsletter'),
            'description'  => lang('The addresses left on the newsletter form of the bookshop.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'notice'       => array('name' => lang('Bookshop Notice'), 'tree' => $notice()),
        'header'       => array('name' => lang('Bookshop Header'), 'tree' => $header()),
        'footer'       => array('name' => lang('Bookshop Footer'), 'tree' => $footer()),
        'gift_band'    => array('name' => lang('Bookshop Gift Card Band'), 'tree' => $gift_band()),
        'email_header' => array('name' => lang('Bookshop E-mail Header'), 'tree' =>
            $el('div', 'px-4 py-3 border-bottom', lang('E-mail Header'), array(
                $link('{{site_name}}', '{{page:home}}', 'fs-5 fw-bold text-decoration-none link-body-emphasis',
                    array($attr('style', 'font-size:18px;font-weight:700;color:#3f6212;text-decoration:none'))),
            ), $mail_style('padding:16px 24px;border-bottom:1px solid #e7e2d6'))),
        'email_footer' => array('name' => lang('Bookshop E-mail Footer'), 'tree' =>
            $el('div', 'px-4 py-3 border-top small text-body-secondary', lang('E-mail Footer'), array(
                $para('© {{year}} {{site_name}}', 'mb-1', array('_attrs' => array($attr('style', 'margin:0 0 4px')))),
                $link(lang('Visit the shop'), '{{page:home}}', 'link-secondary',
                    array($attr('style', 'color:#6b6a5e'))),
            ), $mail_style('padding:16px 24px;border-top:1px solid #e7e2d6;font-size:13px;color:#6b6a5e'))),
    ),
);
