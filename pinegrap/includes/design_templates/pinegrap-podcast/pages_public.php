<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Podcast Network", its public pages: home, about the
 * network, the hosts, how to listen and the questions page. Included after
 * parts.php in the same scope (its builders and the network's data);
 * returns array('pages' => ..., 'widgets' => ...).
 *
 * Every figure comes from parts.php ($network, $hosts, $podcasts,
 * $episodes, $apps, $audience, $tiers), so a change made there reaches
 * every page here. No page here links a shop page: the template stands on
 * a site without the shop.
 *
 * The home opens with the Podcast: Episode Hero with Chapters block, the
 * hosts are the Team: Profiles block, the listening page reads along on
 * the Podcast: Transcript block and the figures are the Stats: Counter
 * block; sections rise or fade in as they come into view.
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
// visitor may search for that it does not say itself. The words and the
// picture rise in turn on view.
$story_band = function ($id, $eyebrow, $title, $texts, $picture, $alt, $flip = false, $more = array(), $topics = '', $class = 'py-5')
    use ($el, $heading, $para, $container, $row, $col, $rise, $framed, $searchable) {
    $words = array(
        $para($eyebrow, 'ppd-eyebrow text-uppercase fw-semibold text-primary mb-2'),
        $heading('h2', $title, 'ppd-display display-6 mb-3'),
    );
    $last = count($texts) - 1;
    foreach (array_values($texts) as $n => $t) {
        $words[] = $para($t, 'text-body-secondary ' . ($n === $last && empty($more) ? 'mb-0' : 'mb-3'));
    }
    return $el('section', $class, $title, array(
        $container(array(
            $rise($row(array(
                $col(array_merge($words, $more), '', '6', '', '12', $flip ? 'order-lg-last' : ''),
                $col(array($framed($picture, $alt)), '', '6'),
            ), '4', '', 'g-lg-5 align-items-center'), 150),
        )),
    ), $searchable($id, $topics));
};

// The network in four figures, the Stats: Counter block: a large number
// over its label, two to a row on a phone; the figures rise in turn on
// view.
$figures_band = function () use ($el, $heading, $para, $container, $row, $col, $rise, $audience) {
    $cols = array();
    foreach ($audience as $f) {
        $cols[] = $col(array(
            $heading('div', $f['value'], 'ppd-display display-4 fw-bold text-primary mb-2'),
            $para($f['label'], 'text-body-secondary fw-medium mb-0'),
        ), '3', '', '', '6');
    }
    return $el('section', 'py-5 border-top border-bottom', lang('Stats (Counter)'), array(
        $container(array($rise($row($cols, '4', '', 'text-center'), 120))),
    ));
};

// A band with a sentence and one or two buttons; it fades in on view.
$ask_band = function ($title, $text, $buttons) use ($el, $heading, $para, $container, $fx, $fade_in) {
    return $el('section', 'py-5', $title, array(
        $container(array(
            $fx($el('div', 'd-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3', lang('Text'), array(
                $el('div', '', lang('Text'), array(
                    $heading('h2', $title, 'ppd-display h3 mb-1'),
                    $para($text, 'text-body-secondary mb-0'),
                )),
                $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), $buttons),
            )), $fade_in),
        )),
    ));
};

