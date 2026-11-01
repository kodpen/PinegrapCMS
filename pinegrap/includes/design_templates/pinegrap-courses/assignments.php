<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Online Courses", its enrolments and assignments: the
 * enrolment request and an enrolment's own page with its messages, the
 * assignment form, an assignment's own page with the instructor's feedback,
 * My Assignments, the staff's lists of both, the e-mails the two forms and
 * the comment threads send, and the widgets that draw them. Included after
 * parts.php and mail.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * An enrolment request is a record of the Enrolments form: the way in
 * without the shop (a team, an invoice). A visitor without an account gets
 * one (auto-registration), so the request's page shows it at once; the
 * staff answer under it and give the course's folder in the control panel.
 * An assignment is a record of the Assignments form, sent by a signed-in
 * student from the course's classroom; only its sender and the staff open
 * it (submitter_only). Both carry an office-use-only Status the staff
 * change (Requested, Enrolled, Waitlisted, Declined; Submitted, Needs
 * revision, Passed); the messages and the feedback under them are the
 * page's comments, mailed to the student and to the school.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$study_pages = array();

// The answers of the forms' pick lists. Each answer's value is its own
// words, so the e-mails and the lists read as the student chose. The
// course pages fill the course in by its title (?course=).
$course_titles = array();
foreach ($courses as $c) $course_titles[] = $c['title'];
$enrol_choices = array_merge($course_titles, array($membership['title'], lang('Training for a team')));
$payments = array(lang('Invoice to my company'), lang('Bank transfer'), lang('Not sure yet'));
$enrolment_statuses = array(lang('Requested'), lang('Enrolled'), lang('Waitlisted'), lang('Declined'));
$assignment_statuses = array(lang('Submitted'), lang('Needs revision'), lang('Passed'));
$first_course = reset($courses);

// A detail of a record's card: the label, then the value. $value is either
// the value's props (its sample text and binding) or, with $children, the
// nodes it is made of.
$detail = function ($label, $class, $name, $value, $children = false) use ($el) {
    return array(
        $el('dt', 'col-sm-4 fw-normal text-body-secondary', lang('Label'), array(), array('text' => $label)),
        $el('dd', trim('col-sm-8 ' . $class), $name, $children ? $value : array(), $children ? array() : $value),
    );
};
// The student's name, from the record's two fields.
$student_name = function () use ($span, $bind) {
    return array(
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
    );
};
// The box beside a form: what happens after it is sent.
$next_steps = function ($title, $steps) use ($el, $heading) {
    $items = array();
    foreach ($steps as $n => $text) {
        $items[] = $el('li', $n < count($steps) - 1 ? 'mb-2' : '', lang('Step'), array(), array('text' => $text));
    }
    return $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('How It Works'), array(
        $heading('h2', $title, 'h5 fw-bold mb-3'),
        $el('ol', 'small text-body-secondary ps-3 mb-0', lang('List'), $items),
    ));
};
// A form in its card, with the boxes beside it.
$form_layout = function ($widget_key, $aside) use ($el, $container, $row, $col, $widget) {
    return $el('section', 'py-5', lang('Content'), array(
        $container(array(
            $row(array(
                $col(array(
                    $el('div', 'card border-0 shadow-sm rounded-4', lang('Form Card'), array(
                        $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget($widget_key))),
                    )),
                ), '', '8'),
                $col($aside, '', '4'),
            ), '4', '', 'g-lg-5'),
        )),
    ));
};
// A record page's comment settings: the student and the school write under
// it; each new one is mailed to the other side.
$record_comments = function ($subject) {
    return array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                 'email_page' => '{{tab:email_reply}}', 'email_subject' => $subject, 'notify_email' => '{{site_email}}');
};
// A staff list page: its title, its words and the list.
$staff_list_page = function ($key, $name, $title, $lead, $widget_key) use ($staff_page, $page_header, $el, $container, $widget) {
    return array(
        'key'     => $key,
        'name'    => $name,
        'folder'  => 'private',
        'title'   => $title,
        'search'  => false,
        'sitemap' => false,
        'noindex' => true,
        'tree'    => $staff_page(array(
            $page_header($title, $lead),
            $el('section', 'py-5', lang('Content'), array($container(array($widget($widget_key))))),
        )),
    );
};

