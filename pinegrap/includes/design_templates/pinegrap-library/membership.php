<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Public Library", its library card and reservation
 * requests: the join page (the Membership form), the request's own page
 * with its messages, My Library Card and the staff's list; the item
 * reservation (the Reservations form), its own page with its messages, My
 * Reserved Titles and the staff's list; the e-mails of both requests.
 * Included after parts.php, mail.php, catalogue.php and events.php in the
 * same scope (it uses the request pieces of events.php); returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * A library card is a request: a record of the Membership form with the
 * reader's birth date, address, home branch, the kind of card and a proof
 * of address kept in the private folder. The staff check it, set its
 * office-use-only Status and answer under it; the card itself is issued
 * at the desk. A reader without an account gets one (auto-registration),
 * so the request's page and My Library Card show it at once.
 *
 * A reservation is a request, never a hold in a library system: a record
 * of the Reservations form. The title starts with what the title page
 * sends (?title=, the title's own words): a text field, so a title the
 * staff add later, or one not in the catalogue, is taken as well. The
 * staff set it aside at the pick-up branch, set its Status and tell the
 * reader under it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$membership_pages = array();

// The sample requests the canvas shows. The birth date is never a date in
// the sample: the field shows what it holds.
$sample_card_kind = $card_kinds[0];
$sample_home_branch = $branch_titles[0];
$sample_birth = lang('Date of birth');
$sample_address = lang('Mill Lane 8, Riverside');
$sample_membership_notes = lang('I moved here last month; my rental contract is attached as proof of address.');
$sample_title_key = 'harbour';
$sample_title = $catalogue_items[$sample_title_key]['title'];
$sample_card_number = 'LIB 004521';
$sample_pickup = $branch_titles[1];
$sample_reservation_notes = lang('The large print edition would be even better, if you have it.');

// ── Pages ───────────────────────────────────────────────────────────────

// The library card request. What is sent opens the request's own page.
$membership_pages[] = array(
    'key'              => 'join',
    'name'             => lang('join-the-library'),
    'folder'           => 'public',
    'title'            => lang('Join the Library'),
    'meta_description' => lang('Ask for a free library card online: send your details and proof of address, and collect your card at the branch you choose.'),
    'form'             => array(
        'form_name'            => lang('Membership'),
        'confirmation_message' => lang('Thank you, your library card request has reached us. We check it within two working days.'),
        'confirmation_page_id' => '{{tab:membership_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New library card request: ^^last_name^^'),
        'notify_page_id'       => '{{tab:email_membership_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your library card request'),
        'confirm_page_id'      => '{{tab:email_membership_received}}',
    ),
    'tree'             => $request_page(lang('Join the Library'),
        lang('A library card is free for everyone who lives, works or studies in town. Send your details and a proof of address; we tell you when your card is ready to collect.'),
        'membership_form',
        $request_aside(array(
            lang('Your request opens its own page, and an account for you if you have none.'),
            lang('We check your details within two working days and write to you there.'),
            lang('Collect your card at your home branch: bring the same proof of address.'),
            lang('Your card works in every branch and opens the eBooks and online services.'),
        ), lang('Find a Branch'), '{{page:visit}}')),
);

// One library card request: the request, its status and the messages.
$membership_pages[] = $request_view('membership_view', lang('library-card-request'), lang('Library Card Request'),
    'membership_detail', 'membership_messages', lang('There is a new message about your library card'));

