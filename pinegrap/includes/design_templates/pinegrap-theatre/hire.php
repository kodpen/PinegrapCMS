<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Theatre", its venue hire: the hire page with the spaces
 * and the hire enquiry, the enquiry's own page with its messages, My Hire
 * Enquiries and the staff's list; the privacy page the consent boxes link;
 * the e-mails of the enquiry and the new message e-mail every thread of
 * the site sends. Included after parts.php, mail.php and the collections
 * in the same scope; returns array('pages' => ..., 'widgets' => ...).
 *
 * A hire is a request, never a booked date: a record of the Hire
 * Enquiries form. The spaces are the template's data (parts.php), so a
 * space card opens the enquiry with its space chosen (?space=, the
 * space's title). The theatre answers each enquiry with a quote and sets
 * its office-use-only Status; its answers are the page's comments, mailed
 * to the hirer and to the theatre. A hirer without an account gets one
 * (auto-registration), so the enquiry's page and My Hire Enquiries show it
 * at once.
 *
 * The request pieces under "Request pieces" ($request_aside,
 * $request_page, $request_view, $staff_list_page, $request_row, $detail,
 * $bound, $detail_widget, $request_list, $request_detail,
 * $request_messages, $contact_fields, $mail_sender, $visitor_name) serve
 * the learning requests too, included after this file in the same scope.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$hire_pages = array();

// The sample enquiry the canvas shows.
$sample_space = $spaces['studio']['title'];
$sample_hire_event = lang('Book launch with a reading');
$sample_hire_kind = $hire_kinds[0];
$sample_guests = $guest_counts[1];
$sample_hire_timing = lang('An evening, 18:00 to 22:00, with an hour to set up before');
$sample_hire_notes = lang('We would like a lectern, two microphones and drinks in the foyer after the reading.');

// A date field that takes no day before today.
$not_before_today = array('rules' => array('min_date' => 'today'));

// ── Request pieces ──────────────────────────────────────────────────────

// The side column of a request page: what happens next, then the box
// office hours and a link.
$request_aside = function ($steps, $link_text, $link_href) use ($el, $heading, $link, $hours_list) {
    $items = array();
    foreach ($steps as $n => $text) {
        $items[] = $el('li', $n < count($steps) - 1 ? 'mb-2' : '', lang('Step'), array(), array('text' => $text));
    }
    return array(
        $el('div', 'p-4 bg-body-tertiary mb-4', lang('How It Works'), array(
            $heading('h2', lang('What happens next'), 'pth-display h5 mb-3'),
            $el('ol', 'small text-body-secondary ps-3 mb-0', lang('List'), $items),
        )),
        $el('div', 'p-4 border', lang('Box Office Hours'), array(
            $heading('h2', lang('Box office hours'), 'pth-display h5 mb-3'),
            $hours_list('mb-3'),
            $link($link_text, $link_href, 'fw-semibold link-primary text-decoration-none small'),
        )),
    );
};

// A form in a card beside the side column.
$request_form_row = function ($widget_key, $aside) use ($el, $row, $col, $widget) {
    return $row(array(
        $col(array(
            $el('div', 'card border-0 shadow-sm', lang('Form Card'), array(
                $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget($widget_key))),
            )),
        ), '', '8'),
        $col($aside, '', '4'),
    ), '4', '', 'g-lg-5');
};

