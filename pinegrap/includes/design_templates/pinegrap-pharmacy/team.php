<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Pharmacy", its team and online booking: the staff page
 * that adds a pharmacist (the Pharmacists form, a collection, with the
 * sample pharmacists written in when it is made), the team list with its
 * service filter, the pharmacist's own page, the list the home page places,
 * and the page that books a vaccination or a health check. Included after
 * parts.php in the same scope; returns array('pages' => ..., 'widgets' => ...).
 *
 * A pharmacist is a record of the Pharmacists form. The service they lead
 * is a pick list whose answers are the services' titles in parts.php, so a
 * link with ?service= filters the list by the words a visitor sees. Years
 * in practice is a number field, so a list can sort on it. The photo is an
 * image field kept in the public folder. The pharmacist's page sends a
 * prescription request with the pharmacist chosen: its link carries the
 * record's name as ?pharmacist=, the words the request's Pharmacist list
 * uses.
 *
 * A vaccination or a check is booked with the booking widget in the
 * editor's starter layout ('tree' => 'starter'): the services, the
 * pharmacists who take them and their hours are the site's own, set under
 * Settings › Bookings. A new site has none yet, so the widget shows its
 * no-services words until the pharmacy adds one.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}


$team_pages = array();

// ── Pieces ──────────────────────────────────────────────────────────────

// A filter row of a list's settings: the field, how it compares and the
// value, or where the value comes from (a URL parameter, left out while
// the parameter is not in the address).
$team_filter = function ($field, $operator, $value, $param = '') {
    $row = array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $param !== '' ? 'url parameter' : '');
    if ($param !== '') $row['dynamic_value_attribute'] = $param;
    return $row;
};

// A list's settings: the Pharmacists form, the pharmacist page, the
// longest in practice first (years is a number field, so it sorts as a
// number).
$team_list_config = function ($more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:pharmacist_new}}',
        'detail_page_id'      => '{{tab:pharmacist}}',
        'order_by_field'      => 'years',
        'order_by_direction'  => 'DESC',
        'empty_message'       => lang('No pharmacist leads this service yet. Choose another, or ask us at the counter.'),
    ), $more);
};
// The cards of a list, in rows of four.
$team_grid = function () use ($widget_root, $row, $loop, $pharmacist_card) {
    return $widget_root(array($row(array($loop(array($pharmacist_card()))), '4')));
};

// The links above the list that narrow it: one per service, each opening
// the team list with the filter set.
$team_filter_bar = function () use ($el, $link, $span, $query, $service_titles) {
    $children = array($span(lang('Service'), 'small fw-semibold text-body-secondary me-1', lang('Label')));
    $children[] = $link(lang('All'), '{{page:team}}', 'btn btn-sm btn-outline-secondary rounded-pill');
    foreach ($service_titles as $v) {
        $children[] = $link($v, '{{page:team}}?' . $query(array('service' => $v)), 'btn btn-sm btn-outline-secondary rounded-pill');
    }
    return $el('nav', 'd-flex flex-wrap align-items-center gap-2 mb-4', lang('Filters'), $children);
};

// The opening hours in a short list, beside the booking widget.
$team_hours = function () use ($el, $span, $opening_hours) {
    $items = array();
    foreach ($opening_hours as $h) {
        $items[] = $el('li', 'd-flex justify-content-between gap-3 py-1', $h['days'], array(
            $span($h['days'], '', lang('Days')),
            $span($h['hours'], 'prx-price fw-semibold text-end', lang('Hours')),
        ));
    }
    return $el('ul', 'list-unstyled small mb-0', lang('Opening Hours'), $items);
};

// A link printed by a pharmacist's record that opens another page with one
// of the record's values in its address ([[record-value-page-links]]):
// filled per record by the list or the detail view; a record without the
// value opens the bare page.
$record_link = function ($text, $page, $param, $token, $class) use ($link) {
    return $link($text, '{{page:' . $page . '}}', $class, array(), array(
        '_bindings'   => array('href' => '__page_link'),
        '_bindParams' => array('href' => array('page_id' => '{{tab:' . $page . '}}', 'params' => array(array('name' => $param, 'token' => $token)))),
    ));
};

