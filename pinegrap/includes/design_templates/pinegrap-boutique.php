<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Boutique": a small shop of jewellery, bags, eyewear and
 * watches, and gifts, in a quiet editorial style. A home page with a
 * two-picture hero, the new arrivals in a sideways strip, the categories as
 * a mosaic, three product layouts and the boutique's story on a dark band;
 * a lookbook, a gift guide in tabs, the shop with its filters, one product
 * page for every product, the cart, checkout and the order summary, the
 * sign-in and account pages, an about page with the opening hours and a
 * care guide, a contact page and a newsletter. Built on Bootstrap 5. Read
 * by pg_design_templates() (includes/fn/designer.php).
 *
 * Opening the template gives the site the catalog the pages list ('catalog':
 * a Boutique group with four categories and their products, the pictures
 * from includes/design_templates/pinegrap-boutique/) and the newsletter's
 * contact group ('contact_groups'), each made once and found again by its
 * name the next time (_pg_tpl_catalog(), _pg_tpl_contact_groups()). The new
 * arrivals are placed in the Boutique group itself as well as in their
 * category: a listing that does not drill down shows only the products of
 * its own group, so one listing of the Boutique group is the "New in"
 * strip across every category.
 *
 * The newsletter is one form: the newsletter page owns it (its fields, its
 * contact group, its welcome e-mail) and the band at the foot of the other
 * pages is a widget that shows that same form.
 *
 * Placeholders, filled when the template is opened (pg_design_template_prepare()):
 *   {{page:<key>}}                  address of the template page with that key
 *   {{tab:<key>}}                   that page in a widget setting; its id once published
 *   {{folder:<key>}}                id of that template folder
 *   {{product_group:<key>}}         id of a group of the catalog below
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
// node its id when the tabs open. $name is the node's label in the layers
// tree (_label): it is shown beside the element's own name, so the tree
// still says which element it is.
$el = function ($tag, $class, $name, $children = array(), $extra = array()) {
    $props = array('tag' => $tag, 'cssClass' => $class, '_label' => $name);
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
$icon = function ($name, $class) use ($el, $attr) {
    return $el('i', trim('bi ' . $name . ' ' . $class), lang('Icon'), array(), array('_attrs' => array($attr('aria-hidden', 'true'))));
};
$span = function ($text, $class, $name, $extra = array()) use ($el) {
    return $el('span', $class, $name, array(), array_merge(array('text' => $text), $extra));
};
$container = function ($children, $class = '', $fluid = false) {
    return array('type' => 'container', 'props' => array('fluid' => $fluid, 'cssClass' => $class), 'children' => $children);
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
$lazy = array('_attrs' => array(array('name' => 'loading', 'value' => 'lazy')));
// A Bootstrap button component.
$button = function ($text, $variant, $class = '', $extra = array()) {
    return array('type' => 'component', 'props' => array_merge(array('componentType' => 'btn', 'btnElement' => 'button', 'btnType' => 'submit',
        'variant' => $variant, 'text' => $text, 'cssClass' => $class), $extra), 'children' => array());
};
// The product card's Add to bag: the listing wraps the card in the form
// that adds the product.
$add_to_bag = function ($class = '') use ($button) {
    return $button(lang('Add to bag'), 'dark', $class, array('size' => 'sm', 'outline' => true, '_bindings' => array('action' => 'catalog_add_to_cart')));
};

// The sample picture a product card shows until the widget fills it in.
$sample_product = 'https://picsum.photos/seed/pinegrap-boutique-product/600/600';

// ── The categories ──────────────────────────────────────────────────────

// The categories of the catalog below, in the order the menus list them.
$categories = array(
    'jewellery' => array('title' => lang('Jewellery'), 'text' => lang('Rings, necklaces and earrings, made to be worn every day.')),
    'bags'      => array('title' => lang('Bags'), 'text' => lang('Handbags, totes and backpacks in soft leather and canvas.')),
    'eyewear'   => array('title' => lang('Eyewear & Watches'), 'text' => lang('Sunglasses, optical frames and watches with a quiet face.')),
    'gifts'     => array('title' => lang('Gifts'), 'text' => lang('Small things, wrapped with care, for every occasion.')),
);
$category_url = function ($key) {
    return '{{page:shop}}/{{product_group_path:' . $key . '}}';
};
// The links of the main menu, in the header and in its phone drawer.
$menu_links = array();
foreach ($categories as $key => $c) $menu_links[] = array($c['title'], $category_url($key));
$menu_links[] = array(lang('Lookbook'), '{{page:lookbook}}');
$menu_links[] = array(lang('Gift Guide'), '{{page:gift_guide}}');
$menu_links[] = array(lang('About'), '{{page:about}}');

// ── The parts every page repeats ────────────────────────────────────────

// The thin line at the very top: gift wrapping and returns. A shared
// component, written once for every page.
$announcement = function () use ($el, $icon, $span) {
    return $el('p', 'd-flex align-items-center justify-content-center justify-content-md-start gap-2 mb-0', lang('Announcement'), array(
        $icon('bi-gift', ''),
        $span(lang('Free gift wrapping · Returns within 30 days'), '', lang('Text')),
    ));
};

// The dark bar above the header: the announcement, the language switcher,
// the cart link and the login region. The last two are system widgets and a
// shared component of the template cannot place one, so the bar is part of
// each page.
$account_bar = function () use ($el, $widget, $shared, $container, $row, $col, $attr, $lang_switcher) {
    return $el('div', 'py-2 small bg-body text-body', lang('Account Bar'), array(
        $container(array(
            $row(array(
                $col(array($shared('announcement')), '', '', '', '12', 'col-md'),
                $col(array(
                    $el('div', 'd-flex align-items-center justify-content-center gap-3', lang('Account Links'), array(
                        $lang_switcher('', 'light'), $widget('cart_link'), $widget('login_region'),
                    )),
                ), 'auto'),
            ), '2', '', 'align-items-center'),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The header: the boutique's name in the middle, the menu centred under it,
// a search that opens in a row of its own, and on a phone a drawer with the
// menu. The link to the current page is marked active on the server (smart
// active state), which is what lets one header serve every page.
$header = function () use ($el, $link, $icon, $span, $container, $row, $col, $attr, $menu_links) {
    $wide = array();
    $drawer = array();
    foreach ($menu_links as $l) {
        $wide[] = $el('li', 'nav-item', lang('Nav Item'), array($link($l[0], $l[1], 'nav-link link-body-emphasis')));
        $drawer[] = $el('li', 'nav-item border-bottom', lang('Nav Item'), array($link($l[0], $l[1], 'nav-link link-body-emphasis px-0 py-3')));
    }
    $drawer[] = $el('li', 'nav-item border-bottom', lang('Nav Item'), array($link(lang('Shop all'), '{{page:shop}}', 'nav-link link-body-emphasis px-0 py-3')));
    $drawer[] = $el('li', 'nav-item', lang('Nav Item'), array($link(lang('Contact us'), '{{page:contact}}', 'nav-link link-body-emphasis px-0 py-3')));

    return $el('header', 'bg-body border-bottom', lang('Site Header'), array(
        $container(array(
            $row(array(
                $col(array(
                    $el('button', 'btn btn-link link-body-emphasis p-0 d-lg-none', lang('Menu Button'), array(
                        $icon('bi-list', 'fs-3'),
                    ), array('_attrs' => array(
                        $attr('type', 'button'),
                        $attr('data-bs-toggle', 'offcanvas'),
                        $attr('data-bs-target', '#bq-menu'),
                        $attr('aria-controls', 'bq-menu'),
                        $attr('aria-label', lang('Open the menu')),
                    ))),
                    $el('a', 'd-none d-lg-inline-flex align-items-center gap-2 small link-body-emphasis text-decoration-none', lang('Visit Link'), array(
                        $icon('bi-geo-alt', ''),
                        $span(lang('Visit the boutique'), '', lang('Text')),
                    ), array('href' => '{{page:about}}#bq-visit')),
                ), '', '', '', 'col'),
                $col(array(
                    $link('{{site_name}}', '{{page:home}}', 'd-block fs-3 fw-light text-uppercase text-center text-decoration-none link-body-emphasis'),
                ), '', '', '', 'auto'),
                $col(array(
                    $el('button', 'btn btn-link link-body-emphasis p-0', lang('Search Button'), array(
                        $icon('bi-search', 'fs-5'),
                    ), array('_attrs' => array(
                        $attr('type', 'button'),
                        $attr('data-bs-toggle', 'collapse'),
                        $attr('data-bs-target', '#bq-search'),
                        $attr('aria-controls', 'bq-search'),
                        $attr('aria-expanded', 'false'),
                        $attr('aria-label', lang('Search the shop')),
                    ))),
                ), '', '', '', 'col', 'text-end'),
            ), '', '', 'align-items-center py-3 py-lg-4'),
            $el('nav', 'd-none d-lg-block pb-3', lang('Main Navigation'), array(
                $el('ul', 'nav nav-underline justify-content-center gap-4', lang('Nav Menu'), $wide),
            ), array('smartActive' => true, '_attrs' => array($attr('aria-label', lang('Main navigation'))))),
        )),
        // The shop page's own search: its listing reads ?query=.
        $el('div', 'collapse border-top bg-body-tertiary', lang('Search Row'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('form', '', lang('Search Form'), array(
                            $el('div', 'input-group', lang('Search Box'), array(
                                $el('label', 'visually-hidden', lang('Label'), array(), array('text' => lang('Search for a product'), '_attrs' => array($attr('for', 'bq-search-query')))),
                                $el('input', 'form-control', lang('Input'), array(), array('id' => 'bq-search-query', '_attrs' => array(
                                    $attr('type', 'search'),
                                    $attr('name', 'query'),
                                    $attr('placeholder', lang('Rings, totes, sunglasses…')),
                                ))),
                                $el('button', 'btn btn-dark', lang('Submit Button'), array(), array('text' => lang('Search'), '_attrs' => array($attr('type', 'submit')))),
                            )),
                        ), array('_attrs' => array($attr('action', '{{page:shop}}'), $attr('method', 'get'), $attr('role', 'search')))),
                    ), '', '6'),
                ), '', 'center', 'py-3'),
            )),
        ), array('id' => 'bq-search')),
        // The phone menu: a drawer from the left.
        $el('div', 'offcanvas offcanvas-start', lang('Menu Drawer'), array(
            $el('div', 'offcanvas-header border-bottom', lang('Drawer Header'), array(
                $el('h2', 'offcanvas-title fs-5 fw-light text-uppercase', lang('Drawer Title'), array(), array('id' => 'bq-menu-title', 'text' => '{{site_name}}')),
                $el('button', 'btn-close', lang('Close Button'), array(), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-dismiss', 'offcanvas'),
                    $attr('aria-label', lang('Close')),
                ))),
            )),
            $el('div', 'offcanvas-body', lang('Drawer Body'), array(
                $el('nav', '', lang('Drawer Navigation'), array(
                    $el('ul', 'nav flex-column', lang('Nav Menu'), $drawer),
                ), array('smartActive' => true, '_attrs' => array($attr('aria-label', lang('Main navigation'))))),
            )),
        ), array('id' => 'bq-menu', '_attrs' => array($attr('tabindex', '-1'), $attr('aria-labelledby', 'bq-menu-title')))),
    ));
};

