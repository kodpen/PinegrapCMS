<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Home Services", its public pages: home, about, the
 * service areas, the price guide, the questions page and the customer
 * reviews. Included after parts.php in the same scope (its builders and
 * the company's data); returns array('pages' => ..., 'widgets' => ...).
 *
 * Every figure comes from parts.php ($company, $categories, $services,
 * $service_prices, $call_out, $areas, $how_it_works, $why_us, $figures,
 * $testimonials), so a change made there reaches every page here. No page
 * here links a shop page: the template stands on a site without the shop.
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

// A picture in a rounded frame of the given ratio, loaded lazily unless it
// is the first thing a page shows.
$framed = function ($src, $alt, $ratio = '4x3', $lazy = true) use ($el, $image, $attr) {
    $extra = $lazy ? array('_attrs' => array($attr('loading', 'lazy'))) : array();
    return $el('div', 'ratio ratio-' . $ratio . ' rounded-3 overflow-hidden bg-body-tertiary shadow-sm', lang('Picture Frame'), array(
        $image($src, $alt, 'object-fit-cover', $extra),
    ));
};

// A band of text beside a picture; $flip puts the picture first from lg up.
// The band is a search section under its anchor $id, with the topics a
// visitor may search for that it does not say itself.
$story_band = function ($id, $eyebrow, $title, $texts, $picture, $alt, $flip = false, $more = array(), $topics = '', $class = 'py-5')
    use ($el, $heading, $para, $container, $row, $col, $framed, $searchable) {
    $words = array(
        $para($eyebrow, 'phs-eyebrow text-uppercase fw-semibold text-primary mb-2'),
        $heading('h2', $title, 'phs-display h2 mb-3'),
    );
    $last = count($texts) - 1;
    foreach (array_values($texts) as $n => $t) {
        $words[] = $para($t, 'text-body-secondary ' . ($n === $last && empty($more) ? 'mb-0' : 'mb-3'));
    }
    return $el('section', $class, $title, array(
        $container(array(
            $row(array(
                $col(array_merge($words, $more), '', '6', '', '12', $flip ? 'order-lg-last' : ''),
                $col(array($framed($picture, $alt)), '', '6'),
            ), '4', '', 'g-lg-5 align-items-center'),
        )),
    ), $searchable($id, $topics));
};

// A tile with an icon, a title and a line: why customers choose the
// company and what it promises.
$feature_tile = function ($icon_name, $title, $text, $lg = '3') use ($el, $heading, $para, $col, $icon_tile) {
    return $col(array(
        $el('div', 'h-100 p-4 rounded-3 bg-body border shadow-sm', $title, array(
            $icon_tile($icon_name, 'mb-3'),
            $heading('h3', $title, 'phs-display h5 mb-2'),
            $para($text, 'small text-body-secondary mb-0'),
        )),
    ), '6', $lg);
};

// The company in four figures, in one row.
$figure_row = function () use ($el, $para, $row, $col, $figures) {
    $cols = array();
    foreach ($figures as $f) {
        $cols[] = $col(array(
            $el('div', 'text-center', $f['label'], array(
                $para($f['value'], 'phs-display display-5 text-primary mb-1'),
                $para($f['label'], 'small text-uppercase fw-semibold text-body-secondary mb-0'),
            )),
        ), '3', '', '', '6');
    }
    return $row($cols, '4');
};

// A band that sends a question to the contact page.
$ask_band = function ($title, $text) use ($el, $heading, $para, $link, $container) {
    return $el('section', 'py-5', lang('Write to Us'), array(
        $container(array(
            $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3', lang('Text'), array(
                $el('div', '', lang('Text'), array(
                    $heading('h2', $title, 'phs-display h4 mb-1'),
                    $para($text, 'text-body-secondary mb-0'),
                )),
                $link(lang('Contact Us'), '{{page:contact}}', 'btn btn-primary px-4 text-nowrap'),
            )),
        )),
    ));
};

// How a job goes, as numbered steps in a list.
$how_steps = function () use ($el, $step, $how_it_works) {
    $items = array();
    foreach ($how_it_works as $n => $s) $items[] = $step($n + 1, $s['title'], $s['text']);
    return $el('ol', 'list-unstyled mb-4', lang('Steps'), $items);
};

// The cards of the shared data, in their order.
$why_tiles = array();
foreach ($why_us as $w) $why_tiles[] = $feature_tile($w['icon'], $w['title'], $w['text']);
$category_cols = array();
foreach (array_keys($categories) as $key) $category_cols[] = $category_card($key);
$area_cols = array();
foreach (array_keys($areas) as $key) $area_cols[] = $area_card($key);
$testimonial_cols = array();
foreach (array_keys($testimonials) as $n) $testimonial_cols[] = $testimonial_card($n);
$call_out_text = $money($call_out);
$core_names = array();
$outer_names = array();
foreach ($areas as $a) {
    if ($a['core']) $core_names[] = $a['name'];
    else $outer_names[] = $a['name'];
}
$emergency_link = 'tel:' . preg_replace('/[^0-9+]/', '', $company['emergency']);

