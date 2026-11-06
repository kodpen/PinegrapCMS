<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Charity", the builders of its form records: a pledge
 * (donate.php) and a volunteer's offer (volunteer.php) are each a record of
 * their own form, sent from a page, followed on the record's page with its
 * status and messages, listed for the sender and for the staff, and e-mailed
 * to both. Included after parts.php and mail.php in the same scope; the
 * page files after it use these builders.
 *
 * A sender without an account gets one (auto-registration); the Status of
 * a record is an office-use-only field the staff change in the control
 * panel; the messages under it are the record page's comments.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// ── Page builders ───────────────────────────────────────────────────────

// The side column of a request page: what happens next, then a box that
// leads to the page answering the usual questions first.
$request_aside = function ($steps, $box_title, $box_text, $box_link_text, $box_key) use ($el, $heading, $para, $link) {
    $items = array();
    foreach ($steps as $n => $text) {
        $items[] = $el('li', $n < count($steps) - 1 ? 'mb-2' : '', lang('Step'), array(), array('text' => $text));
    }
    return array(
        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('How It Works'), array(
            $heading('h2', lang('What happens next'), 'h5 fw-semibold mb-3'),
            $el('ol', 'small text-body-secondary ps-3 mb-0', lang('List'), $items),
        )),
        $el('div', 'p-4 rounded-4 border', $box_title, array(
            $heading('h2', $box_title, 'h5 fw-semibold mb-2'),
            $para($box_text, 'small text-body-secondary mb-3'),
            $link($box_link_text, '{{page:' . $box_key . '}}', 'btn btn-sm btn-outline-primary rounded-pill px-3'),
        )),
    );
};

// A request form's page: the header, the form in a card, the side column.
$request_page = function ($title, $lead, $form_widget, $aside) use ($page, $page_header, $el, $container, $row, $col, $widget) {
    return $page(array(
        $page_header($title, $lead),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-4', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget($form_widget))),
                        )),
                    ), '', '8'),
                    $col($aside, '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false);
};

// A My list page: the header, the list, and a card that sends a new request.
$request_my_page = function ($title, $lead, $list_widget, $new_title, $new_text, $new_link_text, $new_key, $new_icon) use ($page, $page_header, $el, $container, $row, $col, $widget, $icon, $span, $para, $link) {
    return $page(array(
        $page_header($title, $lead),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget($list_widget)), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('New Request'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon($new_icon, 'text-primary'),
                                $span($new_title, '', lang('Text')),
                            )),
                            $para($new_text, 'small text-body-secondary mb-3'),
                            $link($new_link_text, '{{page:' . $new_key . '}}', 'btn btn-sm btn-primary rounded-pill px-3'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false);
};

// A staff list page.
$request_staff_page = function ($title, $lead, $list_widget) use ($staff_page, $page_header, $el, $container, $widget) {
    return $staff_page(array(
        $page_header($title, $lead),
        $el('section', 'py-5', lang('Content'), array(
            $container(array($widget($list_widget))),
        )),
    ));
};


// ── Widget pieces ───────────────────────────────────────────────────────

// The sender's name.
$request_person = function () use ($span, $bind) {
    return array(
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
    );
};

// A request in a list: its title field, the status, the details, linked to
// its page. $details: array(icon, label, sample, field) rows.
$request_row = function ($name, $title_field, $title_sample, $details, $staff) use ($el, $span, $icon, $bind, $request_person) {
    $items = array();
    foreach ($details as $d) {
        $items[] = $el('span', 'd-inline-flex align-items-center gap-1', $d[1], array($icon($d[0]), $span($d[2], '', $d[1], $bind($d[3]))));
    }
    $items[] = $el('span', 'd-inline-flex align-items-center gap-1', lang('Sent'), array($icon('bi-calendar3'), $span(lang('Date'), '', lang('Date'), $bind('submitted_date_and_time'))));
    if ($staff) {
        $items[] = $el('span', 'd-inline-flex align-items-center gap-1', lang('Phone'), array($icon('bi-telephone'), $span('+90 555 000 00 00', '', lang('Phone'), $bind('phone'))));
        $title = $el('span', 'fw-semibold', lang('Sender'), array_merge($request_person(), array(
            $span(' · ', 'text-body-secondary', lang('Text')),
            $span($title_sample, 'text-body-secondary fw-normal', $name, $bind($title_field)),
        )));
    } else {
        $items[] = $el('span', 'd-inline-flex align-items-center gap-1', lang('Messages'), array($icon('bi-chat-left-text'), $span('0', '', lang('Reply Count'), $bind('number_of_comments'))));
        $title = $span($title_sample, 'fw-semibold', $name, $bind($title_field));
    }
    return $el('a', 'list-group-item list-group-item-action p-3', $name, array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $title,
            $span(lang('Received'), 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
        )),
        $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), $items),
    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view')));
};

// A detail of a record's card: the label, then the value. $value is either
// the value's props (its sample text and binding) or, with $children, the
// nodes it is made of.
$request_detail = function ($label, $class, $name, $value, $children = false) use ($el) {
    return array(
        $el('dt', 'col-sm-4 fw-normal text-body-secondary', lang('Label'), array(), array('text' => $label)),
        $el('dd', trim('col-sm-8 ' . $class), $name, $children ? $value : array(), $children ? array() : $value),
    );
};
$request_bound = function ($sample, $field) use ($bind) {
    return array_merge(array('text' => $sample), $bind($field));
};
// A file the record carries, as a link that opens it.
$request_file_link = function ($field, $sample) use ($link) {
    return array($link($sample, '#', '', array(), array('target' => '_blank', 'rel' => 'noopener',
        '_bindings' => array('href' => $field, 'text' => $field))));
};

