<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Sports Club", its matches: the fixtures from today on
 * and the results before today, both narrowed by team, the match page
 * with the score, the outcome and the report, the staff page that adds a
 * match (the Matches form, a collection, with the sample matches written
 * in when it is made) and the two lists the home page places. Included
 * after parts.php and squad.php in the same scope (the $sp_* list helpers
 * come from squad.php); returns array('pages' => ..., 'widgets' => ...).
 *
 * A match is a record of the Matches form. Its date is a date field, so
 * the fixtures list the matches from today on (nearest first) and the
 * results the rest (latest first); the sample dates are counted from the
 * day the template opens ($match_on()). Team, competition, home or away
 * and the outcome are pick lists whose answers are the words parts.php
 * gives, so a link with ?team= filters the lists by the words a visitor
 * sees. The score is words ("2–1") written after the match; the row's
 * score and outcome leave while the match has none. The club's own video
 * of a match (video_url) shows as a button only when it is there.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$match_pages = array();

// The rows of a list, one match under the other; $sample is the match the
// canvas shows.
$match_list = function ($sample) use ($widget_root, $loop, $match_row) {
    return $widget_root(array($loop(array($match_row($sample)))));
};

// ── Pages ───────────────────────────────────────────────────────────────

// The matches to come, narrowed by team from the address.
$match_pages[] = array(
    'key'              => 'fixtures',
    'name'             => lang('fixtures'),
    'folder'           => 'public',
    'title'            => lang('Fixtures'),
    'meta_description' => lang('Every match to come for every team of the club, with the date, the kick-off time and whether it is at home or away.'),
    'tree'             => $page(array(
        $page_header(lang('Fixtures'), lang('Every match to come, nearest first. Choose a team to narrow the list.')),
        $el('section', 'py-5', lang('Fixtures'), array(
            $container(array(
                $sp_filter_bar('fixtures', lang('By team'), 'team', $team_titles),
                $widget('fixtures_list'),
                $el('p', 'mt-4 mb-0', lang('Text'), array(
                    $link(lang('See the results'), '{{page:results}}', 'fw-semibold link-primary'),
                )),
            )),
        ), $searchable('sp-fixtures', lang('fixtures, next match, kick-off, schedule, calendar'))),
    )),
);

// The matches already played, latest first.
$match_pages[] = array(
    'key'              => 'results',
    'name'             => lang('results'),
    'folder'           => 'public',
    'title'            => lang('Match Results'),
    'meta_description' => lang('The scores of the club\'s matches, latest first, with a report of each.'),
    'tree'             => $page(array(
        $page_header(lang('Match Results'), lang('Every match played, latest first. Choose a team to narrow the list.')),
        $el('section', 'py-5', lang('Match Results'), array(
            $container(array(
                $sp_filter_bar('results', lang('By team'), 'team', $team_titles),
                $widget('results_list'),
                $el('p', 'mt-4 mb-0', lang('Text'), array(
                    $link(lang('See the fixtures'), '{{page:fixtures}}', 'fw-semibold link-primary'),
                )),
            )),
        ), $searchable('sp-results', lang('results, scores, match report, won, lost, drawn'))),
    )),
);

// One match.
$match_pages[] = array(
    'key'              => 'match',
    'name'             => lang('match'),
    'folder'           => 'public',
    'title'            => lang('Match Details'),
    'meta_description' => lang('A match of the club: the date, the kick-off, the competition, the score and the match report.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('match_detail'),
    )),
);

// The staff page that adds a match. The form is the Matches collection;
// the sample matches are written in when the form is made, their dates
// counted from today.
$sample_matches = array();
foreach ($matches as $key => $m) {
    $r = array(
        'title'       => $match_title($key),
        'team'        => $teams[$m['team']]['title'],
        'opponent'    => $opponents[$m['opponent']],
        'competition' => $competitions[$m['competition']],
        'venue'       => $venues[$m['venue']],
        'match_date'  => $match_on($m['weekday'], $m['weeks']),
        'kickoff'     => $m['kickoff'],
        'report'      => $m['report'],
        'featured'    => $m['featured'] ? $sp_featured_choices[0] : $sp_featured_choices[1],
    );
    // A played match has its score and outcome; one to come has neither.
    if ($m['score'] !== '') {
        $r['score'] = $m['score'];
        $r['outcome'] = $outcomes[$m['outcome']]['title'];
    }
    $sample_matches[] = $r;
}
$match_pages[] = array(
    'key'     => 'match_new',
    'name'    => lang('new-match'),
    'folder'  => 'private',
    'title'   => lang('New Match'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Matches'),
        'collection'           => 1,
        'confirmation_message' => lang('The match is published.'),
        'confirmation_page_id' => '{{tab:fixtures}}',
        'sample_records'       => $sample_matches,
    ),
    'tree'    => $sp_form_page(lang('New Match'),
        lang('Add a match with its date and kick-off; it appears in the fixtures as soon as you send it and moves to the results the day after. Edit it after the final whistle to add the score and the report.'),
        'match_form'),
);

