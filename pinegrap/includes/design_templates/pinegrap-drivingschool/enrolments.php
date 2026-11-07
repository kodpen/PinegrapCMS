<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Driving School", its course enrolment requests: the
 * enrolment form, the enrolment's own page with its messages, the
 * learner's own list and the staff's list; the privacy page the consent
 * boxes link, the e-mails of the form and the new message e-mail every
 * thread of the site sends, and the widgets that draw them. Included after
 * parts.php, mail.php and courses.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...). The record pieces here (the
 * form page, the record page, the lists, the messages and the e-mail
 * lines) are reused by lessons.php, included after it.
 *
 * An enrolment is a request, never a payment: a record of the Course
 * Enrolments form. A learner without an account gets one
 * (auto-registration), so the enrolment's page and My Enrolments show it
 * at once. Its Status is an office-use-only field the school sets in the
 * control panel; the answers are written under it as messages (the page's
 * comments), mailed to the learner and to the school. The school takes
 * the payment at its desk once the enrolment is confirmed.
 *
 * The course starts with what a link sends (?course=: the course's name,
 * as the course page sends it).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$enrolment_pages = array();

// The sample enrolment the canvas shows: words, never a date.
$sample_course = $courses['car_standard']['name'];
$sample_branch = $branch_titles[0];
$sample_lesson_times = $lesson_time_choices[2];
$sample_theory = $theory_choices[0];
$sample_enrolment_notes = lang('I work until five on weekdays, so evening lessons suit me best. I have only driven a little, in an empty car park.');
$yes_no_choices = array(array(lang('Yes'), lang('Yes')), array(lang('No'), lang('No')));

// ── Pieces ──────────────────────────────────────────────────────────────

// A list of the records of a form, newest first.
$ds_list = function ($form_key, $detail_key, $empty, $more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:' . $form_key . '}}',
        'detail_page_id'      => '{{tab:' . $detail_key . '}}',
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
        'empty_message'       => $empty,
    ), $more);
};
// The note a record page shows when its record is not found or not the
// reader's.
$ds_not_found = function ($icon_name, $title, $text, $back_text, $back_key) use ($el, $icon, $heading, $para, $link, $bind, $show_when) {
    return $el('div', 'text-center py-5', lang('Not Found'), array(
        $icon($icon_name, 'display-5 text-body-tertiary'),
        $heading('h2', $title, 'h3 fw-semibold mt-3'),
        $para($text, 'text-body-secondary mb-4', $bind('not_found')),
        $link($back_text, '{{page:' . $back_key . '}}', 'btn btn-primary px-4'),
    ), $show_when('record_not_found'));
};

// The side column of a form page: what happens next, then the school's
// phone and hours.
$ds_form_aside = function ($title, $steps, $more) use ($el, $heading, $para, $icon, $span, $school) {
    $items = array();
    foreach ($steps as $n => $text) {
        $items[] = $el('li', $n < count($steps) - 1 ? 'mb-2' : '', lang('Step'), array(), array('text' => $text));
    }
    return array(
        $el('div', 'p-4 rounded-3 bg-body-tertiary mb-4', lang('How It Works'), array(
            $heading('h2', $title, 'pdr-display h5 mb-3'),
            $el('ol', 'small text-body-secondary ps-3 mb-0', lang('List'), $items),
        )),
        $el('div', 'p-4 rounded-3 border', lang('Office'), array(
            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                $icon('bi-telephone', 'text-primary'),
                $span(lang('Rather talk to us?'), '', lang('Text')),
            )),
            $para($more, 'small text-body-secondary mb-2'),
            $para($school['phone'], 'small fw-semibold mb-1'),
            $para($school['hours'], 'small text-body-secondary mb-0'),
        )),
    );
};

// A form page: the header, the form in a card and the side column.
$ds_ask_page = function ($title, $lead, $widget_key, $aside) use ($page, $page_header, $el, $container, $row, $col, $widget) {
    return $page(array(
        $page_header($title, $lead),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-3', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget($widget_key))),
                        )),
                    ), '', '8'),
                    $col($aside, '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false);
};

// A learner's own list page, with the way to send another.
$ds_my_page = function ($title, $lead, $widget_key, $more_title, $more_text, $more_link, $more_key) use ($page, $page_header, $el, $container, $row, $col, $widget, $icon, $span, $para, $link) {
    return $page(array(
        $page_header($title, $lead),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget($widget_key)), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-3 bg-body-tertiary', lang('New Request'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-plus-circle', 'text-primary'),
                                $span($more_title, '', lang('Text')),
                            )),
                            $para($more_text, 'small text-body-secondary mb-3'),
                            $link($more_link, '{{page:' . $more_key . '}}', 'btn btn-sm btn-primary px-3'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false);
};

