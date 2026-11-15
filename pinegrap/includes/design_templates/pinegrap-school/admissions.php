<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Private School", its admissions: the admission
 * application, the application's own page with its messages, My
 * Applications and the staff's list; the school visit request and the
 * staff's list of visits; the privacy page the consent boxes link, the
 * e-mails of both forms and the new message e-mail every thread of the
 * site sends, and the widgets that draw them. Included after parts.php and
 * mail.php in the same scope; returns array('pages' => ..., 'widgets' => ...).
 *
 * An application is a request for a place, never an enrolment: a record of
 * the Admission Applications form. A parent without an account gets one
 * (auto-registration), so the application's page and My Applications show
 * it at once. Its Status is an office-use-only field the school changes in
 * the control panel (received, visit booked, place offered, waiting list,
 * not this time); the visit day and the offer are written under it as
 * messages (the page's comments), mailed to the parent and to the school.
 * The child's documents are kept in the template's private folder: the
 * application's page links them for whoever may read the record.
 *
 * The programme starts with what a link sends (?programme=: the
 * programme's title, as the programme pages write it).
 *
 * A visit request is a smaller form: the day and time the family would
 * like as words, how many come, and who to call. The school calls back to
 * agree the day and keeps the status in the staff's list; the request has
 * no page of its own.
 *
 * The helpers of this file are named ad_…, so they never take the place
 * of a helper another page file of the folder defines in the same scope.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$admission_pages = array();

// The sample application the canvas shows. The child is never named and
// the birth date is never a date in the sample: the fields show what they
// hold.
$ad_sample_programme = $programmes['kindergarten']['title'];
$ad_sample_start = $start_choices[0];
$ad_sample_birth = lang('Date of birth');
$ad_sample_school = lang('At home until now');
$ad_sample_notes = lang('Our child is shy for the first days and has a nut allergy. We would like to visit before we decide.');
$ad_sample_day = lang('Any Tuesday or Thursday');

// ── Page shells ─────────────────────────────────────────────────────────

// The side column of the application page: what happens next, what to
// have ready and the office hours.
$ad_apply_aside = function () use ($el, $heading, $para, $icon, $span, $check_list, $link, $school) {
    $steps = array(
        lang('Your application opens its own page, and an account for you if you have none.'),
        lang('The admissions office calls you within a week to book a visit for you and your child.'),
        lang('After the visit we offer a place, or a place on the waiting list when the group is full.'),
        lang('Questions or more documents? Write under your application.'),
    );
    $items = array();
    foreach ($steps as $n => $text) {
        $items[] = $el('li', $n < count($steps) - 1 ? 'mb-2' : '', lang('Step'), array(), array('text' => $text));
    }
    return array(
        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('How It Works'), array(
            $heading('h2', lang('What happens next'), 'psh-display h5 mb-3'),
            $el('ol', 'small text-body-secondary ps-3 mb-0', lang('List'), $items),
        )),
        $el('div', 'p-4 rounded-4 border mb-4', lang('Documents'), array(
            $heading('h2', lang('Good to have ready'), 'psh-display h5 mb-3'),
            $check_list(array(
                lang('Your child\'s birth certificate or identity card'),
                lang('The vaccination card'),
                lang('A report from the last nursery or school, if there is one'),
            ), 'list-unstyled small mb-0'),
        )),
        $el('div', 'p-4 rounded-4 border', lang('Office Hours'), array(
            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                $icon('bi-clock', 'text-primary'),
                $span(lang('Office Hours'), '', lang('Text')),
            )),
            $para($school['hours'], 'small text-body-secondary mb-1'),
            $para($school['phone'], 'small fw-semibold mb-3'),
            $link(lang('Book a School Visit'), '{{page:visit}}', 'small fw-semibold'),
        )),
    );
};

// The side column of the visit page: what a visit holds and the office.
$ad_visit_aside = function () use ($el, $heading, $para, $icon, $span, $check_list, $school) {
    return array(
        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('On a Visit'), array(
            $heading('h2', lang('What a visit holds'), 'psh-display h5 mb-3'),
            $check_list(array(
                lang('A walk through the classrooms, the garden and the dining hall on an ordinary day.'),
                lang('Time with a teacher of the programme you are thinking of.'),
                lang('Every question about the day, the meals and admissions answered.'),
            ), 'list-unstyled small mb-0'),
        )),
        $el('div', 'p-4 rounded-4 border', lang('Office Hours'), array(
            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                $icon('bi-clock', 'text-primary'),
                $span(lang('Office Hours'), '', lang('Text')),
            )),
            $para($school['hours'], 'small text-body-secondary mb-1'),
            $para($school['phone'], 'small fw-semibold mb-0'),
        )),
    );
};