// ── Pages ───────────────────────────────────────────────────────────────

// The enrolment request: the course (filled in from its page), who is
// coming and how the school is paid. What is sent opens the request's own
// page.
$study_pages[] = array(
    'key'              => 'enrolment',
    'name'             => lang('request-enrolment'),
    'folder'           => 'public',
    'title'            => lang('Request Enrolment'),
    'meta_description' => lang('Enrol in a course without paying online, or enrol a team with one invoice; we answer within a working day.'),
    'form'             => array(
        'form_name'            => lang('Enrolments'),
        'confirmation_message' => lang('Thank you, your enrolment request has reached us. Our answer comes by e-mail.'),
        'confirmation_page_id' => '{{tab:enrolment_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New enrolment request: ^^course^^'),
        'notify_page_id'       => '{{tab:email_enrolment_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your enrolment request'),
        'confirm_page_id'      => '{{tab:email_enrolment_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Request Enrolment'), lang('For an invoice, a team or a course you would rather not pay for online. We answer within a working day and open the course for you.')),
        $form_layout('enrolment_form', array(
            $next_steps(lang('What happens next'), array(
                lang('Your request opens its own page, and an account for you if you have none.'),
                lang('We send the invoice or the bank details by e-mail.'),
                lang('Once it is paid, the course opens in My Courses.'),
            )),
            $el('div', 'p-4 rounded-4 border', lang('Pay Online'), array(
                $heading('h2', lang('Rather pay by card?'), 'h5 fw-bold mb-3'),
                $para(lang('Buy the course on its page and it opens at once, with no request needed.'), 'small text-body-secondary mb-3'),
                $link(lang('Browse courses'), '{{page:courses}}', 'btn btn-sm btn-outline-primary px-3'),
            )),
        )),
    ), false),
);

// One enrolment request: the details, its status and the messages under it.
$study_pages[] = array(
    'key'      => 'enrolment_view',
    'name'     => lang('enrolment'),
    'folder'   => 'registration',
    'title'    => lang('My Enrolment'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => $record_comments(lang('There is a new message about your enrolment')),
    'tree'     => $page(array(
        $widget('enrolment_detail'),
        $comments_section(lang('Messages'), 'enrolment_messages'),
    ), false),
);

// The assignment form, for signed-in students: the course (filled in from
// its classroom), the work and a note to the instructor.
$study_pages[] = array(
    'key'              => 'assignment',
    'name'             => lang('send-an-assignment'),
    'folder'           => 'registration',
    'title'            => lang('Send an Assignment'),
    'search'           => false,
    'sitemap'          => false,
    'form'             => array(
        'form_name'            => lang('Assignments'),
        'confirmation_message' => lang('Thank you, your project has reached the instructor.'),
        'confirmation_page_id' => '{{tab:assignment_view}}',
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New assignment: ^^course^^'),
        'notify_page_id'       => '{{tab:email_assignment_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your assignment'),
        'confirm_page_id'      => '{{tab:email_assignment_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Send an Assignment'), lang('Hand in the course project. The instructor reads it and writes feedback under it, usually within three working days.')),
        $form_layout('assignment_form', array(
            $next_steps(lang('How feedback works'), array(
                lang('Your project opens its own page; only you and the instructors see it.'),
                lang('The instructor writes feedback under it and sets its status.'),
                lang('Asked for changes? Send a new version and link it in your answer.'),
            )),
            $el('div', 'p-4 rounded-4 border', lang('Earlier Projects'), array(
                $heading('h2', lang('Sent one before?'), 'h5 fw-bold mb-3'),
                $para(lang('Every project you sent, its status and its feedback are in My Assignments.'), 'small text-body-secondary mb-3'),
                $link(lang('My Assignments'), '{{page:my_assignments}}', 'btn btn-sm btn-outline-primary px-3'),
            )),
        )),
    ), false),
);

