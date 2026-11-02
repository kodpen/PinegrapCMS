<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Real Estate Agency", its requests: the viewing request,
 * the viewing's own page with its messages, My Viewings and the staff's
 * list; the valuation request of an owner who sells or lets, its page, My
 * Valuations and the staff's list; the privacy page the consent boxes link,
 * the e-mails of both forms and the new message e-mail, and the widgets
 * that draw them. Included after parts.php, mail.php and listings.php in
 * the same scope; returns array('pages' => ..., 'widgets' => ...).
 *
 * A request is a record of its form (Viewings, Valuations), as a
 * consultation is in the Law Firm template. A visitor without an account
 * gets one (auto-registration), so the request's page and the My lists
 * show it at once. Its Status is an office-use-only field the staff change
 * in the control panel; the messages under it are the page's comments,
 * mailed to the client and to the agency.
 *
 * The viewing form's Listing starts with the home the listing page sends
 * (?listing=, the listing's title); the valuation form's Neighbourhood and
 * Agent start with the neighbourhood and agent pages' choices (?area=,
 * ?agent=). The photos and papers an owner sends are kept in the
 * template's private folder: the record's page links them for whoever may
 * read the record.
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

// The statuses, in the order a request moves through them.
$viewing_statuses = array(lang('Received'), lang('Viewing confirmed'), lang('Viewed'), lang('Cancelled'));
$valuation_statuses = array(lang('Received'), lang('Visit arranged'), lang('Valuation sent'), lang('Instructed'), lang('Closed'));

// What an owner asks the valuation for: each answer's value is its words.
$purposes = array(
    array(lang('To sell'), lang('To sell')),
    array(lang('To let'), lang('To let')),
);

// The side column of a request page: what happens next, then the hours.
$request_aside = function ($steps) use ($el, $heading, $hours) {
    $items = array();
    foreach ($steps as $n => $text) {
        $items[] = $el('li', $n < count($steps) - 1 ? 'mb-2' : '', lang('Step'), array(), array('text' => $text));
    }
    return array(
        $el('div', 'p-4 rounded-3 bg-body-tertiary mb-4', lang('How It Works'), array(
            $heading('h2', lang('What happens next'), 'h5 fw-semibold mb-3'),
            $el('ol', 'small text-body-secondary ps-3 mb-0', lang('List'), $items),
        )),
        $el('div', 'p-4 rounded-3 border', lang('Office hours'), array(
            $heading('h2', lang('Office hours'), 'h5 fw-semibold mb-3'),
            $hours(),
        )),
    );
};

// A request form's page: the header, the form in a card, the side column.
$request_page = function ($title, $lead, $form_widget, $steps) use ($page, $page_header, $el, $container, $row, $col, $widget, $request_aside) {
    return $page(array(
        $page_header($title, $lead),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-3', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget($form_widget))),
                        )),
                    ), '', '8'),
                    $col($request_aside($steps), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false);
};

// A My list page: the header, the list, and a card that sends a new request.
$my_page = function ($title, $lead, $list_widget, $new_title, $new_text, $new_link_text, $new_key, $new_icon) use ($page, $page_header, $el, $container, $row, $col, $widget, $icon, $span, $para, $link) {
    return $page(array(
        $page_header($title, $lead),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget($list_widget)), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-3 bg-body-tertiary', lang('New Request'), array(
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
$staff_list_page = function ($title, $lead, $list_widget) use ($staff_page, $page_header, $el, $container, $widget) {
    return $staff_page(array(
        $page_header($title, $lead),
        $el('section', 'py-5', lang('Content'), array(
            $container(array($widget($list_widget))),
        )),
    ));
};

// ── Pages ───────────────────────────────────────────────────────────────

// The viewing request. What is sent opens the viewing's own page.
$request_pages[] = array(
    'key'              => 'viewing',
    'name'             => lang('book-a-viewing'),
    'folder'           => 'public',
    'title'            => lang('Book a Viewing'),
    'meta_description' => lang('Ask to see a home: choose a day and a time of day, and an agent confirms within a working day.'),
    'form'             => array(
        'form_name'            => lang('Viewings'),
        'confirmation_message' => lang('Thank you, your viewing request has reached us. An agent confirms the time within a working day.'),
        'confirmation_page_id' => '{{tab:viewing_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New viewing request: ^^listing^^'),
        'notify_page_id'       => '{{tab:email_viewing_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your viewing request'),
        'confirm_page_id'      => '{{tab:email_viewing_received}}',
    ),
    'tree'             => $request_page(lang('Book a Viewing'),
        lang('Choose a day and a time of day. An agent confirms within a working day and meets you at the door.'),
        'viewing_form', array(
            lang('Your request opens its own page, and an account for you if you have none.'),
            lang('An agent confirms the day and time, or proposes another.'),
            lang('Questions before the viewing? Write under your request.'),
        )),
);