// ── Pages ───────────────────────────────────────────────────────────────

// The admission application. What is sent opens the application's page.
$admission_pages[] = array(
    'key'              => 'apply',
    'name'             => lang('apply'),
    'folder'           => 'public',
    'title'            => lang('Apply for a Place'),
    'meta_description' => lang('Send an admission application for your child; the admissions office calls you within a week to book a visit.'),
    'form'             => array(
        'form_name'            => lang('Admission Applications'),
        'confirmation_message' => lang('Thank you, your application has reached us. The admissions office calls you within a week.'),
        'confirmation_page_id' => '{{tab:application_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New admission application: ^^programme^^'),
        'notify_page_id'       => '{{tab:email_application_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your admission application'),
        'confirm_page_id'      => '{{tab:email_application_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Apply for a Place'),
            lang('Tell us about your child and the programme you are thinking of. An application is not a commitment: we meet first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-4', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('application_form'))),
                        )),
                    ), '', '8'),
                    $col($ad_apply_aside(), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// One application: the child, the programme, its status and the messages
// under it.
$admission_pages[] = array(
    'key'      => 'application_view',
    'name'     => lang('admission-application'),
    'folder'   => 'registration',
    'title'    => lang('Admission Application'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                        'attachments' => true,
                        'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new message about your admission application'),
                        'notify_email' => '{{site_email}}'),
    'tree'     => $page(array(
        $widget('application_detail'),
        $comments_section(lang('Messages'), 'application_messages'),
    ), false),
);

// My Applications: the parent's own applications (for the staff,
// everybody's).
$admission_pages[] = array(
    'key'     => 'my_applications',
    'name'    => lang('my-applications'),
    'folder'  => 'registration',
    'title'   => lang('My Applications'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('My Applications'), lang('Your admission applications, their status and our answers, newest first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('my_applications_list')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('Another Child'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-plus-circle', 'text-primary'),
                                $span(lang('A brother or sister?'), '', lang('Text')),
                            )),
                            $para(lang('Send an application for another child; your details are filled in for you.'), 'small text-body-secondary mb-3'),
                            $link(lang('Apply Now'), '{{page:apply}}', 'btn btn-sm btn-primary rounded-pill px-3'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// The staff's list of every application, newest first, with a search.
$admission_pages[] = array(
    'key'     => 'staff_applications',
    'name'    => lang('all-admission-applications'),
    'folder'  => 'private',
    'title'   => lang('Admission Applications'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $staff_page(array(
        $page_header(lang('Admission Applications'), lang('Every admission application, newest first: book the visit, offer the place, set the status and write to the family under each.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array($widget('staff_applications_list'))),
        )),
    )),
);

// The school visit request. What is sent shows the form's thank-you note.
$admission_pages[] = array(
    'key'              => 'visit',
    'name'             => lang('book-a-school-visit'),
    'folder'           => 'public',
    'title'            => lang('Book a School Visit'),
    'meta_description' => lang('Come and see the school on an ordinary day: tell us when suits you, and the office calls you to agree the day.'),
    'form'             => array(
        'form_name'            => lang('School Visit Requests'),
        'confirmation_message' => lang('Thank you, your visit request has reached us. The office calls you within two working days to agree the day.'),
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New school visit request: ^^first_name^^ ^^last_name^^'),
        'notify_page_id'       => '{{tab:email_visit_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your school visit request'),
        'confirm_page_id'      => '{{tab:email_visit_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Book a School Visit'),
            lang('The best way to know a school is to walk through it on an ordinary day. Tell us when suits you.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-4', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('visit_form'))),
                        )),
                    ), '', '8'),
                    $col($ad_visit_aside(), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// The staff's list of every visit request, newest first, with a search.
$admission_pages[] = array(
    'key'     => 'staff_visits',
    'name'    => lang('all-school-visit-requests'),
    'folder'  => 'private',
    'title'   => lang('Visit Requests'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $staff_page(array(
        $page_header(lang('Visit Requests'), lang('Every school visit request, newest first: call the family, agree the day and set the status.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array($widget('staff_visits_list'))),
        )),
    )),
);

// The privacy notice the consent boxes and the footer link to: sample text
// with a note to adapt it.
$admission_pages[] = array(
    'key'              => 'privacy',
    'name'             => lang('privacy-policy'),
    'folder'           => 'public',
    'title'            => lang('Privacy Policy'),
    'meta_description' => lang('What the school keeps about you and your child when you apply, book a visit or open an account, why, and what you can ask of us.'),
    'tree'             => $page(array(
        $page_header(lang('Privacy Policy'), lang('What the school keeps about you and your child when you apply, book a visit or open an account, why, and what you can ask of us.')),
        $el('section', 'py-5', lang('Your Data'), array(
            $container(array($row(array($col(array(
                $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Sample Text Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Sample text: adapt it to your school and to the data protection law that applies to you before you publish.'), '', lang('Text')),
                ), array('_attrs' => array($attr('role', 'note')))),
                $heading('h2', lang('Your data'), 'h4 fw-semibold mb-3'),
                $para(lang('When you apply for a place, book a visit, join the newsletter or open an account, we keep your name and contact details, your child\'s name, date of birth and what you tell us about them, and the documents you send, to answer you, arrange the visit and consider the application.'), 'mb-3'),
                $para(lang('Only the admissions office and the teachers of the programme read an application. We keep it as long as the law requires, never sell it and never give it to advertisers. You can see and change your account data from My Account, and ask us about the rest through the contact page.'), 'mb-0'),
            ), '', '8'))))),
        ), $searchable('sh-your-data', lang('privacy, personal data, KVKK, GDPR, delete my data'))),
    )),
);

