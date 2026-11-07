<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Theatre", its learning pages: the youth theatre groups
 * and the school workshops by age, the school group request and the youth
 * theatre place request, each with the request's own page and its
 * messages, the visitor's list, the staff's list and the request's
 * e-mails. Included after parts.php, mail.php and hire.php in the same
 * scope (it uses the request pieces of hire.php); returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * A school group and a youth theatre place are requests, never a booked
 * day or a held place: records of the School Groups and the Youth Theatre
 * Places forms. The learning team answers each request and sets its
 * office-use-only Status; its answers are the page's comments, mailed to
 * the sender and to the theatre. A sender without an account gets one
 * (auto-registration), so the request's page and the visitor's list show
 * it at once. The workshop starts with what a workshop card sends
 * (?workshop=), the group with what a group card sends (?group=), each
 * the title parts.php writes.
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

// The sample requests the canvas shows.
$sample_workshop = $workshops['day']['title'];
$sample_school = lang('Riverside Primary School');
$sample_year_group = $year_groups[0];
$sample_pupils = '28';
$sample_adults = '4';
$sample_visit_day = $visit_day_choices[0];
$sample_school_notes = lang('Two pupils use wheelchairs, and the class is reading a play together this term.');
$sample_group = $youth_groups['junior']['title'];
$sample_participant = lang('Ada Cooper');
$sample_age = '11';
$sample_place_notes = lang('Ada has never acted before but loves singing. She has mild asthma and carries an inhaler.');

// A count of people: a number from 1 up to $max.
$people_count = function ($max) use ($attr) {
    return array($attr('min', '1'), $attr('max', (string)$max), $attr('step', '1'));
};

// ── Pages ───────────────────────────────────────────────────────────────

// The learning page: the youth theatre groups, the school workshops, how a
// school group visit works and the ways to ask.
$learning_group_cols = array();
foreach (array_keys($youth_groups) as $key) $learning_group_cols[] = $group_card($key);
$learning_workshop_cols = array();
foreach (array_keys($workshops) as $key) $learning_workshop_cols[] = $workshop_card($key);
$learning_pages[] = array(
    'key'              => 'learning',
    'name'             => lang('learning'),
    'folder'           => 'public',
    'title'            => lang('Learning'),
    'meta_description' => lang('A youth theatre from age five, an evening class for adults and workshops for schools, led by the theatre\'s actors and directors.'),
    'tree'             => $page(array(
        $page_header(lang('Learning'), lang('A youth theatre for every age, an evening class for grown-ups and workshops for schools, led by the people who make our shows.'), array(
            $el('div', 'd-flex flex-wrap gap-2 mt-3', lang('Buttons'), array(
                $link(lang('Ask for a Youth Theatre Place'), '{{page:youth_place}}', 'btn btn-primary px-4'),
                $link(lang('Book a School Workshop'), '{{page:school_group}}', 'btn btn-outline-secondary px-4'),
            )),
        )),
        $el('section', 'py-5', lang('Youth Theatre'), array(
            $container(array(
                $section_header(lang('Youth theatre'), lang('A group for every age')),
                $row($learning_group_cols, '4'),
            )),
        ), $searchable('th-youth-theatre', lang('youth theatre, drama club, acting classes, children, teenagers, adults, weekly'))),
        $el('section', 'py-5 bg-body-tertiary', lang('School Workshops'), array(
            $container(array(
                $section_header(lang('For schools'), lang('Workshops for your class')),
                $row($learning_workshop_cols, '4'),
            )),
        ), $searchable('th-school-workshops', lang('school trip, class visit, pupils, teachers, set text, backstage tour, careers'))),
        $el('section', 'py-5', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $section_header(lang('School groups'), lang('How a school workshop works')),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), array(
                            $step(1, lang('Choose a workshop'), lang('Pick the workshop for your pupils\' age, or tell us you are not sure yet.')),
                            $step(2, lang('Send a request'), lang('Tell us your school, how many are coming and the days that suit you. Your request opens its own page.')),
                            $step(3, lang('We find a day'), lang('The learning team writes to you there within two working days with a day and a time.')),
                            $step(4, lang('Come to the theatre'), lang('A member of the team meets your class at the stage door and leads the workshop.')),
                        )),
                    ), '', '7'),
                    $col(array(
                        $el('div', 'p-4 bg-body-tertiary', lang('Good to Know'), array(
                            $heading('h2', lang('Good to know'), 'pth-display h5 mb-3'),
                            $check_list(array(
                                lang('Schools in the city pay a reduced rate for every workshop.'),
                                lang('One adult for every eight pupils, and adults come free.'),
                                lang('Youth theatre places go first to those already on the waiting list.'),
                                lang('Bursaries cover youth theatre fees for families who need them.'),
                            ), 'list-unstyled small mb-4'),
                            $link(lang('My School Groups'), '{{page:my_school_groups}}', 'btn btn-outline-secondary w-100 mb-2'),
                            $link(lang('My Youth Theatre Places'), '{{page:my_youth_places}}', 'btn btn-outline-secondary w-100'),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('th-learning-how', lang('book a school workshop, price, ratio, adults, bursary, waiting list'))),
    )),
);