// One viewing: the request, its status and the messages under it.
$request_pages[] = array(
    'key'      => 'viewing_view',
    'name'     => lang('viewing'),
    'folder'   => 'registration',
    'title'    => lang('Viewing'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                        'attachments' => true,
                        'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new message about your viewing'),
                        'notify_email' => '{{site_email}}'),
    'tree'     => $page(array(
        $widget('viewing_detail'),
        $comments_section(lang('Messages'), 'viewing_messages'),
    ), false),
);

// My Viewings: the client's viewing requests (for the staff, everybody's).
$request_pages[] = array(
    'key'     => 'my_viewings',
    'name'    => lang('my-viewings'),
    'folder'  => 'registration',
    'title'   => lang('My Viewings'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $my_page(lang('My Viewings'), lang('Your viewing requests, their status and your agent\'s messages, newest first.'),
        'my_viewings_list', lang('Another home?'), lang('Open its listing and press "Ask for a viewing"; your details are filled in for you.'),
        lang('See the listings'), 'listings', 'bi-house-add'),
);

// The staff's list of every viewing request, newest first, with a search.
$request_pages[] = array(
    'key'     => 'staff_viewings',
    'name'    => lang('all-viewings'),
    'folder'  => 'private',
    'title'   => lang('All Viewings'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $staff_list_page(lang('All Viewings'), lang('Every viewing request, newest first: confirm the time, set the status and answer under each.'), 'staff_viewings_list'),
);

// The valuation request of an owner who sells or lets.
$request_pages[] = array(
    'key'              => 'valuation',
    'name'             => lang('free-valuation'),
    'folder'           => 'public',
    'title'            => lang('Free Valuation'),
    'meta_description' => lang('What is your home worth? Tell us about it; an agent visits within two working days and sends a price in writing, free of charge.'),
    'form'             => array(
        'form_name'            => lang('Valuations'),
        'confirmation_message' => lang('Thank you, your valuation request has reached us. An agent calls you within a working day to arrange the visit.'),
        'confirmation_page_id' => '{{tab:valuation_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New valuation request: ^^area^^'),
        'notify_page_id'       => '{{tab:email_valuation_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your valuation request'),
        'confirm_page_id'      => '{{tab:email_valuation_received}}',
    ),
    'tree'             => $request_page(lang('Free Valuation'),
        lang('Selling or letting? Tell us about your home. An agent visits within two working days and sends you a price in writing.'),
        'valuation_form', array(
            lang('Your request opens its own page, and an account for you if you have none.'),
            lang('An agent calls you to arrange the visit, then measures and photographs the rooms.'),
            lang('You get the price in writing on your request\'s page, with the recent sales it is based on.'),
        )),
);

// One valuation: the request, its status and the messages under it.
$request_pages[] = array(
    'key'      => 'valuation_view',
    'name'     => lang('valuation'),
    'folder'   => 'registration',
    'title'    => lang('Valuation'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                        'attachments' => true,
                        'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new message about your valuation'),
                        'notify_email' => '{{site_email}}'),
    'tree'     => $page(array(
        $widget('valuation_detail'),
        $comments_section(lang('Messages'), 'valuation_messages'),
    ), false),
);

