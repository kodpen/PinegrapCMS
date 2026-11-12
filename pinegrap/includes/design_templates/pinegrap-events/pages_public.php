<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Events and Conferences", its public pages: home, the
 * programme, the speakers, the venue and the about page, and the Google
 * Reviews widget the home page places beside its sample testimonials.
 * Included after parts.php in the same scope (its builders and the
 * conference's data); returns array('pages' => ..., 'widgets' => ...).
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

// The figures of the conference, in a row of four.
$figure_row = function ($class = '') use ($figures, $el, $row, $col, $span) {
    $cols = array();
    foreach ($figures as $f) {
        $cols[] = $col(array(
            $el('div', 'text-center', lang('Figure'), array(
                $span($f['value'], 'd-block display-6 fw-bold text-primary', lang('Number')),
                $span($f['label'], 'small text-body-secondary', lang('Label')),
            )),
        ), '3', '', '', '6');
    }
    return $row($cols, '4', '', $class);
};

// What attendees say: sample words the organiser replaces with its own.
$testimonial = function ($quote, $who, $what) use ($el, $para, $col) {
    return $col(array(
        $el('figure', 'h-100 p-4 rounded-4 bg-body mb-0', lang('Testimonial'), array(
            $el('blockquote', 'mb-3', lang('Quote'), array($para($quote, 'mb-0'))),
            $el('figcaption', 'small', lang('Author'), array(
                $para($who, 'fw-semibold mb-0'),
                $para($what, 'text-body-secondary mb-0'),
            )),
        )),
    ), '', '4');
};

// The sponsors of one tier as a row of logos; the headline partner larger.
$sponsor_row = function ($tier) use ($sponsors, $el, $row, $col, $image, $logo, $heading, $attr) {
    $cols = array();
    $big = $tier === 'headline';
    foreach ($sponsors[$tier]['names'] as $name) {
        $cols[] = $col(array(
            $el('div', 'd-flex align-items-center justify-content-center h-100 p-3 rounded-3 border bg-body', $name, array(
                $image($logo($name), $name, 'img-fluid', array('_attrs' => array($attr('loading', 'lazy')))),
            )),
        ), $big ? '6' : '4', $big ? '4' : '3', '', '6');
    }
    return $el('div', 'mb-4', $sponsors[$tier]['title'], array(
        $heading('h3', $sponsors[$tier]['title'], 'h6 text-uppercase small fw-bold text-body-secondary mb-3'),
        $row($cols, '3', $big ? 'center' : ''),
    ));
};

// ── The Conference blocks ───────────────────────────────────────────────
// The home and the programme are built on the visual designer's Conference
// blocks (assets/js/style_designer_blocks_conference.js): the same structure
// and classes with the conference's own data; their rules ship in the
// template's User Styles. Motion comes from the interactions on the nodes
// (pg_fx; nothing moves under reduced motion): $fx gives a node one effect.
$fx = function ($node, $effect) {
    $node['props']['_fx'] = $effect;
    return $node;
};
// Section headings fade in; a list's or a row's children rise one by one.
$fade_in = function ($node) use ($fx) {
    return $fx($node, array('effect' => 'fade', 'trigger' => 'view', 'duration' => 700));
};
$rise = function ($node, $stagger = 90) use ($fx) {
    return $fx($node, array('effect' => 'slide-up', 'trigger' => 'view', 'duration' => 600, 'stagger' => $stagger));
};
// An icon and a short text side by side, as one line of details.
$detail = function ($icon_name, $text, $class = '') use ($el, $icon, $span) {
    return $el('span', trim('d-inline-flex align-items-center gap-1 ' . $class), $text, array(
        $icon($icon_name),
        $span($text, '', lang('Text')),
    ));
};
// A second button: the body's text colour and a border, readable on any
// band, with its own focus ring (pg-conference-second).
$second_button = function ($text, $href, $size = '') use ($link) {
    return $link($text, $href, trim('btn ' . $size . ' border px-4 link-body-emphasis pg-conference-second'));
};
// A track's pill: its icon and its name on the track's soft colour, so the
// colour never names the track alone.
$track_pill = function ($key, $tag = 'span') use ($el, $icon, $span, $tracks) {
    $t = $tracks[$key];
    return $el($tag, 'badge rounded-pill bg-' . $t['color'] . '-subtle text-' . $t['color'] . '-emphasis text-wrap fw-semibold d-inline-flex align-items-center gap-1 px-3 py-2', $t['title'], array(
        $icon($t['icon']),
        $span($t['title'], '', lang('Text')),
    ));
};

// The opening hero: where and when, the line, the way to register and the
// passes, the figures counting up, and an attendee's badge hanging on its
// lanyard from the band's top edge.
$conference_hero = function () use ($el, $heading, $para, $link, $span, $icon, $container, $row, $col, $attr, $fx, $detail, $second_button, $figures, $venue, $passes) {
    $figure_cols = array();
    foreach ($figures as $f) {
        $figure_cols[] = $col(array(
            $fx($para($f['value'], 'pg-conference-figure display-6 fw-bold text-body-emphasis mb-1'), array('effect' => 'count', 'duration' => 1600, 'delay' => 300)),
            $para($f['label'], 'small text-body-secondary mb-0'),
        ), '3', '', '', '6');
    }
    $words = $fx($el('div', '', lang('Hero Text'), array(
        $el('p', 'd-flex flex-wrap align-items-center column-gap-3 row-gap-1 small fw-semibold text-body-secondary mb-4', lang('Event Line'), array(
            $detail('bi-calendar2-week', lang('Two days in autumn'), 'gap-2'),
            $detail('bi-geo-alt', $venue['name'], 'gap-2'),
        )),
        $heading('h1', lang('The conference for people who build products'), 'pg-display-xl fw-bold mb-4'),
        $para(lang('Keynotes, four tracks and hands-on workshops by the people behind products you use every day. Meet them, and the 1,200 others who come to learn.'), 'lead text-body-secondary mb-4'),
        $el('div', 'd-flex flex-wrap gap-2 mb-3', lang('Buttons'), array(
            $link(lang('Register'), '{{page:registration}}', 'btn btn-primary btn-lg px-4'),
            $second_button(lang('See the programme'), '{{page:programme}}', 'btn-lg'),
        )),
        $el('a', 'd-inline-flex align-items-center gap-2 small fw-semibold link-body-emphasis link-underline-opacity-25', lang('Passes'), array(
            $icon('bi-ticket-perforated'),
            $span(sprintf(lang('Passes from %s'), $passes['day']['price']), '', lang('Text')),
        ), array('href' => '{{page:tickets}}')),
    )), array('effect' => 'slide-up', 'trigger' => 'load', 'duration' => 800));
    $chip = function ($text) use ($el, $span) {
        return $el('li', 'badge rounded-pill border text-body-emphasis fw-semibold text-wrap', $text, array($span($text, '', lang('Text'))));
    };
    $badge = $fx($el('div', 'pg-conference-lanyard', lang('Lanyard'), array(
        $el('div', 'pg-conference-badge bg-body text-body rounded-4 shadow-lg', lang('Attendee Badge'), array(
            $el('div', 'text-bg-primary d-flex align-items-center justify-content-between gap-3 rounded-top-4 px-4 pt-4 pb-3', lang('Header'), array(
                $el('p', 'd-flex align-items-center gap-2 fw-bold mb-0', lang('Brand'), array(
                    $icon('bi-calendar2-event'),
                    $span('{{site_name}}', '', lang('Name')),
                )),
                $para(lang('2 days'), 'small fw-semibold mb-0'),
            )),
            $el('div', 'px-4 pt-4 pb-3', lang('Details'), array(
                $heading('h2', $passes['full']['title'], 'h6 text-uppercase fw-bold text-body-secondary mb-3'),
                $para(lang('Jane Cooper'), 'fs-2 fw-bold lh-sm text-body-emphasis mb-1'),
                $para('Vertex', 'fs-5 fw-semibold text-body-emphasis mb-0'),
                $para(lang('Design Director'), 'small text-body-secondary mb-4'),
                $el('ul', 'list-unstyled d-flex flex-wrap gap-2 mb-0', lang('Included'), array(
                    $chip(lang('Day 1')),
                    $chip(lang('Day 2')),
                    $chip(lang('The evening reception')),
                )),
            )),
            $el('div', 'd-flex align-items-end justify-content-between gap-3 border-top px-4 py-3', lang('Footer'), array(
                $el('div', 'pg-conference-code', lang('Barcode'), array(), array('_attrs' => array($attr('aria-hidden', 'true')))),
                $para('0427', 'small font-monospace text-body-secondary mb-0'),
            )),
        ), array('_attrs' => array($attr('data-bs-theme', 'light')))),
    )), array('effect' => 'slide-down', 'trigger' => 'load', 'duration' => 1000, 'delay' => 250, 'distance' => 120));
    return $el('section', 'pg-conference-hero py-5 bg-body text-body overflow-hidden', lang('Hero'), array(
        $container(array(
            $row(array(
                $col(array($words), '', '7'),
                $col(array($badge), '', '5'),
            ), '4', '', 'g-lg-5 align-items-center'),
            $el('div', 'border-top mt-5 pt-4', lang('Figures'), array($row($figure_cols, '4'))),
        ), 'pt-lg-4'),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// A day of the programme as the Day Agenda's time slots: the sessions at
// one time together; a keynote across the width; a break, the welcome or
// the evening as a quiet line; the others side by side, each with its
// track, speaker, kind and room. On the programme ($anchors) each slot
// keeps the id the speakers page links to, and a talk with a speaker is a
// search section.
$agenda_slots = function ($day_key, $anchors) use ($programme, $speakers, $session_kinds, $el, $heading, $para, $link, $span, $image, $portrait, $row, $col, $attr, $detail, $track_pill, $searchable) {
    $groups = array();
    foreach ($programme[$day_key]['sessions'] as $s) {
        $last = count($groups) - 1;
        if ($last >= 0 && $groups[$last][0]['time'] === $s['time']) $groups[$last][] = $s;
        else $groups[] = array($s);
    }
    $id_of = function ($s) use ($anchors, $speakers, $searchable) {
        if (!$anchors) return array();
        return $s['speaker'] !== '' ? $searchable('session-' . $s['key'], $speakers[$s['speaker']]['name']) : array('id' => 'session-' . $s['key']);
    };
    $speaker_link = function ($key, $class) use ($speakers, $link) {
        return $link($speakers[$key]['name'], '{{page:speakers}}#speaker-' . $key, $class);
    };
    $slots = array();
    foreach ($groups as $g) {
        $s = $g[0];
        $time = $el('time', 'pg-conference-time', lang('Time'), array(), array('text' => $s['time'], '_attrs' => array($attr('datetime', $s['time']))));
        $kind = $session_kinds[$s['kind']];
        if (count($g) === 1 && $s['kind'] === 'keynote') {
            $who = array();
            if ($s['speaker'] !== '') {
                $p = $speakers[$s['speaker']];
                $who[] = $image($portrait($p['name'], 160), $p['name'], 'pg-conference-face rounded-circle object-fit-cover', array('width' => '56', 'height' => '56', '_attrs' => array($attr('loading', 'lazy'))));
                $who[] = $el('div', 'flex-grow-1', lang('Text'), array(
                    $el('p', 'mb-0', lang('Speaker'), array($speaker_link($s['speaker'], 'fw-semibold link-body-emphasis'))),
                    $para($p['role'] . ', ' . $p['company'], 'small text-body-secondary mb-0'),
                ));
            }
            $who[] = $detail('bi-door-open', $s['room'], 'small text-body-secondary');
            $slots[] = $el('li', 'pg-conference-slot', $s['title'], array(
                $time,
                $el('div', 'pg-conference-keynote rounded-4 border bg-body p-4 p-lg-5', $kind['label'], array(
                    $el('p', 'd-flex align-items-center gap-2 small text-uppercase fw-semibold text-body-secondary mb-2', lang('Kind'), array(
                        $el('i', 'bi ' . $kind['icon'] . ' text-primary-emphasis', lang('Icon'), array(), array('_attrs' => array($attr('aria-hidden', 'true')))),
                        $span($kind['label'], '', lang('Text')),
                    )),
                    $heading('h3', $s['title'], 'h3 fw-bold mb-4'),
                    $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Speaker'), $who),
                )),
            ), $id_of($s));
        } elseif (count($g) === 1 && $s['track'] === '' && $s['speaker'] === '') {
            $slots[] = $el('li', 'pg-conference-slot pg-conference-break', $s['title'], array(
                $time,
                $el('p', 'd-flex flex-wrap align-items-center column-gap-3 row-gap-1 text-body-secondary mb-0', lang('Break'), array(
                    $detail($kind['icon'], $s['title'], 'gap-2 fw-semibold text-body-emphasis'),
                    $detail('bi-door-open', $s['room'], 'small'),
                )),
            ), $id_of($s));
        } else {
            $cols = array();
            $md = count($g) > 2 ? '4' : (count($g) === 2 ? '6' : '');
            foreach ($g as $t) {
                $k = $session_kinds[$t['kind']];
                $body = array();
                if ($t['track'] !== '') $body[] = $track_pill($t['track']);
                $body[] = $heading('h3', $t['title'], 'h6 fw-bold mt-3 mb-2');
                if ($t['speaker'] !== '') $body[] = $el('p', 'small mb-2', lang('Speaker'), array($speaker_link($t['speaker'], 'fw-semibold link-body-emphasis')));
                $body[] = $el('p', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary mb-0', lang('Session Details'), array(
                    $detail($k['icon'], $k['label']),
                    $detail('bi-door-open', $t['room']),
                ));
                $cols[] = $col(array(
                    $el('div', 'h-100 rounded-3 border bg-body p-3', $t['title'], $body, $id_of($t)),
                ), $md);
            }
            $slots[] = $el('li', 'pg-conference-slot', lang('Parallel Sessions'), array(
                $time,
                $row($cols, '3'),
            ));
        }
    }
    return $slots;
};

// The speaker line-up: the keynotes in giant type, then each track's
// speakers, every name a link to the profile.
$conference_lineup = function () use ($programme, $speakers, $tracks, $el, $heading, $para, $link, $container, $row, $col, $fade_in, $rise, $second_button) {
    $keynote = array();
    foreach ($programme as $day) {
        foreach ($day['sessions'] as $s) if ($s['kind'] === 'keynote' && $s['speaker'] !== '') $keynote[$s['speaker']] = true;
    }
    $group = function ($title, $keys, $giant) use ($speakers, $el, $heading, $link) {
        $items = array();
        foreach (array_values($keys) as $i => $key) {
            $items[] = $el('li', $i < count($keys) - 1 ? 'pg-conference-slash' : '', $speakers[$key]['name'], array(
                $link($speakers[$key]['name'], '{{page:speakers}}#speaker-' . $key, 'link-body-emphasis link-underline-opacity-0 link-underline-opacity-100-hover link-offset-2'),
            ));
        }
        return $el('div', 'pg-conference-group', $title, array(
            $heading('h3', $title, 'h6 text-uppercase fw-semibold text-body-secondary mb-0'),
            $el('ul', 'pg-conference-names' . ($giant ? ' pg-conference-keynotes' : ''), lang('Names'), $items),
        ));
    };
    $groups = array($group(lang('Keynotes'), array_keys($keynote), true));
    foreach ($tracks as $tkey => $t) {
        $keys = array();
        foreach ($speakers as $key => $s) if ($s['track'] === $tkey && !isset($keynote[$key])) $keys[] = $key;
        if ($keys) $groups[] = $group($t['title'], $keys, false);
    }
    return $el('section', 'py-5 overflow-hidden', lang('Speakers'), array(
        $container(array(
            $fade_in($row(array(
                $col(array(
                    $para(lang('On stage'), 'small text-uppercase fw-semibold text-body-secondary mb-2'),
                    $heading('h2', lang('The line-up'), 'display-5 fw-bold mb-3'),
                    $para(lang('Two keynotes open the mornings; the people behind the products you use fill the four tracks.'), 'lead text-body-secondary mb-0'),
                ), '', '8'),
            ), '4', '', 'mb-4')),
            $rise($el('div', 'pg-conference-lineup', lang('Line-up'), $groups), 120),
            $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-3 border-top pt-4', lang('Footer'), array(
                $para(lang('And more than thirty others, in talks, workshops and the lightning round.'), 'text-body-secondary mb-0'),
                $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                    $link(lang('All speakers'), '{{page:speakers}}', 'btn btn-primary px-4'),
                    $second_button(lang('Propose a talk'), '{{page:speakers}}#call-for-speakers'),
                )),
            )),
        )),
    ));
};

