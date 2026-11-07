<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Theatre", its performances: the staff page that adds a
 * performance (the Performances form, a collection, with the sample
 * performances written in when it is made), What's On from today on with
 * its production and access filters, the performances already played, the
 * performance page with its production, its facts and the way to the
 * tickets, the list the home page places and the list of the production
 * page. Included after parts.php and productions.php in the same scope;
 * returns array('pages' => ..., 'widgets' => ...).
 *
 * A performance is a record of the Performances form. Its production is a
 * reference into the Productions form: the field holds the production's
 * code, the rows print its title through the reference, and the
 * production page lists its own performances (the dynamic value "shown
 * record"). ?production= on What's On takes that code (or an address
 * name) and the page prints the production's title; the production page
 * and each performance send it. Codes are made when the template is
 * installed, so no link bar of productions is built; the access bar is,
 * from the Access field's answers. The date is a date field, so What's On
 * lists the performances from today on (nearest first) and the past ones
 * the rest (latest first); the sample dates are counted from the day the
 * template opens ($perf_on()). Featured is the theatre's own choice
 * (office use only): the home page shows the featured performances to
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

$performance_pages = array();

// The answers of the Featured list: the first marks a performance for the
// home page.
$th_featured_choices = array(lang('Yes'), lang('No'));

// A date filter against today: the performances to come (on or after
// today) or those already played (before today).
$th_date_filter = function ($operator) {
    return array('field' => 'perf_date', 'operator' => $operator, 'value' => '', 'dynamic_value' => 'current date');
};

// A list of performances: the form, the record page, the order by date.
$th_performance_list = function ($direction, $empty, $more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:performance_new}}',
        'detail_page_id'      => '{{tab:performance}}',
        'order_by_field'      => 'perf_date',
        'order_by_direction'  => $direction,
        'empty_message'       => $empty,
    ), $more);
};

// The rows of a list, one performance under the other.
$performance_list = function () use ($widget_root, $loop, $performance_row) {
    return $widget_root(array($loop(array($performance_row()))));
};

// ── Pages ───────────────────────────────────────────────────────────────

// The performances to come, narrowed by production and access from the
// address.
$performance_pages[] = array(
    'key'              => 'whats_on',
    'name'             => lang('whats-on'),
    'folder'           => 'public',
    'title'            => lang('What\'s On'),
    'meta_description' => lang('Every performance to come at the theatre with its date, curtain time, stage, access and ticket state.'),
    'tree'             => $page(array(
        $page_header(lang('What\'s On'), lang('Every performance to come, nearest first. Choose an access performance to narrow the list, or open a production for its own dates.')),
        $el('section', 'py-5', lang('What\'s On'), array(
            $container(array(
                $th_filter_bar('whats_on', lang('Access Performance'), 'access', $access_titles),
                $widget('whats_on_list'),
                $el('p', 'mt-4 mb-0', lang('Text'), array(
                    $link(lang('See the performances already played'), '{{page:past_performances}}', 'fw-semibold link-primary'),
                )),
            )),
        ), $searchable('th-whats-on', lang('performances, dates, times, tickets, relaxed, captioned, audio described, sign language'))),
    )),
);

// The performances already played, latest first.
$performance_pages[] = array(
    'key'              => 'past_performances',
    'name'             => lang('past-performances'),
    'folder'           => 'public',
    'title'            => lang('Past Performances'),
    'meta_description' => lang('The performances the theatre has already played, latest first.'),
    'tree'             => $page(array(
        $page_header(lang('Past Performances'), lang('What has played on our stages, latest first. Thank you to everyone who came.')),
        $el('section', 'py-5', lang('Past Performances'), array(
            $container(array(
                $widget('past_performances_list'),
                $el('p', 'mt-4 mb-0', lang('Text'), array(
                    $link(lang('See the performances to come'), '{{page:whats_on}}', 'fw-semibold link-primary'),
                )),
            )),
        )),
    )),
);

// One performance.
$performance_pages[] = array(
    'key'              => 'performance',
    'name'             => lang('performance'),
    'folder'           => 'public',
    'title'            => lang('Performance Details'),
    'meta_description' => lang('A performance at the theatre: the production, the date, the curtain time, the stage, the access and the way to the tickets.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('performance_detail'),
    )),
);