// The school group request. What is sent opens the request's own page.
$learning_pages[] = array(
    'key'              => 'school_group',
    'name'             => lang('book-a-school-workshop'),
    'folder'           => 'public',
    'title'            => lang('Book a School Workshop'),
    'meta_description' => lang('Request a workshop or a backstage tour for your class: the learning team finds a day with you within two working days.'),
    'form'             => array(
        'form_name'            => lang('School Groups'),
        'confirmation_message' => lang('Thank you, your school group request has reached us. The learning team writes to you within two working days.'),
        'confirmation_page_id' => '{{tab:school_group_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New school group request: ^^school^^'),
        'notify_page_id'       => '{{tab:email_school_group_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your school group request'),
        'confirm_page_id'      => '{{tab:email_school_group_received}}',
    ),
    'tree'             => $request_page(lang('Book a School Workshop'),
        lang('Choose a workshop, tell us about your class and the days that suit you. The learning team finds a day with you; nothing is paid now.'),
        'school_group_form',
        $request_aside(array(
            lang('Your request opens its own page, and an account for you if you have none.'),
            lang('The learning team writes to you there within two working days with a day and a time.'),
            lang('Once the day is set, we send a page of tips to share with the adults coming.'),
            lang('Plans change? Write to us on the request\'s page.'),
        ), lang('See the workshops'), '{{page:learning}}#th-school-workshops')),
);

// One school group request: the request, its status and the messages.
$learning_pages[] = $request_view('school_group_view', lang('school-group-request'), lang('School Group Request'),
    'school_group_detail', 'school_group_messages', lang('There is a new message about your school group'));

$learning_pages[] = $my_requests_page('my_school_groups', lang('my-school-groups'), lang('My School Groups'),
    lang('Your school group requests, their status and the learning team\'s answers, newest first.'), 'my_school_groups_list',
    'bi-backpack', lang('Another class?'), lang('Choose a workshop; your details are filled in for you.'),
    lang('See the workshops'), '{{page:learning}}#th-school-workshops');

$learning_pages[] = $staff_list_page('staff_school_groups', lang('all-school-group-requests'), lang('School Group Requests'),
    lang('Every school group request, newest first: find a day, set the status and write to the teacher under each.'),
    'staff_school_groups_list');

