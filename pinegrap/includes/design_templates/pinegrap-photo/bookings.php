<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Photography Studio", its shoot bookings: the booking
 * request, the request's own page with its messages, My Bookings and the
 * staff's list; the privacy page the consent boxes link, the e-mails of
 * the booking form and the new message e-mail every thread of the site
 * sends, and the widgets that draw them. Included after parts.php and
 * mail.php in the same scope; returns array('pages' => ..., 'widgets' =>
 * ...).
 *
 * A booking is a request, never a reservation: a record of the Shoot
 * Bookings form. A client without an account gets one (auto-registration),
 * so the request's page and My Bookings show it at once. Its Status is an
 * office-use-only field the studio changes in the control panel; the date
 * held and the price are written under it as messages (the page's
 * comments), mailed to the client and to the studio, and finished files
 * may be attached to them. The template keeps no calendar for shoots on
 * location; a studio mini session at a free time is booked on book_online.
 *
 * The package and the kind of shoot start with what a link sends
 * (?package=, ?category=: the package's and the category's title, as
 * parts.php writes them).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$booking_pages = array();

// The sample request the canvas shows. Its date is words, never a date: a
// fixed day would read as a past season a year later.
$sample_package = $packages['family']['title'];
$sample_category = $categories['family']['title'];
$sample_date = lang('Preferred date');
$sample_location = lang('The park by the river, near the old boathouse');
$sample_notes = lang('Two children of four and seven and our dog. We would love relaxed photos playing outside rather than posed ones.');

// ── Page shells ─────────────────────────────────────────────────────────

// The side column of the booking page: what happens next, then the
// studio's promise and its links.
$booking_aside = function () use ($el, $heading, $link, $check_list, $studio) {
    $steps = array(
        lang('Your request opens its own page, and an account for you if you have none.'),
        lang('The studio answers there within a day, with a date and the price of your shoot.'),
        lang('Confirm it in a message; the status shows Confirmed once the date is yours.'),
        lang('Nothing is paid now: a deposit holds the date once we agree on it.'),
    );
    $items = array();
    foreach ($steps as $n => $text) {
        $items[] = $el('li', $n < count($steps) - 1 ? 'mb-2' : '', lang('Step'), array(), array('text' => $text));
    }
    return array(
        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('How It Works'), array(
            $heading('h2', lang('What happens next'), 'pph-display h5 mb-3'),
            $el('ol', 'small text-body-secondary ps-3 mb-0', lang('List'), $items),
        )),
        $el('div', 'p-4 rounded-4 border', lang('Good to know'), array(
            $heading('h2', lang('Good to know'), 'pph-display h5 mb-3'),
            $check_list(array(
                $studio['hours'],
                $studio['promise'],
                lang('A date can be moved once for free up to a week before the shoot.'),
            ), 'list-unstyled small mb-3'),
            $el('div', 'd-flex flex-column gap-1', lang('Links'), array(
                $link(lang('See the Packages'), '{{page:packages}}', 'fw-semibold link-primary text-decoration-none small'),
                $link(lang('Book a Studio Mini Session'), '{{page:book_online}}', 'fw-semibold link-primary text-decoration-none small'),
            )),
        )),
    );
};

// ── Pages ───────────────────────────────────────────────────────────────

// The booking request. What is sent opens the request's own page.
$booking_pages[] = array(
    'key'              => 'book',
    'name'             => lang('book'),
    'folder'           => 'public',
    'title'            => lang('Book a Shoot'),
    'meta_description' => lang('Tell us about the shoot you have in mind: the studio answers within a day with a date and the price.'),
    'form'             => array(
        'form_name'            => lang('Shoot Bookings'),
        'confirmation_message' => lang('Thank you, your booking request has reached us. The studio answers within a day.'),
        'confirmation_page_id' => '{{tab:booking_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New shoot booking request: ^^package^^'),
        'notify_page_id'       => '{{tab:email_booking_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your booking request'),
        'confirm_page_id'      => '{{tab:email_booking_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Book a Shoot'),
            lang('Tell us about the shoot you have in mind. The studio answers within a day with a date and the price; nothing is paid now.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-4', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('book_form'))),
                        )),
                    ), '', '8'),
                    $col($booking_aside(), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// One booking request: the request, its status and the messages under it.
// The studio may attach finished files to its messages.
$booking_pages[] = array(
    'key'      => 'booking_view',
    'name'     => lang('booking-request'),
    'folder'   => 'registration',
    'title'    => lang('Booking Request'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                        'attachments' => true,
                        'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new message about your booking request'),
                        'notify_email' => '{{site_email}}'),
    'tree'     => $page(array(
        $widget('booking_detail'),
        $comments_section(lang('Messages'), 'booking_messages'),
    ), false),
);