// My Valuations: the owner's valuation requests (for the staff, everybody's).
$request_pages[] = array(
    'key'     => 'my_valuations',
    'name'    => lang('my-valuations'),
    'folder'  => 'registration',
    'title'   => lang('My Valuations'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $my_page(lang('My Valuations'), lang('Your valuation requests, their status and your agent\'s messages, newest first.'),
        'my_valuations_list', lang('Another property?'), lang('Ask for a valuation of it; your details are filled in for you.'),
        lang('Free Valuation'), 'valuation', 'bi-house-check'),
);

// The staff's list of every valuation request, newest first, with a search.
$request_pages[] = array(
    'key'     => 'staff_valuations',
    'name'    => lang('all-valuations'),
    'folder'  => 'private',
    'title'   => lang('All Valuations'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $staff_list_page(lang('All Valuations'), lang('Every valuation request, newest first: arrange the visit, set the status and answer under each.'), 'staff_valuations_list'),
);

// The privacy notice the consent boxes link to: sample text with a note to
// adapt it.
$request_pages[] = array(
    'key'              => 'privacy',
    'name'             => lang('privacy-policy'),
    'folder'           => 'public',
    'title'            => lang('Privacy Policy'),
    'meta_description' => lang('What the agency keeps about you, why, and what you can ask of us.'),
    'tree'             => $page(array(
        $page_header(lang('Privacy Policy'), lang('What the agency keeps about you, why, and what you can ask of us.')),
        $el('section', 'py-5', lang('Your Data'), array(
            $container(array($row(array($col(array(
                $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Sample Text Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Sample text: adapt it to your agency and to the data protection law that applies to you before you publish.'), '', lang('Text')),
                ), array('_attrs' => array($attr('role', 'note')))),
                $heading('h2', lang('Your data'), 'h4 fw-semibold mb-3'),
                $para(lang('When you ask for a viewing or a valuation, sign up for listing alerts or open an account, we keep your name, your contact details, what you tell us about the home and the photos and papers you send, to arrange the viewing or the valuation and to answer you.'), 'mb-3'),
                $para(lang('We keep it as long as the law requires, never sell it and never give it to advertisers. An owner\'s details reach a buyer only with the owner\'s agreement. You can see and change your account data from My Account, and ask us about the rest through the contact page.'), 'mb-0'),
            ), '', '8'))))),
        ), $searchable('your-data', lang('privacy, personal data, KVKK, GDPR, delete my data'))),
    )),
);

// The e-mails: what the staff receive and the reply to the sender, for each
// form, and the new message e-mail.
$request_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_viewing_new',
    'name'  => lang('email-new-viewing'),
    'title' => lang('New Viewing E-mail'),
    'tree'  => $mail_page(array($widget('email_viewing_admin'))),
));
$request_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_viewing_received',
    'name'  => lang('email-viewing-received'),
    'title' => lang('Viewing Received E-mail'),
    'tree'  => $mail_page(array($widget('email_viewing_client'))),
));
$request_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_valuation_new',
    'name'  => lang('email-new-valuation'),
    'title' => lang('New Valuation E-mail'),
    'tree'  => $mail_page(array($widget('email_valuation_admin'))),
));
$request_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_valuation_received',
    'name'  => lang('email-valuation-received'),
    'title' => lang('Valuation Received E-mail'),
    'tree'  => $mail_page(array($widget('email_valuation_client'))),
));
// A new message or reply: sent to the client of a viewing or a valuation,
// the sender of a conversation and the visitors watching a page. Its
// Comments widget draws the comment in the card of mail.php; the System
// region after it keeps the fixed comment for an e-mail the widget does
// not draw.
$request_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: your viewing, your valuation, a conversation or an article. You can read it below and answer it on the page.')),
        $widget('email_comment'),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));

// ── Widget pieces ───────────────────────────────────────────────────────

// The client's name.
$person_name = function () use ($span, $bind) {
    return array(
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
    );
};

