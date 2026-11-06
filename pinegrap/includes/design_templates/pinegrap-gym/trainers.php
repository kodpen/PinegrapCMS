<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Gym", its trainers and personal training: the staff
 * page that adds a trainer (the Trainers form, a collection, with the
 * sample trainers written in when it is made), the list of every trainer
 * with its focus filter, the trainer's own page, the list the home page
 * places, and the page that books a personal training session at a free
 * time. Included after parts.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * A trainer is a record of the Trainers form. Its focus is a pick list
 * whose answers are the words parts.php gives, so a link with ?focus=
 * filters the list by the words a visitor sees. Years of coaching is a
 * number field, so a list can sort on it. The photo is an image field kept
 * in the public folder.
 *
 * Personal training is booked with the booking widget in the editor's
 * starter layout ('tree' => 'starter'): the services, the staff who take
 * them and their hours are the site's own, set under Settings › Bookings.
 * A new site has none yet, so the widget shows its no-services words
 * until the gym adds one.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$trainer_pages = array();

// ── Pieces ──────────────────────────────────────────────────────────────

// A filter row of a list's settings: the field, how it compares and the
// value, or where the value comes from (a URL parameter, left out while
// the parameter is not in the address).
$trainer_filter = function ($field, $operator, $value, $param = '') {
    $row = array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $param !== '' ? 'url parameter' : '');
    if ($param !== '') $row['dynamic_value_attribute'] = $param;
    return $row;
};

// A list's settings: the Trainers form, the trainer page, the longest
// coaching first (years is a number field, so it sorts as a number).
$trainer_list_config = function ($more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:trainer_new}}',
        'detail_page_id'      => '{{tab:trainer}}',
        'order_by_field'      => 'years',
        'order_by_direction'  => 'DESC',
        'empty_message'       => lang('No trainer matches this focus yet. Choose another, or ask the front desk.'),
    ), $more);
};
// The cards of a list, in rows of four.
$trainer_grid = function () use ($widget_root, $row, $loop, $trainer_card) {
    return $widget_root(array($row(array($loop(array($trainer_card()))), '4')));
};

// The links above the list that narrow it: one per focus, each opening the
// trainer list with the filter set.
$trainer_filter_bar = function () use ($el, $link, $span, $query, $focus_titles) {
    $children = array($span(lang('Focus'), 'small fw-semibold text-body-secondary me-1', lang('Label')));
    $children[] = $link(lang('All'), '{{page:trainers}}', 'btn btn-sm btn-outline-secondary');
    foreach ($focus_titles as $v) {
        $children[] = $link($v, '{{page:trainers}}?' . $query(array('focus' => $v)), 'btn btn-sm btn-outline-secondary');
    }
    return $el('nav', 'd-flex flex-wrap align-items-center gap-2 mb-4', lang('Filters'), $children);
};

// The opening hours in a short list, beside the booking widget.
$trainer_hours = function () use ($el, $span, $opening_hours) {
    $items = array();
    foreach ($opening_hours as $h) {
        $items[] = $el('li', 'd-flex justify-content-between gap-3 py-1', $h['days'], array(
            $span($h['days'], '', lang('Days')),
            $span($h['hours'], 'pgy-price fw-semibold', lang('Hours')),
        ));
    }
    return $el('ul', 'list-unstyled small mb-0', lang('Opening Hours'), $items);
};

// ── Pages ───────────────────────────────────────────────────────────────

// Every trainer, narrowed by focus from the address.
$trainer_pages[] = array(
    'key'              => 'trainers',
    'name'             => lang('our-trainers'),
    'folder'           => 'public',
    'title'            => lang('Our Trainers'),
    'meta_description' => lang('The coaches of the gym: strength, conditioning, cycling, yoga and Pilates, with their classes and years of coaching.'),
    'tree'             => $page(array(
        $page_header(lang('Our Trainers'), lang('Meet the coaches who run the classes and the personal training. Choose a focus to narrow the list.')),
        $el('section', 'py-5', lang('Trainers'), array(
            $container(array($trainer_filter_bar(), $widget('trainers_list'))),
        ), $searchable('gy-trainers', lang('coach, coaches, instructor, personal trainer, team'))),
    )),
);

// One trainer: the photo, the focus, the name, the role, the words, the
// classes and the way to book a session.
$trainer_pages[] = array(
    'key'              => 'trainer',
    'name'             => lang('trainer'),
    'folder'           => 'public',
    'title'            => lang('Trainer'),
    'meta_description' => lang('A coach of the gym: what they coach, the classes they teach and how to book a session with them.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('trainer_detail'),
    )),
);

