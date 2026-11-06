<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Music Artist", its public pages: home, about the band,
 * videos and the questions page. Included after parts.php in the same
 * scope (its builders and the artist's data); returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * Every figure comes from parts.php ($artist, $members, $releases,
 * $press_quotes, $videos, $figures), so a change made there
 * reaches every page here. No page here links a shop page: the template
 * stands on a site without the shop.
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
    return $el('div', 'ratio ratio-' . $ratio . ' rounded-4 overflow-hidden bg-body-tertiary shadow-sm', lang('Picture Frame'), array(
        $image($src, $alt, 'object-fit-cover', $extra),
    ));
};

// A band of text beside a picture; $flip puts the picture first from lg up.
// The band is a search section under its anchor $id, with the topics a
// visitor may search for that it does not say itself.
$story_band = function ($id, $eyebrow, $title, $texts, $picture, $alt, $flip = false, $more = array(), $topics = '', $class = 'py-5')
    use ($el, $heading, $para, $container, $row, $col, $framed, $searchable) {
    $words = array(
        $para($eyebrow, 'pmu-eyebrow text-uppercase fw-semibold text-primary mb-2'),
        $heading('h2', $title, 'pmu-display display-6 mb-3'),
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

// The band in four figures, in one row.
$figure_row = function () use ($el, $para, $row, $col, $figures) {
    $cols = array();
    foreach ($figures as $f) {
        $cols[] = $col(array(
            $el('div', 'text-center', $f['label'], array(
                $para($f['value'], 'pmu-display display-4 text-primary mb-1'),
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
                    $heading('h2', $title, 'pmu-display h3 mb-1'),
                    $para($text, 'text-body-secondary mb-0'),
                )),
                $link(lang('Contact Us'), '{{page:contact}}', 'btn btn-primary rounded-pill px-4 text-nowrap'),
            )),
        )),
    ));
};

// The cards of the shared data, in their order.
$member_cols = array();
foreach (array_keys($members) as $key) $member_cols[] = $member_card($key);
$press_cols = array();
foreach (array_keys($press_quotes) as $n) $press_cols[] = $press_quote_card($n);
$video_cols = array();
foreach (array_keys($videos) as $n) $video_cols[] = $video_card($n);
// The newest release leads the home: the first featured single, else the
// first release.
$lead_key = key($releases);
foreach ($releases as $key => $r) {
    if ($r['featured'] && $r['format'] === 'single') { $lead_key = $key; break; }
}
$lead = $releases[$lead_key];

// ── Home ────────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('New music, tour dates and news from the band, with the way onto the mailing list.'),
    'tree'             => $page(array(
        // The way in, on a dark band: the newest release, the two ways on
        // and a photo of the band on stage.
        $el('section', 'pmu-hero py-5 bg-body text-body overflow-hidden', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para($lead['note'], 'pmu-eyebrow text-uppercase fw-semibold text-primary mb-3'),
                        $heading('h1', $lead['title'], 'pmu-display display-2 mb-3'),
                        $para($lead['summary'], 'lead mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2 mb-4', lang('Buttons'), array(
                            $link(lang('Listen now'), '{{page:discography}}', 'btn btn-primary btn-lg rounded-pill px-4'),
                            $link(lang('Tour Dates'), '{{page:tour}}', 'btn btn-outline-light btn-lg rounded-pill px-4'),
                        )),
                        $listen_links('d-flex flex-wrap gap-3'),
                    ), '', '5'),
                    $col(array(
                        $framed($photo('hero', 1400, 1050), lang('The band on stage under purple lights'), '4x3', false),
                    ), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), array('_attrs' => array($attr('data-bs-theme', 'dark')))),

        // The next shows, read from the Shows collection from today on.
        $el('section', 'py-5', lang('Next Shows'), array(
            $container(array(
                $section_header(lang('On tour'), lang('Next shows'), lang('All Tour Dates'), '{{page:tour}}'),
                $widget('home_shows'),
            )),
        )),

        // The featured releases, read from the Releases collection.
        $el('section', 'py-5 bg-body-tertiary', lang('Latest Releases'), array(
            $container(array(
                $section_header(lang('Discography'), lang('Latest releases'), lang('All Releases'), '{{page:discography}}'),
                $widget('home_releases'),
            )),
        )),

        $el('section', 'py-5', lang('Videos'), array(
            $container(array(
                $section_header(lang('On screen'), lang('Videos'), lang('All Videos'), '{{page:videos}}'),
                $row($video_cols, '4'),
            )),
        )),

        $el('section', 'py-5 bg-body-tertiary', lang('Press Quotes'), array(
            $container(array(
                $section_header(lang('Press'), lang('What the press says'), lang('Press Kit'), '{{page:press}}'),
                $row($press_cols, '4'),
            )),
        ), $searchable('mu-home-press', lang('reviews, critics, magazine, press'))),

        $story_band('mu-home-band', lang('The Band'), lang('Four friends and a rehearsal room'),
            array($artist['story']),
            $photo('home-band', 1200, 900), lang('Four musicians in a small rehearsal room'), true,
            array($link(lang('Meet the Band'), '{{page:about}}', 'btn btn-outline-primary rounded-pill px-4')),
            lang('members, line-up, biography, history')),

        $el('section', 'py-5 bg-body-tertiary', lang('From the News'), array(
            $container(array(
                $section_header(lang('News'), lang('Latest news'), lang('All News'), '{{page:blog}}'),
                $widget('home_posts'),
            )),
        )),
    )),
);