// One assignment: the work, its status and the instructor's feedback.
$study_pages[] = array(
    'key'      => 'assignment_view',
    'name'     => lang('assignment'),
    'folder'   => 'registration',
    'title'    => lang('My Assignment'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => $record_comments(lang('There is new feedback on your assignment')),
    'tree'     => $page(array(
        $widget('assignment_detail'),
        $comments_section(lang('Feedback'), 'assignment_feedback'),
    ), false),
);

// My Assignments: the projects the student sent, newest first.
$study_pages[] = array(
    'key'     => 'my_assignments',
    'name'    => lang('my-assignments'),
    'folder'  => 'registration',
    'title'   => lang('My Assignments'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('My Assignments'), lang('The projects you sent, their status and the instructor\'s feedback, newest first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('my_assignments_list')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('Your Courses'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-journal-check', 'text-primary'),
                                $span(lang('Ready for the next one?'), '', lang('Text')),
                            )),
                            $para(lang('Each course\'s project is at the end of its classroom.'), 'small text-body-secondary mb-3'),
                            $link(lang('My Courses'), '{{page:my_courses}}', 'btn btn-sm btn-primary px-3'),
                        )),
                        $el('div', 'p-4 rounded-4 border', lang('Statuses'), array(
                            $heading('h2', lang('What the status means'), 'h6 fw-bold mb-2'),
                            $para(lang('Submitted: waiting for the instructor. Needs revision: read the feedback and send a new version. Passed: well done.'), 'small text-body-secondary mb-0'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// The staff's lists, in the staff bar.
$study_pages[] = $staff_list_page('staff_enrolments', lang('all-enrolments'), lang('All Enrolments'),
    lang('Every enrolment request, newest first. Open one to answer it; give the course\'s folder to the student in the control panel.'), 'staff_enrolments_list');
$study_pages[] = $staff_list_page('staff_assignments', lang('all-assignments'), lang('All Assignments'),
    lang('Every project sent, newest first. Open one to write feedback and press Change Status.'), 'staff_assignments_list');

// The forms' e-mails: the school's notice and the student's receipt.
$study_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_enrolment_new',
    'name'  => lang('email-new-enrolment'),
    'title' => lang('New Enrolment E-mail'),
    'tree'  => $mail_page(array($widget('email_enrolment_admin'))),
));
$study_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_enrolment_received',
    'name'  => lang('email-enrolment-received'),
    'title' => lang('Enrolment Received E-mail'),
    'tree'  => $mail_page(array($widget('email_enrolment_client'))),
));
$study_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_assignment_new',
    'name'  => lang('email-new-assignment'),
    'title' => lang('New Assignment E-mail'),
    'tree'  => $mail_page(array($widget('email_assignment_admin'))),
));
$study_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_assignment_received',
    'name'  => lang('email-assignment-received'),
    'title' => lang('Assignment Received E-mail'),
    'tree'  => $mail_page(array($widget('email_assignment_client'))),
));
// A new message, feedback or answer: sent to the student of an enrolment
// or an assignment, the sender of a conversation and whoever follows a
// lesson's questions. Its Comments widget draws the comment with its "View
// or Reply" button; the System region after it keeps the fixed comment for
// an e-mail the widget does not draw (a card edited so it lost its link to
// the comment).
$study_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: a lesson\'s questions, your enrolment, your project or a conversation. You can read it below and answer it on the page.')),
        $widget('email_comment'),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));

// ── Widgets ─────────────────────────────────────────────────────────────

// A record in a list, linked to its page: the title line (for the staff,
// the student first), the status, and the details under it.
$record_row = function ($title, $status, $details) use ($el, $span, $bind) {
    return $el('a', 'list-group-item list-group-item-action p-3', lang('Record'), array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $title,
            $span($status, 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
        )),
        $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), $details),
    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view')));
};
$row_detail = function ($icon_name, $name, $sample, $token) use ($el, $icon, $span, $bind) {
    return $el('span', 'd-inline-flex align-items-center gap-1', $name, array($icon($icon_name), $span($sample, '', $name, $bind($token))));
};
$staff_title = function () use ($el, $span, $bind, $student_name, $first_course) {
    return $el('span', 'fw-semibold', lang('Student'), array_merge($student_name(), array(
        $span(' · ', 'text-body-secondary', lang('Text')),
        $span($first_course['title'], 'text-body-secondary fw-normal', lang('Course'), $bind('course')),
    )));
};