// The e-mails: what the school receives and the reply to the family for
// each form, and the new message e-mail.
$admission_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_application_new',
    'name'  => lang('email-new-admission-application'),
    'title' => lang('New Admission Application E-mail'),
    'tree'  => $mail_page(array($widget('email_application_admin'))),
));
$admission_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_application_received',
    'name'  => lang('email-admission-application-received'),
    'title' => lang('Admission Application Received E-mail'),
    'tree'  => $mail_page(array($widget('email_application_parent'))),
));
$admission_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_visit_new',
    'name'  => lang('email-new-school-visit-request'),
    'title' => lang('New School Visit Request E-mail'),
    'tree'  => $mail_page(array($widget('email_visit_admin'))),
));
$admission_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_visit_received',
    'name'  => lang('email-school-visit-request-received'),
    'title' => lang('School Visit Request Received E-mail'),
    'tree'  => $mail_page(array($widget('email_visit_parent'))),
));
// A new message or reply: sent to the parent of an application, the
// sender of a conversation and the visitors watching a page. Its Comments
// widget draws the comment in the card of mail.php; the System region
// after it keeps the fixed comment for an e-mail the widget does not draw.
$admission_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: your admission application, a conversation or a news post. You can read it below and answer it on the page.')),
        $widget('email_comment'),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));

// ── Widget pieces ───────────────────────────────────────────────────────

// The parent's name.
$ad_parent_name = function () use ($span, $bind) {
    return array(
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
    );
};
// The child's name: the fields' own words in the sample.
$ad_child_name = function () use ($span, $bind) {
    return array(
        $span(lang('Child\'s first name'), '', lang('Child\'s First Name'), $bind('child_first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Child\'s last name'), '', lang('Child\'s Last Name'), $bind('child_last_name')),
    );
};

// A list item with its icon.
$ad_item = function ($icon_name, $name, $children) use ($el, $icon) {
    return $el('span', 'd-inline-flex align-items-center gap-1', $name, array_merge(array($icon($icon_name)), $children));
};

// An application in a list: the child, the status, the programme and the
// start, linked to its page. The staff's row adds the parent and the
// phone; the parent's own adds the messages.
$ad_application_row = function ($staff = false) use ($el, $span, $bind, $ad_item, $ad_child_name, $ad_parent_name, $ad_sample_programme, $ad_sample_start, $ad_sample_birth, $application_statuses) {
    $items = array(
        $ad_item('bi-backpack', lang('Programme'), array($span($ad_sample_programme, '', lang('Programme'), $bind('programme')))),
        $ad_item('bi-calendar-event', lang('Start'), array($span($ad_sample_start, '', lang('Start'), $bind('start')))),
        $ad_item('bi-balloon', lang('Date of Birth'), array($span($ad_sample_birth, '', lang('Date of Birth'), $bind('child_birth_date')))),
    );
    if ($staff) {
        $items[] = $ad_item('bi-person', lang('Parent or Guardian'), $ad_parent_name());
        $items[] = $ad_item('bi-telephone', lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone'))));
    } else {
        $items[] = $ad_item('bi-chat-left-text', lang('Messages'), array($span('0', '', lang('Reply Count'), $bind('number_of_comments'))));
    }
    return $el('a', 'list-group-item list-group-item-action p-3', lang('Admission Application'), array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $el('span', 'fw-semibold', lang('Child'), $ad_child_name()),
            $span($application_statuses[0], 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
        )),
        $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), $items),
    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view')));
};

