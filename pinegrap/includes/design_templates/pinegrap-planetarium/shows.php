<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Planetarium", its dome shows: the staff page that adds
 * a show (the Dome Shows form, a collection, with the sample shows written
 * in when it is made), the show list from today on, filtered by audience
 * (?audience=), the show's page and today's featured shows the home page
 * places (home_shows). Included after parts.php, cards.php and
 * pages_public.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...). It also leaves the list pieces
 * ($ppm_filter, $ppm_record_list, $ppm_featured_choices) for nights.php.
 *
 * Every sample show is dated from the day the template opens ($show_on),
 * and the lists keep only the shows from today on (the current date
 * filter), nearest first. The cards are the show cards of cards.php; the
 * list page opens like the planetarium-shows block, the audiences as the
 * links over the shows.
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

// ── List pieces (nights.php uses them too) ──────────────────────────────

// A filter row of a list. $param reads the value from the visitor's
// address (?audience=); an empty one leaves the list whole.
$ppm_filter = function ($field, $operator, $value, $param = '', $dynamic = '') {
    $row = array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $param !== '' ? 'url parameter' : $dynamic);
    if ($param !== '') $row['dynamic_value_attribute'] = $param;
    return $row;
};

// A list of a collection's records: its form page, its record page, the
// order and the words shown when nothing matches.
$ppm_record_list = function ($form_page, $detail_page, $empty, $more, $order = 'title', $direction = 'ASC') {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:' . $form_page . '}}',
        'detail_page_id'      => '{{tab:' . $detail_page . '}}',
        'order_by_field'      => $order,
        'order_by_direction'  => $direction,
        'empty_message'       => $empty,
    ), $more);
};

// The answers of an office field that puts a record on the home page.
$ppm_featured_choices = array(lang('Yes'), lang('No'));

// ── Pieces ──────────────────────────────────────────────────────────────

// The sample shows as records of the Dome Shows form, each dated from the
// day the template opens; the length, the age and the details as words.
$sample_shows = array();
foreach ($shows as $key => $s) {
    $sample_shows[] = array(
        'title'     => $s['title'],
        'show_date' => $show_on($s['day']),
        'showtimes' => $s['times'],
        'length'    => $length_text($s['length']),
        'age'       => $age_text($s['age']),
        'language'  => $s['language'],
        'audience'  => $show_audience($key),
        'summary'   => $s['summary'],
        'details'   => $details_text($s['details']),
        'photo_1'   => $photo('show-' . $key, 1200, 675),
        'featured'  => $s['featured'] ? $ppm_featured_choices[0] : $ppm_featured_choices[1],
    );
}

// The shows from today on, nearest first.
$show_list = function ($empty, $more) use ($ppm_record_list, $ppm_filter) {
    return $ppm_record_list('show_new', 'show', $empty, array_merge(array(
        'filters' => array($ppm_filter('show_date', 'is greater than or equal to', '', '', 'current date')),
    ), $more), 'show_date', 'ASC');
};

// The audiences over the show list, as the planetarium-shows block puts
// its days over the shows: every show, then one link per audience in the
// list's own words.
$audience_links = function () use ($el, $link, $attr, $audience_titles, $query) {
    $items = array(
        $el('li', '', lang('Audience Item'), array(
            $link(lang('All Shows'), '{{page:shows}}', 'btn btn-sm rounded-pill btn-primary'),
        )),
    );
    foreach ($audience_titles as $title) {
        $items[] = $el('li', '', lang('Audience Item'), array(
            $link($title, '{{page:shows}}?' . $query(array('audience' => $title)), 'btn btn-sm rounded-pill btn-outline-secondary'),
        ));
    }
    return $el('nav', 'mt-4', lang('Audiences'), array(
        $el('ul', 'list-unstyled d-flex flex-wrap gap-2 mb-0', lang('Audience List'), $items),
    ), array('_attrs' => array($attr('aria-label', lang('Shows by audience')))));
};

// ── Pages ───────────────────────────────────────────────────────────────