// A form page: the header, the form in a card and the side column.
$request_page = function ($title, $lead, $widget_key, $aside) use ($page, $page_header, $el, $container, $request_form_row) {
    return $page(array(
        $page_header($title, $lead),
        $el('section', 'py-5', lang('Content'), array(
            $container(array($request_form_row($widget_key, $aside))),
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

// The visitor's own list of one kind of request, with the way to send
// another beside it.
$my_requests_page = function ($key, $name, $title, $lead, $widget_key, $icon_name, $another, $another_text, $link_text, $link_href)
    use ($page, $page_header, $el, $container, $row, $col, $widget, $icon, $span, $para, $link) {
    return array(
        'key'     => $key,
        'name'    => $name,
        'folder'  => 'registration',
        'title'   => $title,
        'search'  => false,
        'sitemap' => false,
        'tree'    => $page(array(
            $page_header($title, $lead),
            $el('section', 'py-5', lang('Content'), array(
                $container(array(
                    $row(array(
                        $col(array($widget($widget_key)), '', '8'),
                        $col(array(
                            $el('div', 'p-4 bg-body-tertiary', lang('New Request'), array(
                                $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                    $icon($icon_name, 'text-primary'),
                                    $span($another, '', lang('Text')),
                                )),
                                $para($another_text, 'small text-body-secondary mb-3'),
                                $link($link_text, $link_href, 'btn btn-sm btn-primary px-3'),
                            )),
                        ), '', '4'),
                    ), '4', '', 'g-lg-5'),
                )),
            )),
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
// phone; the visitor's own adds the messages.
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

// A list of requests: the visitor's own (the staff see them all), or the
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
                        $icon('bi-file-earmark-x', 'display-5 text-body-tertiary'),
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
                        $el('div', 'card border-0 shadow-sm mb-4', lang('Request Card'), array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
                                    $el('div', '', lang('Words'), array(
                                        $para($eyebrow, 'pth-eyebrow small text-uppercase fw-semibold text-primary mb-1'),
                                        $heading('h1', $title_sample, 'pth-display h3 mb-0', $bind($title_field)),
                                    )),
                                    $span($status, 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
                                )),
                                $el('dl', 'row small mb-0', lang('Details'), $details),
                            )),
                        )),
                        $el('div', 'pg-hide-if-empty p-4 bg-body-tertiary mb-4', lang('Notes'), array(
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
// the theatre every message.
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

// ── Pages ───────────────────────────────────────────────────────────────

// The hire page: the spaces, what comes with them, and the enquiry. A
// space card opens it with its space chosen (?space=, at #th-hire-form).
// What is sent opens the enquiry's own page.
$space_cards = array();
foreach (array_keys($spaces) as $key) $space_cards[] = $space_card($key);
$hire_pages[] = array(
    'key'              => 'hire',
    'name'             => lang('venue-hire'),
    'folder'           => 'public',
    'title'            => lang('Hire a Space'),
    'meta_description' => lang('Hire the theatre\'s stages, rehearsal room or foyer bar for a conference, a launch, a party or filming. Send an enquiry for a quote.'),
    'form'             => array(
        'form_name'            => lang('Hire Enquiries'),
        'confirmation_message' => lang('Thank you, your enquiry has reached us. The theatre sends a quote within two working days.'),
        'confirmation_page_id' => '{{tab:hire_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New hire enquiry: ^^space^^'),
        'notify_page_id'       => '{{tab:email_hire_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your hire enquiry'),
        'confirm_page_id'      => '{{tab:email_hire_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Hire a Space'), lang('Two stages, a rehearsal room and a foyer bar, with the crew that runs our own shows. Tell us about your event and we send a quote.')),
        $el('section', 'py-5', lang('Spaces'), array(
            $container(array(
                $section_header(lang('Spaces'), lang('Choose your space')),
                $row($space_cards, '4'),
            )),
        ), $searchable('th-hire-spaces', lang('venue hire, rooms, auditorium, capacity, conference, meeting room, party, filming'))),
        $el('section', 'py-5 bg-body-tertiary', lang('What Comes With It'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $section_header(lang('With every hire'), lang('What comes with the space')),
                        $check_list(array(
                            lang('A duty manager and front of house staff for your guests'),
                            lang('Our technicians for lighting, sound and projection'),
                            lang('Step-free access and an assisted listening system'),
                            lang('Drinks and catering through the foyer bar team'),
                        )),
                    ), '', '6'),
                    $col(array(
                        $para(lang('Hires fit around our performances: daytimes, Sundays and the weeks between productions are the easiest to find. A quote is free and holds the date for a week.'), 'lead mb-0'),
                    ), '', '6'),
                ), '4', '', 'align-items-center'),
            )),
        )),
        $el('section', 'py-5', lang('Hire Enquiry'), array(
            $container(array(
                $section_header(lang('Enquiry'), lang('Tell us about your event')),
                $request_form_row('hire_form', $request_aside(array(
                    lang('Your enquiry opens its own page, and an account for you if you have none.'),
                    lang('The theatre sends a quote there within two working days and holds the date for a week.'),
                    lang('Come and see the space with our hire manager before you decide.'),
                    lang('Write to us on the enquiry\'s page about timings, equipment or catering.'),
                ), lang('Read the FAQ'), '{{page:faq}}')),
            )),
        ), $searchable('th-hire-form', lang('hire enquiry, quote, price, book a room, private event'))),
    ), false),
);