// A request in a list: its title field, the status, the details, linked to
// its page. $details: array(icon, label, sample, field) rows.
$request_row = function ($name, $title_field, $title_sample, $details, $staff) use ($el, $span, $icon, $bind, $person_name) {
    $items = array();
    foreach ($details as $d) {
        $items[] = $el('span', 'd-inline-flex align-items-center gap-1', $d[1], array($icon($d[0]), $span($d[2], '', $d[1], $bind($d[3]))));
    }
    $items[] = $el('span', 'd-inline-flex align-items-center gap-1', lang('Sent'), array($icon('bi-calendar3'), $span(lang('Date'), '', lang('Date'), $bind('submitted_date_and_time'))));
    if ($staff) {
        $items[] = $el('span', 'd-inline-flex align-items-center gap-1', lang('Phone'), array($icon('bi-telephone'), $span('+90 555 000 00 00', '', lang('Phone'), $bind('phone'))));
        $title = $el('span', 'fw-semibold', lang('Client'), array_merge($person_name(), array(
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
$sample_listing = $listings['seaview']['title'];
$viewing_row = function ($staff = false) use ($request_row, $sample_listing, $time_windows) {
    return $request_row(lang('Viewing'), 'listing', $sample_listing, array(
        array('bi-calendar-event', lang('Day'), '2026-12-05', 'viewing_date'),
        array('bi-clock', lang('Time of day'), $time_windows[0], 'preferred_time'),
    ), $staff);
};
$valuation_row = function ($staff = false) use ($request_row, $area_titles, $type_titles, $agent_choices) {
    return $request_row(lang('Valuation'), 'area', $area_titles[0], array(
        array('bi-house', lang('Property type'), $type_titles[0], 'property_type'),
        array('bi-tag', lang('Valuation for'), lang('To sell'), 'purpose'),
        array('bi-person', lang('Agent'), $agent_choices[0], 'agent'),
    ), $staff);
};

// A detail of a record's card: the label, then the value. $value is either
// the value's props (its sample text and binding) or, with $children, the
// nodes it is made of.
$detail = function ($label, $class, $name, $value, $children = false) use ($el) {
    return array(
        $el('dt', 'col-sm-4 fw-normal text-body-secondary', lang('Label'), array(), array('text' => $label)),
        $el('dd', trim('col-sm-8 ' . $class), $name, $children ? $value : array(), $children ? array() : $value),
    );
};
$bound = function ($sample, $field) use ($bind) {
    return array_merge(array('text' => $sample), $bind($field));
};
// A file the record carries, as a link that opens it.
$file_link = function ($field, $sample) use ($link) {
    return array($link($sample, '#', '', array(), array('target' => '_blank', 'rel' => 'noopener',
        '_bindings' => array('href' => $field, 'text' => $field))));
};

// A record's page (a viewing, a valuation): the not-found notice, the sent
// notice, the new account notice, the way back and the Edit button, then
// the record's own parts.
$record_view = function ($icon_name, $missing_title, $missing_text, $back_key, $back_text, $sent_text, $parts) use ($el, $heading, $para, $link, $span, $icon, $attr, $bind, $show_when, $container, $row, $col, $edit_button) {
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
$record_title = function ($kind, $sample, $field) use ($el, $heading, $para, $span, $bind) {
    return $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
        $el('div', '', lang('Words'), array(
            $para($kind, 'small text-uppercase fw-semibold text-primary mb-1'),
            $heading('h1', $sample, 'h3 fw-semibold mb-0', $bind($field)),
        )),
        $span(lang('Received'), 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
    ));
};
// A long text the record carries, in a soft box that leaves when empty.
$record_text = function ($label, $field, $sample) use ($el, $para, $bind) {
    return $el('div', 'pg-hide-if-empty p-4 rounded-3 bg-body-tertiary mb-4', $label, array(
        $para($label, 'small fw-semibold text-body-secondary mb-2'),
        $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $sample), $bind($field))),
    ));
};
// The note under a record's card: where to write.
$record_note = function ($text) use ($el, $icon, $span) {
    return $el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
        $icon('bi-chat-left-text', 'text-primary mt-1'),
        $span($text, '', lang('Text')),
    ));
};

