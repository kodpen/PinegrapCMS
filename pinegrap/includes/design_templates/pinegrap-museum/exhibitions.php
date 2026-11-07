<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Museum", its exhibitions: the staff page that adds an
 * exhibition (the Exhibitions form, a collection, with the sample
 * exhibitions written in when it is made), the list of every exhibition
 * with its state filter, the exhibition's own page and the list the home
 * page places. Included after parts.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * An exhibition is a record of the Exhibitions form. Its state is a pick
 * list whose answers are the words parts.php gives (Now On, Coming Soon,
 * Past Exhibitions), so a link with ?status= (the filter row, the footer,
 * the exhibition page) filters the list by the words a visitor sees; the
 * museum moves an exhibition on by editing its state. The dates are words:
 * the museum writes its own in each record, the sample never names a year.
 * The home page shows the exhibitions on now. The newest added comes first.
 *
 * The pieces under "Pieces" ($mz_filter, $mz_page_link, $mz_filter_bar,
 * $mz_record_list, $mz_featured_choices) serve the template's other
 * collections too, included after this file in the same scope.
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

// ── Pieces ──────────────────────────────────────────────────────────────

// A filter row of a list's settings: the field, how it compares and the
// value, or where the value comes from (a URL parameter, left out while
// the parameter is not in the address).
$mz_filter = function ($field, $operator, $value, $param = '') {
    $row = array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $param !== '' ? 'url parameter' : '');
    if ($param !== '') $row['dynamic_value_attribute'] = $param;
    return $row;
};
// The answers of a Featured list: the first marks a record for the home.
$mz_featured_choices = array(lang('Yes'), lang('No'));

// A link to a template page that sends values of the record it is drawn
// in: $params maps the address name to the field it takes (the renderer's
// __page_link).
$mz_page_link = function ($page_key, $params) {
    $list = array();
    foreach ($params as $name => $token) $list[] = array('name' => $name, 'token' => $token);
    return array(
        '_bindings'   => array('href' => '__page_link'),
        '_bindParams' => array('href' => array('page_id' => '{{tab:' . $page_key . '}}', 'params' => $list)),
    );
};

// A list of a collection's records: its form page, its record page, the
// newest first, and the words shown when nothing matches.
$mz_record_list = function ($form_page, $detail_page, $empty, $more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:' . $form_page . '}}',
        'detail_page_id'      => '{{tab:' . $detail_page . '}}',
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
        'empty_message'       => $empty,
    ), $more);
};

// The links above a list that narrow it: one per answer, each opening the
// list page with the filter set.
$mz_filter_bar = function ($page_key, $label, $param, $values) use ($el, $link, $span, $query) {
    $children = array($span($label, 'small fw-semibold text-body-secondary me-1', lang('Label')));
    $children[] = $link(lang('All'), '{{page:' . $page_key . '}}', 'btn btn-sm btn-outline-secondary');
    foreach ($values as $v) {
        $children[] = $link($v, '{{page:' . $page_key . '}}?' . $query(array($param => $v)), 'btn btn-sm btn-outline-secondary');
    }
    return $el('nav', 'd-flex flex-wrap align-items-center gap-2 mb-4', lang('Filters'), $children);
};

// The exhibition cards of a list, in rows of three.
$exhibition_grid = function () use ($widget_root, $row, $loop, $exhibition_card) {
    return $widget_root(array($row(array($loop(array($exhibition_card()))), '4')));
};

// ── Pages ───────────────────────────────────────────────────────────────

// Every exhibition, narrowed by state from the address.
$exhibition_pages[] = array(
    'key'              => 'exhibitions',
    'name'             => lang('exhibitions'),
    'folder'           => 'public',
    'title'            => lang('Exhibitions'),
    'meta_description' => lang('The exhibitions on now at the museum, the ones coming soon and the ones we have shown before.'),
    'tree'             => $page(array(
        $page_header(lang('Exhibitions'), lang('What is on now, what is coming and what we have shown before. Choose a state to narrow the list.')),
        $el('section', 'py-5', lang('Exhibitions'), array(
            $container(array(
                $mz_filter_bar('exhibitions', lang('Show'), 'status', $exhibition_statuses),
                $widget('exhibitions_list'),
            )),
        ), $searchable('mz-exhibitions', lang('what\'s on, current, upcoming, past, show, display'))),
    )),
);