// One hire enquiry: the enquiry, its status and the messages.
$hire_pages[] = $request_view('hire_view', lang('hire-enquiry'), lang('Hire Enquiry'),
    'hire_detail', 'hire_messages', lang('There is a new message about your hire enquiry'));

// My Hire Enquiries: the hirer's own enquiries (for the staff, everybody's).
$hire_pages[] = $my_requests_page('my_hire', lang('my-hire-enquiries'), lang('My Hire Enquiries'),
    lang('Your hire enquiries, their status and the theatre\'s answers, newest first.'), 'my_hire_list',
    'bi-building-add', lang('Another event?'), lang('Send another enquiry; your details are filled in for you.'),
    lang('Hire a Space'), '{{page:hire}}');

$hire_pages[] = $staff_list_page('staff_hire', lang('all-hire-enquiries'), lang('Hire Enquiries'),
    lang('Every hire enquiry, newest first: send the quote, set the status and write to the hirer under each.'),
    'staff_hire_list');

// The privacy notice the consent boxes and the footer link to: sample text
// with a note to adapt it.
$privacy_words = lang('What the theatre keeps about you when you hire a space, book a school workshop, ask for a youth theatre place, buy a ticket or become a friend, why, and what you can ask of us.');
$hire_pages[] = array(
    'key'              => 'privacy',
    'name'             => lang('privacy-policy'),
    'folder'           => 'public',
    'title'            => lang('Privacy Policy'),
    'meta_description' => lang('What the theatre keeps about you, why, how long and what you can ask of us.'),
    'tree'             => $page(array(
        $page_header(lang('Privacy Policy'), $privacy_words),
        $el('section', 'py-5', lang('Your Data'), array(
            $container(array($row(array($col(array(
                $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Sample Text Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Sample text: adapt it to your company and to the data protection law that applies to you before you publish.'), '', lang('Text')),
                ), array('_attrs' => array($attr('role', 'note')))),
                $heading('h2', lang('Your data'), 'pth-display h4 mb-3'),
                $para(lang('When you send a hire enquiry or a school group request, ask for a youth theatre place, buy a ticket, become a friend, join the newsletter or open an account, we keep your name, your contact details and what you tell us, such as access needs, to answer you, keep your seat or place and welcome you.'), 'mb-3'),
                $para(lang('We keep it as long as the law requires, never sell it and never give it to advertisers. You can see and change your account data from My Account, and ask us about the rest through the contact page.'), 'mb-0'),
            ), '', '8'))))),
        ), $searchable('th-your-data', lang('privacy, personal data, KVKK, GDPR, delete my data'))),
    )),
);

