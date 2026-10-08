<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Online Store": a shop front. A home page that shows four
 * categories, each in a layout of its own (a grid, a list, a lookbook, a
 * strip), a header whose menu opens the categories, the shop with its
 * filters, one product page for every product, the cart, checkout and the
 * order summary, the sign-in and account pages, a help page, a contact page
 * and a newsletter that collects e-mail addresses. Built on Bootstrap 5.
 * Read by pg_design_templates() (includes/fn/designer.php).
 *
 * Opening the template gives the site the catalog the pages list ('catalog':
 * a store group with four categories and their products, the pictures from
 * includes/design_templates/pinegrap-store/) and the newsletter's contact
 * group ('contact_groups'), each made once and found again by its name the
 * next time (_pg_tpl_catalog(), _pg_tpl_contact_groups()).
 *
 * The newsletter is one form: the newsletter page owns it (its fields, its
 * contact group, its welcome e-mail) and the band at the foot of the other
 * pages is a widget that shows that same form, so every sign-up lands in one
 * list whichever page it came from.
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
// node its id when the tabs open.
$el = function ($tag, $class, $name, $children = array(), $extra = array()) {
    $props = array('tag' => $tag, 'cssClass' => $class);
    if ($name !== '') $props['customName'] = $name;
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
$icon = function ($name, $class) use ($el) {
    return $el('i', 'bi ' . $name . ' ' . $class, '', array(), array('_attrs' => array(array('name' => 'aria-hidden', 'value' => 'true'))));
};
$span = function ($text, $class, $name = '', $extra = array()) use ($el) {
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
$attr = function ($name, $value) {
    return array('name' => $name, 'value' => $value);
};
// A Bootstrap button component.
$button = function ($text, $variant, $class = '', $extra = array()) {
    return array('type' => 'component', 'props' => array_merge(array('componentType' => 'btn', 'btnElement' => 'button', 'btnType' => 'submit',
        'variant' => $variant, 'text' => $text, 'cssClass' => $class), $extra), 'children' => array());
};
// The product card's Add to Cart: the listing wraps the card in the form
// that adds the product.
$add_to_cart = function ($text, $variant, $class = '', $outline = false) use ($button) {
    return $button($text, $variant, $class, array('size' => 'sm', 'outline' => $outline, '_bindings' => array('action' => 'catalog_add_to_cart')));
};

// The sample picture a product card shows until the widget fills it in.
$sample_product = 'https://picsum.photos/seed/pinegrap-store-product/600/600';

// ── The parts every page repeats ────────────────────────────────────────

// The categories of the catalog below, in the order the menus list them.
$categories = array(
    'electronics' => array('title' => lang('Electronics'), 'icon' => 'bi-headphones', 'text' => lang('Headphones, watches, speakers and cameras.')),
    'home'        => array('title' => lang('Home & Living'), 'icon' => 'bi-lamp', 'text' => lang('Lamps, mugs, clocks and pots for every room.')),
    'fashion'     => array('title' => lang('Fashion'), 'icon' => 'bi-handbag', 'text' => lang('Bags, sunglasses and accessories for every day.')),
    'sports'      => array('title' => lang('Sports & Outdoors'), 'icon' => 'bi-bicycle', 'text' => lang('Gear for the road, the trail and the gym.')),
);
$category_url = function ($key) {
    return '{{page:shop}}/{{product_group_path:' . $key . '}}';
};

// The line above the header: a word on shipping and returns. A shared
// component, written once for every page.
$announcement = function () use ($el, $icon, $span) {
    return $el('p', 'd-flex align-items-center gap-2 mb-0 text-body-secondary', lang('Announcement'), array(
        $icon('bi-truck', 'text-primary'),
        $span(lang('Free shipping on orders over $50 · Returns within 14 days'), ''),
    ));
};

// The bar above the header: the announcement on the left, the language
// switcher, the cart link and the login region on the right. The last two
// are system widgets and a shared component cannot hold one yet, so the bar
// is part of each page.
$account_bar = function () use ($el, $widget, $shared, $lang_switcher) {
    return $el('div', 'bg-body-tertiary border-bottom small', lang('Account Bar'), array(
        $el('div', 'container d-flex flex-wrap align-items-center justify-content-between gap-2 py-1', lang('Account Area'), array(
            $el('div', 'd-none d-md-block', lang('Announcement Area'), array($shared('announcement'))),
            $el('div', 'd-flex align-items-center gap-2 ms-auto', lang('Account Links'), array(
                $lang_switcher(), $widget('cart_link'), $widget('login_region'),
            )),
        )),
    ));
};

// The header: the store's name, the search over the products, and the
// category bar whose first item opens every category at once (a mega menu).
// The links to the current page are marked active on the server (smart
// active state), which is what lets one header serve every page.
$header = function () use ($el, $link, $icon, $span, $image, $categories, $category_url, $attr) {
    $mega = array();
    foreach ($categories as $key => $c) {
        $mega[] = $el('div', 'col-6 col-lg-3', $c['title'], array(
            $el('a', 'd-block text-decoration-none link-body-emphasis', lang('Category Link'), array(
                $el('div', 'ratio ratio-4x3 rounded-3 overflow-hidden bg-body-tertiary mb-2', lang('Picture Frame'), array(
                    $image('{{product_group_image:' . $key . '}}', $c['title'], 'object-fit-cover',
                        array('_attrs' => array($attr('loading', 'lazy')))),
                )),
                $span($c['title'], 'd-block fw-semibold', lang('Category Name')),
                $span($c['text'], 'd-block small text-body-secondary', lang('Category Text')),
            ), array('href' => $category_url($key))),
        ));
    }
    $items = array(
        $el('li', 'nav-item dropdown position-static', lang('All Categories'), array(
            $el('a', 'nav-link dropdown-toggle fw-semibold', lang('Menu Button'), array(
                $icon('bi-grid-3x3-gap', 'me-1'),
                $span(lang('All Categories'), ''),
            ), array('href' => '#', '_attrs' => array(
                $attr('role', 'button'),
                $attr('data-bs-toggle', 'dropdown'),
                $attr('aria-expanded', 'false'),
            ))),
            $el('div', 'dropdown-menu w-100 mt-0 border-0 border-top rounded-0 shadow py-4', lang('Mega Menu'), array(
                $el('div', 'container', lang('Mega Menu Container'), array(
                    $el('div', 'row g-4', lang('Categories'), $mega),
                )),
            )),
        )),
    );
    foreach ($categories as $key => $c) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($c['title'], $category_url($key), 'nav-link')));
    }
    $items[] = $el('li', 'nav-item ms-lg-auto', lang('Nav Item'), array($link(lang('All Products'), '{{page:shop}}', 'nav-link fw-semibold text-primary')));
    $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link(lang('Help'), '{{page:help}}', 'nav-link')));
    $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link(lang('Contact us'), '{{page:contact}}', 'nav-link')));

    return $el('header', 'bg-body border-bottom', lang('Site Header'), array(
        $el('div', 'container d-flex flex-wrap align-items-center gap-3 py-3', lang('Header Top'), array(
            $link('{{site_name}}', '{{page:home}}', 'fs-4 fw-bold text-decoration-none link-body-emphasis me-lg-4'),
            // The shop page's own search: its listing reads ?query=.
            $el('form', 'd-flex flex-grow-1 order-last order-lg-0 col-12 col-lg', lang('Search Form'), array(
                $el('div', 'input-group', lang('Search Box'), array(
                    $el('input', 'form-control', '', array(), array('_attrs' => array(
                        $attr('type', 'search'),
                        $attr('name', 'query'),
                        $attr('placeholder', lang('Search for a product…')),
                        $attr('aria-label', lang('Search for a product')),
                    ))),
                    $el('button', 'btn btn-primary px-3', lang('Search Button'), array(
                        $icon('bi-search', ''),
                    ), array('_attrs' => array($attr('type', 'submit'), $attr('aria-label', lang('Search'))))),
                )),
            ), array('_attrs' => array($attr('action', '{{page:shop}}'), $attr('method', 'get'), $attr('role', 'search')))),
            // Outside the navbar, so an icon of its own rather than
            // .navbar-toggler-icon, whose picture the navbar supplies.
            $el('button', 'btn btn-outline-secondary d-lg-none ms-auto', lang('Toggler'), array(
                $icon('bi-list', 'fs-5'),
            ), array('_attrs' => array(
                $attr('type', 'button'),
                $attr('data-bs-toggle', 'collapse'),
                $attr('data-bs-target', '#ps-nav'),
                $attr('aria-controls', 'ps-nav'),
                $attr('aria-expanded', 'false'),
                $attr('aria-label', lang('Toggle navigation')),
            ))),
        )),
        $el('nav', 'navbar navbar-expand-lg py-0 border-top', lang('Category Bar'), array(
            $el('div', 'container', lang('Category Bar Container'), array(
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $el('ul', 'navbar-nav w-100 gap-lg-2', lang('Nav Menu'), $items),
                ), array('id' => 'ps-nav')),
            )),
        ), array('smartActive' => true)),
    ));
};