// ── Widgets ─────────────────────────────────────────────────────────────

$match_widgets = array(

    // The staff's Matches form: one match. The score, the outcome, the
    // report and the video are left empty until the match is played.
    'match_form' => array(
        'page'   => 'match_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The match')),
            $field('sp-match-title', lang('Match Title'), pg_cf_with_role($input('sp-match-title', 'text', 'title', 'off'), 'title'), 'mb-3',
                lang('The two sides, home side first, for example Riverside Rovers vs our club.')),
            $row(array(
                $col(array($field('sp-match-team', lang('Team Name'), pg_cf_with_role($select('sp-match-team', 'team', lang('Choose the team…'), $team_titles), 'category'))), '6'),
                $col(array($field('sp-match-opponent', lang('Opponent'), $input('sp-match-opponent', 'text', 'opponent', 'off'))), '6'),
                $col(array($field('sp-match-competition', lang('Competition'), $select('sp-match-competition', 'competition', lang('Choose a competition…'), $competition_titles))), '6'),
                $col(array($field('sp-match-venue', lang('Home or Away'), $select('sp-match-venue', 'venue', '', $venue_titles, true, $venue_titles[0]))), '6'),
                $col(array($field('sp-match-date', lang('Match Date'), $input('sp-match-date', 'date', 'match_date', 'off'))), '6'),
                $col(array($field('sp-match-kickoff', lang('Kick-off'), $input('sp-match-kickoff', 'text', 'kickoff', 'off'), 'mb-3',
                    lang('The time the match starts, for example 15:00.'))), '6'),
            ), '3'),
            $form_step(2, lang('After the final whistle')),
            $row(array(
                $col(array($field('sp-match-score', lang('Match Score'), $input('sp-match-score', 'text', 'score', 'off', false), 'mb-3',
                    lang('Home side first, for example 2–1. Leave empty until the match is played.'))), '6'),
                $col(array($field('sp-match-outcome', lang('Outcome'), $select('sp-match-outcome', 'outcome', lang('Not played yet'), $outcome_titles, false))), '6'),
            ), '3'),
            $field('sp-match-report', lang('Match Report'), pg_cf_with_role($textarea('sp-match-report', 'report', 6, false), 'description'), 'mb-3',
                lang('Before the match a line about it; after it, the report.')),
            $field('sp-match-video', lang('Match video'), $input('sp-match-video', 'url', 'video_url', 'url', false), 'mb-3',
                lang('The address of the club\'s video of the match. Leave empty to hide the button.')),
            $field('sp-match-featured', lang('Featured'), $select('sp-match-featured', 'featured', '', $sp_featured_choices, false, $sp_featured_choices[1], array('office_use_only' => true)),
                'mb-3', lang('A featured match is one the club points to first.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // The matches to come, filtered by the address (team), nearest first.
    'fixtures_list' => array(
        'page'   => 'fixtures',
        'slug'   => lang('form-list'),
        'config' => $sp_list_config('match_new', 'match', 'match_date', 'ASC',
            lang('No match planned for this team yet. Check back soon.'), array(
            'items_per_page' => 20,
            'filters'        => array(
                $sp_date_filter('match_date', 'is greater than or equal to'),
                $sp_filter('team', 'is equal to', '', 'team'),
            ),
        )),
        'tree'   => $match_list('next_first'),
    ),

    // The matches already played, filtered by the address (team), latest
    // first.
    'results_list' => array(
        'page'   => 'results',
        'slug'   => lang('form-list'),
        'config' => $sp_list_config('match_new', 'match', 'match_date', 'DESC', lang('No result for this team yet.'), array(
            'items_per_page' => 20,
            'filters'        => array(
                $sp_date_filter('match_date', 'is less than'),
                $sp_filter('team', 'is equal to', '', 'team'),
            ),
        )),
        'tree'   => $match_list('last_first'),
    ),

    // The home page's next matches, nearest first, four at most.
    'home_fixtures' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $sp_list_config('match_new', 'match', 'match_date', 'ASC', lang('No match planned yet.'), array(
            'items_per_page' => 4,
            'max_results'    => 4,
            'filters'        => array($sp_date_filter('match_date', 'is greater than or equal to')),
        )),
        'tree'   => $match_list('next_first'),
    ),

    // The home page's latest results, latest first, four at most.
    'home_results' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $sp_list_config('match_new', 'match', 'match_date', 'DESC', lang('No result yet.'), array(
            'items_per_page' => 4,
            'max_results'    => 4,
            'filters'        => array($sp_date_filter('match_date', 'is less than')),
        )),
        'tree'   => $match_list('last_first'),
    ),
);

