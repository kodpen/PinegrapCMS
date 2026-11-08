<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Art Gallery", its exhibitions: the staff page that adds
 * an exhibition (the Exhibitions form, a collection, with the sample
 * exhibitions written in when it is made), the list of every exhibition
 * with its state filter, the exhibition's own page with its works and its
 * opening night, and the exhibitions the home page places. Included after
 * parts.php, cards.php and artists.php in the same scope (it uses the list
 * pieces of artists.php); returns array('pages' => ..., 'widgets' => ...).
 *
 * An exhibition is a record of the Exhibitions form. Its state is a pick
 * list whose answers are the words parts.php gives (Now On, Coming Soon,
 * Past), so a link with ?status= (the filter row, the notice, the footer)
 * filters the list by the words a visitor sees; the gallery moves an
 * exhibition on by editing its state. The dates and the opening night are
 * words: the gallery writes its own in each record, the sample never names
 * a year. A work's exhibition is a reference into this form, so the
 * exhibition's page lists its works (the dynamic value "shown record").
 * The home page shows the exhibitions now on and coming soon. The newest
 * added comes first.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$exhibition_pages = array();

// The exhibition cards of a list, in rows of three.
$exhibition_grid = function () use ($widget_root, $row, $loop, $exhibition_card) {
    return $widget_root(array($row(array($loop(array($exhibition_card()))), '4')));
};

// The artists of an exhibition in words, the way the record keeps them.
$exhibition_artists = function ($key) use ($exhibitions, $artists) {
    $names = array();
    foreach ($exhibitions[$key]['artists'] as $a) $names[] = $artists[$a]['name'];
    return implode(', ', $names);
};

// An installation view of the exhibition's page, left out when the record
// has none.
$installation_view = function ($n, $sample_key) use ($col, $optional_image, $photo) {
    return $col(array(
        $optional_image('photo_' . $n, 'title', 'w-100 h-100 object-fit-cover', 'ratio ratio-4x3 bg-body-tertiary pgl-frame',
            $photo('exhibition-' . $sample_key . '-' . $n, 1200, 900), lang('Installation View')),
    ), '6');
};

// ── Pages ───────────────────────────────────────────────────────────────

// Every exhibition, narrowed by state from the address.
$exhibition_pages[] = array(
    'key'              => 'exhibitions',
    'name'             => lang('exhibitions'),
    'folder'           => 'public',
    'title'            => lang('Exhibitions'),
    'meta_description' => lang('The gallery\'s exhibitions now on, coming soon and past, each with its artists, its works and its opening night.'),
    'tree'             => $page(array(
        $page_header(lang('Exhibitions'), lang('What is on the walls now, what opens next and what we have shown before. Every opening night is open to all.')),
        $el('section', 'py-5', lang('Exhibitions'), array(
            $container(array(
                $gl_filter_bar('exhibitions', lang('Status'), 'status', $status_titles),
                $widget('exhibitions_list'),
            )),
        ), $searchable('gl-exhibitions', lang('exhibitions, shows, now on, coming soon, past, opening night, private view'))),
    )),
);

// One exhibition: the record, then its works.
$exhibition_pages[] = array(
    'key'              => 'exhibition',
    'name'             => lang('exhibition'),
    'folder'           => 'public',
    'title'            => lang('Exhibition'),
    'meta_description' => lang('An exhibition at the gallery: its artists, the curator\'s text, the opening night and the works on show.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('exhibition_detail'),
        $el('section', 'py-5 border-top', lang('Works'), array(
            $container(array(
                $section_header(lang('On show'), lang('Works in the exhibition'), lang('All works'), '{{page:works}}'),
                $widget('exhibition_works'),
            )),
        )),
    )),
);