// The footer: about the store, the categories, help and the account.
$footer = function () use ($el, $para, $link, $icon, $heading, $categories, $category_url, $attr) {
    $list = function ($title, $links) use ($el, $heading) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('div', 'col-6 col-lg-2', $title, array(
            $heading('h2', $title, 'h6 text-uppercase small fw-bold mb-3'),
            $el('ul', 'list-unstyled small mb-0', lang('Links'), $items),
        ));
    };
    $plain = 'link-body-emphasis text-decoration-none';
    $shop_links = array();
    foreach ($categories as $key => $c) $shop_links[] = $link($c['title'], $category_url($key), $plain);
    $shop_links[] = $link(lang('All Products'), '{{page:shop}}', $plain);
    $social = function ($name, $label) use ($el, $icon, $attr) {
        return $el('a', 'link-body-emphasis fs-5', $label, array($icon($name, '')), array('href' => '#', '_attrs' => array($attr('aria-label', $label))));
    };

    return $el('footer', 'mt-auto bg-body text-body pt-5 pb-4 border-top', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-lg-4', lang('About the Store'), array(
                    $link('{{site_name}}', '{{page:home}}', 'fs-5 fw-bold text-decoration-none link-body-emphasis'),
                    $para(lang('Good products at fair prices, packed with care and sent the same day.'), 'small text-body-secondary mt-2 mb-3'),
                    $el('div', 'd-flex gap-3', lang('Social Links'), array(
                        $social('bi-instagram', 'Instagram'),
                        $social('bi-facebook', 'Facebook'),
                        $social('bi-twitter-x', 'X'),
                        $social('bi-youtube', 'YouTube'),
                    )),
                )),
                $list(lang('Shop'), $shop_links),
                $list(lang('Help'), array(
                    $link(lang('Shipping & Returns'), '{{page:help}}', $plain),
                    $link(lang('Contact us'), '{{page:contact}}', $plain),
                    $link(lang('Newsletter'), '{{page:newsletter}}', $plain),
                )),
                $list(lang('My Account'), array(
                    $link(lang('My Account'), '{{page:my_account}}', $plain),
                    $link(lang('Log In'), '{{page:login}}', $plain),
                    $link(lang('Sign Up'), '{{page:register}}', $plain),
                    $link(lang('Shopping Cart'), '{{page:cart}}', $plain),
                )),
                $el('div', 'col-6 col-lg-2', lang('Contact Details'), array(
                    $heading('h2', lang('Get in Touch'), 'h6 text-uppercase small fw-bold mb-3'),
                    $el('p', 'small d-flex align-items-center gap-2 mb-2', lang('Email'), array(
                        $icon('bi-envelope', 'text-primary'),
                        $link('hello@example.com', 'mailto:hello@example.com', 'link-body-emphasis text-decoration-none text-break'),
                    )),
                    $el('p', 'small d-flex align-items-center gap-2 mb-0', lang('Phone'), array(
                        $icon('bi-telephone', 'text-primary'),
                        $link('+90 555 000 00 00', 'tel:+905550000000', 'link-body-emphasis text-decoration-none'),
                    )),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-2 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $el('div', 'd-flex align-items-center gap-3 fs-4 text-body-secondary', lang('Payment Methods'), array(
                    $icon('bi-credit-card-2-front', ''),
                    $icon('bi-paypal', ''),
                    $icon('bi-bank', ''),
                    $icon('bi-shield-lock', ''),
                ), array('_attrs' => array($attr('aria-label', lang('Secure payment'))))),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// A page of the store: the account bar, the shared header, the page's own
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

// The top band of the inner pages: title and one sentence.
$page_header = function ($title, $lead) use ($el, $heading, $para, $container) {
    return $el('header', 'py-5 bg-body-tertiary border-bottom', lang('Page Header'), array(
        $container(array(
            $heading('h1', $title, 'display-6 fw-bold mb-2'),
            $para($lead, 'lead text-body-secondary mb-0'),
        )),
    ));
};

// The heading over a section: a small line above, the title, and a link to
// everything on the right.
$section_header = function ($eyebrow, $title, $more_text = '', $more_href = '') use ($el, $heading, $para, $link) {
    $text = array(
        $para($eyebrow, 'text-primary fw-semibold text-uppercase small mb-1'),
        $heading('h2', $title, 'h3 fw-bold mb-0'),
    );
    $children = array($el('div', '', lang('Text'), $text));
    if ($more_text !== '') $children[] = $link($more_text, $more_href, 'link-primary text-decoration-none fw-semibold text-nowrap');
    return $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-2 mb-4', lang('Section Header'), $children);
};

// The four promises of the store, under the products.
$benefits = function ($class = 'py-5 border-top') use ($el, $icon, $para, $container, $row, $col) {
    $item = function ($icon_name, $title, $text) use ($el, $icon, $para, $col) {
        return $col(array(
            $el('div', 'd-flex align-items-start gap-3', lang('Benefit'), array(
                $el('div', 'd-inline-flex align-items-center justify-content-center rounded-3 bg-primary-subtle text-primary p-3 flex-shrink-0', lang('Icon Box'), array(
                    $icon($icon_name, 'fs-4'),
                )),
                $el('div', '', lang('Text'), array(
                    $para($title, 'fw-semibold mb-1'),
                    $para($text, 'small text-body-secondary mb-0'),
                )),
            )),
        ), '', '3', '6');
    };
    return $el('section', $class, lang('Benefits'), array(
        $container(array(
            $row(array(
                $item('bi-truck', lang('Free shipping'), lang('On every order over $50, sent the same working day.')),
                $item('bi-arrow-repeat', lang('Easy returns'), lang('Changed your mind? Send it back within 14 days.')),
                $item('bi-shield-check', lang('Secure payment'), lang('Card details are encrypted and never stored by us.')),
                $item('bi-headset', lang('Friendly support'), lang('A real person answers, on weekdays from 9 to 6.')),
            ), '4'),
        )),
    ));
};

// ── Product cards: four layouts for the home page's four categories ─────

// The product's name, linked to its page; the whole card is the link.
$product_title = function ($tag, $class) use ($el, $link) {
    return $el($tag, $class, lang('Product Name'), array(
        $link(lang('Wireless Headphones'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
            array('_bindings' => array('text' => '__short_description', 'href' => '__detail_url'))),
    ));
};
$product_picture = function ($frame, $class = 'object-fit-cover') use ($el, $image, $sample_product, $attr) {
    return $el('div', $frame, lang('Picture Frame'), array(
        $image($sample_product, lang('Product'), $class, array(
            '_attrs'    => array($attr('loading', 'lazy')),
            '_bindings' => array('src' => '__image_url', 'alt' => '__short_description'),
        )),
    ));
};
$price = function ($class) use ($span, $bind) {
    return $span(lang('$89.00'), $class, lang('Price'), $bind('__price_formatted'));
};

// Grid: a picture, the category, the name, the price and the button.
$card_grid = function () use ($el, $col, $product_title, $product_picture, $price, $add_to_cart) {
    return $col(array(
        $el('div', 'card h-100 border-0 shadow-sm overflow-hidden', lang('Product Card'), array(
            $product_picture('ratio ratio-1x1 bg-body-tertiary'),
            $el('div', 'card-body d-flex flex-column', lang('Card Body'), array(
                $product_title('h3', 'h6 card-title mb-3'),
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mt-auto position-relative z-2', lang('Price and Button'), array(
                    $price('fw-bold fs-5'),
                    $add_to_cart(lang('Add to Cart'), 'primary'),
                )),
            )),
        )),
    ), '4', '3', '', '6');
};

// List: the picture beside the words, two to a row.
$card_list = function () use ($el, $col, $product_title, $product_picture, $price, $add_to_cart) {
    return $col(array(
        $el('div', 'card h-100 border-0 bg-body-tertiary overflow-hidden', lang('Product Card'), array(
            $el('div', 'row g-0 h-100 align-items-stretch', lang('Card Row'), array(
                $el('div', 'col-5', lang('Picture'), array(
                    $product_picture('ratio ratio-1x1 h-100'),
                )),
                $el('div', 'col-7', lang('Details'), array(
                    $el('div', 'card-body d-flex flex-column h-100 p-3 p-lg-4', lang('Card Body'), array(
                        $product_title('h3', 'h5 card-title mb-2'),
                        $price('fw-bold mb-3'),
                        $el('div', 'mt-auto position-relative z-2', lang('Button'), array(
                            $add_to_cart(lang('Add to Cart'), 'primary', '', true),
                        )),
                    )),
                )),
            )),
        )),
    ), '6', '6');
};

// Lookbook: a square picture with the name and the price on a frosted
// label.
$card_lookbook = function () use ($el, $col, $product_title, $product_picture, $price) {
    return $col(array(
        $el('div', 'position-relative rounded-4 overflow-hidden h-100', lang('Product Card'), array(
            $product_picture('ratio ratio-1x1'),
            $el('div', 'position-absolute bottom-0 start-0 end-0 m-3 p-3 rounded-3 bg-body bg-opacity-75 shadow-sm', lang('Label'), array(
                $product_title('h3', 'h6 mb-1 text-truncate'),
                $price('small fw-semibold text-body-secondary'),
            )),
        )),
    ), '', '3', '', '6');
};

// Strip: small cards side by side, scrolled sideways on a phone.
$card_strip = function () use ($el, $col, $product_title, $product_picture, $price, $add_to_cart) {
    return $col(array(
        $el('div', 'card h-100 border shadow-none', lang('Product Card'), array(
            $product_picture('ratio ratio-4x3 bg-body-tertiary'),
            $el('div', 'card-body', lang('Card Body'), array(
                $product_title('h3', 'h6 card-title mb-1'),
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 position-relative z-2', lang('Price and Button'), array(
                    $price('fw-semibold'),
                    $add_to_cart(lang('Add'), 'primary', '', true),
                )),
            )),
        )),
    ), '4', '3', '6', '9');
};

