<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Marina", its berth requests and charter enquiries: the
 * berth request, the request's own page with its messages, My Berth
 * Requests and the staff's list; the charter enquiry, the enquiry's own
 * page, My Charter Enquiries and the staff's list; the privacy page the
 * consent boxes link, the e-mails of both forms and the new message
 * e-mail every thread of the site sends, and the widgets that draw them.
 * Included after parts.php, mail.php and charters.php in the same scope;
 * returns array('pages' => ..., 'widgets' => ...).
 *
 * A berth request is a request, never a reservation: a record of the
 * Berth Requests form. A skipper without an account gets one
 * (auto-registration), so the request's page and My Berth Requests show
 * it at once. Its Status is an office-use-only field the harbour office
 * changes in the control panel; the berth allocated and the rate are
 * written under it as messages (the page's comments), mailed to the
 * skipper and to the harbour. The template keeps no berth plan and no
 * prices per date.
 *
 * The kind of berth and the season start with what a link sends (?kind=
 * from the berth-kind pages, ?season= from the rates table), each the
 * answer's own words as parts.php writes them. A charter enquiry is a
 * record of its own form the same way; its boat starts with ?boat= from
 * the boat's page.
 *
 * The pieces named mn_… (the record rows, the record page, the lists,
 * the messages, the e-mail lines, the form ends) serve the event entries
 * too.
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

// The sample records the canvas shows. Their dates are words, never a
// date: a fixed day would read as a past season a year later.
$rq_sample_kind = $kinds['visitor']['title'];
$rq_sample_season = $seasons['high']['title'];
$rq_sample_boat = lang('Sea Swallow');
$rq_sample_notes = lang('We sail in from the islands with four aboard and would like to stay a week; a berth near the showers would be lovely, and we arrive in the late afternoon.');
$rq_season_choices = array_merge($season_titles, array(lang('Not sure yet')));
$rq_missing = lang('This berth request could not be found, or it is not yours to see.');
$ce_sample_boat = $boats['aurora']['name'];
$ce_sample_note = lang('A family of four with two teenagers: a week among the islands, with a skipper for the first two days while we find our feet.');
$ce_missing = lang('This charter enquiry could not be found, or it is not yours to see.');
$mn_sample_arrival = lang('Arrival date');
$mn_sample_departure = lang('Departure date');

// ── Page shells ─────────────────────────────────────────────────────────

// The side column of a form: what happens next, then what is good to
// know and a link.
$mn_aside = function ($steps, $good, $link_text, $link_key) use ($el, $heading, $link, $check_list) {
    $items = array();
    foreach ($steps as $n => $text) {
        $items[] = $el('li', $n < count($steps) - 1 ? 'mb-2' : '', lang('Step'), array(), array('text' => $text));
    }
    return array(
        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('How It Works'), array(
            $heading('h2', lang('What happens next'), 'h5 fw-semibold mb-3'),
            $el('ol', 'small text-body-secondary ps-3 mb-0', lang('List'), $items),
        )),
        $el('div', 'p-4 rounded-4 border', lang('Good to know'), array(
            $heading('h2', lang('Good to know'), 'h5 fw-semibold mb-3'),
            $check_list($good, 'list-unstyled small mb-3'),
            $link($link_text, '{{page:' . $link_key . '}}', 'fw-semibold link-primary text-decoration-none small'),
        )),
    );
};

// The card of a My… list that starts another record.
$mn_new_card = function ($icon_name, $title, $text, $button, $page_key) use ($el, $icon, $span, $para, $link) {
    return $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('New Request'), array(
        $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
            $icon($icon_name, 'text-primary'),
            $span($title, '', lang('Text')),
        )),
        $para($text, 'small text-body-secondary mb-3'),
        $link($button, '{{page:' . $page_key . '}}', 'btn btn-sm btn-primary rounded-pill px-3'),
    ));
};

// A form page: the header, the form's widget in a card and the side column.
$mn_form_page = function ($title, $lead, $widget_key, $aside) use ($page, $page_header, $el, $container, $row, $col, $widget) {
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

// A record page: the record's widget and the messages under it. $label is
// the subject of the e-mail a new message sends.
$mn_record_page = function ($key, $name, $title, $detail_key, $messages_key, $subject, $attachments) use ($page, $widget, $comments_section) {
    return array(
        'key'      => $key,
        'name'     => $name,
        'folder'   => 'registration',
        'title'    => $title,
        'search'   => false,
        'sitemap'  => false,
        'comments' => array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                            'attachments' => $attachments,
                            'email_page' => '{{tab:email_reply}}', 'email_subject' => $subject,
                            'notify_email' => '{{site_email}}'),
        'tree'     => $page(array(
            $widget($detail_key),
            $comments_section(lang('Messages'), $messages_key),
        ), false),
    );
};

// The skipper's own list, with a card that starts another record.
$mn_mine_page = function ($key, $name, $title, $lead, $widget_key, $card) use ($page, $page_header, $el, $container, $row, $col, $widget) {
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
                        $col(array($card), '', '4'),
                    ), '4', '', 'g-lg-5'),
                )),
            )),
        ), false),
    );
};