// A record's page (a pledge, a volunteer's offer): the not-found notice,
// the sent notice, the new account notice, the way back and the Edit
// button, then the record's own parts.
$request_record_view = function ($icon_name, $missing_title, $missing_text, $back_key, $back_text, $sent_text, $parts) use ($el, $heading, $para, $link, $span, $icon, $attr, $bind, $show_when, $container, $row, $col, $edit_button) {
    return array('type' => 'root', 'props' => array(), 'children' => array(
        $container(array(
            $row(array(
                $col(array(
                    $el('div', 'text-center py-5', lang('Not Found'), array(
                        $icon($icon_name, 'display-5 text-body-tertiary'),
                        $heading('h2', $missing_title, 'h3 fw-semibold mt-3'),
                        $para($missing_text, 'text-body-secondary mb-4', $bind('not_found')),
                        $link($back_text, '{{page:' . $back_key . '}}', 'btn btn-primary rounded-pill px-4'),
                    ), $show_when('record_not_found')),
                    $el('div', '', lang('Record'), array_merge(array(
                        $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
                            $icon('bi-check-circle', 'mt-1'),
                            $span($sent_text, '', lang('Text')),
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
                    ), $parts), $show_when('record_found')),
                ), '', '8'),
            ), '', 'center'),
        ), 'py-5'),
    ));
};

// The heading of a record's card: the kind, the title, the status.
$request_record_title = function ($kind, $sample, $field) use ($el, $heading, $para, $span, $bind) {
    return $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
        $el('div', '', lang('Words'), array(
            $para($kind, 'pch-eyebrow small text-uppercase fw-semibold text-primary mb-1'),
            $heading('h1', $sample, 'h3 fw-semibold mb-0', $bind($field)),
        )),
        $span(lang('Received'), 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
    ));
};
// A long text the record carries, in a soft box that leaves when empty.
$request_record_text = function ($label, $field, $sample) use ($el, $para, $bind) {
    return $el('div', 'pg-hide-if-empty p-4 rounded-4 bg-body-tertiary mb-4', $label, array(
        $para($label, 'small fw-semibold text-body-secondary mb-2'),
        $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $sample), $bind($field))),
    ));
};
// The note under a record's card: where to write.
$request_record_note = function ($text) use ($el, $icon, $span) {
    return $el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
        $icon('bi-chat-left-text', 'text-primary mt-1'),
        $span($text, '', lang('Text')),
    ));
};

// The messages under a request: the page's own Comments widget, shown only
// to whoever may read the request above it. Oldest first, all on one page,
// with a file each. No Reply, Share or follow box: the sender is e-mailed
// every answer and the charity every message.
$request_messages = function ($page_key, $empty, $heading_text, $sign_in) use ($comment_thread) {
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
            'heading'   => $heading_text,
            'label'     => lang('Your message'),
            'sign_in'   => $sign_in,
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

// A list of requests: the sender's own (the staff see them all), or the
// staff's with a search.
$request_list = function ($page_key, $form_key, $view_key, $row_node, $empty, $search_fields = array(), $search_label = '') use ($widget_root, $el, $loop) {
    $config = array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:' . $form_key . '}}',
        'detail_page_id'      => '{{tab:' . $view_key . '}}',
        'items_per_page'      => 20,
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
    );
    if ($search_fields) {
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
            $el('div', 'list-group', lang('Request List'), array($loop(array($row_node)))),
        )),
    );
};

// The contact part of both forms: the name, the e-mail and the phone, the
// status (the staff's), the consent with the privacy link and the send
// button.
$request_contact = function ($prefix, $statuses, $consent, $send, $promise) use ($el, $row, $col, $field, $input, $select, $agree, $captcha, $submit, $para) {
    return array(
        $row(array(
            $col(array($field($prefix . '-first-name', lang('First Name'), $input($prefix . '-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
            $col(array($field($prefix . '-last-name', lang('Last Name'), $input($prefix . '-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
            $col(array($field($prefix . '-email', lang('Email'), $input($prefix . '-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
            $col(array($field($prefix . '-phone', lang('Phone'), $input($prefix . '-phone', 'tel', 'phone', 'tel', false, array('contact_field' => 'mobile_phone')))), '6'),
        ), '3'),
        $field($prefix . '-status', lang('Status'), $select($prefix . '-status', 'status', '', $statuses, false, $statuses[0], array('office_use_only' => true))),
        $agree($prefix . '-consent', 'consent', $consent, true),
        $captcha(),
        $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
            $submit($send, 'btn btn-primary btn-lg rounded-pill px-4'),
            $para($promise, 'small text-body-secondary mb-0'),
        )),
    );
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
    ), array_merge($mail_style('border-top:1px solid #a7f3d0;padding-top:16px'), $show_when('has_new_account')));
};
// The sender's lines of a staff e-mail.
$mail_sender = function ($more = array()) use ($mail_box, $mail_line, $span, $bind, $request_person) {
    return $mail_box(array_merge(array(
        $mail_line(lang('Sender'), $request_person()),
        $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
        $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
    ), $more), lang('Sender'));
};
// The form detail settings of an e-mail page's widget (public: the mail is
// drawn for the submission just sent) and of a request page's widget (its
// sender only, and the staff).
$request_detail_config = function ($page, $form, $missing, $access) {
    return array(
        'page'   => $page,
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:' . $form . '}}',
            'access_control'      => $access,
            'not_found_message'   => $missing,
        ),
    );
};
