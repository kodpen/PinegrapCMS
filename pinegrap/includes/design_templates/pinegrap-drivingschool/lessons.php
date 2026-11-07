<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Driving School", its lesson requests and online
 * booking: the lesson request form, the request's own page with its
 * messages, the learner's own list and the staff's list, the two e-mails
 * of the form, and the booking page that places the booking widget.
 * Included after parts.php, mail.php, courses.php and enrolments.php in
 * the same scope (it reuses the record pieces of enrolments.php); returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * A lesson request is a record of the Lesson Requests form, never a
 * booking: the learner names the licence, an instructor or any, the
 * length, the gearbox, where to be picked up and a preferred day, and the
 * school confirms a time under the request. Its Status is office use
 * only. The licence starts with what a link sends (?licence=: the licence
 * title, as the licence pages, the course page and the footer send it),
 * the instructor with ?instructor= (the instructor's name, as the
 * instructor cards send it).
 *
 * Beside the form, book_online places the booking widget in the editor's
 * starter layout: a school that sets its lesson types and instructors in
 * Settings > Bookings takes bookings of a free time there; until then the
 * widget shows its own "no services" text and the request form stays.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$lesson_pages = array();

// The sample request the canvas shows: words, never a date.
$sample_licence = $licences['car']['title'];
$sample_instructor = $instructor_names[0];
$sample_length = $lesson_lengths[0];
$sample_gearbox = $gearboxes['manual'];
$sample_pickup = lang('Outside the library on Park Street');
$sample_lesson_time = $lesson_time_choices[1];
$sample_lesson_notes = lang('My test is in a month. I would like to practise roundabouts and parallel parking.');

// A request in a list: the licence, then the instructor, the length and
// the preferred time.
$lesson_row = function ($staff) use ($ds_record_row, $sample_licence, $sample_instructor, $sample_length, $sample_lesson_time, $sample_pickup, $lesson_statuses) {
    $items = array(
        array('bi-person', lang('Instructor'), $sample_instructor, 'instructor'),
        array('bi-hourglass-split', lang('Lesson Length'), $sample_length, 'lesson_length'),
        array('bi-clock', lang('Preferred Time'), $sample_lesson_time, 'preferred_time'),
    );
    if ($staff) $items[] = array('bi-geo-alt', lang('Pick-up Point'), $sample_pickup, 'pickup_point');
    return $ds_record_row(lang('Licence Class'), $sample_licence, 'licence', $lesson_statuses[0], $items, $staff);
};

// The lines of a lesson request, for its e-mails.
$mail_lesson = function () use ($mail_box, $mail_line, $span, $bind, $sample_licence, $sample_instructor, $sample_length, $sample_gearbox, $sample_pickup, $sample_lesson_time) {
    $line = function ($label, $sample, $field) use ($mail_line, $span, $bind) {
        return $mail_line($label, array($span($sample, '', $label, $bind($field))));
    };
    return $mail_box(array(
        $line(lang('Licence Class'), $sample_licence, 'licence'),
        $line(lang('Instructor'), $sample_instructor, 'instructor'),
        $line(lang('Lesson Length'), $sample_length, 'lesson_length'),
        $line(lang('Gearbox'), $sample_gearbox, 'gearbox'),
        $line(lang('Preferred Day'), lang('Date'), 'preferred_date'),
        $line(lang('Preferred Time'), $sample_lesson_time, 'preferred_time'),
        $line(lang('Pick-up Point'), $sample_pickup, 'pickup_point'),
    ), lang('Lesson Request'));
};

// The box that points to the other way of asking for a lesson.
$lesson_switch_box = function ($title, $text, $button, $key) use ($el, $heading, $para, $link) {
    return $el('div', 'p-4 rounded-3 bg-primary-subtle mb-4', $title, array(
        $heading('h2', $title, 'pdr-display h5 mb-2'),
        $para($text, 'small mb-3'),
        $link($button, '{{page:' . $key . '}}', 'btn btn-sm btn-primary px-3'),
    ));
};

