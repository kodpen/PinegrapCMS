<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Ski Resort", its lesson requests: the request page of
 * the ski school, the request's own page with its messages, My Lessons and
 * the staff's list, the privacy page the consent boxes link, the reply
 * e-mail of every request's messages and the two e-mails of a lesson
 * request. Included after parts.php, cards.php and mail.php in the same
 * scope; returns array('pages' => ..., 'widgets' => ...).
 *
 * A lesson is a request, never a booking: a record of the Lesson Requests
 * form. The ski school answers each request and sets its office-use-only
 * Status; its answers are the page's comments, mailed to the guest and to
 * the resort. A guest without an account gets one (auto-registration), so
 * the request's page and My Lessons show it at once. The lesson, the level
 * and the age group start with what a lesson card sends (?lesson=,
 * ?level=, ?age=, each in its list's own words).
 *
 * The request pieces under "Request pieces" ($request_aside,
 * $request_page, $request_view, $staff_list_page, $request_row, $detail,
 * $bound, $detail_widget, $request_list, $request_detail,
 * $request_messages, $contact_fields, $mail_reference, $mail_new_account,
 * $mail_sender, $not_before_today) serve the stay and group enquiries too,
 * included after this file in the same scope.
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

// The sample request the canvas shows.
$sample_lesson_key = 'junior';
$sample_lesson = $lessons[$sample_lesson_key]['title'];
$sample_level = $levels[$lessons[$sample_lesson_key]['level']];
$sample_age = $age_groups[$lessons[$sample_lesson_key]['age']];
$sample_days = $lesson_day_choices[2];
$sample_skiers = $skier_counts[1];
$sample_sport = $sport_choices[0][0];
$sample_lesson_notes = lang('Two brothers, eight and ten. The younger one has skied two days before; they would like to stay in the same group.');

// ── Request pieces ──────────────────────────────────────────────────────

// The side column of a request page: what happens next, then the hours of
// the lifts and the desks, and a link.
$request_aside = function ($steps, $link_text, $link_href) use ($el, $heading, $link, $hours_list) {
    $items = array();
    foreach ($steps as $n => $text) {
        $items[] = $el('li', $n < count($steps) - 1 ? 'mb-2' : '', lang('Step'), array(), array('text' => $text));
    }
    return array(
        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('How It Works'), array(
            $heading('h2', lang('What happens next'), 'psk-display h5 mb-3'),
            $el('ol', 'small text-body-secondary ps-3 mb-0', lang('List'), $items),
        )),
        $el('div', 'p-4 rounded-4 border', lang('Opening Hours'), array(
            $heading('h2', lang('Opening hours'), 'psk-display h5 mb-3'),
            $hours_list('mb-3'),
            $link($link_text, $link_href, 'fw-semibold link-primary text-decoration-none small'),
        )),
    );
};

// A form page: the header, the form in a card and the side column.
$request_page = function ($title, $lead, $widget_key, $aside) use ($page, $page_header, $el, $container, $row, $col, $widget) {
    return $page(array(
        $page_header($title, $lead),
        $el('section', 'py-5', lang('Content'), array(
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
        )),
    ), false);
};

// A request's own page: the request and the messages under it.
$request_view = function ($key, $name, $title, $detail_key, $messages_key, $subject) use ($page, $widget, $comments_section) {
    return array(
        'key'      => $key,
        'name'     => $name,
        'folder'   => 'registration',
        'title'    => $title,
        'search'   => false,
        'sitemap'  => false,
        'comments' => array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                            'attachments' => false,
                            'email_page' => '{{tab:email_reply}}', 'email_subject' => $subject,
                            'notify_email' => '{{site_email}}'),
        'tree'     => $page(array(
            $widget($detail_key),
            $comments_section(lang('Messages'), $messages_key),
        ), false),
    );
};