// A visit request in the staff's list: the family, the status, the day,
// the time, the visitors, the programme and the phone. A request has no
// page: the record's pencil opens it in the control panel.
$ad_visit_row = function () use ($el, $span, $bind, $ad_item, $ad_parent_name, $ad_sample_day, $ad_sample_programme, $visit_times, $visitor_counts, $visit_statuses) {
    return $el('div', 'list-group-item p-3', lang('Visit Request'), array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $el('span', 'fw-semibold', lang('Parent or Guardian'), $ad_parent_name()),
            $span($visit_statuses[0], 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
        )),
        $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), array(
            $ad_item('bi-calendar-heart', lang('Preferred Day'), array($span($ad_sample_day, '', lang('Preferred Day'), $bind('preferred_day')))),
            $ad_item('bi-clock', lang('Time of Day'), array($span($visit_times[0], '', lang('Time of Day'), $bind('visit_time')))),
            $ad_item('bi-people', lang('Visitors'), array($span($visitor_counts[1], '', lang('Visitors'), $bind('visitors')))),
            $ad_item('bi-backpack', lang('Programme'), array($span($ad_sample_programme, '', lang('Programme'), $bind('programme')))),
            $ad_item('bi-telephone', lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
        )),
    ));
};

// A detail of the record's card: the label, then the value. $value is
// either the value's props (its sample text and binding) or, with
// $children, the nodes it is made of.
$ad_detail = function ($label, $class, $name, $value, $children = false) use ($el) {
    return array(
        $el('dt', 'col-sm-4 fw-normal text-body-secondary', lang('Label'), array(), array('text' => $label)),
        $el('dd', trim('col-sm-8 ' . $class), $name, $children ? $value : array(), $children ? array() : $value),
    );
};
$ad_bound = function ($sample, $field) use ($bind) {
    return array_merge(array('text' => $sample), $bind($field));
};