// A home page listing: the products of one category, in one of the layouts
// above, four at most, without the search, the sorting or the pages.
$home_listing = function ($group, $card, $row_class = '', $gutter = '4') use ($widget_root, $row, $loop) {
    return array(
        'page'     => 'home',
        'requires' => 'ecommerce',
        'slug'     => lang('catalog'),
        'config'   => array(
            'regionType'               => 'catalog_listing',
            'product_group_id'         => '{{product_group:' . $group . '}}',
            'group_navigation'         => 'none',
            'items_per_page'           => 4,
            'max_results'              => 4,
            'order_by_field'           => 'sort_order',
            'order_by_direction'       => 'ASC',
            'detail_page_id'           => '{{tab:product}}',
            'add_to_cart_stay_on_page' => true,
            // The notice after an add names this store's cart, not the one
            // the visitor happened to see last on a site with two shops.
            'add_to_cart_next_page_id' => '{{tab:cart}}',
            'empty_message'            => lang('Product not found.'),
        ),
        'tree'     => $widget_root(array(
            $row(array($loop(array($card()))), $gutter, '', $row_class),
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
$input = function ($id, $type, $name, $autocomplete = '', $required = true, $extra_attrs = array(), $cf = array()) use ($el, $attr) {
    $attrs = array($attr('type', $type), $attr('name', $name));
    if ($required) $attrs[] = $attr('required', '');
    if ($autocomplete !== '') $attrs[] = $attr('autocomplete', $autocomplete);
    $props = array('id' => $id, '_attrs' => array_merge($attrs, $extra_attrs));
    if ($cf) $props['_cf'] = $cf;
    return $el('input', 'form-control', '', array(), $props);
};
$textarea = function ($id, $name, $rows, $required = true) use ($el, $attr) {
    $attrs = array($attr('name', $name), $attr('rows', (string)$rows));
    if ($required) $attrs[] = $attr('required', '');
    return $el('textarea', 'form-control', '', array(), array('id' => $id, '_attrs' => $attrs));
};
$submit = function ($text, $name, $class = 'btn btn-primary px-4') use ($el, $attr) {
    return $el('button', $class, $name, array(), array('text' => $text, '_attrs' => array($attr('type', 'submit'))));
};
$captcha = function () use ($el) {
    return $el('div', 'mb-3', lang('CAPTCHA'), array(), array('_bindings' => array('section' => 'captcha')));
};
// The consent box under the newsletter's e-mail field: what was agreed to
// is stored with the sign-up.
$consent = function ($id) use ($el, $attr) {
    return $el('div', 'form-check small', lang('Consent'), array(
        $el('input', 'form-check-input', '', array(), array('id' => $id, '_attrs' => array(
            $attr('type', 'checkbox'), $attr('name', 'consent'), $attr('value', 'yes'), $attr('required', ''),
        ))),
        $el('label', 'form-check-label', lang('Label'), array(), array(
            'text'   => lang('I would like to receive e-mails about new products and offers. I can unsubscribe at any time.'),
            '_attrs' => array($attr('for', $id)),
        )),
    ));
};
// The newsletter's e-mail field and button, in one line. The address is the
// contact's, so the sign-up becomes a contact of the newsletter's group.
$newsletter_line = function ($id) use ($el, $attr, $submit) {
    return $el('div', 'input-group input-group-lg mb-3', lang('E-mail and Button'), array(
        $el('label', 'visually-hidden', lang('Label'), array(), array('text' => lang('Email'), '_attrs' => array($attr('for', $id)))),
        $el('input', 'form-control', '', array(), array('id' => $id, '_cf' => array('contact_field' => 'email_address'), '_attrs' => array(
            $attr('type', 'email'), $attr('name', 'email'), $attr('required', ''), $attr('autocomplete', 'email'),
            $attr('placeholder', lang('Your e-mail address')),
        ))),
        $submit(lang('Subscribe'), lang('Send'), 'btn btn-primary px-4'),
    ));
};

// ── Pages ───────────────────────────────────────────────────────────────

$pages = array();

// Home: a campaign, the categories, the four categories' products each in
// a layout of its own, and the store's promises.
$category_tiles = array();
foreach ($categories as $key => $c) {
    $category_tiles[] = $el('a', 'col-6 col-lg-3 text-decoration-none link-body-emphasis', $c['title'], array(
        $el('div', 'position-relative rounded-4 overflow-hidden', lang('Category Card'), array(
            $el('div', 'ratio ratio-4x3 bg-body-tertiary', lang('Picture Frame'), array(
                $image('{{product_group_image:' . $key . '}}', $c['title'], 'object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
            )),
        )),
        $el('div', 'd-flex align-items-center justify-content-between gap-2 mt-2', lang('Category Name'), array(
            $span($c['title'], 'fw-semibold', lang('Name')),
            $icon('bi-arrow-right', 'text-primary'),
        )),
    ), array('href' => $category_url($key)));
}

$pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('Electronics, home goods, fashion and sports gear, delivered to your door.'),
    'tree'             => $page(array(
        // The campaign: a large panel in the palette's colour and two
        // smaller ones beside it.
        $el('section', 'py-4 py-lg-5', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'h-100 rounded-4 p-4 p-lg-5 text-bg-primary d-flex flex-column justify-content-center', lang('Campaign'), array(
                            $el('span', 'badge rounded-pill text-bg-light align-self-start mb-3', lang('Badge'), array(), array('text' => lang('New season'))),
                            $heading('h1', lang('Everything you love, delivered to your door'), 'display-5 fw-bold mb-3'),
                            $para(lang('Fresh arrivals in every category, free shipping over $50 and returns within 14 days.'), 'lead opacity-75 mb-4'),
                            $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                                $link(lang('Shop Now'), '{{page:shop}}', 'btn btn-light btn-lg px-4'),
                                $link(lang('See the Offers'), $category_url('electronics'), 'btn btn-outline-light btn-lg px-4'),
                            )),
                        )),
                    ), '', '8'),
                    $col(array(
                        $el('div', 'd-flex flex-column gap-4 h-100', lang('Side Offers'), array(
                            $el('a', 'flex-fill rounded-4 p-4 bg-secondary-subtle text-decoration-none link-body-emphasis d-flex align-items-center gap-3', lang('Offer'), array(
                                $el('div', 'flex-grow-1', lang('Text'), array(
                                    $para(lang('This week'), 'small text-uppercase fw-semibold text-secondary-emphasis mb-1'),
                                    $heading('h2', lang('Up to 30% off fashion'), 'h4 fw-bold mb-2'),
                                    $span(lang('Shop the collection'), 'small fw-semibold', lang('Link Text')),
                                )),
                                $el('div', 'flex-shrink-0 w-25', lang('Picture'), array(
                                    $image('{{product_group_image:fashion}}', lang('Fashion'), 'img-fluid rounded-3'),
                                )),
                            ), array('href' => $category_url('fashion'))),
                            $el('a', 'flex-fill rounded-4 p-4 bg-body-tertiary text-decoration-none link-body-emphasis d-flex align-items-center gap-3', lang('Offer'), array(
                                $el('div', 'flex-grow-1', lang('Text'), array(
                                    $para(lang('New in'), 'small text-uppercase fw-semibold text-primary mb-1'),
                                    $heading('h2', lang('Make your home yours'), 'h4 fw-bold mb-2'),
                                    $span(lang('Shop home & living'), 'small fw-semibold', lang('Link Text')),
                                )),
                                $el('div', 'flex-shrink-0 w-25', lang('Picture'), array(
                                    $image('{{product_group_image:home}}', lang('Home & Living'), 'img-fluid rounded-3'),
                                )),
                            ), array('href' => $category_url('home'))),
                        )),
                    ), '', '4'),
                ), '4'),
            )),
        )),

        // The categories, as picture tiles.
        $el('section', 'pb-5', lang('Categories'), array(
            $container(array(
                $section_header(lang('Categories'), lang('Shop by category'), lang('All Products'), '{{page:shop}}'),
                $el('div', 'row g-3 g-lg-4', lang('Category Tiles'), $category_tiles),
            )),
        )),

        // Electronics: a grid of cards.
        $el('section', 'py-5 bg-body-tertiary', lang('Electronics'), array(
            $container(array(
                $section_header(lang('Electronics'), lang('Technology that keeps up with you'), lang('See all'), $category_url('electronics')),
                $widget('home_electronics'),
            )),
        )),

        // Home & Living: the picture beside the words.
        $el('section', 'py-5', lang('Home & Living'), array(
            $container(array(
                $section_header(lang('Home & Living'), lang('Small things that make a home'), lang('See all'), $category_url('home')),
                $widget('home_living'),
            )),
        )),

        // Fashion: a lookbook on a dark band.
        $el('section', 'py-5 bg-body text-body', lang('Fashion'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Fashion'), 'text-primary fw-semibold text-uppercase small mb-2'),
                        $heading('h2', lang('The lookbook'), 'display-6 fw-bold mb-3'),
                        $para(lang('Pieces that go with everything you already own, picked for the season.'), 'text-body-secondary mb-4'),
                        $link(lang('Shop fashion'), $category_url('fashion'), 'btn btn-primary px-4'),
                    ), '', '3'),
                    $col(array(
                        $widget('home_fashion'),
                    ), '', '9'),
                ), '4', '', 'align-items-center'),
            )),
        ), array('_attrs' => array($attr('data-bs-theme', 'dark')))),

        // Sports & Outdoors: a strip of small cards.
        $el('section', 'py-5', lang('Sports & Outdoors'), array(
            $container(array(
                $section_header(lang('Sports & Outdoors'), lang('Ready for the road and the trail'), lang('See all'), $category_url('sports')),
                $widget('home_sports'),
            )),
        )),

        $benefits(),
    )),
);