// A staff list page.
$staff_list_page = function ($key, $name, $title, $lead, $widget_key) use ($staff_page, $page_header, $el, $container, $widget) {
    return array(
        'key'     => $key,
        'name'    => $name,
        'folder'  => 'private',
        'title'   => $title,
        'search'  => false,
        'sitemap' => false,
        'tree'    => $staff_page(array(
            $page_header($title, $lead),
            $el('section', 'py-5', lang('Content'), array(
                $container(array($widget($widget_key))),
            )),
        )),
    );
};

// The sender's name.
$visitor_name = function () use ($span, $bind) {
    return array(
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
    );
};

// A request in a list, linked to its page: its title and status, then its
// details. The staff's row starts with the sender's name and adds the
// phone; the guest's own adds the messages.
$request_row = function ($staff, $title_sample, $title_field, $title_name, $status, $items) use ($el, $span, $icon, $bind, $visitor_name) {
    if ($staff) {
        $items[] = $el('span', 'd-inline-flex align-items-center gap-1', lang('Phone'), array(
            $icon('bi-telephone'), $span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')),
        ));
        $title = $el('span', 'fw-semibold', lang('Sender'), array_merge($visitor_name(), array(
            $span(' · ', 'text-body-secondary', lang('Text')),
            $span($title_sample, 'text-body-secondary fw-normal', $title_name, $bind($title_field)),
        )));
    } else {
        $items[] = $el('span', 'd-inline-flex align-items-center gap-1', lang('Messages'), array(
            $icon('bi-chat-left-text'), $span('0', '', lang('Reply Count'), $bind('number_of_comments')),
        ));
        $title = $span($title_sample, 'fw-semibold', $title_name, $bind($title_field));
    }
    return $el('a', 'list-group-item list-group-item-action p-3', lang('Request'), array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $title,
            $span($status, 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
        )),
        $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), $items),
    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view')));
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
    ), array_merge($mail_style('border-top:1px solid #e5e7eb;padding-top:16px'), $show_when('has_new_account')));
};
// The sender's lines of a staff e-mail.
$mail_sender = function ($notes_sample) use ($mail_box, $mail_line, $span, $bind, $visitor_name) {
    return $mail_box(array(
        $mail_line(lang('Name'), $visitor_name()),
        $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
        $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
        $mail_line(lang('Notes'), array($span($notes_sample, '', lang('Notes'), $bind('notes')))),
    ), lang('Sender'));
};
// The form detail settings of an e-mail page's widget (any reader: the
// e-mail is drawn for the submission just sent) and of a request page's
// widget (the sender's own, and the staff's).
$detail_widget = function ($page_key, $form_key, $missing, $access) {
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
// A list of requests: the guest's own (the staff see them all), or the
// staff's with a search.
$request_list = function ($page_key, $form_key, $view_key, $row, $search_fields, $search_label, $empty) use ($widget_root, $el, $loop) {
    $staff = $search_fields !== array();
    $config = array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:' . $form_key . '}}',
        'detail_page_id'      => '{{tab:' . $view_key . '}}',
        'items_per_page'      => 20,
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
    );
    if ($staff) {
        $config += array(
            'search_enabled' => true,
            'search_fields'  => $search_fields,
            'search_width'   => 'half',
            'search_label'   => $search_label,
        );
    } else {
        $config += array(
            'viewer_filter'           => true,
            'viewer_filter_submitter' => true,
            'viewer_filter_watcher'   => true,
            'viewer_filter_editor'    => true,
        );
    }
    $config['empty_message'] = $empty;
    return array(
        'page'   => $page_key,
        'slug'   => lang('form-list'),
        'config' => $config,
        'tree'   => $widget_root(array(
            $el('div', 'list-group', lang('Request List'), array($loop(array($row($staff))))),
        )),
    );
};