// The staff's list of every record, newest first, with a search.
$mn_staff_page = function ($key, $name, $title, $lead, $widget_key) use ($staff_page, $page_header, $el, $container, $widget) {
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

// An e-mail page that draws one widget.
$mn_mail_page = function ($key, $name, $title, $widget_key) use ($mail_settings, $mail_page, $widget) {
    return array_merge($mail_settings, array(
        'key'   => $key,
        'name'  => $name,
        'title' => $title,
        'tree'  => $mail_page(array($widget($widget_key))),
    ));
};

// ── Pages: berth requests ───────────────────────────────────────────────

// The berth request. What is sent opens the request's own page.
$request_pages[] = array(
    'key'              => 'berth_request',
    'name'             => lang('request-a-berth'),
    'folder'           => 'public',
    'title'            => lang('Request a Berth'),
    'meta_description' => lang('Tell us your boat and your dates: the harbour office answers within a day with the berth it allocates and the rate.'),
    'form'             => array(
        'form_name'            => lang('Berth Requests'),
        'confirmation_message' => lang('Thank you, your berth request has reached us. The harbour office answers within a day.'),
        'confirmation_page_id' => '{{tab:request_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New berth request: ^^berth_kind^^'),
        'notify_page_id'       => '{{tab:email_request_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your berth request'),
        'confirm_page_id'      => '{{tab:email_request_received}}',
    ),
    'tree'             => $mn_form_page(lang('Request a Berth'),
        lang('Tell us your boat and your dates. The harbour office answers within a day with the berth it allocates and the rate; nothing is paid now.'),
        'request_form', $mn_aside(array(
            lang('Your request opens its own page, and an account for you if you have none.'),
            lang('The harbour office answers there within a day, with the berth it allocates and the rate.'),
            lang('Confirm it in a message; the status shows Berth confirmed once the berth is yours.'),
            lang('Nothing is paid now: the berth is paid at the harbour office on arrival.'),
        ), array($harbour['arrival'], $harbour['vhf'], $harbour['hours']), lang('See the Rates'), 'rates')),
);

// One berth request: the request, its status and the messages under it.
$request_pages[] = $mn_record_page('request_view', lang('berth-request'), lang('Berth Request'), 'request_detail', 'request_messages',
    lang('There is a new message about your berth request'), false);

// My Berth Requests: the skipper's own requests (for the staff, everybody's).
$request_pages[] = $mn_mine_page('my_requests', lang('my-berth-requests'), lang('My Berth Requests'),
    lang('Your berth requests, their status and the harbour office\'s answers, newest first.'), 'my_requests_list',
    $mn_new_card('bi-calendar-plus', lang('Another visit?'),
        lang('Ask for other dates or another kind of berth; your details are filled in for you.'), lang('Request a Berth'), 'berth_request'));

$request_pages[] = $mn_staff_page('staff_requests', lang('all-berth-requests'), lang('Berth Requests'),
    lang('Every berth request, newest first: allocate a berth, set the status and write to the skipper under each.'), 'staff_requests_list');

// The privacy notice the consent boxes and the footer link to: sample text
// with a note to adapt it.
$request_pages[] = array(
    'key'              => 'privacy',
    'name'             => lang('privacy-policy'),
    'folder'           => 'public',
    'title'            => lang('Privacy Policy'),
    'meta_description' => lang('What the marina keeps about you when you ask for a berth, a charter or an event entry, why, and what you can ask of us.'),
    'tree'             => $page(array(
        $page_header(lang('Privacy Policy'), lang('What the marina keeps about you when you ask for a berth, a charter or an event entry, why, and what you can ask of us.')),
        $el('section', 'py-5', lang('Your Data'), array(
            $container(array($row(array($col(array(
                $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Sample Text Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Sample text: adapt it to your company and to the data protection law that applies to you before you publish.'), '', lang('Text')),
                ), array('_attrs' => array($attr('role', 'note')))),
                $heading('h2', lang('Your data'), 'h4 fw-semibold mb-3'),
                $para(lang('When you ask for a berth, a charter or an event entry, join the newsletter or open an account, we keep your name, your contact details, your boat\'s details, your dates and what you tell us, to answer you, allocate your berth and welcome you.'), 'mb-3'),
                $para(lang('We keep it as long as the law requires, never sell it and never give it to advertisers. You can see and change your account data from My Account, and ask us about the rest through the contact page.'), 'mb-0'),
            ), '', '8'))))),
        ), $searchable('mn-your-data', lang('privacy, personal data, KVKK, GDPR, delete my data'))),
    )),
);

// The e-mails of the berth request: what the harbour office receives and
// the reply to the skipper.
$request_pages[] = $mn_mail_page('email_request_new', lang('email-new-berth-request'), lang('New Berth Request E-mail'), 'email_request_admin');
$request_pages[] = $mn_mail_page('email_request_received', lang('email-berth-request-received'), lang('Berth Request Received E-mail'), 'email_request_client');

// A new message or reply: sent to the skipper of a berth request, a
// charter enquiry or an event entry, the sender of a conversation and the
// visitors watching a page. Its Comments widget draws the comment in the
// card of mail.php; the System region after it keeps the fixed comment
// for an e-mail the widget does not draw.
$request_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: your berth request, your charter enquiry, your event entry, a conversation or a news post. You can read it below and answer it on the page.')),
        $widget('email_comment'),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));