// One exhibition: its record.
$exhibition_pages[] = array(
    'key'              => 'exhibition',
    'name'             => lang('exhibition'),
    'folder'           => 'public',
    'title'            => lang('Exhibition'),
    'meta_description' => lang('An exhibition at the museum: when it is on, where to find it and what you will see.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('exhibition_detail'),
    )),
);

// The staff page that adds an exhibition. The form is the Exhibitions
// collection; the sample exhibitions are written in when the form is made,
// so the lists do not open empty (pg_cf_seed_sample_records()).
$sample_exhibitions = array();
foreach ($exhibitions as $key => $x) {
    $sample_exhibitions[] = array(
        'title'   => $x['title'],
        'status'  => $exhibition_status($key),
        'when'    => $x['when'],
        'gallery' => $x['gallery'],
        'curator' => $x['curator'],
        'summary' => $x['summary'],
        'details' => $details_text($x['details']),
        'photo_1' => $photo('exhibition-' . $key, 1200, 900),
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
        // The first photo is the exhibition's share image
        // (pg_cf_share_image_field_id()).
        'share_image_field'    => 'photo_1',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Exhibition'), lang('Add the exhibition with its dates, gallery and photos; it appears in the list as soon as you send it. Change its state from its Edit button as it opens and closes.')),
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
    // Coming Soon, the usual state of a new exhibition.
    'exhibition_form' => array(
        'page'   => 'exhibition_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The exhibition')),
            $field('mz-exhibition-title', lang('Exhibition Title'), pg_cf_with_role($input('mz-exhibition-title', 'text', 'title', 'off'), 'title')),
            $row(array(
                $col(array($field('mz-exhibition-status', lang('Status'), pg_cf_with_role($select('mz-exhibition-status', 'status', '', $exhibition_statuses, true, $exhibition_status_titles['soon']), 'category'))), '6'),
                $col(array($field('mz-exhibition-when', lang('When'), $input('mz-exhibition-when', 'text', 'when', 'off'), 'mb-3',
                    sprintf(lang('The dates in words, for example %s.'), $exhibitions['light']['when']))), '6'),
            ), '3'),
            $row(array(
                $col(array($field('mz-exhibition-gallery', lang('Gallery'), $input('mz-exhibition-gallery', 'text', 'gallery', 'off'))), '6'),
                $col(array($field('mz-exhibition-curator', lang('Curator'), $input('mz-exhibition-curator', 'text', 'curator', 'off', false))), '6'),
            ), '3'),
            $form_step(2, lang('Words')),
            $field('mz-exhibition-summary', lang('Summary'), pg_cf_with_role($textarea('mz-exhibition-summary', 'summary', 2), 'description'), 'mb-3',
                lang('One or two lines for the cards of the lists.')),
            $field('mz-exhibition-details', lang('What You Will See'), $textarea('mz-exhibition-details', 'details', 5, false), 'mb-3', lang('One point on each line.')),
            $form_step(3, lang('Photos')),
            $field('mz-exhibition-photo-1', lang('Main Photo'), pg_cf_with_role($file('mz-exhibition-photo-1', 'photo_1', 'public', 'image/*'), 'media'), 'mb-3',
                lang('The photo of the cards and the share image.')),
            $row(array(
                $col(array($field('mz-exhibition-photo-2', lang('Second Photo'), $file('mz-exhibition-photo-2', 'photo_2', 'public', 'image/*'))), '6'),
                $col(array($field('mz-exhibition-photo-3', lang('Third Photo'), $file('mz-exhibition-photo-3', 'photo_3', 'public', 'image/*'))), '6'),
            ), '3'),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every exhibition, filtered by the address (state), the newest first,
    // with a search over the words of the exhibitions.
    'exhibitions_list' => array(
        'page'   => 'exhibitions',
        'slug'   => lang('form-list'),
        'config' => $mz_record_list('exhibition_new', 'exhibition', lang('No exhibition in this list yet. Choose another, or see all exhibitions.'), array(
            'items_per_page' => 12,
            'filters'        => array($mz_filter('status', 'is equal to', '', 'status')),
            'search_enabled' => true,
            'search_fields'  => array('title', 'status', 'when', 'gallery', 'curator', 'summary', 'details'),
            'search_width'   => 'full',
            'search_label'   => lang('Search the exhibitions: an artist, a gallery, a theme...'),
        )),
        'tree'   => $exhibition_grid(),
    ),

    // The home page's exhibitions on now, three at most.
    'home_exhibitions' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $mz_record_list('exhibition_new', 'exhibition', lang('No exhibition is on just now. See what is coming soon.'), array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'filters'        => array($mz_filter('status', 'is equal to', $exhibition_status_titles['now'])),
        )),
        'tree'   => $exhibition_grid(),
    ),
);