// The footer, on a light ground: the name and the social links centred,
// four columns of links, the ways to pay and the copyright line.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $container, $row, $col, $categories, $category_url, $attr) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($title, $links) use ($el, $heading, $col) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $col(array(
            $heading('h2', $title, 'small text-uppercase fw-normal text-body-secondary mb-3'),
            $el('ul', 'list-unstyled small mb-0', lang('Links'), $items),
        ), '3', '', '', '6');
    };
    $shop_links = array();
    foreach ($categories as $key => $c) $shop_links[] = $link($c['title'], $category_url($key), $plain);
    $shop_links[] = $link(lang('Shop all'), '{{page:shop}}', $plain);
    $social = function ($name, $label) use ($el, $icon, $attr) {
        return $el('a', 'd-inline-flex p-2 lh-1 border rounded-circle link-body-emphasis', lang('Social Link'), array($icon($name, '')),
            array('href' => '#', '_attrs' => array($attr('aria-label', $label))));
    };

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body-tertiary border-top', lang('Site Footer'), array(
        $container(array(
            $el('div', 'text-center mb-4', lang('Footer Brand'), array(
                $link('{{site_name}}', '{{page:home}}', 'd-inline-block mb-2 fs-4 fw-light text-uppercase text-decoration-none link-body-emphasis'),
                $para(lang('Jewellery, bags, eyewear and gifts, chosen one by one and wrapped by hand.'), 'small text-body-secondary mb-3'),
                $el('div', 'd-flex justify-content-center gap-2', lang('Social Links'), array(
                    $social('bi-instagram', 'Instagram'),
                    $social('bi-pinterest', 'Pinterest'),
                    $social('bi-facebook', 'Facebook'),
                    $social('bi-tiktok', 'TikTok'),
                )),
            )),
            $el('div', 'py-4 border-top border-bottom', lang('Footer Links'), array(
                $row(array(
                    $list(lang('Shop'), $shop_links),
                    $list(lang('The Boutique'), array(
                        $link(lang('Our story'), '{{page:about}}', $plain),
                        $link(lang('Lookbook'), '{{page:lookbook}}', $plain),
                        $link(lang('Gift Guide'), '{{page:gift_guide}}', $plain),
                        $link(lang('Visit the boutique'), '{{page:about}}#bq-visit', $plain),
                    )),
                    $list(lang('Customer Care'), array(
                        $link(lang('Care guide'), '{{page:about}}#bq-care', $plain),
                        $link(lang('Contact us'), '{{page:contact}}', $plain),
                        $link(lang('Newsletter'), '{{page:newsletter}}', $plain),
                        $link('hello@example.com', 'mailto:hello@example.com', $plain . ' text-break'),
                    )),
                    $list(lang('My Account'), array(
                        $link(lang('My Account'), '{{page:my_account}}', $plain),
                        $link(lang('Log In'), '{{page:login}}', $plain),
                        $link(lang('Sign Up'), '{{page:register}}', $plain),
                        $link(lang('Shopping bag'), '{{page:cart}}', $plain),
                    )),
                ), '4'),
            )),
            $el('div', 'd-flex flex-wrap align-items-center justify-content-center gap-3 pt-4 text-body-secondary', lang('Payment Methods'), array(
                $span(lang('Secure payment'), 'small', lang('Text')),
                $icon('bi-credit-card-2-front', 'fs-4'),
                $icon('bi-paypal', 'fs-4'),
                $icon('bi-apple', 'fs-4'),
                $icon('bi-google', 'fs-4'),
                $icon('bi-bank', 'fs-4'),
                $span(lang('Cards, PayPal, Apple Pay, Google Pay and bank transfer'), 'visually-hidden', lang('Screen Reader Text')),
            )),
            $el('p', 'small text-body-secondary text-center mt-3 mb-0', lang('Copyright'), array(
                $span('© {{year}} {{site_name}}', '', lang('Text')),
                $span(' · ', '', lang('Separator')),
                $span(lang('All rights reserved.'), '', lang('Text')),
            )),
        )),
    ));
};

// A page of the boutique: the account bar, the shared header, the page's
// own content, the newsletter band (left off where it would be in the way:
// the cart, checkout, the account pages) and the shared footer.
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

// The top of the inner pages: a small line, the title and a sentence,
// centred.
$page_header = function ($eyebrow, $title, $lead) use ($el, $heading, $para, $container, $row, $col) {
    return $el('header', 'py-5 text-center border-bottom', lang('Page Header'), array(
        $container(array(
            $row(array(
                $col(array(
                    $para($eyebrow, 'small text-uppercase text-body-secondary mb-2'),
                    $heading('h1', $title, 'display-5 mb-3'),
                    $para($lead, 'lead text-body-secondary mb-0'),
                ), '10', '7'),
            ), '', 'center'),
        ), 'py-lg-3'),
    ));
};

// The heading over a section, centred: a small line, the title and an
// optional link under it.
$section_header = function ($eyebrow, $title, $more_text = '', $more_href = '') use ($el, $heading, $para, $link) {
    $children = array(
        $para($eyebrow, 'small text-uppercase text-body-secondary mb-2'),
        $heading('h2', $title, 'display-6 mb-0'),
    );
    if ($more_text !== '') {
        $children[] = $el('p', 'mt-3 mb-0', lang('More Link'), array($link($more_text, $more_href, 'link-body-emphasis link-offset-2')));
    }
    return $el('div', 'text-center mb-4 mb-lg-5', lang('Section Header'), $children);
};

// The boutique's three promises, under the products.
$service_notes = function ($class = 'py-5 border-top') use ($el, $heading, $para, $icon, $container) {
    $item = function ($icon_name, $title, $text) use ($el, $para, $icon) {
        return $el('li', 'col-12 col-md-4 text-center', lang('Service Note'), array(
            $icon($icon_name, 'd-block fs-2 mb-2 text-primary'),
            $para($title, 'fw-semibold mb-1'),
            $para($text, 'small text-body-secondary mb-0'),
        ));
    };
    return $el('section', $class, lang('Service Notes'), array(
        $container(array(
            $heading('h2', lang('Our promises'), 'visually-hidden'),
            $el('ul', 'list-unstyled row g-4 mb-0', lang('Notes List'), array(
                $item('bi-gift', lang('Free gift wrapping'), lang('Every order leaves in our paper and ribbon, with a handwritten card if you like.')),
                $item('bi-arrow-counterclockwise', lang('30-day returns'), lang('Changed your mind? Send it back unworn within 30 days for a full refund.')),
                $item('bi-shield-lock', lang('Secure payment'), lang('Card details are encrypted by the payment provider and never stored by us.')),
            )),
        )),
    ));
};

// ── Product cards ───────────────────────────────────────────────────────
// The name links to the product; the card is the positioned box, so the
// link covers the whole card (the heading is position-static, otherwise
// the renderer makes it the link's box).