// The youth theatre place request. What is sent opens the request's own
// page.
$learning_pages[] = array(
    'key'              => 'youth_place',
    'name'             => lang('youth-theatre-place'),
    'folder'           => 'public',
    'title'            => lang('Ask for a Youth Theatre Place'),
    'meta_description' => lang('Ask for a place in a youth theatre group or the evening class: the learning team offers a place or adds you to the waiting list.'),
    'form'             => array(
        'form_name'            => lang('Youth Theatre Places'),
        'confirmation_message' => lang('Thank you, your request has reached us. The learning team writes to you within a week.'),
        'confirmation_page_id' => '{{tab:youth_place_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New youth theatre place request: ^^group^^'),
        'notify_page_id'       => '{{tab:email_youth_place_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your youth theatre place request'),
        'confirm_page_id'      => '{{tab:email_youth_place_received}}',
    ),
    'tree'             => $request_page(lang('Ask for a Youth Theatre Place'),
        lang('Choose a group and tell us about the young person, or about yourself for the evening class. No audition is needed.'),
        'youth_place_form',
        $request_aside(array(
            lang('Your request opens its own page, and an account for you if you have none.'),
            lang('The learning team writes to you there within a week, with a place or a place on the waiting list.'),
            lang('The first session is a free taster: come along before you decide.'),
            lang('A question about fees or a bursary? Write to us on the request\'s page.'),
        ), lang('See the groups'), '{{page:learning}}#th-youth-theatre')),
);

// One youth theatre place request: the request, its status and the
// messages.
$learning_pages[] = $request_view('youth_place_view', lang('youth-theatre-place-request'), lang('Youth Theatre Place Request'),
    'youth_place_detail', 'youth_place_messages', lang('There is a new message about your youth theatre place'));

$learning_pages[] = $my_requests_page('my_youth_places', lang('my-youth-theatre-places'), lang('My Youth Theatre Places'),
    lang('Your youth theatre place requests, their status and the learning team\'s answers, newest first.'), 'my_youth_places_list',
    'bi-stars', lang('Another group?'), lang('Choose a group; your details are filled in for you.'),
    lang('See the groups'), '{{page:learning}}#th-youth-theatre');

$learning_pages[] = $staff_list_page('staff_youth_places', lang('all-youth-theatre-place-requests'), lang('Youth Theatre Place Requests'),
    lang('Every youth theatre place request, newest first: offer a place, set the status and write to the family under each.'),
    'staff_youth_places_list');

// The e-mails: what the theatre receives and the reply to the sender of
// each request.
$learning_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_school_group_new',
    'name'  => lang('email-new-school-group-request'),
    'title' => lang('New School Group Request E-mail'),
    'tree'  => $mail_page(array($widget('email_school_group_admin'))),
));
$learning_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_school_group_received',
    'name'  => lang('email-school-group-request-received'),
    'title' => lang('School Group Request Received E-mail'),
    'tree'  => $mail_page(array($widget('email_school_group_client'))),
));
$learning_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_youth_place_new',
    'name'  => lang('email-new-youth-theatre-place-request'),
    'title' => lang('New Youth Theatre Place Request E-mail'),
    'tree'  => $mail_page(array($widget('email_youth_place_admin'))),
));
$learning_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_youth_place_received',
    'name'  => lang('email-youth-theatre-place-request-received'),
    'title' => lang('Youth Theatre Place Request Received E-mail'),
    'tree'  => $mail_page(array($widget('email_youth_place_client'))),
));

// ── Widgets ─────────────────────────────────────────────────────────────