// My Bookings: the client's own requests (for the staff, everybody's).
$booking_pages[] = array(
    'key'     => 'my_bookings',
    'name'    => lang('my-bookings'),
    'folder'  => 'registration',
    'title'   => lang('My Bookings'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('My Bookings'), lang('Your shoot booking requests, their status and the studio\'s answers, newest first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('my_bookings_list')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('New Request'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-camera', 'text-primary'),
                                $span(lang('Another shoot?'), '', lang('Text')),
                            )),
                            $para(lang('Ask for another date or another package; your details are filled in for you.'), 'small text-body-secondary mb-3'),
                            $link(lang('Book a Shoot'), '{{page:book}}', 'btn btn-sm btn-primary rounded-pill px-3'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// The staff's list of every booking request, newest first, with a search.
$booking_pages[] = array(
    'key'     => 'staff_bookings',
    'name'    => lang('all-booking-requests'),
    'folder'  => 'private',
    'title'   => lang('Shoot Bookings'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $staff_page(array(
        $page_header(lang('Shoot Bookings'), lang('Every shoot booking request, newest first: answer with a date and the price, set the status and write to the client under each.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array($widget('staff_bookings_list'))),
        )),
    )),
);

// The privacy notice the consent boxes and the footer link to: sample text
// with a note to adapt it.
$booking_pages[] = array(
    'key'              => 'privacy',
    'name'             => lang('privacy-policy'),
    'folder'           => 'public',
    'title'            => lang('Privacy Policy'),
    'meta_description' => lang('What the studio keeps about you and your photos when you book a shoot, choose your photos or buy a voucher, why, and what you can ask of us.'),
    'tree'             => $page(array(
        $page_header(lang('Privacy Policy'), lang('What the studio keeps about you and your photos when you book a shoot, choose your photos or buy a voucher, why, and what you can ask of us.')),
        $el('section', 'py-5', lang('Your Data'), array(
            $container(array($row(array($col(array(
                $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Sample Text Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Sample text: adapt it to your company and to the data protection law that applies to you before you publish.'), '', lang('Text')),
                ), array('_attrs' => array($attr('role', 'note')))),
                $heading('h2', lang('Your data'), 'pph-display h4 mb-3'),
                $para(lang('When you book a shoot, choose your photos, buy a gift voucher, join the newsletter or open an account, we keep your name, your contact details, the date and what you tell us about the shoot, to answer you, plan the shoot and deliver your photos.'), 'mb-3'),
                $heading('h2', lang('Your photos'), 'pph-display h4 mb-3'),
                $para(lang('Your gallery is private: only you, and the people you ask us to add, can open it after signing in. We show a photo of you in our portfolio or on social media only with your consent, and remove it when you ask.'), 'mb-3'),
                $para(lang('We keep your data as long as the law requires, never sell it and never give it to advertisers. You can see and change your account data from My Account, and ask us about the rest through the contact page.'), 'mb-0'),
            ), '', '8'))))),
        ), $searchable('ph-your-data', lang('privacy, personal data, photos, consent, KVKK, GDPR, delete my data'))),
    )),
);

// The e-mails: what the studio receives and the reply to the client, and
// the new message e-mail.
$booking_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_booking_new',
    'name'  => lang('email-new-booking-request'),
    'title' => lang('New Booking Request E-mail'),
    'tree'  => $mail_page(array($widget('email_booking_admin'))),
));
$booking_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_booking_received',
    'name'  => lang('email-booking-request-received'),
    'title' => lang('Booking Request Received E-mail'),
    'tree'  => $mail_page(array($widget('email_booking_client'))),
));
// A new message or reply: sent to the client of a booking request or a
// proof selection, the sender of a conversation and the visitors watching
// a page. Its Comments widget draws the comment in the card of mail.php;
// the System region after it keeps the fixed comment for an e-mail the
// widget does not draw.
$booking_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: your booking request, your photo selection, a conversation or a Photo Journal post. You can read it below and answer it on the page.')),
        $widget('email_comment'),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));