// ── The blocks ──────────────────────────────────────────────────────────
// The trees below draw the palette's blocks (Home Services: Hero with Job
// Sheet and Emergency Line, Booking: Arrival Windows, Locations: Zones)
// with the company's data: the same structure, classes and interactions,
// so the rules the template ships in User Styles style them as they style
// a dropped block. Headings and eyebrows wear the look's phs- classes.

// The home's way in, the Hero with Job Sheet block: the company in a line,
// the two ways on and the emergency number come in on load; beside them a
// visit's job sheet (a leak repair in the morning window at its agreed
// price, the founder coming) scales in after them, and what the technician
// brings is ticked off last.
$jobsheet_hero = function () use ($el, $heading, $para, $link, $image, $span, $icon, $container, $row, $col, $fx,
    $company, $services, $service_prices, $money, $time_windows, $team, $portrait, $visit_statuses, $emergency_link) {
    $fact = function ($icon_name, $label, $text) use ($el, $icon, $span) {
        return $el('div', 'pg-homeservices-fact', $label, array(
            $el('dt', '', lang('Label'), array($icon($icon_name, 'text-primary'), $span($label, '', lang('Text')))),
            $el('dd', '', lang('Value'), array($span($text, '', lang('Text')))),
        ));
    };
    $tick = function ($text) use ($el, $icon, $span) {
        return $el('li', 'd-flex align-items-start gap-2 mb-2', $text, array(
            $icon('bi-check-circle-fill', 'text-success flex-shrink-0'),
            $span($text, '', lang('Text')),
        ));
    };
    $words = $fx($el('div', '', lang('Hero Text'), array(
        $para(lang('One call for every job at home'), 'phs-eyebrow text-uppercase fw-semibold text-primary mb-3'),
        $heading('h1', lang('Your home, looked after'), 'phs-display display-4 mb-3'),
        $para($company['tagline'] . '.', 'lead text-body-secondary mb-4'),
        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
            $link(lang('Book a Visit'), '{{page:book}}', 'btn btn-primary btn-lg px-4'),
            $link(lang('Get a Free Estimate'), '{{page:estimate}}', 'btn btn-outline-primary btn-lg px-4'),
        )),
        $el('div', 'd-flex flex-wrap align-items-center gap-2 small mt-4', lang('Emergency Line'), array(
            $icon('bi-telephone-fill', 'text-danger'),
            $span(lang('Burst pipe or no power? Our emergency line:'), 'text-body-secondary', lang('Text')),
            $link($company['emergency'], $emergency_link, 'fw-semibold link-primary text-nowrap'),
        )),
    )), array('effect' => 'slide-up', 'trigger' => 'load', 'duration' => 800));
    $m = $team[0];
    $paper = $el('div', 'pg-homeservices-paper p-4', lang('Paper'), array(
        $el('div', 'd-flex align-items-start justify-content-between gap-3 mb-3', lang('Job'), array(
            $el('div', '', lang('Title'), array(
                $el('div', 'd-flex gap-2 small text-body-secondary mb-1', lang('Job Number'), array(
                    $span(lang('Job sheet'), '', lang('Text')),
                    $span('#0427', 'pg-homeservices-no', lang('Text')),
                )),
                $heading('h2', $services['leak_repair']['name'], 'phs-display h4 mb-0'),
            )),
            $para($visit_statuses[1], 'pg-homeservices-stamp small flex-shrink-0 mt-1 mb-0'),
        )),
        $el('dl', 'pg-homeservices-facts mb-4', lang('Visit Details'), array(
            $fact('bi-clock', lang('Arrival time'), $time_windows[0]),
            $fact('bi-geo-alt', lang('Where'), lang('Kitchen, second-floor flat')),
            $fact('bi-tag', lang('Price'), sprintf(lang('%s, agreed before we start'), $money($service_prices['leak_repair']))),
            $fact('bi-shield-check', lang('Guarantee'), lang('Twelve months on the work')),
        )),
        $el('div', '', lang('Checklist'), array(
            $heading('h3', lang('We bring'), 'h6 fw-semibold mb-2'),
            $fx($el('ul', 'list-unstyled small mb-0', lang('List'), array(
                $tick(lang('The parts the job is likely to need')),
                $tick(lang('Shoe covers and dust sheets')),
                $tick(lang('A message when we are on the way')),
            )), array('effect' => 'slide-up', 'trigger' => 'load', 'duration' => 600, 'delay' => 900, 'stagger' => 150)),
        )),
        $el('div', 'd-flex align-items-center gap-3 pt-3 mt-3 border-top', lang('Technician'), array(
            $image($portrait($m['name'], 96), lang('The technician coming to the visit'), 'rounded-circle object-fit-cover flex-shrink-0'),
            $el('div', '', lang('Name'), array(
                $para($m['name'], 'fw-semibold mb-0'),
                $para(sprintf(lang('%1$s, %2$d years in the trade'), $m['role'], $m['years']), 'small text-body-secondary mb-0'),
            )),
        )),
    ));
    $sheet = $fx($el('div', 'pg-homeservices-sheet', lang('Job Sheet'), array($paper)),
        array('effect' => 'scale', 'trigger' => 'load', 'duration' => 900, 'delay' => 250));
    return $el('section', 'py-5 overflow-hidden', lang('Home Services (Hero with Job Sheet)'), array(
        $container(array(
            $row(array(
                $col(array($words), '', '6'),
                $col(array($sheet), '', '6'),
            ), '4', '', 'align-items-center g-lg-5 py-lg-4'),
        )),
    ));
};