// The supporters' call: what a supporter gets, with the way to the
// support page (which stands with or without the shop). The words and the
// card rise in turn on view.
$support_band = function () use ($el, $heading, $para, $link, $container, $row, $col, $rise, $check_list, $tiers) {
    $perks = array();
    foreach ($tiers as $t) $perks[] = $t['perks'][count($t['perks']) - 1];
    return $el('section', 'py-5 bg-body-tertiary', lang('Support the Network'), array(
        $container(array(
            $rise($row(array(
                $col(array(
                    $para(lang('Listener supported'), 'ppd-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                    $heading('h2', lang('Keep the podcasts independent'), 'ppd-display display-6 mb-3'),
                    $para(lang('Supporters pay for most of what we make. In return they hear bonus episodes, ask the guests their own questions and come to the live recordings.'), 'text-body-secondary mb-4'),
                    $link(lang('Become a Supporter'), '{{page:support}}', 'btn btn-primary rounded-pill px-4'),
                ), '', '6'),
                $col(array(
                    $el('div', 'p-4 rounded-4 bg-body shadow-sm', lang('Card'), array($check_list($perks))),
                ), '', '6'),
            ), '4', '', 'g-lg-5 align-items-center'), 150),
        )),
    ));
};

// The cards of the shared data, in their order.
$host_cols = array();
foreach (array_keys($hosts) as $key) $host_cols[] = $host_card($key);
// The newest episode leads the home: the featured episode out most
// recently.
$lead_key = key($episodes);
foreach ($episodes as $key => $e) {
    if ($e['featured'] && (!$episodes[$lead_key]['featured'] || $e['days'] < $episodes[$lead_key]['days'])) $lead_key = $key;
}
$lead = $episodes[$lead_key];
$lead_podcast = $podcasts[$lead['podcast']];
$lead_host = $hosts[$lead_podcast['host']]['name'];

// ── The blocks ──────────────────────────────────────────────────────────
// The trees below draw the palette's blocks (Podcast: Episode Hero with
// Chapters and Transcript, Team: Profiles) with the network's data: the
// same structure, classes and interactions, so the rules the template
// ships in User Styles style them as they style a dropped block. Headings
// and eyebrows wear the look's ppd- classes.

// The small spaced line above a title, in the theme's darker shade: small
// text in the plain theme colour is under 4.5:1 on a dark band.
$eyebrow = function ($text, $class = 'mb-2') use ($para) {
    return $para($text, 'ppd-eyebrow small text-uppercase fw-semibold text-primary-emphasis ' . $class);
};

// The home's way in, the Podcast: Episode Hero with Chapters block on a
// dark band: the newest episode's cover grows in and its sound wave slides
// in under it, its podcast, season, length, title and the ways to listen
// rise beside it; under them its chapters come in one by one and the
// guest card fades in. Listen Now opens the episodes, where it is the
// first: a page cannot address a record that is only made when the
// template opens.
$episode_hero = function () use ($el, $heading, $para, $link, $image, $span, $icon, $container, $row, $col, $fx, $at, $attr, $searchable,
    $query, $eyebrow, $lead_key, $lead, $lead_podcast, $lead_host, $categories, $apps, $photo, $portrait, $episode_number_text, $length_text) {
    $fact = function ($icon_name, $node) use ($el, $icon) {
        return $el('div', 'd-inline-flex align-items-center gap-2', lang('Detail'), array($icon($icon_name, 'text-primary-emphasis'), $node));
    };
    // The icons keep together, so on a phone they wrap under the line as one
    // row instead of leaving the last one alone.
    $app_links = array();
    foreach ($apps as $a) {
        $app_links[] = $el('a', 'btn border link-body-emphasis d-inline-flex align-items-center justify-content-center px-2', $a['name'],
            array($icon($a['icon'])), array('href' => '#', '_attrs' => array($attr('aria-label', sprintf(lang('Listen on %s'), $a['name'])))));
    }
    $cover = $fx($el('div', 'pg-podcast-cover', lang('Cover'), array(
        $image($photo('cover-' . $lead['podcast'], 1000, 1000), $lead_podcast['title'], 'rounded-4 shadow-lg'),
        $fx($el('div', 'pg-podcast-wave', lang('Sound Wave'), array(), array('_attrs' => array($attr('aria-hidden', 'true')))),
            array('effect' => 'slide-right', 'trigger' => 'load', 'duration' => 1200, 'delay' => 500, 'distance' => 60)),
    )), array('effect' => 'scale', 'trigger' => 'load', 'duration' => 900));
    $category = $categories[$lead_podcast['category']]['title'];
    $words = $fx($el('div', '', lang('Episode'), array(
        $eyebrow(lang('New episode'), 'mb-3'),
        $el('div', 'd-flex flex-wrap gap-3 small text-body-secondary mb-3', lang('Details'), array(
            $fact('bi-mic', $link($lead_podcast['title'], '{{page:podcasts}}?' . $query(array('category' => $category)), 'fw-semibold link-body-emphasis link-offset-2')),
            $fact('bi-collection-play', $span($episode_number_text($lead_key), '', lang('Episode Number'))),
            $fact('bi-clock', $el('time', '', lang('Length'), array(), array('text' => $length_text($lead_key),
                '_attrs' => array($attr('datetime', 'PT' . $lead['minutes'] . 'M'))))),
        )),
        $heading('h1', $lead['title'], 'ppd-display display-5 fw-bold mb-3'),
        $para($lead['summary'], 'lead text-body-secondary mb-4'),
        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
            $link(lang('Listen Now'), '{{page:episodes}}', 'btn btn-primary btn-lg px-4'),
            $link(lang('All Podcasts'), '{{page:podcasts}}', 'btn btn-lg border px-4 link-body-emphasis'),
        )),
        $el('div', 'd-flex flex-wrap align-items-center gap-2 mt-4', lang('Podcast Apps'), array(
            $para(lang('Also in your podcast app'), 'small text-body-secondary mb-0 me-1'),
            $el('div', 'd-flex gap-2', lang('Apps'), $app_links),
        )),
    )), array('effect' => 'slide-up', 'trigger' => 'load', 'duration' => 700, 'delay' => 150));

    $chapters = array();
    foreach ($lead['chapters'] as $c) $chapters[] = $el('li', '', $c[1], array($at($c[0]), $span($c[1], '', lang('Text'))));
    $below = array($col(array(
        $heading('h2', lang('Chapters'), 'ppd-display h5 fw-bold mb-3'),
        $fx($el('ol', 'pg-podcast-chapters', lang('Chapter List'), $chapters),
            array('effect' => 'slide-up', 'trigger' => 'view', 'duration' => 600, 'stagger' => 100)),
    ), '', '7'));
    if (isset($lead['guest'])) {
        $g = $lead['guest'];
        $below[] = $col(array(
            $fx($el('div', 'p-4 rounded-4 bg-body-tertiary', lang('Guest Card'), array(
                $heading('h2', lang('Our guest'), 'ppd-display h5 fw-bold mb-3'),
                $el('div', 'd-flex align-items-center gap-3 mb-3', lang('Name'), array(
                    $image($portrait($g['name'], 96), $g['name'], 'rounded-circle object-fit-cover flex-shrink-0',
                        array('width' => '96', 'height' => '96', '_attrs' => array($attr('loading', 'lazy')))),
                    $el('div', '', lang('Name'), array(
                        $para($g['name'], 'fw-semibold mb-0'),
                        $para($g['role'], 'small text-body-secondary mb-0'),
                    )),
                )),
                $el('blockquote', 'border-start border-3 border-primary ps-3 mb-3', lang('Quote'), array($para($g['quote'], 'fst-italic mb-0'))),
                $para(sprintf(lang('Hosted by %s'), $lead_host), 'small text-body-secondary mb-0'),
            )), array('effect' => 'fade', 'trigger' => 'view', 'duration' => 800, 'delay' => 200)),
        ), '', '5');
    }
    return $el('section', 'ppd-hero py-5 bg-body text-body overflow-hidden', lang('Podcast (Episode Hero)'), array(
        $container(array(
            $row(array(
                $col(array($cover), '', '5'),
                $col(array($words), '', '7'),
            ), '4', '', 'g-lg-5 align-items-center'),
            $row($below, '4', '', 'g-lg-5 mt-4 mt-lg-5'),
        )),
    ), array_merge($searchable('pd-home-episode', lang('latest, new episode, listen, audio')),
        array('_attrs' => array($attr('data-bs-theme', 'dark')))));
};

