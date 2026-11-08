<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Craft Brewery", its tours: the tours page (the three
 * tours with their prices, how a tour goes, what to know before coming
 * and the private tastings), the tour booking request, the request's own
 * page with its messages, My Tour Bookings and the staff's list, the
 * private tastings booked online with the booking widget, the privacy
 * page the consent boxes link, the e-mails of the booking form and the
 * new message e-mail every thread of the site sends, and the widgets that
 * draw them. Included after parts.php and mail.php in the same scope;
 * returns array('pages' => ..., 'widgets' => ...).
 *
 * A tour booking is a request, never a held place: a record of the Tour
 * Bookings form. A visitor without an account gets one
 * (auto-registration), so the request's page and My Tour Bookings show it
 * at once. Its Status is an office-use-only field the brewery's staff
 * change in the control panel; the answer is written under it as
 * messages (the page's comments), mailed to the visitor and to the
 * brewery. The tour starts with what a link sends (?tour=), and every
 * request confirms that each taster is of legal drinking age.
 *
 * A private tasting is booked at a free time with the booking widget in
 * the editor's starter layout; its services and staff come from Settings
 * › Bookings.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$tour_pages = array();

// The sample request the canvas shows. Its date is words, never a date: a
// fixed day would read as a past season a year later.
$sample_tour = $tour_titles[0];
$sample_tour_date = lang('Tour date');
$sample_tour_notes = lang('Two of us drink no alcohol; could they taste the soft drinks we brew instead?');

// ── Page shells ─────────────────────────────────────────────────────────

// The taproom's opening hours in a short list, beside a form or the
// booking widget.
$tour_hours = function () use ($el, $span, $hours) {
    $items = array();
    foreach ($hours as $h) {
        $items[] = $el('li', 'd-flex justify-content-between gap-3 py-1', $h['days'], array(
            $span($h['days'], '', lang('Days')),
            $span($h['time'], 'fw-semibold', lang('Hours')),
        ));
    }
    return $el('ul', 'list-unstyled small mb-0', lang('Opening Hours'), $items);
};

// What every visitor should know before a tour.
$tour_rules = array(
    lang('Every taster must be of legal drinking age; bring an ID.'),
    lang('Wear closed shoes: the brewhouse floor is wet and has steps.'),
    lang('Not drinking? Join the walk and taste our soft drinks instead.'),
    lang('A group larger than twelve gets a tour of its own; tell us in the notes.'),
);

// The side column of the booking request: what happens next, then what to
// know and the tours address.
$tour_aside = function () use ($el, $heading, $link, $check_list, $tour_rules, $brewery) {
    $steps = array(
        lang('Your request opens its own page, and an account for you if you have none.'),
        lang('We answer there within a working day: the time is confirmed, or we offer another one.'),
        lang('Come ten minutes early to the taproom; the tour starts at the bar.'),
        lang('Nothing is paid now: you pay per person on the day.'),
    );
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
            $check_list($tour_rules, 'list-unstyled small mb-3'),
            $link($brewery['tours'], 'mailto:' . $brewery['tours'], 'fw-semibold link-primary text-decoration-none small'),
        )),
    );
};

// ── Pages ───────────────────────────────────────────────────────────────

// The tours: the three tours with their prices, a tour step by step, what
// to know, and the private tastings beside them.
$tour_cols = array();
foreach (array_keys($tours) as $key) $tour_cols[] = $tour_card($key);
$tour_walk = array(
    array(lang('The grain room'), lang('Smell and taste the malts, and see how a brew day starts.')),
    array(lang('The brewhouse'), lang('Mash, boil and hops: the brewer shows each vessel and what happens in it.')),
    array(lang('The cellar'), lang('The tanks where the beer ferments and rests, and a taste straight from one.')),
    array(lang('The taproom'), lang('Back at the bar for the tasting, with the brewer to answer your questions.')),
);
$walk_steps = array();
foreach ($tour_walk as $n => $s) $walk_steps[] = $step($n + 1, $s[0], $s[1]);