// ── Widget pieces ───────────────────────────────────────────────────────

// The client's name.
$client_name = function () use ($span, $bind) {
    return array(
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
    );
};
// The shoot's date and time of day.
$shoot_when = function () use ($span, $bind, $sample_date, $time_choices) {
    return array(
        $span($sample_date, '', lang('Preferred date'), $bind('shoot_date')),
        $span(' · ', '', lang('Text')),
        $span($time_choices[2], '', lang('Time of day'), $bind('time_of_day')),
    );
};

// A booking request in a list: the package, the status, the date, the
// place and the people, linked to its page. The staff's row starts with
// the client's name and adds the phone; the client's own adds the
// messages.
$booking_row = function ($staff = false) use ($el, $span, $icon, $bind, $client_name, $shoot_when, $sample_package, $place_choices, $people_choices, $booking_statuses) {
    $item = function ($icon_name, $name, $children) use ($el, $icon) {
        return $el('span', 'd-inline-flex align-items-center gap-1', $name, array_merge(array($icon($icon_name)), $children));
    };
    $items = array(
        $item('bi-calendar-event', lang('Shoot date'), $shoot_when()),
        $item('bi-geo-alt', lang('Where'), array($span($place_choices[1][1], '', lang('Where'), $bind('place')))),
        $item('bi-people', lang('People'), array($span($people_choices[2], '', lang('People'), $bind('people')))),
    );
    if ($staff) {
        $items[] = $item('bi-telephone', lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone'))));
        $title = $el('span', 'fw-semibold', lang('Client'), array_merge($client_name(), array(
            $span(' · ', 'text-body-secondary', lang('Text')),
            $span($sample_package, 'text-body-secondary fw-normal', lang('Package'), $bind('package')),
        )));
    } else {
        $items[] = $item('bi-chat-left-text', lang('Messages'), array($span('0', '', lang('Reply Count'), $bind('number_of_comments'))));
        $title = $span($sample_package, 'fw-semibold', lang('Package'), $bind('package'));
    }
    return $el('a', 'list-group-item list-group-item-action p-3', lang('Booking Request'), array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $title,
            $span($booking_statuses[0], 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
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

// The lines of a booking request, for its e-mails.
$mail_booking = function () use ($mail_box, $mail_line, $span, $bind, $shoot_when, $sample_package, $sample_category, $sample_location, $place_choices, $people_choices) {
    return $mail_box(array(
        $mail_line(lang('Package'), array($span($sample_package, '', lang('Package'), $bind('package')))),
        $mail_line(lang('Kind of shoot'), array($span($sample_category, '', lang('Kind of shoot'), $bind('category')))),
        $mail_line(lang('Shoot date'), $shoot_when()),
        $mail_line(lang('Where'), array($span($place_choices[1][1], '', lang('Where'), $bind('place')))),
        $mail_line(lang('Location'), array($span($sample_location, '', lang('Location'), $bind('location')))),
        $mail_line(lang('People'), array($span($people_choices[2], '', lang('People'), $bind('people')))),
    ), lang('Booking Request'));
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
    ), array_merge($mail_style('border-top:1px solid #dde3ea;padding-top:16px'), $show_when('has_new_account')));
};
// The client's lines of the staff e-mail.
$mail_client = function () use ($mail_box, $mail_line, $span, $bind, $client_name, $sample_notes) {
    return $mail_box(array(
        $mail_line(lang('Client'), $client_name()),
        $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
        $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
        $mail_line(lang('Notes'), array($span($sample_notes, '', lang('Notes'), $bind('notes')))),
    ), lang('Client'));
};
// The form detail settings of an e-mail page's widget (any reader: the
// e-mail is drawn for the submission just sent) and of the request page's
// widget (the client's own, and the staff's).
$detail_widget = function ($page_key, $missing, $access) {
    return array(
        'page'   => $page_key,
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:book}}',
            'access_control'      => $access,
            'not_found_message'   => $missing,
        ),
    );
};
// A list of booking requests: the client's own (the staff see them all),
// or the staff's with a search.
$booking_list = function ($page_key, $staff, $empty) use ($widget_root, $el, $loop, $booking_row) {
    $config = array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:book}}',
        'detail_page_id'      => '{{tab:booking_view}}',
        'items_per_page'      => 20,
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
    );
    if ($staff) {
        $config += array(
            'search_enabled' => true,
            'search_fields'  => array('first_name', 'last_name', 'email', 'package', 'category', 'location', 'status'),
            'search_width'   => 'half',
            'search_label'   => lang('Search by client, e-mail, package, kind of shoot, location or status...'),
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
            $el('div', 'list-group', lang('Request List'), array($loop(array($booking_row($staff))))),
        )),
    );
};

