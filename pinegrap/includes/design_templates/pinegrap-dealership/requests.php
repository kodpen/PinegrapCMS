<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Car Dealership", its test drives and trade-ins: the test
 * drive request, its own page with its messages, My Test Drives and the
 * staff's list; the trade-in valuation of the car a customer drives now,
 * its page, My Trade-ins and the staff's list; the privacy page the consent
 * boxes link, the e-mails of both forms and the new message e-mail, and the
 * widgets that draw them. Included after parts.php and mail.php in the same
 * scope; returns array('pages' => ..., 'widgets' => ...).
 *
 * A request is a record of its form (Test Drives, Trade-ins). A visitor
 * without an account gets one (auto-registration), so the request's page
 * and the My lists show it at once. Its Status is an office-use-only field
 * the staff change in the control panel; the messages under it are the
 * page's comments, mailed to the customer and to the dealer.
 *
 * Both forms' Vehicle starts with the car the vehicle page sends
 * (?vehicle=, the vehicle's title). The photos of a trade-in are kept in
 * the template's private folder: the record's page links them for whoever
 * may read the record.
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
        $el('div', 'p-4 rounded-3 border', lang('Showroom hours'), array(
            $heading('h2', lang('Showroom hours'), 'h5 fw-semibold mb-3'),
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
                            $link($new_link_text, '{{page:' . $new_key . '}}', 'btn btn-sm btn-primary px-3'),
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

// The comment settings of a request's page: messages with a file each,
// published at once, mailed to the customer and to the dealer.
$request_comments = function ($subject) {
    return array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                 'attachments' => true,
                 'email_page' => '{{tab:email_reply}}', 'email_subject' => $subject,
                 'notify_email' => '{{site_email}}');
};

// ── Pages ───────────────────────────────────────────────────────────────

// The test drive request. What is sent opens the test drive's own page.
$request_pages[] = array(
    'key'              => 'test_drive',
    'name'             => lang('book-a-test-drive'),
    'folder'           => 'public',
    'title'            => lang('Book a Test Drive'),
    'meta_description' => lang('Choose a car, a day and a time of day; we confirm within a working day and have the car ready and charged.'),
    'form'             => array(
        'form_name'            => lang('Test Drives'),
        'confirmation_message' => lang('Thank you, your test drive request has reached us. We confirm the time within a working day.'),
        'confirmation_page_id' => '{{tab:test_drive_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New test drive request: ^^vehicle^^'),
        'notify_page_id'       => '{{tab:email_test_drive_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your test drive request'),
        'confirm_page_id'      => '{{tab:email_test_drive_received}}',
    ),
    'tree'             => $request_page(lang('Book a Test Drive'),
        lang('Choose the car, a day and a time of day. We confirm within a working day and have the car ready when you arrive.'),
        'test_drive_form', array(
            lang('Your request opens its own page, and an account for you if you have none.'),
            lang('We confirm the day and time, or propose another, and keep the car for you.'),
            lang('Bring your driving licence; a drive takes about half an hour.'),
        )),
);

