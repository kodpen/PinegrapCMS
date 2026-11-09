<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Film Studio", its requests: the project enquiry and the
 * studio hire request, each with its form, its own page with the messages
 * under it, the sender's list and the staff's list; the privacy page the
 * consent boxes link, the e-mails of both forms and the new message e-mail
 * every thread of the site sends, and the widgets that draw them.
 * Included after parts.php and mail.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * A project enquiry is a request, never a contract: a record of the
 * Project Enquiries form. A client without an account gets one
 * (auto-registration), so the enquiry's page and My Enquiries show it at
 * once. Its Status is an office-use-only field the studio changes in the
 * control panel (received, call booked, proposal sent, in production,
 * declined); the call and the treatment are written under it as messages
 * (the page's comments), mailed to the client and to the studio. A brief,
 * a script or references are kept in the template's private folder: the
 * record's page links them for whoever may read the record.
 *
 * A studio hire request is the smaller form: the space, the day (never in
 * the past), how long, the crew and the kit in words. There is no
 * calendar: the studio answers under the request with a confirmation.
 *
 * Both requests are built by the same page and widget builders from a
 * description of their own: the enquiry starts its service with what a
 * link sends (?service=, the service pages and the project page), the hire
 * request its space (?space=, the space cards of the studio page).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The sample enquiry and hire request the canvas shows. The delivery and
// the day are words, never a date.
$sample_service = $services['brand_films']['title'];
$sample_purpose = lang('The launch of our new skincare range');
$sample_screen = $screen_choices[1];
$sample_length = $length_choices[1];
$sample_budget = $budget_choices[1];
$sample_delivery = $delivery_choices[1];
$sample_details = lang('We make skincare by hand in a small workshop by the sea. We would like a two-minute film about the people and the plants behind it for our website, with short cut-downs for social media.');
$sample_company = lang('Saltwater Botanicals');
$sample_space = $spaces['cyclorama']['title'];
$sample_day = lang('Tuesday next week');
$sample_hire_length = $hire_lengths[1];
$sample_crew = '12';
$sample_kit = lang('Two LED panels, a smoke machine and a jib');
$sample_notes = lang('A product shoot for an online shop: forty items on a white background, and a short turntable video of each.');

// ── Page and widget builders ────────────────────────────────────────────

// The sender's name.
$fm_sender_name = function () use ($span, $bind) {
    return array(
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
    );
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

// The contact part both forms end with: name, e-mail, phone, the company,
// how to reach the sender. $p is the id prefix.
$fm_contact_fields = function ($p) use ($row, $col, $field, $input, $radios, $contact_choices) {
    return array(
        $row(array(
            $col(array($field($p . '-first-name', lang('First Name'), $input($p . '-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
            $col(array($field($p . '-last-name', lang('Last Name'), $input($p . '-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
            $col(array($field($p . '-email', lang('Email'), $input($p . '-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
            $col(array($field($p . '-phone', lang('Phone'), $input($p . '-phone', 'tel', 'phone', 'tel', true, array('contact_field' => 'mobile_phone')))), '6'),
        ), '3'),
        $field($p . '-company', lang('Company'), $input($p . '-company', 'text', 'company', 'organization', false), 'mb-3',
            lang('Only when you write for a company, a label or an agency.')),
        $radios($p . '-contact', 'contact_method', lang('Contact by'), $contact_choices, $contact_choices[0][0]),
    );
};

// The side column of a form page: what happens next, a reassurance box and
// the studio's hours.
$fm_form_aside = function ($steps, $box_title, $box_items) use ($el, $heading, $para, $icon, $span, $check_list, $studio) {
    $items = array();
    foreach ($steps as $n => $text) {
        $items[] = $el('li', $n < count($steps) - 1 ? 'mb-2' : '', lang('Step'), array(), array('text' => $text));
    }
    return array(
        $el('div', 'p-4 rounded-3 bg-body-tertiary mb-4', lang('How It Works'), array(
            $heading('h2', lang('What happens next'), 'pfm-display h5 mb-3'),
            $el('ol', 'small text-body-secondary ps-3 mb-0', lang('List'), $items),
        )),
        $el('div', 'p-4 rounded-3 border mb-4', $box_title, array(
            $heading('h2', $box_title, 'pfm-display h5 mb-3'),
            $check_list($box_items, 'list-unstyled small mb-0'),
        )),
        $el('div', 'p-4 rounded-3 border', lang('Office Hours'), array(
            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                $icon('bi-clock', 'text-primary'),
                $span(lang('Office Hours'), '', lang('Text')),
            )),
            $para($studio['hours'], 'small text-body-secondary mb-1'),
            $para($studio['phone'], 'small fw-semibold mb-0'),
        )),
    );
};

// The pages of one request: the form page, the record's page with its
// messages, the sender's list with the way to send another, the staff's
// list. $r describes the request (keys, words, the aside).
$fm_request_pages = function ($r) use ($page, $staff_page, $page_header, $el, $container, $row, $col, $widget, $icon, $span, $para, $link, $comments_section) {
    $form_page = array(
        'key'              => $r['key'],
        'name'             => $r['slug'],
        'folder'           => 'public',
        'title'            => $r['title'],
        'meta_description' => $r['meta'],
        'form'             => array(
            'form_name'            => $r['form_name'],
            'confirmation_message' => $r['confirmation'],
            'confirmation_page_id' => '{{tab:' . $r['view'] . '}}',
            'auto_registration'    => 1,
            'notify_email'         => '{{site_email}}',
            'notify_subject'       => $r['notify_subject'],
            'notify_page_id'       => '{{tab:email_' . $r['mail'] . '_new}}',
            'confirm_email'        => 1,
            'confirm_subject'      => $r['confirm_subject'],
            'confirm_page_id'      => '{{tab:email_' . $r['mail'] . '_received}}',
        ),
        'tree'             => $page(array(
            $page_header($r['title'], $r['lead']),
            $el('section', 'py-5', lang('Content'), array(
                $container(array(
                    $row(array(
                        $col(array(
                            $el('div', 'card border-0 shadow-sm rounded-3', lang('Form Card'), array(
                                $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget($r['key'] . '_form'))),
                            )),
                        ), '', '8'),
                        $col($r['aside'], '', '4'),
                    ), '4', '', 'g-lg-5'),
                )),
            )),
        ), false),
    );
    $view_page = array(
        'key'      => $r['view'],
        'name'     => $r['view_slug'],
        'folder'   => 'registration',
        'title'    => $r['view_title'],
        'search'   => false,
        'sitemap'  => false,
        'comments' => array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
            'attachments' => true, 'email_page' => '{{tab:email_reply}}', 'email_subject' => $r['message_subject'],
            'notify_email' => '{{site_email}}'),
        'tree'     => $page(array(
            $widget($r['key'] . '_detail'),
            $comments_section(lang('Messages'), $r['key'] . '_messages'),
        ), false),
    );
    $mine_page = array(
        'key'     => $r['mine'],
        'name'    => $r['mine_slug'],
        'folder'  => 'registration',
        'title'   => $r['mine_title'],
        'search'  => false,
        'sitemap' => false,
        'tree'    => $page(array(
            $page_header($r['mine_title'], $r['mine_lead']),
            $el('section', 'py-5', lang('Content'), array(
                $container(array(
                    $row(array(
                        $col(array($widget($r['mine'] . '_list')), '', '8'),
                        $col(array(
                            $el('div', 'p-4 rounded-3 bg-body-tertiary', lang('New Request'), array(
                                $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                    $icon('bi-plus-circle', 'text-primary'),
                                    $span($r['more_title'], '', lang('Text')),
                                )),
                                $para($r['more_text'], 'small text-body-secondary mb-3'),
                                $link($r['title'], '{{page:' . $r['key'] . '}}', 'btn btn-sm btn-primary px-3'),
                            )),
                        ), '', '4'),
                    ), '4', '', 'g-lg-5'),
                )),
            )),
        ), false),
    );
    $staff_list = array(
        'key'     => $r['staff'],
        'name'    => $r['staff_slug'],
        'folder'  => 'private',
        'title'   => $r['form_name'],
        'search'  => false,
        'sitemap' => false,
        'tree'    => $staff_page(array(
            $page_header($r['form_name'], $r['staff_lead']),
            $el('section', 'py-5', lang('Content'), array(
                $container(array($widget($r['staff'] . '_list'))),
            )),
        )),
    );
    return array($form_page, $view_page, $mine_page, $staff_list);
};

// The e-mail pages of one request: what the studio receives and what the
// sender receives.
$fm_request_mail_pages = function ($r) use ($mail_settings, $mail_page, $widget) {
    return array(
        array_merge($mail_settings, array(
            'key'   => 'email_' . $r['mail'] . '_new',
            'name'  => $r['mail_new_slug'],
            'title' => $r['mail_new_title'],
            'tree'  => $mail_page(array($widget('email_' . $r['mail'] . '_admin'))),
        )),
        array_merge($mail_settings, array(
            'key'   => 'email_' . $r['mail'] . '_received',
            'name'  => $r['mail_received_slug'],
            'title' => $r['mail_received_title'],
            'tree'  => $mail_page(array($widget('email_' . $r['mail'] . '_client'))),
        )),
    );
};

// A request in a list: its title and status on top, the details under
// them, linked to its page. The staff's row starts with the sender's name
// and adds the phone; the sender's own adds the messages.
$fm_request_row = function ($r, $staff) use ($el, $span, $icon, $bind, $fm_sender_name) {
    $item = function ($icon_name, $label, $sample, $field) use ($el, $icon, $span, $bind) {
        return $el('span', 'd-inline-flex align-items-center gap-1', $label, array(
            $icon($icon_name),
            $span($sample, '', $label, $bind($field)),
        ));
    };
    $items = array();
    foreach ($r['row_items'] as $i) $items[] = $item($i[0], $i[1], $i[2], $i[3]);
    if ($staff) {
        $items[] = $item('bi-telephone', lang('Phone'), '+90 555 000 00 00', 'phone');
        $title = $el('span', 'fw-semibold', lang('Client'), array_merge($fm_sender_name(), array(
            $span(' · ', 'text-body-secondary', lang('Text')),
            $span($r['title_sample'], 'text-body-secondary fw-normal', $r['title_label'], $bind($r['title_field'])),
        )));
    } else {
        $items[] = $item('bi-chat-left-text', lang('Messages'), '0', 'number_of_comments');
        $title = $span($r['title_sample'], 'fw-semibold', $r['title_label'], $bind($r['title_field']));
    }
    return $el('a', 'list-group-item list-group-item-action p-3', $r['view_title'], array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $title,
            $span($r['status_sample'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
        )),
        $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), $items),
    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view')));
};

// The form detail settings of a widget reading one record of the request's
// form: an e-mail page's (any reader: the e-mail is drawn for the
// submission just sent) or the record page's (the sender's own, and the
// staff's).
$fm_detail_widget = function ($r, $page_key, $missing, $access) {
    return array(
        'page'   => $page_key,
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:' . $r['key'] . '}}',
            'access_control'      => $access,
            'not_found_message'   => $missing,
        ),
    );
};

// A list of one request's records: the sender's own (the staff see them
// all), or the staff's with a search.
$fm_request_list = function ($r, $staff) use ($widget_root, $el, $loop, $fm_request_row) {
    $config = array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:' . $r['key'] . '}}',
        'detail_page_id'      => '{{tab:' . $r['view'] . '}}',
        'items_per_page'      => 20,
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
    );
    if ($staff) {
        $config += array(
            'search_enabled' => true,
            'search_fields'  => $r['search'],
            'search_width'   => 'half',
            'search_label'   => $r['search_label'],
        );
    } else {
        $config += array(
            'viewer_filter'           => true,
            'viewer_filter_submitter' => true,
            'viewer_filter_watcher'   => true,
            'viewer_filter_editor'    => true,
        );
    }
    $config['empty_message'] = $staff ? $r['staff_empty'] : $r['mine_empty'];
    return array(
        'page'   => $staff ? $r['staff'] : $r['mine'],
        'slug'   => lang('form-list'),
        'config' => $config,
        'tree'   => $widget_root(array(
            $el('div', 'list-group', lang('Request List'), array($loop(array($fm_request_row($r, $staff))))),
        )),
    );
};

