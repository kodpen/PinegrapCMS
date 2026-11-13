<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Driving School", its pass stories: the staff page that
 * adds a story (the Pass Stories form, a collection, with the sample
 * stories written in when it is made), the list of every story with its
 * licence filter, the story's own page and the list the home page places.
 * Included after parts.php and courses.php in the same scope (it reuses
 * $ds_filter, $ds_page_link, $ds_featured_choices and $ds_filter_bar);
 * returns array('pages' => ..., 'widgets' => ...).
 *
 * A story is a record of the Pass Stories form: the learner's first name
 * only, the licence class, at which try they passed, the instructor, the
 * lessons as words, the learner's own words and a photo. Licence, try and
 * instructor are pick lists whose answers are the words parts.php gives,
 * so a link with ?licence= filters the list by the words a visitor sees,
 * and the story page opens a lesson request with its instructor chosen.
 * Featured is the school's choice (office use only): the home page shows
 * the featured stories.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$story_pages = array();

// A list of stories: the Pass Stories form, the story page, newest first,
// and the words shown when nothing matches.
$ds_story_list = function ($empty, $more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:story_new}}',
        'detail_page_id'      => '{{tab:story}}',
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
        'empty_message'       => $empty,
    ), $more);
};
// The story cards of a list, in rows of three.
$story_grid = function () use ($widget_root, $row, $loop, $story_card) {
    return $widget_root(array($row(array($loop(array($story_card()))), '4')));
};

// ── Pages ───────────────────────────────────────────────────────────────

// Every story, narrowed by licence from the address.
$story_pages[] = array(
    'key'              => 'stories',
    'name'             => lang('pass-stories'),
    'folder'           => 'public',
    'title'            => lang('Pass Stories'),
    'meta_description' => lang('Learners who passed their driving test with us, in their own words: the licence, the lessons it took and their instructor.'),
    'tree'             => $page(array(
        $page_header(lang('Pass Stories'), lang('Learners who passed with us tell how it went: the nerves, the lessons it took and the day they passed. Choose a licence to narrow the list.')),
        $el('section', 'py-5', lang('Stories'), array(
            $container(array(
                $ds_filter_bar('stories', array(
                    array('label' => lang('Licence Class'), 'param' => 'licence', 'values' => $licence_titles),
                )),
                $widget('stories_list'),
            )),
        ), $searchable('drv-stories', lang('pass stories, passed first time, reviews, learner drivers, testimonials'))),
    )),
);

// One story: its record.
$story_pages[] = array(
    'key'              => 'story',
    'name'             => lang('pass-story'),
    'folder'           => 'public',
    'title'            => lang('Pass Story'),
    'meta_description' => lang('A learner\'s pass story: the licence, the lessons it took, the instructor and the day they passed.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('story_detail'),
    )),
);

