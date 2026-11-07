<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Museum", its learning pages and timed entry: the
 * sessions for schools by age, the school visit request, the request's own
 * page with its messages, My School Visits and the staff's list, the
 * e-mails of the request, and the online booking page for a timed entry.
 * Included after parts.php, mail.php and events.php in the same scope (it
 * uses the request pieces of events.php); returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * A school visit is a request, never a booked slot: a record of the School
 * Visits form. The learning team answers each request and sets its
 * office-use-only Status; its answers are the page's comments, mailed to
 * the teacher and to the museum. A teacher without an account gets one
 * (auto-registration), so the request's page and My School Visits show it
 * at once. The session starts with what a session card sends (?session=,
 * the session's title as parts.php writes it).
 *
 * Timed entry is the booking widget with the editor's starter layout: the
 * museum sets its entry slots up as a bookable service with times in
 * Settings › Bookings; until then the widget shows its no-services text.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$learning_pages = array();

// The sample request the canvas shows.
$sample_session = $sessions['primary']['title'];
$sample_school = lang('Riverside Primary School');
$sample_year_group = $year_groups[1];
$sample_pupils = '28';
$sample_adults = '4';
$sample_visit_day = $visit_day_choices[0];
$sample_visit_notes = lang('Two pupils use wheelchairs, and we would like to eat our packed lunches inside if it rains.');

// ── Pages ───────────────────────────────────────────────────────────────

// The learning page: the sessions by age, how a school visit works and the
// way to request one.
$learning_session_cols = array();
foreach (array_keys($sessions) as $key) $learning_session_cols[] = $session_card($key);
$learning_pages[] = array(
    'key'              => 'learning',
    'name'             => lang('learning'),
    'folder'           => 'public',
    'title'            => lang('Learning'),
    'meta_description' => lang('School visits for every age, led by the museum\'s learning team: sessions, how a visit works and how to request one.'),
    'tree'             => $page(array(
        $page_header(lang('Learning'), lang('Sessions for schools led by our learning team in the galleries and the studio. Choose a session for your class and send us a request.'), array(
            $link(lang('Book a School Visit'), '{{page:school_visit}}', 'btn btn-primary px-4'),
        )),
        $el('section', 'py-5', lang('Sessions'), array(
            $container(array(
                $section_header(lang('For schools'), lang('A session for every age')),
                $row($learning_session_cols, '4'),
            )),
        ), $searchable('mz-learning-sessions', lang('school trip, class visit, pupils, students, teachers, workshop, education'))),
        $el('section', 'py-5 bg-body-tertiary', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $section_header(lang('School visits'), lang('How a school visit works')),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), array(
                            $step(1, lang('Choose a session'), lang('Pick the session for your pupils\' age, or tell us you are not sure yet.')),
                            $step(2, lang('Send a request'), lang('Tell us your school, how many are coming and the days that suit you. Your request opens its own page.')),
                            $step(3, lang('We find a day'), lang('The learning team writes to you there within two working days with a day and a time.')),
                            $step(4, lang('Come and explore'), lang('A member of the team meets your class at the entrance and leads the session.')),
                        )),
                    ), '', '7'),
                    $col(array(
                        $el('div', 'p-4 rounded-3 bg-body', lang('Good to Know'), array(
                            $heading('h2', lang('Good to know'), 'pmz-display h5 mb-3'),
                            $check_list(array(
                                lang('School sessions are free; a small fee covers materials in the studio.'),
                                lang('One adult for every eight pupils, and adults come free.'),
                                lang('A room for coats and packed lunches.'),
                                lang('Step-free access to every gallery.'),
                            ), 'list-unstyled small mb-4'),
                            $link(lang('Book a School Visit'), '{{page:school_visit}}', 'btn btn-primary w-100 mb-2'),
                            $link(lang('My School Visits'), '{{page:my_school_visits}}', 'btn btn-outline-secondary w-100'),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('mz-learning-how', lang('book a school trip, free, ratio, adults, packed lunch, access'))),
    )),
);

// The school visit request. What is sent opens the request's own page.
$learning_pages[] = array(
    'key'              => 'school_visit',
    'name'             => lang('book-a-school-visit'),
    'folder'           => 'public',
    'title'            => lang('Book a School Visit'),
    'meta_description' => lang('Request a school visit with a session for your class: the learning team finds a day with you within two working days.'),
    'form'             => array(
        'form_name'            => lang('School Visits'),
        'confirmation_message' => lang('Thank you, your school visit request has reached us. The learning team writes to you within two working days.'),
        'confirmation_page_id' => '{{tab:school_visit_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New school visit request: ^^school^^'),
        'notify_page_id'       => '{{tab:email_school_visit_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your school visit request'),
        'confirm_page_id'      => '{{tab:email_school_visit_received}}',
    ),
    'tree'             => $request_page(lang('Book a School Visit'),
        lang('Choose a session, tell us about your class and the days that suit you. The learning team finds a day with you; nothing is paid now.'),
        'school_visit_form',
        array_merge(array(
            $el('div', 'p-4 rounded-3 border mb-4', lang('Family Visit'), array(
                $heading('h2', lang('Coming as a family?'), 'pmz-display h5 mb-2'),
                $para(lang('No request needed: pick a free entry time and come.'), 'small text-body-secondary mb-3'),
                $link(lang('Book Timed Entry'), '{{page:book_online}}', 'btn btn-sm btn-outline-primary px-3'),
            )),
        ), $request_aside(array(
            lang('Your request opens its own page, and an account for you if you have none.'),
            lang('The learning team writes to you there within two working days with a day and a time.'),
            lang('Once the visit is booked, we send a page of tips to share with the adults coming.'),
            lang('Plans change? Write to us on the request\'s page.'),
        ), lang('See the sessions'), '{{page:learning}}'))),
);

