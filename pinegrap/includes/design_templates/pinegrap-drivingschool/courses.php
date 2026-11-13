<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Driving School", its courses: the staff page that adds
 * a course (the Courses form, a collection, with the sample courses
 * written in when it is made), the list of every course with its licence
 * and format filters, the course's own page and the list the home page
 * places. Included after parts.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * A course is a record of the Courses form. Licence, format and gearbox
 * are pick lists whose answers are the words parts.php gives, so a link
 * with ?licence= or ?format= (the filter links, the licence cards and
 * pages, the footer) filters the list by the words a visitor sees. The
 * price is words in the site's currency, never a product: the school
 * takes the payment at its desk once the enrolment is confirmed. Lessons
 * and duration are words, what is included is one item a line. Featured
 * is the school's choice (office use only): the home page shows the
 * featured courses.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$course_pages = array();

// ── Pieces ──────────────────────────────────────────────────────────────

// A filter row of a list's settings: the field, how it compares and the
// value, or where the value comes from (a URL parameter, left out while
// the parameter is not in the address).
$ds_filter = function ($field, $operator, $value, $param = '') {
    $row = array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $param !== '' ? 'url parameter' : '');
    if ($param !== '') $row['dynamic_value_attribute'] = $param;
    return $row;
};
// The answers of the Featured list: the first marks a record for the home.
$ds_featured_choices = array(lang('Yes'), lang('No'));

// A link to a template page that sends values of the record it is drawn
// in: $params maps the address name to the field it takes (the renderer's
// __page_link).
$ds_page_link = function ($page_key, $params) {
    $list = array();
    foreach ($params as $name => $token) $list[] = array('name' => $name, 'token' => $token);
    return array(
        '_bindings'   => array('href' => '__page_link'),
        '_bindParams' => array('href' => array('page_id' => '{{tab:' . $page_key . '}}', 'params' => $list)),
    );
};

// A list of courses: the Courses form, the course page, A to Z, and the
// words shown when nothing matches.
$ds_course_list = function ($empty, $more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:course_new}}',
        'detail_page_id'      => '{{tab:course}}',
        'order_by_field'      => 'name',
        'order_by_direction'  => 'ASC',
        'empty_message'       => $empty,
    ), $more);
};
// The course cards of a list, in rows of three.
$course_grid = function () use ($widget_root, $row, $loop, $course_card) {
    return $widget_root(array($row(array($loop(array($course_card()))), '4')));
};

// The links above a list that narrow it: one per answer of each pick
// list, each opening the list ($page) with the filter set.
$ds_filter_bar = function ($page, $groups) use ($el, $link, $span, $query) {
    $navs = array();
    foreach ($groups as $g) {
        $children = array($span($g['label'], 'small fw-semibold text-body-secondary me-1', lang('Label')));
        $children[] = $link(lang('All'), '{{page:' . $page . '}}', 'btn btn-sm btn-outline-secondary rounded-pill');
        foreach ($g['values'] as $v) {
            $children[] = $link($v, '{{page:' . $page . '}}?' . $query(array($g['param'] => $v)), 'btn btn-sm btn-outline-secondary rounded-pill');
        }
        $navs[] = $el('div', 'd-flex flex-wrap align-items-center gap-2 mb-2', $g['label'], $children);
    }
    return $el('nav', 'mb-4', lang('Filters'), $navs);
};

// ── Pages ───────────────────────────────────────────────────────────────

// Every course, narrowed by licence and format from the address.
$course_pages[] = array(
    'key'              => 'courses',
    'name'             => lang('courses'),
    'folder'           => 'public',
    'title'            => lang('Courses and Prices'),
    'meta_description' => lang('Driving courses for cars, automatics, motorcycles and trailers, with the price, the lessons and what each course includes.'),
    'tree'             => $page(array(
        $page_header(lang('Courses and Prices'), lang('Every course with its price and what it includes. Choose a licence or a format to narrow the list.')),
        $el('section', 'py-5', lang('Courses'), array(
            $container(array(
                $ds_filter_bar('courses', array(
                    array('label' => lang('Licence Class'), 'param' => 'licence', 'values' => $licence_titles),
                    array('label' => lang('Format'), 'param' => 'format', 'values' => $format_titles),
                )),
                $widget('courses_list'),
                $para(lang('Prices include the theory classes and the vehicle for the test where the course says so. You pay at the branch once we have confirmed your enrolment.'), 'small text-body-secondary mt-4 mb-0'),
            )),
        )),
    )),
);

