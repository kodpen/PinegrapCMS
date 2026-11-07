<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Restaurant", its private dining: the Private Dining
 * page with the rooms and the occasions, the enquiry, the enquiry's own
 * page with its messages, My Enquiries and the staff's list, the two
 * e-mails of the enquiry form, and the widgets that draw them. Included
 * after parts.php, mail.php, menu.php and reservations.php in the same
 * scope (it builds on the request pieces of reservations.php); returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * An enquiry is a record of the Private Dining Enquiries form, as a table
 * reservation is a record of its own form: a request, never a held room. A
 * visitor without an account gets one (auto-registration), so the
 * enquiry's page and My Enquiries show it at once. Its Status is an
 * office-use-only field the events team changes in the control panel; the
 * messages under it are the page's comments, mailed to the guest and to
 * the restaurant.
 *
 * The enquiry's Room and Occasion start with the choice of the card that
 * sent the visitor (?room=, ?kind=, each the answer's own words). The
 * seating plan or draft menu a guest sends is kept in the template's
 * private folder: the enquiry's page links it for whoever may read the
 * record.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$private_pages = array();

// The sample enquiry the canvas shows.
$sample_room = $room_choices[1];
$sample_kind = $enquiry_kinds[1];
$sample_party = '24';
$sample_enquiry_notes = lang('A wedding dinner for about twenty-four guests on a summer evening: drinks in the herb garden, then a long table and the Seasons Menu.');
$enquiry_missing = lang('This enquiry could not be found, or it is not yours to see.');

// What the restaurant hosts, each card opening the enquiry with the
// occasion chosen (?kind=, one of $enquiry_kinds).
$private_occasions = array(
    array('icon' => 'bi-balloon-heart', 'kind' => $enquiry_kinds[0],
        'text' => lang('A birthday, an anniversary or the whole family round one table, with a cake from our pastry kitchen.')),
    array('icon' => 'bi-heart', 'kind' => $enquiry_kinds[1],
        'text' => lang('Dinner after the ceremony: the Seasons Menu for your guests, speeches between the courses and dancing on the terrace.')),
    array('icon' => 'bi-briefcase', 'kind' => $enquiry_kinds[2],
        'text' => lang('A team dinner, a client evening or a launch, with a menu in your company\'s name and a screen if you need one.')),
    array('icon' => 'bi-cup-straw', 'kind' => $enquiry_kinds[3],
        'text' => lang('Standing in the garden with small plates from the wood oven and wines chosen by our sommelier.')),
);
$private_occasion_card = function ($o) use ($el, $heading, $para, $link, $col, $icon_tile, $query) {
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm', lang('Occasion Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile($o['icon'], 'align-self-start mb-3'),
                $heading('h3', $o['kind'], 'prt-display h5 mb-2'),
                $para($o['text'], 'small text-body-secondary mb-4'),
                $link(lang('Start an enquiry'), '{{page:private_enquiry}}?' . $query(array('kind' => $o['kind'])), 'btn btn-sm btn-outline-primary mt-auto align-self-start'),
            )),
        )),
    ), '6', '3');
};

// ── Pages ───────────────────────────────────────────────────────────────

$room_cards = array();
foreach (array_keys($rooms) as $key) $room_cards[] = $room_card($key);
$occasion_cards = array();
foreach ($private_occasions as $o) $occasion_cards[] = $private_occasion_card($o);