// ── Pages ───────────────────────────────────────────────────────────────

// Every pharmacist, narrowed by the service they lead from the address.
$team_pages[] = array(
    'key'              => 'team',
    'name'             => lang('our-pharmacists'),
    'folder'           => 'public',
    'title'            => lang('Our Pharmacists'),
    'meta_description' => lang('The pharmacists of the pharmacy: prescriptions, vaccinations and travel, health checks and home delivery, with their days and years in practice.'),
    'tree'             => $page(array(
        $page_header(lang('Our Pharmacists'), lang('Meet the pharmacists behind the counter. Choose a service to see who leads it.')),
        $el('section', 'py-5', lang('Our Pharmacists'), array(
            $container(array($team_filter_bar(), $widget('team_list'))),
        ), $searchable('rx-team', lang('pharmacist, chemist, team, staff'))),
    )),
);

// One pharmacist: the photo, the service, the name, the role, the words,
// the services, the working days and the ways to reach them.
$team_pages[] = array(
    'key'              => 'pharmacist',
    'name'             => lang('pharmacist'),
    'folder'           => 'public',
    'title'            => lang('Pharmacist'),
    'meta_description' => lang('A pharmacist of the pharmacy: the service they lead, what they do, their working days and how to send them a prescription.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('pharmacist_detail'),
    )),
);

// A vaccination or a health check: the booking widget in the editor's
// starter layout, beside the prescription request, the on-duty page and
// the opening hours.
$team_pages[] = array(
    'key'              => 'book_online',
    'name'             => lang('book-online'),
    'folder'           => 'public',
    'title'            => lang('Book Online'),
    'meta_description' => lang('Book a vaccination or a health check online: choose the service, a pharmacist and one of the free times, and it is booked at once.'),
    'tree'             => $page(array(
        $page_header(lang('Book Online'), lang('Choose a vaccination or a health check, a pharmacist and one of the free times. The time is yours as soon as you confirm.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-4', lang('Booking Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('booking_slots'))),
                        )),
                    ), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('Prescription Request'), array(
                            $heading('h2', lang('Here for your medicines?'), 'h5 fw-semibold mb-2'),
                            $para(lang('Prescriptions need no booking: send us the request and collect the bag when we tell you it is ready.'), 'small text-body-secondary mb-3'),
                            $link(lang('Send a Prescription'), '{{page:prescription}}', 'btn btn-sm btn-outline-primary rounded-pill px-3'),
                        )),
                        $el('div', 'p-4 rounded-4 bg-danger-subtle text-danger-emphasis mb-4', lang('On Duty'), array(
                            $heading('h2', lang('Closed or urgent?'), 'h5 fw-semibold mb-2'),
                            $para(lang('At night find the pharmacy on duty; in an emergency call 112 at once.'), 'small mb-3'),
                            $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                                $link(lang('On Duty'), '{{page:on_duty}}', 'btn btn-sm btn-outline-danger rounded-pill px-3'),
                                $link($pharmacy['emergency'], $tel($pharmacy['emergency']), 'btn btn-sm btn-danger rounded-pill px-3'),
                            )),
                        )),
                        $el('div', 'p-4 rounded-4 border', lang('Opening hours'), array(
                            $heading('h2', lang('Opening hours'), 'h5 fw-semibold mb-3'),
                            $team_hours(),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('rx-book-online', lang('book online, free time, appointment, flu jab, vaccination, check'))),
    ), false),
);

