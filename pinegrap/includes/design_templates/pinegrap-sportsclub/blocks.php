<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Sports Club", its palette blocks: the Sports Club block
 * family (assets/js/style_designer_blocks_sportsclub.js) as the home, the
 * teams, the fixtures and the results pages place it, with the blocks'
 * structure and pg-sc- classes, filled from the club's data (Match Day,
 * League Table, Squad by Position). Their rules ship in the template's
 * User Styles (pg_design_template_user_styles()). Motion is a node
 * interaction (props._fx, played by pg_fx), so reduced motion keeps every
 * part still. Included after parts.php in the same scope; sets $fx,
 * $sc_matchday, $sc_table and $sc_squad.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$fx = function ($node, $effect, $trigger = 'view', $more = array()) {
    $node['props']['_fx'] = array_merge(array('effect' => $effect, 'trigger' => $trigger), $more);
    return $node;
};
$sc_days = array(1 => lang('Monday'), 2 => lang('Tuesday'), 3 => lang('Wednesday'), 4 => lang('Thursday'),
    5 => lang('Friday'), 6 => lang('Saturday'), 7 => lang('Sunday'));
// A kick-off as a <time>, its machine value the same 24-hour words.
$sc_time = function ($hhmm, $class) use ($el, $attr) {
    return $el('time', $class, lang('Kick-off Time'), array(), array('text' => $hhmm, '_attrs' => array($attr('datetime', $hhmm))));
};
// A result said with an icon and a word, never by colour alone.
$sc_outcome = function ($key) use ($el, $icon, $span, $outcomes) {
    $look = array(
        'win'  => array('bi-check-circle-fill', 'bg-success-subtle text-success-emphasis'),
        'draw' => array('bi-dash-circle-fill', 'bg-secondary-subtle text-secondary-emphasis'),
        'loss' => array('bi-x-circle-fill', 'bg-danger-subtle text-danger-emphasis'),
    );
    return $el('span', 'badge rounded-pill ' . $look[$key][1], lang('Result Badge'), array(
        $icon($look[$key][0], 'me-1'),
        $span($outcomes[$key]['title'], '', lang('Outcome')),
    ));
};
// One side of the match: its crest in a ring, its name, home or away.
$sc_side = function ($badge, $name, $where) use ($el, $icon, $span) {
    return $el('div', 'd-flex flex-column align-items-center text-center gap-2', $name, array(
        $el('span', 'pg-sc-crest', lang('Crest'), array($icon($badge))),
        $span($name, 'fw-bold fs-5 lh-sm', lang('Team Name')),
        $span($where, 'small text-body-secondary', lang('Side')),
    ));
};

