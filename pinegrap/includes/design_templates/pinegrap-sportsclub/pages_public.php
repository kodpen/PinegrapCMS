<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Sports Club", its public pages: home, the teams and a
 * page per team, the club and its honours, the ground, the youth academy,
 * the sponsors and the questions. Included after parts.php in the same
 * scope (its builders and the club's data); returns array('pages' => ...,
 * 'widgets' => ...).
 *
 * Every figure comes from parts.php ($club, $ground, $sports, $teams,
 * $competitions, $honours, $academy_points, $sponsors, $sponsor_tiers,
 * $sponsor_packages, $figures, $testimonials), so a change made there
 * reaches every page here. No page here links a shop page: the template
 * stands on a site without the shop; a player joins through the trial
 * request, a child through the academy registration.
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


// A picture in a frame of the given ratio, loaded lazily unless it is the
// first thing a page shows.
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
        $para($eyebrow, 'psp-eyebrow text-uppercase fw-semibold text-primary mb-2'),
        $heading('h2', $title, 'psp-display h2 mb-3'),
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

// A tile with an icon, a title and a line: the academy's values, the
// ground's ways in and the reasons to sponsor.
$feature_tile = function ($icon_name, $title, $text, $lg = '4', $more = array()) use ($el, $heading, $para, $col, $icon_tile) {
    return $col(array(
        $el('div', 'h-100 p-4 rounded-3 bg-body border shadow-sm', $title, array_merge(array(
            $icon_tile($icon_name, 'mb-3'),
            $heading('h3', $title, 'psp-display h5 mb-2'),
            $para($text, 'small text-body-secondary mb-0'),
        ), $more)),
    ), '6', $lg);
};

// The club in four figures, in one row.
$figure_row = function () use ($el, $para, $row, $col, $figures) {
    $cols = array();
    foreach ($figures as $f) {
        $cols[] = $col(array(
            $el('div', 'text-center', $f['label'], array(
                $para($f['value'], 'psp-display display-5 text-primary mb-1'),
                $para($f['label'], 'small text-uppercase fw-semibold text-body-secondary mb-0'),
            )),
        ), '3', '', '', '6');
    }
    return $row($cols, '4');
};

// The club's honours as a list: what was won and how often.
$honour_list = function () use ($el, $icon, $span, $honours) {
    $items = array();
    foreach ($honours as $h) {
        $items[] = $el('li', 'd-flex align-items-center gap-3 py-3 border-bottom', $h['title'], array(
            $icon($h['icon'], 'fs-4 text-primary'),
            $span($h['title'], 'flex-grow-1 fw-semibold', lang('Honour')),
            $span($h['times'], 'psp-score text-nowrap', lang('How Often')),
        ));
    }
    return $el('ul', 'list-unstyled mb-0', lang('Honours'), $items);
};

// The cards of the shared data, in their order.
$senior_cards = array();
$youth_cards = array();
foreach ($teams as $key => $t) {
    if ($t['youth']) $youth_cards[] = $team_card($key);
    else $senior_cards[] = $team_card($key);
}
$testimonial_cols = array();
foreach (array_keys($testimonials) as $n) $testimonial_cols[] = $testimonial_card($n);
$academy_tiles = array();
foreach ($academy_points as $a) $academy_tiles[] = $feature_tile($a['icon'], $a['title'], $a['text'], '3');
// The main partner and the gold partners, for the home's sponsor strip.
$top_sponsor_tiles = array();
foreach ($sponsors as $key => $s) {
    if ($s['tier'] !== 'friends') $top_sponsor_tiles[] = $sponsor_tile($key);
}