$tour_pages[] = array(
    'key'              => 'tours',
    'name'             => lang('brewery-tours'),
    'folder'           => 'public',
    'title'            => lang('Brewery Tours'),
    'meta_description' => lang('Walk through the brewhouse with a brewer and taste the beer where it is made: our tours, their prices and how to book one.'),
    'tree'             => $page(array(
        $page_header(lang('Brewery Tours'),
            lang('See where the beer is made, from the grain to the tank, and taste it with the brewer who made it.'),
            array($el('div', 'd-flex flex-wrap gap-2 mt-4', lang('Buttons'), array(
                $link(lang('Book a Tour'), '{{page:tour_booking}}', 'btn btn-primary rounded-pill px-4'),
                $link(lang('Private Tastings'), '{{page:book_online}}', 'btn btn-outline-primary rounded-pill px-4'),
            )))),
        $el('section', 'py-5', lang('Tours'), array(
            $container(array(
                $section_header(lang('Choose your visit'), lang('Our tours')),
                $row($tour_cols, '4'),
                $para(lang('Prices are per person and paid on the day; children under the drinking age join the walk for free.'), 'small text-body-secondary mt-4 mb-0'),
            )),
        ), $searchable('bw-tours', lang('tour, visit, brewhouse, tasting, price, group'))),
        $el('section', 'py-5 bg-body-tertiary', lang('A Tour Step by Step'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $section_header(lang('About an hour'), lang('A tour step by step')),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), $walk_steps),
                    ), '', '7'),
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-4 p-4', lang('Good to know'), array(
                            $heading('h2', lang('Before you come'), 'pbw-display h4 mb-3'),
                            $check_list($tour_rules),
                        )),
                    ), '', '5'),
                ), '4', 'center', 'g-lg-5'),
            )),
        ), $searchable('bw-tour-steps', lang('what to expect, ID, age, shoes, soft drinks, accessibility'))),
        $el('section', 'py-5', lang('Private Tastings'), array(
            $container(array(
                $el('div', 'pbw-hero rounded-4 p-4 p-lg-5 text-bg-dark', lang('Private Tastings Box'), array(
                    $row(array(
                        $col(array(
                            $para(lang('For a birthday, a team or a club'), 'pbw-eyebrow text-uppercase fw-semibold mb-2'),
                            $heading('h2', lang('A private tasting, at a time that suits you'), 'pbw-display h2 mb-2'),
                            $para(lang('Your own table in the taproom and a brewer pouring six beers for your group. Pick a free time online and it is yours as soon as you confirm.'), 'mb-0'),
                        ), '', '8'),
                        $col(array(
                            $link(lang('Pick a Free Time'), '{{page:book_online}}', 'btn btn-light btn-lg rounded-pill px-4'),
                        ), '', '4', '', '12', 'text-lg-end'),
                    ), '4', '', 'align-items-center'),
                )),
            )),
        ), $searchable('bw-private-tastings', lang('private tasting, party, team event, group booking'))),
    )),
);

// The tour booking request. What is sent opens the request's own page.
$tour_pages[] = array(
    'key'              => 'tour_booking',
    'name'             => lang('book-a-tour'),
    'folder'           => 'public',
    'title'            => lang('Book a Tour'),
    'meta_description' => lang('Ask for a brewery tour: choose the tour, the day, the time and your group. We confirm it within a working day.'),
    'form'             => array(
        'form_name'            => lang('Tour Bookings'),
        'confirmation_message' => lang('Thank you, your tour booking request has reached us. We answer within a working day.'),
        'confirmation_page_id' => '{{tab:booking_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New tour booking request: ^^tour^^'),
        'notify_page_id'       => '{{tab:email_booking_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your tour booking request'),
        'confirm_page_id'      => '{{tab:email_booking_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Book a Tour'),
            lang('Choose the tour, the day, the time and your group. We confirm it within a working day.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-4', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('tour_booking_form'))),
                        )),
                    ), '', '8'),
                    $col($tour_aside(), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// One request: the request, its status and the messages under it.
$tour_pages[] = array(
    'key'      => 'booking_view',
    'name'     => lang('tour-booking-details'),
    'folder'   => 'registration',
    'title'    => lang('Tour Booking Details'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                        'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new message about your tour booking'),
                        'notify_email' => '{{site_email}}'),
    'tree'     => $page(array(
        $widget('booking_detail'),
        $comments_section(lang('Messages'), 'booking_messages'),
    ), false),
);