// The lines of an application, for its e-mails.
$ad_mail_application = function () use ($mail_box, $mail_line, $span, $bind, $ad_child_name, $ad_sample_programme, $ad_sample_start, $ad_sample_birth, $ad_sample_school) {
    return $mail_box(array(
        $mail_line(lang('Child'), $ad_child_name()),
        $mail_line(lang('Date of Birth'), array($span($ad_sample_birth, '', lang('Date of Birth'), $bind('child_birth_date')))),
        $mail_line(lang('Programme'), array($span($ad_sample_programme, '', lang('Programme'), $bind('programme')))),
        $mail_line(lang('Start'), array($span($ad_sample_start, '', lang('Start'), $bind('start')))),
        $mail_line(lang('Current nursery or school'), array($span($ad_sample_school, '', lang('Current School'), $bind('current_school')))),
    ), lang('Admission Application'));
};
// The lines of a visit request, for its e-mails.
$ad_mail_visit = function () use ($mail_box, $mail_line, $span, $bind, $ad_sample_day, $ad_sample_programme, $visit_times, $visitor_counts) {
    return $mail_box(array(
        $mail_line(lang('Preferred Day'), array($span($ad_sample_day, '', lang('Preferred Day'), $bind('preferred_day')))),
        $mail_line(lang('Time of Day'), array($span($visit_times[0], '', lang('Time of Day'), $bind('visit_time')))),
        $mail_line(lang('Visitors'), array($span($visitor_counts[1], '', lang('Visitors'), $bind('visitors')))),
        $mail_line(lang('Programme'), array($span($ad_sample_programme, '', lang('Programme'), $bind('programme')))),
    ), lang('Visit Request'));
};
// The family's lines of a staff e-mail; the application's adds how the
// parent is related to the child.
$ad_mail_family = function ($application) use ($mail_box, $mail_line, $span, $bind, $ad_parent_name, $ad_sample_notes, $relation_choices) {
    $lines = array($mail_line(lang('Parent or Guardian'), $ad_parent_name()));
    if ($application) {
        $lines[] = $mail_line(lang('Relation to the child'), array($span($relation_choices[0], '', lang('Relation'), $bind('relation'))));
    }
    $lines[] = $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email'))));
    $lines[] = $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone'))));
    $lines[] = $mail_line(lang('Contact by'), array($span(lang('Phone'), '', lang('Contact by'), $bind('contact_method'))));
    $lines[] = $mail_line(lang('Notes'), array($span($ad_sample_notes, '', lang('Notes'), $bind('notes'))));
    return $mail_box($lines, lang('Family'));
};
// A reference code line under a staff e-mail.
$ad_mail_reference = function () use ($el, $span, $bind, $mail_style) {
    return $el('p', 'small text-body-secondary mt-4 mb-0', lang('Reference'), array(
        $span(lang('Reference Code') . ': ', '', lang('Label')),
        $span('ABC123', '', lang('Reference Code'), $bind('reference_code')),
    ), $mail_style('font-size:13px;color:#4b5563;margin:24px 0 0'));
};
// The account the application opened for a parent who had none, in the
// reply e-mail.
$ad_mail_new_account = function () use ($el, $span, $link, $bind, $show_when, $mail_style) {
    return $el('div', 'border-top pt-3', lang('New Account'), array(
        $el('p', 'mb-2', lang('Sign-in Email'), array(
            $span(lang('We opened an account for you with this address:') . ' ', '', lang('Label')),
            $span('sample@example.com', 'fw-semibold', lang('Value'), $bind('__new_account_email')),
        ), $mail_style('margin:0 0 8px')),
        $el('p', 'mb-0', lang('Password'), array(
            $span(lang('To choose your password, use') . ' ', '', lang('Label')),
            $link(lang('Forgot Password'), '{{page:forgot_password}}', ''),
        ), $mail_style('margin:0')),
    ), array_merge($mail_style('border-top:1px solid #fed7aa;padding-top:16px'), $show_when('has_new_account')));
};
// The form detail settings of an e-mail page's widget (any reader: the
// e-mail is drawn for the submission just sent) and of the application
// page's widget (the parent's own, and the staff's).
$ad_detail_widget = function ($page_key, $form_key, $missing, $access) {
    return array(
        'page'   => $page_key,
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:' . $form_key . '}}',
            'access_control'      => $access,
            'not_found_message'   => $missing,
        ),
    );
};
// A list of applications: the parent's own (the staff see them all), or
// the staff's with a search; or the staff's list of visit requests.
$ad_list = function ($page_key, $form_key, $mode, $empty) use ($widget_root, $el, $loop, $ad_application_row, $ad_visit_row) {
    $config = array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:' . $form_key . '}}',
    );
    if ($form_key === 'apply') $config['detail_page_id'] = '{{tab:application_view}}';
    $config += array(
        'items_per_page'      => 20,
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
    );
    if ($mode === 'own') {
        $config += array(
            'viewer_filter'           => true,
            'viewer_filter_submitter' => true,
            'viewer_filter_watcher'   => true,
            'viewer_filter_editor'    => true,
        );
    } else {
        $config += array(
            'search_enabled' => true,
            'search_fields'  => $form_key === 'apply'
                ? array('child_first_name', 'child_last_name', 'first_name', 'last_name', 'email', 'programme', 'status')
                : array('first_name', 'last_name', 'email', 'phone', 'programme', 'status'),
            'search_width'   => 'half',
            'search_label'   => $form_key === 'apply'
                ? lang('Search by child, parent, programme or status...')
                : lang('Search by name, phone, programme or status...'),
        );
    }
    $config['empty_message'] = $empty;
    $row = $form_key === 'apply' ? $ad_application_row($mode === 'staff') : $ad_visit_row();
    return array(
        'page'   => $page_key,
        'slug'   => lang('form-list'),
        'config' => $config,
        'tree'   => $widget_root(array(
            $el('div', 'list-group', lang('Request List'), array($loop(array($row)))),
        )),
    );
};

$ad_missing = lang('This application could not be found, or it is not yours to see.');