// A part of the newest episode written out, the Podcast: Transcript block:
// beside the words on reading along, the episode it is from and its two
// speakers, the chapter and its timed turns rise one by one on view. The
// guest's name is in the theme's shade, in the list and in the turns.
$transcript_band = function () use ($el, $heading, $para, $link, $span, $icon, $container, $row, $col, $fx, $at, $searchable,
    $eyebrow, $lead, $lead_host) {
    $guest = $lead['guest']['name'];
    $chapter = $lead['chapters'][$lead['script']['chapter']];
    $speaker = function ($icon_name, $text, $class) use ($el, $icon, $span) {
        return $el('li', 'd-flex align-items-center gap-2 mb-2', $text, array($icon($icon_name, $class), $span($text, '', lang('Text'))));
    };
    $turns = array($el('div', 'pg-podcast-turn', lang('Chapter'), array(
        $at($chapter[0]),
        $heading('h3', $chapter[1], 'h6 fw-bold text-uppercase mb-0'),
    )));
    foreach ($lead['script']['turns'] as $t) {
        $is_guest = $t[1] === 'guest';
        $who = $is_guest ? $guest : $lead_host;
        $turns[] = $el('div', 'pg-podcast-turn', $who, array(
            $at($t[0]),
            $el('div', '', lang('Words'), array(
                $para($who, 'fw-semibold mb-1 ' . ($is_guest ? 'text-primary-emphasis' : 'text-body-emphasis')),
                $para($t[2], 'mb-0'),
            )),
        ));
    }
    $words = $fx($el('div', '', lang('Text'), array(
        $eyebrow(lang('Read along')),
        $heading('h2', lang('Transcript'), 'ppd-display display-6 fw-bold mb-3'),
        $para(lang('Every episode is written out in full, for listeners who would rather read, or cannot hear it.'), 'text-body-secondary mb-4'),
        $para(lang('In this part'), 'small fw-semibold text-body-emphasis mb-1'),
        $para(sprintf(lang('From the episode %s.'), $lead['title']), 'small text-body-secondary mb-2'),
        $el('ul', 'list-unstyled small mb-4', lang('Speakers'), array(
            $speaker('bi-mic-fill', sprintf(lang('%s, host'), $lead_host), 'text-body-emphasis'),
            $speaker('bi-person-fill', sprintf(lang('%s, guest'), $guest), 'text-primary-emphasis'),
        )),
        $link(lang('Read the Full Transcript'), '{{page:episodes}}', 'btn btn-primary px-4'),
    )), array('effect' => 'slide-up', 'trigger' => 'view', 'duration' => 700));
    $script = $el('div', 'p-4 p-lg-5 rounded-4 border', lang('Transcript'), array(
        $fx($el('div', 'pg-podcast-script', lang('Turns'), $turns),
            array('effect' => 'slide-up', 'trigger' => 'view', 'duration' => 600, 'delay' => 200, 'stagger' => 100)),
    ));
    return $el('section', 'py-5 border-top', lang('Podcast (Transcript)'), array(
        $container(array(
            $row(array(
                $col(array($words), '', '4'),
                $col(array($script), '', '8'),
            ), '4', '', 'g-lg-5'),
        )),
    ), $searchable('pd-listen-transcript', lang('transcript, text, deaf, hard of hearing, accessibility')));
};