// The staff's list page.
$ds_staff_list_page = function ($title, $lead, $widget_key) use ($staff_page, $page_header, $el, $container, $widget) {
    return $staff_page(array(
        $page_header($title, $lead),
        $el('section', 'py-5', lang('Content'), array(
            $container(array($widget($widget_key))),
        )),
    ));
};

// The comments settings of a record page: messages by signed-in readers,
// published at once, a file each, the reply e-mail to the learner and a
// copy to the school.
$ds_messages_settings = function ($subject) {
    return array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
        'attachments' => true,
        'email_page' => '{{tab:email_reply}}', 'email_subject' => $subject,
        'notify_email' => '{{site_email}}');
};

// The learner's name.
$learner_name = function () use ($span, $bind) {
    return array(
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
    );
};

// A record in a list: its title, its status and its details, linked to its
// page. The staff's row starts with the learner's name and adds the phone;
// the learner's own adds the messages.
$ds_record_row = function ($name, $title_sample, $title_field, $status, $items, $staff) use ($el, $span, $icon, $bind, $learner_name) {
    $item = function ($icon_name, $label, $sample, $field) use ($el, $icon, $span, $bind) {
        return $el('span', 'd-inline-flex align-items-center gap-1', $label, array(
            $icon($icon_name),
            $span($sample, '', $label, $bind($field)),
        ));
    };
    $children = array();
    foreach ($items as $i) $children[] = $item($i[0], $i[1], $i[2], $i[3]);
    if ($staff) {
        $children[] = $item('bi-telephone', lang('Phone'), '+90 555 000 00 00', 'phone');
        $title = $el('span', 'fw-semibold', lang('Learner'), array_merge($learner_name(), array(
            $span(' · ', 'text-body-secondary', lang('Text')),
            $span($title_sample, 'text-body-secondary fw-normal', $name, $bind($title_field)),
        )));
    } else {
        $children[] = $item('bi-chat-left-text', lang('Messages'), '0', 'number_of_comments');
        $title = $span($title_sample, 'fw-semibold', $name, $bind($title_field));
    }
    return $el('a', 'list-group-item list-group-item-action p-3', $name, array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $title,
            $span($status, 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
        )),
        $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), $children),
    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view')));
};
$enrolment_row = function ($staff) use ($ds_record_row, $sample_course, $sample_branch, $sample_lesson_times, $sample_theory, $enrolment_statuses) {
    $items = array(
        array('bi-geo-alt', lang('Branch'), $sample_branch, 'branch'),
        array('bi-clock', lang('Lesson Times'), $sample_lesson_times, 'lesson_times'),
    );
    if ($staff) $items[] = array('bi-journal-check', lang('Theory Test'), $sample_theory, 'theory_test');
    return $ds_record_row(lang('Course'), $sample_course, 'course', $enrolment_statuses[0], $items, $staff);
};

// A detail of the record's card: the label, then the value. $value is
// either the value's props (its sample text and binding) or, with
// $children, the nodes it is made of.
$detail = function ($label, $class, $name, $value, $children = false) use ($el) {
    return array(
        $el('dt', 'col-sm-4 fw-normal text-body-secondary', lang('Label'), array(), array('text' => $label)),
        $el('dd', trim('col-sm-8 ' . $class), $name, $children ? $value : array(), $children ? array() : $value),
    );
};
$bound = function ($sample, $field) use ($bind) {
    return array_merge(array('text' => $sample), $bind($field));
};