// The school group request's workshop, class and preferred days, for its
// e-mails.
$mail_school_group = function () use ($mail_box, $mail_line, $span, $bind, $sample_workshop, $sample_school, $sample_year_group, $sample_pupils, $sample_adults, $sample_visit_day) {
    return $mail_box(array(
        $mail_line(lang('Workshop'), array($span($sample_workshop, '', lang('Workshop'), $bind('workshop')))),
        $mail_line(lang('School'), array($span($sample_school, '', lang('School'), $bind('school')))),
        $mail_line(lang('Year group'), array($span($sample_year_group, '', lang('Year group'), $bind('year_group')))),
        $mail_line(lang('Pupils'), array($span($sample_pupils, '', lang('Pupils'), $bind('pupils')))),
        $mail_line(lang('Adults'), array($span($sample_adults, '', lang('Adults'), $bind('adults')))),
        $mail_line(lang('Preferred date'), array($span(lang('Preferred date'), '', lang('Preferred date'), $bind('preferred_date')))),
        $mail_line(lang('Best time'), array($span($sample_visit_day, '', lang('Best time'), $bind('visit_time')))),
    ), lang('School Group Request'));
};
// The youth theatre place request's group and participant, for its
// e-mails.
$mail_youth_place = function () use ($mail_box, $mail_line, $span, $bind, $sample_group, $sample_participant, $sample_age) {
    return $mail_box(array(
        $mail_line(lang('Group'), array($span($sample_group, '', lang('Group'), $bind('group')))),
        $mail_line(lang('Participant'), array($span($sample_participant, '', lang('Participant'), $bind('participant')))),
        $mail_line(lang('Age'), array($span($sample_age, '', lang('Age'), $bind('age')))),
    ), lang('Youth Theatre Place Request'));
};
$school_group_row = function ($staff = false) use ($el, $icon, $span, $bind, $request_row, $sample_workshop, $sample_school, $sample_pupils, $school_group_statuses) {
    return $request_row($staff, $sample_workshop, 'workshop', lang('Workshop'), $school_group_statuses[0], array(
        $el('span', 'd-inline-flex align-items-center gap-1', lang('School'), array(
            $icon('bi-building'), $span($sample_school, '', lang('School'), $bind('school')),
        )),
        $el('span', 'd-inline-flex align-items-center gap-1', lang('Pupils'), array(
            $icon('bi-people'), $span($sample_pupils, '', lang('Pupils'), $bind('pupils')),
        )),
    ));
};
$youth_place_row = function ($staff = false) use ($el, $icon, $span, $bind, $request_row, $sample_group, $sample_participant, $sample_age, $place_statuses) {
    return $request_row($staff, $sample_group, 'group', lang('Group'), $place_statuses[0], array(
        $el('span', 'd-inline-flex align-items-center gap-1', lang('Participant'), array(
            $icon('bi-person'), $span($sample_participant, '', lang('Participant'), $bind('participant')),
        )),
        $el('span', 'd-inline-flex align-items-center gap-1', lang('Age'), array(
            $icon('bi-balloon'), $span($sample_age, '', lang('Age'), $bind('age')),
        )),
    ));
};
$school_group_missing = lang('This school group request could not be found, or it is not yours to see.');
$youth_place_missing = lang('This youth theatre place request could not be found, or it is not yours to see.');