// The arrival windows, the Booking: Arrival Windows block: the heading
// fades in, a card per window rises in turn (its hours on the working
// day's track, a drawing screen readers skip: they read the times), and
// the note with the booking button follows.
$windows_band = function () use ($el, $heading, $para, $link, $span, $icon, $time, $container, $fx, $attr, $searchable, $arrival_windows) {
    $cards = array();
    foreach ($arrival_windows as $w) {
        $top = array($el('span', 'pg-window-icon rounded-circle bg-primary-subtle text-primary-emphasis fs-4', lang('Icon'), array($icon($w['icon'])),
            array('_attrs' => array($attr('aria-hidden', 'true')))));
        if ($w['tag'] !== '') $top[] = $para($w['tag'], 'badge rounded-pill text-bg-primary mb-0');
        $cards[] = $el('li', 'col', $w['name'], array(
            $el('div', 'pg-window h-100 p-4 rounded-4 border bg-body', lang('Card'), array(
                $el('div', 'd-flex align-items-start justify-content-between gap-2 mb-3', lang('Card Top'), $top),
                $heading('h3', $w['name'], 'phs-display h5 mb-1'),
                $el('div', 'fs-3 fw-bold mb-3', lang('Time Range'), array(
                    $time($w['from'], $w['from'], '', lang('Start Time')),
                    $span('–', 'mx-1 text-body-secondary', lang('Text')),
                    $time($w['to'], $w['to'], '', lang('End Time')),
                )),
                $el('div', 'pg-window-day mb-4', lang('Drawing'), array($el('div', $w['span'], lang('Window'))),
                    array('_attrs' => array($attr('aria-hidden', 'true')))),
                $para($w['text'], 'text-body-secondary mb-3'),
                $para($w['days'], 'small fw-semibold mb-0'),
            )),
        ));
    }
    return $el('section', 'py-5 bg-body-tertiary', lang('Booking (Arrival Windows)'), array(
        $container(array(
            $fx($el('div', 'row g-4 align-items-end mb-5', lang('Section Header'), array(
                $el('div', 'col-lg-7', lang('Section Title'), array(
                    $para(lang('Arrival windows'), 'phs-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                    $heading('h2', lang('Choose when we come'), 'phs-display display-6 mb-0'),
                )),
                $el('div', 'col-lg-5', lang('Lead'), array(
                    $para(lang('Pick a window when you book. We arrive inside it, send a message when we are on the way and call if we are running late.'), 'text-body-secondary mb-0'),
                )),
            )), array('effect' => 'fade', 'trigger' => 'view', 'duration' => 800)),
            $fx($el('ul', 'list-unstyled row row-cols-1 row-cols-md-3 g-4 mb-4', lang('Windows'), $cards),
                array('effect' => 'slide-up', 'trigger' => 'view', 'duration' => 700, 'stagger' => 120)),
            $fx($el('div', 'd-flex flex-column flex-md-row align-items-md-center gap-3', lang('Note'), array(
                $icon('bi-chat-dots', 'fs-4 text-primary d-none d-md-inline'),
                $para(lang('Need us at an exact time? Ask when you book and we will tell you whether we can.'), 'flex-grow-1 text-body-secondary mb-0'),
                $link(lang('Book a Visit'), '{{page:book}}', 'btn btn-primary px-4 flex-shrink-0'),
            )), array('effect' => 'fade', 'trigger' => 'view', 'duration' => 800, 'delay' => 200)),
        )),
    ), $searchable('hs-home-windows', lang('arrival time, time window, morning, afternoon, evening, appointment')));
};

// The emergency line, the Emergency Line block: a dark band whatever the
// visitor's theme. The company's emergency number beside a mark that rings
// three times, its promises and the jobs it covers rise on view; beside
// them, the steps to take safely before a technician arrives.
$emergency_band = function () use ($el, $heading, $para, $link, $span, $icon, $container, $row, $col, $fx, $attr, $searchable, $company, $emergency_link) {
    $promise = function ($icon_name, $text) use ($el, $icon, $span) {
        return $el('li', 'd-inline-flex align-items-center gap-2', $text, array(
            $icon($icon_name, 'text-danger-emphasis'),
            $span($text, '', lang('Text')),
        ));
    };
    $chip = function ($text) use ($el, $span) {
        return $el('li', 'badge rounded-pill border bg-body-tertiary text-body fw-normal', $text, array($span($text, '', lang('Text'))));
    };
    $safe = function ($title, $text) use ($el, $para) {
        return $el('li', '', $title, array(
            $para($title, 'fw-semibold mb-1'),
            $para($text, 'small text-body-secondary mb-0'),
        ));
    };
    $words = $fx($el('div', '', lang('Text'), array(
        $para(lang('Emergencies'), 'phs-eyebrow text-uppercase fw-semibold text-danger-emphasis mb-2'),
        $heading('h2', lang('A burst pipe, a flood or no power?'), 'phs-display display-6 mb-3'),
        $para(lang('Call the emergency line. A technician is on the way within the hour, day and night, every day.'), 'lead text-body-secondary mb-4'),
        $el('div', 'd-flex align-items-center gap-3 mb-4', lang('Emergency Line'), array(
            $el('span', 'pg-homeservices-call text-bg-danger', lang('Icon'), array($icon('bi-telephone-fill')),
                array('_attrs' => array($attr('aria-hidden', 'true')))),
            $el('div', '', lang('Phone Number'), array(
                $para(lang('Emergency line, day and night'), 'small text-body-secondary mb-0'),
                $link($company['emergency'], $emergency_link, 'fs-3 fw-bold link-body-emphasis link-underline-opacity-0 text-nowrap'),
            )),
        )),
        $el('ul', 'list-unstyled d-flex flex-wrap gap-3 small mb-4', lang('Promises'), array(
            $promise('bi-stopwatch', lang('On the way within the hour')),
            $promise('bi-moon-stars', lang('Day and night, every day')),
            $promise('bi-tag', lang('Emergency rate agreed on the phone')),
        )),
        $heading('h3', lang('We come out for'), 'h6 fw-semibold mb-2'),
        $el('ul', 'list-unstyled d-flex flex-wrap gap-2 mb-4', lang('Emergencies'), array(
            $chip(lang('Burst pipe')),
            $chip(lang('Leak through the ceiling')),
            $chip(lang('No power')),
            $chip(lang('Blocked drain')),
            $chip(lang('No heating or hot water')),
            $chip(lang('Broken window')),
        )),
        $link(lang('Not urgent? Book a visit instead'), '{{page:book}}', 'small link-body-emphasis link-offset-2'),
    )), array('effect' => 'slide-up', 'trigger' => 'view', 'duration' => 800));
    $card = $fx($el('div', 'p-4 p-lg-5 rounded-4 border bg-body-tertiary', lang('Before We Arrive'), array(
        $heading('h3', lang('Before we arrive'), 'phs-display h4 mb-1'),
        $para(lang('What you can do safely while you wait.'), 'small text-body-secondary mb-4'),
        $fx($el('ol', 'pg-homeservices-safety mb-4', lang('Steps'), array(
            $safe(lang('Turn off the water'), lang('Close the stopcock, usually under the kitchen sink or beside the water meter.')),
            $safe(lang('Make the electrics safe'), lang('If water is near sockets or lights, switch off the power at the fuse box. Do not touch wet switches.')),
            $safe(lang('Catch the water'), lang('Put a bucket under the leak and move what you can out of its way.')),
            $safe(lang('Take photos'), lang('Pictures of the damage help your insurer, and help the technician bring the right parts.')),
        )), array('effect' => 'slide-up', 'trigger' => 'view', 'duration' => 600, 'delay' => 300, 'stagger' => 120)),
        $el('div', 'd-flex gap-3 p-3 rounded-3 bg-warning-subtle text-warning-emphasis', lang('Gas Warning'), array(
            $icon('bi-exclamation-triangle-fill', 'fs-5 flex-shrink-0'),
            $para(lang('Smell gas? Do not use switches or flames. Leave the building first, then call the gas emergency number.'), 'small mb-0'),
        )),
    )), array('effect' => 'slide-up', 'trigger' => 'view', 'duration' => 800, 'delay' => 150));
    return $el('section', 'py-5 bg-body text-body overflow-hidden', lang('Home Services (Emergency Line)'), array(
        $container(array(
            $row(array(
                $col(array($words), '', '6'),
                $col(array($card), '', '6'),
            ), '4', '', 'align-items-center g-lg-5'),
        ), 'py-lg-4'),
    ), array_merge($searchable('hs-home-emergency', lang('emergency, urgent, flood, leak, power cut, out of hours, gas')),
        array('_attrs' => array($attr('data-bs-theme', 'dark')))));
};

// The service areas as zones, the Locations: Zones block: three rings
// around the centre (a drawing screen readers skip), and beside them the
// core areas with no call-out charge, the outer areas with it on their
// visit days and further afield by estimate; under them the arrival
// windows as time slots and the rule of the charge. The row rises on view.
$zones_band = function () use ($el, $heading, $para, $span, $icon, $time, $container, $row, $col, $fx, $fade_in, $attr, $searchable, $section_header,
    $arrival_windows, $core_names, $outer_names, $call_out_text) {
    $rings = array(
        1 => 'text-bg-primary',
        2 => 'bg-primary-subtle border border-2 border-primary-subtle',
        3 => 'pg-zone-z3 border border-2 border-primary',
    );
    $zone = function ($n, $name, $text, $when, $today, $fee) use ($el, $heading, $para, $span, $attr, $rings) {
        return $el('li', 'd-flex gap-3 py-3 border-bottom', sprintf(lang('Zone %s'), $n), array(
            $el('div', 'pg-zone-key ' . $rings[$n], lang('Zone Mark'), array(), array('_attrs' => array($attr('aria-hidden', 'true')))),
            $el('div', 'flex-grow-1', lang('Zone Text'), array(
                $el('div', 'd-flex flex-wrap align-items-baseline justify-content-between gap-2 mb-1', lang('Title'), array(
                    $heading('h3', $name, 'phs-display h5 mb-0'),
                    $el('div', '', lang('Fee'), array(
                        $span(lang('Call-out charge'), 'small text-body-secondary me-1', lang('Text')),
                        $span($fee, 'phs-price fw-bold', lang('Text')),
                    )),
                )),
                $para($text, 'small text-body-secondary mb-2'),
                $span($when, 'badge rounded-pill text-wrap ' . ($today ? 'text-bg-primary' : 'bg-body-secondary text-body-emphasis'), lang('Badge')),
            )),
        ));
    };
    $map = $el('div', 'pg-zone-map', lang('Zone Drawing'), array(
        $el('div', 'pg-zone-ring ' . $rings[3], sprintf(lang('Zone %s'), 3), array(
            $el('div', 'pg-zone-ring ' . $rings[2], sprintf(lang('Zone %s'), 2), array(
                $el('div', 'pg-zone-ring pg-zone-z1 ' . $rings[1], sprintf(lang('Zone %s'), 1), array($icon('bi-geo-alt-fill', 'fs-3'))),
            )),
        )),
    ), array('_attrs' => array($attr('aria-hidden', 'true'))));
    $slots = array();
    foreach ($arrival_windows as $w) {
        $slots[] = $el('li', 'col', $w['name'], array(
            $el('div', 'h-100 p-3 rounded-3 border text-center', lang('Time Slot'), array(
                $icon($w['icon'], 'd-block text-primary fs-4 mb-1'),
                $span($w['name'], 'd-block fw-semibold', lang('Text')),
                $el('div', 'small text-body-secondary', lang('Hours'), array(
                    $time($w['from'], $w['from'], '', lang('Start Time')),
                    $span('–', 'mx-1', lang('Text')),
                    $time($w['to'], $w['to'], '', lang('End Time')),
                )),
            )),
        ));
    }
    $zone_row = $row(array(
        $col(array($map), '', '5'),
        $col(array(
            $el('ul', 'pg-zone-list list-unstyled mb-4 border-top', lang('Zones'), array(
                $zone(1, lang('Core areas'), implode(', ', $core_names) . '.', lang('Every day we are open'), true, lang('None')),
                $zone(2, lang('Outer areas'), implode(', ', $outer_names) . '.', lang('Two or three days a week'), false, $call_out_text),
                $zone(3, lang('Further afield'), lang('We sometimes travel further for a larger job. Ask for an estimate and tell us where you are; we answer within a working day.'),
                    lang('By estimate'), false, lang('In the estimate')),
            )),
            $heading('h3', lang('Arrival windows'), 'h6 fw-semibold text-uppercase text-body-secondary mb-3'),
            $el('ul', 'pg-zone-list list-unstyled row row-cols-1 row-cols-sm-3 g-3 mb-4', lang('Time Slots'), $slots),
            $el('div', 'pg-zone-list d-flex align-items-center gap-3 p-3 rounded-3 bg-primary-subtle text-primary-emphasis', lang('Call-out Charge'), array(
                $icon('bi-receipt', 'fs-3'),
                $el('div', '', lang('Text'), array(
                    $span(lang('One charge per visit'), 'd-block fw-semibold', lang('Text')),
                    $para(lang('The charge is added once per visit, however many jobs we do while we are there.'), 'small mb-0'),
                )),
            )),
        ), '', '7'),
    ), '4', '', 'align-items-center g-lg-5');
    return $el('section', 'py-5', lang('Locations (Zones)'), array(
        $container(array(
            $fx($section_header(lang('Where we work'), lang('Three zones around the city centre')), $fade_in),
            $fx($zone_row, array('effect' => 'slide-up', 'trigger' => 'view', 'stagger' => 150)),
        )),
    ), $searchable('hs-call-out', lang('call-out fee, travel charge, distance, outside the city, zone')));
};

// ── Home ────────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('Cleaning, plumbing, electrical work and repairs at home, with the price agreed first and a twelve-month guarantee.'),
    'tree'             => $page(array(
        // The way in: the company in a sentence beside a visit's job sheet.
        $jobsheet_hero(),

        $el('section', 'py-5 border-top', lang('Service Categories'), array(
            $container(array(
                $fx($section_header(lang('What we do'), lang('Every job at home, one team'), lang('All Services'), '{{page:services}}'), $fade_in),
                $rise($row($category_cols, '4')),
            )),
        ), $searchable('hs-home-categories', lang('cleaner, plumber, electrician, handyman, repairs'))),

        // When a technician comes: the visit form's arrival windows.
        $windows_band(),

        // The featured services, read from the Services collection.
        $el('section', 'py-5', lang('Featured Services'), array(
            $container(array(
                $fx($section_header(lang('Booked most often'), lang('Popular services'), lang('See the prices'), '{{page:prices}}'), $fade_in),
                $widget('home_services'),
            )),
        )),

        // The emergency line and what to do before the technician arrives.
        $emergency_band(),

        // The team, read from the Team collection.
        $el('section', 'py-5', lang('Our Team'), array(
            $container(array(
                $fx($section_header(lang('The people who come'), lang('Meet the team'), lang('Our Team'), '{{page:team}}'), $fade_in),
                $widget('home_team'),
            )),
        )),

        // What customers say: sample words for the company to replace, and
        // beside them its own Google reviews, which show once Google Reviews
        // is set up under Site Settings. Each service page carries its own
        // reviews.
        $el('section', 'py-5 bg-body-tertiary', lang('Testimonials'), array(
            $container(array(
                $fx($section_header(lang('Our customers'), lang('What customers say'), lang('Customer Reviews'), '{{page:reviews}}'), $fade_in),
                $rise($row($testimonial_cols, '4'), 120),
                $widget('google_reviews'),
            )),
        )),

        $el('section', 'py-5', lang('From the Blog'), array(
            $container(array(
                $fx($section_header(lang('Home Care Tips'), lang('Keep your home in good shape'), lang('All tips'), '{{page:blog}}'), $fade_in),
                $widget('home_posts'),
            )),
        )),
    )),
);