// Private Dining: the rooms, the occasions, how it works and the questions
// asked before an enquiry.
$private_pages[] = array(
    'key'              => 'private_dining',
    'name'             => lang('private-dining'),
    'folder'           => 'public',
    'title'            => lang('Private Dining'),
    'meta_description' => lang('The wine cellar, the garden room or the whole restaurant for your birthday, wedding dinner or company evening, with a menu of your own.'),
    'tree'             => $page(array(
        $page_header(lang('Private Dining'),
            lang('The wine cellar, the garden room or the whole restaurant, with a menu the chef writes for your evening. Tell us the day and the guests and we plan it with you.'),
            array($el('div', 'd-flex flex-wrap gap-2 mt-4', lang('Buttons'), array(
                $link(lang('Start an Enquiry'), '{{page:private_enquiry}}', 'btn btn-primary px-4'),
                $link(lang('My Enquiries'), '{{page:my_enquiries}}', 'btn btn-outline-primary px-4'),
            )))),
        $el('section', 'py-5', lang('Private Rooms'), array(
            $container(array(
                $section_header(lang('The rooms'), lang('Three rooms for your evening')),
                $row($room_cards, '4'),
            )),
        ), $searchable('rt-private-rooms', lang('private room, wine cellar, garden room, group, capacity, seats'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Occasions'), array(
            $container(array(
                $section_header(lang('What we host'), lang('One party at a time, cooked for you')),
                $row($occasion_cards, '4'),
            )),
        ), $searchable('rt-private-occasions', lang('birthday, wedding dinner, company dinner, reception, party, celebration'))),
        $el('section', 'py-5', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $section_header(lang('How it works'), lang('From the first question to the evening')),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), array(
                            $step(1, lang('Tell us about it'), lang('The occasion, the date, how many guests and the room you like, in a short enquiry.')),
                            $step(2, lang('Get a proposal'), lang('Within two working days: the room, a menu written for you, the wines and the price, on your enquiry\'s page.')),
                            $step(3, lang('Taste and confirm'), lang('We hold the date while you decide; the menu and the wines are settled with you under your enquiry.')),
                        )),
                    ), '', '6'),
                    $col(array(
                        $section_header(lang('Good to know'), lang('Before you ask')),
                        $el('div', 'accordion', lang('Questions'), array(
                            $faq(1, lang('Is there a minimum spend?'),
                                lang('Each room has a minimum for the evening, written in the proposal; the menu and the wines count towards it.'), lang('minimum spend, price, cost'), 'rt-private-faq'),
                            $faq(2, lang('Can the kitchen cook for every diet at the table?'),
                                lang('Yes. Tell us the allergies and diets of your guests with the enquiry and the chef writes their plates into the menu.'), lang('allergy, diet, vegan, vegetarian, gluten'), 'rt-private-faq'),
                            $faq(3, lang('Can we bring a cake, flowers or music?'),
                                lang('Of course. The garden room has power for a band until midnight, and our pastry kitchen can bake the cake.'), lang('cake, flowers, music, band, decoration'), 'rt-private-faq'),
                        ), array('id' => 'rt-private-faq')),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('rt-private-how', lang('proposal, menu, minimum spend, tasting, deposit'))),
    )),
);

// The enquiry. What is sent opens the enquiry's own page.
$private_pages[] = array(
    'key'              => 'private_enquiry',
    'name'             => lang('private-dining-enquiry'),
    'folder'           => 'public',
    'title'            => lang('Private Dining Enquiry'),
    'meta_description' => lang('Ask about a private room for your dinner or reception: the events team answers within two working days with a menu and a price.'),
    'form'             => array(
        'form_name'            => lang('Private Dining Enquiries'),
        'confirmation_message' => lang('Thank you, your enquiry has reached the events team. We answer within two working days with a proposal.'),
        'confirmation_page_id' => '{{tab:enquiry_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New private dining enquiry: ^^event_kind^^'),
        'notify_page_id'       => '{{tab:email_enquiry_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your private dining enquiry'),
        'confirm_page_id'      => '{{tab:email_enquiry_received}}',
    ),
    'tree'             => $request_page(lang('Private Dining Enquiry'),
        lang('Tell us about the occasion, the date and your guests. The events team answers within two working days with a menu and a price.'),
        'enquiry_form',
        $request_aside(array(
            lang('Your enquiry opens its own page, and an account for you if you have none.'),
            lang('The events team answers within two working days with a proposal and the price.'),
            lang('We hold the date for you while you decide; questions and changes go under your enquiry.'),
        ), lang('See the private rooms'), '{{page:private_dining}}')),
);

// One enquiry: what was asked, its status and the messages under it.
$private_pages[] = $request_view('enquiry_view', lang('enquiry'), lang('Private Dining Enquiry'),
    'enquiry_detail', 'enquiry_messages', lang('There is a new message about your private dinner'));

// My Enquiries: the guest's own enquiries, and a card that starts another.
$private_pages[] = array(
    'key'     => 'my_enquiries',
    'name'    => lang('my-enquiries'),
    'folder'  => 'registration',
    'title'   => lang('My Enquiries'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('My Enquiries'), lang('Your private dining enquiries, their status and the events team\'s messages, newest first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('my_enquiries_list')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-3 bg-body-tertiary', lang('New Enquiry'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-calendar-heart', 'text-primary'),
                                $span(lang('Another occasion?'), '', lang('Text')),
                            )),
                            $para(lang('Start a new enquiry; your details are filled in for you.'), 'small text-body-secondary mb-3'),
                            $link(lang('Start an Enquiry'), '{{page:private_enquiry}}', 'btn btn-sm btn-primary px-3'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

$private_pages[] = $staff_list_page('staff_enquiries', lang('all-private-dining-enquiries'), lang('Private Dining Enquiries'),
    lang('Every private dining enquiry, newest first: send the proposal, set the status and answer under each.'),
    'staff_enquiries_list');

// The e-mails of the enquiry form: what the restaurant receives and the
// reply to the guest. A message under an enquiry goes out with the new
// message e-mail (email_reply) the record page names.
$private_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_enquiry_new',
    'name'  => lang('email-new-private-dining-enquiry'),
    'title' => lang('New Private Dining Enquiry E-mail'),
    'tree'  => $mail_page(array($widget('email_enquiry_admin'))),
));
$private_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_enquiry_received',
    'name'  => lang('email-private-dining-enquiry-received'),
    'title' => lang('Private Dining Enquiry Received E-mail'),
    'tree'  => $mail_page(array($widget('email_enquiry_client'))),
));