// One school visit request: the request, its status and the messages.
$learning_pages[] = $request_view('school_visit_view', lang('school-visit-request'), lang('School Visit Request'),
    'school_visit_detail', 'school_visit_messages', lang('There is a new message about your school visit'));

// My School Visits: the teacher's own requests (for the staff, everybody's).
$learning_pages[] = array(
    'key'     => 'my_school_visits',
    'name'    => lang('my-school-visits'),
    'folder'  => 'registration',
    'title'   => lang('My School Visits'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('My School Visits'), lang('Your school visit requests, their status and the learning team\'s answers, newest first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('my_school_visits_list')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-3 bg-body-tertiary', lang('New Request'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-backpack', 'text-primary'),
                                $span(lang('Another class?'), '', lang('Text')),
                            )),
                            $para(lang('Choose a session; your details are filled in for you.'), 'small text-body-secondary mb-3'),
                            $link(lang('See the sessions'), '{{page:learning}}', 'btn btn-sm btn-primary px-3'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

$learning_pages[] = $staff_list_page('staff_school_visits', lang('all-school-visit-requests'), lang('School Visit Requests'),
    lang('Every school visit request, newest first: find a day, set the status and write to the teacher under each.'),
    'staff_school_visits_list');

// The e-mails: what the museum receives and the reply to the teacher.
$learning_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_school_visit_new',
    'name'  => lang('email-new-school-visit-request'),
    'title' => lang('New School Visit Request E-mail'),
    'tree'  => $mail_page(array($widget('email_school_visit_admin'))),
));
$learning_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_school_visit_received',
    'name'  => lang('email-school-visit-request-received'),
    'title' => lang('School Visit Request Received E-mail'),
    'tree'  => $mail_page(array($widget('email_school_visit_client'))),
));