$learning_widgets = array(

    // The school group request: the fields are the page's form. The
    // workshop starts with what a workshop card sends (?workshop=). The
    // Status is the learning team's (office use only): every request
    // starts as Request received.
    'school_group_form' => array(
        'page'   => 'school_group',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('Your class')),
            $field('th-sg-workshop', lang('Workshop'), $select('th-sg-workshop', 'workshop', lang('Choose a workshop'), $school_workshop_choices, true, '', array('url_param' => 'workshop'))),
            $row(array(
                $col(array($field('th-sg-school', lang('School'), $input('th-sg-school', 'text', 'school', 'organization'))), '6'),
                $col(array($field('th-sg-year-group', lang('Year group'), $select('th-sg-year-group', 'year_group', lang('Choose a year group'), $year_groups))), '6'),
            ), '3'),
            $row(array(
                $col(array($field('th-sg-pupils', lang('Pupils'), $input('th-sg-pupils', 'number', 'pupils', 'off', true, array('kind' => 'number'), $people_count(120)))), '6'),
                $col(array($field('th-sg-adults', lang('Adults'), $input('th-sg-adults', 'number', 'adults', 'off', true, array('kind' => 'number'), $people_count(30)), 'mb-3',
                    lang('One adult for every eight pupils.'))), '6'),
            ), '3'),
            $row(array(
                $col(array($field('th-sg-date', lang('Preferred date'), $input('th-sg-date', 'date', 'preferred_date', 'off', false, $not_before_today), 'mb-3',
                    lang('Leave it empty if any day suits you.'))), '6'),
                $col(array($field('th-sg-time', lang('Best time'), $select('th-sg-time', 'visit_time', '', $visit_day_choices, true, $visit_day_choices[2]))), '6'),
            ), '3'),
            $field('th-sg-notes', lang('Access needs and notes'), $textarea('th-sg-notes', 'notes', 3, false), 'mb-3',
                lang('Wheelchair users, special educational needs, a play you are studying.')),
            $form_step(2, lang('How to reach you')),
            $contact_fields('th-sg'),
            $radios('th-sg-reply', 'reply_by', lang('Reply by'), $contact_choices, $contact_choices[0][0]),
            $field('th-sg-status', lang('Status'), $select('th-sg-status', 'status', '', $school_group_statuses, false, $school_group_statuses[0], array('office_use_only' => true))),
            $agree('th-sg-consent', 'consent', lang('I agree that the theatre keeps these details to plan the workshop with me.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Request'), 'btn btn-primary btn-lg px-4'),
                $para(lang('The learning team writes to you within two working days.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // The youth theatre place request: the group starts with what a group
    // card sends (?group=). The sender is the parent or carer, or the
    // grown-up joining the evening class; the Status is the learning
    // team's (office use only).
    'youth_place_form' => array(
        'page'   => 'youth_place',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The group')),
            $field('th-yp-group', lang('Group'), $select('th-yp-group', 'group', lang('Choose a group'), $group_titles, true, '', array('url_param' => 'group'))),
            $row(array(
                $col(array($field('th-yp-participant', lang('Participant'), $input('th-yp-participant', 'text', 'participant', 'off'), 'mb-3',
                    lang('The young person\'s name, or yours for the evening class.'))), '8'),
                $col(array($field('th-yp-age', lang('Age'), $input('th-yp-age', 'number', 'age', 'off', true, array('kind' => 'number'),
                    array($attr('min', '5'), $attr('max', '99'), $attr('step', '1'))))), '4'),
            ), '3'),
            $field('th-yp-notes', lang('About them'), $textarea('th-yp-notes', 'notes', 3, false), 'mb-3',
                lang('Any stage experience, access or medical needs, and what they would love to do.')),
            $form_step(2, lang('How to reach you')),
            $contact_fields('th-yp'),
            $radios('th-yp-reply', 'reply_by', lang('Reply by'), $contact_choices, $contact_choices[0][0]),
            $field('th-yp-status', lang('Status'), $select('th-yp-status', 'status', '', $place_statuses, false, $place_statuses[0], array('office_use_only' => true))),
            $agree('th-yp-consent', 'consent', lang('I agree that the theatre keeps these details to offer the place and keep the group safe.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Request'), 'btn btn-primary btn-lg px-4'),
                $para(lang('The learning team writes to you within a week.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One school group request; the learning team's answers follow as
    // comments.
    'school_group_detail' => $detail_widget('school_group_view', 'school_group', $school_group_missing, 'submitter_only') + array(
        'tree' => $request_detail(lang('My School Groups'), 'my_school_groups', $school_group_missing, lang('School group request not found'),
            lang('School Group Request'), $sample_workshop, 'workshop', $school_group_statuses[0],
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
            $sample_school_notes,
            lang('Another day, more pupils or a question? Write to us below; we answer here and by e-mail.')),
    ),

    'school_group_messages' => $request_messages('school_group_view',
        lang('No messages yet. The learning team\'s answer comes here; you can write to us about the workshop too.')),

    'my_school_groups_list' => $request_list('my_school_groups', 'school_group', 'school_group_view', $school_group_row, array(), '',
        lang('No school group requests yet. Choose a workshop for your class.')),

    'staff_school_groups_list' => $request_list('staff_school_groups', 'school_group', 'school_group_view', $school_group_row,
        array('first_name', 'last_name', 'email', 'school', 'workshop', 'year_group', 'status'),
        lang('Search by name, e-mail, school, workshop or status...'), lang('No school group requests yet.')),

    // One youth theatre place request; the learning team's answers follow
    // as comments.
    'youth_place_detail' => $detail_widget('youth_place_view', 'youth_place', $youth_place_missing, 'submitter_only') + array(
        'tree' => $request_detail(lang('My Youth Theatre Places'), 'my_youth_places', $youth_place_missing, lang('Youth theatre place request not found'),
            lang('Youth Theatre Place Request'), $sample_group, 'group', $place_statuses[0],
            lang('Thank you, your request has reached us. The learning team writes to you here within a week; this page shows the status, and you can write to us below.'),
            array_merge(
                $detail(lang('Participant'), '', lang('Participant'), $bound($sample_participant, 'participant')),
                $detail(lang('Age'), '', lang('Age'), $bound($sample_age, 'age')),
                $detail(lang('Reply by'), '', lang('Reply by'), $bound($contact_choices[0][1], 'reply_by')),
                $detail(lang('Name'), '', lang('Name'), $visitor_name(), true),
                $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
            ),
            $sample_place_notes,
            lang('A question about the group, the fees or a bursary? Write to us below; we answer here and by e-mail.')),
    ),

    'youth_place_messages' => $request_messages('youth_place_view',
        lang('No messages yet. The learning team\'s answer comes here; you can write to us about the group too.')),

    'my_youth_places_list' => $request_list('my_youth_places', 'youth_place', 'youth_place_view', $youth_place_row, array(), '',
        lang('No youth theatre place requests yet. Choose a group and ask for a place.')),

    'staff_youth_places_list' => $request_list('staff_youth_places', 'youth_place', 'youth_place_view', $youth_place_row,
        array('first_name', 'last_name', 'email', 'participant', 'group', 'status'),
        lang('Search by name, e-mail, participant, group or status...'), lang('No youth theatre place requests yet.')),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_school_group_admin' => $detail_widget('email_school_group_new', 'school_group', lang('The school group request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new school group request')),
            $mail_para(lang('It came from the school workshop form. Offer a day and a time under the request on its page and set the status; what you write there reaches the teacher by e-mail.')),
            $mail_school_group(),
            $mail_sender($sample_school_notes),
            $mail_button(lang('Open the request'), '{{page:school_group_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_school_group_client' => $detail_widget('email_school_group_received', 'school_group', lang('The school group request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your school group request')),
            $mail_greeting(),
            $mail_para(lang('Thank you. The learning team writes to you within two working days with a day and a time, on the request\'s page and by e-mail.')),
            $mail_school_group(),
            $mail_para(lang('Plans change? Write to us on the request\'s page; everything about the workshop stays there.')),
            $mail_button(lang('Open my request'), '{{page:school_group_view}}?r=^^reference_code^^'),
            $mail_new_account(),
        )),
    ),
    'email_youth_place_admin' => $detail_widget('email_youth_place_new', 'youth_place', lang('The youth theatre place request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new youth theatre place request')),
            $mail_para(lang('It came from the youth theatre form. Offer a place or the waiting list under the request on its page and set the status; what you write there reaches the family by e-mail.')),
            $mail_youth_place(),
            $mail_sender($sample_place_notes),
            $mail_button(lang('Open the request'), '{{page:youth_place_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_youth_place_client' => $detail_widget('email_youth_place_received', 'youth_place', lang('The youth theatre place request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your youth theatre place request')),
            $mail_greeting(),
            $mail_para(lang('Thank you. The learning team writes to you within a week with a place or a place on the waiting list, on the request\'s page and by e-mail.')),
            $mail_youth_place(),
            $mail_para(lang('The first session is a free taster. A question about fees or a bursary? Write to us on the request\'s page.')),
            $mail_button(lang('Open my request'), '{{page:youth_place_view}}?r=^^reference_code^^'),
            $mail_new_account(),
        )),
    ),
);

return array('pages' => $learning_pages, 'widgets' => $learning_widgets);
