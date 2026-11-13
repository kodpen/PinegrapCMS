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
    return $el('p', 'mb-0', lang('Notice'), array(
        $icon('bi-easel2', 'text-primary me-1'),
        $span(lang('Free entry, Tuesday to Saturday.'), '', lang('Text')),
        $link(lang('See what is on now'), '{{page:exhibitions}}?' . $query(array('status' => $exhibition_statuses['now'])), 'link-primary fw-semibold ms-1'),
    ));
};

// The bar above the header: the notice on the left, the language switcher,
// the login region and the cart (with the shop) on the right. Every page
// places it as one shared component.
$account_bar_tree = function () use ($el, $widget, $shared, $lang_switcher) {
    return $el('div', 'border-bottom small bg-body', lang('Account Bar'), array(
        $el('div', 'container d-flex flex-wrap align-items-center justify-content-between gap-2 py-1', lang('Account Area'), array(
            $el('div', '', lang('Announcement Area'), array($shared('notice'))),
            $el('div', 'd-flex flex-wrap align-items-center justify-content-end column-gap-1 column-gap-sm-2 row-gap-1 ms-auto', lang('Account Links'), array(
                $lang_switcher('text-nowrap'), $widget('login_region'), $widget('cart_link'),
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
        // The bar opens out from 1200 px: from 992 px the brand, the links
        // and the button overflowed the container in Turkish.
        $el('nav', 'navbar navbar-expand-xl py-3', lang('Navbar'), array(
            $el('div', 'container', lang('Navbar Container'), array(
                $brand('navbar-brand fs-3 link-body-emphasis text-truncate'),
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
                    $el('ul', 'navbar-nav ms-xl-auto me-xl-3 column-gap-xl-2 py-3 py-xl-0', lang('Nav Menu'), $items, array('smartActive' => true)),
                    $el('div', 'd-flex flex-column flex-xl-row align-items-xl-center gap-2 pb-2 pb-xl-0', lang('Header Actions'), array(
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

// The footer: the gallery in a sentence and its profiles, what is on, the
// collectors' pages, the gallery's pages and how to reach it with the
// opening hours; at the bottom the year and the site's name.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $gallery, $opening_hours, $attr, $brand, $tel, $query, $exhibition_statuses) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $items);
    };
    $status_links = array();
    foreach ($exhibition_statuses as $title) {
        $status_links[] = $link($title, '{{page:exhibitions}}?' . $query(array('status' => $title)), $plain);
    }
    $social = function ($name, $label) use ($el, $icon, $attr) {
        return $el('a', 'link-body-emphasis fs-5', $label, array($icon($name)), array('href' => '#', '_attrs' => array($attr('aria-label', $label))));
    };
    $contact_line = function ($icon_name, $child, $name) use ($el, $icon) {
        return $el('p', 'd-flex align-items-start gap-2 small mb-2', $name, array($icon($icon_name), $child));
    };
    $hours = array();
    foreach ($opening_hours as $h) {
        $hours[] = $el('span', 'd-block', lang('Opening Hours'), array(
            $span($h['days'] . ': ', '', lang('Days')),
            $span($h['hours'], 'pgl-price', lang('Hours')),
        ));
    }

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body border-top', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-lg-4', lang('About the Gallery'), array(
                    $brand('d-inline-block fs-3 mb-3 ' . $plain),
                    $para($gallery['tagline'], 'small text-body-secondary mb-3'),
                    $el('div', 'd-flex gap-3', lang('Social Links'), array(
                        $social('bi-instagram', 'Instagram'),
                        $social('bi-facebook', 'Facebook'),
                        $social('bi-pinterest', 'Pinterest'),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Exhibition Links'), array(
                    $heading('h2', lang('Exhibitions'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array_merge($status_links, array(
                        $link(lang('Artists'), '{{page:artists}}', $plain),
                        $link(lang('Works'), '{{page:works}}', $plain),
                    ))),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Collector Links'), array(
                    $heading('h2', lang('For Collectors'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Ask About a Work'), '{{page:enquire}}', $plain),
                        $link(lang('My Enquiries'), '{{page:my_enquiries}}', $plain),
                        $link(lang('Book a Private Viewing'), '{{page:viewing}}', $plain),
                        $link(lang('My Private Viewings'), '{{page:my_viewings}}', $plain),
                        $link(lang('Art Advisory'), '{{page:advisory}}', $plain),
                        $link(lang('FAQ'), '{{page:faq}}', $plain),
                        $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Footer Links'), array(
                    $heading('h2', lang('The Gallery'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('About Us'), '{{page:about}}', $plain),
                        $link(lang('Journal'), '{{page:blog}}', $plain),
                        $link(lang('Newsletter'), '{{page:newsletter}}', $plain),
                        $link(lang('Contact Us'), '{{page:contact}}', $plain),
                        $link(lang('My Account'), '{{page:my_account}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Contact Details'), array(
                    $heading('h2', lang('Visit'), 'h6 text-uppercase small fw-bold mb-3'),
                    $contact_line('bi-geo-alt', $link($gallery['address'], '{{page:visit}}', $plain), lang('Address')),
                    $contact_line('bi-telephone', $link($gallery['phone'], $tel($gallery['phone']), $plain), lang('Phone')),
                    $contact_line('bi-envelope', $link($gallery['email'], 'mailto:' . $gallery['email'], $plain . ' text-break'), lang('Email')),
                    $contact_line('bi-clock', $el('span', '', lang('Opening Hours'), $hours), lang('Opening Hours')),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $para(lang('All images of works are shown with the artists\' permission.'), 'small text-body-secondary mb-0'),
            )),
        )),
    ));
};

// The private viewing band: a full-width dark band with the two things most
// visitors come back for. Shared, so every page that ends with it carries
// the same offer.
$viewing_band = function () use ($el, $heading, $para, $link, $container, $row, $col, $attr) {
    return $el('section', 'py-5 bg-body text-body', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $heading('h2', lang('See the works in a quiet room'), 'pgl-display h2 mb-2'),
                    $para(lang('Book a private viewing before or after opening hours, or ask us about any work you have seen here.'), 'mb-0'),
                ), '', '8'),
                $col(array(
                    $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                        $link(lang('Book a Private Viewing'), '{{page:viewing}}', 'btn btn-secondary btn-lg rounded-0 px-4'),
                        $link(lang('Ask About a Work'), '{{page:enquire}}', 'btn btn-link btn-lg text-reset'),
                    )),
                ), '', '4'),
            ), '4', '', 'align-items-center'),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// An icon in a square tile, the way the advisory services are marked.
$icon_tile = function ($name, $class = '') use ($el, $icon) {
    return $el('span', trim('d-inline-flex align-items-center justify-content-center border p-3 lh-1 ' . $class), lang('Icon Box'), array(
        $icon($name, 'fs-4'),
    ));
};

// ── Cards of the lists ──────────────────────────────────────────────────
// Each card of a collection list is bound to the form's fields of the same
// name; the sample fills the canvas. Artists: photo, name, medium, based,
// summary. Exhibitions: photo_1, title, status, dates, opening, summary.
// Works: photo, title, artist (a reference to the Artists form, printed by
// the artist's name through the reference), technique, size, price,
// availability.

// An artist's card (the home and the artists page): the portrait, the
// medium, the name linking the artist's page, where the studio is and the
// summary.
$artist_card = function ($sample_key = 'lena') use ($el, $para, $span, $link, $image, $col, $bind, $artists, $mediums, $portrait, $attr) {
    $a = $artists[$sample_key];
    return $col(array(
        $el('article', 'card h-100 border-0 bg-transparent rounded-0 pgl-lift', lang('Artist Card'), array(
            $el('span', 'pg-field-value d-block ratio ratio-1x1 bg-body-tertiary mb-3 pgl-frame', lang('Picture Frame'), array(
                $image($portrait($a['name'], 600), $a['name'], 'w-100 h-100 object-fit-cover',
                    array('_bindings' => array('src' => 'photo', 'alt' => 'name'), '_attrs' => array($attr('loading', 'lazy')))),
            )),
            $span($mediums[$a['medium']], 'pgl-eyebrow d-block small text-uppercase fw-semibold text-body-secondary mb-1', lang('Art Form'), $bind('medium')),
            $el('h3', 'pgl-display h4 position-static mb-1', lang('Artist Name'), array(
                $link($a['name'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                    array('_bindings' => array('text' => 'name', 'href' => 'form_item_view'))),
            )),
            $span($a['based'], 'd-block small text-body-secondary mb-2', lang('Studio'), $bind('based')),
            $para($a['summary'], 'small mb-0', $bind('summary')),
        )),
    ), '6', '4');
};

// An exhibition's card (the home and the exhibitions page): the picture,
// the state, the title linking the exhibition's page, the dates, the
// opening night and the summary.
$exhibition_card = function ($sample_key = 'after_hours') use ($el, $para, $span, $link, $image, $col, $bind, $icon, $exhibitions, $exhibition_statuses, $photo, $attr) {
    $x = $exhibitions[$sample_key];
    return $col(array(
        $el('article', 'card h-100 border-0 bg-transparent rounded-0 pgl-lift', lang('Exhibition Card'), array(
            $el('span', 'pg-field-value d-block ratio ratio-4x3 bg-body-tertiary mb-3 pgl-frame', lang('Picture Frame'), array(
                $image($photo('exhibition-' . $sample_key, 900, 675), $x['title'], 'w-100 h-100 object-fit-cover',
                    array('_bindings' => array('src' => 'photo_1', 'alt' => 'title'), '_attrs' => array($attr('loading', 'lazy')))),
            )),
            $span($exhibition_statuses[$x['status']], 'badge rounded-0 text-bg-primary align-self-start mb-2', lang('Status'), $bind('status')),
            $el('h3', 'pgl-display h3 position-static mb-1', lang('Exhibition Title'), array(
                $link($x['title'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                    array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
            )),
            $el('p', 'd-flex align-items-start gap-1 small fw-semibold mb-1', lang('Dates'), array(
                $icon('bi-calendar-range'),
                $span($x['dates'], '', lang('Dates'), $bind('dates')),
            )),
            $el('p', 'd-flex align-items-start gap-1 small mb-2', lang('Opening Night'), array(
                $icon('bi-stars'),
                $span($x['opening'], '', lang('Opening Night'), $bind('opening')),
            )),
            $para($x['summary'], 'small text-body-secondary mb-0', $bind('summary')),
        )),
    ), '6', '4');
};

// A work's artist: the field holds the artist's code, so a row prints the
// artist's name through the reference into the Artists form.
$artist_ref = function ($props = array('text' => 'name')) {
    $bindings = array();
    $params = array();
    foreach ($props as $prop => $show) {
        $bindings[$prop] = '__ref';
        $params[$prop] = array('field' => 'artist', 'page_id' => '{{tab:artist_new}}', 'show' => $show);
    }
    return array('_bindings' => $bindings, '_bindParams' => $params);
};

// A work's caption, the way a label beside it on the wall reads: the
// artist, the title, the technique and the size.
$work_caption = function ($sample_key = 'last_table') use ($el, $span, $artist_ref, $bind, $works, $artists) {
    $w = $works[$sample_key];
    return $el('div', 'pgl-caption small', lang('Caption'), array(
        $span($artists[$w['artist']]['name'], 'd-block fw-semibold', lang('Artist'), $artist_ref()),
        $span($w['technique'], 'd-block text-body-secondary', lang('Technique'), $bind('technique')),
        $span($w['size'], 'd-block text-body-secondary', lang('Size'), $bind('size')),
    ));
};

// A work's card (an artist's page, an exhibition's page and the works
// page): the work framed, the title linking its page, the caption, the
// price and its availability.
$work_card = function ($sample_key = 'last_table') use ($el, $span, $link, $image, $col, $bind, $works, $availability, $work_price, $work_caption, $photo, $attr) {
    $w = $works[$sample_key];
    return $col(array(
        $el('article', 'card h-100 border-0 bg-transparent rounded-0 pgl-lift', lang('Work Card'), array(
            $el('span', 'pg-field-value d-block ratio ratio-1x1 bg-body-tertiary mb-3 pgl-frame', lang('Picture Frame'), array(
                $image($photo('work-' . $sample_key, 700, 700), $w['title'], 'w-100 h-100 object-fit-contain p-3',
                    array('_bindings' => array('src' => 'photo', 'alt' => 'title'), '_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('h3', 'pgl-display h5 fst-italic position-static mb-1', lang('Work Title'), array(
                $link($w['title'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                    array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
            )),
            $work_caption($sample_key),
            $el('p', 'd-flex flex-wrap align-items-center gap-2 small mt-2 mb-0', lang('Price and Availability'), array(
                $span($work_price($sample_key), 'pgl-price fw-semibold', lang('Price'), $bind('price')),
                $span($availability[$w['state']], 'badge rounded-0 border text-body', lang('Availability'), $bind('availability')),
            )),
        )),
    ), '6', '3');
};

// An advisory service's card (the advisory page and the home): the icon,
// the name, a sentence and an enquiry with its kind chosen.
$advisory_card = function ($key) use ($el, $heading, $para, $link, $col, $icon_tile, $advisory, $query, $enquiry_choices) {
    $s = $advisory[$key];
    $kinds = array(
        'first_work' => $enquiry_choices[0],
        'collection' => $enquiry_choices[0],
        'workplace'  => $enquiry_choices[4],
        'commission' => $enquiry_choices[2],
        'framing'    => $enquiry_choices[3],
    );
    return $col(array(
        $el('article', 'card h-100 rounded-0 pgl-lift', lang('Advisory Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile($s['icon'], 'align-self-start mb-3'),
                $heading('h3', $s['title'], 'pgl-display h4 mb-2'),
                $para($s['text'], 'small text-body-secondary mb-4'),
                $link(lang('Ask us'), '{{page:enquire}}?' . $query(array('kind' => $kinds[$key])), 'btn btn-outline-primary rounded-0 align-self-start mt-auto'),
            )),
        )),
    ), '6', '4');
};

// What a collector says: the quote and the collector, with the artist the
// words are about.
$testimonial_card = function ($n) use ($el, $para, $span, $col, $testimonials, $artists) {
    $t = $testimonials[$n];
    return $col(array(
        $el('figure', 'h-100 border-start border-2 ps-4 mb-0', lang('Collector Quote'), array(
            $el('blockquote', 'pgl-quote mb-3', lang('Quote'), array($para($t['quote'], 'mb-0'))),
            $el('figcaption', 'small', lang('Collector'), array(
                $span($t['name'], 'd-block fw-semibold', lang('Name')),
                $span($t['role'] . ' · ' . $artists[$t['artist']]['name'], 'd-block text-body-secondary', lang('Role')),
            )),
        )),
    ), '', '4');
};

// ── Form fields ─────────────────────────────────────────────────────────
$field = function ($id, $label, $control, $class = 'mb-3', $hint = '') use ($el, $attr, $para) {
    $children = array(
        $el('label', 'form-label', lang('Label'), array(), array('text' => $label, '_attrs' => array($attr('for', $id)))),
        $control,
    );
    if ($hint !== '') $children[] = $para($hint, 'form-text', array('id' => $id . '-help'));
    return $el('div', $class, $label, $children);
};
$input = function ($id, $type, $name, $autocomplete = '', $required = true, $cf = array(), $more = array()) use ($el, $attr) {
    $attrs = array($attr('type', $type), $attr('name', $name));
    if ($required) $attrs[] = $attr('required', '');
    if ($autocomplete !== '') $attrs[] = $attr('autocomplete', $autocomplete);
    $props = array('id' => $id, '_attrs' => array_merge($attrs, $more));
    if ($cf) $props['_cf'] = $cf;
    return $el('input', 'form-control', lang('Field'), array(), $props);
};
$textarea = function ($id, $name, $rows, $required = true, $cf = array()) use ($el, $attr) {
    $attrs = array($attr('name', $name), $attr('rows', (string)$rows));
    if ($required) $attrs[] = $attr('required', '');
    $props = array('id' => $id, '_attrs' => $attrs);
    if ($cf) $props['_cf'] = $cf;
    return $el('textarea', 'form-control', lang('Field'), array(), $props);
};
// A pick list: the first, empty option is the prompt, not an answer.
// $selected is the answer chosen to begin with (no prompt then).
$select = function ($id, $name, $prompt, $options, $required = true, $selected = '', $cf = array()) use ($el, $attr) {
    $children = array();
    if ($selected === '') {
        $children[] = $el('option', '', lang('Option'), array(), array('text' => $prompt, '_attrs' => array($attr('value', ''))));
    }
    foreach ($options as $text) {
        $oattrs = array($attr('value', $text));
        if ($text === $selected) $oattrs[] = $attr('selected', '');
        $children[] = $el('option', '', lang('Option'), array(), array('text' => $text, '_attrs' => $oattrs));
    }
    $attrs = array($attr('name', $name));
    if ($required) $attrs[] = $attr('required', '');
    $props = array('id' => $id, '_attrs' => $attrs);
    if ($cf) $props['_cf'] = $cf;
    return $el('select', 'form-select', lang('Field'), $children, $props);
};
$submit = function ($text, $class = 'btn btn-primary rounded-0 px-4') use ($el, $attr) {
    return $el('button', $class, lang('Send Button'), array(), array('text' => $text, '_attrs' => array($attr('type', 'submit'))));
};
$captcha = function () use ($el) {
    return $el('div', 'mb-3', lang('CAPTCHA'), array(), array('_bindings' => array('section' => 'captcha')));
};
// A box the visitor must tick: what was agreed to is stored with the form.
// $privacy adds a link to the privacy page after the label.
$agree = function ($id, $name, $text, $privacy = false) use ($el, $attr, $link) {
    $children = array(
        $el('input', 'form-check-input', lang('Field'), array(), array('id' => $id, '_attrs' => array(
            $attr('type', 'checkbox'), $attr('name', $name), $attr('value', 'yes'), $attr('required', ''),
        ))),
        $el('label', 'form-check-label', lang('Label'), array(), array('text' => $text, '_attrs' => array($attr('for', $id)))),
    );
    if ($privacy) $children[] = $link(lang('Privacy Policy'), '{{page:privacy}}', 'ms-1');
    return $el('div', 'form-check small mb-3', lang('Consent'), $children);
};
// A group of radio buttons under its legend: the legend names the field.
// Each option is array(value, label).
$radios = function ($id, $name, $legend, $options, $checked) use ($el, $attr) {
    $children = array($el('legend', 'form-label fs-6 mb-2', lang('Label'), array(), array('text' => $legend)));
    foreach (array_values($options) as $n => $o) {
        $rid = $id . '-' . ($n + 1);
        $rattrs = array($attr('type', 'radio'), $attr('name', $name), $attr('value', $o[0]));
        if ($o[0] === $checked) $rattrs[] = $attr('checked', '');
        $children[] = $el('div', 'form-check form-check-inline', lang('Option'), array(
            $el('input', 'form-check-input', lang('Field'), array(), array('id' => $rid, '_attrs' => $rattrs)),
            $el('label', 'form-check-label', lang('Label'), array(), array('text' => $o[1], '_attrs' => array($attr('for', $rid)))),
        ));
    }
    return $el('fieldset', 'mb-3', $legend, $children);
};
// The numbered heading of a part of a longer form.
$form_step = function ($n, $title) use ($el, $span, $heading) {
    return $el('div', 'd-flex align-items-center gap-2 mt-2 mb-3', lang('Step'), array(
        $span((string)$n, 'badge rounded-circle text-bg-primary', lang('Step Number')),
        $heading('h2', $title, 'h6 fw-bold mb-0'),
    ));
};

// The record's Edit button, shown to whoever may edit the submissions of
// its form; it opens the record in the control panel and comes back.
$edit_button = function ($text = '') use ($link) {
    return $link($text !== '' ? $text : lang('Edit'), '#', 'btn btn-sm btn-outline-secondary rounded-0', array(),
        array('_bindings' => array('href' => '__edit_url', 'eo_visible_if' => 'can_edit')));
};

// The comments of a record page (a request's messages, a conversation's
// replies, a post's comments), in the reading column: the page's Comments
// widget, which reads the record the page's view above it shows.
$comments_section = function ($name, $widget_key) use ($el, $container, $row, $col, $widget) {
    return $el('section', 'pb-5', $name, array(
        $container(array(
            $row(array(
                $col(array($widget($widget_key)), '', '8'),
            ), '', 'center'),
        )),
    ));
};

// A Messages node: the notices and errors of the form it sits in.
$messages = function ($class = '') {
    return array('type' => 'content', 'props' => array('contentType' => 'messages', 'cssClass' => $class), 'children' => array());
};

// A thread of the Comments widget: the comments in the widget's order, each
// a card with its writer, badges, date, the comment it answers where the
// thread stops going deeper, its words, its file and its links; the page
// links; the form (its notices first, the comment being answered, the
// stars while the page asks for them, the words, the name for whoever types
// one, the file, the staff's publish options, the box to follow the thread,
// the CAPTCHA); the box to follow or stop following; the staff's Stop and
// Allow buttons. A part under a flag shows only while it holds for the
// visitor. $w: the words (title, empty, heading, label, sign_in, submit,
// watch_box, follow, unfollow, close, reopen), reply (each comment can be
// answered and shared), watch (the boxes to follow the thread).
$comment_thread = function ($w) use ($el, $heading, $para, $link, $span, $attr, $bind, $show_when, $loop, $widget_root, $messages) {
    $actions = array();
    if ($w['reply']) {
        $actions[] = $link(lang('Reply'), '#', 'link-primary', array(), array('_bindings' => array('action' => 'comment_reply')));
        $actions[] = $link(lang('Share'), '#', 'link-secondary', array(), array('_bindings' => array('action' => 'comment_share')));
    }
    // Bound to the comment's edit screen, so the list adds no pencil of its own.
    $actions[] = $link(lang('Edit'), '#', 'link-secondary', array(), array('_bindings' => array('href' => '__comment_edit_url', 'eo_visible_if' => 'comment_can_edit')));

    $badges = array(
        $span(lang('Jane Cooper'), 'fw-semibold', lang('Name'), $bind('__comment_name')),
        $span('jane', 'small text-body-secondary', lang('User Name'), array('_bindings' => array('text' => '__comment_staff_name', 'eo_visible_if' => 'is_staff'))),
        $span(lang('Badge'), 'badge rounded-0 text-bg-secondary', lang('Badge'), array('_bindings' => array('text' => '__comment_badge', 'eo_visible_if' => 'comment_has_badge'))),
    );
    if ($w['reply']) {
        $badges[] = $span(lang('Featured'), 'badge rounded-0 text-bg-primary', lang('Featured'), $show_when('comment_is_featured'));
    }
    $card = $el('div', 'card border-0 bg-body-tertiary rounded-0 flex-grow-1', lang('Comment Card'), array(
        $el('div', 'card-body p-4', lang('Card Body'), array(
            $el('p', 'small fw-semibold text-danger-emphasis mb-2 pg-hide-if-empty', lang('Comment Status'), array(),
                array('text' => lang('Awaiting approval'), '_bindings' => array('text' => '__comment_status', 'eo_visible_if' => 'comment_is_pending'))),
            $el('div', 'text-warning mb-1', lang('Stars'), pg_sw_part_comment_stars(), $show_when('comment_has_rating')),
            $el('div', 'd-flex flex-wrap align-items-center gap-2', lang('Name Line'), $badges),
            $span(lang('2 days ago'), 'd-block small text-body-secondary pg-hide-if-empty', lang('Time'), $bind('__comment_date')),
            $el('blockquote', 'border-start border-3 ps-3 my-2 small text-body-secondary', lang('Quote'), array(
                $el('p', 'mb-1', lang('In reply to'), array(
                    $span(lang('In reply to'), 'me-1', lang('Label')),
                    $link(lang('Jane Cooper'), '#', 'fw-semibold link-body-emphasis', array(), array('_bindings' => array('href' => '__comment_parent_url', 'text' => '__comment_parent_name'))),
                )),
                $el('p', 'mb-0 pg-hide-if-empty', lang('Quoted text'), array(), array('text' => lang('Quoted text'), '_bindings' => array('text' => '__comment_quote'))),
            ), $show_when('comment_has_quote')),
            $el('div', 'mt-2', lang('Message'), array(), array('text' => lang('Comment text'), '_bindings' => array('text' => '__comment_message_html'))),
            $el('p', 'small mt-3 mb-0', lang('Attachment'), array(
                $link('file.pdf', '#', 'me-2', array(), array('target' => '_blank', 'rel' => 'noopener', '_bindings' => array('href' => '__comment_file_url', 'text' => '__comment_file_name'))),
                $span('12 KB', 'text-body-secondary', lang('Value'), $bind('__comment_file_size')),
            ), $show_when('comment_has_file')),
            $el('div', 'd-flex flex-wrap gap-3 mt-3 small', lang('Comment Actions'), $actions),
        )),
    ));

    $label = function ($for, $text, $class = 'form-label') use ($el, $attr) {
        return $el('label', $class, lang('Label'), array(), array('text' => $text, '_attrs' => array($attr('for', $for))));
    };
    $control = function ($tag, $class, $id, $value, $attrs = array()) use ($el) {
        return $el($tag, $class, lang('Field'), array(), array('id' => $id, '_attrs' => $attrs, '_bindings' => array('value' => $value)));
    };
    $fields = array($messages());
    if ($w['reply']) {
        // The comment being answered. Reply shows the box by its hidden
        // attribute, so it takes no display utility (d-flex would keep it
        // on screen, empty).
        $fields[] = $el('div', 'alert alert-secondary py-2 small', lang('Replying To'), array(
            $span(lang('Replying to'), 'me-1', lang('Text')),
            $el('strong', '', lang('Name'), array(), array('text' => lang('Jane Cooper'), '_bindings' => array('text' => '__reply_to_name'))),
            $link(lang('Cancel'), '#', 'ms-3 alert-link', array(), array('_bindings' => array('action' => 'comment_reply_cancel'))),
        ), $show_when('is_replying'));
    }
    $fields[] = $el('div', 'mb-3', lang('Rating'), array(
        $el('div', 'form-label', lang('Label'), array(), array('text' => lang('Rating'))),
        $el('div', '', lang('Stars'), array(), array('_bindings' => array('section' => 'comment_rating_input'))),
    ), $show_when('rating_on'));
    $fields[] = $el('div', 'mb-3', lang('Message'), array(
        $label('comment_message', $w['label']),
        $control('textarea', 'form-control', 'comment_message', 'comment_message', array($attr('rows', '5'))),
    ));
    $fields[] = $el('div', 'mb-3', lang('Your Name'), array(
        $label('comment_name', lang('Your Name')),
        $control('input', 'form-control', 'comment_name', 'comment_name', array($attr('type', 'text'))),
    ), $show_when('name_is_typed'));
    $fields[] = $el('div', 'mb-3', lang('Your Name'), array(
        $label('comment_name_type', lang('Your Name')),
        $control('select', 'form-select', 'comment_name_type', 'comment_name_type'),
    ), $show_when('name_is_chosen'));
    $fields[] = $el('p', 'mb-3 small', lang('Posting as'), array(
        $span(lang('Posting as'), 'me-1', lang('Label')),
        $el('strong', '', lang('Name'), array(), array('text' => lang('Jane Cooper'), '_bindings' => array('text' => '__comment_author_name'))),
    ), $show_when('name_is_fixed'));
    $fields[] = $el('div', 'mb-3', lang('Attach a File'), array(
        $label('comment_file', lang('Attach a File')),
        $control('input', 'form-control', 'comment_file', 'comment_file', array($attr('type', 'file'))),
    ), $show_when('files_on'));
    $fields[] = $el('div', 'mb-3 small', lang('Publish'), array(), array('_bindings' => array('section' => 'comment_publish')));
    if ($w['watch']) {
        $fields[] = $el('div', 'form-check mb-3', lang('Watch'), array(
            $control('input', 'form-check-input', 'comment_watcher', 'comment_watcher', array($attr('type', 'checkbox'))),
            $label('comment_watcher', $w['watch_box'], 'form-check-label'),
        ), $show_when('watching_on'));
    }
    $fields[] = $el('div', 'mb-3', lang('CAPTCHA'), array(), array('_bindings' => array('section' => 'comment_captcha')));
    $fields[] = $el('div', '', lang('Send Button'), array(
        $el('button', 'btn btn-primary rounded-0 px-4', lang('Send Button'), array(), array('text' => $w['submit'], '_attrs' => array($attr('type', 'submit')), '_bindings' => array('action' => 'comment_submit'))),
    ));

    $small_button = function ($text, $action) use ($el, $attr) {
        return $el('button', 'btn btn-sm btn-outline-secondary rounded-0', lang('Send Button'), array(), array('text' => $text, '_attrs' => array($attr('type', 'submit')), '_bindings' => array('action' => $action)));
    };

    $children = array(
        $heading('h2', $w['title'], 'h4 fw-bold mb-3', array('_bindings' => array('text' => '__comments_title', 'eo_visible_if' => 'has_comments'))),
        $el('div', 'pg-hide-if-empty', lang('Comment List'), array(
            $loop(array($el('div', 'd-flex mb-3', lang('Comment'), array(
                $el('div', 'flex-shrink-0 border-start border-2 ms-2 me-3', lang('Reply Indent'), array(), $show_when('comment_is_reply')),
                $card,
            )))),
            $el('div', 'mt-3', lang('Pagination'), array(), array('_bindings' => array('section' => 'comment_pagination'))),
        )),
        $para($w['empty'], 'text-body-secondary', $show_when('no_comments')),
        $el('div', 'mt-4', lang('Add a comment'), array(
            $heading('h3', $w['heading'], 'h5 fw-bold mb-2', $show_when('comments_open')),
            $el('p', 'small text-body-secondary mb-3 pg-hide-if-empty', lang('Text'), array(), array('_bindings' => array('text' => '__comments_intro', 'eo_visible_if' => 'comments_open'))),
            $el('div', 'alert alert-secondary', lang('Closed'), array(), array('_bindings' => array('text' => '__comments_closed_message', 'eo_visible_if' => 'comments_closed'))),
            $el('div', 'alert alert-secondary d-flex flex-wrap align-items-center gap-2', lang('Sign in'), array(
                $span($w['sign_in'], '', lang('Text')),
                $link(lang('Sign in'), '#', 'alert-link', array(), array('rel' => 'nofollow', '_bindings' => array('href' => '__login_url'))),
            ), $show_when('must_sign_in')),
            $el('form', 'card card-body border-0 bg-body-tertiary rounded-0 p-4', lang('Comment Form'), $fields, array('_bindings' => array('action' => 'comment_form', 'eo_visible_if' => 'can_comment'))),
        )),
    );
    if ($w['watch']) {
        // Following the thread: how many follow it, the visitor's own
        // button, and the staff's list of who follows.
        $children[] = $el('div', 'small border-top pt-3 mt-4 d-flex flex-column gap-2 pg-hide-if-empty', lang('Watch'), array(
            $el('p', 'mb-0 pg-hide-if-empty', lang('Text'), array(), $bind('__watcher_count_text')),
            $el('form', 'd-flex flex-wrap align-items-center gap-2', lang('Watch Form'), array(
                $messages('w-100'),
                $small_button($w['follow'], 'comment_watch'),
                $small_button($w['unfollow'], 'comment_unwatch'),
            ), array('_bindings' => array('action' => 'comment_watch_form'))),
            $el('div', '', lang('Watchers'), array(), array('_bindings' => array('section' => 'comment_watchers'))),
        ), $show_when('watching_on'));
    }
    $children[] = $el('div', 'd-flex justify-content-end mt-3', lang('Staff Controls'), array(
        $el('form', 'd-flex gap-2', lang('Open or Close'), array(
            $small_button($w['close'], 'comment_close'),
            $small_button($w['reopen'], 'comment_reopen'),
        ), array('_bindings' => array('action' => 'comment_allow_new_form'))),
    ), $show_when('is_staff'));

    return $widget_root($children);
};

// ── Staff, newsletter and blog parts ────────────────────────────────────

// The staff pages' own menu, under the header: one shared component.
$staff_bar = function () use ($el, $link, $span) {
    $item = function ($key, $text) use ($link) {
        return $link($text, '{{page:' . $key . '}}', 'nav-link px-0');
    };
    return $el('div', 'border-bottom small', lang('Staff Bar'), array(
        $el('div', 'container d-flex flex-wrap align-items-center gap-4 py-2', lang('Staff Bar Container'), array(
            $span(lang('Staff'), 'badge rounded-0 text-bg-primary', lang('Staff Badge')),
            $el('nav', 'nav nav-underline gap-4', lang('Staff Links'), array(
                $item('staff', lang('Staff Area')),
                $item('staff_enquiries', lang('Enquiries')),
                $item('staff_viewings', lang('Private Viewings')),
                $item('artist_new', lang('New Artist')),
                $item('work_new', lang('New Artwork')),
                $item('exhibition_new', lang('New Exhibition')),
                $item('blog_new', lang('New Article')),
                $item('inbox', lang('Inbox')),
            ), array('smartActive' => true)),
        )),
    ));
};

// A page of the staff area: the staff bar under the header, no band.
$staff_page = function ($main) use ($root, $shared, $el) {
    return $root(array(
        $shared('account_bar'),
        $shared('header'),
        $shared('staff_bar'),
        $el('main', '', lang('Main Content'), $main),
        $shared('footer'),
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
$newsletter_consent = function ($id) use ($agree) {
    return $agree($id, 'consent', lang('I would like to receive invitations to openings, news of the gallery\'s artists and Journal articles by e-mail. I can unsubscribe at any time.'));
};

// A file field; what is sent is kept in the template folder named. A
// question may carry a photo of the wall a work is for or a floor plan, so
// the default accepts PDFs, office files, text and pictures; one that takes
// only pictures is a collection's image kind (an artist's portrait).
$file = function ($id, $name, $folder, $accept = '.pdf,.doc,.docx,.odt,.txt,.jpg,.jpeg,.png') use ($el, $attr) {
    return $el('input', 'form-control', '', array(), array('id' => $id, '_attrs' => array(
        $attr('type', 'file'), $attr('name', $name), $attr('accept', $accept),
    ), '_cf' => array('upload_folder_id' => '{{folder:' . $folder . '}}')
            + ($accept === 'image/*' ? array('kind' => 'image') : array())));
};

// A picture that leaves with its row when the record has none (the
// pg-hide-if-empty row of the form widgets).
$optional_image = function ($token, $alt_token, $class, $frame, $sample, $alt) use ($el, $image) {
    return $el('div', 'pg-hide-if-empty', lang('Picture'), array(
        $el('span', 'pg-field-value d-block ' . $frame, lang('Picture Frame'), array(
            $image($sample, $alt, $class, array('_bindings' => array('src' => $token, 'alt' => $alt_token))),
        )),
    ));
};

// A blog post card of the lists (the blog page and the home page).
$post_card = function () use ($el, $para, $span, $link, $col, $optional_image, $bind, $photo) {
    return $col(array(
        $el('article', 'card h-100 border-0 bg-transparent rounded-0 pgl-lift', lang('Post Card'), array(
            $optional_image('cover_image', 'title', 'w-100 h-100 object-fit-cover', 'ratio ratio-16x9 bg-body-tertiary mb-3 pgl-frame',
                $photo('post', 800, 450), lang('Cover Image')),
            $el('div', 'd-flex flex-column flex-grow-1', lang('Card Body'), array(
                $para(lang('Date'), 'small text-uppercase fw-semibold text-body-secondary mb-2', $bind('submitted_date_and_time')),
                $el('h3', 'pgl-display h4 position-static', lang('Post Title'), array(
                    $link(lang('Five questions to ask before buying your first work'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
                )),
                $para(lang('A short summary of the post. It is what makes a visitor open it, so two sentences are enough.'),
                    'small text-body-secondary', $bind('summary')),
                $span(lang('Read More'), 'mt-auto small fw-semibold link-body-emphasis', lang('Read More')),
            )),
        )),
    ), '6', '4');
};

// Summarize the post with AI: with the visitor's own assistant (each link
// opens it with a question about the post's address) or with the browser's
// built-in model, whose button the browser shows only where it can
// summarize and whose points come into the box below. Shown for a post
// anybody may open.
$ai_summary = function () use ($el, $link, $icon, $span, $attr, $bind, $show_when) {
    $assistant = function ($text, $token) use ($link, $attr) {
        return $link($text, '#', 'btn btn-sm btn-outline-primary rounded-0', array($attr('rel', 'noopener nofollow')),
            array('target' => '_blank', '_bindings' => array('href' => $token)));
    };
    return $el('aside', 'p-3 rounded-0 bg-body-tertiary mb-4', lang('AI Summary'), array(
        $el('div', 'd-flex flex-wrap align-items-center gap-2', lang('AI Buttons'), array(
            $icon('bi-stars', 'text-primary'),
            $span(lang('Summarize this post with AI'), 'small fw-semibold me-1', lang('Label')),
            $assistant('ChatGPT', '__ai_chatgpt_url'),
            $assistant('Claude', '__ai_claude_url'),
            $assistant('Perplexity', '__ai_perplexity_url'),
            $assistant('Google AI', '__ai_google_url'),
            $el('button', 'btn btn-sm btn-primary rounded-0', lang('Summarize Here Button'), array(), array(
                'text' => lang('Summarize here'), '_bindings' => array('action' => 'ai_summarize'))),
        )),
        $el('div', 'small mt-3', lang('Summary Box'), array(), array_merge(
            array('text' => lang('The main points of the post, written by the visitor\'s browser.')), $bind('__ai_summary'))),
    ), $show_when('has_ai_links'));
};