$product_title = function ($tag, $class) use ($el, $link) {
    return $el($tag, trim($class . ' position-static'), lang('Product Name'), array(
        $link(lang('Solitaire Ring'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
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
    return $span(lang('$145.00'), $class, lang('Price'), $bind('__price_formatted'));
};

// New in: a tall card in a sideways strip, the "New" mark on the picture.
$card_new = function () use ($el, $col, $span, $product_title, $product_picture, $price) {
    return $col(array(
        $el('article', 'position-relative h-100', lang('Product Card'), array(
            $el('div', 'position-relative mb-3', lang('Picture Area'), array(
                $product_picture('ratio ratio-1x1 bg-body-tertiary'),
                $span(lang('New'), 'badge position-absolute top-0 start-0 m-3 fw-normal text-uppercase text-bg-light', lang('Badge')),
            )),
            $product_title('h3', 'h6 fw-normal mb-1'),
            $price('small text-body-secondary'),
        )),
    ), '4', '3', '5', '8');
};

// Minimal: the picture, the name and the price, centred, nothing else.
$card_minimal = function () use ($el, $col, $product_title, $product_picture, $price) {
    return $col(array(
        $el('article', 'position-relative h-100 text-center', lang('Product Card'), array(
            $product_picture('ratio ratio-1x1 mb-3 bg-body-tertiary'),
            $product_title('h3', 'h6 fw-normal mb-1'),
            $price('small text-body-secondary'),
        )),
    ), '3', '', '', '6');
};

// Editorial: a large picture with the words and the button beside it.
$card_editorial = function () use ($el, $col, $row, $span, $bind, $product_title, $product_picture, $price, $add_to_bag) {
    return $col(array(
        $el('article', 'position-relative h-100 border', lang('Product Card'), array(
            $row(array(
                $col(array($product_picture('ratio ratio-1x1 h-100 bg-body-tertiary')), '', '', '6'),
                $col(array(
                    $el('div', 'd-flex flex-column h-100 p-4', lang('Details'), array(
                        $span(lang('Bags'), 'small text-uppercase text-body-secondary mb-2', lang('Category'), $bind('__category_name')),
                        $product_title('h3', 'h4 mb-2'),
                        $price('mb-4 text-body-secondary'),
                        $el('div', 'mt-auto position-relative z-2', lang('Button'), array($add_to_bag())),
                    )),
                ), '', '', '6'),
            ), '', '', 'g-0 h-100'),
        )),
    ), '', '6');
};

// Collage: two square pictures side by side, the name and the price under
// each.
$card_collage = function () use ($el, $col, $product_title, $product_picture, $price) {
    return $col(array(
        $el('article', 'position-relative', lang('Product Card'), array(
            $product_picture('ratio ratio-1x1 mb-2 bg-body-tertiary'),
            $el('div', 'd-flex flex-wrap justify-content-between gap-1 small', lang('Caption'), array(
                $product_title('h3', 'fs-6 fw-normal mb-0'),
                $price('text-body-secondary'),
            )),
        )),
    ), '', '', '', '6');
};

// Gift: the minimal card with the button under it.
$card_gift = function () use ($el, $col, $product_title, $product_picture, $price, $add_to_bag) {
    return $col(array(
        $el('article', 'position-relative d-flex flex-column h-100 text-center', lang('Product Card'), array(
            $product_picture('ratio ratio-1x1 mb-3 bg-body-tertiary'),
            $product_title('h3', 'h6 fw-normal mb-1'),
            $price('small mb-3 text-body-secondary'),
            $el('div', 'mt-auto position-relative z-2', lang('Button'), array($add_to_bag('w-100'))),
        )),
    ), '3', '', '', '6');
};

// A listing of one group in one of the layouts above: a few products,
// without the search, the sorting or the pages.
$listing = function ($page_key, $group, $card, $max, $row_class = '', $gutter = '4') use ($widget_root, $row, $loop) {
    return array(
        'page'     => $page_key,
        'requires' => 'ecommerce',
        'slug'     => lang('catalog'),
        'config'   => array(
            'regionType'               => 'catalog_listing',
            'product_group_id'         => '{{product_group:' . $group . '}}',
            'group_navigation'         => 'none',
            'items_per_page'           => $max,
            'max_results'              => $max,
            'order_by_field'           => 'sort_order',
            'order_by_direction'       => 'ASC',
            'detail_page_id'           => '{{tab:product}}',
            'add_to_cart_stay_on_page' => true,
            // The notice after an add names this boutique's cart, not the
            // one the visitor happened to see last on a site with two shops.
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
    return $el('div', $class, lang('Field'), array(
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
    return $el('input', 'form-control', lang('Input'), array(), $props);
};
$textarea = function ($id, $name, $rows) use ($el, $attr) {
    return $el('textarea', 'form-control', lang('Input'), array(), array('id' => $id, '_attrs' => array(
        $attr('name', $name), $attr('rows', (string)$rows), $attr('required', ''),
    )));
};
$submit = function ($text, $class = 'btn btn-dark') use ($el, $attr) {
    return $el('button', $class, lang('Submit Button'), array(), array('text' => $text, '_attrs' => array($attr('type', 'submit'))));
};
$captcha = function () use ($el) {
    return $el('div', 'mb-3', lang('CAPTCHA'), array(), array('_bindings' => array('section' => 'captcha')));
};
// The consent box under the newsletter's e-mail field: what was agreed to
// is stored with the sign-up.
$consent = function ($id) use ($el, $attr) {
    return $el('div', 'form-check small text-start mb-3', lang('Consent'), array(
        $el('input', 'form-check-input', lang('Input'), array(), array('id' => $id, '_attrs' => array(
            $attr('type', 'checkbox'), $attr('name', 'consent'), $attr('value', 'yes'), $attr('required', ''),
        ))),
        $el('label', 'form-check-label', lang('Label'), array(), array(
            'text'   => lang('I would like to receive e-mails about new arrivals and gift ideas. I can unsubscribe at any time.'),
            '_attrs' => array($attr('for', $id)),
        )),
    ));
};
// The newsletter's e-mail field and button, in one line. The address is the
// contact's, so the sign-up becomes a contact of the newsletter's group.
$newsletter_line = function ($id) use ($el, $attr, $submit) {
    return $el('div', 'input-group mb-3', lang('E-mail and Button'), array(
        $el('label', 'visually-hidden', lang('Label'), array(), array('text' => lang('Email'), '_attrs' => array($attr('for', $id)))),
        $el('input', 'form-control', lang('Input'), array(), array('id' => $id, '_cf' => array('contact_field' => 'email_address'), '_attrs' => array(
            $attr('type', 'email'), $attr('name', 'email'), $attr('required', ''), $attr('autocomplete', 'email'),
            $attr('placeholder', lang('Your e-mail address')),
        ))),
        $submit(lang('Subscribe')),
    ));
};

// ── Pages ───────────────────────────────────────────────────────────────

$pages = array();

// Home: the hero, the new arrivals, the categories, three categories in
// three layouts, the story and the promises.
$tile = function ($key, $lg, $ratio) use ($el, $span, $icon, $image, $categories, $category_url, $lazy) {
    $c = $categories[$key];
    return $el('a', 'col-12 col-md-6 col-lg-' . $lg . ' d-block text-decoration-none link-body-emphasis', lang('Category Tile'), array(
        $el('div', 'position-relative h-100 overflow-hidden', lang('Tile'), array(
            // h-100: the shorter picture of a row grows to the taller one.
            $el('div', 'ratio ratio-' . $ratio . ' h-100 bg-body-tertiary', lang('Picture Frame'), array(
                $image('{{product_group_image:' . $key . '}}', $c['title'], 'object-fit-cover', $lazy),
            )),
            $el('span', 'position-absolute bottom-0 start-0 d-inline-flex align-items-center gap-2 m-3 px-3 py-2 small text-uppercase bg-body bg-opacity-75', lang('Label'), array(
                $span($c['title'], '', lang('Name')),
                $icon('bi-arrow-right', ''),
            )),
        )),
    ), array('href' => $category_url($key)));
};
$stat = function ($number, $text) use ($col, $para) {
    return $col(array(
        $para($number, 'display-6 mb-1'),
        $para($text, 'small text-body-secondary mb-0'),
    ), '', '', '', '4');
};

$pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('Jewellery, bags, eyewear, watches and gifts, chosen one by one and wrapped with care.'),
    'tree'             => $page(array(
        // The hero: the words on the left; on the right a picture with a
        // second, square one laid over its corner (from lg; one picture on
        // a phone).
        $el('section', 'py-5', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('The autumn edit'), 'small text-uppercase text-body-secondary mb-3'),
                        $heading('h1', lang('Small pieces, chosen to be kept for years'), 'display-4 mb-4'),
                        $para(lang('Jewellery, bags and gifts from independent makers, wrapped by hand in our boutique.'), 'lead text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Buttons'), array(
                            $link(lang('Shop the edit'), '{{page:shop}}', 'btn btn-dark btn-lg'),
                            $link(lang('See the lookbook'), '{{page:lookbook}}', 'btn btn-link px-0 link-body-emphasis'),
                        )),
                    ), '6', '5'),
                    $col(array(
                        $el('div', 'position-relative mb-lg-5 ps-lg-5', lang('Picture Stack'), array(
                            $el('div', 'ratio ratio-4x3 bg-body-tertiary', lang('Large Picture'), array(
                                $image('{{product_group_image:jewellery}}', $categories['jewellery']['title'], 'object-fit-cover'),
                            )),
                            $el('div', 'd-none d-lg-block position-absolute top-100 start-0 translate-middle-y w-25 p-2 bg-body shadow', lang('Small Picture'), array(
                                $el('div', 'ratio ratio-1x1', lang('Picture Frame'), array(
                                    $image('{{product_group_image:gifts}}', $categories['gifts']['title'], 'object-fit-cover'),
                                )),
                            )),
                        )),
                    ), '6', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        // New in: the new arrivals of every category, scrolled sideways.
        $el('section', 'py-5 border-top', lang('New In'), array(
            $container(array(
                $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-2 mb-4', lang('Section Header'), array(
                    $el('div', '', lang('Text'), array(
                        $para(lang('Just arrived'), 'small text-uppercase text-body-secondary mb-2'),
                        $heading('h2', lang('New in'), 'display-6 mb-0'),
                    )),
                    $link(lang('Shop all new pieces'), '{{page:shop}}', 'link-body-emphasis link-offset-2'),
                )),
                $widget('home_new'),
            )),
        )),

        // The categories: a large and a small picture, then the other way
        // round.
        $el('section', 'py-5', lang('Categories'), array(
            $container(array(
                $section_header(lang('The boutique'), lang('Shop by category')),
                $row(array(
                    $tile('jewellery', '7', '16x9'),
                    $tile('bags', '5', '4x3'),
                    $tile('eyewear', '5', '4x3'),
                    $tile('gifts', '7', '16x9'),
                ), '3', '', 'g-lg-4'),
            )),
        )),

        // Jewellery: minimal cards.
        $el('section', 'py-5 bg-body-tertiary', lang('Jewellery'), array(
            $container(array(
                $section_header($categories['jewellery']['title'], lang('Everyday pieces that sparkle quietly'), lang('Shop jewellery'), $category_url('jewellery')),
                $widget('home_jewellery'),
            )),
        )),

        // The story: a quote and three numbers on a dark band.
        $el('section', 'py-5 bg-body text-body', lang('Our Story'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Our story'), 'small text-uppercase text-body-secondary mb-4'),
                        $el('figure', 'mb-0', lang('Quote'), array(
                            $el('blockquote', 'blockquote mb-3', lang('Quote Text'), array(
                                $para(lang('“We opened the boutique to sell the things we would give our own friends: made with care, fairly priced and wrapped as if it mattered. It still does.”'), 'fs-4 mb-0'),
                            )),
                            $el('figcaption', 'blockquote-footer mb-0', lang('Quote Author'), array(), array('text' => lang('Jane Cooper, founder of the boutique'))),
                        )),
                    ), '', '7'),
                    $col(array(
                        $row(array(
                            $stat('12', lang('years on the same street')),
                            $stat('40+', lang('independent makers')),
                            $stat('30', lang('days to change your mind')),
                        ), '3', '', 'text-center'),
                    ), '', '5'),
                ), '4', '', 'g-lg-5 align-items-center py-lg-4'),
            )),
        ), array('_attrs' => array($attr('data-bs-theme', 'dark')))),

        // Bags: the editorial layout.
        $el('section', 'py-5', lang('Bags'), array(
            $container(array(
                $section_header($categories['bags']['title'], lang('Carried every day, made to last'), lang('Shop bags'), $category_url('bags')),
                $widget('home_bags'),
            )),
        )),

        // Eyewear & watches: the words beside a collage of two.
        $el('section', 'py-5 border-top', lang('Eyewear & Watches'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para($categories['eyewear']['title'], 'small text-uppercase text-body-secondary mb-2'),
                        $heading('h2', lang('A clear view, a quiet face'), 'display-6 mb-3'),
                        $para(lang('Frames in light acetate and titanium, and watches with nothing on the dial that does not need to be there.'), 'text-body-secondary mb-4'),
                        $link(lang('Shop eyewear & watches'), $category_url('eyewear'), 'btn btn-outline-dark'),
                    ), '', '4'),
                    $col(array($widget('home_eyewear')), '', '8'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        $service_notes(),
    )),
);