// ── About ───────────────────────────────────────────────────────────────

$about_values = array(
    array('icon' => 'bi-hand-thumbs-up', 'title' => lang('Done right the first time'), 'text' => lang('We bring the parts a job is likely to need, so most jobs end in one visit.')),
    array('icon' => 'bi-house-heart', 'title' => lang('Treat every home as ours'), 'text' => lang('Shoe covers on, dust sheets down and the room left cleaner than we found it.')),
    array('icon' => 'bi-mortarboard', 'title' => lang('Always learning'), 'text' => lang('Every technician trains each year on new materials, appliances and safety rules.')),
);
$about_value_tiles = array();
foreach ($about_values as $v) $about_value_tiles[] = $feature_tile($v['icon'], $v['title'], $v['text'], '4');

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About Us'),
    'meta_description' => lang('A home services company started by a plumber and a cleaner: one team for every job, prices agreed first.'),
    'tree'             => $page(array(
        $page_header(lang('About Us'), $company['tagline'] . '.'),
        $story_band('hs-our-story', lang('Our story'), lang('A plumber, a cleaner and one phone number'),
            array($company['story'], $company['licence'] . '.'),
            $photo('about-team', 1200, 900), lang('The team in front of the company vans'), false, array(),
            lang('history, founders, company, who we are')),
        $el('section', 'py-4 border-top border-bottom bg-body-tertiary', lang('Figures'), array(
            $container(array($rise($figure_row(), 120))),
        )),
        $el('section', 'py-5', lang('Our Values'), array(
            $container(array(
                $fx($section_header(lang('How we work'), lang('What we care about'), lang('Our Team'), '{{page:team}}'), $fade_in),
                $rise($row($about_value_tiles, '4'), 120),
            )),
        ), $searchable('hs-values', lang('values, quality, tidy, training, respect'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Why Choose Us'), array(
            $container(array(
                $fx($section_header(lang('Our promise'), lang('Why customers choose us')), $fade_in),
                $rise($row($why_tiles, '4')),
            )),
        ), $searchable('hs-about-why', lang('insured, vetted, guarantee, fixed price, punctual'))),
        $ask_band(lang('A question about a job?'), lang('Write to us with a few words and a photo; the right technician answers, usually the same day.')),
    )),
);

