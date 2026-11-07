<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Veterinary", its vets and online booking: the staff
 * page that adds a vet (the Vets form, a collection, with the sample vets
 * written in when it is made), the list of every vet with its pet filter,
 * the vet's own page, the list the home page places, and the page that
 * books a free time with a vet. Included after parts.php in the same
 * scope; returns array('pages' => ..., 'widgets' => ...).
 *
 * A vet is a record of the Vets form. The pets they see most is a pick
 * list whose answers are the words parts.php gives, so a link with
 * ?species= filters the list by the words a visitor sees. Years in practice
 * is a number field, so a list can sort on it. The photo is an image field
 * kept in the public folder. The vet's page asks for an appointment with
 * the vet chosen: its link carries the record's name as ?vet=, the words
 * the appointment request's Vet list uses.
 *
 * A free time is booked with the booking widget in the editor's starter
 * layout ('tree' => 'starter'): the services, the vets who take them and
 * their hours are the site's own, set under Settings › Bookings. A new
 * site has none yet, so the widget shows its no-services words until the
 * practice adds one.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$vet_pages = array();

// ── Pieces ──────────────────────────────────────────────────────────────

// A filter row of a list's settings: the field, how it compares and the
// value, or where the value comes from (a URL parameter, left out while
// the parameter is not in the address).
$vet_filter = function ($field, $operator, $value, $param = '') {
    $row = array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $param !== '' ? 'url parameter' : '');
    if ($param !== '') $row['dynamic_value_attribute'] = $param;
    return $row;
};

// A list's settings: the Vets form, the vet page, the longest in practice
// first (years is a number field, so it sorts as a number).
$vet_list_config = function ($more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:vet_new}}',
        'detail_page_id'      => '{{tab:vet}}',
        'order_by_field'      => 'years',
        'order_by_direction'  => 'DESC',
        'empty_message'       => lang('No vet sees these pets most yet. Choose another, or ask the reception.'),
    ), $more);
};
// The cards of a list, in rows of four.
$vet_grid = function () use ($widget_root, $row, $loop, $vet_card) {
    return $widget_root(array($row(array($loop(array($vet_card()))), '4')));
};

// The links above the list that narrow it: one per pet, each opening the
// vet list with the filter set.
$vet_filter_bar = function () use ($el, $link, $span, $query, $species_titles) {
    $children = array($span(lang('Pets'), 'small fw-semibold text-body-secondary me-1', lang('Label')));
    $children[] = $link(lang('All'), '{{page:vets}}', 'btn btn-sm btn-outline-secondary rounded-pill');
    foreach ($species_titles as $v) {
        $children[] = $link($v, '{{page:vets}}?' . $query(array('species' => $v)), 'btn btn-sm btn-outline-secondary rounded-pill');
    }
    return $el('nav', 'd-flex flex-wrap align-items-center gap-2 mb-4', lang('Filters'), $children);
};

// The opening hours in a short list, beside the booking widget.
$vet_hours = function () use ($el, $span, $opening_hours) {
    $items = array();
    foreach ($opening_hours as $h) {
        $items[] = $el('li', 'd-flex justify-content-between gap-3 py-1', $h['days'], array(
            $span($h['days'], '', lang('Days')),
            $span($h['hours'], 'pvt-price fw-semibold', lang('Hours')),
        ));
    }
    return $el('ul', 'list-unstyled small mb-0', lang('Opening Hours'), $items);
};

// A link printed by a vet's record that opens another page with one of the
// record's values in its address ([[record-value-page-links]]): filled per
// record by the list or the detail view; a record without the value opens
// the bare page.
$record_link = function ($text, $page, $param, $token, $class) use ($link) {
    return $link($text, '{{page:' . $page . '}}', $class, array(), array(
        '_bindings'   => array('href' => '__page_link'),
        '_bindParams' => array('href' => array('page_id' => '{{tab:' . $page . '}}', 'params' => array(array('name' => $param, 'token' => $token)))),
    ));
};

