<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Music Artist", its press kit and booking enquiries: the
 * press kit (the band in three lengths, photos, the quotes, the figures,
 * the line-up and the files: technical rider, stage plot, photos and
 * logos), the staff page that adds a press file, the booking enquiry for
 * promoters, the enquiry's own page with its messages, My Enquiries and
 * the staff's list; the privacy page the consent boxes link, the e-mails
 * of the enquiry form and the new message e-mail every thread of the site
 * sends, and the widgets that draw them. Included after parts.php and
 * mail.php in the same scope; returns array('pages' => ..., 'widgets' =>
 * ...).
 *
 * The press files are records of the Press Files form, a collection whose
 * file field keeps what is sent in the public `press` folder, so anybody
 * may download them from the press kit. The band's words, photos and
 * quotes are page content, edited in the designer.
 *
 * An enquiry is a request, never a booked show: a record of the Show
 * Bookings form. A promoter without an account gets one
 * (auto-registration), so the enquiry's page and My Enquiries show it at
 * once. Its Status is an office-use-only field the band's staff change in
 * the control panel; the offer is written under it as messages (the
 * page's comments), mailed to the promoter and to the band. The kind of
 * event starts with what a link sends (?event=).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$press_pages = array();

// ── The press kit's data ────────────────────────────────────────────────

// The band in three lengths, to copy into a programme, a festival page or
// an article.
$press_bios = array(
    'short'  => array('title' => lang('In one line'), 'text' => $artist['tagline'] . '.'),
    'medium' => array('title' => lang('In a paragraph'), 'text' => $artist['story']),
    'long'   => array('title' => lang('The full story'), 'text' => $artist['story'] . ' ' . lang('Two albums, an EP and a live record later, the band plays clubs and festivals across Europe with the same four people and the same van. The songs are written on night buses and ferries between shows, recorded live in one room, and played a little faster every night of a tour.')),
);

// The kinds of a press file, the press kit's groups.
$press_file_kinds = array(lang('Technical rider'), lang('Stage plot'), lang('Press photos'), lang('Logos'));

// The sample press files the Press Files form starts with. They have no
// file yet: the band sends its own, and the Download button stays away
// until a file is there.
$press_samples = array(
    array('title' => lang('Technical Rider'), 'kind' => $press_file_kinds[0],
        'summary' => lang('The backline, the inputs and the monitors we need, and what we bring ourselves.')),
    array('title' => lang('Stage Plot and Input List'), 'kind' => $press_file_kinds[1],
        'summary' => lang('Where the four of us stand, with the input list for the front of house.')),
    array('title' => lang('Press Photos'), 'kind' => $press_file_kinds[2],
        'summary' => lang('Six photos of the band, live and in the rehearsal room, in print quality.')),
    array('title' => lang('Logos'), 'kind' => $press_file_kinds[3],
        'summary' => lang('The band\'s name in black and in white, for posters and festival pages.')),
);

// The sample enquiry the canvas shows. Its date is words, never a date: a
// fixed day would read as a past season a year later.
$sample_event = $event_kinds[0];
$sample_event_name = lang('Harbour Summer Festival');
$sample_event_city = lang('Izmir');
$sample_event_date = lang('Event date');
$sample_details = lang('An open-air festival by the sea; we would like you to headline the Saturday night, about seventy-five minutes, with our backline.');

// ── Page shells ─────────────────────────────────────────────────────────

// A picture in a rounded frame, loaded lazily.
$press_picture = function ($src, $alt, $ratio = '4x3') use ($el, $image, $attr) {
    return $el('div', 'ratio ratio-' . $ratio . ' rounded-4 overflow-hidden bg-body-tertiary shadow-sm', lang('Picture Frame'), array(
        $image($src, $alt, 'object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
    ));
};

// The side column of the enquiry page: what happens next, then the
// addresses for bookings and management.
$enquiry_aside = function () use ($el, $heading, $link, $check_list, $artist) {
    $steps = array(
        lang('Your enquiry opens its own page, and an account for you if you have none.'),
        lang('Our booking agent answers there within two working days, with an offer or a question.'),
        lang('Agree the offer in a message; the status shows Confirmed once the show is booked.'),
        lang('Nothing is paid now: the contract follows the confirmation.'),
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
            $check_list(array(
                sprintf(lang('Bookings: %s'), $artist['booking']),
                sprintf(lang('Management: %s'), $artist['management']),
                lang('The technical rider and the stage plot are in the press kit.'),
            ), 'list-unstyled small mb-3'),
            $link(lang('Open the Press Kit'), '{{page:press}}', 'fw-semibold link-primary text-decoration-none small'),
        )),
    );
};