// ── Lesson requests ─────────────────────────────────────────────────────

// The request form. What is sent opens the request's own page.
$lesson_pages[] = array(
    'key'              => 'lesson_request',
    'name'             => lang('book-a-lesson'),
    'folder'           => 'public',
    'title'            => lang('Book a Lesson'),
    'meta_description' => lang('Ask for a driving lesson: choose the licence, an instructor, the length and a day that suits you, and we confirm a time.'),
    'form'             => array(
        'form_name'            => lang('Lesson Requests'),
        'confirmation_message' => lang('Thank you, your lesson request has reached the school. We confirm a time within one working day.'),
        'confirmation_page_id' => '{{tab:lesson_request_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New lesson request: ^^licence^^'),
        'notify_page_id'       => '{{tab:email_lesson_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your lesson request'),
        'confirm_page_id'      => '{{tab:email_lesson_received}}',
    ),
    'tree'             => $ds_ask_page(lang('Book a Lesson'),
        lang('A single lesson, a mock test or a lesson before your test: tell us the licence, the instructor you would like and a day that suits you, and we confirm a time.'),
        'lesson_form',
        array_merge(array(
            $lesson_switch_box(lang('Pick a free time now'),
                lang('Lesson types and instructors set up for online booking show their free times; choose one and it is yours at once.'),
                lang('Book Online'), 'book_online'),
        ), $ds_form_aside(lang('What happens next'), array(
            lang('Your request opens its own page, and an account for you if you have none.'),
            lang('We confirm a day and a time within one working day, under your request and by e-mail.'),
            lang('Your instructor picks you up at the place you named.'),
        ), lang('Lessons are paid at the branch or to your instructor; nothing is charged online.')))),
);