// ── Pages ───────────────────────────────────────────────────────────────

// Every vet, narrowed by the pets they see from the address.
$vet_pages[] = array(
    'key'              => 'vets',
    'name'             => lang('our-vets'),
    'folder'           => 'public',
    'title'            => lang('Our Vets'),
    'meta_description' => lang('The vets of the practice: surgery, cats, rabbits and exotics, puppies and kittens, with their consulting days and years in practice.'),
    'tree'             => $page(array(
        $page_header(lang('Our Vets'), lang('Meet the vets who look after your pets. Choose a pet to see the vets who treat it most.')),
        $el('section', 'py-5', lang('Our Vets'), array(
            $container(array($vet_filter_bar(), $widget('vets_list'))),
        ), $searchable('vt-vets', lang('vet, veterinarian, doctor, surgeon, team'))),
    )),
);

// One vet: the photo, the pets, the name, the role, the words, the
// services, the consulting days and the ways to book.
$vet_pages[] = array(
    'key'              => 'vet',
    'name'             => lang('vet'),
    'folder'           => 'public',
    'title'            => lang('Vet'),
    'meta_description' => lang('A vet of the practice: the pets they see most, what they do, their consulting days and how to book an appointment with them.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('vet_detail'),
    )),
);

// A free time with a vet: the booking widget in the editor's starter
// layout, beside the appointment request, the emergency line and the
// opening hours.
$vet_pages[] = array(
    'key'              => 'book_online',
    'name'             => lang('book-online'),
    'folder'           => 'public',
    'title'            => lang('Book Online'),
    'meta_description' => lang('Book a vet appointment online: choose the visit, a vet and one of the free times, and it is booked at once.'),
    'tree'             => $page(array(
        $page_header(lang('Book Online'), lang('Choose the visit, a vet and one of the free times. The time is yours as soon as you confirm.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-4', lang('Booking Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('booking_slots'))),
                        )),
                    ), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('Appointment Request'), array(
                            $heading('h2', lang('Rather ask first?'), 'h5 fw-semibold mb-2'),
                            $para(lang('Tell us about your pet and the reception finds the right vet and time, usually the same day.'), 'small text-body-secondary mb-3'),
                            $link(lang('Book an Appointment'), '{{page:appointment}}', 'btn btn-sm btn-outline-primary rounded-pill px-3'),
                        )),
                        $el('div', 'p-4 rounded-4 bg-danger-subtle text-danger-emphasis mb-4', lang('Emergencies'), array(
                            $heading('h2', lang('An emergency?'), 'h5 fw-semibold mb-2'),
                            $para(lang('Do not wait for a free time: call the emergency line, day or night.'), 'small mb-3'),
                            $link($practice['emergency'], 'tel:' . preg_replace('/[^0-9+]/', '', $practice['emergency']), 'btn btn-sm btn-danger rounded-pill px-3'),
                        )),
                        $el('div', 'p-4 rounded-4 border', lang('Opening hours'), array(
                            $heading('h2', lang('Opening hours'), 'h5 fw-semibold mb-3'),
                            $vet_hours(),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('vt-book-online', lang('book online, free time, appointment, slot, vet'))),
    ), false),
);