// Match Day: the first team's next match as two crests facing each other,
// the kick-off between them, on a dark band; the last result beside it.
// $head opens the band (the home's h1, the fixtures' h2); $buttons follow
// the ground line; $trigger is 'load' at the top of a page.
$sc_matchday = function ($head, $buttons, $trigger, $id, $topics)
    use ($el, $span, $para, $link, $container, $row, $col, $attr, $searchable, $fx, $sc_days, $sc_time, $sc_outcome, $sc_side,
         $matches, $opponents, $competitions, $ground) {
    $m = $matches['next_first'];
    $l = $matches['last_first'];
    $us = $sc_side('bi-shield-fill', '{{site_name}}', $m['venue'] === 'home' ? lang('Home side') : lang('Away side'));
    $them = $sc_side('bi-shield', $opponents[$m['opponent']], $m['venue'] === 'home' ? lang('Away side') : lang('Home side'));
    $sides = $m['venue'] === 'home' ? array($us, $them) : array($them, $us);
    $next = array_merge($head, array(
        $el('div', 'd-flex flex-wrap align-items-center gap-2 mt-3', lang('Match Label'), array(
            $span($competitions[$m['competition']], 'badge rounded-pill text-bg-primary', lang('Competition')),
            $span(lang('Next match'), 'small fw-semibold text-uppercase text-body-secondary', lang('Label')),
        )),
        $fx($el('div', 'd-flex align-items-center justify-content-center gap-3 gap-md-5 py-4', lang('Both Sides'), array(
            $sides[0],
            $el('div', 'text-center', lang('Kick-off'), array(
                $span($sc_days[$m['weekday']], 'd-block small text-body-secondary', lang('Day')),
                $sc_time($m['kickoff'], 'd-block display-6 pg-sc-score'),
                $span(lang('vs'), 'd-block small fw-semibold text-uppercase text-primary', lang('Versus')),
            )),
            $sides[1],
        )), 'slide-up', $trigger, array('stagger' => 150)),
        $para(sprintf(lang('%s · gates open an hour before kick-off'), $ground['name']), 'text-center text-body-secondary mb-4'),
        $el('div', 'd-flex flex-wrap justify-content-center gap-2', lang('Buttons'), $buttons),
    ));
    $last = $fx($el('div', 'card h-100 border-0 bg-body-tertiary', lang('Last Result'), array(
        $el('div', 'card-body p-4 d-flex flex-column', lang('Card Body'), array(
            $el('div', 'd-flex align-items-center justify-content-between gap-2 mb-3', lang('Card Head'), array(
                $el('h2', 'psp-display h6 text-uppercase mb-0', lang('Title'), array(), array('text' => lang('Last result'))),
                $sc_outcome($l['outcome']),
            )),
            $el('div', 'd-flex align-items-center justify-content-between gap-3 mb-3', lang('Score Line'), array(
                $span('{{site_name}}', 'fw-semibold', lang('Team Name')),
                $span($l['score'], 'fs-2 pg-sc-score', lang('Match Score')),
                $span($opponents[$l['opponent']], 'fw-semibold text-end', lang('Team Name')),
            )),
            $para($l['report'], 'small text-body-secondary mb-3'),
            $link(lang('All results'), '{{page:results}}', 'link-primary fw-semibold mt-auto'),
        )),
    )), 'fade', $trigger, array('delay' => 300));
    return $el('section', 'py-5 bg-body text-body', lang('Match Day'), array(
        $container(array(
            $row(array(
                $col($next, '', '8'),
                $col(array($last), '', '4'),
            ), '4', '', 'align-items-stretch g-lg-5'),
        )),
    ), array_merge($searchable($id, $topics), array('_attrs' => array($attr('data-bs-theme', 'dark')))));
};