// ── Home ────────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('A community football and basketball club with teams for every age, a youth academy, weekend matches and a clubhouse open to all.'),
    'tree'             => $page(array(
        // The way in: the club in a sentence, the two ways on and a photo
        // of a match day.
        $el('section', 'psp-hero py-5 overflow-hidden', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para($ground['name'], 'psp-eyebrow text-uppercase fw-semibold text-primary mb-3'),
                        $heading('h1', lang('One club, every age'), 'psp-display display-3 mb-3'),
                        $para($club['tagline'] . '.', 'lead text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('See the Fixtures'), '{{page:fixtures}}', 'btn btn-primary btn-lg px-4'),
                            $link(lang('Ask for a Trial'), '{{page:trial}}', 'btn btn-outline-primary btn-lg px-4'),
                        )),
                    ), '', '5'),
                    $col(array(
                        $framed($photo('hero', 1400, 1050), lang('Players challenging for the ball under the floodlights at Mill Lane'), '4x3', false),
                    ), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        $el('section', 'py-4 border-top border-bottom bg-body-tertiary', lang('Figures'), array(
            $container(array($figure_row())),
        )),

        // The next matches and the last results, read from the Matches
        // collection.
        $el('section', 'py-5', lang('Fixtures'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $section_header(lang('Coming up'), lang('Next matches'), lang('All fixtures'), '{{page:fixtures}}'),
                        $widget('home_fixtures'),
                    ), '', '6'),
                    $col(array(
                        $section_header(lang('Final whistle'), lang('Latest results'), lang('All results'), '{{page:results}}'),
                        $widget('home_results'),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('sp-home-matches', lang('fixtures, results, kick-off, score, next match'))),

        $el('section', 'py-5 bg-body-tertiary', lang('Our Teams'), array(
            $container(array(
                $section_header(lang('Play for us'), lang('A team for every age and level'), lang('All teams'), '{{page:teams}}'),
                $row(array_merge($senior_cards, $youth_cards), '4'),
            )),
        ), $searchable('sp-home-teams', lang('teams, men, women, veterans, youth, basketball, training times'))),

        $story_band('sp-home-academy', lang('Youth Academy'), lang('Where the next first team starts'),
            array(lang('Boys and girls from five to sixteen train every week with qualified coaches, play in friendly leagues and learn to love the game.')),
            $photo('home-academy', 1200, 900), lang('Young players dribbling around cones on the all-weather pitch'), true,
            array($link(lang('About the academy'), '{{page:academy}}', 'btn btn-outline-primary px-4')),
            lang('children, kids, juniors, girls, boys, academy'), 'py-5'),

        $el('section', 'py-5 bg-body-tertiary', lang('Sponsors'), array(
            $container(array(
                $section_header(lang('Thank you'), lang('Our partners'), lang('All sponsors'), '{{page:sponsors}}'),
                $row($top_sponsor_tiles, '4'),
            )),
        )),

        // What players, parents and fans say: sample words for the club to
        // replace, and beside them its own Google reviews, which show once
        // Google Reviews is set up under Site Settings.
        $el('section', 'py-5', lang('Voices of the Club'), array(
            $container(array(
                $section_header(lang('Our people'), lang('What players and fans say')),
                $row($testimonial_cols, '4'),
                $widget('google_reviews'),
            )),
        )),

        $el('section', 'py-5 bg-body-tertiary', lang('Club News'), array(
            $container(array(
                $section_header(lang('Club News'), lang('Latest from the club'), lang('All news'), '{{page:blog}}'),
                $widget('home_posts'),
            )),
        )),
    )),
);