// The staff page that adds an exhibition. The form is the Exhibitions
// collection; the sample exhibitions are written in when the form is made,
// before the works that name them (pg_cf_seed_sample_records()).
$sample_exhibitions = array();
foreach ($exhibitions as $key => $x) {
    $sample_exhibitions[] = array(
        'title'   => $x['title'],
        'status'  => $exhibition_statuses[$x['status']],
        'artists' => $exhibition_artists($key),
        'dates'   => $x['dates'],
        'opening' => $x['opening'],
        'summary' => $x['summary'],
        'curator_text' => $x['text'],
        'photo_1' => $photo('exhibition-' . $key, 1600, 1200),
        'photo_2' => $photo('exhibition-' . $key . '-2', 1200, 900),
        'photo_3' => $photo('exhibition-' . $key . '-3', 1200, 900),
    );
}
$exhibition_pages[] = array(
    'key'     => 'exhibition_new',
    'name'    => lang('new-exhibition'),
    'folder'  => 'private',
    'title'   => lang('New Exhibition'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Exhibitions'),
        'collection'           => 1,
        'confirmation_message' => lang('The exhibition is published.'),
        'confirmation_page_id' => '{{tab:exhibitions}}',
        'sample_records'       => $sample_exhibitions,
        // The main picture is the exhibition's share image
        // (pg_cf_share_image_field_id()).
        'share_image_field'    => 'photo_1',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Exhibition'), lang('Add an exhibition with its artists, dates, opening night and pictures; it appears in the list as soon as you send it. Change its state from its Edit button as it opens and closes.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 border', lang('Form Card'), array($widget('exhibition_form'))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// ── Widgets ─────────────────────────────────────────────────────────────

$exhibition_widgets = array(

    // The staff's Exhibitions form: one exhibition. The state starts as
    // Coming Soon.
    'exhibition_form' => array(
        'page'   => 'exhibition_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The exhibition')),
            $field('gl-exhibition-title', lang('Title'), pg_cf_with_role($input('gl-exhibition-title', 'text', 'title', 'off'), 'title')),
            $row(array(
                $col(array($field('gl-exhibition-status', lang('Status'), pg_cf_with_role($select('gl-exhibition-status', 'status', '', $status_titles, true, $status_titles[1]), 'category'))), '6'),
                $col(array($field('gl-exhibition-artists', lang('Artists'), $input('gl-exhibition-artists', 'text', 'artists', 'off'), 'mb-3',
                    lang('Their names, separated by commas.'))), '6'),
            ), '3'),
            $field('gl-exhibition-dates', lang('Dates'), $input('gl-exhibition-dates', 'text', 'dates', 'off'), 'mb-3',
                sprintf(lang('In words, for example %s.'), $exhibitions['night_lines']['dates'])),
            $field('gl-exhibition-opening', lang('Opening Night'), $input('gl-exhibition-opening', 'text', 'opening', 'off'), 'mb-3',
                sprintf(lang('In words, for example %s.'), $exhibitions['night_lines']['opening'])),
            $form_step(2, lang('About the exhibition')),
            $field('gl-exhibition-summary', lang('Summary'), pg_cf_with_role($input('gl-exhibition-summary', 'text', 'summary', 'off'), 'description'), 'mb-3',
                lang('One sentence for the cards of the lists.')),
            $field('gl-exhibition-text', lang('Curator\'s Text'), $textarea('gl-exhibition-text', 'curator_text', 6)),
            $form_step(3, lang('Pictures')),
            $field('gl-exhibition-photo-1', lang('Main picture'), pg_cf_with_role($file('gl-exhibition-photo-1', 'photo_1', 'public', 'image/*'), 'media'), 'mb-3',
                lang('The picture of the cards and the share image.')),
            $row(array(
                $col(array($field('gl-exhibition-photo-2', lang('Installation view 1'), $file('gl-exhibition-photo-2', 'photo_2', 'public', 'image/*'))), '6'),
                $col(array($field('gl-exhibition-photo-3', lang('Installation view 2'), $file('gl-exhibition-photo-3', 'photo_3', 'public', 'image/*'))), '6'),
            ), '3'),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every exhibition, filtered by the address (state), the newest first.
    'exhibitions_list' => array(
        'page'   => 'exhibitions',
        'slug'   => lang('form-list'),
        'config' => $gl_record_list('exhibition_new', 'exhibition', lang('No exhibition in this list yet. Choose another, or see every exhibition.'), array(
            'items_per_page' => 12,
            'filters'        => array($gl_filter('status', 'is equal to', '', 'status')),
        )),
        'tree'   => $exhibition_grid(),
    ),

    // The home page's exhibitions: those now on and coming soon, three at
    // most.
    'home_exhibitions' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $gl_record_list('exhibition_new', 'exhibition', lang('No exhibition on now. See what we have shown before.'), array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'filters'        => array($gl_filter('status', 'is not equal to', $exhibition_statuses['past'])),
        )),
        'tree'   => $exhibition_grid(),
    ),

    // An exhibition's page: the works of the exhibition it shows, the
    // newest first.
    'exhibition_works' => array(
        'page'   => 'exhibition',
        'slug'   => lang('form-list'),
        'config' => $gl_record_list('work_new', 'work', lang('The works of this exhibition are not listed yet. Ask us for the list.'), array(
            'items_per_page' => 24,
            'filters'        => array(
                array('field' => 'exhibition', 'operator' => 'is equal to', 'value' => '', 'dynamic_value' => 'shown record', 'dynamic_value_attribute' => ''),
            ),
        )),
        'tree'   => $work_grid(),
    ),
);

