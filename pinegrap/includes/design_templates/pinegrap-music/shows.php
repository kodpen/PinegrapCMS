<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Music Artist", its shows: the staff page that adds a
 * show (the Shows form, a collection, with the sample shows written in
 * when it is made), the tour dates from today on with their country
 * filter, the shows already played, the show page with the venue, the
 * doors, the ticket state and the ticket link, and the list the home page
 * places. Included after parts.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * A show is a record of the Shows form. Its date is a date field, so the
 * tour lists the shows from today on (nearest first) and the past shows
 * the rest (latest first); the sample dates are counted from the day the
 * template opens ($show_on()). Country and ticket state are pick lists
 * whose answers are the words parts.php gives, so a link with ?country=
 * filters the tour by the words a visitor sees. The tickets are sold by
 * the venue or a ticket service: the show links its page (ticket_url),
 * and the button leaves when a show has none. Featured is the band's own
 * choice (office use only): the home page shows the featured shows to
 * come.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$show_pages = array();

// The rows of a list, one show under the other.
$show_list = function () use ($widget_root, $loop, $show_row) {
    return $widget_root(array($loop(array($show_row()))));
};

// ── Pages ───────────────────────────────────────────────────────────────

// The shows to come, narrowed by country from the address.
$show_pages[] = array(
    'key'              => 'tour',
    'name'             => lang('tour-dates'),
    'folder'           => 'public',
    'title'            => lang('Tour Dates'),
    'meta_description' => lang('Every show to come with its city, venue, doors time and the way to the tickets.'),
    'tree'             => $page(array(
        $page_header(lang('Tour Dates'), lang('Every show to come, nearest first. Choose a country to narrow the list.')),
        $el('section', 'py-5', lang('Tour Dates'), array(
            $container(array(
                $mu_filter_bar('tour', lang('Country'), 'country', $country_titles),
                $widget('tour_list'),
                $el('p', 'mt-4 mb-0', lang('Text'), array(
                    $link(lang('See the shows already played'), '{{page:past_shows}}', 'fw-semibold link-primary'),
                )),
            )),
        )),
    )),
);

// The shows already played, latest first.
$show_pages[] = array(
    'key'              => 'past_shows',
    'name'             => lang('past-shows'),
    'folder'           => 'public',
    'title'            => lang('Past Shows'),
    'meta_description' => lang('The shows the band has already played, with the city and the venue of each.'),
    'tree'             => $page(array(
        $page_header(lang('Past Shows'), lang('Where we have played, latest first. Thank you to everyone who came.')),
        $el('section', 'py-5', lang('Past Shows'), array(
            $container(array(
                $widget('past_shows_list'),
                $el('p', 'mt-4 mb-0', lang('Text'), array(
                    $link(lang('See the tour dates'), '{{page:tour}}', 'fw-semibold link-primary'),
                )),
            )),
        )),
    )),
);

// One show.
$show_pages[] = array(
    'key'              => 'show',
    'name'             => lang('concert'),
    'folder'           => 'public',
    'title'            => lang('Show Details'),
    'meta_description' => lang('A show of the band: the date, the venue, the doors time, the support act and the way to the tickets.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('show_detail'),
    )),
);

// The staff page that adds a show. The form is the Shows collection; the
// sample shows are written in when the form is made, their dates counted
// from today.
$sample_shows = array();
foreach ($shows as $key => $s) {
    $sample_shows[] = array(
        'show_date'    => $show_on($s['weekday'], $s['weeks']),
        'city'         => $s['city'],
        'country'      => $countries[$s['country']],
        'venue'        => $s['venue'],
        'doors'        => $s['doors'],
        'support'      => $s['support'],
        'ticket_state' => $ticket_states[$s['state']]['title'],
        'featured'     => $s['featured'] ? $mu_featured_choices[0] : $mu_featured_choices[1],
    );
    // A show with no support act leaves the field empty; a show with
    // tickets to sell links a sample ticket page the band replaces.
    $n = count($sample_shows) - 1;
    if ($s['support'] === '') unset($sample_shows[$n]['support']);
    if ($s['weeks'] >= 0 && in_array($s['state'], array('on_sale', 'few_left'), true)) {
        $sample_shows[$n]['ticket_url'] = 'https://tickets.example.com/';
    }
}
$show_pages[] = array(
    'key'     => 'show_new',
    'name'    => lang('new-show'),
    'folder'  => 'private',
    'title'   => lang('New Show'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Tour Shows'),
        'collection'           => 1,
        'confirmation_message' => lang('The show is published.'),
        'confirmation_page_id' => '{{tab:tour}}',
        'sample_records'       => $sample_shows,
    ),
    'tree'    => $mu_form_page(lang('New Show'),
        lang('Add the show with its date, venue and ticket link; it appears in the tour dates as soon as you send it and moves to the past shows the day after.'),
        'show_form'),
);

// ── Widgets ─────────────────────────────────────────────────────────────