// ── Service areas ───────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'areas',
    'name'             => lang('service-areas'),
    'folder'           => 'public',
    'title'            => lang('Service Areas'),
    'meta_description' => lang('The parts of the city we visit, on which days, and where a call-out charge applies.'),
    'tree'             => $page(array(
        $page_header(lang('Service Areas'), lang('Where our technicians go, on which days, and what a visit costs to get there.')),
        // The areas as zones around the centre, with the call-out charge.
        $zones_band(),
        $el('section', 'py-5 bg-body-tertiary', lang('Areas'), array(
            $container(array(
                $fx($section_header(lang('Area by area'), lang('The days we visit each area')), $fade_in),
                $rise($row($area_cols, '4')),
            )),
        ), $searchable('hs-areas', lang('district, neighbourhood, location, coverage, do you come to'))),
        $el('section', 'py-5', lang('Outside these areas?'), array(
            $container(array(
                $fx($el('div', 'd-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3', lang('Text'), array(
                    $el('div', '', lang('Text'), array(
                        $heading('h2', lang('Outside these areas?'), 'phs-display h4 mb-1'),
                        $para(lang('Send us a few photos and a line about the job: we reply with a written estimate, free and with no obligation.'), 'text-body-secondary mb-0'),
                    )),
                    $link(lang('Get an Estimate'), '{{page:estimate}}', 'btn btn-outline-primary px-4 text-nowrap'),
                )), $fade_in),
            )),
        )),
    )),
);

