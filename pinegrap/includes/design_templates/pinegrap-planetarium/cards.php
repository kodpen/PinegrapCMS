<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Planetarium", part 2 of its files: the parts every page
 * repeats (account bar, notice, header, footer, staff bar), the
 * planetarium's cards (opening hours, audience, dome show and its facts,
 * telescope night and its facts, telescope, ticket, membership, school
 * visit and astronomer cards), the form fields, the comment thread, the
 * newsletter line and the blog parts.
 * Included after parts.php in the same scope; the page files after it
 * build their trees with these variables.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// A date field in the format given (date()'s letters), in place of the
// site's own date format.
$bind_date = function ($token, $format) {
    return array('_bindings' => array('text' => $token), '_bindFormats' => array('text' => $format));
};

// ── The parts every page repeats ────────────────────────────────────────

// The line in the account bar: the telescope nights and the way to
// tonight's sky. A shared component.
$notice = function () use ($el, $icon, $span, $link, $planetarium) {
    return $el('p', 'd-flex flex-wrap align-items-center gap-2 mb-0', lang('Notice'), array(
        $icon('bi-stars', 'text-primary'),
        $span($planetarium['nights'], '', lang('Text')),
        $link(lang('Tonight\'s Sky'), '{{page:tonight}}', 'link-primary fw-semibold'),
    ));
};