// ── Pages ───────────────────────────────────────────────────────────────

// The press kit: the band in three lengths, photos, quotes, figures, the
// line-up and the files, with the addresses for press and bookings.
$bio_cols = array();
foreach ($press_bios as $key => $b) {
    $bio_cols[] = $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4', $b['title'], array(
            $el('div', 'card-body p-4', lang('Card Body'), array(
                $heading('h3', $b['title'], 'pmu-display h5 mb-2'),
                $para($b['text'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), '', '4');
}
$photo_cols = array();
foreach (array('live', 'portrait', 'studio') as $seed) {
    $photo_cols[] = $col(array($press_picture($photo('press-' . $seed, 900, 675), lang('A photo of the band'))), '', '4', '', '12');
}
$member_cols = array();
foreach (array_keys($members) as $key) $member_cols[] = $member_card($key);
$quote_cols = array();
foreach (array_keys($press_quotes) as $n) $quote_cols[] = $press_quote_card($n);
$figure_cols = array();
foreach ($figures as $f) {
    $figure_cols[] = $col(array(
        $el('div', 'text-center', $f['label'], array(
            $para($f['value'], 'pmu-display display-4 text-primary mb-1'),
            $para($f['label'], 'small text-uppercase fw-semibold text-body-secondary mb-0'),
        )),
    ), '3', '', '', '6');
}
$press_pages[] = array(
    'key'              => 'press',
    'name'             => lang('press-kit'),
    'folder'           => 'public',
    'title'            => lang('Press Kit'),
    'meta_description' => lang('The band\'s story in three lengths, press photos, quotes, the technical rider, the stage plot and the addresses for press and bookings.'),
    'tree'             => $page(array(
        $page_header(lang('Press Kit'), lang('Everything a promoter, a festival or a journalist needs: the story, the photos, the files and who to write to.'), array(
            $el('div', 'd-flex flex-wrap gap-2 mt-3', lang('Buttons'), array(
                $link(lang('Send a Booking Enquiry'), '{{page:booking}}', 'btn btn-primary rounded-pill px-4'),
                $link(sprintf(lang('Press: %s'), $artist['press']), 'mailto:' . $artist['press'], 'btn btn-outline-primary rounded-pill px-4'),
            )),
        )),
        $el('section', 'py-5', lang('The Band'), array(
            $container(array(
                $section_header(lang('Biography'), lang('The band in three lengths')),
                $row($bio_cols, '4'),
            )),
        ), $searchable('mu-press-bio', lang('biography, bio, about the band, story'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Photos'), array(
            $container(array(
                $section_header(lang('Photos'), lang('Press photos'), lang('Download all photos'), '#mu-press-files'),
                $row($photo_cols, '4'),
            )),
        )),
        $el('section', 'py-5', lang('Press Quotes'), array(
            $container(array(
                $section_header(lang('Press'), lang('What the press says')),
                $row($quote_cols, '4'),
            )),
        )),
        $el('section', 'py-5 bg-body-tertiary', lang('Figures'), array(
            $container(array($row($figure_cols, '4'))),
        )),
        $el('section', 'py-5', lang('Line-up'), array(
            $container(array(
                $section_header(lang('Line-up'), lang('Four people on stage')),
                $row($member_cols, '4'),
            )),
        )),
        $el('section', 'py-5 bg-body-tertiary', lang('Press Files'), array(
            $container(array(
                $section_header(lang('Downloads'), lang('Rider, stage plot, photos and logos')),
                $widget('press_files'),
            )),
        ), $searchable('mu-press-files', lang('technical rider, stage plot, input list, logo, download'))),
    )),
);

// The staff page that adds a press file. The form is the Press Files
// collection; the sample files are written in when it is made.
$press_pages[] = array(
    'key'     => 'press_file_new',
    'name'    => lang('new-press-file'),
    'folder'  => 'private',
    'title'   => lang('New Press File'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Press Files'),
        'collection'           => 1,
        'confirmation_message' => lang('The file is in the press kit.'),
        'confirmation_page_id' => '{{tab:press}}',
        'sample_records'       => $press_samples,
    ),
    'tree'    => $mu_form_page(lang('New Press File'),
        lang('Add a rider, a stage plot, photos or logos; anybody can download it from the press kit as soon as you send it.'),
        'press_file_form'),
);

// The booking enquiry. What is sent opens the enquiry's own page.
$press_pages[] = array(
    'key'              => 'booking',
    'name'             => lang('booking-enquiry'),
    'folder'           => 'public',
    'title'            => lang('Booking Enquiry'),
    'meta_description' => lang('Want the band at your festival, club or event? Tell us the date, the city and the stage: our booking agent answers within two working days.'),
    'form'             => array(
        'form_name'            => lang('Show Bookings'),
        'confirmation_message' => lang('Thank you, your booking enquiry has reached us. Our booking agent answers within two working days.'),
        'confirmation_page_id' => '{{tab:booking_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New booking enquiry: ^^event_name^^'),
        'notify_page_id'       => '{{tab:email_booking_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your booking enquiry'),
        'confirm_page_id'      => '{{tab:email_booking_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Booking Enquiry'),
            lang('Festivals, clubs and private events: tell us the date, the city and the stage. Our booking agent answers within two working days.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-4', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('booking_form'))),
                        )),
                    ), '', '8'),
                    $col($enquiry_aside(), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// One enquiry: the enquiry, its status and the messages under it.
$press_pages[] = array(
    'key'      => 'booking_view',
    'name'     => lang('booking-enquiry-details'),
    'folder'   => 'registration',
    'title'    => lang('Booking Enquiry Details'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                        'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new message about your booking enquiry'),
                        'notify_email' => '{{site_email}}'),
    'tree'     => $page(array(
        $widget('booking_detail'),
        $comments_section(lang('Messages'), 'booking_messages'),
    ), false),
);