// ── Pages: charter enquiries ────────────────────────────────────────────

// The charter enquiry. What is sent opens the enquiry's own page.
$request_pages[] = array(
    'key'              => 'charter_enquiry',
    'name'             => lang('charter-enquiry'),
    'folder'           => 'public',
    'title'            => lang('Charter Enquiry'),
    'meta_description' => lang('Ask about a charter boat for a day or a week, with or without a skipper: the charter desk answers within a day with an offer.'),
    'form'             => array(
        'form_name'            => lang('Charter Enquiries'),
        'confirmation_message' => lang('Thank you, your charter enquiry has reached us. The charter desk answers within a day with an offer.'),
        'confirmation_page_id' => '{{tab:enquiry_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New charter enquiry: ^^boat^^'),
        'notify_page_id'       => '{{tab:email_enquiry_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your charter enquiry'),
        'confirm_page_id'      => '{{tab:email_enquiry_received}}',
    ),
    'tree'             => $mn_form_page(lang('Charter Enquiry'),
        lang('Tell us the boat, the dates and who sails with you. The charter desk answers within a day with an offer; nothing is paid now.'),
        'enquiry_form', $mn_aside(array(
            lang('Your enquiry opens its own page, and an account for you if you have none.'),
            lang('The charter desk answers there within a day, with an offer for the boat and the dates.'),
            lang('Confirm it in a message; the status shows Charter confirmed once the boat is yours.'),
            lang('Nothing is paid now: the charter desk explains the deposit with its offer.'),
        ), array($charter_note, $harbour['hours']), lang('See All Charter Boats'), 'charters')),
);

// One charter enquiry: what was asked, its status and the messages under it.
$request_pages[] = $mn_record_page('enquiry_view', lang('charter-enquiry-view'), lang('Charter Enquiry'), 'enquiry_detail', 'enquiry_messages',
    lang('There is a new message about your charter enquiry'), false);

$request_pages[] = $mn_mine_page('my_enquiries', lang('my-charter-enquiries'), lang('My Charter Enquiries'),
    lang('Your charter enquiries, their status and the charter desk\'s offers, newest first.'), 'my_enquiries_list',
    $mn_new_card('bi-water', lang('Another charter?'),
        lang('Ask about another boat or other dates; your details are filled in for you.'), lang('Send a Charter Enquiry'), 'charter_enquiry'));

$request_pages[] = $mn_staff_page('staff_enquiries', lang('all-charter-enquiries'), lang('Charter Enquiries'),
    lang('Every charter enquiry, newest first: send the offer, set the status and answer under each.'), 'staff_enquiries_list');

$request_pages[] = $mn_mail_page('email_enquiry_new', lang('email-new-charter-enquiry'), lang('New Charter Enquiry E-mail'), 'email_enquiry_admin');
$request_pages[] = $mn_mail_page('email_enquiry_received', lang('email-charter-enquiry-received'), lang('Charter Enquiry Received E-mail'), 'email_enquiry_client');

// ── Widget pieces ───────────────────────────────────────────────────────

// The skipper's name.
$mn_person = function () use ($span, $bind) {
    return array(
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
    );
};
// Two dates: the first, an arrow, the last.
$mn_dates = function ($from = 'arrival_date', $to = 'departure_date') use ($span, $bind, $mn_sample_arrival, $mn_sample_departure) {
    return array(
        $span($mn_sample_arrival, '', lang('Arrival'), $bind($from)),
        $span(' → ', '', lang('Text')),
        $span($mn_sample_departure, '', lang('Departure'), $bind($to)),
    );
};
// A detail of a record's card: the label, then the value. $value is either
// the value's props (its sample text and binding) or, with $children, the
// nodes it is made of.
$mn_detail = function ($label, $class, $name, $value, $children = false) use ($el) {
    return array(
        $el('dt', 'col-sm-4 fw-normal text-body-secondary', lang('Label'), array(), array('text' => $label)),
        $el('dd', trim('col-sm-8 ' . $class), $name, $children ? $value : array(), $children ? array() : $value),
    );
};
$mn_bound = function ($sample, $field) use ($bind) {
    return array_merge(array('text' => $sample), $bind($field));
};