// The form detail settings of an e-mail page's widget (any reader: the
// e-mail is drawn for the submission just sent) and of a record page's
// widget (the learner's own, and the staff's).
$ds_detail_widget = function ($page_key, $form_key, $missing, $access) {
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
// A list of records: the learner's own (the staff see them all), or the
// staff's with a search.
$ds_record_list = function ($page_key, $form_key, $view_key, $staff, $empty, $search_fields, $search_label, $item) use ($ds_list, $widget_root, $el, $loop) {
    $more = array('items_per_page' => 20);
    if ($staff) {
        $more += array(
            'search_enabled' => true,
            'search_fields'  => $search_fields,
            'search_width'   => 'half',
            'search_label'   => $search_label,
        );
    } else {
        $more += array(
            'viewer_filter'           => true,
            'viewer_filter_submitter' => true,
            'viewer_filter_watcher'   => true,
            'viewer_filter_editor'    => true,
        );
    }
    return array(
        'page'   => $page_key,
        'slug'   => lang('form-list'),
        'config' => $ds_list($form_key, $view_key, $empty, $more),
        'tree'   => $widget_root(array(
            $el('div', 'list-group', lang('Request List'), array($loop(array($item)))),
        )),
    );
};

// The messages under a record: the page's own Comments widget, shown only
// to whoever may read the record above it. Oldest first, all on one page,
// with a file each. No Reply, Share or follow box: the learner is e-mailed
// every answer and the school every message.
$ds_messages_widget = function ($page_key, $empty) use ($comment_thread) {
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
        'tree'   => $comment_thread(array(
            'title'     => lang('Messages'),
            'empty'     => $empty,
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
    );
};

// The notes at the top of a record just sent: thank you, and the account
// the form opened for a sender who had none.
$ds_sent_notes = function ($thanks) use ($el, $icon, $span, $link, $para, $bind, $show_when, $attr) {
    return array(
        $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
            $icon('bi-check-circle', 'mt-1'),
            $span($thanks, '', lang('Text')),
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
    );
};

// A record's page: not found, or the notes, the toolbar, the card with its
// details, the learner's own words and the note about messages.
$ds_record_tree = function ($r) use ($el, $heading, $para, $icon, $span, $container, $row, $col, $bind, $edit_button, $show_when, $ds_not_found, $ds_sent_notes) {
    return array('type' => 'root', 'props' => array(), 'children' => array(
        $container(array(
            $row(array(
                $col(array(
                    $ds_not_found($r['missing_icon'], $r['missing_title'], $r['missing'], $r['back'], $r['back_key']),
                    $el('div', '', lang('Record'), array_merge($ds_sent_notes($r['thanks']), array(
                        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                            $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                                $icon('bi-arrow-left'),
                                $span($r['back'], '', lang('Link Text')),
                            ), array('href' => '{{page:' . $r['back_key'] . '}}')),
                            $edit_button(lang('Change Status')),
                        )),
                        $el('div', 'card border-0 shadow-sm rounded-3 mb-4', $r['card'], array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
                                    $el('div', '', lang('Words'), array(
                                        $para($r['eyebrow'], 'pdr-eyebrow small text-uppercase fw-semibold text-primary mb-1'),
                                        $heading('h1', $r['title'], 'pdr-display h3 mb-0', $bind($r['title_field'])),
                                    )),
                                    $span($r['status'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
                                )),
                                $el('dl', 'row small mb-0', lang('Details'), $r['details']),
                            )),
                        )),
                        $el('div', 'pg-hide-if-empty p-4 rounded-3 bg-body-tertiary mb-4', $r['words_label'], array(
                            $para($r['words_label'], 'small fw-semibold text-body-secondary mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $r['words']), $bind($r['words_field']))),
                        )),
                        $el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
                            $icon('bi-chat-left-text', 'text-primary mt-1'),
                            $span($r['note'], '', lang('Text')),
                        )),
                    )), $show_when('record_found')),
                ), '', '8'),
            ), '', 'center'),
        ), 'py-5'),
    ));
};