// A fact of the match page: an icon, the label and the bound value.
$match_fact = function ($icon_name, $label, $token, $sample) use ($el, $icon, $span, $bind) {
    return $el('p', 'pg-hide-if-empty d-flex align-items-center gap-2 mb-2', $label, array(
        $icon($icon_name, 'text-primary'),
        $span($label, 'text-body-secondary', lang('Label')),
        $span($sample, 'pg-field-value fw-semibold', lang('Value'), $bind($token)),
    ));
};

// One match: the competition and the team, the two sides, the score and
// the outcome once played, the date, the kick-off and the venue, the
// report and the video.
$match_sample_key = 'last_first';
$match_sample = $matches[$match_sample_key];
$match_not_found = lang('This match could not be found. It may have been moved or called off.');
$match_widgets['match_detail'] = array(
    'page'   => 'match',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:match_new}}',
        'access_control'      => 'public',
        'not_found_message'   => $match_not_found,
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $sp_not_found('bi-calendar-x', lang('Match not found'), $match_not_found, lang('See the fixtures'), 'fixtures'),
            $el('article', '', lang('Match Details'), array(
                $sp_toolbar('fixtures', lang('All fixtures')),
                $row(array(
                    $col(array(
                        $el('p', 'd-flex flex-wrap gap-2 small text-body-secondary mb-2', lang('Competition and Team'), array(
                            $span($competitions[$match_sample['competition']], 'psp-eyebrow text-uppercase fw-semibold text-primary', lang('Competition'), $bind('competition')),
                            $span('·', '', lang('Separator')),
                            $span($teams[$match_sample['team']]['title'], '', lang('Team Name'), $bind('team')),
                        )),
                        $heading('h1', $match_title($match_sample_key), 'psp-display display-6 mb-3', $bind('title')),
                        $el('div', 'd-flex align-items-center gap-3 mb-4 pg-hide-if-empty', lang('Result'), array(
                            $span($match_sample['score'], 'psp-score display-4 fw-bold pg-hide-if-empty', lang('Match Score'), $bind('score')),
                            $span($outcomes[$match_sample['outcome']]['title'], 'badge rounded-pill fs-6 ' . $outcomes[$match_sample['outcome']]['badge'] . ' pg-hide-if-empty', lang('Outcome'), $bind('outcome')),
                        )),
                        $sp_long_field(lang('Match Report'), 'report', $match_sample['report']),
                        $sp_record_link(lang('Watch the match'), 'video_url', 'btn btn-outline-primary px-4'),
                    ), '', '7'),
                    $col(array(
                        $el('aside', 'card border-0 shadow-sm', lang('Match Facts'), array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $match_fact('bi-calendar-event', lang('Date'), 'match_date', $match_on($match_sample['weekday'], $match_sample['weeks'])),
                                $match_fact('bi-clock', lang('Kick-off'), 'kickoff', $match_sample['kickoff']),
                                $match_fact('bi-geo-alt', lang('Home or Away'), 'venue', $venues[$match_sample['venue']]),
                                $match_fact('bi-shield', lang('Opponent'), 'opponent', $opponents[$match_sample['opponent']]),
                                $el('div', 'd-flex flex-wrap gap-2 mt-3', lang('Buttons'), array(
                                    $link(lang('Getting to the ground'), '{{page:ground}}', 'btn btn-sm btn-outline-primary'),
                                    $link(lang('Match Results'), '{{page:results}}', 'btn btn-sm btn-outline-primary'),
                                )),
                            )),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $match_pages, 'widgets' => $match_widgets);