// The lines of a request or a project, for its two e-mails.
$mail_enrolment = function () use ($mail_box, $mail_line, $span, $bind, $student_name, $first_course) {
    return $mail_box(array(
        $mail_line(lang('Course'), array($span($first_course['title'], '', lang('Course'), $bind('course')))),
        $mail_line(lang('Student'), $student_name()),
        $mail_line(lang('Company'), array($span(lang('Northwind Labs'), '', lang('Company'), $bind('company')))),
        $mail_line(lang('Number of people'), array($span('1', '', lang('Number of people'), $bind('seats')))),
        $mail_line(lang('Payment'), array($span(lang('Invoice to my company'), '', lang('Payment'), $bind('payment')))),
    ), lang('Enrolment'));
};
$mail_assignment = function () use ($mail_box, $mail_line, $span, $bind, $student_name, $first_course) {
    return $mail_box(array(
        $mail_line(lang('Course'), array($span($first_course['title'], '', lang('Course'), $bind('course')))),
        $mail_line(lang('Student'), $student_name()),
        $mail_line(lang('Link to the project'), array($span('https://example.com/my-project', '', lang('Link'), $bind('project_link')))),
        $mail_line(lang('File'), array($span('project.pdf', '', lang('File'), $bind('project_file')))),
    ), lang('Assignment'));
};