// One lesson request: the request, its status and the messages under it.
$lesson_pages[] = array(
    'key'      => 'lesson_request_view',
    'name'     => lang('lesson-request'),
    'folder'   => 'registration',
    'title'    => lang('Lesson Request'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => $ds_messages_settings(lang('There is a new message about your lesson request')),
    'tree'     => $page(array(
        $widget('lesson_detail'),
        $comments_section(lang('Messages'), 'lesson_messages'),
    ), false),
);

// My Lesson Requests: the learner's own (for the staff, everybody's).
$lesson_pages[] = array(
    'key'     => 'my_lesson_requests',
    'name'    => lang('my-lesson-requests'),
    'folder'  => 'registration',
    'title'   => lang('My Lesson Requests'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $ds_my_page(lang('My Lesson Requests'), lang('Your lesson requests, their status and the school\'s answers, newest first.'),
        'my_lesson_requests_list', lang('Another lesson?'),
        lang('Ask for another lesson; your details are filled in for you.'), lang('Book a Lesson'), 'lesson_request'),
);

// The staff's list of every lesson request, newest first, with a search.
$lesson_pages[] = array(
    'key'     => 'staff_lesson_requests',
    'name'    => lang('all-lesson-requests'),
    'folder'  => 'private',
    'title'   => lang('Lesson Requests'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $ds_staff_list_page(lang('Lesson Requests'),
        lang('Every lesson request, newest first: confirm a time under each, then set the status.'), 'staff_lesson_requests_list'),
);

// ── Online booking ──────────────────────────────────────────────────────

// The booking widget in the editor's starter layout, beside the request
// form and the school's hours.
$lesson_pages[] = array(
    'key'              => 'book_online',
    'name'             => lang('book-online'),
    'folder'           => 'public',
    'title'            => lang('Book Online'),
    'meta_description' => lang('Book a driving lesson online: choose the lesson, an instructor and one of the free times, and it is booked at once.'),
    'tree'             => $page(array(
        $page_header(lang('Book Online'), lang('Choose the lesson, an instructor and one of the free times. The time is yours as soon as you confirm.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-3', lang('Booking Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('booking_slots'))),
                        )),
                    ), '', '8'),
                    $col(array(
                        $lesson_switch_box(lang('Rather ask first?'),
                            lang('No free time that suits you, or a first lesson to plan? Send a lesson request and we find a time together.'),
                            lang('Book a Lesson'), 'lesson_request'),
                        $el('div', 'p-4 rounded-3 border', lang('Opening hours'), array(
                            $heading('h2', lang('Opening hours'), 'pdr-display h5 mb-2'),
                            $para($school['hours'], 'small text-body-secondary mb-2'),
                            $para($school['phone'], 'small fw-semibold mb-0'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('drv-book-online', lang('book online, free time, driving lesson, instructor, mock test'))),
    ), false),
);

// ── E-mails ─────────────────────────────────────────────────────────────

$lesson_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_lesson_new',
    'name'  => lang('email-new-lesson-request'),
    'title' => lang('New Lesson Request E-mail'),
    'tree'  => $mail_page(array($widget('email_lesson_admin'))),
));
$lesson_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_lesson_received',
    'name'  => lang('email-lesson-request-received'),
    'title' => lang('Lesson Request Received E-mail'),
    'tree'  => $mail_page(array($widget('email_lesson_learner'))),
));

// ── Widgets ─────────────────────────────────────────────────────────────

$lesson_missing = lang('This lesson request could not be found, or it is not yours to see.');

$lesson_widgets = array(

    // The lesson request: the fields are the page's form. The licence and
    // the instructor start with what a link sends (?licence=,
    // ?instructor=). The Status is the school's (office use only).
    'lesson_form' => array(
        'page'   => 'lesson_request',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array_merge(array(
            $form_step(1, lang('Your lesson')),
            $row(array(
                $col(array($field('drv-ls-licence', lang('Licence Class'), $select('drv-ls-licence', 'licence', lang('Choose a licence…'), $licence_titles, true, '', array('url_param' => 'licence')))), '6'),
                $col(array($field('drv-ls-instructor', lang('Instructor'), $select('drv-ls-instructor', 'instructor', '', $instructor_choices, true, $instructor_choices[0], array('url_param' => 'instructor')))), '6'),
                $col(array($field('drv-ls-length', lang('Lesson Length'), $select('drv-ls-length', 'lesson_length', '', $lesson_lengths, true, $lesson_lengths[0]))), '6'),
                $col(array($field('drv-ls-gearbox', lang('Gearbox'), $select('drv-ls-gearbox', 'gearbox', '', $gearbox_titles, true, $gearbox_titles[0]))), '6'),
                $col(array($field('drv-ls-date', lang('Preferred Day'), $input('drv-ls-date', 'date', 'preferred_date', 'off', true, array('rules' => array('min_date' => 'today'))))), '6'),
                $col(array($field('drv-ls-time', lang('Preferred Time'), $select('drv-ls-time', 'preferred_time', '', $lesson_time_choices, true, $lesson_time_choices[4]))), '6'),
            ), '3'),
            $field('drv-ls-pickup', lang('Pick-up Point'), $input('drv-ls-pickup', 'text', 'pickup_point', 'street-address', true), 'mb-3',
                lang('Your home, school or work within our area, or the branch.')),
            $field('drv-ls-notes', lang('What would you like to practise?'), $textarea('drv-ls-notes', 'notes', 3, false), 'mb-3'),
            $form_step(2, lang('How to reach you')),
        ), $ds_contact_fields('drv-ls'), array(
            $field('drv-ls-status', lang('Status'), $select('drv-ls-status', 'status', '', $lesson_statuses, false, $lesson_statuses[0], array('office_use_only' => true))),
            $agree('drv-ls-consent', 'consent', lang('I agree that the school keeps these details to arrange my lesson, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Ask for the Lesson'), 'btn btn-primary btn-lg px-4'),
                $para(lang('We confirm a time within one working day.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        ))),
    ),

    'lesson_detail' => $ds_detail_widget('lesson_request_view', 'lesson_request', $lesson_missing, 'submitter_only') + array(
        'tree' => $ds_record_tree(array(
            'missing_icon'  => 'bi-calendar-x',
            'missing_title' => lang('Lesson request not found'),
            'missing'       => $lesson_missing,
            'back'          => lang('My Lesson Requests'),
            'back_key'      => 'my_lesson_requests',
            'thanks'        => lang('Thank you, your lesson request has reached the school. We confirm a time within one working day; this page shows the status, and you can write to us below.'),
            'card'          => lang('Request Card'),
            'eyebrow'       => lang('Lesson Request'),
            'title'         => $sample_licence,
            'title_field'   => 'licence',
            'status'        => $lesson_statuses[0],
            'details'       => array_merge(
                $detail(lang('Instructor'), '', lang('Instructor'), $bound($sample_instructor, 'instructor')),
                $detail(lang('Lesson Length'), '', lang('Lesson Length'), $bound($sample_length, 'lesson_length')),
                $detail(lang('Gearbox'), '', lang('Gearbox'), $bound($sample_gearbox, 'gearbox')),
                $detail(lang('Preferred Day'), '', lang('Preferred Day'), $bound(lang('Date'), 'preferred_date')),
                $detail(lang('Preferred Time'), '', lang('Preferred Time'), $bound($sample_lesson_time, 'preferred_time')),
                $detail(lang('Pick-up Point'), '', lang('Pick-up Point'), $bound($sample_pickup, 'pickup_point')),
                $detail(lang('Learner'), '', lang('Learner'), $learner_name(), true),
                $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
            ),
            'words_label'   => lang('What would you like to practise?'),
            'words'         => $sample_lesson_notes,
            'words_field'   => 'notes',
            'note'          => lang('Another day suits you better? Write to us below; we answer here and by e-mail.'),
        )),
    ),

    'lesson_messages' => $ds_messages_widget('lesson_request_view',
        lang('No messages yet. The time we confirm comes here; you can write to us too.')),

    'my_lesson_requests_list' => $ds_record_list('my_lesson_requests', 'lesson_request', 'lesson_request_view', false,
        lang('No lesson requests yet. Ask for a lesson and follow the answer here.'),
        array(), '', $lesson_row(false)),

    'staff_lesson_requests_list' => $ds_record_list('staff_lesson_requests', 'lesson_request', 'lesson_request_view', true, lang('No lesson requests yet.'),
        array('first_name', 'last_name', 'email', 'phone', 'licence', 'instructor', 'status'),
        lang('Search by learner, licence, instructor or status...'), $lesson_row(true)),

    // A lesson at a free time: the booking widget in the editor's starter
    // layout (lesson type, instructor, day and time, details,
    // confirmation).
    'booking_slots' => array(
        'page'   => 'book_online',
        'slug'   => lang('booking'),
        'config' => array('regionType' => 'booking'),
        'tree'   => 'starter',
    ),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_lesson_admin' => $ds_detail_widget('email_lesson_new', 'lesson_request', lang('This lesson request was not found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new lesson request')),
            $mail_para(lang('It came from the lesson form. Confirm a time under the request on its page and set the status; what you write there reaches the learner by e-mail.')),
            $mail_lesson(),
            $mail_learner(lang('What would you like to practise?'), $sample_lesson_notes, 'notes'),
            $mail_button(lang('Open the request'), '{{page:lesson_request_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_lesson_learner' => $ds_detail_widget('email_lesson_received', 'lesson_request', lang('This lesson request was not found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your lesson request')),
            $mail_greeting(),
            $mail_para(lang('Thank you. We confirm a day and a time within one working day, on the request\'s page and by e-mail.')),
            $mail_lesson(),
            $mail_button(lang('Open my request'), '{{page:lesson_request_view}}?r=^^reference_code^^'),
            $mail_new_account(),
        )),
    ),
);

return array('pages' => $lesson_pages, 'widgets' => $lesson_widgets);