// The shop: every category, opened in place, with the search, the sorting
// and the filters.
$pages[] = array(
    'key'              => 'shop',
    'requires'         => 'ecommerce',
    'name'             => lang('shop'),
    'folder'           => 'public',
    'title'            => lang('Shop'),
    'meta_description' => lang('Every product of the store, by category, ready to order online.'),
    'tree'             => $page(array(
        $page_header(lang('Shop'), lang('Every product of the store, by category. Search, sort and filter to find yours.')),
        $widget('shop_catalog'),
    )),
);

// One product: the page every product's link opens.
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
        $benefits('py-5 border-top bg-body-tertiary'),
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

// The newsletter: its page owns the form. What is sent adds the address to
// the newsletter's contact group, and the subscriber gets the welcome
// e-mail.
$pages[] = array(
    'key'              => 'newsletter',
    'name'             => lang('newsletter'),
    'folder'           => 'public',
    'title'            => lang('Newsletter'),
    'meta_description' => lang('New products and offers, in your inbox first.'),
    'form'             => array(
        'form_name'            => lang('Newsletter'),
        'confirmation_message' => lang('Thank you! You are now subscribed to our newsletter.'),
        'contact_group_id'     => '{{contact_group:newsletter}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('Welcome to our newsletter'),
        'confirm_page_id'      => '{{tab:email_welcome}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Newsletter'), lang('New products and offers, in your inbox first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('newsletter_form'))),
                        )),
                    ), '', '7'),
                    $col(array(
                        $el('div', 'p-4 border rounded-3 bg-body-tertiary', lang('What You Get'), array(
                            $heading('h2', lang('What you get'), 'h5 mb-3'),
                            $el('ul', 'list-unstyled mb-0', lang('Check List'), array(
                                $el('li', 'd-flex gap-2 mb-2', lang('List Item'), array($icon('bi-check2-circle', 'text-primary'), $span(lang('New products before anyone else'), ''))),
                                $el('li', 'd-flex gap-2 mb-2', lang('List Item'), array($icon('bi-check2-circle', 'text-primary'), $span(lang('Offers only subscribers get'), ''))),
                                $el('li', 'd-flex gap-2 mb-0', lang('List Item'), array($icon('bi-check2-circle', 'text-primary'), $span(lang('One e-mail a week at most, never your address to anyone else'), ''))),
                            )),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// Contact: a message to the store; the staff are told, the sender gets a
// reply.
$pages[] = array(
    'key'              => 'contact',
    'name'             => lang('contact-us'),
    'folder'           => 'public',
    'title'            => lang('Contact us'),
    'meta_description' => lang('Questions about an order or a product? Write to us.'),
    'form'             => array(
        'form_name'            => lang('Contact Form'),
        'confirmation_message' => lang('Thank you, your message has reached us. We will get back to you soon.'),
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New message: ^^subject^^'),
        'notify_page_id'       => '{{tab:email_new_message}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your message'),
        'confirm_page_id'      => '{{tab:email_message_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Contact us'), lang('Questions about an order or a product? Write to us; we answer on weekdays.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('contact_form')), '', '7'),
                    $col(array(
                        $el('div', 'p-4 border rounded-3 bg-body-tertiary mb-4', lang('Contact Details'), array(
                            $heading('h2', lang('Contact Details'), 'h5 mb-4'),
                            $el('p', 'd-flex align-items-center gap-2 mb-3', lang('Email'), array(
                                $icon('bi-envelope', 'text-primary'),
                                $link('hello@example.com', 'mailto:hello@example.com', 'link-body-emphasis text-decoration-none'),
                            )),
                            $el('p', 'd-flex align-items-center gap-2 mb-3', lang('Phone'), array(
                                $icon('bi-telephone', 'text-primary'),
                                $link('+90 555 000 00 00', 'tel:+905550000000', 'link-body-emphasis text-decoration-none'),
                            )),
                            $el('p', 'd-flex align-items-start gap-2 mb-3', lang('Address'), array(
                                $icon('bi-geo-alt', 'text-primary mt-1'),
                                $span(lang('Sample Street 1, 34000 Istanbul, Türkiye'), ''),
                            )),
                            $para(lang('Weekdays 09:00–18:00'), 'small text-body-secondary mb-0'),
                        )),
                        $el('div', 'p-4 border rounded-3', lang('Help Note'), array(
                            $para(lang('Most questions about shipping and returns are answered on the help page.'), 'small text-body-secondary mb-3'),
                            $link(lang('Shipping & Returns'), '{{page:help}}', 'btn btn-sm btn-outline-primary'),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    )),
);

// Help: shipping, returns and payment, and the questions customers ask.
$faq = function ($n, $question, $answer) use ($el, $para, $attr) {
    $id = 'ps-faq-' . $n;
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
            $el('div', 'accordion-body text-body-secondary', lang('Answer'), array($para($answer, 'mb-0'))),
        ), array('id' => $id, '_attrs' => array($attr('data-bs-parent', '#ps-faq')))),
    ));
};
$help_card = function ($icon_name, $title, $text) use ($el, $heading, $para, $icon, $col) {
    return $col(array(
        $el('div', 'card h-100', lang('Help Card'), array(
            $el('div', 'card-body p-4', lang('Card Body'), array(
                $el('div', 'd-inline-flex align-items-center justify-content-center rounded-3 bg-primary-subtle text-primary p-3 mb-3', lang('Icon Box'), array(
                    $icon($icon_name, 'fs-4'),
                )),
                $heading('h2', $title, 'h5'),
                $para($text, 'text-body-secondary mb-0'),
            )),
        )),
    ), '', '4');
};
$pages[] = array(
    'key'              => 'help',
    'name'             => lang('shipping-and-returns'),
    'folder'           => 'public',
    'title'            => lang('Shipping & Returns'),
    'meta_description' => lang('How we ship, how to return a product and how to pay.'),
    'tree'             => $page(array(
        $page_header(lang('Shipping & Returns'), lang('How we ship, how to return a product and how to pay.')),
        $el('section', 'py-5', lang('Help Topics'), array(
            $container(array(
                $row(array(
                    $help_card('bi-truck', lang('Shipping'), lang('Orders placed by 3 pm on a working day leave the same day and arrive in 1–3 working days. Shipping is free over $50.')),
                    $help_card('bi-arrow-repeat', lang('Returns'), lang('Send a product back unused within 14 days of delivery. The money is refunded to the card you paid with within a week.')),
                    $help_card('bi-credit-card', lang('Payment'), lang('Pay by card or bank transfer. Card payments are processed by the payment provider; we never see or keep your card details.')),
                ), '4'),
            )),
        )),
        $el('section', 'pb-5', lang('Frequently Asked Questions'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Questions'), 'text-primary fw-semibold text-uppercase small mb-2'),
                        $heading('h2', lang('Frequently asked questions'), 'h3 fw-bold mb-3'),
                        $para(lang('Did not find your answer? Write to us and we will get back to you on the same working day.'), 'text-body-secondary mb-4'),
                        $link(lang('Write to Us'), '{{page:contact}}', 'btn btn-outline-primary'),
                    ), '', '4'),
                    $col(array(
                        $el('div', 'accordion', lang('FAQ'), array(
                            $faq(1, lang('How can I follow my order?'), lang('Once your order leaves, the order summary shows the tracking number. Signed-in customers find every order under My Account.')),
                            $faq(2, lang('Can I change or cancel my order?'), lang('Write to us as soon as you can. An order that has not left yet can be changed or cancelled.')),
                            $faq(3, lang('Do I need an account to order?'), lang('No. You can check out as a guest; an account only keeps your addresses and your orders in one place.')),
                            $faq(4, lang('Which countries do you ship to?'), lang('Change this answer to the countries you ship to and what shipping costs there.')),
                        ), array('id' => 'ps-faq')),
                    ), '', '8'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    )),
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
$link_card = function ($icon_name, $title, $text, $href, $href_token = '') use ($el, $heading, $para, $icon) {
    $extra = array('href' => $href);
    if ($href_token !== '') $extra['_bindings'] = array('href' => $href_token);
    return $el('a', 'col-12 col-md-6 col-lg-4 text-decoration-none link-body-emphasis', $title, array(
        $el('div', 'card h-100 border-0 shadow-sm', lang('Card'), array(
            $el('div', 'card-body d-flex align-items-start gap-3', lang('Card Body'), array(
                $icon($icon_name, 'fs-3 text-primary'),
                $el('div', '', lang('Text'), array(
                    $heading('h2', $title, 'h6 mb-1'),
                    $para($text, 'small text-body-secondary mb-0'),
                )),
            )),
        )),
    ), $extra);
};
$account_cards = array(
    $link_card('bi-person-vcard', lang('My Profile'), lang('Your name, phone and address.'), '#', '__profile_url'),
    $link_card('bi-journal-bookmark', lang('Address Book'), lang('Where your orders are sent.'), '#', '__address_book_url'),
    $link_card('bi-shield-lock', lang('Change Password'), lang('A new password for your account.'), '#', '__change_password_url'),
    $link_card('bi-envelope-paper', lang('Email Preferences'), lang('The mailing lists you receive.'), '#', '__email_preferences_url'),
    $link_card('bi-bag', lang('Continue Shopping'), lang('Back to the products.'), '{{page:shop}}'),
);
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
            ), $mail_style('max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden')),
        ), $mail_style('background:#f3f4f6;padding:24px 12px;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.55;color:#1f2937')),
    ));
};
$mail_heading = function ($text) use ($heading, $attr) {
    return $heading('h1', $text, 'h4 fw-bold mb-3', array('_attrs' => array($attr('style', 'font-size:22px;font-weight:700;margin:0 0 16px;color:#111827'))));
};
$mail_para = function ($text, $class = 'mb-3') use ($para, $attr) {
    return $para($text, $class, array('_attrs' => array($attr('style', 'margin:0 0 16px'))));
};
$mail_button = function ($text, $href) use ($el, $link, $mail_style, $attr) {
    return $el('p', 'my-4', lang('Button'), array(
        $link($text, $href, 'btn btn-primary px-4', array($attr('style',
            'display:inline-block;padding:10px 22px;border-radius:8px;font-weight:600;text-decoration:none;'
            . 'background-color:#0d6efd;background-color:var(--bs-primary);color:#ffffff;color:var(--pg-on-primary,#ffffff)'))),
    ), $mail_style('margin:24px 0'));
};
$mail_line = function ($label, $values) use ($el, $span, $mail_style, $attr) {
    return $el('p', 'pg-hide-if-empty mb-1', lang('Line'), array(
        $span($label . ': ', 'text-body-secondary', lang('Label'), array('_attrs' => array($attr('style', 'color:#6b7280')))),
        $el('span', 'pg-field-value fw-semibold', lang('Value'), $values),
    ), $mail_style('margin:0 0 4px'));
};
$mail_box = function ($children, $name) use ($el, $mail_style) {
    return $el('div', 'bg-body-tertiary rounded-3 p-3 my-3', $name, $children,
        $mail_style('background:#f3f4f6;border-radius:8px;padding:16px;margin:16px 0'));
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
        $mail_heading(lang('Welcome to our newsletter')),
        $mail_para(lang('Thank you for subscribing. From now on you hear about new products and offers first, at most once a week.')),
        $mail_para(lang('Changed your mind? Every e-mail we send has a link to unsubscribe.'), 'small text-body-secondary mb-3'),
        $mail_button(lang('Start Shopping'), '{{page:shop}}'),
    )),
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