// A form's e-mail widget: drawn for the submission just sent.
$mail_widget = function ($page_key, $form_key, $children) use ($widget_root) {
    return array(
        'page'   => $page_key,
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:' . $form_key . '}}',
            'access_control'      => 'public',
            'not_found_message'   => lang('The submission could not be found.'),
        ),
        'tree'   => $widget_root($children),
    );
};
// The messages or feedback of a record page: the page's own Comments
// widget, which shows them only to whoever may read the record above it.
// Oldest first, one after another, all on one page, never pinned. No
// Reply, Share or follow box: the record has two sides, its student is
// e-mailed every answer and the school every message.
$record_thread = function ($page_key, $words) use ($comment_thread) {
    return array(
        'page'   => $page_key,
        'slug'   => lang('comments'),
        'config' => array(
            'regionType' => 'comments',
            'order'      => 'oldest',
            'featured'   => 'none',
            'layout'     => 'flat',
            'max_depth'  => 1,
            'per_page'   => 0,
        ),
        'tree'   => $comment_thread($words + array(
            'watch_box' => '',
            'follow'    => '',
            'unfollow'  => '',
            'close'     => lang('Stop new messages'),
            'reopen'    => lang('Allow new messages'),
            'reply'     => false,
            'watch'     => false,
        )),
    );
};
// A list of a form's records: the student's own (or, with $staff, all of
// them with a search box), newest first.
$record_list = function ($page_key, $form_key, $view_key, $row, $empty, $search = array()) use ($widget_root, $el, $loop) {
    $config = array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:' . $form_key . '}}',
        'detail_page_id'      => '{{tab:' . $view_key . '}}',
        'items_per_page'      => 20,
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
        'empty_message'       => $empty,
    );
    if ($search) {
        $config += array(
            'search_enabled' => true,
            'search_fields'  => $search['fields'],
            'search_width'   => 'half',
            'search_label'   => $search['label'],
        );
    } else {
        $config += array(
            'viewer_filter'           => true,
            'viewer_filter_submitter' => true,
            'viewer_filter_watcher'   => true,
            'viewer_filter_editor'    => true,
        );
    }
    return array(
        'page'   => $page_key,
        'slug'   => lang('form-list'),
        'config' => $config,
        'tree'   => $widget_root(array(
            $el('div', 'list-group', lang('Record List'), array($loop(array($row)))),
        )),
    );
};
// A record's page: not found, the sent notice, the new account, the
// toolbar, then the record's card ($card) and what follows it.
$record_detail = function ($page_key, $form_key, $icon_name, $not_found_title, $not_found, $back_key, $back_text, $sent, $after) use ($widget_root, $container, $row, $col, $el, $icon, $heading, $para, $link, $span, $attr, $bind, $show_when, $edit_button) {
    return array(
        'page'   => $page_key,
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:' . $form_key . '}}',
            'access_control'      => 'submitter_only',
            'not_found_message'   => $not_found,
        ),
        'tree'   => $widget_root(array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'text-center py-5', lang('Not Found'), array(
                            $icon($icon_name, 'display-5 text-body-tertiary'),
                            $heading('h2', $not_found_title, 'h3 fw-bold mt-3'),
                            $para($not_found, 'text-body-secondary mb-4', $bind('not_found')),
                            $link($back_text, '{{page:' . $back_key . '}}', 'btn btn-primary px-4'),
                        ), $show_when('record_not_found')),
                        $el('div', '', lang('Record'), array_merge(array(
                            $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
                                $icon('bi-check-circle', 'mt-1'),
                                $span($sent, '', lang('Text')),
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
                                    $span($back_text, '', lang('Link Text')),
                                ), array('href' => '{{page:' . $back_key . '}}')),
                                $edit_button(lang('Change Status')),
                            )),
                        ), $after), $show_when('record_found')),
                    ), '', '8'),
                ), '', 'center'),
            ), 'py-5'),
        )),
    );
};
// A record's card: the eyebrow, the course as its title, the status, and
// the details.
$record_card = function ($eyebrow, $details) use ($el, $para, $heading, $span, $bind, $first_course) {
    return $el('div', 'card border-0 shadow-sm rounded-4 mb-4', lang('Record Card'), array(
        $el('div', 'card-body p-4', lang('Card Body'), array(
            $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
                $el('div', '', lang('Words'), array(
                    $para($eyebrow, 'small text-uppercase fw-semibold text-primary mb-1'),
                    $heading('h1', $first_course['title'], 'h3 fw-bold mb-0', $bind('course')),
                )),
                $span(lang('Submitted'), 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
            )),
            $el('dl', 'row small mb-0', lang('Details'), $details),
        )),
    ));
};
// A long field of a record, in its own box; the box leaves when it is
// empty.
$record_notes = function ($label, $token, $sample) use ($el, $para, $bind) {
    return $el('div', 'pg-hide-if-empty p-4 rounded-4 bg-body-tertiary mb-4', lang('Notes'), array(
        $para($label, 'small fw-semibold text-body-secondary mb-2'),
        $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $sample), $bind($token))),
    ));
};
$record_hint = function ($icon_name, $text) use ($el, $icon, $span) {
    return $el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
        $icon($icon_name, 'text-primary mt-1'),
        $span($text, '', lang('Text')),
    ));
};
// The new account the enrolment form opened for a visitor who had none:
// the address it signs in with, and how to choose a password (the set
// password link is shown on the request's page, not mailed).
$mail_new_account = function () use ($el, $span, $link, $bind, $show_when, $mail_style) {
    return $el('div', 'border-top pt-3', lang('New Account'), array(
        $el('p', 'mb-2', lang('Sign-in Email'), array(
            $span(lang('We opened an account for you with this address:') . ' ', '', lang('Label')),
            $span('sample@example.com', 'fw-semibold', lang('Value'), $bind('__new_account_email')),
        ), $mail_style('margin:0 0 8px')),
        $el('p', 'mb-0', lang('Password'), array(
            $span(lang('To choose your password, use') . ' ', '', lang('Label')),
            $link(lang('Forgot Password'), '{{page:forgot_password}}', ''),
        ), $mail_style('margin:0')),
    ), array_merge($mail_style('border-top:1px solid #ccfbf1;padding-top:16px'), $show_when('has_new_account')));
};
$mail_reference = function () use ($el, $span, $bind, $mail_style) {
    return $el('p', 'small text-body-secondary mt-4 mb-0', lang('Reference'), array(
        $span(lang('Reference Code') . ': ', '', lang('Label')),
        $span('ABC123', '', lang('Reference Code'), $bind('reference_code')),
    ), $mail_style('font-size:13px;color:#64748b;margin:24px 0 0'));
};