// The record page of a request: not found, the notices of a request just
// sent, the toolbar, the card of details, the notes and a closing line.
$request_detail = function ($back_text, $back_key, $missing, $not_found_title, $eyebrow, $title_sample, $title_field, $status, $sent, $details, $notes_sample, $closing)
    use ($widget_root, $container, $row, $col, $el, $icon, $heading, $para, $link, $span, $bind, $show_when, $attr, $edit_button, $bound) {
    return $widget_root(array(
        $container(array(
            $row(array(
                $col(array(
                    $el('div', 'text-center py-5', lang('Not Found'), array(
                        $icon('bi-calendar-x', 'display-5 text-body-tertiary'),
                        $heading('h2', $not_found_title, 'h3 fw-semibold mt-3'),
                        $para($missing, 'text-body-secondary mb-4', $bind('not_found')),
                        $link($back_text, '{{page:' . $back_key . '}}', 'btn btn-primary px-4'),
                    ), $show_when('record_not_found')),
                    $el('div', '', lang('Record'), array(
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
                        $el('div', 'card border-0 shadow-sm rounded-4 mb-4', lang('Request Card'), array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
                                    $el('div', '', lang('Words'), array(
                                        $para($eyebrow, 'psk-eyebrow small text-uppercase fw-semibold text-primary mb-1'),
                                        $heading('h1', $title_sample, 'psk-display h3 mb-0', $bind($title_field)),
                                    )),
                                    $span($status, 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
                                )),
                                $el('dl', 'row small mb-0', lang('Details'), $details),
                            )),
                        )),
                        $el('div', 'pg-hide-if-empty p-4 rounded-4 bg-body-tertiary mb-4', lang('Notes'), array(
                            $para(lang('Notes'), 'small fw-semibold text-body-secondary mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), $bound($notes_sample, 'notes')),
                        )),
                        $el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
                            $icon('bi-chat-left-text', 'text-primary mt-1'),
                            $span($closing, '', lang('Text')),
                        )),
                    ), $show_when('record_found')),
                ), '', '8'),
            ), '', 'center'),
        ), 'py-5'),
    ));
};