// The hosts as the Team: Profiles block: a card each with the portrait, the
// name, the podcast they host, a line about them, the podcast's category
// and day as tags, and the way to the podcast's category; the cards rise
// in turn on view.
$host_podcast = array();
foreach ($podcasts as $p) $host_podcast[$p['host']] = $p;
$host_profiles = function () use ($el, $heading, $para, $link, $image, $span, $container, $row, $col, $rise, $attr, $searchable, $query,
    $hosts, $host_podcast, $categories, $portrait) {
    $cards = array();
    foreach ($hosts as $key => $h) {
        $p = $host_podcast[$key];
        $category = $categories[$p['category']]['title'];
        $cards[] = $col(array(
            $el('article', 'card h-100 border-0 shadow-sm', lang('Profile Card'), array(
                $el('div', 'card-body d-flex flex-column flex-sm-row gap-4 p-4', lang('Profile Body'), array(
                    $image($portrait($h['name'], 240), $h['name'], 'rounded-4 object-fit-cover flex-shrink-0 align-self-start',
                        array('width' => '112', 'height' => '112', '_attrs' => array($attr('loading', 'lazy')))),
                    $el('div', 'flex-grow-1', lang('Profile Copy'), array(
                        $heading('h3', $h['name'], 'ppd-display h5 fw-bold mb-1'),
                        $para($h['role'], 'text-primary-emphasis fw-semibold small mb-2'),
                        $para($h['bio'], 'small text-body-secondary mb-3'),
                        $el('div', 'd-flex flex-wrap gap-2 mb-3', lang('Tags'), array(
                            $span($category, 'badge rounded-pill bg-body-tertiary text-body border', lang('Category')),
                            $span($p['schedule'], 'badge rounded-pill bg-body-tertiary text-body border text-wrap text-start', lang('Schedule')),
                        )),
                        $link(lang('Hear the Podcast'), '{{page:podcasts}}?' . $query(array('category' => $category)), 'btn btn-primary btn-sm'),
                    )),
                )),
            )),
        ), '', '6');
    }
    return $el('section', 'py-5', lang('Team (Profiles)'), array(
        $container(array($rise($row($cards, '4'), 120))),
    ), $searchable('pd-hosts', lang('hosts, presenters, interviewers, team')));
};