// My Enquiries: the promoter's own enquiries (for the staff, everybody's).
$press_pages[] = array(
    'key'     => 'my_bookings',
    'name'    => lang('my-enquiries'),
    'folder'  => 'registration',
    'title'   => lang('My Enquiries'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('My Enquiries'), lang('Your booking enquiries, their status and our answers, newest first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('my_bookings_list')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('New Enquiry'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-calendar-plus', 'text-primary'),
                                $span(lang('Another event?'), '', lang('Text')),
                            )),
                            $para(lang('Ask for another date or another city; your details are filled in for you.'), 'small text-body-secondary mb-3'),
                            $link(lang('Send a Booking Enquiry'), '{{page:booking}}', 'btn btn-sm btn-primary rounded-pill px-3'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// The staff's list of every enquiry, newest first, with a search.
$press_pages[] = array(
    'key'     => 'staff_bookings',
    'name'    => lang('all-booking-enquiries'),
    'folder'  => 'private',
    'title'   => lang('Booking Enquiries'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $staff_page(array(
        $page_header(lang('Booking Enquiries'), lang('Every booking enquiry, newest first: answer with an offer, set the status and write to the promoter under each.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array($widget('staff_bookings_list'))),
        )),
    )),
);

// The privacy notice the consent boxes and the footer link to: sample text
// with a note to adapt it.
$press_pages[] = array(
    'key'              => 'privacy',
    'name'             => lang('privacy-policy'),
    'folder'           => 'public',
    'title'            => lang('Privacy Policy'),
    'meta_description' => lang('What the band keeps about you when you join the mailing list, send a booking enquiry or buy merch, why, and what you can ask of us.'),
    'tree'             => $page(array(
        $page_header(lang('Privacy Policy'), lang('What the band keeps about you when you join the mailing list, send a booking enquiry or buy merch, why, and what you can ask of us.')),
        $el('section', 'py-5', lang('Your Data'), array(
            $container(array($row(array($col(array(
                $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Sample Text Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Sample text: adapt it to your company and to the data protection law that applies to you before you publish.'), '', lang('Text')),
                ), array('_attrs' => array($attr('role', 'note')))),
                $heading('h2', lang('Your data'), 'h4 fw-semibold mb-3'),
                $para(lang('When you join the mailing list, send a booking enquiry, write to us, buy merch or open an account, we keep your name, your contact details and what you tell us, to answer you, send what you ordered and tell you about new songs and shows if you asked for that.'), 'mb-3'),
                $para(lang('We keep it as long as the law requires, never sell it and never give it to advertisers. You can see and change your account data from My Account, leave the mailing list from any of its e-mails, and ask us about the rest through the contact page.'), 'mb-0'),
            ), '', '8'))))),
        ), $searchable('mu-your-data', lang('privacy, personal data, KVKK, GDPR, delete my data'))),
    )),
);