// ── Teams ───────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'teams',
    'name'             => lang('teams'),
    'folder'           => 'public',
    'title'            => lang('Our Teams'),
    'meta_description' => lang('Men\'s and women\'s football, veterans, youth teams from Under-8s to Under-16s and a basketball section: find your team.'),
    'tree'             => $page(array(
        $page_header(lang('Our Teams'), lang('Seven teams in two sports, from first kicks to Sunday league veterans. Every team welcomes new players.')),
        $el('section', 'py-5', lang('Senior Teams'), array(
            $container(array(
                $section_header(lang('Adults'), lang('Senior teams'), lang('Ask for a Trial'), '{{page:trial}}'),
                $row($senior_cards, '4'),
            )),
        ), $searchable('sp-senior-teams', lang('men, women, veterans, basketball, adults, training'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Academy Teams'), array(
            $container(array(
                $section_header(lang('Juniors'), lang('Academy teams'), lang('Youth Academy'), '{{page:academy}}'),
                $row($youth_cards, '4'),
            )),
        ), $searchable('sp-youth-teams', lang('children, kids, juniors, under 8, under 12, under 16'))),
        $el('section', 'py-5', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('How to join a team'), 'psp-display h3 mb-4'),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), array(
                            $step(1, lang('Choose a team'), lang('Pick the team for your age and level, or leave it to the coaches.')),
                            $step(2, lang('Ask for a trial'), lang('Send the short form; a coach writes back within a few days with a training night to try.')),
                            $step(3, lang('Come and train'), lang('Bring boots or trainers, shin pads and water. If you enjoy it, you are in.')),
                        )),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'p-4 p-lg-5 rounded-3 bg-primary-subtle', lang('Text'), array(
                            $heading('h2', lang('Registering a child?'), 'psp-display h4 mb-2'),
                            $para(lang('Children join through the youth academy. Register them for their age group and we invite them to a taster session.'), 'mb-3'),
                            $link(lang('Register a child'), '{{page:academy_register}}', 'btn btn-primary px-4'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('sp-join-team', lang('join, sign up, trial, new player, register'))),
    )),
);