// League Table: where the first team stands, its own row marked, the
// promotion and relegation places named in a key. Sample figures for the
// club to keep up to date after each match.
$sc_table = function () use ($el, $span, $container, $attr, $searchable, $section_header, $fx, $competitions, $opponents) {
    $head = function ($short, $full, $class = 'text-center') use ($el, $span, $attr) {
        return $el('th', $class, $full, array(
            $span($short, '', lang('Short')),
            $span($full, 'visually-hidden', lang('Full')),
        ), array('_attrs' => array($attr('scope', 'col'))));
    };
    $standings = array(
        array('1', $opponents['oldtown'], array('12', '9', '2', '1', '+15', '29'), 'up'),
        array('2', '{{site_name}}', array('12', '8', '2', '2', '+11', '26'), 'up'),
        array('3', $opponents['harbour'], array('12', '7', '3', '2', '+8', '24'), ''),
        array('4', $opponents['hillside'], array('12', '5', '3', '4', '+2', '18'), ''),
        array('5', $opponents['eastgate'], array('12', '3', '2', '7', '-9', '11'), 'down'),
        array('6', $opponents['rovers'], array('12', '1', '2', '9', '-27', '5'), 'down'),
    );
    $rows = array();
    foreach ($standings as $s) {
        $ours = $s[1] === '{{site_name}}';
        $cells = array(
            $el('td', 'text-center fw-semibold', lang('Cell'), array(), array('text' => $s[0])),
            $el('th', $ours ? 'fw-bold' : 'fw-normal', lang('Club'), array(), array('text' => $s[1], '_attrs' => array($attr('scope', 'row')))),
        );
        foreach ($s[2] as $i => $f) $cells[] = $el('td', $i === 5 ? 'text-center fw-bold' : 'text-center', lang('Cell'), array(), array('text' => $f));
        $class = trim(($s[3] !== '' ? 'pg-sc-' . $s[3] : '') . ($ours ? ' table-primary' : ''));
        $rows[] = $el('tr', $class, $s[1], $cells, $ours ? array('_attrs' => array($attr('aria-current', 'true'))) : array());
    }
    $caption = lang('League table after twelve matches');
    $table = $el('table', 'table align-middle mb-0 pg-sc-score', lang('League Table'), array(
        $el('caption', 'visually-hidden', lang('Table Caption'), array(), array('text' => $caption)),
        $el('thead', '', lang('Table Head'), array(
            $el('tr', '', lang('Head Row'), array(
                $head(lang('#'), lang('League position')),
                $head(lang('Club'), lang('Club'), ''),
                $head(lang('P'), lang('Matches played')),
                $head(lang('W'), lang('Wins')),
                $head(lang('D'), lang('Draws')),
                $head(lang('L'), lang('Losses')),
                $head(lang('GD'), lang('Goal difference')),
                $head(lang('Pts'), lang('League points')),
            )),
        )),
        $fx($el('tbody', '', lang('Table Body'), $rows), 'fade', 'view', array('stagger' => 60)),
    ));
    $zone = function ($class, $text) use ($el, $span) {
        return $el('li', 'd-flex align-items-center gap-2', $text, array(
            $span('', 'pg-sc-mark ' . $class, lang('Mark')),
            $span($text, '', lang('Text')),
        ));
    };
    return $el('section', 'py-5 bg-body-tertiary', lang('League Table'), array(
        $container(array(
            $section_header($competitions['league'], lang('League table'), lang('All results'), '{{page:results}}'),
            $el('div', 'card border-0 shadow-sm', lang('Table Card'), array(
                $el('div', 'card-body p-0 p-md-3', lang('Card Body'), array(
                    $el('div', 'table-responsive position-relative', lang('Table Wrap'), array($table), array('_attrs' => array(
                        $attr('role', 'region'), $attr('aria-label', $caption), $attr('tabindex', '0'),
                    ))),
                )),
            )),
            $el('ul', 'list-unstyled d-flex flex-wrap gap-4 small text-body-secondary mt-3 mb-0', lang('Table Key'), array(
                $zone('bg-success', lang('Promotion places')),
                $zone('bg-danger', lang('Relegation places')),
            )),
        )),
    ), $searchable('sp-league-table', lang('league table, standings, points, position, promotion')));
};

// Squad by Position: a team's players under a heading per position, each
// card led by the shirt number; the name opens the team's squad list.
$sc_squad = function ($team) use ($el, $span, $heading, $link, $image, $row, $col, $attr, $fx, $query, $portrait, $players, $teams) {
    $groups = array(
        'goalkeeper' => lang('Goalkeepers'),
        'defender'   => lang('Defenders'),
        'midfielder' => lang('Midfielders'),
        'forward'    => lang('Forwards'),
    );
    $href = '{{page:squad}}?' . $query(array('team' => $teams[$team]['title']));
    $out = array();
    foreach ($groups as $position => $title) {
        $cards = array();
        foreach ($players as $p) {
            if ($p['team'] !== $team || $p['position'] !== $position) continue;
            $cards[] = $col(array(
                $el('div', 'card h-100 border-0 shadow-sm position-relative', lang('Player Card'), array(
                    $image($portrait($p['name'], 400), $p['name'], 'card-img-top pg-ratio-1x1', array('_attrs' => array($attr('loading', 'lazy')))),
                    $el('div', 'card-body d-flex align-items-center gap-3', lang('Card Body'), array(
                        $span((string)$p['number'], 'pg-sc-number', lang('Shirt Number')),
                        $el('div', '', lang('Player Name'), array(
                            $link($p['name'], $href, 'd-block fw-bold text-body text-decoration-none stretched-link'),
                            $span($p['summary'], 'small text-body-secondary', lang('Text')),
                        )),
                    )),
                )),
            ), '4', '3', '6');
        }
        if (!$cards) continue;
        $out[] = $el('div', 'mb-5', $title, array(
            $heading('h3', $title, 'psp-display h5 text-uppercase border-bottom pb-2 mb-3'),
            $fx($row($cards, '4'), 'slide-up', 'view', array('stagger' => 80)),
        ));
    }
    return $out;
};