// ── Home ────────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('Independent podcasts and their newest episodes, the hosts behind them and the way to listen, support or sponsor.'),
    'tree'             => $page(array(
        // The way in, on a dark band: the newest episode with its cover,
        // the ways to listen, its chapters and its guest.
        $episode_hero(),

        // The newest episodes of every podcast, read from the Episodes
        // collection.
        $el('section', 'py-5', lang('Latest Episodes'), array(
            $container(array(
                $fx($section_header(lang('Just out'), lang('Latest episodes'), lang('All Episodes'), '{{page:episodes}}'), $fade_in),
                $widget('home_episodes'),
            )),
        )),

        // The network in figures, between the episodes and the podcasts.
        $figures_band(),

        // The featured podcasts, read from the Podcasts collection.
        $el('section', 'py-5 bg-body-tertiary', lang('Podcasts'), array(
            $container(array(
                $fx($section_header(lang('On the network'), lang('Our podcasts'), lang('All Podcasts'), '{{page:podcasts}}'), $fade_in),
                $widget('home_podcasts'),
            )),
        )),

        $support_band(),

        $el('section', 'py-5', lang('From the News'), array(
            $container(array(
                $fx($section_header(lang('News'), lang('From the studio'), lang('All News'), '{{page:blog}}'), $fade_in),
                $widget('home_posts'),
            )),
        )),

        $ask_band(lang('Reach our listeners'), lang('Sponsor an episode or a whole season of a podcast that fits your brand.'), array(
            $link(lang('Sponsorship'), '{{page:sponsor}}', 'btn btn-primary rounded-pill px-4 text-nowrap'),
        )),
    )),
);