// ── About ───────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About the Band'),
    'meta_description' => lang('The story of the band, its four members and what the press says about them.'),
    'tree'             => $page(array(
        $page_header(lang('About the Band'), $artist['tagline'] . '.'),
        $story_band('mu-our-story', lang('Our story'), lang('From covers to our own songs'),
            array($artist['story'], $artist['based'] . '.'),
            $photo('about-story', 1200, 900), lang('A rehearsal room with amplifiers and a drum kit'), false, array(),
            lang('history, biography, how it started, who we are')),
        $el('section', 'py-4 border-top border-bottom bg-body-tertiary', lang('Figures'), array(
            $container(array($figure_row())),
        )),
        $el('section', 'py-5', lang('Members'), array(
            $container(array(
                $section_header(lang('Line-up'), lang('The Band')),
                $row($member_cols, '4'),
            )),
        ), $searchable('mu-members', lang('members, line-up, singer, guitarist, bassist, drummer'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Press Quotes'), array(
            $container(array(
                $section_header(lang('Press'), lang('What the press says'), lang('Press Kit'), '{{page:press}}'),
                $row($press_cols, '4'),
            )),
        )),
        $ask_band(lang('Want the band at your event?'), lang('Festivals, clubs and private events: send a booking enquiry or write to us.')),
    )),
);

// ── Videos ──────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'videos',
    'name'             => lang('videos'),
    'folder'           => 'public',
    'title'            => lang('Videos'),
    'meta_description' => lang('The band\'s music videos and live recordings.'),
    'tree'             => $page(array(
        $page_header(lang('Videos'), lang('Music videos and songs filmed live. Each opens on the video\'s own page.')),
        $el('section', 'py-5', lang('Videos'), array(
            $container(array(
                $row($video_cols, '4'),
            )),
        ), $searchable('mu-videos', lang('music video, clip, live video, watch'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Listen'), array(
            $container(array(
                $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3', lang('Text'), array(
                    $el('div', '', lang('Text'), array(
                        $heading('h2', lang('Listen everywhere'), 'pmu-display h3 mb-1'),
                        $para(lang('Every release is on the listening services; follow the band to hear new songs first.'), 'text-body-secondary mb-0'),
                    )),
                    $listen_links('d-flex flex-wrap gap-3 fs-4'),
                )),
            )),
        )),
    )),
);

// ── Questions ───────────────────────────────────────────────────────────
// Three accordions: shows and tickets, music and merch, booking the band.
// Each question is a search section of its own ($faq).
$faq_group = function ($id, $title, $items) use ($el, $heading, $faq) {
    $children = array();
    foreach ($items as $n => $q) $children[] = $faq($n + 1, $q[0], $q[1], $q[2], $id);
    return $el('div', 'mb-5', $title, array(
        $heading('h2', $title, 'pmu-display h3 mb-3'),
        $el('div', 'accordion', lang('Questions'), $children, array('id' => $id)),
    ));
};

$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Frequently Asked Questions'),
    'meta_description' => lang('Answers about tickets, show times, age limits, the records, the merch and booking the band.'),
    'tree'             => $page(array(
        $page_header(lang('Frequently Asked Questions'), lang('Shows, music and bookings. Not answered here? Write to us.')),
        $el('section', 'py-5', lang('Questions'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $faq_group('mu-faq-shows', lang('Shows and tickets'), array(
                            array(lang('Where do I buy tickets?'), lang('Each show on the tour page links to the venue or the ticket service that sells it. We do not sell tickets on this site.'), lang('tickets, buy, box office, sold out')),
                            array(lang('What time does the band go on?'), lang('Doors open at the time the show lists; the support band plays about half an hour later and we follow about an hour after the doors.'), lang('stage time, set time, doors, start')),
                            array(lang('Is there an age limit?'), lang('It is the venue\'s rule: most shows are open to all ages with an adult, some clubs are 18 and over. The ticket page says which.'), lang('age, children, kids, under 18')),
                            array(lang('Will you play near me?'), lang('New shows are added through the year. Join the mailing list and you hear about them before they go on sale.'), lang('new dates, city, announce, presale')),
                        )),
                        $faq_group('mu-faq-music', lang('Music and merch'), array(
                            array(lang('Where can I listen to the music?'), lang('Every release is on the listening services; the links are on each release\'s page and at the foot of every page.'), lang('streaming, listen, download, spotify')),
                            array(lang('Are the records on vinyl?'), lang('The albums come out on vinyl and cassette too. You find them at the merch stand after every show.'), lang('vinyl, record, cassette, physical')),
                            array(lang('Can I use a song in my video?'), lang('Write to the management address in the footer with what the video is and where it will be shown.'), lang('licence, sync, permission, cover version')),
                        )),
                        $faq_group('mu-faq-booking', lang('Booking the band'), array(
                            array(lang('How do I book the band?'), lang('Send a booking enquiry with the date, the city and the kind of event; we answer within a few days with an offer.'), lang('booking, festival, private event, hire, fee')),
                            array(lang('What do you need from the venue?'), lang('The technical rider and the stage plan are in the press kit; we send the full rider with our offer.'), lang('rider, stage plan, technical, sound')),
                            array(lang('Do you give interviews?'), lang('Yes: write to the press address; photos, logos and the biography are in the press kit.'), lang('interview, press, journalist, photos')),
                        )),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
    )),
);

// The home page's lists: its next shows (home_shows) and releases
// (home_releases) come with the releases and shows, its news (home_posts)
// with the standard pages; this part places no widget of its own.
$public_widgets = array();

return array('pages' => $public_pages, 'widgets' => $public_widgets);