// One exhibition: the state, the title, the summary, when, where and who
// chose the works, the photos, what you will see and beside them the
// opening hours, the way to book a timed entry and the other exhibitions
// of the same state.
$exhibition_sample_key = 'light';
$exhibition_sample = $exhibitions[$exhibition_sample_key];
$exhibition_not_found = lang('This exhibition could not be found. It may have closed or been taken off the list.');
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
                $icon('bi-easel', 'display-5 text-body-tertiary'),
                $heading('h2', lang('Exhibition not found'), 'h3 fw-semibold mt-3'),
                $para($exhibition_not_found, 'text-body-secondary mb-4', $bind('not_found')),
                $link(lang('All Exhibitions'), '{{page:exhibitions}}', 'btn btn-primary px-4'),
            ), $show_when('record_not_found')),
            $el('article', '', lang('Exhibition'), array(
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                    $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                        $icon('bi-arrow-left'),
                        $span(lang('All Exhibitions'), '', lang('Link Text')),
                    ), array('href' => '{{page:exhibitions}}')),
                    $edit_button(),
                )),
                $el('div', 'mb-2', lang('Status'), array($status_badge($exhibition_status($exhibition_sample_key)))),
                $heading('h1', $exhibition_sample['title'], 'pmz-display display-5 mb-2', $bind('title')),
                $para($exhibition_sample['summary'], 'lead text-body-secondary mb-3', $bind('summary')),
                $exhibition_facts($exhibition_sample_key, 'list-unstyled d-flex flex-wrap gap-3 text-body-secondary mb-4'),
                $row(array(
                    $col(array(
                        $optional_image('photo_1', 'title', 'object-fit-cover', 'ratio ratio-4x3 mb-4',
                            $photo('exhibition-' . $exhibition_sample_key, 1200, 900), lang('Main Photo')),
                        $el('div', 'pg-hide-if-empty mb-4', lang('What You Will See'), array(
                            $heading('h2', lang('What you will see'), 'pmz-display h4 mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $details_text($exhibition_sample['details'])), $bind('details'))),
                        )),
                        $row(array(
                            $col(array($optional_image('photo_2', 'title', 'object-fit-cover', 'ratio ratio-4x3',
                                $photo('exhibition-' . $exhibition_sample_key . '-2', 1200, 900), lang('Second Photo'))), '6'),
                            $col(array($optional_image('photo_3', 'title', 'object-fit-cover', 'ratio ratio-4x3',
                                $photo('exhibition-' . $exhibition_sample_key . '-3', 1200, 900), lang('Third Photo'))), '6'),
                        ), '3'),
                    ), '', '8'),
                    $col(array(
                        $el('aside', 'card border-0 bg-body-tertiary', lang('Visit Card'), array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $heading('h2', lang('Visit the exhibition'), 'pmz-display h5 mb-3'),
                                $hours_list('mb-3'),
                                $para($museum['free'] . '.', 'small mb-3'),
                                $link(lang('Book Timed Entry'), '{{page:book_online}}', 'btn btn-primary w-100 mb-2'),
                                $link(lang('Plan Your Visit'), '{{page:visit}}', 'btn btn-outline-secondary w-100 mb-2'),
                                // Opens the list on this exhibition's state
                                // (?status=).
                                $link(lang('More exhibitions like this'), '#', 'btn btn-link w-100', array(),
                                    $mz_page_link('exhibitions', array('status' => 'status'))),
                            )),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5 mb-4'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $exhibition_pages, 'widgets' => $exhibition_widgets);