// The dome shows from today on, nearest first, by audience; who each kind
// of show is for, and the ways to the tickets and the school visits.
$show_pages[] = array(
    'key'              => 'shows',
    'name'             => lang('dome-shows'),
    'folder'           => 'public',
    'title'            => lang('Dome Shows'),
    'meta_description' => lang('Every dome show from today on with its showtimes, length, minimum age and language, for families, schools and adults.'),
    'tree'             => $page(array(
        $page_header(lang('Dome Shows'), lang('Full-dome shows under an eighteen-metre sky, narrated live by our astronomers. The nearest first.'), array(
            $audience_links(),
        )),
        $el('section', 'py-5', lang('Programme'), array(
            $container(array(
                $section_header(lang('The programme'), lang('Each show with its showtimes of the day. Doors open ten minutes before each show.'),
                    lang('Tickets and Prices'), '{{page:tickets}}'),
                $widget('shows_list'),
            )),
        ), $searchable('plm-shows', lang('dome show, showtime, programme, planetarium show, families, schools, adults, children'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Audiences'), array(
            $container(array(
                $section_header(lang('A show for every age'), lang('Choose the shows made for you; the minimum age is on each one.')),
                $rise($row(array(
                    $audience_card('families'),
                    $audience_card('little'),
                    $audience_card('schools'),
                    $audience_card('adults'),
                ), '3')),
                $el('div', 'd-flex flex-wrap align-items-center gap-3 mt-4', lang('Footer'), array(
                    $link(lang('Request a School Visit'), '{{page:visit_request}}', 'btn btn-primary'),
                    $link(lang('Plan Your Visit'), '{{page:visit}}', 'btn btn-outline-primary'),
                    $para(lang('Late arrivals cannot enter once the dome goes dark.'), 'small text-body-secondary mb-0'),
                )),
            )),
        )),
    )),
);

// One dome show.
$show_pages[] = array(
    'key'              => 'show',
    'name'             => lang('dome-show'),
    'folder'           => 'public',
    'title'            => lang('Dome Show'),
    'meta_description' => lang('A dome show of the planetarium: its date and showtimes, who it is for, its length, minimum age and language.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('show_detail'),
    )),
);