// The home: the opening hero, the first day at a glance, the line-up, then
// the events between two conferences, the attendees' words, the sponsors
// and the news. Section headings fade in and rows rise as they are seen.
$legend = array();
foreach ($tracks as $key => $t) $legend[] = $track_pill($key, 'li');

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('Two days of talks and workshops for people who build products, and meetups all year. See the programme and register.'),
    'tree'             => $page(array(
        $conference_hero(),

        // The first day at a glance: its sessions by time and track.
        $el('section', 'py-5 bg-body-tertiary', lang('Programme'), array(
            $container(array(
                $fade_in($row(array(
                    $col(array(
                        $para($programme['day-1']['title'], 'small text-uppercase fw-semibold text-body-secondary mb-2'),
                        $heading('h2', $programme['day-1']['theme'], 'display-5 fw-bold mb-3'),
                        $para(lang('Two days, four tracks and a keynote to open each morning. Choose your sessions on the day; every talk is recorded.'), 'lead text-body-secondary mb-0'),
                    ), '', '7'),
                    $col(array(
                        $el('ul', 'list-unstyled d-flex flex-wrap gap-2 justify-content-lg-end mb-3', lang('Tracks'), $legend),
                        $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                            $link(lang('Register'), '{{page:registration}}', 'btn btn-primary px-4'),
                            $second_button(lang('See the programme'), '{{page:programme}}'),
                        )),
                    ), '', '5'),
                ), '4', '', 'align-items-end mb-5')),
                $rise($el('ol', 'pg-conference-agenda', lang('Sessions'), $agenda_slots('day-1', false))),
            )),
        )),

        $conference_lineup(),

        // The coming events: meetups, workshops and webinars between two
        // conferences (records of the Events form, from today on).
        $el('section', 'py-5 bg-body-tertiary', lang('Coming Events'), array(
            $container(array(
                $fade_in($section_header(lang('All year round'), lang('Coming events'), lang('All events'), '{{page:events}}')),
                $widget('home_events'),
            )),
        )),

        // What attendees say: three sample cards, and the reviews from
        // Google beside them once the place is set.
        $el('section', 'py-5', lang('Testimonials'), array(
            $container(array(
                $fade_in($section_header(lang('Our attendees'), lang('What attendees say'))),
                $rise($row(array(
                    $testimonial(lang('“The best two days of the year for our team. We came back with a list of things to try on Monday.”'), lang('Ece T.'), lang('Product Manager')),
                    $testimonial(lang('“Talks that go deeper than the slides, and speakers who stay for the questions in the corridor.”'), lang('Mert A.'), lang('Software Engineer')),
                    $testimonial(lang('“I came for the workshops and left with three people I now call when I am stuck.”'), lang('Laura S.'), lang('Design Lead')),
                ), '4')),
                $widget('google_reviews'),
            )),
        )),

        // The sponsors.
        $el('section', 'py-5 border-top', lang('Sponsors'), array(
            $container(array(
                $fade_in($section_header(lang('Made possible by'), lang('Our sponsors'), lang('Become a sponsor'), '{{page:contact}}')),
                $sponsor_row('headline'),
                $sponsor_row('gold'),
            )),
        )),

        // The newest news.
        $el('section', 'py-5 border-top', lang('From the Blog'), array(
            $container(array(
                $fade_in($section_header(lang('News'), lang('Latest news'), lang('All news'), '{{page:blog}}')),
                $widget('home_posts'),
            )),
        )),

        $shared('register_band'),
    )),
);