// One course: its record.
$course_pages[] = array(
    'key'              => 'course',
    'name'             => lang('course'),
    'folder'           => 'public',
    'title'            => lang('Course'),
    'meta_description' => lang('A course of the driving school: the price, the lessons, what is included and how to enrol.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('course_detail'),
    )),
);

// The staff page that adds a course. The form is the Courses collection;
// the sample courses are written in when the form is made, so the lists
// do not open empty (pg_cf_seed_sample_records()).
$sample_courses = array();
foreach ($courses as $key => $c) {
    $sample_courses[] = array(
        'name'     => $c['name'],
        'licence'  => $licences[$c['licence']]['title'],
        'format'   => $formats[$c['format']],
        'gearbox'  => $gearboxes[$c['gearbox']],
        'price'    => $price_text($key),
        'lessons'  => $c['lessons'],
        'duration' => $c['hours'],
        'summary'  => $c['summary'],
        'included' => $included_text($key),
        'photo_1'  => $photo('course-' . $key, 800, 450),
        'featured' => $c['featured'] ? $ds_featured_choices[0] : $ds_featured_choices[1],
    );
}
$course_pages[] = array(
    'key'     => 'course_new',
    'name'    => lang('new-course'),
    'folder'  => 'private',
    'title'   => lang('New Course'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Courses'),
        'collection'           => 1,
        'confirmation_message' => lang('The course is published.'),
        'confirmation_page_id' => '{{tab:courses}}',
        'sample_records'       => $sample_courses,
        // The photo is the course's share image (pg_cf_share_image_field_id()).
        'share_image_field'    => 'photo_1',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Course'), lang('Add the course with its price and what it includes; it appears in the list as soon as you send it. Mark it featured from its Edit button.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 rounded-3 border', lang('Form Card'), array($widget('course_form'))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// ── Widgets ─────────────────────────────────────────────────────────────

$course_widgets = array(

    // The staff's Courses form: one course. Featured starts as No.
    'course_form' => array(
        'page'   => 'course_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The course')),
            $field('drv-course-name', lang('Course Name'), pg_cf_with_role($input('drv-course-name', 'text', 'name', 'off'), 'title')),
            $row(array(
                $col(array($field('drv-course-licence', lang('Licence Class'), pg_cf_with_role($select('drv-course-licence', 'licence', lang('Choose a licence…'), $licence_titles), 'category'))), '6'),
                $col(array($field('drv-course-format', lang('Format'), $select('drv-course-format', 'format', lang('Choose a format…'), $format_titles))), '6'),
                $col(array($field('drv-course-gearbox', lang('Gearbox'), $select('drv-course-gearbox', 'gearbox', '', $gearbox_titles, true, $gearbox_titles[0]))), '6'),
                $col(array($field('drv-course-price', lang('Price'), $input('drv-course-price', 'text', 'price', 'off'), 'mb-3',
                    lang('As it should read, with the currency.'))), '6'),
                $col(array($field('drv-course-lessons', lang('Lessons'), $input('drv-course-lessons', 'text', 'lessons', 'off'), 'mb-3',
                    lang('In words, for example 25 lessons.'))), '6'),
                $col(array($field('drv-course-duration', lang('Duration'), $input('drv-course-duration', 'text', 'duration', 'off'), 'mb-3',
                    lang('In words, never a date, for example about ten weeks.'))), '6'),
            ), '3'),
            $form_step(2, lang('Words')),
            $field('drv-course-summary', lang('Summary'), pg_cf_with_role($textarea('drv-course-summary', 'summary', 2), 'description'), 'mb-3',
                lang('One or two lines for the cards of the lists.')),
            $field('drv-course-included', lang('What Is Included'), $textarea('drv-course-included', 'included', 5, false), 'mb-3',
                lang('One item on each line.')),
            $form_step(3, lang('Photo')),
            $field('drv-course-photo', lang('Photo'), pg_cf_with_role($file('drv-course-photo', 'photo_1', 'public', 'image/*'), 'media'), 'mb-3',
                lang('The photo of the cards and the share image.')),
            $field('drv-course-featured', lang('Featured'), $select('drv-course-featured', 'featured', '', $ds_featured_choices, false, $ds_featured_choices[1], array('office_use_only' => true)),
                'mb-3', lang('A featured course is shown on the home page.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every course, filtered by the address (licence, format), A to Z,
    // with a search over the words of the courses.
    'courses_list' => array(
        'page'   => 'courses',
        'slug'   => lang('form-list'),
        'config' => $ds_course_list(lang('No course matches these choices yet. Try another licence or format, or ask us about the lessons you need.'), array(
            'items_per_page' => 12,
            'filters'        => array(
                $ds_filter('licence', 'is equal to', '', 'licence'),
                $ds_filter('format', 'is equal to', '', 'format'),
            ),
            'search_enabled' => true,
            'search_fields'  => array('name', 'licence', 'format', 'gearbox', 'summary', 'included'),
            'search_width'   => 'full',
            'search_label'   => lang('Search the courses: intensive, automatic, motorcycle...'),
        )),
        'tree'   => $course_grid(),
    ),

    // The home page's featured courses, three at most.
    'home_courses' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $ds_course_list(lang('No featured courses yet.'), array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'filters'        => array($ds_filter('featured', 'is equal to', $ds_featured_choices[0])),
        )),
        'tree'   => $course_grid(),
    ),
);

// One course: the licence and format, the name, the summary, the price,
// the facts, the photo, what is included, and beside them the way to
// enrol on this course, to ask for a single lesson in its licence class
// or to see the other courses of the class.
$course_sample_key = 'car_standard';
$course_sample = $courses[$course_sample_key];
$course_not_found = lang('This course could not be found. It may have been renamed or taken off the list.');
$course_widgets['course_detail'] = array(
    'page'   => 'course',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:course_new}}',
        'access_control'      => 'public',
        'not_found_message'   => $course_not_found,
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $el('div', 'text-center py-5', lang('Not Found'), array(
                $icon('bi-car-front', 'display-5 text-body-tertiary'),
                $heading('h2', lang('Course not found'), 'h3 fw-semibold mt-3'),
                $para($course_not_found, 'text-body-secondary mb-4', $bind('not_found')),
                $link(lang('See All Courses'), '{{page:courses}}', 'btn btn-primary px-4'),
            ), $show_when('record_not_found')),
            $el('article', '', lang('Course'), array(
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                    $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                        $icon('bi-arrow-left'),
                        $span(lang('All Courses'), '', lang('Link Text')),
                    ), array('href' => '{{page:courses}}')),
                    $edit_button(),
                )),
                $el('div', 'd-flex flex-wrap gap-2 mb-2', lang('Badges'), array(
                    $span($licences[$course_sample['licence']]['title'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Licence Class'), $bind('licence')),
                    $span($formats[$course_sample['format']], 'pdr-badge badge rounded-pill text-bg-secondary', lang('Format'), $bind('format')),
                )),
                $heading('h1', $course_sample['name'], 'pdr-display display-6 mb-2', $bind('name')),
                $para($course_sample['summary'], 'lead text-body-secondary mb-3', $bind('summary')),
                $course_facts($course_sample_key, 'd-flex flex-wrap gap-3 text-body-secondary mb-4'),
                $row(array(
                    $col(array(
                        $optional_image('photo_1', 'name', 'rounded-3 object-fit-cover', 'ratio ratio-16x9 mb-4',
                            $photo('course-' . $course_sample_key, 1280, 720), lang('Photo')),
                        $el('div', 'pg-hide-if-empty mb-4', lang('What Is Included'), array($included_list($course_sample_key))),
                    ), '', '8'),
                    $col(array(
                        $el('aside', 'card border-0 shadow-sm rounded-3', lang('Course Card'), array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $span(lang('Course price'), 'd-block small text-uppercase fw-semibold text-body-secondary', lang('Label')),
                                $span($price_text($course_sample_key), 'pdr-stat d-block display-6 fw-bold text-primary mb-2', lang('Price'), $bind('price')),
                                $para(lang('Paid at the branch once we confirm your enrolment by phone; nothing is charged online.'), 'small text-body-secondary mb-3'),
                                // Opens the enrolment with this course
                                // chosen (?course=).
                                $link(lang('Enrol on this course'), '#', 'btn btn-primary w-100 mb-2', array(),
                                    $ds_page_link('enrol', array('course' => 'name'))),
                                // Opens a lesson request in this course's
                                // licence class (?licence=).
                                $link(lang('Ask for a single lesson'), '#', 'btn btn-outline-secondary w-100 mb-2', array(),
                                    $ds_page_link('lesson_request', array('licence' => 'licence'))),
                                // Opens the list on this course's licence
                                // class (?licence=).
                                $link(lang('More courses for this licence'), '#', 'btn btn-link w-100', array(),
                                    $ds_page_link('courses', array('licence' => 'licence'))),
                            )),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5 mb-4'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $course_pages, 'widgets' => $course_widgets);