// The e-mails: what the band receives and the reply to the promoter, and
// the new message e-mail.
$press_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_booking_new',
    'name'  => lang('email-new-booking-enquiry'),
    'title' => lang('New Booking Enquiry E-mail'),
    'tree'  => $mail_page(array($widget('email_booking_admin'))),
));
$press_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_booking_received',
    'name'  => lang('email-booking-enquiry-received'),
    'title' => lang('Booking Enquiry Received E-mail'),
    'tree'  => $mail_page(array($widget('email_booking_client'))),
));
// A new message or reply: sent to the promoter of an enquiry, the sender
// of a conversation and the visitors following a page. Its Comments
// widget draws the comment in the card of mail.php; the System region
// after it keeps the fixed comment for an e-mail the widget does not draw.
$press_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: your booking enquiry, a conversation, a release or a news post. You can read it below and answer it on the page.')),
        $widget('email_comment'),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));

// ── Widget pieces ───────────────────────────────────────────────────────

// A press file's card: the kind, the title, what it holds and its
// download, which leaves while the record has no file.
$press_file_card = function () use ($el, $span, $para, $icon, $col, $link, $bind, $press_samples) {
    $s = $press_samples[0];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4', lang('Press File Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $el('p', 'd-flex align-items-center gap-2 small fw-semibold text-primary mb-2', lang('Kind'), array(
                    $icon('bi-file-earmark-arrow-down'),
                    $span($s['kind'], '', lang('Kind'), $bind('kind')),
                )),
                $el('h3', 'pmu-display h5 mb-2', lang('Title'), array(), array_merge(array('text' => $s['title']), $bind('title'))),
                $para($s['summary'], 'small text-body-secondary mb-3', $bind('summary')),
                $el('div', 'pg-hide-if-empty mt-auto', lang('Download'), array(
                    $el('span', 'pg-field-value', lang('Value'), array(
                        $link(lang('Download'), '#', 'btn btn-sm btn-outline-primary rounded-pill px-3', array(),
                            array('target' => '_blank', 'rel' => 'noopener', '_bindings' => array('href' => 'file'))),
                    )),
                )),
            )),
        )),
    ), '6', '3');
};

// The promoter's name.
$promoter_name = function () use ($span, $bind) {
    return array(
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
    );
};