// ── About ───────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About the Network'),
    'meta_description' => lang('How the network began, who makes the podcasts and how listeners and sponsors pay for them.'),
    'tree'             => $page(array(
        $page_header(lang('About the Network'), $network['tagline'] . '.'),
        $story_band('pd-our-story', lang('Our story'), lang('From a kitchen table to a studio'),
            array($network['story'], $network['based'] . '.'),
            $photo('about-story', 1200, 900), lang('A kitchen table with a microphone and two cups of tea'), false, array(),
            lang('history, how it started, who we are, studio')),
        $figures_band(),
        $story_band('pd-how-we-work', lang('How we work'), lang('Paid for by listeners first'),
            array(
                lang('Every podcast is recorded, edited and mixed in our own studio. We choose the guests and the stories; nobody pays to be on a show.'),
                lang('Supporters pay for most of the work. Sponsors pay for the rest, and only sponsors that fit a podcast are read on it, always marked as a sponsor.'),
            ),
            $photo('about-edit', 1200, 900), lang('A producer editing a recording on two screens'), true,
            array($link(lang('Support Us'), '{{page:support}}', 'btn btn-primary rounded-pill px-4')),
            lang('independent, editorial, advertising, funding, sponsors')),
        $el('section', 'py-5 bg-body-tertiary', lang('Hosts'), array(
            $container(array(
                $section_header(lang('Behind the microphone'), lang('The hosts'), lang('Meet the Hosts'), '{{page:hosts}}'),
                $row($host_cols, '4'),
            )),
        ), $searchable('pd-about-hosts', lang('hosts, presenters, team'))),
        $ask_band(lang('Questions for the studio?'), lang('Write to us about the podcasts, the press or working together.'), array(
            $link(lang('Contact Us'), '{{page:contact}}', 'btn btn-primary rounded-pill px-4 text-nowrap'),
        )),
    )),
);

// ── Hosts ───────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'hosts',
    'name'             => lang('hosts'),
    'folder'           => 'public',
    'title'            => lang('Hosts'),
    'meta_description' => lang('The people behind the microphones of the network\'s podcasts.'),
    'tree'             => $page(array(
        $page_header(lang('Hosts'), lang('Each podcast has its own host, and every host has a story of how they got here.')),
        $host_profiles(),
        $story_band('pd-be-a-guest', lang('Guests on the podcasts'), lang('Have a story worth telling?'),
            array(lang('Our hosts look for guests with something to say: a craft, a business, a night that changed things. Tell us about yourself or someone you know.')),
            $photo('hosts-guest', 1200, 900), lang('A guest laughing in front of a microphone'), true,
            array($link(lang('Be a Guest'), '{{page:guest_pitch}}', 'btn btn-primary rounded-pill px-4')),
            lang('guest, interview, pitch, appear on the podcast'), 'py-5 bg-body-tertiary'),
    )),
);