$enrolment_row = function ($staff) use ($record_row, $row_detail, $staff_title, $span, $bind, $first_course) {
    $details = array($row_detail('bi-people', lang('Number of people'), '1', 'seats'));
    if ($staff) {
        $details[] = $row_detail('bi-building', lang('Company'), lang('Northwind Labs'), 'company');
        $details[] = $row_detail('bi-receipt', lang('Payment'), lang('Invoice to my company'), 'payment');
        $title = $staff_title();
    } else {
        $details[] = $row_detail('bi-chat-left-text', lang('Messages'), '0', 'number_of_comments');
        $title = $span($first_course['title'], 'fw-semibold', lang('Course'), $bind('course'));
    }
    return $record_row($title, lang('Requested'), $details);
};
$assignment_row = function ($staff) use ($record_row, $row_detail, $staff_title, $span, $bind, $first_course) {
    $details = array(
        $row_detail('bi-calendar3', lang('Sent'), lang('Date'), 'submitted_date_and_time'),
        $row_detail('bi-chat-left-text', lang('Feedback'), '0', 'number_of_comments'),
    );
    $title = $staff ? $staff_title() : $span($first_course['title'], 'fw-semibold', lang('Course'), $bind('course'));
    return $record_row($title, lang('Submitted'), $details);
};