// My Tour Bookings: the visitor's own requests (for the staff, everybody's).
$tour_pages[] = array(
    'key'     => 'my_bookings',
    'name'    => lang('my-tour-bookings'),
    'folder'  => 'registration',
    'title'   => lang('My Tour Bookings'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('My Tour Bookings'), lang('Your tour booking requests, their status and our answers, newest first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('my_bookings_list')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('New Request'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-calendar-plus', 'text-primary'),
                                $span(lang('Another visit?'), '', lang('Text')),
                            )),
                            $para(lang('Ask for another tour or another day; your details are filled in for you.'), 'small text-body-secondary mb-3'),
                            $link(lang('Book a Tour'), '{{page:tour_booking}}', 'btn btn-sm btn-primary rounded-pill px-3'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// The staff's list of every request, newest first, with a search.
$tour_pages[] = array(
    'key'     => 'staff_bookings',
    'name'    => lang('all-tour-bookings'),
    'folder'  => 'private',
    'title'   => lang('Tour Bookings'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'tree'    => $staff_page(array(
        $page_header(lang('Tour Bookings'), lang('Every tour booking request, newest first: confirm the time or offer another, set the status and write to the visitor under each.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array($widget('staff_bookings_list'))),
        )),
    )),
);

// Private tastings at a free time: the booking widget in the editor's
// starter layout, beside the tours and the taproom's hours.
$tour_pages[] = array(
    'key'              => 'book_online',
    'name'             => lang('book-online'),
    'folder'           => 'public',
    'title'            => lang('Private Tastings'),
    'meta_description' => lang('Book a private tasting online: choose the tasting, a brewer and one of the free times.'),
    'tree'             => $page(array(
        $page_header(lang('Private Tastings'), lang('Choose the tasting, a brewer and one of the free times. The time is yours as soon as you confirm.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-4', lang('Booking Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('booking_slots'))),
                        )),
                    ), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('Tours'), array(
                            $heading('h2', lang('Rather join a tour?'), 'h5 fw-semibold mb-2'),
                            $para(lang('Walk through the brewhouse with a brewer and taste the beer where it is made; ask for a day and we confirm it.'), 'small text-body-secondary mb-3'),
                            $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                                $link(lang('See the tours'), '{{page:tours}}', 'btn btn-sm btn-outline-primary rounded-pill px-3'),
                                $link(lang('Book a Tour'), '{{page:tour_booking}}', 'btn btn-sm btn-outline-primary rounded-pill px-3'),
                            )),
                        )),
                        $el('div', 'p-4 rounded-4 border mb-4', lang('Opening hours'), array(
                            $heading('h2', lang('Opening hours'), 'h5 fw-semibold mb-3'),
                            $tour_hours(),
                        )),
                        $para($brewery['adults'], 'small text-body-secondary mb-0'),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('bw-private-tasting', lang('private tasting, party, team event, birthday, group'))),
    ), false),
);

// The privacy notice the consent boxes and the footer link to: sample text
// with a note to adapt it.
$tour_pages[] = array(
    'key'              => 'privacy',
    'name'             => lang('privacy-policy'),
    'folder'           => 'public',
    'title'            => lang('Privacy Policy'),
    'meta_description' => lang('What the brewery keeps about you when you book a tour, join the newsletter or buy from the shop, why, and what you can ask of us.'),
    'tree'             => $page(array(
        $page_header(lang('Privacy Policy'), lang('What the brewery keeps about you when you book a tour, join the newsletter or buy from the shop, why, and what you can ask of us.')),
        $el('section', 'py-5', lang('Your Data'), array(
            $container(array($row(array($col(array(
                $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Sample Text Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Sample text: adapt it to your company and to the data protection law that applies to you before you publish.'), '', lang('Text')),
                ), array('_attrs' => array($attr('role', 'note')))),
                $heading('h2', lang('Your data'), 'h4 fw-semibold mb-3'),
                $para(lang('When you book a tour or a tasting, join the newsletter or the Beer Club, write to us, buy from the shop or open an account, we keep your name, your contact details and what you tell us, to answer you, welcome you on the day, send what you ordered and tell you about new beers and events if you asked for that.'), 'mb-3'),
                $para(lang('We keep it as long as the law requires, never sell it and never give it to advertisers. You can see and change your account data from My Account, leave the newsletter from any of its e-mails, and ask us about the rest through the contact page.'), 'mb-0'),
            ), '', '8'))))),
        ), $searchable('bw-your-data', lang('privacy, personal data, KVKK, GDPR, delete my data'))),
    )),
);