// The messages under a request: the page's own Comments widget, shown only
// to whoever may read the request above it. Oldest first, all on one page.
// No Reply, Share or follow box: the sender is e-mailed every answer and
// the resort every message.
$request_messages = function ($page_key, $empty) use ($comment_thread) {
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

// The sender's fields at the end of a request form.
$contact_fields = function ($p) use ($row, $col, $field, $input) {
    return $row(array(
        $col(array($field($p . '-first-name', lang('First Name'), $input($p . '-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
        $col(array($field($p . '-last-name', lang('Last Name'), $input($p . '-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
        $col(array($field($p . '-email', lang('Email'), $input($p . '-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
        $col(array($field($p . '-phone', lang('Phone'), $input($p . '-phone', 'tel', 'phone', 'tel', true, array('contact_field' => 'mobile_phone')))), '6'),
    ), '3');
};

// A first day or an arrival: today or later.
$not_before_today = array('rules' => array('min_date' => 'today'));

// ── Pages ───────────────────────────────────────────────────────────────

// The lesson request. What is sent opens the request's own page.
$lesson_pages[] = array(
    'key'              => 'lesson_request',
    'name'             => lang('request-a-lesson'),
    'folder'           => 'public',
    'title'            => lang('Request a Lesson'),
    'meta_description' => lang('Ask the ski school for a lesson for your level and age: it writes back with the instructor and the meeting time.'),
    'form'             => array(
        'form_name'            => lang('Lesson Requests'),
        'confirmation_message' => lang('Thank you, your lesson request has reached the ski school. It writes to you within a day.'),
        'confirmation_page_id' => '{{tab:lesson_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New lesson request: ^^lesson^^'),
        'notify_page_id'       => '{{tab:email_lesson_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your lesson request'),
        'confirm_page_id'      => '{{tab:email_lesson_received}}',
    ),
    'tree'             => $request_page(lang('Request a Lesson'),
        lang('Choose the lesson, the level and the age group, and the first day that suits you. The ski school writes back with an instructor; nothing is paid now.'),
        'lesson_form',
        array_merge(array(
            $el('div', 'p-4 rounded-4 border mb-4', lang('Coming as a Group'), array(
                $heading('h2', lang('A group or a school trip?'), 'psk-display h5 mb-2'),
                $para(lang('Lessons, passes and a place to stay in one offer: send us one enquiry.'), 'small text-body-secondary mb-3'),
                $link(lang('Stays and Groups'), '{{page:group_enquiry}}', 'btn btn-sm btn-outline-primary px-3'),
            )),
        ), $request_aside(array(
            lang('Your request opens its own page, and an account for you if you have none.'),
            lang('The ski school writes to you there within a day with the instructor and the meeting time.'),
            lang('The lesson is paid at the ski school desk on the first morning.'),
            lang('Plans change? Write to us on the request\'s page.'),
        ), lang('See the lessons'), '{{page:ski_school}}'))),
);

// One lesson request: the request, its status and the messages.
$lesson_pages[] = $request_view('lesson_view', lang('lesson-request'), lang('Lesson Request'),
    'lesson_detail', 'lesson_messages', lang('There is a new message about your lesson'));

// My Lessons: the guest's own requests (for the staff, everybody's).
$lesson_pages[] = array(
    'key'     => 'my_lessons',
    'name'    => lang('my-lessons'),
    'folder'  => 'registration',
    'title'   => lang('My Lessons'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('My Lessons'), lang('Your lesson requests, their status and the ski school\'s answers, newest first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('my_lessons_list')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('New Request'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-person-arms-up', 'text-primary'),
                                $span(lang('Another lesson?'), '', lang('Text')),
                            )),
                            $para(lang('Choose a lesson; your details are filled in for you.'), 'small text-body-secondary mb-3'),
                            $link(lang('See the lessons'), '{{page:ski_school}}', 'btn btn-sm btn-primary px-3'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

$lesson_pages[] = $staff_list_page('staff_lessons', lang('all-lesson-requests'), lang('Lesson Requests'),
    lang('Every lesson request, newest first: find an instructor, set the status and write to the guest under each.'),
    'staff_lessons_list');

// The privacy notice the consent boxes and the footer link to: sample text
// with a note to adapt it.
$lesson_pages[] = array(
    'key'              => 'privacy',
    'name'             => lang('privacy-policy'),
    'folder'           => 'public',
    'title'            => lang('Privacy Policy'),
    'meta_description' => lang('What the resort keeps about you when you request a lesson, ask about a stay, buy a pass or open an account, why, and what you can ask of us.'),
    'tree'             => $page(array(
        $page_header(lang('Privacy Policy'), lang('What the resort keeps about you when you request a lesson, ask about a stay, buy a pass or open an account, why, and what you can ask of us.')),
        $el('section', 'py-5', lang('Your Data'), array(
            $container(array($row(array($col(array(
                $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Sample Text Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Sample text: adapt it to your company and to the data protection law that applies to you before you publish.'), '', lang('Text')),
                ), array('_attrs' => array($attr('role', 'note')))),
                $heading('h2', lang('Your data'), 'psk-display h4 mb-3'),
                $para(lang('When you request a lesson, ask about a stay or a group trip, buy a pass, rent equipment, join the newsletter or open an account, we keep your name, your contact details and what you tell us, such as the skiers\' ages and levels, to answer you, find an instructor and welcome you on the mountain.'), 'mb-3'),
                $para(lang('We keep it as long as the law requires, never sell it and never give it to advertisers. You can see and change your account data from My Account, and ask us about the rest through the contact page.'), 'mb-0'),
            ), '', '8'))))),
        ), $searchable('skr-your-data', lang('privacy, personal data, KVKK, GDPR, delete my data'))),
    )),
);

// The e-mails: what the resort receives and the reply to the guest.
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
    'tree'  => $mail_page(array($widget('email_lesson_client'))),
));
// A new message or reply: sent to the sender of a request, the sender of a
// conversation and the visitors watching a page. Its Comments widget draws
// the comment in the card of mail.php; the System region after it keeps
// the fixed comment for an e-mail the widget does not draw.
$lesson_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: your lesson request, your stay enquiry, a conversation or a news post. You can read it below and answer it on the page.')),
        $widget('email_comment'),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));

// ── Widgets ─────────────────────────────────────────────────────────────

// The request's lesson, skiers and first day, for its e-mails.
$mail_lesson = function () use ($mail_box, $mail_line, $span, $bind, $sample_lesson, $sample_level, $sample_age, $sample_sport, $sample_days, $sample_skiers) {
    return $mail_box(array(
        $mail_line(lang('Lesson'), array($span($sample_lesson, '', lang('Lesson'), $bind('lesson')))),
        $mail_line(lang('Level'), array($span($sample_level, '', lang('Level'), $bind('level')))),
        $mail_line(lang('Age group'), array($span($sample_age, '', lang('Age group'), $bind('age_group')))),
        $mail_line(lang('Ski or snowboard'), array($span($sample_sport, '', lang('Ski or snowboard'), $bind('sport')))),
        $mail_line(lang('First day'), array($span(lang('First day'), '', lang('First day'), $bind('first_day')))),
        $mail_line(lang('Days'), array($span($sample_days, '', lang('Days'), $bind('days')))),
        $mail_line(lang('Skiers'), array($span($sample_skiers, '', lang('Skiers'), $bind('skiers')))),
    ), lang('Lesson Request'));
};
$lesson_row = function ($staff = false) use ($el, $icon, $span, $bind, $request_row, $sample_lesson, $sample_level, $sample_skiers, $lesson_statuses) {
    return $request_row($staff, $sample_lesson, 'lesson', lang('Lesson'), $lesson_statuses[0], array(
        $el('span', 'd-inline-flex align-items-center gap-1', lang('Level'), array(
            $icon('bi-bar-chart'), $span($sample_level, '', lang('Level'), $bind('level')),
        )),
        $el('span', 'd-inline-flex align-items-center gap-1', lang('First day'), array(
            $icon('bi-calendar-event'), $span(lang('First day'), '', lang('First day'), $bind('first_day')),
        )),
        $el('span', 'd-inline-flex align-items-center gap-1', lang('Skiers'), array(
            $icon('bi-people'), $span($sample_skiers, '', lang('Skiers'), $bind('skiers')),
        )),
    ));
};
$lesson_missing = lang('This lesson request could not be found, or it is not yours to see.');

$lesson_widgets = array(

    // The lesson request: the fields are the page's form. The lesson, level
    // and age group start with what a lesson card sends (?lesson=, ?level=,
    // ?age=). The Status is the ski school's (office use only): every
    // request starts as Request received.
    'lesson_form' => array(
        'page'   => 'lesson_request',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The lesson')),
            $field('skr-lr-lesson', lang('Lesson'), $select('skr-lr-lesson', 'lesson', lang('Choose a lesson…'), $lesson_choices, true, '', array('url_param' => 'lesson'))),
            $row(array(
                $col(array($field('skr-lr-level', lang('Level'), $select('skr-lr-level', 'level', lang('Choose…'), $level_titles, true, '', array('url_param' => 'level')))), '6'),
                $col(array($field('skr-lr-age', lang('Age group'), $select('skr-lr-age', 'age_group', lang('Choose…'), $age_titles, true, '', array('url_param' => 'age')))), '6'),
            ), '3'),
            $radios('skr-lr-sport', 'sport', lang('Ski or snowboard'), $sport_choices, $sport_choices[0][0]),
            $row(array(
                $col(array($field('skr-lr-first-day', lang('First day'), $input('skr-lr-first-day', 'date', 'first_day', 'off', true, $not_before_today))), '4'),
                $col(array($field('skr-lr-days', lang('Days'), $select('skr-lr-days', 'days', '', $lesson_day_choices, true, $lesson_day_choices[0]))), '4'),
                $col(array($field('skr-lr-skiers', lang('Skiers'), $select('skr-lr-skiers', 'skiers', '', $skier_counts, true, $skier_counts[0]), 'mb-3',
                    lang('Everyone in the same group.'))), '4'),
            ), '3'),
            $field('skr-lr-notes', lang('Notes'), $textarea('skr-lr-notes', 'notes', 3, false), 'mb-3',
                lang('The skiers\' ages, a lesson they had before, anything the instructor should know.')),
            $form_step(2, lang('How to reach you')),
            $contact_fields('skr-lr'),
            $radios('skr-lr-reply', 'reply_by', lang('Reply by'), $contact_choices, $contact_choices[0][0]),
            $field('skr-lr-status', lang('Status'), $select('skr-lr-status', 'status', '', $lesson_statuses, false, $lesson_statuses[0], array('office_use_only' => true))),
            $agree('skr-lr-consent', 'consent', lang('I agree that the resort keeps these details to arrange the lesson with me.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Request'), 'btn btn-primary btn-lg px-4'),
                $para(lang('The ski school writes to you within a day.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One lesson request; the ski school's answers follow as comments.
    'lesson_detail' => $detail_widget('lesson_view', 'lesson_request', $lesson_missing, 'submitter_only') + array(
        'tree' => $request_detail(lang('My Lessons'), 'my_lessons', $lesson_missing, lang('Lesson request not found'),
            lang('Lesson Request'), $sample_lesson, 'lesson', $lesson_statuses[0],
            lang('Thank you, your request has reached the ski school. It writes to you here within a day; this page shows the status, and you can write to us below.'),
            array_merge(
                $detail(lang('Level'), '', lang('Level'), $bound($sample_level, 'level')),
                $detail(lang('Age group'), '', lang('Age group'), $bound($sample_age, 'age_group')),
                $detail(lang('Ski or snowboard'), '', lang('Ski or snowboard'), $bound($sample_sport, 'sport')),
                $detail(lang('First day'), '', lang('First day'), $bound(lang('First day'), 'first_day')),
                $detail(lang('Days'), '', lang('Days'), $bound($sample_days, 'days')),
                $detail(lang('Skiers'), '', lang('Skiers'), $bound($sample_skiers, 'skiers')),
                $detail(lang('Name'), '', lang('Name'), $visitor_name(), true),
                $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
            ),
            $sample_lesson_notes,
            lang('Another day, one more skier or a question? Write to us below; we answer here and by e-mail.')),
    ),

    'lesson_messages' => $request_messages('lesson_view',
        lang('No messages yet. The ski school\'s answer comes here; you can write to us about the lesson too.')),

    'my_lessons_list' => $request_list('my_lessons', 'lesson_request', 'lesson_view', $lesson_row, array(), '',
        lang('No lesson requests yet. Choose a lesson at the ski school.')),

    'staff_lessons_list' => $request_list('staff_lessons', 'lesson_request', 'lesson_view', $lesson_row,
        array('first_name', 'last_name', 'email', 'lesson', 'level', 'age_group', 'status'),
        lang('Search by name, e-mail, lesson, level or status...'), lang('No lesson requests yet.')),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_lesson_admin' => $detail_widget('email_lesson_new', 'lesson_request', lang('The lesson request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new lesson request')),
            $mail_para(lang('It came from the lesson request form. Offer an instructor and a meeting time under the request on its page and set the status; what you write there reaches the guest by e-mail.')),
            $mail_lesson(),
            $mail_sender($sample_lesson_notes),
            $mail_button(lang('Open the request'), '{{page:lesson_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_lesson_client' => $detail_widget('email_lesson_received', 'lesson_request', lang('The lesson request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your lesson request')),
            $mail_greeting(),
            $mail_para(lang('Thank you. The ski school writes to you within a day with the instructor and the meeting time, on the request\'s page and by e-mail.')),
            $mail_lesson(),
            $mail_para(lang('Plans change? Write to us on the request\'s page; everything about the lesson stays there.')),
            $mail_button(lang('Open my request'), '{{page:lesson_view}}?r=^^reference_code^^'),
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

return array('pages' => $lesson_pages, 'widgets' => $lesson_widgets);