// ── How to listen ───────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'listen',
    'name'             => lang('how-to-listen'),
    'folder'           => 'public',
    'title'            => lang('How to Listen'),
    'meta_description' => lang('Follow the podcasts in your podcast app, play them here on the site, or get each new episode by e-mail.'),
    'tree'             => $page(array(
        $page_header(lang('How to Listen'), $network['feed'] . '.'),
        $el('section', 'py-5', lang('Podcast Apps'), array(
            $container(array(
                $rise($row(array(
                    $col(array(
                        $heading('h2', lang('In your podcast app'), 'ppd-display h3 mb-3'),
                        $el('ol', 'list-unstyled mb-4', lang('Steps'), array(
                            $step(1, lang('Open your app'), lang('Any podcast app works: the ones below and every other app that reads a feed.')),
                            $step(2, lang('Search for the podcast'), lang('Type the podcast\'s name, or open its page here and press the app\'s button.')),
                            $step(3, lang('Press Follow'), lang('New episodes arrive by themselves, usually within an hour of coming out.')),
                        )),
                        $app_links('d-flex flex-wrap gap-4 fs-3'),
                    ), '', '6'),
                    $col(array(
                        $heading('h2', lang('Here on the site'), 'ppd-display h3 mb-3'),
                        $para(lang('Every episode has its own page with the player, the notes, the chapters and the guests. Nothing to install.'), 'text-body-secondary mb-3'),
                        $link(lang('Latest Episodes'), '{{page:episodes}}', 'btn btn-primary rounded-pill px-4 mb-4'),
                        $heading('h2', lang('By e-mail'), 'ppd-display h3 mb-3'),
                        $para(lang('Get each new episode in your inbox with its notes, once a week at most.'), 'text-body-secondary mb-3'),
                        $link(lang('Newsletter'), '{{page:newsletter}}', 'btn border rounded-pill px-4 link-body-emphasis'),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'), 150),
            )),
        ), $searchable('pd-listen', lang('subscribe, follow, apple podcasts, spotify, rss, feed, app'))),
        // Reading along: a part of the newest episode written out.
        $transcript_band(),
        $support_band(),
    )),
);

// ── Questions ───────────────────────────────────────────────────────────
// Three accordions: listening, supporting, sponsors and guests. Each
// question is a search section of its own ($faq).
$faq_group = function ($id, $title, $items) use ($el, $heading, $faq) {
    $children = array();
    foreach ($items as $n => $q) $children[] = $faq($n + 1, $q[0], $q[1], $q[2], $id);
    return $el('div', 'mb-5', $title, array(
        $heading('h2', $title, 'ppd-display h3 mb-3'),
        $el('div', 'accordion', lang('Questions'), $children, array('id' => $id)),
    ));
};

$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Frequently Asked Questions'),
    'meta_description' => lang('Answers about listening, supporting the network, sponsorship and being a guest.'),
    'tree'             => $page(array(
        $page_header(lang('Frequently Asked Questions'), lang('Listening, supporting, sponsoring. Not answered here? Write to us.')),
        $el('section', 'py-5', lang('Questions'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $faq_group('pd-faq-listen', lang('Listening to the podcasts'), array(
                            array(lang('Are the podcasts free?'), lang('Yes. Every episode is free in the podcast apps and on this site; supporters also hear the bonus episodes.'), lang('free, cost, price, paywall')),
                            array(lang('When do new episodes come out?'), lang('Each podcast has its own day, written on its page. Follow it in your app and new episodes arrive by themselves.'), lang('schedule, release day, new episode, weekly')),
                            array(lang('Is there a transcript?'), lang('Most episodes have one on their page, under the notes. If one is missing, write to us and we add it.'), lang('transcript, text, deaf, hard of hearing, accessibility')),
                        )),
                        $faq_group('pd-faq-support', lang('Supporting the network'), array(
                            array(lang('What do supporters get?'), lang('Bonus episodes, every episode without sponsor mentions and, from the higher tiers, questions for the guests and seats at the live recordings.'), lang('membership, perks, bonus, patron')),
                            array(lang('Can I stop my support?'), lang('Yes, at any time from your account. Your support runs to the end of the month you have paid for.'), lang('cancel, stop, end membership, refund')),
                            array(lang('Can I give support as a gift?'), lang('Yes: a year of support can be given to someone else. Write to us if you cannot find it.'), lang('gift, present, give')),
                        )),
                        $faq_group('pd-faq-work', lang('Sponsors and guests'), array(
                            array(lang('How do I sponsor an episode?'), lang('Send a sponsorship enquiry with the podcast and the dates you have in mind; we answer within a few days with a proposal.'), lang('advertise, sponsor, ads, media kit, rates')),
                            array(lang('Do you read every sponsor?'), lang('No. A host reads only sponsors that fit the podcast, and every mention is marked as a sponsor.'), lang('editorial, independence, advertising policy')),
                            array(lang('How do I become a guest?'), lang('Send a guest pitch about yourself or someone you know. The hosts read every pitch and write back to the ones they invite.'), lang('guest, interview, pitch, appear')),
                        )),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
    )),
);

// The home page's lists: its episodes (home_episodes) and podcasts
// (home_podcasts) come with the collections, its news (home_posts) with
// the standard pages; this part places no widget of its own.
$public_widgets = array();

return array('pages' => $public_pages, 'widgets' => $public_widgets);