// The record page's tree: the not-found note, the sent and new-account
// notices, the toolbar, the card with the details, the sender's words and
// a note on the messages under it.
$fm_record_tree = function ($r) use ($el, $heading, $para, $link, $span, $icon, $attr, $bind, $show_when, $container, $row, $col, $edit_button, $bound) {
    return array('type' => 'root', 'props' => array(), 'children' => array(
        $container(array(
            $row(array(
                $col(array(
                    $el('div', 'text-center py-5', lang('Not Found'), array(
                        $icon($r['missing_icon'], 'display-5 text-body-tertiary'),
                        $heading('h2', $r['missing_title'], 'pfm-display h3 mt-3'),
                        $para($r['missing'], 'text-body-secondary mb-4', $bind('not_found')),
                        $link($r['mine_title'], '{{page:' . $r['mine'] . '}}', 'btn btn-primary px-4'),
                    ), $show_when('record_not_found')),
                    $el('div', '', lang('Record'), array(
                        $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
                            $icon('bi-check-circle', 'mt-1'),
                            $span($r['sent'], '', lang('Text')),
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
                                $span($r['mine_title'], '', lang('Link Text')),
                            ), array('href' => '{{page:' . $r['mine'] . '}}')),
                            $edit_button(lang('Change Status')),
                        )),
                        $el('div', 'card border-0 shadow-sm rounded-3 mb-4', lang('Request Card'), array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
                                    $el('div', '', lang('Words'), array(
                                        $para($r['view_title'], 'pfm-eyebrow small text-uppercase fw-semibold text-primary mb-1'),
                                        $heading('h1', $r['title_sample'], 'pfm-display h3 mb-0', $bind($r['title_field'])),
                                    )),
                                    $span($r['status_sample'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
                                )),
                                $el('dl', 'row small mb-0', lang('Details'), $r['details']),
                            )),
                        )),
                        $el('div', 'pg-hide-if-empty p-4 rounded-3 bg-body-tertiary mb-4', $r['words_label'], array(
                            $para($r['words_label'], 'small fw-semibold text-body-secondary mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), $bound($r['words'], $r['words_field'])),
                        )),
                        $el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
                            $icon('bi-chat-left-text', 'text-primary mt-1'),
                            $span($r['note'], '', lang('Text')),
                        )),
                    ), $show_when('record_found')),
                ), '', '8'),
            ), '', 'center'),
        ), 'py-5'),
    ));
};