// An enquiry in a list: the event's name, the status, the date, the city,
// the kind and the capacity, linked to its page. The staff's row starts
// with the promoter's name and adds the phone; the promoter's own adds the
// messages.
$enquiry_row = function ($staff = false) use ($el, $span, $icon, $bind, $promoter_name, $sample_event, $sample_event_name, $sample_event_city, $sample_event_date, $capacity_choices, $booking_statuses) {
    $item = function ($icon_name, $name, $children) use ($el, $icon) {
        return $el('span', 'd-inline-flex align-items-center gap-1', $name, array_merge(array($icon($icon_name)), $children));
    };
    $items = array(
        $item('bi-calendar-event', lang('Event date'), array($span($sample_event_date, '', lang('Event date'), $bind('event_date')))),
        $item('bi-geo-alt', lang('City'), array($span($sample_event_city, '', lang('City'), $bind('city')))),
        $item('bi-music-note-list', lang('Kind of event'), array($span($sample_event, '', lang('Kind of event'), $bind('event')))),
        $item('bi-people', lang('Capacity'), array($span($capacity_choices[1], '', lang('Capacity'), $bind('capacity')))),
    );
    if ($staff) {
        $items[] = $item('bi-telephone', lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone'))));
        $title = $el('span', 'fw-semibold', lang('Promoter'), array_merge($promoter_name(), array(
            $span(' · ', 'text-body-secondary', lang('Text')),
            $span($sample_event_name, 'text-body-secondary fw-normal', lang('Event name'), $bind('event_name')),
        )));
    } else {
        $items[] = $item('bi-chat-left-text', lang('Messages'), array($span('0', '', lang('Reply Count'), $bind('number_of_comments'))));
        $title = $span($sample_event_name, 'fw-semibold', lang('Event name'), $bind('event_name'));
    }
    return $el('a', 'list-group-item list-group-item-action p-3', lang('Booking Enquiry'), array(
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

// The lines of an enquiry, for its e-mails.
$mail_enquiry = function () use ($mail_box, $mail_line, $span, $bind, $sample_event, $sample_event_name, $sample_event_city, $sample_event_date, $capacity_choices, $fee_choices) {
    return $mail_box(array(
        $mail_line(lang('Event name'), array($span($sample_event_name, '', lang('Event name'), $bind('event_name')))),
        $mail_line(lang('Kind of event'), array($span($sample_event, '', lang('Kind of event'), $bind('event')))),
        $mail_line(lang('Event date'), array($span($sample_event_date, '', lang('Event date'), $bind('event_date')))),
        $mail_line(lang('City'), array($span($sample_event_city, '', lang('City'), $bind('city')))),
        $mail_line(lang('Venue'), array($span(lang('Seafront Stage'), '', lang('Venue'), $bind('venue')))),
        $mail_line(lang('Capacity'), array($span($capacity_choices[1], '', lang('Capacity'), $bind('capacity')))),
        $mail_line(lang('Fee'), array($span($fee_choices[1], '', lang('Fee'), $bind('fee')))),
    ), lang('Booking Enquiry'));
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
// The promoter's lines of the staff e-mail.
$mail_promoter = function () use ($mail_box, $mail_line, $span, $bind, $promoter_name, $sample_details) {
    return $mail_box(array(
        $mail_line(lang('Promoter'), $promoter_name()),
        $mail_line(lang('Organisation'), array($span(lang('Harbour Events'), '', lang('Organisation'), $bind('organisation')))),
        $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
        $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
        $mail_line(lang('About the event'), array($span($sample_details, '', lang('About the event'), $bind('details')))),
    ), lang('Promoter'));
};
// The form detail settings of an e-mail page's widget (any reader: the
// e-mail is drawn for the submission just sent) and of the enquiry page's
// widget (the promoter's own, and the staff's).
$detail_widget = function ($page_key, $missing, $access) {
    return array(
        'page'   => $page_key,
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:booking}}',
            'access_control'      => $access,
            'not_found_message'   => $missing,
        ),
    );
};
// A list of enquiries: the promoter's own (the staff see them all), or the
// staff's with a search.
$enquiry_list = function ($page_key, $staff, $empty) use ($widget_root, $el, $loop, $enquiry_row) {
    $config = array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:booking}}',
        'detail_page_id'      => '{{tab:booking_view}}',
        'items_per_page'      => 20,
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
    );
    if ($staff) {
        $config += array(
            'search_enabled' => true,
            'search_fields'  => array('first_name', 'last_name', 'email', 'organisation', 'event_name', 'city', 'event', 'status'),
            'search_width'   => 'half',
            'search_label'   => lang('Search by promoter, e-mail, event, city or status...'),
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
            $el('div', 'list-group', lang('Enquiry List'), array($loop(array($enquiry_row($staff))))),
        )),
    );
};

$enquiry_missing = lang('This booking enquiry could not be found, or it is not yours to see.');