// The staff page that adds a pharmacist. The form is the Pharmacists
// collection; the sample pharmacists are written in when the form is
// made, so the lists do not open empty (pg_cf_seed_sample_records()).
$sample_pharmacists = array();
foreach ($pharmacists as $key => $p) {
    $does = array();
    foreach ($p['services'] as $s) $does[] = $services[$s]['title'];
    $on = array();
    foreach ($p['days'] as $d) $on[] = $days[$d];
    $sample_pharmacists[] = array(
        'name'     => $p['name'],
        'service'  => $services[$p['service']]['title'],
        'role'     => $p['role'],
        'years'    => (string)$p['years'],
        'summary'  => $p['summary'],
        'bio'      => $p['bio'],
        'services' => implode("\n", $does),
        'days'     => implode(', ', $on),
        'photo'    => $portrait($p['name'], 900),
    );
}
$team_pages[] = array(
    'key'     => 'pharmacist_new',
    'name'    => lang('new-pharmacist'),
    'folder'  => 'private',
    'title'   => lang('New Pharmacist'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Pharmacists'),
        'collection'           => 1,
        'confirmation_message' => lang('The pharmacist is published.'),
        'confirmation_page_id' => '{{tab:team}}',
        'sample_records'       => $sample_pharmacists,
        // The photo is the pharmacist's share image (pg_cf_share_image_field_id()).
        'share_image_field'    => 'photo',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Pharmacist'), lang('Add the pharmacist with a photo and a few words; they appear in the team list as soon as you send it.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 rounded-4 border', lang('Form Card'), array($widget('pharmacist_form'))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// ── Widgets ─────────────────────────────────────────────────────────────

$team_widgets = array(

    // The staff's Pharmacists form: one pharmacist.
    'pharmacist_form' => array(
        'page'   => 'pharmacist_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The pharmacist')),
            $field('rx-ph-name', lang('Name'), pg_cf_with_role($input('rx-ph-name', 'text', 'name', 'off'), 'title')),
            $row(array(
                $col(array($field('rx-ph-service', lang('Service they lead'), pg_cf_with_role($select('rx-ph-service', 'service', lang('Choose the service…'), $service_titles), 'category'))), '6'),
                $col(array($field('rx-ph-years', lang('Years in Practice'),
                    $input('rx-ph-years', 'number', 'years', 'off', true, array('kind' => 'number'), array($attr('min', '0'), $attr('step', '1'))))), '6'),
            ), '3'),
            $field('rx-ph-role', lang('Role'), $input('rx-ph-role', 'text', 'role', 'off'), 'mb-3',
                lang('As customers read it, for example Responsible Pharmacist.')),
            $form_step(2, lang('Words')),
            $field('rx-ph-summary', lang('Summary'), pg_cf_with_role($textarea('rx-ph-summary', 'summary', 2), 'description'), 'mb-3',
                lang('Two lines for the cards of the lists.')),
            $field('rx-ph-bio', lang('About the pharmacist'), $textarea('rx-ph-bio', 'bio', 6)),
            $field('rx-ph-services', lang('What they do'), $textarea('rx-ph-services', 'services', 3, false), 'mb-3',
                lang('One service on each line, in the words of the services page.')),
            $field('rx-ph-days', lang('Working days'), $input('rx-ph-days', 'text', 'days', 'off', false), 'mb-3',
                lang('For example Monday, Wednesday, Friday.')),
            $form_step(3, lang('Photo')),
            $field('rx-ph-photo', lang('Photo'), pg_cf_with_role($file('rx-ph-photo', 'photo', 'public', 'image/*'), 'media'), 'mb-3',
                lang('A square photo works best; it is also the share image.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every pharmacist, filtered by the address (service), with a search
    // over the words of the pharmacists.
    'team_list' => array(
        'page'   => 'team',
        'slug'   => lang('form-list'),
        'config' => $team_list_config(array(
            'items_per_page' => 12,
            'filters'        => array($team_filter('service', 'is equal to', '', 'service')),
            'search_enabled' => true,
            'search_fields'  => array('name', 'service', 'role', 'summary', 'bio', 'services'),
            'search_width'   => 'full',
            'search_label'   => lang('Search the team: vaccinations, health checks, delivery...'),
        )),
        'tree'   => $team_grid(),
    ),

    // The home page's pharmacists, four at most.
    'home_team' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $team_list_config(array(
            'items_per_page' => 4,
            'max_results'    => 4,
            'empty_message'  => lang('No pharmacists yet.'),
        )),
        'tree'   => $team_grid(),
    ),

    // A vaccination or a check: the booking widget in the editor's starter
    // layout (service, pharmacist, day and time, details, confirmation).
    'booking_slots' => array(
        'page'   => 'book_online',
        'slug'   => lang('booking'),
        'config' => array('regionType' => 'booking'),
        'tree'   => 'starter',
    ),
);

// One pharmacist: the photo, the service, the name, the role, the years,
// the words, what they do, the working days and the ways to reach them.
$ph_sample = $pharmacists['selin'];
$ph_sample_does = array();
foreach ($ph_sample['services'] as $s) $ph_sample_does[] = $services[$s]['title'];
$ph_sample_days = array();
foreach ($ph_sample['days'] as $d) $ph_sample_days[] = $days[$d];
$team_widgets['pharmacist_detail'] = array(
    'page'   => 'pharmacist',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:pharmacist_new}}',
        'access_control'      => 'public',
        'not_found_message'   => lang('This pharmacist could not be found. They may have moved on, or the page was renamed.'),
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $el('div', 'text-center py-5', lang('Not Found'), array(
                $icon('bi-person-x', 'display-5 text-body-tertiary'),
                $heading('h2', lang('Pharmacist not found'), 'h3 fw-semibold mt-3'),
                $para(lang('This pharmacist could not be found. They may have moved on, or the page was renamed.'), 'text-body-secondary mb-4', $bind('not_found')),
                $link(lang('Meet the team'), '{{page:team}}', 'btn btn-primary rounded-pill px-4'),
            ), $show_when('record_not_found')),
            $el('article', '', lang('Pharmacist'), array(
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-4', lang('Toolbar'), array(
                    $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                        $icon('bi-arrow-left'),
                        $span(lang('All pharmacists'), '', lang('Link Text')),
                    ), array('href' => '{{page:team}}')),
                    $edit_button(),
                )),
                $row(array(
                    $col(array(
                        $optional_image('photo', 'name', 'rounded-4 object-fit-cover', 'ratio ratio-1x1',
                            $portrait($ph_sample['name'], 900), lang('Photo')),
                    ), '5', '4'),
                    $col(array(
                        $span($services[$ph_sample['service']]['title'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis mb-2', lang('Service'), $bind('service')),
                        $heading('h1', $ph_sample['name'], 'prx-display display-6 mb-1', $bind('name')),
                        $para($ph_sample['role'], 'fw-semibold mb-3', $bind('role')),
                        $el('p', 'd-flex align-items-center gap-1 text-body-secondary mb-3', lang('Years of Practice'), array(
                            $icon('bi-award', 'text-primary'),
                            $span((string)$ph_sample['years'], 'prx-price', lang('Value'), $bind('years')),
                            $span(lang('years in practice'), '', lang('Unit')),
                        )),
                        $para($ph_sample['summary'], 'lead text-body-secondary mb-4', $bind('summary')),
                        $el('div', 'pg-hide-if-empty mb-4', lang('About the pharmacist'), array(
                            $heading('h2', lang('About the pharmacist'), 'prx-display h5 mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $ph_sample['bio']), $bind('bio'))),
                        )),
                        $row(array(
                            $col(array(
                                $el('div', 'pg-hide-if-empty h-100 p-4 rounded-4 bg-body-tertiary', lang('What they do'), array(
                                    $heading('h2', lang('What they do'), 'prx-display h6 mb-2'),
                                    $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => implode("\n", $ph_sample_does)), $bind('services'))),
                                )),
                            ), '6'),
                            $col(array(
                                $el('div', 'pg-hide-if-empty h-100 p-4 rounded-4 bg-body-tertiary', lang('Working days'), array(
                                    $heading('h2', lang('Working days'), 'prx-display h6 mb-2'),
                                    $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => implode(', ', $ph_sample_days)), $bind('days'))),
                                )),
                            ), '6'),
                        ), '3', '', 'mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $record_link(lang('Send a prescription to this pharmacist'), 'prescription', 'pharmacist', 'name', 'btn btn-primary rounded-pill px-4'),
                            $link(lang('Book Online'), '{{page:book_online}}', 'btn btn-outline-primary rounded-pill px-4'),
                            $link(lang('Ask a Pharmacist'), '{{page:ask}}', 'btn btn-link'),
                        )),
                    ), '7', '8'),
                ), '4', '', 'g-lg-5'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $team_pages, 'widgets' => $team_widgets);