$widgets = array(

    // The header's view of the session.
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
                $link(lang('Log In'), '#', 'btn btn-sm btn-link text-decoration-none', array(), array('_bindings' => array('href' => '__login_url'))),
                $link(lang('Sign Up'), '#', 'btn btn-sm btn-primary', array(), array('_bindings' => array('href' => '__register_url'))),
            ), $show_when('is_signed_out')),
            $el('div', 'dropdown', lang('Signed In'), array(
                $el('a', 'd-flex align-items-center gap-2 link-body-emphasis text-decoration-none dropdown-toggle', lang('Account Button'), array(
                    $image('https://placehold.co/64x64/e11d48/ffffff?text=JC', lang('Jane Cooper'), 'rounded-circle object-fit-cover',
                        array('width' => '28', 'height' => '28', '_bindings' => array('src' => '__user_avatar_url', 'alt' => '__user_name'))),
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

    // The home page's four categories, each in its own layout.
    'home_electronics' => $home_listing('electronics', $card_grid),
    'home_living'      => $home_listing('home', $card_list),
    'home_fashion'     => $home_listing('fashion', $card_lookbook, '', '3'),
    'home_sports'      => $home_listing('sports', $card_strip, 'flex-nowrap overflow-x-auto pb-2'),

    // The shop: the store's categories open in place; the filters (price,
    // in stock, the products' attributes) sit in the side panel.
    'shop_catalog' => array(
        'page'     => 'shop',
        'requires' => 'ecommerce',
        'slug'     => lang('catalog'),
        'config'   => array(
            'regionType'               => 'catalog_listing',
            'product_group_id'         => '{{product_group:store}}',
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

    // The newsletter's own form, on its page: the address and the consent.
    'newsletter_form' => array(
        'page'   => 'newsletter',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $heading('h2', lang('Subscribe to our newsletter'), 'h4 fw-bold mb-2'),
            $para(lang('Leave your e-mail address; we write when there is something worth reading.'), 'text-body-secondary mb-4'),
            $newsletter_line('ps-newsletter-email'),
            $consent('ps-newsletter-consent'),
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
                    $el('div', 'rounded-4 p-4 p-lg-5 bg-primary-subtle', lang('Newsletter Band'), array(
                        $row(array(
                            $col(array(
                                $el('div', 'd-flex align-items-start gap-3', lang('Text'), array(
                                    $icon('bi-envelope-paper-heart', 'fs-1 text-primary lh-1'),
                                    $el('div', '', lang('Words'), array(
                                        $heading('h2', lang('Hear about new products first'), 'h4 fw-bold mb-1'),
                                        $para(lang('Offers and new arrivals, at most once a week. Unsubscribe whenever you like.'), 'text-body-secondary mb-0'),
                                    )),
                                )),
                            ), '', '6'),
                            $col(array(
                                $newsletter_line('ps-band-email'),
                                $consent('ps-band-consent'),
                                $captcha(),
                            ), '', '6'),
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
                $col(array($field('ps-contact-first-name', lang('First Name'), $input('ps-contact-first-name', 'text', 'first_name', 'given-name', true, array(), array('contact_field' => 'first_name')))), '6'),
                $col(array($field('ps-contact-last-name', lang('Last Name'), $input('ps-contact-last-name', 'text', 'last_name', 'family-name', false, array(), array('contact_field' => 'last_name')))), '6'),
                $col(array($field('ps-contact-email', lang('Email'), $input('ps-contact-email', 'email', 'email', 'email', true, array(), array('contact_field' => 'email_address')))), '6'),
                $col(array($field('ps-contact-order', lang('Order Number'), $input('ps-contact-order', 'text', 'order_number', 'off', false))), '6'),
            ), '3'),
            $field('ps-contact-subject', lang('Subject'), $input('ps-contact-subject', 'text', 'subject', 'off')),
            $field('ps-contact-message', lang('Your Message'), $textarea('ps-contact-message', 'message', 6)),
            $captcha(),
            $submit(lang('Send Message'), lang('Send')),
            $loop(array()),
        )),
    ),

    // The member's account.
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
                $el('div', 'd-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4', lang('Account Header'), array(
                    $el('div', '', lang('Greeting'), array(
                        $para(lang('Welcome back'), 'text-body-secondary mb-1'),
                        $heading('h1', lang('Jane Cooper'), 'h3 fw-bold mb-1', $bind('__display_name')),
                        $para(lang('jane.cooper@example.com'), 'small text-body-secondary mb-0', $bind('email_address')),
                    )),
                    $link(lang('Log out'), '#', 'btn btn-outline-secondary', array(), array('_bindings' => array('href' => '__logout_url'))),
                )),
                $heading('h2', lang('My Orders'), 'h5 mb-3', $show_when('has_orders')),
                $el('div', 'mb-5', lang('Order History'), array(), array('_bindings' => array('section' => 'order_history'))),
                $el('div', 'row g-3', lang('Link Cards'), $account_cards),
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
            $mail_para(lang('It came from the contact form of the store. Answer the sender by e-mail.')),
            $mail_box(array(
                $mail_line(lang('Sender'), array(
                    $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
                    $span(' ', ''),
                    $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
                )),
                $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
                $mail_line(lang('Order Number'), array($span('PG-12345', '', lang('Order Number'), $bind('order_number')))),
                $mail_line(lang('Subject'), array($span(lang('A question about my order'), '', lang('Subject'), $bind('subject')))),
            ), lang('Sender')),
            $el('div', 'lh-lg', lang('Message'), array(), array_merge(
                array('text' => lang('Hello, I would like to know when my order will be shipped.')), $bind('message'))),
            $el('p', 'small text-body-secondary mt-4 mb-0', lang('Reference'), array(
                $span(lang('Reference Code') . ': ', ''),
                $span('ABC123', '', lang('Reference Code'), $bind('reference_code')),
            ), $mail_style('font-size:13px;color:#6b7280;margin:24px 0 0')),
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
            $el('p', 'mb-3', lang('Greeting'), array(
                $span(lang('Hello') . ' ', ''),
                $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
            ), $mail_style('margin:0 0 16px')),
            $mail_para(lang('Thank you for writing to us. We answer on weekdays, usually on the same day.')),
            $mail_box(array(
                $el('p', 'fw-semibold mb-2', lang('Subject'), array(), array_merge(
                    array('text' => lang('A question about my order'), '_attrs' => array($attr('style', 'font-weight:600;margin:0 0 8px'))), $bind('subject'))),
                $el('div', 'lh-lg', lang('Message'), array(), array_merge(
                    array('text' => lang('Hello, I would like to know when my order will be shipped.')), $bind('message'))),
            ), lang('Your Message')),
            $mail_button(lang('Visit the Store'), '{{page:home}}'),
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
                $span(lang('Order Number') . ': ', 'text-body-secondary'),
                $span('PG-12345', 'fw-semibold', lang('Order Number'), $bind('__order_no')),
                $span(' · ', 'text-body-secondary'),
                $span('27.09.2026', '', lang('Date'), $bind('__order_date')),
            ), $mail_style('margin:0 0 16px')),
            $el('table', 'table align-middle mb-3', lang('Order Items'), array(
                $el('tbody', '', lang('Rows'), array(
                    $loop(array(
                        $el('tr', '', lang('Row'), array(
                            $el('td', 'ps-0', lang('Product'), array(
                                $span(lang('Sample Product Name'), 'fw-semibold', lang('Product'), $bind('__item_short_description')),
                                $span(' × ', 'text-body-secondary'),
                                $span('1', '', lang('Quantity'), $bind('__item_qty')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #e5e7eb')),
                            $el('td', 'pe-0 text-end', lang('Total'), array(
                                $span(lang('$89.00'), '', lang('Total'), $bind('__item_total')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #e5e7eb;text-align:right')),
                        )),
                    )),
                )),
            ), $mail_style('width:100%;border-collapse:collapse;margin:0 0 16px')),
            $el('table', 'table table-sm mb-4', lang('Totals'), array(
                $el('tbody', '', lang('Rows'), array(
                    $el('tr', '', lang('Subtotal'), array(
                        $el('td', 'ps-0 text-body-secondary', lang('Label'), array($span(lang('Subtotal'), ''))),
                        $el('td', 'pe-0 text-end', lang('Value'), array($span(lang('$89.00'), '', lang('Subtotal'), $bind('__order_subtotal'))), $mail_style('text-align:right')),
                    )),
                    $el('tr', '', lang('Discount'), array(
                        $el('td', 'ps-0 text-body-secondary', lang('Label'), array($span(lang('Discount'), ''))),
                        $el('td', 'pe-0 text-end', lang('Value'), array($span(lang('$89.00'), '', lang('Discount'), $bind('__order_discount'))), $mail_style('text-align:right')),
                    ), $show_when('has_discount')),
                    $el('tr', '', lang('Shipping'), array(
                        $el('td', 'ps-0 text-body-secondary', lang('Label'), array($span(lang('Shipping'), ''))),
                        $el('td', 'pe-0 text-end', lang('Value'), array($span(lang('$89.00'), '', lang('Shipping'), $bind('__order_shipping'))), $mail_style('text-align:right')),
                    ), $show_when('has_shipping_cost')),
                    $el('tr', '', lang('Tax'), array(
                        $el('td', 'ps-0 text-body-secondary', lang('Label'), array($span(lang('VAT'), ''))),
                        $el('td', 'pe-0 text-end', lang('Value'), array($span(lang('$89.00'), '', lang('VAT'), $bind('__order_tax'))), $mail_style('text-align:right')),
                    ), $show_when('has_tax')),
                    $el('tr', 'fw-bold', lang('Total'), array(
                        $el('td', 'ps-0', lang('Label'), array($span(lang('Total'), ''))),
                        $el('td', 'pe-0 text-end', lang('Value'), array($span(lang('$89.00'), '', lang('Total'), $bind('__order_total'))), $mail_style('text-align:right;font-weight:700')),
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
// are in includes/design_templates/pinegrap-store/.
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
    'folder'  => 'pinegrap_store_catalog',
    // The Details tab of every product page.
    'details' => array(
        lang('Sent within one working day; delivered in 1–3 working days.'),
        lang('Returns within 14 days of delivery.'),
        lang('A sample product of the template: change its name, price and pictures in the catalog, or remove it.'),
    ),
    'groups' => array(
        'store' => array(
            'parent'      => 'root',
            'title'       => lang('Store'),
            'description' => lang('Every product of the store, by category.'),
        ),
        'electronics' => array(
            'parent' => 'store', 'sort_order' => 1,
            'title' => $categories['electronics']['title'], 'description' => $categories['electronics']['text'],
            'image' => 'category-electronics.jpg',
            'products' => array(
                $product('PGS-1001', 'electronics-headphones.jpg', lang('Wireless Headphones'), lang('Over-ear headphones with active noise cancelling and 30 hours of battery life.'), 129, 4999, array('new' => true, 'featured' => true)),
                $product('PGS-1002', 'electronics-smartwatch.jpg', lang('Smart Watch'), lang('Counts your steps, your sleep and your heart rate, and shows your messages at a glance.'), 199, 7499, array('new' => true)),
                $product('PGS-1003', 'electronics-speaker.jpg', lang('Bluetooth Speaker'), lang('A compact speaker with a big sound, water resistant for the garden and the beach.'), 79, 2999),
                $product('PGS-1004', 'electronics-camera.jpg', lang('Instant Camera'), lang('Prints the moment in seconds. Comes with a strap and ten sheets of film.'), 99, 3799),
                $product('PGS-1005', 'electronics-tablet.jpg', lang('Tablet'), lang('A 10-inch screen for reading, films and video calls, light enough to carry everywhere.'), 299, 11499),
            ),
        ),
        'home' => array(
            'parent' => 'store', 'sort_order' => 2,
            'title' => $categories['home']['title'], 'description' => $categories['home']['text'],
            'image' => 'category-home.jpg',
            'products' => array(
                $product('PGS-2001', 'home-lamp.jpg', lang('Desk Lamp'), lang('A dimmable lamp with a warm light and a steady metal base.'), 49, 1899, array('new' => true)),
                $product('PGS-2002', 'home-cup-hot.jpg', lang('Ceramic Mug'), lang('Hand-glazed stoneware that keeps your coffee warm a little longer.'), 19, 699),
                $product('PGS-2003', 'home-clock.jpg', lang('Wall Clock'), lang('A silent wall clock with a clean face that suits any room.'), 39, 1499),
                $product('PGS-2004', 'home-flower2.jpg', lang('Plant Pot'), lang('A ceramic pot with a saucer, for plants large and small.'), 29, 999),
            ),
        ),
        'fashion' => array(
            'parent' => 'store', 'sort_order' => 3,
            'title' => $categories['fashion']['title'], 'description' => $categories['fashion']['text'],
            'image' => 'category-fashion.jpg',
            'products' => array(
                $product('PGS-3001', 'fashion-backpack2.jpg', lang('Everyday Backpack'), lang('Water-repellent fabric, a padded laptop sleeve and plenty of pockets.'), 69, 2599, array('featured' => true)),
                $product('PGS-3002', 'fashion-sunglasses.jpg', lang('Sunglasses'), lang('Polarised lenses with full UV protection in a light frame.'), 59, 2199, array('new' => true)),
                $product('PGS-3003', 'fashion-handbag.jpg', lang('Leather Handbag'), lang('Soft leather, an inner zip pocket and a strap that adjusts.'), 119, 4499),
                $product('PGS-3004', 'fashion-watch.jpg', lang('Classic Watch'), lang('A slim watch with a leather strap that goes with everything.'), 89, 3399),
                $product('PGS-3005', 'fashion-umbrella.jpg', lang('Compact Umbrella'), lang('Opens and closes with one button and stands up to the wind.'), 25, 899),
            ),
        ),
        'sports' => array(
            'parent' => 'store', 'sort_order' => 4,
            'title' => $categories['sports']['title'], 'description' => $categories['sports']['text'],
            'image' => 'category-sports.jpg',
            'products' => array(
                $product('PGS-4001', 'sports-bicycle.jpg', lang('City Bike'), lang('A light aluminium frame, seven gears and a comfortable saddle.'), 449, 16999, array('featured' => true)),
                $product('PGS-4002', 'sports-cup-straw.jpg', lang('Sports Bottle'), lang('Keeps drinks cold for 24 hours, with a leak-proof lid and a straw.'), 22, 799, array('new' => true)),
                $product('PGS-4003', 'sports-compass.jpg', lang('Hiking Compass'), lang('A reliable compass with a sighting mirror and a lanyard.'), 29, 1099),
                $product('PGS-4004', 'sports-stopwatch.jpg', lang('Digital Stopwatch'), lang('Lap times to the hundredth of a second, on a large display.'), 19, 699),
            ),
        ),
    ),
);

return array(
    'name'        => lang('Online Store'),
    'version'     => '1.0.2',
    'framework'   => 'bootstrap5',
    'order'       => 20,
    'icon'        => 'bi-bag-heart',
    // The picture on the template's card and beside the designs made from
    // it: a shop front (pg_design_thumb_svg()).
    'thumb'       => 'store',
    'requires'    => 'ecommerce',
    'description' => lang('A shop front: a home page that shows four categories in four layouts, a menu that opens the categories, the shop with filters, one product page, cart, checkout and account pages, a help page and a newsletter.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor.
    'look'        => 'modern-sharp',
    'palette'     => 'coral',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('A catalog to start from: four categories and their products, with pictures.'),
        lang('A newsletter form on the pages: every address joins one contact group, ready for e-mail campaigns.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_store', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
    ),

    'catalog'        => $catalog,

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Newsletter Subscribers'),
            'description'  => lang('The addresses left on the newsletter form of the store.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'announcement' => array('name' => lang('Store Announcement'), 'tree' => $announcement()),
        'header'       => array('name' => lang('Store Header'), 'tree' => $header()),
        'footer'       => array('name' => lang('Store Footer'), 'tree' => $footer()),
        'email_header' => array('name' => lang('Store E-mail Header'), 'tree' =>
            $el('div', 'px-4 py-3 border-bottom', lang('E-mail Header'), array(
                $link('{{site_name}}', '{{page:home}}', 'fs-5 fw-bold text-decoration-none link-body-emphasis',
                    array($attr('style', 'font-size:18px;font-weight:700;color:#111827;text-decoration:none'))),
            ), $mail_style('padding:16px 24px;border-bottom:1px solid #e5e7eb'))),
        'email_footer' => array('name' => lang('Store E-mail Footer'), 'tree' =>
            $el('div', 'px-4 py-3 border-top small text-body-secondary', lang('E-mail Footer'), array(
                $para('© {{year}} {{site_name}}', 'mb-1', array('_attrs' => array($attr('style', 'margin:0 0 4px')))),
                $link(lang('Visit the Store'), '{{page:home}}', 'link-secondary',
                    array($attr('style', 'color:#6b7280'))),
            ), $mail_style('padding:16px 24px;border-top:1px solid #e5e7eb;font-size:13px;color:#6b7280'))),
    ),
);