// The bar above the header, on the night ground like the header: the
// notice on the left, the language switcher, the login region and the cart
// (with the shop) on the right. Every page places it as one shared
// component, so a change to it is made once for the whole site.
$account_bar_tree = function () use ($el, $widget, $shared, $lang_switcher, $attr) {
    return $el('div', 'border-bottom small bg-body-tertiary text-body', lang('Account Bar'), array(
        $el('div', 'container d-flex flex-wrap align-items-center justify-content-between gap-2 py-1', lang('Account Area'), array(
            $el('div', '', lang('Announcement Area'), array($shared('notice'))),
            $el('div', 'd-flex align-items-center gap-2 ms-auto', lang('Account Links'), array(
                $lang_switcher(), $widget('login_region'), $widget('cart_link'),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The header, on the night ground: the planetarium's name, the menu and
// the way to request a school visit. The links to the current page are
// marked active on the server (smart active state), so one header serves
// every page. The shop's pages are linked from the tickets and memberships
// pages only, so the header stands on a site without the shop.
$header = function () use ($el, $link, $icon, $span, $attr) {
    $items = array();
    foreach (array(
        array('tonight', lang('Tonight')),
        array('shows', lang('Dome Shows')),
        array('nights', lang('Telescope Nights')),
        array('visit', lang('Visit')),
        array('tickets', lang('Tickets')),
        array('memberships', lang('Memberships')),
        array('observatory', lang('The Observatory')),
        array('blog', lang('News')),
    ) as $n) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($n[1], '{{page:' . $n[0] . '}}', 'nav-link text-nowrap')));
    }
    return $el('header', 'bg-body text-body border-bottom', lang('Site Header'), array(
        $el('nav', 'navbar navbar-expand-xl py-3', lang('Navbar'), array(
            $el('div', 'container', lang('Navbar Container'), array(
                $el('a', 'navbar-brand d-flex align-items-center gap-2', lang('Brand'), array(
                    $icon('bi-stars', 'fs-4 text-primary'),
                    $span('{{site_name}}', 'ppm-brand fs-4', lang('Name')),
                ), array('href' => '{{page:home}}')),
                $el('button', 'navbar-toggler', lang('Toggler'), array(
                    $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#plm-nav'),
                    $attr('aria-controls', 'plm-nav'),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-label', lang('Toggle navigation')),
                ))),
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $el('ul', 'navbar-nav ms-xl-auto me-xl-3 column-gap-xl-1 py-3 py-xl-0', lang('Nav Menu'), $items, array('smartActive' => true)),
                    $el('div', 'd-flex flex-column flex-xl-row align-items-xl-center gap-2 pb-2 pb-xl-0', lang('Header Actions'), array(
                        $el('a', 'btn btn-primary d-inline-flex align-items-center justify-content-center gap-2 px-4 text-nowrap', lang('Call to Action'), array(
                            $icon('bi-backpack'),
                            $span(lang('School Visits'), '', lang('Link Text')),
                        ), array('href' => '{{page:visit_request}}')),
                    )),
                ), array('id' => 'plm-nav')),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Main navigation'))))),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The footer, on the night ground: the planetarium in a sentence, how to
// reach it and its profiles, the dome shows by audience, the sky's pages,
// the ways to plan a visit and the planetarium's pages; at the bottom the
// year, the site's name and the dome in a line.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $planetarium, $audience_titles, $query, $attr) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $items);
    };
    $show_links = array($link(lang('All Dome Shows'), '{{page:shows}}', $plain));
    foreach ($audience_titles as $title) $show_links[] = $link($title, '{{page:shows}}?' . $query(array('audience' => $title)), $plain);
    $social = function ($name, $label) use ($el, $icon, $attr) {
        return $el('a', 'link-body-emphasis fs-5', $label, array($icon($name)), array('href' => '#', '_attrs' => array($attr('aria-label', $label))));
    };
    $contact_line = function ($icon_name, $child, $name) use ($el, $icon) {
        return $el('p', 'd-flex align-items-start gap-2 small mb-2', $name, array($icon($icon_name, 'text-primary'), $child));
    };

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-lg-4', lang('About the Planetarium'), array(
                    $el('a', 'd-inline-flex align-items-center gap-2 fs-4 mb-3 ' . $plain, lang('Brand'), array(
                        $icon('bi-stars', 'text-primary'),
                        $span('{{site_name}}', 'ppm-brand', lang('Name')),
                    ), array('href' => '{{page:home}}')),
                    $para($planetarium['tagline'], 'small text-body-secondary mb-3'),
                    $el('div', '', lang('Contact Details'), array(
                        $contact_line('bi-geo-alt', $link($planetarium['address'], '{{page:visit}}', $plain), lang('Address')),
                        $contact_line('bi-telephone', $link($planetarium['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $planetarium['phone']), $plain), lang('Phone')),
                        $contact_line('bi-envelope', $link($planetarium['email'], 'mailto:' . $planetarium['email'], $plain . ' text-break'), lang('Email')),
                        $contact_line('bi-clock', $span($planetarium['hours'], '', lang('Text')), lang('Opening Hours')),
                    )),
                    $el('div', 'd-flex gap-3 mt-3', lang('Social Links'), array(
                        $social('bi-instagram', 'Instagram'),
                        $social('bi-facebook', 'Facebook'),
                        $social('bi-youtube', 'YouTube'),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Show Links'), array(
                    $heading('h2', lang('Dome Shows'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($show_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Sky Links'), array(
                    $heading('h2', lang('The Sky'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Tonight\'s Sky'), '{{page:tonight}}', $plain),
                        $link(lang('Telescope Nights'), '{{page:nights}}', $plain),
                        $link(lang('The Observatory'), '{{page:observatory}}', $plain),
                        $link(lang('News'), '{{page:blog}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Visit Links'), array(
                    $heading('h2', lang('Plan Your Visit'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Visit'), '{{page:visit}}', $plain),
                        $link(lang('Tickets'), '{{page:tickets}}', $plain),
                        $link(lang('Memberships'), '{{page:memberships}}', $plain),
                        $link(lang('School Visits'), '{{page:visit_request}}', $plain),
                        $link(lang('FAQ'), '{{page:faq}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Footer Links'), array(
                    $heading('h2', lang('The Planetarium'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Newsletter'), '{{page:newsletter}}', $plain),
                        $link(lang('Contact Us'), '{{page:contact}}', $plain),
                        $link(lang('My Account'), '{{page:my_account}}', $plain),
                        $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                    )),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $para($planetarium['dome'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// ── The planetarium's cards ─────────────────────────────────────────────

// The opening hours, as a list of terms and values with tabular times.
$hours_list = function ($class = '') use ($el, $para, $opening_hours, $planetarium) {
    $rows = array();
    foreach ($opening_hours as $h) {
        $rows[] = $el('div', 'd-flex justify-content-between gap-3 py-2 border-bottom', lang('Opening Hours Row'), array(
            $el('dt', 'fw-semibold', lang('Days'), array(), array('text' => $h['days'])),
            $el('dd', 'ppm-time mb-0 text-end', lang('Hours'), array(), array('text' => $h['hours'])),
        ));
    }
    return $el('div', $class, lang('Opening Hours'), array(
        $el('dl', 'mb-2', lang('Opening Hours List'), $rows),
        $para($planetarium['nights'], 'small text-body-secondary mb-0'),
    ));
};

// An audience of the dome shows (the shows page and the visit page): the
// icon and the title, linking the show list filtered on it in the list's
// own words.
$audience_card = function ($key) use ($el, $link, $col, $icon_tile, $audiences, $query) {
    $a = $audiences[$key];
    return $col(array(
        $el('article', 'ppm-card card h-100 border-0 bg-body-tertiary p-3 d-flex flex-row align-items-center gap-3', lang('Audience Card'), array(
            $icon_tile($a['icon']),
            $el('h3', 'ppm-display h6 position-static mb-0', lang('Audience'), array(
                $link($a['title'], '{{page:shows}}?' . $query(array('audience' => $a['title'])), 'stretched-link link-body-emphasis text-decoration-none'),
            )),
        )),
    ), '6', '3', '', '12');
};

// The date of a record as a leaf: the day large, the month and year under
// it, both read from the record's date field.
$date_leaf = function ($date, $token) use ($el, $span) {
    return $el('div', 'position-absolute top-0 start-0 m-3 text-center rounded-3 bg-body text-body px-3 py-2 shadow-sm', lang('Date Leaf'), array(
        $span(date('d', strtotime($date)), 'ppm-display ppm-time d-block fs-3 lh-1', lang('Day'), array('_bindings' => array('text' => $token), '_bindFormats' => array('text' => 'd'))),
        $span(date('m.Y', strtotime($date)), 'ppm-time d-block small fw-semibold text-primary mt-1', lang('Month'), array('_bindings' => array('text' => $token), '_bindFormats' => array('text' => 'm.Y'))),
    ));
};

// A dome show's card of the lists (the home and the show list): the still
// with the date on a leaf, the audience, the title linking the show's
// page, the showtimes, the length, the minimum age, the language and the
// summary. Every value is bound to the Dome Shows form's field of the same
// name; the sample, dated from today, fills the canvas.
$show_card = function ($sample_key = 'solar_tour') use ($el, $para, $span, $link, $image, $col, $bind, $icon, $date_leaf, $shows, $show_audience, $show_on, $length_text, $age_text, $photo, $attr) {
    $s = $shows[$sample_key];
    $fact = function ($icon_name, $text, $token, $name) use ($el, $icon, $span, $bind) {
        return $el('span', 'd-inline-flex align-items-center gap-1', $name, array(
            $icon($icon_name, 'text-primary'),
            $span($text, '', $name, $bind($token)),
        ));
    };
    return $col(array(
        $el('article', 'ppm-card card h-100 border-0 shadow-sm overflow-hidden', lang('Show Card'), array(
            $el('div', 'position-relative', lang('Picture Area'), array(
                $el('span', 'pg-field-value d-block ratio ratio-16x9 bg-body-tertiary', lang('Picture Frame'), array(
                    $image($photo('show-' . $sample_key, 800, 450), $s['title'], 'w-100 h-100 object-fit-cover',
                        array('_bindings' => array('src' => 'photo_1', 'alt' => 'title'), '_attrs' => array($attr('loading', 'lazy')))),
                )),
                $date_leaf($show_on($s['day']), 'show_date'),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $span($show_audience($sample_key), 'ppm-eyebrow small text-uppercase fw-semibold text-primary mb-1', lang('Audience'), $bind('audience')),
                $el('h3', 'ppm-display h5 position-static mb-2', lang('Show Title'), array(
                    $link($s['title'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
                )),
                $el('p', 'd-flex align-items-start gap-1 small fw-semibold mb-2', lang('Showtimes'), array(
                    $icon('bi-clock', 'text-primary'),
                    $span($s['times'], 'ppm-time', lang('Showtimes'), $bind('showtimes')),
                )),
                $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary mb-2', lang('Show Facts'), array(
                    $fact('bi-hourglass-split', $length_text($s['length']), 'length', lang('Length')),
                    $fact('bi-person-check', $age_text($s['age']), 'age', lang('Minimum Age')),
                    $fact('bi-translate', $s['language'], 'language', lang('Language')),
                )),
                $para($s['summary'], 'small text-body-secondary mb-0', $bind('summary')),
            )),
        )),
    ), '6', '4');
};

// The facts of a dome show in one list (the show's page), each bound to
// the Dome Shows form's field of the same name.
$show_facts = function ($sample_key = 'solar_tour', $class = 'list-unstyled small mb-0') use ($el, $span, $bind, $bind_date, $shows, $show_audience, $show_on, $length_text, $age_text) {
    $s = $shows[$sample_key];
    $row = function ($label, $value_node) use ($el, $span) {
        return $el('li', 'd-flex justify-content-between gap-3 py-2 border-bottom', $label, array(
            $span($label, 'text-body-secondary', lang('Label')),
            $value_node,
        ));
    };
    $value = function ($text, $token, $name, $class = 'fw-semibold text-end') use ($span, $bind) {
        return $span($text, $class, $name, $bind($token));
    };
    return $el('ul', $class, lang('Show Facts'), array(
        $row(lang('Date'), $span(date('d.m.Y', strtotime($show_on($s['day']))), 'ppm-time fw-semibold text-end', lang('Date'), $bind_date('show_date', 'd.m.Y'))),
        $row(lang('Showtimes'), $value($s['times'], 'showtimes', lang('Showtimes'), 'ppm-time fw-semibold text-end')),
        $row(lang('Audience'), $value($show_audience($sample_key), 'audience', lang('Audience'))),
        $row(lang('Length'), $value($length_text($s['length']), 'length', lang('Length'))),
        $row(lang('Minimum Age'), $value($age_text($s['age']), 'age', lang('Minimum Age'))),
        $row(lang('Language'), $value($s['language'], 'language', lang('Language'))),
    ));
};

// The state of a telescope night as a pill, bound to its Status field: the
// staff's word (On, Called Off, Moved), never a weather feed.
$night_status_badge = function ($text) use ($el, $icon, $span, $bind) {
    return $el('span', 'badge rounded-pill text-bg-primary d-inline-flex align-items-center gap-1', lang('Night Status'), array(
        $icon('bi-moon-stars'),
        $span($text, '', lang('Status'), $bind('status')),
    ));
};

// A telescope night's card of the lists (the home and the night list): the
// photo with the date on a leaf, the place, the state, the title linking
// the night's page, the start, the target and the places left, with the
// summary. Bound to the Telescope Nights form's fields; the sample's date
// is counted from today.
$night_card = function ($sample_key = 'saturn') use ($el, $para, $span, $link, $image, $col, $bind, $icon, $date_leaf, $nights, $night_place, $night_status, $night_status_badge, $show_on, $photo, $attr) {
    $n = $nights[$sample_key];
    return $col(array(
        $el('article', 'ppm-card card h-100 border-0 shadow-sm overflow-hidden', lang('Night Card'), array(
            $el('div', 'position-relative', lang('Picture Area'), array(
                $el('span', 'pg-field-value d-block ratio ratio-16x9 bg-body-tertiary', lang('Picture Frame'), array(
                    $image($photo('night-' . $sample_key, 800, 450), $n['title'], 'w-100 h-100 object-fit-cover',
                        array('_bindings' => array('src' => 'photo_1', 'alt' => 'title'), '_attrs' => array($attr('loading', 'lazy')))),
                )),
                $date_leaf($show_on($n['day']), 'night_date'),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-2', lang('Card Top'), array(
                    $span($night_place($sample_key), 'ppm-eyebrow small text-uppercase fw-semibold text-primary', lang('Place'), $bind('place')),
                    $night_status_badge($night_status($sample_key)),
                )),
                $el('h3', 'ppm-display h5 position-static mb-2', lang('Night Title'), array(
                    $link($n['title'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
                )),
                $el('p', 'd-flex align-items-start gap-1 small fw-semibold mb-1', lang('Start'), array(
                    $icon('bi-clock', 'text-primary'),
                    $span($n['time'], 'ppm-time', lang('Time'), $bind('start_time')),
                )),
                $el('p', 'd-flex align-items-start gap-1 small mb-1', lang('Target'), array(
                    $icon('bi-binoculars', 'text-primary'),
                    $span($n['target'], '', lang('Target'), $bind('target')),
                )),
                $el('p', 'd-flex align-items-start gap-1 small mb-2', lang('Places'), array(
                    $icon('bi-people', 'text-primary'),
                    $span($n['places'], '', lang('Places'), $bind('places')),
                )),
                $para($n['summary'], 'small text-body-secondary mb-0', $bind('summary')),
            )),
        )),
    ), '6', '4');
};

// The facts of a telescope night in one list (the night's page), each
// bound to the Telescope Nights form's field of the same name.
$night_facts = function ($sample_key = 'saturn', $class = 'list-unstyled small mb-0') use ($el, $span, $bind, $bind_date, $nights, $night_place, $night_status, $show_on) {
    $n = $nights[$sample_key];
    $row = function ($label, $text, $token, $class = 'fw-semibold text-end') use ($el, $span, $bind) {
        return $el('li', 'd-flex justify-content-between gap-3 py-2 border-bottom', $label, array(
            $span($label, 'text-body-secondary', lang('Label')),
            $span($text, $class, $label, $bind($token)),
        ));
    };
    return $el('ul', $class, lang('Night Facts'), array(
        $el('li', 'd-flex justify-content-between gap-3 py-2 border-bottom', lang('Date'), array(
            $span(lang('Date'), 'text-body-secondary', lang('Label')),
            $span(date('d.m.Y', strtotime($show_on($n['day']))), 'ppm-time fw-semibold text-end', lang('Date'), $bind_date('night_date', 'd.m.Y')),
        )),
        $row(lang('Start'), $n['time'], 'start_time', 'ppm-time fw-semibold text-end'),
        $row(lang('Target'), $n['target'], 'target'),
        $row(lang('Place'), $night_place($sample_key), 'place'),
        $row(lang('Places'), $n['places'], 'places'),
        $row(lang('Status'), $night_status($sample_key), 'status'),
    ));
};

// A telescope of the observatory (the observatory page): the icon, the
// name, the kind and a sentence.
$telescope_card = function ($n) use ($el, $heading, $para, $col, $icon_tile, $telescopes) {
    $t = $telescopes[$n];
    return $col(array(
        $el('article', 'card h-100 border-0 bg-body-tertiary p-4', lang('Telescope Card'), array(
            $icon_tile($t['icon'], 'mb-3 align-self-start'),
            $heading('h3', $t['title'], 'ppm-display h5 mb-1'),
            $para($t['kind'], 'ppm-eyebrow small text-uppercase fw-semibold text-primary mb-2'),
            $para($t['summary'], 'small text-body-secondary mb-0'),
        )),
    ), '', '4');
};

// A ticket (the tickets page): who it is for and the price. It does not
// link the shop: the tickets page stands without it.
$ticket_card = function ($key) use ($el, $heading, $para, $span, $col, $icon, $tickets, $ticket_prices, $money) {
    $t = $tickets[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm p-4', lang('Ticket Card'), array(
            $icon($key === 'night' ? 'bi-moon-stars' : 'bi-ticket-perforated', 'fs-3 text-primary mb-2'),
            $heading('h3', $t['title'], 'ppm-display h5 mb-1'),
            $para($t['who'], 'small text-body-secondary mb-3'),
            $span($money($ticket_prices[$key]), 'ppm-price d-block h4 fw-bold text-primary mt-auto mb-0', lang('Price')),
        )),
    ), '6', '3');
};

// A membership (the memberships page): who it is for, the price a year and
// what it gives.
$membership_card = function ($key) use ($el, $heading, $para, $span, $col, $check_list, $memberships, $membership_price_text) {
    $m = $memberships[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm p-4', lang('Membership Card'), array(
            $heading('h3', $m['title'], 'ppm-display h5 mb-1'),
            $para($m['who'], 'small text-body-secondary mb-3'),
            $span($membership_price_text($key), 'ppm-price d-block h4 fw-bold text-primary mb-3', lang('Price')),
            $check_list($m['benefits'], 'list-unstyled small mb-0'),
        )),
    ), '', '4');
};

// A kind of school or group visit (the visit page and the visit request):
// the icon, the title, who it is for, how long it takes and a sentence,
// with the way to request it (?visit=).
$visit_card = function ($key) use ($el, $heading, $para, $link, $col, $icon_tile, $visits, $query) {
    $v = $visits[$key];
    return $col(array(
        $el('article', 'ppm-card card h-100 border-0 shadow-sm p-4', lang('Visit Card'), array(
            $icon_tile($v['icon'], 'mb-3 align-self-start'),
            $heading('h3', $v['title'], 'ppm-display h5 mb-1'),
            $para($v['who'], 'small fw-semibold mb-1'),
            $para($v['length'], 'small text-body-secondary mb-2'),
            $para($v['summary'], 'small text-body-secondary mb-3'),
            $link(lang('Request this visit'), '{{page:visit_request}}?' . $query(array('visit' => $v['title'])), 'btn btn-sm btn-outline-primary mt-auto align-self-start'),
        )),
    ), '6', '3');
};

// An astronomer (the observatory page): the portrait, the name, the role
// and a sentence.
$person_card = function ($n) use ($el, $heading, $para, $image, $col, $attr, $portrait, $people) {
    $t = $people[$n];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm text-center p-4', lang('Staff Member'), array(
            $image($portrait($t['name'], 240), $t['name'], 'rounded-circle mx-auto mb-3', array('_attrs' => array($attr('width', '120'), $attr('height', '120'), $attr('loading', 'lazy')))),
            $heading('h3', $t['name'], 'ppm-display h5 mb-1'),
            $para($t['role'], 'small fw-semibold text-primary mb-2'),
            $para($t['bio'], 'small text-body-secondary mb-0'),
        )),
    ), '6', '3');
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
$submit = function ($text, $class = 'btn btn-primary px-4') use ($el, $attr) {
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
        $span((string)$n, 'ppm-time badge rounded-circle text-bg-primary', lang('Step Number')),
        $heading('h2', $title, 'h6 fw-bold mb-0'),
    ));
};

// The record's Edit button, shown to whoever may edit the submissions of
// its form; it opens the record in the control panel and comes back.
$edit_button = function ($text = '') use ($link) {
    return $link($text !== '' ? $text : lang('Edit'), '#', 'btn btn-sm btn-outline-secondary rounded-pill', array(),
        array('_bindings' => array('href' => '__edit_url', 'eo_visible_if' => 'can_edit')));
};

// The comments of a record page (a visit request's messages, a
// conversation's replies, a post's comments), in the reading column: the
// page's Comments widget, which reads the record the page's view above it
// shows.
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
        $span(lang('Badge'), 'badge rounded-pill text-bg-secondary', lang('Badge'), array('_bindings' => array('text' => '__comment_badge', 'eo_visible_if' => 'comment_has_badge'))),
    );
    if ($w['reply']) {
        $badges[] = $span(lang('Featured'), 'badge rounded-pill text-bg-primary', lang('Featured'), $show_when('comment_is_featured'));
    }
    $card = $el('div', 'card border-0 bg-body-tertiary rounded-4 flex-grow-1', lang('Comment Card'), array(
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
        $el('button', 'btn btn-primary rounded-pill px-4', lang('Send Button'), array(), array('text' => $w['submit'], '_attrs' => array($attr('type', 'submit')), '_bindings' => array('action' => 'comment_submit'))),
    ));

    $small_button = function ($text, $action) use ($el, $attr) {
        return $el('button', 'btn btn-sm btn-outline-secondary rounded-pill', lang('Send Button'), array(), array('text' => $text, '_attrs' => array($attr('type', 'submit')), '_bindings' => array('action' => $action)));
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
            $el('form', 'card card-body border-0 bg-body-tertiary rounded-4 p-4', lang('Comment Form'), $fields, array('_bindings' => array('action' => 'comment_form', 'eo_visible_if' => 'can_comment'))),
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
            $span(lang('Staff'), 'badge rounded-pill text-bg-primary', lang('Staff Badge')),
            $el('nav', 'nav nav-underline gap-4', lang('Staff Links'), array(
                $item('staff', lang('Staff Area')),
                $item('staff_visits', lang('Visit Requests')),
                $item('show_new', lang('New Dome Show')),
                $item('night_new', lang('New Telescope Night')),
                $item('blog_new', lang('New Article')),
                $item('inbox', lang('Inbox')),
                $item('staff_directory', lang('Staff Directory')),
            ), array('smartActive' => true)),
        )),
    ));
};

// A page of the staff area: the staff bar under the header.
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
    return $agree($id, 'consent', lang('I would like to receive the planetarium\'s sky news, new dome shows and telescope night invitations by e-mail. I can unsubscribe at any time.'));
};

// A file field; what is sent is kept in the template folder named. A visit
// request may carry a list of pupils or a lesson plan, so the default
// accepts PDFs, office files, text and pictures; one that takes only
// pictures is a collection's image kind (a show's or a night's photo).
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

// A news post card of the lists (the news page and the home page).
$post_card = function () use ($el, $para, $span, $link, $col, $optional_image, $bind, $photo) {
    return $col(array(
        $el('article', 'ppm-card card h-100 border-0 shadow-sm overflow-hidden', lang('Post Card'), array(
            $optional_image('cover_image', 'title', 'card-img-top object-fit-cover', 'ratio ratio-16x9 bg-body-tertiary',
                $photo('post', 800, 450), lang('Cover Image')),
            $el('div', 'card-body d-flex flex-column', lang('Card Body'), array(
                $para(lang('Date'), 'small text-uppercase fw-semibold text-primary mb-2', $bind('submitted_date_and_time')),
                $el('h3', 'ppm-display h5 card-title fw-semibold position-static', lang('Post Title'), array(
                    $link(lang('What to look for in the sky this month'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
                )),
                $para(lang('A short summary of the post. It is what makes a visitor open it, so two sentences are enough.'),
                    'card-text small text-body-secondary', $bind('summary')),
                $span(lang('Read More'), 'mt-auto small fw-semibold text-primary', lang('Read More')),
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
        return $link($text, '#', 'btn btn-sm btn-outline-primary rounded-pill', array($attr('rel', 'noopener nofollow')),
            array('target' => '_blank', '_bindings' => array('href' => $token)));
    };
    return $el('aside', 'p-3 rounded-4 bg-body-tertiary mb-4', lang('AI Summary'), array(
        $el('div', 'd-flex flex-wrap align-items-center gap-2', lang('AI Buttons'), array(
            $icon('bi-stars', 'text-primary'),
            $span(lang('Summarize this post with AI'), 'small fw-semibold me-1', lang('Label')),
            $assistant('ChatGPT', '__ai_chatgpt_url'),
            $assistant('Claude', '__ai_claude_url'),
            $assistant('Perplexity', '__ai_perplexity_url'),
            $assistant('Google AI', '__ai_google_url'),
            $el('button', 'btn btn-sm btn-primary rounded-pill', lang('Summarize Here Button'), array(), array(
                'text' => lang('Summarize here'), '_bindings' => array('action' => 'ai_summarize'))),
        )),
        $el('div', 'small mt-3', lang('Summary Box'), array(), array_merge(
            array('text' => lang('The main points of the post, written by the visitor\'s browser.')), $bind('__ai_summary'))),
    ), $show_when('has_ai_links'));
};