// ── Programme ───────────────────────────────────────────────────────────
// A tab for each day; each day its time slots as on the home (the Day
// Agenda), every session with its kind, track, speaker and room. Each day
// is a search section, and so is each talk with a speaker; every slot keeps
// the id the speakers page links to.
$day_tabs = array();
$day_panes = array();
$first = true;
foreach ($programme as $key => $day) {
    $day_tabs[] = $el('li', 'nav-item', lang('Nav Item'), array(
        $el('button', 'nav-link' . ($first ? ' active' : ''), $day['title'], array(), array(
            'text'   => $day['title'],
            'id'     => $key . '-tab',
            '_attrs' => array(
                $attr('type', 'button'),
                $attr('role', 'tab'),
                $attr('data-bs-toggle', 'tab'),
                $attr('data-bs-target', '#' . $key),
                $attr('aria-controls', $key),
                $attr('aria-selected', $first ? 'true' : 'false'),
            ),
        )),
    ), array('_attrs' => array($attr('role', 'presentation'))));
    $day_panes[] = $el('div', 'tab-pane fade' . ($first ? ' show active' : ''), $day['title'], array(
        $heading('h2', $day['theme'], 'h3 fw-bold mb-3'),
        $rise($el('ol', 'pg-conference-agenda', lang('Sessions'), $agenda_slots($key, true))),
    ), array_merge($searchable($key, $day['theme']), array('_attrs' => array(
        $attr('role', 'tabpanel'), $attr('aria-labelledby', $key . '-tab'), $attr('tabindex', '0'),
    ))));
    $first = false;
}