// ── A page per team ─────────────────────────────────────────────────────
// The team's facts and coach, its squad (adult teams), its fixtures and
// results, and the way in: the trial request for an adult team, the
// academy registration for a youth team, each with the team chosen.
$team_page_names = array(
    'first'      => lang('mens-first-team'),
    'women'      => lang('womens-team'),
    'veterans'   => lang('veterans'),
    'u16'        => lang('under-16s'),
    'u12'        => lang('under-12s'),
    'u8'         => lang('under-8s'),
    'basketball' => lang('basketball'),
);
foreach ($teams as $key => $t) {
    $team_q = $query(array('team' => $t['title']));
    $buttons = array(
        $link(lang('Fixtures'), '{{page:fixtures}}?' . $team_q, 'btn btn-primary px-4'),
        $link(lang('Match Results'), '{{page:results}}?' . $team_q, 'btn btn-outline-primary px-4'),
    );
    if (!$t['youth']) $buttons[] = $link(lang('The Squad'), '{{page:squad}}?' . $team_q, 'btn btn-outline-primary px-4');
    $join = $t['youth']
        ? array(
            $heading('h2', lang('Join this group'), 'psp-display h4 mb-2'),
            $para(lang('Register your child for this age group. We invite them to a taster session before they commit to anything.'), 'mb-3'),
            $link(lang('Register a child'), '{{page:academy_register}}?' . $team_q, 'btn btn-primary px-4'),
        )
        : array(
            $heading('h2', lang('Want to play?'), 'psp-display h4 mb-2'),
            $para(lang('New players are welcome all season. Ask for a trial and the coach invites you to a training night.'), 'mb-3'),
            $link(lang('Ask for a Trial'), '{{page:trial}}?' . $team_q, 'btn btn-primary px-4'),
        );
    $public_pages[] = array(
        'key'              => 'team_' . $key,
        'name'             => $team_page_names[$key],
        'folder'           => 'public',
        'title'            => $t['title'],
        'meta_description' => $t['summary'],
        'tree'             => $page(array(
            $page_header($t['title'], $t['summary'], array(
                $el('div', 'd-flex flex-wrap gap-2 mt-3', lang('Buttons'), $buttons),
            )),
            $el('section', 'py-5', lang('About the Team'), array(
                $container(array(
                    $row(array(
                        $col(array(
                            $para($sports[$t['sport']]['title'], 'psp-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                            $heading('h2', lang('Training and matches'), 'psp-display h3 mb-3'),
                            $team_facts($key, 'list-unstyled mb-4'),
                            $el('p', 'd-flex align-items-center gap-2 mb-0', lang('Coach'), array(
                                $icon('bi-person-badge', 'text-primary'),
                                $span(lang('Coach:'), 'fw-semibold', lang('Label')),
                                $span($t['coach'], '', lang('Name')),
                            )),
                        ), '', '6'),
                        $col(array(
                            $el('div', 'p-4 p-lg-5 rounded-3 bg-primary-subtle', lang('Join'), $join),
                        ), '', '6'),
                    ), '4', '', 'g-lg-5 align-items-center'),
                )),
            ), $searchable('sp-team-' . $key, lang('training, times, coach, league, age group'))),
            $el('section', 'py-5 bg-body-tertiary', lang('Match Day'), array(
                $container(array(
                    $row(array(
                        $col(array(
                            $framed($photo('team-' . $key, 1200, 900), sprintf(lang('The %s at a home match'), $t['title'])),
                        ), '', '6'),
                        $col(array(
                            $heading('h2', lang('Come and watch'), 'psp-display h3 mb-3'),
                            $para(sprintf(lang('Home matches are played at %s. Entry is free for members and juniors, and the clubhouse opens an hour before kick-off.'), $ground['name']), 'text-body-secondary mb-3'),
                            $link(lang('Getting to the ground'), '{{page:ground}}', 'btn btn-outline-primary px-4'),
                        ), '', '6'),
                    ), '4', '', 'g-lg-5 align-items-center'),
                )),
            )),
        )),
    );
}

// ── The club ────────────────────────────────────────────────────────────

$club_values = array(
    array('icon' => 'bi-people', 'title' => lang('Run by volunteers'), 'text' => lang('Coaches, kit washers, groundskeepers and the committee give their evenings so everyone can play.')),
    array('icon' => 'bi-hand-thumbs-up', 'title' => lang('Respect on and off the pitch'), 'text' => lang('For referees, opponents and team-mates, whatever the score.')),
    array('icon' => 'bi-door-open', 'title' => lang('Open to everyone'), 'text' => lang('Any age, any level, any background: the clubhouse door is always open.')),
);
$club_value_tiles = array();
foreach ($club_values as $v) $club_value_tiles[] = $feature_tile($v['icon'], $v['title'], $v['text']);

$public_pages[] = array(
    'key'              => 'club',
    'name'             => lang('about-the-club'),
    'folder'           => 'public',
    'title'            => lang('About the Club'),
    'meta_description' => lang('Founded by railway workers on a borrowed field: the story of the club, its honours and the volunteers who run it.'),
    'tree'             => $page(array(
        $page_header(lang('About the Club'), $club['tagline'] . '.'),
        $story_band('sp-our-story', lang('Our story'), lang('From a borrowed field to a ground of our own'),
            array($club['story']),
            $photo('club', 1200, 900), lang('The clubhouse and the covered stand beside the main pitch'), false, array(),
            lang('history, founded, founders, committee, who we are')),
        $el('section', 'py-4 border-top border-bottom bg-body-tertiary', lang('Figures'), array(
            $container(array($figure_row())),
        )),
        $el('section', 'py-5', lang('Honours'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $section_header(lang('Trophy cabinet'), lang('Our honours')),
                        $honour_list(),
                    ), '', '8'),
                ), '', 'center'),
            )),
        ), $searchable('sp-honours', lang('trophies, titles, champions, cup, honours'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Our Values'), array(
            $container(array(
                $section_header(lang('What we stand for'), lang('How we run the club')),
                $row($club_value_tiles, '4'),
            )),
        ), $searchable('sp-values', lang('values, volunteers, respect, committee, community'))),
        $el('section', 'py-5', lang('Write to Us'), array(
            $container(array(
                $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3', lang('Text'), array(
                    $el('div', '', lang('Text'), array(
                        $heading('h2', lang('Want to help out?'), 'psp-display h4 mb-1'),
                        $para(lang('We always need volunteers on match days, at the academy and behind the bar. Write to the committee.'), 'text-body-secondary mb-0'),
                    )),
                    $link(lang('Contact Us'), '{{page:contact}}', 'btn btn-primary px-4 text-nowrap'),
                )),
            )),
        )),
    )),
);