// The widgets of one request: the form (its own fields, then the contact
// part, the office-only status, the consent and the CAPTCHA), the record,
// its messages, the two lists and the two e-mails.
$fm_request_widgets = function ($r) use ($widget_root, $el, $para, $loop, $form_step, $agree, $captcha, $submit, $comment_thread,
    $fm_contact_fields, $fm_detail_widget, $fm_request_list, $fm_record_tree, $fm_sender_name,
    $mail_heading, $mail_para, $mail_greeting, $mail_button, $mail_box, $mail_line, $mail_reference, $mail_new_account, $span, $bind, $sample_company) {
    $sender_lines = array(
        $mail_line(lang('Client'), $fm_sender_name()),
        $mail_line(lang('Company'), array($span($sample_company, '', lang('Company'), $bind('company')))),
        $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
        $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
        $mail_line(lang('Contact by'), array($span(lang('E-mail'), '', lang('Contact by'), $bind('contact_method')))),
        $mail_line($r['words_label'], array($span($r['words'], '', $r['words_label'], $bind($r['words_field'])))),
    );
    $open = '{{page:' . $r['view'] . '}}?r=^^reference_code^^';
    $mail_missing = $r['mail_missing'];
    return array(
        // The fields are the page's form. The Status is the studio's
        // (office use only): every request starts at its first state.
        $r['key'] . '_form' => array(
            'page'   => $r['key'],
            'slug'   => 'form',
            'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
            'tree'   => $widget_root(array_merge(
                array($form_step(1, $r['step'])),
                $r['fields'],
                array($form_step(2, lang('How to reach you'))),
                $fm_contact_fields($r['prefix']),
                array(
                    $r['status_field'],
                    $agree($r['prefix'] . '-consent', 'consent', $r['consent'], true),
                    $captcha(),
                    $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                        $submit($r['submit'], 'btn btn-primary btn-lg px-4'),
                        $para($r['submit_note'], 'small text-body-secondary mb-0'),
                    )),
                    $loop(array()),
                )
            )),
        ),

        // One record: what the sender wrote, its status; the studio's
        // answers follow as comments.
        $r['key'] . '_detail' => $fm_detail_widget($r, $r['view'], $r['missing'], 'submitter_only') + array(
            'tree' => $fm_record_tree($r),
        ),

        // The messages under a record: the page's own Comments widget,
        // shown only to whoever may read the record above it. Oldest first,
        // all on one page, with a file each. No Reply, Share or follow box:
        // the sender is e-mailed every answer and the studio every message.
        $r['key'] . '_messages' => array(
            'page'   => $r['view'],
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
                'empty'     => $r['messages_empty'],
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

        $r['mine'] . '_list'  => $fm_request_list($r, false),
        $r['staff'] . '_list' => $fm_request_list($r, true),

        // The e-mail pages' widgets: drawn for the submission just sent.
        'email_' . $r['mail'] . '_admin' => $fm_detail_widget($r, 'email_' . $r['mail'] . '_new', $mail_missing, 'public') + array(
            'tree' => $widget_root(array(
                $mail_heading($r['mail_new_heading']),
                $mail_para($r['mail_new_text']),
                $r['mail_lines'],
                $mail_box($sender_lines, lang('Client')),
                $mail_button($r['mail_open'], $open),
                $mail_reference(),
            )),
        ),
        'email_' . $r['mail'] . '_client' => $fm_detail_widget($r, 'email_' . $r['mail'] . '_received', $mail_missing, 'public') + array(
            'tree' => $widget_root(array(
                $mail_heading($r['confirm_subject']),
                $mail_greeting(),
                $mail_para($r['mail_received_text']),
                $r['mail_lines'],
                $mail_para($r['mail_received_more']),
                $mail_button($r['mail_open_mine'], $open),
                $mail_new_account(),
            )),
        ),
    );
};