// My Library Card: the reader's own requests (for the staff, everybody's).
$membership_pages[] = array(
    'key'     => 'my_memberships',
    'name'    => lang('my-library-card'),
    'folder'  => 'registration',
    'title'   => lang('My Library Card'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('My Library Card'), lang('Your library card requests, their status and the library\'s answers, newest first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('my_memberships_list')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-3 bg-body-tertiary', lang('With Your Card'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-person-vcard', 'text-primary'),
                                $span(lang('Your card is ready?'), '', lang('Text')),
                            )),
                            $para(lang('Reserve a title from the catalogue and collect it at the branch you choose.'), 'small text-body-secondary mb-3'),
                            $link(lang('Search the Catalogue'), '{{page:catalogue}}', 'btn btn-sm btn-primary px-3'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

$membership_pages[] = $staff_list_page('staff_memberships', lang('all-library-card-requests'), lang('Library Card Requests'),
    lang('Every library card request, newest first: check the proof of address, set the status and write to the reader under each.'),
    'staff_memberships_list');

// The reservation. What is sent opens the request's own page.
$membership_pages[] = array(
    'key'              => 'reserve',
    'name'             => lang('reserve-a-title'),
    'folder'           => 'public',
    'title'            => lang('Reserve a Title'),
    'meta_description' => lang('Reserve a book, film or audiobook with your library card and collect it at the branch you choose: we tell you when it is ready.'),
    'form'             => array(
        'form_name'            => lang('Title Reservations'),
        'confirmation_message' => lang('Thank you, your reservation has reached us. We tell you when the title is ready to collect.'),
        'confirmation_page_id' => '{{tab:reservation_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New reservation: ^^title^^'),
        'notify_page_id'       => '{{tab:email_reservation_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your reservation'),
        'confirm_page_id'      => '{{tab:email_reservation_received}}',
    ),
    'tree'             => $request_page(lang('Reserve a Title'),
        lang('Tell us the title and where you would like to collect it. Reserving is free; we keep the title for you for a week once it is ready.'),
        'reservation_form',
        $request_aside(array(
            lang('Your reservation opens its own page, and an account for you if you have none.'),
            lang('We set the title aside at your pick-up branch and tell you there and by e-mail when it is ready.'),
            lang('Collect it within a week with your library card.'),
            lang('Changed your mind? Write to us on the reservation\'s page.'),
        ), lang('Search the Catalogue'), '{{page:catalogue}}')),
);

// One reservation: the request, its status and the messages.
$membership_pages[] = $request_view('reservation_view', lang('title-reservation'), lang('Title Reservation'),
    'reservation_detail', 'reservation_messages', lang('There is a new message about your reservation'));

// My Reserved Titles: the reader's own reservations (for the staff,
// everybody's).
$membership_pages[] = array(
    'key'     => 'my_reservations',
    'name'    => lang('my-reserved-titles'),
    'folder'  => 'registration',
    'title'   => lang('My Reserved Titles'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('My Reserved Titles'), lang('The titles you reserved, whether they are ready to collect and the library\'s answers, newest first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('my_reservations_list')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-3 bg-body-tertiary', lang('New Request'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-bookmark-plus', 'text-primary'),
                                $span(lang('Another title?'), '', lang('Text')),
                            )),
                            $para(lang('Find it in the catalogue and reserve it from its page; your details are filled in for you.'), 'small text-body-secondary mb-3'),
                            $link(lang('Search the Catalogue'), '{{page:catalogue}}', 'btn btn-sm btn-primary px-3'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

$membership_pages[] = $staff_list_page('staff_reservations', lang('all-title-reservations'), lang('Title Reservations'),
    lang('Every reservation, newest first: set the title aside, set the status and tell the reader under each.'),
    'staff_reservations_list');

// The e-mails: what the library receives and the reply to the reader, for
// each request.
$membership_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_membership_new',
    'name'  => lang('email-new-library-card-request'),
    'title' => lang('New Library Card Request E-mail'),
    'tree'  => $mail_page(array($widget('email_membership_admin'))),
));
$membership_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_membership_received',
    'name'  => lang('email-library-card-request-received'),
    'title' => lang('Library Card Request Received E-mail'),
    'tree'  => $mail_page(array($widget('email_membership_client'))),
));
$membership_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reservation_new',
    'name'  => lang('email-new-title-reservation'),
    'title' => lang('New Title Reservation E-mail'),
    'tree'  => $mail_page(array($widget('email_reservation_admin'))),
));
$membership_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reservation_received',
    'name'  => lang('email-title-reservation-received'),
    'title' => lang('Title Reservation Received E-mail'),
    'tree'  => $mail_page(array($widget('email_reservation_client'))),
));

// ── Widgets ─────────────────────────────────────────────────────────────

