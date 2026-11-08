<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Planetarium", its telescope nights: the staff page that
 * adds a night (the Telescope Nights form, a collection, with the sample
 * nights written in when it is made), the night list from today on, the
 * night's page with its status and the next nights the home page places
 * (home_nights). Included after parts.php, cards.php, pages_public.php and
 * shows.php in the same scope (it reads $ppm_filter and $ppm_record_list
 * from shows.php); returns array('pages' => ..., 'widgets' => ...).
 *
 * Every sample night is dated from the day the template opens ($show_on),
 * and the lists keep only the nights from today on, nearest first. The
 * status (On, Called Off, Moved) is an office field: the staff call each
 * night on the day in the panel, never a weather feed, and the lists and
 * the night's page show their word.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$night_pages = array();

// ── Pieces ──────────────────────────────────────────────────────────────

// The sample nights as records of the Telescope Nights form, each dated
// from the day the template opens; the target and the places as words.
$sample_nights = array();
foreach ($nights as $key => $n) {
    $sample_nights[] = array(
        'title'      => $n['title'],
        'night_date' => $show_on($n['day']),
        'start_time' => $n['time'],
        'target'     => $n['target'],
        'place'      => $night_place($key),
        'places'     => $n['places'],
        'summary'    => $n['summary'],
        'photo_1'    => $photo('night-' . $key, 1200, 675),
        'status'     => $night_status($key),
    );
}

// The nights from today on, nearest first.
$night_list = function ($empty, $more) use ($ppm_record_list, $ppm_filter) {
    return $ppm_record_list('night_new', 'night', $empty, array_merge(array(
        'filters' => array($ppm_filter('night_date', 'is greater than or equal to', '', '', 'current date')),
    ), $more), 'night_date', 'ASC');
};

// What each status means to a visitor, by the status pill of parts.php.
$status_meanings = array(
    'on'    => lang('The telescopes open at the start time; come ten minutes early.'),
    'off'   => lang('The sky is overcast: your ticket moves to the next night.'),
    'moved' => lang('The night moves to another date; the new date is on its page.'),
);

// ── Pages ───────────────────────────────────────────────────────────────

// The telescope nights from today on, nearest first, with the meaning of
// each status and the ways to the tickets and the questions.
$status_items = array();
foreach ($night_statuses as $key => $s) {
    $status_items[] = $el('li', 'd-flex flex-wrap align-items-center gap-2 py-2 border-bottom', lang('Status Item'), array(
        $status_pill($key),
        $span($status_meanings[$key], 'small text-body-secondary', lang('Text')),
    ));
}
$night_pages[] = array(
    'key'              => 'nights',
    'name'             => lang('telescope-nights'),
    'folder'           => 'public',
    'title'            => lang('Telescope Nights'),
    'meta_description' => lang('Every telescope night from today on with its start time, the object of the night, the place, the places left and whether it is on.'),
    'tree'             => $page(array(
        $page_header(lang('Telescope Nights'), lang('Clear nights at the eyepiece with our astronomers, on the roof, in the dome or at the dark-sky site. The nearest first.')),
        $el('section', 'py-5', lang('Programme'), array(
            $container(array(
                $section_header(lang('The coming nights'), lang('Each night with its start time and the object of the night. The status is set by our astronomers on the day.'),
                    lang('Tickets and Prices'), '{{page:tickets}}'),
                $widget('nights_list'),
            )),
        ), $searchable('plm-nights', lang('telescope night, stargazing, eyepiece, observing, star party, dark-sky site, called off'))),
        $night(array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Is tonight on?'), 'ppm-display h2 fw-semibold mb-2'),
                        $para(lang('A telescope night is called off by 17:00 if the sky is overcast; your ticket moves to the next one.'), 'text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Tonight\'s Sky'), '{{page:tonight}}', 'btn btn-primary'),
                            $link(lang('What is a telescope night?'), '{{page:faq}}#plm-faq-nights', 'btn btn-outline-secondary'),
                        )),
                    ), '', '5'),
                    $col(array(
                        $el('ul', 'list-unstyled mb-0', lang('Status List'), $status_items),
                    ), '', '7'),
                ), '4', 'center'),
            )),
        ), 'py-5', lang('Night Status')),
    )),
);