$admission_widgets = array(

    // The admission application: the fields are the page's form. The
    // programme starts with what a link sends (?programme=). The Status is
    // the school's (office use only): every application starts as
    // Application received.
    'application_form' => array(
        'page'   => 'apply',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('Your child')),
            $row(array(
                $col(array($field('sh-ap-child-first', lang('Child\'s First Name'), $input('sh-ap-child-first', 'text', 'child_first_name', 'off'))), '6'),
                $col(array($field('sh-ap-child-last', lang('Child\'s Last Name'), $input('sh-ap-child-last', 'text', 'child_last_name', 'off'))), '6'),
                $col(array($field('sh-ap-birth', lang('Date of Birth'), $input('sh-ap-birth', 'date', 'child_birth_date', 'off'))), '6'),
                $col(array($field('sh-ap-school', lang('Current nursery or school'), $input('sh-ap-school', 'text', 'current_school', 'off', false))), '6'),
            ), '3'),
            $row(array(
                $col(array($field('sh-ap-programme', lang('Programme'), $select('sh-ap-programme', 'programme', lang('Choose a programme…'), $application_programme_choices, true, '', array('url_param' => 'programme')))), '6'),
                $col(array($field('sh-ap-start', lang('Start'), $select('sh-ap-start', 'start', '', $start_choices, false, $start_choices[0]))), '6'),
            ), '3'),
            $field('sh-ap-notes', lang('Notes'), $textarea('sh-ap-notes', 'notes', 4, false), 'mb-3',
                lang('Anything that helps us welcome your child: health, allergies, languages at home, a brother or sister at the school.')),
            $field('sh-ap-documents', lang('Documents'), $file('sh-ap-documents', 'documents', 'private'), 'mb-3',
                lang('A birth certificate or a report from the last school (PDF, Word or a picture). More can follow under your application.')),
            $form_step(2, lang('About you')),
            $row(array(
                $col(array($field('sh-ap-first-name', lang('First Name'), $input('sh-ap-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
                $col(array($field('sh-ap-last-name', lang('Last Name'), $input('sh-ap-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
                $col(array($field('sh-ap-relation', lang('Relation to the child'), $select('sh-ap-relation', 'relation', '', $relation_choices, false, $relation_choices[0]))), '6'),
                $col(array($field('sh-ap-phone', lang('Phone'), $input('sh-ap-phone', 'tel', 'phone', 'tel', true, array('contact_field' => 'mobile_phone')))), '6'),
            ), '3'),
            $field('sh-ap-email', lang('Email'), $input('sh-ap-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address'))),
            $radios('sh-ap-contact', 'contact_method', lang('Contact by'), $contact_choices, $contact_choices[1][0]),
            $field('sh-ap-status', lang('Status'), $select('sh-ap-status', 'status', '', $application_statuses, false, $application_statuses[0], array('office_use_only' => true))),
            $agree('sh-ap-consent', 'consent', lang('I agree that the school keeps these details about me and my child to consider the application, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Application'), 'btn btn-primary btn-lg rounded-pill px-4'),
                $para(lang('The admissions office calls you within a week.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One application: the child, the programme, its status and what the
    // parent wrote; the school's answers (the visit day and the offer)
    // follow as comments.
    'application_detail' => $ad_detail_widget('application_view', 'apply', $ad_missing, 'submitter_only') + array(
        'tree' => array('type' => 'root', 'props' => array(), 'children' => array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'text-center py-5', lang('Not Found'), array(
                            $icon('bi-clipboard-x', 'display-5 text-body-tertiary'),
                            $heading('h2', lang('Application not found'), 'h3 fw-semibold mt-3'),
                            $para($ad_missing, 'text-body-secondary mb-4', $bind('not_found')),
                            $link(lang('My Applications'), '{{page:my_applications}}', 'btn btn-primary rounded-pill px-4'),
                        ), $show_when('record_not_found')),
                        $el('div', '', lang('Record'), array(
                            $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
                                $icon('bi-check-circle', 'mt-1'),
                                $span(lang('Thank you, your application has reached us. The admissions office calls you within a week to book a visit; this page shows the status, and you can write to us below.'), '', lang('Text')),
                            ), array_merge(array('_attrs' => array($attr('role', 'status'))), $show_when('submitted_this_session'))),
                            $el('div', 'alert alert-info', lang('New Account'), array(
                                $el('p', 'fw-semibold mb-2', lang('Title'), array(), array('text' => lang('We opened an account for you'))),
                                $el('p', 'mb-1', lang('Sign-in Email'), array(
                                    $span(lang('Email') . ': ', '', lang('Label')),
                                    $span('sample@example.com', 'fw-semibold', lang('Value'), $bind('__new_account_email')),
                                )),
                                // The single-use set password link, for the person who sent the form
                                // while it is live; the sentence below covers a used or expired link.
                                $link(lang('Set your password'), '#', 'btn btn-sm btn-primary', array(),
                                    array('_bindings' => array('href' => '__new_account_set_password_url', 'eo_visible_if' => 'has_set_password_link'))),
                                $para(lang('You can set a password with "Forgot password" on the sign-in page.'), 'small mt-2 mb-0'),
                            ), $show_when('has_new_account')),
                            $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                                $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                                    $icon('bi-arrow-left'),
                                    $span(lang('My Applications'), '', lang('Link Text')),
                                ), array('href' => '{{page:my_applications}}')),
                                $edit_button(lang('Change Status')),
                            )),
                            $el('div', 'card border-0 shadow-sm rounded-4 mb-4', lang('Application Card'), array(
                                $el('div', 'card-body p-4', lang('Card Body'), array(
                                    $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
                                        $el('div', '', lang('Words'), array(
                                            $para(lang('Admission Application'), 'psh-eyebrow small text-uppercase fw-semibold text-primary mb-1'),
                                            $heading('h1', $ad_sample_programme, 'psh-display h3 mb-0', $bind('programme')),
                                        )),
                                        $span($application_statuses[0], 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
                                    )),
                                    $el('dl', 'row small mb-0', lang('Details'), array_merge(
                                        $ad_detail(lang('Child'), '', lang('Child'), $ad_child_name(), true),
                                        $ad_detail(lang('Date of Birth'), '', lang('Date of Birth'), $ad_bound($ad_sample_birth, 'child_birth_date')),
                                        $ad_detail(lang('Start'), '', lang('Start'), $ad_bound($ad_sample_start, 'start')),
                                        $ad_detail(lang('Current nursery or school'), '', lang('Current School'), $ad_bound($ad_sample_school, 'current_school')),
                                        $ad_detail(lang('Parent or Guardian'), '', lang('Parent or Guardian'), $ad_parent_name(), true),
                                        $ad_detail(lang('Relation to the child'), '', lang('Relation'), $ad_bound($relation_choices[0], 'relation')),
                                        $ad_detail(lang('Documents'), 'text-break', lang('Documents'), array(
                                            $link('birth-certificate.pdf', '#', '', array(), array('target' => '_blank', 'rel' => 'noopener',
                                                '_bindings' => array('href' => 'documents', 'text' => 'documents'))),
                                        ), true),
                                        $ad_detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $ad_bound('ABC123', 'reference_code'))
                                    )),
                                )),
                            )),
                            $el('div', 'pg-hide-if-empty p-4 rounded-4 bg-body-tertiary mb-4', lang('Notes'), array(
                                $para(lang('Notes'), 'small fw-semibold text-body-secondary mb-2'),
                                $el('div', 'pg-field-value lh-lg', lang('Value'), array(), $ad_bound($ad_sample_notes, 'notes')),
                            )),
                            $el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
                                $icon('bi-chat-left-text', 'text-primary mt-1'),
                                $span(lang('More documents or a question? Write to us below; we answer here and by e-mail.'), '', lang('Text')),
                            )),
                        ), $show_when('record_found')),
                    ), '', '8'),
                ), '', 'center'),
            ), 'py-5'),
        )),
    ),

    // The messages under an application: the page's own Comments widget,
    // shown only to whoever may read the application above it. Oldest
    // first, all on one page, with a file each. No Reply, Share or follow
    // box: the parent is e-mailed every answer and the school every message.
    'application_messages' => array(
        'page'   => 'application_view',
        'slug'   => lang('comments'),
        'config' => array(
            'regionType' => 'comments',
            'order'      => 'oldest',
            'featured'   => 'none',
            'layout'     => 'flat',
            'max_depth'  => 1,
            'per_page'   => 0,
        ),
        'tree'   => $comment_thread(array(
            'title'     => lang('Messages'),
            'empty'     => lang('No messages yet. The day of your visit and our answer come here; you can write to us about your child too.'),
            'heading'   => lang('Write to us'),
            'label'     => lang('Your message'),
            'sign_in'   => lang('Sign in to write to us.'),
            'submit'    => lang('Send message'),
            'watch_box' => '',
            'follow'    => '',
            'unfollow'  => '',
            'close'     => lang('Stop new messages'),
            'reopen'    => lang('Allow new messages'),
            'reply'     => false,
            'watch'     => false,
        )),
    ),

    'my_applications_list' => $ad_list('my_applications', 'apply', 'own',
        lang('No applications yet. Tell us about your child, and the admissions office calls you within a week.')),

    'staff_applications_list' => $ad_list('staff_applications', 'apply', 'staff', lang('No admission applications yet.')),

    // The school visit request: the day and time as words, how many come,
    // the programme the family is thinking of and who to call. The Status is
    // the school's (office use only).
    'visit_form' => array(
        'page'   => 'visit',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('Your visit')),
            $row(array(
                $col(array($field('sh-vs-day', lang('Preferred Day'), $input('sh-vs-day', 'text', 'preferred_day', 'off'), 'mb-3',
                    lang('A day or two that suit you, in your own words.'))), '6'),
                $col(array($field('sh-vs-time', lang('Time of Day'), $select('sh-vs-time', 'visit_time', '', $visit_times, false, $visit_times[0]))), '6'),
                $col(array($field('sh-vs-visitors', lang('Visitors'), $select('sh-vs-visitors', 'visitors', '', $visitor_counts, false, $visitor_counts[1]))), '6'),
                $col(array($field('sh-vs-programme', lang('Programme'), $select('sh-vs-programme', 'programme', '', $application_programme_choices, false,
                    $application_programme_choices[count($application_programme_choices) - 1], array('url_param' => 'programme')))), '6'),
            ), '3'),
            $form_step(2, lang('How to reach you')),
            $row(array(
                $col(array($field('sh-vs-first-name', lang('First Name'), $input('sh-vs-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
                $col(array($field('sh-vs-last-name', lang('Last Name'), $input('sh-vs-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
                $col(array($field('sh-vs-email', lang('Email'), $input('sh-vs-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
                $col(array($field('sh-vs-phone', lang('Phone'), $input('sh-vs-phone', 'tel', 'phone', 'tel', true, array('contact_field' => 'mobile_phone')))), '6'),
            ), '3'),
            $radios('sh-vs-contact', 'contact_method', lang('Contact by'), $contact_choices, $contact_choices[1][0]),
            $field('sh-vs-notes', lang('Notes'), $textarea('sh-vs-notes', 'notes', 3, false), 'mb-3',
                lang('Your child\'s age, or what you would most like to see.')),
            $field('sh-vs-status', lang('Status'), $select('sh-vs-status', 'status', '', $visit_statuses, false, $visit_statuses[0], array('office_use_only' => true))),
            $agree('sh-vs-consent', 'consent', lang('I agree that the school keeps these details to arrange the visit, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Ask for a Visit'), 'btn btn-primary btn-lg rounded-pill px-4'),
                $para(lang('The office calls you within two working days.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    'staff_visits_list' => $ad_list('staff_visits', 'visit', 'staff', lang('No school visit requests yet.')),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_application_admin' => $ad_detail_widget('email_application_new', 'apply', lang('This application was not found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new admission application')),
            $mail_para(lang('It came from the application form. Call the family to book a visit, write the day under the application on its page and set the status; what you write there reaches the family by e-mail.')),
            $ad_mail_application(),
            $ad_mail_family(true),
            $mail_button(lang('Open the application'), '{{page:application_view}}?r=^^reference_code^^'),
            $ad_mail_reference(),
        )),
    ),
    'email_application_parent' => $ad_detail_widget('email_application_received', 'apply', lang('This application was not found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your admission application')),
            $mail_greeting(),
            $mail_para(lang('Thank you. The admissions office calls you within a week to book a visit for you and your child; the status and our answers are on the application\'s page and come by e-mail.')),
            $ad_mail_application(),
            $mail_para(lang('More documents or a question? Write to us on the application\'s page; everything about your application stays there.')),
            $mail_button(lang('Open my application'), '{{page:application_view}}?r=^^reference_code^^'),
            $ad_mail_new_account(),
        )),
    ),
    'email_visit_admin' => $ad_detail_widget('email_visit_new', 'visit', lang('This visit request was not found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new school visit request')),
            $mail_para(lang('It came from the visit form. Call the family to agree the day, then set the status in the list of visit requests.')),
            $ad_mail_visit(),
            $ad_mail_family(false),
            $mail_button(lang('Open the visit requests'), '{{page:staff_visits}}'),
            $ad_mail_reference(),
        )),
    ),
    'email_visit_parent' => $ad_detail_widget('email_visit_received', 'visit', lang('This visit request was not found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your school visit request')),
            $mail_greeting(),
            $mail_para(lang('Thank you. The school office calls you within two working days to agree the day of your visit.')),
            $ad_mail_visit(),
            $mail_para(lang('Ready to apply? You can send an application before or after the visit.')),
            $mail_button(lang('Apply Now'), '{{page:apply}}'),
        )),
    ),

    // The comment of the reply e-mail, drawn for the comment the e-mail is
    // about, flat and alone.
    'email_comment' => array(
        'page'   => 'email_reply',
        'slug'   => lang('comments'),
        'config' => array(
            'regionType' => 'comments',
            'order'      => 'oldest',
            'featured'   => 'none',
            'layout'     => 'flat',
            'max_depth'  => 1,
            'per_page'   => 0,
        ),
        'tree'   => $mail_comment_tree,
    ),
);

return array('pages' => $admission_pages, 'widgets' => $admission_widgets);