$study_widgets = array(

    // The enrolment request: the fields are the page's form. The Status is
    // the staff's (office use only): a visitor never sees it, and every
    // request starts as Requested.
    'enrolment_form' => array(
        'page'   => 'enrolment',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('Which course?')),
            $row(array(
                // Filled in from the course's Request enrolment button (?course=).
                $col(array($field('pco-enrol-course', lang('Course'), $select('pco-enrol-course', 'course', lang('Choose a course…'), $enrol_choices, true, '', array('url_param' => 'course')),
                    'mb-3', lang('Filled in when you come from a course\'s page.'))), '8'),
                $col(array($field('pco-enrol-seats', lang('Number of people'), $input('pco-enrol-seats', 'number', 'seats', 'off', true, array(),
                    array($attr('min', '1'), $attr('value', '1'))))), '4'),
            ), '3'),
            $form_step(2, lang('Who is enrolling?')),
            $row(array(
                $col(array($field('pco-enrol-first-name', lang('First Name'), $input('pco-enrol-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
                $col(array($field('pco-enrol-last-name', lang('Last Name'), $input('pco-enrol-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
                $col(array($field('pco-enrol-email', lang('Email'), $input('pco-enrol-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
                $col(array($field('pco-enrol-phone', lang('Phone'), $input('pco-enrol-phone', 'tel', 'phone', 'tel', false, array('contact_field' => 'mobile_phone')))), '6'),
                $col(array($field('pco-enrol-company', lang('Company'), $input('pco-enrol-company', 'text', 'company', 'organization', false, array('contact_field' => 'company')))), '12'),
            ), '3'),
            $form_step(3, lang('How would you like to pay?')),
            $field('pco-enrol-payment', lang('Payment'), $select('pco-enrol-payment', 'payment', '', $payments, false, lang('Invoice to my company'))),
            $field('pco-enrol-notes', lang('Notes'), $textarea('pco-enrol-notes', 'notes', 3, false), 'mb-3',
                lang('The names of a team, an invoice address or anything else.')),
            $field('pco-enrol-status', lang('Status'), $select('pco-enrol-status', 'status', '', $enrolment_statuses, false, lang('Requested'), array('office_use_only' => true))),
            $agree('pco-enrol-consent', 'consent', lang('I agree that the school keeps these details to enrol me, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send request'), 'btn btn-primary btn-lg px-4'),
                $para(lang('Our answer comes by e-mail.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    'enrolment_detail' => $record_detail('enrolment_view', 'enrolment', 'bi-person-badge', lang('Enrolment not found'),
        lang('This enrolment request could not be found, or it is not yours to see.'), 'my_courses', lang('My Courses'),
        lang('Thank you, your enrolment request has reached us. We answer by e-mail; this page shows the status, and you can write to us below.'),
        array(
            $record_card(lang('Enrolment request'), array_merge(
                $detail(lang('Student'), '', lang('Student'), $student_name(), true),
                $detail(lang('Number of people'), '', lang('Number of people'), array_merge(array('text' => '1'), $bind('seats'))),
                $detail(lang('Company'), '', lang('Company'), array_merge(array('text' => lang('Northwind Labs')), $bind('company'))),
                $detail(lang('Payment'), '', lang('Payment'), array_merge(array('text' => lang('Invoice to my company')), $bind('payment'))),
                $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), array_merge(array('text' => 'ABC123'), $bind('reference_code')))
            )),
            $record_notes(lang('Notes'), 'notes', lang('Please send the invoice to our finance team at finance@example.com.')),
            $record_hint('bi-chat-left-text', lang('A question about the course or the invoice? Write to us below; we answer here and by e-mail.')),
        )),

    'enrolment_messages' => $record_thread('enrolment_view', array(
        'title'   => lang('Messages'),
        'empty'   => lang('No messages yet. Write to us here about your enrolment.'),
        'heading' => lang('Write to us'),
        'label'   => lang('Your message'),
        'sign_in' => lang('Sign in to write to us.'),
        'submit'  => lang('Send message'),
    )),

    // The assignment: the course, the work (a link, a file or both) and a
    // note. Name and e-mail come from the student's account. The Status is
    // the instructor's (office use only); every project starts as
    // Submitted.
    'assignment_form' => array(
        'page'   => 'assignment',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            // Filled in from the classroom's Send your project button (?course=).
            $field('pco-task-course', lang('Course'), $select('pco-task-course', 'course', lang('Choose a course…'), $course_titles, true, '', array('url_param' => 'course')),
                'mb-3', lang('Filled in when you come from the course\'s classroom.')),
            $row(array(
                $col(array($field('pco-task-first-name', lang('First Name'), $input('pco-task-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
                $col(array($field('pco-task-last-name', lang('Last Name'), $input('pco-task-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
                $col(array($field('pco-task-email', lang('Email'), $input('pco-task-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '12'),
            ), '3'),
            $field('pco-task-link', lang('Link to the project'), $input('pco-task-link', 'url', 'project_link', 'url', false, array(),
                array($attr('placeholder', 'https://'))), 'mb-3', lang('Your published site, a shared document or a design file.')),
            $field('pco-task-file', lang('File'), $file('pco-task-file', 'project_file', 'private', '.pdf,.zip,.png,.jpg,.jpeg,.xlsx,.docx'), 'mb-3',
                lang('Or attach it: PDF, ZIP, a picture, a spreadsheet or a document.')),
            $field('pco-task-notes', lang('A note to the instructor'), $textarea('pco-task-notes', 'notes', 4, false), 'mb-3',
                lang('What you tried, where you got stuck, what you would like feedback on.')),
            $field('pco-task-status', lang('Status'), $select('pco-task-status', 'status', '', $assignment_statuses, false, lang('Submitted'), array('office_use_only' => true))),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send project'), 'btn btn-primary btn-lg px-4'),
                $para(lang('Feedback usually comes within three working days.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    'assignment_detail' => $record_detail('assignment_view', 'assignment', 'bi-journal-x', lang('Assignment not found'),
        lang('This assignment could not be found, or it is not yours to see.'), 'my_assignments', lang('My Assignments'),
        lang('Thank you, your project has reached the instructor. The feedback comes below and by e-mail.'),
        array(
            $record_card(lang('Assignment'), array_merge(
                $detail(lang('Student'), '', lang('Student'), $student_name(), true),
                $detail(lang('Sent'), '', lang('Sent'), array_merge(array('text' => lang('Date')), $bind('submitted_date_and_time'))),
                $detail(lang('Link to the project'), 'text-break', lang('Link'), array(
                    $link('https://example.com/my-project', '#', '', array(), array('target' => '_blank', 'rel' => 'noopener',
                        '_bindings' => array('href' => 'project_link', 'text' => 'project_link'))),
                ), true),
                $detail(lang('File'), 'mb-0', lang('File'), array(
                    $link('project.pdf', '#', '', array(), array('target' => '_blank', 'rel' => 'noopener',
                        '_bindings' => array('href' => 'project_file', 'text' => 'project_file'))),
                ), true)
            )),
            $record_notes(lang('A note to the instructor'), 'notes', lang('I was not sure how to make the menu fit on a phone; is my way right?')),
            $record_hint('bi-pencil-square', lang('Asked for changes? Send a new version from the course\'s classroom and say here what you changed.')),
        )),

    'assignment_feedback' => $record_thread('assignment_view', array(
        'title'   => lang('Feedback'),
        'empty'   => lang('No feedback yet. The instructor writes here, usually within three working days.'),
        'heading' => lang('Write to the instructor'),
        'label'   => lang('Your message'),
        'sign_in' => lang('Sign in to write to the instructor.'),
        'submit'  => lang('Send message'),
    )),

    'my_assignments_list' => $record_list('my_assignments', 'assignment', 'assignment_view', $assignment_row(false),
        lang('No projects sent yet. Each course\'s project is at the end of its classroom.')),

    'staff_enrolments_list' => $record_list('staff_enrolments', 'enrolment', 'enrolment_view', $enrolment_row(true),
        lang('No enrolment requests yet.'), array(
            'fields' => array('first_name', 'last_name', 'company', 'course', 'status'),
            'label'  => lang('Search by student, company, course or status...'),
        )),
    'staff_assignments_list' => $record_list('staff_assignments', 'assignment', 'assignment_view', $assignment_row(true),
        lang('No projects sent yet.'), array(
            'fields' => array('first_name', 'last_name', 'course', 'status'),
            'label'  => lang('Search by student, course or status...'),
        )),

    // The e-mails of the two forms.
    'email_enrolment_admin' => $mail_widget('email_enrolment_new', 'enrolment', array(
        $mail_heading(lang('A new enrolment request')),
        $mail_para(lang('It came from the enrolment form. Send the invoice or the payment details by writing under it; once it is paid, give the student the course\'s folder in the control panel and set the status to Enrolled.')),
        $mail_enrolment(),
        $mail_box(array(
            $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
            $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
            $mail_line(lang('Notes'), array($span(lang('Please send the invoice to our finance team at finance@example.com.'), '', lang('Notes'), $bind('notes')))),
        ), lang('Student')),
        $mail_button(lang('Open the request'), '{{page:enrolment_view}}?r=^^reference_code^^'),
        $mail_reference(),
    )),
    'email_enrolment_client' => $mail_widget('email_enrolment_received', 'enrolment', array(
        $mail_heading(lang('We received your enrolment request')),
        $mail_greeting(),
        $mail_para(lang('Thank you. We answer within a working day with the invoice or the payment details; once it is paid, the course opens in My Courses.')),
        $mail_enrolment(),
        $mail_button(lang('Open my request'), '{{page:enrolment_view}}?r=^^reference_code^^'),
        $mail_new_account(),
    )),
    'email_assignment_admin' => $mail_widget('email_assignment_new', 'assignment', array(
        $mail_heading(lang('A new project to review')),
        $mail_para(lang('A student sent the project of a course. Write your feedback under it and set its status: Needs revision or Passed.')),
        $mail_assignment(),
        $mail_box(array(
            $mail_line(lang('A note to the instructor'), array($span(lang('I was not sure how to make the menu fit on a phone; is my way right?'), '', lang('Notes'), $bind('notes')))),
        ), lang('Note')),
        $mail_button(lang('Open the project'), '{{page:assignment_view}}?r=^^reference_code^^'),
        $mail_reference(),
    )),
    'email_assignment_client' => $mail_widget('email_assignment_received', 'assignment', array(
        $mail_heading(lang('We received your assignment')),
        $mail_greeting(),
        $mail_para(lang('Thank you for sending your project. The instructor reads it and writes feedback under it, usually within three working days; you get an e-mail when it is there.')),
        $mail_assignment(),
        $mail_button(lang('Open my project'), '{{page:assignment_view}}?r=^^reference_code^^'),
    )),

    // The comment of the reply e-mail, drawn for the comment the e-mail is
    // about, flat and alone. The pages that name the e-mail keep their
    // comment settings.
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

return array('pages' => $study_pages, 'widgets' => $study_widgets);