// A record in a list: the title, the status, the details, linked to its
// page. The staff's row starts with the skipper's name and adds the
// phone; the skipper's own adds the messages. $items: array(icon, label,
// sample, field) each.
$mn_row = function ($name, $title_sample, $title_label, $title_field, $status, $items, $staff) use ($el, $span, $icon, $bind, $mn_person) {
    $nodes = array();
    if ($staff) $items[] = array('bi-telephone', lang('Phone'), '+90 555 000 00 00', 'phone');
    else $items[] = array('bi-chat-left-text', lang('Messages'), '0', 'number_of_comments');
    foreach ($items as $i) {
        $nodes[] = $el('span', 'd-inline-flex align-items-center gap-1', $i[1], array($icon($i[0]), $span($i[2], '', $i[1], $bind($i[3]))));
    }
    if ($staff) {
        $title = $el('span', 'fw-semibold', lang('Skipper'), array_merge($mn_person(), array(
            $span(' · ', 'text-body-secondary', lang('Text')),
            $span($title_sample, 'text-body-secondary fw-normal', $title_label, $bind($title_field)),
        )));
    } else {
        $title = $span($title_sample, 'fw-semibold', $title_label, $bind($title_field));
    }
    return $el('a', 'list-group-item list-group-item-action p-3', $name, array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $title,
            $span($status, 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
        )),
        $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), $nodes),
    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view')));
};
$rq_row = function ($staff = false) use ($mn_row, $rq_sample_kind, $rq_sample_boat, $mn_sample_arrival, $request_statuses) {
    return $mn_row(lang('Berth Request'), $rq_sample_kind, lang('Kind of berth'), 'berth_kind', $request_statuses[0], array(
        array('bi-calendar-range', lang('Arrival'), $mn_sample_arrival, 'arrival_date'),
        array('bi-water', lang('Boat'), $rq_sample_boat, 'boat_name'),
        array('bi-rulers', lang('Length'), lang('12 metres'), 'boat_length'),
    ), $staff);
};
$ce_row = function ($staff = false) use ($mn_row, $ce_sample_boat, $mn_sample_arrival, $enquiry_statuses) {
    return $mn_row(lang('Charter Enquiry'), $ce_sample_boat, lang('Boat'), 'boat', $enquiry_statuses[0], array(
        array('bi-calendar-range', lang('First day'), $mn_sample_arrival, 'start_date'),
        array('bi-people', lang('Guests'), '4', 'guests'),
        array('bi-send', lang('Sent'), lang('Date'), 'submitted_date_and_time'),
    ), $staff);
};

// The form detail settings of a widget: the e-mail pages read the record
// they are sent for (public), the record's page only the skipper's own.
$mn_detail_config = function ($page_key, $form_key, $missing, $access) {
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
// A list of records: the skipper's own (the staff see them all), or the
// staff's with a search.
$mn_list = function ($page_key, $form_key, $detail_key, $row_node, $empty, $search_fields = array(), $search_label = '') use ($widget_root, $el, $loop) {
    $config = array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:' . $form_key . '}}',
        'detail_page_id'      => '{{tab:' . $detail_key . '}}',
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

// The record's page: the not-found notice, the sent and new-account
// notices, the toolbar, the card with the details, the skipper's words and
// a note on the messages under it.
$mn_record = function ($w) use ($widget_root, $container, $row, $col, $el, $icon, $heading, $para, $span, $link, $attr, $bind, $show_when, $edit_button, $mn_bound) {
    return $widget_root(array(
        $container(array(
            $row(array(
                $col(array(
                    $el('div', 'text-center py-5', lang('Not Found'), array(
                        $icon('bi-calendar-x', 'display-5 text-body-tertiary'),
                        $heading('h2', $w['not_found_title'], 'h3 fw-semibold mt-3'),
                        $para($w['missing'], 'text-body-secondary mb-4', $bind('not_found')),
                        $link($w['list_title'], '{{page:' . $w['list_key'] . '}}', 'btn btn-primary rounded-pill px-4'),
                    ), $show_when('record_not_found')),
                    $el('div', '', lang('Record'), array(
                        $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
                            $icon('bi-check-circle', 'mt-1'),
                            $span($w['sent'], '', lang('Text')),
                        ), array_merge(array('_attrs' => array($attr('role', 'status'))), $show_when('submitted_this_session'))),
                        $el('div', 'alert alert-info', lang('New Account'), array(
                            $el('p', 'fw-semibold mb-2', lang('Title'), array(), array('text' => lang('We opened an account for you'))),
                            $el('p', 'mb-1', lang('Sign-in Email'), array(
                                $span(lang('Email') . ': ', '', lang('Label')),
                                $span('sample@example.com', 'fw-semibold', lang('Value'), $bind('__new_account_email')),
                            )),
                            // The single-use set password link, for the skipper who sent
                            // the form while it is live; the sentence below covers a used
                            // or expired link.
                            $link(lang('Set your password'), '#', 'btn btn-sm btn-primary', array(),
                                array('_bindings' => array('href' => '__new_account_set_password_url', 'eo_visible_if' => 'has_set_password_link'))),
                            $para(lang('You can set a password with "Forgot password" on the sign-in page.'), 'small mt-2 mb-0'),
                        ), $show_when('has_new_account')),
                        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                            $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                                $icon('bi-arrow-left'),
                                $span($w['list_title'], '', lang('Link Text')),
                            ), array('href' => '{{page:' . $w['list_key'] . '}}')),
                            $edit_button(lang('Change Status')),
                        )),
                        $el('div', 'card border-0 shadow-sm rounded-4 mb-4', $w['card'], array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
                                    $el('div', '', lang('Words'), array(
                                        $para($w['eyebrow'], 'pmn-eyebrow small text-uppercase fw-semibold text-primary mb-1'),
                                        $heading('h1', $w['title_sample'], 'pmn-display h3 mb-0', $bind($w['title_field'])),
                                    )),
                                    $span($w['status'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
                                )),
                                $el('dl', 'row small mb-0', lang('Details'), $w['details']),
                            )),
                        )),
                        $el('div', 'pg-hide-if-empty p-4 rounded-4 bg-body-tertiary mb-4', $w['words_label'], array(
                            $para($w['words_label'], 'small fw-semibold text-body-secondary mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), $mn_bound($w['words_sample'], $w['words_field'])),
                        )),
                        $el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
                            $icon('bi-chat-left-text', 'text-primary mt-1'),
                            $span($w['note'], '', lang('Text')),
                        )),
                    ), $show_when('record_found')),
                ), '', '8'),
            ), '', 'center'),
        ), 'py-5'),
    ));
};