// The staff page that adds a performance. The form is the Performances
// collection; the sample performances are written in when the form is
// made, after the productions they name by title (the seeding finds each
// production's code).
$sample_performances = array();
foreach ($performances as $key => $s) {
    $sample_performances[] = array(
        'production'   => $performance_production($key),
        'perf_date'    => $perf_on($s['weekday'], $s['weeks']),
        'perf_time'    => $s['time'],
        'stage'        => $production_stage($s['production']),
        'access'       => $performance_access($key),
        'ticket_state' => $performance_state($key),
        'featured'     => $s['featured'] ? $th_featured_choices[0] : $th_featured_choices[1],
    );
    // A performance to come with tickets to sell links a sample ticket
    // page the theatre replaces, or leaves empty for its own box office.
    if ($s['weeks'] >= 0 && $s['state'] !== 'sold_out') {
        $sample_performances[count($sample_performances) - 1]['ticket_url'] = 'https://tickets.example.com/';
    }
}
$performance_pages[] = array(
    'key'     => 'performance_new',
    'name'    => lang('new-performance'),
    'folder'  => 'private',
    'title'   => lang('New Performance'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Performances'),
        'collection'           => 1,
        'confirmation_message' => lang('The performance is published.'),
        'confirmation_page_id' => '{{tab:whats_on}}',
        'sample_records'       => $sample_performances,
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Performance'), lang('Add a date of a production with its curtain time, stage and access; it appears in What\'s On as soon as you send it and moves to the past performances the day after.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 border', lang('Form Card'), array($widget('performance_form'))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// ── Widgets ─────────────────────────────────────────────────────────────

$performance_widgets = array(

    // The staff's Performances form: one performance. The production is a
    // reference into the Productions form, whose records are the choices.
    // Access starts as Standard, the ticket state as On Sale, Featured as
    // No.
    'performance_form' => array(
        'page'   => 'performance_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The performance')),
            $field('th-performance-production', lang('The Production'), pg_cf_with_role($el('select', 'form-select', lang('Field'), array(
                $el('option', '', lang('Option'), array(), array('text' => lang('Choose a production'), '_attrs' => array($attr('value', '')))),
            ), array('id' => 'th-performance-production', '_attrs' => array($attr('name', 'production'), $attr('required', '')),
                     '_cf' => array('kind' => 'reference', 'ref' => '{{tab:production_new}}'))), 'category')),
            $row(array(
                $col(array($field('th-performance-date', lang('Performance Date'), $input('th-performance-date', 'date', 'perf_date', 'off'))), '6'),
                $col(array($field('th-performance-time', lang('Curtain Up'), $input('th-performance-time', 'text', 'perf_time', 'off'), 'mb-3',
                    lang('The time the curtain goes up, for example 19:30.'))), '6'),
            ), '3'),
            $row(array(
                $col(array($field('th-performance-stage', lang('Theatre Stage'), $select('th-performance-stage', 'stage', '', $stage_titles, true, $stage_titles[0]))), '6'),
                $col(array($field('th-performance-access', lang('Access Performance'), $select('th-performance-access', 'access', '', $access_titles, true, $access_titles[0]))), '6'),
            ), '3'),
            $form_step(2, lang('Tickets')),
            $field('th-performance-state', lang('Ticket State'), $select('th-performance-state', 'ticket_state', '', $ticket_state_titles, true, $ticket_state_titles[0])),
            $field('th-performance-tickets', lang('Ticket address'), $input('th-performance-tickets', 'url', 'ticket_url', 'url', false), 'mb-3',
                lang('The page of an outside box office. Leave empty when tickets are sold at our own box office.')),
            $field('th-performance-featured', lang('Featured'), $select('th-performance-featured', 'featured', '', $th_featured_choices, false, $th_featured_choices[1], array('office_use_only' => true)),
                'mb-3', lang('A featured performance to come is shown on the home page.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // The performances to come, filtered by the address (production and
    // access), nearest first.
    'whats_on_list' => array(
        'page'   => 'whats_on',
        'slug'   => lang('form-list'),
        'config' => $th_performance_list('ASC', lang('No performance to come in this list. Choose another, or see every performance.'), array(
            'items_per_page' => 20,
            'filters'        => array(
                $th_date_filter('is greater than or equal to'),
                $th_filter('production', 'is equal to', '', 'production'),
                $th_filter('access', 'is equal to', '', 'access'),
            ),
        )),
        'tree'   => $performance_list(),
    ),

    // The performances already played, latest first.
    'past_performances_list' => array(
        'page'   => 'past_performances',
        'slug'   => lang('form-list'),
        'config' => $th_performance_list('DESC', lang('No performance played yet.'), array(
            'items_per_page' => 20,
            'filters'        => array($th_date_filter('is less than')),
        )),
        'tree'   => $performance_list(),
    ),

    // The home page's featured performances to come, nearest first, three
    // at most.
    'home_performances' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $th_performance_list('ASC', lang('No featured performance to come yet. See every date in What\'s On.'), array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'filters'        => array(
                $th_date_filter('is greater than or equal to'),
                $th_filter('featured', 'is equal to', $th_featured_choices[0]),
            ),
        )),
        'tree'   => $performance_list(),
    ),

    // A production's page: the performances of the production it shows,
    // from today on, nearest first.
    'production_performances' => array(
        'page'   => 'production',
        'slug'   => lang('form-list'),
        'config' => $th_performance_list('ASC', lang('No performance of this production to come yet.'), array(
            'items_per_page' => 10,
            'filters'        => array(
                array('field' => 'production', 'operator' => 'is equal to', 'value' => '', 'dynamic_value' => 'shown record', 'dynamic_value_attribute' => ''),
                $th_date_filter('is greater than or equal to'),
            ),
        )),
        'tree'   => $performance_list(),
    ),
);

// One performance: the date and the curtain, the production's title
// (through the reference), the facts, and beside them the ticket state,
// the way to the tickets and the production's other dates (?production=,
// the code the field holds).
$performance_sample_key = 'lh_1';
$performance_sample = $performances[$performance_sample_key];
$performance_not_found = lang('This performance could not be found. It may have been moved or called off.');
$performance_widgets['performance_detail'] = array(
    'page'   => 'performance',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:performance_new}}',
        'access_control'      => 'public',
        'not_found_message'   => $performance_not_found,
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $el('div', 'text-center py-5', lang('Not Found'), array(
                $icon('bi-calendar-x', 'display-5 text-body-tertiary'),
                $heading('h2', lang('Performance not found'), 'h3 fw-semibold mt-3'),
                $para($performance_not_found, 'text-body-secondary mb-4', $bind('not_found')),
                $link(lang('See What\'s On'), '{{page:whats_on}}', 'btn btn-primary px-4'),
            ), $show_when('record_not_found')),
            $el('article', '', lang('Performance Details'), array(
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                    $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                        $icon('bi-arrow-left'),
                        $span(lang('All Performances'), '', lang('Link Text')),
                    ), array('href' => '{{page:whats_on}}')),
                    $edit_button(),
                )),
                $row(array(
                    $col(array(
                        $el('p', 'pth-date d-flex flex-wrap gap-2 fs-5 fw-bold text-primary mb-2', lang('Date and Time'), array(
                            $span($perf_on($performance_sample['weekday'], $performance_sample['weeks']), '', lang('Performance Date'), $bind('perf_date')),
                            $span($performance_sample['time'], '', lang('Curtain Up'), $bind('perf_time')),
                        )),
                        $heading('h1', $performance_production($performance_sample_key), 'pth-display display-5 mb-4',
                            $th_production_ref(array('text' => 'title'))),
                        $performance_facts($performance_sample_key, 'list-unstyled mb-4'),
                        $para($theatre['doors'], 'small text-body-secondary mb-0'),
                    ), '', '7'),
                    $col(array(
                        $el('aside', 'card border-0 bg-body-tertiary', lang('Box Office Card'), array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $span($performance_state($performance_sample_key), 'badge rounded-pill ' . $ticket_states[$performance_sample['state']]['badge'] . ' mb-3',
                                    lang('Ticket State'), $bind('ticket_state')),
                                $heading('h2', lang('Book your seats'), 'pth-display h5 mb-3'),
                                $hours_list('mb-3'),
                                $para($theatre['phone'], 'fw-semibold mb-3'),
                                // An outside box office's page, left out
                                // when the performance has none.
                                $link(lang('Tickets'), '#', 'btn btn-primary w-100 mb-2', array($attr('rel', 'noopener')),
                                    array('target' => '_blank', '_bindings' => array('href' => 'ticket_url', 'show_if' => 'ticket_url'))),
                                // The other dates of this production
                                // (?production=, its code).
                                $link(lang('More dates of this production'), '#', 'btn btn-outline-secondary w-100', array(),
                                    $th_page_link('whats_on', array('production' => 'production'))),
                            )),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $performance_pages, 'widgets' => $performance_widgets);
