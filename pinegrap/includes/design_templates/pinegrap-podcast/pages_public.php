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
        $para($eyebrow, 'ppd-eyebrow text-uppercase fw-semibold text-primary mb-2'),
        $heading('h2', $title, 'ppd-display display-6 mb-3'),
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

// The network in four figures, in one row on a tinted band.
$figures_band = function () use ($el, $container, $row, $figure_tile, $audience) {
    $tiles = array();
    foreach (array_keys($audience) as $n) $tiles[] = $figure_tile($n);
    return $el('section', 'py-5 border-top border-bottom bg-body-tertiary', lang('Figures'), array(
        $container(array($row($tiles, '4'))),
    ));
};

// A band with a sentence and one or two buttons.
$ask_band = function ($title, $text, $buttons) use ($el, $heading, $para, $container) {
    return $el('section', 'py-5', $title, array(
        $container(array(
            $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3', lang('Text'), array(
                $el('div', '', lang('Text'), array(
                    $heading('h2', $title, 'ppd-display h3 mb-1'),
                    $para($text, 'text-body-secondary mb-0'),
                )),
                $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), $buttons),
            )),
        )),
    ));
};

// The supporters' call: what a supporter gets, with the way to the
// support page (which stands with or without the shop).
$support_band = function () use ($el, $heading, $para, $link, $container, $row, $col, $check_list, $tiers) {
    $perks = array();
    foreach ($tiers as $t) $perks[] = $t['perks'][count($t['perks']) - 1];
    return $el('section', 'py-5 bg-body-tertiary', lang('Support the Network'), array(
        $container(array(
            $row(array(
                $col(array(
                    $para(lang('Listener supported'), 'ppd-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                    $heading('h2', lang('Keep the podcasts independent'), 'ppd-display display-6 mb-3'),
                    $para(lang('Supporters pay for most of what we make. In return they hear bonus episodes, ask the guests their own questions and come to the live recordings.'), 'text-body-secondary mb-4'),
                    $link(lang('Become a Supporter'), '{{page:support}}', 'btn btn-primary rounded-pill px-4'),
                ), '', '6'),
                $col(array(
                    $el('div', 'p-4 rounded-4 bg-body shadow-sm', lang('Card'), array($check_list($perks))),
                ), '', '6'),
            ), '4', '', 'g-lg-5 align-items-center'),
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

// ── Home ────────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('Independent podcasts and their newest episodes, the hosts behind them and the way to listen, support or sponsor.'),
    'tree'             => $page(array(
        // The way in, on a dark band: the newest episode, the two ways on,
        // the apps and the cover of its podcast.
        $el('section', 'ppd-hero py-5 bg-body text-body overflow-hidden', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(sprintf(lang('New on %s'), $podcasts[$lead['podcast']]['title']), 'ppd-eyebrow text-uppercase fw-semibold text-primary mb-3'),
                        $heading('h1', $lead['title'], 'ppd-display display-4 mb-3'),
                        $para($lead['summary'], 'lead mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2 mb-4', lang('Buttons'), array(
                            $link(lang('Latest Episodes'), '{{page:episodes}}', 'btn btn-primary btn-lg rounded-pill px-4'),
                            $link(lang('All Podcasts'), '{{page:podcasts}}', 'btn btn-outline-light btn-lg rounded-pill px-4'),
                        )),
                        $app_links('d-flex flex-wrap gap-3'),
                    ), '', '7'),
                    $col(array(
                        $framed($photo('cover-' . $lead['podcast'], 1000, 1000), $podcasts[$lead['podcast']]['title'], '1x1', false),
                    ), '', '5'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), array('_attrs' => array($attr('data-bs-theme', 'dark')))),

        // The newest episodes of every podcast, read from the Episodes
        // collection.
        $el('section', 'py-5', lang('Latest Episodes'), array(
            $container(array(
                $section_header(lang('Just out'), lang('Latest episodes'), lang('All Episodes'), '{{page:episodes}}'),
                $widget('home_episodes'),
            )),
        )),

        // The featured podcasts, read from the Podcasts collection.
        $el('section', 'py-5 bg-body-tertiary', lang('Podcasts'), array(
            $container(array(
                $section_header(lang('On the network'), lang('Our podcasts'), lang('All Podcasts'), '{{page:podcasts}}'),
                $widget('home_podcasts'),
            )),
        )),

        $figures_band(),

        $story_band('pd-home-network', lang('The Network'), lang('A small studio with big ears'),
            array($network['story']),
            $photo('home-studio', 1200, 900), lang('Two microphones on a wooden table in a small studio'), true,
            array($link(lang('Meet the Hosts'), '{{page:hosts}}', 'btn btn-outline-primary rounded-pill px-4')),
            lang('about, studio, who we are, team, producers')),

        $support_band(),

        $el('section', 'py-5', lang('From the News'), array(
            $container(array(
                $section_header(lang('News'), lang('From the studio'), lang('All News'), '{{page:blog}}'),
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
        $el('section', 'py-5', lang('Hosts'), array(
            $container(array(
                $row($host_cols, '4'),
            )),
        ), $searchable('pd-hosts', lang('hosts, presenters, interviewers, team'))),
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
                $row(array(
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
                        $link(lang('Newsletter'), '{{page:newsletter}}', 'btn btn-outline-primary rounded-pill px-4'),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('pd-listen', lang('subscribe, follow, apple podcasts, spotify, rss, feed, app'))),
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
