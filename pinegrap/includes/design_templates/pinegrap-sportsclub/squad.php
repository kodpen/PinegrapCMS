<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Sports Club", its squads: the helpers every collection
 * of the template shares (list settings, filters, the filter bar, the
 * record pages' toolbar and not-found note, long fields, record links, the
 * staff's form page), then the Players collection: the squad list
 * narrowed by team, the player page and the staff page that adds a
 * player (the Players form, with the sample players written in when it is
 * made). Included after parts.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * A player belongs to an adult team: the Team list holds the senior teams
 * only, so no child is named on the site by the template. Team and
 * position are pick lists whose answers are the words parts.php gives, so
 * a link with ?team= filters the squad by the words a visitor sees. The
 * shirt number is a number field, so the squad sorts by it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// ── Collection lists and record pages ───────────────────────────────────

// A filter row of a list's settings: the field, how it compares and the
// value, or where the value comes from (a URL parameter, left out while
// the parameter is not in the address; or today's date).
$sp_filter = function ($field, $operator, $value, $param = '') {
    $row = array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $param !== '' ? 'url parameter' : '');
    if ($param !== '') $row['dynamic_value_attribute'] = $param;
    return $row;
};
// A date filter against today: the matches to come (on or after today)
// or those already played (before today).
$sp_date_filter = function ($field, $operator) {
    return array('field' => $field, 'operator' => $operator, 'value' => '', 'dynamic_value' => 'current date');
};
// The answers of the Featured list: the first marks a record for the home.
$sp_featured_choices = array(lang('Yes'), lang('No'));

// A list's settings: the form, its record page, the order and the words
// shown when nothing matches.
$sp_list_config = function ($form_page, $detail_page, $order_field, $direction, $empty, $more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:' . $form_page . '}}',
        'detail_page_id'      => '{{tab:' . $detail_page . '}}',
        'order_by_field'      => $order_field,
        'order_by_direction'  => $direction,
        'empty_message'       => $empty,
    ), $more);
};

// The links above a list that narrow it: one per answer of a pick list,
// each opening the list with the filter set.
$sp_filter_bar = function ($page_key, $label, $param, $values) use ($el, $link, $span, $query) {
    $children = array($span($label, 'small fw-semibold text-body-secondary me-1', lang('Label')));
    $children[] = $link(lang('All'), '{{page:' . $page_key . '}}', 'btn btn-sm btn-outline-secondary rounded-pill');
    foreach ($values as $v) {
        $children[] = $link($v, '{{page:' . $page_key . '}}?' . $query(array($param => $v)), 'btn btn-sm btn-outline-secondary rounded-pill');
    }
    return $el('nav', 'd-flex flex-wrap align-items-center gap-2 mb-4', lang('Filters'), $children);
};