// The staff page that adds a dome show. The form is the Dome Shows
// collection; the sample shows are written in when the form is made, so
// the lists do not open empty (pg_cf_seed_sample_records()).
$show_pages[] = array(
    'key'     => 'show_new',
    'name'    => lang('new-dome-show'),
    'folder'  => 'private',
    'title'   => lang('New Dome Show'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Dome Shows'),
        'collection'           => 1,
        'confirmation_message' => lang('The dome show is published.'),
        'confirmation_page_id' => '{{tab:shows}}',
        'sample_records'       => $sample_shows,
        'share_image_field'    => 'photo_1',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Dome Show'), lang('Add a show on its day with its showtimes; it stays in the programme until its day has passed.'), array(), false),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm p-4', lang('Form Card'), array($widget('show_form'))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// ── Widgets ─────────────────────────────────────────────────────────────

$solar = $shows['solar_tour'];
$show_widgets = array(

    // The staff's Dome Shows form: one show on one day, its showtimes as
    // words, who it is for and whether the home shows it on its day.
    'show_form' => array(
        'page'   => 'show_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The show')),
            $field('plm-show-title', lang('Name'), pg_cf_with_role($input('plm-show-title', 'text', 'title', 'off'), 'title')),
            $row(array(
                $col(array($field('plm-show-date', lang('Date'), $input('plm-show-date', 'date', 'show_date', 'off'))), '4'),
                $col(array($field('plm-show-times', lang('Showtimes'), $input('plm-show-times', 'text', 'showtimes', 'off'), 'mb-3',
                    sprintf(lang('In words, for example %s.'), $solar['times']))), '8'),
            ), '3'),
            $row(array(
                $col(array($field('plm-show-audience', lang('Audience'), pg_cf_with_role($select('plm-show-audience', 'audience', lang('Choose an audience'), $audience_titles), 'category'))), '6'),
                $col(array($field('plm-show-language', lang('Language'), $input('plm-show-language', 'text', 'language', 'off'), 'mb-3',
                    sprintf(lang('In words, for example %s.'), $solar['language']))), '6'),
            ), '3'),
            $row(array(
                $col(array($field('plm-show-length', lang('Length'), $input('plm-show-length', 'text', 'length', 'off'), 'mb-3',
                    sprintf(lang('In words, for example %s.'), $length_text($solar['length'])))), '6'),
                $col(array($field('plm-show-age', lang('Minimum Age'), $input('plm-show-age', 'text', 'age', 'off'), 'mb-3',
                    sprintf(lang('In words, for example %s.'), $age_text($solar['age'])))), '6'),
            ), '3'),
            $field('plm-show-summary', lang('Summary'), pg_cf_with_role($textarea('plm-show-summary', 'summary', 3), 'description')),
            $field('plm-show-details', lang('Details'), $textarea('plm-show-details', 'details', 4, false), 'mb-3', lang('One point on each line.')),
            $form_step(2, lang('Photo')),
            $field('plm-show-photo', lang('Photo'), pg_cf_with_role($file('plm-show-photo', 'photo_1', 'public', 'image/*'), 'media'), 'mb-3',
                lang('The picture of the cards and the share image.')),
            $field('plm-show-featured', lang('Featured'), $select('plm-show-featured', 'featured', '', $ppm_featured_choices, false, $ppm_featured_choices[0], array('office_use_only' => true)),
                'mb-3', lang('A featured show is shown on the home page on its day.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // The shows from today on, nearest first, narrowed by the audience in
    // the address, with a search over the words of the shows.
    'shows_list' => array(
        'page'   => 'shows',
        'slug'   => lang('form-list'),
        'config' => $show_list(lang('No dome show matches this choice. Choose another audience, or see every show.'), array(
            'items_per_page' => 12,
            'filters'        => array(
                $ppm_filter('show_date', 'is greater than or equal to', '', '', 'current date'),
                $ppm_filter('audience', 'is equal to', '', 'audience'),
            ),
            'search_enabled' => true,
            'search_fields'  => array('title', 'summary', 'language'),
            'search_width'   => 'full',
            'search_label'   => lang('Search the shows: a planet, a title...'),
        )),
        'tree'   => $widget_root(array($row(array($loop(array($show_card()))), '4'))),
    ),

    // The home page's shows: today's featured ones, three at most.
    'home_shows' => array(
        'page'   => 'home',
        'slug'   => lang('todays-shows'),
        'config' => $show_list(lang('No dome show today. The programme lists the coming days.'), array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'filters'        => array(
                $ppm_filter('show_date', 'is equal to', '', '', 'current date'),
                $ppm_filter('featured', 'is equal to', $ppm_featured_choices[0]),
            ),
        )),
        'tree'   => $widget_root(array($row(array($loop(array($show_card()))), '4'))),
    ),
);

// One show: the still, the audience, the title, the summary and the
// details, beside the facts on a night card with the ways to the tickets
// and, for a class, to the school visit request.
$show_sample_key = 'solar_tour';
$show_not_found = lang('This dome show could not be found. It may have passed, or the page was renamed.');
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
            $el('div', 'text-center py-5', lang('Not Found'), array(
                $icon('bi-calendar-x', 'display-5 text-body-secondary'),
                $heading('h2', lang('Dome show not found'), 'ppm-display h3 mt-3'),
                $para($show_not_found, 'text-body-secondary mb-4', $bind('not_found')),
                $link(lang('See the dome shows'), '{{page:shows}}', 'btn btn-primary px-4'),
            ), $show_when('record_not_found')),
            $el('article', '', lang('Dome Show'), array(
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-4', lang('Toolbar'), array(
                    $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                        $icon('bi-arrow-left'),
                        $span(lang('Dome Shows'), '', lang('Link Text')),
                    ), array('href' => '{{page:shows}}')),
                    $edit_button(),
                )),
                $row(array(
                    $col(array(
                        $optional_image('photo_1', 'title', 'w-100 h-100 object-fit-cover', 'ratio ratio-16x9 rounded-4 overflow-hidden bg-body-tertiary mb-4',
                            $photo('show-' . $show_sample_key, 1200, 675), lang('Photo')),
                        $span($show_audience($show_sample_key), 'ppm-eyebrow d-block small text-uppercase fw-semibold text-primary mb-2', lang('Audience'), $bind('audience')),
                        $heading('h1', $solar['title'], 'ppm-display display-5 fw-semibold mb-3', $bind('title')),
                        $para($solar['summary'], 'lead text-body-secondary mb-4', $bind('summary')),
                        $el('div', 'pg-hide-if-empty', lang('Details'), array(
                            $heading('h2', lang('Good to know'), 'ppm-display h5 mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $details_text($solar['details'])), $bind('details'))),
                        )),
                    ), '', '7'),
                    $col(array(
                        $night(array(
                            $el('div', 'p-4', lang('Card Body'), array(
                                $heading('h2', lang('Showtimes'), 'ppm-display h5 mb-1'),
                                $para($solar['times'], 'ppm-time fs-4 fw-semibold mb-3', $bind('showtimes')),
                                $show_facts($show_sample_key, 'list-unstyled small mb-4'),
                                $el('div', 'd-grid gap-2', lang('Buttons'), array(
                                    $link(lang('Tickets and Prices'), '{{page:tickets}}', 'btn btn-primary'),
                                    $link(lang('Request a School Visit'), '{{page:visit_request}}?' . $query(array('visit' => $visits['dome']['title'])), 'btn btn-outline-primary'),
                                )),
                            )),
                        ), 'rounded-4 overflow-hidden', lang('Show Facts Card')),
                    ), '', '5'),
                ), '4', '', 'g-lg-5'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $show_pages, 'widgets' => $show_widgets);
