<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "City Hall", its citizen requests and the meeting
 * requests of the mayor's office: each form, the record's own page with
 * its messages, the resident's own list and the staff's list; the privacy
 * page the consent boxes link, the e-mails of both forms and the new
 * message e-mail every thread of the site sends, and the widgets that draw
 * them. Included after parts.php, mail.php and notices.php in the same
 * scope (it reuses $cy_list and $cy_not_found); returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * A request is a record of the Citizen Requests form, never a case
 * system: a resident without an account gets one (auto-registration), so
 * the request's page and My Requests show it at once. Its Status and its
 * Department are office-use-only fields the city hall sets in the control
 * panel; the answers are written under it as messages (the page's
 * comments), mailed to the resident and to the city hall. A photo of the
 * place is kept in the template's private folder: the record's page links
 * it for whoever may read the record.
 *
 * The topic starts with what a link sends (?topic=: the service area's
 * title, as parts.php writes it on the service-area pages). A meeting
 * request with the mayor's office works the same way with a smaller form.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$request_pages = array();

// The sample request and meeting the canvas shows: words, never a date.
$sample_kind = $request_kinds[0];
$sample_topic = $service_areas['roads']['title'];
$sample_neighbourhood = $neighbourhoods[1];
$sample_place = lang('Mill Street, by the footbridge over the river');
$sample_description = lang('The street light in front of number 12 has been out for a week, and the pavement under it is broken. The corner is very dark in the evening.');
$sample_department = $departments['works']['title'];
$sample_subject = $meeting_subjects[0];
$sample_time = $meeting_times[0];
$sample_days = lang('Tuesday or Thursday next week');
$sample_meeting = lang('Our street association would like to present the residents\' ideas for the empty plot behind the school: a small park and a playground.');

// ── Pieces ──────────────────────────────────────────────────────────────

// The side column of a form page: what happens next, then the citizen
// desk and its line.
$cy_form_aside = function ($title, $steps, $more) use ($el, $heading, $para, $icon, $span, $city) {
    $items = array();
    foreach ($steps as $n => $text) {
        $items[] = $el('li', $n < count($steps) - 1 ? 'mb-2' : '', lang('Step'), array(), array('text' => $text));
    }
    return array(
        $el('div', 'p-4 rounded-3 bg-body-tertiary mb-4', lang('How It Works'), array(
            $heading('h2', $title, 'pcy-display h5 mb-3'),
            $el('ol', 'small text-body-secondary ps-3 mb-0', lang('List'), $items),
        )),
        $el('div', 'p-4 rounded-3 border', lang('Citizen line'), array(
            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                $icon('bi-headset', 'text-primary'),
                $span(lang('Citizen line'), '', lang('Text')),
            )),
            $para($more, 'small text-body-secondary mb-2'),
            $para($city['line'], 'small fw-semibold mb-1'),
            $para($city['line_hours'], 'small text-body-secondary mb-0'),
        )),
    );
};