$public_pages[] = array(
    'key'              => 'programme',
    'name'             => lang('programme'),
    'folder'           => 'public',
    'title'            => lang('Programme'),
    'meta_description' => lang('The conference programme by day: keynotes, four tracks side by side, workshops, the breaks and the evening reception.'),
    'tree'             => $page(array(
        $page_header(lang('Programme'), lang('Two days, four tracks and a keynote to open each morning. Choose your sessions on the day; every talk is recorded.')),
        $el('section', 'py-5', lang('Programme'), array(
            $container(array(
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-3 mb-4', lang('Days and Tracks'), array(
                    $el('ul', 'nav nav-pills gap-2', lang('Days'), $day_tabs, array('id' => 'ev-days', '_attrs' => array($attr('role', 'tablist')))),
                    $el('ul', 'list-unstyled d-flex flex-wrap gap-2 mb-0', lang('Tracks'), $legend),
                )),
                $el('div', 'tab-content', lang('Day Panes'), $day_panes),
            )),
        )),
        $shared('register_band'),
    )),
);

// ── Speakers ────────────────────────────────────────────────────────────
// A profile for each: the portrait, the role, the track, what they talk
// about and a link to the session in the programme.
$session_titles = array();
foreach ($programme as $dkey => $day) {
    foreach ($day['sessions'] as $s) $session_titles[$s['key']] = array('title' => $s['title'], 'day' => $day['title'], 'time' => $s['time']);
}
$speaker_profiles = array();
foreach ($speakers as $key => $s) {
    $talk = $session_titles[$s['session']];
    $speaker_profiles[] = $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 overflow-hidden', $s['name'], array(
            $el('div', 'row g-0 h-100', lang('Card Row'), array(
                $el('div', 'col-sm-5', lang('Portrait'), array(
                    $el('div', 'ratio ratio-1x1 h-100 bg-primary-subtle', lang('Picture Frame'), array(
                        $image($portrait($s['name']), $s['name'], 'object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
                    )),
                )),
                $el('div', 'col-sm-7', lang('Details'), array(
                    $el('div', 'card-body d-flex flex-column h-100 p-4', lang('Card Body'), array(
                        $el('div', 'mb-2', lang('Badges'), array($track_badge($s['track']))),
                        $heading('h2', $s['name'], 'h5 fw-bold mb-1'),
                        $para($s['role'] . ' · ' . $s['company'], 'small text-primary mb-2'),
                        $para($s['bio'], 'small text-body-secondary mb-3'),
                        $el('p', 'd-flex align-items-start gap-2 small mb-0 mt-auto', lang('Session'), array(
                            $icon('bi-mic', 'text-primary mt-1'),
                            $el('span', '', lang('Text'), array(
                                $link($talk['title'], '{{page:programme}}#session-' . $s['session'], 'fw-semibold link-body-emphasis'),
                                $span(' · ' . $talk['day'] . ', ' . $talk['time'], 'text-body-secondary', lang('When')),
                            )),
                        )),
                    )),
                )),
            )),
        ), $searchable('speaker-' . $key, $s['company'] . ', ' . $tracks[$s['track']]['title'])),
    ), '', '6');
}