// The messages under a record: the page's own Comments widget, shown only
// to whoever may read the record above it. Oldest first, all on one page.
// No Reply, Share or follow box: the skipper is e-mailed every answer and
// the harbour every message.
$mn_messages = function ($page_key, $empty) use ($comment_thread) {
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

// The lines of a berth request, for its e-mails.
$rq_mail_request = function () use ($mail_box, $mail_line, $span, $bind, $mn_dates, $rq_sample_kind, $rq_sample_season, $rq_sample_boat, $vessel_choices, $power_choices) {
    return $mail_box(array(
        $mail_line(lang('Kind of berth'), array($span($rq_sample_kind, '', lang('Kind of berth'), $bind('berth_kind')))),
        $mail_line(lang('Season'), array($span($rq_sample_season, '', lang('Season'), $bind('season')))),
        $mail_line(lang('Stay'), $mn_dates()),
        $mail_line(lang('Boat'), array($span($rq_sample_boat, '', lang('Boat'), $bind('boat_name')))),
        $mail_line(lang('Type of boat'), array($span($vessel_choices[0], '', lang('Type of boat'), $bind('vessel')))),
        $mail_line(lang('Length'), array($span(lang('12 metres'), '', lang('Length'), $bind('boat_length')))),
        $mail_line(lang('Beam'), array($span(lang('4 metres'), '', lang('Beam'), $bind('beam')))),
        $mail_line(lang('Draught'), array($span(lang('2 metres'), '', lang('Draught'), $bind('draught')))),
        $mail_line(lang('Crew'), array($span('4', '', lang('Crew'), $bind('crew')))),
        $mail_line(lang('Shore power'), array($span($power_choices[0], '', lang('Shore power'), $bind('power')))),
    ), lang('Berth Request'));
};
// The lines of a charter enquiry, for its e-mails.
$ce_mail_enquiry = function () use ($mail_box, $mail_line, $span, $bind, $mn_dates, $ce_sample_boat, $experience_choices) {
    return $mail_box(array(
        $mail_line(lang('Boat'), array($span($ce_sample_boat, '', lang('Boat'), $bind('boat')))),
        $mail_line(lang('Charter'), $mn_dates('start_date', 'end_date')),
        $mail_line(lang('Guests'), array($span('4', '', lang('Guests'), $bind('guests')))),
        $mail_line(lang('Sailing experience'), array($span($experience_choices[0], '', lang('Sailing experience'), $bind('experience')))),
        $mail_line(lang('Contact by'), array($span(lang('E-mail'), '', lang('Contact by'), $bind('contact_method')))),
    ), lang('Charter Enquiry'));
};
// The skipper's lines of a staff e-mail; $words is the field of what the
// skipper wrote and its sample.
$mn_mail_person = function ($words_label, $words_sample, $words_field) use ($mail_box, $mail_line, $span, $bind, $mn_person) {
    return $mail_box(array(
        $mail_line(lang('Skipper'), $mn_person()),
        $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
        $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
        $mail_line($words_label, array($span($words_sample, '', $words_label, $bind($words_field)))),
    ), lang('Skipper'));
};
// The reference code under a staff e-mail.
$mn_mail_reference = function () use ($el, $span, $bind, $mail_style) {
    return $el('p', 'small text-body-secondary mt-4 mb-0', lang('Reference'), array(
        $span(lang('Reference Code') . ': ', '', lang('Label')),
        $span('ABC123', '', lang('Reference Code'), $bind('reference_code')),
    ), $mail_style('font-size:13px;color:#4b5563;margin:24px 0 0'));
};
// The account a form opened for a skipper who had none, in the reply
// e-mail.
$mn_mail_new_account = function () use ($el, $span, $link, $bind, $show_when, $mail_style) {
    return $el('div', 'border-top pt-3', lang('New Account'), array(
        $el('p', 'mb-2', lang('Sign-in Email'), array(
            $span(lang('We opened an account for you with this address:') . ' ', '', lang('Label')),
            $span('sample@example.com', 'fw-semibold', lang('Value'), $bind('__new_account_email')),
        ), $mail_style('margin:0 0 8px')),
        $el('p', 'mb-0', lang('Password'), array(
            $span(lang('To choose your password, use') . ' ', '', lang('Label')),
            $link(lang('Forgot Password'), '{{page:forgot_password}}', ''),
        ), $mail_style('margin:0')),
    ), array_merge($mail_style('border-top:1px solid #dbeafe;padding-top:16px'), $show_when('has_new_account')));
};

$mn_not_before_today = array('rules' => array('min_date' => 'today'));
$mn_count = function ($id, $name, $min, $max, $value) use ($input, $attr) {
    return $input($id, 'number', $name, 'off', true, array('kind' => 'number'),
        array($attr('min', (string)$min), $attr('max', (string)$max), $attr('step', '1'), $attr('value', (string)$value)));
};
// The skipper's name, e-mail and phone, the same four fields on every form.
$mn_contact_fields = function ($prefix) use ($row, $col, $field, $input) {
    return $row(array(
        $col(array($field($prefix . '-first-name', lang('First Name'), $input($prefix . '-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
        $col(array($field($prefix . '-last-name', lang('Last Name'), $input($prefix . '-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
        $col(array($field($prefix . '-email', lang('Email'), $input($prefix . '-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
        $col(array($field($prefix . '-phone', lang('Phone'), $input($prefix . '-phone', 'tel', 'phone', 'tel', true, array('contact_field' => 'mobile_phone')))), '6'),
    ), '3');
};
// Two dates, never before today.
$mn_date_fields = function ($prefix, $from = 'arrival_date', $to = 'departure_date', $from_label = '', $to_label = '') use ($row, $col, $field, $input, $mn_not_before_today) {
    return $row(array(
        $col(array($field($prefix . '-from', $from_label !== '' ? $from_label : lang('Arrival'), $input($prefix . '-from', 'date', $from, 'off', true, $mn_not_before_today))), '6'),
        $col(array($field($prefix . '-to', $to_label !== '' ? $to_label : lang('Departure'), $input($prefix . '-to', 'date', $to, 'off', true, $mn_not_before_today))), '6'),
    ), '3');
};
// The end of a form: the consent with the privacy link, the CAPTCHA, the
// button and a line beside it.
$mn_form_end = function ($consent_id, $consent_text, $button, $line) use ($el, $agree, $captcha, $submit, $para, $loop) {
    return array(
        $agree($consent_id, 'consent', $consent_text, true),
        $captcha(),
        $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
            $submit($button, 'btn btn-primary btn-lg rounded-pill px-4'),
            $para($line, 'small text-body-secondary mb-0'),
        )),
        $loop(array()),
    );
};
// A boat's measure as words, with its example.
$rq_measure = function ($id, $name, $label, $example, $required) use ($field, $input) {
    return $field($id, $label, $input($id, 'text', $name, 'off', $required), 'mb-3', sprintf(lang('In words, for example %s.'), $example));
};

$request_widgets = array(

    // The berth request: the fields are the page's form. The kind and the
    // season start with what a link sends (?kind=, ?season=). The Status
    // is the harbour office's (office use only): every request starts as
    // Request received.
    'request_form' => array(
        'page'   => 'berth_request',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array_merge(array(
            $form_step(1, lang('Your visit')),
            $mn_date_fields('mn-rq'),
            $row(array(
                $col(array($field('mn-rq-kind', lang('Kind of berth'), $select('mn-rq-kind', 'berth_kind', lang('Choose a kind of berth…'), $request_kind_choices, true, '', array('url_param' => 'kind')))), '6'),
                $col(array($field('mn-rq-season', lang('Season'), $select('mn-rq-season', 'season', '', $rq_season_choices, false, lang('Not sure yet'), array('url_param' => 'season')))), '6'),
            ), '3'),
            $form_step(2, lang('Your boat')),
            $row(array(
                $col(array($field('mn-rq-boat', lang('Boat name'), $input('mn-rq-boat', 'text', 'boat_name', 'off'))), '6'),
                $col(array($field('mn-rq-vessel', lang('Type of boat'), $select('mn-rq-vessel', 'vessel', lang('Choose a type of boat…'), $vessel_choices, true))), '6'),
                $col(array($rq_measure('mn-rq-length', 'boat_length', lang('Length overall'), lang('12 metres'), true)), '4'),
                $col(array($rq_measure('mn-rq-beam', 'beam', lang('Beam'), lang('4 metres'), false)), '4'),
                $col(array($rq_measure('mn-rq-draught', 'draught', lang('Draught'), lang('2 metres'), false)), '4'),
                $col(array($field('mn-rq-crew', lang('Crew'), $mn_count('mn-rq-crew', 'crew', 1, 20, 2), 'mb-3', lang('Everyone aboard, the skipper included.'))), '6'),
                $col(array($field('mn-rq-power', lang('Shore power'), $select('mn-rq-power', 'power', '', $power_choices, false, $power_choices[0]))), '6'),
            ), '3'),
            $field('mn-rq-notes', lang('Notes'), $textarea('mn-rq-notes', 'notes', 3, false), 'mb-3',
                lang('Your time of arrival, a berth near the showers, a crew change or a boat in need of repair.')),
            $form_step(3, lang('How to reach you')),
            $mn_contact_fields('mn-rq'),
            $field('mn-rq-status', lang('Status'), $select('mn-rq-status', 'status', '', $request_statuses, false, $request_statuses[0], array('office_use_only' => true))),
        ), $mn_form_end('mn-rq-consent',
            lang('I agree that the marina keeps these details to answer my request and prepare my berth, as its privacy notice explains.'),
            lang('Send the Request'), lang('The harbour office answers within a day. Nothing is paid now.')))),
    ),

    // One berth request: the request, its status and what the skipper
    // wrote; the harbour office's answers (the berth allocated among them)
    // follow as comments.
    'request_detail' => $mn_detail_config('request_view', 'berth_request', $rq_missing, 'submitter_only') + array(
        'tree' => $mn_record(array(
            'not_found_title' => lang('Berth request not found'),
            'missing'         => $rq_missing,
            'list_title'      => lang('My Berth Requests'),
            'list_key'        => 'my_requests',
            'sent'            => lang('Thank you, your request has reached us. The harbour office answers here within a day with the berth it allocates; this page shows the status, and you can write to us below.'),
            'card'            => lang('Request Card'),
            'eyebrow'         => lang('Berth Request'),
            'title_sample'    => $rq_sample_kind,
            'title_field'     => 'berth_kind',
            'status'          => $request_statuses[0],
            'details'         => array_merge(
                $mn_detail(lang('Stay'), '', lang('Stay'), $mn_dates(), true),
                $mn_detail(lang('Season'), '', lang('Season'), $mn_bound($rq_sample_season, 'season')),
                $mn_detail(lang('Boat name'), '', lang('Boat name'), $mn_bound($rq_sample_boat, 'boat_name')),
                $mn_detail(lang('Type of boat'), '', lang('Type of boat'), $mn_bound($vessel_choices[0], 'vessel')),
                $mn_detail(lang('Length overall'), '', lang('Length overall'), $mn_bound(lang('12 metres'), 'boat_length')),
                $mn_detail(lang('Beam'), 'pg-hide-if-empty', lang('Beam'), $mn_bound(lang('4 metres'), 'beam')),
                $mn_detail(lang('Draught'), 'pg-hide-if-empty', lang('Draught'), $mn_bound(lang('2 metres'), 'draught')),
                $mn_detail(lang('Crew'), '', lang('Crew'), $mn_bound('4', 'crew')),
                $mn_detail(lang('Shore power'), '', lang('Shore power'), $mn_bound($power_choices[0], 'power')),
                $mn_detail(lang('Skipper'), '', lang('Skipper'), $mn_person(), true),
                $mn_detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $mn_bound('ABC123', 'reference_code'))
            ),
            'words_label'     => lang('Notes'),
            'words_sample'    => $rq_sample_notes,
            'words_field'     => 'notes',
            'note'            => lang('Other dates, a question about the berth or something to add? Write to us below; we answer here and by e-mail.'),
        )),
    ),

    'request_messages' => $mn_messages('request_view',
        lang('No messages yet. The berth the harbour office allocates comes here; you can write to us about your visit too.')),

    'my_requests_list' => $mn_list('my_requests', 'berth_request', 'request_view', $rq_row(),
        lang('No berth requests yet. Tell us your boat and your dates.')),

    'staff_requests_list' => $mn_list('staff_requests', 'berth_request', 'request_view', $rq_row(true),
        lang('No berth requests yet.'),
        array('first_name', 'last_name', 'email', 'boat_name', 'berth_kind', 'season', 'status'),
        lang('Search by skipper, e-mail, boat, kind of berth, season or status...')),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_request_admin' => $mn_detail_config('email_request_new', 'berth_request', lang('The berth request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new berth request')),
            $mail_para(lang('It came from the berth request form. Answer under the request on its page with the berth you allocate and the rate, and set the status; what you write there reaches the skipper by e-mail.')),
            $rq_mail_request(),
            $mn_mail_person(lang('Notes'), $rq_sample_notes, 'notes'),
            $mail_button(lang('Open the request'), '{{page:request_view}}?r=^^reference_code^^'),
            $mn_mail_reference(),
        )),
    ),
    'email_request_client' => $mn_detail_config('email_request_received', 'berth_request', lang('The berth request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your berth request')),
            $mail_greeting(),
            $mail_para(lang('Thank you. The harbour office looks at your boat and your dates and answers within a day with the berth it allocates and the rate, on the request\'s page and by e-mail. Nothing is paid now.')),
            $rq_mail_request(),
            $mail_para(lang('Other dates or a question? Write to us on the request\'s page; everything about your visit stays there.')),
            $mail_button(lang('Open my request'), '{{page:request_view}}?r=^^reference_code^^'),
            $mn_mail_new_account(),
        )),
    ),

    // The charter enquiry: the fields are the page's form. The boat
    // starts with the boat page that sent the visitor (?boat=). The Status
    // is the charter desk's (office use only): every enquiry starts as
    // Enquiry received.
    'enquiry_form' => array(
        'page'   => 'charter_enquiry',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array_merge(array(
            $form_step(1, lang('Your charter')),
            $field('mn-ce-boat', lang('Boat'), $select('mn-ce-boat', 'boat', '', $charter_boat_choices, false, $charter_boat_choices[0], array('url_param' => 'boat')), 'mb-3',
                lang('A boat you liked, or any boat that suits your crew.')),
            $mn_date_fields('mn-ce', 'start_date', 'end_date', lang('First day'), lang('Last day')),
            $row(array(
                $col(array($field('mn-ce-guests', lang('Guests'), $mn_count('mn-ce-guests', 'guests', 1, 12, 2))), '6'),
                $col(array($field('mn-ce-experience', lang('Sailing experience'), $select('mn-ce-experience', 'experience', lang('Choose one…'), $experience_choices, true))), '6'),
            ), '3'),
            $field('mn-ce-details', lang('About your charter'), $textarea('mn-ce-details', 'details', 4, false), 'mb-3',
                lang('Where you would like to sail, children aboard, a skipper for some days, provisioning or anything we should know.')),
            $form_step(2, lang('How to reach you')),
            $radios('mn-ce-contact', 'contact_method', lang('Best way to reach you'), $contact_choices, lang('E-mail')),
            $mn_contact_fields('mn-ce'),
            $field('mn-ce-status', lang('Status'), $select('mn-ce-status', 'status', '', $enquiry_statuses, false, $enquiry_statuses[0], array('office_use_only' => true))),
        ), $mn_form_end('mn-ce-consent',
            lang('I agree that the marina keeps these details to answer my charter enquiry, as its privacy notice explains.'),
            lang('Send the Enquiry'), lang('The charter desk answers within a day.')))),
    ),

    // One charter enquiry: what was asked, its status and what the skipper
    // wrote; the offer and the messages follow as comments.
    'enquiry_detail' => $mn_detail_config('enquiry_view', 'charter_enquiry', $ce_missing, 'submitter_only') + array(
        'tree' => $mn_record(array(
            'not_found_title' => lang('Charter enquiry not found'),
            'missing'         => $ce_missing,
            'list_title'      => lang('My Charter Enquiries'),
            'list_key'        => 'my_enquiries',
            'sent'            => lang('Thank you, your charter enquiry has reached us. The charter desk answers within a day with an offer; this page shows the status, and you can write to us below.'),
            'card'            => lang('Enquiry Card'),
            'eyebrow'         => lang('Charter Enquiry'),
            'title_sample'    => $ce_sample_boat,
            'title_field'     => 'boat',
            'status'          => $enquiry_statuses[0],
            'details'         => array_merge(
                $mn_detail(lang('Charter'), '', lang('Charter'), $mn_dates('start_date', 'end_date'), true),
                $mn_detail(lang('Guests'), '', lang('Guests'), $mn_bound('4', 'guests')),
                $mn_detail(lang('Sailing experience'), '', lang('Sailing experience'), $mn_bound($experience_choices[0], 'experience')),
                $mn_detail(lang('Best way to reach you'), '', lang('Contact by'), $mn_bound(lang('E-mail'), 'contact_method')),
                $mn_detail(lang('Skipper'), '', lang('Skipper'), $mn_person(), true),
                $mn_detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $mn_bound('ABC123', 'reference_code'))
            ),
            'words_label'     => lang('About your charter'),
            'words_sample'    => $ce_sample_note,
            'words_field'     => 'details',
            'note'            => lang('Other dates, another boat or a question about the offer? Write to us below; the offer comes here too, and every answer by e-mail.'),
        )),
    ),

    'enquiry_messages' => $mn_messages('enquiry_view',
        lang('No messages yet. The charter desk\'s offer comes here; you can write to us about your charter too.')),

    'my_enquiries_list' => $mn_list('my_enquiries', 'charter_enquiry', 'enquiry_view', $ce_row(),
        lang('No charter enquiries yet. Choose a boat and ask us about it.')),

    'staff_enquiries_list' => $mn_list('staff_enquiries', 'charter_enquiry', 'enquiry_view', $ce_row(true),
        lang('No charter enquiries yet.'),
        array('first_name', 'last_name', 'email', 'boat', 'experience', 'status'),
        lang('Search by skipper, e-mail, boat, experience or status...')),

    'email_enquiry_admin' => $mn_detail_config('email_enquiry_new', 'charter_enquiry', lang('The charter enquiry could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new charter enquiry')),
            $mail_para(lang('It came from the charter enquiry form. Check the boat and the dates, send the offer under the enquiry and set its status; what you write there reaches the skipper by e-mail.')),
            $ce_mail_enquiry(),
            $mn_mail_person(lang('About your charter'), $ce_sample_note, 'details'),
            $mail_button(lang('Open the enquiry'), '{{page:enquiry_view}}?r=^^reference_code^^'),
            $mn_mail_reference(),
        )),
    ),
    'email_enquiry_client' => $mn_detail_config('email_enquiry_received', 'charter_enquiry', lang('The charter enquiry could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your charter enquiry')),
            $mail_greeting(),
            $mail_para(lang('Thank you for thinking of us for your charter. The charter desk answers within a day with an offer for the boat and the dates, on the enquiry\'s page and by e-mail.')),
            $ce_mail_enquiry(),
            $mail_para(lang('A change or a question? Write to us on the enquiry\'s page; everything about your charter stays there.')),
            $mail_button(lang('Open my enquiry'), '{{page:enquiry_view}}?r=^^reference_code^^'),
            $mn_mail_new_account(),
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