// The e-mails: what the theatre receives and the reply to the sender of a
// hire enquiry, and the new message e-mail.
$hire_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_hire_new',
    'name'  => lang('email-new-hire-enquiry'),
    'title' => lang('New Hire Enquiry E-mail'),
    'tree'  => $mail_page(array($widget('email_hire_admin'))),
));
$hire_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_hire_received',
    'name'  => lang('email-hire-enquiry-received'),
    'title' => lang('Hire Enquiry Received E-mail'),
    'tree'  => $mail_page(array($widget('email_hire_client'))),
));
// A new message or reply: sent to the sender of a request, the sender of a
// conversation and the visitors watching a page. Its Comments widget draws
// the comment in the card of mail.php; the System region after it keeps
// the fixed comment for an e-mail the widget does not draw.
$hire_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: your hire enquiry, your school group or youth theatre request, a conversation, a production or a news post. You can read it below and answer it on the page.')),
        $widget('email_comment'),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));

// ── Widgets ─────────────────────────────────────────────────────────────

// The enquiry's space, event, guests and timing, for its e-mails.
$mail_hire = function () use ($mail_box, $mail_line, $span, $bind, $sample_space, $sample_hire_event, $sample_guests, $sample_hire_timing) {
    return $mail_box(array(
        $mail_line(lang('Space for hire'), array($span($sample_space, '', lang('Space for hire'), $bind('space')))),
        $mail_line(lang('Event'), array($span($sample_hire_event, '', lang('Event'), $bind('event_name')))),
        $mail_line(lang('Date'), array($span(lang('Date'), '', lang('Date'), $bind('event_date')))),
        $mail_line(lang('Timing'), array($span($sample_hire_timing, '', lang('Timing'), $bind('timing')))),
        $mail_line(lang('Guests'), array($span($sample_guests, '', lang('Guests'), $bind('guests')))),
    ), lang('Hire Enquiry'));
};
$hire_row = function ($staff = false) use ($el, $icon, $span, $bind, $request_row, $sample_space, $sample_guests, $hire_statuses) {
    return $request_row($staff, $sample_space, 'space', lang('Space for hire'), $hire_statuses[0], array(
        $el('span', 'd-inline-flex align-items-center gap-1', lang('Date'), array(
            $icon('bi-calendar-event'), $span(lang('Date'), '', lang('Date'), $bind('event_date')),
        )),
        $el('span', 'd-inline-flex align-items-center gap-1', lang('Guests'), array(
            $icon('bi-people'), $span($sample_guests, '', lang('Guests'), $bind('guests')),
        )),
    ));
};
$hire_missing = lang('This hire enquiry could not be found, or it is not yours to see.');