// ── Price guide ─────────────────────────────────────────────────────────

// A table per category, rising on view: a row for each sample service, its
// starting price and a button opening the visit booking with the service
// chosen.
$price_tables = array();
foreach ($categories as $ckey => $c) {
    $rows = array();
    foreach ($services as $key => $s) {
        if ($s['category'] !== $ckey) continue;
        $rows[] = $el('tr', '', $s['name'], array(
            $el('th', 'fw-semibold', lang('Service Name'), array(), array('text' => $s['name'], '_attrs' => array($attr('scope', 'row')))),
            $el('td', 'text-nowrap', lang('Duration'), array(), array('text' => $s['duration'])),
            $el('td', 'phs-price fw-semibold text-nowrap', lang('Price'), array(), array('text' => $price_text($key))),
            $el('td', 'text-end', lang('Action'), array(
                $link(lang('Book a visit for this service'), '{{page:book}}?' . $query(array('service' => $s['name'])), 'btn btn-sm btn-outline-primary px-3 text-nowrap'),
            )),
        ));
    }
    $price_tables[] = $fx($el('div', 'mb-5', $c['title'], array(
        $el('div', 'd-flex align-items-center gap-2 mb-3', lang('Category Heading'), array(
            $icon($c['icon'], 'fs-4 text-primary'),
            $heading('h2', $c['title'], 'phs-display h4 mb-0'),
        )),
        $el('div', 'table-responsive', lang('Table'), array(
            $el('table', 'table align-middle mb-0', lang('Price Table'), array(
                $el('thead', '', lang('Table Head'), array(
                    $el('tr', '', lang('Row'), array(
                        $el('th', '', lang('Service Name'), array(), array('text' => lang('Service Name'), '_attrs' => array($attr('scope', 'col')))),
                        $el('th', '', lang('Duration'), array(), array('text' => lang('How long'), '_attrs' => array($attr('scope', 'col')))),
                        $el('th', '', lang('Price'), array(), array('text' => lang('Starting price'), '_attrs' => array($attr('scope', 'col')))),
                        $el('th', '', lang('Action'), array($span(lang('Book a visit for this service'), 'visually-hidden', lang('Text'))), array('_attrs' => array($attr('scope', 'col')))),
                    )),
                )),
                $el('tbody', '', lang('Table Body'), $rows),
            )),
        )),
    ), array('id' => 'hs-prices-' . $ckey)), array('effect' => 'slide-up', 'trigger' => 'view', 'duration' => 700));
}