// The shop: every category, opened in place, with the search, the sorting
// and the filters; the new arrivals listed beside the categories.
$pages[] = array(
    'key'              => 'shop',
    'requires'         => 'ecommerce',
    'name'             => lang('shop'),
    'folder'           => 'public',
    'title'            => lang('Shop'),
    'meta_description' => lang('Every piece of the boutique, by category, ready to order online.'),
    'tree'             => $page(array(
        $page_header(lang('The shop'), lang('Everything in the boutique'), lang('Search, sort and filter, or open a category to see what is in it.')),
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
        $service_notes('py-5 border-top bg-body-tertiary'),
    )),
);

// The lookbook: full-width bands of pictures and words, and the pieces to
// shop at the end.
$split_band = function ($picture_key, $number, $title, $text, $link_text, $href, $picture_last = false, $dark = false)
    use ($el, $heading, $para, $link, $image, $container, $row, $col, $categories, $attr, $lazy) {
    $words_class = 'd-flex flex-column justify-content-center h-100 p-4 p-lg-5 bg-body text-body';
    $words = $col(array(
        $el('div', $words_class, lang('Words'), array(
            $para($number, 'small text-uppercase text-body-secondary mb-2'),
            $heading('h2', $title, 'display-6 mb-3'),
            $para($text, 'text-body-secondary mb-4'),
            $el('p', 'mb-0', lang('More Link'), array($link($link_text, $href, 'link-body-emphasis link-offset-2'))),
        ), $dark ? array('_attrs' => array($attr('data-bs-theme', 'dark'))) : array()),
    ), '6', '', '', '12', $picture_last ? 'order-md-first' : '');
    $picture = $col(array(
        $el('div', 'ratio ratio-1x1 bg-body-tertiary', lang('Picture Frame'), array(
            $image('{{product_group_image:' . $picture_key . '}}', $categories[$picture_key]['title'], 'object-fit-cover', $lazy),
        )),
    ), '6');
    return $el('section', 'border-top', lang('Look'), array(
        $container(array(
            $row(array($picture, $words), '', '', 'g-0'),
        ), 'px-0', true),
    ));
};
$pages[] = array(
    'key'              => 'lookbook',
    'requires'         => 'ecommerce',
    'name'             => lang('lookbook'),
    'folder'           => 'public',
    'title'            => lang('Lookbook'),
    'meta_description' => lang('The season in pictures: how we wear the pieces of the boutique.'),
    'tree'             => $page(array(
        $page_header(lang('Lookbook'), lang('The autumn lookbook'), lang('Four looks for the shorter days, put together from the shelves of the boutique.')),
        $split_band('jewellery', lang('Look 01'), lang('Gold at golden hour'),
            lang('Fine chains and a single ring, worn with a white shirt and nothing else to compete with them.'),
            lang('Shop jewellery'), $category_url('jewellery')),
        $split_band('bags', lang('Look 02'), lang('One bag, every day'),
            lang('A structured leather bag that goes from the office to dinner, with room for a book and an umbrella.'),
            lang('Shop bags'), $category_url('bags'), true, true),
        // Two pictures side by side, the width of the screen.
        $el('section', 'border-top', lang('Look'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'position-relative', lang('Picture Area'), array(
                            $el('div', 'ratio ratio-1x1 bg-body-tertiary', lang('Picture Frame'), array(
                                $image('{{product_group_image:eyewear}}', $categories['eyewear']['title'], 'object-fit-cover', $lazy),
                            )),
                            $span(lang('Look 03'), 'badge position-absolute top-0 start-0 m-3 fw-normal text-uppercase text-bg-light', lang('Badge')),
                        )),
                    ), '', '', '', '6'),
                    $col(array(
                        $el('div', 'position-relative', lang('Picture Area'), array(
                            $el('div', 'ratio ratio-1x1 bg-body-tertiary', lang('Picture Frame'), array(
                                $image('{{product_group_image:gifts}}', $categories['gifts']['title'], 'object-fit-cover', $lazy),
                            )),
                            $span(lang('Look 04'), 'badge position-absolute top-0 start-0 m-3 fw-normal text-uppercase text-bg-light', lang('Badge')),
                        )),
                    ), '', '', '', '6'),
                ), '', '', 'g-0'),
            ), 'px-0', true),
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Sunglasses in October, a card for every reason'), 'h3 mb-3'),
                        $para(lang('Light frames for the low autumn sun, and the small things we tuck into a parcel to make someone smile.'), 'text-body-secondary mb-0'),
                    ), '10', '7'),
                ), '', 'center', 'text-center'),
            ), 'py-5'),
        )),
        // The pieces of the looks.
        $el('section', 'py-5 bg-body-tertiary', lang('Shop the Look'), array(
            $container(array(
                $section_header(lang('Shop the look'), lang('Pieces from these pages'), lang('Shop all'), '{{page:shop}}'),
                $widget('lookbook_products'),
            )),
        )),
    )),
);