// The staff page that adds a story. The form is the Pass Stories
// collection; the sample stories are written in when the form is made, so
// the lists do not open empty (pg_cf_seed_sample_records()).
$sample_stories = array();
foreach ($stories as $key => $s) {
    $sample_stories[] = array(
        'name'       => $s['name'],
        'licence'    => $licences[$s['licence']]['title'],
        'try'        => $try_choices[$s['try']],
        'instructor' => $instructors[$s['instructor']]['name'],
        'lessons'    => $s['lessons'],
        'words'      => $s['quote'],
        'photo_1'    => $photo('story-' . $key, 800, 600),
        'featured'   => $s['featured'] ? $ds_featured_choices[0] : $ds_featured_choices[1],
    );
}
$story_pages[] = array(
    'key'     => 'story_new',
    'name'    => lang('new-pass-story'),
    'folder'  => 'private',
    'title'   => lang('New Pass Story'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Pass Stories'),
        'collection'           => 1,
        'confirmation_message' => lang('The pass story is published.'),
        'confirmation_page_id' => '{{tab:stories}}',
        'sample_records'       => $sample_stories,
        // The photo is the story's share image (pg_cf_share_image_field_id()).
        'share_image_field'    => 'photo_1',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Pass Story'), lang('Add a learner\'s story once they have passed and agreed to share it; it appears in the list as soon as you send it. Mark it featured from its Edit button.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 rounded-3 border', lang('Form Card'), array($widget('story_form'))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// ── Widgets ─────────────────────────────────────────────────────────────

$story_widgets = array(

    // The staff's Pass Stories form: one story. Featured starts as No.
    'story_form' => array(
        'page'   => 'story_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The learner')),
            $field('drv-story-name', lang('First Name'), pg_cf_with_role($input('drv-story-name', 'text', 'name', 'off'), 'title'), 'mb-3',
                lang('The first name only, as the learner agreed.')),
            $row(array(
                $col(array($field('drv-story-licence', lang('Licence Class'), pg_cf_with_role($select('drv-story-licence', 'licence', lang('Choose a licence…'), $licence_titles), 'category'))), '6'),
                $col(array($field('drv-story-try', lang('Passed At'), $select('drv-story-try', 'try', '', $try_choices, true, $try_choices[0]))), '6'),
                $col(array($field('drv-story-instructor', lang('Instructor'), $select('drv-story-instructor', 'instructor', lang('Choose an instructor…'), $instructor_names))), '6'),
                $col(array($field('drv-story-lessons', lang('Lessons'), $input('drv-story-lessons', 'text', 'lessons', 'off'), 'mb-3',
                    lang('In words, for example 26 lessons.'))), '6'),
            ), '3'),
            $form_step(2, lang('Their words')),
            $field('drv-story-words', lang('The Learner\'s Words'), pg_cf_with_role($textarea('drv-story-words', 'words', 4), 'description'), 'mb-3',
                lang('A few lines in the learner\'s own words.')),
            $field('drv-story-photo', lang('Photo'), pg_cf_with_role($file('drv-story-photo', 'photo_1', 'public', 'image/*'), 'media'), 'mb-3',
                lang('A photo the learner agreed to share, for example with their pass certificate.')),
            $field('drv-story-featured', lang('Featured'), $select('drv-story-featured', 'featured', '', $ds_featured_choices, false, $ds_featured_choices[1], array('office_use_only' => true)),
                'mb-3', lang('A featured story is shown on the home page.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every story, filtered by the address (licence), newest first.
    'stories_list' => array(
        'page'   => 'stories',
        'slug'   => lang('form-list'),
        'config' => $ds_story_list(lang('No pass story for this licence yet. Choose another licence, or be the next to pass.'), array(
            'items_per_page' => 12,
            'filters'        => array($ds_filter('licence', 'is equal to', '', 'licence')),
        )),
        'tree'   => $story_grid(),
    ),

    // The home page's featured stories, three at most.
    'home_stories' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $ds_story_list(lang('No featured pass stories yet.'), array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'filters'        => array($ds_filter('featured', 'is equal to', $ds_featured_choices[0])),
        )),
        'tree'   => $story_grid(),
    ),
);

// One story: the photo, the licence and try, the name, the words, the
// instructor and the lessons, and beside them the way to a lesson with the
// same instructor and to the courses of the same licence.
$story_sample_key = 'zeynep';
$story_sample = $stories[$story_sample_key];
$story_not_found = lang('This pass story could not be found. It may have been taken off the list.');
$story_widgets['story_detail'] = array(
    'page'   => 'story',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:story_new}}',
        'access_control'      => 'public',
        'not_found_message'   => $story_not_found,
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $el('div', 'text-center py-5', lang('Not Found'), array(
                $icon('bi-trophy', 'display-5 text-body-tertiary'),
                $heading('h2', lang('Pass story not found'), 'h3 fw-semibold mt-3'),
                $para($story_not_found, 'text-body-secondary mb-4', $bind('not_found')),
                $link(lang('All Pass Stories'), '{{page:stories}}', 'btn btn-primary px-4'),
            ), $show_when('record_not_found')),
            $el('article', '', lang('Pass Story'), array(
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-4', lang('Toolbar'), array(
                    $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                        $icon('bi-arrow-left'),
                        $span(lang('All Pass Stories'), '', lang('Link Text')),
                    ), array('href' => '{{page:stories}}')),
                    $edit_button(),
                )),
                $row(array(
                    $col(array(
                        $optional_image('photo_1', 'name', 'rounded-3 object-fit-cover', 'ratio ratio-4x3',
                            $photo('story-' . $story_sample_key, 960, 720), lang('Photo')),
                    ), '', '5'),
                    $col(array(
                        $el('div', 'd-flex flex-wrap gap-2 mb-3', lang('Badges'), array(
                            $span($licences[$story_sample['licence']]['title'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Licence Class'), $bind('licence')),
                            $span($try_choices[$story_sample['try']], 'pdr-badge badge rounded-pill text-bg-secondary', lang('Passed At'), $bind('try')),
                        )),
                        $heading('h1', $story_sample['name'], 'pdr-display display-6 mb-3', $bind('name')),
                        $el('blockquote', 'mb-4', lang('Quote'), array(
                            $para($story_sample['quote'], 'pdr-quote fs-5 lh-lg mb-0', $bind('words')),
                        )),
                        $el('p', 'd-flex flex-wrap gap-3 text-body-secondary mb-4', lang('Facts'), array(
                            $el('span', 'd-inline-flex align-items-center gap-1', lang('Instructor'), array(
                                $icon('bi-person-badge', 'text-primary'),
                                $span($instructors[$story_sample['instructor']]['name'], '', lang('Instructor'), $bind('instructor')),
                            )),
                            $el('span', 'd-inline-flex align-items-center gap-1 pg-hide-if-empty', lang('Lessons'), array(
                                $icon('bi-journal-check', 'text-primary'),
                                $span($story_sample['lessons'], 'pg-field-value', lang('Lessons'), $bind('lessons')),
                            )),
                        )),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            // Opens a lesson request with this story's
                            // instructor chosen (?instructor=).
                            $link(lang('Book a lesson with this instructor'), '#', 'btn btn-primary px-4', array(),
                                $ds_page_link('lesson_request', array('instructor' => 'instructor'))),
                            // Opens the course list on this story's
                            // licence class (?licence=).
                            $link(lang('Courses for this licence'), '#', 'btn btn-outline-secondary px-4', array(),
                                $ds_page_link('courses', array('licence' => 'licence'))),
                        )),
                    ), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $story_pages, 'widgets' => $story_widgets);