// Personal training at a free time: the booking widget in the editor's
// starter layout, beside the group classes and the opening hours.
$trainer_pages[] = array(
    'key'              => 'book_online',
    'name'             => lang('book-online'),
    'folder'           => 'public',
    'title'            => lang('Personal Training'),
    'meta_description' => lang('Book a personal training session online: choose the session, a coach and one of the free times.'),
    'tree'             => $page(array(
        $page_header(lang('Personal Training'), lang('Choose the session, a coach and one of the free times. The time is yours as soon as you confirm.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm', lang('Booking Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('booking_slots'))),
                        )),
                    ), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-3 bg-body-tertiary mb-4', lang('Group Classes'), array(
                            $heading('h2', lang('Rather train in a group?'), 'h5 fw-semibold mb-2'),
                            $para(lang('Pick a class on the timetable and ask for your place; the front desk confirms it the same day.'), 'small text-body-secondary mb-3'),
                            $link(lang('See the timetable'), '{{page:timetable}}', 'btn btn-sm btn-outline-primary px-3'),
                        )),
                        $el('div', 'p-4 rounded-3 border', lang('Opening hours'), array(
                            $heading('h2', lang('Opening hours'), 'h5 fw-semibold mb-3'),
                            $trainer_hours(),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('gy-personal-training', lang('personal trainer, PT, one to one, session, coach'))),
    ), false),
);