// The card request's details, for its e-mails. The proof of address stays
// out of the e-mail: it is opened from the request's page.
$mail_membership = function () use ($mail_box, $mail_line, $span, $bind, $sample_card_kind, $sample_home_branch, $sample_birth, $sample_address) {
    return $mail_box(array(
        $mail_line(lang('Card'), array($span($sample_card_kind, '', lang('Card'), $bind('card_kind')))),
        $mail_line(lang('Home Branch'), array($span($sample_home_branch, '', lang('Home Branch'), $bind('home_branch')))),
        $mail_line(lang('Date of Birth'), array($span($sample_birth, '', lang('Date of Birth'), $bind('birth_date')))),
        $mail_line(lang('Address'), array($span($sample_address, '', lang('Address'), $bind('address')))),
    ), lang('Library Card Request'));
};
// The reservation's title and pick-up branch, for its e-mails.
$mail_reservation = function () use ($mail_box, $mail_line, $span, $bind, $sample_title, $sample_card_number, $sample_pickup) {
    return $mail_box(array(
        $mail_line(lang('Title'), array($span($sample_title, '', lang('Title'), $bind('title')))),
        $mail_line(lang('Library Card Number'), array($span($sample_card_number, '', lang('Library Card Number'), $bind('card_number')))),
        $mail_line(lang('Pick-up Branch'), array($span($sample_pickup, '', lang('Pick-up Branch'), $bind('pickup_branch')))),
    ), lang('Title Reservation'));
};
$membership_row = function ($staff = false) use ($el, $icon, $span, $bind, $request_row, $sample_card_kind, $sample_home_branch, $membership_statuses) {
    return $request_row($staff, $sample_card_kind, 'card_kind', lang('Card'), $membership_statuses[0], array(
        $el('span', 'd-inline-flex align-items-center gap-1', lang('Home Branch'), array(
            $icon('bi-geo-alt'), $span($sample_home_branch, '', lang('Home Branch'), $bind('home_branch')),
        )),
    ));
};
$reservation_row = function ($staff = false) use ($el, $icon, $span, $bind, $request_row, $sample_title, $sample_pickup, $reservation_statuses) {
    return $request_row($staff, $sample_title, 'title', lang('Title'), $reservation_statuses[0], array(
        $el('span', 'd-inline-flex align-items-center gap-1', lang('Pick-up Branch'), array(
            $icon('bi-geo-alt'), $span($sample_pickup, '', lang('Pick-up Branch'), $bind('pickup_branch')),
        )),
    ));
};
$membership_missing = lang('This library card request could not be found, or it is not yours to see.');
$reservation_missing = lang('This reservation could not be found, or it is not yours to see.');