$booking_missing = lang('This booking request could not be found, or it is not yours to see.');
$not_before_today = array('rules' => array('min_date' => 'today'));

$booking_widgets = array(

    // The booking request: the fields are the page's form. The package and
    // the kind of shoot start with what a link sends (?package=,
    // ?category=). The Status is the studio's (office use only): every
    // request starts as Request received.
    'book_form' => array(
        'page'   => 'book',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('Your shoot')),
            $row(array(
                $col(array($field('ph-bk-package', lang('Package'), $select('ph-bk-package', 'package', lang('Choose a package…'), $booking_package_choices, true, '', array('url_param' => 'package')), 'mb-3',
                    lang('Not sure? Choose "Not decided yet" and we suggest one.'))), '6'),
                $col(array($field('ph-bk-category', lang('Kind of shoot'), $select('ph-bk-category', 'category', lang('Choose a kind of shoot…'), $category_titles, false, '', array('url_param' => 'category')))), '6'),
                $col(array($field('ph-bk-date', lang('Preferred date'), $input('ph-bk-date', 'date', 'shoot_date', 'off', true, $not_before_today))), '6'),
                $col(array($field('ph-bk-time', lang('Time of day'), $select('ph-bk-time', 'time_of_day', '', $time_choices, false, $time_choices[3]))), '6'),
            ), '3'),
            $radios('ph-bk-place', 'place', lang('Where'), $place_choices, $place_choices[0][0]),
            $row(array(
                $col(array($field('ph-bk-location', lang('Location'), $input('ph-bk-location', 'text', 'location', 'off', false), 'mb-3',
                    lang('For a shoot on location: the place, or the area you have in mind.'))), '6'),
                $col(array($field('ph-bk-people', lang('People'), $select('ph-bk-people', 'people', '', $people_choices, false, $people_choices[0]))), '6'),
            ), '3'),
            $field('ph-bk-notes', lang('Notes'), $textarea('ph-bk-notes', 'notes', 3, false), 'mb-3',
                lang('The occasion, the people and pets, the style you love, anything we should know.')),
            $form_step(2, lang('How to reach you')),
            $row(array(
                $col(array($field('ph-bk-first-name', lang('First Name'), $input('ph-bk-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
                $col(array($field('ph-bk-last-name', lang('Last Name'), $input('ph-bk-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
                $col(array($field('ph-bk-email', lang('Email'), $input('ph-bk-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
                $col(array($field('ph-bk-phone', lang('Phone'), $input('ph-bk-phone', 'tel', 'phone', 'tel', true, array('contact_field' => 'mobile_phone')))), '6'),
            ), '3'),
            $field('ph-bk-status', lang('Status'), $select('ph-bk-status', 'status', '', $booking_statuses, false, $booking_statuses[0], array('office_use_only' => true))),
            $agree('ph-bk-consent', 'consent', lang('I agree that the studio keeps these details to answer my request and plan my shoot, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Request'), 'btn btn-primary btn-lg rounded-pill px-4'),
                $para(lang('The studio answers within a day. Nothing is paid now.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One booking request: the request, its status and what the client
    // wrote; the studio's answers (the date and the price among them)
    // follow as comments.
    'booking_detail' => $detail_widget('booking_view', $booking_missing, 'submitter_only') + array(
        'tree' => array('type' => 'root', 'props' => array(), 'children' => array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'text-center py-5', lang('Not Found'), array(
                            $icon('bi-calendar-x', 'display-5 text-body-tertiary'),
                            $heading('h2', lang('Booking request not found'), 'pph-display h3 mt-3'),
                            $para($booking_missing, 'text-body-secondary mb-4', $bind('not_found')),
                            $link(lang('My Bookings'), '{{page:my_bookings}}', 'btn btn-primary rounded-pill px-4'),
                        ), $show_when('record_not_found')),
                        $el('div', '', lang('Record'), array(
                            $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
                                $icon('bi-check-circle', 'mt-1'),
                                $span(lang('Thank you, your request has reached us. The studio answers here within a day with a date and the price; this page shows the status, and you can write to us below.'), '', lang('Text')),
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
                                    $span(lang('My Bookings'), '', lang('Link Text')),
                                ), array('href' => '{{page:my_bookings}}')),
                                $edit_button(lang('Change Status')),
                            )),
                            $el('div', 'card border-0 shadow-sm rounded-4 mb-4', lang('Booking Card'), array(
                                $el('div', 'card-body p-4', lang('Card Body'), array(
                                    $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
                                        $el('div', '', lang('Words'), array(
                                            $para(lang('Booking Request'), 'pph-eyebrow small text-uppercase fw-semibold text-primary mb-1'),
                                            $heading('h1', $sample_package, 'pph-display h3 mb-0', $bind('package')),
                                        )),
                                        $span($booking_statuses[0], 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
                                    )),
                                    $el('dl', 'row small mb-0', lang('Details'), array_merge(
                                        $detail(lang('Kind of shoot'), '', lang('Kind of shoot'), $bound($sample_category, 'category')),
                                        $detail(lang('Shoot date'), '', lang('Shoot date'), $shoot_when(), true),
                                        $detail(lang('Where'), '', lang('Where'), $bound($place_choices[1][1], 'place')),
                                        $detail(lang('Location'), '', lang('Location'), $bound($sample_location, 'location')),
                                        $detail(lang('People'), '', lang('People'), $bound($people_choices[2], 'people')),
                                        $detail(lang('Client'), '', lang('Client'), $client_name(), true),
                                        $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
                                    )),
                                )),
                            )),
                            $el('div', 'pg-hide-if-empty p-4 rounded-4 bg-body-tertiary mb-4', lang('Notes'), array(
                                $para(lang('Notes'), 'small fw-semibold text-body-secondary mb-2'),
                                $el('div', 'pg-field-value lh-lg', lang('Value'), array(), $bound($sample_notes, 'notes')),
                            )),
                            $el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
                                $icon('bi-chat-left-text', 'text-primary mt-1'),
                                $span(lang('Another date, a question about the price or an idea for the shoot? Write to us below; we answer here and by e-mail.'), '', lang('Text')),
                            )),
                        ), $show_when('record_found')),
                    ), '', '8'),
                ), '', 'center'),
            ), 'py-5'),
        )),
    ),

    // The messages under a request: the page's own Comments widget, shown
    // only to whoever may read the request above it. Oldest first, all on
    // one page. No Reply, Share or follow box: the client is e-mailed every
    // answer and the studio every message.
    'booking_messages' => array(
        'page'   => 'booking_view',
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
            'empty'     => lang('No messages yet. The studio\'s answer comes here; you can write to us about your shoot too.'),
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

    'my_bookings_list' => $booking_list('my_bookings', false,
        lang('No booking requests yet. Tell us about the shoot you have in mind.')),

    'staff_bookings_list' => $booking_list('staff_bookings', true, lang('No booking requests yet.')),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_booking_admin' => $detail_widget('email_booking_new', lang('The booking request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new shoot booking request')),
            $mail_para(lang('It came from the booking form. Answer under the request on its page with a date and the price, and set the status; what you write there reaches the client by e-mail.')),
            $mail_booking(),
            $mail_client(),
            $mail_button(lang('Open the request'), '{{page:booking_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_booking_client' => $detail_widget('email_booking_received', lang('The booking request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your booking request')),
            $mail_greeting(),
            $mail_para(lang('Thank you. The studio looks at your request and answers within a day with a date and the price, on the request\'s page and by e-mail. Nothing is paid now.')),
            $mail_booking(),
            $mail_para(lang('Another date or a question? Write to us on the request\'s page; everything about your shoot stays there.')),
            $mail_button(lang('Open my request'), '{{page:booking_view}}?r=^^reference_code^^'),
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

return array('pages' => $booking_pages, 'widgets' => $booking_widgets);