// ── Widget pieces ───────────────────────────────────────────────────────

// An enquiry in a list: the occasion, the status, the date, the room and
// the guests.
$enquiry_row = function ($staff = false) use ($el, $icon, $span, $bind, $request_row, $sample_kind, $sample_room, $sample_party, $enquiry_statuses) {
    $item = function ($icon_name, $label, $sample, $field) use ($el, $span, $icon, $bind) {
        return $el('span', 'd-inline-flex align-items-center gap-1', $label, array($icon($icon_name), $span($sample, '', $label, $bind($field))));
    };
    return $request_row($staff, $sample_kind, 'event_kind', lang('Occasion'), $enquiry_statuses[0], array(
        $item('bi-calendar-event', lang('Date'), lang('Date'), 'event_date'),
        $item('bi-door-open', lang('Room'), $sample_room, 'room'),
        $item('bi-people', lang('Guests'), $sample_party, 'guest_count'),
    ));
};

// The lines of an enquiry, for its e-mails.
$mail_enquiry = function () use ($mail_box, $mail_line, $span, $bind, $sample_kind, $sample_room, $sample_party, $tasting_choices) {
    return $mail_box(array(
        $mail_line(lang('Occasion'), array($span($sample_kind, '', lang('Occasion'), $bind('event_kind')))),
        $mail_line(lang('Date'), array($span(lang('Date'), '', lang('Date'), $bind('event_date')))),
        $mail_line(lang('Other dates that suit you'), array($span(lang('Any Friday in summer'), '', lang('Other Dates'), $bind('other_dates')))),
        $mail_line(lang('Room'), array($span($sample_room, '', lang('Room'), $bind('room')))),
        $mail_line(lang('Guests'), array($span($sample_party, '', lang('Guests'), $bind('guest_count')))),
        $mail_line(lang('Menu'), array($span($tasting_choices[1], '', lang('Menu'), $bind('tasting')))),
        $mail_line(lang('Contact by'), array($span(lang('E-mail'), '', lang('Contact by'), $bind('contact_method')))),
    ), lang('Enquiry'));
};