$public_pages[] = array(
    'key'              => 'speakers',
    'name'             => lang('our-speakers'),
    'folder'           => 'public',
    'title'            => lang('Speakers'),
    'meta_description' => lang('The speakers of the conference: who they are, where they work and what they will talk about.'),
    'tree'             => $page(array(
        $page_header(lang('Speakers'), lang('People who build, run and grow products, here to share what worked and what did not.')),
        $el('section', 'py-5', lang('Speakers'), array(
            $container(array(
                $row($speaker_profiles, '4'),
            )),
        )),
        $el('section', 'py-5 bg-body-tertiary', lang('Call for Speakers'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Want to speak next time?'), 'h4 fw-bold mb-2'),
                        $para(lang('We keep a third of the programme for talks from the community. Tell us what you would like to share and why it matters.'), 'text-body-secondary mb-0'),
                    ), '', '8'),
                    $col(array(
                        $link(lang('Propose a talk'), '{{page:contact}}', 'btn btn-outline-primary px-4'),
                    ), '', '4', '', '12', 'text-lg-end'),
                ), '4', '', 'align-items-center'),
            )),
        ), $searchable('call-for-speakers', lang('call for papers, propose a talk, speak, CFP'))),
        $shared('register_band'),
    )),
);

// ── Venue & Travel ──────────────────────────────────────────────────────
$room_items = array();
foreach ($venue['rooms'] as $r) {
    $room_items[] = $el('li', 'list-group-item d-flex justify-content-between gap-3 px-0 bg-transparent', $r[0], array(
        $span($r[0], 'fw-semibold', lang('Room')),
        $span($r[1], 'small text-body-secondary text-end', lang('Text')),
    ));
}
$travel_cards = array();
foreach ($venue['travel'] as $t) {
    $travel_cards[] = $col(array(
        $el('div', 'h-100', $t['title'], array(
            $icon_tile($t['icon'], 'mb-3'),
            $heading('h3', $t['title'], 'h6 fw-bold mb-2'),
            $para($t['text'], 'small text-body-secondary mb-0'),
        )),
    ), '6', '3');
}
$hotel_cards = array();
foreach ($venue['hotels'] as $h) {
    $hotel_cards[] = $col(array(
        $el('div', 'card h-100 border-0 shadow-sm rounded-4', $h['name'], array(
            $el('div', 'card-body p-4', lang('Card Body'), array(
                $heading('h3', $h['name'], 'h6 fw-bold mb-1'),
                $el('p', 'd-flex align-items-center gap-1 small text-primary mb-2', lang('Distance'), array($icon('bi-signpost'), $span($h['distance'], '', lang('Text')))),
                $para($h['text'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), '', '4');
}

$public_pages[] = array(
    'key'              => 'venue',
    'name'             => lang('venue'),
    'folder'           => 'public',
    'title'            => lang('Venue & Travel'),
    'meta_description' => lang('Where the conference takes place, how to get there by metro, from the airport or by car, and where to stay nearby.'),
    'tree'             => $page(array(
        $page_header(lang('Venue & Travel'), lang('A congress centre on the water, five minutes from the metro.')),
        $el('section', 'py-5', lang('The Venue'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'ratio ratio-4x3 rounded-4 overflow-hidden', lang('Picture Frame'), array(
                            $image($photo('venue', 1200, 900), lang('The congress centre seen from the quay at dusk'), 'object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
                        )),
                    ), '', '6'),
                    $col(array(
                        $para(lang('The venue'), 'small text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', $venue['name'], 'h3 fw-bold mb-2'),
                        $el('p', 'd-flex align-items-start gap-2 text-body-secondary mb-4', lang('Address'), array($icon('bi-geo-alt', 'text-primary mt-1'), $span($venue['address'], '', lang('Text')))),
                        $el('ul', 'list-group list-group-flush mb-4', lang('Rooms'), $room_items),
                        $link(lang('Open in maps'), 'https://www.openstreetmap.org/search?query=' . rawurlencode($venue['address']), 'btn btn-outline-primary px-4', array($attr('rel', 'noopener')), array('target' => '_blank')),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('the-venue', lang('venue, address, location, rooms, halls'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Getting There'), array(
            $container(array(
                $section_header(lang('Getting there'), lang('How to reach the venue')),
                $row($travel_cards, '4'),
            )),
        ), $searchable('getting-there', lang('metro, airport, taxi, parking, car park, bus'))),
        $el('section', 'py-5', lang('Where to Stay'), array(
            $container(array(
                $section_header(lang('Where to stay'), lang('Hotels nearby')),
                $row($hotel_cards, '4'),
            )),
        ), $searchable('where-to-stay', lang('hotel, accommodation, room rate, stay'))),
        $el('section', 'py-5 bg-body-tertiary', lang('FAQ'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Good to know'), 'small text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('Before you come'), 'h3 fw-bold mb-3'),
                        $para(lang('Anything else? Write to us and the answer comes to your inbox.'), 'text-body-secondary mb-3'),
                        $link(lang('Contact us'), '{{page:contact}}', 'btn btn-outline-primary px-4'),
                    ), '', '4'),
                    $col(array(
                        $el('div', 'accordion', lang('Questions'), array(
                            $faq(1, lang('Is the venue accessible?'), lang('Yes: step-free entrances, lifts to every floor, wheelchair places in every hall and a quiet room. Tell us your needs on the registration form.'), lang('accessible, wheelchair, step-free, quiet room')),
                            $faq(2, lang('Can I pass my ticket to a colleague?'), lang('Yes, until a week before the conference. Write under your registration with your colleague\'s name and e-mail.'), lang('transfer, ticket, colleague, name change')),
                            $faq(3, lang('Is food included?'), lang('Breakfast, lunch, coffee and snacks are included with every pass, with vegetarian, vegan and gluten-free choices.'), lang('food, lunch, vegan, vegetarian, gluten-free, dietary')),
                            $faq(4, lang('Will the talks be recorded?'), lang('Every talk in the halls is recorded; attendees get the recordings by e-mail within two weeks.'), lang('recording, video, slides')),
                        ), array('id' => 'ev-faq')),
                    ), '', '8'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
        $shared('register_band'),
    )),
);

// ── About ───────────────────────────────────────────────────────────────
// Who organises the conference, the tracks (#tracks, each #track-<key>)
// and the sponsors (#sponsors).
$track_items = array();
foreach ($tracks as $key => $t) {
    $track_items[] = $col(array(
        $el('div', 'd-flex gap-3 h-100', $t['title'], array(
            $icon_tile($t['icon']),
            $el('div', '', lang('Text'), array(
                $heading('h3', $t['title'], 'h5 fw-bold mb-1'),
                $para($t['text'], 'text-body-secondary mb-0'),
            )),
        ), $searchable('track-' . $key, $t['topics'])),
    ), '6');
}

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About'),
    'meta_description' => lang('Who organises the conference, the four tracks, and the sponsors who make it possible.'),
    'tree'             => $page(array(
        $page_header(lang('About'), lang('A conference made by a small team for the people who build products, and the sponsors who support it.')),
        $el('section', 'py-5', lang('Our Story'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'ratio ratio-4x3 rounded-4 overflow-hidden', lang('Picture Frame'), array(
                            $image($photo('about', 1200, 900), lang('Attendees talking in the foyer between two sessions'), 'object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
                        )),
                    ), '', '6'),
                    $col(array(
                        $para(lang('Our story'), 'small text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('From a meetup of 40 to a conference of 1,200'), 'h3 fw-bold mb-3'),
                        $para(lang('It began as an evening meetup in a borrowed office. The questions after each talk ran longer than the talks, so we gave them a whole day, and then two.'), 'text-body-secondary'),
                        $para(lang('We still pick every talk ourselves, pay our speakers\' travel and keep the tickets affordable, so the people who build products can learn from each other.'), 'text-body-secondary mb-4'),
                        $figure_row('text-start'),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('our-story', lang('story, organiser, history, team'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Tracks'), array(
            $container(array(
                $section_header(lang('The programme'), lang('Four tracks'), lang('See the programme'), '{{page:programme}}'),
                $row($track_items, '4'),
            )),
        ), array('id' => 'tracks')),
        $el('section', 'py-5', lang('Sponsors'), array(
            $container(array(
                $section_header(lang('Made possible by'), lang('Our sponsors')),
                $sponsor_row('headline'),
                $sponsor_row('gold'),
                $sponsor_row('partner'),
                $el('div', 'p-4 rounded-4 bg-primary-subtle d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mt-2', lang('Become a Sponsor'), array(
                    $el('div', '', lang('Text'), array(
                        $heading('h3', lang('Become a sponsor'), 'h5 fw-bold mb-1'),
                        $para(lang('Meet the people who build products: a stand, a talk slot or the evening reception. Ask for the sponsorship pack.'), 'small mb-0'),
                    )),
                    $link(lang('Ask for the pack'), '{{page:contact}}', 'btn btn-primary px-4 text-nowrap'),
                )),
            )),
        ), $searchable('sponsors', lang('sponsor, sponsorship, partner, stand, exhibit'))),
        $shared('register_band'),
    )),
);

// ── Widgets ─────────────────────────────────────────────────────────────
$public_widgets = array(
    // Google reviews of the venue or the organiser, beside the sample
    // testimonials of the home page. The widget draws nothing until a
    // Places key and the Place ID are saved, and this layout shows only
    // while Google returns reviews. Google asks for each review's author and
    // its link, and for "Google Maps" beside what is shown without a map.
    'google_reviews' => array(
        'page'   => 'home',
        'slug'   => lang('google-reviews'),
        'config' => array('regionType' => 'google_reviews'),
        'tree'   => $widget_root(array(
            $el('div', 'mt-5 pt-4 border-top', lang('Google Reviews'), array(
                $el('div', 'd-flex flex-wrap align-items-center gap-2 mb-4', lang('Rating'), array(
                    $heading('h3', lang('Reviews on Google'), 'h5 fw-bold mb-0 me-2'),
                    $icon('bi-star-fill', 'text-warning'),
                    $span(lang('4.8'), 'fw-bold', lang('Average Rating'), $bind('__place_rating')),
                    $el('span', 'small text-body-secondary', lang('Number of ratings'), array(
                        $span('120', '', lang('Number'), $bind('__place_review_count')),
                        $span(' ' . lang('ratings'), '', lang('Label')),
                    )),
                )),
                $row(array($loop(array($col(array(
                    $el('article', 'card h-100 border-0 shadow-sm p-4', lang('Review'), array(
                        $el('div', 'd-flex align-items-center gap-2 mb-3', lang('Author'), array(
                            $image('', '', 'rounded-circle flex-shrink-0 object-fit-cover', array('width' => '40', 'height' => '40',
                                '_attrs' => array($attr('referrerpolicy', 'no-referrer')),
                                '_bindings' => array('src' => '__review_author_photo', 'eo_visible_if' => 'review_has_photo'))),
                            $el('div', 'small', lang('Name and time'), array(
                                $link(lang('Author name'), '#', 'd-block fw-semibold text-body text-decoration-none', array(),
                                    array('target' => '_blank', 'rel' => 'noopener', '_bindings' => array('href' => '__review_author_url', 'text' => '__review_author_name'))),
                                $span(lang('2 weeks ago'), 'text-body-secondary', lang('When'), $bind('__review_relative_time')),
                            )),
                        )),
                        $el('p', 'small mb-2', lang('Review rating'), array(
                            $icon('bi-star-fill', 'text-warning'),
                            $span('5', 'ms-1', lang('Rating'), $bind('__review_rating')),
                            $span('/5', '', lang('Out of')),
                        )),
                        $el('p', 'mb-3', lang('Review text'), array(), array('text' => lang('Review text'),
                            '_bindings' => array('text' => '__review_text', 'eo_visible_if' => 'review_has_text'))),
                        $link(lang('Read on Google Maps'), '#', 'small mt-auto', array(),
                            array('target' => '_blank', 'rel' => 'noopener', '_bindings' => array('href' => '__review_url'))),
                    )),
                ), '6', '4')))), '4'),
                $el('div', 'd-flex flex-wrap justify-content-between align-items-center gap-2 mt-4 small', lang('Attribution'), array(
                    $link(lang('See all reviews on Google Maps'), '#', '', array(),
                        array('target' => '_blank', 'rel' => 'noopener', '_bindings' => array('href' => '__place_maps_url'))),
                    $span('Google Maps', 'text-body-secondary notranslate', lang('Google attribution'),
                        array('_attrs' => array($attr('translate', 'no')), '_bindings' => array('text' => '__google_attribution'))),
                )),
            ), $show_when('has_reviews')),
        )),
    ),
);

return array('pages' => $public_pages, 'widgets' => $public_widgets);