// One test drive: the request, its status and the messages under it.
$request_pages[] = array(
    'key'      => 'test_drive_view',
    'name'     => lang('test-drive'),
    'folder'   => 'registration',
    'title'    => lang('Test Drive'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => $request_comments(lang('There is a new message about your test drive')),
    'tree'     => $page(array(
        $widget('test_drive_detail'),
        $comments_section(lang('Messages'), 'test_drive_messages'),
    ), false),
);

// My Test Drives: the customer's test drive requests (for the staff,
// everybody's).
$request_pages[] = array(
    'key'     => 'my_test_drives',
    'name'    => lang('my-test-drives'),
    'folder'  => 'registration',
    'title'   => lang('My Test Drives'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $my_page(lang('My Test Drives'), lang('Your test drive requests, their status and our messages, newest first.'),
        'my_test_drives_list', lang('Another car?'), lang('Open its page and press "Book a Test Drive"; your details are filled in for you.'),
        lang('See the cars in stock'), 'vehicles', 'bi-car-front'),
);

// The staff's list of every test drive request, newest first, with a search.
$request_pages[] = array(
    'key'     => 'staff_test_drives',
    'name'    => lang('all-test-drives'),
    'folder'  => 'private',
    'title'   => lang('All Test Drives'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $staff_list_page(lang('All Test Drives'), lang('Every test drive request, newest first: confirm the time, set the status and answer under each.'), 'staff_test_drives_list'),
);

// The trade-in valuation of the car a customer drives now.
$request_pages[] = array(
    'key'              => 'tradein',
    'name'             => lang('value-my-car'),
    'folder'           => 'public',
    'title'            => lang('Value My Car'),
    'meta_description' => lang('Tell us about the car you drive now and send a few photos; a written trade-in offer follows within a working day.'),
    'form'             => array(
        'form_name'            => lang('Trade-ins'),
        'confirmation_message' => lang('Thank you, your trade-in request has reached us. A first offer follows within a working day.'),
        'confirmation_page_id' => '{{tab:tradein_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New trade-in request: ^^make^^ ^^model^^'),
        'notify_page_id'       => '{{tab:email_tradein_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your trade-in request'),
        'confirm_page_id'      => '{{tab:email_tradein_received}}',
    ),
    'tree'             => $request_page(lang('Value My Car'),
        lang('Tell us about the car you drive now. A first offer follows in writing within a working day, and it is taken off the price of your next car.'),
        'tradein_form', array(
            lang('Your request opens its own page, and an account for you if you have none.'),
            lang('We send a first offer from the details and photos, then check the car when you bring it.'),
            lang('The final offer comes in writing on your request\'s page and is taken off the price of your next car.'),
        )),
);

// One trade-in: the request, its status and the messages under it.
$request_pages[] = array(
    'key'      => 'tradein_view',
    'name'     => lang('trade-in'),
    'folder'   => 'registration',
    'title'    => lang('Trade-in'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => $request_comments(lang('There is a new message about your trade-in')),
    'tree'     => $page(array(
        $widget('tradein_detail'),
        $comments_section(lang('Messages'), 'tradein_messages'),
    ), false),
);

// My Trade-ins: the customer's trade-in requests (for the staff,
// everybody's).
$request_pages[] = array(
    'key'     => 'my_tradeins',
    'name'    => lang('my-trade-ins'),
    'folder'  => 'registration',
    'title'   => lang('My Trade-ins'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $my_page(lang('My Trade-ins'), lang('Your trade-in requests, their status and our offers, newest first.'),
        'my_tradeins_list', lang('Another car to sell?'), lang('Ask for an offer on it; your details are filled in for you.'),
        lang('Value My Car'), 'tradein', 'bi-arrow-left-right'),
);

// The staff's list of every trade-in request, newest first, with a search.
$request_pages[] = array(
    'key'     => 'staff_tradeins',
    'name'    => lang('all-trade-ins'),
    'folder'  => 'private',
    'title'   => lang('All Trade-ins'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $staff_list_page(lang('All Trade-ins'), lang('Every trade-in request, newest first: send the offer, set the status and answer under each.'), 'staff_tradeins_list'),
);

// The privacy notice the consent boxes link to: sample text with a note to
// adapt it.
$request_pages[] = array(
    'key'              => 'privacy',
    'name'             => lang('privacy-policy'),
    'folder'           => 'public',
    'title'            => lang('Privacy Policy'),
    'meta_description' => lang('What the dealer keeps about you, why, and what you can ask of us.'),
    'tree'             => $page(array(
        $page_header(lang('Privacy Policy'), lang('What the dealer keeps about you, why, and what you can ask of us.')),
        $el('section', 'py-5', lang('Your Data'), array(
            $container(array($row(array($col(array(
                $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Sample Text Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Sample text: adapt it to your dealership and to the data protection law that applies to you before you publish.'), '', lang('Text')),
                ), array('_attrs' => array($attr('role', 'note')))),
                $heading('h2', lang('Your data'), 'h4 fw-semibold mb-3'),
                $para(lang('When you book a test drive, ask for a trade-in offer or for finance, sign up for the newsletter or open an account, we keep your name, your contact details, what you tell us about your car and the photos you send, to arrange the drive, make the offer and answer you.'), 'mb-3'),
                $para(lang('A finance request asks only for bands such as the deposit and the income; we never ask for an ID or a bank number online, and the adviser checks the documents in person.'), 'mb-3'),
                $para(lang('We keep your data as long as the law requires, never sell it and never give it to advertisers. You can see and change your account data from My Account, and ask us about the rest through the contact page.'), 'mb-0'),
            ), '', '8'))))),
        ), $searchable('your-data', lang('privacy, personal data, KVKK, GDPR, delete my data'))),
    )),
);

// The e-mails: what the staff receive and the reply to the sender, for each
// form, and the new message e-mail.
$request_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_test_drive_new',
    'name'  => lang('email-new-test-drive'),
    'title' => lang('New Test Drive E-mail'),
    'tree'  => $mail_page(array($widget('email_test_drive_admin'))),
));
$request_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_test_drive_received',
    'name'  => lang('email-test-drive-received'),
    'title' => lang('Test Drive Received E-mail'),
    'tree'  => $mail_page(array($widget('email_test_drive_client'))),
));
$request_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_tradein_new',
    'name'  => lang('email-new-trade-in'),
    'title' => lang('New Trade-in E-mail'),
    'tree'  => $mail_page(array($widget('email_tradein_admin'))),
));
$request_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_tradein_received',
    'name'  => lang('email-trade-in-received'),
    'title' => lang('Trade-in Received E-mail'),
    'tree'  => $mail_page(array($widget('email_tradein_client'))),
));
// A new message or reply: sent to the customer of a test drive, a trade-in
// or a finance request, the sender of a conversation and the visitors
// watching a page. Its Comments widget draws the comment in the card of
// mail.php; the System region after it keeps the fixed comment for an
// e-mail the widget does not draw.
$request_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: your test drive, your trade-in, your finance request, a conversation or an article. You can read it below and answer it on the page.')),
        $widget('email_comment'),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));