// The staff page that adds a vet. The form is the Vets collection; the
// sample vets are written in when the form is made, so the lists do not
// open empty (pg_cf_seed_sample_records()).
$sample_vets = array();
foreach ($vets as $key => $v) {
    $does = array();
    foreach ($v['services'] as $s) $does[] = $services[$s]['title'];
    $on = array();
    foreach ($v['days'] as $d) $on[] = $days[$d];
    $sample_vets[] = array(
        'name'     => $v['name'],
        'species'  => $species[$v['species']]['title'],
        'role'     => $v['role'],
        'years'    => (string)$v['years'],
        'summary'  => $v['summary'],
        'bio'      => $v['bio'],
        'services' => implode("\n", $does),
        'days'     => implode(', ', $on),
        'photo'    => $portrait($v['name'], 900),
    );
}
$vet_pages[] = array(
    'key'     => 'vet_new',
    'name'    => lang('new-vet'),
    'folder'  => 'private',
    'title'   => lang('New Vet'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Vets'),
        'collection'           => 1,
        'confirmation_message' => lang('The vet is published.'),
        'confirmation_page_id' => '{{tab:vets}}',
        'sample_records'       => $sample_vets,
        // The photo is the vet's share image (pg_cf_share_image_field_id()).
        'share_image_field'    => 'photo',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Vet'), lang('Add the vet with a photo and a few words; they appear in the list as soon as you send it.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 rounded-4 border', lang('Form Card'), array($widget('vet_form'))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// ── Widgets ─────────────────────────────────────────────────────────────

$vet_widgets = array(

    // The staff's Vets form: one vet.
    'vet_form' => array(
        'page'   => 'vet_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The vet')),
            $field('vt-vet-name', lang('Name'), pg_cf_with_role($input('vt-vet-name', 'text', 'name', 'off'), 'title')),
            $row(array(
                $col(array($field('vt-vet-species', lang('Pets they see most'), pg_cf_with_role($select('vt-vet-species', 'species', lang('Choose the pets…'), $species_titles), 'category'))), '6'),
                $col(array($field('vt-vet-years', lang('Years in Practice'),
                    $input('vt-vet-years', 'number', 'years', 'off', true, array('kind' => 'number'), array($attr('min', '0'), $attr('step', '1'))))), '6'),
            ), '3'),
            $field('vt-vet-role', lang('Role'), $input('vt-vet-role', 'text', 'role', 'off'), 'mb-3',
                lang('As owners read it, for example Senior Veterinary Surgeon.')),
            $form_step(2, lang('Words')),
            $field('vt-vet-summary', lang('Summary'), pg_cf_with_role($textarea('vt-vet-summary', 'summary', 2), 'description'), 'mb-3',
                lang('Two lines for the cards of the lists.')),
            $field('vt-vet-bio', lang('About the vet'), $textarea('vt-vet-bio', 'bio', 6)),
            $field('vt-vet-services', lang('What they do'), $textarea('vt-vet-services', 'services', 3, false), 'mb-3',
                lang('One service on each line, in the words of the services page.')),
            $field('vt-vet-days', lang('Consulting days'), $input('vt-vet-days', 'text', 'days', 'off', false), 'mb-3',
                lang('For example Monday, Wednesday, Friday.')),
            $form_step(3, lang('Photo')),
            $field('vt-vet-photo', lang('Photo'), pg_cf_with_role($file('vt-vet-photo', 'photo', 'public', 'image/*'), 'media'), 'mb-3',
                lang('A square photo works best; it is also the share image.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every vet, filtered by the address (species), with a search over
    // the words of the vets.
    'vets_list' => array(
        'page'   => 'vets',
        'slug'   => lang('form-list'),
        'config' => $vet_list_config(array(
            'items_per_page' => 12,
            'filters'        => array($vet_filter('species', 'is equal to', '', 'species')),
            'search_enabled' => true,
            'search_fields'  => array('name', 'species', 'role', 'summary', 'bio', 'services'),
            'search_width'   => 'full',
            'search_label'   => lang('Search the vets: surgery, rabbits, a cat vet...'),
        )),
        'tree'   => $vet_grid(),
    ),

    // The home page's vets, four at most.
    'home_vets' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $vet_list_config(array(
            'items_per_page' => 4,
            'max_results'    => 4,
            'empty_message'  => lang('No vets yet.'),
        )),
        'tree'   => $vet_grid(),
    ),

    // A free time with a vet: the booking widget in the editor's starter
    // layout (visit, vet, day and time, details, confirmation).
    'booking_slots' => array(
        'page'   => 'book_online',
        'slug'   => lang('booking'),
        'config' => array('regionType' => 'booking'),
        'tree'   => 'starter',
    ),
);

// One vet: the photo, the pets, the name, the role, the years, the words,
// what they do, the consulting days and the ways to book.
$vet_sample = $vets['elif'];
$vet_sample_does = array();
foreach ($vet_sample['services'] as $s) $vet_sample_does[] = $services[$s]['title'];
$vet_sample_days = array();
foreach ($vet_sample['days'] as $d) $vet_sample_days[] = $days[$d];
$vet_widgets['vet_detail'] = array(
    'page'   => 'vet',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:vet_new}}',
        'access_control'      => 'public',
        'not_found_message'   => lang('This vet could not be found. They may have moved on, or the page was renamed.'),
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $el('div', 'text-center py-5', lang('Not Found'), array(
                $icon('bi-person-x', 'display-5 text-body-tertiary'),
                $heading('h2', lang('Vet not found'), 'h3 fw-semibold mt-3'),
                $para(lang('This vet could not be found. They may have moved on, or the page was renamed.'), 'text-body-secondary mb-4', $bind('not_found')),
                $link(lang('See the vets'), '{{page:vets}}', 'btn btn-primary rounded-pill px-4'),
            ), $show_when('record_not_found')),
            $el('article', '', lang('Vet'), array(
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-4', lang('Toolbar'), array(
                    $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                        $icon('bi-arrow-left'),
                        $span(lang('All vets'), '', lang('Link Text')),
                    ), array('href' => '{{page:vets}}')),
                    $edit_button(),
                )),
                $row(array(
                    $col(array(
                        $optional_image('photo', 'name', 'rounded-4 object-fit-cover', 'ratio ratio-1x1',
                            $portrait($vet_sample['name'], 900), lang('Photo')),
                    ), '5', '4'),
                    $col(array(
                        $span($species[$vet_sample['species']]['title'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis mb-2', lang('Pets'), $bind('species')),
                        $heading('h1', $vet_sample['name'], 'pvt-display display-6 mb-1', $bind('name')),
                        $para($vet_sample['role'], 'fw-semibold mb-3', $bind('role')),
                        $el('p', 'd-flex align-items-center gap-1 text-body-secondary mb-3', lang('Years of Practice'), array(
                            $icon('bi-award', 'text-primary'),
                            $span((string)$vet_sample['years'], 'pvt-price', lang('Value'), $bind('years')),
                            $span(lang('years in practice'), '', lang('Unit')),
                        )),
                        $para($vet_sample['summary'], 'lead text-body-secondary mb-4', $bind('summary')),
                        $el('div', 'pg-hide-if-empty mb-4', lang('About the vet'), array(
                            $heading('h2', lang('About the vet'), 'pvt-display h5 mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $vet_sample['bio']), $bind('bio'))),
                        )),
                        $row(array(
                            $col(array(
                                $el('div', 'pg-hide-if-empty h-100 p-4 rounded-4 bg-body-tertiary', lang('What they do'), array(
                                    $heading('h2', lang('What they do'), 'pvt-display h6 mb-2'),
                                    $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => implode("\n", $vet_sample_does)), $bind('services'))),
                                )),
                            ), '6'),
                            $col(array(
                                $el('div', 'pg-hide-if-empty h-100 p-4 rounded-4 bg-body-tertiary', lang('Consulting days'), array(
                                    $heading('h2', lang('Consulting days'), 'pvt-display h6 mb-2'),
                                    $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => implode(', ', $vet_sample_days)), $bind('days'))),
                                )),
                            ), '6'),
                        ), '3', '', 'mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $record_link(lang('Book with this vet'), 'appointment', 'vet', 'name', 'btn btn-primary rounded-pill px-4'),
                            $link(lang('Pick a Free Time'), '{{page:book_online}}', 'btn btn-outline-primary rounded-pill px-4'),
                            $record_link(lang('Pet Care Articles'), 'blog', 'species', 'species', 'btn btn-link'),
                        )),
                    ), '7', '8'),
                ), '4', '', 'g-lg-5'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $vet_pages, 'widgets' => $vet_widgets);