$show_widgets = array(

    // The staff's Shows form: one show. Featured starts as No.
    'show_form' => array(
        'page'   => 'show_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('When and where')),
            $row(array(
                $col(array($field('mu-show-date', lang('Show Date'), $input('mu-show-date', 'date', 'show_date', 'off'))), '6'),
                $col(array($field('mu-show-doors', lang('Doors'), $input('mu-show-doors', 'text', 'doors', 'off'), 'mb-3',
                    lang('The time the doors open, for example 20:00.'))), '6'),
                $col(array($field('mu-show-city', lang('City'), pg_cf_with_role($input('mu-show-city', 'text', 'city', 'off'), 'title'))), '6'),
                $col(array($field('mu-show-country', lang('Country'), pg_cf_with_role($select('mu-show-country', 'country', lang('Choose a country…'), $country_titles), 'category'))), '6'),
            ), '3'),
            $field('mu-show-venue', lang('Venue'), $input('mu-show-venue', 'text', 'venue', 'off')),
            $field('mu-show-support', lang('Support act'), $input('mu-show-support', 'text', 'support', 'off', false), 'mb-3',
                lang('The band playing before us. Leave empty when there is none.')),
            $form_step(2, lang('Tickets')),
            $field('mu-show-state', lang('Ticket State'), $select('mu-show-state', 'ticket_state', '', $ticket_state_titles, true, $ticket_state_titles[0])),
            $field('mu-show-tickets', lang('Ticket address'), $input('mu-show-tickets', 'url', 'ticket_url', 'url', false), 'mb-3',
                lang('The page of the venue or the ticket service. Leave empty to hide the button.')),
            $field('mu-show-featured', lang('Featured'), $select('mu-show-featured', 'featured', '', $mu_featured_choices, false, $mu_featured_choices[1], array('office_use_only' => true)),
                'mb-3', lang('A featured show to come is shown on the home page.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // The shows to come, filtered by the address (country), nearest first.
    'tour_list' => array(
        'page'   => 'tour',
        'slug'   => lang('form-list'),
        'config' => $mu_list_config('show_new', 'show', 'show_date', 'ASC',
            lang('No show planned here yet. Join the mailing list to hear about new dates first.'), array(
            'items_per_page' => 20,
            'filters'        => array(
                $mu_date_filter('show_date', 'is greater than or equal to'),
                $mu_filter('country', 'is equal to', '', 'country'),
            ),
        )),
        'tree'   => $show_list(),
    ),

    // The shows already played, latest first.
    'past_shows_list' => array(
        'page'   => 'past_shows',
        'slug'   => lang('form-list'),
        'config' => $mu_list_config('show_new', 'show', 'show_date', 'DESC', lang('No shows played yet.'), array(
            'items_per_page' => 20,
            'filters'        => array($mu_date_filter('show_date', 'is less than')),
        )),
        'tree'   => $show_list(),
    ),

    // The home page's featured shows to come, nearest first, three at most.
    'home_shows' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $mu_list_config('show_new', 'show', 'show_date', 'ASC', lang('No featured shows to come yet.'), array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'filters'        => array(
                $mu_date_filter('show_date', 'is greater than or equal to'),
                $mu_filter('featured', 'is equal to', $mu_featured_choices[0]),
            ),
        )),
        'tree'   => $show_list(),
    ),
);

// A fact of the show page: an icon, the label and the bound value.
$show_fact = function ($icon_name, $label, $token, $sample) use ($el, $icon, $span, $bind) {
    return $el('p', 'pg-hide-if-empty d-flex align-items-center gap-2 mb-2', $label, array(
        $icon($icon_name, 'text-primary'),
        $span($label, 'text-body-secondary', lang('Label')),
        $span($sample, 'pg-field-value fw-semibold', lang('Value'), $bind($token)),
    ));
};

// One show: the date, the city and the country, the venue, the doors, the
// support act, the ticket state and the ticket link.
$show_sample_key = 'istanbul';
$show_sample = $shows[$show_sample_key];
$show_not_found = lang('This show could not be found. It may have been moved or called off.');
$show_widgets['show_detail'] = array(
    'page'   => 'show',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:show_new}}',
        'access_control'      => 'public',
        'not_found_message'   => $show_not_found,
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $mu_not_found('bi-calendar-x', lang('Show not found'), $show_not_found, lang('See the tour dates'), 'tour'),
            $el('article', '', lang('Show Details'), array(
                $mu_toolbar('tour', lang('All Tour Dates')),
                $row(array(
                    $col(array(
                        $el('div', 'pmu-date fs-4 fw-bold text-primary mb-2', lang('Show Date'), array(), array_merge(
                            array('text' => $show_on($show_sample['weekday'], $show_sample['weeks'])), $bind('show_date'))),
                        $el('h1', 'pmu-display display-5 mb-1', lang('City'), array(
                            $span($show_sample['city'], '', lang('City'), $bind('city')),
                        )),
                        $para($countries[$show_sample['country']], 'lead text-body-secondary mb-4', $bind('country')),
                        $show_fact('bi-geo-alt', lang('Venue'), 'venue', $show_sample['venue']),
                        $show_fact('bi-door-open', lang('Doors'), 'doors', $show_sample['doors']),
                        $show_fact('bi-people', lang('Support act'), 'support', $show_sample['support']),
                    ), '', '7'),
                    $col(array(
                        $el('aside', 'card border-0 shadow-sm rounded-4', lang('Tickets'), array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $span($ticket_states[$show_sample['state']]['title'], 'badge rounded-pill ' . $ticket_states[$show_sample['state']]['badge'] . ' mb-3', lang('Ticket State'), $bind('ticket_state')),
                                $para(lang('Tickets are sold by the venue or a ticket service; the button opens its page.'), 'small text-body-secondary mb-3'),
                                $mu_record_link(lang('Tickets'), 'ticket_url', 'btn btn-primary rounded-pill w-100 mb-2'),
                                $link(lang('Join the Mailing List'), '{{page:newsletter}}', 'btn btn-outline-primary rounded-pill w-100'),
                            )),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $show_pages, 'widgets' => $show_widgets);