$hire_widgets = array(

    // The hire enquiry: the fields are the hire page's form. The space
    // starts with what a space card sends (?space=, the space's title).
    // The Status is the theatre's (office use only): every enquiry starts
    // as Enquiry received.
    'hire_form' => array(
        'page'   => 'hire',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('Your event')),
            $row(array(
                $col(array($field('th-hire-space', lang('Space for hire'), $select('th-hire-space', 'space', lang('Choose a space'), $space_choices, true, '', array('url_param' => 'space')))), '6'),
                $col(array($field('th-hire-kind', lang('Kind of event'), $select('th-hire-kind', 'kind', lang('Choose a kind'), $hire_kinds))), '6'),
            ), '3'),
            $field('th-hire-event', lang('Your event'), $input('th-hire-event', 'text', 'event_name', 'off'), 'mb-3',
                lang('A name or a line about it, for example a book launch with a reading.')),
            $row(array(
                $col(array($field('th-hire-date', lang('Preferred date'), $input('th-hire-date', 'date', 'event_date', 'off', true, $not_before_today))), '6'),
                $col(array($field('th-hire-guests', lang('Guests'), $select('th-hire-guests', 'guests', '', $guest_counts, true, $guest_counts[0]))), '6'),
            ), '3'),
            $field('th-hire-timing', lang('Timing'), $input('th-hire-timing', 'text', 'timing', 'off', false), 'mb-3',
                lang('The hours you need and any other dates that would suit, in words.')),
            $field('th-hire-notes', lang('Notes'), $textarea('th-hire-notes', 'notes', 3, false), 'mb-3',
                lang('Equipment, catering, access needs, a budget.')),
            $form_step(2, lang('How to reach you')),
            $contact_fields('th-hire'),
            $field('th-hire-company', lang('Company or organisation'), $input('th-hire-company', 'text', 'company', 'organization', false)),
            $radios('th-hire-reply', 'reply_by', lang('Reply by'), $contact_choices, $contact_choices[0][0]),
            $field('th-hire-status', lang('Status'), $select('th-hire-status', 'status', '', $hire_statuses, false, $hire_statuses[0], array('office_use_only' => true))),
            $agree('th-hire-consent', 'consent', lang('I agree that the theatre keeps these details to answer my enquiry.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Enquiry'), 'btn btn-primary btn-lg px-4'),
                $para(lang('A quote is free. Nothing is booked or paid now.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One hire enquiry; the theatre's answers follow as comments.
    'hire_detail' => $detail_widget('hire_view', 'hire', $hire_missing, 'submitter_only') + array(
        'tree' => $request_detail(lang('My Hire Enquiries'), 'my_hire', $hire_missing, lang('Hire enquiry not found'),
            lang('Hire Enquiry'), $sample_space, 'space', $hire_statuses[0],
            lang('Thank you, your enquiry has reached us. The theatre sends a quote here within two working days; this page shows the status, and you can write to us below.'),
            array_merge(
                $detail(lang('Your event'), '', lang('Your event'), $bound($sample_hire_event, 'event_name')),
                $detail(lang('Kind of event'), '', lang('Kind of event'), $bound($sample_hire_kind, 'kind')),
                $detail(lang('Preferred date'), '', lang('Preferred date'), $bound(lang('Preferred date'), 'event_date')),
                $detail(lang('Timing'), '', lang('Timing'), $bound($sample_hire_timing, 'timing')),
                $detail(lang('Guests'), '', lang('Guests'), $bound($sample_guests, 'guests')),
                $detail(lang('Reply by'), '', lang('Reply by'), $bound($contact_choices[0][1], 'reply_by')),
                $detail(lang('Name'), '', lang('Name'), $visitor_name(), true),
                $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
            ),
            $sample_hire_notes,
            lang('Another date, a question or a change of plan? Write to us below; we answer here and by e-mail.')),
    ),

    'hire_messages' => $request_messages('hire_view',
        lang('No messages yet. The theatre\'s quote comes here; you can write to us about your event too.')),

    'my_hire_list' => $request_list('my_hire', 'hire', 'hire_view', $hire_row, array(), '',
        lang('No hire enquiries yet. Choose a space and tell us about your event.')),

    'staff_hire_list' => $request_list('staff_hire', 'hire', 'hire_view', $hire_row,
        array('first_name', 'last_name', 'email', 'company', 'space', 'event_name', 'status'),
        lang('Search by name, e-mail, company, space or status...'), lang('No hire enquiries yet.')),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_hire_admin' => $detail_widget('email_hire_new', 'hire', lang('The hire enquiry could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new hire enquiry')),
            $mail_para(lang('It came from the hire page. Send the quote under the enquiry on its page and set the status; what you write there reaches the hirer by e-mail.')),
            $mail_hire(),
            $mail_sender($sample_hire_notes),
            $mail_button(lang('Open the enquiry'), '{{page:hire_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_hire_client' => $detail_widget('email_hire_received', 'hire', lang('The hire enquiry could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your hire enquiry')),
            $mail_greeting(),
            $mail_para(lang('Thank you. The theatre sends a quote within two working days, on the enquiry\'s page and by e-mail, and holds the date for a week.')),
            $mail_hire(),
            $mail_para(lang('Want to see the space first? Write to us on the enquiry\'s page and we arrange a visit.')),
            $mail_button(lang('Open my enquiry'), '{{page:hire_view}}?r=^^reference_code^^'),
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

return array('pages' => $hire_pages, 'widgets' => $hire_widgets);