// ── Project enquiries ───────────────────────────────────────────────────

$enquiry_request = array(
    'key'   => 'enquiry',
    'view'  => 'enquiry_view',
    'mine'  => 'my_enquiries',
    'staff' => 'staff_enquiries',
    'mail'  => 'enquiry',
    'slug'  => lang('start-a-project'),
    'view_slug'          => lang('project-enquiry'),
    'mine_slug'          => lang('my-project-enquiries'),
    'staff_slug'         => lang('all-project-enquiries'),
    'mail_new_slug'      => lang('email-new-project-enquiry'),
    'mail_received_slug' => lang('email-project-enquiry-received'),
    'title' => lang('Start a Project'),
    'meta'  => lang('Tell the studio what your film must do; a producer calls you back within a day and sends a written treatment within a week.'),
    'lead'  => lang('Tell us what the film must do, where it will be shown and when you need it. A producer calls you back within a day and sends a written treatment within a week.'),
    'aside' => $fm_form_aside(array(
        lang('Your project enquiry opens its own page, and an account for you if you have none.'),
        lang('A producer reads it and calls you back within a day.'),
        lang('Within a week you receive a written treatment: the idea, the look and a first budget.'),
        lang('A script, references or a question? Write under your enquiry.'),
    ), lang('No obligation'), array(
        lang('The first call and the treatment are free.'),
        lang('The treatment is yours to accept or not.'),
        lang('Your brief and references are seen only by the studio.'),
    )),
    'form_name'       => lang('Project Enquiries'),
    'confirmation'    => lang('Thank you, your project enquiry has reached us. A producer calls you back within a day.'),
    'notify_subject'  => lang('New project enquiry: ^^service^^'),
    'confirm_subject' => lang('We received your project enquiry'),
    'view_title'      => lang('Project Enquiry'),
    'message_subject' => lang('There is a new message about your project enquiry'),
    'mine_title'      => lang('My Enquiries'),
    'mine_lead'       => lang('Your project enquiries, their status and our answers, newest first.'),
    'more_title'      => lang('Another film?'),
    'more_text'       => lang('Send an enquiry for another film; your details are filled in for you.'),
    'staff_lead'      => lang('Every project enquiry, newest first: book the call, send the treatment, set the status and write to the client under each.'),
    'mail_new_title'      => lang('New Project Enquiry E-mail'),
    'mail_received_title' => lang('Project Enquiry Received E-mail'),

    'prefix' => 'fm-enq',
    'step'   => lang('The film'),
    'fields' => array(
        $row(array(
            $col(array($field('fm-enq-service', lang('Service Type'), $select('fm-enq-service', 'service', lang('Choose a service…'), $enquiry_service_choices, true, '', array('url_param' => 'service')))), '6'),
            $col(array($field('fm-enq-screen', lang('Where It Will Be Shown'), $select('fm-enq-screen', 'screen', lang('Choose one…'), $screen_choices))), '6'),
        ), '3'),
        $field('fm-enq-purpose', lang('Purpose of the Film'), $input('fm-enq-purpose', 'text', 'purpose', 'off'), 'mb-3',
            lang('A launch, a campaign, a festival, a training course: what the film must do.')),
        $row(array(
            $col(array($field('fm-enq-length', lang('Running Time'), $select('fm-enq-length', 'length', '', $length_choices, false, $length_choices[1]))), '4'),
            $col(array($field('fm-enq-budget', lang('Budget'), $select('fm-enq-budget', 'budget', '', $budget_choices, false, $budget_choices[4]))), '4'),
            $col(array($field('fm-enq-delivery', lang('Delivery Window'), $select('fm-enq-delivery', 'delivery', '', $delivery_choices, false, $delivery_choices[3]))), '4'),
        ), '3'),
        $field('fm-enq-details', lang('Your film'), $textarea('fm-enq-details', 'details', 5), 'mb-3',
            lang('A few lines are enough: the story or the message, who should watch it and anything already decided, such as a location or a launch day.')),
        $field('fm-enq-brief', lang('Brief or References'), $file('fm-enq-brief', 'brief', 'private'), 'mb-3',
            lang('A brief, a script, a mood board or reference pictures (PDF, Word or a picture). More can follow under your enquiry.')),
    ),
    'status_field' => $field('fm-enq-status', lang('Status'), $select('fm-enq-status', 'status', '', $enquiry_statuses, false, $enquiry_statuses[0], array('office_use_only' => true))),
    'consent'      => lang('I agree that the studio keeps these details to answer my enquiry and prepare a treatment, as its privacy notice explains.'),
    'submit'       => lang('Send the Enquiry'),
    'submit_note'  => lang('A producer calls you back within a day.'),

    'missing'       => lang('This project enquiry could not be found, or it is not yours to see.'),
    'missing_icon'  => 'bi-file-earmark-x',
    'missing_title' => lang('Project enquiry not found'),
    'sent'          => lang('Thank you, your project enquiry has reached us. A producer calls you back within a day and sends a written treatment within a week; this page shows the status, and you can write to us below.'),
    'title_sample'  => $sample_service,
    'title_field'   => 'service',
    'title_label'   => lang('Service Type'),
    'status_sample' => $enquiry_statuses[0],
    'details'       => array_merge(
        $detail(lang('Purpose of the Film'), '', lang('Purpose of the Film'), $bound($sample_purpose, 'purpose')),
        $detail(lang('Where It Will Be Shown'), '', lang('Where It Will Be Shown'), $bound($sample_screen, 'screen')),
        $detail(lang('Running Time'), '', lang('Running Time'), $bound($sample_length, 'length')),
        $detail(lang('Budget'), '', lang('Budget'), $bound($sample_budget, 'budget')),
        $detail(lang('Delivery Window'), '', lang('Delivery Window'), $bound($sample_delivery, 'delivery')),
        $detail(lang('Client'), '', lang('Client'), $fm_sender_name(), true),
        $detail(lang('Company'), '', lang('Company'), $bound($sample_company, 'company')),
        $detail(lang('Contact by'), '', lang('Contact by'), $bound($contact_choices[0][1], 'contact_method')),
        $detail(lang('Brief or References'), 'text-break', lang('Brief or References'), array(
            $link('brief.pdf', '#', '', array(), array('target' => '_blank', 'rel' => 'noopener',
                '_bindings' => array('href' => 'brief', 'text' => 'brief'))),
        ), true),
        $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
    ),
    'words_label'    => lang('Your film'),
    'words'          => $sample_details,
    'words_field'    => 'details',
    'note'           => lang('A script, references or a question? Write to us below; we answer here and by e-mail.'),
    'messages_empty' => lang('No messages yet. The time of our call and the treatment come here; you can write to us about your film too.'),
    'row_items'      => array(
        array('bi-display', lang('Where It Will Be Shown'), $sample_screen, 'screen'),
        array('bi-stopwatch', lang('Running Time'), $sample_length, 'length'),
        array('bi-calendar-check', lang('Delivery Window'), $sample_delivery, 'delivery'),
    ),
    'mine_empty'   => lang('No project enquiries yet. Tell us what your film must do, and a producer calls you back within a day.'),
    'staff_empty'  => lang('No project enquiries yet.'),
    'search'       => array('first_name', 'last_name', 'email', 'company', 'service', 'screen', 'status'),
    'search_label' => lang('Search by client, company, service, screen or status...'),

    'mail_missing'       => lang('This project enquiry was not found.'),
    'mail_lines'         => $mail_lines(array(
        array(lang('Service Type'), $sample_service, 'service'),
        array(lang('Purpose of the Film'), $sample_purpose, 'purpose'),
        array(lang('Where It Will Be Shown'), $sample_screen, 'screen'),
        array(lang('Running Time'), $sample_length, 'length'),
        array(lang('Budget'), $sample_budget, 'budget'),
        array(lang('Delivery Window'), $sample_delivery, 'delivery'),
    ), lang('Project Enquiry')),
    'mail_new_heading'   => lang('A new project enquiry'),
    'mail_new_text'      => lang('It came from the enquiry form. Call the client within a day, write the time of the call under the enquiry on its page and set the status; what you write there reaches the client by e-mail.'),
    'mail_received_text' => lang('Thank you. A producer reads your enquiry and calls you back within a day; within a week you receive a written treatment with the idea, the look and a first budget, on the enquiry\'s page and by e-mail.'),
    'mail_received_more' => lang('A script, references or a question? Write to us on the enquiry\'s page; everything about your film stays there.'),
    'mail_open'          => lang('Open the enquiry'),
    'mail_open_mine'     => lang('Open my enquiry'),
);