// The messages under a request: the page's own Comments widget, shown only
// to whoever may read the request above it. Oldest first, all on one page,
// with a file each. No Reply, Share or follow box: the client is e-mailed
// every answer and the agency every message.
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

// A list of requests: the client's own (the staff see them all), or the
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

// The contact part of both forms: how to reach the client, the name, the
// e-mail and the phone, the status (the staff's), the consent and the send
// button.
$contact_part = function ($prefix, $statuses, $consent, $send, $promise) use ($el, $row, $col, $field, $input, $select, $radios, $agree, $captcha, $submit, $para, $contact_choices) {
    return array(
        $radios($prefix . '-contact', 'contact_method', lang('Best way to reach you'), $contact_choices, lang('Phone')),
        $row(array(
            $col(array($field($prefix . '-first-name', lang('First Name'), $input($prefix . '-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
            $col(array($field($prefix . '-last-name', lang('Last Name'), $input($prefix . '-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
            $col(array($field($prefix . '-email', lang('Email'), $input($prefix . '-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
            $col(array($field($prefix . '-phone', lang('Phone'), $input($prefix . '-phone', 'tel', 'phone', 'tel', true, array('contact_field' => 'mobile_phone')))), '6'),
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

// The lines of a viewing and of a valuation, for their e-mails.
$mail_viewing = function () use ($mail_box, $mail_line, $span, $bind, $sample_listing, $time_windows) {
    return $mail_box(array(
        $mail_line(lang('Listing'), array($span($sample_listing, '', lang('Listing'), $bind('listing')))),
        $mail_line(lang('Day'), array($span('2026-12-05', '', lang('Day'), $bind('viewing_date')))),
        $mail_line(lang('Time of day'), array($span($time_windows[0], '', lang('Time'), $bind('preferred_time')))),
        $mail_line(lang('Contact by'), array($span(lang('Phone'), '', lang('Contact by'), $bind('contact_method')))),
    ), lang('Viewing'));
};
$mail_valuation = function () use ($mail_box, $mail_line, $span, $bind, $area_titles, $type_titles, $agent_choices, $time_windows) {
    return $mail_box(array(
        $mail_line(lang('Valuation for'), array($span(lang('To sell'), '', lang('Valuation for'), $bind('purpose')))),
        $mail_line(lang('Neighbourhood'), array($span($area_titles[0], '', lang('Neighbourhood'), $bind('area')))),
        $mail_line(lang('Property type'), array($span($type_titles[0], '', lang('Property type'), $bind('property_type')))),
        $mail_line(lang('Agent'), array($span($agent_choices[0], '', lang('Agent'), $bind('agent')))),
        $mail_line(lang('Time of day'), array($span($time_windows[0], '', lang('Time'), $bind('preferred_time')))),
    ), lang('Valuation'));
};
$sample_viewing_note = lang('We would like to see the flat on a weekday evening, if possible with a look at the storage room.');
$sample_valuation_note = lang('A third-floor flat with a balcony, kitchen renovated in 2023. We are moving abroad next summer.');

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
    ), array_merge($mail_style('border-top:1px solid #ccfbf1;padding-top:16px'), $show_when('has_new_account')));
};
// The client's lines of a staff e-mail.
$mail_client = function ($more = array()) use ($mail_box, $mail_line, $span, $bind, $person_name) {
    return $mail_box(array_merge(array(
        $mail_line(lang('Client'), $person_name()),
        $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
        $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
    ), $more), lang('Client'));
};
// The form detail settings of an e-mail page's widget.
$mail_detail = function ($page, $form, $missing) {
    return array(
        'page'   => $page,
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:' . $form . '}}',
            'access_control'      => 'public',
            'not_found_message'   => $missing,
        ),
    );
};
// The form detail settings of a request page's widget.
$view_detail = function ($page, $form, $missing) {
    return array(
        'page'   => $page,
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:' . $form . '}}',
            'access_control'      => 'submitter_only',
            'not_found_message'   => $missing,
        ),
    );
};

$viewing_missing = lang('This viewing could not be found, or it is not yours to see.');
$valuation_missing = lang('This valuation could not be found, or it is not yours to see.');

$request_widgets = array(

    // The viewing request: the fields are the page's form. The Listing
    // starts with the home the listing page sends (?listing=). The Status
    // is the staff's (office use only): every request starts as Received.
    'viewing_form' => array(
        'page'   => 'viewing',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array_merge(array(
            $form_step(1, lang('The home and the time')),
            $field('re-vw-listing', lang('Listing'), $input('re-vw-listing', 'text', 'listing', 'off', true, array('url_param' => 'listing')), 'mb-3',
                lang('The home you would like to see; the listing\'s button fills it in.')),
            $row(array(
                $col(array($field('re-vw-date', lang('Day'), $input('re-vw-date', 'date', 'viewing_date', 'off', true, array('rules' => array('min_date' => 'today'))))), '6'),
                $col(array($field('re-vw-time', lang('Time of day'), $select('re-vw-time', 'preferred_time', lang('Choose a time of day…'), $time_windows))), '6'),
            ), '3'),
            $field('re-vw-note', lang('Anything we should know?'), $textarea('re-vw-note', 'note', 3, false), 'mb-3',
                lang('Another day that suits you, a question about the home, or whether you need a mortgage.')),
            $form_step(2, lang('How to reach you')),
        ), $contact_part('re-vw', $viewing_statuses,
            lang('I agree that the agency keeps these details to arrange the viewing and answer me, as its privacy notice explains.'),
            lang('Ask for the Viewing'), lang('An agent confirms within a working day.')), array(
            $loop(array()),
        ))),
    ),

    // One viewing: the request, its status and what the client wrote; the
    // messages follow as comments.
    'viewing_detail' => $view_detail('viewing_view', 'viewing', $viewing_missing) + array(
        'tree' => $record_view('bi-calendar-x', lang('Viewing not found'), $viewing_missing,
            'my_viewings', lang('My Viewings'),
            lang('Thank you, your request has reached us. An agent confirms the time within a working day; this page shows the status, and you can write to us below.'),
            array(
                $el('div', 'card border-0 shadow-sm rounded-3 mb-4', lang('Viewing Card'), array(
                    $el('div', 'card-body p-4', lang('Card Body'), array(
                        $record_title(lang('Viewing'), $sample_listing, 'listing'),
                        $el('dl', 'row small mb-0', lang('Details'), array_merge(
                            $detail(lang('Day'), '', lang('Day'), $bound('2026-12-05', 'viewing_date')),
                            $detail(lang('Time of day'), '', lang('Time'), $bound($time_windows[0], 'preferred_time')),
                            $detail(lang('Best way to reach you'), '', lang('Contact by'), $bound(lang('Phone'), 'contact_method')),
                            $detail(lang('Client'), '', lang('Client'), $person_name(), true),
                            $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
                        )),
                    )),
                )),
                $record_text(lang('Anything we should know?'), 'note', $sample_viewing_note),
                $record_note(lang('Another day suits you better, or a question about the home? Write to your agent below; we answer here and by e-mail.')),
            )),
    ),

    'viewing_messages' => $request_messages('viewing_view',
        lang('No messages yet. Write to your agent here about the viewing.'),
        lang('Write to your agent'), lang('Sign in to write to your agent.')),

    'my_viewings_list' => $request_list('my_viewings', 'viewing', 'viewing_view', $viewing_row(),
        lang('No viewing requests yet. Open a listing and press "Ask for a viewing".')),

    'staff_viewings_list' => $request_list('staff_viewings', 'viewing', 'viewing_view', $viewing_row(true),
        lang('No viewing requests yet.'),
        array('first_name', 'last_name', 'listing', 'viewing_date', 'status'),
        lang('Search by client, listing, day or status...')),

    // The valuation request: the fields are the page's form. The
    // Neighbourhood and the Agent start with the choice of the page that
    // sent the visitor (?area=, ?agent=).
    'valuation_form' => array(
        'page'   => 'valuation',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array_merge(array(
            $form_step(1, lang('Your home')),
            $radios('re-va-purpose', 'purpose', lang('Valuation for'), $purposes, lang('To sell')),
            $row(array(
                $col(array($field('re-va-area', lang('Neighbourhood'), $select('re-va-area', 'area', lang('Choose a neighbourhood…'), $area_titles, true, '', array('url_param' => 'area')))), '6'),
                $col(array($field('re-va-type', lang('Property type'), $select('re-va-type', 'property_type', lang('Choose a type…'), $type_titles))), '6'),
            ), '3'),
            $field('re-va-address', lang('Address'), $input('re-va-address', 'text', 'address', 'street-address'), 'mb-3',
                lang('Only the agency sees it; it is never shown on a listing without your agreement.')),
            $row(array(
                $col(array($field('re-va-bedrooms', lang('Bedrooms'), $input('re-va-bedrooms', 'number', 'bedrooms', 'off', false, array('kind' => 'number'), array($attr('min', '0'), $attr('step', '1'))))), '6'),
                $col(array($field('re-va-size', lang('Size (m²)'), $input('re-va-size', 'number', 'size', 'off', false, array('kind' => 'number'), array($attr('min', '0'), $attr('step', '1'))))), '6'),
            ), '3'),
            $field('re-va-note', lang('About the home'), $textarea('re-va-note', 'description', 4, false), 'mb-3',
                lang('Its floor, the view, recent work, and when you would like to sell or let.')),
            $field('re-va-photos', lang('Photos or papers'), $file('re-va-photos', 'documents', 'private'), 'mb-3',
                lang('A photo, a floor plan or the title deed (PDF, Word or a picture). More can follow under your request.')),
            $form_step(2, lang('The visit and how to reach you')),
            $row(array(
                $col(array($field('re-va-agent', lang('Agent'), $select('re-va-agent', 'agent', '', $agent_choices, true, lang('No preference'), array('url_param' => 'agent')))), '6'),
                $col(array($field('re-va-time', lang('Time of day'), $select('re-va-time', 'preferred_time', lang('Choose a time of day…'), $time_windows))), '6'),
            ), '3'),
        ), $contact_part('re-va', $valuation_statuses,
            lang('I agree that the agency keeps these details to value my home and answer me, as its privacy notice explains.'),
            lang('Ask for a Valuation'), lang('An agent calls you within a working day.')), array(
            $loop(array()),
        ))),
    ),

    // One valuation: the request, its status and what the owner wrote; the
    // messages (the written price among them) follow as comments.
    'valuation_detail' => $view_detail('valuation_view', 'valuation', $valuation_missing) + array(
        'tree' => $record_view('bi-house-x', lang('Valuation not found'), $valuation_missing,
            'my_valuations', lang('My Valuations'),
            lang('Thank you, your request has reached us. An agent calls you within a working day to arrange the visit; this page shows the status, and you can write to us below.'),
            array(
                $el('div', 'card border-0 shadow-sm rounded-3 mb-4', lang('Valuation Card'), array(
                    $el('div', 'card-body p-4', lang('Card Body'), array(
                        $record_title(lang('Valuation'), $area_titles[0], 'area'),
                        $el('dl', 'row small mb-0', lang('Details'), array_merge(
                            $detail(lang('Valuation for'), '', lang('Valuation for'), $bound(lang('To sell'), 'purpose')),
                            $detail(lang('Property type'), '', lang('Property type'), $bound($type_titles[0], 'property_type')),
                            $detail(lang('Address'), '', lang('Address'), $bound(lang('Sample Street 1, 34000 Istanbul, Türkiye'), 'address')),
                            $detail(lang('Bedrooms'), '', lang('Bedrooms'), $bound('2', 'bedrooms')),
                            $detail(lang('Size (m²)'), '', lang('Size'), $bound('95', 'size')),
                            $detail(lang('Agent'), '', lang('Agent'), $bound($agent_choices[0], 'agent')),
                            $detail(lang('Time of day'), '', lang('Time'), $bound($time_windows[0], 'preferred_time')),
                            $detail(lang('Client'), '', lang('Client'), $person_name(), true),
                            $detail(lang('Photos or papers'), 'text-break', lang('Documents'), $file_link('documents', 'floor-plan.pdf'), true),
                            $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
                        )),
                    )),
                )),
                $record_text(lang('About the home'), 'description', $sample_valuation_note),
                $record_note(lang('More photos or a question? Write to your agent below; the written valuation comes here too.')),
            )),
    ),

    'valuation_messages' => $request_messages('valuation_view',
        lang('No messages yet. Write to your agent here about the valuation.'),
        lang('Write to your agent'), lang('Sign in to write to your agent.')),

    'my_valuations_list' => $request_list('my_valuations', 'valuation', 'valuation_view', $valuation_row(),
        lang('No valuation requests yet. Tell us about your home for a free valuation.')),

    'staff_valuations_list' => $request_list('staff_valuations', 'valuation', 'valuation_view', $valuation_row(true),
        lang('No valuation requests yet.'),
        array('first_name', 'last_name', 'area', 'property_type', 'purpose', 'agent', 'status'),
        lang('Search by client, neighbourhood, type, agent or status...')),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_viewing_admin' => $mail_detail('email_viewing_new', 'viewing', lang('The viewing request could not be found.')) + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new viewing request')),
            $mail_para(lang('It came from the viewing form. Confirm the time with the client and the owner, then set the status on the request\'s page; what you write under it reaches the client by e-mail.')),
            $mail_viewing(),
            $mail_client(array(
                $mail_line(lang('Anything we should know?'), array($span($sample_viewing_note, '', lang('Note'), $bind('note')))),
            )),
            $mail_button(lang('Open the request'), '{{page:viewing_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_viewing_client' => $mail_detail('email_viewing_received', 'viewing', lang('The viewing request could not be found.')) + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your viewing request')),
            $mail_greeting(),
            $mail_para(lang('Thank you for your interest in the home. An agent confirms the day and time within a working day and meets you at the door.')),
            $mail_viewing(),
            $mail_para(lang('Another day suits you better, or a question? Write to us on the request\'s page; everything about your viewing stays there.')),
            $mail_button(lang('Open my request'), '{{page:viewing_view}}?r=^^reference_code^^'),
            $mail_new_account(),
        )),
    ),
    'email_valuation_admin' => $mail_detail('email_valuation_new', 'valuation', lang('The valuation request could not be found.')) + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new valuation request')),
            $mail_para(lang('It came from the valuation form. Call the owner to arrange the visit, then set the status on the request\'s page; what you write under it reaches the owner by e-mail.')),
            $mail_valuation(),
            $mail_client(array(
                $mail_line(lang('Address'), array($span(lang('Sample Street 1, 34000 Istanbul, Türkiye'), '', lang('Address'), $bind('address')))),
                $mail_line(lang('About the home'), array($span($sample_valuation_note, '', lang('Text'), $bind('description')))),
            )),
            $mail_button(lang('Open the request'), '{{page:valuation_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_valuation_client' => $mail_detail('email_valuation_received', 'valuation', lang('The valuation request could not be found.')) + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your valuation request')),
            $mail_greeting(),
            $mail_para(lang('Thank you for thinking of us. An agent calls you within a working day to arrange a visit; the written valuation follows within two working days of it.')),
            $mail_valuation(),
            $mail_para(lang('More photos or a question? Write to us on the request\'s page; the valuation comes there too.')),
            $mail_button(lang('Open my request'), '{{page:valuation_view}}?r=^^reference_code^^'),
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