// One telescope night.
$night_pages[] = array(
    'key'              => 'night',
    'name'             => lang('telescope-night'),
    'folder'           => 'public',
    'title'            => lang('Telescope Night'),
    'meta_description' => lang('A telescope night of the observatory: its date and start time, the object of the night, the place, the places left and its status.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('night_detail'),
    )),
);

// The staff page that adds a telescope night. The form is the Telescope
// Nights collection; the sample nights are written in when the form is
// made, so the lists do not open empty (pg_cf_seed_sample_records()).
$night_pages[] = array(
    'key'     => 'night_new',
    'name'    => lang('new-telescope-night'),
    'folder'  => 'private',
    'title'   => lang('New Telescope Night'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Telescope Nights'),
        'collection'           => 1,
        'confirmation_message' => lang('The telescope night is published.'),
        'confirmation_page_id' => '{{tab:nights}}',
        'sample_records'       => $sample_nights,
        'share_image_field'    => 'photo_1',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Telescope Night'), lang('Add a night on its date; call it On, Called Off or Moved in the panel on the day.'), array(), false),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm p-4', lang('Form Card'), array($widget('night_form'))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// ── Widgets ─────────────────────────────────────────────────────────────

$saturn = $nights['saturn'];
$night_widgets = array(

    // The staff's Telescope Nights form: one night on one date, its start,
    // its object, the place and the places left; the status is the
    // office's field, On until the staff call it otherwise.
    'night_form' => array(
        'page'   => 'night_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The night')),
            $field('plm-night-title', lang('Name'), pg_cf_with_role($input('plm-night-title', 'text', 'title', 'off'), 'title')),
            $row(array(
                $col(array($field('plm-night-date', lang('Date'), $input('plm-night-date', 'date', 'night_date', 'off'))), '6'),
                $col(array($field('plm-night-start', lang('Start'), $input('plm-night-start', 'text', 'start_time', 'off'), 'mb-3',
                    sprintf(lang('In words, for example %s.'), $saturn['time']))), '6'),
            ), '3'),
            $field('plm-night-target', lang('Target'), $input('plm-night-target', 'text', 'target', 'off'), 'mb-3',
                sprintf(lang('In words, for example %s.'), $saturn['target'])),
            $row(array(
                $col(array($field('plm-night-place', lang('Place'), pg_cf_with_role($select('plm-night-place', 'place', lang('Choose a place'), $place_titles), 'category'))), '6'),
                $col(array($field('plm-night-places', lang('Places'), $input('plm-night-places', 'text', 'places', 'off'), 'mb-3',
                    sprintf(lang('In words, for example %s.'), $saturn['places']))), '6'),
            ), '3'),
            $field('plm-night-summary', lang('Summary'), pg_cf_with_role($textarea('plm-night-summary', 'summary', 3), 'description')),
            $form_step(2, lang('Photo')),
            $field('plm-night-photo', lang('Photo'), pg_cf_with_role($file('plm-night-photo', 'photo_1', 'public', 'image/*'), 'media'), 'mb-3',
                lang('The picture of the cards and the share image.')),
            $field('plm-night-status', lang('Status'), $select('plm-night-status', 'status', '', $night_status_titles, false, $night_status_titles[0], array('office_use_only' => true)),
                'mb-3', lang('Called by the staff on the day: On, Called Off or Moved.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // The nights from today on, nearest first, with a search over the
    // words of the nights.
    'nights_list' => array(
        'page'   => 'nights',
        'slug'   => lang('form-list'),
        'config' => $night_list(lang('No telescope night is planned yet. Follow the news for the next one.'), array(
            'items_per_page' => 12,
            'search_enabled' => true,
            'search_fields'  => array('title', 'target', 'summary'),
            'search_width'   => 'full',
            'search_label'   => lang('Search the nights: a planet, a cluster...'),
        )),
        'tree'   => $widget_root(array($row(array($loop(array($night_card()))), '4'))),
    ),

    // The home page's nights: the next three from today on.
    'home_nights' => array(
        'page'   => 'home',
        'slug'   => lang('next-nights'),
        'config' => $night_list(lang('No telescope night is planned yet. Follow the news for the next one.'), array(
            'items_per_page' => 3,
            'max_results'    => 3,
        )),
        'tree'   => $widget_root(array($row(array($loop(array($night_card()))), '4'))),
    ),
);

// One night: the photo, the place, the title, the summary and the call-off
// rule, beside the facts and the status on a night card with the ways to
// the tickets and tonight's sky.
$night_sample_key = 'saturn';
$night_not_found = lang('This telescope night could not be found. It may have passed, or the page was renamed.');
$night_widgets['night_detail'] = array(
    'page'   => 'night',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:night_new}}',
        'access_control'      => 'public',
        'not_found_message'   => $night_not_found,
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $el('div', 'text-center py-5', lang('Not Found'), array(
                $icon('bi-calendar-x', 'display-5 text-body-secondary'),
                $heading('h2', lang('Telescope night not found'), 'ppm-display h3 mt-3'),
                $para($night_not_found, 'text-body-secondary mb-4', $bind('not_found')),
                $link(lang('See the telescope nights'), '{{page:nights}}', 'btn btn-primary px-4'),
            ), $show_when('record_not_found')),
            $el('article', '', lang('Telescope Night'), array(
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-4', lang('Toolbar'), array(
                    $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                        $icon('bi-arrow-left'),
                        $span(lang('Telescope Nights'), '', lang('Link Text')),
                    ), array('href' => '{{page:nights}}')),
                    $edit_button(),
                )),
                $row(array(
                    $col(array(
                        $optional_image('photo_1', 'title', 'w-100 h-100 object-fit-cover', 'ratio ratio-16x9 rounded-4 overflow-hidden bg-body-tertiary mb-4',
                            $photo('night-' . $night_sample_key, 1200, 675), lang('Photo')),
                        $span($night_place($night_sample_key), 'ppm-eyebrow d-block small text-uppercase fw-semibold text-primary mb-2', lang('Place'), $bind('place')),
                        $heading('h1', $saturn['title'], 'ppm-display display-5 fw-semibold mb-3', $bind('title')),
                        $para($saturn['summary'], 'lead text-body-secondary mb-4', $bind('summary')),
                        $heading('h2', lang('If the sky is cloudy'), 'ppm-display h5 mb-2'),
                        $para(lang('A telescope night is called off by 17:00 if the sky is overcast; your ticket moves to the next one.'), 'text-body-secondary mb-0'),
                    ), '', '7'),
                    $col(array(
                        $night(array(
                            $el('div', 'p-4', lang('Card Body'), array(
                                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Card Top'), array(
                                    $heading('h2', lang('Start'), 'ppm-display h5 mb-0'),
                                    $night_status_badge($night_status($night_sample_key)),
                                )),
                                $para($saturn['time'], 'ppm-time fs-4 fw-semibold mb-3', $bind('start_time')),
                                $night_facts($night_sample_key, 'list-unstyled small mb-4'),
                                $el('div', 'd-grid gap-2', lang('Buttons'), array(
                                    $link(lang('Tickets and Prices'), '{{page:tickets}}', 'btn btn-primary'),
                                    $link(lang('Tonight\'s Sky'), '{{page:tonight}}', 'btn btn-outline-primary'),
                                )),
                            )),
                        ), 'rounded-4 overflow-hidden', lang('Night Facts Card')),
                    ), '', '5'),
                ), '4', '', 'g-lg-5'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $night_pages, 'widgets' => $night_widgets);