$press_widgets = array(

    // The press kit's files, grouped by kind as the staff add them.
    'press_files' => array(
        'page'   => 'press',
        'slug'   => lang('form-list'),
        'config' => array(
            'regionType'          => 'form_list_view',
            'custom_form_page_id' => '{{tab:press_file_new}}',
            'items_per_page'      => 24,
            'order_by_field'      => 'submitted_date_and_time',
            'order_by_direction'  => 'ASC',
            'empty_message'       => lang('The press files are on their way. Write to us for the rider in the meantime.'),
        ),
        'tree'   => $widget_root(array(
            $row(array($loop(array($press_file_card()))), '4'),
        )),
    ),

    // The staff's Press Files form: one file, kept in the public press
    // folder.
    'press_file_form' => array(
        'page'   => 'press_file_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $field('mu-pf-title', lang('Title'), pg_cf_with_role($input('mu-pf-title', 'text', 'title', 'off'), 'title')),
            $field('mu-pf-kind', lang('Kind'), pg_cf_with_role($select('mu-pf-kind', 'kind', lang('Choose…'), $press_file_kinds), 'category')),
            $field('mu-pf-summary', lang('Summary'), pg_cf_with_role($textarea('mu-pf-summary', 'summary', 2), 'description'), 'mb-3',
                lang('One sentence on what the file holds.')),
            $field('mu-pf-file', lang('File'), $file('mu-pf-file', 'file', 'press', '.pdf,.zip,.jpg,.jpeg,.png,.svg'), 'mb-3',
                lang('A PDF, a ZIP of photos or a picture; anybody can download it from the press kit.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // The booking enquiry: the fields are the page's form. The kind of
    // event starts with what a link sends (?event=). The Status is the
    // band's (office use only): every enquiry starts as Enquiry received.
    'booking_form' => array(
        'page'   => 'booking',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('Your event')),
            $row(array(
                $col(array($field('mu-bk-event', lang('Kind of event'), $select('mu-bk-event', 'event', lang('Choose a kind of event…'), $event_kinds, true, '', array('url_param' => 'event')))), '6'),
                $col(array($field('mu-bk-name', lang('Event name'), $input('mu-bk-name', 'text', 'event_name', 'off'))), '6'),
                $col(array($field('mu-bk-date', lang('Event date'), $input('mu-bk-date', 'date', 'event_date', 'off', true, array('rules' => array('min_date' => 'today'))))), '6'),
                $col(array($field('mu-bk-city', lang('City'), $input('mu-bk-city', 'text', 'city', 'address-level2'))), '6'),
                $col(array($field('mu-bk-venue', lang('Venue'), $input('mu-bk-venue', 'text', 'venue', 'off', false))), '6'),
                $col(array($field('mu-bk-capacity', lang('Capacity'), $select('mu-bk-capacity', 'capacity', lang('Choose a capacity…'), $capacity_choices))), '6'),
            ), '3'),
            $field('mu-bk-fee', lang('Fee'), $select('mu-bk-fee', 'fee', '', $fee_choices, false, $fee_choices[3]), 'mb-3',
                lang('The fee you have in mind for the show, before travel and accommodation.')),
            $field('mu-bk-details', lang('About the event'), $textarea('mu-bk-details', 'details', 4), 'mb-3',
                lang('The stage, the set length, the other acts, what you provide.')),
            $field('mu-bk-attachment', lang('Attachment'), $file('mu-bk-attachment', 'attachment', 'press'), 'mb-3',
                lang('A festival poster, a stage plan or an offer, if you have one.')),
            $form_step(2, lang('How to reach you')),
            $row(array(
                $col(array($field('mu-bk-first-name', lang('First Name'), $input('mu-bk-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
                $col(array($field('mu-bk-last-name', lang('Last Name'), $input('mu-bk-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
                $col(array($field('mu-bk-organisation', lang('Organisation'), $input('mu-bk-organisation', 'text', 'organisation', 'organization', false, array('contact_field' => 'company')))), '6'),
                $col(array($field('mu-bk-email', lang('Email'), $input('mu-bk-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
                $col(array($field('mu-bk-phone', lang('Phone'), $input('mu-bk-phone', 'tel', 'phone', 'tel', true, array('contact_field' => 'mobile_phone')))), '6'),
            ), '3'),
            $radios('mu-bk-contact', 'contact_by', lang('Best way to reach you'), $contact_choices, $contact_choices[0][0]),
            $field('mu-bk-status', lang('Status'), $select('mu-bk-status', 'status', '', $booking_statuses, false, $booking_statuses[0], array('office_use_only' => true))),
            $agree('mu-bk-consent', 'consent', lang('I agree that the band keeps these details to answer my enquiry and plan the show, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Enquiry'), 'btn btn-primary btn-lg rounded-pill px-4'),
                $para(lang('Our booking agent answers within two working days.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One enquiry: the enquiry, its status and what the promoter wrote;
    // the band's answers (the offer among them) follow as comments.
    'booking_detail' => $detail_widget('booking_view', $enquiry_missing, 'submitter_only') + array(
        'tree' => array('type' => 'root', 'props' => array(), 'children' => array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'text-center py-5', lang('Not Found'), array(
                            $icon('bi-calendar-x', 'display-5 text-body-tertiary'),
                            $heading('h2', lang('Booking enquiry not found'), 'h3 fw-semibold mt-3'),
                            $para($enquiry_missing, 'text-body-secondary mb-4', $bind('not_found')),
                            $link(lang('My Enquiries'), '{{page:my_bookings}}', 'btn btn-primary rounded-pill px-4'),
                        ), $show_when('record_not_found')),
                        $el('div', '', lang('Record'), array(
                            $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
                                $icon('bi-check-circle', 'mt-1'),
                                $span(lang('Thank you, your enquiry has reached us. Our booking agent answers here within two working days; this page shows the status, and you can write to us below.'), '', lang('Text')),
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
                                    $span(lang('My Enquiries'), '', lang('Link Text')),
                                ), array('href' => '{{page:my_bookings}}')),
                                $edit_button(lang('Change Status')),
                            )),
                            $el('div', 'card border-0 shadow-sm rounded-4 mb-4', lang('Enquiry Card'), array(
                                $el('div', 'card-body p-4', lang('Card Body'), array(
                                    $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
                                        $el('div', '', lang('Words'), array(
                                            $para(lang('Booking Enquiry'), 'pmu-eyebrow small text-uppercase fw-semibold text-primary mb-1'),
                                            $heading('h1', $sample_event_name, 'pmu-display h3 mb-0', $bind('event_name')),
                                        )),
                                        $span($booking_statuses[0], 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
                                    )),
                                    $el('dl', 'row small mb-0', lang('Details'), array_merge(
                                        $detail(lang('Kind of event'), '', lang('Kind of event'), $bound($sample_event, 'event')),
                                        $detail(lang('Event date'), '', lang('Event date'), $bound($sample_event_date, 'event_date')),
                                        $detail(lang('City'), '', lang('City'), $bound($sample_event_city, 'city')),
                                        $detail(lang('Venue'), '', lang('Venue'), $bound(lang('Seafront Stage'), 'venue')),
                                        $detail(lang('Capacity'), '', lang('Capacity'), $bound($capacity_choices[1], 'capacity')),
                                        $detail(lang('Fee'), '', lang('Fee'), $bound($fee_choices[1], 'fee')),
                                        $detail(lang('Promoter'), '', lang('Promoter'), $promoter_name(), true),
                                        $detail(lang('Organisation'), '', lang('Organisation'), $bound(lang('Harbour Events'), 'organisation')),
                                        $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
                                    )),
                                )),
                            )),
                            $el('div', 'pg-hide-if-empty p-4 rounded-4 bg-body-tertiary mb-4', lang('About the event'), array(
                                $para(lang('About the event'), 'small fw-semibold text-body-secondary mb-2'),
                                $el('div', 'pg-field-value lh-lg', lang('Value'), array(), $bound($sample_details, 'details')),
                            )),
                            $el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
                                $icon('bi-chat-left-text', 'text-primary mt-1'),
                                $span(lang('Another date, a question about the offer or something to add? Write to us below; we answer here and by e-mail.'), '', lang('Text')),
                            )),
                        ), $show_when('record_found')),
                    ), '', '8'),
                ), '', 'center'),
            ), 'py-5'),
        )),
    ),

    // The messages under an enquiry: the page's own Comments widget, shown
    // only to whoever may read the enquiry above it. Oldest first, all on
    // one page. No Reply, Share or follow box: the promoter is e-mailed
    // every answer and the band every message.
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
            'empty'     => lang('No messages yet. Our booking agent\'s answer comes here; you can write to us about the show too.'),
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

    'my_bookings_list' => $enquiry_list('my_bookings', false,
        lang('No booking enquiries yet. Tell us the date, the city and the stage.')),

    'staff_bookings_list' => $enquiry_list('staff_bookings', true, lang('No booking enquiries yet.')),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_booking_admin' => $detail_widget('email_booking_new', lang('The booking enquiry could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new booking enquiry')),
            $mail_para(lang('It came from the booking form. Answer under the enquiry on its page with an offer or a question, and set the status; what you write there reaches the promoter by e-mail.')),
            $mail_enquiry(),
            $mail_promoter(),
            $mail_button(lang('Open the enquiry'), '{{page:booking_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_booking_client' => $detail_widget('email_booking_received', lang('The booking enquiry could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your booking enquiry')),
            $mail_greeting(),
            $mail_para(lang('Thank you. Our booking agent looks at the date and the city and answers within two working days, on the enquiry\'s page and by e-mail.')),
            $mail_enquiry(),
            $mail_para(lang('Another date or a question? Write to us on the enquiry\'s page; everything about the show stays there.')),
            $mail_button(lang('Open my enquiry'), '{{page:booking_view}}?r=^^reference_code^^'),
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

return array('pages' => $press_pages, 'widgets' => $press_widgets);