$public_pages[] = array(
    'key'              => 'prices',
    'name'             => lang('prices'),
    'folder'           => 'public',
    'title'            => lang('Prices'),
    'meta_description' => lang('Starting prices for cleaning, plumbing, electrical work and repairs, and how the final price is agreed.'),
    'tree'             => $page(array(
        $page_header(lang('Prices'), lang('What our services start from. We confirm the exact price before any work begins.')),
        $el('section', 'py-5', lang('Price Guide'), array(
            $container(array(
                $row(array(
                    $col($price_tables, '', '10'),
                ), '', 'center'),
            )),
        ), $searchable('hs-price-guide', lang('price, cost, how much, rate, hourly, fee'))),
        $el('section', 'py-5 bg-body-tertiary', lang('How Prices Work'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('How the price is set'), 'phs-display h3 mb-3'),
                        $check_list(array(
                            lang('A price per visit covers the work the service describes; we confirm it when we confirm your visit.'),
                            lang('A price per hour is charged for the time on site, one hour at least, then by the half hour.'),
                            lang('Parts beyond small fixings are priced separately and agreed with you before they are fitted.'),
                            sprintf(lang('A call-out charge of %1$s per visit in %2$s.'), $call_out_text, implode(', ', $outer_names)),
                        )),
                    ), '', '6'),
                    $col(array(
                        $heading('h2', lang('A bigger or unusual job?'), 'phs-display h3 mb-3'),
                        $para(lang('Send us a few photos and a line about the job: we reply with a written estimate, free and with no obligation.'), 'text-body-secondary mb-3'),
                        $link(lang('Get a Free Estimate'), '{{page:estimate}}', 'btn btn-primary px-4'),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('hs-how-prices-work', lang('parts, materials, hourly rate, minimum charge, quote'))),
        // How a job goes, from the booking to the payment: the steps come in
        // one after another.
        $el('section', 'py-5', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $fx($el('div', '', lang('Section Title'), array(
                            $para(lang('Simple from start to finish'), 'phs-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                            $heading('h2', lang('How it works'), 'phs-display h2 mb-4'),
                        )), $fade_in),
                        $rise($how_steps(), 120),
                        $link(lang('Book a Visit'), '{{page:book}}', 'btn btn-primary px-4'),
                    ), '', '6'),
                    $col(array($framed($photo('home-how', 1200, 900), lang('A technician showing a customer the work on a tablet'))), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('hs-how-it-works', lang('how to book, steps, payment, arrival window'))),
    )),
);

// ── Questions ───────────────────────────────────────────────────────────
// Three accordions: booking, on the day, prices and payment. Each question
// is a search section of its own ($faq); each group's heading fades in.
$faq_group = function ($id, $title, $items) use ($el, $heading, $faq, $fx, $fade_in) {
    $children = array();
    foreach ($items as $n => $q) $children[] = $faq($n + 1, $q[0], $q[1], $q[2], $id);
    return $el('div', 'mb-5', $title, array(
        $fx($heading('h2', $title, 'phs-display h4 mb-3'), $fade_in),
        $el('div', 'accordion', lang('Questions'), $children, array('id' => $id)),
    ));
};