// The e-mails: what the brewery receives and the reply to the visitor,
// and the new message e-mail.
$tour_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_booking_new',
    'name'  => lang('email-new-tour-booking'),
    'title' => lang('New Tour Booking E-mail'),
    'tree'  => $mail_page(array($widget('email_booking_admin'))),
));
$tour_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_booking_received',
    'name'  => lang('email-tour-booking-received'),
    'title' => lang('Tour Booking Received E-mail'),
    'tree'  => $mail_page(array($widget('email_booking_client'))),
));
// A new message or reply: sent to the visitor of a tour booking, the
// sender of a conversation, the brewery about a drinker's note and the
// visitors following a page. Its Comments widget draws the comment in the
// card of mail.php; the System region after it keeps the fixed comment
// for an e-mail the widget does not draw.
$tour_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: your tour booking, a conversation, a beer or a news post. You can read it below and answer it on the page.')),
        $widget('email_comment'),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));

// ── Widget pieces ───────────────────────────────────────────────────────

// The visitor's name.
$visitor_name = function () use ($span, $bind) {
    return array(
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
    );
};

// A request in a list: the tour, the status, the day, the time and the
// group, linked to its page. The staff's row starts with the visitor's
// name and adds the phone; the visitor's own adds the messages.
$tour_booking_row = function ($staff = false) use ($el, $span, $icon, $bind, $visitor_name, $sample_tour, $sample_tour_date, $tour_times, $group_sizes, $booking_statuses) {
    $item = function ($icon_name, $name, $children) use ($el, $icon) {
        return $el('span', 'd-inline-flex align-items-center gap-1', $name, array_merge(array($icon($icon_name)), $children));
    };
    $items = array(
        $item('bi-calendar-event', lang('Tour date'), array($span($sample_tour_date, '', lang('Tour date'), $bind('tour_date')))),
        $item('bi-clock', lang('Start time'), array($span($tour_times[1], '', lang('Start time'), $bind('tour_time')))),
        $item('bi-people', lang('Group size'), array($span($group_sizes[1], '', lang('Group size'), $bind('group_size')))),
    );
    if ($staff) {
        $items[] = $item('bi-telephone', lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone'))));
        $title = $el('span', 'fw-semibold', lang('Visitor'), array_merge($visitor_name(), array(
            $span(' · ', 'text-body-secondary', lang('Text')),
            $span($sample_tour, 'text-body-secondary fw-normal', lang('Tour'), $bind('tour')),
        )));
    } else {
        $items[] = $item('bi-chat-left-text', lang('Messages'), array($span('0', '', lang('Reply Count'), $bind('number_of_comments'))));
        $title = $span($sample_tour, 'fw-semibold', lang('Tour'), $bind('tour'));
    }
    return $el('a', 'list-group-item list-group-item-action p-3', lang('Tour Booking'), array(
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

// The lines of a request, for its e-mails.
$mail_tour_booking = function () use ($mail_box, $mail_line, $span, $bind, $sample_tour, $sample_tour_date, $tour_times, $group_sizes) {
    return $mail_box(array(
        $mail_line(lang('Tour'), array($span($sample_tour, '', lang('Tour'), $bind('tour')))),
        $mail_line(lang('Tour date'), array($span($sample_tour_date, '', lang('Tour date'), $bind('tour_date')))),
        $mail_line(lang('Start time'), array($span($tour_times[1], '', lang('Start time'), $bind('tour_time')))),
        $mail_line(lang('Group size'), array($span($group_sizes[1], '', lang('Group size'), $bind('group_size')))),
    ), lang('Tour Booking'));
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
// The visitor's lines of the staff e-mail.
$mail_visitor = function () use ($mail_box, $mail_line, $span, $bind, $visitor_name, $sample_tour_notes) {
    return $mail_box(array(
        $mail_line(lang('Visitor'), $visitor_name()),
        $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
        $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
        $mail_line(lang('Best way to reach you'), array($span(lang('E-mail'), '', lang('Best way to reach you'), $bind('contact_by')))),
        $mail_line(lang('Notes'), array($span($sample_tour_notes, '', lang('Notes'), $bind('notes')))),
    ), lang('Visitor'));
};
// The form detail settings of an e-mail page's widget (any reader: the
// e-mail is drawn for the submission just sent) and of the request page's
// widget (the visitor's own, and the staff's).
$detail_widget = function ($page_key, $missing, $access) {
    return array(
        'page'   => $page_key,
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:tour_booking}}',
            'access_control'      => $access,
            'not_found_message'   => $missing,
        ),
    );
};
// A list of requests: the visitor's own (the staff see them all), or the
// staff's with a search.
$tour_booking_list = function ($page_key, $staff, $empty) use ($widget_root, $el, $loop, $tour_booking_row) {
    $config = array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:tour_booking}}',
        'detail_page_id'      => '{{tab:booking_view}}',
        'items_per_page'      => 20,
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
    );
    if ($staff) {
        $config += array(
            'search_enabled' => true,
            'search_fields'  => array('first_name', 'last_name', 'email', 'phone', 'tour', 'tour_date', 'status'),
            'search_width'   => 'half',
            'search_label'   => lang('Search by visitor, e-mail, tour, day or status...'),
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
            $el('div', 'list-group', lang('Tour Booking List'), array($loop(array($tour_booking_row($staff))))),
        )),
    );
};