// ── The ground ──────────────────────────────────────────────────────────

$ways_here = array(
    array('icon' => 'bi-train-front', 'title' => lang('By train'), 'text' => lang('Get off at the river station and walk ten minutes up Mill Lane; follow the floodlights.')),
    array('icon' => 'bi-bicycle', 'title' => lang('By bike'), 'text' => lang('Bike racks beside the clubhouse door, inside the gate.')),
    array('icon' => 'bi-car-front', 'title' => lang('By car'), 'text' => lang('Free parking behind the clubhouse; on big match days the overflow field opens next door.')),
);
$way_tiles = array();
foreach ($ways_here as $w) $way_tiles[] = $feature_tile($w['icon'], $w['title'], $w['text']);

$public_pages[] = array(
    'key'              => 'ground',
    'name'             => lang('the-ground'),
    'folder'           => 'public',
    'title'            => $ground['name'],
    'meta_description' => lang('The club\'s home ground: floodlit pitch, covered stand, sports hall and clubhouse, with directions by train, bike and car.'),
    'tree'             => $page(array(
        $page_header($ground['name'], $club['region'] . '.'),
        $el('section', 'py-5', lang('Address'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Find Us'), 'psp-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                        $heading('h2', $club['address'], 'psp-display h3 mb-3'),
                        $para($ground['capacity'] . '.', 'fw-semibold mb-2'),
                        $para($ground['text'], 'text-body-secondary mb-4'),
                        $el('ul', 'list-unstyled mb-4', lang('Contact Details'), array(
                            $el('li', 'd-flex gap-2 mb-2', lang('Phone'), array(
                                $icon('bi-telephone', 'text-primary'),
                                $link($club['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $club['phone']), 'link-body-emphasis text-decoration-none'),
                            )),
                            $el('li', 'd-flex gap-2 mb-0', lang('Email'), array(
                                $icon('bi-envelope', 'text-primary'),
                                $link($club['email'], 'mailto:' . $club['email'], 'link-body-emphasis text-decoration-none text-break'),
                            )),
                        )),
                        $link(lang('Open in maps'), 'https://www.openstreetmap.org/search?query=' . rawurlencode($club['address']), 'btn btn-outline-primary px-4', array($attr('rel', 'noopener')), array('target' => '_blank')),
                    ), '', '5'),
                    $col(array($framed($photo('ground', 1400, 1050), lang('The main pitch and the covered stand at Mill Lane'))), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('sp-address', lang('address, map, directions, stadium, pitch, where'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Facilities'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('At the ground'), 'psp-display h3 mb-3'),
                        $check_list($ground['points']),
                    ), '', '6'),
                    $col(array(
                        $heading('h2', lang('On match days'), 'psp-display h3 mb-3'),
                        $check_list(array(
                            lang('Gates open an hour before kick-off'),
                            lang('Pay at the gate, by card or cash; members and juniors come in free'),
                            lang('Hot food and drinks in the clubhouse'),
                            lang('Dogs on a lead are welcome on the terrace'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('sp-ground-facilities', lang('entry, tickets, gate, food, parking, accessibility, wheelchair'))),
        $el('section', 'py-5', lang('How to Get Here'), array(
            $container(array(
                $section_header(lang('On the way'), lang('How to get here')),
                $row($way_tiles, '4'),
            )),
        ), $searchable('sp-travel', lang('train, parking, bike, car, transport'))),
    )),
);

// ── Youth academy ───────────────────────────────────────────────────────
// What the academy stands for, its groups (each opening the registration
// with the group chosen) and how a child joins.
$academy_group_cards = array();
foreach ($teams as $key => $t) {
    if (!$t['youth']) continue;
    $academy_group_cards[] = $col(array(
        $el('article', 'card h-100 border-0 shadow-sm psp-lift', lang('Team Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile($t['icon'], 'align-self-start mb-3'),
                $heading('h3', $t['title'], 'psp-display h4 mb-2'),
                $para($t['summary'], 'small text-body-secondary mb-3'),
                $team_facts($key, 'list-unstyled small text-body-secondary mb-4'),
                $el('div', 'd-flex flex-wrap gap-2 mt-auto', lang('Buttons'), array(
                    $link(lang('Register a child'), '{{page:academy_register}}?' . $query(array('team' => $t['title'])), 'btn btn-primary'),
                    $link(lang('More'), '{{page:team_' . $key . '}}', 'btn btn-outline-primary'),
                )),
            )),
        )),
    ), '6', '4');
}

$public_pages[] = array(
    'key'              => 'academy',
    'name'             => lang('youth-academy'),
    'folder'           => 'public',
    'title'            => lang('Youth Academy'),
    'meta_description' => lang('Football for boys and girls from five to sixteen, with qualified coaches, weekly training and a taster session before joining.'),
    'tree'             => $page(array(
        $page_header(lang('Youth Academy'), lang('Football for boys and girls from five to sixteen: weekly training with qualified coaches, friendly matches and lots of fun.'), array(
            $link(lang('Register a child'), '{{page:academy_register}}', 'btn btn-primary px-4 mt-3'),
        )),
        $el('section', 'py-5', lang('Our Values'), array(
            $container(array(
                $section_header(lang('How we coach'), lang('What the academy stands for')),
                $row($academy_tiles, '4'),
            )),
        ), $searchable('sp-academy-values', lang('coaching, safeguarding, girls, fun, development'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Academy Teams'), array(
            $container(array(
                $section_header(lang('Age groups'), lang('Find your child\'s group')),
                $row($academy_group_cards, '4'),
            )),
        ), $searchable('sp-academy-groups', lang('age group, under 8, under 12, under 16, training times'))),
        $el('section', 'py-5', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('How a child joins'), 'psp-display h3 mb-4'),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), array(
                            $step(1, lang('Register online'), lang('Tell us about your child and their age group; it takes five minutes.')),
                            $step(2, lang('Come to a taster session'), lang('We invite your child to a free session with their group, with you on the touchline.')),
                            $step(3, lang('Join the group'), lang('If they enjoy it, we offer a place and send the season\'s training and match days.')),
                        )),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'p-4 p-lg-5 rounded-3 bg-primary-subtle', lang('Text'), array(
                            $heading('h2', lang('Safe and welcoming'), 'psp-display h4 mb-2'),
                            $para(lang('Every coach is background-checked and trained in first aid. Parents can watch every session, and we never share photos of children without consent.'), 'mb-0'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('sp-academy-join', lang('register, sign up, taster, safeguarding, first aid, photos'))),
    )),
);

// ── Sponsors ────────────────────────────────────────────────────────────
// The sponsors by tier, then the packages, each opening the enquiry with
// the package chosen.
$tier_sections = array();
$n = 0;
foreach ($sponsor_tiers as $tier => $title) {
    $tiles = array();
    foreach ($sponsors as $key => $s) {
        if ($s['tier'] === $tier) $tiles[] = $sponsor_tile($key);
    }
    $tier_sections[] = $el('section', 'py-5' . ($n % 2 ? ' bg-body-tertiary' : ''), $title, array(
        $container(array(
            $section_header(lang('Thank you'), $title),
            $row($tiles, '4'),
        )),
    ), $searchable('sp-sponsors-' . $tier, lang('sponsors, partners, local businesses')));
    $n++;
}
$package_cols = array();
foreach (array_keys($sponsor_packages) as $key) $package_cols[] = $package_card($key);

$public_pages[] = array(
    'key'              => 'sponsors',
    'name'             => lang('sponsors'),
    'folder'           => 'public',
    'title'            => lang('Sponsors'),
    'meta_description' => lang('The local businesses who keep the club playing, and the ways your business can support a community club.'),
    'tree'             => $page(array_merge(
        array($page_header(lang('Sponsors'), lang('Local businesses keep a volunteer club on the pitch. Thank you to every one of them.'))),
        $tier_sections,
        array(
            $el('section', 'py-5', lang('Packages'), array(
                $container(array(
                    $section_header(lang('Support the club'), lang('Become a sponsor'), lang('Ask about sponsorship'), '{{page:sponsor_enquiry}}'),
                    $row($package_cols, '4'),
                    $para(lang('Every package is agreed with the committee and can be shared between businesses. Ask us and we send the details.'), 'small text-body-secondary mt-4 mb-0'),
                )),
            ), $searchable('sp-packages', lang('sponsorship, advertising, shirt sponsor, board, price'))),
        )
    )),
);

// ── Questions ───────────────────────────────────────────────────────────
// Three accordions: playing, match days, the academy. Each question is a
// search section of its own ($faq).
$faq_group = function ($id, $title, $items) use ($el, $heading, $faq) {
    $children = array();
    foreach ($items as $n => $q) $children[] = $faq($n + 1, $q[0], $q[1], $q[2], $id);
    return $el('div', 'mb-5', $title, array(
        $heading('h2', $title, 'psp-display h4 mb-3'),
        $el('div', 'accordion', lang('Questions'), $children, array('id' => $id)),
    ));
};

$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Frequently Asked Questions'),
    'meta_description' => lang('Answers about trials, joining a team, subscriptions, match days, entry to the ground and the youth academy.'),
    'tree'             => $page(array(
        $page_header(lang('Frequently Asked Questions'), lang('Playing, watching and the academy. Not answered here? Write to us.')),
        $el('section', 'py-5', lang('Questions'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $faq_group('sp-faq-playing', lang('Playing for the club'), array(
                            array(lang('Can I join if I have never played?'), lang('Yes. The women\'s team, the veterans and the basketball section all welcome beginners; ask for a trial and say so.'), lang('beginner, new, never played, level')),
                            array(lang('Is there a cost to play?'), lang('Players pay a small subscription each season towards pitches, referees and kit. Your first training nights are free.'), lang('subs, subscription, fee, cost, price')),
                            array(lang('How do I follow my request?'), lang('Sending a form opens an account for you. Under My Account you see the state of each request and can write to the club there.'), lang('my account, status, track')),
                        )),
                        $faq_group('sp-faq-matchday', lang('Match days'), array(
                            array(lang('How much is entry to a match?'), lang('Pay at the gate on the day. Members and children under 16 come in free; a season ticket covers every home league match.'), lang('tickets, entry, price, gate, season ticket')),
                            array(lang('Where do I find kick-off times?'), lang('Every match is on the fixtures page, with the kick-off time and whether it is at home or away.'), lang('kick-off, time, fixtures, schedule')),
                            array(lang('Is the ground accessible?'), lang('Yes: step-free entry, places for wheelchair users beside the covered stand and an accessible toilet in the clubhouse.'), lang('wheelchair, accessible, disability, toilet')),
                        )),
                        $faq_group('sp-faq-academy', lang('Youth Academy'), array(
                            array(lang('From what age can a child join?'), lang('From five, in the Under-8s. Each group covers two or three school years.'), lang('age, minimum age, children, kids')),
                            array(lang('Do girls play in the academy?'), lang('Yes, in every group, and the oldest girls train with the women\'s team once a month.'), lang('girls, mixed, women')),
                            array(lang('What does my child need?'), lang('Trainers or boots, shin pads, a water bottle and clothes for the weather. Training tops come with the first season.'), lang('kit, boots, shin pads, what to bring')),
                        )),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
    )),
);

// The home page's lists: its matches (home_fixtures, home_results) come
// with the matches, its news (home_posts) and the Google reviews
// (google_reviews) with the standard pages; this part places no widget of
// its own.
$public_widgets = array();

return array('pages' => $public_pages, 'widgets' => $public_widgets);