// The gift guide: three ideas in tabs, each listing a category.
$gift_tabs = array(
    array('jewellery', 'gift_jewellery', lang('Something that sparkles'), lang('For birthdays and anniversaries: a piece of jewellery is worn long after the day itself.'), lang('See all jewellery')),
    array('eyewear', 'gift_eyewear', lang('Everyday classics'), lang('A watch or a pair of sunglasses, chosen to go with everything they already own.'), lang('See all eyewear & watches')),
    array('gifts', 'gift_gifts', lang('Little treats'), lang('Thank-yous, host gifts and small surprises, all under $60 and wrapped for free.'), lang('See all gifts')),
);
$tab_buttons = array();
$tab_panes = array();
foreach ($gift_tabs as $i => $t) {
    $n = $i + 1;
    $tab_buttons[] = $el('li', 'nav-item', lang('Tab Item'), array(
        $el('button', 'nav-link link-body-emphasis' . ($n === 1 ? ' active' : ''), lang('Tab Button'), array(), array(
            'id'     => 'bq-gift-tab-' . $n,
            'text'   => $t[2],
            '_attrs' => array(
                $attr('type', 'button'),
                $attr('role', 'tab'),
                $attr('data-bs-toggle', 'tab'),
                $attr('data-bs-target', '#bq-gift-pane-' . $n),
                $attr('aria-controls', 'bq-gift-pane-' . $n),
                $attr('aria-selected', $n === 1 ? 'true' : 'false'),
            ),
        )),
    ), array('_attrs' => array($attr('role', 'presentation'))));
    $tab_panes[] = $el('div', 'tab-pane fade' . ($n === 1 ? ' show active' : ''), lang('Tab Pane'), array(
        $para($t[3], 'text-center text-body-secondary mb-4'),
        $widget($t[1]),
        $el('p', 'text-center mt-4 mb-0', lang('More Link'), array($link($t[4], $category_url($t[0]), 'link-body-emphasis link-offset-2'))),
    ), array('id' => 'bq-gift-pane-' . $n, '_attrs' => array(
        $attr('role', 'tabpanel'),
        $attr('aria-labelledby', 'bq-gift-tab-' . $n),
        $attr('tabindex', '0'),
    )));
}
$pages[] = array(
    'key'              => 'gift_guide',
    'requires'         => 'ecommerce',
    'name'             => lang('gift-guide'),
    'folder'           => 'public',
    'title'            => lang('Gift Guide'),
    'meta_description' => lang('Gift ideas from the boutique, wrapped for free and sent the next working day.'),
    'tree'             => $page(array(
        $page_header(lang('Gift Guide'), lang('Gifts they will keep'), lang('Three ideas to start from. Every order is wrapped by hand, with a card if you like.')),
        $el('section', 'py-5', lang('Gift Ideas'), array(
            $container(array(
                $el('ul', 'nav nav-underline justify-content-center gap-4 mb-4 mb-lg-5', lang('Tabs'), $tab_buttons, array('_attrs' => array($attr('role', 'tablist')))),
                $el('div', 'tab-content', lang('Tab Content'), $tab_panes),
            )),
        )),
        $service_notes('py-5 border-top bg-body-tertiary'),
    )),
);

// About: the boutique's story, where to find it and how to look after what
// was bought there.
$hours = function ($day, $time) use ($el, $span) {
    return $el('li', 'd-flex justify-content-between gap-3 py-2 border-bottom', lang('Opening Hours Line'), array(
        $span($day, '', lang('Day')),
        $span($time, 'text-body-secondary', lang('Hours')),
    ));
};
$care = function ($n, $question, $answer) use ($el, $para, $attr) {
    $id = 'bq-care-' . $n;
    return $el('div', 'accordion-item', lang('Care Item'), array(
        $el('h3', 'accordion-header', lang('Care Item Header'), array(
            $el('button', 'accordion-button collapsed', lang('Care Item Button'), array(), array(
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
        $el('div', 'accordion-collapse collapse', lang('Care Item Panel'), array(
            $el('div', 'accordion-body text-body-secondary', lang('Care Item Body'), array($para($answer, 'mb-0'))),
        ), array('id' => $id, '_attrs' => array($attr('data-bs-parent', '#bq-care-list')))),
    ));
};
$pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About'),
    'meta_description' => lang('The story of the boutique, its opening hours and how to look after what you buy.'),
    'tree'             => $page(array(
        $page_header(lang('Our story'), lang('A small shop on a quiet street'), lang('Since 2014 we have chosen every piece ourselves, from makers we know by name.')),
        $el('section', 'py-5', lang('Story'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'ratio ratio-4x3 bg-body-tertiary', lang('Picture Frame'), array(
                            $image('{{product_group_image:gifts}}', $categories['gifts']['title'], 'object-fit-cover', $lazy),
                        )),
                    ), '', '6'),
                    $col(array(
                        $heading('h2', lang('How it started'), 'display-6 mb-4'),
                        $para(lang('The boutique began as a table of rings and cards at a Sunday market. People came back for the wrapping as much as for the rings, so we found a shop with a window and kept wrapping.'), 'mb-3'),
                        $para(lang('Today we work with more than forty small workshops. We visit most of them, we know how each piece is made, and we only sell what we would happily give ourselves.'), 'text-body-secondary mb-4'),
                        $para(lang('Jane Cooper, founder'), 'fst-italic mb-0'),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),
        $el('section', 'py-5 bg-body-tertiary', lang('Visit and Care'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card h-100 border-0 shadow-sm', lang('Visit Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array(
                                $heading('h2', lang('Visit the boutique'), 'h3 mb-4'),
                                $el('p', 'd-flex align-items-start gap-2 mb-2', lang('Address'), array(
                                    $icon('bi-geo-alt', 'mt-1 text-primary'),
                                    $span(lang('Sample Street 1, 34000 Istanbul, Türkiye'), '', lang('Text')),
                                )),
                                $el('p', 'd-flex align-items-center gap-2 mb-2', lang('Phone'), array(
                                    $icon('bi-telephone', 'text-primary'),
                                    $link('+90 555 000 00 00', 'tel:+905550000000', 'link-body-emphasis text-decoration-none'),
                                )),
                                $el('p', 'd-flex align-items-center gap-2 mb-4', lang('Email'), array(
                                    $icon('bi-envelope', 'text-primary'),
                                    $link('hello@example.com', 'mailto:hello@example.com', 'link-body-emphasis text-decoration-none text-break'),
                                )),
                                $heading('h3', lang('Opening hours'), 'h6 text-uppercase fw-normal text-body-secondary mb-2'),
                                $el('ul', 'list-unstyled small mb-4', lang('Opening Hours'), array(
                                    $hours(lang('Monday – Friday'), '10:00 – 19:00'),
                                    $hours(lang('Saturday'), '10:00 – 18:00'),
                                    $hours(lang('Sunday'), lang('Closed')),
                                )),
                                $link(lang('Write to us'), '{{page:contact}}', 'btn btn-outline-dark'),
                            )),
                        ), array('id' => 'bq-visit')),
                    ), '', '5'),
                    $col(array(
                        $el('div', '', lang('Care Guide'), array(
                            $heading('h2', lang('Care guide'), 'h3 mb-3'),
                            $para(lang('A little care keeps a piece looking the way it did on the day you unwrapped it.'), 'text-body-secondary mb-4'),
                            $el('div', 'accordion', lang('Care Questions'), array(
                                $care(1, lang('Silver and gold jewellery'), lang('Put jewellery on after perfume and lotion, and take it off to swim or shower. Wipe it with a soft cloth and keep each piece in its own pouch so the chains do not tangle.')),
                                $care(2, lang('Leather bags'), lang('Keep leather out of strong sun and away from heaters. Stuff the bag with paper when you store it, and treat it with a neutral leather balm twice a year.')),
                                $care(3, lang('Sunglasses and frames'), lang('Clean the lenses with lukewarm water and the cloth in the case, never with a paper towel. Fold them before you put them down, lenses up.')),
                                $care(4, lang('Watches'), lang('Wind a mechanical watch at the same time every day. Keep it away from magnets and phones, and have it serviced every four to five years.')),
                            ), array('id' => 'bq-care-list')),
                        ), array('id' => 'bq-care')),
                    ), '', '7'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    )),
);

// Contact: a message to the boutique; the staff are told, the sender gets
// a reply.
$pages[] = array(
    'key'              => 'contact',
    'name'             => lang('contact-us'),
    'folder'           => 'public',
    'title'            => lang('Contact us'),
    'meta_description' => lang('A question about a piece, an order or a gift? Write to the boutique.'),
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
        $page_header(lang('Contact us'), lang('Write to the boutique'), lang('A question about a piece, an order or a gift? We answer every message ourselves, on the same working day.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('contact_form')), '', '7'),
                    $col(array(
                        $el('aside', 'p-4 p-lg-5 h-100 bg-body-tertiary', lang('Contact Details'), array(
                            $heading('h2', lang('The boutique'), 'h4 mb-4'),
                            $el('p', 'd-flex align-items-start gap-2 mb-3', lang('Address'), array(
                                $icon('bi-geo-alt', 'mt-1 text-primary'),
                                $span(lang('Sample Street 1, 34000 Istanbul, Türkiye'), '', lang('Text')),
                            )),
                            $el('p', 'd-flex align-items-center gap-2 mb-3', lang('Phone'), array(
                                $icon('bi-telephone', 'text-primary'),
                                $link('+90 555 000 00 00', 'tel:+905550000000', 'link-body-emphasis text-decoration-none'),
                            )),
                            $el('p', 'd-flex align-items-center gap-2 mb-4', lang('Email'), array(
                                $icon('bi-envelope', 'text-primary'),
                                $link('hello@example.com', 'mailto:hello@example.com', 'link-body-emphasis text-decoration-none text-break'),
                            )),
                            $para(lang('Open Monday to Saturday from 10:00, closed on Sundays.'), 'small text-body-secondary mb-4'),
                            $el('p', 'small mb-0', lang('More Link'), array(
                                $link(lang('How to look after your pieces'), '{{page:about}}#bq-care', 'link-body-emphasis link-offset-2'),
                            )),
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
$perk = function ($icon_name, $text) use ($el, $icon, $span) {
    return $el('li', 'd-flex align-items-start gap-2 mb-2', lang('List Item'), array(
        $icon($icon_name, 'text-primary'),
        $span($text, '', lang('Text')),
    ));
};
$pages[] = array(
    'key'              => 'newsletter',
    'name'             => lang('newsletter'),
    'folder'           => 'public',
    'title'            => lang('Newsletter'),
    'meta_description' => lang('New arrivals and gift ideas from the boutique, in your inbox first.'),
    'form'             => array(
        'form_name'            => lang('Newsletter'),
        'confirmation_message' => lang('Thank you! You are now subscribed to our newsletter.'),
        'contact_group_id'     => '{{contact_group:newsletter}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('Welcome to our newsletter'),
        'confirm_page_id'      => '{{tab:email_welcome}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Newsletter'), lang('Letters from the boutique'), lang('New arrivals, gift ideas and the odd invitation, once or twice a month.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 p-lg-5 border', lang('Form Box'), array($widget('newsletter_form'))),
                        $el('ul', 'list-unstyled small text-body-secondary mt-4 mb-0', lang('Check List'), array(
                            $perk('bi-stars', lang('The new pieces before they reach the window')),
                            $perk('bi-calendar-heart', lang('An invitation to our evenings in the boutique')),
                            $perk('bi-envelope-check', lang('Two e-mails a month at most, never your address to anyone else')),
                        )),
                    ), '10', '6'),
                ), '', 'center'),
            )),
        )),
    ), false),
);