$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Frequently Asked Questions'),
    'meta_description' => lang('Answers about booking a visit, arrival times, emergencies, prices, parts, payment and our guarantee.'),
    'tree'             => $page(array(
        $page_header(lang('Frequently Asked Questions'), lang('Booking a visit, the day itself, prices and payment. Not answered here? Write to us.')),
        $el('section', 'py-5', lang('Questions'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $faq_group('hs-faq-booking', lang('Booking a visit'), array(
                            array(lang('Is my visit booked as soon as I send the form?'), lang('Not yet: we check the day and the time window and confirm within a working day. Your visit is booked when it reads "Confirmed" under My Visits.'), lang('confirmation, request, availability, appointment')),
                            array(lang('How do I follow my visit or my estimate?'), lang('Sending a form opens an account for you. Under My Account you see the state of each visit and estimate and can write to us there.'), lang('my account, status, follow, message')),
                            array(lang('Should I book a visit or ask for an estimate?'), lang('Book a visit when the job is one of our listed services. Ask for an estimate for a larger or unusual job, or when you want a price in writing first.'), lang('quote, estimate, price first, large job')),
                            array(lang('What about emergencies?'), sprintf(lang('For a burst pipe, a flood or a home without power, call our emergency line on %s; do not use the forms.'), $company['emergency']), lang('emergency, urgent, flood, leak, power cut, out of hours')),
                        )),
                        $faq_group('hs-faq-visit', lang('On the day'), array(
                            array(lang('When will the technician arrive?'), lang('Within the time window you chose. If we are running late, the technician calls you before the window ends.'), lang('arrival time, late, window, punctual')),
                            array(lang('Do I need to be at home?'), lang('Not always: you can leave a key for us, and say so in the booking. We send you a message when the work is done.'), lang('key, access, not at home, absent')),
                            array(lang('Do you bring your own products and tools?'), lang('Yes: our cleaners bring their products and equipment, and our technicians their tools and the common parts.'), lang('equipment, products, tools, supplies')),
                            array(lang('Who comes to my home?'), lang('A trained and insured member of our own team; each one is on our team page.'), lang('technician, cleaner, staff, vetted, insured')),
                        )),
                        $faq_group('hs-faq-payment', lang('Prices and payment'), array(
                            array(lang('How much will it cost?'), lang('Our price guide shows what each service starts from; we confirm the price before any work begins, and it does not change halfway.'), lang('price, cost, how much, fixed price')),
                            array(lang('Is there a call-out charge?'), sprintf(lang('Only outside the centre: %1$s per visit in %2$s.'), $call_out_text, implode(', ', $outer_names)), lang('call-out fee, travel charge')),
                            array(lang('When and how do I pay?'), lang('Once the work is done and you are happy with it, by card or bank transfer. We send the invoice by e-mail.'), lang('payment, card, bank transfer, invoice')),
                            array(lang('What if something goes wrong later?'), $company['licence'] . '.', lang('guarantee, warranty, insurance, come back')),
                        )),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
    )),
);

// ── Customer reviews ────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'reviews',
    'name'             => lang('reviews'),
    'folder'           => 'public',
    'title'            => lang('Customer Reviews'),
    'meta_description' => lang('What our customers say about our cleaning, plumbing, electrical and repair work.'),
    'tree'             => $page(array(
        $page_header(lang('Customer Reviews'), lang('What our customers say. Every service page carries the reviews of that service, with their stars.')),
        // Sample words for the company to replace, then its own Google
        // reviews, which show once Google Reviews is set up under Site
        // Settings.
        $el('section', 'py-5', lang('Testimonials'), array(
            $container(array(
                $rise($row($testimonial_cols, '4'), 120),
            )),
        ), $searchable('hs-reviews', lang('reviews, ratings, testimonials, feedback, stars'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Google Reviews'), array(
            $container(array(
                $widget('google_reviews'),
            )),
        )),
        $el('section', 'py-5', lang('Leave a Review'), array(
            $container(array(
                $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3', lang('Text'), array(
                    $el('div', '', lang('Text'), array(
                        $heading('h2', lang('Had a job done by us?'), 'phs-display h4 mb-1'),
                        $para(lang('Open the service you had on our service list and leave your review and stars at the foot of its page.'), 'text-body-secondary mb-0'),
                    )),
                    $link(lang('Our Services'), '{{page:services}}', 'btn btn-primary px-4 text-nowrap'),
                )),
            )),
        ), $searchable('hs-leave-review', lang('write a review, rate, feedback'))),
    )),
);

// The home page's lists: its featured services (home_services) and team
// (home_team) come with the services, its tips (home_posts) and the Google
// reviews (google_reviews) with the standard pages; this part places no
// widget of its own.
$public_widgets = array();

return array('pages' => $public_pages, 'widgets' => $public_widgets);