$private_widgets = array(

    // The enquiry: the fields are the page's form. The Occasion and the
    // Room start with the choice of the card that sent the visitor (?kind=,
    // ?room=). The Status is the events team's (office use only): every
    // enquiry starts as Enquiry received.
    'enquiry_form' => array(
        'page'   => 'private_enquiry',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The occasion')),
            $row(array(
                $col(array($field('rt-pd-kind', lang('Occasion'), $select('rt-pd-kind', 'event_kind', lang('Choose an occasion…'), $enquiry_kinds, true, '', array('url_param' => 'kind')))), '6'),
                $col(array($field('rt-pd-room', lang('Room'), $select('rt-pd-room', 'room', '', array_merge(array(lang('Not sure yet')), $room_choices), true, lang('Not sure yet'), array('url_param' => 'room')))), '6'),
                $col(array($field('rt-pd-date', lang('Date'), $input('rt-pd-date', 'date', 'event_date', 'off', true, $not_before_today))), '6'),
                $col(array($field('rt-pd-guests', lang('Guests'), $input('rt-pd-guests', 'number', 'guest_count', 'off', true, array('kind' => 'number'), array($attr('min', '2'), $attr('step', '1'))))), '6'),
            ), '3'),
            $field('rt-pd-other-dates', lang('Other dates that suit you'), $input('rt-pd-other-dates', 'text', 'other_dates', 'off', false), 'mb-3',
                lang('If the day can move, the dates or the season that would also work.')),
            $field('rt-pd-tasting', lang('Menu'), $select('rt-pd-tasting', 'tasting', '', $tasting_choices, false, $tasting_choices[0])),
            $field('rt-pd-details', lang('About the evening'), $textarea('rt-pd-details', 'notes', 4, false), 'mb-3',
                lang('The programme of the evening, allergies and diets of your guests, music, flowers or anything we should know.')),
            $field('rt-pd-documents', lang('Seating plan or menu ideas'), $file('rt-pd-documents', 'documents', 'private'), 'mb-3',
                lang('A seating plan, a draft menu or a mood board (PDF, Word or a picture).')),
            $form_step(2, lang('How to reach you')),
            $radios('rt-pd-contact', 'contact_method', lang('Best way to reach you'), $contact_choices, lang('E-mail')),
            $contact_fields('rt-pd'),
            $field('rt-pd-company', lang('Company'), $input('rt-pd-company', 'text', 'company', 'organization', false, array('contact_field' => 'company')), 'mb-3',
                lang('For a company dinner; it goes on the proposal.')),
            $field('rt-pd-status', lang('Status'), $select('rt-pd-status', 'status', '', $enquiry_statuses, false, $enquiry_statuses[0], array('office_use_only' => true))),
            $agree('rt-pd-consent', 'consent', lang('I agree that the restaurant keeps these details, my guests\' allergies among them, to plan the evening and answer me, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Enquiry'), 'btn btn-primary btn-lg px-4'),
                $para(lang('The events team answers within two working days.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One enquiry; the proposal and the messages follow as comments.
    'enquiry_detail' => $detail_widget('enquiry_view', 'private_enquiry', $enquiry_missing, 'submitter_only') + array(
        'tree' => $request_detail(lang('My Enquiries'), 'my_enquiries', $enquiry_missing, lang('Enquiry not found'),
            lang('Private Dining Enquiry'), $sample_kind, 'event_kind', $enquiry_statuses[0],
            lang('Thank you, your enquiry has reached the events team. We answer within two working days with a proposal; this page shows the status, and you can write to us below.'),
            array_merge(
                $detail(lang('Date'), '', lang('Date'), $bound(lang('Date'), 'event_date')),
                $detail(lang('Other dates that suit you'), 'pg-hide-if-empty', lang('Other Dates'), $bound(lang('Any Friday in summer'), 'other_dates')),
                $detail(lang('Room'), '', lang('Room'), $bound($sample_room, 'room')),
                $detail(lang('Guests'), '', lang('Guests'), $bound($sample_party, 'guest_count')),
                $detail(lang('Menu'), '', lang('Menu'), $bound($tasting_choices[1], 'tasting')),
                $detail(lang('Best way to reach you'), '', lang('Contact by'), $bound(lang('E-mail'), 'contact_method')),
                $detail(lang('Name'), '', lang('Name'), $guest_name(), true),
                $detail(lang('Company'), 'pg-hide-if-empty', lang('Company'), $bound(lang('Sample Company'), 'company')),
                $detail(lang('Seating plan or menu ideas'), 'pg-hide-if-empty text-break', lang('Documents'), array(
                    $link('seating-plan.pdf', '#', '', array(), array('target' => '_blank', 'rel' => 'noopener',
                        '_bindings' => array('href' => 'documents', 'text' => 'documents'))),
                ), true),
                $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
            ),
            $sample_enquiry_notes,
            lang('A change of date, more guests or a question? Write to the events team below; the proposal comes here too, and every answer by e-mail.')),
    ),

    'enquiry_messages' => $request_messages('enquiry_view',
        lang('No messages yet. The proposal comes here; you can write to the events team about your evening too.')),

    'my_enquiries_list' => $request_list('my_enquiries', 'private_enquiry', 'enquiry_view', $enquiry_row, array(), '',
        lang('No private dining enquiries yet. Tell us about your evening and the events team answers within two working days.')),

    'staff_enquiries_list' => $request_list('staff_enquiries', 'private_enquiry', 'enquiry_view', $enquiry_row,
        array('first_name', 'last_name', 'company', 'event_kind', 'room', 'event_date', 'status'),
        lang('Search by guest, company, occasion, room, date or status...'), lang('No private dining enquiries yet.')),

    // The e-mail pages' widgets: drawn for the enquiry just sent.
    'email_enquiry_admin' => $detail_widget('email_enquiry_new', 'private_enquiry', lang('The enquiry could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new private dining enquiry')),
            $mail_para(lang('It came from the private dining form. Check the date and the room, send the proposal under the enquiry and set its status; what you write there reaches the guest by e-mail.')),
            $mail_enquiry(),
            $mail_sender($sample_enquiry_notes),
            $mail_button(lang('Open the enquiry'), '{{page:enquiry_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_enquiry_client' => $detail_widget('email_enquiry_received', 'private_enquiry', lang('The enquiry could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your private dining enquiry')),
            $mail_greeting(),
            $mail_para(lang('Thank you for thinking of us for your evening. The events team answers within two working days with a menu and the price, and holds the date for you while you decide.')),
            $mail_enquiry(),
            $mail_para(lang('A change or a question? Write to us on the enquiry\'s page; everything about your evening stays there.')),
            $mail_button(lang('Open my enquiry'), '{{page:enquiry_view}}?r=^^reference_code^^'),
            $mail_new_account(),
        )),
    ),
);

return array('pages' => $private_pages, 'widgets' => $private_widgets);