// The staff page that adds a trainer. The form is the Trainers collection;
// the sample trainers are written in when the form is made, so the lists
// do not open empty (pg_cf_seed_sample_records()).
$sample_trainers = array();
foreach ($trainers as $key => $t) {
    $teaches = array();
    foreach ($t['classes'] as $c) $teaches[] = $classes[$c]['name'];
    $sample_trainers[] = array(
        'name'    => $t['name'],
        'focus'   => $focuses[$t['focus']]['title'],
        'role'    => $t['role'],
        'years'   => (string)$t['years'],
        'summary' => $t['summary'],
        'bio'     => $t['bio'],
        'teaches' => implode("\n", $teaches),
        'photo'   => $portrait($t['name'], 900),
    );
}
$trainer_pages[] = array(
    'key'     => 'trainer_new',
    'name'    => lang('new-trainer'),
    'folder'  => 'private',
    'title'   => lang('New Trainer'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Trainers'),
        'collection'           => 1,
        'confirmation_message' => lang('The trainer is published.'),
        'confirmation_page_id' => '{{tab:trainers}}',
        'sample_records'       => $sample_trainers,
        // The photo is the trainer's share image (pg_cf_share_image_field_id()).
        'share_image_field'    => 'photo',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Trainer'), lang('Add the coach with a photo and a few words; they appear in the list as soon as you send it.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 rounded-3 border', lang('Form Card'), array($widget('trainer_form'))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// ── Widgets ─────────────────────────────────────────────────────────────

$trainer_widgets = array(

    // The staff's Trainers form: one coach.
    'trainer_form' => array(
        'page'   => 'trainer_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The coach')),
            $field('gy-trainer-name', lang('Name'), pg_cf_with_role($input('gy-trainer-name', 'text', 'name', 'off'), 'title')),
            $row(array(
                $col(array($field('gy-trainer-focus', lang('Focus'), pg_cf_with_role($select('gy-trainer-focus', 'focus', lang('Choose a focus…'), $focus_titles), 'category'))), '6'),
                $col(array($field('gy-trainer-years', lang('Years of Coaching'),
                    $input('gy-trainer-years', 'number', 'years', 'off', true, array('kind' => 'number'), array($attr('min', '0'), $attr('step', '1'))))), '6'),
            ), '3'),
            $field('gy-trainer-role', lang('Role'), $input('gy-trainer-role', 'text', 'role', 'off'), 'mb-3',
                lang('As members read it, for example Head Coach or Cycle Instructor.')),
            $form_step(2, lang('Words')),
            $field('gy-trainer-summary', lang('Summary'), pg_cf_with_role($textarea('gy-trainer-summary', 'summary', 2), 'description'), 'mb-3',
                lang('Two lines for the cards of the lists.')),
            $field('gy-trainer-bio', lang('About the coach'), $textarea('gy-trainer-bio', 'bio', 6)),
            $field('gy-trainer-teaches', lang('Classes they teach'), $textarea('gy-trainer-teaches', 'teaches', 3, false), 'mb-3',
                lang('One class on each line, in the words of the timetable.')),
            $form_step(3, lang('Photo')),
            $field('gy-trainer-photo', lang('Photo'), pg_cf_with_role($file('gy-trainer-photo', 'photo', 'public', 'image/*'), 'media'), 'mb-3',
                lang('A square photo works best; it is also the share image.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every trainer, filtered by the address (focus), with a search over
    // the words of the trainers.
    'trainers_list' => array(
        'page'   => 'trainers',
        'slug'   => lang('form-list'),
        'config' => $trainer_list_config(array(
            'items_per_page' => 12,
            'filters'        => array($trainer_filter('focus', 'is equal to', '', 'focus')),
            'search_enabled' => true,
            'search_fields'  => array('name', 'focus', 'role', 'summary', 'bio', 'teaches'),
            'search_width'   => 'full',
            'search_label'   => lang('Search the coaches: yoga, boxing, a beginner\'s coach...'),
        )),
        'tree'   => $trainer_grid(),
    ),

    // The home page's trainers, four at most.
    'home_trainers' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $trainer_list_config(array(
            'items_per_page' => 4,
            'max_results'    => 4,
            'empty_message'  => lang('No trainers yet.'),
        )),
        'tree'   => $trainer_grid(),
    ),

    // Personal training at a free time: the booking widget in the editor's
    // starter layout (services, coach, day and time, details,
    // confirmation).
    'booking_slots' => array(
        'page'   => 'book_online',
        'slug'   => lang('booking'),
        'config' => array('regionType' => 'booking'),
        'tree'   => 'starter',
    ),
);

// One trainer: the photo, the focus, the name, the role, the years, the
// words, the classes and the way to book a session or a class.
$trainer_sample = $trainers['deniz'];
$trainer_sample_teaches = array();
foreach ($trainer_sample['classes'] as $c) $trainer_sample_teaches[] = $classes[$c]['name'];
$trainer_widgets['trainer_detail'] = array(
    'page'   => 'trainer',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:trainer_new}}',
        'access_control'      => 'public',
        'not_found_message'   => lang('This trainer could not be found. They may have moved on, or the page was renamed.'),
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $el('div', 'text-center py-5', lang('Not Found'), array(
                $icon('bi-person-x', 'display-5 text-body-tertiary'),
                $heading('h2', lang('Trainer not found'), 'h3 fw-semibold mt-3'),
                $para(lang('This trainer could not be found. They may have moved on, or the page was renamed.'), 'text-body-secondary mb-4', $bind('not_found')),
                $link(lang('See the trainers'), '{{page:trainers}}', 'btn btn-primary px-4'),
            ), $show_when('record_not_found')),
            $el('article', '', lang('Trainer'), array(
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-4', lang('Toolbar'), array(
                    $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                        $icon('bi-arrow-left'),
                        $span(lang('All trainers'), '', lang('Link Text')),
                    ), array('href' => '{{page:trainers}}')),
                    $edit_button(),
                )),
                $row(array(
                    $col(array(
                        $optional_image('photo', 'name', 'rounded-3 object-fit-cover', 'ratio ratio-1x1',
                            $portrait($trainer_sample['name'], 900), lang('Photo')),
                    ), '5', '4'),
                    $col(array(
                        $span($focuses[$trainer_sample['focus']]['title'], 'badge bg-primary-subtle text-primary-emphasis mb-2', lang('Focus'), $bind('focus')),
                        $heading('h1', $trainer_sample['name'], 'pgy-display display-6 mb-1', $bind('name')),
                        $para($trainer_sample['role'], 'fw-semibold mb-3', $bind('role')),
                        $el('p', 'd-flex align-items-center gap-1 text-body-secondary mb-3', lang('Years of Coaching'), array(
                            $icon('bi-award', 'text-primary'),
                            $span((string)$trainer_sample['years'], 'pgy-price', lang('Value'), $bind('years')),
                            $span(lang('years of coaching'), '', lang('Unit')),
                        )),
                        $para($trainer_sample['summary'], 'lead text-body-secondary mb-4', $bind('summary')),
                        $el('div', 'pg-hide-if-empty mb-4', lang('About the coach'), array(
                            $heading('h2', lang('About the coach'), 'pgy-display h5 mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $trainer_sample['bio']), $bind('bio'))),
                        )),
                        $el('div', 'pg-hide-if-empty p-4 rounded-3 bg-body-tertiary mb-4', lang('Classes they teach'), array(
                            $heading('h2', lang('Classes they teach'), 'pgy-display h6 mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => implode("\n", $trainer_sample_teaches)), $bind('teaches'))),
                        )),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Book a session'), '{{page:book_online}}', 'btn btn-primary px-4'),
                            $link(lang('See the timetable'), '{{page:timetable}}', 'btn btn-outline-primary px-4'),
                        )),
                    ), '7', '8'),
                ), '4', '', 'g-lg-5'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $trainer_pages, 'widgets' => $trainer_widgets);