$tour_booking_missing = lang('This tour booking could not be found, or it is not yours to see.');

// ── Widgets ─────────────────────────────────────────────────────────────

$tour_widgets = array(

    // The tour booking request: the fields are the page's form. The tour
    // starts with what a link sends (?tour=), the day is today or later,
    // and every request confirms the tasters' age. The Status is the
    // brewery's (office use only): every request starts as Request
    // received.
    'tour_booking_form' => array(
        'page'   => 'tour_booking',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('Your visit')),
            $field('bw-tb-tour', lang('Tour'), $select('bw-tb-tour', 'tour', lang('Choose a tour…'), $tour_titles, true, '', array('url_param' => 'tour'))),
            $row(array(
                $col(array($field('bw-tb-date', lang('Tour date'), $input('bw-tb-date', 'date', 'tour_date', 'off', true, array('rules' => array('min_date' => 'today'))))), '6'),
                $col(array($field('bw-tb-time', lang('Start time'), $select('bw-tb-time', 'tour_time', lang('Choose a time…'), $tour_times))), '6'),
            ), '3'),
            $field('bw-tb-group', lang('Group size'), $select('bw-tb-group', 'group_size', lang('Choose a group size…'), $group_sizes)),
            $field('bw-tb-notes', lang('Notes'), $textarea('bw-tb-notes', 'notes', 3, false), 'mb-3',
                lang('Anything we should know: an occasion, a wheelchair, guests who do not drink.')),
            $form_step(2, lang('How to reach you')),
            $row(array(
                $col(array($field('bw-tb-first-name', lang('First Name'), $input('bw-tb-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
                $col(array($field('bw-tb-last-name', lang('Last Name'), $input('bw-tb-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
                $col(array($field('bw-tb-email', lang('Email'), $input('bw-tb-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
                $col(array($field('bw-tb-phone', lang('Phone'), $input('bw-tb-phone', 'tel', 'phone', 'tel', true, array('contact_field' => 'mobile_phone')))), '6'),
            ), '3'),
            $radios('bw-tb-contact', 'contact_by', lang('Best way to reach you'), $contact_choices, $contact_choices[0][0]),
            $field('bw-tb-status', lang('Status'), $select('bw-tb-status', 'status', '', $booking_statuses, false, $booking_statuses[0], array('office_use_only' => true))),
            $agree('bw-tb-age', 'legal_age', lang('Everyone in my group who will taste beer is of legal drinking age.')),
            $agree('bw-tb-consent', 'consent', lang('I agree that the brewery keeps these details to answer my request and welcome us, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Request'), 'btn btn-primary btn-lg rounded-pill px-4'),
                $para(lang('We answer within a working day.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One request: the request, its status and what the visitor wrote;
    // the brewery's answers follow as comments.
    'booking_detail' => $detail_widget('booking_view', $tour_booking_missing, 'submitter_only') + array(
        'tree' => array('type' => 'root', 'props' => array(), 'children' => array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'text-center py-5', lang('Not Found'), array(
                            $icon('bi-calendar-x', 'display-5 text-body-tertiary'),
                            $heading('h2', lang('Tour booking not found'), 'h3 fw-semibold mt-3'),
                            $para($tour_booking_missing, 'text-body-secondary mb-4', $bind('not_found')),
                            $link(lang('My Tour Bookings'), '{{page:my_bookings}}', 'btn btn-primary rounded-pill px-4'),
                        ), $show_when('record_not_found')),
                        $el('div', '', lang('Record'), array(
                            $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
                                $icon('bi-check-circle', 'mt-1'),
                                $span(lang('Thank you, your request has reached us. We answer here within a working day; this page shows the status, and you can write to us below.'), '', lang('Text')),
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
                                    $span(lang('My Tour Bookings'), '', lang('Link Text')),
                                ), array('href' => '{{page:my_bookings}}')),
                                $edit_button(lang('Change Status')),
                            )),
                            $el('div', 'card border-0 shadow-sm rounded-4 mb-4', lang('Tour Booking Card'), array(
                                $el('div', 'card-body p-4', lang('Card Body'), array(
                                    $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
                                        $el('div', '', lang('Words'), array(
                                            $para(lang('Tour Booking'), 'pbw-eyebrow small text-uppercase fw-semibold text-primary mb-1'),
                                            $heading('h1', $sample_tour, 'pbw-display h3 mb-0', $bind('tour')),
                                        )),
                                        $span($booking_statuses[0], 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
                                    )),
                                    $el('dl', 'row small mb-0', lang('Details'), array_merge(
                                        $detail(lang('Tour date'), '', lang('Tour date'), $bound($sample_tour_date, 'tour_date')),
                                        $detail(lang('Start time'), '', lang('Start time'), $bound($tour_times[1], 'tour_time')),
                                        $detail(lang('Group size'), '', lang('Group size'), $bound($group_sizes[1], 'group_size')),
                                        $detail(lang('Visitor'), '', lang('Visitor'), $visitor_name(), true),
                                        $detail(lang('Phone'), '', lang('Phone'), $bound('+90 555 000 00 00', 'phone')),
                                        $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
                                    )),
                                )),
                            )),
                            $el('div', 'pg-hide-if-empty p-4 rounded-4 bg-body-tertiary mb-4', lang('Notes'), array(
                                $para(lang('Notes'), 'small fw-semibold text-body-secondary mb-2'),
                                $el('div', 'pg-field-value lh-lg', lang('Value'), array(), $bound($sample_tour_notes, 'notes')),
                            )),
                            $el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
                                $icon('bi-chat-left-text', 'text-primary mt-1'),
                                $span(lang('Another day, a larger group or a question? Write to us below; we answer here and by e-mail.'), '', lang('Text')),
                            )),
                        ), $show_when('record_found')),
                    ), '', '8'),
                ), '', 'center'),
            ), 'py-5'),
        )),
    ),

    // The messages under a request: the page's own Comments widget, shown
    // only to whoever may read the request above it. Oldest first, all on
    // one page. No Reply, Share or follow box: the visitor is e-mailed
    // every answer and the brewery every message.
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
            'empty'     => lang('No messages yet. Our answer about your tour comes here; you can write to us too.'),
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

    'my_bookings_list' => $tour_booking_list('my_bookings', false,
        lang('No tour bookings yet. Choose a tour and ask for a day.')),

    'staff_bookings_list' => $tour_booking_list('staff_bookings', true, lang('No tour bookings yet.')),

    // Private tastings at a free time: the booking widget in the editor's
    // starter layout (services, brewer, day and time, details,
    // confirmation).
    'booking_slots' => array(
        'page'   => 'book_online',
        'slug'   => lang('booking'),
        'config' => array('regionType' => 'booking'),
        'tree'   => 'starter',
    ),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_booking_admin' => $detail_widget('email_booking_new', lang('The tour booking could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new tour booking request')),
            $mail_para(lang('It came from the tour booking form. Confirm the time or offer another under the request on its page, and set the status; what you write there reaches the visitor by e-mail.')),
            $mail_tour_booking(),
            $mail_visitor(),
            $mail_button(lang('Open the request'), '{{page:booking_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_booking_client' => $detail_widget('email_booking_received', lang('The tour booking could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your tour booking request')),
            $mail_greeting(),
            $mail_para(lang('Thank you. We check the day and the time and answer within a working day, on the request\'s page and by e-mail. Nothing is paid now: you pay per person on the day.')),
            $mail_tour_booking(),
            $mail_para(lang('Another day or a question? Write to us on the request\'s page; everything about your visit stays there.')),
            $mail_button(lang('Open my request'), '{{page:booking_view}}?r=^^reference_code^^'),
            $mail_para($brewery['adults'], 'small text-body-secondary mb-3', 'font-size:13px;color:#4b5563;margin:0 0 16px'),
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

return array('pages' => $tour_pages, 'widgets' => $tour_widgets);