// The contact fields every form ends with.
$ds_contact_fields = function ($p) use ($row, $col, $field, $input, $radios, $contact_choices) {
    return array(
        $row(array(
            $col(array($field($p . '-first-name', lang('First Name'), $input($p . '-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
            $col(array($field($p . '-last-name', lang('Last Name'), $input($p . '-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
            $col(array($field($p . '-email', lang('Email'), $input($p . '-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
            $col(array($field($p . '-phone', lang('Phone'), $input($p . '-phone', 'tel', 'phone', 'tel', true, array('contact_field' => 'mobile_phone')))), '6'),
        ), '3'),
        $radios($p . '-contact', 'contact_method', lang('Contact by'), $contact_choices, $contact_choices[0][0]),
    );
};

// ── E-mail pieces ───────────────────────────────────────────────────────

// The lines of an enrolment, for its e-mails.
$mail_enrolment = function () use ($mail_box, $mail_line, $span, $bind, $sample_course, $sample_branch, $sample_lesson_times, $sample_theory) {
    return $mail_box(array(
        $mail_line(lang('Course'), array($span($sample_course, '', lang('Course'), $bind('course')))),
        $mail_line(lang('Branch'), array($span($sample_branch, '', lang('Branch'), $bind('branch')))),
        $mail_line(lang('Lesson Times'), array($span($sample_lesson_times, '', lang('Lesson Times'), $bind('lesson_times')))),
        $mail_line(lang('Theory Test'), array($span($sample_theory, '', lang('Theory Test'), $bind('theory_test')))),
    ), lang('Enrolment'));
};
// The learner's lines of a staff e-mail, with what they wrote.
$mail_learner = function ($words_label, $words, $words_field) use ($mail_box, $mail_line, $span, $bind, $learner_name) {
    return $mail_box(array(
        $mail_line(lang('Learner'), $learner_name()),
        $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
        $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
        $mail_line(lang('Contact by'), array($span(lang('E-mail'), '', lang('Contact by'), $bind('contact_method')))),
        $mail_line($words_label, array($span($words, '', $words_label, $bind($words_field)))),
    ), lang('Learner'));
};
// A reference code line under a staff e-mail.
$mail_reference = function () use ($el, $span, $bind, $mail_style) {
    return $el('p', 'small text-body-secondary mt-4 mb-0', lang('Reference'), array(
        $span(lang('Reference Code') . ': ', '', lang('Label')),
        $span('ABC123', '', lang('Reference Code'), $bind('reference_code')),
    ), $mail_style('font-size:13px;color:#4b5563;margin:24px 0 0'));
};
// The account a form opened for a sender who had none, in the reply e-mail.
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
    ), array_merge($mail_style('border-top:1px solid #fde68a;padding-top:16px'), $show_when('has_new_account')));
};

// ── Course enrolments ───────────────────────────────────────────────────

// The enrolment form. What is sent opens the enrolment's own page.
$enrolment_pages[] = array(
    'key'              => 'enrol',
    'name'             => lang('enrol-on-a-course'),
    'folder'           => 'public',
    'title'            => lang('Enrol on a Course'),
    'meta_description' => lang('Ask for a place on a driving course: choose the course, your branch and your lesson times, and we call you to confirm.'),
    'form'             => array(
        'form_name'            => lang('Course Enrolments'),
        'confirmation_message' => lang('Thank you, your enrolment request has reached the school. We call you within one working day to confirm your place.'),
        'confirmation_page_id' => '{{tab:enrolment_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New course enrolment: ^^course^^'),
        'notify_page_id'       => '{{tab:email_enrolment_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your course enrolment'),
        'confirm_page_id'      => '{{tab:email_enrolment_received}}',
    ),
    'tree'             => $ds_ask_page(lang('Enrol on a Course'),
        lang('Choose your course, the branch you would like to start from and when lessons suit you. Nothing is charged online: we call you to confirm your place first.'),
        'enrol_form',
        $ds_form_aside(lang('What happens next'), array(
            lang('Your enrolment opens its own page, and an account for you if you have none.'),
            lang('We call you within one working day to agree your first lesson and your instructor.'),
            lang('You pay the course at the branch once your place is confirmed.'),
            lang('A question before you start? Write under your enrolment.'),
        ), lang('Not sure which course suits you? Call us and an instructor helps you choose.'))),
);

// One enrolment: the request, its status and the messages under it.
$enrolment_pages[] = array(
    'key'      => 'enrolment_view',
    'name'     => lang('course-enrolment'),
    'folder'   => 'registration',
    'title'    => lang('Course Enrolment'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => $ds_messages_settings(lang('There is a new message about your course enrolment')),
    'tree'     => $page(array(
        $widget('enrolment_detail'),
        $comments_section(lang('Messages'), 'enrolment_messages'),
    ), false),
);

// My Enrolments: the learner's own enrolments (for the staff, everybody's).
$enrolment_pages[] = array(
    'key'     => 'my_enrolments',
    'name'    => lang('my-enrolments'),
    'folder'  => 'registration',
    'title'   => lang('My Enrolments'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $ds_my_page(lang('My Enrolments'), lang('Your course enrolments, their status and the school\'s answers, newest first.'),
        'my_enrolments_list', lang('Another course?'),
        lang('Enrol on another course; your details are filled in for you.'), lang('See All Courses'), 'courses'),
);

// The staff's list of every enrolment, newest first, with a search.
$enrolment_pages[] = array(
    'key'     => 'staff_enrolments',
    'name'    => lang('all-course-enrolments'),
    'folder'  => 'private',
    'title'   => lang('Course Enrolments'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $ds_staff_list_page(lang('Course Enrolments'),
        lang('Every course enrolment, newest first: call the learner, set the status and answer under each.'), 'staff_enrolments_list'),
);

// ── Privacy and e-mails ─────────────────────────────────────────────────

// The privacy notice the consent boxes and the footer link to: sample text
// with a note to adapt it.
$enrolment_pages[] = array(
    'key'              => 'privacy',
    'name'             => lang('privacy-policy'),
    'folder'           => 'public',
    'title'            => lang('Privacy Policy'),
    'meta_description' => lang('What the driving school keeps about you when you enrol, ask for a lesson or open an account, why, and what you can ask of us.'),
    'tree'             => $page(array(
        $page_header(lang('Privacy Policy'), lang('What the driving school keeps about you when you enrol, ask for a lesson or open an account, why, and what you can ask of us.')),
        $el('section', 'py-5', lang('Your Data'), array(
            $container(array($row(array($col(array(
                $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Sample Text Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Sample text: adapt it to your driving school and to the data protection law that applies to you before you publish.'), '', lang('Text')),
                ), array('_attrs' => array($attr('role', 'note')))),
                $heading('h2', lang('Your data'), 'h4 fw-semibold mb-3'),
                $para(lang('When you enrol on a course, ask for a lesson, join the newsletter or open an account, we keep your name, your contact details, your date of birth, where you stand with your licence and what you tell us, to teach you and to enter you for your tests.'), 'mb-3'),
                $para(lang('Only your instructor and the school\'s office see it. We keep it as long as the law requires, never sell it and never use it for anything else. You can see and change your account data from My Account, and ask us about the rest through the contact page.'), 'mb-0'),
            ), '', '8'))))),
        ), $searchable('drv-your-data', lang('privacy, personal data, KVKK, GDPR, delete my data'))),
    )),
);

$enrolment_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_enrolment_new',
    'name'  => lang('email-new-course-enrolment'),
    'title' => lang('New Course Enrolment E-mail'),
    'tree'  => $mail_page(array($widget('email_enrolment_admin'))),
));
$enrolment_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_enrolment_received',
    'name'  => lang('email-course-enrolment-received'),
    'title' => lang('Course Enrolment Received E-mail'),
    'tree'  => $mail_page(array($widget('email_enrolment_learner'))),
));
// A new message or reply: sent to the learner of an enrolment or a lesson
// request, the sender of a conversation and the visitors watching a page.
// Its Comments widget draws the comment in the card of mail.php; the
// System region after it keeps the fixed comment for an e-mail the widget
// does not draw.
$enrolment_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: your course enrolment, your lesson request, a conversation or a news article. You can read it below and answer it on the page.')),
        $widget('email_comment'),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));

// ── Widgets ─────────────────────────────────────────────────────────────

$enrolment_missing = lang('This course enrolment could not be found, or it is not yours to see.');

$enrolment_widgets = array(

    // The enrolment: the fields are the page's form. The course starts
    // with what a link sends (?course=). The Status is the school's
    // (office use only): every enrolment starts as received.
    'enrol_form' => array(
        'page'   => 'enrol',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array_merge(array(
            $form_step(1, lang('Your course')),
            $field('drv-en-course', lang('Course'), $select('drv-en-course', 'course', lang('Choose a course…'), $enrolment_course_choices, true, '', array('url_param' => 'course'))),
            $row(array(
                $col(array($field('drv-en-branch', lang('Branch'), $select('drv-en-branch', 'branch', '', $branch_titles, true, $branch_titles[0]))), '6'),
                $col(array($field('drv-en-times', lang('Lesson Times'), $select('drv-en-times', 'lesson_times', '', $lesson_time_choices, true, $lesson_time_choices[4]))), '6'),
            ), '3'),
            $form_step(2, lang('Where you stand')),
            $row(array(
                $col(array($field('drv-en-birth', lang('Date of Birth'), $input('drv-en-birth', 'date', 'date_of_birth', 'bday', true))), '6'),
                $col(array($field('drv-en-theory', lang('Theory Test'), $select('drv-en-theory', 'theory_test', '', $theory_choices, true, $theory_choices[0]))), '6'),
            ), '3'),
            $radios('drv-en-provisional', 'provisional_licence', lang('Do you hold a provisional licence?'), $yes_no_choices, lang('No')),
            $field('drv-en-notes', lang('Anything we should know?'), $textarea('drv-en-notes', 'notes', 4, false), 'mb-3',
                lang('Driving you have done, what worries you, days that never suit you. A few lines are enough.')),
            $form_step(3, lang('How to reach you')),
        ), $ds_contact_fields('drv-en'), array(
            $field('drv-en-status', lang('Status'), $select('drv-en-status', 'status', '', $enrolment_statuses, false, $enrolment_statuses[0], array('office_use_only' => true))),
            $agree('drv-en-consent', 'consent', lang('I agree that the school keeps these details to arrange my course, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Enrolment'), 'btn btn-primary btn-lg px-4'),
                $para(lang('Nothing is charged online; we call you within one working day.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        ))),
    ),

    // One enrolment: what the learner sent and its status; the answers
    // follow as comments.
    'enrolment_detail' => $ds_detail_widget('enrolment_view', 'enrol', $enrolment_missing, 'submitter_only') + array(
        'tree' => $ds_record_tree(array(
            'missing_icon'  => 'bi-journal-x',
            'missing_title' => lang('Course enrolment not found'),
            'missing'       => $enrolment_missing,
            'back'          => lang('My Enrolments'),
            'back_key'      => 'my_enrolments',
            'thanks'        => lang('Thank you, your enrolment request has reached the school. We call you within one working day to confirm your place; this page shows the status, and you can write to us below.'),
            'card'          => lang('Enrolment Card'),
            'eyebrow'       => lang('Course Enrolment'),
            'title'         => $sample_course,
            'title_field'   => 'course',
            'status'        => $enrolment_statuses[0],
            'details'       => array_merge(
                $detail(lang('Branch'), '', lang('Branch'), $bound($sample_branch, 'branch')),
                $detail(lang('Lesson Times'), '', lang('Lesson Times'), $bound($sample_lesson_times, 'lesson_times')),
                $detail(lang('Theory Test'), '', lang('Theory Test'), $bound($sample_theory, 'theory_test')),
                $detail(lang('Provisional Licence'), '', lang('Provisional Licence'), $bound(lang('No'), 'provisional_licence')),
                $detail(lang('Date of Birth'), '', lang('Date of Birth'), $bound(lang('Date'), 'date_of_birth')),
                $detail(lang('Learner'), '', lang('Learner'), $learner_name(), true),
                $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
            ),
            'words_label'   => lang('Anything we should know?'),
            'words'         => $sample_enrolment_notes,
            'words_field'   => 'notes',
            'note'          => lang('A question before your first lesson? Write to us below; we answer here and by e-mail.'),
        )),
    ),

    'enrolment_messages' => $ds_messages_widget('enrolment_view',
        lang('No messages yet. The school\'s answers come here; you can write to us about your course too.')),

    'my_enrolments_list' => $ds_record_list('my_enrolments', 'enrol', 'enrolment_view', false,
        lang('No course enrolments yet. Choose a course and follow your enrolment here.'),
        array(), '', $enrolment_row(false)),

    'staff_enrolments_list' => $ds_record_list('staff_enrolments', 'enrol', 'enrolment_view', true, lang('No course enrolments yet.'),
        array('first_name', 'last_name', 'email', 'phone', 'course', 'branch', 'status'),
        lang('Search by learner, course, branch or status...'), $enrolment_row(true)),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_enrolment_admin' => $ds_detail_widget('email_enrolment_new', 'enrol', lang('This course enrolment was not found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new course enrolment')),
            $mail_para(lang('It came from the enrolment form. Call the learner to confirm the place, then set the status on the enrolment\'s page; what you write there reaches the learner by e-mail.')),
            $mail_enrolment(),
            $mail_learner(lang('Anything we should know?'), $sample_enrolment_notes, 'notes'),
            $mail_button(lang('Open the enrolment'), '{{page:enrolment_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_enrolment_learner' => $ds_detail_widget('email_enrolment_received', 'enrol', lang('This course enrolment was not found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your course enrolment')),
            $mail_greeting(),
            $mail_para(lang('Thank you. We call you within one working day to agree your first lesson and your instructor. You pay the course at the branch once your place is confirmed.')),
            $mail_enrolment(),
            $mail_para(lang('A question before you start? Write to us on the enrolment\'s page; everything about it stays there.')),
            $mail_button(lang('Open my enrolment'), '{{page:enrolment_view}}?r=^^reference_code^^'),
            $mail_new_account(),
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

return array('pages' => $enrolment_pages, 'widgets' => $enrolment_widgets);