// The record pages' back link and Edit button, above the record.
$sp_toolbar = function ($page_key, $text) use ($el, $icon, $span, $edit_button) {
    return $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
        $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
            $icon('bi-arrow-left'),
            $span($text, '', lang('Link Text')),
        ), array('href' => '{{page:' . $page_key . '}}')),
        $edit_button(),
    ));
};
// What a record page shows when its record is not there.
$sp_not_found = function ($icon_name, $title, $text, $back_text, $back_page) use ($el, $icon, $heading, $para, $link, $bind, $show_when) {
    return $el('div', 'text-center py-5', lang('Not Found'), array(
        $icon($icon_name, 'display-5 text-body-tertiary'),
        $heading('h2', $title, 'h3 fw-semibold mt-3'),
        $para($text, 'text-body-secondary mb-4', $bind('not_found')),
        $link($back_text, '{{page:' . $back_page . '}}', 'btn btn-primary rounded-pill px-4'),
    ), $show_when('record_not_found'));
};
// A long field of a record page under its heading, its lines kept; the
// block leaves when the record has no value.
$sp_long_field = function ($title, $token, $sample, $class = 'mb-4') use ($el, $heading, $bind) {
    return $el('div', 'pg-hide-if-empty ' . $class, $title, array(
        $heading('h2', $title, 'psp-display h4 mb-2'),
        $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $sample), $bind($token))),
    ));
};
// A link to an address the record holds, in a new window, shown only when
// the record has it (a match video, a league page, a ticket seller).
$sp_record_link = function ($text, $token, $class) use ($link, $attr) {
    return $link($text, '#', $class, array($attr('rel', 'noopener')),
        array('target' => '_blank', '_bindings' => array('href' => $token, 'show_if' => $token)));
};
// A staff page holding a collection's form in a card.
$sp_form_page = function ($title, $lead, $widget_key) use ($staff_page, $page_header, $el, $container, $row, $col, $widget) {
    return $staff_page(array(
        $page_header($title, $lead),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 rounded-4 border', lang('Form Card'), array($widget($widget_key))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    ));
};

// ── The Players collection ──────────────────────────────────────────────

$squad_pages = array();

// The teams a player may belong to: the senior teams, in their order.
$squad_team_titles = array();
foreach ($teams as $t) {
    if (!$t['youth']) $squad_team_titles[] = $t['title'];
}

// The cards of a list, in rows of four.
$player_grid = function () use ($widget_root, $row, $loop, $player_card) {
    return $widget_root(array($row(array($loop(array($player_card()))), '4')));
};

// Every player, narrowed by team from the address.
$squad_pages[] = array(
    'key'              => 'squad',
    'name'             => lang('squads'),
    'folder'           => 'public',
    'title'            => lang('Squads'),
    'meta_description' => lang('The players of the club\'s senior teams, with their positions and shirt numbers.'),
    'tree'             => $page(array(
        $page_header(lang('Squads'), lang('The players of the senior teams. Choose a team to see its squad.')),
        $el('section', 'py-5', lang('Squads'), array(
            $container(array(
                $sp_filter_bar('squad', lang('By team'), 'team', $squad_team_titles),
                $widget('players_list'),
            )),
        ), $searchable('sp-squad', lang('players, squad, line-up, goalkeeper, striker, captain'))),
    )),
);

// One player.
$squad_pages[] = array(
    'key'              => 'player',
    'name'             => lang('player'),
    'folder'           => 'public',
    'title'            => lang('Player Profile'),
    'meta_description' => lang('A player of the club: the team, the position, the shirt number and a few words about them.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('player_detail'),
    )),
);

// The staff page that adds a player. The form is the Players collection;
// the sample players are written in when the form is made, so the squad
// does not open empty (pg_cf_seed_sample_records()).
$sample_players = array();
foreach ($players as $p) {
    $sample_players[] = array(
        'name'         => $p['name'],
        'team'         => $teams[$p['team']]['title'],
        'position'     => $positions[$p['position']]['title'],
        'shirt_number' => (string)$p['number'],
        'summary'      => $p['summary'],
        'bio'          => $p['bio'],
        'photo'        => $portrait($p['name'], 900),
        'featured'     => $p['featured'] ? $sp_featured_choices[0] : $sp_featured_choices[1],
    );
}
$squad_pages[] = array(
    'key'     => 'player_new',
    'name'    => lang('new-player'),
    'folder'  => 'private',
    'title'   => lang('New Player'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Players'),
        'collection'           => 1,
        'confirmation_message' => lang('The player is published.'),
        'confirmation_page_id' => '{{tab:squad}}',
        'sample_records'       => $sample_players,
        // The photo is the player's share image (pg_cf_share_image_field_id()).
        'share_image_field'    => 'photo',
    ),
    'tree'    => $sp_form_page(lang('New Player'),
        lang('Add a player of a senior team with a photo and a few words; they appear in the squad as soon as you send it. Youth players are not listed.'),
        'player_form'),
);

$squad_widgets = array(

    // The staff's Players form: one player. Featured starts as No.
    'player_form' => array(
        'page'   => 'player_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The player')),
            $field('sp-player-name', lang('Name'), pg_cf_with_role($input('sp-player-name', 'text', 'name', 'off'), 'title')),
            $row(array(
                $col(array($field('sp-player-team', lang('Team Name'), pg_cf_with_role($select('sp-player-team', 'team', lang('Choose the team…'), $squad_team_titles), 'category'))), '6'),
                $col(array($field('sp-player-position', lang('Playing Position'), $select('sp-player-position', 'position', lang('Choose the position…'), $position_titles))), '6'),
            ), '3'),
            $field('sp-player-number', lang('Shirt Number'),
                $input('sp-player-number', 'number', 'shirt_number', 'off', true, array('kind' => 'number'), array($attr('min', '1'), $attr('max', '99'), $attr('step', '1')))),
            $form_step(2, lang('Words')),
            $field('sp-player-summary', lang('Summary'), pg_cf_with_role($textarea('sp-player-summary', 'summary', 2), 'description'), 'mb-3',
                lang('Two lines for the squad\'s cards.')),
            $field('sp-player-bio', lang('About the player'), $textarea('sp-player-bio', 'bio', 6, false)),
            $form_step(3, lang('Photo')),
            $field('sp-player-photo', lang('Photo'), pg_cf_with_role($file('sp-player-photo', 'photo', 'public', 'image/*'), 'media'), 'mb-3',
                lang('A square photo works best; it is also the share image.')),
            $field('sp-player-featured', lang('Featured'), $select('sp-player-featured', 'featured', '', $sp_featured_choices, false, $sp_featured_choices[1], array('office_use_only' => true)),
                'mb-3', lang('A featured player is one the club points to first.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every player, filtered by the address (team), lowest shirt number
    // first, with a search over the players' words.
    'players_list' => array(
        'page'   => 'squad',
        'slug'   => lang('form-list'),
        'config' => $sp_list_config('player_new', 'player', 'shirt_number', 'ASC',
            lang('No player in this squad yet. Choose another team, or ask for a trial.'), array(
            'items_per_page' => 24,
            'filters'        => array($sp_filter('team', 'is equal to', '', 'team')),
            'search_enabled' => true,
            'search_fields'  => array('name', 'team', 'position', 'summary', 'bio'),
            'search_width'   => 'full',
            'search_label'   => lang('Search the squads: a name, a position...'),
        )),
        'tree'   => $player_grid(),
    ),
);

// One player: the photo, the shirt number and the position, the name, the
// team, the words and the ways on.
$player_sample = $players['kerem'];
$player_not_found = lang('This player could not be found. They may have left the club, or the page was renamed.');
$squad_widgets['player_detail'] = array(
    'page'   => 'player',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:player_new}}',
        'access_control'      => 'public',
        'not_found_message'   => $player_not_found,
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $sp_not_found('bi-person-x', lang('Player not found'), $player_not_found, lang('See the squads'), 'squad'),
            $el('article', '', lang('Player Profile'), array(
                $sp_toolbar('squad', lang('All players')),
                $row(array(
                    $col(array(
                        $optional_image('photo', 'name', 'rounded-3 object-fit-cover', 'ratio ratio-1x1',
                            $portrait($player_sample['name'], 900), lang('Photo')),
                    ), '5', '4'),
                    $col(array(
                        $el('p', 'd-flex align-items-baseline gap-2 mb-2', lang('Number and Position'), array(
                            $span((string)$player_sample['number'], 'psp-score display-6 fw-bold text-primary', lang('Shirt Number'), $bind('shirt_number')),
                            $span($positions[$player_sample['position']]['title'], 'badge bg-primary-subtle text-primary-emphasis', lang('Playing Position'), $bind('position')),
                        )),
                        $heading('h1', $player_sample['name'], 'psp-display display-6 mb-1', $bind('name')),
                        $para($teams[$player_sample['team']]['title'], 'fw-semibold mb-3', $bind('team')),
                        $para($player_sample['summary'], 'lead text-body-secondary mb-4', $bind('summary')),
                        $sp_long_field(lang('About the player'), 'bio', $player_sample['bio']),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('See the Fixtures'), '{{page:fixtures}}', 'btn btn-primary px-4'),
                            $link(lang('Our Teams'), '{{page:teams}}', 'btn btn-outline-primary px-4'),
                        )),
                    ), '7', '8'),
                ), '4', '', 'g-lg-5'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $squad_pages, 'widgets' => $squad_widgets);