// ── Studio hire requests ────────────────────────────────────────────────

$studio_hire_request = array(
    'key'   => 'studio_hire',
    'view'  => 'studio_hire_view',
    'mine'  => 'my_studio_hires',
    'staff' => 'staff_studio_hires',
    'mail'  => 'studio_hire',
    'slug'  => lang('hire-the-studio'),
    'view_slug'          => lang('studio-hire-request'),
    'mine_slug'          => lang('my-studio-hire-requests'),
    'staff_slug'         => lang('all-studio-hire-requests'),
    'mail_new_slug'      => lang('email-new-studio-hire-request'),
    'mail_received_slug' => lang('email-studio-hire-request-received'),
    'title' => lang('Hire the Studio'),
    'meta'  => lang('Hire our stages, the cyclorama, an edit suite or the grading and sound suite: choose the space and the day, and we confirm within a working day.'),
    'lead'  => lang('Our stages and suites are open to other crews too. Choose the space and the day, tell us about the shoot, and we confirm within one working day.'),
    'aside' => $fm_form_aside(array(
        lang('Your request opens its own page, and an account for you if you have none.'),
        lang('We check the day and confirm it, or offer the nearest free one, within one working day.'),
        lang('On the day our technician opens the space and shows you the kit.'),
    ), lang('With every space'), array(
        lang('Power, blackout and the lighting grid'),
        lang('A technician on the day'),
        lang('A make-up room and a kitchen for the crew'),
    )),
    'form_name'       => lang('Studio Hire Requests'),
    'confirmation'    => lang('Thank you, your studio hire request has reached us. We confirm the day within one working day.'),
    'notify_subject'  => lang('New studio hire request: ^^space^^'),
    'confirm_subject' => lang('We received your studio hire request'),
    'view_title'      => lang('Studio Hire Request'),
    'message_subject' => lang('There is a new message about your studio hire request'),
    'mine_title'      => lang('My Studio Hire Requests'),
    'mine_lead'       => lang('Your studio hire requests, the days we confirmed and our answers, newest first.'),
    'more_title'      => lang('Another shoot?'),
    'more_text'       => lang('Ask for another day in the studio; your details are filled in for you.'),
    'staff_lead'      => lang('Every studio hire request, newest first: confirm the day or offer another under each, then set the status.'),
    'mail_new_title'      => lang('New Studio Hire Request E-mail'),
    'mail_received_title' => lang('Studio Hire Request Received E-mail'),

    'prefix' => 'fm-hire',
    'step'   => lang('The shoot'),
    'fields' => array(
        $field('fm-hire-space', lang('Studio Space'), $select('fm-hire-space', 'space', lang('Choose a space…'), $space_titles, true, '', array('url_param' => 'space'))),
        $row(array(
            $col(array($field('fm-hire-date', lang('Preferred Date'), $input('fm-hire-date', 'date', 'preferred_date', 'off', true, array('rules' => array('min_date' => 'today'))), 'mb-3',
                lang('We confirm the day, or offer the nearest free one.'))), '6'),
            $col(array($field('fm-hire-length', lang('Hire Length'), $select('fm-hire-length', 'hire_length', '', $hire_lengths, true, $hire_lengths[1]))), '6'),
        ), '3'),
        $row(array(
            $col(array($field('fm-hire-crew', lang('Crew Size'), $input('fm-hire-crew', 'number', 'crew_size', 'off', false, array('kind' => 'number'), array($attr('min', '1'), $attr('step', '1'))), 'mb-3',
                lang('Everybody who comes on the day, the cast included.'))), '6'),
            $col(array($field('fm-hire-kit', lang('Kit Needed'), $input('fm-hire-kit', 'text', 'kit', 'off', false), 'mb-3',
                lang('In words; leave it empty when you bring your own.'))), '6'),
        ), '3'),
        $field('fm-hire-notes', lang('About the Shoot'), $textarea('fm-hire-notes', 'notes', 4), 'mb-3',
            lang('What you will shoot or work on, and anything the space should be ready for.')),
    ),
    'status_field' => $field('fm-hire-status', lang('Status'), $select('fm-hire-status', 'status', '', $hire_statuses, false, $hire_statuses[0], array('office_use_only' => true))),
    'consent'      => lang('I agree that the studio keeps these details to answer my studio hire request, as its privacy notice explains.'),
    'submit'       => lang('Send the Request'),
    'submit_note'  => lang('We confirm the day within one working day.'),

    'missing'       => lang('This studio hire request could not be found, or it is not yours to see.'),
    'missing_icon'  => 'bi-calendar-x',
    'missing_title' => lang('Studio hire request not found'),
    'sent'          => lang('Thank you, your studio hire request has reached us. We confirm the day within one working day; this page shows the status, and you can write to us below.'),
    'title_sample'  => $sample_space,
    'title_field'   => 'space',
    'title_label'   => lang('Studio Space'),
    'status_sample' => $hire_statuses[0],
    'details'       => array_merge(
        $detail(lang('Preferred Date'), '', lang('Preferred Date'), $bound($sample_day, 'preferred_date')),
        $detail(lang('Hire Length'), '', lang('Hire Length'), $bound($sample_hire_length, 'hire_length')),
        $detail(lang('Crew Size'), '', lang('Crew Size'), $bound($sample_crew, 'crew_size')),
        $detail(lang('Kit Needed'), '', lang('Kit Needed'), $bound($sample_kit, 'kit')),
        $detail(lang('Client'), '', lang('Client'), $fm_sender_name(), true),
        $detail(lang('Company'), '', lang('Company'), $bound($sample_company, 'company')),
        $detail(lang('Contact by'), '', lang('Contact by'), $bound($contact_choices[0][1], 'contact_method')),
        $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
    ),
    'words_label'    => lang('About the Shoot'),
    'words'          => $sample_notes,
    'words_field'    => 'notes',
    'note'           => lang('Another day that suits you, or a question about the kit? Write to us below; we answer here and by e-mail.'),
    'messages_empty' => lang('No messages yet. Our confirmation of the day comes here; you can write to us about your shoot too.'),
    'row_items'      => array(
        array('bi-calendar-event', lang('Preferred Date'), $sample_day, 'preferred_date'),
        array('bi-clock', lang('Hire Length'), $sample_hire_length, 'hire_length'),
        array('bi-people', lang('Crew Size'), $sample_crew, 'crew_size'),
    ),
    'mine_empty'   => lang('No studio hire requests yet. Choose a space and a day, and we confirm within one working day.'),
    'staff_empty'  => lang('No studio hire requests yet.'),
    'search'       => array('first_name', 'last_name', 'email', 'company', 'space', 'hire_length', 'status'),
    'search_label' => lang('Search by client, company, space, length or status...'),

    'mail_missing'       => lang('This studio hire request was not found.'),
    'mail_lines'         => $mail_lines(array(
        array(lang('Studio Space'), $sample_space, 'space'),
        array(lang('Preferred Date'), $sample_day, 'preferred_date'),
        array(lang('Hire Length'), $sample_hire_length, 'hire_length'),
        array(lang('Crew Size'), $sample_crew, 'crew_size'),
        array(lang('Kit Needed'), $sample_kit, 'kit'),
    ), lang('Studio Hire Request')),
    'mail_new_heading'   => lang('A new studio hire request'),
    'mail_new_text'      => lang('It came from the studio hire form. Check the day, confirm it or offer another under the request on its page and set the status; what you write there reaches the sender by e-mail.'),
    'mail_received_text' => lang('Thank you. We check the day and confirm it, or offer the nearest free one, within one working day, on the request\'s page and by e-mail.'),
    'mail_received_more' => lang('Another day or a question about the kit? Write to us on the request\'s page; everything about your shoot stays there.'),
    'mail_open'          => lang('Open the request'),
    'mail_open_mine'     => lang('Open my request'),
);