// A form page: the header, the form in a card and the side column.
$cy_ask_page = function ($title, $lead, $widget_key, $aside) use ($page, $page_header, $el, $container, $row, $col, $widget) {
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

// A resident's own list page, with the way to send another.
$cy_my_page = function ($title, $lead, $widget_key, $more_title, $more_text, $more_link, $more_key) use ($page, $page_header, $el, $container, $row, $col, $widget, $icon, $span, $para, $link) {
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
$cy_staff_list_page = function ($title, $lead, $widget_key) use ($staff_page, $page_header, $el, $container, $widget) {
    return $staff_page(array(
        $page_header($title, $lead),
        $el('section', 'py-5', lang('Content'), array(
            $container(array($widget($widget_key))),
        )),
    ));
};

// The comments settings of a record page: messages by signed-in readers,
// published at once, a file each, the reply e-mail to the resident and a
// copy to the city hall.
$cy_messages_settings = function ($subject) {
    return array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
        'attachments' => true,
        'email_page' => '{{tab:email_reply}}', 'email_subject' => $subject,
        'notify_email' => '{{site_email}}');
};

// The resident's name.
$resident_name = function () use ($span, $bind) {
    return array(
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
    );
};

// A record in a list: its title, its status and its details, linked to its
// page. The staff's row starts with the resident's name and adds the phone;
// the resident's own adds the messages.
$cy_record_row = function ($name, $title_sample, $title_field, $status, $items, $staff) use ($el, $span, $icon, $bind, $resident_name) {
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
        $title = $el('span', 'fw-semibold', lang('Resident'), array_merge($resident_name(), array(
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
$request_row = function ($staff) use ($cy_record_row, $sample_topic, $sample_kind, $sample_neighbourhood, $sample_department, $request_statuses) {
    $items = array(
        array('bi-tag', lang('Kind'), $sample_kind, 'kind'),
        array('bi-geo-alt', lang('Neighbourhood'), $sample_neighbourhood, 'neighbourhood'),
    );
    if ($staff) $items[] = array('bi-diagram-3', lang('Department'), $sample_department, 'department');
    return $cy_record_row(lang('Topic'), $sample_topic, 'topic', $request_statuses[0], $items, $staff);
};
$meeting_row = function ($staff) use ($cy_record_row, $sample_subject, $sample_time, $sample_days, $meeting_statuses) {
    return $cy_record_row(lang('Subject'), $sample_subject, 'subject', $meeting_statuses[0], array(
        array('bi-clock', lang('Preferred Time'), $sample_time, 'preferred_time'),
        array('bi-calendar-event', lang('Preferred Days'), $sample_days, 'preferred_days'),
    ), $staff);
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
// widget (the resident's own, and the staff's).
$cy_detail_widget = function ($page_key, $form_key, $missing, $access) {
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
// A list of records: the resident's own (the staff see them all), or the
// staff's with a search.
$cy_record_list = function ($page_key, $form_key, $view_key, $staff, $empty, $search_fields, $search_label, $item) use ($cy_list, $widget_root, $el, $loop) {
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
        'config' => $cy_list($form_key, $view_key, $empty, $more),
        'tree'   => $widget_root(array(
            $el('div', 'list-group', lang('Request List'), array($loop(array($item)))),
        )),
    );
};

// The messages under a record: the page's own Comments widget, shown only
// to whoever may read the record above it. Oldest first, all on one page,
// with a file each. No Reply, Share or follow box: the resident is
// e-mailed every answer and the city hall every message.
$cy_messages_widget = function ($page_key, $empty) use ($comment_thread) {
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
$cy_sent_notes = function ($thanks) use ($el, $icon, $span, $link, $para, $bind, $show_when, $attr) {
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
// details, the resident's own words and the note about messages.
$cy_record_tree = function ($r) use ($el, $heading, $para, $icon, $span, $container, $row, $col, $bind, $show_when, $edit_button, $cy_not_found, $cy_sent_notes) {
    return array('type' => 'root', 'props' => array(), 'children' => array(
        $container(array(
            $row(array(
                $col(array(
                    $cy_not_found($r['missing_icon'], $r['missing_title'], $r['missing'], $r['back'], $r['back_key']),
                    $el('div', '', lang('Record'), array_merge($cy_sent_notes($r['thanks']), array(
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
                                        $para($r['eyebrow'], 'pcy-eyebrow small text-uppercase fw-semibold text-primary mb-1'),
                                        $heading('h1', $r['title'], 'pcy-display h3 mb-0', $bind($r['title_field'])),
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
$cy_contact_fields = function ($p) use ($row, $col, $field, $input, $radios, $contact_choices) {
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

// The lines of a request and of a meeting request, for their e-mails.
$mail_request = function () use ($mail_box, $mail_line, $span, $bind, $sample_kind, $sample_topic, $sample_neighbourhood, $sample_place) {
    return $mail_box(array(
        $mail_line(lang('Kind'), array($span($sample_kind, '', lang('Kind'), $bind('kind')))),
        $mail_line(lang('Topic'), array($span($sample_topic, '', lang('Topic'), $bind('topic')))),
        $mail_line(lang('Neighbourhood'), array($span($sample_neighbourhood, '', lang('Neighbourhood'), $bind('neighbourhood')))),
        $mail_line(lang('Place'), array($span($sample_place, '', lang('Place'), $bind('place')))),
    ), lang('Request'));
};
$mail_meeting = function () use ($mail_box, $mail_line, $span, $bind, $sample_subject, $sample_time, $sample_days) {
    return $mail_box(array(
        $mail_line(lang('Subject'), array($span($sample_subject, '', lang('Subject'), $bind('subject')))),
        $mail_line(lang('Preferred Time'), array($span($sample_time, '', lang('Preferred Time'), $bind('preferred_time')))),
        $mail_line(lang('Preferred Days'), array($span($sample_days, '', lang('Preferred Days'), $bind('preferred_days')))),
    ), lang('Meeting Request'));
};
// The resident's lines of a staff e-mail, with what they wrote.
$mail_resident = function ($words_label, $words, $words_field, $organisation = false) use ($mail_box, $mail_line, $span, $bind, $resident_name) {
    $lines = array($mail_line(lang('Resident'), $resident_name()));
    if ($organisation) {
        $lines[] = $mail_line(lang('Organisation'), array($span(lang('Linden Street Association'), '', lang('Organisation'), $bind('organisation'))));
    }
    $lines[] = $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email'))));
    $lines[] = $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone'))));
    $lines[] = $mail_line(lang('Contact by'), array($span(lang('E-mail'), '', lang('Contact by'), $bind('contact_method'))));
    $lines[] = $mail_line($words_label, array($span($words, '', $words_label, $bind($words_field))));
    return $mail_box($lines, lang('Resident'));
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
    ), array_merge($mail_style('border-top:1px solid #bfdbfe;padding-top:16px'), $show_when('has_new_account')));
};

// ── Citizen requests ────────────────────────────────────────────────────

// The request form. What is sent opens the request's own page.
$request_pages[] = array(
    'key'              => 'request',
    'name'             => lang('make-a-request'),
    'folder'           => 'public',
    'title'            => lang('Make a Request'),
    'meta_description' => lang('Report a problem, make a complaint or a suggestion, or ask the city hall a question, and follow the answers from your account.'),
    'form'             => array(
        'form_name'            => lang('Citizen Requests'),
        'confirmation_message' => lang('Thank you, your request has reached the city hall. The department in charge answers you on the request\'s page.'),
        'confirmation_page_id' => '{{tab:request_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New citizen request: ^^topic^^'),
        'notify_page_id'       => '{{tab:email_request_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your request'),
        'confirm_page_id'      => '{{tab:email_request_received}}',
    ),
    'tree'             => $cy_ask_page(lang('Make a Request'),
        lang('A broken street light, rubbish left behind, an idea for your park or a question: tell us in a few lines and follow the answers from your account.'),
        'request_form',
        $cy_form_aside(lang('What happens next'), array(
            lang('Your request opens its own page, and an account for you if you have none.'),
            lang('The citizen desk sends it to the department in charge within one working day.'),
            lang('The department writes to you under your request, and you receive each answer by e-mail.'),
            lang('A photo or a question to add? Write under your request.'),
        ), lang('Something urgent, such as a burst water pipe or a fallen tree on the road? Call the citizen line.'))),
);

// One request: the request, its status and the messages under it.
$request_pages[] = array(
    'key'      => 'request_view',
    'name'     => lang('citizen-request'),
    'folder'   => 'registration',
    'title'    => lang('Citizen Request'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => $cy_messages_settings(lang('There is a new message about your request')),
    'tree'     => $page(array(
        $widget('request_detail'),
        $comments_section(lang('Messages'), 'request_messages'),
    ), false),
);

// My Requests: the resident's own requests (for the staff, everybody's).
$request_pages[] = array(
    'key'     => 'my_requests',
    'name'    => lang('my-requests'),
    'folder'  => 'registration',
    'title'   => lang('My Requests'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $cy_my_page(lang('My Requests'), lang('Your requests, their status and the city hall\'s answers, newest first.'),
        'my_requests_list', lang('Something else to tell us?'),
        lang('Send another request; your details are filled in for you.'), lang('Make a Request'), 'request'),
);

// The staff's list of every request, newest first, with a search.
$request_pages[] = array(
    'key'     => 'staff_requests',
    'name'    => lang('all-citizen-requests'),
    'folder'  => 'private',
    'title'   => lang('Citizen Requests'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $cy_staff_list_page(lang('Citizen Requests'),
        lang('Every request, newest first: send it to its department, set the status and answer the resident under each.'), 'staff_requests_list'),
);

// ── Meetings with the mayor's office ────────────────────────────────────

$request_pages[] = array(
    'key'              => 'meet',
    'name'             => lang('meet-the-mayors-office'),
    'folder'           => 'public',
    'title'            => lang('Meet the Mayor\'s Office'),
    'meta_description' => lang('Ask for a meeting with the mayor\'s office: tell us what it is about and when suits you, and the office proposes a day.'),
    'form'             => array(
        'form_name'            => lang('Meeting Requests'),
        'confirmation_message' => lang('Thank you, your meeting request has reached the mayor\'s office. The office proposes a day on the request\'s page.'),
        'confirmation_page_id' => '{{tab:meeting_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New meeting request: ^^subject^^'),
        'notify_page_id'       => '{{tab:email_meeting_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your meeting request'),
        'confirm_page_id'      => '{{tab:email_meeting_received}}',
    ),
    'tree'             => $cy_ask_page(lang('Meet the Mayor\'s Office'),
        lang('The mayor and the mayor\'s advisers meet residents, associations and clubs every week. Tell us what you would like to talk about and when suits you.'),
        'meeting_form',
        $cy_form_aside(lang('What happens next'), array(
            lang('Your request opens its own page, and an account for you if you have none.'),
            lang('Within a week the mayor\'s office proposes a day and a time under your request.'),
            lang('You meet the mayor or the adviser who follows your subject, at the city hall.'),
        ), lang('A request about a street, a service or a bill is answered faster as a citizen request.'))),
);

$request_pages[] = array(
    'key'      => 'meeting_view',
    'name'     => lang('meeting-request'),
    'folder'   => 'registration',
    'title'    => lang('Meeting Request'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => $cy_messages_settings(lang('There is a new message about your meeting request')),
    'tree'     => $page(array(
        $widget('meeting_detail'),
        $comments_section(lang('Messages'), 'meeting_messages'),
    ), false),
);

$request_pages[] = array(
    'key'     => 'my_meetings',
    'name'    => lang('my-meeting-requests'),
    'folder'  => 'registration',
    'title'   => lang('My Meeting Requests'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $cy_my_page(lang('My Meeting Requests'), lang('Your meeting requests with the mayor\'s office and their answers, newest first.'),
        'my_meetings_list', lang('Another subject?'),
        lang('Ask for another meeting; your details are filled in for you.'), lang('Ask for a Meeting'), 'meet'),
);

$request_pages[] = array(
    'key'     => 'staff_meetings',
    'name'    => lang('all-meeting-requests'),
    'folder'  => 'private',
    'title'   => lang('Meeting Requests'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $cy_staff_list_page(lang('Meeting Requests'),
        lang('Every meeting request, newest first: propose a day under each, then set the status.'), 'staff_meetings_list'),
);

// ── Privacy and e-mails ─────────────────────────────────────────────────

// The privacy notice the consent boxes and the footer link to: sample text
// with a note to adapt it.
$request_pages[] = array(
    'key'              => 'privacy',
    'name'             => lang('privacy-policy'),
    'folder'           => 'public',
    'title'            => lang('Privacy Policy'),
    'meta_description' => lang('What the city hall keeps about you when you send a request, ask for a meeting or open an account, why, and what you can ask of us.'),
    'tree'             => $page(array(
        $page_header(lang('Privacy Policy'), lang('What the city hall keeps about you when you send a request, ask for a meeting or open an account, why, and what you can ask of us.')),
        $el('section', 'py-5', lang('Your Data'), array(
            $container(array($row(array($col(array(
                $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Sample Text Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Sample text: adapt it to your city hall and to the data protection law that applies to you before you publish.'), '', lang('Text')),
                ), array('_attrs' => array($attr('role', 'note')))),
                $heading('h2', lang('Your data'), 'h4 fw-semibold mb-3'),
                $para(lang('When you send a request, ask for a meeting, join the newsletter or open an account, we keep your name, your contact details, your neighbourhood, what you tell us and the photos or documents you send, to answer you and to do the work you asked for.'), 'mb-3'),
                $para(lang('Only the department that handles your request and the mayor\'s office see it. We keep it as long as the law requires, never sell it and never use it for anything else. You can see and change your account data from My Account, and ask us about the rest through the contact page.'), 'mb-0'),
            ), '', '8'))))),
        ), $searchable('cy-your-data', lang('privacy, personal data, KVKK, GDPR, delete my data'))),
    )),
);

$request_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_request_new',
    'name'  => lang('email-new-citizen-request'),
    'title' => lang('New Citizen Request E-mail'),
    'tree'  => $mail_page(array($widget('email_request_admin'))),
));
$request_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_request_received',
    'name'  => lang('email-request-received'),
    'title' => lang('Request Received E-mail'),
    'tree'  => $mail_page(array($widget('email_request_resident'))),
));
$request_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_meeting_new',
    'name'  => lang('email-new-meeting-request'),
    'title' => lang('New Meeting Request E-mail'),
    'tree'  => $mail_page(array($widget('email_meeting_admin'))),
));
$request_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_meeting_received',
    'name'  => lang('email-meeting-request-received'),
    'title' => lang('Meeting Request Received E-mail'),
    'tree'  => $mail_page(array($widget('email_meeting_resident'))),
));
// A new message or reply: sent to the resident of a request or a meeting
// request, the sender of a conversation and the visitors watching a page.
// Its Comments widget draws the comment in the card of mail.php; the
// System region after it keeps the fixed comment for an e-mail the widget
// does not draw.
$request_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: your request, your meeting request, a conversation or a news article. You can read it below and answer it on the page.')),
        $widget('email_comment'),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));

// ── Widgets ─────────────────────────────────────────────────────────────

$request_missing = lang('This request could not be found, or it is not yours to see.');
$meeting_missing = lang('This meeting request could not be found, or it is not yours to see.');

$request_widgets = array(

    // The request: the fields are the page's form. The topic starts with
    // what a link sends (?topic=). The Status and the Department are the
    // city hall's (office use only): every request starts as received.
    'request_form' => array(
        'page'   => 'request',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array_merge(array(
            $form_step(1, lang('Your request')),
            $row(array(
                $col(array($field('cy-rq-kind', lang('Kind'), $select('cy-rq-kind', 'kind', '', $request_kinds, true, $request_kinds[0]))), '6'),
                $col(array($field('cy-rq-topic', lang('Topic'), $select('cy-rq-topic', 'topic', lang('Choose a topic…'), $request_topic_choices, true, '', array('url_param' => 'topic')))), '6'),
            ), '3'),
            $row(array(
                $col(array($field('cy-rq-neighbourhood', lang('Neighbourhood'), $select('cy-rq-neighbourhood', 'neighbourhood', lang('Choose a neighbourhood…'), $neighbourhood_choices))), '6'),
                $col(array($field('cy-rq-place', lang('Place'), $input('cy-rq-place', 'text', 'place', 'street-address', false))), '6'),
            ), '3'),
            $field('cy-rq-description', lang('Your request'), $textarea('cy-rq-description', 'description', 5), 'mb-3',
                lang('What you saw or what you would like, where exactly and since when. A few lines are enough.')),
            $field('cy-rq-photo', lang('Photo or Document'), $file('cy-rq-photo', 'photo', 'private'), 'mb-3',
                lang('A photo of the place or a document (a picture, PDF or Word). More can follow under your request.')),
            $form_step(2, lang('How to reach you')),
        ), $cy_contact_fields('cy-rq'), array(
            $row(array(
                $col(array($field('cy-rq-status', lang('Status'), $select('cy-rq-status', 'status', '', $request_statuses, false, $request_statuses[0], array('office_use_only' => true)))), '6'),
                $col(array($field('cy-rq-department', lang('Department'), $select('cy-rq-department', 'department', lang('Choose a department…'), $department_titles, false, '', array('office_use_only' => true)))), '6'),
            ), '3'),
            $agree('cy-rq-consent', 'consent', lang('I agree that the city hall keeps these details to answer my request, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Request'), 'btn btn-primary btn-lg px-4'),
                $para(lang('The citizen desk sends it on within one working day.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        ))),
    ),

    // One request: what the resident wrote, its status and its department;
    // the answers follow as comments.
    'request_detail' => $cy_detail_widget('request_view', 'request', $request_missing, 'submitter_only') + array(
        'tree' => $cy_record_tree(array(
            'missing_icon'  => 'bi-clipboard-x',
            'missing_title' => lang('Request not found'),
            'missing'       => $request_missing,
            'back'          => lang('My Requests'),
            'back_key'      => 'my_requests',
            'thanks'        => lang('Thank you, your request has reached the city hall. The citizen desk sends it to the department in charge within one working day; this page shows the status, and you can write to us below.'),
            'card'          => lang('Request Card'),
            'eyebrow'       => lang('Citizen Request'),
            'title'         => $sample_topic,
            'title_field'   => 'topic',
            'status'        => $request_statuses[0],
            'details'       => array_merge(
                $detail(lang('Kind'), '', lang('Kind'), $bound($sample_kind, 'kind')),
                $detail(lang('Neighbourhood'), '', lang('Neighbourhood'), $bound($sample_neighbourhood, 'neighbourhood')),
                $detail(lang('Place'), '', lang('Place'), $bound($sample_place, 'place')),
                $detail(lang('Department'), '', lang('Department'), $bound($sample_department, 'department')),
                $detail(lang('Resident'), '', lang('Resident'), $resident_name(), true),
                $detail(lang('Photo or Document'), 'text-break', lang('Photo or Document'), array(
                    $link('street-light.jpg', '#', '', array(), array('target' => '_blank', 'rel' => 'noopener',
                        '_bindings' => array('href' => 'photo', 'text' => 'photo'))),
                ), true),
                $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
            ),
            'words_label'   => lang('Your request'),
            'words'         => $sample_description,
            'words_field'   => 'description',
            'note'          => lang('A photo to add or a question? Write to us below; we answer here and by e-mail.'),
        )),
    ),

    'request_messages' => $cy_messages_widget('request_view',
        lang('No messages yet. The department\'s answers come here; you can write to us about your request too.')),

    'my_requests_list' => $cy_record_list('my_requests', 'request', 'request_view', false,
        lang('No requests yet. Tell us what needs fixing or what you would like, and follow the answers here.'),
        array(), '', $request_row(false)),

    'staff_requests_list' => $cy_record_list('staff_requests', 'request', 'request_view', true, lang('No requests yet.'),
        array('first_name', 'last_name', 'email', 'topic', 'kind', 'neighbourhood', 'department', 'status'),
        lang('Search by resident, topic, neighbourhood, department or status...'), $request_row(true)),

    // A meeting request: the subject, when suits the resident and a few
    // lines about it. The Status is the mayor's office's.
    'meeting_form' => array(
        'page'   => 'meet',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array_merge(array(
            $form_step(1, lang('Your meeting')),
            $field('cy-mt-subject', lang('Subject'), $select('cy-mt-subject', 'subject', lang('Choose a subject…'), $meeting_subjects)),
            $row(array(
                $col(array($field('cy-mt-time', lang('Preferred Time'), $select('cy-mt-time', 'preferred_time', '', $meeting_times, false, $meeting_times[2]))), '6'),
                $col(array($field('cy-mt-days', lang('Preferred Days'), $input('cy-mt-days', 'text', 'preferred_days', '', false))), '6'),
            ), '3'),
            $field('cy-mt-details', lang('What would you like to talk about?'), $textarea('cy-mt-details', 'details', 4), 'mb-3',
                lang('A few lines help the office choose the right adviser and prepare.')),
            $form_step(2, lang('How to reach you')),
        ), $cy_contact_fields('cy-mt'), array(
            $field('cy-mt-organisation', lang('Organisation'), $input('cy-mt-organisation', 'text', 'organisation', 'organization', false), 'mb-3',
                lang('Only when you come for an association, a club or a company.')),
            $field('cy-mt-status', lang('Status'), $select('cy-mt-status', 'status', '', $meeting_statuses, false, $meeting_statuses[0], array('office_use_only' => true))),
            $agree('cy-mt-consent', 'consent', lang('I agree that the city hall keeps these details to arrange the meeting, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Ask for a Meeting'), 'btn btn-primary btn-lg px-4'),
                $para(lang('The office proposes a day within a week.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        ))),
    ),

    'meeting_detail' => $cy_detail_widget('meeting_view', 'meet', $meeting_missing, 'submitter_only') + array(
        'tree' => $cy_record_tree(array(
            'missing_icon'  => 'bi-calendar-x',
            'missing_title' => lang('Meeting request not found'),
            'missing'       => $meeting_missing,
            'back'          => lang('My Meeting Requests'),
            'back_key'      => 'my_meetings',
            'thanks'        => lang('Thank you, your meeting request has reached the mayor\'s office. The office proposes a day within a week; this page shows the status, and you can write to us below.'),
            'card'          => lang('Request Card'),
            'eyebrow'       => lang('Meeting Request'),
            'title'         => $sample_subject,
            'title_field'   => 'subject',
            'status'        => $meeting_statuses[0],
            'details'       => array_merge(
                $detail(lang('Preferred Time'), '', lang('Preferred Time'), $bound($sample_time, 'preferred_time')),
                $detail(lang('Preferred Days'), '', lang('Preferred Days'), $bound($sample_days, 'preferred_days')),
                $detail(lang('Resident'), '', lang('Resident'), $resident_name(), true),
                $detail(lang('Organisation'), '', lang('Organisation'), $bound(lang('Linden Street Association'), 'organisation')),
                $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
            ),
            'words_label'   => lang('What would you like to talk about?'),
            'words'         => $sample_meeting,
            'words_field'   => 'details',
            'note'          => lang('A question or another day that suits you? Write to us below; we answer here and by e-mail.'),
        )),
    ),

    'meeting_messages' => $cy_messages_widget('meeting_view',
        lang('No messages yet. The day the office proposes comes here; you can write to us too.')),

    'my_meetings_list' => $cy_record_list('my_meetings', 'meet', 'meeting_view', false,
        lang('No meeting requests yet. Ask for one, and the mayor\'s office proposes a day.'),
        array(), '', $meeting_row(false)),

    'staff_meetings_list' => $cy_record_list('staff_meetings', 'meet', 'meeting_view', true, lang('No meeting requests yet.'),
        array('first_name', 'last_name', 'email', 'organisation', 'subject', 'status'),
        lang('Search by resident, organisation, subject or status...'), $meeting_row(true)),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_request_admin' => $cy_detail_widget('email_request_new', 'request', lang('This request was not found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new citizen request')),
            $mail_para(lang('It came from the request form. Set its department and status on the request\'s page and answer the resident under it; what you write there reaches the resident by e-mail.')),
            $mail_request(),
            $mail_resident(lang('Your request'), $sample_description, 'description'),
            $mail_button(lang('Open the request'), '{{page:request_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_request_resident' => $cy_detail_widget('email_request_received', 'request', lang('This request was not found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your request')),
            $mail_greeting(),
            $mail_para(lang('Thank you. The citizen desk sends your request to the department in charge within one working day; its answers come on the request\'s page and by e-mail.')),
            $mail_request(),
            $mail_para(lang('A photo or a question to add? Write to us on the request\'s page; everything about it stays there.')),
            $mail_button(lang('Open my request'), '{{page:request_view}}?r=^^reference_code^^'),
            $mail_new_account(),
        )),
    ),
    'email_meeting_admin' => $cy_detail_widget('email_meeting_new', 'meet', lang('This meeting request was not found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new meeting request')),
            $mail_para(lang('It came from the meeting form. Propose a day under the request on its page and set the status; what you write there reaches the resident by e-mail.')),
            $mail_meeting(),
            $mail_resident(lang('What would you like to talk about?'), $sample_meeting, 'details', true),
            $mail_button(lang('Open the request'), '{{page:meeting_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_meeting_resident' => $cy_detail_widget('email_meeting_received', 'meet', lang('This meeting request was not found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your meeting request')),
            $mail_greeting(),
            $mail_para(lang('Thank you. Within a week the mayor\'s office proposes a day and a time on the request\'s page and by e-mail.')),
            $mail_meeting(),
            $mail_button(lang('Open my request'), '{{page:meeting_view}}?r=^^reference_code^^'),
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

return array('pages' => $request_pages, 'widgets' => $request_widgets);