$pages[] = array(
    'key'      => 'cart',
    'requires' => 'ecommerce',
    'name'     => lang('cart'),
    'folder'   => 'public',
    'title'    => lang('Shopping bag'),
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

// Signing in and out.
$sign_page = function ($key, $name, $title, $widget_key, $folder = 'public') use ($page, $widget) {
    return array(
        'key'     => $key,
        'name'    => $name,
        'folder'  => $folder,
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
$pages[] = array_merge($sign_page('my_account', lang('my-account'), lang('My Account'), 'my_account', 'registration'),
    array('tree' => $page(array($widget('my_account'), $widget('account_security')), false)));
$pages[] = $sign_page('profile', lang('my-profile'), lang('My Profile'), 'profile', 'registration');
$pages[] = $sign_page('change_password', lang('change-password'), lang('Change Password'), 'change_password', 'registration');
$pages[] = $sign_page('email_preferences', lang('email-preferences'), lang('Email Preferences'), 'email_preferences', 'registration');
$pages[] = array_merge($sign_page('address_book', lang('address-book'), lang('Address Book'), 'address_book', 'registration'), array('requires' => 'ecommerce'));

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
            $el('div', 'mx-auto bg-body border', lang('E-mail Card'), array(
                $shared('email_header'),
                $el('div', 'p-4', lang('E-mail Body'), $children, $mail_style('padding:28px')),
                $shared('email_footer'),
            ), $mail_style('max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #eadfd8')),
        ), $mail_style('background:#f7f1ec;padding:24px 12px;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#2b2326')),
    ));
};
$mail_heading = function ($text) use ($heading, $attr) {
    return $heading('h1', $text, 'h4 fw-normal mb-3', array('_attrs' => array($attr('style', 'font-family:Georgia,Times New Roman,serif;font-size:24px;font-weight:400;margin:0 0 16px;color:#2b2326'))));
};
$mail_para = function ($text, $class = 'mb-3') use ($para, $attr) {
    return $para($text, $class, array('_attrs' => array($attr('style', 'margin:0 0 16px'))));
};
$mail_button = function ($text, $href) use ($el, $link, $mail_style, $attr) {
    return $el('p', 'my-4', lang('Button'), array(
        $link($text, $href, 'btn btn-dark px-4', array($attr('style',
            'display:inline-block;padding:12px 26px;font-size:13px;letter-spacing:.12em;text-transform:uppercase;text-decoration:none;'
            . 'background-color:#2b2326;color:#ffffff'))),
    ), $mail_style('margin:24px 0'));
};
// A row of the details table: the label in one cell, the value in the
// other; the row is dropped when the value comes out empty.
$mail_line = function ($label, $values) use ($el, $span, $mail_style) {
    return $el('tr', 'pg-hide-if-empty', lang('Line'), array(
        $el('td', 'pe-3 py-1 text-body-secondary', lang('Label'), array($span($label, '', lang('Text'))),
            $mail_style('padding:4px 16px 4px 0;color:#7a6b70;vertical-align:top')),
        $el('td', 'pg-field-value py-1 fw-semibold', lang('Value'), $values, $mail_style('padding:4px 0;font-weight:600')),
    ));
};
$mail_box = function ($children, $name) use ($el, $mail_style) {
    return $el('div', 'bg-body-tertiary p-3 my-3', $name, $children,
        $mail_style('background:#f7f1ec;padding:16px;margin:16px 0'));
};
$mail_table = function ($rows) use ($el, $mail_style) {
    return $el('table', 'mb-0', lang('Details'), array($el('tbody', '', lang('Rows'), $rows)),
        $mail_style('border-collapse:collapse'));
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
        $mail_para(lang('Thank you for signing up. From now on you hear about new arrivals, gift ideas and our evenings in the boutique first, once or twice a month.')),
        $mail_para(lang('Changed your mind? Every e-mail we send has a link to unsubscribe.'), 'small text-body-secondary mb-3'),
        $mail_button(lang('See what is new'), '{{page:shop}}'),
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
// The account page's links: a list beside the orders.
$account_link = function ($icon_name, $title, $href, $href_token = '') use ($el, $icon, $span) {
    $extra = array('href' => $href);
    if ($href_token !== '') $extra['_bindings'] = array('href' => $href_token);
    return $el('a', 'list-group-item list-group-item-action d-flex align-items-center gap-3 px-0 py-3 bg-transparent', lang('Account Link'), array(
        $icon($icon_name, 'fs-5 text-primary'),
        $span($title, 'flex-grow-1', lang('Text')),
        $icon('bi-chevron-right', 'small text-body-secondary'),
    ), $extra);
};

$widgets = array(

    // The bar's view of the session.
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
            $el('div', 'd-flex align-items-center gap-3', lang('Signed Out'), array(
                $link(lang('Log In'), '#', 'text-nowrap link-body-emphasis text-decoration-none', array(), array('_bindings' => array('href' => '__login_url'))),
                $link(lang('Sign Up'), '#', 'text-nowrap link-body-emphasis link-offset-2', array(), array('_bindings' => array('href' => '__register_url'))),
            ), $show_when('is_signed_out')),
            $el('div', 'dropdown', lang('Signed In'), array(
                $el('a', 'd-flex align-items-center gap-2 link-body-emphasis text-decoration-none dropdown-toggle', lang('Account Button'), array(
                    $icon('bi-person-circle', ''),
                    $span(lang('Jane'), '', lang('First Name'), $bind('__user_first_name')),
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

    // The way to the shopping bag, beside the login region.
    'cart_link' => array(
        'page'     => '',
        'requires' => 'ecommerce',
        'slug'     => lang('cart-link'),
        'config'   => array('regionType' => 'cart_link', 'cart_page_id' => '{{tab:cart}}'),
        'tree'     => 'starter',
    ),

    // The home page: the new arrivals (the Boutique group's own products),
    // then three categories, each in its own layout.
    'home_new'       => $listing('home', 'boutique', $card_new, 8, 'flex-nowrap overflow-x-auto pb-3'),
    'home_jewellery' => $listing('home', 'jewellery', $card_minimal, 4),
    'home_bags'      => $listing('home', 'bags', $card_editorial, 2),
    'home_eyewear'   => $listing('home', 'eyewear', $card_collage, 2, '', '3'),

    // The lookbook's pieces and the gift guide's three tabs.
    'lookbook_products' => $listing('lookbook', 'boutique', $card_minimal, 4),
    'gift_jewellery'    => $listing('gift_guide', 'jewellery', $card_gift, 4),
    'gift_eyewear'      => $listing('gift_guide', 'eyewear', $card_gift, 4),
    'gift_gifts'        => $listing('gift_guide', 'gifts', $card_gift, 4),

    // The shop: the boutique's categories open in place; the filters
    // (price, in stock, the products' attributes) sit in the side panel.
    'shop_catalog' => array(
        'page'     => 'shop',
        'requires' => 'ecommerce',
        'slug'     => lang('catalog'),
        'config'   => array(
            'regionType'               => 'catalog_listing',
            'product_group_id'         => '{{product_group:boutique}}',
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
            'cart_section_label'          => lang('Your shopping bag'),
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
            $heading('h2', lang('Subscribe to our newsletter'), 'h4 mb-2'),
            $para(lang('Leave your e-mail address; we write when there is something worth reading.'), 'text-body-secondary mb-4'),
            $newsletter_line('bq-newsletter-email'),
            $consent('bq-newsletter-consent'),
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
            $el('section', 'py-5 bg-primary-subtle', lang('Newsletter'), array(
                $container(array(
                    $row(array(
                        $col(array(
                            $el('div', 'text-center', lang('Text'), array(
                                $icon('bi-envelope-heart', 'fs-2 text-primary-emphasis'),
                                $heading('h2', lang('Letters from the boutique'), 'h3 mt-2 mb-2'),
                                $para(lang('New arrivals, gift ideas and the odd invitation, once or twice a month.'), 'text-body-secondary mb-4'),
                            )),
                            $newsletter_line('bq-band-email'),
                            $consent('bq-band-consent'),
                            $captcha(),
                        ), '10', '6'),
                    ), '', 'center'),
                ), 'py-lg-3'),
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
                $col(array($field('bq-contact-first-name', lang('First Name'), $input('bq-contact-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
                $col(array($field('bq-contact-last-name', lang('Last Name'), $input('bq-contact-last-name', 'text', 'last_name', 'family-name', false, array('contact_field' => 'last_name')))), '6'),
            ), '3'),
            $field('bq-contact-email', lang('Email'), $input('bq-contact-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address'))),
            $field('bq-contact-subject', lang('Subject'), $input('bq-contact-subject', 'text', 'subject', 'off')),
            $field('bq-contact-message', lang('Your Message'), $textarea('bq-contact-message', 'message', 6)),
            $captcha(),
            $submit(lang('Send Message')),
            $loop(array()),
        )),
    ),

    // The member's account: the orders, and the account's pages in a list
    // beside them.
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
                $el('div', 'text-center border-bottom pb-4 mb-5', lang('Account Header'), array(
                    $para(lang('Welcome back'), 'small text-uppercase text-body-secondary mb-2'),
                    $heading('h1', lang('Jane Cooper'), 'display-6 mb-1', $bind('__display_name')),
                    $para(lang('jane.cooper@example.com'), 'text-body-secondary mb-0', $bind('email_address')),
                )),
                $row(array(
                    $col(array(
                        $heading('h2', lang('My Orders'), 'h4 mb-3'),
                        $el('div', '', lang('Order History'), array(), array('_bindings' => array('section' => 'order_history'))),
                        $el('div', 'p-4 p-lg-5 text-center bg-body-tertiary', lang('No Orders'), array(
                            $icon('bi-bag-heart', 'd-block fs-2 mb-2 text-primary'),
                            $para(lang('You have not ordered anything yet.'), 'mb-3'),
                            $link(lang('Start Shopping'), '{{page:shop}}', 'btn btn-dark'),
                        ), $show_when('no_orders')),
                    ), '', '8'),
                    $col(array(
                        $heading('h2', lang('My Account'), 'h4 mb-3'),
                        $el('div', 'list-group list-group-flush border-top', lang('Account Links'), array(
                            $account_link('bi-person', lang('My Profile'), '#', '__profile_url'),
                            $account_link('bi-journal-bookmark', lang('Address Book'), '#', '__address_book_url'),
                            $account_link('bi-shield-lock', lang('Change Password'), '#', '__change_password_url'),
                            $account_link('bi-envelope-paper', lang('Email Preferences'), '#', '__email_preferences_url'),
                            $account_link('bi-bag', lang('Continue Shopping'), '{{page:shop}}'),
                        )),
                        $link(lang('Log out'), '#', 'btn btn-outline-dark w-100 mt-4', array(), array('_bindings' => array('href' => '__logout_url'))),
                    ), '', '4'),
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
    // Google connection, remembered devices and two-step verification,
    // under the account overview on the same page.
    'account_security' => array(
        'page'   => 'my_account',
        'slug'   => lang('security'),
        'config' => array('regionType' => 'account_security', 'login_page_id' => '{{tab:login}}'),
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
            $mail_para(lang('It came from the contact form of the boutique. Answer the sender by e-mail.')),
            $mail_box(array(
                $mail_table(array(
                    $mail_line(lang('Sender'), array(
                        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
                        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
                    )),
                    $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
                    $mail_line(lang('Subject'), array($span(lang('A ring for an anniversary'), '', lang('Subject'), $bind('subject')))),
                )),
            ), lang('Sender')),
            $el('div', 'lh-lg', lang('Message'), array(), array_merge(
                array('text' => lang('Hello, could you engrave two initials inside the Solitaire Ring before you send it?')), $bind('message'))),
            $el('p', 'small text-body-secondary mt-4 mb-0', lang('Reference'), array(
                $span(lang('Reference Code'), '', lang('Label')),
                $span('ABC123', 'fw-semibold', lang('Reference Code'), $bind('reference_code')),
            ), $mail_style('font-size:13px;color:#7a6b70;margin:24px 0 0')),
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
                $span(lang('Hello'), '', lang('Text')),
                $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
            ), $mail_style('margin:0 0 16px')),
            $mail_para(lang('Thank you for writing to the boutique. We answer every message ourselves, usually on the same working day.')),
            $mail_box(array(
                $el('p', 'fw-semibold mb-2', lang('Subject'), array(), array_merge(
                    array('text' => lang('A ring for an anniversary'), '_attrs' => array($attr('style', 'font-weight:600;margin:0 0 8px'))), $bind('subject'))),
                $el('div', 'lh-lg', lang('Message'), array(), array_merge(
                    array('text' => lang('Hello, could you engrave two initials inside the Solitaire Ring before you send it?')), $bind('message'))),
            ), lang('Your Message')),
            $mail_button(lang('Visit the boutique'), '{{page:home}}'),
        )),
    ),
    'email_order' => array(
        'page'     => 'email_order',
        'requires' => 'ecommerce',
        'slug'     => lang('order'),
        'config'   => array('regionType' => 'order_view', 'date_format' => ''),
        'tree'     => $widget_root(array(
            $mail_heading(lang('Thank you for your order')),
            $mail_para(lang('We are wrapping it now. You will hear from us again when it leaves the boutique.')),
            $el('p', 'mb-3', lang('Order Details'), array(
                $span(lang('Order Number'), 'text-body-secondary', lang('Label')),
                $span('PGB-12345', 'fw-semibold', lang('Order Number'), $bind('__order_no')),
                $span(' · ', 'text-body-secondary', lang('Separator')),
                $span('27.09.2026', '', lang('Date'), $bind('__order_date')),
            ), $mail_style('margin:0 0 16px')),
            $el('table', 'table align-middle mb-3', lang('Order Items'), array(
                $el('tbody', '', lang('Rows'), array(
                    $loop(array(
                        $el('tr', '', lang('Row'), array(
                            $el('td', 'ps-0', lang('Product'), array(
                                $span(lang('Solitaire Ring'), 'fw-semibold', lang('Product Name'), $bind('__item_short_description')),
                                $span(' × ', 'text-body-secondary', lang('Separator')),
                                $span('1', '', lang('Quantity'), $bind('__item_qty')),
                            ), $mail_style('padding:10px 0;border-bottom:1px solid #eadfd8')),
                            $el('td', 'pe-0 text-end', lang('Line Total'), array(
                                $span(lang('$145.00'), '', lang('Amount'), $bind('__item_total')),
                            ), $mail_style('padding:10px 0;border-bottom:1px solid #eadfd8;text-align:right')),
                        )),
                    )),
                )),
            ), $mail_style('width:100%;border-collapse:collapse;margin:0 0 16px')),
            $el('table', 'table table-sm mb-4', lang('Totals'), array(
                $el('tbody', '', lang('Rows'), array(
                    $el('tr', '', lang('Subtotal'), array(
                        $el('td', 'ps-0 text-body-secondary', lang('Label'), array($span(lang('Subtotal'), '', lang('Text')))),
                        $el('td', 'pe-0 text-end', lang('Value'), array($span(lang('$145.00'), '', lang('Subtotal'), $bind('__order_subtotal'))), $mail_style('text-align:right')),
                    )),
                    $el('tr', '', lang('Discount'), array(
                        $el('td', 'ps-0 text-body-secondary', lang('Label'), array($span(lang('Discount'), '', lang('Text')))),
                        $el('td', 'pe-0 text-end', lang('Value'), array($span(lang('$145.00'), '', lang('Discount'), $bind('__order_discount'))), $mail_style('text-align:right')),
                    ), $show_when('has_discount')),
                    $el('tr', '', lang('Shipping'), array(
                        $el('td', 'ps-0 text-body-secondary', lang('Label'), array($span(lang('Shipping'), '', lang('Text')))),
                        $el('td', 'pe-0 text-end', lang('Value'), array($span(lang('$145.00'), '', lang('Shipping'), $bind('__order_shipping'))), $mail_style('text-align:right')),
                    ), $show_when('has_shipping_cost')),
                    $el('tr', '', lang('Tax'), array(
                        $el('td', 'ps-0 text-body-secondary', lang('Label'), array($span(lang('VAT'), '', lang('Text')))),
                        $el('td', 'pe-0 text-end', lang('Value'), array($span(lang('$145.00'), '', lang('VAT'), $bind('__order_tax'))), $mail_style('text-align:right')),
                    ), $show_when('has_tax')),
                    $el('tr', 'fw-bold', lang('Total'), array(
                        $el('td', 'ps-0', lang('Label'), array($span(lang('Total'), '', lang('Text')))),
                        $el('td', 'pe-0 text-end', lang('Value'), array($span(lang('$145.00'), '', lang('Total'), $bind('__order_total'))), $mail_style('text-align:right;font-weight:700')),
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
// Prices in the base currency, USD for any currency not listed; the
// pictures are in includes/design_templates/pinegrap-boutique/.
$product = function ($sku, $image, $title, $description, $usd, $try, $flags = array()) {
    return array_merge(array(
        'sku'         => $sku,
        'image'       => $image,
        'title'       => $title,
        'description' => $description,
        'price'       => array('USD' => $usd, 'EUR' => $usd, 'GBP' => $usd, 'TRY' => $try),
    ), $flags);
};
$jewellery = array(
    $product('PGB-1001', 'bq-jewellery-gem.jpg', lang('Solitaire Ring'), lang('A single faceted stone on a fine band of recycled sterling silver.'), 145, 5499, array('new' => true, 'featured' => true)),
    $product('PGB-1002', 'bq-jewellery-hourglass.jpg', lang('Hourglass Pendant'), lang('A small hourglass charm on a 45 cm chain, plated in 18k gold.'), 85, 3199),
    $product('PGB-1003', 'bq-jewellery-flower1.jpg', lang('Daisy Earrings'), lang('Enamel daisies on hypoallergenic studs, light enough to forget you are wearing them.'), 55, 2099, array('new' => true)),
    $product('PGB-1004', 'bq-jewellery-moon-stars.jpg', lang('Moon & Stars Necklace'), lang('A crescent and two tiny stars that sit just below the collarbone.'), 95, 3599, array('featured' => true)),
    $product('PGB-1005', 'bq-jewellery-suit-heart.jpg', lang('Heart Signet Ring'), lang('A slim signet ring with an engraved heart, to wear alone or stacked.'), 75, 2799),
);
$bags = array(
    $product('PGB-2001', 'bq-bags-handbag.jpg', lang('Leather Top-Handle Bag'), lang('Structured calf leather with a suede lining and a strap that comes off.'), 245, 9299, array('new' => true, 'featured' => true)),
    $product('PGB-2002', 'bq-bags-bag.jpg', lang('Canvas Tote'), lang('Heavy cotton canvas with leather handles and an inner pocket for your keys.'), 65, 2499),
    $product('PGB-2003', 'bq-bags-bag-heart.jpg', lang('Heart Clutch'), lang('A small evening clutch with a heart clasp and a chain to wear it over the shoulder.'), 110, 4199, array('new' => true)),
    $product('PGB-2004', 'bq-bags-backpack2.jpg', lang('City Backpack'), lang('Waxed canvas, a padded laptop sleeve and a magnetic flap.'), 155, 5899),
);
$eyewear = array(
    $product('PGB-3001', 'bq-eyewear-sunglasses.jpg', lang('Cat-Eye Sunglasses'), lang('Acetate frames with polarised lenses and full UV400 protection.'), 120, 4599, array('new' => true)),
    $product('PGB-3002', 'bq-eyewear-eyeglasses.jpg', lang('Round Optical Frames'), lang('Light titanium frames, ready for your own prescription lenses.'), 135, 5099),
    $product('PGB-3003', 'bq-eyewear-watch.jpg', lang('Classic Leather Watch'), lang('A 36 mm steel case, a sapphire crystal and an Italian leather strap.'), 210, 7999, array('featured' => true)),
    $product('PGB-3004', 'bq-eyewear-smartwatch.jpg', lang('Minimal Smart Watch'), lang('Counts your steps and your sleep behind a plain face that looks like an ordinary watch.'), 189, 7199),
);
$gifts = array(
    $product('PGB-4001', 'bq-gifts-gift.jpg', lang('Gift Box'), lang('A candle, a silk scrunchie and a card in a box, wrapped and ready to give.'), 59, 2299, array('featured' => true)),
    $product('PGB-4002', 'bq-gifts-balloon-heart.jpg', lang('Celebration Card Set'), lang('Six letterpress cards with envelopes, for birthdays and good news.'), 24, 899),
    $product('PGB-4003', 'bq-gifts-feather.jpg', lang('Feather Bookmark'), lang('A brass feather that slips between the pages without a crease.'), 19, 699, array('new' => true)),
    $product('PGB-4004', 'bq-gifts-cup-hot.jpg', lang('Porcelain Cup & Saucer'), lang('Fine white porcelain with a gilded rim, for slow mornings.'), 42, 1599),
    $product('PGB-4005', 'bq-gifts-postcard.jpg', lang('Illustrated Postcards'), lang('Twelve postcards of city streets, printed on thick recycled card.'), 16, 599),
);
// The new arrivals of every category, in the Boutique group itself too.
$new_in = array();
foreach (array($jewellery, $bags, $eyewear, $gifts) as $list) {
    foreach ($list as $p) {
        if (!empty($p['new'])) $new_in[] = $p;
    }
}
$catalog = array(
    'folder'  => 'pinegrap_boutique_catalog',
    // The Details tab of every product page.
    'details' => array(
        lang('Gift-wrapped for free and sent within one working day.'),
        lang('Returns within 30 days of delivery.'),
        lang('A sample product of the template: change its name, price and pictures in the catalog, or remove it.'),
    ),
    'groups' => array(
        'boutique' => array(
            'parent'      => 'root',
            'title'       => lang('Boutique'),
            'description' => lang('Jewellery, bags, eyewear and gifts, chosen one by one.'),
            'products'    => $new_in,
        ),
        'jewellery' => array(
            'parent' => 'boutique', 'sort_order' => 1,
            'title' => $categories['jewellery']['title'], 'description' => $categories['jewellery']['text'],
            'image' => 'bq-category-jewellery.jpg',
            'products' => $jewellery,
        ),
        'bags' => array(
            'parent' => 'boutique', 'sort_order' => 2,
            'title' => $categories['bags']['title'], 'description' => $categories['bags']['text'],
            'image' => 'bq-category-bags.jpg',
            'products' => $bags,
        ),
        'eyewear' => array(
            'parent' => 'boutique', 'sort_order' => 3,
            'title' => $categories['eyewear']['title'], 'description' => $categories['eyewear']['text'],
            'image' => 'bq-category-eyewear.jpg',
            'products' => $eyewear,
        ),
        'gifts' => array(
            'parent' => 'boutique', 'sort_order' => 4,
            'title' => $categories['gifts']['title'], 'description' => $categories['gifts']['text'],
            'image' => 'bq-category-gifts.jpg',
            'products' => $gifts,
        ),
    ),
);

return array(
    'name'        => lang('Boutique'),
    'version'     => '1.0.2',
    'framework'   => 'bootstrap5',
    'order'       => 30,
    'icon'        => 'bi-gem',
    // The picture on the template's card and beside the designs made from
    // it: an editorial shop front (pg_design_thumb_svg()).
    'thumb'       => 'boutique',
    'requires'    => 'ecommerce',
    'description' => lang('A boutique: an editorial home page, a lookbook, a gift guide, the shop with filters, product, cart, checkout and account pages.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor.
    'look'        => 'elegant',
    'palette'     => 'burgundy',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('A catalog to start from: jewellery, bags, eyewear & watches and gifts, with pictures.'),
        lang('A lookbook and a gift guide in tabs, both showing products from the catalog.'),
        lang('A newsletter form on the pages: every address joins one contact group, ready for e-mail campaigns.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_boutique', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
    ),

    'catalog'        => $catalog,

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Boutique Newsletter'),
            'description'  => lang('The addresses left on the newsletter form of the boutique.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'announcement' => array('name' => lang('Boutique Announcement'), 'tree' => $announcement()),
        'header'       => array('name' => lang('Boutique Header'), 'tree' => $header()),
        'footer'       => array('name' => lang('Boutique Footer'), 'tree' => $footer()),
        'email_header' => array('name' => lang('Boutique E-mail Header'), 'tree' =>
            $el('div', 'px-4 py-4 text-center border-bottom', lang('E-mail Header'), array(
                $link('{{site_name}}', '{{page:home}}', 'fs-5 fw-light text-uppercase text-decoration-none link-body-emphasis',
                    array($attr('style', 'font-family:Georgia,Times New Roman,serif;font-size:20px;letter-spacing:.18em;text-transform:uppercase;color:#2b2326;text-decoration:none'))),
            ), $mail_style('padding:22px 24px;text-align:center;border-bottom:1px solid #eadfd8'))),
        'email_footer' => array('name' => lang('Boutique E-mail Footer'), 'tree' =>
            $el('div', 'px-4 py-3 text-center border-top small text-body-secondary', lang('E-mail Footer'), array(
                $para('© {{year}} {{site_name}}', 'mb-1', array('_attrs' => array($attr('style', 'margin:0 0 4px')))),
                $link(lang('Visit the boutique'), '{{page:home}}', 'link-secondary',
                    array($attr('style', 'color:#7a6b70'))),
            ), $mail_style('padding:16px 24px;text-align:center;border-top:1px solid #eadfd8;font-size:13px;color:#7a6b70'))),
    ),
);