// ── Pages ───────────────────────────────────────────────────────────────

$enquiry_pages = array_merge($fm_request_pages($enquiry_request), $fm_request_pages($studio_hire_request));

// The privacy notice the consent boxes and the footer link to: sample text
// with a note to adapt it.
$enquiry_pages[] = array(
    'key'              => 'privacy',
    'name'             => lang('privacy-policy'),
    'folder'           => 'public',
    'title'            => lang('Privacy Policy'),
    'meta_description' => lang('What the studio keeps about you when you send a project enquiry, ask to hire the studio or apply for a crew call, and why.'),
    'tree'             => $page(array(
        $page_header(lang('Privacy Policy'), lang('What the studio keeps about you when you send a project enquiry, ask to hire the studio or apply for a crew call, and why.')),
        $el('section', 'py-5', lang('Your Data'), array(
            $container(array($row(array($col(array(
                $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Sample Text Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Sample text: adapt it to your company and to the data protection law that applies to you before you publish.'), '', lang('Text')),
                ), array('_attrs' => array($attr('role', 'note')))),
                $heading('h2', lang('Your data'), 'pfm-display h4 mb-3'),
                $para(lang('When you send a project enquiry, ask to hire the studio, apply for a crew call, join the newsletter or open an account, we keep your name, your contact details, what you tell us about your film or your shoot and the briefs, references, showreel or CV you send, to answer you, prepare a treatment or consider your application.'), 'mb-3'),
                $para(lang('We pass to the freelance crew and suppliers we work with only what they need for your production. We keep the rest as long as the law requires, never sell it and never give it to advertisers. You can see and change your account data from My Account, and ask us about the rest through the contact page.'), 'mb-0'),
            ), '', '8'))))),
        ), $searchable('fm-your-data', lang('privacy, personal data, KVKK, GDPR, delete my data'))),
    )),
);

$enquiry_pages = array_merge($enquiry_pages, $fm_request_mail_pages($enquiry_request), $fm_request_mail_pages($studio_hire_request));

// A new message or reply: sent to the client of a project enquiry or a
// studio hire request, the applicant of a crew call, the sender of a
// conversation and the visitors watching a page. Its Comments widget draws
// the comment in the card of mail.php; the System region after it keeps
// the fixed comment for an e-mail the widget does not draw.
$enquiry_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: your project enquiry, your studio hire request, your application, a conversation or a Journal article. You can read it below and answer it on the page.')),
        $widget('email_comment'),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));

// ── Widgets ─────────────────────────────────────────────────────────────

$enquiry_widgets = array_merge($fm_request_widgets($enquiry_request), $fm_request_widgets($studio_hire_request), array(
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
));

return array('pages' => $enquiry_pages, 'widgets' => $enquiry_widgets);