// Timed entry: the booking widget shows the entry slots set up in
// Settings › Bookings and holds the chosen time while the visitor writes
// their details. The school visit request stays beside it for a class.
$learning_pages[] = array(
    'key'              => 'book_online',
    'name'             => lang('book-online'),
    'folder'           => 'public',
    'title'            => lang('Book Timed Entry'),
    'meta_description' => lang('Choose a day and a free entry time for your visit to the museum and its exhibitions, and skip the queue at the door.'),
    'tree'             => $page(array(
        $page_header(lang('Book Timed Entry'), lang('Choose a day and one of the free entry times. The time is yours as soon as you confirm; show the confirmation at the door.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-3', lang('Booking Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('booking_slots'))),
                        )),
                    ), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-3 bg-body-tertiary mb-4', lang('Ask First'), array(
                            $heading('h2', lang('Coming with a class?'), 'pmz-display h5 mb-2'),
                            $para(lang('For a school group, send us a request and the learning team finds a day and a session with you.'), 'small text-body-secondary mb-3'),
                            $link(lang('Book a School Visit'), '{{page:school_visit}}', 'btn btn-sm btn-outline-primary px-3'),
                        )),
                        $el('div', 'p-4 rounded-3 border', lang('Opening Hours'), array(
                            $heading('h2', lang('Opening hours'), 'pmz-display h5 mb-3'),
                            $hours_list('mb-3'),
                            $para($museum['free'] . '.', 'small mb-3'),
                            $link(lang('Plan Your Visit'), '{{page:visit}}', 'fw-semibold link-primary text-decoration-none small'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// ── Widgets ─────────────────────────────────────────────────────────────

// The request's session, class and preferred days, for its e-mails.
$mail_school_visit = function () use ($mail_box, $mail_line, $span, $bind, $sample_session, $sample_school, $sample_year_group, $sample_pupils, $sample_adults, $sample_visit_day) {
    return $mail_box(array(
        $mail_line(lang('Session'), array($span($sample_session, '', lang('Session'), $bind('session')))),
        $mail_line(lang('School'), array($span($sample_school, '', lang('School'), $bind('school')))),
        $mail_line(lang('Year group'), array($span($sample_year_group, '', lang('Year group'), $bind('year_group')))),
        $mail_line(lang('Pupils'), array($span($sample_pupils, '', lang('Pupils'), $bind('pupils')))),
        $mail_line(lang('Adults'), array($span($sample_adults, '', lang('Adults'), $bind('adults')))),
        $mail_line(lang('Preferred date'), array($span(lang('Preferred date'), '', lang('Preferred date'), $bind('preferred_date')))),
        $mail_line(lang('Best time'), array($span($sample_visit_day, '', lang('Best time'), $bind('visit_time')))),
    ), lang('School Visit Request'));
};
$school_visit_row = function ($staff = false) use ($el, $icon, $span, $bind, $request_row, $sample_session, $sample_school, $sample_pupils, $school_visit_statuses) {
    return $request_row($staff, $sample_session, 'session', lang('Session'), $school_visit_statuses[0], array(
        $el('span', 'd-inline-flex align-items-center gap-1', lang('School'), array(
            $icon('bi-building'), $span($sample_school, '', lang('School'), $bind('school')),
        )),
        $el('span', 'd-inline-flex align-items-center gap-1', lang('Pupils'), array(
            $icon('bi-people'), $span($sample_pupils, '', lang('Pupils'), $bind('pupils')),
        )),
    ));
};
$school_visit_missing = lang('This school visit request could not be found, or it is not yours to see.');
$not_before_today = array('rules' => array('min_date' => 'today'));
// A count of people: a number from 1 up to $max.
$people_count = function ($max) use ($attr) {
    return array($attr('min', '1'), $attr('max', (string)$max), $attr('step', '1'));
};

$learning_widgets = array(

    // The school visit request: the fields are the page's form. The session
    // starts with what a session card sends (?session=). The Status is the
    // learning team's (office use only): every request starts as Request
    // received.
    'school_visit_form' => array(
        'page'   => 'school_visit',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('Your class')),
            $field('mz-sv-session', lang('Session'), $select('mz-sv-session', 'session', lang('Choose a session…'), $school_session_choices, true, '', array('url_param' => 'session'))),
            $row(array(
                $col(array($field('mz-sv-school', lang('School'), $input('mz-sv-school', 'text', 'school', 'organization'))), '6'),
                $col(array($field('mz-sv-year-group', lang('Year group'), $select('mz-sv-year-group', 'year_group', lang('Choose…'), $year_groups))), '6'),
            ), '3'),
            $row(array(
                $col(array($field('mz-sv-pupils', lang('Pupils'), $input('mz-sv-pupils', 'number', 'pupils', 'off', true, array('kind' => 'number'), $people_count(120)))), '6'),
                $col(array($field('mz-sv-adults', lang('Adults'), $input('mz-sv-adults', 'number', 'adults', 'off', true, array('kind' => 'number'), $people_count(30)), 'mb-3',
                    lang('One adult for every eight pupils.'))), '6'),
            ), '3'),
            $row(array(
                $col(array($field('mz-sv-date', lang('Preferred date'), $input('mz-sv-date', 'date', 'preferred_date', 'off', false, $not_before_today), 'mb-3',
                    lang('Leave it empty if any day suits you.'))), '6'),
                $col(array($field('mz-sv-time', lang('Best time'), $select('mz-sv-time', 'visit_time', '', $visit_day_choices, true, $visit_day_choices[2]))), '6'),
            ), '3'),
            $field('mz-sv-notes', lang('Access needs and notes'), $textarea('mz-sv-notes', 'notes', 3, false), 'mb-3',
                lang('Wheelchair users, special educational needs, a topic you are studying.')),
            $form_step(2, lang('How to reach you')),
            $contact_fields('mz-sv'),
            $radios('mz-sv-reply', 'reply_by', lang('Reply by'), $contact_choices, $contact_choices[0][0]),
            $field('mz-sv-status', lang('Status'), $select('mz-sv-status', 'status', '', $school_visit_statuses, false, $school_visit_statuses[0], array('office_use_only' => true))),
            $agree('mz-sv-consent', 'consent', lang('I agree that the museum keeps these details to plan the visit with me.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Request'), 'btn btn-primary btn-lg px-4'),
                $para(lang('The learning team writes to you within two working days.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One school visit request; the learning team's answers follow as
    // comments.
    'school_visit_detail' => $detail_widget('school_visit_view', 'school_visit', $school_visit_missing, 'submitter_only') + array(
        'tree' => $request_detail(lang('My School Visits'), 'my_school_visits', $school_visit_missing, lang('School visit request not found'),
            lang('School Visit Request'), $sample_session, 'session', $school_visit_statuses[0],
            lang('Thank you, your request has reached us. The learning team writes to you here within two working days; this page shows the status, and you can write to us below.'),
            array_merge(
                $detail(lang('School'), '', lang('School'), $bound($sample_school, 'school')),
                $detail(lang('Year group'), '', lang('Year group'), $bound($sample_year_group, 'year_group')),
                $detail(lang('Pupils'), '', lang('Pupils'), $bound($sample_pupils, 'pupils')),
                $detail(lang('Adults'), '', lang('Adults'), $bound($sample_adults, 'adults')),
                $detail(lang('Preferred date'), '', lang('Preferred date'), $bound(lang('Preferred date'), 'preferred_date')),
                $detail(lang('Best time'), '', lang('Best time'), $bound($sample_visit_day, 'visit_time')),
                $detail(lang('Name'), '', lang('Name'), $visitor_name(), true),
                $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
            ),
            $sample_visit_notes,
            lang('Another day, more pupils or a question? Write to us below; we answer here and by e-mail.')),
    ),

    'school_visit_messages' => $request_messages('school_visit_view',
        lang('No messages yet. The learning team\'s answer comes here; you can write to us about the visit too.')),

    'my_school_visits_list' => $request_list('my_school_visits', 'school_visit', 'school_visit_view', $school_visit_row, array(), '',
        lang('No school visit requests yet. Choose a session for your class.')),

    'staff_school_visits_list' => $request_list('staff_school_visits', 'school_visit', 'school_visit_view', $school_visit_row,
        array('first_name', 'last_name', 'email', 'school', 'session', 'year_group', 'status'),
        lang('Search by name, e-mail, school, session or status...'), lang('No school visit requests yet.')),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_school_visit_admin' => $detail_widget('email_school_visit_new', 'school_visit', lang('The school visit request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new school visit request')),
            $mail_para(lang('It came from the school visit form. Offer a day and a time under the request on its page and set the status; what you write there reaches the teacher by e-mail.')),
            $mail_school_visit(),
            $mail_sender($sample_visit_notes),
            $mail_button(lang('Open the request'), '{{page:school_visit_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_school_visit_client' => $detail_widget('email_school_visit_received', 'school_visit', lang('The school visit request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your school visit request')),
            $mail_greeting(),
            $mail_para(lang('Thank you. The learning team writes to you within two working days with a day and a time, on the request\'s page and by e-mail.')),
            $mail_school_visit(),
            $mail_para(lang('Plans change? Write to us on the request\'s page; everything about the visit stays there.')),
            $mail_button(lang('Open my request'), '{{page:school_visit_view}}?r=^^reference_code^^'),
            $mail_new_account(),
        )),
    ),

    // The booking widget with the editor's starter layout; it shows its
    // no-services text until Settings › Bookings has a service.
    'booking_slots' => array(
        'page'   => 'book_online',
        'slug'   => lang('booking'),
        'config' => array('regionType' => 'booking'),
        'tree'   => 'starter',
    ),
);

return array('pages' => $learning_pages, 'widgets' => $learning_widgets);