// One exhibition: the main picture wide across the page, the state, the
// title, the artists and the dates under it; beside the curator's text an
// invitation to the opening night with the way to a private viewing of
// this exhibition (?exhibition=, its title); then the installation views.
$exhibition_sample_key = 'night_lines';
$exhibition_sample = $exhibitions[$exhibition_sample_key];
$exhibition_not_found = lang('This exhibition could not be found. The page may have moved.');
$exhibition_widgets['exhibition_detail'] = array(
    'page'   => 'exhibition',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:exhibition_new}}',
        'access_control'      => 'public',
        'not_found_message'   => $exhibition_not_found,
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $el('div', 'text-center py-5', lang('Not Found'), array(
                $icon('bi-easel2', 'display-5 text-body-tertiary'),
                $heading('h2', lang('Exhibition not found'), 'pgl-display h3 mt-3'),
                $para($exhibition_not_found, 'text-body-secondary mb-4', $bind('not_found')),
                $link(lang('See the Exhibitions'), '{{page:exhibitions}}', 'btn btn-primary rounded-0 px-4'),
            ), $show_when('record_not_found')),
            $el('article', '', lang('Exhibition'), array(
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-4', lang('Toolbar'), array(
                    $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                        $icon('bi-arrow-left'),
                        $span(lang('Exhibitions'), '', lang('Link Text')),
                    ), array('href' => '{{page:exhibitions}}')),
                    $edit_button(),
                )),
                $optional_image('photo_1', 'title', 'w-100 h-100 object-fit-cover', 'ratio ratio-21x9 bg-body-tertiary pgl-frame mb-4',
                    $photo('exhibition-' . $exhibition_sample_key, 1600, 1200), lang('Main Picture')),
                $el('header', 'd-flex flex-wrap align-items-end justify-content-between gap-3 pb-4 mb-5 border-bottom', lang('Exhibition Header'), array(
                    $el('div', '', lang('Title'), array(
                        $span($exhibition_statuses[$exhibition_sample['status']], 'badge rounded-0 text-bg-dark mb-3', lang('Status'), $bind('status')),
                        $heading('h1', $exhibition_sample['title'], 'pgl-display display-3 mb-1', $bind('title')),
                        $para($exhibition_artists($exhibition_sample_key), 'pgl-eyebrow text-uppercase fw-semibold text-primary mb-0', $bind('artists')),
                    )),
                    $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-0', lang('Dates'), array(
                        $icon('bi-calendar-range'),
                        $span($exhibition_sample['dates'], '', lang('Dates'), $bind('dates')),
                    )),
                )),
                $row(array(
                    $col(array(
                        $para($exhibition_sample['summary'], 'lead mb-4', $bind('summary')),
                        $el('div', 'pg-field-value lh-lg mb-0', lang('Curator\'s Text'), array(), array_merge(array('text' => $exhibition_sample['text']), $bind('curator_text'))),
                    ), '', '7'),
                    $col(array(
                        $el('aside', 'border border-2 p-4 text-center', lang('Opening Night Invitation'), array(
                            $icon('bi-stars', 'd-block fs-2 text-primary mb-2'),
                            $para(lang('You are invited'), 'pgl-eyebrow small text-uppercase fw-semibold text-body-secondary mb-2'),
                            $heading('h2', lang('Opening Night'), 'pgl-display h4 mb-2'),
                            $para($exhibition_sample['opening'], 'mb-3', $bind('opening')),
                            $para($gallery['address'], 'small text-body-secondary mb-4'),
                            // The viewing request starts with this
                            // exhibition chosen (?exhibition=, its title).
                            $link(lang('Book a Private Viewing'), '#', 'btn btn-primary rounded-0 w-100 mb-2', array(),
                                $gl_page_link('viewing', array('exhibition' => 'title'))),
                            $link(lang('Plan Your Visit'), '{{page:visit}}', 'btn btn-link link-body-emphasis'),
                        )),
                    ), '', '4', '', '12', 'offset-lg-1'),
                ), '4', '', 'mb-5'),
                $row(array(
                    $installation_view(2, $exhibition_sample_key),
                    $installation_view(3, $exhibition_sample_key),
                ), '3'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $exhibition_pages, 'widgets' => $exhibition_widgets);