// ── Widget pieces ───────────────────────────────────────────────────────

// The customer's name.
$person_name = function () use ($span, $bind) {
    return array(
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
    );
};

// A request in a list: its title field, the status, the details, linked to
// its page. $details: array(icon, label, sample, field) rows.
$request_row = function ($name, $title_field, $title_sample, $status_sample, $details, $staff) use ($el, $span, $icon, $bind, $person_name) {
    $items = array();
    foreach ($details as $d) {
        $items[] = $el('span', 'd-inline-flex align-items-center gap-1', $d[1], array($icon($d[0]), $span($d[2], '', $d[1], $bind($d[3]))));
    }
    $items[] = $el('span', 'd-inline-flex align-items-center gap-1', lang('Sent'), array($icon('bi-calendar3'), $span(lang('Date'), '', lang('Date'), $bind('submitted_date_and_time'))));
    if ($staff) {
        $items[] = $el('span', 'd-inline-flex align-items-center gap-1', lang('Phone'), array($icon('bi-telephone'), $span('+90 555 000 00 00', '', lang('Phone'), $bind('phone'))));
        $title = $el('span', 'fw-semibold', lang('Customer'), array_merge($person_name(), array(
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
            $span($status_sample, 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
        )),
        $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), $items),
    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view')));
};

// The samples the canvas shows: the car of a test drive, the car offered
// in a trade-in.
$sample_drive_vehicle = $vehicle_title('velo-volt');
$sample_wanted_vehicle = $vehicle_title('norda-tour');
$sample_tradein = array('make' => 'Kestrel', 'model' => 'Sport 1.5', 'year' => '2019', 'mileage' => '86000');

$test_drive_row = function ($staff = false) use ($request_row, $sample_drive_vehicle, $time_windows, $test_drive_statuses) {
    return $request_row(lang('Test Drive'), 'vehicle', $sample_drive_vehicle, $test_drive_statuses[0], array(
        array('bi-calendar-event', lang('Day'), '2026-12-05', 'drive_date'),
        array('bi-clock', lang('Time of day'), $time_windows[0], 'preferred_time'),
    ), $staff);
};
$tradein_row = function ($staff = false) use ($request_row, $sample_tradein, $tradein_statuses) {
    return $request_row(lang('Trade-in'), 'model', $sample_tradein['model'], $tradein_statuses[0], array(
        array('bi-car-front', lang('Make'), $sample_tradein['make'], 'make'),
        array('bi-calendar3', lang('Year'), $sample_tradein['year'], 'year'),
        array('bi-speedometer2', lang('Mileage'), $sample_tradein['mileage'], 'mileage'),
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

// A record's page (a test drive, a trade-in): the not-found notice, the
// sent notice, the new account notice, the way back and the Edit button,
// then the record's own parts.
$record_view = function ($icon_name, $missing_title, $missing_text, $back_key, $back_text, $sent_text, $parts) use ($el, $heading, $para, $link, $span, $icon, $attr, $bind, $show_when, $container, $row, $col, $edit_button) {
    return array('type' => 'root', 'props' => array(), 'children' => array(
        $container(array(
            $row(array(
                $col(array(
                    $el('div', 'text-center py-5', lang('Not Found'), array(
                        $icon($icon_name, 'display-5 text-body-tertiary'),
                        $heading('h2', $missing_title, 'h3 fw-semibold mt-3'),
                        $para($missing_text, 'text-body-secondary mb-4', $bind('not_found')),
                        $link($back_text, '{{page:' . $back_key . '}}', 'btn btn-primary px-4'),
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
$record_title = function ($kind, $title_nodes, $status_sample) use ($el, $para, $span, $bind) {
    return $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
        $el('div', '', lang('Words'), array(
            $para($kind, 'small text-uppercase fw-semibold text-primary mb-1'),
            $el('h1', 'h3 fw-semibold mb-0', lang('Heading'), $title_nodes),
        )),
        $span($status_sample, 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
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
// with a file each. No Reply, Share or follow box: the customer is e-mailed
// every answer and the dealer every message.
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

// A list of requests: the customer's own (the staff see them all), or the
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

// The contact part of both forms: how to reach the customer, the name, the
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
            $submit($send, 'btn btn-primary btn-lg px-4'),
            $para($promise, 'small text-body-secondary mb-0'),
        )),
    );
};

// The lines of a test drive and of a trade-in, for their e-mails.
$mail_test_drive = function () use ($mail_box, $mail_line, $span, $bind, $sample_drive_vehicle, $time_windows) {
    return $mail_box(array(
        $mail_line(lang('Vehicle'), array($span($sample_drive_vehicle, '', lang('Vehicle'), $bind('vehicle')))),
        $mail_line(lang('Day'), array($span('2026-12-05', '', lang('Day'), $bind('drive_date')))),
        $mail_line(lang('Time of day'), array($span($time_windows[0], '', lang('Time'), $bind('preferred_time')))),
        $mail_line(lang('Contact by'), array($span(lang('Phone'), '', lang('Contact by'), $bind('contact_method')))),
    ), lang('Test Drive'));
};
$mail_tradein = function () use ($mail_box, $mail_line, $span, $bind, $sample_tradein, $tradein_conditions, $service_history_choices, $sample_wanted_vehicle) {
    return $mail_box(array(
        $mail_line(lang('Your car'), array(
            $span($sample_tradein['make'], '', lang('Make'), $bind('make')),
            $span(' ', '', lang('Text')),
            $span($sample_tradein['model'], '', lang('Model'), $bind('model')),
        )),
        $mail_line(lang('Year'), array($span($sample_tradein['year'], '', lang('Year'), $bind('year')))),
        $mail_line(lang('Mileage (km)'), array($span($sample_tradein['mileage'], '', lang('Mileage'), $bind('mileage')))),
        $mail_line(lang('Car condition'), array($span($tradein_conditions[1][1], '', lang('Car condition'), $bind('condition')))),
        $mail_line(lang('Service history'), array($span($service_history_choices[0], '', lang('Service history'), $bind('service_history')))),
        $mail_line(lang('Car you want'), array($span($sample_wanted_vehicle, '', lang('Vehicle'), $bind('vehicle')))),
    ), lang('Trade-in'));
};
$sample_drive_note = lang('Could we try the motorway too? My partner will come along.');
$sample_tradein_note = lang('Two keys, new brakes last spring and a small scratch on the rear bumper.');

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
    ), array_merge($mail_style('border-top:1px solid #dbeafe;padding-top:16px'), $show_when('has_new_account')));
};
// The customer's lines of a staff e-mail.
$mail_customer = function ($more = array()) use ($mail_box, $mail_line, $span, $bind, $person_name) {
    return $mail_box(array_merge(array(
        $mail_line(lang('Customer'), $person_name()),
        $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
        $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
    ), $more), lang('Customer'));
};
// The form detail settings of a widget: an e-mail page's reads the
// submission just sent (public), a request page's only the sender's own.
$detail_widget = function ($page, $form, $missing, $access) {
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

$test_drive_missing = lang('This test drive could not be found, or it is not yours to see.');
$tradein_missing = lang('This trade-in could not be found, or it is not yours to see.');

$request_widgets = array(

    // The test drive request: the fields are the page's form. The Vehicle
    // starts with the car the vehicle page sends (?vehicle=). The Status is
    // the staff's (office use only): every request starts as received.
    'test_drive_form' => array(
        'page'   => 'test_drive',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array_merge(array(
            $form_step(1, lang('The car and the time')),
            $field('dl-td-vehicle', lang('Vehicle'), $input('dl-td-vehicle', 'text', 'vehicle', 'off', true, array('url_param' => 'vehicle')), 'mb-3',
                lang('The car you would like to drive; its page\'s button fills it in.')),
            $row(array(
                $col(array($field('dl-td-date', lang('Day'), $input('dl-td-date', 'date', 'drive_date', 'off', true, array('rules' => array('min_date' => 'today'))))), '6'),
                $col(array($field('dl-td-time', lang('Time of day'), $select('dl-td-time', 'preferred_time', lang('Choose a time of day…'), $time_windows))), '6'),
            ), '3'),
            $field('dl-td-note', lang('Anything we should know?'), $textarea('dl-td-note', 'note', 3, false), 'mb-3',
                lang('Another day that suits you, a road you would like to try, or a car to trade in.')),
            $agree('dl-td-licence', 'licence', lang('I hold a full driving licence and will bring it to the test drive.')),
            $form_step(2, lang('How to reach you')),
        ), $contact_part('dl-td', $test_drive_statuses,
            lang('I agree that the dealer keeps these details to arrange the test drive and answer me, as its privacy notice explains.'),
            lang('Book the Test Drive'), lang('We confirm within a working day.')), array(
            $loop(array()),
        ))),
    ),

    // One test drive: the request, its status and what the customer wrote;
    // the messages follow as comments.
    'test_drive_detail' => $detail_widget('test_drive_view', 'test_drive', $test_drive_missing, 'submitter_only') + array(
        'tree' => $record_view('bi-calendar-x', lang('Test drive not found'), $test_drive_missing,
            'my_test_drives', lang('My Test Drives'),
            lang('Thank you, your request has reached us. We confirm the time within a working day; this page shows the status, and you can write to us below.'),
            array(
                $el('div', 'card border-0 shadow-sm rounded-3 mb-4', lang('Test Drive Card'), array(
                    $el('div', 'card-body p-4', lang('Card Body'), array(
                        $record_title(lang('Test Drive'), array($span($sample_drive_vehicle, '', lang('Vehicle'), $bind('vehicle'))), $test_drive_statuses[0]),
                        $el('dl', 'row small mb-0', lang('Details'), array_merge(
                            $detail(lang('Day'), '', lang('Day'), $bound('2026-12-05', 'drive_date')),
                            $detail(lang('Time of day'), '', lang('Time'), $bound($time_windows[0], 'preferred_time')),
                            $detail(lang('Best way to reach you'), '', lang('Contact by'), $bound(lang('Phone'), 'contact_method')),
                            $detail(lang('Customer'), '', lang('Customer'), $person_name(), true),
                            $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
                        )),
                    )),
                )),
                $record_text(lang('Anything we should know?'), 'note', $sample_drive_note),
                $record_note(lang('Another day suits you better, or a question about the car? Write to us below; we answer here and by e-mail.')),
            )),
    ),

    'test_drive_messages' => $request_messages('test_drive_view',
        lang('No messages yet. Write to us here about the test drive.'),
        lang('Write to the showroom'), lang('Sign in to write to the showroom.')),

    'my_test_drives_list' => $request_list('my_test_drives', 'test_drive', 'test_drive_view', $test_drive_row(),
        lang('No test drive requests yet. Open a car\'s page and press "Book a Test Drive".')),

    'staff_test_drives_list' => $request_list('staff_test_drives', 'test_drive', 'test_drive_view', $test_drive_row(true),
        lang('No test drive requests yet.'),
        array('first_name', 'last_name', 'vehicle', 'drive_date', 'status'),
        lang('Search by customer, car, day or status...')),

    // The trade-in request: the car the customer drives now, its photos
    // (kept private) and, from ?vehicle=, the car they want instead.
    'tradein_form' => array(
        'page'   => 'tradein',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array_merge(array(
            $form_step(1, lang('Your car')),
            $row(array(
                $col(array($field('dl-ti-make', lang('Make'), $input('dl-ti-make', 'text', 'make', 'off'))), '6'),
                $col(array($field('dl-ti-model', lang('Model'), $input('dl-ti-model', 'text', 'model', 'off'))), '6'),
                $col(array($field('dl-ti-year', lang('Year'), $input('dl-ti-year', 'number', 'year', 'off', true, array('kind' => 'number'), array($attr('min', '1950'), $attr('step', '1'))))), '6'),
                $col(array($field('dl-ti-mileage', lang('Mileage (km)'), $input('dl-ti-mileage', 'number', 'mileage', 'off', true, array('kind' => 'number'), array($attr('min', '0'), $attr('step', '1'))))), '6'),
                $col(array($field('dl-ti-fuel', lang('Fuel'), $select('dl-ti-fuel', 'fuel', lang('Choose a fuel…'), $fuel_titles, false))), '6'),
                $col(array($field('dl-ti-gearbox', lang('Gearbox'), $select('dl-ti-gearbox', 'transmission', lang('Choose a gearbox…'), $transmission_titles, false))), '6'),
            ), '3'),
            $radios('dl-ti-condition', 'condition', lang('Car condition'), $tradein_conditions, $tradein_conditions[1][0]),
            $field('dl-ti-history', lang('Service history'), $select('dl-ti-history', 'service_history', lang('Choose one…'), $service_history_choices, false)),
            $field('dl-ti-photos', lang('Photos of the car'), $file('dl-ti-photos', 'photos', 'private', 'image/*'), 'mb-3',
                lang('The front, the back, the inside and the dashboard with the mileage. More can follow under your request.')),
            $field('dl-ti-note', lang('Anything else about the car?'), $textarea('dl-ti-note', 'note', 3, false), 'mb-3',
                lang('Keys, recent repairs, damage, or money still owed on it.')),
            $form_step(2, lang('The car you want')),
            $field('dl-ti-vehicle', lang('Car you want'), $input('dl-ti-vehicle', 'text', 'vehicle', 'off', false, array('url_param' => 'vehicle')), 'mb-3',
                lang('A car from our stock, if you have chosen one; the offer is taken off its price.')),
            $form_step(3, lang('How to reach you')),
        ), $contact_part('dl-ti', $tradein_statuses,
            lang('I agree that the dealer keeps these details and photos to make an offer for my car and answer me, as its privacy notice explains.'),
            lang('Get an Offer'), lang('A first offer follows within a working day.')), array(
            $loop(array()),
        ))),
    ),

    // One trade-in: the request, its status and what the customer wrote;
    // the messages (the offer among them) follow as comments.
    'tradein_detail' => $detail_widget('tradein_view', 'tradein', $tradein_missing, 'submitter_only') + array(
        'tree' => $record_view('bi-car-front', lang('Trade-in not found'), $tradein_missing,
            'my_tradeins', lang('My Trade-ins'),
            lang('Thank you, your request has reached us. A first offer comes here within a working day; this page shows the status, and you can write to us below.'),
            array(
                $el('div', 'card border-0 shadow-sm rounded-3 mb-4', lang('Trade-in Card'), array(
                    $el('div', 'card-body p-4', lang('Card Body'), array(
                        $record_title(lang('Trade-in'), array(
                            $span($sample_tradein['make'], '', lang('Make'), $bind('make')),
                            $span(' ', '', lang('Text')),
                            $span($sample_tradein['model'], '', lang('Model'), $bind('model')),
                        ), $tradein_statuses[0]),
                        $el('dl', 'row small mb-0', lang('Details'), array_merge(
                            $detail(lang('Year'), '', lang('Year'), $bound($sample_tradein['year'], 'year')),
                            $detail(lang('Mileage (km)'), '', lang('Mileage'), $bound($sample_tradein['mileage'], 'mileage')),
                            $detail(lang('Fuel'), '', lang('Fuel'), $bound($fuel_titles[0], 'fuel')),
                            $detail(lang('Gearbox'), '', lang('Gearbox'), $bound($transmission_titles[0], 'transmission')),
                            $detail(lang('Car condition'), '', lang('Car condition'), $bound($tradein_conditions[1][1], 'condition')),
                            $detail(lang('Service history'), '', lang('Service history'), $bound($service_history_choices[0], 'service_history')),
                            $detail(lang('Car you want'), '', lang('Vehicle'), $bound($sample_wanted_vehicle, 'vehicle')),
                            $detail(lang('Customer'), '', lang('Customer'), $person_name(), true),
                            $detail(lang('Photos of the car'), 'text-break', lang('Photos'), $file_link('photos', 'car-front.jpg'), true),
                            $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
                        )),
                    )),
                )),
                $record_text(lang('Anything else about the car?'), 'note', $sample_tradein_note),
                $record_note(lang('More photos or a question? Write to us below; the written offer comes here too.')),
            )),
    ),

    'tradein_messages' => $request_messages('tradein_view',
        lang('No messages yet. Write to us here about your car.'),
        lang('Write to the showroom'), lang('Sign in to write to the showroom.')),

    'my_tradeins_list' => $request_list('my_tradeins', 'tradein', 'tradein_view', $tradein_row(),
        lang('No trade-in requests yet. Tell us about your car for a free offer.')),

    'staff_tradeins_list' => $request_list('staff_tradeins', 'tradein', 'tradein_view', $tradein_row(true),
        lang('No trade-in requests yet.'),
        array('first_name', 'last_name', 'make', 'model', 'vehicle', 'status'),
        lang('Search by customer, make, model, car wanted or status...')),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_test_drive_admin' => $detail_widget('email_test_drive_new', 'test_drive', lang('The test drive request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new test drive request')),
            $mail_para(lang('It came from the test drive form. Confirm the time with the customer and keep the car ready, then set the status on the request\'s page; what you write under it reaches the customer by e-mail.')),
            $mail_test_drive(),
            $mail_customer(array(
                $mail_line(lang('Anything we should know?'), array($span($sample_drive_note, '', lang('Note'), $bind('note')))),
            )),
            $mail_button(lang('Open the request'), '{{page:test_drive_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_test_drive_client' => $detail_widget('email_test_drive_received', 'test_drive', lang('The test drive request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your test drive request')),
            $mail_greeting(),
            $mail_para(lang('Thank you for your interest in the car. We confirm the day and time within a working day and have the car ready when you arrive. Please bring your driving licence.')),
            $mail_test_drive(),
            $mail_para(lang('Another day suits you better, or a question? Write to us on the request\'s page; everything about your test drive stays there.')),
            $mail_button(lang('Open my request'), '{{page:test_drive_view}}?r=^^reference_code^^'),
            $mail_new_account(),
        )),
    ),
    'email_tradein_admin' => $detail_widget('email_tradein_new', 'tradein', lang('The trade-in request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new trade-in request')),
            $mail_para(lang('It came from the trade-in form. Look at the photos, send a first offer under the request and book the inspection; what you write under it reaches the customer by e-mail.')),
            $mail_tradein(),
            $mail_customer(array(
                $mail_line(lang('Anything else about the car?'), array($span($sample_tradein_note, '', lang('Note'), $bind('note')))),
            )),
            $mail_button(lang('Open the request'), '{{page:tradein_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_tradein_client' => $detail_widget('email_tradein_received', 'tradein', lang('The trade-in request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your trade-in request')),
            $mail_greeting(),
            $mail_para(lang('Thank you for thinking of us. A first offer follows on your request\'s page within a working day; the final one comes after we have seen the car.')),
            $mail_tradein(),
            $mail_para(lang('More photos or a question? Write to us on the request\'s page; the offer comes there too.')),
            $mail_button(lang('Open my request'), '{{page:tradein_view}}?r=^^reference_code^^'),
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
