<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Art Gallery", part 2 of its files: the parts every page
 * repeats (account bar with the notice, header, footer, private viewing
 * band), the cards of the lists (artist, exhibition, work, advisory,
 * collector), the form fields, the comment thread, the staff bar and the
 * newsletter and journal parts. Included after parts.php in the same scope:
 * it builds on the node builders and the data defined there.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// ── The parts every page repeats ────────────────────────────────────────

// The phone link of a number as the browser dials it.
$tel = function ($number) {
    return 'tel:' . preg_replace('/[^0-9+]/', '', $number);
};

// The line in the account bar: what is on now, that entry is free, and the
// way to the exhibitions. A shared component.
$notice = function () use ($el, $icon, $span, $link, $query, $exhibition_statuses) {
    return $el('p', 'd-flex flex-wrap align-items-center gap-2 mb-0', lang('Notice'), array(
        $icon('bi-easel2', 'text-primary'),
        $span(lang('Free entry, Tuesday to Saturday.'), '', lang('Text')),
        $link(lang('See what is on now'), '{{page:exhibitions}}?' . $query(array('status' => $exhibition_statuses['now'])), 'link-primary fw-semibold'),
    ));
};

// The bar above the header: the notice on the left, the language switcher,
// the login region and the cart (with the shop) on the right. Every page
// places it as one shared component.
$account_bar_tree = function () use ($el, $widget, $shared, $lang_switcher) {
    return $el('div', 'border-bottom small bg-body', lang('Account Bar'), array(
        $el('div', 'container d-flex flex-wrap align-items-center justify-content-between gap-2 py-1', lang('Account Area'), array(
            $el('div', '', lang('Announcement Area'), array($shared('notice'))),
            $el('div', 'd-flex align-items-center gap-2 ms-auto', lang('Account Links'), array(
                $lang_switcher(), $widget('login_region'), $widget('cart_link'),
            )),
        )),
    ));
};

// The gallery's name, set in the heading font, in the header and the footer.
$brand = function ($class) use ($el, $span) {
    return $el('a', $class, lang('Brand'), array(
        $span('{{site_name}}', 'pgl-brand', lang('Name')),
    ), array('href' => '{{page:home}}'));
};

// The header: the gallery's name, the menu and the way to ask for a
// private viewing. The links to the current page are marked active on the
// server (smart active state), so one header serves every page. The shop
// is linked from the shop pages only, so the header stands on a site
// without it.
$header = function () use ($el, $link, $icon, $span, $attr, $brand) {
    $items = array();
    foreach (array(
        array('exhibitions', lang('Exhibitions')),
        array('artists', lang('Artists')),
        array('works', lang('Works')),
        array('advisory', lang('Art Advisory')),
        array('blog', lang('Journal')),
        array('visit', lang('Visit')),
    ) as $n) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($n[1], '{{page:' . $n[0] . '}}', 'nav-link text-nowrap')));
    }
    return $el('header', 'bg-body border-bottom', lang('Site Header'), array(
        $el('nav', 'navbar navbar-expand-lg py-3', lang('Navbar'), array(
            $el('div', 'container', lang('Navbar Container'), array(
                $brand('navbar-brand fs-3 link-body-emphasis'),
                $el('button', 'navbar-toggler', lang('Toggler'), array(
                    $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#gl-nav'),
                    $attr('aria-controls', 'gl-nav'),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-label', lang('Toggle navigation')),
                ))),
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $el('ul', 'navbar-nav ms-lg-auto me-lg-3 column-gap-lg-2 py-3 py-lg-0', lang('Nav Menu'), $items, array('smartActive' => true)),
                    $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center gap-2 pb-2 pb-lg-0', lang('Header Actions'), array(
                        $el('a', 'btn btn-primary rounded-0 d-inline-flex align-items-center justify-content-center gap-2 px-4 text-nowrap', lang('Call to Action'), array(
                            $icon('bi-calendar2-heart'),
                            $span(lang('Book a Private Viewing'), '', lang('Link Text')),
                        ), array('href' => '{{page:viewing}}')),
                    )),
                ), array('id' => 'gl-nav')),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Main navigation'))))),
    ));
};