$membership_widgets = array(

    // The library card request: the fields are the page's form. The proof
    // of address goes to the private folder, opened only by the staff and
    // the reader. The Status is the library's (office use only): every
    // request starts as Request received.
    'membership_form' => array(
        'page'   => 'join',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('Your card')),
            $row(array(
                $col(array($field('lib-mb-card', lang('Card'), $select('lib-mb-card', 'card_kind', '', $card_kinds, true, $card_kinds[0]))), '6'),
                $col(array($field('lib-mb-branch', lang('Home Branch'), $select('lib-mb-branch', 'home_branch', '', $branch_titles, true, $branch_titles[0]), 'mb-3',
                    lang('Where you will collect your card.'))), '6'),
            ), '3'),
            $form_step(2, lang('About you')),
            $contact_fields('lib-mb'),
            $row(array(
                $col(array($field('lib-mb-birth', lang('Date of Birth'), $input('lib-mb-birth', 'date', 'birth_date', 'bday'))), '6'),
            ), '3'),
            $field('lib-mb-address', lang('Address'), $textarea('lib-mb-address', 'address', 2)),
            $field('lib-mb-proof', lang('Proof of Address'), $file('lib-mb-proof', 'proof_of_address', 'private'), 'mb-3',
                lang('A recent bill, a rental contract or an official letter with your name and address (PDF or a picture).')),
            $field('lib-mb-notes', lang('Notes'), $textarea('lib-mb-notes', 'notes', 3, false), 'mb-3',
                lang('Anything we should know, such as a card for a child you look after.')),
            $radios('lib-mb-reply', 'reply_by', lang('Reply by'), $contact_choices, $contact_choices[0][0]),
            $field('lib-mb-status', lang('Status'), $select('lib-mb-status', 'status', '', $membership_statuses, false, $membership_statuses[0], array('office_use_only' => true))),
            $agree('lib-mb-consent', 'consent', lang('I agree that the library keeps these details and my proof of address to issue my card.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Request'), 'btn btn-primary btn-lg px-4'),
                $para(lang('A library card is free. We check your request within two working days.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One library card request; the library's answers follow as comments.
    'membership_detail' => $detail_widget('membership_view', 'join', $membership_missing, 'submitter_only') + array(
        'tree' => $request_detail(lang('My Library Card'), 'my_memberships', $membership_missing, lang('Library card request not found'),
            lang('Library Card Request'), $sample_card_kind, 'card_kind', $membership_statuses[0],
            lang('Thank you, your request has reached us. We check it here within two working days; this page shows the status, and you can write to us below.'),
            array_merge(
                $detail(lang('Home Branch'), '', lang('Home Branch'), $bound($sample_home_branch, 'home_branch')),
                $detail(lang('Name'), '', lang('Name'), $visitor_name(), true),
                $detail(lang('Date of Birth'), '', lang('Date of Birth'), $bound($sample_birth, 'birth_date')),
                $detail(lang('Address'), '', lang('Address'), $bound($sample_address, 'address')),
                $detail(lang('Proof of Address'), 'text-break', lang('Proof of Address'), array(
                    $link('proof-of-address.pdf', '#', '', array(), array('target' => '_blank', 'rel' => 'noopener',
                        '_bindings' => array('href' => 'proof_of_address', 'text' => 'proof_of_address'))),
                ), true),
                $detail(lang('Reply by'), '', lang('Reply by'), $bound($contact_choices[0][1], 'reply_by')),
                $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
            ),
            $sample_membership_notes,
            lang('A question, or asked for more information? Write to us below; we answer here and by e-mail.')),
    ),

    'membership_messages' => $request_messages('membership_view',
        lang('No messages yet. The library\'s answer comes here; you can write to us about your card too.')),

    'my_memberships_list' => $request_list('my_memberships', 'join', 'membership_view', $membership_row, array(), '',
        lang('No library card requests yet. Join the library: the card is free.')),

    'staff_memberships_list' => $request_list('staff_memberships', 'join', 'membership_view', $membership_row,
        array('first_name', 'last_name', 'email', 'card_kind', 'home_branch', 'status'),
        lang('Search by name, e-mail, card, branch or status...'), lang('No library card requests yet.')),

    // The reservation: the fields are the page's form. The title starts
    // with what the title page sends (?title=). The Status is the
    // library's (office use only): every request starts as Request
    // received.
    'reservation_form' => array(
        'page'   => 'reserve',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The title')),
            $field('lib-rs-title', lang('Title'), $input('lib-rs-title', 'text', 'title', 'off', true, array('url_param' => 'title')), 'mb-3',
                lang('Filled in from the title\'s page; write the title and its author if it is empty.')),
            $row(array(
                $col(array($field('lib-rs-card', lang('Library Card Number'), $input('lib-rs-card', 'text', 'card_number', 'off'), 'mb-3',
                    lang('Printed under the barcode on your card.'))), '6'),
                $col(array($field('lib-rs-pickup', lang('Pick-up Branch'), $select('lib-rs-pickup', 'pickup_branch', '', $branch_titles, true, $branch_titles[0]))), '6'),
            ), '3'),
            $field('lib-rs-notes', lang('Notes'), $textarea('lib-rs-notes', 'notes', 3, false), 'mb-3',
                lang('Another edition or format that would do, or a date after which you no longer need it.')),
            $form_step(2, lang('How to reach you')),
            $contact_fields('lib-rs'),
            $radios('lib-rs-reply', 'reply_by', lang('Reply by'), $contact_choices, $contact_choices[0][0]),
            $field('lib-rs-status', lang('Status'), $select('lib-rs-status', 'status', '', $reservation_statuses, false, $reservation_statuses[0], array('office_use_only' => true))),
            $agree('lib-rs-consent', 'consent', lang('I agree that the library keeps these details to set the title aside for me.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Reserve'), 'btn btn-primary btn-lg px-4'),
                $para(lang('Reserving is free. We tell you when the title is ready to collect.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One reservation; the library's answers follow as comments.
    'reservation_detail' => $detail_widget('reservation_view', 'reserve', $reservation_missing, 'submitter_only') + array(
        'tree' => $request_detail(lang('My Reserved Titles'), 'my_reservations', $reservation_missing, lang('Reservation not found'),
            lang('Title Reservation'), $sample_title, 'title', $reservation_statuses[0],
            lang('Thank you, your reservation has reached us. This page shows when the title is ready to collect, and you can write to us below.'),
            array_merge(
                $detail(lang('Library Card Number'), '', lang('Library Card Number'), $bound($sample_card_number, 'card_number')),
                $detail(lang('Pick-up Branch'), '', lang('Pick-up Branch'), $bound($sample_pickup, 'pickup_branch')),
                $detail(lang('Reply by'), '', lang('Reply by'), $bound($contact_choices[0][1], 'reply_by')),
                $detail(lang('Name'), '', lang('Name'), $visitor_name(), true),
                $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
            ),
            $sample_reservation_notes,
            lang('Another branch, or no longer need it? Write to us below; we answer here and by e-mail.')),
    ),

    'reservation_messages' => $request_messages('reservation_view',
        lang('No messages yet. The library\'s answer comes here; you can write to us about the title too.')),

    'my_reservations_list' => $request_list('my_reservations', 'reserve', 'reservation_view', $reservation_row, array(), '',
        lang('No reservations yet. Find a title in the catalogue and reserve it from its page.')),

    'staff_reservations_list' => $request_list('staff_reservations', 'reserve', 'reservation_view', $reservation_row,
        array('first_name', 'last_name', 'email', 'title', 'card_number', 'pickup_branch', 'status'),
        lang('Search by name, e-mail, title, card number or status...'), lang('No reservations yet.')),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_membership_admin' => $detail_widget('email_membership_new', 'join', lang('The library card request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new library card request')),
            $mail_para(lang('It came from the join form. Check the proof of address on the request\'s page, set the status and write to the reader there; what you write reaches them by e-mail.')),
            $mail_membership(),
            $mail_sender($sample_membership_notes),
            $mail_button(lang('Open the request'), '{{page:membership_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_membership_client' => $detail_widget('email_membership_received', 'join', lang('The library card request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your library card request')),
            $mail_greeting(),
            $mail_para(lang('Thank you. We check your details within two working days and tell you on the request\'s page and by e-mail when your card is ready to collect.')),
            $mail_membership(),
            $mail_para(lang('Bring the same proof of address when you collect your card.')),
            $mail_button(lang('Open my request'), '{{page:membership_view}}?r=^^reference_code^^'),
            $mail_new_account(),
        )),
    ),
    'email_reservation_admin' => $detail_widget('email_reservation_new', 'reserve', lang('The reservation could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new reservation')),
            $mail_para(lang('It came from the reservation form. Set the title aside at the pick-up branch, set the status and tell the reader on the reservation\'s page; what you write reaches them by e-mail.')),
            $mail_reservation(),
            $mail_sender($sample_reservation_notes),
            $mail_button(lang('Open the reservation'), '{{page:reservation_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_reservation_client' => $detail_widget('email_reservation_received', 'reserve', lang('The reservation could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your reservation')),
            $mail_greeting(),
            $mail_para(lang('Thank you. We tell you on the reservation\'s page and by e-mail when the title is ready to collect; we keep it for you for a week.')),
            $mail_reservation(),
            $mail_para(lang('Changed your mind? Write to us on the reservation\'s page so the title goes to the next reader.')),
            $mail_button(lang('Open my reservation'), '{{page:reservation_view}}?r=^^reference_code^^'),
            $mail_new_account(),
        )),
    ),
);

return array('pages' => $membership_pages, 'widgets' => $membership_widgets);
